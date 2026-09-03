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

// ══════════════════════════════════════════════════════════════════════════
// RESPONDER ANTES DE PROCESSAR
//
// O Quepasa aborta o webhook em 10s e NÃO tenta de novo — quando estoura, a
// mensagem é perdida em definitivo. Do log do Quepasa em 2026-09-03:
//
//   level=warning msg="webhook timeout after 10s"
//   level=error   msg="webhook failed with status 0: context deadline exceeded"
//   level=error   msg="error on dispatch: ..."      msgid=2A4A224CE289A69C4BFB
//
// E as mensagens RECEBIDAS estavam consumindo 4 a 5 segundos de forma
// consistente (as de eco, que não fazem esse trabalho todo, levam 25-70ms).
// Ou seja: metade do orçamento já era gasta no caminho normal, e qualquer
// lentidão adicional — download de mídia, resolução de @lid, avatar, banco
// mais carregado — jogava a request para além dos 10s.
//
// Devolvendo 200 imediatamente e processando depois, o tempo de resposta cai
// para dezenas de milissegundos e o timeout deixa de ser alcançável. Como o
// Quepasa não repete a entrega de qualquer forma, não há nada a perder: o
// desfecho real fica registrado em whatsapp_webhook_audit.
// ══════════════════════════════════════════════════════════════════════════
ignore_user_abort(true);

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['success' => true]);

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    // Sem PHP-FPM: fecha o buffer para liberar o cliente o quanto antes
    if (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
}

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
} catch (\Throwable $e) {
    // ⚠️ Throwable (não só Exception): TypeError/Error de PHP 8 são \Error e
    // passavam batido aqui, matando a request sem log e sem gravar a mensagem.
    Logger::error("WhatsApp Webhook Error (rid={$rid}): " . get_class($e) . ' - ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    Logger::error("Stack trace: " . $e->getTraceAsString());

    // A resposta 200 já foi enviada ao Quepasa — o que importa agora é deixar o
    // erro registrado. Devolver 500 não adiantaria nada: o Quepasa não repete.
    WebhookAuditService::error(get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
}

