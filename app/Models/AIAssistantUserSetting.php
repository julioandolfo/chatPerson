<?php
/**
 * Model AIAssistantUserSetting
 * Configurações personalizadas do usuário para funcionalidades do Assistente IA
 */

namespace App\Models;

use App\Helpers\Database;

class AIAssistantUserSetting extends Model
{
    protected string $table = 'ai_assistant_user_settings';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'feature_key', 'enabled', 'ai_agent_id', 'custom_settings'
    ];
    protected bool $timestamps = true;

    /**
     * Obter configuração do usuário para uma funcionalidade
     */
    public static function getUserSetting(int $userId, string $featureKey): ?array
    {
        $sql = "SELECT u.*, a.name as agent_name, a.agent_type as agent_type
                FROM ai_assistant_user_settings u
                LEFT JOIN ai_agents a ON u.ai_agent_id = a.id
                WHERE u.user_id = ? AND u.feature_key = ?";
        return Database::fetch($sql, [$userId, $featureKey]);
    }

    /**
     * Obter todas as configurações do usuário
     */
    public static function getUserSettings(int $userId): array
    {
        $sql = "SELECT u.*, a.name as agent_name, a.agent_type as agent_type
                FROM ai_assistant_user_settings u
                LEFT JOIN ai_agents a ON u.ai_agent_id = a.id
                WHERE u.user_id = ?
                ORDER BY u.feature_key ASC";
        return Database::fetchAll($sql, [$userId]);
    }

    /**
     * Salvar ou atualizar configuração do usuário
     */
    public static function saveUserSetting(int $userId, string $featureKey, array $data): bool
    {
        $existing = self::getUserSetting($userId, $featureKey);
        
        $settingsData = [
            'user_id' => $userId,
            'feature_key' => $featureKey,
            'enabled' => $data['enabled'] ?? true,
            'ai_agent_id' => $data['ai_agent_id'] ?? null,
            'custom_settings' => !empty($data['custom_settings']) 
                ? json_encode($data['custom_settings'], JSON_UNESCAPED_UNICODE) 
                : null
        ];

        if ($existing) {
            return self::update($existing['id'], $settingsData);
        } else {
            return self::create($settingsData) > 0;
        }
    }

    /**
     * Verificar se funcionalidade está habilitada para o usuário
     */
    public static function isEnabledForUser(int $userId, string $featureKey): bool
    {
        $setting = self::getUserSetting($userId, $featureKey);
        
        // Se não tem configuração personalizada, usar padrão da funcionalidade
        if (!$setting) {
            $feature = \App\Models\AIAssistantFeature::getByKey($featureKey);
            return $feature ? (bool)$feature['enabled'] : false;
        }

        return (bool)$setting['enabled'];
    }

    /**
     * Obter agente preferido do usuário para uma funcionalidade
     */
    public static function getUserPreferredAgent(int $userId, string $featureKey): ?int
    {
        $setting = self::getUserSetting($userId, $featureKey);
        return $setting && !empty($setting['ai_agent_id']) ? (int)$setting['ai_agent_id'] : null;
    }

    /**
     * Obter configurações customizadas do usuário
     */
    public static function getCustomSettings(int $userId, string $featureKey): array
    {
        $setting = self::getUserSetting($userId, $featureKey);
        if (!$setting || empty($setting['custom_settings'])) {
            return [];
        }

        $settings = json_decode($setting['custom_settings'], true);
        return is_array($settings) ? $settings : [];
    }
}

