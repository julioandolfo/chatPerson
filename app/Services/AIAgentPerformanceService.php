<?php
/**
 * Service AIAgentPerformanceService
 * Métricas e estatísticas específicas de performance de Agentes de IA
 */

namespace App\Services;

use App\Models\AIAgent;
use App\Models\AIConversation;
use App\Helpers\Database;

class AIAgentPerformanceService
{
    /**
     * Obter estatísticas de performance de um agente de IA específico
     */
    public static function getPerformanceStats(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');

        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }

        $agent = AIAgent::find($agentId);
        if (!$agent) {
            return [];
        }

        // Total de conversas atendidas
        $totalConversations = self::getTotalConversations($agentId, $dateFrom, $dateTo);
        
        // Conversas ativas atualmente
        $activeConversations = self::getActiveConversations($agentId);
        
        // Conversas resolvidas (sem escalar)
        $resolvedConversations = self::getResolvedConversations($agentId, $dateFrom, $dateTo);
        
        // Conversas escalonadas (foi para humano)
        $escalatedConversations = self::getEscalatedConversations($agentId, $dateFrom, $dateTo);
        
        // Total de mensagens enviadas
        $totalMessages = self::getTotalMessages($agentId, $dateFrom, $dateTo);
        
        // Tokens e custo
        $tokensAndCost = self::getTokensAndCost($agentId, $dateFrom, $dateTo);
        
        // Tempo médio de resposta (em segundos)
        $avgResponseTime = self::getAverageResponseTime($agentId, $dateFrom, $dateTo);
        
        // Tools utilizadas
        $toolsUsed = self::getToolsUsed($agentId, $dateFrom, $dateTo);
        
        // Taxa de resolução
        $resolutionRate = $totalConversations > 0 
            ? round(($resolvedConversations / $totalConversations) * 100, 2) 
            : 0;
        
        // Taxa de escalonamento
        $escalationRate = $totalConversations > 0 
            ? round(($escalatedConversations / $totalConversations) * 100, 2) 
            : 0;
        
        // Conversas por dia (média)
        $daysDiff = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);
        $conversationsPerDay = round($totalConversations / $daysDiff, 2);
        
        // Mensagens por conversa
        $avgMessagesPerConversation = $totalConversations > 0 
            ? round($totalMessages / $totalConversations, 2) 
            : 0;
        
        // Custo médio por conversa
        $avgCostPerConversation = $totalConversations > 0 
            ? round($tokensAndCost['total_cost'] / $totalConversations, 4) 
            : 0;

        return [
            'agent_id' => $agentId,
            'agent_name' => $agent['name'] ?? 'Desconhecido',
            'agent_type' => $agent['agent_type'] ?? 'custom',
            'model' => $agent['model'] ?? 'gpt-4',
            'enabled' => (bool)($agent['enabled'] ?? false),
            
            // Conversas
            'total_conversations' => $totalConversations,
            'active_conversations' => $activeConversations,
            'resolved_conversations' => $resolvedConversations,
            'escalated_conversations' => $escalatedConversations,
            'conversations_per_day' => $conversationsPerDay,
            
            // Mensagens
            'total_messages' => $totalMessages,
            'avg_messages_per_conversation' => $avgMessagesPerConversation,
            
            // Tempo
            'avg_response_time_seconds' => $avgResponseTime,
            'avg_response_time_formatted' => self::formatTimeSeconds($avgResponseTime),
            
            // Custos
            'total_tokens' => $tokensAndCost['total_tokens'],
            'tokens_prompt' => $tokensAndCost['tokens_prompt'],
            'tokens_completion' => $tokensAndCost['tokens_completion'],
            'total_cost' => $tokensAndCost['total_cost'],
            'avg_cost_per_conversation' => $avgCostPerConversation,
            
            // Taxas
            'resolution_rate' => $resolutionRate,
            'escalation_rate' => $escalationRate,
            
            // Tools
            'tools_used' => $toolsUsed,
            
            // Período
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }

    /**
     * Total de conversas atendidas pelo agente de IA
     */
    private static function getTotalConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(DISTINCT ac.conversation_id) as total
                FROM ai_conversations ac
                INNER JOIN conversations c ON c.id = ac.conversation_id
                WHERE ac.ai_agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?";
        
        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Conversas ativas atualmente (sendo atendidas pelo agente)
     */
    private static function getActiveConversations(int $agentId): int
    {
        $sql = "SELECT COUNT(DISTINCT ac.conversation_id) as total
                FROM ai_conversations ac
                INNER JOIN conversations c ON c.id = ac.conversation_id
                WHERE ac.ai_agent_id = ?
                AND ac.status = 'active'
                AND c.status IN ('open', 'pending')";
        
        $result = Database::fetch($sql, [$agentId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Conversas resolvidas pelo agente (sem escalonar)
     */
    private static function getResolvedConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(DISTINCT ac.conversation_id) as total
                FROM ai_conversations ac
                INNER JOIN conversations c ON c.id = ac.conversation_id
                WHERE ac.ai_agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?
                AND c.status IN ('resolved', 'closed')
                AND ac.status = 'completed'";
        
        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Conversas escalonadas (IA foi para humano)
     */
    private static function getEscalatedConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(DISTINCT ac.conversation_id) as total
                FROM ai_conversations ac
                INNER JOIN conversations c ON c.id = ac.conversation_id
                WHERE ac.ai_agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?
                AND ac.status IN ('escalated', 'removed')";
        
        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Total de mensagens enviadas pelo agente
     */
    private static function getTotalMessages(int $agentId, string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM messages m
                INNER JOIN conversations c ON c.id = m.conversation_id
                WHERE m.ai_agent_id = ?
                AND m.sender_type = 'agent'
                AND c.created_at >= ?
                AND c.created_at <= ?";
        
        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Tokens e custo do agente
     */
    private static function getTokensAndCost(int $agentId, string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT 
                    COALESCE(SUM(ac.tokens_used), 0) as total_tokens,
                    COALESCE(SUM(ac.tokens_prompt), 0) as tokens_prompt,
                    COALESCE(SUM(ac.tokens_completion), 0) as tokens_completion,
                    COALESCE(SUM(ac.cost), 0) as total_cost
                FROM ai_conversations ac
                INNER JOIN conversations c ON c.id = ac.conversation_id
                WHERE ac.ai_agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?";
        
        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        
        return [
            'total_tokens' => (int)($result['total_tokens'] ?? 0),
            'tokens_prompt' => (int)($result['tokens_prompt'] ?? 0),
            'tokens_completion' => (int)($result['tokens_completion'] ?? 0),
            'total_cost' => round((float)($result['total_cost'] ?? 0), 4)
        ];
    }

    /**
     * Tempo médio de resposta do agente (em segundos)
     */
    private static function getAverageResponseTime(int $agentId, string $dateFrom, string $dateTo): ?float
    {
        $sql = "SELECT AVG(response_time_seconds) as avg_time
                FROM (
                    SELECT 
                        m1.conversation_id,
                        AVG(TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at)) as response_time_seconds
                    FROM messages m1
                    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                        AND m2.sender_type = 'agent'
                        AND m2.ai_agent_id = ?
                        AND m2.created_at > m1.created_at
                        AND m2.created_at = (
                            SELECT MIN(m3.created_at)
                            FROM messages m3
                            WHERE m3.conversation_id = m1.conversation_id
                            AND m3.sender_type = 'agent'
                            AND m3.ai_agent_id = ?
                            AND m3.created_at > m1.created_at
                        )
                    INNER JOIN conversations c ON c.id = m1.conversation_id
                    WHERE m1.sender_type = 'contact'
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                    GROUP BY m1.conversation_id
                ) as response_times";
        
        $result = Database::fetch($sql, [$agentId, $agentId, $dateFrom, $dateTo]);
        return $result && $result['avg_time'] !== null ? round((float)$result['avg_time'], 2) : null;
    }

    /**
     * Tools utilizadas pelo agente
     */
    private static function getToolsUsed(int $agentId, string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT ac.tools_used
                FROM ai_conversations ac
                INNER JOIN conversations c ON c.id = ac.conversation_id
                WHERE ac.ai_agent_id = ?
                AND ac.tools_used IS NOT NULL
                AND ac.tools_used != '[]'
                AND c.created_at >= ?
                AND c.created_at <= ?";
        
        $results = Database::fetchAll($sql, [$agentId, $dateFrom, $dateTo]);
        
        $toolsCounts = [];
        foreach ($results as $row) {
            $toolsData = is_string($row['tools_used']) 
                ? json_decode($row['tools_used'], true) 
                : ($row['tools_used'] ?? []);
            
            if (is_array($toolsData)) {
                foreach ($toolsData as $toolUsage) {
                    $toolName = $toolUsage['tool'] ?? 'unknown';
                    $toolsCounts[$toolName] = ($toolsCounts[$toolName] ?? 0) + 1;
                }
            }
        }
        
        // Ordenar por contagem (decrescente)
        arsort($toolsCounts);
        
        return $toolsCounts;
    }

    /**
     * Ranking de agentes de IA por performance
     */
    public static function getAIAgentsRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }

        $sql = "SELECT 
                    ai.id,
                    ai.name,
                    ai.agent_type,
                    ai.model,
                    ai.enabled,
                    COUNT(DISTINCT ac.conversation_id) as total_conversations,
                    COUNT(DISTINCT CASE WHEN c.status IN ('resolved', 'closed') AND ac.status = 'completed' 
                          THEN ac.conversation_id END) as resolved_conversations,
                    COUNT(DISTINCT CASE WHEN ac.status IN ('escalated', 'removed') 
                          THEN ac.conversation_id END) as escalated_conversations,
                    COALESCE(SUM(ac.tokens_used), 0) as total_tokens,
                    COALESCE(SUM(ac.cost), 0) as total_cost
                FROM ai_agents ai
                LEFT JOIN ai_conversations ac ON ai.id = ac.ai_agent_id
                LEFT JOIN conversations c ON ac.conversation_id = c.id 
                    AND c.created_at >= ? 
                    AND c.created_at <= ?
                WHERE ai.enabled = 1
                GROUP BY ai.id, ai.name, ai.agent_type, ai.model, ai.enabled
                ORDER BY total_conversations DESC
                LIMIT ?";
        
        $agents = Database::fetchAll($sql, [$dateFrom, $dateTo, $limit]);
        
        // Calcular taxas
        foreach ($agents as &$agent) {
            $total = (int)($agent['total_conversations'] ?? 0);
            $resolved = (int)($agent['resolved_conversations'] ?? 0);
            $escalated = (int)($agent['escalated_conversations'] ?? 0);
            
            $agent['resolution_rate'] = $total > 0 
                ? round(($resolved / $total) * 100, 2) 
                : 0;
            
            $agent['escalation_rate'] = $total > 0 
                ? round(($escalated / $total) * 100, 2) 
                : 0;
            
            $agent['total_cost'] = round((float)($agent['total_cost'] ?? 0), 4);
        }
        
        return $agents;
    }

    /**
     * Obter todos os agentes de IA com suas métricas
     */
    public static function getAllAIAgentsMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $sql = "SELECT id, name, agent_type, model, enabled 
                FROM ai_agents 
                ORDER BY name ASC";
        
        $agents = Database::fetchAll($sql);
        
        $result = [];
        foreach ($agents as $agent) {
            $metrics = self::getPerformanceStats($agent['id'], $dateFrom, $dateTo);
            if (!empty($metrics)) {
                $result[] = $metrics;
            }
        }
        
        return $result;
    }

    /**
     * Formatar tempo em segundos para formato legível
     */
    public static function formatTimeSeconds(?float $seconds): string
    {
        if ($seconds === null || $seconds === 0) {
            return '-';
        }
        
        if ($seconds < 60) {
            return intval(round($seconds)) . 's';
        }
        
        $minutes = intval(floor($seconds / 60));
        $secs = intval(round(fmod($seconds, 60)));
        
        if ($minutes < 60) {
            return $minutes . 'min ' . $secs . 's';
        }
        
        $hours = intval(floor($minutes / 60));
        $mins = $minutes % 60;
        
        return $hours . 'h ' . $mins . 'min';
    }

    /**
     * Comparação de performance: IA vs Humanos
     */
    public static function getComparisonStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d') . ' 23:59:59';
        
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        // Métricas de humanos
        $sqlHuman = "SELECT 
                        COUNT(DISTINCT c.id) as total_conversations,
                        COUNT(DISTINCT CASE WHEN c.status IN ('resolved', 'closed') THEN c.id END) as resolved,
                        AVG(TIMESTAMPDIFF(MINUTE, 
                            (SELECT MIN(m1.created_at) FROM messages m1 
                             WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                            (SELECT MIN(m2.created_at) FROM messages m2 
                             WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent' AND m2.ai_agent_id IS NULL)
                        )) as avg_first_response_minutes
                    FROM conversations c
                    WHERE c.created_at >= ? AND c.created_at <= ?
                    AND c.agent_id IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM messages m 
                        WHERE m.conversation_id = c.id 
                        AND m.sender_type = 'agent' 
                        AND m.ai_agent_id IS NULL
                    )";
        
        $humanStats = Database::fetch($sqlHuman, [$dateFrom, $dateTo]);
        
        // Métricas de IA
        $sqlAI = "SELECT 
                    COUNT(DISTINCT ac.conversation_id) as total_conversations,
                    COUNT(DISTINCT CASE WHEN c.status IN ('resolved', 'closed') AND ac.status = 'completed' 
                          THEN ac.conversation_id END) as resolved,
                    AVG(TIMESTAMPDIFF(SECOND, 
                        (SELECT MIN(m1.created_at) FROM messages m1 
                         WHERE m1.conversation_id = c.id AND m1.sender_type = 'contact'),
                        (SELECT MIN(m2.created_at) FROM messages m2 
                         WHERE m2.conversation_id = c.id AND m2.sender_type = 'agent' AND m2.ai_agent_id IS NOT NULL)
                    )) as avg_first_response_seconds
                FROM ai_conversations ac
                INNER JOIN conversations c ON c.id = ac.conversation_id
                WHERE c.created_at >= ? AND c.created_at <= ?";
        
        $aiStats = Database::fetch($sqlAI, [$dateFrom, $dateTo]);
        
        $humanTotal = (int)($humanStats['total_conversations'] ?? 0);
        $humanResolved = (int)($humanStats['resolved'] ?? 0);
        $aiTotal = (int)($aiStats['total_conversations'] ?? 0);
        $aiResolved = (int)($aiStats['resolved'] ?? 0);
        
        return [
            'human' => [
                'total_conversations' => $humanTotal,
                'resolved_conversations' => $humanResolved,
                'resolution_rate' => $humanTotal > 0 ? round(($humanResolved / $humanTotal) * 100, 2) : 0,
                'avg_first_response_minutes' => round((float)($humanStats['avg_first_response_minutes'] ?? 0), 2),
                'avg_first_response_formatted' => AgentPerformanceService::formatTime($humanStats['avg_first_response_minutes'] ?? 0)
            ],
            'ai' => [
                'total_conversations' => $aiTotal,
                'resolved_conversations' => $aiResolved,
                'resolution_rate' => $aiTotal > 0 ? round(($aiResolved / $aiTotal) * 100, 2) : 0,
                'avg_first_response_seconds' => round((float)($aiStats['avg_first_response_seconds'] ?? 0), 2),
                'avg_first_response_formatted' => self::formatTimeSeconds($aiStats['avg_first_response_seconds'] ?? 0)
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
}

