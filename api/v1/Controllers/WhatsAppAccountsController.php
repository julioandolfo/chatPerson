<?php
/**
 * Controller de Contas WhatsApp para API REST
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use App\Helpers\Database;

class WhatsAppAccountsController
{
    /**
     * Listar todas as contas WhatsApp
     * 
     * GET /api/v1/whatsapp-accounts
     * 
     * Query params:
     * - status: Filtrar por status (active, inactive, disconnected)
     * - page: Página atual (padrão: 1)
     * - per_page: Itens por página (padrão: 20, máximo: 100)
     */
    public function index()
    {
        try {
            // Obter parâmetros da query string
            $status = $_GET['status'] ?? null;
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(100, max(1, intval($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            
            $db = Database::getInstance();
            
            // Query base
            $where = ['1=1'];
            $params = [];
            
            // Filtro de status
            if ($status) {
                $where[] = 'status = ?';
                $params[] = $status;
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Contar total
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM integration_accounts WHERE channel = 'whatsapp' AND {$whereClause}");
            $stmt->execute($params);
            $total = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Buscar contas com paginação
            $stmt = $db->prepare("
                SELECT 
                    id,
                    name,
                    phone_number,
                    provider,
                    api_url,
                    status,
                    default_funnel_id,
                    default_stage_id,
                    created_at,
                    updated_at
                FROM integration_accounts 
                WHERE channel = 'whatsapp' AND {$whereClause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute(array_merge($params, [$perPage, $offset]));
            $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Buscar nomes dos funis e etapas padrão
            foreach ($accounts as &$account) {
                if ($account['default_funnel_id']) {
                    $stmt = $db->prepare("SELECT name FROM funnels WHERE id = ?");
                    $stmt->execute([$account['default_funnel_id']]);
                    $funnel = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $account['default_funnel_name'] = $funnel['name'] ?? null;
                }
                
                if ($account['default_stage_id']) {
                    $stmt = $db->prepare("SELECT name FROM funnel_stages WHERE id = ?");
                    $stmt->execute([$account['default_stage_id']]);
                    $stage = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $account['default_stage_name'] = $stage['name'] ?? null;
                }
            }
            
            // Calcular paginação
            $totalPages = ceil($total / $perPage);
            
            ApiResponse::success([
                'accounts' => $accounts,
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao buscar contas WhatsApp', $e);
        }
    }
    
    /**
     * Obter uma conta WhatsApp específica
     * 
     * GET /api/v1/whatsapp-accounts/:id
     */
    public function show($id)
    {
        try {
            $db = Database::getInstance();
            
            // Buscar conta
            $stmt = $db->prepare("
                SELECT 
                    id,
                    name,
                    phone_number,
                    provider,
                    api_url,
                    status,
                    default_funnel_id,
                    default_stage_id,
                    wavoip_enabled,
                    new_conv_limit_enabled,
                    new_conv_limit_count,
                    new_conv_limit_period_value,
                    new_conv_limit_period,
                    last_connection_check,
                    last_connection_result,
                    consecutive_failures,
                    created_at,
                    updated_at
                FROM integration_accounts 
                WHERE id = ?
            ");
            
            $stmt->execute([$id]);
            $account = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$account) {
                ApiResponse::notFound('Conta WhatsApp não encontrada');
            }
            
            // Buscar nome do funil padrão
            if ($account['default_funnel_id']) {
                $stmt = $db->prepare("SELECT name FROM funnels WHERE id = ?");
                $stmt->execute([$account['default_funnel_id']]);
                $funnel = $stmt->fetch(\PDO::FETCH_ASSOC);
                $account['default_funnel_name'] = $funnel['name'] ?? null;
            }
            
            // Buscar nome da etapa padrão
            if ($account['default_stage_id']) {
                $stmt = $db->prepare("SELECT name FROM funnel_stages WHERE id = ?");
                $stmt->execute([$account['default_stage_id']]);
                $stage = $stmt->fetch(\PDO::FETCH_ASSOC);
                $account['default_stage_name'] = $stage['name'] ?? null;
            }
            
            // Converter valores booleanos
            $account['wavoip_enabled'] = (bool) $account['wavoip_enabled'];
            $account['new_conv_limit_enabled'] = (bool) $account['new_conv_limit_enabled'];
            
            ApiResponse::success($account);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao buscar conta WhatsApp', $e);
        }
    }
}
