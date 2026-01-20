<?php
/**
 * Model GoalBonusTier
 * Níveis de bonificação por meta
 */

namespace App\Models;

use App\Helpers\Database;

class GoalBonusTier extends Model
{
    protected string $table = 'goal_bonus_tiers';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'goal_id',
        'threshold_percentage',
        'bonus_amount',
        'bonus_percentage',
        'is_cumulative',
        'tier_name',
        'tier_color',
        'tier_order'
    ];
    protected bool $timestamps = false;
    
    /**
     * Obter tiers de uma meta (ordenados)
     */
    public static function getByGoal(int $goalId): array
    {
        $sql = "SELECT * FROM goal_bonus_tiers 
                WHERE goal_id = ? 
                ORDER BY threshold_percentage ASC";
        return Database::fetchAll($sql, [$goalId]);
    }
    
    /**
     * Determinar qual tier foi atingido
     */
    public static function getEarnedTier(int $goalId, float $percentage): ?array
    {
        // Buscar o maior tier atingido
        $sql = "SELECT * FROM goal_bonus_tiers 
                WHERE goal_id = ? 
                AND threshold_percentage <= ?
                ORDER BY threshold_percentage DESC 
                LIMIT 1";
        
        return Database::fetch($sql, [$goalId, $percentage]);
    }
    
    /**
     * Calcular bonus total (cumulativo ou não)
     * Agora com suporte a condições de ativação
     * 
     * @param int $goalId ID da meta
     * @param float $percentage Percentual atingido
     * @param int|null $userId ID do usuário (para verificar condições individuais)
     * @param string|null $startDate Data inicial do período
     * @param string|null $endDate Data final do período
     */
    public static function calculateBonus(
        int $goalId, 
        float $percentage,
        ?int $userId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $tiers = self::getByGoal($goalId);
        $totalBonus = 0;
        $achievedTiers = [];
        $lastTier = null;
        $conditionResults = [];
        $conditionsBlocked = false;
        
        // Verificar condições gerais da meta (se existirem)
        $goal = Goal::find($goalId);
        $goalConditionResult = null;
        
        if ($goal && ($goal['enable_bonus_conditions'] ?? false) && $userId) {
            $goalConditionResult = GoalBonusCondition::checkConditions(
                $goalId,
                $userId,
                $startDate ?? $goal['start_date'],
                $endDate ?? $goal['end_date']
            );
            
            $conditionResults['goal_level'] = $goalConditionResult;
            
            // Se condições obrigatórias da meta não forem atendidas, nenhum bônus é liberado
            if (!$goalConditionResult['all_met']) {
                $conditionsBlocked = true;
            }
        }
        
        foreach ($tiers as $tier) {
            if ($percentage >= $tier['threshold_percentage']) {
                $tierBonus = floatval($tier['bonus_amount']);
                $tierConditionResult = null;
                
                // Verificar condições específicas do tier (se existirem)
                if (($tier['has_conditions'] ?? false) && $userId) {
                    $tierConditionResult = GoalBonusCondition::checkTierConditions(
                        $tier['id'],
                        $userId,
                        $startDate ?? $goal['start_date'],
                        $endDate ?? $goal['end_date']
                    );
                    
                    $conditionResults['tier_' . $tier['id']] = $tierConditionResult;
                    
                    // Aplicar modificador se condições não obrigatórias não forem atendidas
                    if (!$tierConditionResult['all_met']) {
                        if ($tierConditionResult['modifier'] == 0) {
                            // Condições obrigatórias não atendidas - pular tier
                            continue;
                        }
                        // Aplicar modificador parcial
                        $tierBonus *= $tierConditionResult['modifier'];
                    }
                }
                
                // Se condições gerais da meta bloquearam, aplicar modificador
                if ($conditionsBlocked && $goalConditionResult) {
                    if ($goalConditionResult['modifier'] == 0) {
                        continue; // Não libera nenhum bônus
                    }
                    $tierBonus *= $goalConditionResult['modifier'];
                }
                
                if ($tier['is_cumulative']) {
                    // Cumulativo: soma todos os tiers
                    $totalBonus += $tierBonus;
                    $tier['calculated_bonus'] = $tierBonus;
                    $tier['condition_result'] = $tierConditionResult;
                    $achievedTiers[] = $tier;
                } else {
                    // Não cumulativo: substitui
                    $totalBonus = $tierBonus;
                    $tier['calculated_bonus'] = $tierBonus;
                    $tier['condition_result'] = $tierConditionResult;
                    $achievedTiers = [$tier];
                }
                $lastTier = $tier;
            }
        }
        
        return [
            'total_bonus' => $totalBonus,
            'achieved_tiers' => $achievedTiers,
            'last_tier' => $lastTier,
            'next_tier' => self::getNextTier($goalId, $percentage),
            'conditions_blocked' => $conditionsBlocked,
            'condition_results' => $conditionResults,
            'goal_condition_result' => $goalConditionResult
        ];
    }
    
    /**
     * Obter próximo tier a atingir
     */
    public static function getNextTier(int $goalId, float $currentPercentage): ?array
    {
        $sql = "SELECT * FROM goal_bonus_tiers 
                WHERE goal_id = ? 
                AND threshold_percentage > ?
                ORDER BY threshold_percentage ASC 
                LIMIT 1";
        
        return Database::fetch($sql, [$goalId, $currentPercentage]);
    }
    
    /**
     * Criar tiers padrão para uma meta
     */
    public static function createDefaultTiers(int $goalId, float $targetCommission): void
    {
        $defaultTiers = [
            ['threshold' => 50.0,  'multiplier' => 0.3,  'name' => 'Bronze',   'color' => '#CD7F32'],
            ['threshold' => 70.0,  'multiplier' => 0.5,  'name' => 'Prata',    'color' => '#C0C0C0'],
            ['threshold' => 90.0,  'multiplier' => 0.8,  'name' => 'Ouro',     'color' => '#FFD700'],
            ['threshold' => 100.0, 'multiplier' => 1.0,  'name' => 'Platina',  'color' => '#E5E4E2'],
            ['threshold' => 120.0, 'multiplier' => 1.5,  'name' => 'Diamante', 'color' => '#B9F2FF'],
        ];
        
        $order = 0;
        foreach ($defaultTiers as $tier) {
            self::create([
                'goal_id' => $goalId,
                'threshold_percentage' => $tier['threshold'],
                'bonus_amount' => $targetCommission * $tier['multiplier'],
                'is_cumulative' => 0,
                'tier_name' => $tier['name'],
                'tier_color' => $tier['color'],
                'tier_order' => $order++
            ]);
        }
    }
}
