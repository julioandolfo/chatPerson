<?php
/**
 * Service SLAMonitoringService
 * Monitoramento e reatribuição automática baseada em SLA
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use App\Helpers\Database;

class SLAMonitoringService
{
    /**
     * Verificar e processar conversas com SLA próximo de vencer ou excedido
     */
    public static function checkAndProcessSLA(): array
    {
        $results = [
            'checked' => 0,
            'reassigned' => 0,
            'alerts' => 0,
            'errors' => []
        ];

        try {
            $settings = ConversationSettingsService::getSettings();
            
            if (!$settings['sla']['enable_sla_monitoring']) {
                return $results;
            }

            // Buscar conversas abertas que precisam verificação
            $conversations = self::getConversationsToCheck();
            $results['checked'] = count($conversations);

            foreach ($conversations as $conversation) {
                try {
                    $result = self::processConversationSLA($conversation['id']);
                    
                    if ($result['reassigned']) {
                        $results['reassigned']++;
                    }
                    
                    if ($result['alerted']) {
                        $results['alerts']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'conversation_id' => $conversation['id'],
                        'error' => $e->getMessage()
                    ];
                    error_log("Erro ao processar SLA da conversa {$conversation['id']}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao verificar SLA: " . $e->getMessage());
            $results['errors'][] = ['error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Obter conversas que precisam verificação de SLA
     * OTIMIZADO: Reduz limite e prioriza conversas mais críticas
     */
    private static function getConversationsToCheck(): array
    {
        // ✅ OTIMIZAÇÃO: Reduzido de 500 para 100 conversas por execução
        // ✅ Foca apenas em conversas abertas recentes (últimas 48h)
        // ✅ Ignora conversas com SLA pausado diretamente na query
        $sql = "SELECT c.id, c.status, c.priority, c.agent_id, c.department_id, 
                       c.funnel_id, c.funnel_stage_id, c.created_at,
                       c.sla_paused_at, c.sla_warning_sent, c.reassignment_count,
                       c.last_reassignment_at, c.metadata,
                       TIMESTAMPDIFF(MINUTE, c.created_at, NOW()) as minutes_open
                FROM conversations c
                WHERE c.status IN ('open', 'pending')
                AND c.sla_paused_at IS NULL
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
                ORDER BY 
                    CASE WHEN c.priority = 'urgent' THEN 1
                         WHEN c.priority = 'high' THEN 2
                         WHEN c.priority = 'normal' THEN 3
                         ELSE 4 END ASC,
                    minutes_open DESC
                LIMIT 100";
        
        return Database::fetchAll($sql);
    }

    /**
     * Processar SLA de uma conversa específica
     * ATUALIZADO: Agora implementa ongoing response, notificações únicas, incrementa contador
     */
    public static function processConversationSLA(int $conversationId): array
    {
        $result = [
            'reassigned' => false,
            'alerted' => false,
            'type' => null
        ];

        $conversation = Conversation::find($conversationId);
        if (!$conversation || $conversation['status'] === 'closed') {
            return $result;
        }
        
        // Ignorar conversas com SLA pausado
        if ($conversation['sla_paused_at']) {
            return $result;
        }

        $settings = ConversationSettingsService::getSettings();
        $slaConfig = \App\Models\SLARule::getSLAForConversation($conversation);
        
        // ========== VERIFICAR SLA DE PRIMEIRA RESPOSTA ==========
        $firstResponseOK = ConversationSettingsService::checkFirstResponseSLA($conversationId);
        
        if (!$firstResponseOK && $settings['sla']['auto_reassign_on_sla_breach']) {
            if (ConversationSettingsService::shouldReassign($conversationId)) {
                // Reatribuir conversa (EXCLUINDO agente atual para não reatribuir para o mesmo)
                $currentAgentId = $conversation['agent_id'] ?? null;
                
                $newAgentId = ConversationSettingsService::autoAssignConversation(
                    $conversationId,
                    $conversation['department_id'] ?? null,
                    $conversation['funnel_id'] ?? null,
                    $conversation['funnel_stage_id'] ?? null,
                    $currentAgentId // Excluir agente atual
                );
                
                if ($newAgentId && $newAgentId != $currentAgentId) {
                    try {
                        // Incrementar contador de reatribuições
                        $reassignmentCount = (int)($conversation['reassignment_count'] ?? 0) + 1;
                        
                        Conversation::update($conversationId, [
                            'reassignment_count' => $reassignmentCount,
                            'last_reassignment_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        // Se ID for negativo, é agente de IA
                        if ($newAgentId < 0) {
                            $aiAgentId = abs($newAgentId);
                            error_log("Conversa {$conversationId} deveria ser reatribuída a agente de IA {$aiAgentId}");
                        } else {
                            ConversationService::assignToAgent($conversationId, $newAgentId, false);
                            $result['reassigned'] = true;
                            $result['type'] = 'first_response';
                            
                            // Criar notificação
                            if (class_exists('\App\Services\NotificationService')) {
                                \App\Services\NotificationService::notifyConversationReassigned(
                                    $newAgentId,
                                    $conversationId,
                                    "Reatribuída automaticamente após SLA (tentativa #{$reassignmentCount})"
                                );
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Erro ao reatribuir conversa {$conversationId}: " . $e->getMessage());
                    }
                }
            }
        }
        
        // ========== VERIFICAR SLA DE RESPOSTA CONTÍNUA (ONGOING) ==========
        // Verifica se já houve primeira resposta e agora está aguardando nova resposta do agente
        if ($firstResponseOK && !$settings['sla']['enable_resolution_sla']) {
            // Obter agente atribuído à conversa
            $assignedAgentId = $conversation['agent_id'] ?? null;
            
            // Verificar última mensagem do contato vs última do agente ATRIBUÍDO
            if ($assignedAgentId) {
                $lastMessages = Database::fetch(
                    "SELECT 
                        MAX(CASE WHEN sender_type = 'contact' THEN created_at END) as last_contact,
                        MAX(CASE WHEN sender_type = 'agent' AND sender_id = ? THEN created_at END) as last_agent
                     FROM messages 
                     WHERE conversation_id = ?",
                    [$assignedAgentId, $conversationId]
                );
            } else {
                // Se não há agente atribuído, considera qualquer agente
                $lastMessages = Database::fetch(
                    "SELECT 
                        MAX(CASE WHEN sender_type = 'contact' THEN created_at END) as last_contact,
                        MAX(CASE WHEN sender_type = 'agent' THEN created_at END) as last_agent
                     FROM messages 
                     WHERE conversation_id = ?",
                    [$conversationId]
                );
            }
            
            if ($lastMessages && $lastMessages['last_contact'] && $lastMessages['last_agent']) {
                $lastContact = new \DateTime($lastMessages['last_contact']);
                $lastAgent = new \DateTime($lastMessages['last_agent']);
                
                // Se última mensagem do contato é mais recente, verificar SLA de resposta
                if ($lastContact > $lastAgent) {
                    // ✅ Verificar delay mínimo antes de contar SLA (configurável)
                    $delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
                    $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
                    if (!$delayEnabled) {
                        $delayMinutes = 0;
                    }
                    $diffSeconds = $lastContact->getTimestamp() - $lastAgent->getTimestamp();
                    $diffMinutes = $diffSeconds / 60;
                    
                    // Se não passou o delay mínimo, não contar SLA ainda
                    if ($diffMinutes < $delayMinutes) {
                        // Ignorar - mensagem muito rápida (provavelmente despedida/automática)
                    } else {
                        // Calcular tempo desde a mensagem do contato (delay é apenas threshold)
                        $slaStartTime = clone $lastContact;
                        
                        $elapsedMinutes = \App\Helpers\WorkingHoursCalculator::calculateMinutes($slaStartTime, new \DateTime());
                        $ongoingSLA = $slaConfig['ongoing_response_time'];
                        
                        if ($elapsedMinutes > $ongoingSLA) {
                            // SLA de resposta contínua excedido - pode reatribuir
                            if ($settings['sla']['auto_reassign_on_sla_breach']) {
                                $currentAgentId = $conversation['agent_id'] ?? null;
                                
                                $newAgentId = ConversationSettingsService::autoAssignConversation(
                                    $conversationId,
                                    $conversation['department_id'] ?? null,
                                    $conversation['funnel_id'] ?? null,
                                    $conversation['funnel_stage_id'] ?? null,
                                    $currentAgentId
                                );
                                
                                if ($newAgentId && $newAgentId != $currentAgentId && $newAgentId > 0) {
                                    $reassignmentCount = (int)($conversation['reassignment_count'] ?? 0) + 1;
                                    
                                    Conversation::update($conversationId, [
                                        'reassignment_count' => $reassignmentCount,
                                        'last_reassignment_at' => date('Y-m-d H:i:s')
                                    ]);
                                    
                                    ConversationService::assignToAgent($conversationId, $newAgentId, false);
                                    $result['reassigned'] = true;
                                    $result['type'] = 'ongoing_response';
                                }
                            }
                        }
                    }
                }
            }
        }

        // ========== ALERTAS DE SLA (EVITAR SPAM) ==========
        // Só enviar alerta se ainda não foi enviado
        if (!$conversation['sla_warning_sent']) {
            $elapsedMinutes = ConversationSettingsService::getElapsedSLAMinutes($conversationId);
            $slaMinutes = $slaConfig['first_response_time'];
            $warningThreshold = $slaMinutes * 0.8; // 80% do SLA
            
            if ($elapsedMinutes >= $warningThreshold && $elapsedMinutes < $slaMinutes) {
                $agentId = $conversation['agent_id'] ?? null;
                if ($agentId && class_exists('\App\Services\NotificationService')) {
                    try {
                        \App\Services\NotificationService::notifyUser($agentId, 'sla_warning', [
                            'type' => 'sla_warning',
                            'title' => 'SLA próximo de vencer',
                            'message' => "Conversa #{$conversationId} está próxima de exceder o SLA de primeira resposta",
                            'link' => '/conversations/' . $conversationId,
                            'data' => [
                                'conversation_id' => $conversationId,
                                'minutes_elapsed' => round($elapsedMinutes, 1),
                                'sla_minutes' => $slaMinutes
                            ]
                        ]);
                        
                        // Marcar como alertado para não spammar
                        Conversation::update($conversationId, ['sla_warning_sent' => 1]);
                        $result['alerted'] = true;
                    } catch (\Exception $e) {
                        error_log("Erro ao criar alerta de SLA: " . $e->getMessage());
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Verificar SLA de resolução
     */
    public static function checkResolutionSLA(int $conversationId): bool
    {
        return ConversationSettingsService::checkResolutionSLA($conversationId);
    }

    /**
     * Obter estatísticas de SLA (GERAL, HUMANO e IA separados)
     */
    public static function getSLAStats(array $filters = []): array
    {
        $settings = ConversationSettingsService::getSettings();
        $firstResponseSLA = $settings['sla']['first_response_time'];
        $resolutionSLA = $settings['sla']['resolution_time'];

        // =========== ESTATÍSTICAS GERAIS (inclui IA e Humanos) ===========
        
        // Conversas dentro do SLA de primeira resposta (GERAL)
        $sql = "SELECT COUNT(*) as total
                FROM conversations c
                WHERE c.status IN ('open', 'pending')
                AND EXISTS (
                    SELECT 1 FROM messages m 
                    WHERE m.conversation_id = c.id 
                    AND m.sender_type = 'agent'
                    AND TIMESTAMPDIFF(MINUTE, c.created_at, m.created_at) <= ?
                )";
        
        $withinSLA = Database::fetch($sql, [$firstResponseSLA]);
        
        // Conversas excedendo SLA de primeira resposta (GERAL)
        $sql = "SELECT COUNT(*) as total
                FROM conversations c
                WHERE c.status IN ('open', 'pending')
                AND TIMESTAMPDIFF(MINUTE, c.created_at, NOW()) > ?
                AND NOT EXISTS (
                    SELECT 1 FROM messages m 
                    WHERE m.conversation_id = c.id 
                    AND m.sender_type = 'agent'
                )";
        
        $exceededSLA = Database::fetch($sql, [$firstResponseSLA]);
        
        // Conversas dentro do SLA de resolução (GERAL)
        $sql = "SELECT COUNT(*) as total
                FROM conversations c
                WHERE c.status = 'closed'
                AND TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) <= ?";
        
        $resolvedWithinSLA = Database::fetch($sql, [$resolutionSLA]);
        
        // =========== ESTATÍSTICAS APENAS HUMANOS (exclui IA) ===========
        // NOTA: Considera apenas respostas do agente ATRIBUÍDO à conversa
        
        // Conversas dentro do SLA de primeira resposta (HUMANO - agente atribuído)
        $sqlHuman = "SELECT COUNT(*) as total
                     FROM conversations c
                     WHERE c.status IN ('open', 'pending')
                     AND c.agent_id IS NOT NULL
                     AND c.agent_id > 0
                     AND EXISTS (
                         SELECT 1 FROM messages m 
                         WHERE m.conversation_id = c.id 
                         AND m.sender_type = 'agent'
                         AND m.sender_id = c.agent_id
                         AND m.ai_agent_id IS NULL
                         AND TIMESTAMPDIFF(MINUTE, c.created_at, m.created_at) <= ?
                     )";
        
        $withinSLAHuman = Database::fetch($sqlHuman, [$firstResponseSLA]);
        
        // Conversas excedendo SLA de primeira resposta (HUMANO - agente atribuído)
        // Considera apenas conversas que têm agente humano atribuído mas sem resposta DELE
        $sqlHumanExceeded = "SELECT COUNT(*) as total
                            FROM conversations c
                            WHERE c.status IN ('open', 'pending')
                            AND c.agent_id IS NOT NULL
                            AND c.agent_id > 0
                            AND TIMESTAMPDIFF(MINUTE, c.created_at, NOW()) > ?
                            AND NOT EXISTS (
                                SELECT 1 FROM messages m 
                                WHERE m.conversation_id = c.id 
                                AND m.sender_type = 'agent'
                                AND m.sender_id = c.agent_id
                                AND m.ai_agent_id IS NULL
                            )";
        
        $exceededSLAHuman = Database::fetch($sqlHumanExceeded, [$firstResponseSLA]);
        
        // =========== ESTATÍSTICAS APENAS IA ===========
        
        // Conversas dentro do SLA de primeira resposta (IA)
        $sqlAI = "SELECT COUNT(*) as total
                  FROM conversations c
                  WHERE c.status IN ('open', 'pending')
                  AND EXISTS (
                      SELECT 1 FROM messages m 
                      WHERE m.conversation_id = c.id 
                      AND m.sender_type = 'agent'
                      AND m.ai_agent_id IS NOT NULL
                      AND TIMESTAMPDIFF(MINUTE, c.created_at, m.created_at) <= ?
                  )";
        
        $withinSLAAI = Database::fetch($sqlAI, [$firstResponseSLA]);
        
        // Total de conversas abertas
        $sqlTotalOpen = "SELECT COUNT(*) as total FROM conversations WHERE status IN ('open', 'pending')";
        $totalOpen = Database::fetch($sqlTotalOpen);
        
        return [
            // Estatísticas GERAIS (inclui IA + Humanos)
            'first_response' => [
                'sla_minutes' => $firstResponseSLA,
                'within_sla' => (int)($withinSLA['total'] ?? 0),
                'exceeded' => (int)($exceededSLA['total'] ?? 0),
                'total_open' => (int)($totalOpen['total'] ?? 0)
            ],
            'resolution' => [
                'sla_minutes' => $resolutionSLA,
                'within_sla' => (int)($resolvedWithinSLA['total'] ?? 0)
            ],
            
            // Estatísticas HUMANOS (exclui IA)
            'first_response_human' => [
                'sla_minutes' => $firstResponseSLA,
                'within_sla' => (int)($withinSLAHuman['total'] ?? 0),
                'exceeded' => (int)($exceededSLAHuman['total'] ?? 0),
            ],
            
            // Estatísticas IA
            'first_response_ai' => [
                'sla_minutes' => $firstResponseSLA,
                'within_sla' => (int)($withinSLAAI['total'] ?? 0),
                // IA raramente excede SLA (responde em segundos)
            ]
        ];
    }

    /**
     * Obter taxa de cumprimento de SLA separada por tipo
     */
    public static function getSLAComplianceRates(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $settings = ConversationSettingsService::getSettings();
        $firstResponseSLA = $settings['sla']['first_response_time'];
        
        // Taxa de SLA GERAL
        $sqlGeneral = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN TIMESTAMPDIFF(MINUTE, 
                            c.created_at,
                            (SELECT MIN(m.created_at) FROM messages m 
                             WHERE m.conversation_id = c.id AND m.sender_type = 'agent')
                        ) <= ? THEN 1 END) as within_sla
                       FROM conversations c
                       WHERE c.created_at >= ? AND c.created_at <= ?
                       AND EXISTS (
                           SELECT 1 FROM messages m 
                           WHERE m.conversation_id = c.id AND m.sender_type = 'agent'
                       )";
        
        $generalStats = Database::fetch($sqlGeneral, [$firstResponseSLA, $dateFrom, $dateTo]);
        
        // Taxa de SLA HUMANOS
        $sqlHuman = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN TIMESTAMPDIFF(MINUTE, 
                            c.created_at,
                            (SELECT MIN(m.created_at) FROM messages m 
                             WHERE m.conversation_id = c.id AND m.sender_type = 'agent' AND m.ai_agent_id IS NULL)
                        ) <= ? THEN 1 END) as within_sla
                     FROM conversations c
                     WHERE c.created_at >= ? AND c.created_at <= ?
                     AND EXISTS (
                         SELECT 1 FROM messages m 
                         WHERE m.conversation_id = c.id AND m.sender_type = 'agent' AND m.ai_agent_id IS NULL
                     )";
        
        $humanStats = Database::fetch($sqlHuman, [$firstResponseSLA, $dateFrom, $dateTo]);
        
        // Taxa de SLA IA
        $sqlAI = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN TIMESTAMPDIFF(MINUTE, 
                        c.created_at,
                        (SELECT MIN(m.created_at) FROM messages m 
                         WHERE m.conversation_id = c.id AND m.sender_type = 'agent' AND m.ai_agent_id IS NOT NULL)
                    ) <= ? THEN 1 END) as within_sla
                  FROM conversations c
                  WHERE c.created_at >= ? AND c.created_at <= ?
                  AND EXISTS (
                      SELECT 1 FROM messages m 
                      WHERE m.conversation_id = c.id AND m.sender_type = 'agent' AND m.ai_agent_id IS NOT NULL
                  )";
        
        $aiStats = Database::fetch($sqlAI, [$firstResponseSLA, $dateFrom, $dateTo]);
        
        $generalTotal = (int)($generalStats['total'] ?? 0);
        $generalWithin = (int)($generalStats['within_sla'] ?? 0);
        $humanTotal = (int)($humanStats['total'] ?? 0);
        $humanWithin = (int)($humanStats['within_sla'] ?? 0);
        $aiTotal = (int)($aiStats['total'] ?? 0);
        $aiWithin = (int)($aiStats['within_sla'] ?? 0);
        
        return [
            'general' => [
                'total' => $generalTotal,
                'within_sla' => $generalWithin,
                'rate' => $generalTotal > 0 ? round(($generalWithin / $generalTotal) * 100, 2) : 0
            ],
            'human' => [
                'total' => $humanTotal,
                'within_sla' => $humanWithin,
                'rate' => $humanTotal > 0 ? round(($humanWithin / $humanTotal) * 100, 2) : 0
            ],
            'ai' => [
                'total' => $aiTotal,
                'within_sla' => $aiWithin,
                'rate' => $aiTotal > 0 ? round(($aiWithin / $aiTotal) * 100, 2) : 0
            ],
            'sla_minutes' => $firstResponseSLA,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
}

