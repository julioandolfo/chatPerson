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
     */
    public static function calculateBonus(int $goalId, float $percentage): array
    {
        $tiers = self::getByGoal($goalId);
        $totalBonus = 0;
        $achievedTiers = [];
        $lastTier = null;
        
        foreach ($tiers as $tier) {
            if ($percentage >= $tier['threshold_percentage']) {
                if ($tier['is_cumulative']) {
                    // Cumulativo: soma todos os tiers
                    $totalBonus += $tier['bonus_amount'];
                    $achievedTiers[] = $tier;
                } else {
                    // Não cumulativo: substitui
                    $totalBonus = $tier['bonus_amount'];
                    $achievedTiers = [$tier];
                }
                $lastTier = $tier;
            }
        }
        
        return [
            'total_bonus' => $totalBonus,
            'achieved_tiers' => $achievedTiers,
            'last_tier' => $lastTier,
            'next_tier' => self::getNextTier($goalId, $percentage)
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
