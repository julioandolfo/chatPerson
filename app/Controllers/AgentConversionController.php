<?php
/**
 * Controller AgentConversionController
 * Métricas de conversão Lead → Venda (WooCommerce)
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\AgentConversionService;
use App\Models\User;

class AgentConversionController
{
    /**
     * Dashboard de conversão - listagem de vendedores
     */
    public function index(): void
    {
        Permission::abortIfCannot('conversations.view.all'); // Reutilizar permissão existente
        
        try {
            $dateFrom = Request::get('date_from', date('Y-m-01'));
            $dateTo = Request::get('date_to', date('Y-m-d'));
            
            // Ranking de conversão
            $ranking = AgentConversionService::getRanking($dateFrom, $dateTo, 20);
            
            // Vendedores cadastrados
            $sellers = User::getSellers();
            
            Response::view('agent-conversion/index', [
                'ranking' => $ranking,
                'sellers' => $sellers,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'title' => 'Conversão WooCommerce'
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao carregar dashboard de conversão: " . $e->getMessage());
            Response::redirect('/dashboard', 'error', 'Erro ao carregar métricas de conversão');
        }
    }
    
    /**
     * Detalhes de conversão de um agente específico
     */
    public function show(): void
    {
        Permission::abortIfCannot('conversations.view.all');
        
        $agentId = (int)Request::get('id');
        
        try {
            $dateFrom = Request::get('date_from', date('Y-m-01'));
            $dateTo = Request::get('date_to', date('Y-m-d'));
            
            // Métricas do agente
            $metrics = AgentConversionService::getConversionMetrics($agentId, $dateFrom, $dateTo);
            
            // Pedidos do agente
            $orders = AgentConversionService::getAgentOrders($agentId, $dateFrom, $dateTo);
            
            Response::view('agent-conversion/show', [
                'metrics' => $metrics,
                'orders' => $orders,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'title' => 'Conversão: ' . ($metrics['agent_name'] ?? 'Agente')
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao carregar detalhes de conversão: " . $e->getMessage());
            Response::redirect('/agent-conversion', 'error', 'Erro ao carregar detalhes');
        }
    }
    
    /**
     * API: Obter métricas de conversão (JSON)
     */
    public function getMetrics(): void
    {
        Permission::abortIfCannot('conversations.view.all');
        
        $agentId = (int)Request::get('agent_id');
        $dateFrom = Request::get('date_from', date('Y-m-01'));
        $dateTo = Request::get('date_to', date('Y-m-d'));
        
        try {
            $metrics = AgentConversionService::getConversionMetrics($agentId, $dateFrom, $dateTo);
            Response::json(['success' => true, 'data' => $metrics]);
        } catch (\Exception $e) {
            error_log("Erro ao obter métricas: " . $e->getMessage());
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * API: Testar meta_key do vendedor no WooCommerce
     */
    public function testSellerMetaKey(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        $integrationId = (int)Request::post('integration_id');
        $metaKey = Request::post('meta_key', '_vendor_id');
        
        try {
            $result = \App\Services\WooCommerceIntegrationService::testSellerMetaKey($integrationId, $metaKey);
            Response::json($result);
        } catch (\Exception $e) {
            error_log("Erro ao testar meta_key: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro ao testar: ' . $e->getMessage()
            ], 500);
        }
    }
}
