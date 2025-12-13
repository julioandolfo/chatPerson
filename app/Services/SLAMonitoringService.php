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
     */
    private static function getConversationsToCheck(): array
    {
        $sql = "SELECT c.*, ct.name as contact_name
                FROM conversations c
                INNER JOIN contacts ct ON c.contact_id = ct.id
                WHERE c.status IN ('open', 'pending')
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY c.created_at ASC
                LIMIT 100";
        
        return Database::fetchAll($sql);
    }

    /**
     * Processar SLA de uma conversa específica
     */
    public static function processConversationSLA(int $conversationId): array
    {
        $result = [
            'reassigned' => false,
            'alerted' => false
        ];

        $conversation = Conversation::find($conversationId);
        if (!$conversation || $conversation['status'] === 'closed') {
            return $result;
        }

        $settings = ConversationSettingsService::getSettings();
        
        // Verificar SLA de primeira resposta
        $firstResponseOK = ConversationSettingsService::checkFirstResponseSLA($conversationId);
        
        if (!$firstResponseOK && $settings['sla']['auto_reassign_on_sla_breach']) {
            // Verificar se deve reatribuir
            if (ConversationSettingsService::shouldReassign($conversationId)) {
                // Reatribuir conversa
                $newAgentId = ConversationSettingsService::autoAssignConversation(
                    $conversationId,
                    $conversation['department_id'] ?? null,
                    $conversation['funnel_id'] ?? null,
                    $conversation['funnel_stage_id'] ?? null
                );
                
                if ($newAgentId) {
                    try {
                        // Se ID for negativo, é agente de IA
                        if ($newAgentId < 0) {
                            $aiAgentId = abs($newAgentId);
                            // Atribuir a agente de IA (já implementado em ConversationService)
                            // Por enquanto, apenas logar
                            error_log("Conversa {$conversationId} deveria ser reatribuída a agente de IA {$aiAgentId}");
                        } else {
                            ConversationService::assignToAgent($conversationId, $newAgentId, false);
                            $result['reassigned'] = true;
                            
                            // Criar notificação
                            if (class_exists('\App\Services\NotificationService')) {
                                \App\Services\NotificationService::notifyConversationReassigned(
                                    $newAgentId,
                                    $conversationId,
                                    'Conversa reatribuída automaticamente após SLA excedido'
                                );
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Erro ao reatribuir conversa {$conversationId}: " . $e->getMessage());
                    }
                }
            }
        }

        // Verificar se SLA está próximo de vencer (80% do tempo)
        $slaMinutes = $settings['sla']['first_response_time'];
        $warningThreshold = $slaMinutes * 0.8; // 80% do SLA
        
        $createdAt = strtotime($conversation['created_at']);
        $now = time();
        $minutesElapsed = ($now - $createdAt) / 60;
        
        if ($minutesElapsed >= $warningThreshold && $minutesElapsed < $slaMinutes) {
            // Criar alerta
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
                            'minutes_elapsed' => round($minutesElapsed, 1),
                            'sla_minutes' => $slaMinutes
                        ]
                    ]);
                    $result['alerted'] = true;
                } catch (\Exception $e) {
                    error_log("Erro ao criar alerta de SLA: " . $e->getMessage());
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
     * Obter estatísticas de SLA
     */
    public static function getSLAStats(array $filters = []): array
    {
        $settings = ConversationSettingsService::getSettings();
        $firstResponseSLA = $settings['sla']['first_response_time'];
        $resolutionSLA = $settings['sla']['resolution_time'];

        // Conversas dentro do SLA de primeira resposta
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
        
        // Conversas excedendo SLA de primeira resposta
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
        
        // Conversas dentro do SLA de resolução
        $sql = "SELECT COUNT(*) as total
                FROM conversations c
                WHERE c.status = 'closed'
                AND TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) <= ?";
        
        $resolvedWithinSLA = Database::fetch($sql, [$resolutionSLA]);
        
        return [
            'first_response' => [
                'sla_minutes' => $firstResponseSLA,
                'within_sla' => (int)($withinSLA['total'] ?? 0),
                'exceeded' => (int)($exceededSLA['total'] ?? 0),
                'total_open' => (int)($withinSLA['total'] ?? 0) + (int)($exceededSLA['total'] ?? 0)
            ],
            'resolution' => [
                'sla_minutes' => $resolutionSLA,
                'within_sla' => (int)($resolvedWithinSLA['total'] ?? 0)
            ]
        ];
    }
}

