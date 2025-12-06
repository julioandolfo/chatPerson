<?php
/**
 * Model FunnelStage
 */

namespace App\Models;

use App\Helpers\Database;

class FunnelStage extends Model
{
    protected string $table = 'funnel_stages';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'funnel_id', 'name', 'description', 'position', 'color', 'is_default',
        'max_conversations', 'allow_move_back', 'allow_skip_stages',
        'blocked_stages', 'required_stages', 'required_tags', 'blocked_tags',
        'auto_assign', 'auto_assign_department_id', 'auto_assign_method',
        'sla_hours', 'settings'
    ];
    protected bool $timestamps = true;

    /**
     * Obter estágio padrão do funil
     */
    public static function getDefault(int $funnelId): ?array
    {
        $sql = "SELECT * FROM funnel_stages WHERE funnel_id = ? AND is_default = TRUE LIMIT 1";
        return Database::fetch($sql, [$funnelId]);
    }

    /**
     * Reordenar estágios
     */
    public static function reorder(int $funnelId, array $stageIds): bool
    {
        try {
            Database::getInstance()->beginTransaction();
            
            foreach ($stageIds as $position => $stageId) {
                $sql = "UPDATE funnel_stages SET position = ? WHERE id = ? AND funnel_id = ?";
                Database::execute($sql, [$position, $stageId, $funnelId]);
            }
            
            Database::getInstance()->commit();
            return true;
        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            return false;
        }
    }

    /**
     * Mover conversa para estágio
     */
    public static function moveConversation(int $conversationId, int $stageId): bool
    {
        $stage = self::find($stageId);
        if (!$stage) {
            return false;
        }
        
        return \App\Models\Conversation::update($conversationId, [
            'funnel_id' => $stage['funnel_id'],
            'funnel_stage_id' => $stageId
        ]);
    }
}

