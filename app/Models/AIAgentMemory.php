<?php
/**
 * Model AIAgentMemory
 * Memória persistente dos agentes de IA
 */

namespace App\Models;

use App\Helpers\PostgreSQL;

class AIAgentMemory extends PostgreSQLModel
{
    protected string $table = 'ai_agent_memory';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'ai_agent_id',
        'conversation_id',
        'memory_type',
        'key',
        'value',
        'importance',
        'expires_at'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar memórias por agente
     */
    public static function getByAgent(int $agentId, int $conversationId = null, int $limit = 50): array
    {
        if ($conversationId) {
            $sql = "SELECT * FROM ai_agent_memory 
                    WHERE ai_agent_id = ? AND conversation_id = ? 
                    AND (expires_at IS NULL OR expires_at > NOW())
                    ORDER BY importance DESC, created_at DESC 
                    LIMIT ?";
            return PostgreSQL::query($sql, [$agentId, $conversationId, $limit]);
        }
        
        $sql = "SELECT * FROM ai_agent_memory 
                WHERE ai_agent_id = ? 
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY importance DESC, created_at DESC 
                LIMIT ?";
        return PostgreSQL::query($sql, [$agentId, $limit]);
    }

    /**
     * Buscar memórias por tipo
     */
    public static function getByType(int $agentId, string $memoryType, int $limit = 50): array
    {
        $sql = "SELECT * FROM ai_agent_memory 
                WHERE ai_agent_id = ? AND memory_type = ? 
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY importance DESC, created_at DESC 
                LIMIT ?";
        return PostgreSQL::query($sql, [$agentId, $memoryType, $limit]);
    }

    /**
     * Buscar memória por chave
     */
    public static function getByKey(int $agentId, string $key): ?array
    {
        $sql = "SELECT * FROM ai_agent_memory 
                WHERE ai_agent_id = ? AND key = ? 
                AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1";
        return PostgreSQL::fetch($sql, [$agentId, $key]);
    }

    /**
     * Salvar ou atualizar memória
     */
    public static function saveOrUpdate(int $agentId, int $conversationId, string $memoryType, string $key, string $value, float $importance = 0.5, ?string $expiresAt = null): int
    {
        // Verificar se já existe
        $existing = self::getByKey($agentId, $key);
        
        if ($existing) {
            // Atualizar
            self::update($existing['id'], [
                'value' => $value,
                'importance' => $importance,
                'expires_at' => $expiresAt,
                'conversation_id' => $conversationId
            ]);
            return $existing['id'];
        } else {
            // Criar
            return self::create([
                'ai_agent_id' => $agentId,
                'conversation_id' => $conversationId,
                'memory_type' => $memoryType,
                'key' => $key,
                'value' => $value,
                'importance' => $importance,
                'expires_at' => $expiresAt
            ]);
        }
    }

    /**
     * Limpar memórias expiradas
     */
    public static function cleanExpired(): int
    {
        $sql = "DELETE FROM ai_agent_memory WHERE expires_at IS NOT NULL AND expires_at < NOW()";
        $stmt = PostgreSQL::getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Contar memórias por agente
     */
    public static function countByAgent(int $agentId): int
    {
        $sql = "SELECT COUNT(*) as total FROM ai_agent_memory 
                WHERE ai_agent_id = ? 
                AND (expires_at IS NULL OR expires_at > NOW())";
        $result = PostgreSQL::fetch($sql, [$agentId]);
        return (int)($result['total'] ?? 0);
    }
}

