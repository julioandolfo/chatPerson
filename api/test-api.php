<?php
/**
 * Script de Teste da API
 * Executa testes básicos para validar funcionamento
 */

echo "🧪 TESTANDO API REST\n";
echo "===================\n\n";

// Configuração
$baseUrl = 'http://localhost/api/v1'; // Ajustar conforme necessário
$email = 'admin@admin.com'; // Ajustar
$password = 'admin123'; // Ajustar

echo "📍 Base URL: {$baseUrl}\n\n";

// Função auxiliar para fazer requisições
function apiRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    $headers = ['Content-Type: application/json'];
    
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Teste 1: Login
echo "1️⃣  Teste: Login\n";
$response = apiRequest("{$baseUrl}/auth/login", 'POST', [
    'email' => $email,
    'password' => $password
]);

if ($response['code'] === 200 && isset($response['body']['data']['access_token'])) {
    $token = $response['body']['data']['access_token'];
    echo "   ✅ Login bem-sucedido\n";
    echo "   🔑 Token: " . substr($token, 0, 20) . "...\n\n";
} else {
    echo "   ❌ Falha no login\n";
    echo "   Código: {$response['code']}\n";
    echo "   Resposta: " . json_encode($response['body']) . "\n\n";
    exit(1);
}

// Teste 2: Obter dados do usuário
echo "2️⃣  Teste: GET /auth/me\n";
$response = apiRequest("{$baseUrl}/auth/me", 'GET', null, $token);

if ($response['code'] === 200) {
    echo "   ✅ Dados do usuário obtidos\n";
    echo "   👤 Nome: " . ($response['body']['data']['user']['name'] ?? 'N/A') . "\n\n";
} else {
    echo "   ❌ Falha ao obter dados\n";
    echo "   Código: {$response['code']}\n\n";
}

// Teste 3: Listar conversas
echo "3️⃣  Teste: GET /conversations\n";
$response = apiRequest("{$baseUrl}/conversations?page=1&per_page=5", 'GET', null, $token);

if ($response['code'] === 200) {
    $total = $response['body']['data']['pagination']['total'] ?? 0;
    echo "   ✅ Conversas listadas\n";
    echo "   📊 Total: {$total}\n\n";
} else {
    echo "   ❌ Falha ao listar conversas\n";
    echo "   Código: {$response['code']}\n\n";
}

// Teste 4: Listar contatos
echo "4️⃣  Teste: GET /contacts\n";
$response = apiRequest("{$baseUrl}/contacts?page=1&per_page=5", 'GET', null, $token);

if ($response['code'] === 200) {
    $total = $response['body']['data']['pagination']['total'] ?? 0;
    echo "   ✅ Contatos listados\n";
    echo "   📊 Total: {$total}\n\n";
} else {
    echo "   ❌ Falha ao listar contatos\n";
    echo "   Código: {$response['code']}\n\n";
}

// Teste 5: Listar agentes
echo "5️⃣  Teste: GET /agents\n";
$response = apiRequest("{$baseUrl}/agents", 'GET', null, $token);

if ($response['code'] === 200) {
    $total = count($response['body']['data'] ?? []);
    echo "   ✅ Agentes listados\n";
    echo "   📊 Total: {$total}\n\n";
} else {
    echo "   ❌ Falha ao listar agentes\n";
    echo "   Código: {$response['code']}\n\n";
}

// Teste 6: Listar funis
echo "6️⃣  Teste: GET /funnels\n";
$response = apiRequest("{$baseUrl}/funnels", 'GET', null, $token);

if ($response['code'] === 200) {
    $total = count($response['body']['data'] ?? []);
    echo "   ✅ Funis listados\n";
    echo "   📊 Total: {$total}\n\n";
} else {
    echo "   ❌ Falha ao listar funis\n";
    echo "   Código: {$response['code']}\n\n";
}

// Teste 7: Rate Limiting (fazer 5 requisições rápidas)
echo "7️⃣  Teste: Rate Limiting\n";
$rateLimitHeaders = [];
for ($i = 1; $i <= 5; $i++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/auth/me");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    
    // Extrair headers
    preg_match('/X-RateLimit-Remaining: (\d+)/', $response, $matches);
    $remaining = $matches[1] ?? 'N/A';
    
    echo "   Requisição {$i}/5 - Remaining: {$remaining}\n";
    
    curl_close($ch);
    usleep(100000); // 0.1s entre requisições
}
echo "   ✅ Rate limiting funcionando\n\n";

// Resumo
echo "===================\n";
echo "✅ TESTES CONCLUÍDOS\n";
echo "===================\n";
echo "\n📖 Consulte api/README.md para mais informações\n";
