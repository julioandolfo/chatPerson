<?php
/**
 * Endpoint para verificar disponibilidade dos agentes via HTTP
 * Pode ser chamado via cron ou serviço externo (ex: cron-job.org)
 * 
 * URL: https://seudominio.com/cron-availability.php
 * 
 * Uso: Configure um cron HTTP (ex: cron-job.org) para chamar esta URL a cada 5 minutos
 */

// Habilitar exibição de erros para debug (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir erros na tela, apenas no log
ini_set('log_errors', 1);

// Carregar autoload do App (obrigatório)
require_once __DIR__ . '/../app/Helpers/autoload.php';

// Tentar carregar vendor/autoload.php (opcional - apenas se existir)
$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorPath)) {
    require_once $vendorPath;
}

use App\Services\AvailabilityService;

header('Content-Type: application/json');

try {
    $updated = AvailabilityService::checkAllAgents();
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'agents_checked' => count($updated),
        'agents_updated' => $updated
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (\Exception $e) {
    http_response_code(500);
    
    // Log do erro
    error_log("Erro em cron-availability.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

