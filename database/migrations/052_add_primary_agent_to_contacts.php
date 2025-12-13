<?php
/**
 * Migration: Adicionar campo primary_agent_id na tabela contacts
 */

function up_add_primary_agent_to_contacts() {
    global $pdo;
    
    $sql = "ALTER TABLE contacts 
            ADD COLUMN IF NOT EXISTS primary_agent_id INT NULL COMMENT 'ID do agente principal atribuído ao contato',
            ADD INDEX IF NOT EXISTS idx_primary_agent_id (primary_agent_id),
            ADD FOREIGN KEY IF NOT EXISTS fk_contacts_primary_agent (primary_agent_id) REFERENCES users(id) ON DELETE SET NULL";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campo 'primary_agent_id' adicionado à tabela 'contacts' com sucesso!\n";
        } catch (\PDOException $e) {
            // Tentar sem IF NOT EXISTS (MySQL antigo)
            try {
                // Verificar se coluna já existe
                $checkSql = "SHOW COLUMNS FROM contacts LIKE 'primary_agent_id'";
                $result = $pdo->query($checkSql);
                if ($result->rowCount() === 0) {
                    $sql2 = "ALTER TABLE contacts 
                            ADD COLUMN primary_agent_id INT NULL COMMENT 'ID do agente principal atribuído ao contato',
                            ADD INDEX idx_primary_agent_id (primary_agent_id)";
                    $pdo->exec($sql2);
                    
                    // Adicionar foreign key separadamente
                    try {
                        $pdo->exec("ALTER TABLE contacts ADD FOREIGN KEY fk_contacts_primary_agent (primary_agent_id) REFERENCES users(id) ON DELETE SET NULL");
                    } catch (\PDOException $fkErr) {
                        // Foreign key pode já existir ou falhar, continuar
                        echo "⚠️ Aviso ao adicionar foreign key: " . $fkErr->getMessage() . "\n";
                    }
                    
                    echo "✅ Campo 'primary_agent_id' adicionado à tabela 'contacts' com sucesso!\n";
                } else {
                    echo "ℹ️ Campo 'primary_agent_id' já existe na tabela 'contacts'\n";
                }
            } catch (\PDOException $e2) {
                echo "⚠️ Erro ao adicionar campo: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campo 'primary_agent_id' adicionado à tabela 'contacts' com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️ Erro ao adicionar campo: " . $e->getMessage() . "\n";
        }
    }
}

function down_add_primary_agent_to_contacts() {
    $sql = "ALTER TABLE contacts DROP FOREIGN KEY IF EXISTS fk_contacts_primary_agent";
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
    } catch (\Exception $e) {
        // Ignorar erro se não existir
    }
    
    $sql = "ALTER TABLE contacts DROP COLUMN IF EXISTS primary_agent_id";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Campo 'primary_agent_id' removido da tabela 'contacts'!\n";
}

