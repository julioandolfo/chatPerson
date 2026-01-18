<?php
/**
 * Script de validação completa da instalação de Campanhas
 * 
 * Verifica se tudo está configurado corretamente
 */

require_once __DIR__ . '/config/bootstrap.php';

$errors = 0;
$warnings = 0;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║          VALIDAÇÃO DE INSTALAÇÃO - CAMPANHAS                 ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

echo "🔍 Verificando instalação...\n\n";

// ============================================
// 1. VERIFICAR TABELAS
// ============================================
echo "1️⃣ VERIFICANDO TABELAS NO BANCO DE DADOS\n";
echo "───────────────────────────────────────────────────────────\n";

$requiredTables = [
    'campaigns',
    'contact_lists',
    'contact_list_items',
    'campaign_messages',
    'campaign_blacklist',
    'campaign_rotation_log'
];

foreach ($requiredTables as $table) {
    try {
        $sql = "SHOW TABLES LIKE '{$table}'";
        $result = \App\Helpers\Database::fetch($sql, []);
        
        if ($result) {
            echo "   ✅ Tabela '{$table}' existe\n";
        } else {
            echo "   ❌ Tabela '{$table}' NÃO EXISTE\n";
            $errors++;
        }
    } catch (\Exception $e) {
        echo "   ❌ Erro ao verificar '{$table}': " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n";

// ============================================
// 2. VERIFICAR MODELS
// ============================================
echo "2️⃣ VERIFICANDO MODELS\n";
echo "───────────────────────────────────────────────────────────\n";

$requiredModels = [
    'Campaign' => 'App\\Models\\Campaign',
    'ContactList' => 'App\\Models\\ContactList',
    'CampaignMessage' => 'App\\Models\\CampaignMessage',
    'CampaignBlacklist' => 'App\\Models\\CampaignBlacklist'
];

foreach ($requiredModels as $name => $class) {
    if (class_exists($class)) {
        echo "   ✅ Model '{$name}' existe\n";
    } else {
        echo "   ❌ Model '{$name}' NÃO EXISTE\n";
        $errors++;
    }
}

echo "\n";

// ============================================
// 3. VERIFICAR SERVICES
// ============================================
echo "3️⃣ VERIFICANDO SERVICES\n";
echo "───────────────────────────────────────────────────────────\n";

$requiredServices = [
    'CampaignService' => 'App\\Services\\CampaignService',
    'ContactListService' => 'App\\Services\\ContactListService',
    'CampaignSchedulerService' => 'App\\Services\\CampaignSchedulerService'
];

foreach ($requiredServices as $name => $class) {
    if (class_exists($class)) {
        echo "   ✅ Service '{$name}' existe\n";
    } else {
        echo "   ❌ Service '{$name}' NÃO EXISTE\n";
        $errors++;
    }
}

echo "\n";

// ============================================
// 4. VERIFICAR CONTROLLERS
// ============================================
echo "4️⃣ VERIFICANDO CONTROLLERS\n";
echo "───────────────────────────────────────────────────────────\n";

$requiredControllers = [
    'CampaignController' => 'App\\Controllers\\CampaignController',
    'ContactListController' => 'App\\Controllers\\ContactListController'
];

foreach ($requiredControllers as $name => $class) {
    if (class_exists($class)) {
        echo "   ✅ Controller '{$name}' existe\n";
    } else {
        echo "   ❌ Controller '{$name}' NÃO EXISTE\n";
        $errors++;
    }
}

echo "\n";

// ============================================
// 5. VERIFICAR SCRIPT DE CRON
// ============================================
echo "5️⃣ VERIFICANDO SCRIPT DE PROCESSAMENTO\n";
echo "───────────────────────────────────────────────────────────\n";

$cronScript = __DIR__ . '/public/scripts/process-campaigns.php';
if (file_exists($cronScript)) {
    echo "   ✅ Script 'process-campaigns.php' existe\n";
    
    // Verificar se é executável
    if (is_readable($cronScript)) {
        echo "   ✅ Script é legível\n";
    } else {
        echo "   ⚠️ Script não é legível\n";
        $warnings++;
    }
} else {
    echo "   ❌ Script 'process-campaigns.php' NÃO EXISTE\n";
    $errors++;
}

echo "\n";

// ============================================
// 6. VERIFICAR CONTAS WHATSAPP
// ============================================
echo "6️⃣ VERIFICANDO CONTAS WHATSAPP\n";
echo "───────────────────────────────────────────────────────────\n";

try {
    $sql = "SELECT COUNT(*) as total FROM integration_accounts WHERE channel = 'whatsapp'";
    $result = \App\Helpers\Database::fetch($sql, []);
    $totalAccounts = $result['total'] ?? 0;
    
    $sqlActive = "SELECT COUNT(*) as total FROM integration_accounts WHERE channel = 'whatsapp' AND status = 'active'";
    $resultActive = \App\Helpers\Database::fetch($sqlActive, []);
    $activeAccounts = $resultActive['total'] ?? 0;
    
    if ($totalAccounts === 0) {
        echo "   ❌ NENHUMA conta WhatsApp encontrada\n";
        echo "      Configure pelo menos 1 conta em /integrations\n";
        $errors++;
    } else {
        echo "   ✅ {$totalAccounts} conta(s) WhatsApp encontrada(s)\n";
        
        if ($activeAccounts === 0) {
            echo "   ❌ NENHUMA conta ATIVA\n";
            echo "      Ative pelo menos 1 conta para usar campanhas\n";
            $errors++;
        } else {
            echo "   ✅ {$activeAccounts} conta(s) ATIVA(s)\n";
            
            if ($activeAccounts === 1) {
                echo "   ⚠️ Apenas 1 conta ativa - rotação não será efetiva\n";
                echo "      Adicione mais contas para aproveitar a rotação\n";
                $warnings++;
            } else {
                echo "   🎉 {$activeAccounts} contas ativas - rotação funcionará!\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Erro ao verificar contas: " . $e->getMessage() . "\n";
    $errors++;
}

echo "\n";

// ============================================
// 7. VERIFICAR CONTATOS
// ============================================
echo "7️⃣ VERIFICANDO CONTATOS\n";
echo "───────────────────────────────────────────────────────────\n";

try {
    $sql = "SELECT COUNT(*) as total FROM contacts";
    $result = \App\Helpers\Database::fetch($sql, []);
    $totalContacts = $result['total'] ?? 0;
    
    if ($totalContacts === 0) {
        echo "   ⚠️ NENHUM contato cadastrado\n";
        echo "      Cadastre contatos em /contacts para testar\n";
        $warnings++;
    } else {
        echo "   ✅ {$totalContacts} contato(s) cadastrado(s)\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Erro ao verificar contatos: " . $e->getMessage() . "\n";
    $errors++;
}

echo "\n";

// ============================================
// RESULTADO FINAL
// ============================================
echo "═══════════════════════════════════════════════════════════\n\n";

if ($errors === 0 && $warnings === 0) {
    echo "🎉 PERFEITO! Sistema 100% configurado e pronto para uso!\n\n";
    echo "Próximos passos:\n";
    echo "   1. Execute: php test-campaign-example.php\n";
    echo "   2. Execute: php public\\scripts\\process-campaigns.php\n";
    echo "   3. Verifique: php check-rotation.php 1\n\n";
    exit(0);
} elseif ($errors === 0 && $warnings > 0) {
    echo "✅ Sistema funcional com {$warnings} aviso(s).\n\n";
    echo "Você pode usar o sistema, mas recomendamos corrigir os avisos.\n\n";
    exit(0);
} else {
    echo "❌ Encontrados {$errors} erro(s) e {$warnings} aviso(s).\n\n";
    echo "Corrija os erros antes de usar o sistema:\n";
    echo "   1. Execute: php database\\migrate.php\n";
    echo "   2. Configure contas WhatsApp\n";
    echo "   3. Execute este script novamente\n\n";
    exit(1);
}
