<?php
/**
 * Script de Verificação - Configurações Meta
 * 
 * Verifica se as credenciais do Meta App estão configuradas corretamente
 */

// Caminho absoluto do root
$rootPath = dirname(__DIR__);

// Carregar autoload
require_once $rootPath . '/app/Helpers/Database.php';
require_once $rootPath . '/app/Config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificação Meta App</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 40px; background: #f5f8fa; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e3a8a; margin-bottom: 10px; }
        .subtitle { color: #64748b; margin-bottom: 30px; }
        .result { padding: 15px; margin-bottom: 15px; border-radius: 6px; border-left: 4px solid; }
        .success { background: #ecfdf5; border-color: #10b981; color: #065f46; }
        .error { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
        .warning { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
        .info { background: #eff6ff; border-color: #3b82f6; color: #1e3a8a; }
        .code { font-family: 'Courier New', monospace; background: #f1f5f9; padding: 10px; border-radius: 3px; font-size: 13px; margin: 10px 0; overflow-x: auto; }
        .icon { margin-right: 8px; font-weight: bold; }
        .button { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .button:hover { background: #2563eb; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f8fafc; font-weight: 600; }
        .masked { filter: blur(4px); }
        .masked:hover { filter: none; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificação de Configurações Meta</h1>
        <p class='subtitle'>Verificando credenciais e configurações do Meta App</p>";

// 1. Verificar se arquivo de configuração existe
echo "<h3>1️⃣ Arquivos de Configuração</h3>";

$configFile = $rootPath . '/config/meta.php';
$jsonConfigFile = $rootPath . '/storage/config/meta.json';

if (file_exists($configFile)) {
    echo "<div class='result success'><span class='icon'>✅</span>";
    echo "<strong>OK:</strong> Arquivo <code>config/meta.php</code> existe</div>";
} else {
    echo "<div class='result error'><span class='icon'>❌</span>";
    echo "<strong>Erro:</strong> Arquivo <code>config/meta.php</code> não encontrado</div>";
}

if (file_exists($jsonConfigFile)) {
    echo "<div class='result success'><span class='icon'>✅</span>";
    echo "<strong>OK:</strong> Arquivo <code>storage/config/meta.json</code> existe (configurado via interface)</div>";
    $configSource = 'JSON';
} else {
    echo "<div class='result info'><span class='icon'>ℹ️</span>";
    echo "<strong>Info:</strong> Arquivo <code>storage/config/meta.json</code> não existe (usando config/meta.php)</div>";
    $configSource = 'PHP';
}

// 2. Carregar configurações
echo "<h3>2️⃣ Credenciais do App Meta</h3>";

$config = [];
if (file_exists($jsonConfigFile)) {
    $json = file_get_contents($jsonConfigFile);
    $jsonConfig = json_decode($json, true);
    
    if ($jsonConfig && !empty($jsonConfig['app_id'])) {
        $phpConfig = require $configFile;
        $config = $phpConfig;
        $config['app_id'] = $jsonConfig['app_id'];
        $config['app_secret'] = $jsonConfig['app_secret'];
        if (!empty($config['webhooks'])) {
            $config['webhooks']['verify_token'] = $jsonConfig['webhook_verify_token'];
        }
    }
} else {
    $config = require $configFile;
}

// Verificar App ID
$appId = $config['app_id'] ?? '';
if (!empty($appId) && $appId !== 'SEU_APP_ID_AQUI') {
    echo "<div class='result success'><span class='icon'>✅</span>";
    echo "<strong>App ID configurado:</strong> <code>{$appId}</code></div>";
} else {
    echo "<div class='result error'><span class='icon'>❌</span>";
    echo "<strong>Erro:</strong> App ID não configurado ou inválido</div>";
}

// Verificar App Secret
$appSecret = $config['app_secret'] ?? '';
if (!empty($appSecret) && $appSecret !== 'SEU_APP_SECRET_AQUI') {
    $maskedSecret = substr($appSecret, 0, 4) . str_repeat('*', strlen($appSecret) - 8) . substr($appSecret, -4);
    echo "<div class='result success'><span class='icon'>✅</span>";
    echo "<strong>App Secret configurado:</strong> <code class='masked'>{$appSecret}</code> (passe o mouse para ver)</div>";
} else {
    echo "<div class='result error'><span class='icon'>❌</span>";
    echo "<strong>Erro:</strong> App Secret não configurado ou inválido</div>";
}

// Verificar Webhook Token
$webhookToken = $config['webhooks']['verify_token'] ?? '';
if (!empty($webhookToken)) {
    $maskedToken = substr($webhookToken, 0, 4) . str_repeat('*', max(0, strlen($webhookToken) - 8)) . substr($webhookToken, -4);
    echo "<div class='result success'><span class='icon'>✅</span>";
    echo "<strong>Webhook Token configurado:</strong> <code class='masked'>{$webhookToken}</code> (passe o mouse para ver)</div>";
} else {
    echo "<div class='result warning'><span class='icon'>⚠️</span>";
    echo "<strong>Aviso:</strong> Webhook Token não configurado (opcional)</div>";
}

// 3. Verificar Permissões (Scopes)
echo "<h3>3️⃣ Permissões (Scopes)</h3>";

$instagramScopes = $config['instagram']['scopes'] ?? [];
if (!empty($instagramScopes)) {
    echo "<div class='result success'><span class='icon'>✅</span>";
    echo "<strong>Permissões Instagram configuradas:</strong></div>";
    echo "<div class='code'>";
    foreach ($instagramScopes as $scope) {
        echo "✓ {$scope}<br>";
    }
    echo "</div>";
} else {
    echo "<div class='result error'><span class='icon'>❌</span>";
    echo "<strong>Erro:</strong> Nenhuma permissão Instagram configurada</div>";
}

// 4. Verificar URLs
echo "<h3>4️⃣ URLs de Integração</h3>";

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$redirectUri = $protocol . '://' . $host . '/integrations/meta/oauth/callback';
$webhookUrl = $protocol . '://' . $host . '/webhooks/meta';

echo "<div class='result info'>";
echo "<strong>Redirect URI (OAuth):</strong><br>";
echo "<div class='code'>{$redirectUri}</div>";
echo "<small>Esta URL deve estar configurada em: Meta App → Facebook Login → Configurações → URIs de redirecionamento</small>";
echo "</div>";

echo "<div class='result info'>";
echo "<strong>Webhook URL:</strong><br>";
echo "<div class='code'>{$webhookUrl}</div>";
echo "<small>Esta URL deve estar configurada em: Meta App → Webhooks → URL de callback</small>";
echo "</div>";

echo "<div class='result info'>";
echo "<strong>Domínio do App:</strong><br>";
echo "<div class='code'>{$host}</div>";
echo "<small>Este domínio deve estar em: Meta App → Configurações → Básico → Domínios do App</small>";
echo "</div>";

// 5. Testar API da Meta (opcional)
echo "<h3>5️⃣ Teste de Conectividade</h3>";

if (!empty($appId) && !empty($appSecret)) {
    $testUrl = "https://graph.facebook.com/oauth/access_token?client_id={$appId}&client_secret={$appSecret}&grant_type=client_credentials";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $testUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            echo "<div class='result success'><span class='icon'>✅</span>";
            echo "<strong>Sucesso!</strong> Credenciais válidas - API Meta está acessível</div>";
        } else {
            echo "<div class='result warning'><span class='icon'>⚠️</span>";
            echo "<strong>Aviso:</strong> API respondeu, mas token não foi retornado<br>";
            echo "<small>Resposta: " . htmlspecialchars($response) . "</small></div>";
        }
    } else {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? $response;
        
        echo "<div class='result error'><span class='icon'>❌</span>";
        echo "<strong>Erro ao conectar com Meta API:</strong> HTTP {$httpCode}<br>";
        echo "<small>" . htmlspecialchars($errorMessage) . "</small></div>";
        
        if ($httpCode === 400 || $httpCode === 401) {
            echo "<div class='result warning'><span class='icon'>💡</span>";
            echo "<strong>Dica:</strong> Verifique se o App ID e App Secret estão corretos no Meta for Developers</div>";
        }
    }
} else {
    echo "<div class='result warning'><span class='icon'>⚠️</span>";
    echo "<strong>Teste não realizado:</strong> App ID ou App Secret não configurados</div>";
}

// Resumo
echo "<h3>📋 Checklist</h3>";
echo "<table>";
echo "<tr><th>Item</th><th>Status</th></tr>";
echo "<tr><td>App ID configurado</td><td>" . (!empty($appId) && $appId !== 'SEU_APP_ID_AQUI' ? '✅ Sim' : '❌ Não') . "</td></tr>";
echo "<tr><td>App Secret configurado</td><td>" . (!empty($appSecret) && $appSecret !== 'SEU_APP_SECRET_AQUI' ? '✅ Sim' : '❌ Não') . "</td></tr>";
echo "<tr><td>Permissões configuradas</td><td>" . (!empty($instagramScopes) ? '✅ Sim (' . count($instagramScopes) . ' scopes)' : '❌ Não') . "</td></tr>";
echo "<tr><td>API Meta acessível</td><td>" . ($httpCode === 200 ? '✅ Sim' : '❌ Não') . "</td></tr>";
echo "</table>";

echo "<a href='/integrations/meta' class='button'>← Voltar para Integrações Meta</a>";
echo "<a href='/integrations/meta?clear_session=1' class='button' style='background: #f59e0b; margin-left: 10px;'>🔄 Limpar Sessão e Tentar Novamente</a>";

echo "</div></body></html>";

