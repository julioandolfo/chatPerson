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
     * Criar novo registro
     */
    public static function create(array $data): int
    {
        $instance = new static();
        
        \App\Helpers\Logger::info("Model::create - Tabela: {$instance->table}", 'kanban_agents.log');
        \App\Helpers\Logger::info("Model::create - Dados antes do filtro: " . json_encode($data), 'kanban_agents.log');
        
        try {
            // Filtrar apenas campos fillable
            $data = array_intersect_key($data, array_flip($instance->fillable));
            
            \App\Helpers\Logger::info("Model::create - Dados após filtro fillable: " . json_encode($data), 'kanban_agents.log');
            
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

            \App\Helpers\Logger::info("Model::create - SQL: {$sql}", 'kanban_agents.log');
            \App\Helpers\Logger::info("Model::create - Values: " . json_encode($values), 'kanban_agents.log');
            
            $id = Database::insert($sql, $values);
            
            \App\Helpers\Logger::info("Model::create - Registro criado com sucesso (ID: $id)", 'kanban_agents.log');
            
            return $id;
        } catch (\Throwable $e) {
            \App\Helpers\Logger::error("Model::create - ERRO ao criar registro na tabela {$instance->table}", 'kanban_agents.log');
            \App\Helpers\Logger::error("Model::create - Tipo: " . get_class($e), 'kanban_agents.log');
            \App\Helpers\Logger::error("Model::create - Mensagem: " . $e->getMessage(), 'kanban_agents.log');
            \App\Helpers\Logger::error("Model::create - Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")", 'kanban_agents.log');
            \App\Helpers\Logger::error("Model::create - Stack trace: " . $e->getTraceAsString(), 'kanban_agents.log');
            throw $e;
        }
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

