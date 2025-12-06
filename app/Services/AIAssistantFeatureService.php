<?php
/**
 * Service AIAssistantFeatureService
 * Gerenciar funcionalidades do Assistente IA
 */

namespace App\Services;

use App\Models\AIAssistantFeature;
use App\Models\AIAssistantUserSetting;
use App\Models\AIAgent;

class AIAssistantFeatureService
{
    private static string $cacheDir = __DIR__ . '/../../storage/cache/ai_assistant/';
    private static int $cacheTTL = 300; // 5 minutos

    /**
     * Obter cache
     */
    private static function getCache(string $key): ?array
    {
        $cacheFile = self::$cacheDir . md5($key) . '.json';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        if (!$data || ($data['expires'] ?? 0) < time()) {
            @unlink($cacheFile);
            return null;
        }
        
        return $data['value'] ?? null;
    }

    /**
     * Salvar no cache
     */
    private static function setCache(string $key, array $value): void
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }
        
        $cacheFile = self::$cacheDir . md5($key) . '.json';
        $data = [
            'value' => $value,
            'expires' => time() + self::$cacheTTL
        ];
        
        file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Invalidar cache
     */
    public static function invalidateCache(?string $pattern = null): void
    {
        if (!is_dir(self::$cacheDir)) {
            return;
        }
        
        $files = glob(self::$cacheDir . '*.json');
        foreach ($files as $file) {
            if ($pattern === null || strpos($file, md5($pattern)) !== false) {
                @unlink($file);
            }
        }
    }

    /**
     * Listar funcionalidades disponíveis para um usuário
     */
    public static function listForUser(int $userId): array
    {
        // Verificar cache
        $cacheKey = "user_{$userId}_features";
        $cached = self::getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $features = AIAssistantFeature::getActive();
        $userSettings = AIAssistantUserSetting::getUserSettings($userId);
        
        // Criar mapa de configurações do usuário
        $userSettingsMap = [];
        foreach ($userSettings as $setting) {
            $userSettingsMap[$setting['feature_key']] = $setting;
        }

        // Combinar funcionalidades com configurações do usuário
        $result = [];
        foreach ($features as $feature) {
            $featureKey = $feature['feature_key'];
            $userSetting = $userSettingsMap[$featureKey] ?? null;

            // Verificar se está habilitado para o usuário
            $enabled = $userSetting 
                ? (bool)$userSetting['enabled'] 
                : (bool)$feature['enabled'];

            if (!$enabled) {
                continue; // Pular funcionalidades desabilitadas
            }

            // Obter configurações (mesclar padrão com personalizadas)
            $defaultSettings = json_decode($feature['settings'] ?? '{}', true);
            $customSettings = $userSetting && !empty($userSetting['custom_settings'])
                ? json_decode($userSetting['custom_settings'], true)
                : [];
            
            $mergedSettings = array_merge($defaultSettings ?? [], $customSettings);

            $result[] = [
                'id' => $feature['id'],
                'feature_key' => $featureKey,
                'name' => $feature['name'],
                'description' => $feature['description'],
                'icon' => $feature['icon'],
                'enabled' => $enabled,
                'default_ai_agent_id' => $feature['default_ai_agent_id'],
                'default_agent_name' => $feature['default_agent_name'] ?? null,
                'auto_select_agent' => (bool)$feature['auto_select_agent'],
                'user_preferred_agent_id' => $userSetting ? $userSetting['ai_agent_id'] : null,
                'user_preferred_agent_name' => $userSetting ? ($userSetting['agent_name'] ?? null) : null,
                'settings' => $mergedSettings,
                'order_index' => $feature['order_index']
            ];
        }

        // Ordenar por order_index
        usort($result, function($a, $b) {
            return ($a['order_index'] ?? 0) - ($b['order_index'] ?? 0);
        });

        // Salvar no cache
        self::setCache($cacheKey, $result);

        return $result;
    }

    /**
     * Obter funcionalidade específica para usuário
     */
    public static function getForUser(int $userId, string $featureKey): ?array
    {
        $features = self::listForUser($userId);
        foreach ($features as $feature) {
            if ($feature['feature_key'] === $featureKey) {
                return $feature;
            }
        }
        return null;
    }

    /**
     * Verificar se funcionalidade está disponível para usuário
     */
    public static function isAvailableForUser(int $userId, string $featureKey): bool
    {
        return self::getForUser($userId, $featureKey) !== null;
    }

    /**
     * Atualizar configuração do usuário
     */
    public static function updateUserSetting(int $userId, string $featureKey, array $data): bool
    {
        return AIAssistantUserSetting::saveUserSetting($userId, $featureKey, $data);
    }

    /**
     * Obter todas as funcionalidades (admin)
     */
    public static function listAll(): array
    {
        return AIAssistantFeature::getActive();
    }

    /**
     * Atualizar funcionalidade (admin)
     */
    public static function updateFeature(int $featureId, array $data): bool
    {
        $updateData = [];
        
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['icon'])) $updateData['icon'] = $data['icon'];
        if (isset($data['enabled'])) $updateData['enabled'] = $data['enabled'];
        if (isset($data['default_ai_agent_id'])) $updateData['default_ai_agent_id'] = $data['default_ai_agent_id'];
        if (isset($data['auto_select_agent'])) $updateData['auto_select_agent'] = $data['auto_select_agent'];
        if (isset($data['order_index'])) $updateData['order_index'] = $data['order_index'];
        
        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
        }

        $result = AIAssistantFeature::update($featureId, $updateData);
        
        // Invalidar cache
        if ($result) {
            self::invalidateCache();
        }
        
        return $result;
    }
}

