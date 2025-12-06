<?php
/**
 * Model MessageTemplate
 */

namespace App\Models;

class MessageTemplate extends Model
{
    protected string $table = 'message_templates';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'category', 'content', 'description', 'department_id', 'channel', 'is_active', 'created_by'];
    protected bool $timestamps = true;

    /**
     * Obter templates por categoria
     */
    public static function getByCategory(string $category): array
    {
        return self::where('category', '=', $category)
            ->where('is_active', '=', true);
    }

    /**
     * Obter templates por setor
     */
    public static function getByDepartment(?int $departmentId): array
    {
        $sql = "SELECT * FROM message_templates 
                WHERE is_active = TRUE 
                AND (department_id = ? OR department_id IS NULL)
                ORDER BY department_id DESC, name ASC";
        return \App\Helpers\Database::fetchAll($sql, [$departmentId]);
    }

    /**
     * Obter templates por canal
     */
    public static function getByChannel(string $channel): array
    {
        $sql = "SELECT * FROM message_templates 
                WHERE is_active = TRUE 
                AND (channel = ? OR channel IS NULL)
                ORDER BY channel DESC, name ASC";
        return \App\Helpers\Database::fetchAll($sql, [$channel]);
    }

    /**
     * Obter templates disponíveis para uso
     */
    public static function getAvailable(?int $departmentId = null, ?string $channel = null): array
    {
        $sql = "SELECT * FROM message_templates 
                WHERE is_active = TRUE";
        $params = [];
        
        if ($departmentId !== null) {
            $sql .= " AND (department_id = ? OR department_id IS NULL)";
            $params[] = $departmentId;
        }
        
        if ($channel !== null) {
            $sql .= " AND (channel = ? OR channel IS NULL)";
            $params[] = $channel;
        }
        
        $sql .= " ORDER BY department_id DESC, channel DESC, name ASC";
        
        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Incrementar contador de uso
     */
    public static function incrementUsage(int $templateId): bool
    {
        $sql = "UPDATE message_templates 
                SET usage_count = usage_count + 1 
                WHERE id = ?";
        return \App\Helpers\Database::execute($sql, [$templateId]) > 0;
    }

    /**
     * Processar template com variáveis
     */
    public static function processTemplate(string $content, array $variables = []): string
    {
        $processed = $content;
        
        // Ordenar variáveis por tamanho (mais longas primeiro) para evitar substituições parciais
        uksort($variables, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        foreach ($variables as $key => $value) {
            // Substituir variáveis com e sem espaços
            $processed = str_replace('{{' . $key . '}}', $value, $processed);
            $processed = str_replace('{{ ' . $key . ' }}', $value, $processed);
            // Também suportar formato com ponto (ex: contact.name)
            $processed = str_replace('{{' . $key . '}}', $value, $processed);
        }
        
        // Remover variáveis não substituídas
        $processed = preg_replace('/\{\{[^}]+\}\}/', '', $processed);
        
        return $processed;
    }
}

