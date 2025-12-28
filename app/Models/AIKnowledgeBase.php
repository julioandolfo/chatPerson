<?php
/**
 * Model AIKnowledgeBase
 * Base de conhecimento vetorizada para sistema RAG
 */

namespace App\Models;

use App\Helpers\PostgreSQL;

class AIKnowledgeBase extends PostgreSQLModel
{
    protected string $table = 'ai_knowledge_base';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'ai_agent_id',
        'content_type',
        'title',
        'content',
        'source_url',
        'metadata',
        'embedding',
        'chunk_index'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar conhecimentos por agente
     */
    public static function getByAgent(int $agentId, int $limit = 100): array
    {
        $sql = "SELECT * FROM ai_knowledge_base 
                WHERE ai_agent_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        return PostgreSQL::query($sql, [$agentId, $limit]);
    }

    /**
     * Buscar conhecimentos similares usando busca vetorial
     * 
     * @param int $agentId ID do agente
     * @param array $queryEmbedding Embedding da query (array de floats)
     * @param int $limit Número de resultados
     * @param float $threshold Limiar de similaridade (0-1)
     * @return array
     */
    public static function findSimilar(int $agentId, array $queryEmbedding, int $limit = 5, float $threshold = 0.7): array
    {
        // Converter array para string PostgreSQL
        $embeddingStr = '[' . implode(',', $queryEmbedding) . ']';
        
        $sql = "
            SELECT 
                id,
                ai_agent_id,
                content_type,
                title,
                content,
                source_url,
                metadata,
                chunk_index,
                1 - (embedding <=> ?::vector) as similarity,
                created_at
            FROM ai_knowledge_base
            WHERE ai_agent_id = ?
                AND embedding IS NOT NULL
                AND (1 - (embedding <=> ?::vector)) >= ?
            ORDER BY embedding <=> ?::vector
            LIMIT ?
        ";
        
        return PostgreSQL::query($sql, [
            $embeddingStr,
            $agentId,
            $embeddingStr,
            $threshold,
            $embeddingStr,
            $limit
        ]);
    }

    /**
     * Adicionar conhecimento com embedding
     */
    public static function createWithEmbedding(array $data, array $embedding): int
    {
        $instance = new static();
        
        // Filtrar apenas campos fillable
        $data = array_intersect_key($data, array_flip($instance->fillable));
        
        // Adicionar timestamps
        if ($instance->timestamps) {
            if (!isset($data['created_at'])) {
                $data['created_at'] = date('Y-m-d H:i:s');
            }
            if (!isset($data['updated_at'])) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
        }

        // Converter embedding para formato PostgreSQL
        $embeddingStr = '[' . implode(',', $embedding) . ']';
        
        // Converter metadata para JSON se for array
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }

        $fields = array_keys($data);
        $fields[] = 'embedding';
        $values = array_values($data);
        $values[] = $embeddingStr;
        
        $placeholders = array_fill(0, count($values), '?');
        
        $sql = "INSERT INTO {$instance->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ") RETURNING id";

        $stmt = PostgreSQL::getConnection()->prepare($sql);
        $stmt->execute($values);
        $result = $stmt->fetch();
        return $result['id'] ?? 0;
    }

    /**
     * Atualizar embedding
     */
    public static function updateEmbedding(int $id, array $embedding): bool
    {
        $embeddingStr = '[' . implode(',', $embedding) . ']';
        $sql = "UPDATE ai_knowledge_base SET embedding = ?::vector, updated_at = NOW() WHERE id = ?";
        return PostgreSQL::execute($sql, [$embeddingStr, $id]);
    }

    /**
     * Contar conhecimentos por agente
     */
    public static function countByAgent(int $agentId): int
    {
        $sql = "SELECT COUNT(*) as total FROM ai_knowledge_base WHERE ai_agent_id = ?";
        $result = PostgreSQL::fetch($sql, [$agentId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Buscar por tipo de conteúdo
     */
    public static function getByContentType(int $agentId, string $contentType, int $limit = 100): array
    {
        $sql = "SELECT * FROM ai_knowledge_base 
                WHERE ai_agent_id = ? AND content_type = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        return PostgreSQL::query($sql, [$agentId, $contentType, $limit]);
    }
}

