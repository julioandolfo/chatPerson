<?php
/**
 * Service PostgreSQLSettingsService
 * Gerencia configurações do PostgreSQL para sistema RAG
 */

namespace App\Services;

use App\Models\Setting;

class PostgreSQLSettingsService
{
    /**
     * Obter todas as configurações do PostgreSQL
     */
    public static function getSettings(): array
    {
        return [
            'postgres_enabled' => Setting::get('postgres_enabled', false),
            'postgres_host' => Setting::get('postgres_host', 'localhost'),
            'postgres_port' => Setting::get('postgres_port', 5432),
            'postgres_database' => Setting::get('postgres_database', 'chat_rag'),
            'postgres_username' => Setting::get('postgres_username', 'chat_user'),
            'postgres_password' => Setting::get('postgres_password', ''),
        ];
    }

    /**
     * Obter configurações padrão
     */
    public static function getDefaultSettings(): array
    {
        return [
            'postgres_enabled' => false,
            'postgres_host' => 'localhost',
            'postgres_port' => 5432,
            'postgres_database' => 'chat_rag',
            'postgres_username' => 'chat_user',
            'postgres_password' => '',
        ];
    }

    /**
     * Salvar configurações do PostgreSQL
     */
    public static function saveSettings(array $settings): bool
    {
        $defaults = self::getDefaultSettings();
        
        foreach ($defaults as $key => $defaultValue) {
            $value = $settings[$key] ?? $defaultValue;
            
            // Determinar tipo
            $type = 'string';
            if (is_bool($value)) {
                $type = 'boolean';
            } elseif (is_int($value)) {
                $type = 'integer';
            }
            
            Setting::set($key, $value, $type, 'postgres');
        }
        
        return true;
    }

    /**
     * Verificar se PostgreSQL está habilitado
     */
    public static function isEnabled(): bool
    {
        return (bool) Setting::get('postgres_enabled', false);
    }

    /**
     * Obter DSN de conexão
     */
    public static function getDSN(): string
    {
        $host = Setting::get('postgres_host', 'localhost');
        $port = Setting::get('postgres_port', 5432);
        $database = Setting::get('postgres_database', 'chat_rag');
        
        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    /**
     * Obter credenciais
     */
    public static function getCredentials(): array
    {
        return [
            'dsn' => self::getDSN(),
            'username' => Setting::get('postgres_username', 'chat_user'),
            'password' => Setting::get('postgres_password', ''),
        ];
    }
}

