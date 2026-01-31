<?php
/**
 * Model Api4ComCallAnalysis
 * Análises de performance de chamadas telefônicas
 */

namespace App\Models;

use App\Helpers\Database;

class Api4ComCallAnalysis extends Model
{
    protected static string $table = 'api4com_call_analysis';
    protected static array $fillable = [
        'call_id', 'agent_id', 'conversation_id',
        'transcription', 'transcription_language', 'transcription_duration', 'transcription_cost',
        'summary', 'call_outcome', 'call_type',
        'score_opening', 'score_tone', 'score_listening', 'score_objection_handling',
        'score_value_proposition', 'score_closing', 'score_qualification',
        'score_control', 'score_professionalism', 'score_empathy', 'overall_score',
        'strengths', 'weaknesses', 'suggestions', 'key_moments', 'detailed_analysis',
        'client_sentiment', 'client_objections', 'client_interests',
        'model_used', 'analysis_cost', 'tokens_used', 'processing_time_ms',
        'status', 'error_message'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar análise por call_id
     */
    public static function findByCallId(int $callId): ?array
    {
        return self::whereFirst('call_id', '=', $callId);
    }

    /**
     * Buscar análises por agente
     */
    public static function getByAgent(int $agentId, int $limit = 50): array
    {
        $sql = "SELECT a.*, c.to_number, c.duration as call_duration, c.recording_url
                FROM api4com_call_analysis a
                JOIN api4com_calls c ON a.call_id = c.id
                WHERE a.agent_id = ? AND a.status = 'completed'
                ORDER BY a.created_at DESC
                LIMIT ?";
        return Database::fetchAll($sql, [$agentId, $limit]);
    }

    /**
     * Buscar chamadas pendentes de análise
     */
    public static function getPendingCalls(int $limit = 10): array
    {
        $sql = "SELECT c.* 
                FROM api4com_calls c
                LEFT JOIN api4com_call_analysis a ON c.id = a.call_id
                WHERE c.recording_url IS NOT NULL 
                AND c.recording_url != ''
                AND c.status = 'ended'
                AND c.duration > 10
                AND a.id IS NULL
                ORDER BY c.created_at DESC
                LIMIT ?";
        return Database::fetchAll($sql, [$limit]);
    }

    /**
     * Estatísticas gerais de análises
     */
    public static function getStats(?string $dateFrom = null, ?string $dateTo = null, ?int $agentId = null): array
    {
        $params = [];
        $whereConditions = ["status = 'completed'"];
        
        if ($dateFrom) {
            $whereConditions[] = "created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $whereConditions[] = "created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($agentId) {
            $whereConditions[] = "agent_id = ?";
            $params[] = $agentId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $sql = "SELECT 
                    COUNT(*) as total_analyzed,
                    AVG(overall_score) as avg_overall_score,
                    AVG(score_opening) as avg_opening,
                    AVG(score_tone) as avg_tone,
                    AVG(score_listening) as avg_listening,
                    AVG(score_objection_handling) as avg_objection_handling,
                    AVG(score_value_proposition) as avg_value_proposition,
                    AVG(score_closing) as avg_closing,
                    AVG(score_qualification) as avg_qualification,
                    AVG(score_control) as avg_control,
                    AVG(score_professionalism) as avg_professionalism,
                    AVG(score_empathy) as avg_empathy,
                    SUM(CASE WHEN call_outcome = 'positive' THEN 1 ELSE 0 END) as positive_calls,
                    SUM(CASE WHEN call_outcome = 'negative' THEN 1 ELSE 0 END) as negative_calls,
                    SUM(CASE WHEN call_outcome = 'neutral' THEN 1 ELSE 0 END) as neutral_calls,
                    SUM(CASE WHEN call_outcome = 'followup_needed' THEN 1 ELSE 0 END) as followup_calls,
                    SUM(transcription_cost + analysis_cost) as total_cost
                FROM api4com_call_analysis {$whereClause}";
        
        $result = Database::fetch($sql, $params);
        
        return [
            'total_analyzed' => (int)($result['total_analyzed'] ?? 0),
            'avg_overall_score' => round((float)($result['avg_overall_score'] ?? 0), 1),
            'dimensions' => [
                'opening' => round((float)($result['avg_opening'] ?? 0), 1),
                'tone' => round((float)($result['avg_tone'] ?? 0), 1),
                'listening' => round((float)($result['avg_listening'] ?? 0), 1),
                'objection_handling' => round((float)($result['avg_objection_handling'] ?? 0), 1),
                'value_proposition' => round((float)($result['avg_value_proposition'] ?? 0), 1),
                'closing' => round((float)($result['avg_closing'] ?? 0), 1),
                'qualification' => round((float)($result['avg_qualification'] ?? 0), 1),
                'control' => round((float)($result['avg_control'] ?? 0), 1),
                'professionalism' => round((float)($result['avg_professionalism'] ?? 0), 1),
                'empathy' => round((float)($result['avg_empathy'] ?? 0), 1),
            ],
            'outcomes' => [
                'positive' => (int)($result['positive_calls'] ?? 0),
                'negative' => (int)($result['negative_calls'] ?? 0),
                'neutral' => (int)($result['neutral_calls'] ?? 0),
                'followup_needed' => (int)($result['followup_calls'] ?? 0),
            ],
            'total_cost' => round((float)($result['total_cost'] ?? 0), 4),
        ];
    }

    /**
     * Ranking de agentes por score
     */
    public static function getAgentRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $params = [];
        $whereConditions = ["a.status = 'completed'"];
        
        if ($dateFrom) {
            $whereConditions[] = "a.created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $whereConditions[] = "a.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        $params[] = $limit;
        
        $sql = "SELECT 
                    a.agent_id,
                    u.name as agent_name,
                    u.avatar as agent_avatar,
                    COUNT(*) as total_calls,
                    AVG(a.overall_score) as avg_score,
                    SUM(CASE WHEN a.call_outcome = 'positive' THEN 1 ELSE 0 END) as positive_calls,
                    SUM(CASE WHEN a.call_outcome = 'negative' THEN 1 ELSE 0 END) as negative_calls,
                    (SUM(CASE WHEN a.call_outcome = 'positive' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as success_rate
                FROM api4com_call_analysis a
                JOIN users u ON a.agent_id = u.id
                {$whereClause}
                GROUP BY a.agent_id, u.name, u.avatar
                ORDER BY avg_score DESC
                LIMIT ?";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Análises recentes
     */
    public static function getRecent(int $limit = 10): array
    {
        $sql = "SELECT a.*, 
                       c.to_number, c.duration as call_duration, c.recording_url,
                       u.name as agent_name
                FROM api4com_call_analysis a
                JOIN api4com_calls c ON a.call_id = c.id
                LEFT JOIN users u ON a.agent_id = u.id
                WHERE a.status = 'completed'
                ORDER BY a.created_at DESC
                LIMIT ?";
        return Database::fetchAll($sql, [$limit]);
    }

    /**
     * Formatar label do outcome
     */
    public static function getOutcomeLabel(string $outcome): string
    {
        $labels = [
            'positive' => 'Positivo',
            'negative' => 'Negativo',
            'neutral' => 'Neutro',
            'followup_needed' => 'Requer Follow-up'
        ];
        return $labels[$outcome] ?? 'Desconhecido';
    }

    /**
     * Formatar cor do outcome
     */
    public static function getOutcomeColor(string $outcome): string
    {
        $colors = [
            'positive' => 'success',
            'negative' => 'danger',
            'neutral' => 'secondary',
            'followup_needed' => 'warning'
        ];
        return $colors[$outcome] ?? 'secondary';
    }

    /**
     * Formatar label do score
     */
    public static function getScoreLabel(float $score): string
    {
        if ($score >= 4.5) return 'Excelente';
        if ($score >= 4.0) return 'Muito Bom';
        if ($score >= 3.5) return 'Bom';
        if ($score >= 3.0) return 'Regular';
        if ($score >= 2.0) return 'Precisa Melhorar';
        return 'Crítico';
    }

    /**
     * Formatar cor do score
     */
    public static function getScoreColor(float $score): string
    {
        if ($score >= 4.5) return 'success';
        if ($score >= 4.0) return 'primary';
        if ($score >= 3.5) return 'info';
        if ($score >= 3.0) return 'warning';
        return 'danger';
    }

    /**
     * Labels das dimensões
     */
    public static function getDimensionLabels(): array
    {
        return [
            'opening' => 'Abertura/Apresentação',
            'tone' => 'Tom de Voz',
            'listening' => 'Escuta Ativa',
            'objection_handling' => 'Quebra de Objeções',
            'value_proposition' => 'Proposta de Valor',
            'closing' => 'Técnicas de Fechamento',
            'qualification' => 'Qualificação',
            'control' => 'Controle da Conversa',
            'professionalism' => 'Profissionalismo',
            'empathy' => 'Empatia/Rapport'
        ];
    }
}
