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
            
            error_log("WooCommerce Webhook - Recebido: " . $payload);
            
            if (empty($data)) {
                Response::json([
                    'success' => false,
                    'message' => 'Payload vazio'
                ], 400);
                return;
            }
            
            // Headers do WooCommerce
            $headers = function_exists('getallheaders') ? getallheaders() : self::getRequestHeaders();
            $event = $headers['X-WC-Webhook-Event'] ?? $headers['x-wc-webhook-event'] ?? null;
            $source = $headers['X-WC-Webhook-Source'] ?? $headers['x-wc-webhook-source'] ?? null;
            
            error_log("WooCommerce Webhook - Event: {$event}, Source: {$source}");
            
            // Validar evento
            if (!in_array($event, ['created', 'updated'])) {
                Response::json([
                    'success' => true,
                    'message' => 'Evento ignorado: ' . $event
                ]);
                return;
            }
            
            // Processar pedido
            $result = self::processWooCommerceOrder($data, $source);
            
            Response::json([
                'success' => true,
                'message' => 'Pedido processado com sucesso',
                'order_id' => $data['id'] ?? null,
                'details' => $result
            ]);
            
        } catch (\Exception $e) {
            error_log("WooCommerce Webhook - Erro: " . $e->getMessage());
            
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
        // 1. Identificar integração pelo source (URL da loja)
        $integration = null;
        if ($source) {
            $integrations = WooCommerceIntegration::getActive();
            foreach ($integrations as $int) {
                if (strpos($source, parse_url($int['woocommerce_url'], PHP_URL_HOST)) !== false) {
                    $integration = $int;
                    break;
                }
            }
        }
        
        // Se não encontrou pelo source, pegar a primeira integração ativa
        if (!$integration) {
            $integrations = WooCommerceIntegration::getActive();
            $integration = $integrations[0] ?? null;
        }
        
        if (!$integration) {
            throw new \Exception('Nenhuma integração WooCommerce ativa encontrada');
        }
        
        $integrationId = $integration['id'];
        $sellerMetaKey = $integration['seller_meta_key'] ?? '_vendor_id';
        $ttlMinutes = $integration['cache_ttl_minutes'] ?? 60;
        
        // 2. Extrair dados do pedido
        $orderId = $orderData['id'];
        $orderStatus = $orderData['status'];
        $orderTotal = $orderData['total'];
        $orderDate = $orderData['date_created'] ?? date('Y-m-d H:i:s');
        
        // 3. Extrair seller_id do meta_data
        $sellerId = null;
        if (!empty($orderData['meta_data'])) {
            foreach ($orderData['meta_data'] as $meta) {
                if ($meta['key'] === $sellerMetaKey) {
                    $sellerId = (int)$meta['value'];
                    break;
                }
            }
        }
        
        // 4. Buscar ou criar contato
        $email = $orderData['billing']['email'] ?? null;
        $phone = $orderData['billing']['phone'] ?? null;
        
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
        } else {
            $contactId = $contact['id'];
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
        } else {
            WooCommerceOrderCache::create($cacheData);
            $action = 'created';
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
