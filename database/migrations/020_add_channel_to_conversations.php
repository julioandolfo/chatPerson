<?php
/**
 * Migration: Adicionar campos channel e whatsapp_account_id à tabela conversations
 */

function up_add_channel_to_conversations() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Verificar se coluna channel já existe
    $checkChannel = $db->query("SHOW COLUMNS FROM conversations LIKE 'channel'")->fetch();
    if (!$checkChannel) {
        $sql = "ALTER TABLE conversations ADD COLUMN channel VARCHAR(50) DEFAULT 'whatsapp' COMMENT 'whatsapp, email, chat, telegram' AFTER funnel_stage_id";
        $db->exec($sql);
        echo "✅ Coluna 'channel' adicionada à tabela 'conversations'!\n";
    } else {
        echo "⚠️ Coluna 'channel' já existe.\n";
    }
    
    // Verificar se coluna whatsapp_account_id já existe
    $checkWhatsApp = $db->query("SHOW COLUMNS FROM conversations LIKE 'whatsapp_account_id'")->fetch();
    if (!$checkWhatsApp) {
        $sql = "ALTER TABLE conversations 
                ADD COLUMN whatsapp_account_id INT NULL COMMENT 'ID da conta WhatsApp se canal for whatsapp' AFTER channel,
                ADD CONSTRAINT fk_conversation_whatsapp_account
                FOREIGN KEY (whatsapp_account_id) REFERENCES whatsapp_accounts(id) ON DELETE SET NULL";
        $db->exec($sql);
        echo "✅ Coluna 'whatsapp_account_id' adicionada à tabela 'conversations'!\n";
    } else {
        echo "⚠️ Coluna 'whatsapp_account_id' já existe.\n";
    }
    
    // Adicionar índice se não existir
    try {
        $db->exec("CREATE INDEX idx_channel ON conversations(channel)");
        echo "✅ Índice 'idx_channel' criado!\n";
    } catch (\Exception $e) {
        echo "⚠️ Índice 'idx_channel' pode já existir.\n";
    }
}

function down_add_channel_to_conversations() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE conversations DROP FOREIGN KEY fk_conversation_whatsapp_account");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE conversations DROP COLUMN whatsapp_account_id");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE conversations DROP COLUMN channel");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    echo "✅ Colunas removidas da tabela 'conversations'!\n";
}

