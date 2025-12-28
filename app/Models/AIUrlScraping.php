<?php
/**
 * Model AIUrlScraping
 * URLs sendo processadas para adicionar à knowledge base
 */

namespace App\Models;

use App\Helpers\PostgreSQL;

class AIUrlScraping extends PostgreSQLModel
{
    protected string $table = 'ai_url_scraping';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'ai_agent_id',
        'url',
        'title',
        'content',
        'scraped_at',
        'status',
        'error_message',
        'chunks_created',
        'metadata'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar URLs pendentes
     */
    public static function getPending(int $agentId = null, int $limit = 50): array
    {
        if ($agentId) {
            $sql = "SELECT * FROM ai_url_scraping 
                    WHERE status = 'pending' AND ai_agent_id = ? 
                    ORDER BY created_at ASC 
                    LIMIT ?";
            return PostgreSQL::query($sql, [$agentId, $limit]);
        }
        
        $sql = "SELECT * FROM ai_url_scraping 
                WHERE status = 'pending' 
                ORDER BY created_at ASC 
                LIMIT ?";
        return PostgreSQL::query($sql, [$limit]);
    }

    /**
     * Marcar como processando
     */
    public static function markAsProcessing(int $id): bool
    {
        return self::update($id, [
            'status' => 'processing',
            'scraped_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar como concluído
     */
    public static function markAsCompleted(int $id, int $chunksCreated = 0): bool
    {
        return self::update($id, [
            'status' => 'completed',
            'chunks_created' => $chunksCreated
        ]);
    }

    /**
     * Marcar como falhou
     */
    public static function markAsFailed(int $id, string $errorMessage): bool
    {
        return self::update($id, [
            'status' => 'failed',
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Verificar se URL já existe para o agente
     */
    public static function urlExists(int $agentId, string $url): bool
    {
        $sql = "SELECT COUNT(*) as total FROM ai_url_scraping 
                WHERE ai_agent_id = ? AND url = ?";
        $result = PostgreSQL::fetch($sql, [$agentId, $url]);
        return (int)($result['total'] ?? 0) > 0;
    }

    /**
     * Buscar URLs por agente
     */
    public static function getByAgent(int $agentId, int $limit = 100): array
    {
        $sql = "SELECT * FROM ai_url_scraping 
                WHERE ai_agent_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        return PostgreSQL::query($sql, [$agentId, $limit]);
    }

    /**
     * Contar URLs por status
     */
    public static function countByStatus(int $agentId, string $status): int
    {
        $sql = "SELECT COUNT(*) as total FROM ai_url_scraping 
                WHERE ai_agent_id = ? AND status = ?";
        $result = PostgreSQL::fetch($sql, [$agentId, $status]);
        return (int)($result['total'] ?? 0);
    }
}

