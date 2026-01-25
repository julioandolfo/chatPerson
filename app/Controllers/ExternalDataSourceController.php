<?php
/**
 * Controller ExternalDataSourceController
 * Gerenciamento de fontes de dados externas
 */

namespace App\Controllers;

use App\Services\ExternalDataSourceService;
use App\Models\ExternalDataSource;
use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;

class ExternalDataSourceController
{
    /**
     * Listar fontes
     */
    public function index(): void
    {
        Permission::abortIfCannot('campaigns.view');

        $sources = ExternalDataSource::all();

        Response::view('external-sources/index', [
            'sources' => $sources,
            'title' => 'Fontes de Dados Externas'
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        Permission::abortIfCannot('campaigns.create');

        Response::view('external-sources/create', [
            'title' => 'Nova Fonte de Dados'
        ]);
    }

    /**
     * Salvar nova fonte
     */
    public function store(): void
    {
        Permission::abortIfCannot('campaigns.create');

        try {
            $data = Request::json();
            $data['created_by'] = Auth::id();

            $sourceId = ExternalDataSourceService::create($data);

            Response::json([
                'success' => true,
                'message' => 'Fonte criada com sucesso!',
                'source_id' => $sourceId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Testar conexão
     */
    public function testConnection(): void
    {
        Permission::abortIfCannot('campaigns.create');

        \App\Helpers\Logger::info('=== TESTE DE CONEXÃO EXTERNA INICIADO ===');

        try {
            $data = Request::json();
            
            $logInfo = json_encode([
                'type' => $data['type'] ?? 'não informado',
                'host' => $data['connection_config']['host'] ?? 'não informado',
                'port' => $data['connection_config']['port'] ?? 'não informado',
                'database' => $data['connection_config']['database'] ?? 'não informado',
                'username' => $data['connection_config']['username'] ?? 'não informado',
                'has_password' => !empty($data['connection_config']['password'])
            ], JSON_UNESCAPED_UNICODE);
            
            \App\Helpers\Logger::info('Dados recebidos: ' . $logInfo);
            
            $result = ExternalDataSourceService::testConnection(
                $data['connection_config'] ?? [],
                $data['type'] ?? 'mysql'
            );

            \App\Helpers\Logger::info('Resultado: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

            Response::json($result);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error('ERRO ao testar conexão: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
            
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_detail' => 'Verifique os logs em view-all-logs.php'
            ], 400);
        }
    }

    /**
     * Listar tabelas
     */
    public function listTables(int $sourceId): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $result = ExternalDataSourceService::listTables($sourceId);
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Listar colunas
     */
    public function listColumns(int $sourceId): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $tableName = Request::get('table');
            if (empty($tableName)) {
                throw new \Exception('Nome da tabela é obrigatório');
            }
            
            $result = ExternalDataSourceService::listColumns($sourceId, $tableName);
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Preview de dados
     */
    public function preview(int $sourceId): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            // Permitir passar tabela como parâmetro (útil durante configuração)
            $tableName = Request::get('table');
            $result = ExternalDataSourceService::previewData($sourceId, $tableName);
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sincronizar fonte
     */
    public function sync(int $sourceId): void
    {
        Permission::abortIfCannot('campaigns.create');

        try {
            $data = Request::json();
            $listId = $data['list_id'] ?? null;
            
            if (empty($listId)) {
                throw new \Exception('ID da lista é obrigatório');
            }
            
            $result = ExternalDataSourceService::sync($sourceId, (int)$listId);
            Response::json($result);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error('Erro ao sincronizar fonte: ' . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deletar fonte
     */
    public function destroy(int $sourceId): void
    {
        Permission::abortIfCannot('campaigns.delete');

        try {
            ExternalDataSource::delete($sourceId);

            Response::json([
                'success' => true,
                'message' => 'Fonte removida com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Testar conexão com Google Maps API
     */
    public function testGoogleMapsConnection(): void
    {
        Permission::abortIfCannot('campaigns.create');

        try {
            $data = Request::json();
            $provider = $data['provider'] ?? 'google_places';
            
            $result = \App\Services\GoogleMapsProspectService::testConnection($provider);
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Preview de busca no Google Maps
     */
    public function previewGoogleMaps(): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $data = Request::json();
            $searchConfig = $data['search_config'] ?? [];
            $provider = $data['provider'] ?? 'google_places';
            
            if (empty($searchConfig['keyword']) || empty($searchConfig['location'])) {
                throw new \Exception('Palavra-chave e localização são obrigatórios');
            }
            
            $result = ExternalDataSourceService::previewGoogleMaps($searchConfig, $provider, 5);
            Response::json($result);
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
    public function testWooCommerceConnection(): void
    {
        Permission::abortIfCannot('campaigns.create');

        try {
            $data = Request::json();
            $storeUrl = $data['store_url'] ?? '';
            $consumerKey = $data['consumer_key'] ?? '';
            $consumerSecret = $data['consumer_secret'] ?? '';
            
            if (empty($storeUrl) || empty($consumerKey) || empty($consumerSecret)) {
                throw new \Exception('URL da loja, Consumer Key e Consumer Secret são obrigatórios');
            }
            
            $result = \App\Services\WooCommerceProspectService::testConnection($storeUrl, $consumerKey, $consumerSecret);
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Preview de clientes do WooCommerce
     */
    public function previewWooCommerce(): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $data = Request::json();
            $storeUrl = $data['store_url'] ?? '';
            $consumerKey = $data['consumer_key'] ?? '';
            $consumerSecret = $data['consumer_secret'] ?? '';
            
            if (empty($storeUrl) || empty($consumerKey) || empty($consumerSecret)) {
                throw new \Exception('URL da loja, Consumer Key e Consumer Secret são obrigatórios');
            }
            
            $result = ExternalDataSourceService::previewWooCommerce($storeUrl, $consumerKey, $consumerSecret, 10);
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
