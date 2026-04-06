<?php
/**
 * Controller VendorBroadcastController
 * Disparos de templates NotificaMe por vendedores
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Auth;
use App\Helpers\Permission;
use App\Services\VendorBroadcastService;

class VendorBroadcastController
{
    /**
     * Página principal de disparos do vendedor
     */
    public function index(): void
    {
        $user = Auth::user();
        $agentId = (int)Request::get('agent_id', $user['id']);

        // Admin pode ver qualquer agente, agente só vê o próprio
        $canViewAll = Permission::can('conversations.view.all');
        if ($agentId !== (int)$user['id'] && !$canViewAll) {
            $agentId = (int)$user['id'];
        }

        $agent = \App\Models\User::find($agentId);
        if (!$agent) {
            Response::redirect('/dashboard', 'error', 'Agente não encontrado');
            return;
        }

        $accounts = VendorBroadcastService::getAvailableAccounts();
        $sentToday = VendorBroadcastService::getSentToday($agentId);
        $remaining = VendorBroadcastService::getRemainingToday($agentId);
        $history = VendorBroadcastService::getBroadcastHistory($agentId);

        Response::view('vendor-broadcast/index', [
            'agent' => $agent,
            'accounts' => $accounts,
            'sentToday' => $sentToday,
            'remaining' => $remaining,
            'dailyLimit' => VendorBroadcastService::DAILY_LIMIT,
            'history' => $history,
            'title' => 'Disparos - ' . ($agent['name'] ?? 'Vendedor'),
        ]);
    }

    /**
     * API: Listar templates de uma conta
     */
    public function templates(): void
    {
        $accountId = (int)Request::get('account_id');
        if ($accountId < 1) {
            Response::json(['success' => false, 'message' => 'account_id inválido'], 400);
            return;
        }

        try {
            $templates = VendorBroadcastService::getTemplates($accountId);
            Response::json(['success' => true, 'data' => $templates]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Listar clientes do vendedor
     */
    public function clients(): void
    {
        $user = Auth::user();
        $agentId = (int)Request::get('agent_id', $user['id']);

        $canViewAll = Permission::can('conversations.view.all');
        if ($agentId !== (int)$user['id'] && !$canViewAll) {
            $agentId = (int)$user['id'];
        }

        $search = Request::get('search', '');
        $limit = min((int)Request::get('limit', 100), 200);

        try {
            $clients = VendorBroadcastService::getVendorClients($agentId, $search ?: null, $limit);
            Response::json(['success' => true, 'data' => $clients]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Enviar disparo
     */
    public function send(): void
    {
        $user = Auth::user();
        $agentId = (int)$user['id'];

        $accountId = (int)Request::post('account_id', 0);
        $templateName = trim(Request::post('template_name', ''));
        $templateLanguage = trim(Request::post('template_language', 'pt_BR'));
        $contacts = Request::post('contacts', []);
        $templateParams = Request::post('template_params', []);

        if ($accountId < 1 || empty($templateName) || empty($contacts)) {
            Response::json([
                'success' => false,
                'message' => 'Preencha todos os campos: conta, template e contatos.'
            ], 400);
            return;
        }

        if (!is_array($contacts) || count($contacts) > VendorBroadcastService::DAILY_LIMIT) {
            Response::json([
                'success' => false,
                'message' => 'Máximo de ' . VendorBroadcastService::DAILY_LIMIT . ' contatos por dia.'
            ], 400);
            return;
        }

        try {
            $result = VendorBroadcastService::createBroadcast(
                $agentId,
                $accountId,
                $templateName,
                $templateLanguage,
                $contacts,
                $templateParams
            );
            $httpCode = ($result['success'] ?? false) ? 200 : 422;
            Response::json($result, $httpCode);
        } catch (\Exception $e) {
            error_log("VendorBroadcast send: " . $e->getMessage());
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Detalhes de um disparo
     */
    public function details(): void
    {
        $user = Auth::user();
        $broadcastId = (int)Request::get('id');
        $agentId = (int)$user['id'];

        // Admin pode ver de qualquer agente
        if (Permission::can('conversations.view.all')) {
            $sql = "SELECT agent_id FROM vendor_broadcasts WHERE id = ?";
            $row = \App\Helpers\Database::fetch($sql, [$broadcastId]);
            if ($row) $agentId = (int)$row['agent_id'];
        }

        $data = VendorBroadcastService::getBroadcastDetails($broadcastId, $agentId);
        if (!$data) {
            Response::json(['success' => false, 'message' => 'Disparo não encontrado'], 404);
            return;
        }

        Response::json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Limite restante hoje
     */
    public function limit(): void
    {
        $user = Auth::user();
        $agentId = (int)$user['id'];

        Response::json([
            'success' => true,
            'data' => [
                'sent_today' => VendorBroadcastService::getSentToday($agentId),
                'remaining' => VendorBroadcastService::getRemainingToday($agentId),
                'daily_limit' => VendorBroadcastService::DAILY_LIMIT,
            ]
        ]);
    }
}
