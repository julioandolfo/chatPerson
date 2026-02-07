<?php
/**
 * Controller VoiceCallController
 * Gerenciamento de chamadas de voz via WavoIP
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\VoiceCallService;
use App\Models\VoiceCall;
use App\Models\IntegrationAccount;
use App\Models\Contact;
use App\Models\Conversation;

class VoiceCallController
{
    /**
     * Listar chamadas de voz
     */
    public function index(): void
    {
        Permission::abortIfCannot('voice_calls.view');
        
        $filters = Request::get();
        $page = (int)($filters['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $db = \App\Helpers\Database::getInstance();
        
        $where = ['1=1'];
        $params = [];

        // Filtros
        if (!empty($filters['contact_id'])) {
            $where[] = 'vc.contact_id = ?';
            $params[] = $filters['contact_id'];
        }

        if (!empty($filters['agent_id'])) {
            $where[] = 'vc.agent_id = ?';
            $params[] = $filters['agent_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'vc.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['whatsapp_account_id'])) {
            $where[] = 'vc.whatsapp_account_id = ?';
            $params[] = $filters['whatsapp_account_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'vc.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'vc.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT vc.*, 
                       c.name as contact_name, c.phone as contact_phone,
                       u.name as agent_name,
                       wa.name as whatsapp_account_name,
                       conv.id as conversation_id
                FROM voice_calls vc
                LEFT JOIN contacts c ON vc.contact_id = c.id
                LEFT JOIN users u ON vc.agent_id = u.id
                LEFT JOIN integration_accounts wa ON vc.whatsapp_account_id = wa.id
                LEFT JOIN conversations conv ON vc.conversation_id = conv.id
                WHERE {$whereClause}
                ORDER BY vc.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        $calls = $db->fetchAll($sql, $params);

        // Processar metadata JSON
        foreach ($calls as &$call) {
            if (!empty($call['metadata'])) {
                $call['metadata'] = json_decode($call['metadata'], true) ?? [];
            } else {
                $call['metadata'] = [];
            }
        }

        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM voice_calls vc WHERE {$whereClause}";
        $total = $db->fetch($countSql, array_slice($params, 0, -2))['total'] ?? 0;

        Response::view('voice-calls/index', [
            'calls' => $calls,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'filters' => $filters,
            'whatsapp_accounts' => IntegrationAccount::getActiveWhatsApp(),
            'agents' => \App\Models\User::where('role', 'IN', ['agent', 'senior_agent', 'supervisor'])
        ]);
    }

    /**
     * Criar nova chamada de voz
     */
    public function create(): void
    {
        Permission::abortIfCannot('voice_calls.create');
        
        try {
            $data = Request::post();
            
            $call = VoiceCallService::createCall($data);
            
            Response::json([
                'success' => true,
                'message' => 'Chamada iniciada com sucesso!',
                'call' => $call
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao iniciar chamada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter detalhes de uma chamada
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('voice_calls.view');
        
        $call = VoiceCall::find($id);
        if (!$call) {
            Response::json([
                'success' => false,
                'message' => 'Chamada não encontrada'
            ], 404);
            return;
        }

        // Enriquecer com dados relacionados
        if ($call['contact_id']) {
            $call['contact'] = Contact::find($call['contact_id']);
        }
        if ($call['agent_id']) {
            $call['agent'] = \App\Models\User::find($call['agent_id']);
        }
        if ($call['whatsapp_account_id']) {
            $call['whatsapp_account'] = IntegrationAccount::find($call['whatsapp_account_id']);
        }
        if ($call['conversation_id']) {
            $call['conversation'] = Conversation::find($call['conversation_id']);
        }

        if (!empty($call['metadata'])) {
            $call['metadata'] = json_decode($call['metadata'], true) ?? [];
        }

        Response::json([
            'success' => true,
            'call' => $call
        ]);
    }

    /**
     * Encerrar chamada
     */
    public function end(int $id): void
    {
        Permission::abortIfCannot('voice_calls.end');
        
        try {
            VoiceCallService::endCall($id);
            
            Response::json([
                'success' => true,
                'message' => 'Chamada encerrada com sucesso!'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao encerrar chamada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook do WavoIP
     */
    public function webhook(): void
    {
        // Webhook não requer autenticação padrão, mas pode ter validação de token
        try {
            $data = Request::json();
            
            if (empty($data)) {
                $data = Request::post();
            }

            VoiceCallService::processWebhook($data);
            
            Response::json([
                'success' => true,
                'message' => 'Webhook processado'
            ]);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("VoiceCallController::webhook - Erro: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter estatísticas de chamadas
     */
    public function statistics(): void
    {
        Permission::abortIfCannot('voice_calls.view');
        
        $filters = Request::get();
        
        $stats = VoiceCallService::getStatistics(
            $filters['agent_id'] ?? null,
            $filters['contact_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null
        );
        
        Response::json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Obter chamadas de uma conversa
     */
    public function getByConversation(int $conversationId): void
    {
        Permission::abortIfCannot('voice_calls.view');
        
        $calls = VoiceCall::getByConversation($conversationId);
        
        // Enriquecer dados
        foreach ($calls as &$call) {
            if ($call['agent_id']) {
                $call['agent'] = \App\Models\User::find($call['agent_id']);
            }
            if (!empty($call['metadata'])) {
                $call['metadata'] = json_decode($call['metadata'], true) ?? [];
            }
        }
        
        Response::json([
            'success' => true,
            'calls' => $calls
        ]);
    }
}

