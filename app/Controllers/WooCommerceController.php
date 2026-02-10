<?php
/**
 * Controller WooCommerceController
 * Integração nativa com WooCommerce
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\WooCommerceIntegrationService;
use App\Models\WooCommerceIntegration;

class WooCommerceController
{
    /**
     * Listar integrações WooCommerce
     */
    public function index(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        try {
            $integrations = WooCommerceIntegration::all();
            
            // Se for requisição AJAX, retornar JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                Response::json([
                    'success' => true,
                    'integrations' => $integrations
                ]);
                return;
            }
            
            Response::view('integrations/woocommerce/index', [
                'integrations' => $integrations
            ]);
        } catch (\Exception $e) {
            // Se for requisição AJAX, retornar erro em JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'integrations' => []
                ], 500);
                return;
            }
            
            Response::view('integrations/woocommerce/index', [
                'integrations' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Criar nova integração
     */
    public function store(): void
    {
        Permission::abortIfCannot('integrations.create');
        
        try {
            $data = Request::post();
            
            // Preparar mapeamento de campos padrão se não fornecido
            if (empty($data['contact_field_mapping'])) {
                $data['contact_field_mapping'] = [
                    'phone' => [
                        'enabled' => true,
                        'woocommerce_field' => 'billing.phone',
                        'normalization_rules' => ['remove_ninth_digit', 'add_country_code', 'remove_special_chars']
                    ],
                    'email' => [
                        'enabled' => true,
                        'woocommerce_field' => 'billing.email'
                    ],
                    'name' => [
                        'enabled' => true,
                        'woocommerce_field' => 'billing.first_name',
                        'fallback_field' => 'billing.last_name'
                    ]
                ];
            }
            
            // Preparar configurações de busca padrão se não fornecido
            if (empty($data['search_settings'])) {
                $data['search_settings'] = [
                    'phone_variations' => true,
                    'email_exact_match' => true,
                    'name_fuzzy_match' => false,
                    'max_results' => 50,
                    'cache_duration_minutes' => 5
                ];
            }
            
            $integrationId = WooCommerceIntegrationService::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'Integração WooCommerce criada com sucesso!',
                'id' => $integrationId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar integração: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar integração
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('integrations.edit');
        
        try {
            $data = Request::post();
            
            if (WooCommerceIntegrationService::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Integração atualizada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar integração'
                ], 404);
            }
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar integração: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar integração
     */
    public function delete(int $id): void
    {
        Permission::abortIfCannot('integrations.delete');
        
        try {
            if (WooCommerceIntegration::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Integração deletada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar integração'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Testar conexão com WooCommerce
     */
    public function testConnection(int $id): void
    {
        Permission::abortIfCannot('integrations.view');
        
        try {
            $result = WooCommerceIntegrationService::testConnection($id);
            
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Buscar pedidos de um contato (API)
     */
    public function getContactOrders(int $contactId): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $integrationId = Request::get('integration_id');
            $orders = WooCommerceIntegrationService::getContactOrders(
                $contactId,
                $integrationId ? (int)$integrationId : null
            );
            
            Response::json([
                'success' => true,
                'orders' => $orders,
                'count' => count($orders)
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'orders' => []
            ], 500);
        }
    }
    
    /**
     * Testar meta_key do vendedor (API)
     */
    public function testSellerMetaKey(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        try {
            $integrationId = Request::post('integration_id');
            $metaKey = Request::post('meta_key');
            
            if (!$integrationId || !$metaKey) {
                Response::json([
                    'success' => false,
                    'message' => 'Parâmetros inválidos'
                ], 400);
                return;
            }
            
            // Buscar um vendedor de teste (primeiro usuário com woocommerce_seller_id)
            $testSeller = \App\Helpers\Database::fetch(
                "SELECT woocommerce_seller_id FROM users 
                 WHERE woocommerce_seller_id IS NOT NULL 
                 LIMIT 1"
            );
            
            $testSellerId = $testSeller['woocommerce_seller_id'] ?? 1;
            
            $result = \App\Services\WooCommerceIntegrationService::testSellerMetaKey(
                (int)$integrationId,
                $metaKey,
                $testSellerId
            );
            
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao testar meta key: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sincronizar pedidos WooCommerce manualmente
     */
    public function syncOrders(): void
    {
        Permission::abortIfCannot('conversion.view');
        
        try {
            $data = Request::json();
            $ordersLimit = (int)($data['orders_limit'] ?? 100);
            $daysBack = (int)($data['days_back'] ?? 7);
            
            // Validações (limite aumentado para suportar paginação completa)
            if ($ordersLimit < 1 || $ordersLimit > 5000) {
                Response::json([
                    'success' => false,
                    'message' => 'Limite de pedidos deve ser entre 1 e 5000'
                ], 400);
                return;
            }
            
            if ($daysBack < 1 || $daysBack > 365) {
                Response::json([
                    'success' => false,
                    'message' => 'Período deve ser entre 1 e 365 dias'
                ], 400);
                return;
            }
            
            // Obter todas as integrações ativas
            $integrations = WooCommerceIntegration::getActive();
            
            if (empty($integrations)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nenhuma integração WooCommerce ativa encontrada'
                ], 404);
                return;
            }
            
            $integrationsProcessed = 0;
            $ordersProcessed = 0;
            $newContacts = 0;
            $errors = [];
            $pagesInfo = [];
            
            foreach ($integrations as $integration) {
                try {
                    $wcUrl = $integration['woocommerce_url'];
                    $consumerKey = $integration['consumer_key'];
                    $consumerSecret = $integration['consumer_secret'];
                    $sellerMetaKey = $integration['seller_meta_key'] ?? '_vendor_id';
                    $cacheTtlMinutes = $integration['cache_ttl_minutes'] ?? 60;
                    
                    $dateMin = date('Y-m-d', strtotime("-{$daysBack} days"));
                    
                    // ═══ PAGINAÇÃO: WooCommerce API limita a 100 por página ═══
                    $perPage = 100; // Máximo do WooCommerce
                    $page = 1;
                    $totalFetched = 0;
                    $totalPages = null;
                    $totalAvailable = null;
                    $integrationOrders = [];
                    
                    while ($totalFetched < $ordersLimit) {
                        $url = rtrim($wcUrl, '/') . '/wp-json/wc/v3/orders?' . http_build_query([
                            'per_page' => min($perPage, $ordersLimit - $totalFetched),
                            'page' => $page,
                            'orderby' => 'date',
                            'order' => 'desc',
                            'after' => $dateMin . 'T00:00:00'
                        ]);
                        
                        $ch = curl_init($url);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
                            CURLOPT_TIMEOUT => 60,
                            CURLOPT_CONNECTTIMEOUT => 15,
                            CURLOPT_HEADER => true // Precisamos dos headers para paginação
                        ]);
                        
                        $fullResponse = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                        curl_close($ch);
                        
                        if ($httpCode !== 200) {
                            $errors[] = "Integração #{$integration['id']}: HTTP {$httpCode} na página {$page}";
                            break;
                        }
                        
                        // Separar headers e body
                        $responseHeaders = substr($fullResponse, 0, $headerSize);
                        $responseBody = substr($fullResponse, $headerSize);
                        
                        // Extrair total de páginas e total de itens dos headers
                        if ($totalPages === null) {
                            if (preg_match('/X-WP-TotalPages:\s*(\d+)/i', $responseHeaders, $m)) {
                                $totalPages = (int)$m[1];
                            }
                            if (preg_match('/X-WP-Total:\s*(\d+)/i', $responseHeaders, $m)) {
                                $totalAvailable = (int)$m[1];
                            }
                        }
                        
                        $orders = json_decode($responseBody, true);
                        
                        if (!is_array($orders) || empty($orders)) {
                            break; // Sem mais resultados
                        }
                        
                        $integrationOrders = array_merge($integrationOrders, $orders);
                        $totalFetched += count($orders);
                        
                        // Se retornou menos que o per_page, não há mais páginas
                        if (count($orders) < $perPage) {
                            break;
                        }
                        
                        // Se atingiu o total de páginas disponíveis
                        if ($totalPages !== null && $page >= $totalPages) {
                            break;
                        }
                        
                        $page++;
                        
                        // Pequena pausa para não sobrecarregar a API do WooCommerce
                        if ($page > 1) {
                            usleep(200000); // 200ms
                        }
                    }
                    
                    $pagesInfo[] = [
                        'integration_id' => $integration['id'],
                        'integration_name' => $integration['name'] ?? '',
                        'pages_fetched' => $page,
                        'total_pages_available' => $totalPages,
                        'total_orders_available' => $totalAvailable,
                        'orders_fetched' => count($integrationOrders)
                    ];
                    
                    // Processar todos os pedidos coletados
                    foreach ($integrationOrders as $order) {
                        // Extrair seller_id
                        $sellerId = null;
                        if (isset($order['meta_data']) && is_array($order['meta_data'])) {
                            foreach ($order['meta_data'] as $meta) {
                                if (isset($meta['key']) && $meta['key'] === $sellerMetaKey) {
                                    $sellerId = (int)$meta['value'];
                                    break;
                                }
                            }
                        }
                        
                        // Encontrar ou criar contato
                        $contactId = null;
                        $customerEmail = $order['billing']['email'] ?? null;
                        $customerPhone = $order['billing']['phone'] ?? null;
                        
                        if ($customerEmail || $customerPhone) {
                            $contact = \App\Models\Contact::findByEmailOrPhone($customerEmail, $customerPhone);
                            if (!$contact) {
                                $contactName = trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? ''));
                                $contactName = !empty($contactName) ? $contactName : 'Cliente WooCommerce';
                                
                                // Normalizar telefone antes de salvar
                                $normalizedPhone = $customerPhone ? \App\Models\Contact::normalizePhoneNumber($customerPhone) : null;
                                
                                $contactId = \App\Models\Contact::create([
                                    'name' => $contactName,
                                    'email' => $customerEmail,
                                    'phone' => $normalizedPhone,
                                    'status' => 'active',
                                    'source' => 'woocommerce_manual_sync'
                                ]);
                                $newContacts++;
                            } else {
                                $contactId = $contact['id'];
                            }
                        }
                        
                        if ($contactId) {
                            \App\Models\WooCommerceOrderCache::cacheOrder(
                                $integration['id'],
                                $contactId,
                                $order,
                                $cacheTtlMinutes,
                                $sellerId
                            );
                            $ordersProcessed++;
                        }
                    }
                    
                    $integrationsProcessed++;
                    WooCommerceIntegration::updateLastSync($integration['id']);
                    
                } catch (\Exception $e) {
                    $errors[] = "Integração #{$integration['id']}: " . $e->getMessage();
                }
            }
            
            Response::json([
                'success' => true,
                'message' => 'Sincronização concluída',
                'integrations_processed' => $integrationsProcessed,
                'orders_processed' => $ordersProcessed,
                'new_contacts' => $newContacts,
                'pagination_info' => $pagesInfo,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao sincronizar pedidos: ' . $e->getMessage()
            ], 500);
        }
    }
}

