<?php
/**
 * Corrigir trigger_config das automações
 * 
 * Problema: integration_account com ID 1 é na verdade whatsapp_account com ID 6
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$pdo = \App\Helpers\Database::getInstance();

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         CORRIGIR TRIGGER_CONFIG DAS AUTOMAÇÕES            ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// 1. Buscar mapeamento de integration_accounts para whatsapp_accounts
echo "🔍 1. MAPEAMENTO DE CONTAS\n";
echo str_repeat("─", 60) . "\n\n";

$sql = "SELECT ia.id as integration_id, ia.name, ia.phone_number, ia.channel,
        wa.id as whatsapp_id
        FROM integration_accounts ia
        LEFT JOIN whatsapp_accounts wa ON ia.phone_number = wa.phone_number
        WHERE ia.channel = 'whatsapp'";

$stmt = $pdo->query($sql);
$mapping = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($mapping as $map) {
    echo "Integration ID: {$map['integration_id']} → WhatsApp ID: " . ($map['whatsapp_id'] ?? 'NULL') . "\n";
    echo "   Nome: {$map['name']}\n";
    echo "   Telefone: {$map['phone_number']}\n\n";
}

// 2. Buscar automações que usam integration_account_id
echo "\n🔍 2. AUTOMAÇÕES COM INTEGRATION_ACCOUNT_ID\n";
echo str_repeat("─", 60) . "\n\n";

$sql = "SELECT id, name, trigger_config 
        FROM automations 
        WHERE trigger_config LIKE '%integration_account_id%' 
        AND trigger_config NOT LIKE '%integration_account_id\":null%'";

$stmt = $pdo->query($sql);
$automations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($automations)) {
    echo "✅ Nenhuma automação encontrada com integration_account_id\n\n";
} else {
    foreach ($automations as $auto) {
        $config = json_decode($auto['trigger_config'], true);
        
        echo "Automação #{$auto['id']}: {$auto['name']}\n";
        echo "   Config atual: " . $auto['trigger_config'] . "\n";
        
        // Buscar whatsapp_account_id correspondente
        if (!empty($config['integration_account_id'])) {
            $integrationId = $config['integration_account_id'];
            
            // Buscar mapeamento
            $whatsappId = null;
            foreach ($mapping as $map) {
                if ($map['integration_id'] == $integrationId) {
                    $whatsappId = $map['whatsapp_id'];
                    break;
                }
            }
            
            if ($whatsappId) {
                echo "   ✅ Encontrado mapeamento: integration_{$integrationId} → whatsapp_{$whatsappId}\n";
                
                // Criar novo config
                $newConfig = $config;
                unset($newConfig['integration_account_id']);
                $newConfig['whatsapp_account_id'] = (string)$whatsappId;
                
                echo "   Config novo: " . json_encode($newConfig) . "\n";
                
                // Perguntar se quer atualizar
                echo "   ⚠️  Atualizar? (s/N): ";
                $handle = fopen ("php://stdin","r");
                $line = trim(fgets($handle));
                
                if(strtolower($line) === 's') {
                    $updateSql = "UPDATE automations SET trigger_config = ? WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([json_encode($newConfig), $auto['id']]);
                    
                    echo "   ✅ Atualizado!\n\n";
                } else {
                    echo "   ❌ Pulado\n\n";
                }
                fclose($handle);
            } else {
                echo "   ⚠️  Não encontrado mapeamento para integration_{$integrationId}\n\n";
            }
        }
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "Processo concluído!\n";
echo "═══════════════════════════════════════════════════════════\n";

