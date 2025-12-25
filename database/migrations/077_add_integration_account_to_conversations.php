<?php
/**
 * Migration: Adicionar campo integration_account_id à tabela conversations
 * Atualizar campo channel para suportar novos canais
 */

function up_add_integration_account_to_conversations() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Verificar se coluna integration_account_id já existe
    $checkIntegration = $db->query("SHOW COLUMNS FROM conversations LIKE 'integration_account_id'")->fetch();
    if (!$checkIntegration) {
        $sql = "ALTER TABLE conversations 
                ADD COLUMN integration_account_id INT NULL COMMENT 'ID da conta de integração' AFTER whatsapp_account_id,
                ADD INDEX idx_integration_account_id (integration_account_id),
                ADD CONSTRAINT fk_conversation_integration_account
                FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE SET NULL";
        $db->exec($sql);
        echo "✅ Coluna 'integration_account_id' adicionada à tabela 'conversations'!\n";
    } else {
        echo "⚠️ Coluna 'integration_account_id' já existe.\n";
    }
    
    // Atualizar campo channel para suportar novos canais
    try {
        $sql = "ALTER TABLE conversations 
                MODIFY COLUMN channel VARCHAR(50) NOT NULL DEFAULT 'whatsapp'
                COMMENT 'whatsapp, instagram, facebook, telegram, mercadolivre, webchat, email, olx, linkedin, google_business, youtube, tiktok'";
        $db->exec($sql);
        echo "✅ Campo 'channel' atualizado para suportar novos canais!\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro ao atualizar campo 'channel': " . $e->getMessage() . "\n";
    }
}

function down_add_integration_account_to_conversations() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE conversations DROP FOREIGN KEY fk_conversation_integration_account");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE conversations DROP COLUMN integration_account_id");
        echo "✅ Coluna 'integration_account_id' removida da tabela 'conversations'!\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro ao remover coluna: " . $e->getMessage() . "\n";
    }
}

