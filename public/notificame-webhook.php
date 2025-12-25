<?php
/**
 * Webhook Notificame
 * Recebe eventos de todos os canais do Notificame
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Services\NotificameService;
use App\Helpers\Logger;

// Receber payload
$payload = json_decode(file_get_contents('php://input'), true);

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload vazio']);
    exit;
}

// Validar secret se configurado
$secret = $_GET['secret'] ?? null;
if ($secret) {
    // Validar secret (implementar validação conforme necessário)
    // Por enquanto, apenas logar
    Logger::info("Notificame webhook - Secret recebido: " . substr($secret, 0, 10) . "...");
}

// Identificar canal do payload
$channel = $payload['channel'] ?? $payload['message']['channel'] ?? 'whatsapp';

// Validar canal
if (!NotificameService::validateChannel($channel)) {
    http_response_code(400);
    echo json_encode(['error' => "Canal inválido: {$channel}"]);
    exit;
}

Logger::info("Notificame webhook recebido - Channel: {$channel}");

// Processar webhook
try {
    NotificameService::processWebhook($payload, $channel);
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (\Exception $e) {
    Logger::error("Erro ao processar webhook Notificame: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

