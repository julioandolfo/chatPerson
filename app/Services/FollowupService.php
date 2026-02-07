<?php
/**
 * Service FollowupService
 * Sistema de followup automático com agentes de IA
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\AIAgent;
use App\Models\AIConversation;
use App\Helpers\Database;

class FollowupService
{
    /**
     * Executar followups pendentes (todos os tipos)
     */
    public static function runFollowups(): void
    {
        // ⚠️ DESATIVADO: Todos os followups automáticos estão desativados.
        // Para ativar, configure um agente de IA do tipo FOLLOWUP nas configurações
        // e descomente os blocos desejados abaixo.
        
        // Verificar se existe algum agente de followup ativo e habilitado
        $followupAgents = AIAgent::getByType('FOLLOWUP');
        $hasActiveAgent = false;
        foreach ($followupAgents as $agent) {
            if (!empty($agent['enabled'])) {
                $hasActiveAgent = true;
                break;
            }
        }
        
        if (!$hasActiveAgent) {
            // Sem agente de followup ativo — não processar nada
            return;
        }
        
        // Verificar configuração de quais tipos estão habilitados
        $settings = [];
        if ($hasActiveAgent) {
            $agentSettings = is_string($followupAgents[0]['settings']) 
                ? json_decode($followupAgents[0]['settings'], true) 
                : ($followupAgents[0]['settings'] ?? []);
            $settings = $agentSettings;
        }
        
        $enabledTypes = $settings['followup_types'] ?? [];
        
        // 1. Followup de conversas fechadas (geral)
        if (in_array('general', $enabledTypes)) {
            $conversations = self::getConversationsNeedingFollowup();
            foreach ($conversations as $conversation) {
                try {
                    self::processFollowup($conversation, 'general');
                } catch (\Exception $e) {
                    error_log("Erro ao processar followup para conversa {$conversation['id']}: " . $e->getMessage());
                }
            }
        }
        
        // 2. Verificação de satisfação pós-atendimento
        if (in_array('satisfaction', $enabledTypes)) {
            self::checkPostServiceSatisfaction();
        }
        
        // 3. Reengajamento de contatos inativos
        if (in_array('reengagement', $enabledTypes)) {
            self::reengageInactiveContacts();
        }
        
        // 4. Followup de leads frios
        if (in_array('cold_leads', $enabledTypes)) {
            self::followupColdLeads();
        }
        
        // 5. Followup de oportunidades de venda
        if (in_array('sales', $enabledTypes)) {
            self::followupSalesOpportunities();
        }
    }

    /**
     * Obter conversas que precisam de followup
     */
    private static function getConversationsNeedingFollowup(): array
    {
        // Buscar conversas fechadas entre 3 e 7 dias atrás (janela segura, sem backlog antigo)
        $sql = "SELECT c.*, ct.name as contact_name, ct.phone, ct.email
                FROM conversations c
                INNER JOIN contacts ct ON c.contact_id = ct.id
                WHERE c.status = 'closed'
                AND c.resolved_at IS NOT NULL
                AND c.resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND DATE_ADD(c.resolved_at, INTERVAL 3 DAY) <= NOW()
                AND c.id NOT IN (
                    SELECT DISTINCT conversation_id 
                    FROM ai_conversations 
                    WHERE ai_agent_id IN (
                        SELECT id FROM ai_agents WHERE agent_type = 'FOLLOWUP' AND enabled = TRUE
                    )
                    AND status = 'active'
                )
                ORDER BY c.resolved_at ASC
                LIMIT 5";
        
        return Database::fetchAll($sql);
    }

    /**
     * Processar followup para uma conversa
     */
    private static function processFollowup(array $conversation, string $followupType = 'general'): void
    {
        // Buscar agente de IA de followup apropriado
        $agent = self::selectFollowupAgent($conversation, $followupType);
        
        if (!$agent) {
            return; // Nenhum agente disponível
        }
        
        // Verificar se já existe followup ativo para esta conversa
        $existingFollowup = AIConversation::whereFirst('conversation_id', '=', $conversation['id']);
        if ($existingFollowup && $existingFollowup['status'] === 'active') {
            return; // Já existe followup ativo
        }
        
        // Criar ou atualizar registro de conversa de IA
        if ($existingFollowup) {
            $aiConversationId = $existingFollowup['id'];
            AIConversation::update($aiConversationId, [
                'ai_agent_id' => $agent['id'],
                'status' => 'active',
                'metadata' => json_encode([
                    'followup_type' => $followupType,
                    'initiated_at' => date('Y-m-d H:i:s')
                ])
            ]);
        } else {
            $aiConversationId = AIConversation::create([
                'conversation_id' => $conversation['id'],
                'ai_agent_id' => $agent['id'],
                'messages' => json_encode([]),
                'status' => 'active',
                'metadata' => json_encode([
                    'followup_type' => $followupType,
                    'initiated_at' => date('Y-m-d H:i:s')
                ])
            ]);
        }
        
        // Atualizar contagem do agente
        AIAgent::updateConversationsCount($agent['id']);
        
        // Gerar mensagem de followup baseada no contexto
        $followupMessage = self::generateFollowupMessage($conversation, $agent, $followupType);
        
        // Processar com agente de IA
        try {
            AIAgentService::processMessage(
                $conversation['id'],
                $agent['id'],
                $followupMessage
            );
        } catch (\Exception $e) {
            error_log("Erro ao processar followup com IA: " . $e->getMessage());
            // Marcar como falha
            AIConversation::updateStatus($aiConversationId, 'failed');
        }
    }
    
    /**
     * Selecionar agente de IA apropriado para followup
     */
    private static function selectFollowupAgent(array $conversation, string $followupType): ?array
    {
        // Buscar agentes de followup disponíveis
        $followupAgents = AIAgent::getByType('FOLLOWUP');
        
        if (empty($followupAgents)) {
            return null;
        }
        
        // Tentar encontrar agente específico para o tipo de followup
        foreach ($followupAgents as $agent) {
            $settings = is_string($agent['settings']) 
                ? json_decode($agent['settings'], true) 
                : ($agent['settings'] ?? []);
            
            $agentFollowupTypes = $settings['followup_types'] ?? ['general'];
            
            if (in_array($followupType, $agentFollowupTypes) && AIAgent::canReceiveMoreConversations($agent['id'])) {
                return $agent;
            }
        }
        
        // Se não encontrou específico, usar primeiro disponível
        foreach ($followupAgents as $agent) {
            if (AIAgent::canReceiveMoreConversations($agent['id'])) {
                return $agent;
            }
        }
        
        return null;
    }

    /**
     * Gerar mensagem inicial de followup baseada no contexto
     */
    private static function generateFollowupMessage(array $conversation, array $agent, string $followupType = 'general'): string
    {
        // Buscar contexto adicional
        $contact = \App\Models\Contact::find($conversation['contact_id']);
        $lastMessage = Database::fetch(
            "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1",
            [$conversation['id']]
        );
        
        $context = [
            'conversation_id' => $conversation['id'],
            'contact_name' => $contact['name'] ?? 'Cliente',
            'days_since_resolution' => $conversation['resolved_at'] 
                ? floor((time() - strtotime($conversation['resolved_at'])) / 86400)
                : null,
            'last_message' => $lastMessage ? [
                'content' => $lastMessage['content'],
                'sender' => $lastMessage['sender_type']
            ] : null,
            'followup_type' => $followupType
        ];
        
        // Gerar mensagem contextual baseada no tipo
        switch ($followupType) {
            case 'satisfaction':
                return sprintf(
                    "Verificação de satisfação pós-atendimento. A conversa #%d foi resolvida há %d dia(s). " .
                    "Por favor, verifique se o cliente está satisfeito com o atendimento recebido e se o problema foi completamente resolvido.",
                    $conversation['id'],
                    $context['days_since_resolution'] ?? 0
                );
            
            case 'reengagement':
                return sprintf(
                    "Reengajamento automático. O contato %s não interage há mais de 7 dias. " .
                    "Por favor, envie uma mensagem amigável para reengajar e verificar se ainda há interesse.",
                    $context['contact_name']
                );
            
            case 'cold_leads':
                return sprintf(
                    "Followup de lead frio. O lead %s não demonstrou interesse recentemente. " .
                    "Por favor, envie uma mensagem para reativar o interesse e qualificar o lead.",
                    $context['contact_name']
                );
            
            case 'sales':
                return sprintf(
                    "Followup de oportunidade de venda. A conversa #%d está relacionada a uma oportunidade de venda. " .
                    "Por favor, acompanhe o progresso e verifique se há interesse em avançar.",
                    $conversation['id']
                );
            
            case 'support':
                return sprintf(
                    "Followup de suporte. A conversa #%d foi resolvida há %d dia(s). " .
                    "Por favor, verifique se o problema técnico foi completamente resolvido e se o cliente precisa de mais ajuda.",
                    $conversation['id'],
                    $context['days_since_resolution'] ?? 0
                );
            
            default:
                return sprintf(
                    "Seguimento automático após %d dia(s) da resolução da conversa #%d. " .
                    "Por favor, verifique se o cliente precisa de mais assistência ou se há algo novo para discutir.",
                    $context['days_since_resolution'] ?? 0,
                    $conversation['id']
                );
        }
    }

    /**
     * Verificar e reengajar contatos inativos
     */
    public static function reengageInactiveContacts(): void
    {
        // Buscar contatos inativos entre 7 e 14 dias (janela segura)
        $sql = "SELECT DISTINCT c.contact_id, ct.name, ct.phone, ct.email,
                       MAX(c.updated_at) as last_interaction
                FROM conversations c
                INNER JOIN contacts ct ON c.contact_id = ct.id
                WHERE c.status IN ('open', 'closed')
                AND c.updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND c.updated_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                GROUP BY c.contact_id
                HAVING MAX(c.updated_at) < DATE_SUB(NOW(), INTERVAL 7 DAY)
                LIMIT 3";
        
        $contacts = Database::fetchAll($sql);
        
        foreach ($contacts as $contact) {
            try {
                self::reengageContact($contact);
            } catch (\Exception $e) {
                error_log("Erro ao reengajar contato {$contact['contact_id']}: " . $e->getMessage());
            }
        }
    }


    /**
     * Verificar satisfação pós-atendimento
     */
    public static function checkPostServiceSatisfaction(): void
    {
        // Buscar conversas fechadas recentemente que ainda não tiveram verificação de satisfação
        $sql = "SELECT c.*, ct.name as contact_name
                FROM conversations c
                INNER JOIN contacts ct ON c.contact_id = ct.id
                WHERE c.status = 'closed'
                AND c.resolved_at IS NOT NULL
                AND DATE_ADD(c.resolved_at, INTERVAL 1 DAY) <= NOW()
                AND DATE_ADD(c.resolved_at, INTERVAL 2 DAY) >= NOW()
                AND c.id NOT IN (
                    SELECT DISTINCT conversation_id 
                    FROM ai_conversations 
                    WHERE ai_agent_id IN (
                        SELECT id FROM ai_agents WHERE agent_type = 'FOLLOWUP' AND enabled = TRUE
                    )
                    AND JSON_EXTRACT(metadata, '$.satisfaction_check') = TRUE
                )
                LIMIT 3";
        
        $conversations = Database::fetchAll($sql);
        
        foreach ($conversations as $conversation) {
            try {
                self::checkSatisfaction($conversation);
            } catch (\Exception $e) {
                error_log("Erro ao verificar satisfação para conversa {$conversation['id']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Verificar satisfação de uma conversa específica
     */
    private static function checkSatisfaction(array $conversation): void
    {
        self::processFollowup($conversation, 'satisfaction');
    }
    
    /**
     * Followup de leads frios (sem interação há mais de X dias)
     */
    public static function followupColdLeads(): void
    {
        // Buscar leads que não interagem há mais de 14 dias
        $sql = "SELECT DISTINCT c.*, ct.name as contact_name, ct.phone, ct.email,
                       MAX(c.updated_at) as last_interaction
                FROM conversations c
                INNER JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN conversation_tags ctg ON c.id = ctg.conversation_id
                LEFT JOIN tags t ON ctg.tag_id = t.id
                WHERE c.status IN ('open', 'closed')
                AND (t.name LIKE '%lead%' OR t.name LIKE '%prospect%' OR c.subject LIKE '%lead%')
                AND c.updated_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND c.updated_at >= DATE_SUB(NOW(), INTERVAL 21 DAY)
                GROUP BY c.id
                HAVING MAX(c.updated_at) < DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND c.id NOT IN (
                    SELECT DISTINCT conversation_id 
                    FROM ai_conversations 
                    WHERE JSON_EXTRACT(metadata, '$.followup_type') = 'cold_leads'
                    AND status = 'active'
                )
                LIMIT 3";
        
        $conversations = Database::fetchAll($sql);
        
        foreach ($conversations as $conversation) {
            try {
                self::processFollowup($conversation, 'cold_leads');
            } catch (\Exception $e) {
                error_log("Erro ao processar followup de lead frio para conversa {$conversation['id']}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Followup de oportunidades de venda
     */
    public static function followupSalesOpportunities(): void
    {
        // Buscar conversas relacionadas a vendas que precisam de acompanhamento
        $sql = "SELECT DISTINCT c.*, ct.name as contact_name
                FROM conversations c
                INNER JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN conversation_tags ctg ON c.id = ctg.conversation_id
                LEFT JOIN tags t ON ctg.tag_id = t.id
                LEFT JOIN funnels f ON c.funnel_id = f.id
                WHERE c.status = 'open'
                AND (
                    t.name LIKE '%venda%' OR t.name LIKE '%oportunidade%' OR 
                    t.name LIKE '%proposta%' OR f.name LIKE '%vendas%'
                )
                AND c.updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
                AND c.updated_at >= DATE_SUB(NOW(), INTERVAL 10 DAY)
                AND c.id NOT IN (
                    SELECT DISTINCT conversation_id 
                    FROM ai_conversations 
                    WHERE JSON_EXTRACT(metadata, '$.followup_type') = 'sales'
                    AND status = 'active'
                )
                LIMIT 3";
        
        $conversations = Database::fetchAll($sql);
        
        foreach ($conversations as $conversation) {
            try {
                self::processFollowup($conversation, 'sales');
            } catch (\Exception $e) {
                error_log("Erro ao processar followup de vendas para conversa {$conversation['id']}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Reengajar um contato específico (melhorado)
     */
    private static function reengageContact(array $contact): void
    {
        // Buscar última conversa do contato
        $lastConversation = Database::fetch(
            "SELECT * FROM conversations WHERE contact_id = ? ORDER BY updated_at DESC LIMIT 1",
            [$contact['contact_id']]
        );
        
        if ($lastConversation) {
            // Usar conversa existente
            self::processFollowup($lastConversation, 'reengagement');
        } else {
            // Criar nova conversa de reengajamento
            $conversationId = Conversation::create([
                'contact_id' => $contact['contact_id'],
                'channel' => 'whatsapp', // ou detectar canal preferido
                'status' => 'open',
                'subject' => 'Reengajamento'
            ]);
            
            $conversation = Conversation::find($conversationId);
            if ($conversation) {
                self::processFollowup($conversation, 'reengagement');
            }
        }
    }
}

