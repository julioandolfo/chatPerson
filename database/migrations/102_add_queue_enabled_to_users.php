<?php
/**
 * Migration: Adicionar campo queue_enabled à tabela users
 * 
 * Este campo permite desabilitar um agente da fila de distribuição automática
 * sem alterar seu status de disponibilidade.
 * 
 * Quando queue_enabled = 0:
 * - O agente NÃO recebe novas conversas via automações
 * - O agente NÃO aparece na distribuição automática (round-robin, por carga, etc)
 * - O agente CONTINUA podendo receber atribuições manuais (forçadas)
 * - O status de disponibilidade (online/offline/away/busy) permanece inalterado
 */

function up_add_queue_enabled_to_users() {
    global $pdo;
    
    $sql = "ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS queue_enabled TINYINT(1) DEFAULT 1 
            COMMENT 'Se habilitado (1), agente pode receber novas conversas da fila de distribuição' 
            AFTER availability_status";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campo 'queue_enabled' adicionado à tabela 'users'!\n";
        } catch (\PDOException $e) {
            // Se der erro de sintaxe do IF NOT EXISTS (versões antigas do MySQL), tentar sem
            try {
                $checkSql = "SHOW COLUMNS FROM users LIKE 'queue_enabled'";
                $result = $pdo->query($checkSql);
                if ($result->rowCount() === 0) {
                    $sql = "ALTER TABLE users 
                            ADD COLUMN queue_enabled TINYINT(1) DEFAULT 1 
                            COMMENT 'Se habilitado (1), agente pode receber novas conversas da fila de distribuição' 
                            AFTER availability_status";
                    $pdo->exec($sql);
                    echo "✅ Campo 'queue_enabled' adicionado à tabela 'users'!\n";
                } else {
                    echo "⚠️  Campo 'queue_enabled' já existe na tabela 'users'.\n";
                }
            } catch (\PDOException $e2) {
                echo "⚠️  Erro ao adicionar campo: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campo 'queue_enabled' adicionado à tabela 'users'!\n";
        } catch (\Exception $e) {
            echo "⚠️  Campo pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    }
    
    // Criar índice para otimizar consultas
    $indexSql = "ALTER TABLE users ADD INDEX IF NOT EXISTS idx_queue_enabled (queue_enabled)";
    try {
        if (isset($pdo)) {
            $pdo->exec($indexSql);
        } else {
            \App\Helpers\Database::getInstance()->exec($indexSql);
        }
        echo "✅ Índice 'idx_queue_enabled' criado!\n";
    } catch (\Exception $e) {
        // Índice pode já existir
    }
}

function down_add_queue_enabled_to_users() {
    $sql = "ALTER TABLE users DROP COLUMN IF EXISTS queue_enabled";
    
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Campo 'queue_enabled' removido da tabela 'users'!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover campo: " . $e->getMessage() . "\n";
    }
}
