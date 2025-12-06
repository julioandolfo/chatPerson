<?php
/**
 * Model AIAssistantFeature
 * Funcionalidades do Assistente IA
 */

namespace App\Models;

use App\Helpers\Database;

class AIAssistantFeature extends Model
{
    protected string $table = 'ai_assistant_features';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'feature_key', 'name', 'description', 'icon', 'enabled', 
        'default_ai_agent_id', 'auto_select_agent', 'settings', 'order_index'
    ];
    protected bool $timestamps = true;

    /**
     * Obter todas as funcionalidades ativas
     */
    public static function getActive(): array
    {
        $sql = "SELECT f.*, a.name as default_agent_name, a.agent_type as default_agent_type
                FROM ai_assistant_features f
                LEFT JOIN ai_agents a ON f.default_ai_agent_id = a.id
                WHERE f.enabled = TRUE
                ORDER BY f.order_index ASC, f.name ASC";
        return Database::fetchAll($sql);
    }

    /**
     * Obter funcionalidade por chave
     */
    public static function getByKey(string $featureKey): ?array
    {
        $sql = "SELECT f.*, a.name as default_agent_name, a.agent_type as default_agent_type
                FROM ai_assistant_features f
                LEFT JOIN ai_agents a ON f.default_ai_agent_id = a.id
                WHERE f.feature_key = ?";
        return Database::fetch($sql, [$featureKey]);
    }

    /**
     * Obter configurações da funcionalidade
     */
    public static function getSettings(string $featureKey): array
    {
        $feature = self::getByKey($featureKey);
        if (!$feature) {
            return [];
        }

        $settings = json_decode($feature['settings'] ?? '{}', true);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Atualizar configurações da funcionalidade
     */
    public static function updateSettings(string $featureKey, array $settings): bool
    {
        $feature = self::getByKey($featureKey);
        if (!$feature) {
            return false;
        }

        return self::update($feature['id'], [
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Obter agentes disponíveis para uma funcionalidade
     */
    public static function getAvailableAgents(string $featureKey): array
    {
        $sql = "SELECT a.*, faa.priority, faa.conditions, faa.enabled as rule_enabled
                FROM ai_assistant_feature_agents faa
                INNER JOIN ai_agents a ON faa.ai_agent_id = a.id
                WHERE faa.feature_key = ? AND faa.enabled = TRUE AND a.enabled = TRUE
                ORDER BY faa.priority DESC, a.name ASC";
        return Database::fetchAll($sql, [$featureKey]);
    }
}

