<?php
/**
 * Script de diagnóstico para Instagram
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

use App\Helpers\Database;

echo "=== DIAGNÓSTICO INSTAGRAM ===\n\n";

// 1. Verificar tokens
echo "1. TOKENS META:\n";
echo "---------------\n";

$db = \App\Helpers\Database::getInstance();
$tokens = $db->query("SELECT * FROM meta_oauth_tokens ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

if (empty($tokens)) {
    echo "❌ Nenhum token encontrado!\n";
} else {
    foreach ($tokens as $token) {
        echo "ID: {$token['id']}\n";
        echo "Meta User ID: {$token['meta_user_id']}\n";
        echo "App Type: {$token['app_type']}\n";
        echo "Válido: " . ($token['is_valid'] ? 'Sim' : 'Não') . "\n";
        echo "Expira em: {$token['expires_at']}\n";
        echo "Integration Account ID: " . ($token['integration_account_id'] ?? 'NULL') . "\n";
        echo "Token (primeiros 20 chars): " . substr($token['access_token'], 0, 20) . "...\n";
        echo "\n";
        
        // Testar o token
        echo "Testando token...\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://graph.facebook.com/v21.0/me/accounts?fields=id,name,access_token&access_token=' . urlencode($token['access_token']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP Code: {$httpCode}\n";
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && isset($data['data'])) {
            echo "✅ Token válido! Encontradas " . count($data['data']) . " página(s) Facebook\n";
            
            // Para cada página, verificar Instagram
            foreach ($data['data'] as $page) {
                echo "\n  Página: {$page['name']} (ID: {$page['id']})\n";
                
                // Verificar Instagram Business Account
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://graph.facebook.com/v21.0/{$page['id']}?fields=instagram_business_account&access_token=" . urlencode($page['access_token']),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                ]);
                $pageResponse = curl_exec($ch);
                curl_close($ch);
                
                $pageData = json_decode($pageResponse, true);
                
                if (isset($pageData['instagram_business_account'])) {
                    $igId = $pageData['instagram_business_account']['id'];
                    echo "  ✅ Instagram Business Account: {$igId}\n";
                    
                    // Buscar perfil
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => "https://graph.facebook.com/v21.0/{$igId}?fields=id,username,name,profile_picture_url,followers_count&access_token=" . urlencode($page['access_token']),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30,
                    ]);
                    $profileResponse = curl_exec($ch);
                    curl_close($ch);
                    
                    $profile = json_decode($profileResponse, true);
                    
                    if (isset($profile['username'])) {
                        echo "  📸 @{$profile['username']} ({$profile['name']})\n";
                        echo "  👥 Seguidores: " . ($profile['followers_count'] ?? 'N/A') . "\n";
                    } else {
                        echo "  ❌ Erro ao buscar perfil: " . json_encode($profile) . "\n";
                    }
                } else {
                    echo "  ⚠️  Sem Instagram Business Account vinculado\n";
                }
            }
        } else {
            echo "❌ Token inválido ou erro: " . json_encode($data) . "\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
}

// 2. Verificar integration_accounts
echo "\n2. INTEGRATION ACCOUNTS (Instagram):\n";
echo "------------------------------------\n";

$accounts = $db->query("SELECT * FROM integration_accounts WHERE channel = 'instagram' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($accounts)) {
    echo "❌ Nenhuma conta Instagram encontrada!\n";
} else {
    foreach ($accounts as $account) {
        echo "ID: {$account['id']}\n";
        echo "Nome: {$account['name']}\n";
        echo "Username: " . ($account['username'] ?? 'N/A') . "\n";
        echo "Account ID: " . ($account['account_id'] ?? 'N/A') . "\n";
        echo "Status: {$account['status']}\n";
        echo "Provider: {$account['provider']}\n";
        
        if (!empty($account['config'])) {
            $config = json_decode($account['config'], true);
            echo "Config: " . json_encode($config, JSON_PRETTY_PRINT) . "\n";
        }
        
        echo "\n";
    }
}

// 3. Verificar instagram_accounts
echo "\n3. INSTAGRAM ACCOUNTS:\n";
echo "---------------------\n";

$igAccounts = $db->query("SELECT * FROM instagram_accounts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($igAccounts)) {
    echo "❌ Nenhuma conta na tabela instagram_accounts!\n";
} else {
    foreach ($igAccounts as $ig) {
        echo "ID: {$ig['id']}\n";
        echo "Instagram ID: " . ($ig['instagram_id'] ?? 'N/A') . "\n";
        echo "Username: " . ($ig['username'] ?? 'N/A') . "\n";
        echo "Status: " . ($ig['status'] ?? 'N/A') . "\n";
        echo "Integration Account ID: " . ($ig['integration_account_id'] ?? 'NULL') . "\n";
        echo "Meta OAuth Token ID: " . ($ig['meta_oauth_token_id'] ?? 'NULL') . "\n";
        echo "\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";

