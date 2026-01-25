<?php
/**
 * Service WooCommerceProspectService
 * 
 * Importação de clientes do WooCommerce para listas de contatos
 * Sincronização automática diária com a loja
 */

namespace App\Services;

use App\Models\ExternalDataSource;
use App\Models\Contact;
use App\Models\ContactList;
use App\Helpers\Database;
use App\Helpers\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WooCommerceProspectService
{
    /**
     * Cliente HTTP
     */
    private static ?Client $httpClient = null;
    
    /**
     * Obter cliente HTTP
     */
    private static function getClient(): Client
    {
        if (self::$httpClient === null) {
            self::$httpClient = new Client([
                'timeout' => 60,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'ChatSystem/1.0',
                    'Accept' => 'application/json',
                ]
            ]);
        }
        return self::$httpClient;
    }
    
    /**
     * Testar conexão com WooCommerce
     */
    public static function testConnection(string $storeUrl, string $consumerKey, string $consumerSecret): array
    {
        try {
            $storeUrl = rtrim($storeUrl, '/');
            $apiUrl = $storeUrl . '/wp-json/wc/v3/system_status';
            
            $client = self::getClient();
            $response = $client->get($apiUrl, [
                'auth' => [$consumerKey, $consumerSecret],
                'timeout' => 15
            ]);
            
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                $storeName = $data['environment']['site_url'] ?? $storeUrl;
                
                return [
                    'success' => true,
                    'message' => "Conexão estabelecida com sucesso! Loja: {$storeName}"
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Resposta inesperada da API'
            ];
            
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            
            if ($statusCode === 401) {
                return ['success' => false, 'message' => 'Credenciais inválidas (Consumer Key/Secret)'];
            } elseif ($statusCode === 404) {
                return ['success' => false, 'message' => 'API WooCommerce não encontrada. Verifique a URL.'];
            }
            
            return ['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
    
    /**
     * Sincronizar clientes do WooCommerce para lista de contatos
     */
    public static function sync(int $sourceId, int $contactListId): array
    {
        $source = ExternalDataSource::find($sourceId);
        if (!$source) {
            return ['success' => false, 'message' => 'Fonte não encontrada'];
        }
        
        // Obter configuração
        $config = json_decode($source['search_config'] ?? '{}', true);
        if (empty($config['store_url']) || empty($config['consumer_key']) || empty($config['consumer_secret'])) {
            return ['success' => false, 'message' => 'Configuração incompleta'];
        }
        
        $storeUrl = rtrim($config['store_url'], '/');
        $consumerKey = $config['consumer_key'];
        $consumerSecret = $config['consumer_secret'];
        $importType = $config['import_type'] ?? 'customers'; // customers, orders
        $daysBack = (int)($config['days_back'] ?? 30);
        $minOrders = (int)($config['min_orders'] ?? 0);
        
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        
        try {
            ExternalDataSource::updateSyncStatus($sourceId, 'syncing', 'Sincronização em andamento...');
            
            $client = self::getClient();
            $page = 1;
            $perPage = 100;
            $hasMore = true;
            
            while ($hasMore) {
                // Buscar clientes ou pedidos
                if ($importType === 'customers') {
                    $apiUrl = $storeUrl . '/wp-json/wc/v3/customers';
                    $params = [
                        'per_page' => $perPage,
                        'page' => $page,
                        'orderby' => 'registered_date',
                        'order' => 'desc'
                    ];
                } else {
                    // Buscar via pedidos (pega clientes que fizeram compras)
                    $apiUrl = $storeUrl . '/wp-json/wc/v3/orders';
                    $after = date('Y-m-d\TH:i:s', strtotime("-{$daysBack} days"));
                    $params = [
                        'per_page' => $perPage,
                        'page' => $page,
                        'after' => $after,
                        'status' => 'completed,processing,on-hold'
                    ];
                }
                
                $response = $client->get($apiUrl, [
                    'auth' => [$consumerKey, $consumerSecret],
                    'query' => $params
                ]);
                
                $data = json_decode($response->getBody()->getContents(), true);
                
                if (empty($data)) {
                    $hasMore = false;
                    break;
                }
                
                // Processar cada registro
                foreach ($data as $record) {
                    try {
                        if ($importType === 'customers') {
                            $result = self::processCustomer($record, $contactListId, $sourceId, $minOrders);
                        } else {
                            $result = self::processOrder($record, $contactListId, $sourceId);
                        }
                        
                        if ($result === 'imported') {
                            $imported++;
                        } elseif ($result === 'updated') {
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = $e->getMessage();
                        $skipped++;
                    }
                }
                
                // Verificar se há mais páginas
                $totalPages = $response->getHeader('X-WP-TotalPages')[0] ?? 1;
                if ($page >= (int)$totalPages) {
                    $hasMore = false;
                }
                
                $page++;
                
                // Delay para não sobrecarregar a API
                usleep(200000); // 200ms
            }
            
            // Atualizar status
            $totalSynced = $imported + $updated;
            $message = "Sincronização concluída: {$imported} importados, {$updated} atualizados, {$skipped} ignorados";
            ExternalDataSource::updateSyncStatus($sourceId, 'success', $message, $totalSynced);
            ExternalDataSource::update($sourceId, ['total_synced' => ($source['total_synced'] ?? 0) + $totalSynced]);
            Logger::info("WooCommerceProspectService::sync - " . $message);
            
            return [
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 10)
            ];
            
        } catch (\Exception $e) {
            ExternalDataSource::updateSyncStatus($sourceId, 'error', $e->getMessage());
            Logger::error("WooCommerceProspectService::sync - Erro: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erro na sincronização: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Processar cliente do WooCommerce
     */
    private static function processCustomer(array $customer, int $contactListId, int $sourceId, int $minOrders = 0): string
    {
        // Verificar quantidade de pedidos
        $ordersCount = (int)($customer['orders_count'] ?? 0);
        if ($minOrders > 0 && $ordersCount < $minOrders) {
            return 'skipped';
        }
        
        // Extrair dados do cliente
        $billing = $customer['billing'] ?? [];
        $phone = self::normalizePhone($billing['phone'] ?? '');
        $email = $customer['email'] ?? $billing['email'] ?? '';
        $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        if (empty($name)) {
            $name = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        }
        
        // Precisa ter telefone ou email
        if (empty($phone) && empty($email)) {
            return 'skipped';
        }
        
        // Buscar contato existente
        $existingContact = null;
        if (!empty($phone)) {
            $existingContact = self::findContactByPhone($phone);
        }
        if (!$existingContact && !empty($email)) {
            $existingContact = self::findContactByEmail($email);
        }
        
        $contactData = [
            'name' => $name ?: 'Cliente WooCommerce',
            'phone' => $phone,
            'email' => $email,
            'source' => 'woocommerce',
            'address' => self::formatAddress($billing),
            'city' => $billing['city'] ?? null,
            'state' => $billing['state'] ?? null,
            'country' => $billing['country'] ?? 'BR',
            'company' => $billing['company'] ?? null,
        ];
        
        if ($existingContact) {
            // Atualizar dados existentes (apenas campos vazios)
            $updates = [];
            foreach ($contactData as $field => $value) {
                if (!empty($value) && empty($existingContact[$field])) {
                    $updates[$field] = $value;
                }
            }
            
            if (!empty($updates)) {
                Contact::update($existingContact['id'], $updates);
            }
            
            // Adicionar à lista se não estiver
            self::addToListIfNotExists($existingContact['id'], $contactListId);
            
            return 'updated';
        }
        
        // Criar novo contato
        $contactId = Contact::create($contactData);
        
        // Adicionar à lista
        ContactList::addContact($contactListId, $contactId);
        
        return 'imported';
    }
    
    /**
     * Processar pedido do WooCommerce (extrai cliente)
     */
    private static function processOrder(array $order, int $contactListId, int $sourceId): string
    {
        $billing = $order['billing'] ?? [];
        $phone = self::normalizePhone($billing['phone'] ?? '');
        $email = $billing['email'] ?? '';
        $name = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        
        // Precisa ter telefone ou email
        if (empty($phone) && empty($email)) {
            return 'skipped';
        }
        
        // Buscar contato existente
        $existingContact = null;
        if (!empty($phone)) {
            $existingContact = self::findContactByPhone($phone);
        }
        if (!$existingContact && !empty($email)) {
            $existingContact = self::findContactByEmail($email);
        }
        
        $contactData = [
            'name' => $name ?: 'Cliente WooCommerce',
            'phone' => $phone,
            'email' => $email,
            'source' => 'woocommerce',
            'address' => self::formatAddress($billing),
            'city' => $billing['city'] ?? null,
            'state' => $billing['state'] ?? null,
            'country' => $billing['country'] ?? 'BR',
            'company' => $billing['company'] ?? null,
        ];
        
        if ($existingContact) {
            // Atualizar dados existentes
            $updates = [];
            foreach ($contactData as $field => $value) {
                if (!empty($value) && empty($existingContact[$field])) {
                    $updates[$field] = $value;
                }
            }
            
            if (!empty($updates)) {
                Contact::update($existingContact['id'], $updates);
            }
            
            // Adicionar à lista se não estiver
            self::addToListIfNotExists($existingContact['id'], $contactListId);
            
            return 'updated';
        }
        
        // Criar novo contato
        $contactId = Contact::create($contactData);
        
        // Adicionar à lista
        ContactList::addContact($contactListId, $contactId);
        
        return 'imported';
    }
    
    /**
     * Formatar endereço
     */
    private static function formatAddress(array $billing): ?string
    {
        $parts = array_filter([
            $billing['address_1'] ?? '',
            $billing['address_2'] ?? '',
            $billing['neighborhood'] ?? ''
        ]);
        
        return !empty($parts) ? implode(', ', $parts) : null;
    }
    
    /**
     * Normalizar telefone
     */
    private static function normalizePhone(string $phone): string
    {
        // Remover tudo que não é número
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Se não tem números suficientes, retornar vazio
        if (strlen($phone) < 10) {
            return '';
        }
        
        // Adicionar código do país se necessário
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Buscar contato por telefone
     */
    private static function findContactByPhone(string $phone): ?array
    {
        if (empty($phone)) {
            return null;
        }
        
        // Buscar pelo telefone normalizado
        return Database::fetch(
            "SELECT * FROM contacts WHERE phone = ? OR phone LIKE ? LIMIT 1",
            [$phone, '%' . substr($phone, -10)]
        );
    }
    
    /**
     * Buscar contato por email
     */
    private static function findContactByEmail(string $email): ?array
    {
        if (empty($email)) {
            return null;
        }
        
        return Database::fetch(
            "SELECT * FROM contacts WHERE email = ? LIMIT 1",
            [$email]
        );
    }
    
    /**
     * Adicionar contato à lista se não existir
     */
    private static function addToListIfNotExists(int $contactId, int $listId): void
    {
        $exists = Database::fetch(
            "SELECT 1 FROM contact_list_items WHERE contact_list_id = ? AND contact_id = ?",
            [$listId, $contactId]
        );
        
        if (!$exists) {
            ContactList::addContact($listId, $contactId);
        }
    }
    
    /**
     * Preview de clientes (sem salvar)
     */
    public static function preview(string $storeUrl, string $consumerKey, string $consumerSecret, int $limit = 10): array
    {
        try {
            $storeUrl = rtrim($storeUrl, '/');
            $apiUrl = $storeUrl . '/wp-json/wc/v3/customers';
            
            $client = self::getClient();
            $response = $client->get($apiUrl, [
                'auth' => [$consumerKey, $consumerSecret],
                'query' => [
                    'per_page' => $limit,
                    'orderby' => 'registered_date',
                    'order' => 'desc'
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            $totalCustomers = $response->getHeader('X-WP-Total')[0] ?? 0;
            
            $customers = [];
            foreach ($data as $customer) {
                $billing = $customer['billing'] ?? [];
                $customers[] = [
                    'id' => $customer['id'],
                    'name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                    'email' => $customer['email'] ?? '',
                    'phone' => $billing['phone'] ?? '',
                    'city' => $billing['city'] ?? '',
                    'orders_count' => $customer['orders_count'] ?? 0,
                    'total_spent' => $customer['total_spent'] ?? '0.00'
                ];
            }
            
            return [
                'success' => true,
                'total' => (int)$totalCustomers,
                'customers' => $customers
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
        }
    }
}
