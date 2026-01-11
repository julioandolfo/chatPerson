<?php
/**
 * Service TeamPerformanceService
 * Métricas agregadas de performance de times
 */

namespace App\Services;

use App\Models\Team;
use App\Helpers\Database;

class TeamPerformanceService
{
    /**
     * Obter estatísticas de performance do time (agregadas de todos os membros)
     */
    public static function getPerformanceStats(int $teamId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01'); // Primeiro dia do mês atual
        $dateTo = $dateTo ?? date('Y-m-d H:i:s'); // Hoje
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        // Obter IDs dos membros do time
        $memberIds = Team::getMemberIds($teamId);
        
        if (empty($memberIds)) {
            return self::getEmptyStats($teamId, $dateFrom, $dateTo);
        }
        
        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $params = array_merge($memberIds, [$dateFrom, $dateTo]);
        
        // Total de conversas usando histórico de atribuições
        $totalConversationsFromHistory = 0;
        foreach ($memberIds as $memberId) {
            $totalConversationsFromHistory += \App\Models\ConversationAssignment::countAgentConversations(
                $memberId,
                $dateFrom,
                $dateTo
            );
        }
        
        // Estatísticas agregadas do time
        $sql = "SELECT 
                    COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_conversations,
                    COUNT(DISTINCT CASE WHEN c.status IN ('open', 'pending') THEN c.id END) as open_conversations,
                    COUNT(DISTINCT m.id) as total_messages,
                    AVG(CASE WHEN c.status IN ('closed', 'resolved') AND c.resolved_at IS NOT NULL 
                        THEN TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) END) as avg_resolution_time
                FROM conversations c
                LEFT JOIN messages m ON c.id = m.conversation_id 
                    AND m.sender_type = 'agent'
                    AND m.ai_agent_id IS NULL
                    AND m.sender_id IN ($placeholders)
                    AND m.created_at >= ?
                    AND m.created_at <= ?
                WHERE c.agent_id IN ($placeholders)
                AND c.created_at >= ?
                AND c.created_at <= ?";
        
        $paramsForQuery = array_merge($memberIds, [$dateFrom, $dateTo], $memberIds, [$dateFrom, $dateTo]);
        $stats = Database::fetch($sql, $paramsForQuery);
        
        // Usar total de conversas do histórico
        $totalConversations = $totalConversationsFromHistory;
        
        // Calcular métricas adicionais (totalConversations já foi definido acima do histórico)
        $closedConversations = (int)($stats['closed_conversations'] ?? 0);
        $openConversations = (int)($stats['open_conversations'] ?? 0);
        $totalMessages = (int)($stats['total_messages'] ?? 0);
        $avgResolutionTime = $stats['avg_resolution_time'] ? round((float)$stats['avg_resolution_time'], 2) : null;
        
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
        
        // Tempo médio de primeira resposta do time
        $avgFirstResponseTime = self::getAverageFirstResponseTime($memberIds, $dateFrom, $dateTo);
        
        // Conversas por status
        $conversationsByStatus = self::getConversationsByStatus($memberIds, $dateFrom, $dateTo);
        
        // Performance individual dos membros
        $membersPerformance = self::getMembersPerformance($memberIds, $dateFrom, $dateTo);
        
        return [
            'team_id' => $teamId,
            'members_count' => count($memberIds),
            'total_conversations' => $totalConversations,
            'closed_conversations' => $closedConversations,
            'open_conversations' => $openConversations,
            'total_messages' => $totalMessages,
            'avg_first_response_time' => $avgFirstResponseTime,
            'avg_resolution_time' => $avgResolutionTime,
            'resolution_rate' => $resolutionRate,
            'conversations_per_day' => $conversationsPerDay,
            'avg_messages_per_conversation' => $avgMessagesPerConversation,
            'conversations_by_status' => $conversationsByStatus,
            'members_performance' => $membersPerformance,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
    
    /**
     * Estatísticas vazias (quando não há membros)
     */
    private static function getEmptyStats(int $teamId, string $dateFrom, string $dateTo): array
    {
        return [
            'team_id' => $teamId,
            'members_count' => 0,
            'total_conversations' => 0,
            'closed_conversations' => 0,
            'open_conversations' => 0,
            'total_messages' => 0,
            'avg_first_response_time' => null,
            'avg_resolution_time' => null,
            'resolution_rate' => 0,
            'conversations_per_day' => 0,
            'avg_messages_per_conversation' => 0,
            'conversations_by_status' => [],
            'members_performance' => [],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
    
    /**
     * Tempo médio de primeira resposta do time (em minutos)
     */
    private static function getAverageFirstResponseTime(array $memberIds, string $dateFrom, string $dateTo): ?float
    {
        if (empty($memberIds)) {
            return null;
        }
        
        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $params = array_merge($memberIds, [$dateFrom, $dateTo]);
        
        $sql = "SELECT AVG(response_time) as avg_time
                FROM (
                    SELECT 
                        c.id as conversation_id,
                        TIMESTAMPDIFF(MINUTE, 
                            (SELECT MIN(m_cliente.created_at) 
                             FROM messages m_cliente 
                             WHERE m_cliente.conversation_id = c.id 
                             AND m_cliente.sender_type = 'contact'
                             AND m_cliente.created_at >= COALESCE(
                                 (SELECT MAX(m_ia.created_at) 
                                  FROM messages m_ia 
                                  WHERE m_ia.conversation_id = c.id 
                                  AND m_ia.ai_agent_id IS NOT NULL),
                                 c.created_at
                             )),
                            (SELECT MIN(m_agente.created_at) 
                             FROM messages m_agente 
                             WHERE m_agente.conversation_id = c.id 
                             AND m_agente.sender_type = 'agent'
                             AND m_agente.sender_id IN ($placeholders)
                             AND m_agente.ai_agent_id IS NULL)
                        ) as response_time
                    FROM conversations c
                    WHERE c.agent_id IN ($placeholders)
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                    AND EXISTS (
                        SELECT 1 FROM messages m 
                        WHERE m.conversation_id = c.id 
                        AND m.sender_type = 'agent'
                        AND m.sender_id IN ($placeholders)
                        AND m.ai_agent_id IS NULL
                    )
                ) as response_times
                WHERE response_time IS NOT NULL AND response_time >= 0";
        
        $paramsForQuery = array_merge($memberIds, $memberIds, [$dateFrom, $dateTo], $memberIds);
        $result = Database::fetch($sql, $paramsForQuery);
        return $result && $result['avg_time'] !== null ? round((float)$result['avg_time'], 2) : null;
    }
    
    /**
     * Conversas por status
     */
    private static function getConversationsByStatus(array $memberIds, string $dateFrom, string $dateTo): array
    {
        if (empty($memberIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $params = array_merge($memberIds, [$dateFrom, $dateTo]);
        
        $sql = "SELECT status, COUNT(*) as count 
                FROM conversations 
                WHERE agent_id IN ($placeholders)
                AND created_at >= ? 
                AND created_at <= ?
                GROUP BY status";
        
        $results = Database::fetchAll($sql, $params);
        
        $byStatus = [];
        foreach ($results as $row) {
            $byStatus[$row['status']] = (int)$row['count'];
        }
        
        return $byStatus;
    }
    
    /**
     * Performance individual dos membros do time
     */
    private static function getMembersPerformance(array $memberIds, string $dateFrom, string $dateTo): array
    {
        if (empty($memberIds)) {
            return [];
        }
        
        $membersPerformance = [];
        
        foreach ($memberIds as $memberId) {
            try {
                // Usar o AgentPerformanceService existente
                $performance = AgentPerformanceService::getPerformanceStats($memberId, $dateFrom, $dateTo);
                
                // Adicionar informações do usuário
                $user = \App\Models\User::find($memberId);
                if ($user) {
                    $performance['user_id'] = $user['id'];
                    $performance['user_name'] = $user['name'];
                    $performance['user_email'] = $user['email'];
                    $performance['user_avatar'] = $user['avatar'];
                }
                
                $membersPerformance[] = $performance;
            } catch (\Exception $e) {
                error_log("Erro ao obter performance do membro {$memberId}: " . $e->getMessage());
            }
        }
        
        // Ordenar por total de conversas (maior para menor)
        usort($membersPerformance, function($a, $b) {
            return ($b['total_conversations'] ?? 0) <=> ($a['total_conversations'] ?? 0);
        });
        
        return $membersPerformance;
    }
    
    /**
     * Obter ranking de times por performance
     */
    public static function getTeamsRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $teams = Team::getActive();
        $ranking = [];
        
        foreach ($teams as $team) {
            try {
                $stats = self::getPerformanceStats($team['id'], $dateFrom, $dateTo);
                
                $ranking[] = [
                    'team_id' => $team['id'],
                    'team_name' => $team['name'],
                    'team_color' => $team['color'],
                    'leader_name' => $team['leader_name'],
                    'department_name' => $team['department_name'],
                    'members_count' => $stats['members_count'],
                    'total_conversations' => $stats['total_conversations'],
                    'closed_conversations' => $stats['closed_conversations'],
                    'resolution_rate' => $stats['resolution_rate'],
                    'avg_first_response_time' => $stats['avg_first_response_time'],
                    'avg_resolution_time' => $stats['avg_resolution_time']
                ];
            } catch (\Exception $e) {
                error_log("Erro ao obter performance do time {$team['id']}: " . $e->getMessage());
            }
        }
        
        // Ordenar por conversas fechadas (maior para menor)
        usort($ranking, function($a, $b) {
            return ($b['closed_conversations'] ?? 0) <=> ($a['closed_conversations'] ?? 0);
        });
        
        return array_slice($ranking, 0, $limit);
    }
    
    /**
     * Comparar performance de múltiplos times
     */
    public static function compareTeams(array $teamIds, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        
        $comparison = [];
        
        foreach ($teamIds as $teamId) {
            $team = Team::find($teamId);
            if ($team) {
                $stats = self::getPerformanceStats($teamId, $dateFrom, $dateTo);
                $stats['team_name'] = $team['name'];
                $stats['team_color'] = $team['color'];
                $comparison[] = $stats;
            }
        }
        
        return $comparison;
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
