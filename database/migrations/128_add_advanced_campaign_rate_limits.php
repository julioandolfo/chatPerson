<?php
/**
 * Migration: Adicionar controles avançados de taxa de envio em campanhas
 * 
 * Novos campos para controle granular de envio:
 * - Limite diário de mensagens
 * - Limite por hora
 * - Limite por conta por dia
 * - Intervalo aleatório (min/max)
 * - Tamanho de lote e pausa entre lotes
 */

function up_add_advanced_campaign_rate_limits() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Verificar quais colunas já existem
    $existingColumns = [];
    try {
        $result = $db->query("SHOW COLUMNS FROM campaigns");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $row['Field'];
        }
    } catch (Exception $e) {
        echo "⚠️ Erro ao verificar colunas: " . $e->getMessage() . "\n";
        return;
    }
    
    $columnsToAdd = [
        // Limites de quantidade
        'daily_limit' => "INT DEFAULT NULL COMMENT 'Limite máximo de mensagens por dia (NULL = sem limite)'",
        'hourly_limit' => "INT DEFAULT NULL COMMENT 'Limite máximo de mensagens por hora (NULL = sem limite)'",
        'daily_limit_per_account' => "INT DEFAULT NULL COMMENT 'Limite diário por conta WhatsApp (NULL = sem limite)'",
        
        // Intervalo aleatório para parecer mais humano
        'random_interval_enabled' => "BOOLEAN DEFAULT FALSE COMMENT 'Usar intervalo aleatório entre mensagens'",
        'random_interval_min' => "INT DEFAULT 5 COMMENT 'Intervalo mínimo em segundos'",
        'random_interval_max' => "INT DEFAULT 15 COMMENT 'Intervalo máximo em segundos'",
        
        // Lotes com pausas
        'batch_size' => "INT DEFAULT NULL COMMENT 'Tamanho do lote (NULL = sem lotes)'",
        'batch_pause_minutes' => "INT DEFAULT 5 COMMENT 'Pausa entre lotes em minutos'",
        
        // Contadores auxiliares
        'sent_today' => "INT DEFAULT 0 COMMENT 'Mensagens enviadas hoje (resetado diariamente)'",
        'sent_this_hour' => "INT DEFAULT 0 COMMENT 'Mensagens enviadas na hora atual'",
        'last_counter_reset' => "DATE DEFAULT NULL COMMENT 'Data do último reset do contador diário'",
        'last_hourly_reset' => "DATETIME DEFAULT NULL COMMENT 'Data/hora do último reset do contador horário'",
        
        // Geração de mensagem com IA
        'ai_message_enabled' => "BOOLEAN DEFAULT FALSE COMMENT 'Gerar mensagem única com IA para cada contato'",
        'ai_message_prompt' => "TEXT NULL COMMENT 'Prompt para geração de mensagem com IA'",
        'ai_temperature' => "DECIMAL(2,1) DEFAULT 0.7 COMMENT 'Temperatura da IA (0.0-1.0)'",
        
        // Execução de automações
        'execute_automations' => "BOOLEAN DEFAULT FALSE COMMENT 'Executar automações ao criar conversa na etapa'",
    ];
    
    foreach ($columnsToAdd as $column => $definition) {
        if (!in_array($column, $existingColumns)) {
            try {
                $sql = "ALTER TABLE campaigns ADD COLUMN {$column} {$definition}";
                $db->exec($sql);
                echo "✅ Coluna '{$column}' adicionada à tabela campaigns\n";
            } catch (Exception $e) {
                echo "⚠️ Erro ao adicionar coluna {$column}: " . $e->getMessage() . "\n";
            }
        } else {
            echo "ℹ️ Coluna '{$column}' já existe\n";
        }
    }
    
    echo "✅ Migration de controles avançados de campanha concluída!\n";
}

function down_add_advanced_campaign_rate_limits() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $columns = [
        'daily_limit',
        'hourly_limit',
        'daily_limit_per_account',
        'random_interval_enabled',
        'random_interval_min',
        'random_interval_max',
        'batch_size',
        'batch_pause_minutes',
        'sent_today',
        'sent_this_hour',
        'last_counter_reset',
        'last_hourly_reset',
    ];
    
    foreach ($columns as $column) {
        try {
            $sql = "ALTER TABLE campaigns DROP COLUMN {$column}";
            $db->exec($sql);
            echo "✅ Coluna '{$column}' removida\n";
        } catch (Exception $e) {
            echo "⚠️ Coluna '{$column}' não encontrada\n";
        }
    }
}
