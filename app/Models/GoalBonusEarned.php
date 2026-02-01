<?php
/**
 * Model GoalBonusEarned
 * Bonificações ganhas pelos agentes
 */

namespace App\Models;

use App\Helpers\Database;

class GoalBonusEarned extends Model
{
    protected string $table = 'goal_bonus_earned';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'goal_id',
        'tier_id',
        'user_id',
        'bonus_amount',
        'percentage_achieved',
        'earned_at',
        'period_start',
        'period_end',
        'status',
        'notes'
    ];
    protected bool $timestamps = true;
    
    /**
     * Registrar bonus ganho
     */
    public static function recordBonus(
        int $goalId, 
        int $userId, 
        float $bonusAmount, 
        float $percentage,
        ?int $tierId = null
    ): int {
        // Verificar se já existe
        $goal = \App\Models\Goal::find($goalId);
        if (!$goal) {
            throw new \InvalidArgumentException('Meta não encontrada');
        }
        
        $existing = Database::fetch(
            "SELECT id FROM goal_bonus_earned 
             WHERE goal_id = ? AND user_id = ? 
             AND period_start = ? AND period_end = ?",
            [$goalId, $userId, $goal['start_date'], $goal['end_date']]
        );
        
        if ($existing) {
            // Atualizar existente
            Database::execute(
                "UPDATE goal_bonus_earned 
                 SET tier_id = ?, bonus_amount = ?, percentage_achieved = ?, 
                     earned_at = NOW(), status = 'pendente'
                 WHERE id = ?",
                [$tierId, $bonusAmount, $percentage, $existing['id']]
            );
            return $existing['id'];
        } else {
            // Criar novo
            return self::create([
                'goal_id' => $goalId,
                'tier_id' => $tierId,
                'user_id' => $userId,
                'bonus_amount' => $bonusAmount,
                'percentage_achieved' => $percentage,
                'earned_at' => date('Y-m-d H:i:s'),
                'period_start' => $goal['start_date'],
                'period_end' => $goal['end_date'],
                'status' => 'pendente'
            ]);
        }
    }
    
    /**
     * Obter bonificações de um agente
     */
    public static function getByAgent(int $userId, ?string $status = null, ?int $limit = null): array
    {
        $sql = "SELECT gbe.*, 
                       g.name as goal_name, 
                       g.type as goal_type,
                       gbt.tier_name,
                       gbt.tier_color
                FROM goal_bonus_earned gbe
                INNER JOIN goals g ON gbe.goal_id = g.id
                LEFT JOIN goal_bonus_tiers gbt ON gbe.tier_id = gbt.id
                WHERE gbe.user_id = ?";
        
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND gbe.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY gbe.earned_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Obter total de bonificações por período
     */
    public static function getTotalByPeriod(int $userId, string $startDate, string $endDate, ?string $status = 'paid'): float
    {
        $sql = "SELECT COALESCE(SUM(bonus_amount), 0) as total
                FROM goal_bonus_earned
                WHERE user_id = ?
                AND period_start >= ? AND period_end <= ?";
        
        $params = [$userId, $startDate, $endDate];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $result = Database::fetch($sql, $params);
        return (float) ($result['total'] ?? 0);
    }
    
    /**
     * Obter bonificações pendentes de aprovação
     */
    public static function getPending(?int $limit = 50): array
    {
        $sql = "SELECT gbe.*, 
                       g.name as goal_name,
                       u.name as agent_name,
                       u.email as agent_email
                FROM goal_bonus_earned gbe
                INNER JOIN goals g ON gbe.goal_id = g.id
                INNER JOIN users u ON gbe.user_id = u.id
                WHERE gbe.status = 'pendente'
                ORDER BY gbe.earned_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            return Database::fetchAll($sql, [$limit]);
        }
        
        return Database::fetchAll($sql);
    }
    
    /**
     * Aprovar bonificação
     */
    public static function approve(int $bonusId, int $approvedBy): bool
    {
        $sql = "UPDATE goal_bonus_earned 
                SET status = 'approved', approved_by = ?, approved_at = NOW()
                WHERE id = ?";
        
        Database::execute($sql, [$approvedBy, $bonusId]);
        return true;
    }
    
    /**
     * Marcar como pago
     */
    public static function markAsPaid(int $bonusId): bool
    {
        $sql = "UPDATE goal_bonus_earned 
                SET status = 'paid', paid_at = NOW()
                WHERE id = ?";
        
        Database::execute($sql, [$bonusId]);
        return true;
    }
    
    /**
     * Relatório consolidado por agente
     */
    public static function getAgentSummary(int $userId, int $year, int $month): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $sql = "SELECT 
                    COUNT(*) as total_bonuses,
                    SUM(CASE WHEN status = 'pendente' THEN bonus_amount ELSE 0 END) as pendente_amount,
                    SUM(CASE WHEN status = 'approved' THEN bonus_amount ELSE 0 END) as approved_amount,
                    SUM(CASE WHEN status = 'paid' THEN bonus_amount ELSE 0 END) as paid_amount,
                    SUM(bonus_amount) as total_amount
                FROM goal_bonus_earned
                WHERE user_id = ?
                AND period_start >= ? AND period_end <= ?";
        
        return Database::fetch($sql, [$userId, $startDate, $endDate]) ?: [
            'total_bonuses' => 0,
            'pendente_amount' => 0,
            'approved_amount' => 0,
            'paid_amount' => 0,
            'total_amount' => 0
        ];
    }
}
