<?php
/**
 * Script de Setup Automático - Interface Web de Campanhas
 * 
 * Este script:
 * 1. Executa migrations
 * 2. Valida estrutura
 * 3. Cria dados de exemplo (opcional)
 * 4. Configura permissões
 */

require_once __DIR__ . '/config/bootstrap.php';

$errors = [];
$warnings = [];
$success = [];

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     SETUP AUTOMÁTICO - INTERFACE WEB DE CAMPANHAS        ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// PASSO 1: Executar Migrations
echo "📦 PASSO 1: Executando migrations...\n";
echo "─────────────────────────────────────────────────────────────\n";

try {
    exec('php database\migrate.php', $output, $returnCode);
    
    if ($returnCode === 0) {
        $success[] = "Migrations executadas com sucesso";
        echo "✅ Migrations concluídas\n\n";
    } else {
        $errors[] = "Erro ao executar migrations";
        echo "❌ Erro nas migrations\n\n";
    }
} catch (\Exception $e) {
    $errors[] = "Erro ao executar migrations: " . $e->getMessage();
    echo "❌ Erro: " . $e->getMessage() . "\n\n";
}

// PASSO 2: Validar Tabelas
echo "🔍 PASSO 2: Validando tabelas...\n";
echo "─────────────────────────────────────────────────────────────\n";

$requiredTables = [
    'campaigns', 'contact_lists', 'contact_list_items',
    'campaign_messages', 'campaign_blacklist', 'campaign_rotation_log',
    'campaign_variants', 'campaign_notifications',
    'drip_sequences', 'drip_steps', 'drip_contact_progress'
];

foreach ($requiredTables as $table) {
    try {
        $sql = "SHOW TABLES LIKE '{$table}'";
        $result = \App\Helpers\Database::fetch($sql, []);
        
        if ($result) {
            echo "✅ Tabela '{$table}' OK\n";
        } else {
            $errors[] = "Tabela '{$table}' não encontrada";
            echo "❌ Tabela '{$table}' não encontrada\n";
        }
    } catch (\Exception $e) {
        $errors[] = "Erro ao verificar tabela '{$table}'";
        echo "❌ Erro: '{$table}'\n";
    }
}

echo "\n";

// PASSO 3: Validar Views
echo "🎨 PASSO 3: Validando views...\n";
echo "─────────────────────────────────────────────────────────────\n";

$requiredViews = [
    'views/campaigns/index.php',
    'views/campaigns/create.php',
    'views/campaigns/show.php',
    'views/campaigns/dashboard.php',
    'views/campaigns/analytics.php',
    'views/campaigns/ab-test.php',
    'views/contact-lists/index.php',
    'views/contact-lists/show.php'
];

foreach ($requiredViews as $view) {
    if (file_exists(__DIR__ . '/' . $view)) {
        echo "✅ View '{$view}' OK\n";
    } else {
        $warnings[] = "View '{$view}' não encontrada";
        echo "⚠️ View '{$view}' não encontrada\n";
    }
}

echo "\n";

// PASSO 4: Verificar Contas WhatsApp
echo "📱 PASSO 4: Verificando contas WhatsApp...\n";
echo "─────────────────────────────────────────────────────────────\n";

try {
    $sql = "SELECT COUNT(*) as total FROM integration_accounts WHERE channel = 'whatsapp' AND status = 'active'";
    $result = \App\Helpers\Database::fetch($sql, []);
    $activeAccounts = $result['total'] ?? 0;
    
    if ($activeAccounts === 0) {
        $warnings[] = "Nenhuma conta WhatsApp ativa encontrada";
        echo "⚠️ Nenhuma conta ativa. Configure em /integrations\n";
    } elseif ($activeAccounts === 1) {
        $warnings[] = "Apenas 1 conta ativa (rotação não será efetiva)";
        echo "⚠️ {$activeAccounts} conta ativa (adicione mais para rotação)\n";
    } else {
        $success[] = "{$activeAccounts} contas WhatsApp ativas";
        echo "✅ {$activeAccounts} contas ativas - rotação OK!\n";
    }
} catch (\Exception $e) {
    $errors[] = "Erro ao verificar contas WhatsApp";
    echo "❌ Erro ao verificar contas\n";
}

echo "\n";

// PASSO 5: Criar Dados de Exemplo (Opcional)
echo "📊 PASSO 5: Dados de exemplo (opcional)...\n";
echo "─────────────────────────────────────────────────────────────\n";
echo "Digite 'sim' para criar dados de exemplo (lista + campanha): ";

$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));

if (strtolower($response) === 'sim' || strtolower($response) === 's') {
    try {
        // Criar lista de exemplo
        $listId = \App\Services\ContactListService::create([
            'name' => 'Lista de Exemplo - Setup',
            'description' => 'Lista criada automaticamente durante setup',
            'created_by' => 1
        ]);
        
        echo "✅ Lista de exemplo criada (ID: {$listId})\n";
        
        // Adicionar contatos de exemplo (se houver)
        $sql = "SELECT id FROM contacts LIMIT 3";
        $contacts = \App\Helpers\Database::fetchAll($sql, []);
        
        foreach ($contacts as $contact) {
            \App\Services\ContactListService::addContact($listId, $contact['id']);
        }
        
        echo "✅ " . count($contacts) . " contatos adicionados\n";
        
        $success[] = "Dados de exemplo criados";
        
    } catch (\Exception $e) {
        $warnings[] = "Erro ao criar dados de exemplo: " . $e->getMessage();
        echo "⚠️ Erro ao criar dados de exemplo\n";
    }
} else {
    echo "⏭️ Pulando criação de dados de exemplo\n";
}

echo "\n";

// RESULTADO FINAL
echo "═══════════════════════════════════════════════════════════\n";
echo "📊 RESULTADO DO SETUP\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "✅ Sucessos: " . count($success) . "\n";
foreach ($success as $msg) {
    echo "   • {$msg}\n";
}

if (!empty($warnings)) {
    echo "\n⚠️ Avisos: " . count($warnings) . "\n";
    foreach ($warnings as $msg) {
        echo "   • {$msg}\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ Erros: " . count($errors) . "\n";
    foreach ($errors as $msg) {
        echo "   • {$msg}\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";

if (empty($errors)) {
    echo "🎉 SETUP CONCLUÍDO COM SUCESSO!\n\n";
    echo "📍 Próximos passos:\n";
    echo "   1. Acesse: http://localhost/campaigns\n";
    echo "   2. Ou: http://localhost/campaigns/quick-start (tour guiado)\n";
    echo "   3. Configure cron job para processamento automático\n\n";
    exit(0);
} else {
    echo "❌ SETUP INCOMPLETO\n\n";
    echo "Corrija os erros e execute novamente.\n\n";
    exit(1);
}
