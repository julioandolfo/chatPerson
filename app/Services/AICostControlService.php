<?php
/**
 * Service AICostControlService
 * Controle de custos e rate limiting para agentes de IA
 */

namespace App\Services;

use App\Models\AIAgent;
use App\Models\AIConversation;
use App\Models\Setting;
use App\Helpers\Database;

class AICostControlService
{
    /**
     * Verificar se agente pode processar mensagem (rate limiting e custos)
     */
    public static function canProcessMessage(int $agentId): array
    {
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            return ['allowed' => false, 'reason' => 'Agente não encontrado'];
        }

        // Verificar rate limiting
        $rateLimitCheck = self::checkRateLimit($agentId, $agent);
        if (!$rateLimitCheck['allowed']) {
            return $rateLimitCheck;
        }

        // Verificar limite de custo mensal
        $costLimitCheck = self::checkMonthlyCostLimit($agentId, $agent);
        if (!$costLimitCheck['allowed']) {
            return $costLimitCheck;
        }

        return ['allowed' => true];
    }

    /**
     * Verificar rate limiting por agente
     */
    private static function checkRateLimit(int $agentId, array $agent): array
    {
        $settings = is_string($agent['settings']) 
            ? json_decode($agent['settings'], true) 
            : ($agent['settings'] ?? []);

        $rateLimits = $settings['rate_limits'] ?? null;
        
        if (!$rateLimits || !isset($rateLimits['enabled']) || !$rateLimits['enabled']) {
            return ['allowed' => true]; // Sem limite configurado
        }

        $period = $rateLimits['period'] ?? 'hour'; // hour, day, month
        $maxMessages = $rateLimits['max_messages'] ?? null;
        $maxTokens = $rateLimits['max_tokens'] ?? null;

        // Verificar limite de mensagens
        if ($maxMessages !== null) {
            $currentMessages = self::getMessageCountInPeriod($agentId, $period);
            if ($currentMessages >= $maxMessages) {
                return [
                    'allowed' => false,
                    'reason' => "Limite de mensagens atingido ({$currentMessages}/{$maxMessages} no período {$period})"
                ];
            }
        }

        // Verificar limite de tokens
        if ($maxTokens !== null) {
            $currentTokens = self::getTokenCountInPeriod($agentId, $period);
            if ($currentTokens >= $maxTokens) {
                return [
                    'allowed' => false,
                    'reason' => "Limite de tokens atingido ({$currentTokens}/{$maxTokens} no período {$period})"
                ];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Verificar limite de custo mensal
     */
    private static function checkMonthlyCostLimit(int $agentId, array $agent): array
    {
        $settings = is_string($agent['settings']) 
            ? json_decode($agent['settings'], true) 
            : ($agent['settings'] ?? []);

        $costLimits = $settings['cost_limits'] ?? null;
        
        if (!$costLimits || !isset($costLimits['enabled']) || !$costLimits['enabled']) {
            return ['allowed' => true]; // Sem limite configurado
        }

        $monthlyLimit = $costLimits['monthly_limit'] ?? null;
        $autoDisable = $costLimits['auto_disable'] ?? false;
        $alertThreshold = $costLimits['alert_threshold'] ?? null; // Percentual para alertar

        if ($monthlyLimit === null) {
            return ['allowed' => true];
        }

        // Obter custo do mês atual
        $currentMonthCost = self::getMonthlyCost($agentId);
        
        // Verificar se excedeu limite
        if ($currentMonthCost >= $monthlyLimit) {
            // Desativar agente se configurado
            if ($autoDisable && $agent['enabled']) {
                AIAgent::update($agentId, ['enabled' => false]);
                self::createCostAlert($agentId, 'limit_exceeded', [
                    'monthly_limit' => $monthlyLimit,
                    'current_cost' => $currentMonthCost
                ]);
                
                return [
                    'allowed' => false,
                    'reason' => "Limite de custo mensal excedido (R$ {$currentMonthCost} / R$ {$monthlyLimit}). Agente desativado automaticamente."
                ];
            }
            
            return [
                'allowed' => false,
                'reason' => "Limite de custo mensal excedido (R$ {$currentMonthCost} / R$ {$monthlyLimit})"
            ];
        }

        // Verificar se está próximo do limite (para alerta)
        if ($alertThreshold !== null) {
            $threshold = ($monthlyLimit * $alertThreshold) / 100;
            if ($currentMonthCost >= $threshold && $currentMonthCost < $monthlyLimit) {
                // Verificar se já alertou neste mês
                $lastAlert = self::getLastCostAlert($agentId, 'threshold_warning');
                if (!$lastAlert || strtotime($lastAlert['created_at']) < strtotime('first day of this month')) {
                    self::createCostAlert($agentId, 'threshold_warning', [
                        'monthly_limit' => $monthlyLimit,
                        'current_cost' => $currentMonthCost,
                        'threshold' => $threshold,
                        'percentage' => round(($currentMonthCost / $monthlyLimit) * 100, 2)
                    ]);
                }
            }
        }

        return ['allowed' => true];
    }

    /**
     * Obter número de mensagens processadas no período
     */
    private static function getMessageCountInPeriod(int $agentId, string $period): int
    {
        $dateCondition = self::getDateConditionForPeriod($period);
        
        $sql = "SELECT COUNT(*) as total
                FROM ai_conversations ac
                WHERE ac.ai_agent_id = ?
                AND ac.created_at >= " . $dateCondition;
        
        $result = Database::fetch($sql, [$agentId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Obter número de tokens usados no período
     */
    private static function getTokenCountInPeriod(int $agentId, string $period): int
    {
        $dateCondition = self::getDateConditionForPeriod($period);
        
        $sql = "SELECT COALESCE(SUM(ac.tokens_used), 0) as total
                FROM ai_conversations ac
                WHERE ac.ai_agent_id = ?
                AND ac.created_at >= " . $dateCondition;
        
        $result = Database::fetch($sql, [$agentId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Obter custo mensal do agente
     */
    public static function getMonthlyCost(int $agentId, ?string $month = null): float
    {
        if ($month === null) {
            $month = date('Y-m');
        }
        
        $sql = "SELECT COALESCE(SUM(ac.cost), 0) as total_cost
                FROM ai_conversations ac
                WHERE ac.ai_agent_id = ?
                AND DATE_FORMAT(ac.created_at, '%Y-%m') = ?";
        
        $result = Database::fetch($sql, [$agentId, $month]);
        return (float)($result['total_cost'] ?? 0);
    }

    /**
     * Obter custo total do agente (todas as conversas)
     */
    public static function getTotalCost(int $agentId): float
    {
        $sql = "SELECT COALESCE(SUM(ac.cost), 0) as total_cost
                FROM ai_conversations ac
                WHERE ac.ai_agent_id = ?";
        
        $result = Database::fetch($sql, [$agentId]);
        return (float)($result['total_cost'] ?? 0);
    }

    /**
     * Obter estatísticas de custo do agente
     */
    public static function getCostStats(int $agentId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_conversations,
                    COALESCE(SUM(ac.cost), 0) as total_cost,
                    COALESCE(AVG(ac.cost), 0) as avg_cost_per_conversation,
                    COALESCE(SUM(ac.tokens_used), 0) as total_tokens,
                    COALESCE(AVG(ac.tokens_used), 0) as avg_tokens_per_conversation,
                    MIN(ac.cost) as min_cost,
                    MAX(ac.cost) as max_cost
                FROM ai_conversations ac
                WHERE ac.ai_agent_id = ?";
        
        $params = [$agentId];
        
        if ($startDate) {
            $sql .= " AND ac.created_at >= ?";
            $params[] = $startDate . " 00:00:00";
        }
        
        if ($endDate) {
            $sql .= " AND ac.created_at <= ?";
            $params[] = $endDate . " 23:59:59";
        }
        
        return Database::fetch($sql, $params) ?? [];
    }

    /**
     * Criar alerta de custo
     */
    private static function createCostAlert(int $agentId, string $type, array $data): void
    {
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            return;
        }

        // Criar notificação para administradores
        try {
            if (class_exists('\App\Services\NotificationService')) {
                $sql = "SELECT * FROM users WHERE role IN ('admin', 'super_admin') AND status = 'active'";
                $adminUsers = Database::fetchAll($sql);
                
                foreach ($adminUsers as $admin) {
                    $message = self::formatCostAlertMessage($type, $agent, $data);
                    \App\Services\NotificationService::create([
                        'user_id' => $admin['id'],
                        'type' => 'cost_alert',
                        'title' => 'Alerta de Custo - Agente de IA',
                        'message' => $message,
                        'data' => json_encode([
                            'agent_id' => $agentId,
                            'alert_type' => $type,
                            'data' => $data
                        ])
                    ]);
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar alerta de custo: " . $e->getMessage());
        }

        // Log do alerta
        error_log(sprintf(
            "ALERTA DE CUSTO - Agente: %s (ID: %d) - Tipo: %s - Dados: %s",
            $agent['name'],
            $agentId,
            $type,
            json_encode($data)
        ));
    }

    /**
     * Obter último alerta de custo
     */
    private static function getLastCostAlert(int $agentId, string $type): ?array
    {
        try {
            if (class_exists('\App\Models\Notification')) {
                $sql = "SELECT * FROM notifications 
                        WHERE type = 'cost_alert' 
                        AND JSON_EXTRACT(data, '$.agent_id') = ?
                        AND JSON_EXTRACT(data, '$.alert_type') = ?
                        ORDER BY created_at DESC 
                        LIMIT 1";
                
                return Database::fetch($sql, [$agentId, $type]);
            }
        } catch (\Exception $e) {
            // Ignorar erro
        }
        
        return null;
    }

    /**
     * Formatar mensagem de alerta de custo
     */
    private static function formatCostAlertMessage(string $type, array $agent, array $data): string
    {
        switch ($type) {
            case 'limit_exceeded':
                return sprintf(
                    "⚠️ O agente de IA '%s' excedeu o limite de custo mensal!\n\n" .
                    "Limite configurado: R$ %.2f\n" .
                    "Custo atual: R$ %.2f\n\n" .
                    "O agente foi desativado automaticamente.",
                    $agent['name'],
                    $data['monthly_limit'] ?? 0,
                    $data['current_cost'] ?? 0
                );
            
            case 'threshold_warning':
                return sprintf(
                    "⚠️ Atenção: O agente de IA '%s' está próximo do limite de custo mensal!\n\n" .
                    "Limite configurado: R$ %.2f\n" .
                    "Custo atual: R$ %.2f (%.1f%% do limite)\n\n" .
                    "Considere revisar o uso ou aumentar o limite.",
                    $agent['name'],
                    $data['monthly_limit'] ?? 0,
                    $data['current_cost'] ?? 0,
                    $data['percentage'] ?? 0
                );
            
            default:
                return "Alerta de custo para o agente '{$agent['name']}'";
        }
    }

    /**
     * Obter condição SQL para período
     */
    private static function getDateConditionForPeriod(string $period): string
    {
        switch ($period) {
            case 'hour':
                return "(NOW() - INTERVAL 1 HOUR)";
            case 'day':
                return "(NOW() - INTERVAL 1 DAY)";
            case 'month':
                return "(NOW() - INTERVAL 1 MONTH)";
            default:
                return "(NOW() - INTERVAL 1 HOUR)";
        }
    }

    /**
     * Verificar e processar alertas de custo para todos os agentes
     */
    public static function checkAllAgentsCosts(): void
    {
        $sql = "SELECT * FROM ai_agents WHERE enabled = TRUE";
        $agents = Database::fetchAll($sql);
        
        foreach ($agents as $agent) {
            try {
                self::checkMonthlyCostLimit($agent['id'], $agent);
            } catch (\Exception $e) {
                error_log("Erro ao verificar custos do agente {$agent['id']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Reativar agente após resetar limite (início do mês)
     */
    public static function resetMonthlyLimits(): void
    {
        // Buscar agentes desativados por limite de custo
        $sql = "SELECT * FROM ai_agents WHERE enabled = FALSE";
        $disabledAgents = Database::fetchAll($sql);
        
        foreach ($disabledAgents as $agent) {
            $settings = is_string($agent['settings']) 
                ? json_decode($agent['settings'], true) 
                : ($agent['settings'] ?? []);
            
            $costLimits = $settings['cost_limits'] ?? null;
            
            if ($costLimits && isset($costLimits['auto_disable']) && $costLimits['auto_disable']) {
                // Verificar se já passou para o próximo mês
                $lastAlert = self::getLastCostAlert($agent['id'], 'limit_exceeded');
                
                if ($lastAlert) {
                    $alertMonth = date('Y-m', strtotime($lastAlert['created_at']));
                    $currentMonth = date('Y-m');
                    
                    // Se mudou de mês, reativar agente
                    if ($alertMonth !== $currentMonth) {
                        AIAgent::update($agent['id'], ['enabled' => true]);
                        error_log("Agente {$agent['name']} (ID: {$agent['id']}) reativado após reset mensal");
                    }
                }
            }
        }
    }
}

