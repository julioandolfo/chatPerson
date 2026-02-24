<?php
/**
 * Webhook Handler para WhatsApp (Quepasa API e Evolution API)
 * 
 * Este arquivo recebe eventos quando mensagens são recebidas
 * 
 * Configuração:
 * - Quepasa:   Webhook URL: https://seudominio.com/whatsapp-webhook
 * - Evolution: Webhook URL: https://seudominio.com/whatsapp-webhook
 * - Método: POST
 * - Content-Type: application/json
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar configurações
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

use App\Services\WhatsAppService;
use App\Services\EvolutionService;
use App\Helpers\Logger;

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Obter payload
$rawInput = file_get_contents('php://input');

Logger::quepasa("=== WEBHOOK WHATSAPP RECEBIDO (whatsapp-webhook.php) ===");
Logger::quepasa("Raw input length: " . strlen($rawInput) . " bytes");
Logger::quepasa("Raw input preview: " . substr($rawInput, 0, 500));

$payload = json_decode($rawInput, true);

if (!$payload) {
    Logger::error("WhatsApp webhook - JSON inválido ou vazio");
    Logger::error("Raw input: " . $rawInput);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

Logger::quepasa("Payload decodificado - Keys: " . implode(', ', array_keys($payload)));
Logger::quepasa("Payload completo: " . json_encode($payload, JSON_UNESCAPED_UNICODE));

try {
    // Detectar se é webhook da Evolution API
    // Evolution API envia: { "event": "messages.upsert", "instance": "nome", "data": { ... } }
    $isEvolution = isset($payload['event']) && isset($payload['instance']) && isset($payload['data']);
    
    if ($isEvolution) {
        Logger::info("Webhook Evolution API detectado - Event: {$payload['event']}, Instance: {$payload['instance']}");
        EvolutionService::processWebhook($payload);
    } else {
        Logger::quepasa("Chamando WhatsAppService::processWebhook (Quepasa)...");
        WhatsAppService::processWebhook($payload);
    }
    
    Logger::quepasa("Webhook processado com sucesso!");
    
    // Responder com sucesso
    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (\Exception $e) {
    Logger::error("WhatsApp Webhook Error: " . $e->getMessage());
    Logger::error("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

