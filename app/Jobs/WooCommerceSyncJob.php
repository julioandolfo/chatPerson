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
    private static string $logFile = '';

    private static function log(string $msg, string $level = 'INFO'): void
    {
        if (empty(self::$logFile)) {
            self::$logFile = dirname(__DIR__, 2) . '/logs/wc_sync.log';
        }
        $line = '[' . date('Y-m-d H:i:s') . "] [{$level}] {$msg}";
        echo $line . "\n";
        @file_put_contents(self::$logFile, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Executar sincronização
     */
    public static function run(): void
    {
        $startTime = microtime(true);
        self::log('Iniciando sincronização de pedidos WooCommerce (últimos 30 dias)...');
        
        try {
            // 1. Buscar todas as integrações ativas
            $integrations = WooCommerceIntegration::getAllActive();
            
            if (empty($integrations)) {
                self::log('Nenhuma integração ativa encontrada.', 'WARNING');
                return;
            }
            
            self::log('Encontradas ' . count($integrations) . ' integração(ões) ativa(s).');
            
            $totalOrders = 0;
            $totalErrors = 0;
            
            foreach ($integrations as $integration) {
                try {
                    self::log("Sincronizando integração #{$integration['id']}: {$integration['name']}...");
                    
                    $orders = self::syncIntegration($integration);
                    $totalOrders += $orders;
                    
                    self::log("✅ {$orders} pedidos sincronizados da integração #{$integration['id']}", 'SUCCESS');
                } catch (\Exception $e) {
                    $totalErrors++;
                    self::log("❌ Erro na integração #{$integration['id']}: {$e->getMessage()}", 'ERROR');
                }
            }
            
            // 2. Limpar cache expirado (pedidos com TTL >30 dias sem renovação)
            $expired = WooCommerceOrderCache::clearExpired();
            if ($expired > 0) {
                self::log("Limpeza: {$expired} pedido(s) removido(s) do cache (TTL expirado, >30 dias sem renovação).", 'WARNING');
            } else {
                self::log('Limpeza: nenhum pedido expirado no cache.');
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            self::log("✅ Sincronização concluída em {$duration}s — {$totalOrders} pedidos sincronizados, {$totalErrors} erros.", 'SUCCESS');
            
        } catch (\Exception $e) {
            self::log("❌ ERRO CRÍTICO: {$e->getMessage()}", 'ERROR');
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
        $sellerMetaKey = $integration['seller_meta_key'] ?? '_vendor_id';
        
        // TTL longo para cache histórico (30 dias) — independente do cache_ttl_minutes
        // que é para cache de curto prazo (busca em tempo real por contato).
        // Webhooks cuidam de atualizações em tempo real; o cron renova o histórico.
        $syncTtlMinutes = 30 * 24 * 60; // 43200 minutos = 30 dias
        
        // Buscar pedidos dos últimos 30 dias (alinhado com o TTL histórico)
        $dateFrom = date('Y-m-d', strtotime('-30 days')) . 'T00:00:00';
        
        // ═══ PAGINAÇÃO: WooCommerce API limita a 100 por página ═══
        $perPage = 100;
        $page = 1;
        $maxPages = 50; // Limite de segurança (5000 pedidos máx)
        $allOrders = [];
        
        self::log("  Integração #{$integration['id']}: buscando pedidos dos últimos 30 dias a partir de {$dateFrom} (per_page={$perPage})...");
        
        while ($page <= $maxPages) {
            $url = $wcUrl . '/wp-json/wc/v3/orders?' . http_build_query([
                'per_page' => $perPage,
                'page' => $page,
                'orderby' => 'date',
                'order' => 'desc',
                'after' => $dateFrom,
                'status' => 'any'
            ]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER => true
            ]);
            
            $fullResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                if ($page === 1) {
                    throw new \Exception("Erro ao buscar pedidos: HTTP {$httpCode}");
                }
                self::log("  Página {$page}: HTTP {$httpCode}, parando paginação.", 'WARNING');
                break;
            }
            
            // Separar headers e body
            $responseHeaders = substr($fullResponse, 0, $headerSize);
            $responseBody = substr($fullResponse, $headerSize);
            
            // Extrair total de páginas dos headers
            $totalPages = null;
            $totalAvailable = null;
            if (preg_match('/X-WP-TotalPages:\s*(\d+)/i', $responseHeaders, $m)) {
                $totalPages = (int)$m[1];
            }
            if (preg_match('/X-WP-Total:\s*(\d+)/i', $responseHeaders, $m)) {
                $totalAvailable = (int)$m[1];
            }
            
            if ($page === 1 && $totalAvailable !== null) {
                self::log("  Total disponível no WooCommerce (últimos 30 dias): {$totalAvailable} pedido(s) em {$totalPages} página(s).");
            }
            
            $orders = json_decode($responseBody, true);
            
            if (!is_array($orders) || empty($orders)) {
                break; // Sem mais resultados
            }
            
            $allOrders = array_merge($allOrders, $orders);
            self::log('  Página ' . $page . ': ' . count($orders) . ' pedidos (acumulado: ' . count($allOrders) . ')');
            
            // Se retornou menos que o per_page, não há mais páginas
            if (count($orders) < $perPage) {
                break;
            }
            
            // Se atingiu o total de páginas disponíveis
            if ($totalPages !== null && $page >= $totalPages) {
                break;
            }
            
            $page++;
            
            // Pequena pausa entre páginas para não sobrecarregar a API
            usleep(200000); // 200ms
        }
        
        if (empty($allOrders)) {
            return 0;
        }
        
        self::log('  Processando ' . count($allOrders) . ' pedidos...');
        
        $syncedCount = 0;
        
        foreach ($allOrders as $order) {
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
                    $contact = Contact::findByPhoneNormalized(self::cleanPhone($phone));
                }
                
                if (!$contact) {
                    // Criar contato se não existir
                    $normalizedPhone = $phone ? Contact::normalizePhoneNumber(self::cleanPhone($phone)) : null;
                    
                    $contactData = [
                        'name' => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
                        'email' => $email,
                        'phone' => $normalizedPhone,
                        'source' => 'woocommerce'
                    ];
                    
                    $contactId = Contact::create($contactData);
                } else {
                    $contactId = $contact['id'];
                }
                
                // Cachear pedido com TTL histórico (30 dias)
                $expiresAt = date('Y-m-d H:i:s', time() + ($syncTtlMinutes * 60));
                
                $cacheData = [
                    'woocommerce_integration_id' => $integration['id'],
                    'contact_id' => $contactId,
                    'order_id' => $orderId,
                    'order_data' => json_encode($order),
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
