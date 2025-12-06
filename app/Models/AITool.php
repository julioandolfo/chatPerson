<?php
/**
 * Model AITool
 * Tools disponíveis para agentes de IA
 */

namespace App\Models;

use App\Helpers\Database;

class AITool extends Model
{
    protected string $table = 'ai_tools';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'slug', 'description', 'tool_type', 'function_schema', 'config', 'enabled'];
    protected bool $timestamps = true;

    /**
     * Obter tool por slug
     */
    public static function findBySlug(string $slug): ?array
    {
        return self::whereFirst('slug', '=', $slug);
    }

    /**
     * Obter tools por tipo
     */
    public static function getByType(string $type): array
    {
        $sql = "SELECT * FROM ai_tools 
                WHERE tool_type = ? AND enabled = TRUE 
                ORDER BY name ASC";
        return Database::fetchAll($sql, [$type]);
    }

    /**
     * Obter tools de um agente
     */
    public static function getByAgent(int $agentId): array
    {
        $sql = "SELECT t.*, at.config as agent_tool_config, at.enabled as tool_enabled
                FROM ai_tools t
                INNER JOIN ai_agent_tools at ON t.id = at.ai_tool_id
                WHERE at.ai_agent_id = ? AND at.enabled = TRUE AND t.enabled = TRUE
                ORDER BY t.name ASC";
        return Database::fetchAll($sql, [$agentId]);
    }

    /**
     * Obter schema da função para OpenAI
     */
    public static function getFunctionSchema(int $toolId): ?array
    {
        $tool = self::find($toolId);
        if (!$tool || !$tool['function_schema']) {
            return null;
        }
        
        $schema = is_string($tool['function_schema']) 
            ? json_decode($tool['function_schema'], true) 
            : $tool['function_schema'];
        
        return $schema;
    }

    /**
     * Obter todas as tools ativas
     */
    public static function getAllActive(): array
    {
        $sql = "SELECT * FROM ai_tools 
                WHERE enabled = TRUE 
                ORDER BY tool_type ASC, name ASC";
        return Database::fetchAll($sql);
    }
}

