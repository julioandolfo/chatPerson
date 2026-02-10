<?php
/**
 * Service WooCommerceIntegrationService
 * Integração nativa com WooCommerce
 */

namespace App\Services;

use App\Models\WooCommerceIntegration;
use App\Models\WooCommerceOrderCache;
use App\Models\Contact;
use App\Helpers\Database;
use App\Helpers\Logger;

class WooCommerceIntegrationService
{
    /**
     * Buscar pedidos de um contato
     */
    public static function getContactOrders(int $contactId, ?int $integrationId = null): array
    {
        // 1. Obter integrações ativas
        $integrations = $integrationId 
            ? [WooCommerceIntegration::find($integrationId)]
            : WooCommerceIntegration::getActive();
        
        if (empty($integrations)) {
            return [];
        }
        
        $allOrders = [];
        
        foreach ($integrations as $integration) {
            if (!$integration || ($integration['status'] ?? '') !== 'active') {
                continue;
            }
            
            // Dados da integração para incluir nos pedidos
            $integrationUrl = rtrim($integration['woocommerce_url'] ?? '', '/');
            $integrationName = $integration['name'] ?? 'Loja';
            $integrationId = $integration['id'];
            
            // 2. Verificar cache primeiro
            $cacheEnabled = $integration['cache_enabled'] ?? true;
            if ($cacheEnabled) {
                $cachedOrders = WooCommerceOrderCache::getByContact($contactId, $integration['id']);
                if (!empty($cachedOrders)) {
                    // Converter cache para formato de pedido
                    foreach ($cachedOrders as $cached) {
                        $orderData = json_decode($cached['order_data'], true);
                        if ($orderData) {
                            // Adicionar informações da integração ao pedido
                            $orderData['integration_url'] = $integrationUrl;
                            $orderData['integration_name'] = $integrationName;
                            $orderData['integration_id'] = $integrationId;
                            $allOrders[] = $orderData;
                        }
                    }
                    continue; // Usar cache, não buscar novamente
                }
            }
            
            // 3. Obter dados do contato
            $contact = Contact::find($contactId);
            if (!$contact) {
                continue;
            }
            
            // 4. Buscar pedidos usando mapeamento configurado
            try {
                $orders = self::searchOrders($integration, $contact);
                
                // Adicionar informações da integração a cada pedido
                foreach ($orders as &$order) {
                    $order['integration_url'] = $integrationUrl;
                    $order['integration_name'] = $integrationName;
                    $order['integration_id'] = $integrationId;
                }
                unset($order);
                
                // 5. Cachear resultados
                if ($cacheEnabled && !empty($orders)) {
                    $ttlMinutes = $integration['cache_ttl_minutes'] ?? 5;
                    foreach ($orders as $order) {
                        WooCommerceOrderCache::cacheOrder($integration['id'], $contactId, $order, $ttlMinutes);
                    }
                }
                
                $allOrders = array_merge($allOrders, $orders);
            } catch (\Exception $e) {
                Logger::error("WooCommerceIntegrationService::getContactOrders - Erro ao buscar pedidos: " . $e->getMessage());
                WooCommerceIntegration::updateLastSync($integration['id'], $e->getMessage());
            }
        }
        
        // 6. Remover duplicatas (mesmo order_id)
        $uniqueOrders = [];
        $seenIds = [];
        foreach ($allOrders as $order) {
            $orderId = $order['id'] ?? null;
            if ($orderId && !in_array($orderId, $seenIds)) {
                $uniqueOrders[] = $order;
                $seenIds[] = $orderId;
            }
        }
        
        // 7. Ordenar por data (mais recente primeiro)
        usort($uniqueOrders, function($a, $b) {
            $dateA = strtotime($a['date_created'] ?? '1970-01-01');
            $dateB = strtotime($b['date_created'] ?? '1970-01-01');
            return $dateB - $dateA;
        });
        
        return $uniqueOrders;
    }
    
    /**
     * Buscar pedidos usando mapeamento configurado
     */
    private static function searchOrders(array $integration, array $contact): array
    {
        $mapping = WooCommerceIntegration::getFieldMapping($integration['id']);
        $searchSettings = WooCommerceIntegration::getSearchSettings($integration['id']);
        
        $orders = [];
        
        // Buscar por telefone (se habilitado)
        if (($mapping['phone']['enabled'] ?? false) && !empty($contact['phone'])) {
            $phoneOrders = self::searchByPhone($integration, $contact['phone'], $searchSettings);
            $orders = array_merge($orders, $phoneOrders);
        }
        
        // Buscar por email (se habilitado)
        if (($mapping['email']['enabled'] ?? false) && !empty($contact['email'])) {
            $emailOrders = self::searchByEmail($integration, $contact['email'], $searchSettings);
            $orders = array_merge($orders, $emailOrders);
        }
        
        // Buscar por nome (se habilitado)
        if (($mapping['name']['enabled'] ?? false) && !empty($contact['name'])) {
            $nameOrders = self::searchByName($integration, $contact['name'], $searchSettings);
            $orders = array_merge($orders, $nameOrders);
        }
        
        // VALIDAR pedidos retornados para garantir que pertencem ao contato
        $validatedOrders = self::validateOrders($orders, $contact, $mapping);
        
        return $validatedOrders;
    }
    
    /**
     * Validar pedidos retornados para garantir que pertencem ao contato
     */
    private static function validateOrders(array $orders, array $contact, array $mapping): array
    {
        $validated = [];
        
        // Gerar variações do telefone do contato para comparação
        $phoneVariations = !empty($contact['phone']) 
            ? PhoneNormalizationService::generateVariations($contact['phone']) 
            : [];
        
        foreach ($orders as $order) {
            $isValid = false;
            
            // Validar por telefone (mais confiável)
            if ($mapping['phone']['enabled'] ?? false) {
                $orderPhone = $order['billing']['phone'] ?? '';
                if (!empty($orderPhone)) {
                    // Normalizar telefone do pedido e comparar com variações do contato
                    $normalizedOrderPhone = PhoneNormalizationService::normalizeWooCommercePhone($orderPhone);
                    if (in_array($normalizedOrderPhone, $phoneVariations)) {
                        $isValid = true;
                    }
                }
            }
            
            // Validar por email (muito confiável)
            if (!$isValid && ($mapping['email']['enabled'] ?? false)) {
                $orderEmail = strtolower(trim($order['billing']['email'] ?? ''));
                $contactEmail = strtolower(trim($contact['email'] ?? ''));
                if (!empty($orderEmail) && !empty($contactEmail) && $orderEmail === $contactEmail) {
                    $isValid = true;
                }
            }
            
            // Se busca por nome está habilitada, exigir que pelo menos telefone OU email batam
            // (não aceitar pedidos APENAS por nome)
            if (($mapping['name']['enabled'] ?? false) && !($mapping['phone']['enabled'] ?? false) && !($mapping['email']['enabled'] ?? false)) {
                // Se APENAS nome está habilitado (não recomendado), aceitar o pedido
                $isValid = true;
            }
            
            if ($isValid) {
                $validated[] = $order;
            }
        }
        
        return $validated;
    }
    
    /**
     * Buscar por telefone com variações
     */
    private static function searchByPhone(array $integration, string $phone, array $settings): array
    {
        // Gerar variações do telefone
        $variations = PhoneNormalizationService::generateVariations($phone);
        
        $allOrders = [];
        $maxResults = $settings['max_results'] ?? 50;
        
        // Buscar no WooCommerce usando cada variação
        foreach ($variations as $variation) {
            try {
                $orders = self::callWooCommerceAPI($integration, 'orders', [
                    'billing_phone' => $variation,
                    'per_page' => $maxResults
                ]);
                
                $allOrders = array_merge($allOrders, $orders);
                
                // Se encontrou resultados, pode parar (ou continuar para garantir todos)
            } catch (\Exception $e) {
                Logger::error("WooCommerceIntegrationService::searchByPhone - Erro ao buscar por telefone '{$variation}': " . $e->getMessage());
            }
        }
        
        return $allOrders;
    }
    
    /**
     * Buscar por email
     */
    private static function searchByEmail(array $integration, string $email, array $settings): array
    {
        $maxResults = $settings['max_results'] ?? 50;
        
        try {
            return self::callWooCommerceAPI($integration, 'orders', [
                'billing_email' => $email,
                'per_page' => $maxResults
            ]);
        } catch (\Exception $e) {
            Logger::error("WooCommerceIntegrationService::searchByEmail - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buscar por nome
     */
    private static function searchByName(array $integration, string $name, array $settings): array
    {
        $maxResults = $settings['max_results'] ?? 50;
        
        try {
            // Buscar por primeiro nome
            $orders = self::callWooCommerceAPI($integration, 'orders', [
                'billing_first_name' => $name,
                'per_page' => $maxResults
            ]);
            
            // Também tentar buscar por último nome se houver
            $nameParts = explode(' ', trim($name));
            if (count($nameParts) > 1) {
                $lastName = end($nameParts);
                $lastNameOrders = self::callWooCommerceAPI($integration, 'orders', [
                    'billing_last_name' => $lastName,
                    'per_page' => $maxResults
                ]);
                $orders = array_merge($orders, $lastNameOrders);
            }
            
            return $orders;
        } catch (\Exception $e) {
            Logger::error("WooCommerceIntegrationService::searchByName - Erro: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Chamar API do WooCommerce
     */
    private static function callWooCommerceAPI(array $integration, string $endpoint, array $params = []): array
    {
        $url = rtrim($integration['woocommerce_url'], '/') . '/wp-json/wc/v3/' . $endpoint;
        
        $auth = base64_encode($integration['consumer_key'] . ':' . $integration['consumer_secret']);
        
        $queryString = http_build_query($params);
        if ($queryString) {
            $url .= '?' . $queryString;
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $auth,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("Erro de conexão: {$error}");
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['message'] ?? $errorData['code'] ?? "HTTP {$httpCode}";
            throw new \Exception("Erro ao buscar pedidos: {$errorMessage}");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Resposta inválida da API WooCommerce");
        }
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Testar conexão com WooCommerce
     */
    public static function testConnection(int $integrationId): array
    {
        $integration = WooCommerceIntegration::find($integrationId);
        if (!$integration) {
            throw new \Exception('Integração não encontrada');
        }
        
        try {
            // Tentar buscar informações básicas da loja
            $response = self::callWooCommerceAPI($integration, 'system_status');
            
            WooCommerceIntegration::updateLastSync($integrationId);
            
            return [
                'success' => true,
                'message' => 'Conexão bem-sucedida',
                'data' => $response
            ];
        } catch (\Exception $e) {
            WooCommerceIntegration::updateLastSync($integrationId, $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Criar integração
     */
    public static function create(array $data): int
    {
        $errors = \App\Helpers\Validator::validate($data, [
            'name' => 'required|string|max:255',
            'woocommerce_url' => 'required|url|max:500',
            'consumer_key' => 'required|string|max:255',
            'consumer_secret' => 'required|string|max:500',
            'contact_field_mapping' => 'required|array',
            'search_settings' => 'nullable|array',
            'seller_meta_key' => 'nullable|string|max:255'
        ]);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        // Preparar dados
        $integrationData = [
            'name' => $data['name'],
            'woocommerce_url' => rtrim($data['woocommerce_url'], '/'),
            'consumer_key' => $data['consumer_key'],
            'consumer_secret' => $data['consumer_secret'],
            'contact_field_mapping' => json_encode($data['contact_field_mapping']),
            'search_settings' => json_encode($data['search_settings'] ?? []),
            'seller_meta_key' => $data['seller_meta_key'] ?? '_vendor_id',
            'status' => 'active',
            'cache_enabled' => $data['cache_enabled'] ?? true,
            'cache_ttl_minutes' => $data['cache_ttl_minutes'] ?? 5,
            'sync_frequency_minutes' => $data['sync_frequency_minutes'] ?? 15
        ];
        
        return WooCommerceIntegration::create($integrationData);
    }
    
    /**
     * Atualizar integração
     */
    public static function update(int $id, array $data): bool
    {
        $errors = \App\Helpers\Validator::validate($data, [
            'name' => 'nullable|string|max:255',
            'woocommerce_url' => 'nullable|url|max:500',
            'consumer_key' => 'nullable|string|max:255',
            'consumer_secret' => 'nullable|string|max:500',
            'contact_field_mapping' => 'nullable|array',
            'search_settings' => 'nullable|array',
            'seller_meta_key' => 'nullable|string|max:255'
        ]);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['woocommerce_url'])) {
            $updateData['woocommerce_url'] = rtrim($data['woocommerce_url'], '/');
        }
        if (isset($data['consumer_key'])) {
            $updateData['consumer_key'] = $data['consumer_key'];
        }
        if (isset($data['consumer_secret'])) {
            $updateData['consumer_secret'] = $data['consumer_secret'];
        }
        if (isset($data['contact_field_mapping'])) {
            $updateData['contact_field_mapping'] = json_encode($data['contact_field_mapping']);
        }
        if (isset($data['search_settings'])) {
            $updateData['search_settings'] = json_encode($data['search_settings']);
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['cache_enabled'])) {
            $updateData['cache_enabled'] = $data['cache_enabled'] ? 1 : 0;
        }
        if (isset($data['cache_ttl_minutes'])) {
            $updateData['cache_ttl_minutes'] = (int)$data['cache_ttl_minutes'];
        }
        if (isset($data['sync_frequency_minutes'])) {
            $updateData['sync_frequency_minutes'] = (int)$data['sync_frequency_minutes'];
        }
        if (isset($data['seller_meta_key'])) {
            $updateData['seller_meta_key'] = $data['seller_meta_key'];
        }
        
        return WooCommerceIntegration::update($id, $updateData);
    }
    
    /**
     * Testar conexão e validar meta_key do vendedor
     * Retorna feedback sobre a validade do campo configurado
     */
    public static function testSellerMetaKey(int $integrationId, string $metaKey): array
    {
        try {
            $integration = WooCommerceIntegration::find($integrationId);
            if (!$integration) {
                return [
                    'success' => false,
                    'message' => 'Integração não encontrada'
                ];
            }
            
            $wcUrl = $integration['woocommerce_url'];
            $consumerKey = $integration['consumer_key'];
            $consumerSecret = $integration['consumer_secret'];
            
            // 1. Buscar últimos 10 pedidos
            $url = rtrim($wcUrl, '/') . '/wp-json/wc/v3/orders?per_page=10&orderby=date&order=desc';
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return [
                    'success' => false,
                    'message' => 'Erro ao conectar com WooCommerce: ' . $error
                ];
            }
            
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'message' => "Erro HTTP {$httpCode}: Verifique as credenciais da API"
                ];
            }
            
            $orders = json_decode($response, true);
            
            if (empty($orders)) {
                return [
                    'success' => false,
                    'message' => 'Nenhum pedido encontrado na loja'
                ];
            }
            
            // 2. Verificar se o meta_key existe nos pedidos
            $foundMetaKey = false;
            $sellersFound = [];
            $exampleOrder = null;
            
            foreach ($orders as $order) {
                if (!isset($order['meta_data']) || !is_array($order['meta_data'])) {
                    continue;
                }
                
                foreach ($order['meta_data'] as $meta) {
                    if ($meta['key'] === $metaKey) {
                        $foundMetaKey = true;
                        $sellerId = $meta['value'];
                        if (!in_array($sellerId, $sellersFound)) {
                            $sellersFound[] = $sellerId;
                        }
                        if (!$exampleOrder) {
                            $exampleOrder = [
                                'id' => $order['id'],
                                'seller_id' => $sellerId,
                                'total' => $order['total'] ?? '0',
                                'date' => $order['date_created'] ?? null
                            ];
                        }
                    }
                }
            }
            
            // 3. Retornar resultado
            if ($foundMetaKey) {
                return [
                    'success' => true,
                    'message' => "✅ Meta key '{$metaKey}' encontrado com sucesso!",
                    'details' => [
                        'total_orders_checked' => count($orders),
                        'sellers_found' => count($sellersFound),
                        'seller_ids' => $sellersFound,
                        'example_order' => $exampleOrder
                    ]
                ];
            } else {
                // Listar meta_keys disponíveis para ajudar
                $availableKeys = [];
                foreach ($orders as $order) {
                    if (isset($order['meta_data']) && is_array($order['meta_data'])) {
                        foreach ($order['meta_data'] as $meta) {
                            $key = $meta['key'];
                            if (!in_array($key, $availableKeys)) {
                                $availableKeys[] = $key;
                            }
                        }
                    }
                }
                
                return [
                    'success' => false,
                    'message' => "⚠️ Meta key '{$metaKey}' NÃO encontrado nos pedidos",
                    'details' => [
                        'total_orders_checked' => count($orders),
                        'suggestion' => 'Verifique se o campo está correto ou se há pedidos com vendedor associado',
                        'available_meta_keys' => array_slice($availableKeys, 0, 20) // Primeiros 20
                    ]
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao testar: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Buscar pedidos por ID do vendedor
     */
    public static function getOrdersBySeller(
        int $sellerId,
        ?int $integrationId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        $integrations = $integrationId 
            ? [WooCommerceIntegration::find($integrationId)]
            : WooCommerceIntegration::getActive();
        
        if (empty($integrations)) {
            return [];
        }
        
        $allOrders = [];
        
        foreach ($integrations as $integration) {
            if (!$integration || ($integration['status'] ?? '') !== 'active') {
                continue;
            }
            
            try {
                $wcUrl = $integration['woocommerce_url'];
                $consumerKey = $integration['consumer_key'];
                $consumerSecret = $integration['consumer_secret'];
                $metaKey = $integration['seller_meta_key'] ?? '_vendor_id';
                
                // ═══ PAGINAÇÃO: WooCommerce API limita a 100 por página ═══
                $perPage = 100;
                $page = 1;
                $maxPages = 50; // Limite de segurança
                
                while ($page <= $maxPages) {
                    $params = [
                        'per_page' => $perPage,
                        'page' => $page,
                        'orderby' => 'date',
                        'order' => 'desc'
                    ];
                    
                    if ($dateFrom) {
                        $params['after'] = date('c', strtotime($dateFrom));
                    }
                    if ($dateTo) {
                        $params['before'] = date('c', strtotime($dateTo));
                    }
                    
                    $url = rtrim($wcUrl, '/') . '/wp-json/wc/v3/orders?' . http_build_query($params);
                    
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
                        CURLOPT_TIMEOUT => 60,
                        CURLOPT_CONNECTTIMEOUT => 15,
                        CURLOPT_HEADER => true
                    ]);
                    
                    $fullResponse = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                    curl_close($ch);
                    
                    if ($httpCode !== 200) {
                        break;
                    }
                    
                    // Separar headers e body
                    $responseHeaders = substr($fullResponse, 0, $headerSize);
                    $responseBody = substr($fullResponse, $headerSize);
                    
                    // Extrair total de páginas dos headers
                    $totalPages = null;
                    if (preg_match('/X-WP-TotalPages:\s*(\d+)/i', $responseHeaders, $m)) {
                        $totalPages = (int)$m[1];
                    }
                    
                    $orders = json_decode($responseBody, true);
                    
                    if (!is_array($orders) || empty($orders)) {
                        break;
                    }
                    
                    // Filtrar pedidos do vendedor específico
                    foreach ($orders as $order) {
                        if (isset($order['meta_data']) && is_array($order['meta_data'])) {
                            foreach ($order['meta_data'] as $meta) {
                                if ($meta['key'] === $metaKey && $meta['value'] == $sellerId) {
                                    $allOrders[] = $order;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Se retornou menos que o per_page, não há mais páginas
                    if (count($orders) < $perPage) {
                        break;
                    }
                    
                    // Se atingiu o total de páginas disponíveis
                    if ($totalPages !== null && $page >= $totalPages) {
                        break;
                    }
                    
                    $page++;
                    
                    // Pequena pausa entre páginas
                    usleep(200000); // 200ms
                }
            } catch (\Exception $e) {
                Logger::error("WooCommerceIntegrationService::getOrdersBySeller - Erro: " . $e->getMessage());
            }
        }
        
        // Remover duplicatas
        $uniqueOrders = [];
        $seenIds = [];
        foreach ($allOrders as $order) {
            $orderId = $order['id'] ?? null;
            if ($orderId && !in_array($orderId, $seenIds)) {
                $uniqueOrders[] = $order;
                $seenIds[] = $orderId;
            }
        }
        
        return $uniqueOrders;
    }
}

