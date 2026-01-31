<?php
/**
 * Controller Api4ComCallController
 * Gerenciamento de chamadas Api4Com
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\Api4ComService;
use App\Models\Api4ComCall;
use App\Models\Api4ComAccount;
use App\Models\Contact;
use App\Models\Conversation;

class Api4ComCallController
{
    /**
     * Listar chamadas
     */
    public function index(): void
    {
        Permission::abortIfCannot('api4com_calls.view');
        
        $filters = Request::get();
        $page = (int)($filters['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['contact_id'])) {
            $where[] = 'ac.contact_id = ?';
            $params[] = $filters['contact_id'];
        }

        if (!empty($filters['agent_id'])) {
            $where[] = 'ac.agent_id = ?';
            $params[] = $filters['agent_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'ac.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['api4com_account_id'])) {
            $where[] = 'ac.api4com_account_id = ?';
            $params[] = $filters['api4com_account_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'ac.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'ac.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT ac.*, 
                       c.name as contact_name, c.phone as contact_phone,
                       u.name as agent_name,
                       acc.name as api4com_account_name,
                       conv.id as conversation_id
                FROM api4com_calls ac
                LEFT JOIN contacts c ON ac.contact_id = c.id
                LEFT JOIN users u ON ac.agent_id = u.id
                LEFT JOIN api4com_accounts acc ON ac.api4com_account_id = acc.id
                LEFT JOIN conversations conv ON ac.conversation_id = conv.id
                WHERE {$whereClause}
                ORDER BY ac.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;

        $calls = \App\Helpers\Database::fetchAll($sql, $params);

        foreach ($calls as &$call) {
            if (!empty($call['metadata'])) {
                $call['metadata'] = json_decode($call['metadata'], true) ?? [];
            } else {
                $call['metadata'] = [];
            }
        }

        $countSql = "SELECT COUNT(*) as total FROM api4com_calls ac WHERE {$whereClause}";
        $countResult = \App\Helpers\Database::fetch($countSql, array_slice($params, 0, -2));
        $total = $countResult['total'] ?? 0;

        Response::view('api4com-calls/index', [
            'calls' => $calls,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'filters' => $filters,
            'api4com_accounts' => Api4ComAccount::getEnabled(),
            'agents' => \App\Models\User::where('role', 'IN', ['agent', 'senior_agent', 'supervisor'])
        ]);
    }

    /**
     * Criar nova chamada
     */
    public function create(): void
    {
        Permission::abortIfCannot('api4com_calls.create');
        
        try {
            $data = Request::post();
            
            $call = Api4ComService::createCall($data);
            
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
        Permission::abortIfCannot('api4com_calls.view');
        
        $call = Api4ComCall::find($id);
        if (!$call) {
            Response::json([
                'success' => false,
                'message' => 'Chamada não encontrada'
            ], 404);
            return;
        }

        if ($call['contact_id']) {
            $call['contact'] = Contact::find($call['contact_id']);
        }
        if ($call['agent_id']) {
            $call['agent'] = \App\Models\User::find($call['agent_id']);
        }
        if ($call['api4com_account_id']) {
            $call['api4com_account'] = Api4ComAccount::find($call['api4com_account_id']);
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
        Permission::abortIfCannot('api4com_calls.end');
        
        try {
            Api4ComService::endCall($id);
            
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
     * Webhook do Api4Com
     */
    public function webhook(): void
    {
        try {
            $data = Request::json();
            
            if (empty($data)) {
                $data = Request::post();
            }

            Api4ComService::processWebhook($data);
            
            Response::json([
                'success' => true,
                'message' => 'Webhook processado'
            ]);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Api4ComCallController::webhook - Erro: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar status da chamada (via WebPhone)
     */
    public function updateStatus(): void
    {
        try {
            $data = Request::json();
            $status = $data['status'] ?? '';
            $duration = (int)($data['duration'] ?? 0);
            $agentId = auth()->id();
            
            \App\Helpers\Logger::api4com("updateStatus - Status: {$status}, Duration: {$duration}, Agent: {$agentId}");
            
            // Buscar a última chamada ativa do agente
            $calls = Api4ComCall::where('agent_id', '=', $agentId);
            $activeCall = null;
            
            foreach ($calls as $call) {
                if (in_array($call['status'], ['initiated', 'ringing', 'answered'])) {
                    $activeCall = $call;
                    break;
                }
            }
            
            if (!$activeCall) {
                \App\Helpers\Logger::api4com("updateStatus - Nenhuma chamada ativa encontrada para o agente {$agentId}");
                Response::json([
                    'success' => false,
                    'message' => 'Nenhuma chamada ativa encontrada'
                ], 404);
                return;
            }
            
            $callId = $activeCall['id'];
            \App\Helpers\Logger::api4com("updateStatus - Atualizando chamada #{$callId} para status: {$status}");
            
            $updateData = [];
            
            if ($status === 'answered') {
                $updateData['status'] = 'answered';
                $updateData['answered_at'] = date('Y-m-d H:i:s');
            } elseif ($status === 'ended') {
                $updateData['status'] = 'ended';
                $updateData['ended_at'] = date('Y-m-d H:i:s');
                $updateData['duration'] = $duration;
            }
            
            if (!empty($updateData)) {
                Api4ComCall::update($callId, $updateData);
                \App\Helpers\Logger::api4com("updateStatus - Chamada #{$callId} atualizada: " . json_encode($updateData));
                
                // Criar nota de chamada
                if (in_array($status, ['answered', 'ended'])) {
                    $call = Api4ComCall::find($callId);
                    if ($call && !empty($call['conversation_id'])) {
                        Api4ComService::createCallNote($call, $status);
                    }
                }
            }
            
            Response::json([
                'success' => true,
                'message' => 'Status atualizado',
                'call_id' => $callId
            ]);
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::api4com("updateStatus - Erro: " . $e->getMessage(), 'ERROR');
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obter estatísticas
     */
    public function statistics(): void
    {
        Permission::abortIfCannot('api4com_calls.view');
        
        $filters = Request::get();
        
        $stats = Api4ComService::getStatistics(
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
        Permission::abortIfCannot('api4com_calls.view');
        
        $calls = Api4ComCall::getByConversation($conversationId);
        
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

