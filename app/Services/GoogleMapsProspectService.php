<?php
/**
 * Service GoogleMapsProspectService
 * 
 * Prospecção de leads via Google Maps (Google Places API / Outscraper)
 * Busca empresas por categoria e região, extrai telefone para WhatsApp
 */

namespace App\Services;

use App\Models\ExternalDataSource;
use App\Models\Contact;
use App\Models\ContactList;
use App\Helpers\Database;
use App\Helpers\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GoogleMapsProspectService
{
    /**
     * URLs das APIs
     */
    private const GOOGLE_PLACES_BASE_URL = 'https://maps.googleapis.com/maps/api/place';
    private const OUTSCRAPER_BASE_URL = 'https://api.outscraper.com/maps/search-v3';
    
    /**
     * Configurações padrão
     */
    private const DEFAULT_RADIUS = 5000; // 5km
    private const DEFAULT_MAX_RESULTS = 60; // Google Places retorna max 60 por busca
    private const DEFAULT_LANGUAGE = 'pt-BR';
    private const REQUEST_DELAY_MS = 200; // Delay entre requisições
    
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
                'timeout' => 30,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'
                ]
            ]);
        }
        return self::$httpClient;
    }
    
    /**
     * Obter API Key do Google Places
     */
    private static function getGoogleApiKey(): ?string
    {
        // 1. Tentar obter do banco de dados (configurações do sistema)
        try {
            $dbKey = \App\Models\Setting::get('google_places_api_key', '');
            if (!empty($dbKey)) {
                return $dbKey;
            }
        } catch (\Exception $e) {
            // Ignorar erro se tabela não existir
        }
        
        // 2. Tentar obter do arquivo de config
        $configPath = __DIR__ . '/../../config/services.php';
        if (file_exists($configPath)) {
            $config = include $configPath;
            if (!empty($config['google_places']['api_key'])) {
                return $config['google_places']['api_key'];
            }
        }
        
        // 3. Tentar obter de variável de ambiente
        return $_ENV['GOOGLE_PLACES_API_KEY'] ?? getenv('GOOGLE_PLACES_API_KEY') ?: null;
    }
    
    /**
     * Obter API Key do Outscraper
     */
    private static function getOutscraperApiKey(): ?string
    {
        // 1. Tentar obter do banco de dados (configurações do sistema)
        try {
            $dbKey = \App\Models\Setting::get('outscraper_api_key', '');
            if (!empty($dbKey)) {
                return $dbKey;
            }
        } catch (\Exception $e) {
            // Ignorar erro se tabela não existir
        }
        
        // 2. Tentar obter do arquivo de config
        $configPath = __DIR__ . '/../../config/services.php';
        if (file_exists($configPath)) {
            $config = include $configPath;
            if (!empty($config['outscraper']['api_key'])) {
                return $config['outscraper']['api_key'];
            }
        }
        
        return $_ENV['OUTSCRAPER_API_KEY'] ?? getenv('OUTSCRAPER_API_KEY') ?: null;
    }
    
    /**
     * Buscar leads do Google Maps
     * 
     * @param int $sourceId ID da fonte externa
     * @param int $contactListId ID da lista de contatos
     * @return array Resultado da sincronização
     */
    public static function sync(int $sourceId, int $contactListId): array
    {
        $startTime = microtime(true);
        $stats = [
            'started_at' => date('Y-m-d H:i:s'),
            'records_fetched' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0
        ];
        
        try {
            $source = ExternalDataSource::find($sourceId);
            if (!$source) {
                throw new \Exception("Fonte externa não encontrada: {$sourceId}");
            }
            
            if ($source['type'] !== 'google_maps') {
                throw new \Exception("Tipo de fonte inválido: {$source['type']}");
            }
            
            $searchConfig = json_decode($source['search_config'], true);
            if (empty($searchConfig)) {
                throw new \Exception("Configuração de busca não definida");
            }
            
            $provider = $source['provider'] ?? 'google_places';
            
            Logger::info("GoogleMapsProspectService::sync - Iniciando busca via {$provider}", [
                'source_id' => $sourceId,
                'keyword' => $searchConfig['keyword'] ?? '',
                'location' => $searchConfig['location'] ?? ''
            ]);
            
            // Buscar leads baseado no provider
            if ($provider === 'outscraper') {
                $leads = self::fetchFromOutscraper($searchConfig, $source['last_page_token']);
            } else {
                $leads = self::fetchFromGooglePlaces($searchConfig, $source['last_page_token']);
            }
            
            $stats['records_fetched'] = count($leads['results']);
            
            // Processar cada lead
            foreach ($leads['results'] as $lead) {
                try {
                    $result = self::processLead($lead, $contactListId, $sourceId);
                    
                    if ($result['action'] === 'created') {
                        $stats['records_created']++;
                    } elseif ($result['action'] === 'updated') {
                        $stats['records_updated']++;
                    } elseif ($result['action'] === 'skipped') {
                        $stats['records_skipped']++;
                    }
                    
                } catch (\Exception $e) {
                    Logger::error("Erro ao processar lead: " . $e->getMessage());
                    $stats['records_failed']++;
                }
                
                // Delay entre processamentos
                usleep(50000); // 50ms
            }
            
            // Atualizar fonte com page token e estatísticas
            $updateData = [
                'total_synced' => ($source['total_synced'] ?? 0) + $stats['records_created'] + $stats['records_updated']
            ];
            
            if (!empty($leads['next_page_token'])) {
                $updateData['last_page_token'] = $leads['next_page_token'];
            }
            
            ExternalDataSource::update($sourceId, $updateData);
            
            // Registrar status de sucesso
            $message = "Sincronizado: {$stats['records_created']} novos, {$stats['records_updated']} atualizados, {$stats['records_skipped']} ignorados";
            ExternalDataSource::updateSyncStatus($sourceId, 'success', $message, $stats['records_fetched']);
            
            $stats['completed_at'] = date('Y-m-d H:i:s');
            $stats['status'] = 'success';
            $stats['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);
            
            // Registrar log
            ExternalDataSource::logSync($sourceId, $stats);
            
            // Atualizar lista
            ContactList::update($contactListId, [
                'last_sync_at' => date('Y-m-d H:i:s')
            ]);
            ContactList::recalculateTotal($contactListId);
            
            Logger::info("GoogleMapsProspectService::sync - Concluído", $stats);
            
            return [
                'success' => true,
                'message' => $message,
                'stats' => $stats,
                'has_more' => !empty($leads['next_page_token'])
            ];
            
        } catch (\Exception $e) {
            Logger::error("GoogleMapsProspectService::sync - Erro: " . $e->getMessage());
            
            $stats['completed_at'] = date('Y-m-d H:i:s');
            $stats['status'] = 'error';
            $stats['error_message'] = $e->getMessage();
            $stats['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);
            
            ExternalDataSource::updateSyncStatus($sourceId, 'error', $e->getMessage());
            ExternalDataSource::logSync($sourceId, $stats);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'stats' => $stats
            ];
        }
    }
    
    /**
     * Buscar empresas via Google Places API
     */
    private static function fetchFromGooglePlaces(array $config, ?string $pageToken = null): array
    {
        $apiKey = self::getGoogleApiKey();
        if (empty($apiKey)) {
            throw new \Exception("API Key do Google Places não configurada");
        }
        
        $client = self::getClient();
        $results = [];
        $nextPageToken = null;
        
        // Se temos um page token, usar para continuar paginação
        if ($pageToken) {
            $url = self::GOOGLE_PLACES_BASE_URL . '/nearbysearch/json';
            $params = [
                'pagetoken' => $pageToken,
                'key' => $apiKey
            ];
            
            // Google exige delay de 2s antes de usar page token
            sleep(2);
            
        } else {
            // Primeira requisição - converter localização em coordenadas
            $location = $config['location'] ?? 'São Paulo, SP';
            $coordinates = self::geocodeLocation($location, $apiKey);
            
            if (!$coordinates) {
                throw new \Exception("Não foi possível geocodificar a localização: {$location}");
            }
            
            $url = self::GOOGLE_PLACES_BASE_URL . '/nearbysearch/json';
            $params = [
                'location' => "{$coordinates['lat']},{$coordinates['lng']}",
                'radius' => $config['radius'] ?? self::DEFAULT_RADIUS,
                'keyword' => $config['keyword'] ?? '',
                'language' => $config['language'] ?? self::DEFAULT_LANGUAGE,
                'key' => $apiKey
            ];
        }
        
        try {
            $response = $client->get($url, ['query' => $params]);
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
                throw new \Exception("Google Places API erro: " . ($data['error_message'] ?? $data['status']));
            }
            
            $nextPageToken = $data['next_page_token'] ?? null;
            
            // Processar resultados
            foreach ($data['results'] ?? [] as $place) {
                // Buscar detalhes para obter telefone
                $details = self::getPlaceDetails($place['place_id'], $apiKey);
                
                if ($details && !empty($details['formatted_phone_number'])) {
                    $results[] = [
                        'place_id' => $place['place_id'],
                        'name' => $place['name'] ?? '',
                        'phone' => $details['formatted_phone_number'] ?? '',
                        'international_phone' => $details['international_phone_number'] ?? '',
                        'address' => $place['vicinity'] ?? $details['formatted_address'] ?? '',
                        'rating' => $place['rating'] ?? null,
                        'category' => $place['types'][0] ?? '',
                        'website' => $details['website'] ?? '',
                        'lat' => $place['geometry']['location']['lat'] ?? null,
                        'lng' => $place['geometry']['location']['lng'] ?? null
                    ];
                }
                
                // Delay entre requisições de detalhes
                usleep(self::REQUEST_DELAY_MS * 1000);
                
                // Respeitar limite de resultados
                $maxResults = $config['max_results'] ?? self::DEFAULT_MAX_RESULTS;
                if (count($results) >= $maxResults) {
                    break;
                }
            }
            
        } catch (RequestException $e) {
            throw new \Exception("Erro na requisição Google Places: " . $e->getMessage());
        }
        
        return [
            'results' => $results,
            'next_page_token' => $nextPageToken
        ];
    }
    
    /**
     * Obter detalhes de um lugar (para pegar telefone)
     */
    private static function getPlaceDetails(string $placeId, string $apiKey): ?array
    {
        try {
            $url = self::GOOGLE_PLACES_BASE_URL . '/details/json';
            $params = [
                'place_id' => $placeId,
                'fields' => 'formatted_phone_number,international_phone_number,formatted_address,website',
                'key' => $apiKey
            ];
            
            $response = self::getClient()->get($url, ['query' => $params]);
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($data['status'] === 'OK') {
                return $data['result'];
            }
            
        } catch (\Exception $e) {
            Logger::warning("Erro ao obter detalhes do place {$placeId}: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Geocodificar localização (texto para coordenadas)
     */
    private static function geocodeLocation(string $location, string $apiKey): ?array
    {
        try {
            $url = 'https://maps.googleapis.com/maps/api/geocode/json';
            $params = [
                'address' => $location,
                'key' => $apiKey
            ];
            
            $response = self::getClient()->get($url, ['query' => $params]);
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($data['status'] === 'OK' && !empty($data['results'])) {
                $geometry = $data['results'][0]['geometry']['location'];
                return [
                    'lat' => $geometry['lat'],
                    'lng' => $geometry['lng']
                ];
            }
            
        } catch (\Exception $e) {
            Logger::error("Erro ao geocodificar '{$location}': " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Buscar empresas via Outscraper API
     */
    private static function fetchFromOutscraper(array $config, ?string $pageToken = null): array
    {
        $apiKey = self::getOutscraperApiKey();
        if (empty($apiKey)) {
            throw new \Exception("API Key do Outscraper não configurada");
        }
        
        $client = self::getClient();
        $results = [];
        
        $keyword = $config['keyword'] ?? '';
        $location = $config['location'] ?? 'São Paulo, SP';
        $maxResults = $config['max_results'] ?? 100;
        
        // Outscraper usa query no formato "keyword, location"
        $query = "{$keyword}, {$location}";
        
        $params = [
            'query' => $query,
            'limit' => min($maxResults, 500), // Outscraper max 500
            'language' => $config['language'] ?? 'pt',
            'region' => 'br',
            'async' => false
        ];
        
        try {
            $response = $client->get(self::OUTSCRAPER_BASE_URL, [
                'query' => $params,
                'headers' => [
                    'X-API-KEY' => $apiKey
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!empty($data['data'])) {
                foreach ($data['data'] as $places) {
                    foreach ($places as $place) {
                        if (!empty($place['phone'])) {
                            $results[] = [
                                'place_id' => $place['place_id'] ?? $place['google_id'] ?? '',
                                'name' => $place['name'] ?? '',
                                'phone' => $place['phone'] ?? '',
                                'international_phone' => $place['phone'] ?? '',
                                'address' => $place['full_address'] ?? $place['address'] ?? '',
                                'rating' => $place['rating'] ?? null,
                                'category' => $place['type'] ?? $place['category'] ?? '',
                                'website' => $place['site'] ?? $place['website'] ?? '',
                                'email' => $place['email'] ?? '',
                                'lat' => $place['latitude'] ?? null,
                                'lng' => $place['longitude'] ?? null
                            ];
                        }
                    }
                }
            }
            
        } catch (RequestException $e) {
            throw new \Exception("Erro na requisição Outscraper: " . $e->getMessage());
        }
        
        return [
            'results' => $results,
            'next_page_token' => null // Outscraper não usa paginação por token
        ];
    }
    
    /**
     * Processar um lead e criar/atualizar contato
     */
    private static function processLead(array $lead, int $contactListId, int $sourceId): array
    {
        $placeId = $lead['place_id'] ?? '';
        $phone = self::normalizePhone($lead['phone'] ?? $lead['international_phone'] ?? '');
        
        if (empty($phone)) {
            return ['action' => 'skipped', 'reason' => 'Sem telefone'];
        }
        
        // Verificar duplicata por place_id
        if (!empty($placeId)) {
            $existing = self::findContactByPlaceId($placeId);
            if ($existing) {
                // Adicionar à lista se não estiver
                if (!ContactList::hasContact($contactListId, $existing['id'])) {
                    ContactList::addContact($contactListId, $existing['id']);
                    return ['action' => 'updated', 'contact_id' => $existing['id']];
                }
                return ['action' => 'skipped', 'reason' => 'Já existe na lista'];
            }
        }
        
        // Verificar duplicata por telefone
        $existingByPhone = Contact::findByPhoneNormalized($phone);
        if ($existingByPhone) {
            // Atualizar place_id se não tiver
            if (empty($existingByPhone['place_id']) && !empty($placeId)) {
                Contact::update($existingByPhone['id'], ['place_id' => $placeId]);
            }
            
            // Adicionar à lista se não estiver
            if (!ContactList::hasContact($contactListId, $existingByPhone['id'])) {
                ContactList::addContact($contactListId, $existingByPhone['id']);
                return ['action' => 'updated', 'contact_id' => $existingByPhone['id']];
            }
            return ['action' => 'skipped', 'reason' => 'Telefone já existe'];
        }
        
        // Criar novo contato
        $contactData = [
            'name' => $lead['name'] ?? 'Sem nome',
            'phone' => $phone,
            'place_id' => $placeId,
            'source' => 'google_maps',
            'company' => $lead['name'] ?? '',
            'address' => $lead['address'] ?? '',
            'rating' => $lead['rating'] ?? null,
            'category' => $lead['category'] ?? '',
            'custom_attributes' => json_encode([
                'source_id' => $sourceId,
                'website' => $lead['website'] ?? '',
                'email' => $lead['email'] ?? '',
                'lat' => $lead['lat'] ?? null,
                'lng' => $lead['lng'] ?? null,
                'synced_at' => date('Y-m-d H:i:s')
            ])
        ];
        
        // Extrair cidade/estado do endereço
        $addressParts = self::parseAddress($lead['address'] ?? '');
        if ($addressParts) {
            $contactData['city'] = $addressParts['city'] ?? null;
            $contactData['state'] = $addressParts['state'] ?? null;
        }
        
        $contactId = Contact::create($contactData);
        
        // Adicionar à lista
        ContactList::addContact($contactListId, $contactId, [
            'source' => 'google_maps',
            'place_id' => $placeId
        ]);
        
        Logger::info("Lead criado: {$lead['name']} - {$phone}");
        
        return ['action' => 'created', 'contact_id' => $contactId];
    }
    
    /**
     * Normalizar telefone para formato WhatsApp
     */
    private static function normalizePhone(string $phone): string
    {
        // Remover caracteres não numéricos
        $phone = preg_replace('/\D+/', '', $phone);
        
        // Se começa com 0, remover
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }
        
        // Adicionar código do Brasil se não tiver
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }
        
        // Validar tamanho (Brasil: 55 + DDD 2 + número 8-9 = 12-13 dígitos)
        if (strlen($phone) < 12 || strlen($phone) > 13) {
            return '';
        }
        
        return $phone;
    }
    
    /**
     * Buscar contato por place_id
     */
    private static function findContactByPlaceId(string $placeId): ?array
    {
        $sql = "SELECT * FROM contacts WHERE place_id = ? LIMIT 1";
        return Database::fetch($sql, [$placeId]);
    }
    
    /**
     * Extrair cidade e estado do endereço
     */
    private static function parseAddress(string $address): ?array
    {
        // Padrão brasileiro: "Rua X, 123 - Bairro, Cidade - UF, CEP"
        // ou "Rua X, 123, Bairro, Cidade - UF"
        
        $result = [
            'city' => null,
            'state' => null
        ];
        
        // Tentar extrair UF (2 letras maiúsculas)
        if (preg_match('/\b([A-Z]{2})\b/', $address, $matches)) {
            $result['state'] = $matches[1];
        }
        
        // Tentar extrair cidade (antes do " - UF")
        if (preg_match('/,\s*([^,\-]+)\s*-\s*[A-Z]{2}/', $address, $matches)) {
            $result['city'] = trim($matches[1]);
        }
        
        return $result;
    }
    
    /**
     * Testar conexão com as APIs
     */
    public static function testConnection(string $provider = 'google_places'): array
    {
        try {
            if ($provider === 'google_places') {
                $apiKey = self::getGoogleApiKey();
                if (empty($apiKey)) {
                    return [
                        'success' => false,
                        'message' => 'API Key do Google Places não configurada'
                    ];
                }
                
                // Fazer uma busca simples para testar
                $url = self::GOOGLE_PLACES_BASE_URL . '/nearbysearch/json';
                $params = [
                    'location' => '-23.5505,-46.6333', // São Paulo
                    'radius' => 100,
                    'keyword' => 'test',
                    'key' => $apiKey
                ];
                
                $response = self::getClient()->get($url, ['query' => $params]);
                $data = json_decode($response->getBody()->getContents(), true);
                
                if (in_array($data['status'], ['OK', 'ZERO_RESULTS'])) {
                    return [
                        'success' => true,
                        'message' => 'Conexão com Google Places API estabelecida com sucesso!'
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => 'Erro na API: ' . ($data['error_message'] ?? $data['status'])
                ];
                
            } elseif ($provider === 'outscraper') {
                $apiKey = self::getOutscraperApiKey();
                if (empty($apiKey)) {
                    return [
                        'success' => false,
                        'message' => 'API Key do Outscraper não configurada'
                    ];
                }
                
                // Outscraper não tem endpoint de teste, verificar apenas se a key existe
                return [
                    'success' => true,
                    'message' => 'API Key do Outscraper configurada. Teste real será feito na primeira busca.'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Provider não suportado: ' . $provider
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Preview de busca (sem salvar)
     */
    public static function preview(array $searchConfig, string $provider = 'google_places', int $limit = 5): array
    {
        try {
            $originalLimit = $searchConfig['max_results'] ?? 100;
            $searchConfig['max_results'] = $limit;
            
            if ($provider === 'outscraper') {
                $results = self::fetchFromOutscraper($searchConfig, null);
            } else {
                $results = self::fetchFromGooglePlaces($searchConfig, null);
            }
            
            return [
                'success' => true,
                'results' => array_slice($results['results'], 0, $limit),
                'total_found' => count($results['results']),
                'has_more' => !empty($results['next_page_token']) || count($results['results']) >= $limit
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'results' => []
            ];
        }
    }
}
