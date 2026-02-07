<?php
/**
 * Model ConversationLogo
 * Logos enviadas em conversas
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationLogo extends Model
{
    protected string $table = 'conversation_logos';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id', 'contact_id', 'logo_path', 'thumbnail_path',
        'original_filename', 'file_size', 'mime_type', 'dimensions', 'is_primary'
    ];
    protected array $hidden = [];
    protected bool $timestamps = false;

    /**
     * Criar novo logo para conversa
     */
    public static function createLogo(array $data): ?int
    {
        $fields = [
            'conversation_id' => $data['conversation_id'],
            'contact_id' => $data['contact_id'] ?? null,
            'logo_path' => $data['logo_path'],
            'thumbnail_path' => $data['thumbnail_path'] ?? null,
            'original_filename' => $data['original_filename'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'mime_type' => $data['mime_type'] ?? null,
            'dimensions' => isset($data['dimensions']) ? json_encode($data['dimensions']) : null,
            'is_primary' => $data['is_primary'] ?? false
        ];

        // Se definir como primária, desmarcar outras
        if ($fields['is_primary']) {
            self::unsetPrimary($data['conversation_id']);
        }

        $sql = "INSERT INTO conversation_logos (" . implode(', ', array_keys($fields)) . ")
                VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";

        Database::execute($sql, array_values($fields));
        return Database::lastInsertId();
    }

    /**
     * Buscar logos de uma conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        $sql = "SELECT * FROM conversation_logos 
                WHERE conversation_id = ? 
                ORDER BY is_primary DESC, uploaded_at DESC";

        $logos = Database::fetchAll($sql, [$conversationId]);

        // Decodificar dimensions
        foreach ($logos as &$logo) {
            if (!empty($logo['dimensions'])) {
                $logo['dimensions'] = json_decode($logo['dimensions'], true) ?? [];
            }
        }

        return $logos;
    }

    /**
     * Buscar logo primária da conversa
     */
    public static function getPrimaryByConversation(int $conversationId): ?array
    {
        $sql = "SELECT * FROM conversation_logos 
                WHERE conversation_id = ? AND is_primary = true 
                LIMIT 1";

        $logo = Database::fetch($sql, [$conversationId]);

        if ($logo && !empty($logo['dimensions'])) {
            $logo['dimensions'] = json_decode($logo['dimensions'], true) ?? [];
        }

        return $logo ?: null;
    }

    /**
     * Buscar logo mais recente da conversa
     */
    public static function getLatestByConversation(int $conversationId): ?array
    {
        $sql = "SELECT * FROM conversation_logos 
                WHERE conversation_id = ? 
                ORDER BY uploaded_at DESC 
                LIMIT 1";

        $logo = Database::fetch($sql, [$conversationId]);

        if ($logo && !empty($logo['dimensions'])) {
            $logo['dimensions'] = json_decode($logo['dimensions'], true) ?? [];
        }

        return $logo ?: null;
    }

    /**
     * Definir logo como primária
     */
    public static function setPrimary(int $id, int $conversationId): bool
    {
        // Desmarcar outras
        self::unsetPrimary($conversationId);

        // Marcar esta
        $sql = "UPDATE conversation_logos SET is_primary = true WHERE id = ?";
        return Database::execute($sql, [$id]);
    }

    /**
     * Desmarcar todas as logos primárias de uma conversa
     */
    public static function unsetPrimary(int $conversationId): void
    {
        $sql = "UPDATE conversation_logos SET is_primary = false WHERE conversation_id = ?";
        Database::execute($sql, [$conversationId]);
    }

    /**
     * Buscar por ID
     */
    public static function findById(int $id): ?array
    {
        $sql = "SELECT * FROM conversation_logos WHERE id = ?";
        $logo = Database::fetch($sql, [$id]);

        if ($logo && !empty($logo['dimensions'])) {
            $logo['dimensions'] = json_decode($logo['dimensions'], true) ?? [];
        }

        return $logo ?: null;
    }

    /**
     * Deletar logo
     */
    public static function deleteLogo(int $id): bool
    {
        $sql = "DELETE FROM conversation_logos WHERE id = ?";
        return Database::execute($sql, [$id]);
    }
}
