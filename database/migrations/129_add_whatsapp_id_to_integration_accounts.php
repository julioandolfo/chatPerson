<?php
/**
 * Migration: Adicionar campo whatsapp_id na tabela integration_accounts
 * 
 * Este campo cria uma ligação direta entre integration_accounts e whatsapp_accounts,
 * evitando confusão entre os IDs das duas tabelas.
 * 
 * O campo é populado automaticamente baseado no phone_number que é comum entre as tabelas.
 */

function up_add_whatsapp_id_to_integration_accounts() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Verificar se coluna já existe
    $checkColumn = $db->query("SHOW COLUMNS FROM integration_accounts LIKE 'whatsapp_id'")->fetch();
    
    if (!$checkColumn) {
        // Adicionar coluna whatsapp_id
        $sql = "ALTER TABLE integration_accounts 
                ADD COLUMN whatsapp_id INT NULL 
                COMMENT 'ID correspondente em whatsapp_accounts (para compatibilidade)'
                AFTER id";
        
        $db->exec($sql);
        echo "✅ Coluna 'whatsapp_id' adicionada à tabela 'integration_accounts'!\n";
        
        // Adicionar índice para performance
        try {
            $db->exec("ALTER TABLE integration_accounts ADD INDEX idx_whatsapp_id (whatsapp_id)");
            echo "✅ Índice 'idx_whatsapp_id' criado!\n";
        } catch (\Exception $e) {
            echo "⚠️ Índice já existe ou erro: " . $e->getMessage() . "\n";
        }
        
        // Adicionar foreign key (opcional - pode falhar se houver dados inconsistentes)
        try {
            $db->exec("ALTER TABLE integration_accounts 
                       ADD CONSTRAINT fk_integration_whatsapp 
                       FOREIGN KEY (whatsapp_id) REFERENCES whatsapp_accounts(id) 
                       ON DELETE SET NULL");
            echo "✅ Foreign key 'fk_integration_whatsapp' criada!\n";
        } catch (\Exception $e) {
            echo "⚠️ Foreign key não criada (pode já existir ou haver dados inconsistentes): " . $e->getMessage() . "\n";
        }
    } else {
        echo "⚠️ Coluna 'whatsapp_id' já existe.\n";
    }
    
    // Popular whatsapp_id automaticamente baseado no phone_number
    $sql = "
        UPDATE integration_accounts ia
        INNER JOIN whatsapp_accounts wa ON (
            ia.phone_number = wa.phone_number
            OR ia.phone_number = CONCAT('55', wa.phone_number)
            OR CONCAT('55', ia.phone_number) = wa.phone_number
            OR REPLACE(ia.phone_number, '+', '') = REPLACE(wa.phone_number, '+', '')
        )
        SET ia.whatsapp_id = wa.id
        WHERE ia.whatsapp_id IS NULL
            AND ia.channel = 'whatsapp'
    ";
    
    $affected = $db->exec($sql);
    echo "✅ {$affected} registros atualizados com whatsapp_id!\n";
    
    // Verificar se há registros não mapeados
    $unmapped = $db->query("
        SELECT id, name, phone_number 
        FROM integration_accounts 
        WHERE channel = 'whatsapp' 
            AND whatsapp_id IS NULL
    ")->fetchAll(\PDO::FETCH_ASSOC);
    
    if (count($unmapped) > 0) {
        echo "⚠️ Existem " . count($unmapped) . " contas WhatsApp sem correspondência em whatsapp_accounts:\n";
        foreach ($unmapped as $account) {
            echo "   - ID {$account['id']}: {$account['name']} ({$account['phone_number']})\n";
        }
        echo "   Verifique se os números de telefone estão corretos em ambas as tabelas.\n";
    } else {
        echo "✅ Todas as contas WhatsApp foram mapeadas corretamente!\n";
    }
}

function down_add_whatsapp_id_to_integration_accounts() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Remover foreign key
    try {
        $db->exec("ALTER TABLE integration_accounts DROP FOREIGN KEY fk_integration_whatsapp");
        echo "✅ Foreign key 'fk_integration_whatsapp' removida!\n";
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    // Remover índice
    try {
        $db->exec("ALTER TABLE integration_accounts DROP INDEX idx_whatsapp_id");
        echo "✅ Índice 'idx_whatsapp_id' removido!\n";
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    // Remover coluna
    try {
        $db->exec("ALTER TABLE integration_accounts DROP COLUMN whatsapp_id");
        echo "✅ Coluna 'whatsapp_id' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro ao remover coluna: " . $e->getMessage() . "\n";
    }
}
