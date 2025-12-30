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
}

