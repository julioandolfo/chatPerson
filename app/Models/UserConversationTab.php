<?php
/**
 * Model UserConversationTab
 * Gerencia as abas personalizadas de cada agente na listagem de conversas.
 * Cada aba é vinculada a uma tag existente.
 */

namespace App\Models;

use App\Helpers\Database;

class UserConversationTab extends Model
{
    protected string $table = 'user_conversation_tabs';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'tag_id', 'position'];
    protected bool $timestamps = false;

    /**
     * Obter todas as abas de um usuário com dados da tag
     */
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT uct.*, t.name as tag_name, t.color as tag_color, t.description as tag_description
                FROM user_conversation_tabs uct
                INNER JOIN tags t ON uct.tag_id = t.id
                WHERE uct.user_id = ?
                ORDER BY uct.position ASC, uct.id ASC";
        return Database::fetchAll($sql, [$userId]);
    }

    /**
     * Obter abas com contagem de conversas (apenas abertas)
     */
    public static function getByUserWithCounts(int $userId): array
    {
        $sql = "SELECT uct.*, t.name as tag_name, t.color as tag_color, t.description as tag_description,
                    (SELECT COUNT(DISTINCT ct.conversation_id) 
                     FROM conversation_tags ct 
                     INNER JOIN conversations c ON ct.conversation_id = c.id 
                     WHERE ct.tag_id = uct.tag_id AND c.status = 'open'
                    ) as conversation_count
                FROM user_conversation_tabs uct
                INNER JOIN tags t ON uct.tag_id = t.id
                WHERE uct.user_id = ?
                ORDER BY uct.position ASC, uct.id ASC";
        return Database::fetchAll($sql, [$userId]);
    }

    /**
     * Adicionar aba para usuário
     */
    public static function addTab(int $userId, int $tagId, ?int $position = null): bool
    {
        try {
            if ($position === null) {
                // Obter próxima posição
                $sql = "SELECT COALESCE(MAX(position), -1) + 1 as next_pos 
                        FROM user_conversation_tabs WHERE user_id = ?";
                $result = Database::fetch($sql, [$userId]);
                $position = $result ? (int)$result['next_pos'] : 0;
            }

            $sql = "INSERT IGNORE INTO user_conversation_tabs (user_id, tag_id, position) VALUES (?, ?, ?)";
            Database::execute($sql, [$userId, $tagId, $position]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao adicionar aba: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover aba do usuário
     */
    public static function removeTab(int $userId, int $tagId): bool
    {
        try {
            $sql = "DELETE FROM user_conversation_tabs WHERE user_id = ? AND tag_id = ?";
            Database::execute($sql, [$userId, $tagId]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao remover aba: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reordenar abas do usuário
     */
    public static function reorder(int $userId, array $tabIds): bool
    {
        try {
            $db = Database::getInstance();
            $db->beginTransaction();

            foreach ($tabIds as $position => $tabId) {
                $sql = "UPDATE user_conversation_tabs SET position = ? WHERE id = ? AND user_id = ?";
                Database::execute($sql, [$position, $tabId, $userId]);
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Erro ao reordenar abas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar se tag já é aba do usuário
     */
    public static function isTab(int $userId, int $tagId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM user_conversation_tabs WHERE user_id = ? AND tag_id = ?";
        $result = Database::fetch($sql, [$userId, $tagId]);
        return $result && $result['count'] > 0;
    }
}
