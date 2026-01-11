<?php
/**
 * Service CoachingService
 * Sistema de coaching automático: metas, feedback, sugestões
 */

namespace App\Services;

use App\Models\AgentPerformanceGoal;
use App\Models\AgentPerformanceAnalysis;
use App\Helpers\Database;
use App\Helpers\Logger;

class CoachingService
{
    /**
     * Auto-criar metas baseado em análise
     */
    public static function autoCreateGoals(array $analysis): array
    {
        $agentId = $analysis['agent_id'];
        $createdGoals = [];
        
        try {
            // Buscar médias dos últimos 30 dias
            $dateFrom = date('Y-m-d', strtotime('-30 days'));
            $dateTo = date('Y-m-d');
            $averages = AgentPerformanceAnalysis::getAgentAverages($agentId, $dateFrom, $dateTo);
            
            if (!$averages) return [];
            
            // Identificar pontos fracos (nota < 3.5)
            $dimensions = [
                'proactivity' => 'Proatividade',
                'objection_handling' => 'Quebra de Objeções',
                'rapport' => 'Rapport',
                'closing_techniques' => 'Fechamento',
                'qualification' => 'Qualificação',
                'clarity' => 'Clareza',
                'value_proposition' => 'Valor',
                'response_time' => 'Tempo de Resposta',
                'follow_up' => 'Follow-up',
                'professionalism' => 'Profissionalismo'
            ];
            
            foreach ($dimensions as $key => $name) {
                $avgKey = 'avg_' . $key;
                $currentScore = (float)($averages[$avgKey] ?? 0);
                
                // Criar meta apenas se está abaixo de 3.5 e ainda não tem meta ativa
                if ($currentScore > 0 && $currentScore < 3.5) {
                    $hasActiveGoal = Database::fetch(
                        "SELECT COUNT(*) as count FROM agent_performance_goals 
                         WHERE agent_id = ? AND dimension = ? AND status = 'active'",
                        [$agentId, $key]
                    );
                    
                    if ($hasActiveGoal['count'] == 0) {
                        $targetScore = min(5.0, $currentScore + 1.0); // Meta: melhorar 1 ponto
                        $deadline = date('Y-m-d', strtotime('+60 days')); // 60 dias para atingir
                        
                        $goalData = [
                            'agent_id' => $agentId,
                            'dimension' => $key,
                            'current_score' => $currentScore,
                            'target_score' => $targetScore,
                            'deadline' => $deadline,
                            'status' => 'active',
                            'created_by' => null, // Sistema
                            'notes' => "Meta criada automaticamente baseada em análise de performance. Score atual: {$currentScore}"
                        ];
                        
                        $goalId = AgentPerformanceGoal::create($goalData);
                        $goalData['id'] = $goalId;
                        $createdGoals[] = $goalData;
                        
                        Logger::log("CoachingService - Meta criada para agente {$agentId}: {$name} ({$currentScore} → {$targetScore})");
                    }
                }
            }
            
        } catch (\Exception $e) {
            Logger::error("CoachingService::autoCreateGoals - Erro: " . $e->getMessage());
        }
        
        return $createdGoals;
    }
    
    /**
     * Enviar feedback para agente
     */
    public static function sendFeedback(array $analysis, array $conversation): bool
    {
        try {
            $agentId = $analysis['agent_id'];
            $overallScore = $analysis['overall_score'];
            
            // Decodificar JSONs
            $strengths = json_decode($analysis['strengths'] ?? '[]', true) ?: [];
            $weaknesses = json_decode($analysis['weaknesses'] ?? '[]', true) ?: [];
            $suggestions = json_decode($analysis['improvement_suggestions'] ?? '[]', true) ?: [];
            
            // Construir mensagem de feedback
            $message = self::buildFeedbackMessage($overallScore, $strengths, $weaknesses, $suggestions);
            
            // Enviar notificação (implementar quando tiver sistema de notificações)
            // NotificationService::send($agentId, 'feedback', $message);
            
            Logger::log("CoachingService - Feedback enviado para agente {$agentId}");
            
            return true;
            
        } catch (\Exception $e) {
            Logger::error("CoachingService::sendFeedback - Erro: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Construir mensagem de feedback
     */
    private static function buildFeedbackMessage(float $score, array $strengths, array $weaknesses, array $suggestions): string
    {
        $message = "📊 Análise de Performance\n\n";
        $message .= "Nota: " . number_format($score, 1) . "/5.0 ";
        $message .= $score >= 4.5 ? "⭐⭐⭐⭐⭐ Excelente!" : 
                    ($score >= 3.5 ? "⭐⭐⭐⭐ Bom trabalho!" : 
                    ($score >= 2.5 ? "⭐⭐⭐ Pode melhorar" : "⭐⭐ Precisa de atenção"));
        $message .= "\n\n";
        
        if (!empty($strengths)) {
            $message .= "✅ Pontos Fortes:\n";
            foreach ($strengths as $strength) {
                $message .= "• {$strength}\n";
            }
            $message .= "\n";
        }
        
        if (!empty($weaknesses)) {
            $message .= "⚠️ Pontos a Melhorar:\n";
            foreach ($weaknesses as $weakness) {
                $message .= "• {$weakness}\n";
            }
            $message .= "\n";
        }
        
        if (!empty($suggestions)) {
            $message .= "💡 Sugestões:\n";
            foreach ($suggestions as $suggestion) {
                $message .= "• {$suggestion}\n";
            }
        }
        
        return $message;
    }
    
    /**
     * Verificar progresso das metas
     */
    public static function checkGoalsProgress(int $agentId): array
    {
        return AgentPerformanceGoal::checkProgress($agentId);
    }
    
    /**
     * Atualizar status de metas
     */
    public static function updateGoalsStatus(int $agentId): array
    {
        $updated = [];
        
        try {
            $progress = self::checkGoalsProgress($agentId);
            
            foreach ($progress as $goal) {
                $currentScore = (float)($goal['current_score_now'] ?? 0);
                $targetScore = (float)$goal['target_score'];
                $deadline = $goal['deadline'];
                
                // Verificar se atingiu a meta
                if ($currentScore >= $targetScore) {
                    $sql = "UPDATE agent_performance_goals 
                            SET status = 'completed', completed_at = NOW() 
                            WHERE id = ? AND status = 'active'";
                    Database::execute($sql, [$goal['id']]);
                    $updated[] = ['id' => $goal['id'], 'status' => 'completed'];
                    Logger::log("CoachingService - Meta {$goal['id']} completada pelo agente {$agentId}!");
                }
                // Verificar se passou do prazo
                elseif ($deadline && strtotime($deadline) < time()) {
                    $sql = "UPDATE agent_performance_goals 
                            SET status = 'failed' 
                            WHERE id = ? AND status = 'active'";
                    Database::execute($sql, [$goal['id']]);
                    $updated[] = ['id' => $goal['id'], 'status' => 'failed'];
                }
            }
            
        } catch (\Exception $e) {
            Logger::error("CoachingService::updateGoalsStatus - Erro: " . $e->getMessage());
        }
        
        return $updated;
    }
    
    /**
     * Criar meta manual
     */
    public static function createGoal(int $agentId, string $dimension, float $targetScore, string $deadline, ?int $createdBy = null, ?string $notes = null, ?string $startDate = null): ?int
    {
        try {
            // Buscar score atual
            $dateFrom = date('Y-m-d', strtotime('-30 days'));
            $dateTo = date('Y-m-d');
            $averages = AgentPerformanceAnalysis::getAgentAverages($agentId, $dateFrom, $dateTo);
            
            $avgKey = 'avg_' . $dimension;
            $currentScore = (float)($averages[$avgKey] ?? 0);
            
            $goalData = [
                'agent_id' => $agentId,
                'dimension' => $dimension,
                'current_score' => $currentScore,
                'target_score' => $targetScore,
                'start_date' => $startDate ?: date('Y-m-d'),
                'end_date' => $deadline,
                'status' => 'active',
                'created_by' => $createdBy,
                'feedback' => $notes
            ];
            
            return AgentPerformanceGoal::create($goalData);
            
        } catch (\Exception $e) {
            Logger::error("CoachingService::createGoal - Erro: " . $e->getMessage());
            return null;
        }
    }
}
