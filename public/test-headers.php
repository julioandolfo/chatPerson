<?php
/**
 * Teste de Headers HTTP
 * Use este arquivo para diagnosticar problemas com o header Authorization
 * 
 * Teste:
 * curl -H "Authorization: Bearer teste123" https://chat.personizi.com.br/test-headers.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$result = [
    'success' => true,
    'message' => 'Teste de headers HTTP',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'headers_found' => []
];

// Método 1: $_SERVER com HTTP_
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerName = str_replace('HTTP_', '', $key);
        $headerName = str_replace('_', '-', $headerName);
        $result['headers_found']['method_1_$_SERVER'][$headerName] = $value;
    }
}

// Método 2: apache_request_headers()
if (function_exists('apache_request_headers')) {
    $result['headers_found']['method_2_apache_request_headers'] = apache_request_headers();
}

// Método 3: getallheaders()
if (function_exists('getallheaders')) {
    $result['headers_found']['method_3_getallheaders'] = getallheaders();
}

// Verificações específicas do Authorization
$authChecks = [
    '$_SERVER[HTTP_AUTHORIZATION]' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    '$_SERVER[REDIRECT_HTTP_AUTHORIZATION]' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
    '$_SERVER[HTTP_X_API_KEY]' => $_SERVER['HTTP_X_API_KEY'] ?? null,
];

$result['authorization_checks'] = $authChecks;

// Verificar se pelo menos um método encontrou Authorization
$authFound = false;
foreach ($authChecks as $check) {
    if (!empty($check)) {
        $authFound = true;
        break;
    }
}

if (!$authFound && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization']) || isset($headers['authorization'])) {
        $authFound = true;
    }
}

$result['authorization_header_working'] = $authFound;

if (!$authFound) {
    $result['warning'] = 'Header Authorization não foi detectado. Verifique a configuração do servidor.';
    $result['solutions'] = [
        '1. Adicione no .htaccess: RewriteCond %{HTTP:Authorization} . + RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
        '2. Ou no Apache config: SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1',
        '3. Ou use o header alternativo: X-API-Key'
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
