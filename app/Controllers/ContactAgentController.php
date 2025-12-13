<?php
/**
 * Controller ContactAgentController
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Permission;
use App\Services\ContactAgentService;

class ContactAgentController
{
    /**
     * Listar agentes de um contato
     */
    public function index(int $contactId): void
    {
        Permission::abortIfCannot('contacts.view.own');
        
        try {
            $agents = ContactAgentService::getAgents($contactId);
            
            Response::json([
                'success' => true,
                'agents' => $agents
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Adicionar agente a contato
     */
    public function store(int $contactId): void
    {
        Permission::abortIfCannot('contacts.edit');
        
        try {
            $agentId = $_POST['agent_id'] ?? null;
            $isPrimary = isset($_POST['is_primary']) && $_POST['is_primary'] === '1';
            $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
            
            if (!$agentId) {
                throw new \Exception('Agente não informado');
            }
            
            $agent = ContactAgentService::addAgent($contactId, $agentId, $isPrimary, $priority);
            
            Response::json([
                'success' => true,
                'message' => 'Agente adicionado com sucesso',
                'agent' => $agent
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Definir agente principal
     */
    public function setPrimary(int $contactId): void
    {
        Permission::abortIfCannot('contacts.edit');
        
        try {
            $agentId = $_POST['agent_id'] ?? null;
            
            if (!$agentId) {
                throw new \Exception('Agente não informado');
            }
            
            $agent = ContactAgentService::setPrimaryAgent($contactId, $agentId);
            
            Response::json([
                'success' => true,
                'message' => 'Agente principal definido com sucesso',
                'agent' => $agent
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Remover agente de contato
     */
    public function destroy(int $contactId, int $agentId): void
    {
        Permission::abortIfCannot('contacts.edit');
        
        try {
            ContactAgentService::removeAgent($contactId, $agentId);
            
            Response::json([
                'success' => true,
                'message' => 'Agente removido com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

