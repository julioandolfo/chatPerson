<?php
/**
 * Migration: Criar tabela activities (histórico de atividades e auditoria)
 */

function up_activities_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL COMMENT 'Usuário que realizou a ação',
        activity_type VARCHAR(50) NOT NULL COMMENT 'Tipo de atividade: conversation_assigned, conversation_closed, conversation_reopened, message_sent, tag_added, tag_removed, stage_moved, user_created, user_updated, etc',
        entity_type VARCHAR(50) NOT NULL COMMENT 'Tipo de entidade afetada: conversation, message, user, contact, tag, etc',
        entity_id INT NULL COMMENT 'ID da entidade afetada',
        description TEXT NULL COMMENT 'Descrição da atividade',
        metadata JSON NULL COMMENT 'Dados adicionais em JSON',
        ip_address VARCHAR(45) NULL COMMENT 'IP do usuário',
        user_agent TEXT NULL COMMENT 'User agent do navegador',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_activity_type (activity_type),
        INDEX idx_entity_type (entity_type),
        INDEX idx_entity_id (entity_id),
        INDEX idx_created_at (created_at),
        INDEX idx_user_activity (user_id, activity_type, created_at),
        INDEX idx_entity_activity (entity_type, entity_id, created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'activities' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'activities' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    }
}

function down_activities_table() {
    $sql = "DROP TABLE IF EXISTS activities";
    
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Tabela 'activities' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela: " . $e->getMessage() . "\n";
    }
}

