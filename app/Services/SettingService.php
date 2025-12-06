<?php
/**
 * Service SettingService
 * Gerencia configurações do sistema
 */

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    /**
     * Obter configuração
     */
    public static function get(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }

    /**
     * Definir configuração
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general'): bool
    {
        return Setting::set($key, $value, $type, $group);
    }

    /**
     * Obter configurações de um grupo
     */
    public static function getGroup(string $group): array
    {
        return Setting::getByGroup($group);
    }

    /**
     * Salvar múltiplas configurações
     */
    public static function saveMultiple(array $settings, string $group = 'general'): bool
    {
        foreach ($settings as $key => $value) {
            $type = self::detectType($value);
            Setting::set($key, $value, $type, $group);
        }
        return true;
    }

    /**
     * Detectar tipo do valor
     */
    private static function detectType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_array($value)) {
            return 'json';
        }
        return 'string';
    }

    /**
     * Obter configurações gerais padrão
     */
    public static function getDefaultGeneralSettings(): array
    {
        return [
            'app_name' => Setting::get('app_name', 'Sistema Multiatendimento'),
            'app_logo' => Setting::get('app_logo', ''),
            'app_timezone' => Setting::get('app_timezone', 'America/Sao_Paulo'),
            'app_locale' => Setting::get('app_locale', 'pt_BR'),
            'max_conversations_per_agent' => Setting::get('max_conversations_per_agent', 10),
            'auto_assign_conversations' => Setting::get('auto_assign_conversations', false),
            'conversation_timeout_minutes' => Setting::get('conversation_timeout_minutes', 30),
            'openai_api_key' => Setting::get('openai_api_key', ''),
        ];
    }

    /**
     * Obter configurações de email padrão
     */
    public static function getDefaultEmailSettings(): array
    {
        return [
            'email_enabled' => Setting::get('email_enabled', false),
            'email_host' => Setting::get('email_host', ''),
            'email_port' => Setting::get('email_port', 587),
            'email_username' => Setting::get('email_username', ''),
            'email_password' => Setting::get('email_password', ''),
            'email_encryption' => Setting::get('email_encryption', 'tls'),
            'email_from_address' => Setting::get('email_from_address', ''),
            'email_from_name' => Setting::get('email_from_name', 'Sistema Multiatendimento'),
        ];
    }

    /**
     * Obter configurações de WebSocket/Tempo Real padrão
     */
    public static function getDefaultWebSocketSettings(): array
    {
        return [
            'websocket_enabled' => Setting::get('websocket_enabled', true),
            'websocket_connection_type' => Setting::get('websocket_connection_type', 'auto'), // auto, websocket, polling
            'websocket_port' => Setting::get('websocket_port', 8080),
            'websocket_path' => Setting::get('websocket_path', '/ws'),
            'websocket_custom_url' => Setting::get('websocket_custom_url', ''),
            'websocket_polling_interval' => Setting::get('websocket_polling_interval', 3000), // em milissegundos
        ];
    }

    /**
     * Obter configurações de WhatsApp padrão
     */
    public static function getDefaultWhatsAppSettings(): array
    {
        return [
            'whatsapp_provider' => Setting::get('whatsapp_provider', 'quepasa'),
            'whatsapp_quepasa_url' => Setting::get('whatsapp_quepasa_url', ''),
            'whatsapp_quepasa_token' => Setting::get('whatsapp_quepasa_token', ''),
            'whatsapp_evolution_url' => Setting::get('whatsapp_evolution_url', ''),
            'whatsapp_evolution_api_key' => Setting::get('whatsapp_evolution_api_key', ''),
            'whatsapp_webhook_url' => Setting::get('whatsapp_webhook_url', ''),
            'whatsapp_allow_group_messages' => Setting::get('whatsapp_allow_group_messages', true),
        ];
    }

    /**
     * Obter configurações de segurança padrão
     */
    public static function getDefaultSecuritySettings(): array
    {
        return [
            'password_min_length' => Setting::get('password_min_length', 6),
            'password_require_uppercase' => Setting::get('password_require_uppercase', false),
            'password_require_lowercase' => Setting::get('password_require_lowercase', false),
            'password_require_numbers' => Setting::get('password_require_numbers', false),
            'password_require_symbols' => Setting::get('password_require_symbols', false),
            'session_lifetime' => Setting::get('session_lifetime', 120),
            'max_login_attempts' => Setting::get('max_login_attempts', 5),
            'lockout_duration' => Setting::get('lockout_duration', 15),
        ];
    }
}

