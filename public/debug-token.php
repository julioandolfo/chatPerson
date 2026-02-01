<?php
/**
 * Debug de Token - Verificar se o token existe no banco
 */

require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode([
        'error' => 'Forneça o token via ?token=SEU_TOKEN',
        'example' => $_SERVER['HTTP_HOST'] . '/debug-token.php?token=b481e4bb...'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $db = Database::getInstance();
    
    $result = [
        'token_provided' => $token,
        'token_length' => strlen($token),
        'token_sha256' => hash('sha256', $token),
        'database_checks' => []
    ];
    
    // 1. Buscar com hash SHA256
    $stmt = $db->prepare("
        SELECT id, user_id, name, token, revoked_at, expires_at, created_at, last_used_at
        FROM api_tokens 
        WHERE token = ?
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $token)]);
    $tokenWithHash = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result['database_checks']['with_sha256_hash'] = [
        'query' => 'WHERE token = SHA256(token_provided)',
        'found' => !empty($tokenWithHash),
        'data' => $tokenWithHash ?: null
    ];
    
    // 2. Buscar sem hash (token direto)
    $stmt->execute([$token]);
    $tokenDirect = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result['database_checks']['direct_token'] = [
        'query' => 'WHERE token = token_provided',
        'found' => !empty($tokenDirect),
        'data' => $tokenDirect ?: null
    ];
    
    // 3. Listar todos os tokens (primeiros 50 chars)
    $stmt = $db->prepare("
        SELECT id, name, LEFT(token, 50) as token_preview, 
               revoked_at, expires_at, created_at
        FROM api_tokens 
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $allTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result['all_tokens_in_database'] = [
        'total' => count($allTokens),
        'tokens' => $allTokens
    ];
    
    // 4. Verificar se existe algum token ativo
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM api_tokens 
        WHERE revoked_at IS NULL 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute();
    $activeCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result['active_tokens_count'] = $activeCount['total'];
    
    // 5. Diagnóstico
    $result['diagnosis'] = [];
    
    if ($tokenWithHash || $tokenDirect) {
        $foundToken = $tokenWithHash ?: $tokenDirect;
        
        if ($foundToken['revoked_at']) {
            $result['diagnosis'][] = '❌ Token foi REVOGADO em: ' . $foundToken['revoked_at'];
        }
        
        if ($foundToken['expires_at'] && strtotime($foundToken['expires_at']) < time()) {
            $result['diagnosis'][] = '❌ Token EXPIROU em: ' . $foundToken['expires_at'];
        }
        
        if (!$foundToken['revoked_at'] && (!$foundToken['expires_at'] || strtotime($foundToken['expires_at']) > time())) {
            $result['diagnosis'][] = '✅ Token está VÁLIDO e ATIVO';
            $result['diagnosis'][] = 'Nome: ' . $foundToken['name'];
            $result['diagnosis'][] = 'User ID: ' . $foundToken['user_id'];
        }
        
        $result['diagnosis'][] = $tokenWithHash 
            ? 'ℹ Token armazenado COM hash SHA256' 
            : 'ℹ Token armazenado SEM hash (texto plano)';
    } else {
        $result['diagnosis'][] = '❌ Token NÃO ENCONTRADO no banco de dados';
        $result['diagnosis'][] = 'Verifique se o token foi gerado corretamente em /settings/api-tokens';
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Erro ao conectar no banco de dados',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
