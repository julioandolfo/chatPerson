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

class DashboardService
{
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
    }

    /**
     * Obter estatísticas por funil
     */
    public static function getFunnelStats(): array
    {
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
    }

    /**
     * Obter atividade recente (últimas 24 horas)
     */
    public static function getRecentActivity(int $limit = 20): array
    {
        if (!class_exists('\App\Models\Activity')) {
            return [];
        }
        
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
     * Calcula o tempo entre a primeira mensagem do cliente e a primeira resposta do agente
     * NOTA: Usa SEGUNDOS internamente e converte para minutos para maior precisão (IA responde em segundos)
     */
    private static function getAverageFirstResponseTime(string $dateFrom, string $dateTo): ?array
    {
        // Usar SEGUNDOS para maior precisão (IA responde em segundos, não minutos)
        $sql = "SELECT AVG(time_diff_seconds) as avg_seconds
                FROM (
                    SELECT 
                        c.id,
                        TIMESTAMPDIFF(SECOND, 
                            (SELECT MIN(m1.created_at) FROM messages m1 WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                            (SELECT MIN(m2.created_at) FROM messages m2 WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent')
                        ) as time_diff_seconds
                    FROM conversations c
                    WHERE c.created_at >= ?
                    AND c.created_at <= ?
                    AND EXISTS (SELECT 1 FROM messages m3 WHERE m3.conversation_id = c.id AND m3.sender_type = 'agent')
                    AND EXISTS (SELECT 1 FROM messages m4 WHERE m4.conversation_id = c.id AND m4.sender_type = 'contact')
                    HAVING time_diff_seconds IS NOT NULL AND time_diff_seconds > 0
                ) as valid_times";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        
        self::logDash("getAverageFirstResponseTime result: " . json_encode($result));
        
        // Retornar segundos e minutos
        if ($result && isset($result['avg_seconds']) && $result['avg_seconds'] !== null) {
            $seconds = (float)$result['avg_seconds'];
            $minutes = $seconds / 60;
            self::logDash("getAverageFirstResponseTime: {$seconds}s = {$minutes}min");
            return ['seconds' => round($seconds, 2), 'minutes' => round($minutes, 2)];
        }
        
        return ['seconds' => 0, 'minutes' => 0];
    }
    
    /**
     * Obter tempo médio geral de resposta (em minutos)
     * Calcula o tempo médio entre TODAS as mensagens do cliente e as respostas do agente
     * NOTA: Usa SEGUNDOS internamente e converte para minutos para maior precisão
     */
    private static function getAverageResponseTime(string $dateFrom, string $dateTo): ?array
    {
        // Usar SEGUNDOS para maior precisão (IA responde em segundos)
        $sql = "SELECT AVG(response_time_seconds) as avg_seconds
                FROM (
                    SELECT 
                        TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds
                    FROM messages m1
                    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                        AND m2.sender_type = 'agent'
                        AND m2.created_at > m1.created_at
                        AND m2.created_at = (
                            SELECT MIN(m3.created_at)
                            FROM messages m3
                            WHERE m3.conversation_id = m1.conversation_id
                            AND m3.sender_type = 'agent'
                            AND m3.created_at > m1.created_at
                        )
                    INNER JOIN conversations c ON c.id = m1.conversation_id
                    WHERE m1.sender_type = 'contact'
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                    HAVING response_time_seconds IS NOT NULL AND response_time_seconds > 0
                ) as response_times";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        
        self::logDash("getAverageResponseTime result: " . json_encode($result));
        
        // Retornar segundos e minutos
        if ($result && isset($result['avg_seconds']) && $result['avg_seconds'] !== null) {
            $seconds = (float)$result['avg_seconds'];
            $minutes = $seconds / 60;
            self::logDash("getAverageResponseTime: {$seconds}s = {$minutes}min");
            return ['seconds' => round($seconds, 2), 'minutes' => round($minutes, 2)];
        }
        
        return ['seconds' => 0, 'minutes' => 0];
    }

    /**
     * Obter tempo médio de primeira resposta APENAS HUMANOS (exclui IA)
     * Considera apenas mensagens onde ai_agent_id IS NULL
     */
    private static function getAverageFirstResponseTimeHuman(string $dateFrom, string $dateTo): ?float
    {
        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, 
                    (SELECT MIN(m1.created_at) 
                     FROM messages m1 
                     WHERE m1.conversation_id = c.id 
                     AND m1.sender_type = 'contact'),
                    (SELECT MIN(m2.created_at) 
                     FROM messages m2 
                     WHERE m2.conversation_id = c.id 
                     AND m2.sender_type = 'agent'
                     AND m2.ai_agent_id IS NULL)
                )) as avg_time
                FROM conversations c
                WHERE c.created_at >= ?
                AND c.created_at <= ?
                AND EXISTS (
                    SELECT 1 FROM messages m3 
                    WHERE m3.conversation_id = c.id 
                    AND m3.sender_type = 'agent'
                    AND m3.ai_agent_id IS NULL
                )";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        return $result && $result['avg_time'] !== null ? round((float)$result['avg_time'], 2) : null;
    }

    /**
     * Obter tempo médio geral de resposta APENAS HUMANOS (exclui IA)
     * Considera apenas mensagens onde ai_agent_id IS NULL
     */
    private static function getAverageResponseTimeHuman(string $dateFrom, string $dateTo): ?float
    {
        $sql = "SELECT AVG(response_time_minutes) as avg_time
                FROM (
                    SELECT 
                        m1.conversation_id,
                        AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as response_time_minutes
                    FROM messages m1
                    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                        AND m2.sender_type = 'agent'
                        AND m2.ai_agent_id IS NULL
                        AND m2.created_at > m1.created_at
                        AND m2.created_at = (
                            SELECT MIN(m3.created_at)
                            FROM messages m3
                            WHERE m3.conversation_id = m1.conversation_id
                            AND m3.sender_type = 'agent'
                            AND m3.ai_agent_id IS NULL
                            AND m3.created_at > m1.created_at
                        )
                    INNER JOIN conversations c ON c.id = m1.conversation_id
                    WHERE m1.sender_type = 'contact'
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                    GROUP BY m1.conversation_id
                ) as response_times";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        return $result && $result['avg_time'] !== null ? round((float)$result['avg_time'], 2) : null;
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
        
        // Total de tokens e custo
        $sqlTokensCost = "SELECT 
                            COALESCE(SUM(ac.tokens_used), 0) as total_tokens,
                            COALESCE(SUM(ac.cost), 0) as total_cost
                         FROM ai_conversations ac
                         INNER JOIN conversations c ON c.id = ac.conversation_id
                         WHERE c.created_at >= ? AND c.created_at <= ?";
        $tokensCostResult = \App\Helpers\Database::fetch($sqlTokensCost, [$dateFrom, $dateTo]);
        $totalTokens = (int)($tokensCostResult['total_tokens'] ?? 0);
        $totalCost = round((float)($tokensCostResult['total_cost'] ?? 0), 4);
        
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
            'total_cost' => $totalCost,
            'resolved_by_ai' => $aiResolvedConversations,
            'escalated_to_human' => $aiEscalatedConversations,
            'ai_resolution_rate' => $aiResolutionRate,
            'ai_escalation_rate' => $aiEscalationRate,
            'active_ai_agents' => $totalActiveAIAgents
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
     */
    public static function getAgentMetrics(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $agent = User::find($agentId);
        if (!$agent) {
            return [];
        }
        
        // Buscar SLA configurado
        $slaSettings = ConversationSettingsService::getSettings()['sla'] ?? [];
        $slaFirstResponseMinutes = $slaSettings['first_response_time'] ?? 15;
        $slaResponseMinutes = $slaSettings['ongoing_response_time'] ?? $slaFirstResponseMinutes; // SLA para respostas contínuas
        
        // Conversas totais recebidas no período
        // Usar SEGUNDOS para maior precisão (IA responde em segundos)
        $sql = "SELECT 
                    COUNT(DISTINCT c.id) as total_conversations,
                    COUNT(DISTINCT CASE WHEN c.status = 'open' THEN c.id END) as open_conversations,
                    COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_conversations,
                    COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed_conversations,
                    AVG(TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at))) as avg_resolution_hours,
                    AVG(TIMESTAMPDIFF(SECOND, 
                        (SELECT MIN(m1.created_at) FROM messages m1 
                         WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                        (SELECT MIN(m2.created_at) FROM messages m2 
                         WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent')
                    )) as avg_first_response_seconds,
                    COUNT(DISTINCT CASE WHEN TIMESTAMPDIFF(SECOND, 
                        (SELECT MIN(m1.created_at) FROM messages m1 
                         WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                        (SELECT MIN(m2.created_at) FROM messages m2 
                         WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent')
                    ) <= ? THEN c.id END) as first_response_within_sla
                FROM conversations c
                WHERE c.agent_id = ?
                AND (c.created_at >= ? OR c.updated_at >= ?)
                AND (c.created_at <= ? OR c.updated_at <= ?)";
        
        // SLA em segundos
        $slaFirstResponseSeconds = $slaFirstResponseMinutes * 60;
        $slaResponseSeconds = $slaResponseMinutes * 60;
        
        $metrics = \App\Helpers\Database::fetch($sql, [
            $slaFirstResponseSeconds,
            $agentId,
            $dateFrom, $dateFrom,
            $dateTo, $dateTo
        ]);
        
        // Conversas atuais abertas
        $sqlCurrent = "SELECT COUNT(*) as total FROM conversations 
                      WHERE agent_id = ? AND status IN ('open', 'pending')";
        $current = \App\Helpers\Database::fetch($sqlCurrent, [$agentId]);
        
        $total = (int)($metrics['total_conversations'] ?? 0);
        $resolved = (int)($metrics['resolved_conversations'] ?? 0);
        $closed = (int)($metrics['closed_conversations'] ?? 0);
        
        // Converter segundos para minutos
        $avgFirstResponseSeconds = (float)($metrics['avg_first_response_seconds'] ?? 0);
        $avgFirstResponseMinutes = $avgFirstResponseSeconds > 0 ? round($avgFirstResponseSeconds / 60, 2) : 0;
        
        // Calcular tempo médio de resposta geral (todas as trocas de mensagens) - em SEGUNDOS
        // E também contar quantas respostas estão dentro do SLA de respostas
        $sqlAvgResponse = "SELECT 
                    AVG(response_time_seconds) as avg_time_seconds,
                    COUNT(*) as total_responses,
                    SUM(CASE WHEN response_time_seconds <= ? THEN 1 ELSE 0 END) as responses_within_sla
                FROM (
                    SELECT 
                        TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds
                    FROM messages m1
                    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                        AND m2.sender_type = 'agent'
                        AND m2.created_at > m1.created_at
                        AND m2.created_at = (
                            SELECT MIN(m3.created_at)
                            FROM messages m3
                            WHERE m3.conversation_id = m1.conversation_id
                            AND m3.sender_type = 'agent'
                            AND m3.created_at > m1.created_at
                        )
                    INNER JOIN conversations c ON c.id = m1.conversation_id
                    WHERE m1.sender_type = 'contact'
                    AND c.agent_id = ?
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                ) as response_times";
        
        $avgResponseResult = \App\Helpers\Database::fetch($sqlAvgResponse, [$slaResponseSeconds, $agentId, $dateFrom, $dateTo]);
        $avgResponseSeconds = $avgResponseResult && $avgResponseResult['avg_time_seconds'] !== null 
            ? (float)$avgResponseResult['avg_time_seconds']
            : 0;
        $avgResponseMinutes = $avgResponseSeconds > 0 ? round($avgResponseSeconds / 60, 2) : 0;
        $totalResponses = (int)($avgResponseResult['total_responses'] ?? 0);
        $responsesWithinSla = (int)($avgResponseResult['responses_within_sla'] ?? 0);
        
        // Calcular taxa de SLA de primeira resposta
        $slaFirstResponseRate = $total > 0 ? round((($metrics['first_response_within_sla'] ?? 0) / $total) * 100, 2) : 0;
        
        // Calcular taxa de SLA de respostas
        $slaResponseRate = $totalResponses > 0 ? round(($responsesWithinSla / $totalResponses) * 100, 2) : 0;
        
        return [
            'agent_id' => $agentId,
            'agent_name' => $agent['name'] ?? 'Desconhecido',
            'agent_avatar' => $agent['avatar'] ?? null,
            'availability_status' => $agent['availability_status'] ?? 'offline',
            'total_conversations' => $total,
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
                $result[] = $metrics;
            }
        }
        
        return $result;
    }

    /**
     * Obter dados de conversas ao longo do tempo (para gráfico de linha)
     */
    public static function getConversationsOverTime(?string $dateFrom = null, ?string $dateTo = null, string $groupBy = 'day'): array
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
                    COUNT(CASE WHEN status IN ('open', 'pending') THEN 1 END) as open_count,
                    COUNT(CASE WHEN status IN ('closed', 'resolved') THEN 1 END) as closed_count
                FROM conversations
                WHERE created_at >= ? AND created_at <= ?
                GROUP BY period
                ORDER BY period ASC";
        
        self::logDash("getConversationsOverTime SQL: dateFrom={$dateFrom}, dateTo={$dateTo}, dateFormat={$dateFormat}");
        
        $result = \App\Helpers\Database::fetchAll($sql, [$dateFormat, $dateFrom, $dateTo]);
        
        self::logDash("getConversationsOverTime: " . count($result) . " registros retornados");
        
        return $result;
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
}

