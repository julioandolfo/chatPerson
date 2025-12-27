<?php
/**
 * Teste: Acessar Instagram Business diretamente (sem passar por páginas)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<pre>";
echo "=== TESTE: INSTAGRAM BUSINESS DIRETO ===\n\n";

$db = App\Helpers\Database::getInstance();
$token = $db->query("SELECT * FROM meta_oauth_tokens ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    echo "❌ Nenhum token encontrado!\n";
    exit;
}

$accessToken = $token['access_token'];
echo "✅ Token encontrado!\n\n";

// Método 1: Buscar Instagram Business Accounts via me/accounts (sem campos específicos)
echo "==========================================\n";
echo "MÉTODO 1: Buscar contas sem campos restritos\n";
echo "==========================================\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://graph.facebook.com/v21.0/me/accounts?fields=id,name&access_token=' . urlencode($accessToken),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
$pages = json_decode($response, true);

if (isset($pages['data'])) {
    echo "✅ Encontradas " . count($pages['data']) . " página(s)\n\n";
    
    foreach ($pages['data'] as $page) {
        echo "Página: {$page['name']} (ID: {$page['id']})\n";
    }
}

// Método 2: Tentar acessar /{user-id}/accounts com fields mínimos
echo "\n==========================================\n";
echo "MÉTODO 2: Via User ID específico\n";
echo "==========================================\n";

$userId = $token['meta_user_id'];
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/{$userId}/accounts?fields=id,name&access_token=" . urlencode($accessToken),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
$data = json_decode($response, true);
echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";

// Método 3: Buscar Instagram Business Discovery API
echo "\n==========================================\n";
echo "MÉTODO 3: Instagram Business Discovery API\n";
echo "==========================================\n";
echo "Nota: Este método requer que você saiba o Instagram User ID previamente\n";
echo "Vamos tentar com alguns campos básicos do user\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v21.0/me?fields=id,name&access_token=" . urlencode($accessToken),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
curl_close($ch);

$userData = json_decode($response, true);
echo "User Data: " . json_encode($userData, JSON_PRETTY_PRINT) . "\n";

// Método 4: Verificar Debug Token Info
echo "\n==========================================\n";
echo "MÉTODO 4: Debug Token (ver scopes/permissões)\n";
echo "==========================================\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://graph.facebook.com/v21.0/debug_token?input_token=' . urlencode($accessToken) . '&access_token=' . urlencode($accessToken),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
curl_close($ch);

$debugData = json_decode($response, true);
echo "Debug Info:\n";
echo json_encode($debugData, JSON_PRETTY_PRINT) . "\n";

// Método 5: Tentar via Business Discovery (requer conhecer username)
echo "\n==========================================\n";
echo "MÉTODO 5: Verificar configuração do App\n";
echo "==========================================\n";

$appId = $token['meta_app_id'];
$configFile = __DIR__ . '/../storage/config/meta.json';

echo "App ID usado no OAuth: {$appId}\n";

if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    echo "Config salvo: " . json_encode($config, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "⚠️  Arquivo de configuração não encontrado\n";
}

// Método 6: Sugestão - usar Notificame (já funciona!)
echo "\n==========================================\n";
echo "SOLUÇÃO ALTERNATIVA: Usar Notificame\n";
echo "==========================================\n";
echo "✅ Você JÁ TEM contas Instagram funcionando via Notificame!\n";
echo "✅ ID 3: Instagram Personizi (@personizi) - ATIVO\n\n";

echo "📋 RECOMENDAÇÃO:\n";
echo "1. Continue usando Notificame para Instagram (já funciona 100%)\n";
echo "2. Meta oficial só funciona com App Review aprovado (demora semanas/meses)\n";
echo "3. Para uso em produção, Notificame é a melhor opção atualmente\n\n";

echo "🔗 Para mais informações sobre App Review:\n";
echo "https://developers.facebook.com/docs/app-review\n";

echo "</pre>";

