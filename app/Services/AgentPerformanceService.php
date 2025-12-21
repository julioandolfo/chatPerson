<?php
/**
 * Service AgentPerformanceService
 * Cálculo de métricas e performance de agentes
 */

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Activity;

class AgentPerformanceService
{
    /**
     * Obter estatísticas de performance do agente
     */
    public static function getPerformanceStats(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01'); // Primeiro dia do mês atual
        $dateTo = $dateTo ?? date('Y-m-d H:i:s'); // Hoje

        // Total de conversas atribuídas
        $totalConversations = self::getTotalConversations($agentId, $dateFrom, $dateTo);
        
        // Conversas fechadas/resolvidas
        $closedConversations = self::getClosedConversations($agentId, $dateFrom, $dateTo);
        
        // Conversas abertas atualmente
        $openConversations = self::getOpenConversations($agentId);
        
        // Total de mensagens enviadas
        $totalMessages = self::getTotalMessages($agentId, $dateFrom, $dateTo);
        
        // Tempo médio de primeira resposta
        $avgFirstResponseTime = self::getAverageFirstResponseTime($agentId, $dateFrom, $dateTo);
        
        // Tempo médio de resolução
        $avgResolutionTime = self::getAverageResolutionTime($agentId, $dateFrom, $dateTo);
        
        // Taxa de resolução
        $resolutionRate = $totalConversations > 0 
            ? round(($closedConversations / $totalConversations) * 100, 2) 
            : 0;
        
        // Conversas por dia (média)
        $daysDiff = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);
        $conversationsPerDay = round($totalConversations / $daysDiff, 2);
        
        // Mensagens por conversa (média)
        $avgMessagesPerConversation = $totalConversations > 0 
            ? round($totalMessages / $totalConversations, 2) 
            : 0;
        
        // Atividades realizadas
        $totalActivities = self::getTotalActivities($agentId, $dateFrom, $dateTo);
        
        // Conversas por status
        $conversationsByStatus = self::getConversationsByStatus($agentId, $dateFrom, $dateTo);
        
        return [
            'total_conversations' => $totalConversations,
            'closed_conversations' => $closedConversations,
            'open_conversations' => $openConversations,
            'total_messages' => $totalMessages,
            'avg_first_response_time' => $avgFirstResponseTime,
            'avg_resolution_time' => $avgResolutionTime,
            'resolution_rate' => $resolutionRate,
            'conversations_per_day' => $conversationsPerDay,
            'avg_messages_per_conversation' => $avgMessagesPerConversation,
            'total_activities' => $totalActivities,
            'conversations_by_status' => $conversationsByStatus,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }

    /**
     * Total de conversas atribuídas ao agente
     */
    private static function getTotalConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as count FROM conversations 
                WHERE agent_id = ? 
                AND created_at >= ? 
                AND created_at <= ?";
        
        $result = \App\Helpers\Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Conversas fechadas/resolvidas
     */
    private static function getClosedConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as count FROM conversations 
                WHERE agent_id = ? 
                AND status IN ('closed', 'resolved')
                AND updated_at >= ? 
                AND updated_at <= ?";
        
        $result = \App\Helpers\Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Conversas abertas atualmente
     */
    private static function getOpenConversations(int $agentId): int
    {
        $sql = "SELECT COUNT(*) as count FROM conversations 
                WHERE agent_id = ? 
                AND status IN ('open', 'pending')";
        
        $result = \App\Helpers\Database::fetch($sql, [$agentId]);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Total de mensagens enviadas pelo agente
     */
    private static function getTotalMessages(int $agentId, string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as count FROM messages 
                WHERE sender_id = ? 
                AND sender_type = 'agent'
                AND created_at >= ? 
                AND created_at <= ?";
        
        $result = \App\Helpers\Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Tempo médio de primeira resposta (em minutos)
     */
    private static function getAverageFirstResponseTime(int $agentId, string $dateFrom, string $dateTo): ?float
    {
        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, c.created_at, m.created_at)) as avg_time
                FROM conversations c
                INNER JOIN messages m ON m.conversation_id = c.id
                WHERE c.agent_id = ?
                AND m.sender_type = 'agent'
                AND m.sender_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?
                AND m.created_at = (
                    SELECT MIN(m2.created_at) 
                    FROM messages m2 
                    WHERE m2.conversation_id = c.id 
                    AND m2.sender_type = 'agent'
                    AND m2.sender_id = ?
                )";
        
        $result = \App\Helpers\Database::fetch($sql, [$agentId, $agentId, $dateFrom, $dateTo, $agentId]);
        return $result && $result['avg_time'] !== null ? round((float)$result['avg_time'], 2) : null;
    }

    /**
     * Tempo médio de resolução (em minutos)
     */
    private static function getAverageResolutionTime(int $agentId, string $dateFrom, string $dateTo): ?float
    {
        $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) as avg_time
                FROM conversations 
                WHERE agent_id = ?
                AND status IN ('closed', 'resolved')
                AND resolved_at IS NOT NULL
                AND resolved_at >= ?
                AND resolved_at <= ?";
        
        $result = \App\Helpers\Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return $result && $result['avg_time'] !== null ? round((float)$result['avg_time'], 2) : null;
    }

    /**
     * Total de atividades realizadas
     */
    private static function getTotalActivities(int $agentId, string $dateFrom, string $dateTo): int
    {
        if (!class_exists('\App\Models\Activity')) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM activities 
                WHERE user_id = ?
                AND created_at >= ? 
                AND created_at <= ?";
        
        $result = \App\Helpers\Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Conversas por status
     */
    private static function getConversationsByStatus(int $agentId, string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT status, COUNT(*) as count 
                FROM conversations 
                WHERE agent_id = ?
                AND created_at >= ? 
                AND created_at <= ?
                GROUP BY status";
        
        $results = \App\Helpers\Database::fetchAll($sql, [$agentId, $dateFrom, $dateTo]);
        
        $byStatus = [];
        foreach ($results as $row) {
            $byStatus[$row['status']] = (int)$row['count'];
        }
        
        return $byStatus;
    }

    /**
     * Obter ranking de agentes por performance
     */
    public static function getAgentsRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');

        $sql = "SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.avatar,
                    COUNT(DISTINCT c.id) as total_conversations,
                    COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_conversations,
                    COUNT(DISTINCT m.id) as total_messages,
                    AVG(CASE WHEN c.status IN ('closed', 'resolved') AND c.resolved_at IS NOT NULL 
                        THEN TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) END) as avg_resolution_time
                FROM users u
                LEFT JOIN conversations c ON u.id = c.agent_id 
                    AND c.created_at >= ? 
                    AND c.created_at <= ?
                LEFT JOIN messages m ON u.id = m.sender_id 
                    AND m.sender_type = 'agent'
                    AND m.created_at >= ? 
                    AND m.created_at <= ?
                WHERE u.role IN ('agent', 'admin', 'supervisor')
                    AND u.status = 'active'
                GROUP BY u.id, u.name, u.email, u.avatar
                HAVING total_conversations > 0
                ORDER BY closed_conversations DESC, total_conversations DESC
                LIMIT ?";
        
        $agents = \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo, $dateFrom, $dateTo, $limit]);
        
        // Calcular taxa de resolução para cada agente
        foreach ($agents as &$agent) {
            $agent['resolution_rate'] = $agent['total_conversations'] > 0
                ? round(($agent['closed_conversations'] / $agent['total_conversations']) * 100, 2)
                : 0;
            $agent['avg_resolution_time'] = $agent['avg_resolution_time'] 
                ? round((float)$agent['avg_resolution_time'], 2) 
                : null;
        }
        
        return $agents;
    }

    /**
     * Formatar tempo em formato legível
     */
    public static function formatTime(?float $minutes): string
    {
        if ($minutes === null || $minutes === 0) {
            return '-';
        }
        
        if ($minutes < 60) {
            return intval(round($minutes)) . ' min';
        }
        
        $hours = intval(floor($minutes / 60));
        $mins = intval(round(fmod($minutes, 60)));
        
        if ($hours < 24) {
            return $hours . 'h ' . $mins . 'min';
        }
        
        $days = intval(floor($hours / 24));
        $remainingHours = $hours % 24;
        
        return $days . 'd ' . $remainingHours . 'h';
    }
}

