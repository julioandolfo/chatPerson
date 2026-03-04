<?php
/**
 * Webhook Notificame
 * Recebe eventos de todos os canais do Notificame
 */

// Carregar autoload e configs (seguindo padrão whatsapp-webhook)
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

use App\Services\NotificameService;
use App\Helpers\Logger;

// ── Log imediato de todas as requisições recebidas ──
$requestMethod  = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$requestUri     = $_SERVER['REQUEST_URI'] ?? '';
$remoteAddr     = $_SERVER['REMOTE_ADDR'] ?? '';
$contentType    = $_SERVER['CONTENT_TYPE'] ?? '';
$rawBody        = file_get_contents('php://input');

Logger::notificame("========== Notificame Webhook REQUEST RECEBIDO ==========");
Logger::notificame("Método: {$requestMethod} | URI: {$requestUri} | IP: {$remoteAddr}");
Logger::notificame("Content-Type: {$contentType}");
Logger::notificame("Body raw (" . strlen($rawBody) . " bytes): " . substr($rawBody, 0, 2000));

// Decodificar payload
$payload = json_decode($rawBody, true);

if (empty($payload)) {
    $jsonError = json_last_error_msg();
    Logger::notificame("ERRO: Payload vazio ou JSON inválido. json_error={$jsonError}");
    Logger::notificame("========== Notificame Webhook FIM (Payload vazio) ==========");
    http_response_code(400);
    echo json_encode(['error' => 'Payload vazio ou JSON inválido', 'json_error' => $jsonError]);
    exit;
}

Logger::notificame("Payload decodificado OK. Chaves de topo: " . implode(', ', array_keys($payload)));

// Validar secret se configurado
$secret = $_GET['secret'] ?? null;
if ($secret) {
    Logger::notificame("Secret recebido: " . substr($secret, 0, 10) . "...");
}

// Identificar canal do payload (tentar múltiplas localizações)
$channel = $payload['channel']
    ?? $payload['message']['channel']
    ?? $payload['type']
    ?? null;

if (!$channel) {
    Logger::notificame("AVISO: Campo 'channel' não encontrado no payload. Usando 'whatsapp' como fallback.");
    Logger::notificame("Payload completo para análise: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $channel = 'whatsapp';
}

Logger::notificame("Canal identificado: {$channel}");

// Validar canal
if (!NotificameService::validateChannel($channel)) {
    Logger::notificame("ERRO: Canal inválido: {$channel}. Canais aceitos: " . implode(', ', NotificameService::CHANNELS));
    Logger::notificame("========== Notificame Webhook FIM (Canal inválido) ==========");
    http_response_code(400);
    echo json_encode(['error' => "Canal inválido: {$channel}"]);
    exit;
}

Logger::notificame("Canal '{$channel}' validado. Iniciando processamento...");

// Processar webhook
try {
    NotificameService::processWebhook($payload, $channel);
    Logger::notificame("========== Notificame Webhook FIM (Sucesso HTTP) ==========");
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (\Exception $e) {
    Logger::notificame("ERRO FATAL ao processar webhook: " . $e->getMessage());
    Logger::notificame("Trace: " . $e->getTraceAsString());
    Logger::notificame("========== Notificame Webhook FIM (Erro fatal) ==========");
    Logger::error("Erro ao processar webhook Notificame: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

