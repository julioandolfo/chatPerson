<?php
/**
 * Teste avançado da API do Instagram
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<pre>";
echo "=== TESTE AVANÇADO API INSTAGRAM ===\n\n";

$db = App\Helpers\Database::getInstance();
$token = $db->query("SELECT * FROM meta_oauth_tokens ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    echo "❌ Nenhum token encontrado!\n";
    exit;
}

$accessToken = $token['access_token'];

echo "✅ Token encontrado!\n";
echo "Meta User ID: {$token['meta_user_id']}\n\n";

// Teste 1: Buscar permissões concedidas
echo "==========================================\n";
echo "TESTE 1: Permissões Concedidas\n";
echo "==========================================\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://graph.facebook.com/v21.0/me/permissions?access_token=' . urlencode($accessToken),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
curl_close($ch);

$permissions = json_decode($response, true);
if (isset($permissions['data'])) {
    echo "Permissões concedidas:\n";
    foreach ($permissions['data'] as $perm) {
        $status = $perm['status'] === 'granted' ? '✅' : '❌';
        echo "  {$status} {$perm['permission']}\n";
    }
} else {
    echo "Erro ao buscar permissões: " . json_encode($permissions) . "\n";
}

// Teste 2: Buscar páginas (método atual)
echo "\n==========================================\n";
echo "TESTE 2: Buscar Páginas (Método Atual)\n";
echo "==========================================\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://graph.facebook.com/v21.0/me/accounts?fields=id,name,access_token&access_token=' . urlencode($accessToken),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
$pages = json_decode($response, true);

if (isset($pages['data'])) {
    echo "Encontradas " . count($pages['data']) . " página(s)\n\n";
    
    foreach ($pages['data'] as $page) {
        echo "Página: {$page['name']}\n";
        echo "ID: {$page['id']}\n";
        
        // Teste 2.1: Buscar Instagram Business Account (campo único)
        echo "\n  Teste 2.1: Campo instagram_business_account\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://graph.facebook.com/v21.0/{$page['id']}?fields=instagram_business_account&access_token=" . urlencode($page['access_token']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response1 = curl_exec($ch);
        $httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  HTTP Code: {$httpCode1}\n";
        $data1 = json_decode($response1, true);
        echo "  Response: " . json_encode($data1, JSON_PRETTY_PRINT) . "\n";
        
        // Teste 2.2: Buscar todos os campos possíveis relacionados ao Instagram
        echo "\n  Teste 2.2: Todos os campos Instagram possíveis\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://graph.facebook.com/v21.0/{$page['id']}?fields=id,name,instagram_business_account,connected_instagram_account&access_token=" . urlencode($page['access_token']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response2 = curl_exec($ch);
        $httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  HTTP Code: {$httpCode2}\n";
        $data2 = json_decode($response2, true);
        echo "  Response: " . json_encode($data2, JSON_PRETTY_PRINT) . "\n";
        
        // Teste 2.3: Tentar via Graph API v18 (versão anterior)
        echo "\n  Teste 2.3: Usando API v18.0 (versão anterior)\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://graph.facebook.com/v18.0/{$page['id']}?fields=instagram_business_account&access_token=" . urlencode($page['access_token']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response3 = curl_exec($ch);
        $httpCode3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  HTTP Code: {$httpCode3}\n";
        $data3 = json_decode($response3, true);
        echo "  Response: " . json_encode($data3, JSON_PRETTY_PRINT) . "\n";
        
        // Teste 2.4: Buscar metadata completa da página
        echo "\n  Teste 2.4: Metadata completa da página\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://graph.facebook.com/v21.0/{$page['id']}?metadata=1&access_token=" . urlencode($page['access_token']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response4 = curl_exec($ch);
        curl_close($ch);
        
        $data4 = json_decode($response4, true);
        
        if (isset($data4['metadata']['fields'])) {
            echo "  Campos disponíveis para esta página:\n";
            foreach ($data4['metadata']['fields'] as $field) {
                if (stripos($field['name'], 'instagram') !== false) {
                    echo "    - {$field['name']} ({$field['type']})\n";
                }
            }
        }
        
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }
} else {
    echo "Erro: " . json_encode($pages) . "\n";
}

// Teste 3: Permissões necessárias segundo a documentação
echo "\n==========================================\n";
echo "TESTE 3: Diagnóstico de Permissões\n";
echo "==========================================\n";

$requiredPermissions = [
    'pages_show_list' => 'Listar páginas',
    'pages_read_engagement' => 'Ler engajamento (inclui Instagram)',
    'instagram_basic' => 'Acesso básico Instagram (legado)',
    'instagram_manage_messages' => 'Gerenciar mensagens Instagram',
    'pages_manage_metadata' => 'Gerenciar metadata',
];

echo "Permissões potencialmente necessárias para acessar Instagram Business Account:\n\n";

foreach ($requiredPermissions as $perm => $desc) {
    $hasPermission = false;
    if (isset($permissions['data'])) {
        foreach ($permissions['data'] as $p) {
            if ($p['permission'] === $perm && $p['status'] === 'granted') {
                $hasPermission = true;
                break;
            }
        }
    }
    
    $status = $hasPermission ? '✅ CONCEDIDA' : '❌ NÃO CONCEDIDA';
    echo "{$status} - {$perm}: {$desc}\n";
}

echo "\n==========================================\n";
echo "RECOMENDAÇÕES:\n";
echo "==========================================\n";
echo "1. Verificar se a conta Instagram está mesmo vinculada à página no Facebook\n";
echo "2. Verificar se é Instagram Business (não Creator)\n";
echo "3. Pode ser necessário adicionar a permissão 'pages_read_engagement'\n";
echo "4. Verificar no Meta App se os produtos Instagram estão habilitados\n";

echo "</pre>";

