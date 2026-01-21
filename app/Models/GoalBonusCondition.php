<?php
/**
 * Model GoalBonusCondition
 * Condições de ativação para bônus de metas
 * 
 * Permite configurar que um bônus só seja liberado se outras métricas
 * atingirem valores mínimos. Ex: Bônus de faturamento só ativa se conversão >= 15%
 */

namespace App\Models;

use App\Helpers\Database;

class GoalBonusCondition extends Model
{
    protected string $table = 'goal_bonus_conditions';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'goal_id',
        'bonus_tier_id',
        'condition_type',
        'operator',
        'min_value',
        'max_value',
        'reference_goal_id',
        'is_required',
        'bonus_modifier',
        'description',
        'check_order',
        'is_active'
    ];
    protected bool $timestamps = false;
    
    /**
     * Tipos de condição disponíveis
     */
    const CONDITION_TYPES = [
        'revenue' => 'Faturamento',
        'average_ticket' => 'Ticket Médio',
        'conversion_rate' => 'Taxa de Conversão',
        'sales_count' => 'Quantidade de Vendas',
        'conversations_count' => 'Quantidade de Conversas',
        'resolution_rate' => 'Taxa de Resolução',
        'response_time' => 'Tempo de Resposta',
        'csat_score' => 'CSAT',
        'messages_sent' => 'Mensagens Enviadas',
        'sla_compliance' => 'SLA',
        'first_response_time' => 'Primeira Resposta',
        'resolution_time' => 'Tempo de Resolução',
        'goal_percentage' => 'Percentual de Outra Meta'
    ];
    
    /**
     * Operadores disponíveis
     */
    const OPERATORS = [
        '>=' => 'Maior ou igual a',
        '>' => 'Maior que',
        '<=' => 'Menor ou igual a',
        '<' => 'Menor que',
        '=' => 'Igual a',
        '!=' => 'Diferente de',
        'between' => 'Entre'
    ];
    
    /**
     * Obter condições de uma meta
     */
    public static function getByGoal(int $goalId): array
    {
        $sql = "SELECT gbc.*, 
                       g.name as reference_goal_name,
                       g.type as reference_goal_type
                FROM goal_bonus_conditions gbc
                LEFT JOIN goals g ON gbc.reference_goal_id = g.id
                WHERE gbc.goal_id = ? AND gbc.is_active = 1
                ORDER BY gbc.check_order ASC";
        return Database::fetchAll($sql, [$goalId]);
    }
    
    /**
     * Obter condições de um tier específico
     */
    public static function getByTier(int $tierId): array
    {
        $sql = "SELECT gbc.*, 
                       g.name as reference_goal_name,
                       g.type as reference_goal_type
                FROM goal_bonus_conditions gbc
                LEFT JOIN goals g ON gbc.reference_goal_id = g.id
                WHERE gbc.bonus_tier_id = ? AND gbc.is_active = 1
                ORDER BY gbc.check_order ASC";
        return Database::fetchAll($sql, [$tierId]);
    }
    
    /**
     * Verificar se todas as condições de uma meta estão atendidas
     * 
     * @param int $goalId ID da meta
     * @param int $userId ID do usuário (para calcular métricas individuais)
     * @param string $startDate Data inicial do período
     * @param string $endDate Data final do período
     * @return array Resultado com detalhes de cada condição
     */
    public static function checkConditions(
        int $goalId, 
        int $userId, 
        string $startDate, 
        string $endDate
    ): array {
        $conditions = self::getByGoal($goalId);
        
        if (empty($conditions)) {
            return [
                'all_met' => true,
                'conditions_checked' => 0,
                'conditions_passed' => 0,
                'modifier' => 1.0,
                'details' => []
            ];
        }
        
        $results = [];
        $allRequiredMet = true;
        $totalModifier = 1.0;
        $passed = 0;
        
        foreach ($conditions as $condition) {
            $result = self::evaluateCondition($condition, $userId, $startDate, $endDate);
            $results[] = $result;
            
            if ($result['met']) {
                $passed++;
            } else {
                if ($condition['is_required']) {
                    $allRequiredMet = false;
                } else {
                    // Aplicar modificador do bônus
                    $totalModifier *= floatval($condition['bonus_modifier']);
                }
            }
        }
        
        return [
            'all_met' => $allRequiredMet,
            'conditions_checked' => count($conditions),
            'conditions_passed' => $passed,
            'modifier' => $allRequiredMet ? $totalModifier : 0,
            'details' => $results
        ];
    }
    
    /**
     * Verificar condições de um tier específico
     */
    public static function checkTierConditions(
        int $tierId,
        int $userId,
        string $startDate,
        string $endDate
    ): array {
        $conditions = self::getByTier($tierId);
        
        if (empty($conditions)) {
            return [
                'all_met' => true,
                'conditions_checked' => 0,
                'conditions_passed' => 0,
                'modifier' => 1.0,
                'details' => []
            ];
        }
        
        $results = [];
        $allRequiredMet = true;
        $totalModifier = 1.0;
        $passed = 0;
        
        foreach ($conditions as $condition) {
            $result = self::evaluateCondition($condition, $userId, $startDate, $endDate);
            $results[] = $result;
            
            if ($result['met']) {
                $passed++;
            } else {
                if ($condition['is_required']) {
                    $allRequiredMet = false;
                } else {
                    $totalModifier *= floatval($condition['bonus_modifier']);
                }
            }
        }
        
        return [
            'all_met' => $allRequiredMet,
            'conditions_checked' => count($conditions),
            'conditions_passed' => $passed,
            'modifier' => $allRequiredMet ? $totalModifier : 0,
            'details' => $results
        ];
    }
    
    /**
     * Avaliar uma condição específica
     */
    private static function evaluateCondition(
        array $condition, 
        int $userId, 
        string $startDate, 
        string $endDate
    ): array {
        $conditionType = $condition['condition_type'];
        $operator = $condition['operator'];
        $minValue = floatval($condition['min_value']);
        $maxValue = $condition['max_value'] ? floatval($condition['max_value']) : null;
        
        // Obter valor atual da métrica
        if ($conditionType === 'goal_percentage' && $condition['reference_goal_id']) {
            // Verificar percentual de outra meta
            $currentValue = self::getReferenceGoalPercentage($condition['reference_goal_id']);
        } else {
            // Calcular métrica diretamente
            $currentValue = self::calculateMetricValue(
                $conditionType, 
                [$userId], 
                $startDate, 
                $endDate
            );
        }
        
        // Avaliar condição
        $met = self::evaluateOperator($currentValue, $operator, $minValue, $maxValue);
        
        return [
            'condition_id' => $condition['id'],
            'condition_type' => $conditionType,
            'description' => $condition['description'] ?? self::generateDescription($condition),
            'operator' => $operator,
            'required_value' => $minValue,
            'max_value' => $maxValue,
            'current_value' => $currentValue,
            'met' => $met,
            'is_required' => (bool)$condition['is_required'],
            'bonus_modifier' => floatval($condition['bonus_modifier'])
        ];
    }
    
    /**
     * Avaliar operador
     */
    private static function evaluateOperator(
        float $currentValue, 
        string $operator, 
        float $minValue, 
        ?float $maxValue
    ): bool {
        switch ($operator) {
            case '>=':
                return $currentValue >= $minValue;
            case '>':
                return $currentValue > $minValue;
            case '<=':
                return $currentValue <= $minValue;
            case '<':
                return $currentValue < $minValue;
            case '=':
                return abs($currentValue - $minValue) < 0.001;
            case '!=':
                return abs($currentValue - $minValue) >= 0.001;
            case 'between':
                return $currentValue >= $minValue && ($maxValue === null || $currentValue <= $maxValue);
            default:
                return false;
        }
    }
    
    /**
     * Obter percentual de outra meta
     */
    private static function getReferenceGoalPercentage(int $goalId): float
    {
        $progress = GoalProgress::getLatest($goalId);
        return $progress ? floatval($progress['percentage']) : 0;
    }
    
    /**
     * Calcular valor de uma métrica
     * Reutiliza lógica do GoalService
     */
    private static function calculateMetricValue(
        string $type, 
        array $agentIds, 
        string $startDate, 
        string $endDate
    ): float {
        // Importar métodos do GoalService via reflection ou duplicar lógica básica
        // Por simplicidade, vamos usar consultas diretas para as métricas mais comuns
        
        if (empty($agentIds)) return 0;
        
        $placeholders = str_repeat('?,', count($agentIds) - 1) . '?';
        $params = array_merge($agentIds, [$startDate, $endDate]);
        
        switch ($type) {
            case 'revenue':
                $sellerIds = self::getWooCommerceSellerIds($agentIds);
                if (empty($sellerIds)) return 0;
                $statusList = self::getValidWooCommerceStatuses();
                $sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
                $statusPlaceholders = implode(',', array_fill(0, count($statusList), '?'));
                $params = array_merge($sellerIds, [$startDate, $endDate], $statusList);
                $sql = "SELECT COALESCE(SUM(oc.order_total), 0) as value
                        FROM woocommerce_order_cache oc
                        WHERE oc.seller_id IN ({$sellerPlaceholders})
                        AND oc.order_date BETWEEN ? AND ?
                        AND oc.order_status IN ({$statusPlaceholders})";
                break;
                
            case 'conversion_rate':
                // Total de conversas
                $sqlTotal = "SELECT COUNT(DISTINCT c.id) as total
                             FROM conversations c
                             INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                             WHERE ca.agent_id IN ($placeholders)
                             AND c.created_at BETWEEN ? AND ?";
                $total = Database::fetch($sqlTotal, $params);
                $totalConversations = floatval($total['total'] ?? 0);
                
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
                
                return (floatval($converted['converted'] ?? 0) / $totalConversations) * 100;
                
            case 'sales_count':
                $sellerIds = self::getWooCommerceSellerIds($agentIds);
                if (empty($sellerIds)) return 0;
                $statusList = self::getValidWooCommerceStatuses();
                $sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
                $statusPlaceholders = implode(',', array_fill(0, count($statusList), '?'));
                $params = array_merge($sellerIds, [$startDate, $endDate], $statusList);
                $sql = "SELECT COUNT(DISTINCT oc.order_id) as value
                        FROM woocommerce_order_cache oc
                        WHERE oc.seller_id IN ({$sellerPlaceholders})
                        AND oc.order_date BETWEEN ? AND ?
                        AND oc.order_status IN ({$statusPlaceholders})";
                break;
                
            case 'average_ticket':
                $sellerIds = self::getWooCommerceSellerIds($agentIds);
                if (empty($sellerIds)) return 0;
                $statusList = self::getValidWooCommerceStatuses();
                $sellerPlaceholders = implode(',', array_fill(0, count($sellerIds), '?'));
                $statusPlaceholders = implode(',', array_fill(0, count($statusList), '?'));
                $params = array_merge($sellerIds, [$startDate, $endDate], $statusList);
                $sql = "SELECT COALESCE(AVG(oc.order_total), 0) as value
                        FROM woocommerce_order_cache oc
                        WHERE oc.seller_id IN ({$sellerPlaceholders})
                        AND oc.order_date BETWEEN ? AND ?
                        AND oc.order_status IN ({$statusPlaceholders})";
                break;
                
            case 'conversations_count':
                $sql = "SELECT COUNT(DISTINCT c.id) as value
                        FROM conversations c
                        INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                        WHERE ca.agent_id IN ($placeholders)
                        AND ca.assigned_at BETWEEN ? AND ?";
                break;
                
            case 'csat_score':
                $sql = "SELECT AVG(cs.rating) as value
                        FROM conversation_surveys cs
                        INNER JOIN conversations c ON cs.conversation_id = c.id
                        INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                        WHERE ca.agent_id IN ($placeholders)
                        AND cs.created_at BETWEEN ? AND ?";
                break;
                
            case 'resolution_rate':
                $sqlTotal = "SELECT COUNT(DISTINCT c.id) as total
                             FROM conversations c
                             INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                             WHERE ca.agent_id IN ($placeholders)
                             AND c.created_at BETWEEN ? AND ?";
                $total = Database::fetch($sqlTotal, $params);
                $totalConversations = floatval($total['total'] ?? 0);
                
                if ($totalConversations == 0) return 0;
                
                $sqlResolved = "SELECT COUNT(DISTINCT c.id) as resolved
                                FROM conversations c
                                INNER JOIN conversation_assignments ca ON c.id = ca.conversation_id
                                WHERE ca.agent_id IN ($placeholders)
                                AND c.created_at BETWEEN ? AND ?
                                AND c.status IN ('resolved', 'closed')";
                $resolved = Database::fetch($sqlResolved, $params);
                
                return (floatval($resolved['resolved'] ?? 0) / $totalConversations) * 100;
                
            default:
                return 0;
        }
        
        $result = Database::fetch($sql, $params);
        return floatval($result['value'] ?? 0);
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
     * Gerar descrição automática da condição
     */
    private static function generateDescription(array $condition): string
    {
        $typeLabel = self::CONDITION_TYPES[$condition['condition_type']] ?? $condition['condition_type'];
        $operatorLabel = self::OPERATORS[$condition['operator']] ?? $condition['operator'];
        $value = number_format($condition['min_value'], 2, ',', '.');
        
        // Formatar valor conforme o tipo
        if (in_array($condition['condition_type'], ['conversion_rate', 'resolution_rate', 'sla_compliance'])) {
            $value .= '%';
        } elseif (in_array($condition['condition_type'], ['revenue', 'average_ticket'])) {
            $value = 'R$ ' . $value;
        }
        
        $description = "{$typeLabel} {$operatorLabel} {$value}";
        
        if ($condition['operator'] === 'between' && $condition['max_value']) {
            $maxValue = number_format($condition['max_value'], 2, ',', '.');
            $description .= " e {$maxValue}";
        }
        
        return $description;
    }
    
    /**
     * Registrar log de verificação de condições
     */
    public static function logConditionCheck(
        int $goalId,
        ?int $tierId,
        int $userId,
        array $checkResult,
        float $originalBonus,
        float $finalBonus
    ): int {
        $sql = "INSERT INTO goal_bonus_condition_logs 
                (goal_id, bonus_tier_id, user_id, all_conditions_met, conditions_checked, 
                 conditions_passed, condition_results, original_bonus, final_bonus, modifier_applied)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        Database::execute($sql, [
            $goalId,
            $tierId,
            $userId,
            $checkResult['all_met'] ? 1 : 0,
            $checkResult['conditions_checked'],
            $checkResult['conditions_passed'],
            json_encode($checkResult['details']),
            $originalBonus,
            $finalBonus,
            $checkResult['modifier']
        ]);
        
        return Database::lastInsertId();
    }
    
    /**
     * Obter histórico de verificações de um usuário
     */
    public static function getCheckHistory(int $userId, int $limit = 20): array
    {
        $sql = "SELECT gbcl.*, g.name as goal_name, gbt.tier_name
                FROM goal_bonus_condition_logs gbcl
                INNER JOIN goals g ON gbcl.goal_id = g.id
                LEFT JOIN goal_bonus_tiers gbt ON gbcl.bonus_tier_id = gbt.id
                WHERE gbcl.user_id = ?
                ORDER BY gbcl.checked_at DESC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$userId, $limit]);
    }
}
