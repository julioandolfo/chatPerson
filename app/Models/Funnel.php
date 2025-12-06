<?php
/**
 * Model Funnel
 */

namespace App\Models;

use App\Helpers\Database;

class Funnel extends Model
{
    protected string $table = 'funnels';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'description', 'is_default', 'status'];
    protected bool $timestamps = true;

    /**
     * Obter funil padrão
     */
    public static function getDefault(): ?array
    {
        return self::whereFirst('is_default', '=', true);
    }

    /**
     * Obter funis ativos
     */
    public static function whereActive(): array
    {
        return self::where('status', '=', 'active');
    }

    /**
     * Obter estágios do funil
     */
    public static function getStages(int $funnelId): array
    {
        $sql = "SELECT * FROM funnel_stages 
                WHERE funnel_id = ? 
                ORDER BY position ASC, id ASC";
        return Database::fetchAll($sql, [$funnelId]);
    }

    /**
     * Obter funil com estágios
     */
    public static function findWithStages(int $funnelId): ?array
    {
        $funnel = self::find($funnelId);
        if (!$funnel) {
            return null;
        }
        
        $funnel['stages'] = self::getStages($funnelId);
        return $funnel;
    }

    /**
     * Obter conversas por estágio
     */
    public static function getConversationsByStage(int $funnelId, int $stageId): array
    {
        $sql = "SELECT c.*, 
                       ct.name as contact_name, ct.phone as contact_phone, ct.avatar as contact_avatar,
                       u.name as agent_name, u.avatar as agent_avatar,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.read_at IS NULL) as unread_count,
                       (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_at
                FROM conversations c
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users u ON c.agent_id = u.id
                WHERE c.funnel_id = ? AND c.funnel_stage_id = ?
                ORDER BY c.updated_at DESC";
        
        return Database::fetchAll($sql, [$funnelId, $stageId]);
    }
}

