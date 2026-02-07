<?php
/**
 * Model MockupTemplate
 * Templates salvos do editor canvas
 */

namespace App\Models;

use App\Helpers\Database;

class MockupTemplate extends Model
{
    protected string $table = 'mockup_templates';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'description', 'category', 'canvas_data', 'canvas_width', 'canvas_height',
        'thumbnail_path', 'is_public', 'usage_count', 'created_by'
    ];
    protected array $hidden = [];
    protected bool $timestamps = true;

    /**
     * Criar novo template
     */
    public static function createTemplate(array $data): ?int
    {
        $fields = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'canvas_data' => json_encode($data['canvas_data']),
            'canvas_width' => $data['canvas_width'] ?? 1024,
            'canvas_height' => $data['canvas_height'] ?? 1024,
            'thumbnail_path' => $data['thumbnail_path'] ?? null,
            'is_public' => $data['is_public'] ?? false,
            'created_by' => $data['created_by'] ?? null
        ];

        $sql = "INSERT INTO mockup_templates (" . implode(', ', array_keys($fields)) . ")
                VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";

        Database::execute($sql, array_values($fields));
        return Database::lastInsertId();
    }

    /**
     * Buscar templates por usuário
     */
    public static function getByUser(int $userId, ?string $category = null, int $limit = 50): array
    {
        $params = [$userId];
        $where = "(created_by = ? OR is_public = true)";

        if ($category) {
            $where .= " AND category = ?";
            $params[] = $category;
        }

        $sql = "SELECT * FROM mockup_templates 
                WHERE $where 
                ORDER BY usage_count DESC, created_at DESC 
                LIMIT ?";
        $params[] = $limit;

        $templates = Database::fetchAll($sql, $params);

        // Decodificar canvas_data
        foreach ($templates as &$template) {
            if (!empty($template['canvas_data'])) {
                $template['canvas_data'] = json_decode($template['canvas_data'], true) ?? [];
            }
        }

        return $templates;
    }

    /**
     * Buscar templates públicos
     */
    public static function getPublic(?string $category = null, int $limit = 50): array
    {
        $params = [];
        $where = "is_public = true";

        if ($category) {
            $where .= " AND category = ?";
            $params[] = $category;
        }

        $sql = "SELECT * FROM mockup_templates 
                WHERE $where 
                ORDER BY usage_count DESC 
                LIMIT ?";
        $params[] = $limit;

        $templates = Database::fetchAll($sql, $params);

        foreach ($templates as &$template) {
            if (!empty($template['canvas_data'])) {
                $template['canvas_data'] = json_decode($template['canvas_data'], true) ?? [];
            }
        }

        return $templates;
    }

    /**
     * Buscar por categoria
     */
    public static function getByCategory(string $category, int $userId, int $limit = 50): array
    {
        $sql = "SELECT * FROM mockup_templates 
                WHERE category = ? AND (created_by = ? OR is_public = true)
                ORDER BY usage_count DESC 
                LIMIT ?";

        $templates = Database::fetchAll($sql, [$category, $userId, $limit]);

        foreach ($templates as &$template) {
            if (!empty($template['canvas_data'])) {
                $template['canvas_data'] = json_decode($template['canvas_data'], true) ?? [];
            }
        }

        return $templates;
    }

    /**
     * Incrementar contador de uso
     */
    public static function incrementUsage(int $id): void
    {
        $sql = "UPDATE mockup_templates SET usage_count = usage_count + 1 WHERE id = ?";
        Database::execute($sql, [$id]);
    }

    /**
     * Buscar por ID
     */
    public static function findById(int $id): ?array
    {
        $sql = "SELECT * FROM mockup_templates WHERE id = ?";
        $template = Database::fetch($sql, [$id]);

        if ($template && !empty($template['canvas_data'])) {
            $template['canvas_data'] = json_decode($template['canvas_data'], true) ?? [];
        }

        return $template ?: null;
    }

    /**
     * Atualizar template
     */
    public static function updateTemplate(int $id, array $data): bool
    {
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if ($key === 'canvas_data') {
                $value = json_encode($value);
            }
            $updates[] = "$key = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = "UPDATE mockup_templates SET " . implode(', ', $updates) . " WHERE id = ?";
        return Database::execute($sql, $params);
    }

    /**
     * Deletar template
     */
    public static function deleteTemplate(int $id): bool
    {
        $sql = "DELETE FROM mockup_templates WHERE id = ?";
        return Database::execute($sql, [$id]);
    }
}
