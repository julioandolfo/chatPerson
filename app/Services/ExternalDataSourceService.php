<?php
/**
 * Service ExternalDataSourceService
 * Gerenciamento de fontes de dados externas
 */

namespace App\Services;

use App\Models\ExternalDataSource;
use App\Models\ContactList;
use App\Models\Contact;
use App\Helpers\Validator;
use App\Helpers\Logger;

class ExternalDataSourceService
{
    /**
     * Criar fonte de dados externa
     */
    public static function create(array $data): int
    {
        // Validação diferente para google_maps e woocommerce
        $type = $data['type'] ?? '';
        
        if ($type === 'google_maps' || $type === 'woocommerce') {
            $errors = Validator::validate($data, [
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:mysql,postgresql,api,google_maps,woocommerce',
                'search_config' => 'required|array'
            ]);
        } else {
            $errors = Validator::validate($data, [
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:mysql,postgresql,api,google_maps,woocommerce',
                'connection_config' => 'required|array'
            ]);
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Serializar configs
        if (isset($data['connection_config']) && is_array($data['connection_config'])) {
            $data['connection_config'] = json_encode($data['connection_config']);
        }
        if (isset($data['column_mapping']) && is_array($data['column_mapping'])) {
            $data['column_mapping'] = json_encode($data['column_mapping']);
        }
        if (isset($data['query_config']) && is_array($data['query_config'])) {
            $data['query_config'] = json_encode($data['query_config']);
        }
        if (isset($data['search_config']) && is_array($data['search_config'])) {
            $data['search_config'] = json_encode($data['search_config']);
        }
        
        // Definir valor padrão para connection_config se não fornecido (Google Maps, WooCommerce)
        if (!isset($data['connection_config']) || empty($data['connection_config'])) {
            $data['connection_config'] = '{}';
        }

        return ExternalDataSource::create($data);
    }

    /**
     * Testar conexão com fonte externa
     */
    public static function testConnection(array $connectionConfig, string $type, ?string $provider = null): array
    {
        // Para Google Maps, delegar para o service específico
        if ($type === 'google_maps') {
            $provider = $provider ?? 'google_places';
            return GoogleMapsProspectService::testConnection($provider);
        }
        
        // Para WooCommerce, delegar para o service específico
        if ($type === 'woocommerce') {
            $storeUrl = $connectionConfig['store_url'] ?? '';
            $consumerKey = $connectionConfig['consumer_key'] ?? '';
            $consumerSecret = $connectionConfig['consumer_secret'] ?? '';
            return WooCommerceProspectService::testConnection($storeUrl, $consumerKey, $consumerSecret);
        }
        
        $logInfo = json_encode([
            'type' => $type,
            'host' => $connectionConfig['host'] ?? 'não definido',
            'port' => $connectionConfig['port'] ?? 'não definido',
            'database' => $connectionConfig['database'] ?? 'não definido',
            'username' => $connectionConfig['username'] ?? 'não definido'
        ], JSON_UNESCAPED_UNICODE);
        
        Logger::info('ExternalDataSourceService::testConnection - Iniciando: ' . $logInfo);
        
        try {
            Logger::info('ExternalDataSourceService::testConnection - Criando conexão PDO');
            
            $connection = self::createConnection($connectionConfig, $type);
            
            Logger::info('ExternalDataSourceService::testConnection - Conexão PDO criada, executando SELECT 1');
            
            // Testar query simples
            $result = $connection->query("SELECT 1 as test")->fetch();
            
            Logger::info('ExternalDataSourceService::testConnection - Query executada: ' . json_encode($result));
            
            if ($result && $result['test'] == 1) {
                Logger::info('ExternalDataSourceService::testConnection - Teste bem-sucedido');
                return [
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso!'
                ];
            }
            
            Logger::warning('ExternalDataSourceService::testConnection - Query retornou resultado inesperado');
            return [
                'success' => false,
                'message' => 'Falha ao testar conexão'
            ];
            
        } catch (\PDOException $e) {
            Logger::error('ExternalDataSourceService::testConnection - Erro PDO [' . $e->getCode() . ']: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Erro de conexão PDO: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        } catch (\Exception $e) {
            Logger::error('ExternalDataSourceService::testConnection - Erro geral: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            
            return [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Criar conexão PDO com banco externo
     */
    private static function createConnection(array $config, string $type): \PDO
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? ($type === 'postgresql' ? 5432 : 3306);
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        
        $logInfo = "type={$type}, host={$host}, port={$port}, database={$database}, username={$username}, has_password=" . (!empty($password) ? 'yes' : 'no');
        Logger::info('ExternalDataSourceService::createConnection - Preparando: ' . $logInfo);
        
        $driverMap = [
            'mysql' => 'mysql',
            'postgresql' => 'pgsql',
            'sqlserver' => 'sqlsrv'
        ];
        
        $driver = $driverMap[$type] ?? 'mysql';
        $dsn = "{$driver}:host={$host};port={$port};dbname={$database}";
        
        if ($type === 'mysql') {
            $dsn .= ';charset=utf8mb4';
        }
        
        Logger::info('ExternalDataSourceService::createConnection - DSN: ' . $dsn);
        
        try {
            Logger::info('ExternalDataSourceService::createConnection - Tentando criar PDO');
            
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 5 // Timeout de 5 segundos
            ]);
            
            Logger::info('ExternalDataSourceService::createConnection - PDO criado com sucesso');
            
            return $pdo;
            
        } catch (\PDOException $e) {
            Logger::error('ExternalDataSourceService::createConnection - Erro PDO [' . $e->getCode() . ']: ' . $e->getMessage() . ' | DSN: ' . $dsn);
            throw $e;
        }
    }

    /**
     * Listar tabelas do banco externo
     */
    public static function listTables(int $sourceId): array
    {
        try {
            $source = ExternalDataSource::find($sourceId);
            if (!$source) {
                throw new \Exception('Fonte não encontrada');
            }
            
            $connectionConfig = json_decode($source['connection_config'], true);
            $connection = self::createConnection($connectionConfig, $source['type']);
            
            $tables = [];
            
            if ($source['type'] === 'mysql') {
                $stmt = $connection->query("SHOW TABLES");
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
            } elseif ($source['type'] === 'postgresql') {
                $stmt = $connection->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
                while ($row = $stmt->fetch()) {
                    $tables[] = $row['table_name'];
                }
            }
            
            return [
                'success' => true,
                'tables' => $tables
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Listar colunas de uma tabela
     */
    public static function listColumns(int $sourceId, string $tableName): array
    {
        try {
            $source = ExternalDataSource::find($sourceId);
            if (!$source) {
                throw new \Exception('Fonte não encontrada');
            }
            
            $connectionConfig = json_decode($source['connection_config'], true);
            $connection = self::createConnection($connectionConfig, $source['type']);
            
            $columns = [];
            
            if ($source['type'] === 'mysql') {
                $stmt = $connection->query("SHOW COLUMNS FROM `{$tableName}`");
                while ($row = $stmt->fetch()) {
                    $columns[] = [
                        'name' => $row['Field'],
                        'type' => $row['Type'],
                        'nullable' => $row['Null'] === 'YES'
                    ];
                }
            } elseif ($source['type'] === 'postgresql') {
                $stmt = $connection->query("SELECT column_name, data_type, is_nullable 
                                           FROM information_schema.columns 
                                           WHERE table_name = '{$tableName}'");
                while ($row = $stmt->fetch()) {
                    $columns[] = [
                        'name' => $row['column_name'],
                        'type' => $row['data_type'],
                        'nullable' => $row['is_nullable'] === 'YES'
                    ];
                }
            }
            
            return [
                'success' => true,
                'columns' => $columns
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Preview de dados (primeiras 10 linhas)
     */
    public static function previewData(int $sourceId, ?string $tableOverride = null): array
    {
        try {
            $source = ExternalDataSource::find($sourceId);
            if (!$source) {
                throw new \Exception('Fonte não encontrada');
            }
            
            // Permitir override da tabela (útil durante configuração)
            $tableName = $tableOverride ?: $source['table_name'];
            
            if (empty($tableName)) {
                throw new \Exception('Tabela não configurada. Selecione uma tabela primeiro.');
            }
            
            $connectionConfig = json_decode($source['connection_config'], true);
            $connection = self::createConnection($connectionConfig, $source['type']);
            
            $queryConfig = json_decode($source['query_config'], true) ?? [];
            $whereClause = $queryConfig['where'] ?? '';
            $orderBy = $queryConfig['order_by'] ?? '';
            
            $sql = "SELECT * FROM `{$tableName}`";
            
            if ($whereClause) {
                $sql .= " WHERE {$whereClause}";
            }
            
            if ($orderBy) {
                $sql .= " ORDER BY {$orderBy}";
            }
            
            $sql .= " LIMIT 10";
            
            $stmt = $connection->query($sql);
            $rows = $stmt->fetchAll();
            
            return [
                'success' => true,
                'rows' => $rows,
                'total_preview' => count($rows)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Sincronizar dados da fonte externa
     */
    public static function sync(int $sourceId, int $contactListId): array
    {
        $startTime = microtime(true);
        $logData = [
            'started_at' => date('Y-m-d H:i:s'),
            'records_fetched' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_failed' => 0
        ];

        try {
            $source = ExternalDataSource::find($sourceId);
            if (!$source) {
                throw new \Exception('Fonte não encontrada');
            }

            // Para Google Maps, delegar para o service específico
            if ($source['type'] === 'google_maps') {
                return GoogleMapsProspectService::sync($sourceId, $contactListId);
            }
            
            // Para WooCommerce, delegar para o service específico
            if ($source['type'] === 'woocommerce') {
                return WooCommerceProspectService::sync($sourceId, $contactListId);
            }

            if (empty($source['table_name']) || empty($source['column_mapping'])) {
                throw new \Exception('Fonte não configurada corretamente');
            }

            $connectionConfig = json_decode($source['connection_config'], true);
            $columnMapping = json_decode($source['column_mapping'], true);
            $queryConfig = json_decode($source['query_config'], true) ?? [];
            
            $connection = self::createConnection($connectionConfig, $source['type']);
            
            // Construir query
            $sql = "SELECT * FROM `{$source['table_name']}`";
            
            if (!empty($queryConfig['where'])) {
                $sql .= " WHERE {$queryConfig['where']}";
            }
            
            if (!empty($queryConfig['order_by'])) {
                $sql .= " ORDER BY {$queryConfig['order_by']}";
            }
            
            if (!empty($queryConfig['limit'])) {
                $sql .= " LIMIT {$queryConfig['limit']}";
            }
            
            Logger::info("ExternalDataSource::sync - Query: {$sql}");
            
            $stmt = $connection->query($sql);
            $rows = $stmt->fetchAll();
            
            $logData['records_fetched'] = count($rows);
            
            // Processar cada linha
            foreach ($rows as $row) {
                try {
                    $contactData = self::mapRowToContact($row, $columnMapping);
                    
                    if (empty($contactData['phone'])) {
                        $logData['records_failed']++;
                        continue;
                    }
                    
                    // Verificar se contato já existe
                    $existingContact = Contact::findByPhoneNormalized($contactData['phone']);
                    
                    if ($existingContact) {
                        // Atualizar
                        Contact::update($existingContact['id'], $contactData);
                        
                        // Adicionar à lista se ainda não estiver
                        ContactListService::addContact($contactListId, $existingContact['id']);
                        
                        $logData['records_updated']++;
                    } else {
                        // Criar novo
                        $contactId = Contact::create($contactData);
                        
                        // Adicionar à lista
                        ContactListService::addContact($contactListId, $contactId);
                        
                        $logData['records_created']++;
                    }
                    
                } catch (\Exception $e) {
                    Logger::error("Erro ao processar linha: " . $e->getMessage());
                    $logData['records_failed']++;
                }
            }
            
            $logData['completed_at'] = date('Y-m-d H:i:s');
            $logData['status'] = 'success';
            $logData['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);
            
            // Atualizar fonte
            ExternalDataSource::updateSyncStatus(
                $sourceId, 
                'success', 
                "Sincronizado {$logData['records_created']} novos, {$logData['records_updated']} atualizados",
                $logData['records_fetched']
            );
            
            // Salvar log
            ExternalDataSource::logSync($sourceId, $logData);
            
            // Atualizar last_sync_at da lista
            ContactList::update($contactListId, [
                'last_sync_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'message' => 'Sincronização concluída',
                'stats' => $logData
            ];
            
        } catch (\Exception $e) {
            $logData['completed_at'] = date('Y-m-d H:i:s');
            $logData['status'] = 'error';
            $logData['error_message'] = $e->getMessage();
            $logData['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);
            
            ExternalDataSource::updateSyncStatus($sourceId, 'error', $e->getMessage());
            ExternalDataSource::logSync($sourceId, $logData);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'stats' => $logData
            ];
        }
    }

    /**
     * Mapear linha do banco externo para dados de contato
     */
    private static function mapRowToContact(array $row, array $mapping): array
    {
        $contactData = [];
        
        // Campos principais
        foreach (['name', 'phone', 'email'] as $field) {
            if (!empty($mapping[$field]) && isset($row[$mapping[$field]])) {
                $contactData[$field] = $row[$mapping[$field]];
            }
        }
        
        // Campos customizados
        if (!empty($mapping['custom_fields']) && is_array($mapping['custom_fields'])) {
            $customData = [];
            foreach ($mapping['custom_fields'] as $targetField => $sourceColumn) {
                if (isset($row[$sourceColumn])) {
                    $customData[$targetField] = $row[$sourceColumn];
                }
            }
            if (!empty($customData)) {
                $contactData['custom_fields'] = json_encode($customData);
            }
        }
        
        return $contactData;
    }

    /**
     * Processar todas as fontes pendentes (para cron)
     */
    public static function processPending(): void
    {
        $sources = ExternalDataSource::getNeedingSync();
        
        Logger::info("ExternalDataSource::processPending - " . count($sources) . " fonte(s) pendente(s)");
        
        foreach ($sources as $source) {
            try {
                // Buscar listas vinculadas a esta fonte
                $sql = "SELECT id FROM contact_lists WHERE external_source_id = ? AND sync_enabled = TRUE";
                $lists = \App\Helpers\Database::fetchAll($sql, [$source['id']]);
                
                foreach ($lists as $list) {
                    Logger::info("Sincronizando fonte #{$source['id']} (tipo: {$source['type']}) para lista #{$list['id']}");
                    
                    // Delegar para o service apropriado baseado no tipo
                    if ($source['type'] === 'google_maps') {
                        GoogleMapsProspectService::sync($source['id'], $list['id']);
                    } elseif ($source['type'] === 'woocommerce') {
                        WooCommerceProspectService::sync($source['id'], $list['id']);
                    } else {
                        self::sync($source['id'], $list['id']);
                    }
                }
                
            } catch (\Exception $e) {
                Logger::error("Erro ao processar fonte #{$source['id']}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Preview de busca do Google Maps (sem salvar)
     */
    public static function previewGoogleMaps(array $searchConfig, string $provider = 'google_places', int $limit = 5): array
    {
        return GoogleMapsProspectService::preview($searchConfig, $provider, $limit);
    }
    
    /**
     * Preview de clientes do WooCommerce (sem salvar)
     */
    public static function previewWooCommerce(string $storeUrl, string $consumerKey, string $consumerSecret, int $limit = 10): array
    {
        return WooCommerceProspectService::preview($storeUrl, $consumerKey, $consumerSecret, $limit);
    }

    /**
     * Obter contatos com ordem customizada
     */
    public static function getContactsWithOrder(int $listId): array
    {
        $list = ContactList::find($listId);
        if (!$list) {
            return [];
        }

        $sendOrder = $list['send_order'] ?? 'default';
        $sendOrderConfig = !empty($list['send_order_config']) ? json_decode($list['send_order_config'], true) : [];

        $sql = "SELECT c.* 
                FROM contacts c
                INNER JOIN contact_list_items cli ON c.id = cli.contact_id
                WHERE cli.list_id = ?";

        switch ($sendOrder) {
            case 'random':
                $sql .= " ORDER BY RAND()";
                break;
                
            case 'asc':
                $field = $sendOrderConfig['field'] ?? 'id';
                $sql .= " ORDER BY c.`{$field}` ASC";
                break;
                
            case 'desc':
                $field = $sendOrderConfig['field'] ?? 'id';
                $sql .= " ORDER BY c.`{$field}` DESC";
                break;
                
            case 'custom':
                if (!empty($sendOrderConfig['order_by'])) {
                    $sql .= " ORDER BY {$sendOrderConfig['order_by']}";
                }
                if (!empty($sendOrderConfig['where'])) {
                    $sql = str_replace('WHERE cli.list_id = ?', 
                                      "WHERE cli.list_id = ? AND ({$sendOrderConfig['where']})", 
                                      $sql);
                }
                break;
                
            default: // 'default'
                $sql .= " ORDER BY cli.created_at ASC";
        }

        return \App\Helpers\Database::fetchAll($sql, [$listId]);
    }
}
