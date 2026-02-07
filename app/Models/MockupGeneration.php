<?php
/**
 * Model MockupGeneration
 * Histórico de gerações de mockup
 */

namespace App\Models;

use App\Helpers\Database;

class MockupGeneration extends Model
{
    protected string $table = 'mockup_generations';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id', 'product_id', 'product_image_path', 'logo_id', 'logo_image_path',
        'logo_config', 'generation_mode', 'original_prompt', 'optimized_prompt', 'gpt4_analysis',
        'dalle_model', 'dalle_size', 'dalle_quality', 'canvas_data', 'result_image_path',
        'result_thumbnail_path', 'result_size', 'status', 'error_message', 'processing_time',
        'gpt4_cost', 'dalle_cost', 'total_cost', 'sent_as_message', 'message_id', 'generated_by'
    ];
    protected array $hidden = [];
    protected bool $timestamps = true;

    /**
     * Criar nova geração
     */
    public static function createGeneration(array $data): ?int
    {
        $fields = [
            'conversation_id' => $data['conversation_id'],
            'product_id' => $data['product_id'] ?? null,
            'product_image_path' => $data['product_image_path'] ?? null,
            'logo_id' => $data['logo_id'] ?? null,
            'logo_image_path' => $data['logo_image_path'] ?? null,
            'logo_config' => isset($data['logo_config']) ? json_encode($data['logo_config']) : null,
            'generation_mode' => $data['generation_mode'],
            'original_prompt' => $data['original_prompt'] ?? null,
            'dalle_model' => $data['dalle_model'] ?? 'dall-e-3',
            'dalle_size' => $data['dalle_size'] ?? '1024x1024',
            'dalle_quality' => $data['dalle_quality'] ?? 'standard',
            'status' => $data['status'] ?? 'generating',
            'generated_by' => $data['generated_by'] ?? null
        ];

        $sql = "INSERT INTO mockup_generations (" . implode(', ', array_keys($fields)) . ")
                VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";

        Database::execute($sql, array_values($fields));
        return Database::lastInsertId();
    }

    /**
     * Atualizar geração
     */
    public static function updateGeneration(int $id, array $data): bool
    {
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['logo_config', 'canvas_data'])) {
                $value = json_encode($value);
            }
            $updates[] = "$key = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = "UPDATE mockup_generations SET " . implode(', ', $updates) . " WHERE id = ?";
        return Database::execute($sql, $params);
    }

    /**
     * Marcar como completada
     */
    public static function markAsCompleted(int $id, string $resultPath, ?string $thumbnailPath, int $processingTime): bool
    {
        $sql = "UPDATE mockup_generations 
                SET status = 'completed', 
                    result_image_path = ?, 
                    result_thumbnail_path = ?,
                    processing_time = ?
                WHERE id = ?";

        return Database::execute($sql, [$resultPath, $thumbnailPath, $processingTime, $id]);
    }

    /**
     * Marcar como falha
     */
    public static function markAsFailed(int $id, string $errorMessage): bool
    {
        $sql = "UPDATE mockup_generations 
                SET status = 'failed', error_message = ? 
                WHERE id = ?";

        return Database::execute($sql, [$errorMessage, $id]);
    }

    /**
     * Buscar gerações de uma conversa
     */
    public static function getByConversation(int $conversationId, int $limit = 50): array
    {
        $sql = "SELECT mg.*, 
                       mp.name as product_name,
                       u.name as generated_by_name
                FROM mockup_generations mg
                LEFT JOIN mockup_products mp ON mg.product_id = mp.id
                LEFT JOIN users u ON mg.generated_by = u.id
                WHERE mg.conversation_id = ?
                ORDER BY mg.created_at DESC
                LIMIT ?";

        $generations = Database::fetchAll($sql, [$conversationId, $limit]);

        // Decodificar JSON
        foreach ($generations as &$gen) {
            if (!empty($gen['logo_config'])) {
                $gen['logo_config'] = json_decode($gen['logo_config'], true) ?? [];
            }
            if (!empty($gen['canvas_data'])) {
                $gen['canvas_data'] = json_decode($gen['canvas_data'], true) ?? [];
            }
        }

        return $generations;
    }

    /**
     * Buscar apenas completadas de uma conversa
     */
    public static function getCompletedByConversation(int $conversationId, int $limit = 50): array
    {
        $sql = "SELECT * FROM mockup_generations 
                WHERE conversation_id = ? AND status = 'completed'
                ORDER BY created_at DESC
                LIMIT ?";

        $generations = Database::fetchAll($sql, [$conversationId, $limit]);

        foreach ($generations as &$gen) {
            if (!empty($gen['logo_config'])) {
                $gen['logo_config'] = json_decode($gen['logo_config'], true) ?? [];
            }
            if (!empty($gen['canvas_data'])) {
                $gen['canvas_data'] = json_decode($gen['canvas_data'], true) ?? [];
            }
        }

        return $generations;
    }

    /**
     * Buscar por ID
     */
    public static function findById(int $id): ?array
    {
        $sql = "SELECT * FROM mockup_generations WHERE id = ?";
        $gen = Database::fetch($sql, [$id]);

        if ($gen) {
            if (!empty($gen['logo_config'])) {
                $gen['logo_config'] = json_decode($gen['logo_config'], true) ?? [];
            }
            if (!empty($gen['canvas_data'])) {
                $gen['canvas_data'] = json_decode($gen['canvas_data'], true) ?? [];
            }
        }

        return $gen ?: null;
    }

    /**
     * Marcar como enviada em mensagem
     */
    public static function markAsSent(int $id, int $messageId): bool
    {
        $sql = "UPDATE mockup_generations 
                SET sent_as_message = true, message_id = ? 
                WHERE id = ?";

        return Database::execute($sql, [$messageId, $id]);
    }

    /**
     * Deletar geração
     */
    public static function deleteGeneration(int $id): bool
    {
        $sql = "DELETE FROM mockup_generations WHERE id = ?";
        return Database::execute($sql, [$id]);
    }

    /**
     * Estatísticas de uso
     */
    public static function getStats(?int $conversationId = null): array
    {
        $where = $conversationId ? "WHERE conversation_id = ?" : "";
        $params = $conversationId ? [$conversationId] : [];

        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    COUNT(CASE WHEN generation_mode = 'ai' THEN 1 END) as ai_mode,
                    COUNT(CASE WHEN generation_mode = 'manual' THEN 1 END) as manual_mode,
                    COUNT(CASE WHEN generation_mode = 'hybrid' THEN 1 END) as hybrid_mode,
                    AVG(processing_time) as avg_processing_time,
                    SUM(total_cost) as total_cost
                FROM mockup_generations
                $where";

        return Database::fetch($sql, $params) ?: [];
    }
}
