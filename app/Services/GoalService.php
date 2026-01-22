<?php
/**
 * Service GoalService
 * Gerenciamento e cálculo de progresso de metas
 */

namespace App\Services;

use App\Models\Goal;
use App\Models\GoalProgress;
use App\Models\GoalAchievement;
use App\Models\GoalAlert;
use App\Models\GoalBonusTier;
use App\Models\GoalBonusEarned;
use App\Models\GoalBonusCondition;
use App\Helpers\Database;
use App\Helpers\Validator;
use App\Helpers\Logger;

class GoalService
{
    /**
     * Criar nova meta
     */
    public static function create(array $data): int
    {
        // Validação
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'target_type' => 'required|string',
            'target_value' => 'required|numeric',
            'period_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        // Validar target_id se não for global
        if ($data['target_type'] !== 'global' && empty($data['target_id'])) {
            throw new \InvalidArgumentException('target_id é obrigatório para metas não globais');
        }
        
        // Validar datas
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            throw new \InvalidArgumentException('Data final deve ser maior que data inicial');
        }
        
        // Criar meta
        $goalId = Goal::create($data);
        
        // Calcular progresso inicial
        self::calculateProgress($goalId);
        
        Logger::info('Meta criada: ' . json_encode(['goal_id' => $goalId, 'name' => $data['name']]), 'goals.log');
        
        return $goalId;
    }
    
    /**
     * Atualizar meta
     */
    public static function update(int $id, array $data): bool
    {
        $goal = Goal::find($id);
        if (!$goal) {
            throw new \InvalidArgumentException('Meta não encontrada');
        }
        
        // Validar datas se fornecidas
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
                throw new \InvalidArgumentException('Data final deve ser maior que data inicial');
            }
        }
        
        $updated = Goal::update($id, $data);
        
        // Recalcular progresso
        if ($updated) {
            self::calculateProgress($id);
            Logger::info('Meta atualizada: ' . json_encode(['goal_id' => $id]), 'goals.log');
        }
        
        return $updated;
    }
    
    /**
     * Deletar meta
     */
    public static function delete(int $id): bool
    {
        $goal = Goal::find($id);
        if (!$goal) {
            return false;
        }
        
        Logger::info('Meta deletada: ' . json_encode(['goal_id' => $id, 'name' => $goal['name']]), 'goals.log');
        return Goal::delete($id);
    }
    
    /**
     * Calcular progresso de uma meta
     */
    public static function calculateProgress(int $goalId): array
    {
        $goal = Goal::find($goalId);
        if (!$goal) {
            throw new \InvalidArgumentException('Meta não encontrada');
        }
        
        // Obter valor atual baseado no tipo de meta
        $currentValue = self::getCurrentValue($goal);
        
        // Calcular porcentagem
        $percentage = $goal['target_value'] > 0 
            ? ($currentValue / $goal['target_value']) * 100 
            : 0;
        
        // Calcular projeção se habilitado
        $projection = null;
        if ($goal['enable_projection'] ?? true) {
            $projection = self::calculateProjection($goal, $currentValue, $percentage);
        }
        
        // Determinar status
        $status = self::determineStatus($percentage, $goal);
        
        // Determinar flag
        $flagStatus = self::determineFlagStatus($percentage, $goal);
        
        // Salvar progresso com projeção
        self::saveProgressWithProjection($goalId, $currentValue, $percentage, $status, $projection, $flagStatus);
        
        // Verificar se atingiu a meta
        if ($percentage >= 100 && !GoalAchievement::isAchieved($goalId)) {
            self::recordAchievement($goal, $currentValue, $percentage);
        }
        
        // Calcular e registrar bonificações (se habilitado)
        if ($goal['enable_bonus'] ?? false) {
            self::calculateAndRecordBonus($goal, $percentage);
        }
        
        // Gerar alertas se necessário
        if ($goal['alert_on_risk'] ?? true) {
            self::checkAndCreateAlerts($goal, $percentage, $projection);
        }
        
        return [
            'goal_id' => $goalId,
            'current_value' => $currentValue,
            'target_value' => $goal['target_value'],
            'percentage' => round($percentage, 2),
            'status' => $status,
            'flag_status' => $flagStatus,
            'projection' => $projection
        ];
    }
    
    /**
     * Calcular projeção de atingimento
     */
    private static function calculateProjection(array $goal, float $currentValue, float $percentage): array
    {
        $startDate = new \DateTime($goal['start_date']);
        $endDate = new \DateTime($goal['end_date']);
        $today = new \DateTime();
        
        // Garantir que today não ultrapasse end_date
        if ($today > $endDate) {
            $today = clone $endDate;
        }
        
        // Garantir que today não seja anterior a start_date
        if ($today < $startDate) {
            $today = clone $startDate;
        }
        
        // Calcular dias
        $daysTotal = $startDate->diff($endDate)->days + 1;
        $daysElapsed = $startDate->diff($today)->days + 1;
        $daysRemaining = max(0, $today->diff($endDate)->days);
        
        // Calcular % esperado para este momento
        $expectedPercentage = ($daysElapsed / $daysTotal) * 100;
        
        // Calcular projeção linear
        $dailyAverage = $daysElapsed > 0 ? ($currentValue / $daysElapsed) : 0;
        $projectedValue = $dailyAverage * $daysTotal;
        $projectedPercentage = $goal['target_value'] > 0 
            ? ($projectedValue / $goal['target_value']) * 100 
            : 0;
        
        // Verificar se está no caminho certo
        $isOnTrack = $percentage >= ($expectedPercentage * 0.95); // Tolerância de 5%
        
        // Calcular desvio
        $deviation = $percentage - $expectedPercentage;
        
        return [
            'days_total' => $daysTotal,
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'expected_percentage' => round($expectedPercentage, 2),
            'projected_value' => round($projectedValue, 2),
            'projected_percentage' => round($projectedPercentage, 2),
            'is_on_track' => $isOnTrack,
            'deviation' => round($deviation, 2),
            'needs_daily' => $daysRemaining > 0 
                ? round(($goal['target_value'] - $currentValue) / $daysRemaining, 2)
                : 0
        ];
    }

    /**
     * Determinar flag baseado no percentual e thresholds da meta
     */
    private static function determineFlagStatus(float $percentage, array $goal): string
    {
        $critical = $goal['flag_critical_threshold'] ?? 70.0;
        $warning = $goal['flag_warning_threshold'] ?? 85.0;
        $good = $goal['flag_good_threshold'] ?? 95.0;

        if ($percentage >= 100) {
            return 'excellent';
        }
        if ($percentage >= $good) {
            return 'good';
        }
        if ($percentage >= $warning) {
            return 'warning';
        }
        if ($percentage >= $critical) {
            return 'warning';
        }
        return 'critical';
    }
    
    /**
     * Salvar progresso com projeção
     */
    private static function saveProgressWithProjection(
        int $goalId, 
        float $currentValue, 
        float $percentage, 
        string $status,
        ?array $projection,
        string $flagStatus
    ): void {
        $date = date('Y-m-d');
        
        $existing = Database::fetch(
            "SELECT id FROM goal_progress WHERE goal_id = ? AND date = ?",
            [$goalId, $date]
        );
        
        if ($existing) {
            // Atualizar
            $sql = "UPDATE goal_progress SET 
                    current_value = ?, 
                    percentage = ?, 
                    status = ?,
                    days_elapsed = ?,
                    days_total = ?,
                    expected_percentage = ?,
                    projection_percentage = ?,
                    projection_value = ?,
                    is_on_track = ?,
                    flag_status = ?,
                    calculated_at = NOW() 
                    WHERE goal_id = ? AND date = ?";
            
            Database::execute($sql, [
                $currentValue,
                $percentage,
                $status,
                $projection ? $projection['days_elapsed'] : null,
                $projection ? $projection['days_total'] : null,
                $projection ? $projection['expected_percentage'] : null,
                $projection ? $projection['projected_percentage'] : null,
                $projection ? $projection['projected_value'] : null,
                $projection ? ($projection['is_on_track'] ? 1 : 0) : null,
                $flagStatus,
                $goalId,
                $date
            ]);
        } else {
            // Inserir
            $sql = "INSERT INTO goal_progress (
                    goal_id, date, current_value, percentage, status,
                    days_elapsed, days_total, expected_percentage,
                    projection_percentage, projection_value, is_on_track, flag_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            Database::execute($sql, [
                $goalId,
                $date,
                $currentValue,
                $percentage,
                $status,
                $projection ? $projection['days_elapsed'] : null,
                $projection ? $projection['days_total'] : null,
                $projection ? $projection['expected_percentage'] : null,
                $projection ? $projection['projected_percentage'] : null,
                $projection ? $projection['projected_value'] : null,
                $projection ? ($projection['is_on_track'] ? 1 : 0) : null,
                $flagStatus
            ]);
        }
    }
    
    /**
     * Verificar e criar alertas
     */
    private static function checkAndCreateAlerts(array $goal, float $percentage, ?array $projection): void
    {
        // Alerta de meta crítica
        $criticalThreshold = $goal['flag_critical_threshold'] ?? 70.0;
        if ($percentage < $criticalThreshold && $percentage > 0) {
            $daysRemaining = $projection ? $projection['days_remaining'] : '?';
            GoalAlert::createAlert(
                $goal['id'],
                'critical',
                'critical',
                "Meta em situação crítica! Apenas {$percentage}% atingido. {$daysRemaining} dias restantes.",
                ['percentage' => $percentage, 'threshold' => $criticalThreshold]
            );
        }
        
        // Alerta de fora do ritmo
        if ($projection && !$projection['is_on_track'] && $percentage < 95) {
            $deviation = abs($projection['deviation']);
            GoalAlert::createAlert(
                $goal['id'],
                'off_track',
                'warning',
                "Fora do ritmo esperado! Desvio de {$deviation}%. Esperado: {$projection['expected_percentage']}%, Atual: {$percentage}%.",
                $projection
            );
        }
        
        // Alerta de risco de não atingir
        if ($projection && $projection['projected_percentage'] < 100 && $percentage < 90) {
            GoalAlert::createAlert(
                $goal['id'],
                'at_risk',
                'warning',
                "Risco de não atingir meta! Projeção atual: {$projection['projected_percentage']}%.",
                $projection
            );
        }
    }
    
    /**
     * Obter valor atual baseado no tipo de meta
     */
    private static function getCurrentValue(array $goal): float
    {
        $targetType = $goal['target_type'];
        $targetId = $goal['target_id'];
        $startDate = $goal['start_date'];
        $endDate = $goal['end_date'];
        $type = $goal['type'];

        // Evitar erro caso integração WooCommerce não esteja instalada
        if (in_array($type, ['revenue', 'average_ticket', 'conversion_rate', 'sales_count'], true)
            && !self::tableExists('woocommerce_order_cache')
        ) {
            Logger::warning(
                "Meta '{$type}': tabela 'woocommerce_order_cache' não encontrada. Valor 0 aplicado.",
                'goals.log'
            );
            return 0;
        }
        
        // Determinar IDs dos agentes baseado no target_type
        $agentIds = self::getTargetAgentIds($targetType, $targetId);
        
        if (empty($agentIds)) {
            return 0;
        }
        
        // Calcular valor baseado no tipo de meta
        switch ($type) {
            case 'revenue':
                return self::calculateRevenue($agentIds, $startDate, $endDate);
                
            case 'average_ticket':
                return self::calculateAverageTicket($agentIds, $startDate, $endDate);
                
            case 'conversion_rate':
                return self::calculateConversionRate($agentIds, $startDate, $endDate);
                
            case 'sales_count':
                return self::calculateSalesCount($agentIds, $startDate, $endDate);
                
            case 'conversations_count':
                return self::calculateConversationsCount($agentIds, $startDate, $endDate);
                
            case 'resolution_rate':
                return self::calculateResolutionRate($agentIds, $startDate, $endDate);
                
            case 'response_time':
                return self::calculateResponseTime($agentIds, $startDate, $endDate);
                
            case 'csat_score':
                return self::calculateCSAT($agentIds, $startDate, $endDate);
                
            case 'messages_sent':
                return self::calculateMessagesSent($agentIds, $startDate, $endDate);
                
            case 'sla_compliance':
                return self::calculateSLACompliance($agentIds, $startDate, $endDate);
                
            case 'first_response_time':
                return self::calculateFirstResponseTime($agentIds, $startDate, $endDate);
                
            case 'resolution_time':
                return self::calculateResolutionTime($agentIds, $startDate, $endDate);
                
            default:
                return 0;
        }
    }

    /**
     * Verificar se uma tabela existe no banco
     */
    private static function tableExists(string $table): bool
    {
        $db = Database::getInstance();
        $quoted = $db->quote($table);
        $result = Database::fetch("SHOW TABLES LIKE {$quoted}");
        return !empty($result);
    }
    
    /**
     * Obter IDs dos agentes baseado no target_type
     */
    private static function getTargetAgentIds(string $targetType, ?int $targetId): array
    {
        switch ($targetType) {
            case 'individual':
                return $targetId ? [$targetId] : [];
                
            case 'team':
                if (!$targetId) return [];
                $members = Database::fetchAll(
                    "SELECT user_id FROM team_members WHERE team_id = ?",
                    [$targetId]
                );
                return array_column($members, 'user_id');
                
            case 'department':
                if (!$targetId) return [];
                // Usar tabela de relacionamento agent_departments
                $agents = Database::fetchAll(
                    "SELECT DISTINCT ad.user_id 
                     FROM agent_departments ad
                     INNER JOIN users u ON ad.user_id = u.id
                     WHERE ad.department_id = ? AND u.status = 'active'",
                    [$targetId]
                );
                return array_column($agents, 'user_id');
                
            case 'global':
                $users = Database::fetchAll(
                    "SELECT id FROM users WHERE status = 'active' 
                     AND role IN ('super_admin', 'admin', 'supervisor', 'senior_agent', 'agent', 'junior_agent')"
                );
                return array_column($users, 'id');
                
            default:
                return [];
        }
    }
    
    /**
     * Calcular faturamento total
     */
    private static function calculateRevenue(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        $sellerIds = self::getWooCommerceSellerIds($agentIds);
        if (empty($sellerIds)) return 0;

        $statusList = self::getValidWooCommerceStatuses();
        $sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count($statusList), '?'));
        $params = array_merge($sellerIds, [$startDate, $endDate], $statusList);

        $sql = "SELECT COALESCE(SUM(oc.order_total), 0) as revenue
                FROM woocommerce_order_cache oc
                WHERE oc.seller_id IN ({$sellerPlaceholders})
                AND oc.order_date BETWEEN ? AND ?
                AND oc.order_status IN ({$statusPlaceholders})";

        $result = Database::fetch($sql, $params);
        return (float) ($result['revenue'] ?? 0);
    }
    
    /**
     * Calcular ticket médio
     */
    private static function calculateAverageTicket(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        $sellerIds = self::getWooCommerceSellerIds($agentIds);
        if (empty($sellerIds)) return 0;

        $statusList = self::getValidWooCommerceStatuses();
        $sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count($statusList), '?'));
        $params = array_merge($sellerIds, [$startDate, $endDate], $statusList);

        $sql = "SELECT COALESCE(AVG(oc.order_total), 0) as avg_ticket
                FROM woocommerce_order_cache oc
                WHERE oc.seller_id IN ({$sellerPlaceholders})
                AND oc.order_date BETWEEN ? AND ?
                AND oc.order_status IN ({$statusPlaceholders})";

        $result = Database::fetch($sql, $params);
        return (float) ($result['avg_ticket'] ?? 0);
    }
    
    /**
     * Calcular taxa de conversão
     */
    private static function calculateConversionRate(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);

        // Total de conversas
        $sqlTotal = "SELECT COUNT(DISTINCT c.id) as total
                     FROM conversations c
                     INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                     WHERE ca.agent_id IN ($placeholders)
                     AND c.created_at BETWEEN ? AND ?";

        $total = Database::fetch($sqlTotal, $params);
        $totalConversations = (float) ($total['total'] ?? 0);
        
        if ($totalConversations == 0) return 0;

        $sellerIds = self::getWooCommerceSellerIds($agentIds);
        if (empty($sellerIds)) return 0;

        $statusList = self::getValidWooCommerceStatuses();
        $sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count($statusList), '?'));
        $conversionParams = array_merge($sellerIds, [$startDate, $endDate], $statusList);

        // Conversas com venda (pedidos válidos no cache)
        $sqlConverted = "SELECT COUNT(DISTINCT oc.order_id) as converted
                         FROM woocommerce_order_cache oc
                         WHERE oc.seller_id IN ({$sellerPlaceholders})
                         AND oc.order_date BETWEEN ? AND ?
                         AND oc.order_status IN ({$statusPlaceholders})";

        $converted = Database::fetch($sqlConverted, $conversionParams);
        $convertedConversations = (float) ($converted['converted'] ?? 0);
        
        return ($convertedConversations / $totalConversations) * 100;
    }
    
    /**
     * Calcular quantidade de vendas
     */
    private static function calculateSalesCount(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        $sellerIds = self::getWooCommerceSellerIds($agentIds);
        if (empty($sellerIds)) return 0;

        $statusList = self::getValidWooCommerceStatuses();
        $sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count($statusList), '?'));
        $params = array_merge($sellerIds, [$startDate, $endDate], $statusList);

        $sql = "SELECT COUNT(DISTINCT oc.order_id) as sales_count
                FROM woocommerce_order_cache oc
                WHERE oc.seller_id IN ({$sellerPlaceholders})
                AND oc.order_date BETWEEN ? AND ?
                AND oc.order_status IN ({$statusPlaceholders})";

        $result = Database::fetch($sql, $params);
        return (float) ($result['sales_count'] ?? 0);
    }

    /**
     * IDs de vendedores WooCommerce vinculados aos agentes
     */
    private static function getWooCommerceSellerIds(array $agentIds): array
    {
        if (empty($agentIds)) return [];
        $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
        $rows = Database::fetchAll(
            "SELECT woocommerce_seller_id FROM users 
             WHERE id IN ({$placeholders}) AND woocommerce_seller_id IS NOT NULL",
            $agentIds
        );
        $sellerIds = array_column($rows, 'woocommerce_seller_id');
        return array_values(array_filter($sellerIds));
    }

    /**
     * Status válidos para considerar como venda/conversão
     */
    private static function getValidWooCommerceStatuses(): array
    {
        return ['processing', 'completed', 'producao', 'designer', 'pedido-enviado', 'pedido-entregue'];
    }
    
    /**
     * Calcular quantidade de conversas
     */
    private static function calculateConversationsCount(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        $sql = "SELECT COUNT(DISTINCT c.id) as conversations_count
                FROM conversations c
                INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                WHERE ca.agent_id IN ($placeholders)
                AND ca.assigned_at BETWEEN ? AND ?";
        
        $result = Database::fetch($sql, $params);
        return (float) ($result['conversations_count'] ?? 0);
    }
    
    /**
     * Calcular taxa de resolução
     */
    private static function calculateResolutionRate(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        // Total de conversas
        $sqlTotal = "SELECT COUNT(DISTINCT c.id) as total
                     FROM conversations c
                     INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                     WHERE ca.agent_id IN ($placeholders)
                     AND c.created_at BETWEEN ? AND ?";
        
        $total = Database::fetch($sqlTotal, $params);
        $totalConversations = (float) ($total['total'] ?? 0);
        
        if ($totalConversations == 0) return 0;
        
        // Conversas resolvidas
        $sqlResolved = "SELECT COUNT(DISTINCT c.id) as resolved
                        FROM conversations c
                        INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                        WHERE ca.agent_id IN ($placeholders)
                        AND c.created_at BETWEEN ? AND ?
                        AND c.status IN ('resolved', 'closed')";
        
        $resolved = Database::fetch($sqlResolved, $params);
        $resolvedConversations = (float) ($resolved['resolved'] ?? 0);
        
        return ($resolvedConversations / $totalConversations) * 100;
    }
    
    /**
     * Calcular tempo médio de resposta
     */
    private static function calculateResponseTime(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        $sql = "SELECT AVG(
                    TIMESTAMPDIFF(SECOND, 
                        (SELECT created_at FROM messages WHERE conversation_id = m1.conversation_id AND sender_type = 'contact' AND created_at < m1.created_at ORDER BY created_at DESC LIMIT 1),
                        m1.created_at
                    )
                ) / 60 as avg_response_time
                FROM messages m1
                WHERE m1.sender_type = 'user'
                AND m1.user_id IN ($placeholders)
                AND m1.created_at BETWEEN ? AND ?";
        
        $result = Database::fetch($sql, $params);
        return (float) ($result['avg_response_time'] ?? 0);
    }
    
    /**
     * Calcular CSAT médio
     */
    private static function calculateCSAT(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        $sql = "SELECT AVG(cs.rating) as avg_csat
                FROM conversation_surveys cs
                INNER JOIN conversations c ON cs.conversation_id = c.id
                INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                WHERE ca.agent_id IN ($placeholders)
                AND cs.created_at BETWEEN ? AND ?";
        
        $result = Database::fetch($sql, $params);
        return (float) ($result['avg_csat'] ?? 0);
    }
    
    /**
     * Calcular mensagens enviadas
     */
    private static function calculateMessagesSent(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        $sql = "SELECT COUNT(*) as messages_count
                FROM messages
                WHERE sender_type = 'user'
                AND user_id IN ($placeholders)
                AND created_at BETWEEN ? AND ?";
        
        $result = Database::fetch($sql, $params);
        return (float) ($result['messages_count'] ?? 0);
    }
    
    /**
     * Calcular compliance de SLA
     */
    private static function calculateSLACompliance(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        // Total de respostas
        $sqlTotal = "SELECT COUNT(*) as total
                     FROM messages
                     WHERE sender_type = 'user'
                     AND user_id IN ($placeholders)
                     AND created_at BETWEEN ? AND ?";
        
        $total = Database::fetch($sqlTotal, $params);
        $totalResponses = (float) ($total['total'] ?? 0);
        
        if ($totalResponses == 0) return 0;
        
        // Respostas dentro do SLA (assumindo 5 minutos)
        $sql = "SELECT COUNT(*) as within_sla
                FROM messages m1
                WHERE m1.sender_type = 'user'
                AND m1.user_id IN ($placeholders)
                AND m1.created_at BETWEEN ? AND ?
                AND TIMESTAMPDIFF(SECOND, 
                    (SELECT created_at FROM messages WHERE conversation_id = m1.conversation_id AND sender_type = 'contact' AND created_at < m1.created_at ORDER BY created_at DESC LIMIT 1),
                    m1.created_at
                ) <= 300";
        
        $result = Database::fetch($sql, $params);
        $withinSLA = (float) ($result['within_sla'] ?? 0);
        
        return ($withinSLA / $totalResponses) * 100;
    }
    
    /**
     * Calcular tempo de primeira resposta
     */
    private static function calculateFirstResponseTime(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, c.created_at, m.created_at)) / 60 as avg_first_response
                FROM conversations c
                INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                INNER JOIN (
                    SELECT conversation_id, MIN(created_at) as created_at
                    FROM messages
                    WHERE sender_type = 'user'
                    GROUP BY conversation_id
                ) m ON c.id = m.conversation_id
                WHERE ca.agent_id IN ($placeholders)
                AND c.created_at BETWEEN ? AND ?";
        
        $result = Database::fetch($sql, $params);
        return (float) ($result['avg_first_response'] ?? 0);
    }
    
    /**
     * Calcular tempo de resolução
     */
    private static function calculateResolutionTime(array $agentIds, string $startDate, string $endDate): float
    {
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, c.created_at, c.resolved_at)) / 60 as avg_resolution_time
                FROM conversations c
                INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                WHERE ca.agent_id IN ($placeholders)
                AND c.created_at BETWEEN ? AND ?
                AND c.resolved_at IS NOT NULL";
        
        $result = Database::fetch($sql, $params);
        return (float) ($result['avg_resolution_time'] ?? 0);
    }
    
    /**
     * Determinar status da meta
     */
    private static function determineStatus(float $percentage, array $goal): string
    {
        $now = date('Y-m-d');
        $started = $now >= $goal['start_date'];
        $ended = $now > $goal['end_date'];
        
        if (!$started) {
            return 'not_started';
        }
        
        if ($percentage >= 100) {
            return 'exceeded';
        }
        
        if ($percentage >= 100) {
            return 'achieved';
        }
        
        if ($ended && $percentage < 100) {
            return 'failed';
        }
        
        return 'in_progress';
    }
    
    /**
     * Registrar conquista de meta
     */
    private static function recordAchievement(array $goal, float $finalValue, float $percentage): void
    {
        $startDate = new \DateTime($goal['start_date']);
        $now = new \DateTime();
        $daysToAchieve = $now->diff($startDate)->days;
        
        GoalAchievement::record(
            $goal['id'],
            $finalValue,
            $percentage,
            $daysToAchieve,
            $goal['reward_points'] ?? 0,
            $goal['reward_badge'] ?? null
        );
        
        Logger::info('Meta atingida: ' . json_encode([
            'goal_id' => $goal['id'],
            'name' => $goal['name'],
            'days' => $daysToAchieve,
            'percentage' => $percentage
        ]), 'goals');
        
        // TODO: Enviar notificação
    }
    
    /**
     * Calcular progresso de todas as metas ativas
     */
    public static function calculateAllProgress(): array
    {
        $goals = Goal::getActive(['active_period' => true]);
        $results = [];
        
        foreach ($goals as $goal) {
            try {
                $results[] = self::calculateProgress($goal['id']);
            } catch (\Exception $e) {
                Logger::error('Erro ao calcular progresso da meta: ' . json_encode([
                    'goal_id' => $goal['id'],
                    'error' => $e->getMessage()
                ]), 'goals');
            }
        }
        
        return $results;
    }
    
    /**
     * Calcular e registrar bonificação
     * Agora com suporte a condições de ativação
     */
    private static function calculateAndRecordBonus(array $goal, float $percentage): void
    {
        // Apenas para metas individuais
        if ($goal['target_type'] !== 'individual' || empty($goal['target_id'])) {
            return;
        }
        
        try {
            // Passar usuário e datas para verificação de condições
            $bonusData = GoalBonusTier::calculateBonus(
                $goal['id'], 
                $percentage,
                $goal['target_id'],
                $goal['start_date'],
                $goal['end_date']
            );
            
            // Registrar log de verificação de condições (se houver)
            if (!empty($bonusData['goal_condition_result']) || !empty($bonusData['condition_results'])) {
                $originalBonus = $bonusData['conditions_blocked'] ? 0 : $bonusData['total_bonus'];
                
                GoalBonusCondition::logConditionCheck(
                    $goal['id'],
                    $bonusData['last_tier']['id'] ?? null,
                    $goal['target_id'],
                    $bonusData['goal_condition_result'] ?? [
                        'all_met' => true,
                        'conditions_checked' => 0,
                        'conditions_passed' => 0,
                        'modifier' => 1.0,
                        'details' => []
                    ],
                    $originalBonus,
                    $bonusData['total_bonus']
                );
            }
            
            if ($bonusData['total_bonus'] > 0 && !empty($bonusData['last_tier'])) {
                GoalBonusEarned::recordBonus(
                    $goal['id'],
                    $goal['target_id'],
                    $bonusData['total_bonus'],
                    $percentage,
                    $bonusData['last_tier']['id'] ?? null
                );
                
                $logData = [
                    'goal_id' => $goal['id'],
                    'user_id' => $goal['target_id'],
                    'bonus' => $bonusData['total_bonus'],
                    'percentage' => $percentage
                ];
                
                // Adicionar info de condições se relevante
                if ($bonusData['conditions_blocked'] ?? false) {
                    $logData['conditions_blocked'] = true;
                    $logData['modifier_applied'] = $bonusData['goal_condition_result']['modifier'] ?? 1.0;
                }
                
                Logger::info('Bonificação calculada: ' . json_encode($logData));
            } elseif ($bonusData['conditions_blocked'] ?? false) {
                Logger::info('Bonificação bloqueada por condições: goal_id=' . $goal['id'] . ', user_id=' . $goal['target_id']);
            }
        } catch (\Exception $e) {
            Logger::error('Erro ao calcular bonificação: ' . $e->getMessage() . ' (goal_id=' . $goal['id'] . ')');
        }
    }
    
    /**
     * Obter resumo de metas para dashboard
     */
    public static function getDashboardSummary(int $userId): array
    {
        $goals = Goal::getAgentGoals($userId);
        $summary = [
            'total_goals' => 0,
            'achieved' => 0,
            'in_progress' => 0,
            'at_risk' => 0,
            'goals_by_level' => [
                'individual' => [],
                'team' => [],
                'department' => [],
                'global' => []
            ]
        ];
        
        foreach ($goals as $level => $levelGoals) {
            foreach ($levelGoals as $goal) {
                $summary['total_goals']++;
                
                $progress = GoalProgress::getLatest($goal['id']);
                
                if ($progress) {
                    $goal['progress'] = $progress;
                    
                    if ($progress['status'] === 'achieved' || $progress['status'] === 'exceeded') {
                        $summary['achieved']++;
                    } elseif ($progress['percentage'] < 50 && strtotime($goal['end_date']) < strtotime('+7 days')) {
                        $summary['at_risk']++;
                    } else {
                        $summary['in_progress']++;
                    }
                }
                
                $summary['goals_by_level'][$level][] = $goal;
            }
        }
        
        // Adicionar resumo de bonificações
        $summary['bonus_summary'] = self::getBonusSummary($userId);
        
        return $summary;
    }
    
    /**
     * Obter resumo de bonificações do agente
     */
    public static function getBonusSummary(int $userId): array
    {
        // Bonificações do mês atual
        $currentMonth = date('Y-m');
        $startDate = $currentMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $monthSummary = GoalBonusEarned::getAgentSummary($userId, (int)date('Y'), (int)date('m'));
        
        // Bonificações recentes
        $recentBonuses = GoalBonusEarned::getByAgent($userId, null, 5);
        
        // Total acumulado no ano
        $yearStart = date('Y') . '-01-01';
        $yearEnd = date('Y') . '-12-31';
        $yearTotal = GoalBonusEarned::getTotalByPeriod($userId, $yearStart, $yearEnd, 'paid');
        
        return [
            'month' => $monthSummary,
            'year_total' => $yearTotal,
            'recent' => $recentBonuses
        ];
    }
    
    /**
     * Criar tiers padrão para uma meta
     */
    public static function createDefaultBonusTiers(int $goalId, float $targetCommission): void
    {
        GoalBonusTier::createDefaultTiers($goalId, $targetCommission);
    }
    
    /**
     * Obter alertas de metas do agente
     */
    public static function getGoalAlerts(int $userId, ?string $severity = null, int $limit = 10): array
    {
        try {
            $sql = "SELECT ga.*, g.name as goal_name, g.type as goal_type
                    FROM goal_alerts ga
                    INNER JOIN goals g ON ga.goal_id = g.id
                    WHERE g.target_type = 'individual' 
                    AND g.target_id = ?
                    AND ga.is_resolved = 0";
            
            $params = [$userId];
            
            if ($severity) {
                $sql .= " AND ga.severity = ?";
                $params[] = $severity;
            }
            
            $sql .= " ORDER BY ga.created_at DESC LIMIT ?";
            $params[] = $limit;
            
            return Database::fetchAll($sql, $params);
        } catch (\Exception $e) {
            Logger::error('Erro ao buscar alertas de metas', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
