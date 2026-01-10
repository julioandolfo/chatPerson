<?php
/**
 * Service PerformanceReportService
 * Geração de relatórios de performance
 */

namespace App\Services;

use App\Models\AgentPerformanceAnalysis;
use App\Models\AgentPerformanceSummary;
use App\Helpers\Database;

class PerformanceReportService
{
    /**
     * Gerar relatório individual de conversa
     */
    public static function generateConversationReport(int $conversationId): ?array
    {
        $analysis = AgentPerformanceAnalysis::getByConversation($conversationId);
        
        if (!$analysis) {
            return null;
        }
        
        // Buscar informações adicionais
        $conversation = \App\Models\Conversation::find($conversationId);
        $agent = \App\Models\User::find($analysis['agent_id']);
        $contact = \App\Models\Contact::find($conversation['contact_id'] ?? 0);
        
        return [
            'analysis' => $analysis,
            'conversation' => $conversation,
            'agent' => $agent,
            'contact' => $contact,
            'strengths' => json_decode($analysis['strengths'] ?? '[]', true),
            'weaknesses' => json_decode($analysis['weaknesses'] ?? '[]', true),
            'suggestions' => json_decode($analysis['improvement_suggestions'] ?? '[]', true),
            'key_moments' => json_decode($analysis['key_moments'] ?? '[]', true)
        ];
    }
    
    /**
     * Gerar relatório de agente (período)
     */
    public static function generateAgentReport(int $agentId, string $dateFrom, string $dateTo): array
    {
        $agent = \App\Models\User::find($agentId);
        $averages = AgentPerformanceAnalysis::getAgentAverages($agentId, $dateFrom, $dateTo);
        $analyses = AgentPerformanceAnalysis::getByPeriod($dateFrom, $dateTo, $agentId);
        
        // Calcular evolução
        $evolution = self::calculateEvolution($agentId, $dateFrom, $dateTo);
        
        // Obter badges
        $badges = \App\Services\GamificationService::getBadgeStats($agentId);
        
        // Obter metas
        $goals = \App\Services\CoachingService::checkGoalsProgress($agentId);
        
        // Extrair pontos fortes e fracos mais comuns
        $allStrengths = [];
        $allWeaknesses = [];
        foreach ($analyses as $analysis) {
            $strengths = json_decode($analysis['strengths'] ?? '[]', true);
            $weaknesses = json_decode($analysis['weaknesses'] ?? '[]', true);
            $allStrengths = array_merge($allStrengths, $strengths);
            $allWeaknesses = array_merge($allWeaknesses, $weaknesses);
        }
        
        // Contar frequência e pegar top 5
        $strengthsCount = array_count_values($allStrengths);
        $weaknessesCount = array_count_values($allWeaknesses);
        arsort($strengthsCount);
        arsort($weaknessesCount);
        $topStrengths = array_slice(array_keys($strengthsCount), 0, 5);
        $topWeaknesses = array_slice(array_keys($weaknessesCount), 0, 5);
        
        return [
            'agent' => $agent,
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'averages' => $averages,
            'analyses' => $analyses,
            'evolution' => $evolution,
            'badges' => $badges,
            'goals' => $goals,
            'total_analyses' => count($analyses),
            'top_strengths' => $topStrengths,
            'top_weaknesses' => $topWeaknesses
        ];
    }
    
    /**
     * Gerar relatório de time
     */
    public static function generateTeamReport(string $dateFrom, string $dateTo, ?int $departmentId = null): array
    {
        $ranking = AgentPerformanceAnalysis::getAgentsRanking($dateFrom, $dateTo);
        $overallStats = AgentPerformanceAnalysis::getOverallStats($dateFrom, $dateTo);
        
        // Médias do time por dimensão
        $teamAverages = self::getTeamAveragesByDimension($dateFrom, $dateTo, $departmentId);
        
        // Top performers por dimensão
        $topPerformers = [];
        $dimensions = ['proactivity', 'objection_handling', 'rapport', 'closing_techniques', 'qualification'];
        
        foreach ($dimensions as $dimension) {
            $topPerformers[$dimension] = AgentPerformanceAnalysis::getTopPerformersInDimension($dimension, 3, $dateFrom, $dateTo);
        }
        
        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'ranking' => $ranking,
            'overall_stats' => $overallStats,
            'team_averages' => $teamAverages,
            'top_performers' => $topPerformers,
            'department_id' => $departmentId
        ];
    }
    
    /**
     * Calcular evolução do agente
     */
    private static function calculateEvolution(int $agentId, string $dateFrom, string $dateTo): array
    {
        // Dividir período em 2 metades
        $start = new \DateTime($dateFrom);
        $end = new \DateTime($dateTo);
        $diff = $start->diff($end)->days;
        $halfDiff = (int)($diff / 2);
        $midpoint = clone $start;
        $midpoint->modify("+{$halfDiff} days");
        
        $firstHalfEnd = $midpoint->format('Y-m-d');
        $secondHalfStart = $midpoint->modify('+1 day')->format('Y-m-d');
        
        $firstHalf = AgentPerformanceAnalysis::getAgentAverages($agentId, $dateFrom, $firstHalfEnd);
        $secondHalf = AgentPerformanceAnalysis::getAgentAverages($agentId, $secondHalfStart, $dateTo);
        
        if (!$firstHalf || !$secondHalf) {
            return [];
        }
        
        $evolution = [];
        $dimensions = [
            'proactivity', 'objection_handling', 'rapport', 'closing_techniques',
            'qualification', 'clarity', 'value_proposition', 'response_time',
            'follow_up', 'professionalism'
        ];
        
        foreach ($dimensions as $dimension) {
            $key = 'avg_' . $dimension;
            $first = (float)($firstHalf[$key] ?? 0);
            $second = (float)($secondHalf[$key] ?? 0);
            
            $evolution[$dimension] = [
                'first' => round($first, 2),
                'second' => round($second, 2),
                'change' => round($second - $first, 2),
                'percent' => $first > 0 ? round((($second - $first) / $first) * 100, 1) : 0
            ];
        }
        
        return $evolution;
    }
    
    /**
     * Obter médias do time por dimensão
     */
    private static function getTeamAveragesByDimension(string $dateFrom, string $dateTo, ?int $departmentId = null): array
    {
        $sql = "SELECT 
                    AVG(proactivity_score) as avg_proactivity,
                    AVG(objection_handling_score) as avg_objection_handling,
                    AVG(rapport_score) as avg_rapport,
                    AVG(closing_techniques_score) as avg_closing_techniques,
                    AVG(qualification_score) as avg_qualification,
                    AVG(clarity_score) as avg_clarity,
                    AVG(value_proposition_score) as avg_value_proposition,
                    AVG(response_time_score) as avg_response_time,
                    AVG(follow_up_score) as avg_follow_up,
                    AVG(professionalism_score) as avg_professionalism,
                    AVG(overall_score) as avg_overall
                FROM agent_performance_analysis apa";
        
        $params = [];
        
        if ($departmentId) {
            $sql .= " INNER JOIN users u ON u.id = apa.agent_id WHERE u.department_id = ?";
            $params[] = $departmentId;
            $sql .= " AND DATE(apa.analyzed_at) BETWEEN ? AND ?";
        } else {
            $sql .= " WHERE DATE(analyzed_at) BETWEEN ? AND ?";
        }
        
        $params[] = $dateFrom;
        $params[] = $dateTo;
        
        return Database::fetch($sql, $params) ?: [];
    }
    
    /**
     * Comparar múltiplos agentes
     */
    public static function compareAgents(array $agentIds, string $dateFrom, string $dateTo): array
    {
        $comparison = [];
        
        foreach ($agentIds as $agentId) {
            $agent = \App\Models\User::find($agentId);
            $averages = AgentPerformanceAnalysis::getAgentAverages($agentId, $dateFrom, $dateTo);
            
            if ($agent && $averages) {
                // Estruturar dimensões
                $dimensions = [
                    'proactivity' => $averages['avg_proactivity'] ?? 0,
                    'objection_handling' => $averages['avg_objection_handling'] ?? 0,
                    'rapport' => $averages['avg_rapport'] ?? 0,
                    'closing_techniques' => $averages['avg_closing_techniques'] ?? 0,
                    'qualification' => $averages['avg_qualification'] ?? 0,
                    'clarity' => $averages['avg_clarity'] ?? 0,
                    'value_proposition' => $averages['avg_value_proposition'] ?? 0,
                    'response_time' => $averages['avg_response_time'] ?? 0,
                    'follow_up' => $averages['avg_follow_up'] ?? 0,
                    'professionalism' => $averages['avg_professionalism'] ?? 0
                ];
                
                $comparison[] = [
                    'agent' => $agent,
                    'overall_score' => $averages['avg_overall'] ?? 0,
                    'dimensions' => $dimensions,
                    'total_analyses' => $averages['total_analyses'] ?? 0
                ];
            }
        }
        
        return $comparison;
    }
}
