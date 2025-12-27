<?php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

// Verificar se está sendo executado via CLI ou browser
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    // Se for via browser, verificar autenticação
    session_start();
    if (!isset($_SESSION['user_id'])) {
        die('❌ Acesso negado. Faça login primeiro.');
    }
    
    // Adicionar header HTML
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Diagnóstico Completo</title>
        <style>
            body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
            .success { color: #4ec9b0; }
            .error { color: #f48771; }
            .warning { color: #ce9178; }
            .info { color: #569cd6; }
            .box { border: 1px solid #444; padding: 15px; margin: 10px 0; }
        </style>
    </head>
    <body>
    <pre>';
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         DIAGNÓSTICO COMPLETO DO SISTEMA                  ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// 1. Testar Conexão com Banco
echo "🔍 1. BANCO DE DADOS\n";
echo str_repeat("─", 60) . "\n";

try {
    $pdo = \App\Helpers\Database::getInstance();
    echo "✅ Conexão com banco: OK\n";
    
    // Testar query
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM automations");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Query funcionando: OK ({$result['total']} automações)\n";
} catch (\Exception $e) {
    echo "❌ Erro no banco: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Verificar Automações
echo "🤖 2. AUTOMAÇÕES\n";
echo str_repeat("─", 60) . "\n";

try {
    $stmt = $pdo->query("
        SELECT 
            id, 
            name, 
            trigger_type, 
            status, 
            is_active,
            trigger_config,
            (SELECT COUNT(*) FROM automation_nodes WHERE automation_id = automations.id) as nodes_count
        FROM automations 
        ORDER BY id
    ");
    $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Total de automações: " . count($automations) . "\n\n";
    
    foreach ($automations as $auto) {
        $statusEmoji = ($auto['status'] === 'active' && $auto['is_active']) ? '✅' : '❌';
        echo "{$statusEmoji} #{$auto['id']}: {$auto['name']}\n";
        echo "   Tipo: {$auto['trigger_type']}\n";
        echo "   Status: {$auto['status']} | Ativo: " . ($auto['is_active'] ? 'SIM' : 'NÃO') . "\n";
        echo "   Nós: {$auto['nodes_count']}\n";
        echo "   Config: " . ($auto['trigger_config'] ?: 'null') . "\n";
        echo "\n";
    }
} catch (\Exception $e) {
    echo "❌ Erro ao buscar automações: " . $e->getMessage() . "\n";
}

// 3. Verificar Integrações
echo "🔌 3. INTEGRAÇÕES\n";
echo str_repeat("─", 60) . "\n";

try {
    // WhatsApp Accounts (Quepasa)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM whatsapp_accounts WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📱 WhatsApp (Quepasa): {$result['total']} contas ativas\n";
    
    // Integration Accounts (Notificame, etc)
    $stmt = $pdo->query("SELECT provider, channel, COUNT(*) as total FROM integration_accounts WHERE is_active = 1 GROUP BY provider, channel");
    $integrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($integrations)) {
        foreach ($integrations as $int) {
            echo "🔗 {$int['provider']} ({$int['channel']}): {$int['total']} contas\n";
        }
    } else {
        echo "⚠️  Nenhuma integration_account ativa\n";
    }
    
    // Meta (Instagram + WhatsApp)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM instagram_accounts WHERE is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📷 Instagram: {$result['total']} contas\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM whatsapp_phones WHERE is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "💚 WhatsApp Cloud: {$result['total']} números\n";
    
} catch (\Exception $e) {
    echo "❌ Erro ao verificar integrações: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Últimas Conversas
echo "💬 4. ÚLTIMAS CONVERSAS\n";
echo str_repeat("─", 60) . "\n";

try {
    $stmt = $pdo->query("
        SELECT 
            id, 
            channel, 
            contact_name, 
            status, 
            created_at,
            updated_at
        FROM conversations 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "⚠️  Nenhuma conversa encontrada\n";
    } else {
        foreach ($conversations as $conv) {
            echo "#{$conv['id']}: {$conv['contact_name']} ({$conv['channel']})\n";
            echo "   Status: {$conv['status']}\n";
            echo "   Criada: {$conv['created_at']}\n";
            echo "   Atualizada: {$conv['updated_at']}\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Erro ao buscar conversas: " . $e->getMessage() . "\n";
}

// 5. Últimas Mensagens
echo "📨 5. ÚLTIMAS MENSAGENS\n";
echo str_repeat("─", 60) . "\n";

try {
    $stmt = $pdo->query("
        SELECT 
            m.id,
            m.conversation_id,
            m.sender_type,
            LEFT(m.content, 50) as content_preview,
            m.created_at,
            c.channel
        FROM messages m
        LEFT JOIN conversations c ON m.conversation_id = c.id
        ORDER BY m.created_at DESC 
        LIMIT 10
    ");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        echo "⚠️  Nenhuma mensagem encontrada\n";
    } else {
        foreach ($messages as $msg) {
            $senderEmoji = $msg['sender_type'] === 'contact' ? '👤' : '🤖';
            echo "{$senderEmoji} Msg #{$msg['id']} (Conv #{$msg['conversation_id']}) - {$msg['channel']}\n";
            echo "   De: {$msg['sender_type']}\n";
            echo "   Conteúdo: {$msg['content_preview']}...\n";
            echo "   Data: {$msg['created_at']}\n\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ Erro ao buscar mensagens: " . $e->getMessage() . "\n";
}

// 6. Logs de Automação
echo "📝 6. ÚLTIMAS EXECUÇÕES DE AUTOMAÇÃO\n";
echo str_repeat("─", 60) . "\n";

$logFile = __DIR__ . '/../storage/logs/automation.log';

if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $lastLines = array_slice($lines, -20);
    
    foreach ($lastLines as $line) {
        if (!empty(trim($line))) {
            echo $line . "\n";
        }
    }
} else {
    echo "⚠️  Arquivo de log não encontrado\n";
}

echo "\n";

// 7. Resumo
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║                       RESUMO                              ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// Verificar problemas
$problems = [];

if (!isset($result) || count($automations) === 0) {
    $problems[] = "❌ Nenhuma automação configurada";
}

$activeAutomations = array_filter($automations, function($a) {
    return $a['status'] === 'active' && $a['is_active'];
});

if (empty($activeAutomations)) {
    $problems[] = "⚠️  Nenhuma automação ativa";
}

if (empty($conversations)) {
    $problems[] = "⚠️  Nenhuma conversa recente";
}

if (empty($messages)) {
    $problems[] = "❌ Nenhuma mensagem recente - PROBLEMA CRÍTICO!";
}

if (!empty($problems)) {
    echo "🚨 PROBLEMAS DETECTADOS:\n\n";
    foreach ($problems as $problem) {
        echo "   {$problem}\n";
    }
    echo "\n";
    
    if (empty($messages)) {
        echo "💡 DIAGNÓSTICO:\n";
        echo "   As automações NÃO estão rodando porque NÃO HÁ MENSAGENS chegando.\n";
        echo "   O problema está nas INTEGRAÇÕES, não nas automações.\n\n";
        echo "   VERIFIQUE:\n";
        echo "   1. Webhooks configurados corretamente?\n";
        echo "   2. Integrações conectadas (Quepasa, Notificame, Meta)?\n";
        echo "   3. Contas ativas e válidas?\n";
        echo "   4. Envie uma mensagem de teste e veja se aparece aqui\n\n";
    }
} else {
    echo "✅ Sistema funcionando normalmente!\n\n";
}

echo "Diagnóstico concluído em: " . date('Y-m-d H:i:s') . "\n";

if (!$isCli) {
    echo '</pre>
    <br><a href="/conversations" style="color: #569cd6;">← Voltar para Conversas</a>
    </body>
    </html>';
}

