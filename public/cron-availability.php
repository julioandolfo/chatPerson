<?php
/**
 * Endpoint para verificar disponibilidade dos agentes via HTTP
 * Pode ser chamado via cron ou serviço externo (ex: cron-job.org)
 * 
 * URL: https://seudominio.com/cron-availability.php?token=SEU_TOKEN_SECRETO
 * 
 * Configure o token em .env: CRON_TOKEN=seu_token_secreto_aqui
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Services\AvailabilityService;

// Verificar token de segurança
$expectedToken = $_ENV['CRON_TOKEN'] ?? 'change-me-in-production';
$providedToken = $_GET['token'] ?? '';

if ($providedToken !== $expectedToken) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Token inválido'
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    $updated = AvailabilityService::checkAllAgents();
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'agents_checked' => count($updated),
        'agents_updated' => $updated
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

