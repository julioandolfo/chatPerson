<?php
/**
 * Migration: Unificar contas de integração
 * 
 * Esta migration garante que:
 * 1. Todas as contas de whatsapp_accounts tenham uma entrada correspondente em integration_accounts
 * 2. Todas as conversas tenham integration_account_id preenchido
 * 3. O campo whatsapp_id em integration_accounts esteja corretamente vinculado
 * 
 * Após esta migration, o sistema usará APENAS integration_account_id para envio de mensagens.
 */

function up_unify_accounts_integration() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "=== UNIFICAÇÃO DE CONTAS DE INTEGRAÇÃO ===\n\n";
    
    // PASSO 1: Verificar e criar entradas em integration_accounts para whatsapp_accounts que não têm
    echo "📋 PASSO 1: Sincronizando whatsapp_accounts -> integration_accounts\n";
    
    // Buscar contas WhatsApp que NÃO têm correspondente em integration_accounts
    $orphanAccounts = $db->query("
        SELECT wa.* 
        FROM whatsapp_accounts wa
        LEFT JOIN integration_accounts ia ON (
            ia.whatsapp_id = wa.id
            OR (ia.phone_number = wa.phone_number AND ia.channel = 'whatsapp')
        )
        WHERE ia.id IS NULL
    ")->fetchAll(\PDO::FETCH_ASSOC);
    
    if (count($orphanAccounts) > 0) {
        echo "   Encontradas " . count($orphanAccounts) . " contas WhatsApp sem correspondente em integration_accounts:\n";
        
        foreach ($orphanAccounts as $wa) {
            echo "   - Criando: {$wa['name']} ({$wa['phone_number']})\n";
            
            // Criar entrada em integration_accounts
            $config = json_encode([
                'quepasa_user' => $wa['quepasa_user'] ?? null,
                'quepasa_token' => $wa['quepasa_token'] ?? null,
                'quepasa_trackid' => $wa['quepasa_trackid'] ?? null,
                'quepasa_chatid' => $wa['quepasa_chatid'] ?? null,
                'wavoip_token' => $wa['wavoip_token'] ?? null,
                'wavoip_enabled' => $wa['wavoip_enabled'] ?? 0,
            ]);
            
            $stmt = $db->prepare("
                INSERT INTO integration_accounts 
                (whatsapp_id, name, provider, channel, api_token, api_url, phone_number, status, config, default_funnel_id, default_stage_id, created_at, updated_at)
                VALUES 
                (?, ?, ?, 'whatsapp', ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $wa['id'],
                $wa['name'],
                $wa['provider'] ?? 'quepasa',
                $wa['quepasa_token'] ?? $wa['api_key'] ?? null,
                $wa['api_url'] ?? null,
                $wa['phone_number'],
                $wa['status'] ?? 'active',
                $config,
                $wa['default_funnel_id'] ?? null,
                $wa['default_stage_id'] ?? null,
                $wa['created_at'] ?? date('Y-m-d H:i:s'),
                $wa['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);
            
            echo "     ✅ Criada com ID: " . $db->lastInsertId() . "\n";
        }
    } else {
        echo "   ✅ Todas as contas WhatsApp já têm correspondente em integration_accounts.\n";
    }
    
    // PASSO 2: Atualizar whatsapp_id em integration_accounts que ainda não tem
    echo "\n📋 PASSO 2: Vinculando integration_accounts -> whatsapp_accounts (whatsapp_id)\n";
    
    $sql = "
        UPDATE integration_accounts ia
        INNER JOIN whatsapp_accounts wa ON (
            ia.phone_number = wa.phone_number
            OR ia.phone_number = CONCAT('55', wa.phone_number)
            OR CONCAT('55', ia.phone_number) = wa.phone_number
            OR REPLACE(REPLACE(ia.phone_number, '+', ''), ' ', '') = REPLACE(REPLACE(wa.phone_number, '+', ''), ' ', '')
        )
        SET ia.whatsapp_id = wa.id
        WHERE ia.whatsapp_id IS NULL
            AND ia.channel = 'whatsapp'
    ";
    
    $affected = $db->exec($sql);
    echo "   ✅ {$affected} registros atualizados com whatsapp_id.\n";
    
    // PASSO 3: Sincronizar integration_account_id em conversas que só têm whatsapp_account_id
    echo "\n📋 PASSO 3: Sincronizando conversas (whatsapp_account_id -> integration_account_id)\n";
    
    // Atualizar conversas que têm whatsapp_account_id mas não têm integration_account_id
    $sql = "
        UPDATE conversations c
        INNER JOIN integration_accounts ia ON ia.whatsapp_id = c.whatsapp_account_id
        SET c.integration_account_id = ia.id
        WHERE c.whatsapp_account_id IS NOT NULL
            AND c.integration_account_id IS NULL
    ";
    
    $affected = $db->exec($sql);
    echo "   ✅ {$affected} conversas atualizadas (via whatsapp_id).\n";
    
    // Tentar também por phone_number se ainda houver conversas não mapeadas
    $sql = "
        UPDATE conversations c
        INNER JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id
        INNER JOIN integration_accounts ia ON (
            ia.phone_number = wa.phone_number 
            AND ia.channel = 'whatsapp'
        )
        SET c.integration_account_id = ia.id
        WHERE c.whatsapp_account_id IS NOT NULL
            AND c.integration_account_id IS NULL
    ";
    
    $affected = $db->exec($sql);
    echo "   ✅ {$affected} conversas adicionais atualizadas (via phone_number).\n";
    
    // PASSO 4: Verificar conversas órfãs
    echo "\n📋 PASSO 4: Verificando conversas órfãs\n";
    
    $orphanConversations = $db->query("
        SELECT c.id, c.whatsapp_account_id, c.integration_account_id, ct.name as contact_name, ct.phone as contact_phone
        FROM conversations c
        LEFT JOIN contacts ct ON c.contact_id = ct.id
        WHERE c.channel = 'whatsapp'
            AND c.whatsapp_account_id IS NOT NULL
            AND c.integration_account_id IS NULL
        LIMIT 10
    ")->fetchAll(\PDO::FETCH_ASSOC);
    
    if (count($orphanConversations) > 0) {
        echo "   ⚠️ Ainda existem " . count($orphanConversations) . " conversas sem integration_account_id:\n";
        foreach ($orphanConversations as $conv) {
            echo "      - Conversa #{$conv['id']}: {$conv['contact_name']} ({$conv['contact_phone']}), wa_id={$conv['whatsapp_account_id']}\n";
        }
        echo "   Estas conversas podem estar vinculadas a contas WhatsApp inexistentes.\n";
    } else {
        echo "   ✅ Todas as conversas WhatsApp têm integration_account_id preenchido.\n";
    }
    
    // PASSO 5: Sincronizar api_token de integration_accounts com quepasa_token de whatsapp_accounts
    echo "\n📋 PASSO 5: Sincronizando tokens (quepasa_token -> api_token)\n";
    
    $sql = "
        UPDATE integration_accounts ia
        INNER JOIN whatsapp_accounts wa ON ia.whatsapp_id = wa.id
        SET ia.api_token = wa.quepasa_token,
            ia.api_url = COALESCE(ia.api_url, wa.api_url)
        WHERE (ia.api_token IS NULL OR ia.api_token = '')
            AND wa.quepasa_token IS NOT NULL
            AND wa.quepasa_token != ''
    ";
    
    $affected = $db->exec($sql);
    echo "   ✅ {$affected} tokens sincronizados.\n";
    
    // RESUMO FINAL
    echo "\n=== RESUMO DA UNIFICAÇÃO ===\n";
    
    $stats = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM whatsapp_accounts) as total_whatsapp,
            (SELECT COUNT(*) FROM integration_accounts WHERE channel = 'whatsapp') as total_integration_whatsapp,
            (SELECT COUNT(*) FROM integration_accounts WHERE channel != 'whatsapp') as total_integration_other,
            (SELECT COUNT(*) FROM conversations WHERE channel = 'whatsapp' AND integration_account_id IS NOT NULL) as conv_with_integration,
            (SELECT COUNT(*) FROM conversations WHERE channel = 'whatsapp' AND integration_account_id IS NULL AND whatsapp_account_id IS NOT NULL) as conv_orphan
    ")->fetch(\PDO::FETCH_ASSOC);
    
    echo "   📊 whatsapp_accounts: {$stats['total_whatsapp']} contas\n";
    echo "   📊 integration_accounts (WhatsApp): {$stats['total_integration_whatsapp']} contas\n";
    echo "   📊 integration_accounts (Outros): {$stats['total_integration_other']} contas\n";
    echo "   📊 Conversas WhatsApp com integration_account_id: {$stats['conv_with_integration']}\n";
    echo "   📊 Conversas WhatsApp órfãs: {$stats['conv_orphan']}\n";
    
    echo "\n✅ Unificação concluída! O sistema agora usará apenas integration_account_id.\n";
}

function down_unify_accounts_integration() {
    echo "⚠️ Esta migration não tem rollback automático.\n";
    echo "   Os dados foram sincronizados mas não removidos.\n";
}
