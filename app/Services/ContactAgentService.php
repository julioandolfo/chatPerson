<?php
/**
 * Service ContactAgentService
 */

namespace App\Services;

use App\Models\ContactAgent;
use App\Models\Contact;
use App\Models\Conversation;

class ContactAgentService
{
    /**
     * Adicionar agente a contato
     */
    public static function addAgent(int $contactId, int $agentId, bool $isPrimary = false, int $priority = 0): array
    {
        $contact = \App\Models\Contact::find($contactId);
        if (!$contact) {
            throw new \Exception('Contato não encontrado');
        }
        
        $agent = \App\Models\User::find($agentId);
        if (!$agent || $agent['status'] !== 'active') {
            throw new \Exception('Agente não encontrado ou inativo');
        }
        
        $id = ContactAgent::addAgent($contactId, $agentId, $isPrimary, $priority);
        
        return ContactAgent::find($id);
    }

    /**
     * Remover agente de contato
     */
    public static function removeAgent(int $contactId, int $agentId): bool
    {
        return ContactAgent::removeAgent($contactId, $agentId);
    }

    /**
     * Definir agente principal
     */
    public static function setPrimaryAgent(int $contactId, int $agentId): array
    {
        $contact = \App\Models\Contact::find($contactId);
        if (!$contact) {
            throw new \Exception('Contato não encontrado');
        }
        
        $agent = \App\Models\User::find($agentId);
        if (!$agent || $agent['status'] !== 'active') {
            throw new \Exception('Agente não encontrado ou inativo');
        }
        
        $success = ContactAgent::setPrimaryAgent($contactId, $agentId);
        
        if (!$success) {
            throw new \Exception('Erro ao definir agente principal');
        }
        
        return ContactAgent::getPrimaryAgent($contactId);
    }

    /**
     * Listar agentes de um contato
     */
    public static function getAgents(int $contactId): array
    {
        return ContactAgent::getByContact($contactId);
    }

    /**
     * Verificar se deve atribuir automaticamente ao criar/reabrir conversa
     * Retorna o ID do agente se deve atribuir, null caso contrário
     */
    public static function shouldAutoAssignOnConversation(int $contactId, ?int $conversationId = null): ?int
    {
        // Se há conversationId, verificar se é uma reabertura de conversa fechada
        if ($conversationId) {
            $conversation = Conversation::find($conversationId);
            if ($conversation && $conversation['status'] === 'closed') {
                // Conversa fechada sendo reaberta - verificar agente do contato
                $primaryAgent = ContactAgent::getPrimaryAgent($contactId);
                if ($primaryAgent && $primaryAgent['auto_assign_on_reopen']) {
                    // Verificar se agente está ativo
                    $agent = \App\Models\User::find($primaryAgent['agent_id']);
                    if ($agent && $agent['status'] === 'active') {
                        return $primaryAgent['agent_id'];
                    }
                }
            }
        } else {
            // Nova conversa - verificar se há conversa fechada anterior
            $sql = "SELECT * FROM conversations 
                    WHERE contact_id = ? AND status = 'closed' 
                    ORDER BY updated_at DESC LIMIT 1";
            $closedConversation = \App\Helpers\Database::fetch($sql, [$contactId]);
            
            if ($closedConversation) {
                // Há conversa fechada anterior - verificar agente do contato
                $primaryAgent = ContactAgent::getPrimaryAgent($contactId);
                if ($primaryAgent && $primaryAgent['auto_assign_on_reopen']) {
                    // Verificar se agente está ativo
                    $agent = \App\Models\User::find($primaryAgent['agent_id']);
                    if ($agent && $agent['status'] === 'active') {
                        return $primaryAgent['agent_id'];
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Atualizar agente principal quando conversa é atribuída pela primeira vez
     * (se configurado nas regras)
     */
    public static function updatePrimaryOnFirstAssignment(int $contactId, int $agentId, bool $updateIfConfigured = true): void
    {
        if (!$updateIfConfigured) {
            return;
        }
        
        // Verificar configuração nas settings
        $settings = \App\Services\ConversationSettingsService::getSettings();
        $autoSetPrimaryOnFirstAssignment = $settings['contact_agents']['auto_set_primary_agent_on_first_assignment'] ?? true;
        
        if (!$autoSetPrimaryOnFirstAssignment) {
            return;
        }
        
        // Verificar se contato já tem agente principal
        $primaryAgent = ContactAgent::getPrimaryAgent($contactId);
        
        // Se não tem agente principal, definir este como principal
        if (!$primaryAgent) {
            ContactAgent::setPrimaryAgent($contactId, $agentId);
        }
    }
}

