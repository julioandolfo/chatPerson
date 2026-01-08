<?php
/**
 * Migration: Adicionar campos avançados de SLA à tabela conversations
 * 
 * Campos adicionados:
 * - first_human_response_at: Primeira resposta de agente HUMANO (separado de IA)
 * - sla_paused_at: Quando o SLA foi pausado (snooze, aguardando cliente, etc)
 * - sla_paused_duration: Duração total de pausas em minutos
 * - sla_warning_sent: Se já foi enviado alerta de SLA (evitar spam)
 * - reassignment_count: Contador de reatribuições automáticas
 * - last_reassignment_at: Última reatribuição
 */

function up_add_sla_advanced_fields_to_conversations() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🔧 Adicionando campos avançados de SLA...\n";
    
    // Lista de colunas a adicionar
    $columns = [
        'first_human_response_at' => "ALTER TABLE conversations ADD COLUMN first_human_response_at TIMESTAMP NULL COMMENT 'Primeira resposta de agente humano (não IA)' AFTER first_response_at",
        'sla_paused_at' => "ALTER TABLE conversations ADD COLUMN sla_paused_at TIMESTAMP NULL COMMENT 'Quando SLA foi pausado' AFTER first_human_response_at",
        'sla_paused_duration' => "ALTER TABLE conversations ADD COLUMN sla_paused_duration INT DEFAULT 0 COMMENT 'Duração total de pausas em minutos' AFTER sla_paused_at",
        'sla_warning_sent' => "ALTER TABLE conversations ADD COLUMN sla_warning_sent TINYINT(1) DEFAULT 0 COMMENT 'Se alerta de SLA já foi enviado' AFTER sla_paused_duration",
        'reassignment_count' => "ALTER TABLE conversations ADD COLUMN reassignment_count INT DEFAULT 0 COMMENT 'Contador de reatribuições automáticas' AFTER sla_warning_sent",
        'last_reassignment_at' => "ALTER TABLE conversations ADD COLUMN last_reassignment_at TIMESTAMP NULL COMMENT 'Última reatribuição automática' AFTER reassignment_count"
    ];
    
    foreach ($columns as $columnName => $sql) {
        // Verificar se coluna já existe
        $checkSql = "SHOW COLUMNS FROM conversations LIKE '$columnName'";
        $result = $db->query($checkSql)->fetchAll();
        
        if (empty($result)) {
            try {
                $db->exec($sql);
                echo "   ✅ Coluna '$columnName' adicionada\n";
            } catch (\PDOException $e) {
                echo "   ⚠️  Erro ao adicionar '$columnName': " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ℹ️  Coluna '$columnName' já existe\n";
        }
    }
    
    // Adicionar índices para performance
    $indexes = [
        'idx_first_human_response_at' => "CREATE INDEX idx_first_human_response_at ON conversations(first_human_response_at)",
        'idx_sla_paused_at' => "CREATE INDEX idx_sla_paused_at ON conversations(sla_paused_at)",
        'idx_reassignment_count' => "CREATE INDEX idx_reassignment_count ON conversations(reassignment_count)"
    ];
    
    echo "\n🔍 Adicionando índices...\n";
    foreach ($indexes as $indexName => $sql) {
        try {
            $db->exec($sql);
            echo "   ✅ Índice '$indexName' criado\n";
        } catch (\PDOException $e) {
            // Índice pode já existir, ignorar erro
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                echo "   ⚠️  Aviso ao criar '$indexName': " . $e->getMessage() . "\n";
            } else {
                echo "   ℹ️  Índice '$indexName' já existe\n";
            }
        }
    }
    
    echo "\n✅ Migration concluída com sucesso!\n";
}

function down_add_sla_advanced_fields_to_conversations() {
    $db = \App\Helpers\Database::getInstance();
    
    echo "🔧 Removendo campos avançados de SLA...\n";
    
    $columns = [
        'first_human_response_at',
        'sla_paused_at',
        'sla_paused_duration',
        'sla_warning_sent',
        'reassignment_count',
        'last_reassignment_at'
    ];
    
    foreach ($columns as $column) {
        try {
            $db->exec("ALTER TABLE conversations DROP COLUMN IF EXISTS $column");
            echo "   ✅ Coluna '$column' removida\n";
        } catch (\PDOException $e) {
            echo "   ⚠️  Erro ao remover '$column': " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Rollback concluído!\n";
}
