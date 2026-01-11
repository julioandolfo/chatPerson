<?php
/**
 * Job: Agregar métricas de coaching em sumários diários
 * Executar diariamente via cron (ex: às 2h da manhã)
 * 
 * Crontab: 0 2 * * * cd /var/www/html && php public/scripts/aggregate-coaching-metrics.php >> storage/logs/coaching-metrics.log 2>&1
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Models\RealtimeCoachingHint;
use App\Models\CoachingAnalyticsSummary;
use App\Models\CoachingConversationImpact;
use App\Models\Conversation;
use App\Models\User;
use App\Helpers\Database;

echo "[" . date('Y-m-d H:i:s') . "] ⚙️  Iniciando agregação de métricas de coaching...\n";

try {
    // Processar ontem (pode ser ajustado para hoje se rodar no final do dia)
    $date = date('Y-m-d', strtotime('-1 day'));
    $dateStart = $date . ' 00:00:00';
    $dateEnd = $date . ' 23:59:59';
    
    echo "[" . date('Y-m-d H:i:s') . "] 📅 Processando data: {$date}\n";
    
    // Buscar todos os agentes que receberam hints no período
    $sql = "SELECT DISTINCT agent_id 
            FROM realtime_coaching_hints 
            WHERE created_at >= :date_start AND created_at <= :date_end";
    
    $agents = Database::fetchAll($sql, [
        'date_start' => $dateStart,
        'date_end' => $dateEnd
    ]);
    
    echo "[" . date('Y-m-d H:i:s') . "] 👥 Encontrados " . count($agents) . " agentes com atividade\n";
    
    foreach ($agents as $agent) {
        $agentId = $agent['agent_id'];
        
        echo "[" . date('Y-m-d H:i:s') . "] 🔄 Processando agente ID {$agentId}...\n";
        
        // Agregar métricas de hints
        $hintsSql = "SELECT 
                        COUNT(*) as total_hints,
                        SUM(CASE WHEN viewed_at IS NOT NULL THEN 1 ELSE 0 END) as hints_viewed,
                        SUM(CASE WHEN feedback = 'helpful' THEN 1 ELSE 0 END) as hints_helpful,
                        SUM(CASE WHEN feedback = 'not_helpful' THEN 1 ELSE 0 END) as hints_not_helpful,
                        SUM(CASE WHEN hint_type = 'objection' THEN 1 ELSE 0 END) as hints_objection,
                        SUM(CASE WHEN hint_type = 'opportunity' THEN 1 ELSE 0 END) as hints_opportunity,
                        SUM(CASE WHEN hint_type = 'buying_signal' THEN 1 ELSE 0 END) as hints_buying_signal,
                        SUM(CASE WHEN hint_type = 'negative_sentiment' THEN 1 ELSE 0 END) as hints_negative_sentiment,
                        SUM(CASE WHEN hint_type = 'closing_opportunity' THEN 1 ELSE 0 END) as hints_closing_opportunity,
                        SUM(CASE WHEN hint_type = 'escalation_needed' THEN 1 ELSE 0 END) as hints_escalation,
                        SUM(CASE WHEN hint_type = 'question' THEN 1 ELSE 0 END) as hints_question,
                        SUM(tokens_used) as total_tokens,
                        SUM(cost) as total_cost
                    FROM realtime_coaching_hints
                    WHERE agent_id = :agent_id
                    AND created_at >= :date_start AND created_at <= :date_end";
        
        $hintsData = Database::fetch($hintsSql, [
            'agent_id' => $agentId,
            'date_start' => $dateStart,
            'date_end' => $dateEnd
        ]);
        
        // Agregar métricas de impacto em conversas
        $impactSql = "SELECT 
                        COUNT(*) as conversations_with_hints,
                        SUM(CASE WHEN conversation_outcome = 'converted' THEN 1 ELSE 0 END) as conversations_converted,
                        SUM(suggestions_used) as suggestions_used,
                        SUM(sales_value) as sales_value_total,
                        AVG(avg_response_time_after) as avg_response_time,
                        AVG(conversion_time_minutes) as avg_conversion_time
                     FROM coaching_conversation_impact
                     WHERE agent_id = :agent_id
                     AND created_at >= :date_start AND created_at <= :date_end";
        
        $impactData = Database::fetch($impactSql, [
            'agent_id' => $agentId,
            'date_start' => $dateStart,
            'date_end' => $dateEnd
        ]) ?? [];
        
        // Calcular melhoria na taxa de conversão
        $conversionImprovement = 0;
        if (!empty($impactData['conversations_with_hints']) && $impactData['conversations_with_hints'] > 0) {
            $conversionRate = ($impactData['conversations_converted'] / $impactData['conversations_with_hints']) * 100;
            
            // Comparar com taxa base do agente (conversas sem coaching no período)
            $baseRateSql = "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                            FROM conversations
                            WHERE agent_id = :agent_id
                            AND created_at >= :date_start AND created_at <= :date_end
                            AND id NOT IN (
                                SELECT conversation_id FROM coaching_conversation_impact
                                WHERE agent_id = :agent_id
                            )";
            
            $baseRate = Database::fetch($baseRateSql, [
                'agent_id' => $agentId,
                'date_start' => $dateStart,
                'date_end' => $dateEnd
            ]);
            
            if ($baseRate && $baseRate['total'] > 0) {
                $baseConversionRate = ($baseRate['closed'] / $baseRate['total']) * 100;
                if ($baseConversionRate > 0) {
                    $conversionImprovement = round((($conversionRate - $baseConversionRate) / $baseConversionRate) * 100, 2);
                }
            }
        }
        
        // Criar/atualizar sumário diário
        $summaryData = [
            'agent_id' => $agentId,
            'period_type' => 'daily',
            'period_start' => $date,
            'period_end' => $date,
            'total_hints_received' => (int)($hintsData['total_hints'] ?? 0),
            'total_hints_viewed' => (int)($hintsData['hints_viewed'] ?? 0),
            'total_hints_helpful' => (int)($hintsData['hints_helpful'] ?? 0),
            'total_hints_not_helpful' => (int)($hintsData['hints_not_helpful'] ?? 0),
            'total_suggestions_used' => (int)($impactData['suggestions_used'] ?? 0),
            'hints_objection' => (int)($hintsData['hints_objection'] ?? 0),
            'hints_opportunity' => (int)($hintsData['hints_opportunity'] ?? 0),
            'hints_buying_signal' => (int)($hintsData['hints_buying_signal'] ?? 0),
            'hints_negative_sentiment' => (int)($hintsData['hints_negative_sentiment'] ?? 0),
            'hints_closing_opportunity' => (int)($hintsData['hints_closing_opportunity'] ?? 0),
            'hints_escalation' => (int)($hintsData['hints_escalation'] ?? 0),
            'hints_question' => (int)($hintsData['hints_question'] ?? 0),
            'conversations_with_hints' => (int)($impactData['conversations_with_hints'] ?? 0),
            'conversations_converted' => (int)($impactData['conversations_converted'] ?? 0),
            'conversion_rate_improvement' => $conversionImprovement,
            'avg_response_time_seconds' => (int)($impactData['avg_response_time'] ?? 0),
            'avg_conversation_duration_minutes' => (int)($impactData['avg_conversion_time'] ?? 0),
            'sales_value_total' => (float)($impactData['sales_value_total'] ?? 0),
            'total_cost' => (float)($hintsData['total_cost'] ?? 0),
            'total_tokens' => (int)($hintsData['total_tokens'] ?? 0)
        ];
        
        CoachingAnalyticsSummary::upsert($summaryData);
        
        echo "[" . date('Y-m-d H:i:s') . "] ✅ Agente {$agentId}: {$summaryData['total_hints_received']} hints, Taxa: " . 
             ($summaryData['total_hints_received'] > 0 ? round(($summaryData['total_hints_helpful'] / $summaryData['total_hints_received']) * 100, 1) : 0) . "%\n";
    }
    
    // Agregar sumários semanais e mensais (apenas aos domingos e último dia do mês)
    $dayOfWeek = date('w'); // 0 = domingo
    $lastDayOfMonth = date('t') == date('d');
    
    if ($dayOfWeek == 0) {
        echo "[" . date('Y-m-d H:i:s') . "] 📅 Agregando sumários semanais...\n";
        aggregateWeeklySummaries();
    }
    
    if ($lastDayOfMonth) {
        echo "[" . date('Y-m-d H:i:s') . "] 📅 Agregando sumários mensais...\n";
        aggregateMonthlySummaries();
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Agregação concluída com sucesso!\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Agregar sumários semanais
 */
function aggregateWeeklySummaries(): void
{
    $weekStart = date('Y-m-d', strtotime('monday last week'));
    $weekEnd = date('Y-m-d', strtotime('sunday last week'));
    
    $sql = "SELECT agent_id,
                   SUM(total_hints_received) as total_hints_received,
                   SUM(total_hints_viewed) as total_hints_viewed,
                   SUM(total_hints_helpful) as total_hints_helpful,
                   SUM(total_hints_not_helpful) as total_hints_not_helpful,
                   SUM(total_suggestions_used) as total_suggestions_used,
                   SUM(hints_objection) as hints_objection,
                   SUM(hints_opportunity) as hints_opportunity,
                   SUM(hints_buying_signal) as hints_buying_signal,
                   SUM(hints_negative_sentiment) as hints_negative_sentiment,
                   SUM(hints_closing_opportunity) as hints_closing_opportunity,
                   SUM(hints_escalation) as hints_escalation,
                   SUM(hints_question) as hints_question,
                   SUM(conversations_with_hints) as conversations_with_hints,
                   SUM(conversations_converted) as conversations_converted,
                   AVG(conversion_rate_improvement) as conversion_rate_improvement,
                   AVG(avg_response_time_seconds) as avg_response_time_seconds,
                   AVG(avg_conversation_duration_minutes) as avg_conversation_duration_minutes,
                   SUM(sales_value_total) as sales_value_total,
                   SUM(total_cost) as total_cost,
                   SUM(total_tokens) as total_tokens
            FROM coaching_analytics_summary
            WHERE period_type = 'daily'
            AND period_start >= :week_start AND period_end <= :week_end
            GROUP BY agent_id";
    
    $weeklySummaries = Database::fetchAll($sql, [
        'week_start' => $weekStart,
        'week_end' => $weekEnd
    ]);
    
    foreach ($weeklySummaries as $summary) {
        $summary['period_type'] = 'weekly';
        $summary['period_start'] = $weekStart;
        $summary['period_end'] = $weekEnd;
        
        CoachingAnalyticsSummary::upsert($summary);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ " . count($weeklySummaries) . " sumários semanais criados\n";
}

/**
 * Agregar sumários mensais
 */
function aggregateMonthlySummaries(): void
{
    $monthStart = date('Y-m-01', strtotime('last month'));
    $monthEnd = date('Y-m-t', strtotime('last month'));
    
    $sql = "SELECT agent_id,
                   SUM(total_hints_received) as total_hints_received,
                   SUM(total_hints_viewed) as total_hints_viewed,
                   SUM(total_hints_helpful) as total_hints_helpful,
                   SUM(total_hints_not_helpful) as total_hints_not_helpful,
                   SUM(total_suggestions_used) as total_suggestions_used,
                   SUM(hints_objection) as hints_objection,
                   SUM(hints_opportunity) as hints_opportunity,
                   SUM(hints_buying_signal) as hints_buying_signal,
                   SUM(hints_negative_sentiment) as hints_negative_sentiment,
                   SUM(hints_closing_opportunity) as hints_closing_opportunity,
                   SUM(hints_escalation) as hints_escalation,
                   SUM(hints_question) as hints_question,
                   SUM(conversations_with_hints) as conversations_with_hints,
                   SUM(conversations_converted) as conversations_converted,
                   AVG(conversion_rate_improvement) as conversion_rate_improvement,
                   AVG(avg_response_time_seconds) as avg_response_time_seconds,
                   AVG(avg_conversation_duration_minutes) as avg_conversation_duration_minutes,
                   SUM(sales_value_total) as sales_value_total,
                   SUM(total_cost) as total_cost,
                   SUM(total_tokens) as total_tokens
            FROM coaching_analytics_summary
            WHERE period_type = 'daily'
            AND period_start >= :month_start AND period_end <= :month_end
            GROUP BY agent_id";
    
    $monthlySummaries = Database::fetchAll($sql, [
        'month_start' => $monthStart,
        'month_end' => $monthEnd
    ]);
    
    foreach ($monthlySummaries as $summary) {
        $summary['period_type'] = 'monthly';
        $summary['period_start'] = $monthStart;
        $summary['period_end'] = $monthEnd;
        
        CoachingAnalyticsSummary::upsert($summary);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ " . count($monthlySummaries) . " sumários mensais criados\n";
}
