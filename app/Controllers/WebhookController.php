<?php
/**
 * Controller WebhookController
 * Recebe webhooks de sistemas externos (WooCommerce, etc)
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Database;
use App\Models\WooCommerceIntegration;
use App\Models\WooCommerceOrderCache;
use App\Models\Contact;

class WebhookController
{
    /**
     * Log específico para webhooks
     */
    private static function log(string $message, string $level = 'INFO'): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/webhook.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
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
            
            // Processar webhook via WhatsAppService
            \App\Services\WhatsAppService::processWebhook($data);
            
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
        try {
            // Log da requisição
            $payload = file_get_contents('php://input');
            $data = json_decode($payload, true);
            
            self::log("=== WEBHOOK RECEBIDO ===");
            self::log("Payload size: " . strlen($payload) . " bytes | " . self::summarizePayload($payload, 600));
            
            // Caso seja apenas um ping do WooCommerce (webhook_id=XX) e não JSON
            if (stripos($payload, 'webhook_id=') === 0 && empty($data)) {
                self::log("PING recebido (webhook_id), ignorando e retornando sucesso.", 'INFO');
                Response::json([
                    'success' => true,
                    'message' => 'Ping recebido'
                ]);
                return;
            }
            
            if (empty($data)) {
                self::log("ERRO: Payload vazio ou inválido", 'ERROR');
                Response::json([
                    'success' => false,
                    'message' => 'Payload vazio'
                ], 400);
                return;
            }
            
            // Headers do WooCommerce
            $headersRaw = function_exists('getallheaders') ? getallheaders() : self::getRequestHeaders();
            $headers = self::normalizeHeaders($headersRaw);
            
            $event = $headers['x-wc-webhook-event'] ?? null;
            $source = $headers['x-wc-webhook-source'] ?? null;
            $orderId = $data['id'] ?? 'N/A';
            $topic = $headers['x-wc-webhook-topic'] ?? null;
            $contentType = $headers['content-type'] ?? '';
            
            self::log("Headers: event={$event} | topic={$topic} | source={$source} | content-type={$contentType}");
            
            self::log("Event: {$event} | Source: {$source} | Order ID: {$orderId}");
            
            // Validar evento
            if (!in_array(strtolower((string)$event), ['created', 'updated'])) {
                self::log("Evento ignorado: {$event} (não é created/updated)", 'WARNING');
                Response::json([
                    'success' => true,
                    'message' => 'Evento ignorado: ' . $event
                ]);
                return;
            }
            
            // Processar pedido
            $result = self::processWooCommerceOrder($data, $source);
            
            self::log("✅ Pedido #{$orderId} processado com sucesso: " . json_encode($result), 'SUCCESS');
            
            Response::json([
                'success' => true,
                'message' => 'Pedido processado com sucesso',
                'order_id' => $data['id'] ?? null,
                'details' => $result
            ]);
            
        } catch (\Exception $e) {
            self::log("❌ ERRO: " . $e->getMessage(), 'ERROR');
            self::log("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            
            Response::json([
                'success' => false,
                'message' => 'Erro ao processar webhook: ' . $e->getMessage()
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
        $ttlMinutes = $integration['cache_ttl_minutes'] ?? 60;
        
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
        }
        if (!$contact && $phone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $contact = Contact::findByPhone($cleanPhone);
        }
        
        if (!$contact) {
            // Criar contato
            $firstName = $orderData['billing']['first_name'] ?? '';
            $lastName = $orderData['billing']['last_name'] ?? '';
            $fullName = trim("{$firstName} {$lastName}");
            
            $contactData = [
                'name' => $fullName ?: 'Cliente WooCommerce',
                'email' => $email,
                'phone' => $phone,
                'source' => 'woocommerce'
            ];
            
            $contactId = Contact::create($contactData);
            self::log("✓ Novo contato criado: ID={$contactId}, Nome={$contactData['name']}");
        } else {
            $contactId = $contact['id'];
            self::log("✓ Contato existente: ID={$contactId}");
        }
        
        // 5. Cachear ou atualizar pedido
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
        
        $cacheData = [
            'woocommerce_integration_id' => $integrationId,
            'contact_id' => $contactId,
            'order_id' => $orderId,
            'order_data' => json_encode($orderData),
            'order_status' => $orderStatus,
            'order_total' => $orderTotal,
            'order_date' => $orderDate,
            'seller_id' => $sellerId,
            'expires_at' => $expiresAt
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
