<?php
/**
 * Model Setting
 */

namespace App\Models;

class Setting extends Model
{
    protected string $table = 'settings';
    protected string $primaryKey = 'id';
    protected array $fillable = ['key', 'value', 'type', 'group', 'label', 'description', 'is_public'];
    protected bool $timestamps = true;

    /**
     * Obter configuração por chave
     */
    public static function get(string $key, $default = null)
    {
        $sql = "SELECT * FROM settings WHERE `key` = ? LIMIT 1";
        $setting = \App\Helpers\Database::fetch($sql, [$key]);
        
        if (!$setting) {
            return $default;
        }
        
        return self::castValue($setting['value'], $setting['type']);
    }

    /**
     * Definir configuração
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general'): bool
    {
        $valueStr = self::serializeValue($value, $type);

        // Para evitar múltiplos registros quando não há UNIQUE em `key`,
        // removemos qualquer registro anterior e inserimos um novo.
        \App\Helpers\Database::execute("DELETE FROM settings WHERE `key` = ?", [$key]);

        $sql = "INSERT INTO settings (`key`, `value`, `type`, `group`, `updated_at`, `created_at`)
                VALUES (?, ?, ?, ?, NOW(), NOW())";

        return \App\Helpers\Database::execute($sql, [$key, $valueStr, $type, $group]) > 0;
    }

    /**
     * Obter todas as configurações de um grupo
     */
    public static function getByGroup(string $group): array
    {
        $sql = "SELECT * FROM settings WHERE `group` = ? ORDER BY `key` ASC";
        $settings = \App\Helpers\Database::fetchAll($sql, [$group]);
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['key']] = self::castValue($setting['value'], $setting['type']);
        }
        
        return $result;
    }

    /**
     * Obter todas as configurações
     */
    public static function getAll(): array
    {
        $sql = "SELECT * FROM settings ORDER BY `group` ASC, `key` ASC";
        return \App\Helpers\Database::fetchAll($sql);
    }

    /**
     * Converter valor conforme tipo
     */
    private static function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }
        
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Serializar valor conforme tipo
     */
    private static function serializeValue($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'json':
                return json_encode($value);
            default:
                return (string)$value;
        }
    }

    /**
     * Deletar configuração
     */
    public static function deleteByKey(string $key): bool
    {
        $sql = "DELETE FROM settings WHERE `key` = ?";
        return \App\Helpers\Database::execute($sql, [$key]) > 0;
    }
}

