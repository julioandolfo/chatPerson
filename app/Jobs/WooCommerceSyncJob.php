<?php
/**
 * Job: Sincronização de Pedidos WooCommerce
 * Sincroniza pedidos recentes do WooCommerce para o cache local
 * Melhora performance e mantém dados atualizados para métricas de conversão
 */

namespace App\Jobs;

use App\Models\WooCommerceIntegration;
use App\Models\WooCommerceOrderCache;
use App\Models\Contact;
use App\Helpers\Database;

class WooCommerceSyncJob
{
    /**
     * Executar sincronização
     */
    public static function run(): void
    {
        $startTime = microtime(true);
        echo "[WooCommerceSync] Iniciando sincronização de pedidos WooCommerce...\n";
        
        try {
            // 1. Buscar todas as integrações ativas
            $integrations = WooCommerceIntegration::getAllActive();
            
            if (empty($integrations)) {
                echo "[WooCommerceSync] Nenhuma integração ativa encontrada.\n";
                return;
            }
            
            echo "[WooCommerceSync] Encontradas " . count($integrations) . " integração(ões) ativa(s).\n";
            
            $totalOrders = 0;
            $totalErrors = 0;
            
            foreach ($integrations as $integration) {
                try {
                    echo "[WooCommerceSync] Sincronizando integração #{$integration['id']}: {$integration['name']}...\n";
                    
                    $orders = self::syncIntegration($integration);
                    $totalOrders += $orders;
                    
                    echo "[WooCommerceSync] ✅ {$orders} pedidos sincronizados da integração #{$integration['id']}\n";
                } catch (\Exception $e) {
                    $totalErrors++;
                    echo "[WooCommerceSync] ❌ Erro na integração #{$integration['id']}: {$e->getMessage()}\n";
                    error_log("WooCommerceSync - Erro integração {$integration['id']}: " . $e->getMessage());
                }
            }
            
            // 2. Limpar cache expirado
            $expired = WooCommerceOrderCache::clearExpired();
            echo "[WooCommerceSync] Limpeza: {$expired} pedidos expirados removidos do cache.\n";
            
            $duration = round(microtime(true) - $startTime, 2);
            echo "[WooCommerceSync] ✅ Sincronização concluída em {$duration}s - {$totalOrders} pedidos sincronizados, {$totalErrors} erros.\n";
            
        } catch (\Exception $e) {
            echo "[WooCommerceSync] ❌ ERRO CRÍTICO: {$e->getMessage()}\n";
            error_log("WooCommerceSync - Erro crítico: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Sincronizar pedidos de uma integração específica
     */
    private static function syncIntegration(array $integration): int
    {
        $wcUrl = rtrim($integration['woocommerce_url'], '/');
        $consumerKey = $integration['consumer_key'];
        $consumerSecret = $integration['consumer_secret'];
        $ttlMinutes = $integration['cache_ttl_minutes'] ?? 60; // Padrão: 1 hora
        $sellerMetaKey = $integration['seller_meta_key'] ?? '_vendor_id';
        
        // Buscar pedidos recentes (últimos 7 dias, máximo 100)
        $dateFrom = date('Y-m-d', strtotime('-7 days')) . 'T00:00:00';
        $url = $wcUrl . '/wp-json/wc/v3/orders?' . http_build_query([
            'per_page' => 100,
            'orderby' => 'date',
            'order' => 'desc',
            'after' => $dateFrom,
            'status' => 'any' // Todos os status
        ]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Erro ao buscar pedidos: HTTP {$httpCode}");
        }
        
        $orders = json_decode($response, true);
        
        if (empty($orders)) {
            return 0;
        }
        
        $syncedCount = 0;
        
        foreach ($orders as $order) {
            try {
                // Extrair dados do pedido
                $orderId = $order['id'];
                $orderStatus = $order['status'];
                $orderTotal = $order['total'];
                $orderDate = $order['date_created'];
                
                // Extrair seller_id do meta_data
                $sellerId = null;
                if (!empty($order['meta_data'])) {
                    foreach ($order['meta_data'] as $meta) {
                        if ($meta['key'] === $sellerMetaKey) {
                            $sellerId = (int)$meta['value'];
                            break;
                        }
                    }
                }
                
                // Buscar contato pelo email ou telefone
                $email = $order['billing']['email'] ?? null;
                $phone = $order['billing']['phone'] ?? null;
                
                $contact = null;
                if ($email) {
                    $contact = Contact::findByEmail($email);
                }
                if (!$contact && $phone) {
                    $contact = Contact::findByPhone(self::cleanPhone($phone));
                }
                
                if (!$contact) {
                    // Criar contato se não existir
                    $contactData = [
                        'name' => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
                        'email' => $email,
                        'phone' => $phone,
                        'source' => 'woocommerce'
                    ];
                    
                    $contactId = Contact::create($contactData);
                } else {
                    $contactId = $contact['id'];
                }
                
                // Cachear pedido
                $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
                
                $cacheData = [
                    'woocommerce_integration_id' => $integration['id'],
                    'contact_id' => $contactId,
                    'order_id' => $orderId,
                    'order_data' => json_encode($order),
                    'order_status' => $orderStatus,
                    'order_total' => $orderTotal,
                    'order_date' => $orderDate,
                    'seller_id' => $sellerId, // IMPORTANTE: Seller ID
                    'expires_at' => $expiresAt
                ];
                
                // Verificar se já existe
                $existing = Database::fetch(
                    "SELECT id FROM woocommerce_order_cache 
                     WHERE woocommerce_integration_id = ? 
                     AND order_id = ?",
                    [$integration['id'], $orderId]
                );
                
                if ($existing) {
                    WooCommerceOrderCache::update($existing['id'], $cacheData);
                } else {
                    WooCommerceOrderCache::create($cacheData);
                }
                
                $syncedCount++;
            } catch (\Exception $e) {
                error_log("WooCommerceSync - Erro ao processar pedido #{$order['id']}: " . $e->getMessage());
            }
        }
        
        return $syncedCount;
    }
    
    /**
     * Limpar número de telefone
     */
    private static function cleanPhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
