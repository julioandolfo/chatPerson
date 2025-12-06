<?php
/**
 * Script para Resetar Conversas e Mensagens
 * 
 * ATENÇÃO: Este script irá DELETAR TODAS as conversas e mensagens!
 * Use apenas em ambiente de desenvolvimento/teste!
 * 
 * Uso: php database/scripts/reset_conversations.php [--whatsapp-only] [--with-contacts]
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Helpers\Database;

// Configurações
$whatsappOnly = in_array('--whatsapp-only', $argv);
$withContacts = in_array('--with-contacts', $argv);

echo "============================================\n";
echo "Script de Reset de Conversas\n";
echo "============================================\n\n";

if ($whatsappOnly) {
    echo "⚠️  Modo: Apenas WhatsApp\n";
} else {
    echo "⚠️  Modo: TODAS as conversas\n";
}

if ($withContacts) {
    echo "⚠️  Modo: Incluir contatos\n";
}

echo "\n";

// Confirmar execução
echo "ATENÇÃO: Este script irá DELETAR dados!\n";
echo "Digite 'SIM' para continuar: ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if ($line !== 'SIM') {
    echo "❌ Operação cancelada.\n";
    exit(0);
}

echo "\n🔄 Iniciando limpeza...\n\n";

try {
    $db = Database::getInstance();
    
    // 1. Verificar contagem antes
    echo "📊 Contagem ANTES da limpeza:\n";
    
    if ($whatsappOnly) {
        $messagesBefore = Database::fetch("SELECT COUNT(*) as total FROM messages m INNER JOIN conversations c ON m.conversation_id = c.id WHERE c.channel = 'whatsapp'");
        $conversationsBefore = Database::fetch("SELECT COUNT(*) as total FROM conversations WHERE channel = 'whatsapp'");
    } else {
        $messagesBefore = Database::fetch("SELECT COUNT(*) as total FROM messages");
        $conversationsBefore = Database::fetch("SELECT COUNT(*) as total FROM conversations");
    }
    
    $contactsBefore = Database::fetch("SELECT COUNT(*) as total FROM contacts");
    
    echo "  - Mensagens: " . ($messagesBefore['total'] ?? 0) . "\n";
    echo "  - Conversas: " . ($conversationsBefore['total'] ?? 0) . "\n";
    echo "  - Contatos: " . ($contactsBefore['total'] ?? 0) . "\n\n";
    
    // 2. Desabilitar foreign keys
    echo "🔓 Desabilitando verificação de foreign keys...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // 3. Deletar mensagens primeiro (devido à foreign key)
    echo "🗑️  Deletando mensagens...\n";
    if ($whatsappOnly) {
        $sql = "DELETE m FROM messages m 
                INNER JOIN conversations c ON m.conversation_id = c.id 
                WHERE c.channel = 'whatsapp'";
    } else {
        $sql = "DELETE FROM messages";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $messagesDeleted = $stmt->rowCount();
    echo "  ✅ {$messagesDeleted} mensagens deletadas\n";
    
    // 4. Deletar relacionamentos de tags
    echo "🗑️  Deletando relacionamentos de tags...\n";
    if ($whatsappOnly) {
        $sql = "DELETE ctt FROM conversation_tags ctt 
                INNER JOIN conversations c ON ctt.conversation_id = c.id 
                WHERE c.channel = 'whatsapp'";
    } else {
        $sql = "DELETE FROM conversation_tags";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $tagsDeleted = $stmt->rowCount();
    echo "  ✅ {$tagsDeleted} relacionamentos de tags deletados\n";
    
    // 5. Deletar logs de automação relacionados (se existir)
    echo "🗑️  Verificando logs de automação...\n";
    try {
        if ($whatsappOnly) {
            $sql = "DELETE al FROM automation_logs al 
                    INNER JOIN conversations c ON al.conversation_id = c.id 
                    WHERE c.channel = 'whatsapp'";
        } else {
            $sql = "DELETE FROM automation_logs WHERE conversation_id IS NOT NULL";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $logsDeleted = $stmt->rowCount();
        echo "  ✅ {$logsDeleted} logs de automação deletados\n";
    } catch (\Exception $e) {
        echo "  ⚠️  Tabela automation_logs não existe ou erro: " . $e->getMessage() . "\n";
    }
    
    // 6. Deletar conversas de IA relacionadas (se existir)
    echo "🗑️  Verificando conversas de IA...\n";
    try {
        if ($whatsappOnly) {
            $sql = "DELETE aic FROM ai_conversations aic 
                    INNER JOIN conversations c ON aic.conversation_id = c.id 
                    WHERE c.channel = 'whatsapp'";
        } else {
            $sql = "DELETE FROM ai_conversations WHERE conversation_id IS NOT NULL";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $aiDeleted = $stmt->rowCount();
        echo "  ✅ {$aiDeleted} conversas de IA deletadas\n";
    } catch (\Exception $e) {
        echo "  ⚠️  Tabela ai_conversations não existe ou erro: " . $e->getMessage() . "\n";
    }
    
    // 7. Deletar conversas
    echo "🗑️  Deletando conversas...\n";
    if ($whatsappOnly) {
        $sql = "DELETE FROM conversations WHERE channel = 'whatsapp'";
    } else {
        $sql = "DELETE FROM conversations";
    }
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $conversationsDeleted = $stmt->rowCount();
    echo "  ✅ {$conversationsDeleted} conversas deletadas\n";
    
    // 8. Deletar contatos (se solicitado)
    if ($withContacts) {
        echo "🗑️  Deletando contatos...\n";
        if ($whatsappOnly) {
            // Deletar apenas contatos que têm whatsapp_id
            $sql = "DELETE FROM contacts WHERE whatsapp_id IS NOT NULL AND whatsapp_id != ''";
        } else {
            $sql = "DELETE FROM contacts";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $contactsDeleted = $stmt->rowCount();
        echo "  ✅ {$contactsDeleted} contatos deletados\n";
    }
    
    // 9. Resetar auto increments
    echo "🔄 Resetando auto increments...\n";
    try {
        $db->exec("ALTER TABLE messages AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE conversations AUTO_INCREMENT = 1");
        if ($withContacts) {
            $db->exec("ALTER TABLE contacts AUTO_INCREMENT = 1");
        }
        echo "  ✅ Auto increments resetados\n";
    } catch (\Exception $e) {
        echo "  ⚠️  Erro ao resetar auto increments: " . $e->getMessage() . "\n";
    }
    
    // 10. Reabilitar foreign keys
    echo "🔒 Reabilitando verificação de foreign keys...\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 11. Verificar contagem depois
    echo "\n📊 Contagem DEPOIS da limpeza:\n";
    
    if ($whatsappOnly) {
        $messagesAfter = Database::fetch("SELECT COUNT(*) as total FROM messages m INNER JOIN conversations c ON m.conversation_id = c.id WHERE c.channel = 'whatsapp'");
        $conversationsAfter = Database::fetch("SELECT COUNT(*) as total FROM conversations WHERE channel = 'whatsapp'");
    } else {
        $messagesAfter = Database::fetch("SELECT COUNT(*) as total FROM messages");
        $conversationsAfter = Database::fetch("SELECT COUNT(*) as total FROM conversations");
    }
    
    $contactsAfter = Database::fetch("SELECT COUNT(*) as total FROM contacts");
    
    echo "  - Mensagens: " . ($messagesAfter['total'] ?? 0) . "\n";
    echo "  - Conversas: " . ($conversationsAfter['total'] ?? 0) . "\n";
    echo "  - Contatos: " . ($contactsAfter['total'] ?? 0) . "\n\n";
    
    echo "✅ Limpeza concluída com sucesso!\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    // Tentar reabilitar foreign keys mesmo em caso de erro
    try {
        $db = Database::getInstance();
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (\Exception $e2) {
        // Ignorar
    }
    
    exit(1);
}

