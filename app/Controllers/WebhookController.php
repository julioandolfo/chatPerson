<?php
/**
 * Controller WebhookController
 * Recebe webhooks de sistemas externos (WooCommerce, etc)
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Database;
use App\Services\EvolutionService;
use App\Models\WooCommerceIntegration;
use App\Models\WooCommerceOrderCache;
use App\Models\Contact;

class WebhookController
{
    /**
     * ID único da request (para rastrear todo o fluxo de uma única chamada)
     */
    private static ?string $requestId = null;
    
    /**
     * Gera ou retorna o REQUEST_ID único da request atual
     */
    private static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        }
        return self::$requestId;
    }
    
    /**
     * Log específico para webhooks (com REQUEST_ID para rastreabilidade)
     */
    private static function log(string $message, string $level = 'INFO'): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/webhook.log';
        $timestamp = date('Y-m-d H:i:s');
        $reqId = self::getRequestId();
        $logMessage = "[{$timestamp}] [{$level}] [RID:{$reqId}] {$message}\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        error_log($logMessage); // Também loga no error_log padrão
    }
    
    /**
     * Normaliza headers para lowercase (chaves)
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }
        return $normalized;
    }
    
    /**
     * Gera um pequeno resumo do payload para debug sem expor demais
     */
    private static function summarizePayload(string $payload, int $max = 1200): string
    {
        $len = strlen($payload);
        $snippet = $len > $max ? substr($payload, 0, $max) . '... [truncado]' : $payload;
        return "len={$len} bytes | preview=" . $snippet;
    }
    
    /**
     * Mascara email/telefone no log
     */
    private static function mask(string $value): string
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $value);
            $name = $parts[0] ?? '';
            $domain = $parts[1] ?? '';
            $maskedName = strlen($name) > 2 ? substr($name, 0, 2) . '***' : '***';
            return $maskedName . '@' . $domain;
        }
        // telefone: manter últimos 4
        $clean = preg_replace('/\D+/', '', $value);
        $last = substr($clean, -4);
        return '***' . $last;
    }
    
    /**
     * Webhook do WhatsApp (Quepasa)
     * Recebe mensagens e eventos do WhatsApp
     * 
     * URL: /whatsapp-webhook
     */
    public function whatsapp(): void
    {
        try {
            // Obter payload bruto
            $payload = file_get_contents('php://input');
            $data = json_decode($payload, true);
            
            \App\Helpers\Logger::quepasa("=== WEBHOOK WHATSAPP RECEBIDO ===");
            \App\Helpers\Logger::quepasa("Payload size: " . strlen($payload) . " bytes");
            \App\Helpers\Logger::quepasa("Data keys: " . (!empty($data) ? implode(', ', array_keys($data)) : 'vazio'));
            
            if (!$data) {
                \App\Helpers\Logger::error("WhatsApp webhook - JSON inválido");
                Response::json(['error' => 'Invalid JSON'], 400);
                return;
            }
            
            // Detectar se é webhook da Evolution API
            // Evolution envia: { "event": "CONNECTION_UPDATE", "instance": "nome", "data": { ... } }
            $isEvolution = isset($data['event']) && isset($data['instance']);
            
            if ($isEvolution) {
                if (!isset($data['data'])) {
                    $data['data'] = [];
                }
                \App\Helpers\Logger::evolution("[INFO] Webhook Evolution recebido via WebhookController - Event: {$data['event']}, Instance: {$data['instance']}");
                \App\Services\EvolutionService::processWebhook($data);
            } else {
                \App\Services\WhatsAppService::processWebhook($data);
            }
            
            // Responder com sucesso
            Response::json(['success' => true]);
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("WhatsApp webhook error: " . $e->getMessage());
            \App\Helpers\Logger::error("Stack trace: " . $e->getTraceAsString());
            
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Webhook do WooCommerce
     * Recebe eventos de criação e atualização de pedidos
     * 
     * URL: /webhooks/woocommerce
     */
    public function woocommerce(): void
    {
        // Reset request ID para cada nova chamada
        self::$requestId = null;
        
        // ══════════════════════════════════════════════════════════════
        // LOG IMEDIATO: Registrar que a request chegou ANTES de tudo
        // Se um webhook não aparece no log, o problema é antes do PHP
        // ══════════════════════════════════════════════════════════════
        $requestStartTime = microtime(true);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? null;
        $realIp = $forwardedFor ? "{$clientIp} (forwarded: {$forwardedFor})" : $clientIp;
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? $_SERVER['HTTP_CONTENT_LENGTH'] ?? 'not-set';
        $serverProtocol = $_SERVER['SERVER_PROTOCOL'] ?? 'unknown';
        
        self::log("══════════════════════════════════════════════════════════");
        self::log("🔔 REQUEST RECEBIDA | IP: {$realIp} | Method: {$requestMethod} | Protocol: {$serverProtocol} | Content-Length: {$contentLength} | UA: {$userAgent}");
        
        try {
            // Ler payload
            $payload = file_get_contents('php://input');
            $payloadSize = strlen($payload);
            $data = json_decode($payload, true);
            $jsonError = json_last_error();
            $jsonErrorMsg = json_last_error_msg();
            
            self::log("📦 Payload recebido: {$payloadSize} bytes | JSON decode: " . ($jsonError === JSON_ERROR_NONE ? 'OK' : "ERRO ({$jsonErrorMsg})"));
            
            if ($payloadSize > 0 && $payloadSize <= 2000) {
                self::log("📦 Payload completo: " . $payload);
            } elseif ($payloadSize > 2000) {
                self::log("📦 Payload preview (primeiros 1500 chars): " . substr($payload, 0, 1500) . "... [truncado, total: {$payloadSize} bytes]");
            } else {
                self::log("📦 Payload VAZIO (0 bytes)", 'WARNING');
            }
            
            // Log de TODOS os headers recebidos (essencial para debug)
            $headersRaw = function_exists('getallheaders') ? getallheaders() : self::getRequestHeaders();
            $headers = self::normalizeHeaders($headersRaw);
            
            $headersList = [];
            foreach ($headers as $k => $v) {
                $headersList[] = "{$k}: {$v}";
            }
            self::log("📋 Headers (" . count($headers) . "): " . implode(' | ', $headersList));
            
            // Headers específicos do WooCommerce
            $event = $headers['x-wc-webhook-event'] ?? null;
            $source = $headers['x-wc-webhook-source'] ?? null;
            $topic = $headers['x-wc-webhook-topic'] ?? null;
            $webhookId = $headers['x-wc-webhook-id'] ?? null;
            $deliveryId = $headers['x-wc-webhook-delivery-id'] ?? null;
            $signature = $headers['x-wc-webhook-signature'] ?? null;
            $contentType = $headers['content-type'] ?? '';
            
            self::log("🏷️ WooCommerce Headers: event={$event} | topic={$topic} | source={$source} | webhook_id={$webhookId} | delivery_id={$deliveryId} | signature=" . ($signature ? substr($signature, 0, 20) . '...' : 'null') . " | content-type={$contentType}");
            
            // ── Caso PING do WooCommerce ──
            if (stripos($payload, 'webhook_id=') === 0 && empty($data)) {
                self::log("🏓 PING recebido (webhook_id form-data), retornando sucesso.", 'INFO');
                $elapsed = round((microtime(true) - $requestStartTime) * 1000, 2);
                self::log("⏱️ Request finalizada em {$elapsed}ms (PING)");
                Response::json([
                    'success' => true,
                    'message' => 'Ping recebido'
                ]);
                return;
            }
            
            // ── Payload vazio ou JSON inválido ──
            if (empty($data)) {
                $reason = $payloadSize === 0 ? 'body vazio (0 bytes)' : "JSON inválido (erro: {$jsonErrorMsg})";
                self::log("❌ REJEITADO: Payload inválido - {$reason}", 'ERROR');
                self::log("❌ Raw payload (até 500 chars): " . substr($payload, 0, 500), 'ERROR');
                $elapsed = round((microtime(true) - $requestStartTime) * 1000, 2);
                self::log("⏱️ Request finalizada em {$elapsed}ms (REJEITADA - payload inválido)");
                Response::json([
                    'success' => false,
                    'message' => 'Payload vazio ou inválido'
                ], 400);
                return;
            }
            
            $orderId = $data['id'] ?? 'N/A';
            $orderNumber = $data['number'] ?? $orderId;
            $orderStatus = $data['status'] ?? 'N/A';
            
            self::log("📝 Dados do pedido: ID={$orderId} | Number={$orderNumber} | Status={$orderStatus} | Event={$event}");
            
            // ── Validar evento ──
            if (!in_array(strtolower((string)$event), ['created', 'updated'])) {
                self::log("⏭️ Evento ignorado: '{$event}' (aceitos: created, updated) | topic={$topic} | Order #{$orderId}", 'WARNING');
                $elapsed = round((microtime(true) - $requestStartTime) * 1000, 2);
                self::log("⏱️ Request finalizada em {$elapsed}ms (EVENTO IGNORADO)");
                Response::json([
                    'success' => true,
                    'message' => 'Evento ignorado: ' . $event
                ]);
                return;
            }
            
            // ── Processar pedido ──
            self::log("🔄 Iniciando processamento do pedido #{$orderId} (evento: {$event})...");
            $result = self::processWooCommerceOrder($data, $source);
            
            $elapsed = round((microtime(true) - $requestStartTime) * 1000, 2);
            self::log("✅ Pedido #{$orderId} processado com SUCESSO em {$elapsed}ms: " . json_encode($result), 'SUCCESS');
            self::log("══════════════════════════════════════════════════════════");
            
            Response::json([
                'success' => true,
                'message' => 'Pedido processado com sucesso',
                'order_id' => $data['id'] ?? null,
                'details' => $result
            ]);
            
        } catch (\Exception $e) {
            $elapsed = round((microtime(true) - $requestStartTime) * 1000, 2);
            self::log("❌ ERRO EXCEPTION: " . $e->getMessage(), 'ERROR');
            self::log("❌ Exception class: " . get_class($e) . " | File: " . $e->getFile() . ":" . $e->getLine(), 'ERROR');
            self::log("❌ Stack trace: " . $e->getTraceAsString(), 'ERROR');
            self::log("⏱️ Request finalizada em {$elapsed}ms (ERRO)", 'ERROR');
            self::log("══════════════════════════════════════════════════════════");
            
            Response::json([
                'success' => false,
                'message' => 'Erro ao processar webhook: ' . $e->getMessage()
            ], 500);
        } catch (\Throwable $t) {
            // Capturar até erros fatais do PHP
            $elapsed = round((microtime(true) - $requestStartTime) * 1000, 2);
            self::log("💀 ERRO FATAL (Throwable): " . $t->getMessage(), 'ERROR');
            self::log("💀 Throwable class: " . get_class($t) . " | File: " . $t->getFile() . ":" . $t->getLine(), 'ERROR');
            self::log("💀 Stack trace: " . $t->getTraceAsString(), 'ERROR');
            self::log("⏱️ Request finalizada em {$elapsed}ms (ERRO FATAL)", 'ERROR');
            self::log("══════════════════════════════════════════════════════════");
            
            Response::json([
                'success' => false,
                'message' => 'Erro fatal: ' . $t->getMessage()
            ], 500);
        }
    }
    
    /**
     * Processar pedido do WooCommerce
     */
    private static function processWooCommerceOrder(array $orderData, ?string $source): array
    {
        $orderId = $orderData['id'] ?? 'N/A';
        
        // 1. Identificar integração pelo source (URL da loja)
        $integration = null;
        if ($source) {
            self::log("Buscando integração para source: {$source}");
            $integrations = WooCommerceIntegration::getActive();
            foreach ($integrations as $int) {
                if (strpos($source, parse_url($int['woocommerce_url'], PHP_URL_HOST)) !== false) {
                    $integration = $int;
                    self::log("✓ Integração encontrada: #{$int['id']} - {$int['name']}");
                    break;
                }
            }
        }
        
        // Se não encontrou pelo source, pegar a primeira integração ativa
        if (!$integration) {
            self::log("Buscando primeira integração ativa...");
            $integrations = WooCommerceIntegration::getActive();
            $integration = $integrations[0] ?? null;
            if ($integration) {
                self::log("✓ Usando integração padrão: #{$integration['id']} - {$integration['name']}");
            }
        }
        
        if (!$integration) {
            self::log("❌ Nenhuma integração WooCommerce ativa encontrada", 'ERROR');
            throw new \Exception('Nenhuma integração WooCommerce ativa encontrada');
        }
        
        $integrationId = $integration['id'];
        $sellerMetaKey = $integration['seller_meta_key'] ?? '_vendor_id';
        // Pedidos recebidos por webhook são permanentes (expires_at = NULL)
        
        // 2. Extrair dados do pedido
        $orderStatus = $orderData['status'];
        $orderTotal = $orderData['total'];
        $orderDate = $orderData['date_created'] ?? date('Y-m-d H:i:s');
        
        self::log("Pedido #{$orderId}: Status={$orderStatus}, Total={$orderTotal}, Data={$orderDate}");
        
        // 3. Extrair seller_id do meta_data
        $sellerId = null;
        if (!empty($orderData['meta_data'])) {
            foreach ($orderData['meta_data'] as $meta) {
                if ($meta['key'] === $sellerMetaKey) {
                    $sellerId = (int)$meta['value'];
                    self::log("✓ Seller ID encontrado: {$sellerId} (meta_key: {$sellerMetaKey})");
                    break;
                }
            }
        }
        if (!$sellerId) {
            self::log("⚠️ Seller ID não encontrado no meta_data (procurado: {$sellerMetaKey})", 'WARNING');
        }
        
        // 4. Buscar ou criar contato
        $email = $orderData['billing']['email'] ?? null;
        $phone = $orderData['billing']['phone'] ?? null;
        
        $maskEmail = $email ? self::mask($email) : 'null';
        $maskPhone = $phone ? self::mask($phone) : 'null';
        self::log("Buscando contato: email={$maskEmail}, phone={$maskPhone}");
        
        $contact = null;
        if ($email) {
            $contact = Contact::findByEmail($email);
            if ($contact) {
                self::log("✓ Contato encontrado por email: ID={$contact['id']}");
            }
        }
        if (!$contact && $phone) {
            // ✅ CORRIGIDO: Usar findByPhoneNormalized para busca robusta (considera variantes)
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $contact = Contact::findByPhoneNormalized($cleanPhone);
            if ($contact) {
                self::log("✓ Contato encontrado por telefone: ID={$contact['id']}, Phone={$contact['phone']}");
            }
        }
        
        if (!$contact) {
            // Criar contato
            $firstName = $orderData['billing']['first_name'] ?? '';
            $lastName = $orderData['billing']['last_name'] ?? '';
            $fullName = trim("{$firstName} {$lastName}");
            
            // ✅ Normalizar telefone antes de salvar
            $normalizedPhone = $phone ? Contact::normalizePhoneNumber(preg_replace('/[^0-9]/', '', $phone)) : null;
            
            $contactData = [
                'name' => $fullName ?: 'Cliente WooCommerce',
                'email' => $email,
                'phone' => $normalizedPhone,
                'source' => 'woocommerce'
            ];
            
            $contactId = Contact::create($contactData);
            self::log("✓ Novo contato criado: ID={$contactId}, Nome={$contactData['name']}, Phone={$normalizedPhone}");
        } else {
            $contactId = $contact['id'];
            self::log("✓ Contato existente: ID={$contactId}, Nome={$contact['name']}");
        }
        
        // 5. Cachear ou atualizar pedido — permanente (expires_at = NULL)
        $cacheData = [
            'woocommerce_integration_id' => $integrationId,
            'contact_id' => $contactId,
            'order_id' => $orderId,
            'order_data' => json_encode($orderData),
            'order_status' => $orderStatus,
            'order_total' => $orderTotal,
            'order_date' => $orderDate,
            'seller_id' => $sellerId,
            'expires_at' => null
        ];
        
        // Verificar se já existe
        $existing = Database::fetch(
            "SELECT id FROM woocommerce_order_cache 
             WHERE woocommerce_integration_id = ? 
             AND order_id = ?",
            [$integrationId, $orderId]
        );
        
        if ($existing) {
            WooCommerceOrderCache::update($existing['id'], $cacheData);
            $action = 'updated';
            self::log("✓ Pedido atualizado no cache (cache_id: {$existing['id']})");
        } else {
            $cacheId = WooCommerceOrderCache::create($cacheData);
            $action = 'created';
            self::log("✓ Pedido criado no cache (cache_id: {$cacheId})");
        }
        
        return [
            'action' => $action,
            'integration_id' => $integrationId,
            'contact_id' => $contactId,
            'order_id' => $orderId,
            'seller_id' => $sellerId,
            'status' => $orderStatus
        ];
    }
    
    /**
     * Obter headers da requisição (fallback se getallheaders não existir)
     */
    private static function getRequestHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
}
