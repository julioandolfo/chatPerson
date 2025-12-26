<?php
/**
 * Service AIFallbackMonitoringService
 * Monitoramento e fallback de segurança para conversas travadas com IA
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\AIConversation;
use App\Models\Message;
use App\Models\Setting;
use App\Helpers\Database;
use App\Helpers\Logger;

class AIFallbackMonitoringService
{
    /**
     * Verificar e processar conversas travadas
     */
    public static function checkAndProcessStuckConversations(): array
    {
        $results = [
            'checked' => 0,
            'stuck_found' => 0,
            'reprocessed' => 0,
            'escalated' => 0,
            'ignored_closing' => 0,
            'errors' => []
        ];

        try {
            $settings = self::getSettings();
            
            if (!$settings['enabled']) {
                return $results;
            }

            // Buscar conversas travadas
            $conversations = self::getStuckConversations($settings);
            $results['checked'] = count($conversations);
            $results['stuck_found'] = count($conversations);

            foreach ($conversations as $conversation) {
                try {
                    $result = self::processStuckConversation($conversation['id'], $settings);
                    
                    if ($result['reprocessed']) {
                        $results['reprocessed']++;
                    }
                    
                    if ($result['escalated']) {
                        $results['escalated']++;
                    }
                    
                    if ($result['ignored_closing']) {
                        $results['ignored_closing']++;
                    }
                    
                    // Registrar métrica
                    self::recordMetric($conversation['id'], $result);
                    
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'conversation_id' => $conversation['id'],
                        'error' => $e->getMessage()
                    ];
                    Logger::error("AIFallbackMonitoringService - Erro ao processar conversa {$conversation['id']}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Logger::error("AIFallbackMonitoringService - Erro ao verificar conversas travadas: " . $e->getMessage());
            $results['errors'][] = ['error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Obter configurações do sistema
     */
    public static function getSettings(): array
    {
        return [
            'enabled' => (bool)(Setting::get('ai_fallback_enabled', true)),
            'check_interval_minutes' => (int)(Setting::get('ai_fallback_check_interval_minutes', 15)),
            'min_delay_minutes' => (int)(Setting::get('ai_fallback_min_delay_minutes', 5)),
            'max_delay_hours' => (int)(Setting::get('ai_fallback_max_delay_hours', 24)),
            'max_retries' => (int)(Setting::get('ai_fallback_max_retries', 3)),
            'escalate_after_hours' => (int)(Setting::get('ai_fallback_escalate_after_hours', 2)),
            'detect_closing_messages' => (bool)(Setting::get('ai_fallback_detect_closing_messages', true)),
            'use_ai_for_closing_detection' => (bool)(Setting::get('ai_fallback_use_ai_for_closing_detection', false)),
        ];
    }

    /**
     * Obter conversas travadas
     */
    private static function getStuckConversations(array $settings): array
    {
        $minDelayMinutes = $settings['min_delay_minutes'];
        $maxDelayHours = $settings['max_delay_hours'];
        
        $sql = "SELECT DISTINCT c.id, c.conversation_id, c.ai_agent_id, c.status as ai_status,
                       c.created_at as ai_started_at,
                       conv.status as conversation_status,
                       conv.agent_id as human_agent_id,
                       last_msg.id as last_message_id,
                       last_msg.content as last_message_content,
                       last_msg.created_at as last_message_at,
                       last_msg.sender_type as last_sender_type,
                       TIMESTAMPDIFF(MINUTE, last_msg.created_at, NOW()) as minutes_since_last_message
                FROM ai_conversations c
                INNER JOIN conversations conv ON conv.id = c.conversation_id
                INNER JOIN (
                    SELECT conversation_id, id, content, created_at, sender_type
                    FROM messages
                    WHERE id IN (
                        SELECT MAX(id) FROM messages GROUP BY conversation_id
                    )
                ) last_msg ON last_msg.conversation_id = conv.id
                WHERE c.status = 'active'
                AND conv.status IN ('open', 'pending')
                AND last_msg.sender_type = 'contact'
                AND TIMESTAMPDIFF(MINUTE, last_msg.created_at, NOW()) >= ?
                AND TIMESTAMPDIFF(HOUR, last_msg.created_at, NOW()) <= ?
                AND (conv.agent_id IS NULL OR conv.agent_id = 0)
                AND NOT EXISTS (
                    SELECT 1 FROM messages m2
                    WHERE m2.conversation_id = conv.id
                    AND m2.sender_type IN ('agent', 'ai')
                    AND m2.created_at > last_msg.created_at
                )
                ORDER BY last_msg.created_at ASC
                LIMIT 50";
        
        return Database::fetchAll($sql, [$minDelayMinutes, $maxDelayHours]);
    }

    /**
     * Processar conversa travada
     */
    private static function processStuckConversation(int $conversationId, array $settings): array
    {
        $result = [
            'reprocessed' => false,
            'escalated' => false,
            'ignored_closing' => false,
            'reason' => ''
        ];

        $conversation = Conversation::find($conversationId);
        if (!$conversation || $conversation['status'] === 'closed') {
            $result['reason'] = 'Conversa fechada';
            return $result;
        }

        // Buscar última mensagem do cliente
        $lastMessage = self::getLastClientMessage($conversationId);
        if (!$lastMessage) {
            $result['reason'] = 'Nenhuma mensagem do cliente encontrada';
            return $result;
        }

        // Verificar se é mensagem de encerramento
        if ($settings['detect_closing_messages']) {
            $isClosing = self::isClosingMessage($lastMessage['content'], $conversationId, $settings);
            if ($isClosing) {
                $result['ignored_closing'] = true;
                $result['reason'] = 'Mensagem de encerramento detectada';
                Logger::info("AIFallbackMonitoringService - Conversa {$conversationId}: Mensagem de encerramento detectada, ignorando");
                return $result;
            }
        }

        // Verificar tentativas anteriores
        $retryCount = self::getRetryCount($conversationId);
        if ($retryCount >= $settings['max_retries']) {
            // Escalar para humano
            $result['escalated'] = self::escalateToHuman($conversationId, $lastMessage);
            $result['reason'] = "Máximo de tentativas ({$retryCount}) excedido";
            return $result;
        }

        // Verificar se deve escalar por tempo
        $hoursSinceLastMessage = (time() - strtotime($lastMessage['created_at'])) / 3600;
        if ($hoursSinceLastMessage >= $settings['escalate_after_hours']) {
            $result['escalated'] = self::escalateToHuman($conversationId, $lastMessage);
            $result['reason'] = "Tempo excedido ({$hoursSinceLastMessage}h >= {$settings['escalate_after_hours']}h)";
            return $result;
        }

        // Validar contexto antes de reprocessar
        if (!self::shouldReprocess($conversationId, $settings)) {
            $result['reason'] = 'Contexto não válido para reprocessamento';
            return $result;
        }

        // Reprocessar mensagem
        try {
            $aiConversation = AIConversation::getByConversationId($conversationId);
            if (!$aiConversation || $aiConversation['status'] !== 'active') {
                $result['reason'] = 'AIConversation não encontrada ou inativa';
                return $result;
            }

            Logger::info("AIFallbackMonitoringService - Reprocessando conversa travada {$conversationId} (tentativa " . ($retryCount + 1) . ")");
            
            // Incrementar contador de retries
            self::incrementRetryCount($conversationId);
            
            // Reprocessar mensagem
            \App\Services\AIAgentService::processMessage(
                $conversationId,
                $aiConversation['ai_agent_id'],
                $lastMessage['content']
            );
            
            $result['reprocessed'] = true;
            $result['reason'] = 'Mensagem reprocessada com sucesso';
            
        } catch (\Exception $e) {
            Logger::error("AIFallbackMonitoringService - Erro ao reprocessar conversa {$conversationId}: " . $e->getMessage());
            $result['reason'] = 'Erro ao reprocessar: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Detectar se mensagem é de encerramento
     */
    public static function isClosingMessage(string $message, int $conversationId, array $settings): bool
    {
        $messageLower = mb_strtolower(trim($message));
        
        // Keywords simples
        $closingKeywords = [
            'obrigado', 'obrigada', 'obrigad', 'valeu',
            'tchau', 'até logo', 'até mais', 'até breve', 'até',
            'finalizado', 'resolvido', 'concluído', 'concluido',
            'ok', 'okay', 'entendi', 'perfeito', 'ótimo', 'otimo', 'show',
            'não preciso mais', 'nao preciso mais', 'já resolvi', 'ja resolvi',
            'tudo certo', 'sem mais', 'é isso', 'e isso', 'só isso', 'so isso',
            'nada mais', 'tudo bem', 'beleza', 'blz'
        ];
        
        foreach ($closingKeywords as $keyword) {
            if (stripos($messageLower, $keyword) !== false) {
                return true;
            }
        }
        
        // Se habilitado, usar IA para detecção mais precisa
        if ($settings['use_ai_for_closing_detection']) {
            return self::detectClosingWithAI($message, $conversationId);
        }
        
        return false;
    }

    /**
     * Detectar encerramento usando IA
     */
    private static function detectClosingWithAI(string $message, int $conversationId): bool
    {
        try {
            $apiKey = Setting::get('openai_api_key') ?: getenv('OPENAI_API_KEY');
            if (empty($apiKey)) {
                return false;
            }
            
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'Você é um classificador de intenções. Analise a mensagem e determine se o cliente está se despedindo ou encerrando a conversa. Retorne APENAS um JSON: {"is_closing": true ou false, "confidence": 0.0-1.0}'
                ],
                [
                    'role' => 'user',
                    'content' => "Mensagem do cliente: \"{$message}\"\n\nO cliente está se despedindo ou encerrando a conversa?"
                ]
            ];
            
            $payload = [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.1,
                'max_tokens' => 50,
                'response_format' => ['type' => 'json_object']
            ];
            
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 5
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return false;
            }
            
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '{}';
            $result = json_decode($content, true);
            
            $isClosing = (bool)($result['is_closing'] ?? false);
            $confidence = (float)($result['confidence'] ?? 0);
            
            // Só considerar se confiança >= 0.7
            return $isClosing && $confidence >= 0.7;
            
        } catch (\Exception $e) {
            Logger::error("AIFallbackMonitoringService - Erro ao detectar encerramento com IA: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar se deve reprocessar
     */
    private static function shouldReprocess(int $conversationId, array $settings): bool
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation || $conversation['status'] === 'closed') {
            return false;
        }
        
        $aiConversation = AIConversation::getByConversationId($conversationId);
        if (!$aiConversation || $aiConversation['status'] !== 'active') {
            return false;
        }
        
        // Verificar se há agente humano atribuído
        if (!empty($conversation['agent_id'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Escalar para humano
     */
    private static function escalateToHuman(int $conversationId, array $lastMessage): bool
    {
        try {
            Logger::info("AIFallbackMonitoringService - Escalando conversa {$conversationId} para humano");
            
            // Buscar supervisor ou agente disponível
            $supervisor = self::findAvailableSupervisor();
            
            if ($supervisor) {
                Conversation::update($conversationId, [
                    'agent_id' => $supervisor['id'],
                    'status' => 'pending'
                ]);
                
                // Enviar notificação
                \App\Services\NotificationService::create([
                    'user_id' => $supervisor['id'],
                    'type' => 'conversation_assigned',
                    'title' => 'Conversa escalada - IA não respondeu',
                    'message' => "Conversa #{$conversationId} foi escalada porque a IA não respondeu após múltiplas tentativas.",
                    'data' => json_encode(['conversation_id' => $conversationId])
                ]);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Logger::error("AIFallbackMonitoringService - Erro ao escalar conversa {$conversationId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar supervisor disponível
     */
    private static function findAvailableSupervisor(): ?array
    {
        $sql = "SELECT u.* FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE r.level <= 3
                AND u.status = 'active'
                ORDER BY r.level ASC, u.id ASC
                LIMIT 1";
        
        return Database::fetch($sql);
    }

    /**
     * Obter última mensagem do cliente
     */
    private static function getLastClientMessage(int $conversationId): ?array
    {
        $sql = "SELECT * FROM messages
                WHERE conversation_id = ? AND sender_type = 'contact'
                ORDER BY created_at DESC
                LIMIT 1";
        
        return Database::fetch($sql, [$conversationId]);
    }

    /**
     * Obter contador de retries
     */
    private static function getRetryCount(int $conversationId): int
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return 0;
        }
        
        $metadata = json_decode($conversation['metadata'] ?? '{}', true);
        return (int)($metadata['ai_fallback_retry_count'] ?? 0);
    }

    /**
     * Incrementar contador de retries
     */
    private static function incrementRetryCount(int $conversationId): void
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return;
        }
        
        $metadata = json_decode($conversation['metadata'] ?? '{}', true);
        $metadata['ai_fallback_retry_count'] = ($metadata['ai_fallback_retry_count'] ?? 0) + 1;
        $metadata['ai_fallback_last_retry_at'] = date('Y-m-d H:i:s');
        
        Conversation::update($conversationId, [
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Registrar métrica
     */
    private static function recordMetric(int $conversationId, array $result): void
    {
        try {
            $sql = "INSERT INTO ai_fallback_metrics 
                    (conversation_id, action, reason, created_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                    action = VALUES(action),
                    reason = VALUES(reason),
                    created_at = VALUES(created_at)";
            
            $action = 'ignored';
            if ($result['reprocessed']) {
                $action = 'reprocessed';
            } elseif ($result['escalated']) {
                $action = 'escalated';
            } elseif ($result['ignored_closing']) {
                $action = 'ignored_closing';
            }
            
            Database::execute($sql, [$conversationId, $action, $result['reason'] ?? '']);
        } catch (\Exception $e) {
            // Ignorar erro se tabela não existir ainda
            Logger::error("AIFallbackMonitoringService - Erro ao registrar métrica: " . $e->getMessage());
        }
    }

    /**
     * Obter estatísticas de fallback para dashboard
     */
    public static function getFallbackStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d 23:59:59');
        
        try {
            // Verificar se tabela existe
            $tableExists = Database::fetch("SHOW TABLES LIKE 'ai_fallback_metrics'");
            if (!$tableExists) {
                return self::getFallbackStatsFromMetadata($dateFrom, $dateTo);
            }
            
            // Total de conversas travadas detectadas
            $sqlTotal = "SELECT COUNT(DISTINCT conversation_id) as total
                        FROM ai_fallback_metrics
                        WHERE created_at >= ? AND created_at <= ?";
            $totalResult = Database::fetch($sqlTotal, [$dateFrom, $dateTo]);
            $totalStuck = (int)($totalResult['total'] ?? 0);
            
            // Por ação
            $sqlActions = "SELECT action, COUNT(*) as count
                          FROM ai_fallback_metrics
                          WHERE created_at >= ? AND created_at <= ?
                          GROUP BY action";
            $actionsResult = Database::fetchAll($sqlActions, [$dateFrom, $dateTo]);
            
            $actions = [
                'reprocessed' => 0,
                'escalated' => 0,
                'ignored_closing' => 0,
                'ignored' => 0
            ];
            
            foreach ($actionsResult as $row) {
                $actions[$row['action']] = (int)$row['count'];
            }
            
            // Conversas atualmente travadas
            $settings = self::getSettings();
            $currentlyStuck = self::getStuckConversations($settings);
            $currentlyStuckCount = count($currentlyStuck);
            
            return [
                'total_stuck' => $totalStuck,
                'reprocessed' => $actions['reprocessed'],
                'escalated' => $actions['escalated'],
                'ignored_closing' => $actions['ignored_closing'],
                'ignored' => $actions['ignored'],
                'currently_stuck' => $currentlyStuckCount,
                'reprocess_rate' => $totalStuck > 0 ? round(($actions['reprocessed'] / $totalStuck) * 100, 2) : 0,
                'escalation_rate' => $totalStuck > 0 ? round(($actions['escalated'] / $totalStuck) * 100, 2) : 0,
            ];
        } catch (\Exception $e) {
            Logger::error("AIFallbackMonitoringService - Erro ao obter estatísticas: " . $e->getMessage());
            return self::getFallbackStatsFromMetadata($dateFrom, $dateTo);
        }
    }

    /**
     * Obter estatísticas a partir de metadata (fallback se tabela não existir)
     */
    private static function getFallbackStatsFromMetadata(?string $dateFrom = null, ?string $dateTo = null): array
    {
        return [
            'total_stuck' => 0,
            'reprocessed' => 0,
            'escalated' => 0,
            'ignored_closing' => 0,
            'ignored' => 0,
            'currently_stuck' => 0,
            'reprocess_rate' => 0,
            'escalation_rate' => 0,
        ];
    }
}

