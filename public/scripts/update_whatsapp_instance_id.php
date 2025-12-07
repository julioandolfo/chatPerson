<?php
/**
 * Script para atualizar instance_id da conta WhatsApp
 * Execute via navegador ou CLI para atualizar o instance_id
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Models\WhatsAppAccount;
use App\Helpers\Logger;

// ID da conta WhatsApp para atualizar (ajuste conforme necessário)
$accountId = 12; // Altere para o ID correto da sua conta

// Buscar conta
$account = WhatsAppAccount::find($accountId);

if (!$account) {
    die("❌ Conta WhatsApp #{$accountId} não encontrada!\n");
}

echo "📋 Conta encontrada: #{$account['id']} - {$account['name']}\n";
echo "   Provider: {$account['provider']}\n";
echo "   Phone: {$account['phone_number']}\n";
echo "   API URL: {$account['api_url']}\n";
echo "   Quepasa User: " . ($account['quepasa_user'] ?? 'não definido') . "\n";
echo "   Quepasa TrackID: " . ($account['quepasa_trackid'] ?? 'não definido') . "\n";
echo "   Instance ID atual: " . ($account['instance_id'] ?: 'VAZIO') . "\n\n";

// Tentar extrair instance_id de várias fontes
$possibleInstanceIds = [];

// 1. Se já tiver instance_id, manter
if (!empty($account['instance_id'])) {
    $possibleInstanceIds['atual'] = $account['instance_id'];
}

// 2. Extrair da URL
if (!empty($account['api_url'])) {
    $urlParts = parse_url($account['api_url']);
    $path = $urlParts['path'] ?? '';
    $pathSegments = array_filter(explode('/', trim($path, '/')));
    if (!empty($pathSegments)) {
        $possibleInstanceIds['url_path'] = end($pathSegments);
    }
    // Também tentar hostname como base
    if (!empty($urlParts['host'])) {
        $possibleInstanceIds['hostname'] = $urlParts['host'];
    }
}

// 3. Quepasa User
if (!empty($account['quepasa_user'])) {
    $possibleInstanceIds['quepasa_user'] = $account['quepasa_user'];
}

// 4. Quepasa TrackID
if (!empty($account['quepasa_trackid'])) {
    $possibleInstanceIds['quepasa_trackid'] = $account['quepasa_trackid'];
}

// 5. Nome da conta (limpo)
$possibleInstanceIds['nome_limpo'] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $account['name']));

echo "🔍 Possíveis valores para instance_id:\n";
foreach ($possibleInstanceIds as $source => $value) {
    echo "   [{$source}]: {$value}\n";
}

echo "\n📝 Escolha qual usar:\n";
echo "   1. quepasa_trackid (recomendado se você definiu na criação)\n";
echo "   2. quepasa_user\n";
echo "   3. url_path (última parte da URL)\n";
echo "   4. nome_limpo\n";
echo "   5. Digitar manualmente\n";
echo "   0. Cancelar\n\n";

// Se for CLI, ler input
if (php_sapi_name() === 'cli') {
    echo "Escolha: ";
    $choice = trim(fgets(STDIN));
    
    switch ($choice) {
        case '1':
            $newInstanceId = $possibleInstanceIds['quepasa_trackid'] ?? null;
            break;
        case '2':
            $newInstanceId = $possibleInstanceIds['quepasa_user'] ?? null;
            break;
        case '3':
            $newInstanceId = $possibleInstanceIds['url_path'] ?? null;
            break;
        case '4':
            $newInstanceId = $possibleInstanceIds['nome_limpo'] ?? null;
            break;
        case '5':
            echo "Digite o instance_id: ";
            $newInstanceId = trim(fgets(STDIN));
            break;
        case '0':
        default:
            die("❌ Cancelado!\n");
    }
    
    if (empty($newInstanceId)) {
        die("❌ Instance ID vazio! Cancelado.\n");
    }
    
    echo "\n✅ Instance ID selecionado: {$newInstanceId}\n";
    echo "Confirma atualização? (s/n): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 's') {
        die("❌ Cancelado!\n");
    }
    
    // Atualizar
    WhatsAppAccount::update($accountId, ['instance_id' => $newInstanceId]);
    Logger::quepasa("Instance ID atualizado manualmente via script para conta {$accountId}: {$newInstanceId}");
    
    echo "✅ Instance ID atualizado com sucesso!\n";
    echo "   Novo valor: {$newInstanceId}\n";
    
} else {
    // Se for web, mostrar form
    echo "⚠️ Execute este script via CLI para atualizar interativamente.\n";
    echo "   Ou edite diretamente no banco de dados:\n";
    echo "   UPDATE whatsapp_accounts SET instance_id = 'SEU_INSTANCE_ID' WHERE id = {$accountId};\n\n";
    
    echo "🎯 Recomendação: Use o valor de 'quepasa_trackid' como instance_id\n";
    if (!empty($possibleInstanceIds['quepasa_trackid'])) {
        echo "   SQL: UPDATE whatsapp_accounts SET instance_id = '{$possibleInstanceIds['quepasa_trackid']}' WHERE id = {$accountId};\n";
    }
}

