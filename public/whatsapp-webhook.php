<?php
/**
 * Webhook Handler para WhatsApp (Quepasa API)
 * 
 * Este arquivo recebe eventos do Quepasa API quando mensagens são recebidas
 * 
 * Configuração no Quepasa:
 * - Webhook URL: https://seudominio.com/whatsapp-webhook.php
 * - Método: POST
 * - Content-Type: application/json
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar configurações
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

use App\Services\WhatsAppService;
use App\Helpers\Logger;

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Obter payload
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

Logger::log("WhatsApp Webhook recebido: " . json_encode($payload));

try {
    // Processar webhook
    WhatsAppService::processWebhook($payload);
    
    // Responder com sucesso
    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    Logger::error("WhatsApp Webhook Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

