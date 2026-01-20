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
    protected array $fillable = ['name', 'description', 'ai_description', 'is_default', 'status'];
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
                ORDER BY stage_order ASC, position ASC, id ASC";
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
                       ct.name as contact_name, 
                       ct.phone as contact_phone, 
                       ct.avatar as contact_avatar,
                       u.name as agent_name, 
                       u.avatar as agent_avatar,
                       fs.color as stage_color,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'contact' AND m.read_at IS NULL) as unread_count,
                       (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
                       (SELECT sender_type FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_sender,
                       (SELECT name FROM users WHERE id = (SELECT sender_id FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'agent' ORDER BY m.created_at DESC LIMIT 1)) as last_agent_name,
                       (SELECT GROUP_CONCAT(t.name SEPARATOR ',') FROM conversation_tags ct_tags 
                        LEFT JOIN tags t ON ct_tags.tag_id = t.id 
                        WHERE ct_tags.conversation_id = c.id) as tags_list,
                       (SELECT GROUP_CONCAT(t.color SEPARATOR ',') FROM conversation_tags ct_tags 
                        LEFT JOIN tags t ON ct_tags.tag_id = t.id 
                        WHERE ct_tags.conversation_id = c.id) as tags_colors,
                       TIMESTAMPDIFF(HOUR, c.updated_at, NOW()) as hours_in_stage,
                       -- SLA do estágio do funil (em HORAS, não minutos - é tempo de permanência no estágio)
                       CASE 
                           WHEN TIMESTAMPDIFF(HOUR, c.updated_at, NOW()) > COALESCE(fs.sla_hours, 24) THEN 'exceeded'
                           WHEN TIMESTAMPDIFF(HOUR, c.updated_at, NOW()) > (COALESCE(fs.sla_hours, 24) * 0.8) THEN 'warning'
                           ELSE 'ok'
                       END as sla_status
                FROM conversations c
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users u ON c.agent_id = u.id
                LEFT JOIN funnel_stages fs ON c.funnel_stage_id = fs.id
                WHERE c.funnel_id = ? AND c.funnel_stage_id = ?
                ORDER BY c.updated_at DESC";
        
        $conversations = Database::fetchAll($sql, [$funnelId, $stageId]);
        
        // Processar tags (transformar string em array)
        foreach ($conversations as &$conv) {
            if (!empty($conv['tags_list'])) {
                $tagNames = explode(',', $conv['tags_list']);
                $tagColors = !empty($conv['tags_colors']) ? explode(',', $conv['tags_colors']) : [];
                
                $conv['tags'] = [];
                foreach ($tagNames as $index => $tagName) {
                    $conv['tags'][] = [
                        'name' => $tagName,
                        'color' => $tagColors[$index] ?? '#009ef7'
                    ];
                }
            } else {
                $conv['tags'] = [];
            }
        }
        
        return $conversations;
    }
}

