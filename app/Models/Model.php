<?php
/**
 * Model Base
 * Active Record Pattern
 */

namespace App\Models;

use App\Helpers\Database;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected bool $timestamps = true;
    
    /**
     * Campos que são do tipo JSON no banco de dados
     * Strings vazias serão convertidas para NULL
     */
    protected array $jsonFields = [];

    /**
     * Obter todos os registros
     */
    public static function all(array $columns = ['*']): array
    {
        $instance = new static();
        $columnsStr = implode(', ', $columns);
        $sql = "SELECT {$columnsStr} FROM `{$instance->table}`";
        
        if ($instance->timestamps) {
            $sql .= " ORDER BY `created_at` DESC";
        }
        
        return Database::fetchAll($sql);
    }

    /**
     * Encontrar por ID
     */
    public static function find(int $id): ?array
    {
        $instance = new static();
        $sql = "SELECT * FROM `{$instance->table}` WHERE `{$instance->primaryKey}` = ?";
        return Database::fetch($sql, [$id]);
    }

    /**
     * Sanitizar campos JSON (converter strings vazias para NULL)
     */
    protected static function sanitizeJsonFields(array $data, array $jsonFields): array
    {
        foreach ($jsonFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                // Se for string vazia ou null, definir como null
                if ($value === '' || $value === null) {
                    $data[$field] = null;
                }
                // Se for array, converter para JSON
                elseif (is_array($value)) {
                    $data[$field] = json_encode($value);
                }
                // Se for string mas não for JSON válido, converter para null
                elseif (is_string($value) && !empty($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $data[$field] = null;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Criar novo registro
     */
    public static function create(array $data): int
    {
        $instance = new static();
        
        // Filtrar apenas campos fillable
        $data = array_intersect_key($data, array_flip($instance->fillable));
        
        // Sanitizar campos JSON
        if (!empty($instance->jsonFields)) {
            $data = self::sanitizeJsonFields($data, $instance->jsonFields);
        }
        
        // Adicionar timestamps (somente se não foram fornecidos)
        if ($instance->timestamps) {
            if (!isset($data['created_at'])) {
                $data['created_at'] = date('Y-m-d H:i:s');
            }
            if (!isset($data['updated_at'])) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
        }

        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');
        
        // Escapar nomes de campos com backticks
        $fieldsEscaped = array_map(function($field) {
            return "`{$field}`";
        }, $fields);

        $sql = "INSERT INTO `{$instance->table}` (" . implode(', ', $fieldsEscaped) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        return Database::insert($sql, $values);
    }

    /**
     * Atualizar registro
     */
    public static function update(int $id, array $data): bool
    {
        $instance = new static();
        
        // Filtrar apenas campos fillable
        $data = array_intersect_key($data, array_flip($instance->fillable));
        
        // Sanitizar campos JSON
        if (!empty($instance->jsonFields)) {
            $data = self::sanitizeJsonFields($data, $instance->jsonFields);
        }
        
        // Adicionar updated_at
        if ($instance->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "`{$field}` = ?";
            $values[] = $value;
        }
        
        $values[] = $id;

        $sql = "UPDATE `{$instance->table}` SET " . implode(', ', $fields) . 
               " WHERE `{$instance->primaryKey}` = ?";

        // Log temporário para debug
        \App\Helpers\Logger::automation("  Model::update - SQL: {$sql}");
        \App\Helpers\Logger::automation("  Model::update - Values: " . json_encode($values));
        
        $affectedRows = Database::execute($sql, $values);
        \App\Helpers\Logger::automation("  Model::update - Linhas afetadas: {$affectedRows}");
        
        return $affectedRows > 0;
    }

    /**
     * Deletar registro
     */
    public static function delete(int $id): bool
    {
        $instance = new static();
        $sql = "DELETE FROM {$instance->table} WHERE {$instance->primaryKey} = ?";
        return Database::execute($sql, [$id]) > 0;
    }

    /**
     * Buscar com condições
     */
    public static function where(string $field, string $operator, $value): array
    {
        $instance = new static();
        // Escapar nome do campo com backticks para evitar problemas com palavras reservadas
        $fieldEscaped = "`{$field}`";
        
        // Tratar operador IN com arrays
        if (strtoupper($operator) === 'IN' && is_array($value)) {
            if (empty($value)) {
                // Se o array estiver vazio, retornar array vazio (não há resultados)
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($value), '?'));
            $sql = "SELECT * FROM `{$instance->table}` WHERE {$fieldEscaped} IN ({$placeholders})";
            return Database::fetchAll($sql, $value);
        }
        
        $sql = "SELECT * FROM `{$instance->table}` WHERE {$fieldEscaped} {$operator} ?";
        return Database::fetchAll($sql, [$value]);
    }

    /**
     * Buscar um registro com condições
     */
    public static function whereFirst(string $field, string $operator, $value): ?array
    {
        $instance = new static();
        // Escapar nome do campo com backticks para evitar problemas com palavras reservadas
        $fieldEscaped = "`{$field}`";
        $sql = "SELECT * FROM `{$instance->table}` WHERE {$fieldEscaped} {$operator} ? LIMIT 1";
        return Database::fetch($sql, [$value]);
    }
}

