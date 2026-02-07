<?php
/**
 * Model MockupProduct
 * Produtos/Brindes salvos para mockups
 */

namespace App\Models;

use App\Helpers\Database;

class MockupProduct extends Model
{
    protected string $table = 'mockup_products';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'category', 'description', 'image_path', 'thumbnail_path',
        'is_template', 'metadata', 'usage_count', 'created_by'
    ];
    protected array $hidden = [];
    protected bool $timestamps = true;

    /**
     * Criar novo produto
     */
    public static function createProduct(array $data): ?int
    {
        $fields = [
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'image_path' => $data['image_path'],
            'thumbnail_path' => $data['thumbnail_path'] ?? null,
            'is_template' => $data['is_template'] ?? false,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_by' => $data['created_by'] ?? null
        ];

        $sql = "INSERT INTO mockup_products (" . implode(', ', array_keys($fields)) . ")
                VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";

        Database::execute($sql, array_values($fields));
        return Database::lastInsertId();
    }

    /**
     * Buscar produtos por usuário
     */
    public static function getByUser(int $userId, ?string $category = null, int $limit = 50): array
    {
        $params = [$userId];
        $where = "created_by = ?";

        if ($category) {
            $where .= " AND category = ?";
            $params[] = $category;
        }

        $sql = "SELECT * FROM mockup_products 
                WHERE $where 
                ORDER BY usage_count DESC, created_at DESC 
                LIMIT ?";
        $params[] = $limit;

        $products = Database::fetchAll($sql, $params);

        // Decodificar metadata
        foreach ($products as &$product) {
            if (!empty($product['metadata'])) {
                $product['metadata'] = json_decode($product['metadata'], true) ?? [];
            }
        }

        return $products;
    }

    /**
     * Buscar produtos recentes
     */
    public static function getRecent(int $userId, int $limit = 10): array
    {
        $sql = "SELECT * FROM mockup_products 
                WHERE created_by = ? 
                ORDER BY created_at DESC 
                LIMIT ?";

        return Database::fetchAll($sql, [$userId, $limit]);
    }

    /**
     * Buscar por categoria
     */
    public static function getByCategory(string $category, int $limit = 50): array
    {
        $sql = "SELECT * FROM mockup_products 
                WHERE category = ? AND (is_template = true OR created_by IS NULL)
                ORDER BY usage_count DESC 
                LIMIT ?";

        return Database::fetchAll($sql, [$category, $limit]);
    }

    /**
     * Incrementar contador de uso
     */
    public static function incrementUsage(int $id): void
    {
        $sql = "UPDATE mockup_products SET usage_count = usage_count + 1 WHERE id = ?";
        Database::execute($sql, [$id]);
    }

    /**
     * Buscar por ID
     */
    public static function findById(int $id): ?array
    {
        $sql = "SELECT * FROM mockup_products WHERE id = ?";
        $product = Database::fetch($sql, [$id]);

        if ($product && !empty($product['metadata'])) {
            $product['metadata'] = json_decode($product['metadata'], true) ?? [];
        }

        return $product ?: null;
    }

    /**
     * Deletar produto
     */
    public static function deleteProduct(int $id): bool
    {
        $sql = "DELETE FROM mockup_products WHERE id = ?";
        return Database::execute($sql, [$id]);
    }
}
