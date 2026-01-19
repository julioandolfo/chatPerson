<?php
/**
 * Service CoachingMetricsService
 * Cálculo de métricas e KPIs de coaching
 */

namespace App\Services;

use App\Models\RealtimeCoachingHint;
use App\Models\CoachingAnalyticsSummary;
use App\Models\CoachingConversationImpact;
use App\Models\Conversation;
use App\Helpers\Database;

class CoachingMetricsService
{
    /**
     * KPI 1: Taxa de Aceitação de Hints
     * (hints_helpful / hints_total) * 100
     * Meta: > 70%
     */
    public static function getAcceptanceRate(
        ?int $agentId = null, 
        string $period = 'week'
    ): array {
        [$dateFrom, $dateTo] = self::getPeriodDates($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_hints,
                    SUM(CASE WHEN feedback = 'helpful' THEN 1 ELSE 0 END) as helpful_hints,
                    SUM(CASE WHEN feedback = 'not_helpful' THEN 1 ELSE 0 END) as not_helpful_hints,
                    SUM(CASE WHEN feedback IS NULL THEN 1 ELSE 0 END) as no_feedback
                FROM realtime_coaching_hints
                WHERE created_at >= :date_from AND created_at <= :date_to";
        
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        
        if ($agentId) {
            $sql .= " AND agent_id = :agent_id";
            $params['agent_id'] = $agentId;
        }
        
        $result = Database::fetch($sql, $params);
        
        $rate = $result['total_hints'] > 0 
            ? round(($result['helpful_hints'] / $result['total_hints']) * 100, 2) 
            : 0;
        
        return [
            'total_hints' => (int)$result['total_hints'],
            'helpful_hints' => (int)$result['helpful_hints'],
            'not_helpful_hints' => (int)$result['not_helpful_hints'],
            'no_feedback' => (int)$result['no_feedback'],
            'acceptance_rate' => $rate,
            'status' => $rate >= 70 ? 'good' : ($rate >= 50 ? 'warning' : 'critical'),
            'target' => 70
        ];
    }
    
    /**
     * KPI 2: ROI do Coaching
     * ROI = ((retorno - custo) / custo) * 100
     * Meta: > 1000%
     */
    public static function getROI(
        ?int $agentId = null, 
        string $period = 'month'
    ): array {
        [$dateFrom, $dateTo] = self::getPeriodDates($period);
        
        // Custo total (hints gerados)
        $costSql = "SELECT SUM(cost) as total_cost, SUM(tokens_used) as total_tokens
                    FROM realtime_coaching_hints
                    WHERE created_at >= :date_from AND created_at <= :date_to";
        
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        
        if ($agentId) {
            $costSql .= " AND agent_id = :agent_id";
            $params['agent_id'] = $agentId;
        }
        
        $costData = Database::fetch($costSql, $params);
        $totalCost = (float)($costData['total_cost'] ?? 0);
        
        // Retorno (vendas de conversas com hints úteis)
        $returnSql = "SELECT SUM(sales_value) as total_sales, COUNT(*) as converted_conversations
                      FROM coaching_conversation_impact
                      WHERE conversation_outcome = 'converted'
                      AND hints_helpful > 0
                      AND created_at >= :date_from AND created_at <= :date_to";
        
        if ($agentId) {
            $returnSql .= " AND agent_id = :agent_id";
        }
        
        $returnData = Database::fetch($returnSql, $params);
        $totalReturn = (float)($returnData['total_sales'] ?? 0);
        $convertedConversations = (int)($returnData['converted_conversations'] ?? 0);
        
        $roi = $totalCost > 0 
            ? round((($totalReturn - $totalCost) / $totalCost) * 100, 2) 
            : 0;
        
        return [
            'total_cost' => $totalCost,
            'total_return' => $totalReturn,
            'net_profit' => $totalReturn - $totalCost,
            'roi_percentage' => $roi,
            'converted_conversations' => $convertedConversations,
            'avg_sale_value' => $convertedConversations > 0 ? round($totalReturn / $convertedConversations, 2) : 0,
            'cost_per_conversion' => $convertedConversations > 0 ? round($totalCost / $convertedConversations, 4) : 0,
            'status' => $roi >= 1000 ? 'excellent' : ($roi >= 500 ? 'good' : ($roi >= 200 ? 'ok' : 'poor')),
            'target' => 1000
        ];
    }
    
    /**
     * KPI 3: Impacto na Conversão
     * Taxa conversão COM coaching vs SEM coaching
     * Meta: +20% taxa conversão
     */
    public static function getConversionImpact(?int $agentId = null, string $period = 'month'): array
    {
        [$dateFrom, $dateTo] = self::getPeriodDates($period);
        
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        
        // Conversas COM coaching
        $withCoachingSql = "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN conversation_outcome = 'converted' THEN 1 ELSE 0 END) as converted,
                                AVG(conversion_time_minutes) as avg_time
                            FROM coaching_conversation_impact
                            WHERE total_hints > 0
                            AND created_at >= :date_from AND created_at <= :date_to";
        
        if ($agentId) {
            $withCoachingSql .= " AND agent_id = :agent_id";
            $params['agent_id'] = $agentId;
        }
        
        $withCoaching = Database::fetch($withCoachingSql, $params);
        
        // Conversas SEM coaching (do mesmo agente/período)
        $withoutCoachingSql = "SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as converted
                                FROM conversations
                                WHERE created_at >= :date_from AND created_at <= :date_to
                                AND id NOT IN (
                                    SELECT conversation_id FROM coaching_conversation_impact 
                                    WHERE total_hints > 0
                                )";
        
        if ($agentId) {
            $withoutCoachingSql .= " AND agent_id = :agent_id";
        }
        
        $withoutCoaching = Database::fetch($withoutCoachingSql, $params);
        
        $conversionWithCoaching = $withCoaching['total'] > 0 
            ? round(($withCoaching['converted'] / $withCoaching['total']) * 100, 2) 
            : 0;
        
        $conversionWithoutCoaching = $withoutCoaching['total'] > 0 
            ? round(($withoutCoaching['converted'] / $withoutCoaching['total']) * 100, 2) 
            : 0;
        
        $improvement = $conversionWithoutCoaching > 0
            ? round((($conversionWithCoaching - $conversionWithoutCoaching) / $conversionWithoutCoaching) * 100, 2)
            : 0;
        
        return [
            'with_coaching' => [
                'total_conversations' => (int)$withCoaching['total'],
                'converted' => (int)$withCoaching['converted'],
                'conversion_rate' => $conversionWithCoaching,
                'avg_time_minutes' => round((float)$withCoaching['avg_time'], 1)
            ],
            'without_coaching' => [
                'total_conversations' => (int)$withoutCoaching['total'],
                'converted' => (int)$withoutCoaching['converted'],
                'conversion_rate' => $conversionWithoutCoaching
            ],
            'improvement_percentage' => $improvement,
            'status' => $improvement >= 20 ? 'excellent' : ($improvement >= 10 ? 'good' : ($improvement >= 5 ? 'ok' : 'needs_improvement')),
            'target' => 20
        ];
    }
    
    /**
     * KPI 4: Velocidade de Aprendizado
     * Taxa de melhoria semana a semana
     */
    public static function getLearningSpeed(int $agentId): array
    {
        $weeks = [];
        
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = date('Y-m-d', strtotime("-{$i} weeks monday"));
            $weekEnd = date('Y-m-d', strtotime("-{$i} weeks sunday"));
            
            $sql = "SELECT 
                        COUNT(*) as total_hints,
                        SUM(CASE WHEN feedback = 'helpful' THEN 1 ELSE 0 END) as helpful_hints
                    FROM realtime_coaching_hints
                    WHERE agent_id = :agent_id
                    AND created_at >= :week_start AND created_at <= :week_end";
            
            $result = Database::fetch($sql, [
                'agent_id' => $agentId,
                'week_start' => $weekStart,
                'week_end' => $weekEnd
            ]);
            
            $rate = $result['total_hints'] > 0 
                ? round(($result['helpful_hints'] / $result['total_hints']) * 100, 2) 
                : 0;
            
            $weeks[] = [
                'week' => $weekStart,
                'total_hints' => (int)$result['total_hints'],
                'helpful_hints' => (int)$result['helpful_hints'],
                'acceptance_rate' => $rate
            ];
        }
        
        // Calcular tendência
        $rates = array_column($weeks, 'acceptance_rate');
        $trend = count($rates) > 1 ? $rates[count($rates)-1] - $rates[0] : 0;
        
        return [
            'weeks' => $weeks,
            'trend' => round($trend, 2),
            'status' => $trend > 5 ? 'improving' : ($trend < -5 ? 'declining' : 'stable'),
            'weeks_to_80_percent' => self::estimateWeeksTo80Percent($rates)
        ];
    }
    
    /**
     * KPI 5: Qualidade dos Hints (IA)
     * Precisão, tempo de resposta, cache hit rate
     * Meta: > 85% precisão
     */
    public static function getHintQuality(string $period = 'week'): array
    {
        [$dateFrom, $dateTo] = self::getPeriodDates($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_hints,
                    SUM(CASE WHEN feedback = 'helpful' THEN 1 ELSE 0 END) as helpful_hints,
                    AVG(tokens_used) as avg_tokens,
                    AVG(cost) as avg_cost,
                    COUNT(DISTINCT conversation_id) as unique_conversations
                FROM realtime_coaching_hints
                WHERE created_at >= :date_from AND created_at <= :date_to";
        
        $result = Database::fetch($sql, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
        
        $precision = $result['total_hints'] > 0 
            ? round(($result['helpful_hints'] / $result['total_hints']) * 100, 2) 
            : 0;
        
        return [
            'total_hints' => (int)$result['total_hints'],
            'precision_rate' => $precision,
            'avg_tokens_per_hint' => round((float)$result['avg_tokens'], 0),
            'avg_cost_per_hint' => round((float)$result['avg_cost'], 4),
            'unique_conversations' => (int)$result['unique_conversations'],
            'hints_per_conversation' => $result['unique_conversations'] > 0 
                ? round($result['total_hints'] / $result['unique_conversations'], 1) 
                : 0,
            'status' => $precision >= 85 ? 'excellent' : ($precision >= 70 ? 'good' : ($precision >= 50 ? 'ok' : 'poor')),
            'target' => 85
        ];
    }
    
    /**
     * KPI 6: Uso de Sugestões
     * % de sugestões clicadas/usadas
     * Meta: > 40% uso
     */
    public static function getSuggestionUsage(?int $agentId = null, string $period = 'week'): array
    {
        [$dateFrom, $dateTo] = self::getPeriodDates($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_hints,
                    SUM(CASE WHEN suggestions IS NOT NULL THEN 1 ELSE 0 END) as hints_with_suggestions
                FROM realtime_coaching_hints
                WHERE created_at >= :date_from AND created_at <= :date_to";
        
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        
        if ($agentId) {
            $sql .= " AND agent_id = :agent_id";
            $params['agent_id'] = $agentId;
        }
        
        $hintData = Database::fetch($sql, $params);
        
        // Sugestões usadas (do coaching_conversation_impact)
        $usageSql = "SELECT SUM(suggestions_used) as total_used
                     FROM coaching_conversation_impact
                     WHERE created_at >= :date_from AND created_at <= :date_to";
        
        if ($agentId) {
            $usageSql .= " AND agent_id = :agent_id";
        }
        
        $usageData = Database::fetch($usageSql, $params);
        
        $totalSuggestions = (int)$hintData['hints_with_suggestions'];
        $totalUsed = (int)($usageData['total_used'] ?? 0);
        
        $usageRate = $totalSuggestions > 0 
            ? round(($totalUsed / $totalSuggestions) * 100, 2) 
            : 0;
        
        return [
            'total_hints' => (int)$hintData['total_hints'],
            'hints_with_suggestions' => $totalSuggestions,
            'suggestions_used' => $totalUsed,
            'usage_rate' => $usageRate,
            'status' => $usageRate >= 40 ? 'excellent' : ($usageRate >= 25 ? 'good' : ($usageRate >= 15 ? 'ok' : 'poor')),
            'target' => 40
        ];
    }
    
    /**
     * Dashboard resumido com todos os KPIs
     */
    public static function getDashboardSummary(?int $agentId = null, string $period = 'week'): array
    {
        return [
            'acceptance_rate' => self::getAcceptanceRate($agentId, $period),
            'roi' => self::getROI($agentId, $period === 'week' ? 'month' : $period),
            'conversion_impact' => self::getConversionImpact($agentId, $period === 'week' ? 'month' : $period),
            'hint_quality' => self::getHintQuality($period),
            'suggestion_usage' => self::getSuggestionUsage($agentId, $period),
            'period' => $period,
            'agent_id' => $agentId
        ];
    }
    
    /**
     * Helper: Obter datas do período
     */
    private static function getPeriodDates(string $period): array
    {
        return match($period) {
            'today' => [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')],
            'week' => [date('Y-m-d 00:00:00', strtotime('monday this week')), date('Y-m-d 23:59:59')],
            'month' => [date('Y-m-01 00:00:00'), date('Y-m-d 23:59:59')],
            'quarter' => [date('Y-m-d 00:00:00', strtotime('-3 months')), date('Y-m-d 23:59:59')],
            'year' => [date('Y-01-01 00:00:00'), date('Y-m-d 23:59:59')],
            default => [date('Y-m-d 00:00:00', strtotime('monday this week')), date('Y-m-d 23:59:59')]
        };
    }
    
    /**
     * Estimar semanas até atingir 80% de aceitação
     */
    private static function estimateWeeksTo80Percent(array $rates): ?int
    {
        if (empty($rates)) return null;
        
        $currentRate = end($rates);
        if ($currentRate >= 80) return 0;
        
        // Calcular tendência média
        $improvements = [];
        for ($i = 1; $i < count($rates); $i++) {
            $improvements[] = $rates[$i] - $rates[$i-1];
        }
        
        if (empty($improvements)) return null;
        
        $avgImprovement = array_sum($improvements) / count($improvements);
        if ($avgImprovement <= 0) return null;
        
        $pointsNeeded = 80 - $currentRate;
        $weeksNeeded = ceil($pointsNeeded / $avgImprovement);
        
        return max(1, min($weeksNeeded, 52)); // Entre 1 e 52 semanas
    }
    
    /**
     * Buscar conversas analisadas com métricas de coaching e performance
     * @param int|null $agentId - Filtrar por agente
     * @param string $period - Período (today, week, month)
     * @param int $page - Página atual
     * @param int $perPage - Itens por página
     * @return array ['conversations' => [...], 'total' => int, 'page' => int, 'per_page' => int, 'total_pages' => int]
     */
    public static function getAnalyzedConversations(
        ?int $agentId = null,
        string $period = 'week',
        int $page = 1,
        int $perPage = 10
    ): array {
        [$dateFrom, $dateTo] = self::getPeriodDates($period);
        
        // Contar total de conversas
        $countSql = "SELECT COUNT(DISTINCT c.id) as total
            FROM conversations c
            LEFT JOIN coaching_conversation_impact cci ON c.id = cci.conversation_id
            LEFT JOIN agent_performance_analysis apa ON c.id = apa.conversation_id
            WHERE (cci.id IS NOT NULL OR apa.id IS NOT NULL)
            AND c.created_at >= :date_from 
            AND c.created_at <= :date_to";
        
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];
        
        if ($agentId) {
            $countSql .= " AND c.agent_id = :agent_id";
            $params['agent_id'] = $agentId;
        }
        
        $countResult = Database::fetch($countSql, $params);
        $total = (int)($countResult['total'] ?? 0);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        
        // Buscar conversas com todas as métricas
        $sql = "SELECT 
                c.id,
                c.agent_id,
                c.contact_id,
                c.status,
                c.channel,
                c.created_at,
                c.updated_at,
                c.resolved_at,
                
                -- Dados do agente
                u.name as agent_name,
                u.avatar as agent_avatar,
                
                -- Dados do contato
                ct.name as contact_name,
                ct.phone as contact_phone,
                
                -- Coaching Impact
                cci.total_hints,
                cci.hints_helpful,
                cci.hints_not_helpful,
                cci.suggestions_used,
                cci.conversation_outcome,
                cci.sales_value,
                cci.performance_improvement_score,
                cci.avg_response_time_before,
                cci.avg_response_time_after,
                
                -- Performance Analysis (10 dimensões)
                apa.overall_score,
                apa.proactivity_score,
                apa.objection_handling_score,
                apa.rapport_score,
                apa.closing_techniques_score,
                apa.qualification_score,
                apa.clarity_score,
                apa.value_proposition_score,
                apa.response_time_score,
                apa.follow_up_score,
                apa.professionalism_score,
                apa.strengths,
                apa.weaknesses,
                apa.detailed_analysis,
                apa.improvement_suggestions,
                
                -- Count hints
                (SELECT COUNT(*) FROM realtime_coaching_hints rch 
                 WHERE rch.conversation_id = c.id) as total_coaching_hints
                
            FROM conversations c
            LEFT JOIN users u ON c.agent_id = u.id
            LEFT JOIN contacts ct ON c.contact_id = ct.id
            LEFT JOIN coaching_conversation_impact cci ON c.id = cci.conversation_id
            LEFT JOIN agent_performance_analysis apa ON c.id = apa.conversation_id
            WHERE (cci.id IS NOT NULL OR apa.id IS NOT NULL)
            AND c.created_at >= :date_from 
            AND c.created_at <= :date_to";
        
        if ($agentId) {
            $sql .= " AND c.agent_id = :agent_id";
        }
        
        $sql .= " ORDER BY c.updated_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        $conversations = Database::fetchAll($sql, $params);
        
        // Processar conversas para adicionar hints detalhados
        foreach ($conversations as &$conversation) {
            // Buscar hints da conversa
            $hintsSql = "SELECT 
                    id,
                    hint_type,
                    hint_text,
                    suggestions,
                    feedback,
                    viewed_at,
                    created_at
                FROM realtime_coaching_hints
                WHERE conversation_id = :conversation_id
                ORDER BY created_at DESC";
            
            $conversation['coaching_hints'] = Database::fetchAll($hintsSql, [
                'conversation_id' => $conversation['id']
            ]);
            
            // Parse JSON fields
            $conversation['strengths'] = $conversation['strengths'] 
                ? json_decode($conversation['strengths'], true) 
                : [];
            $conversation['weaknesses'] = $conversation['weaknesses'] 
                ? json_decode($conversation['weaknesses'], true) 
                : [];
            
            // Formatar valores
            $conversation['sales_value_formatted'] = number_format((float)($conversation['sales_value'] ?? 0), 2, ',', '.');
            $conversation['overall_score_formatted'] = number_format((float)($conversation['overall_score'] ?? 0), 2);
            $conversation['performance_improvement_score_formatted'] = number_format((float)($conversation['performance_improvement_score'] ?? 0), 2);
            
            // Status badge
            $conversation['status_badge'] = match($conversation['status']) {
                'open' => ['class' => 'success', 'text' => 'Aberta'],
                'pending' => ['class' => 'warning', 'text' => 'Pendente'],
                'resolved' => ['class' => 'primary', 'text' => 'Resolvida'],
                'closed' => ['class' => 'secondary', 'text' => 'Fechada'],
                default => ['class' => 'light', 'text' => ucfirst($conversation['status'])]
            };
            
            // Outcome badge
            $conversation['outcome_badge'] = match($conversation['conversation_outcome']) {
                'converted' => ['class' => 'success', 'text' => '✓ Convertida', 'icon' => 'check-circle'],
                'closed' => ['class' => 'primary', 'text' => 'Fechada', 'icon' => 'check'],
                'escalated' => ['class' => 'warning', 'text' => 'Escalada', 'icon' => 'arrow-up'],
                'abandoned' => ['class' => 'danger', 'text' => 'Abandonada', 'icon' => 'cross-circle'],
                default => ['class' => 'light', 'text' => $conversation['conversation_outcome'] ?? 'N/A', 'icon' => 'question']
            };
        }
        
        return [
            'conversations' => $conversations,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_more' => $page < $totalPages
        ];
    }
}
