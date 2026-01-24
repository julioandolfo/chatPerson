<?php
/**
 * Service DashboardService
 * Métricas e estatísticas para o dashboard
 */

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Funnel;
use App\Models\Message;
use App\Helpers\WorkingHoursCalculator;

class DashboardService
{
    // =========================================================================
    // FUNÇÕES AUXILIARES PARA SLA (novas regras)
    // =========================================================================
    
    /**
     * Obter todos os períodos de atribuição de um agente para uma conversa
     */
    private static function getAllAgentAssignmentPeriods(int $conversationId, int $agentId): array
    {
        $allAssignments = \App\Helpers\Database::fetchAll(
            "SELECT agent_id, assigned_at 
             FROM conversation_assignments 
             WHERE conversation_id = ?
             ORDER BY assigned_at ASC",
            [$conversationId]
        );
        
        if (empty($allAssignments)) {
            return [];
        }
        
        $periods = [];
        $currentPeriodStart = null;
        
        foreach ($allAssignments as $assignment) {
            $isTargetAgent = ((int)$assignment['agent_id'] === $agentId);
            
            if ($isTargetAgent && $currentPeriodStart === null) {
                $currentPeriodStart = $assignment['assigned_at'];
            } elseif (!$isTargetAgent && $currentPeriodStart !== null) {
                $periods[] = [
                    'assigned_at' => $currentPeriodStart,
                    'unassigned_at' => $assignment['assigned_at']
                ];
                $currentPeriodStart = null;
            }
        }
        
        if ($currentPeriodStart !== null) {
            $periods[] = [
                'assigned_at' => $currentPeriodStart,
                'unassigned_at' => null
            ];
        }
        
        return $periods;
    }
    
    /**
     * Verificar se uma mensagem está dentro de algum período de atribuição do agente
     */
    private static function isMessageInAgentPeriod(string $messageTime, array $periods): bool
    {
        $msgTime = strtotime($messageTime);
        
        foreach ($periods as $period) {
            $start = strtotime($period['assigned_at']);
            $end = $period['unassigned_at'] ? strtotime($period['unassigned_at']) : PHP_INT_MAX;
            
            if ($msgTime >= $start && $msgTime <= $end) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar se cliente respondeu ao bot
     */
    private static function hasClientRespondedToBot(int $conversationId): bool
    {
        $lastAgentMessage = \App\Helpers\Database::fetch(
            "SELECT created_at 
             FROM messages 
             WHERE conversation_id = ? 
             AND sender_type = 'agent'
             ORDER BY created_at DESC 
             LIMIT 1",
            [$conversationId]
        );
        
        if (!$lastAgentMessage) {
            $hasContact = \App\Helpers\Database::fetch(
                "SELECT 1 FROM messages WHERE conversation_id = ? AND sender_type = 'contact' LIMIT 1",
                [$conversationId]
            );
            return (bool)$hasContact;
        }
        
        $clientAfterAgent = \App\Helpers\Database::fetch(
            "SELECT 1 
             FROM messages 
             WHERE conversation_id = ? 
             AND sender_type = 'contact'
             AND created_at > ?
             LIMIT 1",
            [$conversationId, $lastAgentMessage['created_at']]
        );
        
        return (bool)$clientAfterAgent;
    }
    
    /**
     * Calcular minutos considerando working hours se habilitado
     */
    private static function calculateSLAMinutes(\DateTime $start, \DateTime $end, bool $useWorkingHours): float
    {
        if ($useWorkingHours) {
            return (float)\App\Helpers\WorkingHoursCalculator::calculateMinutes($start, $end);
        }
        return max(0, ($end->getTimestamp() - $start->getTimestamp()) / 60);
    }
    
    // =========================================================================
    // FIM DAS FUNÇÕES AUXILIARES PARA SLA
    // =========================================================================
    
    /**
     * Log para arquivo logs/dash.log
     */
    private static function logDash(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/dash.log';
        
        // Verificar se pode escrever
        if (!is_writable($logDir)) {
            @chmod($logDir, 0777);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [DashboardService] {$message}\n";
        
        // Tentar escrever, mas não falhar se não conseguir
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Obter estatísticas gerais do dashboard
     */
    public static function getGeneralStats(?int $userId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01'); // Primeiro dia do mês
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59'; // Hoje até 23:59:59
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }

        self::logDash("getGeneralStats: userId={$userId}, dateFrom={$dateFrom}, dateTo={$dateTo}");

        // Total de conversas
        $totalConversations = self::getTotalConversations($dateFrom, $dateTo);
        self::logDash("totalConversations={$totalConversations}");
        
        // Conversas abertas
        $openConversations = self::getOpenConversations();
        self::logDash("openConversations={$openConversations}");
        
        // Conversas fechadas
        $closedConversations = self::getClosedConversations($dateFrom, $dateTo);
        self::logDash("closedConversations={$closedConversations}");
        
        // Conversas do usuário (se informado)
        $myConversations = $userId ? self::getMyConversations($userId, $dateFrom, $dateTo) : 0;
        $myOpenConversations = $userId ? self::getMyOpenConversations($userId) : 0;
        self::logDash("myConversations={$myConversations}, myOpenConversations={$myOpenConversations}");
        
        // Tempo médio de resolução (período)
        $avgResolutionTime = self::getAverageResolutionTime($dateFrom, $dateTo);
        
        // Total de agentes
        $totalAgents = self::getTotalAgents();
        $activeAgents = self::getActiveAgents();
        $onlineAgents = self::getOnlineAgents();
        
        // Total de contatos
        $totalContacts = self::getTotalContacts($dateFrom, $dateTo);
        
        // Total de mensagens
        $totalMessages = self::getTotalMessages($dateFrom, $dateTo);
        
        // Conversas por status
        $conversationsByStatus = self::getConversationsByStatus();
        
        // Conversas por canal
        $conversationsByChannel = self::getConversationsByChannel($dateFrom, $dateTo);
        
        // Taxa de resolução
        $resolutionRate = $totalConversations > 0 
            ? round(($closedConversations / $totalConversations) * 100, 2) 
            : 0;
        
        // Tempo médio de primeira resposta (GERAL - inclui IA e Humanos)
        $avgFirstResponseTimeData = self::getAverageFirstResponseTime($dateFrom, $dateTo);
        $avgFirstResponseTimeSeconds = $avgFirstResponseTimeData['seconds'] ?? 0;
        $avgFirstResponseTimeMinutes = $avgFirstResponseTimeData['minutes'] ?? 0;
        
        // Tempo médio geral de resposta (GERAL - inclui IA e Humanos)
        $avgResponseTimeData = self::getAverageResponseTime($dateFrom, $dateTo);
        $avgResponseTimeSeconds = $avgResponseTimeData['seconds'] ?? 0;
        $avgResponseTimeMinutes = $avgResponseTimeData['minutes'] ?? 0;
        
        // Tempo médio de primeira resposta (APENAS HUMANOS - exclui IA)
        $avgFirstResponseTimeHuman = self::getAverageFirstResponseTimeHuman($dateFrom, $dateTo);
        
        // Tempo médio geral de resposta (APENAS HUMANOS - exclui IA)
        $avgResponseTimeHuman = self::getAverageResponseTimeHuman($dateFrom, $dateTo);
        
        // Tempo médio de primeira resposta (APENAS IA)
        $avgFirstResponseTimeAI = self::getAverageFirstResponseTimeAI($dateFrom, $dateTo);
        
        // Tempo médio geral de resposta (APENAS IA)
        $avgResponseTimeAI = self::getAverageResponseTimeAI($dateFrom, $dateTo);
        
        // Conversas sem atribuição
        $unassignedConversations = self::getUnassignedConversations();
        self::logDash("unassignedConversations={$unassignedConversations}");
        self::logDash("avgFirstResponseTime: {$avgFirstResponseTimeSeconds}s / {$avgFirstResponseTimeMinutes}min");
        self::logDash("avgResponseTime: {$avgResponseTimeSeconds}s / {$avgResponseTimeMinutes}min");
        self::logDash("avgFirstResponseTimeHuman={$avgFirstResponseTimeHuman}, avgResponseTimeHuman={$avgResponseTimeHuman}");
        self::logDash("avgFirstResponseTimeAI={$avgFirstResponseTimeAI}, avgResponseTimeAI={$avgResponseTimeAI}");
        
        // Métricas específicas de IA
        $aiMetrics = self::getAIMetrics($dateFrom, $dateTo);
        
        return [
            'conversations' => [
                'total' => $totalConversations,
                'open' => $openConversations,
                'closed' => $closedConversations,
                'my_total' => $myConversations,
                'my_open' => $myOpenConversations,
                'unassigned' => $unassignedConversations,
                'by_status' => $conversationsByStatus,
                'by_channel' => $conversationsByChannel
            ],
            'agents' => [
                'total' => $totalAgents,
                'active' => $activeAgents,
                'online' => $onlineAgents
            ],
            'contacts' => [
                'total' => $totalContacts
            ],
            'messages' => [
                'total' => $totalMessages
            ],
            'metrics' => [
                // Métricas GERAIS (inclui IA + Humanos)
                'resolution_rate' => $resolutionRate,
                'avg_first_response_time_seconds' => $avgFirstResponseTimeSeconds,
                'avg_first_response_time_minutes' => $avgFirstResponseTimeMinutes,
                'avg_response_time_seconds' => $avgResponseTimeSeconds,
                'avg_response_time_minutes' => $avgResponseTimeMinutes,
                'avg_resolution_time' => $avgResolutionTime,
                
                // Métricas HUMANOS (exclui respostas de IA)
                'avg_first_response_time_human' => $avgFirstResponseTimeHuman,
                'avg_response_time_human' => $avgResponseTimeHuman,
                
                // Métricas IA (apenas respostas de IA)
                'avg_first_response_time_ai' => $avgFirstResponseTimeAI,
                'avg_response_time_ai' => $avgResponseTimeAI,
            ],
            'ai_metrics' => $aiMetrics,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }

    /**
     * Obter estatísticas por setor
     */
    public static function getDepartmentStats(): array
    {
        // ✅ Cache de 5 minutos
        return \App\Helpers\Cache::remember('dashboard_department_stats', 300, function() {
        $sql = "SELECT 
                    d.id,
                    d.name,
                    COUNT(DISTINCT c.id) as conversations_count,
                    COUNT(DISTINCT CASE WHEN c.status = 'open' THEN c.id END) as open_conversations,
                    COUNT(DISTINCT ad.user_id) as agents_count
                FROM departments d
                LEFT JOIN conversations c ON d.id = c.department_id
                LEFT JOIN agent_departments ad ON d.id = ad.department_id
                GROUP BY d.id, d.name
                ORDER BY conversations_count DESC
                LIMIT 10";
        
        return \App\Helpers\Database::fetchAll($sql);
        });
    }

    /**
     * Obter estatísticas por funil
     */
    public static function getFunnelStats(): array
    {
        // ✅ Cache de 5 minutos
        return \App\Helpers\Cache::remember('dashboard_funnel_stats', 300, function() {
        $sql = "SELECT 
                    f.id,
                    f.name,
                    COUNT(DISTINCT c.id) as conversations_count,
                    COUNT(DISTINCT fs.id) as stages_count
                FROM funnels f
                LEFT JOIN conversations c ON f.id = c.funnel_id
                LEFT JOIN funnel_stages fs ON f.id = fs.funnel_id
                GROUP BY f.id, f.name
                ORDER BY conversations_count DESC
                LIMIT 10";
        
        return \App\Helpers\Database::fetchAll($sql);
        });
    }

    /**
     * Obter ranking de agentes (top 10)
     */
    public static function getTopAgents(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }

        return \App\Services\AgentPerformanceService::getAgentsRanking($dateFrom, $dateTo, $limit);
    }

    /**
     * Obter conversas recentes
     */
    public static function getRecentConversations(int $limit = 10): array
    {
        // ✅ Cache de 2 minutos (conversas recentes mudam frequentemente)
        $cacheKey = "dashboard_recent_conversations_{$limit}";
        return \App\Helpers\Cache::remember($cacheKey, 120, function() use ($limit) {
        $sql = "SELECT 
                    c.*,
                    ct.name as contact_name,
                    ct.phone as contact_phone,
                    ct.avatar as contact_avatar,
                    u.name as agent_name,
                    (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.read_at IS NULL) as unread_count
                FROM conversations c
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users u ON c.agent_id = u.id
                ORDER BY c.updated_at DESC
                LIMIT ?";
        
        return \App\Helpers\Database::fetchAll($sql, [$limit]);
        });
    }

    /**
     * Obter atividade recente (últimas 24 horas)
     */
    public static function getRecentActivity(int $limit = 20): array
    {
        if (!class_exists('\App\Models\Activity')) {
            return [];
        }
        
        // ✅ Cache de 2 minutos (atividades mudam frequentemente)
        $cacheKey = "dashboard_recent_activity_{$limit}";
        return \App\Helpers\Cache::remember($cacheKey, 120, function() use ($limit) {
        $sql = "SELECT 
                    a.*,
                    u.name as user_name,
                    u.avatar as user_avatar
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY a.created_at DESC
                LIMIT ?";
        
        $activities = \App\Helpers\Database::fetchAll($sql, [$limit]);
        
        // Decodificar metadata
        foreach ($activities as &$activity) {
            if (!empty($activity['metadata'])) {
                $activity['metadata'] = json_decode($activity['metadata'], true);
            } else {
                $activity['metadata'] = [];
            }
        }
        
        return $activities;
        });
    }

    // Métodos privados auxiliares

    private static function getTotalConversations(string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as total FROM conversations 
                WHERE created_at >= ? AND created_at <= ?";
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }

    private static function getOpenConversations(): int
    {
        $sql = "SELECT COUNT(*) as total FROM conversations 
                WHERE status IN ('open', 'pending')";
        $result = \App\Helpers\Database::fetch($sql);
        return (int)($result['total'] ?? 0);
    }

    private static function getClosedConversations(string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as total FROM conversations 
                WHERE status IN ('closed', 'resolved')
                AND updated_at >= ? AND updated_at <= ?";
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }

    private static function getMyConversations(int $userId, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        
        // Contar TODAS as conversas do agente (não apenas as criadas no período)
        $sql = "SELECT COUNT(*) as total FROM conversations 
                WHERE agent_id = ?";
        $result = \App\Helpers\Database::fetch($sql, [$userId]);
        
        self::logDash("getMyConversations: userId=$userId, total=" . ($result['total'] ?? 0));
        
        return (int)($result['total'] ?? 0);
    }

    private static function getMyOpenConversations(int $userId): int
    {
        $sql = "SELECT COUNT(*) as total FROM conversations 
                WHERE agent_id = ? AND status IN ('open', 'pending')";
        $result = \App\Helpers\Database::fetch($sql, [$userId]);
        return (int)($result['total'] ?? 0);
    }

    private static function getTotalAgents(): int
    {
        $sql = "SELECT COUNT(*) as total FROM users 
                WHERE role IN ('agent', 'admin', 'supervisor')";
        $result = \App\Helpers\Database::fetch($sql);
        return (int)($result['total'] ?? 0);
    }

    private static function getActiveAgents(): int
    {
        $sql = "SELECT COUNT(*) as total FROM users 
                WHERE role IN ('agent', 'admin', 'supervisor') 
                AND status = 'active'";
        $result = \App\Helpers\Database::fetch($sql);
        return (int)($result['total'] ?? 0);
    }

    private static function getOnlineAgents(): int
    {
        $sql = "SELECT COUNT(*) as total FROM users 
                WHERE role IN ('agent', 'admin', 'supervisor') 
                AND status = 'active' 
                AND availability_status = 'online'";
        $result = \App\Helpers\Database::fetch($sql);
        return (int)($result['total'] ?? 0);
    }

    private static function getTotalContacts(string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as total FROM contacts 
                WHERE created_at >= ? AND created_at <= ?";
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }

    private static function getTotalMessages(string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as total FROM messages 
                WHERE created_at >= ? AND created_at <= ?";
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }

    private static function getConversationsByStatus(): array
    {
        $sql = "SELECT status, COUNT(*) as count 
                FROM conversations 
                GROUP BY status";
        $results = \App\Helpers\Database::fetchAll($sql);
        
        $byStatus = [];
        foreach ($results as $row) {
            $byStatus[$row['status']] = (int)$row['count'];
        }
        
        return $byStatus;
    }

    private static function getConversationsByChannel(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT channel, COUNT(*) as count 
                FROM conversations 
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY channel";
        $results = \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo]);
        
        $byChannel = [];
        foreach ($results as $row) {
            $byChannel[$row['channel']] = (int)$row['count'];
        }
        
        return $byChannel;
    }

    /**
     * Obter tempo médio de primeira resposta (em minutos)
     * ATUALIZADO: Considera working hours e cliente respondeu ao bot
     */
    private static function getAverageFirstResponseTime(string $dateFrom, string $dateTo): ?array
    {
        $settings = ConversationSettingsService::getSettings();
        $useWorkingHours = $settings['sla']['working_hours_enabled'] ?? false;
        
        // Buscar conversas com primeira resposta
        $sql = "SELECT 
                    c.id,
                    (SELECT MIN(m1.created_at) FROM messages m1 
                     WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact') as first_contact,
                    (SELECT MIN(m2.created_at) FROM messages m2 
                     WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent') as first_agent
                FROM conversations c
                WHERE c.created_at >= ?
                AND c.created_at <= ?
                AND EXISTS (SELECT 1 FROM messages m3 WHERE m3.conversation_id = c.id AND m3.sender_type = 'agent')
                AND EXISTS (SELECT 1 FROM messages m4 WHERE m4.conversation_id = c.id AND m4.sender_type = 'contact')
                LIMIT 500";
        
        $conversations = \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo]);
        
        $totalSeconds = 0;
        $count = 0;
        
        foreach ($conversations as $conv) {
            // Verificar se cliente respondeu ao bot
            if (!self::hasClientRespondedToBot((int)$conv['id'])) {
                continue;
            }
            
            if (empty($conv['first_contact']) || empty($conv['first_agent'])) {
                continue;
            }
            
            $start = new \DateTime($conv['first_contact']);
            $end = new \DateTime($conv['first_agent']);
            
            if ($useWorkingHours) {
                $minutes = self::calculateSLAMinutes($start, $end, true);
                $seconds = $minutes * 60;
            } else {
                $seconds = $end->getTimestamp() - $start->getTimestamp();
            }
            
            if ($seconds > 0 && $seconds < 604800) { // Menos de 1 semana
                $totalSeconds += $seconds;
                $count++;
            }
        }
        
        if ($count > 0) {
            $avgSeconds = $totalSeconds / $count;
            $avgMinutes = $avgSeconds / 60;
            self::logDash("getAverageFirstResponseTime (with rules): {$avgSeconds}s = {$avgMinutes}min (n={$count})");
            return ['seconds' => round($avgSeconds, 2), 'minutes' => round($avgMinutes, 2)];
        }
        
        return ['seconds' => 0, 'minutes' => 0];
    }
    
    /**
     * Obter tempo médio geral de resposta (em minutos)
     * ATUALIZADO: Considera working hours, cliente respondeu ao bot e delay
     */
    private static function getAverageResponseTime(string $dateFrom, string $dateTo): ?array
    {
        $cacheKey = "avg_response_time_v2_{$dateFrom}_{$dateTo}";
        
        return \App\Helpers\Cache::remember($cacheKey, 300, function() use ($dateFrom, $dateTo) {
            $settings = ConversationSettingsService::getSettings();
            $useWorkingHours = $settings['sla']['working_hours_enabled'] ?? false;
            $delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
            $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
            
            if (!$delayEnabled) {
                $delayMinutes = 0;
            }
            
            // Buscar conversas do período
            $sql = "SELECT DISTINCT c.id
                    FROM conversations c
                    WHERE c.created_at >= ?
                    AND c.created_at <= ?
                    AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'agent')
                    AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'contact')
                    LIMIT 300";
            
            $conversations = \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo]);
            
            $totalSeconds = 0;
            $count = 0;
            
            foreach ($conversations as $conv) {
                $convId = (int)$conv['id'];
                
                // Verificar se cliente respondeu ao bot
                if (!self::hasClientRespondedToBot($convId)) {
                    continue;
                }
                
                // Buscar mensagens
                $messages = \App\Helpers\Database::fetchAll(
                    "SELECT sender_type, created_at
                     FROM messages
                     WHERE conversation_id = ?
                     ORDER BY created_at ASC",
                    [$convId]
                );
                
                $lastAgentTime = null;
                $pendingContactTime = null;
                
                foreach ($messages as $msg) {
                    if ($msg['sender_type'] === 'agent') {
                        if ($pendingContactTime) {
                            $start = new \DateTime($pendingContactTime);
                            $end = new \DateTime($msg['created_at']);
                            
                            if ($useWorkingHours) {
                                $minutes = self::calculateSLAMinutes($start, $end, true);
                                $seconds = $minutes * 60;
                            } else {
                                $seconds = $end->getTimestamp() - $start->getTimestamp();
                            }
                            
                            if ($seconds > 0 && $seconds < 604800) {
                                $totalSeconds += $seconds;
                                $count++;
                            }
                            $pendingContactTime = null;
                        }
                        $lastAgentTime = $msg['created_at'];
                        
                    } elseif ($msg['sender_type'] === 'contact' && $lastAgentTime) {
                        // Verificar delay
                        $lastAgent = new \DateTime($lastAgentTime);
                        $contact = new \DateTime($msg['created_at']);
                        $diffMinutes = ($contact->getTimestamp() - $lastAgent->getTimestamp()) / 60;
                        
                        if ($diffMinutes >= $delayMinutes && !$pendingContactTime) {
                            $pendingContactTime = $msg['created_at'];
                        }
                    }
                }
            }
            
            if ($count > 0) {
                $avgSeconds = $totalSeconds / $count;
                $avgMinutes = $avgSeconds / 60;
                self::logDash("getAverageResponseTime (with rules): {$avgSeconds}s = {$avgMinutes}min (n={$count})");
                return ['seconds' => round($avgSeconds, 2), 'minutes' => round($avgMinutes, 2)];
            }
            
            return ['seconds' => 0, 'minutes' => 0];
        });
    }

    /**
     * Obter tempo médio de primeira resposta APENAS HUMANOS (exclui IA)
     * ATUALIZADO: Considera working hours e cliente respondeu ao bot
     */
    private static function getAverageFirstResponseTimeHuman(string $dateFrom, string $dateTo): ?float
    {
        $settings = ConversationSettingsService::getSettings();
        $useWorkingHours = $settings['sla']['working_hours_enabled'] ?? false;
        
        $sql = "SELECT 
                    c.id,
                    (SELECT MIN(m1.created_at) FROM messages m1 
                     WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact') as first_contact,
                    (SELECT MIN(m2.created_at) FROM messages m2 
                     WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent' AND m2.ai_agent_id IS NULL) as first_human
                FROM conversations c
                WHERE c.created_at >= ?
                AND c.created_at <= ?
                AND EXISTS (
                    SELECT 1 FROM messages m3 
                    WHERE m3.conversation_id = c.id 
                    AND m3.sender_type = 'agent'
                    AND m3.ai_agent_id IS NULL
                )
                LIMIT 500";
        
        $conversations = \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo]);
        
        $totalMinutes = 0;
        $count = 0;
        
        foreach ($conversations as $conv) {
            if (!self::hasClientRespondedToBot((int)$conv['id'])) {
                continue;
            }
            
            if (empty($conv['first_contact']) || empty($conv['first_human'])) {
                continue;
            }
            
            $start = new \DateTime($conv['first_contact']);
            $end = new \DateTime($conv['first_human']);
            
            if ($useWorkingHours) {
                $minutes = self::calculateSLAMinutes($start, $end, true);
            } else {
                $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
            }
            
            if ($minutes > 0 && $minutes < 10080) { // Menos de 1 semana
                $totalMinutes += $minutes;
                $count++;
            }
        }
        
        return $count > 0 ? round($totalMinutes / $count, 2) : null;
    }

    /**
     * Obter tempo médio geral de resposta APENAS HUMANOS (exclui IA)
     * ATUALIZADO: Considera working hours, cliente respondeu ao bot e delay
     */
    private static function getAverageResponseTimeHuman(string $dateFrom, string $dateTo): ?float
    {
        $settings = ConversationSettingsService::getSettings();
        $useWorkingHours = $settings['sla']['working_hours_enabled'] ?? false;
        $delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
        $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
        
        if (!$delayEnabled) {
            $delayMinutes = 0;
        }
        
        $sql = "SELECT DISTINCT c.id
                FROM conversations c
                WHERE c.created_at >= ?
                AND c.created_at <= ?
                AND EXISTS (
                    SELECT 1 FROM messages m 
                    WHERE m.conversation_id = c.id 
                    AND m.sender_type = 'agent' 
                    AND m.ai_agent_id IS NULL
                )
                LIMIT 300";
        
        $conversations = \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo]);
        
        $totalMinutes = 0;
        $count = 0;
        
        foreach ($conversations as $conv) {
            $convId = (int)$conv['id'];
            
            if (!self::hasClientRespondedToBot($convId)) {
                continue;
            }
            
            $messages = \App\Helpers\Database::fetchAll(
                "SELECT sender_type, ai_agent_id, created_at
                 FROM messages
                 WHERE conversation_id = ?
                 ORDER BY created_at ASC",
                [$convId]
            );
            
            $lastHumanAgentTime = null;
            $pendingContactTime = null;
            
            foreach ($messages as $msg) {
                if ($msg['sender_type'] === 'agent' && empty($msg['ai_agent_id'])) {
                    if ($pendingContactTime) {
                        $start = new \DateTime($pendingContactTime);
                        $end = new \DateTime($msg['created_at']);
                        
                        if ($useWorkingHours) {
                            $minutes = self::calculateSLAMinutes($start, $end, true);
                        } else {
                            $minutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;
                        }
                        
                        if ($minutes > 0 && $minutes < 10080) {
                            $totalMinutes += $minutes;
                            $count++;
                        }
                        $pendingContactTime = null;
                    }
                    $lastHumanAgentTime = $msg['created_at'];
                    
                } elseif ($msg['sender_type'] === 'contact' && $lastHumanAgentTime) {
                    $lastHuman = new \DateTime($lastHumanAgentTime);
                    $contact = new \DateTime($msg['created_at']);
                    $diffMinutes = ($contact->getTimestamp() - $lastHuman->getTimestamp()) / 60;
                    
                    if ($diffMinutes >= $delayMinutes && !$pendingContactTime) {
                        $pendingContactTime = $msg['created_at'];
                    }
                }
            }
        }
        
        return $count > 0 ? round($totalMinutes / $count, 2) : null;
    }

    /**
     * Obter tempo médio de primeira resposta APENAS IA
     * Considera apenas mensagens onde ai_agent_id IS NOT NULL
     */
    private static function getAverageFirstResponseTimeAI(string $dateFrom, string $dateTo): ?float
    {
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, 
                    (SELECT MIN(m1.created_at) 
                     FROM messages m1 
                     WHERE m1.conversation_id = c.id 
                     AND m1.sender_type = 'contact'),
                    (SELECT MIN(m2.created_at) 
                     FROM messages m2 
                     WHERE m2.conversation_id = c.id 
                     AND m2.sender_type = 'agent'
                     AND m2.ai_agent_id IS NOT NULL)
                )) as avg_time_seconds
                FROM conversations c
                WHERE c.created_at >= ?
                AND c.created_at <= ?
                AND EXISTS (
                    SELECT 1 FROM messages m3 
                    WHERE m3.conversation_id = c.id 
                    AND m3.sender_type = 'agent'
                    AND m3.ai_agent_id IS NOT NULL
                )";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        // Retorna em segundos para IA (pois geralmente é muito rápido)
        return $result && $result['avg_time_seconds'] !== null ? round((float)$result['avg_time_seconds'], 2) : null;
    }

    /**
     * Obter tempo médio geral de resposta APENAS IA
     * Considera apenas mensagens onde ai_agent_id IS NOT NULL
     */
    private static function getAverageResponseTimeAI(string $dateFrom, string $dateTo): ?float
    {
        $sql = "SELECT AVG(response_time_seconds) as avg_time_seconds
                FROM (
                    SELECT 
                        m1.conversation_id,
                        AVG(TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at)) as response_time_seconds
                    FROM messages m1
                    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                        AND m2.sender_type = 'agent'
                        AND m2.ai_agent_id IS NOT NULL
                        AND m2.created_at > m1.created_at
                        AND m2.created_at = (
                            SELECT MIN(m3.created_at)
                            FROM messages m3
                            WHERE m3.conversation_id = m1.conversation_id
                            AND m3.sender_type = 'agent'
                            AND m3.ai_agent_id IS NOT NULL
                            AND m3.created_at > m1.created_at
                        )
                    INNER JOIN conversations c ON c.id = m1.conversation_id
                    WHERE m1.sender_type = 'contact'
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                    GROUP BY m1.conversation_id
                ) as response_times";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        // Retorna em segundos para IA
        return $result && $result['avg_time_seconds'] !== null ? round((float)$result['avg_time_seconds'], 2) : null;
    }

    /**
     * Obter métricas consolidadas de IA
     */
    private static function getAIMetrics(string $dateFrom, string $dateTo): array
    {
        // Total de conversas que tiveram atendimento de IA
        $sqlAIConversations = "SELECT COUNT(DISTINCT ac.conversation_id) as total
                              FROM ai_conversations ac
                              INNER JOIN conversations c ON c.id = ac.conversation_id
                              WHERE c.created_at >= ? AND c.created_at <= ?";
        $aiConversationsResult = \App\Helpers\Database::fetch($sqlAIConversations, [$dateFrom, $dateTo]);
        $totalAIConversations = (int)($aiConversationsResult['total'] ?? 0);
        
        // Total de mensagens enviadas por IA
        $sqlAIMessages = "SELECT COUNT(*) as total
                         FROM messages m
                         INNER JOIN conversations c ON c.id = m.conversation_id
                         WHERE m.ai_agent_id IS NOT NULL
                         AND m.sender_type = 'agent'
                         AND c.created_at >= ? AND c.created_at <= ?";
        $aiMessagesResult = \App\Helpers\Database::fetch($sqlAIMessages, [$dateFrom, $dateTo]);
        $totalAIMessages = (int)($aiMessagesResult['total'] ?? 0);
        
        // =============================================
        // CUSTOS CONSOLIDADOS DE TODAS AS IAs
        // =============================================
        
        // 1. AI Conversations (Conversas com Agentes de IA)
        $sqlAIConvCost = "SELECT 
                            COALESCE(SUM(ac.tokens_used), 0) as tokens,
                            COALESCE(SUM(ac.cost), 0) as cost
                         FROM ai_conversations ac
                         INNER JOIN conversations c ON c.id = ac.conversation_id
                         WHERE c.created_at >= ? AND c.created_at <= ?";
        $aiConvCost = \App\Helpers\Database::fetch($sqlAIConvCost, [$dateFrom, $dateTo]);
        $aiConvTokens = (int)($aiConvCost['tokens'] ?? 0);
        $aiConvCostTotal = (float)($aiConvCost['cost'] ?? 0);
        
        // 2. Sentiment Analysis (Análise de Sentimento)
        $sqlSentimentCost = "SELECT 
                                COALESCE(SUM(cs.tokens_used), 0) as tokens,
                                COALESCE(SUM(cs.cost), 0) as cost,
                                COUNT(*) as analyses
                             FROM conversation_sentiments cs
                             WHERE cs.analyzed_at >= ? AND cs.analyzed_at <= ?";
        $sentimentCost = \App\Helpers\Database::fetch($sqlSentimentCost, [$dateFrom, $dateTo]);
        $sentimentTokens = (int)($sentimentCost['tokens'] ?? 0);
        $sentimentCostTotal = (float)($sentimentCost['cost'] ?? 0);
        $sentimentAnalyses = (int)($sentimentCost['analyses'] ?? 0);
        
        // 3. Agent Performance Analysis (Análise de Performance)
        $sqlPerfCost = "SELECT 
                           COALESCE(SUM(apa.tokens_used), 0) as tokens,
                           COALESCE(SUM(apa.cost), 0) as cost,
                           COUNT(*) as analyses
                        FROM agent_performance_analysis apa
                        WHERE apa.analyzed_at >= ? AND apa.analyzed_at <= ?";
        $perfCost = \App\Helpers\Database::fetch($sqlPerfCost, [$dateFrom, $dateTo]);
        $perfTokens = (int)($perfCost['tokens'] ?? 0);
        $perfCostTotal = (float)($perfCost['cost'] ?? 0);
        $perfAnalyses = (int)($perfCost['analyses'] ?? 0);
        
        // 4. Realtime Coaching (Coaching em Tempo Real)
        $sqlCoachingCost = "SELECT 
                               COALESCE(SUM(rch.tokens_used), 0) as tokens,
                               COALESCE(SUM(rch.cost), 0) as cost,
                               COUNT(*) as hints
                            FROM realtime_coaching_hints rch
                            WHERE rch.created_at >= ? AND rch.created_at <= ?";
        $coachingCost = \App\Helpers\Database::fetch($sqlCoachingCost, [$dateFrom, $dateTo]);
        $coachingTokens = (int)($coachingCost['tokens'] ?? 0);
        $coachingCostTotal = (float)($coachingCost['cost'] ?? 0);
        $coachingHints = (int)($coachingCost['hints'] ?? 0);
        
        // 5. Audio Transcription (Transcrição de Áudio) - se a tabela existir
        $audioTokens = 0;
        $audioCostTotal = 0;
        $audioTranscriptions = 0;
        try {
            $sqlAudioCost = "SELECT 
                                COALESCE(SUM(at.tokens_used), 0) as tokens,
                                COALESCE(SUM(at.cost), 0) as cost,
                                COUNT(*) as transcriptions
                             FROM audio_transcriptions at
                             WHERE at.created_at >= ? AND at.created_at <= ?";
            $audioCost = \App\Helpers\Database::fetch($sqlAudioCost, [$dateFrom, $dateTo]);
            $audioTokens = (int)($audioCost['tokens'] ?? 0);
            $audioCostTotal = (float)($audioCost['cost'] ?? 0);
            $audioTranscriptions = (int)($audioCost['transcriptions'] ?? 0);
        } catch (\Exception $e) {
            // Tabela não existe ou erro - ignorar
        }
        
        // Total consolidado
        $totalTokens = $aiConvTokens + $sentimentTokens + $perfTokens + $coachingTokens + $audioTokens;
        $totalCost = $aiConvCostTotal + $sentimentCostTotal + $perfCostTotal + $coachingCostTotal + $audioCostTotal;
        
        // Conversas resolvidas pela IA (sem escalonar para humano)
        $sqlResolved = "SELECT COUNT(DISTINCT ac.conversation_id) as total
                       FROM ai_conversations ac
                       INNER JOIN conversations c ON c.id = ac.conversation_id
                       WHERE c.created_at >= ? AND c.created_at <= ?
                       AND c.status IN ('resolved', 'closed')
                       AND ac.status = 'completed'";
        $resolvedResult = \App\Helpers\Database::fetch($sqlResolved, [$dateFrom, $dateTo]);
        $aiResolvedConversations = (int)($resolvedResult['total'] ?? 0);
        
        // Conversas escalonadas (IA iniciou mas foi para humano)
        $sqlEscalated = "SELECT COUNT(DISTINCT ac.conversation_id) as total
                        FROM ai_conversations ac
                        INNER JOIN conversations c ON c.id = ac.conversation_id
                        WHERE c.created_at >= ? AND c.created_at <= ?
                        AND ac.status IN ('escalated', 'removed')";
        $escalatedResult = \App\Helpers\Database::fetch($sqlEscalated, [$dateFrom, $dateTo]);
        $aiEscalatedConversations = (int)($escalatedResult['total'] ?? 0);
        
        // Agentes de IA ativos
        $sqlActiveAI = "SELECT COUNT(*) as total FROM ai_agents WHERE enabled = 1";
        $activeAIResult = \App\Helpers\Database::fetch($sqlActiveAI);
        $totalActiveAIAgents = (int)($activeAIResult['total'] ?? 0);
        
        // Taxa de resolução pela IA
        $aiResolutionRate = $totalAIConversations > 0 
            ? round(($aiResolvedConversations / $totalAIConversations) * 100, 2) 
            : 0;
        
        // Taxa de escalonamento
        $aiEscalationRate = $totalAIConversations > 0 
            ? round(($aiEscalatedConversations / $totalAIConversations) * 100, 2) 
            : 0;
        
        return [
            'total_ai_conversations' => $totalAIConversations,
            'total_ai_messages' => $totalAIMessages,
            'total_tokens' => $totalTokens,
            'total_cost' => round($totalCost, 4),
            'resolved_by_ai' => $aiResolvedConversations,
            'escalated_to_human' => $aiEscalatedConversations,
            'ai_resolution_rate' => $aiResolutionRate,
            'ai_escalation_rate' => $aiEscalationRate,
            'active_ai_agents' => $totalActiveAIAgents,
            
            // Breakdown detalhado por tipo de IA
            'breakdown' => [
                'ai_agents' => [
                    'tokens' => $aiConvTokens,
                    'cost' => round($aiConvCostTotal, 4),
                    'count' => $totalAIConversations
                ],
                'sentiment_analysis' => [
                    'tokens' => $sentimentTokens,
                    'cost' => round($sentimentCostTotal, 4),
                    'count' => $sentimentAnalyses
                ],
                'performance_analysis' => [
                    'tokens' => $perfTokens,
                    'cost' => round($perfCostTotal, 4),
                    'count' => $perfAnalyses
                ],
                'realtime_coaching' => [
                    'tokens' => $coachingTokens,
                    'cost' => round($coachingCostTotal, 4),
                    'count' => $coachingHints
                ],
                'audio_transcription' => [
                    'tokens' => $audioTokens,
                    'cost' => round($audioCostTotal, 4),
                    'count' => $audioTranscriptions
                ]
            ]
        ];
    }

    private static function getUnassignedConversations(): int
    {
        $sql = "SELECT COUNT(*) as total FROM conversations 
                WHERE (agent_id IS NULL OR agent_id = 0)
                AND status IN ('open', 'pending')";
        $result = \App\Helpers\Database::fetch($sql);
        
        self::logDash("getUnassignedConversations: total=" . ($result['total'] ?? 0));
        
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * Obter tempo médio de resolução (em horas)
     */
    private static function getAverageResolutionTime(?string $dateFrom = null, ?string $dateTo = null): ?float
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at))) as avg_time
                FROM conversations c
                WHERE c.status IN ('resolved', 'closed')
                AND c.created_at >= ?
                AND c.created_at <= ?
                AND (c.resolved_at IS NOT NULL OR c.updated_at IS NOT NULL)";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        return $result && $result['avg_time'] !== null ? round((float)$result['avg_time'], 2) : null;
    }
    
    /**
     * Obter métricas individuais de um agente
     * NOTA: Usa tabela conversations direto para evitar problemas com conversation_assignments
     */
    public static function getAgentMetrics(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        // ✅ Cache de 3 minutos por agente
        $cacheKey = "dashboard_agent_metrics_{$agentId}_" . md5($dateFrom . $dateTo);
        return \App\Helpers\Cache::remember($cacheKey, 180, function() use ($agentId, $dateFrom, $dateTo) {
        $agent = User::find($agentId);
        if (!$agent) {
            return [];
        }
        
        // Buscar SLA configurado
        $slaSettings = ConversationSettingsService::getSettings()['sla'] ?? [];
        $slaFirstResponseMinutes = $slaSettings['first_response_time'] ?? 15;
        $slaResponseMinutes = $slaSettings['ongoing_response_time'] ?? $slaFirstResponseMinutes;
        $useWorkingHours = $slaSettings['working_hours_enabled'] ?? false;
        $delayEnabled = $slaSettings['message_delay_enabled'] ?? true;
        $delayMinutes = $slaSettings['message_delay_minutes'] ?? 1;
        
        if (!$delayEnabled) {
            $delayMinutes = 0;
        }
        
        // ===== QUERY BÁSICA para métricas gerais =====
        $sql = "SELECT 
                    COUNT(DISTINCT c.id) as total_conversations,
                    COUNT(DISTINCT CASE WHEN EXISTS (
                        SELECT 1 FROM messages m0 
                        WHERE m0.conversation_id = c.id AND m0.sender_type = 'contact'
                    ) THEN c.id END) as total_conversations_with_contact,
                    COUNT(DISTINCT CASE WHEN c.status = 'open' THEN c.id END) as open_conversations,
                    COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_conversations,
                    COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed_conversations,
                    AVG(TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at))) as avg_resolution_hours
                FROM conversations c
                WHERE c.agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?";
        
        $metrics = \App\Helpers\Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        
        $total = (int)($metrics['total_conversations'] ?? 0);
        $totalWithContact = (int)($metrics['total_conversations_with_contact'] ?? 0);
        $resolved = (int)($metrics['resolved_conversations'] ?? 0);
        $closed = (int)($metrics['closed_conversations'] ?? 0);
        
        // ===== CÁLCULO DE SLA COM NOVAS REGRAS =====
        // Buscar conversas do agente com mensagens para calcular SLA corretamente
        $conversationsForSLA = \App\Helpers\Database::fetchAll(
            "SELECT c.id, c.status, c.agent_id, c.sla_paused_duration
             FROM conversations c
             WHERE c.agent_id = ?
             AND c.created_at >= ?
             AND c.created_at <= ?
             AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'contact')",
            [$agentId, $dateFrom, $dateTo]
        );
        
        $firstResponseWithinSla = 0;
        $firstResponseTotal = 0;
        $ongoingResponsesWithinSla = 0;
        $ongoingResponsesTotal = 0;
        $totalFirstResponseSeconds = 0;
        $totalResponseSeconds = 0;
        $responseCount = 0;
        
        foreach ($conversationsForSLA as $conv) {
            $convId = (int)$conv['id'];
            
            // REGRA 1: Verificar se cliente respondeu ao bot
            if (!self::hasClientRespondedToBot($convId)) {
                continue;
            }
            
            // Buscar períodos de atribuição
            $assignmentPeriods = self::getAllAgentAssignmentPeriods($convId, $agentId);
            
            // Buscar mensagens da conversa
            $messages = \App\Helpers\Database::fetchAll(
                "SELECT sender_type, sender_id, created_at
                 FROM messages
                 WHERE conversation_id = ?
                 ORDER BY created_at ASC",
                [$convId]
            );
            
            if (empty($messages)) {
                continue;
            }
            
            // Encontrar primeira mensagem do cliente
            $firstContactTime = null;
            foreach ($messages as $msg) {
                if ($msg['sender_type'] === 'contact') {
                    $firstContactTime = new \DateTime($msg['created_at']);
                    break;
                }
            }
            
            if (!$firstContactTime) {
                continue;
            }
            
            // CALCULAR SLA DE PRIMEIRA RESPOSTA
            $firstAgentResponse = null;
            foreach ($messages as $msg) {
                if ($msg['sender_type'] === 'agent' && (int)$msg['sender_id'] === $agentId) {
                    $firstAgentResponse = $msg;
                    break;
                }
            }
            
            if ($firstAgentResponse) {
                $startTime = clone $firstContactTime;
                
                // Ajustar início se agente foi atribuído depois
                if (!empty($assignmentPeriods)) {
                    $firstPeriod = $assignmentPeriods[0];
                    $assignedTime = new \DateTime($firstPeriod['assigned_at']);
                    if ($assignedTime > $startTime) {
                        $startTime = $assignedTime;
                    }
                }
                
                $endTime = new \DateTime($firstAgentResponse['created_at']);
                $minutes = self::calculateSLAMinutes($startTime, $endTime, $useWorkingHours);
                $minutes -= (int)($conv['sla_paused_duration'] ?? 0);
                $minutes = max(0, $minutes);
                
                $firstResponseTotal++;
                $totalFirstResponseSeconds += $minutes * 60;
                
                if ($minutes <= $slaFirstResponseMinutes) {
                    $firstResponseWithinSla++;
                }
            }
            
            // CALCULAR SLA ONGOING
            $lastAgentMessage = null;
            $pendingContactMessage = null;
            
            foreach ($messages as $msg) {
                if ($msg['sender_type'] === 'agent' && (int)$msg['sender_id'] === $agentId) {
                    // Agente respondeu
                    if ($pendingContactMessage) {
                        $contactTime = new \DateTime($pendingContactMessage['created_at']);
                        $agentTime = new \DateTime($msg['created_at']);
                        $minutes = self::calculateSLAMinutes($contactTime, $agentTime, $useWorkingHours);
                        
                        $ongoingResponsesTotal++;
                        $totalResponseSeconds += $minutes * 60;
                        $responseCount++;
                        
                        if ($minutes <= $slaResponseMinutes) {
                            $ongoingResponsesWithinSla++;
                        }
                        
                        $pendingContactMessage = null;
                    }
                    $lastAgentMessage = $msg;
                    
                } elseif ($msg['sender_type'] === 'contact' && $lastAgentMessage) {
                    // REGRA 2: Verificar período de atribuição
                    if (!empty($assignmentPeriods) && !self::isMessageInAgentPeriod($msg['created_at'], $assignmentPeriods)) {
                        continue;
                    }
                    
                    // REGRA 3: Aplicar delay
                    $lastAgentTime = new \DateTime($lastAgentMessage['created_at']);
                    $contactTime = new \DateTime($msg['created_at']);
                    $diffMinutes = ($contactTime->getTimestamp() - $lastAgentTime->getTimestamp()) / 60;
                    
                    if ($diffMinutes < $delayMinutes) {
                        continue;
                    }
                    
                    if (!$pendingContactMessage) {
                        $pendingContactMessage = $msg;
                    }
                }
            }
        }
        
        // Calcular médias e taxas
        $avgFirstResponseSeconds = $firstResponseTotal > 0 
            ? $totalFirstResponseSeconds / $firstResponseTotal 
            : 0;
        $avgFirstResponseMinutes = $avgFirstResponseSeconds > 0 ? round($avgFirstResponseSeconds / 60, 2) : 0;
        
        $avgResponseSeconds = $responseCount > 0 
            ? $totalResponseSeconds / $responseCount 
            : 0;
        $avgResponseMinutes = $avgResponseSeconds > 0 ? round($avgResponseSeconds / 60, 2) : 0;
        
        $slaFirstResponseRate = $firstResponseTotal > 0 
            ? round(($firstResponseWithinSla / $firstResponseTotal) * 100, 2) 
            : 0;
        
        $slaResponseRate = $ongoingResponsesTotal > 0 
            ? round(($ongoingResponsesWithinSla / $ongoingResponsesTotal) * 100, 2) 
            : 0;
        
        $totalResponses = $ongoingResponsesTotal;
        $responsesWithinSla = $ongoingResponsesWithinSla;
        
        return [
            'agent_id' => $agentId,
            'agent_name' => $agent['name'] ?? 'Desconhecido',
            'agent_avatar' => $agent['avatar'] ?? null,
            'availability_status' => $agent['availability_status'] ?? 'offline',
            'total_conversations' => $total,
            'total_conversations_with_contact' => $totalWithContact,
            'open_conversations' => (int)($current['total'] ?? 0),
            'resolved_conversations' => $resolved,
            'closed_conversations' => $closed,
            'avg_resolution_hours' => round((float)($metrics['avg_resolution_hours'] ?? 0), 2),
            
            // Tempo de primeira resposta
            'avg_first_response_minutes' => $avgFirstResponseMinutes,
            'avg_first_response_seconds' => round($avgFirstResponseSeconds, 2),
            
            // Tempo médio de respostas
            'avg_response_minutes' => $avgResponseMinutes,
            'avg_response_seconds' => round($avgResponseSeconds, 2),
            
            // SLA de primeira resposta
            'sla_first_response_minutes' => $slaFirstResponseMinutes,
            'sla_first_response_rate' => $slaFirstResponseRate,
            'first_response_within_sla' => (int)($metrics['first_response_within_sla'] ?? 0),
            
            // SLA de respostas
            'sla_response_minutes' => $slaResponseMinutes,
            'sla_response_rate' => $slaResponseRate,
            'total_responses' => $totalResponses,
            'responses_within_sla' => $responsesWithinSla,
            
            'resolution_rate' => $total > 0 ? round((($resolved + $closed) / $total) * 100, 2) : 0
        ];
        }); // ✅ Fim do Cache::remember
    }
    
    /**
     * Obter métricas de todos os agentes (para cards individuais)
     */
    public static function getAllAgentsMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        // ✅ Cache de 3 minutos (chama getAgentMetrics que já tem cache, mas cache do resultado completo)
        $cacheKey = "dashboard_all_agents_metrics_" . md5($dateFrom . $dateTo);
        return \App\Helpers\Cache::remember($cacheKey, 180, function() use ($dateFrom, $dateTo) {
        // Buscar todos os agentes ativos
        $sql = "SELECT id, name, email, avatar, availability_status 
                FROM users 
                WHERE role IN ('agent', 'admin', 'supervisor') 
                AND status = 'active'
                ORDER BY name ASC";
        
        $agents = \App\Helpers\Database::fetchAll($sql);
        
        $result = [];
        foreach ($agents as $agent) {
            $metrics = self::getAgentMetrics($agent['id'], $dateFrom, $dateTo);
            if (!empty($metrics)) {
                // Adicionar estatísticas de disponibilidade
                try {
                    $availabilityStats = \App\Services\AvailabilityService::getAvailabilityStats(
                        $agent['id'],
                        $dateFrom,
                        $dateTo
                    );
                    $metrics['availability_stats'] = $availabilityStats;
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error("Erro ao obter estatísticas de disponibilidade para agente {$agent['id']}: " . $e->getMessage());
                    $metrics['availability_stats'] = [
                        'online' => ['seconds' => 0, 'formatted' => '0s', 'percentage' => 0],
                        'offline' => ['seconds' => 0, 'formatted' => '0s', 'percentage' => 0],
                        'away' => ['seconds' => 0, 'formatted' => '0s', 'percentage' => 0],
                        'busy' => ['seconds' => 0, 'formatted' => '0s', 'percentage' => 0]
                    ];
                }
                
                $result[] = $metrics;
            }
        }
        
        return $result;
        }); // ✅ Fim do Cache::remember
    }

    /**
     * Obter dados de conversas ao longo do tempo (para gráfico de linha)
     * ✅ NOVO: Suporta filtros avançados (setor, time, agentes)
     */
    public static function getConversationsOverTime(
        ?string $dateFrom = null, 
        ?string $dateTo = null, 
        string $groupBy = 'day',
        array $filters = []
    ): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $dateFormat = match($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };
        
        // Montar SQL base
        $sql = "SELECT 
                    DATE_FORMAT(c.created_at, ?) as period,
                    COUNT(DISTINCT c.id) as total,
                    COUNT(DISTINCT CASE WHEN c.status IN ('open', 'pending') THEN c.id END) as open_count,
                    COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_count";
        
        // Adicionar contagens por agente se filtro de agentes foi aplicado
        $agentIds = $filters['agent_ids'] ?? [];
        if (!empty($agentIds)) {
            foreach ($agentIds as $agentId) {
                $sql .= ",\n                    COUNT(DISTINCT CASE WHEN c.agent_id = {$agentId} THEN c.id END) as agent_{$agentId}_count";
            }
        }
        
        // Adicionar contagens por time se filtro de times foi aplicado
        $teamIds = $filters['team_ids'] ?? [];
        if (!empty($teamIds)) {
            $sql .= ",\n                    tm.team_id,\n                    t.name as team_name";
        }
        
        $sql .= "\n                FROM conversations c";
        
        // JOIN com times se necessário
        if (!empty($teamIds)) {
            $sql .= "\n                LEFT JOIN team_members tm ON c.agent_id = tm.user_id";
            $sql .= "\n                LEFT JOIN teams t ON tm.team_id = t.id";
        }
        
        // WHERE conditions
        $conditions = ["c.created_at >= ?", "c.created_at <= ?"];
        $params = [$dateFormat, $dateFrom, $dateTo];
        
        // Filtro por setor
        if (!empty($filters['department_id'])) {
            $conditions[] = "c.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        // Filtro por time
        if (!empty($teamIds)) {
            $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
            $conditions[] = "tm.team_id IN ({$placeholders})";
            $params = array_merge($params, $teamIds);
        }
        
        // Filtro por agentes específicos
        if (!empty($agentIds)) {
            $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
            $conditions[] = "c.agent_id IN ({$placeholders})";
            $params = array_merge($params, $agentIds);
        }
        
        // Filtro por canal (se fornecido)
        if (!empty($filters['channel'])) {
            $conditions[] = "c.channel = ?";
            $params[] = $filters['channel'];
        }
        
        // Filtro por funil (se fornecido)
        if (!empty($filters['funnel_id'])) {
            $conditions[] = "c.funnel_id = ?";
            $params[] = $filters['funnel_id'];
        }
        
        $sql .= "\n                WHERE " . implode(' AND ', $conditions);
        
        // GROUP BY
        if (!empty($teamIds)) {
            $sql .= "\n                GROUP BY period, tm.team_id, t.name";
        } else {
            $sql .= "\n                GROUP BY period";
        }
        
        $sql .= "\n                ORDER BY period ASC";
        
        self::logDash("getConversationsOverTime SQL: dateFrom={$dateFrom}, dateTo={$dateTo}, dateFormat={$dateFormat}, filters=" . json_encode($filters));
        
        $result = \App\Helpers\Database::fetchAll($sql, $params);
        
        self::logDash("getConversationsOverTime: " . count($result) . " registros retornados");
        
        // ✅ MODO COMPARATIVO: Retornar dados separados por time/agente
        $viewMode = $filters['view_mode'] ?? 'aggregated'; // 'aggregated' ou 'comparative'
        
        if ($viewMode === 'comparative') {
            // MODO COMPARATIVO: Criar datasets separados
            if (!empty($teamIds)) {
                return self::formatComparativeDataByTeam($result, $teamIds);
            } elseif (!empty($agentIds)) {
                return self::formatComparativeDataByAgent($result, $agentIds, $dateFrom, $dateTo, $dateFormat);
            }
        }
        
        // MODO AGREGADO (padrão): Somar tudo
        if (!empty($teamIds) && !empty($result)) {
            $grouped = [];
            foreach ($result as $row) {
                $period = $row['period'];
                if (!isset($grouped[$period])) {
                    $grouped[$period] = [
                        'period' => $period,
                        'total' => 0,
                        'open_count' => 0,
                        'closed_count' => 0,
                        'teams' => []
                    ];
                }
                
                $grouped[$period]['total'] += (int)$row['total'];
                $grouped[$period]['open_count'] += (int)$row['open_count'];
                $grouped[$period]['closed_count'] += (int)$row['closed_count'];
                
                if ($row['team_id']) {
                    $grouped[$period]['teams'][] = [
                        'team_id' => $row['team_id'],
                        'team_name' => $row['team_name'],
                        'count' => (int)$row['total']
                    ];
                }
            }
            
            return array_values($grouped);
        }
        
        return $result;
    }

    /**
     * Formatar dados comparativos por time
     */
    private static function formatComparativeDataByTeam(array $rawData, array $teamIds): array
    {
        $teamsData = [];
        
        // Buscar informações dos times
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $teamsInfo = \App\Helpers\Database::fetchAll(
            "SELECT id, name, color FROM teams WHERE id IN ({$placeholders})",
            $teamIds
        );
        
        // Mapear times por ID
        $teamsMap = [];
        foreach ($teamsInfo as $team) {
            $teamsMap[$team['id']] = [
                'name' => $team['name'],
                'color' => $team['color'] ?? self::generateColor($team['id'])
            ];
        }
        
        // Organizar dados por time
        foreach ($rawData as $row) {
            $teamId = $row['team_id'];
            $period = $row['period'];
            
            if (!isset($teamsData[$teamId])) {
                $teamsData[$teamId] = [
                    'team_id' => $teamId,
                    'team_name' => $teamsMap[$teamId]['name'] ?? "Time {$teamId}",
                    'color' => $teamsMap[$teamId]['color'] ?? self::generateColor($teamId),
                    'data' => []
                ];
            }
            
            $teamsData[$teamId]['data'][$period] = [
                'total' => (int)$row['total'],
                'open_count' => (int)$row['open_count'],
                'closed_count' => (int)$row['closed_count']
            ];
        }
        
        return [
            'mode' => 'comparative',
            'type' => 'teams',
            'datasets' => array_values($teamsData)
        ];
    }
    
    /**
     * Formatar dados comparativos por agente
     */
    private static function formatComparativeDataByAgent(array $rawData, array $agentIds, string $dateFrom, string $dateTo, string $dateFormat): array
    {
        $agentsData = [];
        
        // Buscar informações dos agentes
        $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
        $agentsInfo = \App\Helpers\Database::fetchAll(
            "SELECT id, name FROM users WHERE id IN ({$placeholders})",
            $agentIds
        );
        
        // Mapear agentes por ID
        $agentsMap = [];
        foreach ($agentsInfo as $agent) {
            $agentsMap[$agent['id']] = $agent['name'];
        }
        
        // Buscar dados de cada agente separadamente
        foreach ($agentIds as $agentId) {
            $sql = "SELECT 
                        DATE_FORMAT(c.created_at, ?) as period,
                        COUNT(DISTINCT c.id) as total,
                        COUNT(DISTINCT CASE WHEN c.status IN ('open', 'pending') THEN c.id END) as open_count,
                        COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_count
                    FROM conversations c
                    WHERE c.agent_id = ?
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                    GROUP BY period
                    ORDER BY period ASC";
            
            $agentData = \App\Helpers\Database::fetchAll($sql, [$dateFormat, $agentId, $dateFrom, $dateTo]);
            
            $dataByPeriod = [];
            foreach ($agentData as $row) {
                $dataByPeriod[$row['period']] = [
                    'total' => (int)$row['total'],
                    'open_count' => (int)$row['open_count'],
                    'closed_count' => (int)$row['closed_count']
                ];
            }
            
            $agentsData[] = [
                'agent_id' => $agentId,
                'agent_name' => $agentsMap[$agentId] ?? "Agente {$agentId}",
                'color' => self::generateColor($agentId),
                'data' => $dataByPeriod
            ];
        }
        
        return [
            'mode' => 'comparative',
            'type' => 'agents',
            'datasets' => $agentsData
        ];
    }
    
    /**
     * Gerar cor baseada em ID
     */
    private static function generateColor(int $id): string
    {
        $colors = [
            '#009ef7', '#50cd89', '#ffc700', '#f1416c', '#7239ea',
            '#00a3ff', '#17c653', '#f6c000', '#dc3545', '#6610f2',
            '#0d6efd', '#198754', '#fd7e14', '#d63384', '#6f42c1',
            '#0dcaf0', '#20c997', '#ffc107', '#dc3545', '#6c757d'
        ];
        
        return $colors[$id % count($colors)];
    }

    /**
     * Obter dados de conversas por canal (para gráfico de pizza)
     */
    public static function getConversationsByChannelChart(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $sql = "SELECT 
                    COALESCE(channel, 'N/A') as channel,
                    COUNT(*) as count,
                    COUNT(CASE WHEN status IN ('open', 'pending') THEN 1 END) as open_count,
                    COUNT(CASE WHEN status IN ('closed', 'resolved') THEN 1 END) as closed_count
                FROM conversations
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY channel
                ORDER BY count DESC";
        
        self::logDash("getConversationsByChannelChart SQL: dateFrom={$dateFrom}, dateTo={$dateTo}");
        
        // Testar sem filtro de data
        $sqlTest = "SELECT COUNT(*) as total FROM conversations";
        $testResult = \App\Helpers\Database::fetch($sqlTest);
        self::logDash("Total de conversas sem filtro: " . ($testResult['total'] ?? 0));
        
        // Testar com filtro
        $sqlTest2 = "SELECT COUNT(*) as total FROM conversations WHERE created_at >= ? AND created_at <= ?";
        $testResult2 = \App\Helpers\Database::fetch($sqlTest2, [$dateFrom, $dateTo]);
        self::logDash("Total de conversas COM filtro (created_at): " . ($testResult2['total'] ?? 0));
        
        $result = \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo]);
        
        self::logDash("getConversationsByChannelChart: " . count($result) . " canais retornados");
        
        return $result;
    }

    /**
     * Obter dados de conversas por status (para gráfico de pizza)
     */
    public static function getConversationsByStatusChart(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM conversations
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY status
                ORDER BY count DESC";
        
        return \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo]);
    }

    /**
     * Obter dados de performance de agentes (para gráfico de barras)
     */
    public static function getAgentsPerformanceChart(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $agents = self::getTopAgents($dateFrom, $dateTo, $limit);
        
        $result = [];
        foreach ($agents as $agent) {
            $result[] = [
                'name' => $agent['name'] ?? 'Sem nome',
                'total_conversations' => (int)($agent['total_conversations'] ?? 0),
                'closed_conversations' => (int)($agent['closed_conversations'] ?? 0),
                'resolution_rate' => (float)($agent['resolution_rate'] ?? 0),
                'avg_response_time' => (float)($agent['avg_response_time'] ?? 0)
            ];
        }
        
        return $result;
    }

    /**
     * Obter dados de mensagens ao longo do tempo
     */
    public static function getMessagesOverTime(?string $dateFrom = null, ?string $dateTo = null, string $groupBy = 'day'): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $dateFormat = match($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };
        
        $sql = "SELECT 
                    DATE_FORMAT(created_at, ?) as period,
                    COUNT(*) as total,
                    COUNT(CASE WHEN sender_type = 'agent' THEN 1 END) as agent_messages,
                    COUNT(CASE WHEN sender_type = 'contact' THEN 1 END) as contact_messages
                FROM messages
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY period
                ORDER BY period ASC";
        
        return \App\Helpers\Database::fetchAll($sql, [$dateFormat, $dateFrom, $dateTo]);
    }

    /**
     * Obter dados de SLA (tempo de resposta)
     */
    public static function getSLAMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_conversations,
                    COUNT(CASE WHEN TIMESTAMPDIFF(MINUTE, 
                        (SELECT MIN(m1.created_at) FROM messages m1 
                         WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                        (SELECT MIN(m2.created_at) FROM messages m2 
                         WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent')
                    ) <= 5 THEN 1 END) as responded_5min,
                    COUNT(CASE WHEN TIMESTAMPDIFF(MINUTE, 
                        (SELECT MIN(m1.created_at) FROM messages m1 
                         WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                        (SELECT MIN(m2.created_at) FROM messages m2 
                         WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent')
                    ) <= 15 THEN 1 END) as responded_15min,
                    COUNT(CASE WHEN TIMESTAMPDIFF(MINUTE, 
                        (SELECT MIN(m1.created_at) FROM messages m1 
                         WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                        (SELECT MIN(m2.created_at) FROM messages m2 
                         WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent')
                    ) <= 30 THEN 1 END) as responded_30min,
                    AVG(TIMESTAMPDIFF(MINUTE, 
                        (SELECT MIN(m1.created_at) FROM messages m1 
                         WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                        (SELECT MIN(m2.created_at) FROM messages m2 
                         WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent')
                    )) as avg_response_time
                FROM conversations c
                WHERE c.created_at >= ? AND c.created_at <= ?
                AND EXISTS (
                    SELECT 1 FROM messages m3 
                    WHERE m3.conversation_id = c.id 
                    AND m3.sender_type = 'agent'
                )";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        
        if (!$result) {
            return [
                'total_conversations' => 0,
                'responded_5min' => 0,
                'responded_15min' => 0,
                'responded_30min' => 0,
                'avg_response_time' => 0,
                'sla_5min_rate' => 0,
                'sla_15min_rate' => 0,
                'sla_30min_rate' => 0
            ];
        }
        
        $total = (int)($result['total_conversations'] ?? 0);
        
        return [
            'total_conversations' => $total,
            'responded_5min' => (int)($result['responded_5min'] ?? 0),
            'responded_15min' => (int)($result['responded_15min'] ?? 0),
            'responded_30min' => (int)($result['responded_30min'] ?? 0),
            'avg_response_time' => round((float)($result['avg_response_time'] ?? 0), 2),
            'sla_5min_rate' => $total > 0 ? round((($result['responded_5min'] ?? 0) / $total) * 100, 2) : 0,
            'sla_15min_rate' => $total > 0 ? round((($result['responded_15min'] ?? 0) / $total) * 100, 2) : 0,
            'sla_30min_rate' => $total > 0 ? round((($result['responded_30min'] ?? 0) / $total) * 100, 2) : 0
        ];
    }
    
    /**
     * Obter métricas detalhadas de um agente (para página de performance completa)
     * Combina métricas de atendimento, mensagens, SLA e mais
     */
    public static function getAgentDetailedMetrics(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        // ✅ Cache de 3 minutos por agente
        $cacheKey = "dashboard_agent_detailed_metrics_{$agentId}_" . md5($dateFrom . $dateTo);
        return \App\Helpers\Cache::remember($cacheKey, 180, function() use ($agentId, $dateFrom, $dateTo) {
            // Obter métricas básicas do agente
            $baseMetrics = self::getAgentMetrics($agentId, $dateFrom, $dateTo);
            
            // Métricas adicionais de mensagens (usando conversations.agent_id)
            $sqlMessages = "SELECT 
                    COUNT(*) as total_messages,
                    COUNT(DISTINCT m.conversation_id) as conversations_with_messages
                FROM messages m
                INNER JOIN conversations c ON c.id = m.conversation_id
                WHERE c.agent_id = ?
                AND m.sender_id = ? 
                AND m.sender_type = 'agent'
                AND m.created_at >= ? 
                AND m.created_at <= ?";
            
            $messagesResult = \App\Helpers\Database::fetch($sqlMessages, [
                $agentId, $agentId, $dateFrom, $dateTo
            ]);
            
            // Conversas por status detalhado (usando conversations.agent_id)
            $sqlStatusDetail = "SELECT 
                    c.status,
                    COUNT(DISTINCT c.id) as count
                FROM conversations c
                WHERE c.agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?
                GROUP BY c.status";
            
            $statusResults = \App\Helpers\Database::fetchAll($sqlStatusDetail, [
                $agentId, $dateFrom, $dateTo
            ]);
            
            $conversationsByStatus = [];
            foreach ($statusResults as $row) {
                $conversationsByStatus[$row['status']] = (int)$row['count'];
            }
            
            // Conversas por canal (usando conversations.agent_id)
            $sqlChannels = "SELECT 
                    c.channel,
                    COUNT(DISTINCT c.id) as count
                FROM conversations c
                WHERE c.agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?
                GROUP BY c.channel";
            
            $channelResults = \App\Helpers\Database::fetchAll($sqlChannels, [
                $agentId, $dateFrom, $dateTo
            ]);
            
            $conversationsByChannel = [];
            foreach ($channelResults as $row) {
                $conversationsByChannel[$row['channel']] = (int)$row['count'];
            }
            
            // Tempo médio de resolução em minutos/horas
            $avgResolutionHours = $baseMetrics['avg_resolution_hours'] ?? 0;
            $avgResolutionMinutes = $avgResolutionHours * 60;
            
            // Calcular média de mensagens por conversa
            $totalMessages = (int)($messagesResult['total_messages'] ?? 0);
            $conversationsWithMessages = (int)($messagesResult['conversations_with_messages'] ?? 0);
            $avgMessagesPerConversation = $conversationsWithMessages > 0 
                ? round($totalMessages / $conversationsWithMessages, 2) 
                : 0;
            
            // Conversas por dia (média)
            $daysDiff = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);
            $totalConversations = $baseMetrics['total_conversations'] ?? 0;
            $conversationsPerDay = round($totalConversations / $daysDiff, 2);
            
            // Calcular CSAT se disponível (placeholder - precisa de tabela de avaliações)
            $csat = null;
            try {
                $sqlCsat = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings
                    FROM conversation_ratings
                    WHERE agent_id = ?
                    AND created_at >= ?
                    AND created_at <= ?";
                $csatResult = \App\Helpers\Database::fetch($sqlCsat, [$agentId, $dateFrom, $dateTo]);
                if ($csatResult && $csatResult['total_ratings'] > 0) {
                    $csat = [
                        'average' => round((float)$csatResult['avg_rating'], 2),
                        'total' => (int)$csatResult['total_ratings']
                    ];
                }
            } catch (\Exception $e) {
                // Tabela de ratings pode não existir
            }
            
            return array_merge($baseMetrics, [
                // Métricas de mensagens
                'total_messages' => $totalMessages,
                'avg_messages_per_conversation' => $avgMessagesPerConversation,
                
                // Detalhamento de conversas
                'conversations_by_status' => $conversationsByStatus,
                'conversations_by_channel' => $conversationsByChannel,
                
                // Tempo de resolução formatado
                'avg_resolution_minutes' => round($avgResolutionMinutes, 2),
                
                // Métricas de produtividade
                'conversations_per_day' => $conversationsPerDay,
                
                // CSAT
                'csat' => $csat,
                
                // Período
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'days' => round($daysDiff)
                ]
            ]);
        }); // ✅ Fim do Cache::remember
    }
    
    /**
     * Obter métricas de atendimento de conversas por agente
     * Retorna dados para dashboard de desempenho de atendimento
     * 
     * Métricas incluídas:
     * - Conversas novas (criadas no período e atribuídas ao agente)
     * - Conversas interagidas (agente enviou mensagem no período)
     * - Total de conversas (novas + interagidas únicas)
     * - Tempo médio de resposta
     * - Métricas adicionais de desempenho
     */
    public static function getAgentAttendanceMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        self::logDash("getAgentAttendanceMetrics: dateFrom={$dateFrom}, dateTo={$dateTo}");
        
        // ✅ Cache de 3 minutos
        $cacheKey = "dashboard_agent_attendance_metrics_" . md5($dateFrom . $dateTo);
        return \App\Helpers\Cache::remember($cacheKey, 180, function() use ($dateFrom, $dateTo) {
            
            // Buscar todos os agentes ativos (humanos)
            $sqlAgents = "SELECT id, name, avatar, availability_status 
                          FROM users 
                          WHERE role IN ('agent', 'admin', 'supervisor') 
                          AND status = 'active'
                          ORDER BY name ASC";
            
            $agents = \App\Helpers\Database::fetchAll($sqlAgents);
            
            $result = [];
            $totals = [
                'new_conversations' => 0,
                'interacted_conversations' => 0,
                'total_unique_conversations' => 0,
                'total_messages_sent' => 0,
                'total_response_time_seconds' => 0,
                'response_count' => 0
            ];
            
            foreach ($agents as $agent) {
                $agentId = $agent['id'];
                
                // 1. Conversas NOVAS no período (atribuídas ao agente no período)
                // Considera conversas onde o agente foi atribuído dentro do período
                $sqlNewConversations = "SELECT COUNT(DISTINCT c.id) as total
                    FROM conversations c
                    WHERE c.agent_id = ?
                    AND c.created_at >= ?
                    AND c.created_at <= ?";
                
                $newConvResult = \App\Helpers\Database::fetch($sqlNewConversations, [$agentId, $dateFrom, $dateTo]);
                $newConversations = (int)($newConvResult['total'] ?? 0);
                
                // 2. Conversas INTERAGIDAS no período (agente enviou mensagem no período)
                // Inclui conversas criadas antes do período mas que tiveram interação
                $sqlInteractedConversations = "SELECT COUNT(DISTINCT c.id) as total
                    FROM conversations c
                    INNER JOIN messages m ON m.conversation_id = c.id
                    WHERE m.sender_type = 'agent'
                    AND m.sender_id = ?
                    AND m.ai_agent_id IS NULL
                    AND m.created_at >= ?
                    AND m.created_at <= ?";
                
                $interactedResult = \App\Helpers\Database::fetch($sqlInteractedConversations, [$agentId, $dateFrom, $dateTo]);
                $interactedConversations = (int)($interactedResult['total'] ?? 0);
                
                // 3. Total de conversas ÚNICAS (união de novas + interagidas)
                $sqlTotalUnique = "SELECT COUNT(DISTINCT conversation_id) as total FROM (
                    -- Conversas novas
                    SELECT c.id as conversation_id
                    FROM conversations c
                    WHERE c.agent_id = ?
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                    
                    UNION
                    
                    -- Conversas interagidas
                    SELECT DISTINCT m.conversation_id
                    FROM messages m
                    WHERE m.sender_type = 'agent'
                    AND m.sender_id = ?
                    AND m.ai_agent_id IS NULL
                    AND m.created_at >= ?
                    AND m.created_at <= ?
                ) as all_conversations";
                
                $totalUniqueResult = \App\Helpers\Database::fetch($sqlTotalUnique, [
                    $agentId, $dateFrom, $dateTo,
                    $agentId, $dateFrom, $dateTo
                ]);
                $totalUniqueConversations = (int)($totalUniqueResult['total'] ?? 0);
                
                // 4. Total de mensagens enviadas pelo agente no período
                $sqlMessagesSent = "SELECT COUNT(*) as total
                    FROM messages m
                    WHERE m.sender_type = 'agent'
                    AND m.sender_id = ?
                    AND m.ai_agent_id IS NULL
                    AND m.created_at >= ?
                    AND m.created_at <= ?";
                
                $messagesSentResult = \App\Helpers\Database::fetch($sqlMessagesSent, [$agentId, $dateFrom, $dateTo]);
                $messagesSent = (int)($messagesSentResult['total'] ?? 0);
                
                // 5. Tempo médio de resposta (em segundos)
                // Calcula o tempo entre mensagem do cliente e resposta do agente
                $sqlAvgResponseTime = "SELECT 
                        AVG(response_time_seconds) as avg_seconds,
                        COUNT(*) as response_count
                    FROM (
                        SELECT 
                            TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds
                        FROM messages m1
                        INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                            AND m2.sender_type = 'agent'
                            AND m2.sender_id = ?
                            AND m2.ai_agent_id IS NULL
                            AND m2.created_at > m1.created_at
                            AND m2.created_at = (
                                SELECT MIN(m3.created_at)
                                FROM messages m3
                                WHERE m3.conversation_id = m1.conversation_id
                                AND m3.sender_type = 'agent'
                                AND m3.sender_id = ?
                                AND m3.ai_agent_id IS NULL
                                AND m3.created_at > m1.created_at
                            )
                        WHERE m1.sender_type = 'contact'
                        AND m1.created_at >= ?
                        AND m1.created_at <= ?
                        HAVING response_time_seconds IS NOT NULL AND response_time_seconds > 0 AND response_time_seconds < 86400
                    ) as response_times";
                
                $avgResponseResult = \App\Helpers\Database::fetch($sqlAvgResponseTime, [
                    $agentId, $agentId, $dateFrom, $dateTo
                ]);
                
                $avgResponseSeconds = (float)($avgResponseResult['avg_seconds'] ?? 0);
                $responseCount = (int)($avgResponseResult['response_count'] ?? 0);
                
                // 6. Tempo médio de primeira resposta
                $sqlFirstResponse = "SELECT 
                        AVG(first_response_seconds) as avg_seconds
                    FROM (
                        SELECT 
                            TIMESTAMPDIFF(SECOND, 
                                (SELECT MIN(m1.created_at) FROM messages m1 
                                 WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                                (SELECT MIN(m2.created_at) FROM messages m2 
                                 WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent' 
                                 AND m2.sender_id = ? AND m2.ai_agent_id IS NULL)
                            ) as first_response_seconds
                        FROM conversations c
                        WHERE c.agent_id = ?
                        AND c.created_at >= ?
                        AND c.created_at <= ?
                        AND EXISTS (SELECT 1 FROM messages m3 WHERE m3.conversation_id = c.id AND m3.sender_type = 'agent' AND m3.sender_id = ? AND m3.ai_agent_id IS NULL)
                        AND EXISTS (SELECT 1 FROM messages m4 WHERE m4.conversation_id = c.id AND m4.sender_type = 'contact')
                        HAVING first_response_seconds IS NOT NULL AND first_response_seconds > 0 AND first_response_seconds < 86400
                    ) as first_responses";
                
                $firstResponseResult = \App\Helpers\Database::fetch($sqlFirstResponse, [
                    $agentId, $agentId, $dateFrom, $dateTo, $agentId
                ]);
                
                $avgFirstResponseSeconds = (float)($firstResponseResult['avg_seconds'] ?? 0);
                
                // 7. Taxa de resolução (conversas fechadas/resolvidas)
                $sqlResolved = "SELECT 
                        COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as resolved,
                        COUNT(DISTINCT c.id) as total
                    FROM conversations c
                    WHERE c.agent_id = ?
                    AND c.created_at >= ?
                    AND c.created_at <= ?";
                
                $resolvedResult = \App\Helpers\Database::fetch($sqlResolved, [$agentId, $dateFrom, $dateTo]);
                $resolvedConversations = (int)($resolvedResult['resolved'] ?? 0);
                $resolutionRate = $newConversations > 0 
                    ? round(($resolvedConversations / $newConversations) * 100, 1) 
                    : 0;
                
                // Formatar tempo médio de resposta
                $avgResponseMinutes = $avgResponseSeconds > 0 ? round($avgResponseSeconds / 60, 1) : 0;
                $avgFirstResponseMinutes = $avgFirstResponseSeconds > 0 ? round($avgFirstResponseSeconds / 60, 1) : 0;
                
                // Formatar tempo para exibição
                $avgResponseFormatted = self::formatDuration($avgResponseSeconds);
                $avgFirstResponseFormatted = self::formatDuration($avgFirstResponseSeconds);
                
                // Calcular conversas por dia
                $daysDiff = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);
                $conversationsPerDay = round($totalUniqueConversations / $daysDiff, 1);
                
                // Calcular mensagens por conversa
                $messagesPerConversation = $totalUniqueConversations > 0 
                    ? round($messagesSent / $totalUniqueConversations, 1) 
                    : 0;
                
                // Determinar classificação de performance
                $performanceScore = self::calculatePerformanceScore(
                    $avgResponseMinutes,
                    $resolutionRate,
                    $totalUniqueConversations,
                    $conversationsPerDay
                );
                
                // Adicionar ao resultado
                $result[] = [
                    'agent_id' => $agentId,
                    'agent_name' => $agent['name'],
                    'agent_avatar' => $agent['avatar'],
                    'availability_status' => $agent['availability_status'],
                    
                    // Métricas principais solicitadas
                    'new_conversations' => $newConversations,
                    'interacted_conversations' => $interactedConversations,
                    'total_unique_conversations' => $totalUniqueConversations,
                    
                    // Tempo de resposta
                    'avg_response_seconds' => round($avgResponseSeconds, 0),
                    'avg_response_minutes' => $avgResponseMinutes,
                    'avg_response_formatted' => $avgResponseFormatted,
                    
                    // Tempo de primeira resposta
                    'avg_first_response_seconds' => round($avgFirstResponseSeconds, 0),
                    'avg_first_response_minutes' => $avgFirstResponseMinutes,
                    'avg_first_response_formatted' => $avgFirstResponseFormatted,
                    
                    // Métricas adicionais
                    'messages_sent' => $messagesSent,
                    'messages_per_conversation' => $messagesPerConversation,
                    'resolved_conversations' => $resolvedConversations,
                    'resolution_rate' => $resolutionRate,
                    'conversations_per_day' => $conversationsPerDay,
                    
                    // Performance
                    'performance_score' => $performanceScore,
                    'performance_level' => self::getPerformanceLevel($performanceScore)
                ];
                
                // Acumular totais
                $totals['new_conversations'] += $newConversations;
                $totals['interacted_conversations'] += $interactedConversations;
                $totals['total_unique_conversations'] += $totalUniqueConversations;
                $totals['total_messages_sent'] += $messagesSent;
                if ($avgResponseSeconds > 0) {
                    $totals['total_response_time_seconds'] += $avgResponseSeconds * $responseCount;
                    $totals['response_count'] += $responseCount;
                }
            }
            
            // Ordenar por total de conversas (decrescente)
            usort($result, function($a, $b) {
                return $b['total_unique_conversations'] <=> $a['total_unique_conversations'];
            });
            
            // Calcular médias totais
            $avgTotalResponseSeconds = $totals['response_count'] > 0 
                ? round($totals['total_response_time_seconds'] / $totals['response_count'], 0)
                : 0;
            
            return [
                'agents' => $result,
                'totals' => [
                    'new_conversations' => $totals['new_conversations'],
                    'interacted_conversations' => $totals['interacted_conversations'],
                    'total_unique_conversations' => $totals['total_unique_conversations'],
                    'total_messages_sent' => $totals['total_messages_sent'],
                    'avg_response_seconds' => $avgTotalResponseSeconds,
                    'avg_response_formatted' => self::formatDuration($avgTotalResponseSeconds),
                    'agents_count' => count($result)
                ],
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ];
        }); // ✅ Fim do Cache::remember
    }
    
    /**
     * Formatar duração em segundos para string legível
     */
    private static function formatDuration(float $seconds): string
    {
        if ($seconds <= 0) {
            return '-';
        }
        
        if ($seconds < 60) {
            return (int)round($seconds) . 's';
        }
        
        $minutes = (int)floor($seconds / 60);
        $remainingSeconds = (int)round(fmod($seconds, 60));
        
        if ($minutes < 60) {
            if ($remainingSeconds > 0) {
                return "{$minutes}m {$remainingSeconds}s";
            }
            return "{$minutes}m";
        }
        
        $hours = (int)floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes > 0) {
            return "{$hours}h {$remainingMinutes}m";
        }
        return "{$hours}h";
    }
    
    /**
     * Calcular score de performance do agente (0-100)
     */
    private static function calculatePerformanceScore(
        float $avgResponseMinutes,
        float $resolutionRate,
        int $totalConversations,
        float $conversationsPerDay
    ): int {
        $score = 0;
        
        // Tempo de resposta (40 pontos) - quanto menor, melhor
        if ($avgResponseMinutes > 0) {
            if ($avgResponseMinutes <= 2) {
                $score += 40; // Excelente
            } elseif ($avgResponseMinutes <= 5) {
                $score += 35; // Muito bom
            } elseif ($avgResponseMinutes <= 10) {
                $score += 30; // Bom
            } elseif ($avgResponseMinutes <= 15) {
                $score += 20; // Regular
            } elseif ($avgResponseMinutes <= 30) {
                $score += 10; // Precisa melhorar
            }
            // Acima de 30 minutos = 0 pontos
        }
        
        // Taxa de resolução (30 pontos)
        $score += min(30, round($resolutionRate * 0.3));
        
        // Volume de atendimento (20 pontos) - baseado em conversas por dia
        if ($conversationsPerDay >= 20) {
            $score += 20;
        } elseif ($conversationsPerDay >= 15) {
            $score += 18;
        } elseif ($conversationsPerDay >= 10) {
            $score += 15;
        } elseif ($conversationsPerDay >= 5) {
            $score += 10;
        } elseif ($conversationsPerDay >= 1) {
            $score += 5;
        }
        
        // Consistência (10 pontos) - ter pelo menos algumas conversas
        if ($totalConversations >= 10) {
            $score += 10;
        } elseif ($totalConversations >= 5) {
            $score += 7;
        } elseif ($totalConversations >= 1) {
            $score += 3;
        }
        
        return min(100, max(0, $score));
    }
    
    /**
     * Obter nível de performance baseado no score
     */
    private static function getPerformanceLevel(int $score): array
    {
        if ($score >= 80) {
            return ['level' => 'excellent', 'label' => 'Excelente', 'color' => 'success'];
        } elseif ($score >= 60) {
            return ['level' => 'good', 'label' => 'Bom', 'color' => 'primary'];
        } elseif ($score >= 40) {
            return ['level' => 'regular', 'label' => 'Regular', 'color' => 'warning'];
        } else {
            return ['level' => 'needs_improvement', 'label' => 'A Melhorar', 'color' => 'danger'];
        }
    }
}

