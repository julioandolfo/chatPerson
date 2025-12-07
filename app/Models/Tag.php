<?php
/**
 * Model Tag
 */

namespace App\Models;

use App\Helpers\Database;

class Tag extends Model
{
    protected string $table = 'tags';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'color', 'description'];
    protected bool $timestamps = true;

    /**
     * Obter tags de uma conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        $sql = "SELECT t.* FROM tags t
                INNER JOIN conversation_tags ct ON t.id = ct.tag_id
                WHERE ct.conversation_id = ?
                ORDER BY t.name ASC";
        return Database::fetchAll($sql, [$conversationId]);
    }

    /**
     * Adicionar tag a conversa
     */
    public static function addToConversation(int $conversationId, int $tagId): bool
    {
        try {
            $sql = "INSERT IGNORE INTO conversation_tags (conversation_id, tag_id) VALUES (?, ?)";
            Database::execute($sql, [$conversationId, $tagId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remover tag de conversa
     */
    public static function removeFromConversation(int $conversationId, int $tagId): bool
    {
        try {
            $sql = "DELETE FROM conversation_tags WHERE conversation_id = ? AND tag_id = ?";
            Database::execute($sql, [$conversationId, $tagId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obter tags de um contato (através das conversas do contato)
     */
    public static function getByContact(int $contactId): array
    {
        $sql = "SELECT DISTINCT t.* FROM tags t
                INNER JOIN conversation_tags ct ON t.id = ct.tag_id
                INNER JOIN conversations c ON ct.conversation_id = c.id
                WHERE c.contact_id = ?
                ORDER BY t.name ASC";
        return Database::fetchAll($sql, [$contactId]);
    }
}

