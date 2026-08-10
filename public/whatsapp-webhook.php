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
use App\Services\WebhookAuditService;
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

// Auditoria: registrar a chegada ANTES de qualquer validação, e capturar fatais.
// Sem isso, um webhook descartado (ou uma request morta por max_execution_time)
// não deixa nenhum rastro consultável.
WebhookAuditService::start($rawInput, is_array($payload) ? $payload : null, 'whatsapp-webhook.php');
WebhookAuditService::registerFatalHandler();

$rid = WebhookAuditService::requestId();

Logger::quepasa("=== WEBHOOK WHATSAPP RECEBIDO (whatsapp-webhook.php) rid={$rid} ===");
Logger::quepasa("[rid={$rid}] Raw input length: " . strlen($rawInput) . " bytes");
Logger::quepasa("[rid={$rid}] Raw input preview: " . substr($rawInput, 0, 500));

if (!$payload) {
    Logger::error("WhatsApp webhook - JSON inválido ou vazio (rid={$rid})");
    Logger::error("Raw input: " . $rawInput);
    WebhookAuditService::drop('json_invalido', 'Body vazio ou JSON inválido (' . strlen($rawInput) . ' bytes)');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

Logger::quepasa("[rid={$rid}] Payload decodificado - Keys: " . implode(', ', array_keys($payload)));
Logger::quepasa("[rid={$rid}] Payload completo: " . json_encode($payload, JSON_UNESCAPED_UNICODE));

try {
    // Detectar se é webhook da Evolution API
    // Evolution API envia: { "event": "CONNECTION_UPDATE", "instance": "nome", "data": { ... } }
    // Nota: nem todos os eventos têm 'data', mas 'event' e 'instance' são sempre presentes
    $isEvolution = isset($payload['event']) && isset($payload['instance']);
    
    if ($isEvolution) {
        // Garantir que data exista como array (pode estar ausente em alguns eventos)
        if (!isset($payload['data'])) {
            $payload['data'] = [];
        }
        Logger::evolution("[INFO] Webhook Evolution API detectado - Event: {$payload['event']}, Instance: {$payload['instance']}");
        EvolutionService::processWebhook($payload);
    } else {
        Logger::quepasa("Chamando WhatsAppService::processWebhook (Quepasa)...");
        WhatsAppService::processWebhook($payload);
    }
    
    Logger::quepasa("[rid={$rid}] Webhook processado com sucesso!");

    // Finaliza a auditoria caso o handler não tenha registrado desfecho próprio
    // (eco outgoing, Evolution, eventos de status). No-op se já finalizada.
    WebhookAuditService::processed();

    // Responder com sucesso
    http_response_code(200);
    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    // ⚠️ Throwable (não só Exception): TypeError/Error de PHP 8 são \Error e
    // passavam batido aqui, matando a request sem log e sem gravar a mensagem.
    Logger::error("WhatsApp Webhook Error (rid={$rid}): " . get_class($e) . ' - ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    Logger::error("Stack trace: " . $e->getTraceAsString());

    WebhookAuditService::error(get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

