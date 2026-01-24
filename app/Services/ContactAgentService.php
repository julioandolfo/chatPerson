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
        // ✅ CORREÇÃO: Sempre verificar se o contato tem agente principal
        // Não importa o status da conversa atual - o que importa é se o contato já teve um agente antes
        
        // Verificar se há agente principal definido para o contato
        $primaryAgent = ContactAgent::getPrimaryAgent($contactId);
        
        if (!$primaryAgent || !$primaryAgent['auto_assign_on_reopen']) {
            // Não tem agente principal OU não está configurado para auto-atribuir
            return null;
        }
        
        // Verificar se deve aplicar auto-atribuição neste contexto
        $shouldAutoAssign = false;
        
        if ($conversationId) {
            // Conversa específica fornecida - verificar se é reabertura ou nova conversa de contato conhecido
            $conversation = Conversation::find($conversationId);
            
            // Verificar se há conversa anterior do contato (qualquer status)
            $sql = "SELECT COUNT(*) as count FROM conversations 
                    WHERE contact_id = ? AND id != ? AND id < ?";
            $result = \App\Helpers\Database::fetch($sql, [$contactId, $conversationId, $conversationId]);
            $hasPreviousConversations = ($result['count'] ?? 0) > 0;
            
            if ($hasPreviousConversations) {
                // Contato já teve conversa antes - atribuir ao agente principal
                $shouldAutoAssign = true;
            }
        } else {
            // Nova conversa sem ID - verificar se há conversa anterior do contato
            $sql = "SELECT COUNT(*) as count FROM conversations WHERE contact_id = ?";
            $result = \App\Helpers\Database::fetch($sql, [$contactId]);
            $hasPreviousConversations = ($result['count'] ?? 0) > 0;
            
            if ($hasPreviousConversations) {
                // Contato já teve conversa antes - atribuir ao agente principal
                $shouldAutoAssign = true;
            }
        }
        
        if ($shouldAutoAssign) {
            // Verificar se agente está ativo
            $agent = \App\Models\User::find($primaryAgent['agent_id']);
            if ($agent && $agent['status'] === 'active') {
                \App\Helpers\Logger::debug(
                    "shouldAutoAssignOnConversation: Contato #{$contactId} tem agente principal #{$primaryAgent['agent_id']} (auto_assign_on_reopen=1, agente ativo). Retornando para priorização.",
                    'conversas.log'
                );
                return $primaryAgent['agent_id'];
            } else {
                \App\Helpers\Logger::debug(
                    "shouldAutoAssignOnConversation: Contato #{$contactId} tem agente principal #{$primaryAgent['agent_id']}, MAS agente está INATIVO. Retornando null.",
                    'conversas.log'
                );
            }
        }
        
        return null;
    }

    /**
     * Atualizar agente principal quando conversa é atribuída pela primeira vez
     * (se configurado nas regras)
     * 
     * IMPORTANTE: O primeiro agente atribuído a um contato SEMPRE se torna o agente principal
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
        
        // PRIMEIRO: Garantir que o agente está na lista de agentes do contato
        // Verificar se já existe na lista
        $existingAgents = ContactAgent::getByContact($contactId);
        $agentExists = false;
        foreach ($existingAgents as $agent) {
            if ($agent['agent_id'] == $agentId) {
                $agentExists = true;
                break;
            }
        }
        
        // Se não existe, adicionar à lista
        if (!$agentExists) {
            ContactAgent::addAgent($contactId, $agentId, false, 0);
        }
        
        // Verificar se contato já tem agente principal
        $primaryAgent = ContactAgent::getPrimaryAgent($contactId);
        
        // Se não tem agente principal, definir este como principal
        // IMPORTANTE: O primeiro agente atribuído SEMPRE se torna o agente principal
        if (!$primaryAgent) {
            ContactAgent::setPrimaryAgent($contactId, $agentId);
            error_log("ContactAgentService: Agente {$agentId} definido como principal do contato {$contactId} (primeira atribuição)");
        }
    }
}

