<?php
/**
 * PostgreSQLModel Base
 * Classe base para Models que usam PostgreSQL
 */

namespace App\Models;

use App\Helpers\PostgreSQL;

abstract class PostgreSQLModel
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected bool $timestamps = true;

    /**
     * Obter todos os registros
     */
    public static function all(array $columns = ['*']): array
    {
        $instance = new static();
        $columnsStr = implode(', ', $columns);
        $sql = "SELECT {$columnsStr} FROM {$instance->table}";
        
        if ($instance->timestamps) {
            $sql .= " ORDER BY created_at DESC";
        }
        
        return PostgreSQL::query($sql);
    }

    /**
     * Encontrar por ID
     */
    public static function find(int $id): ?array
    {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = ?";
        return PostgreSQL::fetch($sql, [$id]);
    }

    /**
     * Criar novo registro
     */
    public static function create(array $data): int
    {
        $instance = new static();
        
        // Filtrar apenas campos fillable
        $data = array_intersect_key($data, array_flip($instance->fillable));
        
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
        
        $sql = "INSERT INTO {$instance->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ") RETURNING id";

        $stmt = PostgreSQL::getConnection()->prepare($sql);
        $stmt->execute($values);
        $result = $stmt->fetch();
        return $result['id'] ?? 0;
    }

    /**
     * Atualizar registro
     */
    public static function update(int $id, array $data): bool
    {
        $instance = new static();
        
        // Filtrar apenas campos fillable
        $data = array_intersect_key($data, array_flip($instance->fillable));
        
        // Adicionar updated_at
        if ($instance->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "{$field} = ?";
            $values[] = $value;
        }
        
        $values[] = $id;

        $sql = "UPDATE {$instance->table} SET " . implode(', ', $fields) . 
               " WHERE {$instance->primaryKey} = ?";

        return PostgreSQL::execute($sql, $values);
    }

    /**
     * Deletar registro
     */
    public static function delete(int $id): bool
    {
        $instance = new static();
        $sql = "DELETE FROM {$instance->table} WHERE {$instance->primaryKey} = ?";
        return PostgreSQL::execute($sql, [$id]);
    }

    /**
     * Buscar com condições
     */
    public static function where(string $field, string $operator, $value): array
    {
        $instance = new static();
        
        // Tratar operador IN com arrays
        if (strtoupper($operator) === 'IN' && is_array($value)) {
            if (empty($value)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($value), '?'));
            $sql = "SELECT * FROM {$instance->table} WHERE {$field} IN ({$placeholders})";
            return PostgreSQL::query($sql, $value);
        }
        
        $sql = "SELECT * FROM {$instance->table} WHERE {$field} {$operator} ?";
        return PostgreSQL::query($sql, [$value]);
    }

    /**
     * Buscar um registro com condições
     */
    public static function whereFirst(string $field, string $operator, $value): ?array
    {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table} WHERE {$field} {$operator} ? LIMIT 1";
        return PostgreSQL::fetch($sql, [$value]);
    }
}

