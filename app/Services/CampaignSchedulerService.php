<?php
/**
 * Service CampaignSchedulerService
 * Processamento de campanhas: envio em massa com rotação de contas WhatsApp
 * 
 * ESTE É O CORAÇÃO DO SISTEMA DE CAMPANHAS
 */

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\CampaignBlacklist;
use App\Models\IntegrationAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Services\IntegrationService;
use App\Services\ConversationService;
use App\Helpers\Logger;

class CampaignSchedulerService
{
    /**
     * Índice da última conta usada (para round robin)
     */
    private static array $lastAccountIndex = [];

    /**
     * Forçar envio imediato da próxima mensagem pendente (para testes)
     * Ignora intervalos, limites e configurações de taxa
     */
    public static function forceSendNext(int $campaignId): array
    {
        Logger::campaign("=== FORÇAR DISPARO - Campanha {$campaignId} ===");

        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            Logger::campaign("Forçar disparo: Campanha {$campaignId} não encontrada");
            return ['success' => false, 'message' => 'Campanha não encontrada'];
        }

        Logger::campaign("Forçar disparo: Campanha '{$campaign['name']}' - Status: {$campaign['status']}");

        // Verificar se a campanha está em um status válido para envio
        if (!in_array($campaign['status'], ['running', 'scheduled', 'paused', 'draft'])) {
            Logger::campaign("Forçar disparo: Status inválido para envio: {$campaign['status']}");
            return ['success' => false, 'message' => 'Campanha não está em status válido para envio'];
        }

        // Buscar próxima mensagem pendente
        $message = CampaignMessage::getNextPending($campaignId);
        if (!$message) {
            Logger::campaign("Forçar disparo: Nenhuma mensagem pendente na campanha {$campaignId}");
            return ['success' => false, 'message' => 'Nenhuma mensagem pendente na campanha'];
        }

        Logger::campaign("Forçar disparo: Mensagem #{$message['id']} encontrada - Contact ID: {$message['contact_id']}");

        $contact = Contact::find($message['contact_id']);
        $contactName = $contact['name'] ?? 'Contato #' . $message['contact_id'];
        $contactPhone = $contact['phone'] ?? 'N/A';

        Logger::campaign("Forçar disparo: Contato '{$contactName}' ({$contactPhone})");

        try {
            // Processar a mensagem imediatamente
            Logger::campaign("Forçar disparo: Iniciando processMessage...");
            $result = self::processMessage($campaignId, $message);
            
            Logger::campaign("Forçar disparo: Mensagem {$message['id']} enviada para {$contactName} - Status: " . ($result['status'] ?? 'unknown'));

            return [
                'success' => true,
                'message_id' => $message['id'],
                'contact_id' => $message['contact_id'],
                'contact_name' => $contactName,
                'status' => $result['status'] ?? 'sent',
                'external_id' => $result['external_id'] ?? null
            ];
        } catch (\Exception $e) {
            Logger::campaign("Forçar disparo: ERRO - " . $e->getMessage());
            Logger::campaign("Forçar disparo: Stack trace - " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'contact_name' => $contactName
            ];
        }
    }

    /**
     * Processar mensagens pendentes de todas as campanhas ativas
     * Este método é chamado pelo cron job a cada 1 minuto
     */
    public static function processPending(int $limit = 50): array
    {
        Logger::campaign("=== INICIANDO PROCESSAMENTO DE CAMPANHAS ===");

        $processed = [];

        // 1. Buscar campanhas ativas
        $campaigns = Campaign::getActive();
        
        if (empty($campaigns)) {
            Logger::campaign("Nenhuma campanha ativa encontrada");
            return $processed;
        }

        Logger::campaign("Campanhas ativas: " . count($campaigns));

        foreach ($campaigns as $campaign) {
            $campaignId = $campaign['id'];
            
            try {
                // 2. Verificar se pode enviar agora (janela de horário)
                if (!self::canSendNow($campaignId)) {
                    Logger::campaign("Campanha {$campaignId} fora da janela de envio");
                    continue;
                }

                // 2.1. Resetar contadores se necessário (diário/horário)
                self::resetCountersIfNeeded($campaignId);
                
                // 2.2. Verificar limites antes de continuar
                $limitCheck = self::checkLimits($campaignId);
                if (!$limitCheck['can_send']) {
                    Logger::campaign("Campanha {$campaignId}: {$limitCheck['reason']}");
                    continue;
                }

                // 3. Calcular quantas mensagens podemos enviar
                $effectiveLimit = self::calculateEffectiveLimit($campaignId, $limit);
                if ($effectiveLimit <= 0) {
                    Logger::campaign("Campanha {$campaignId}: Limite atingido para este ciclo");
                    continue;
                }

                // 4. Buscar mensagens pendentes
                $messages = CampaignMessage::getPending($campaignId, $effectiveLimit);
                
                if (empty($messages)) {
                    // Nenhuma mensagem pendente, verificar se completou
                    if (Campaign::isCompleted($campaignId)) {
                        Campaign::update($campaignId, [
                            'status' => 'completed',
                            'completed_at' => date('Y-m-d H:i:s')
                        ]);
                        Logger::campaign("Campanha {$campaignId} concluída!");
                    }
                    continue;
                }

                Logger::campaign("Campanha {$campaignId}: " . count($messages) . " mensagens a processar (limite efetivo: {$effectiveLimit})");

                // 5. Processar cada mensagem
                $sentInBatch = 0;
                foreach ($messages as $message) {
                    try {
                        // Verificar limite de lote
                        if (!empty($campaign['batch_size']) && $sentInBatch >= $campaign['batch_size']) {
                            $pauseMinutes = $campaign['batch_pause_minutes'] ?? 5;
                            Logger::campaign("Campanha {$campaignId}: Lote de {$campaign['batch_size']} msgs enviado, pausando {$pauseMinutes} min");
                            sleep($pauseMinutes * 60);
                            $sentInBatch = 0;
                        }
                        
                        // Re-verificar limites após cada envio (podem ter sido atingidos)
                        $limitCheck = self::checkLimits($campaignId);
                        if (!$limitCheck['can_send']) {
                            Logger::campaign("Campanha {$campaignId}: {$limitCheck['reason']} - parando processamento");
                            break;
                        }
                        
                        $result = self::processMessage($campaignId, $message);
                        $processed[] = $result;
                        
                        // Incrementar contadores de limite
                        if ($result['status'] === 'sent') {
                            self::incrementSendCounters($campaignId);
                            $sentInBatch++;
                        }

                        // 6. Aplicar cadência (delay entre envios)
                        self::applyCadence($campaignId);

                    } catch (\Exception $e) {
                        Logger::campaign("[ERRO] Erro ao processar mensagem {$message['id']}: " . $e->getMessage());
                        
                        // Marcar como falha
                        CampaignMessage::markAsFailed($message['id'], $e->getMessage());
                        Campaign::incrementFailed($campaignId);
                    }
                }

                // 7. Atualizar último processamento
                Campaign::updateLastProcessed($campaignId);

            } catch (\Exception $e) {
                Logger::campaign("[ERRO] Erro ao processar campanha {$campaignId}: " . $e->getMessage());
            }
        }

        Logger::campaign("=== PROCESSAMENTO CONCLUÍDO: " . count($processed) . " mensagens ===");

        return $processed;
    }
    
    /**
     * Resetar contadores diários/horários se necessário
     */
    private static function resetCountersIfNeeded(int $campaignId): void
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) return;
        
        $today = date('Y-m-d');
        $currentHour = date('Y-m-d H:00:00');
        
        $updates = [];
        
        // Reset diário
        if (empty($campaign['last_counter_reset']) || $campaign['last_counter_reset'] !== $today) {
            $updates['sent_today'] = 0;
            $updates['last_counter_reset'] = $today;
            Logger::campaign("Campanha {$campaignId}: Contador diário resetado");
        }
        
        // Reset horário
        if (empty($campaign['last_hourly_reset']) || $campaign['last_hourly_reset'] < $currentHour) {
            $updates['sent_this_hour'] = 0;
            $updates['last_hourly_reset'] = $currentHour;
        }
        
        if (!empty($updates)) {
            Campaign::update($campaignId, $updates);
        }
    }
    
    /**
     * Verificar se os limites permitem enviar
     */
    private static function checkLimits(int $campaignId): array
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return ['can_send' => false, 'reason' => 'Campanha não encontrada'];
        }
        
        // Verificar limite diário
        if (!empty($campaign['daily_limit'])) {
            $sentToday = (int)($campaign['sent_today'] ?? 0);
            if ($sentToday >= $campaign['daily_limit']) {
                return [
                    'can_send' => false, 
                    'reason' => "Limite diário atingido ({$sentToday}/{$campaign['daily_limit']})"
                ];
            }
        }
        
        // Verificar limite por hora
        if (!empty($campaign['hourly_limit'])) {
            $sentThisHour = (int)($campaign['sent_this_hour'] ?? 0);
            if ($sentThisHour >= $campaign['hourly_limit']) {
                return [
                    'can_send' => false, 
                    'reason' => "Limite por hora atingido ({$sentThisHour}/{$campaign['hourly_limit']})"
                ];
            }
        }
        
        return ['can_send' => true, 'reason' => null];
    }
    
    /**
     * Calcular limite efetivo considerando limites configurados
     */
    private static function calculateEffectiveLimit(int $campaignId, int $defaultLimit): int
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) return $defaultLimit;
        
        $limit = $defaultLimit;
        
        // Ajustar pelo limite diário restante
        if (!empty($campaign['daily_limit'])) {
            $remaining = $campaign['daily_limit'] - (int)($campaign['sent_today'] ?? 0);
            $limit = min($limit, max(0, $remaining));
        }
        
        // Ajustar pelo limite horário restante
        if (!empty($campaign['hourly_limit'])) {
            $remaining = $campaign['hourly_limit'] - (int)($campaign['sent_this_hour'] ?? 0);
            $limit = min($limit, max(0, $remaining));
        }
        
        // Ajustar pelo tamanho do lote
        if (!empty($campaign['batch_size'])) {
            $limit = min($limit, (int)$campaign['batch_size']);
        }
        
        return $limit;
    }
    
    /**
     * Incrementar contadores de envio
     */
    private static function incrementSendCounters(int $campaignId): void
    {
        $sql = "UPDATE campaigns 
                SET sent_today = COALESCE(sent_today, 0) + 1,
                    sent_this_hour = COALESCE(sent_this_hour, 0) + 1
                WHERE id = ?";
        \App\Helpers\Database::execute($sql, [$campaignId]);
    }

    /**
     * Processar uma mensagem individual
     */
    private static function processMessage(int $campaignId, array $message): array
    {
        // Definir campanha atual para verificações de limite por conta
        self::$currentCampaignId = $campaignId;
        
        $campaign = Campaign::find($campaignId);
        $contact = Contact::find($message['contact_id']);

        if (!$campaign || !$contact) {
            throw new \Exception("Campanha ou contato não encontrado");
        }

        // 1. VALIDAÇÕES: Verificar se deve pular este contato
        $skipCheck = self::shouldSkipContact($campaignId, $message['contact_id']);
        if ($skipCheck['skip']) {
            CampaignMessage::markAsSkipped($message['id'], $skipCheck['reason']);
            Campaign::incrementSkipped($campaignId);
            
            return [
                'message_id' => $message['id'],
                'contact_id' => $message['contact_id'],
                'status' => 'skipped',
                'reason' => $skipCheck['reason']
            ];
        }

        // 2. SELECIONAR CONTA (Rotação)
        $accountIds = json_decode($campaign['integration_account_ids'], true);
        if (empty($accountIds)) {
            throw new \Exception("Nenhuma conta configurada para a campanha");
        }

        // Log das contas configuradas na campanha
        Logger::campaign("Campanha {$campaignId}: [CONTA] Contas configuradas: " . json_encode($accountIds));
        Logger::campaign("Campanha {$campaignId}: [CONTA] Estratégia de rotação: {$campaign['rotation_strategy']}");
        
        // Buscar nomes das contas para log mais claro
        foreach ($accountIds as $accId) {
            $acc = IntegrationAccount::find($accId);
            if ($acc) {
                Logger::campaign("Campanha {$campaignId}: [CONTA] ID {$accId}: {$acc['name']} ({$acc['phone_number']}) - Status: {$acc['status']}");
            } else {
                Logger::campaign("Campanha {$campaignId}: [CONTA] ID {$accId}: NÃO ENCONTRADA no banco");
            }
        }

        $integrationAccountId = self::selectAccount($accountIds, $campaign['rotation_strategy']);
        
        if (!$integrationAccountId) {
            Logger::campaign("Campanha {$campaignId}: [CONTA] ERRO - Nenhuma conta ativa disponível!");
            throw new \Exception("Nenhuma conta ativa disponível");
        }
        
        // Log da conta selecionada
        $selectedAccount = IntegrationAccount::find($integrationAccountId);
        Logger::campaign("Campanha {$campaignId}: [CONTA] SELECIONADA: ID {$integrationAccountId} - {$selectedAccount['name']} ({$selectedAccount['phone_number']})");

        // 3. GERAR MENSAGEM COM IA (se configurado)
        $messageContent = $message['content'];
        if (!empty($campaign['ai_message_enabled']) && !empty($campaign['ai_message_prompt'])) {
            Logger::campaign("Campanha {$campaignId}: [IA] Gerando mensagem para contato {$contact['id']} ({$contact['name']})");
            try {
                $aiMessage = \App\Services\OpenAIService::generateCampaignMessage(
                    $campaign['ai_message_prompt'],
                    $contact,
                    $message['content'], // Mensagem original como referência
                    (float)($campaign['ai_temperature'] ?? 0.7)
                );
                
                if ($aiMessage) {
                    $messageContent = $aiMessage;
                    Logger::campaign("Campanha {$campaignId}: [IA] Mensagem gerada com sucesso (len=" . strlen($aiMessage) . ") - Preview: " . substr($aiMessage, 0, 100) . "...");
                } else {
                    Logger::campaign("Campanha {$campaignId}: [IA] AVISO - IA não gerou mensagem, usando conteúdo original");
                }
            } catch (\Exception $e) {
                Logger::campaign("Campanha {$campaignId}: [IA] ERRO ao gerar mensagem - " . $e->getMessage());
                // Continua com a mensagem original
            }
        }

        // 4. CRIAR OU REUTILIZAR CONVERSA (se configurado)
        $conversationId = null;
        $executeAutomations = !empty($campaign['execute_automations']);
        $conversationReused = false;
        
        Logger::campaign("Campanha {$campaignId}: create_conversation=" . ($campaign['create_conversation'] ? 'SIM' : 'NÃO') . ", execute_automations=" . ($executeAutomations ? 'SIM' : 'NÃO'));
        
        if ($campaign['create_conversation']) {
            try {
                // PRIMEIRO: Verificar se já existe conversa ABERTA para este contato
                $existingConversation = \App\Helpers\Database::fetch(
                    "SELECT id, status, funnel_id, funnel_stage_id, integration_account_id 
                     FROM conversations 
                     WHERE contact_id = ? AND status = 'open' 
                     ORDER BY created_at DESC LIMIT 1",
                    [$contact['id']]
                );
                
                if ($existingConversation) {
                    // Reutilizar conversa existente
                    $conversationId = $existingConversation['id'];
                    $conversationReused = true;
                    
                    Logger::campaign("Campanha {$campaignId}: [CONVERSA] Reutilizando conversa existente ID: {$conversationId} (status: {$existingConversation['status']}, conta_atual: {$existingConversation['integration_account_id']})");
                    
                    // Verificar o que precisa atualizar na conversa
                    $updateData = [];
                    
                    // IMPORTANTE: Atualizar o número WhatsApp (integration_account_id) para o da campanha
                    // Isso garante que a conversa reflita o número pelo qual foi feito o último contato
                    if ($existingConversation['integration_account_id'] != $integrationAccountId) {
                        $updateData['integration_account_id'] = $integrationAccountId;
                        Logger::campaign("Campanha {$campaignId}: [CONVERSA] Atualizando conta de integração: {$existingConversation['integration_account_id']} -> {$integrationAccountId}");
                    }
                    
                    // Se a campanha tem funil/etapa específicos e a conversa está em outro, mover
                    if (!empty($campaign['funnel_id']) && !empty($campaign['initial_stage_id'])) {
                        if ($existingConversation['funnel_id'] != $campaign['funnel_id']) {
                            $updateData['funnel_id'] = $campaign['funnel_id'];
                        }
                        if ($existingConversation['funnel_stage_id'] != $campaign['initial_stage_id']) {
                            $updateData['funnel_stage_id'] = $campaign['initial_stage_id'];
                        }
                    }
                    
                    // Aplicar atualizações se houver
                    if (!empty($updateData)) {
                        \App\Models\Conversation::update($conversationId, $updateData);
                        
                        if (isset($updateData['funnel_id']) || isset($updateData['funnel_stage_id'])) {
                            Logger::campaign("Campanha {$campaignId}: [CONVERSA] Conversa movida para Funil: {$campaign['funnel_id']}, Etapa: {$campaign['initial_stage_id']}");
                            
                            // Se marcou para executar automações, executar da nova etapa
                            if ($executeAutomations && isset($updateData['funnel_stage_id'])) {
                                try {
                                    \App\Services\AutomationService::executeForStageChange($conversationId, $campaign['initial_stage_id']);
                                    Logger::campaign("Campanha {$campaignId}: [AUTOMAÇÃO] Automações executadas para conversa existente {$conversationId}");
                                } catch (\Exception $e) {
                                    Logger::campaign("Campanha {$campaignId}: [ERRO] Erro ao executar automações: " . $e->getMessage());
                                }
                            }
                        }
                    }
                } else {
                    // Criar nova conversa
                    $conversationData = [
                        'contact_id' => $contact['id'],
                        'channel' => 'whatsapp',
                        'integration_account_id' => $integrationAccountId,
                        'status' => 'open',
                        'funnel_id' => $campaign['funnel_id'],
                        'stage_id' => $campaign['initial_stage_id']
                    ];

                    Logger::campaign("Campanha {$campaignId}: [CONVERSA] Criando nova conversa - Contato: {$contact['id']}, Funil: {$campaign['funnel_id']}, Etapa: {$campaign['initial_stage_id']}");

                    // Passar executeAutomations para definir se deve executar automações da etapa
                    $conversation = ConversationService::create($conversationData, $executeAutomations);
                    $conversationId = $conversation['id'];
                    
                    Logger::campaign("Campanha {$campaignId}: [CONVERSA] Conversa criada com sucesso - ID: {$conversationId}");
                    
                    if ($executeAutomations) {
                        Logger::campaign("Campanha {$campaignId}: [AUTOMAÇÃO] Automações executadas para nova conversa {$conversationId}");
                    }
                }
            } catch (\Exception $e) {
                Logger::campaign("Campanha {$campaignId}: [ERRO] Erro ao criar/reutilizar conversa: " . $e->getMessage());
            }
        }

        // 5. ENVIAR MENSAGEM
        Logger::campaign("Campanha {$campaignId}: [ENVIO] Iniciando envio para {$contact['phone']} via conta {$integrationAccountId}");
        Logger::campaign("Campanha {$campaignId}: [ENVIO] Conteúdo: " . substr($messageContent, 0, 100) . "...");
        
        $sendResult = IntegrationService::sendMessage(
            $integrationAccountId,
            $contact['phone'],
            $messageContent,
            [
                'attachments' => !empty($message['attachments']) ? json_decode($message['attachments'], true) : []
            ]
        );

        Logger::campaign("Campanha {$campaignId}: [ENVIO] Resultado: " . json_encode($sendResult));

        // Verificar sucesso - WhatsAppService retorna 'success' e 'message_id'
        $isSuccess = !empty($sendResult['success']) || !empty($sendResult['id']) || !empty($sendResult['message_id']);
        $externalId = $sendResult['id'] ?? $sendResult['message_id'] ?? null;
        
        if (!$sendResult || !$isSuccess) {
            $errorMsg = $sendResult['error'] ?? ($sendResult['message'] ?? 'Erro desconhecido');
            Logger::campaign("Campanha {$campaignId}: [ENVIO] FALHA - {$errorMsg}");
            throw new \Exception("Falha ao enviar mensagem: " . $errorMsg);
        }
        
        Logger::campaign("Campanha {$campaignId}: [ENVIO] SUCESSO - ID externo: " . ($externalId ?? 'N/A'));

        // 6. CRIAR REGISTRO NA TABELA MESSAGES
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_type' => 'agent',
            'sender_id' => $campaign['created_by'] ?? 1,
            'content' => $messageContent, // Usar mensagem gerada (IA ou original)
            'message_type' => 'text',
            'status' => 'sent',
            'external_id' => $externalId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if (!empty($message['attachments'])) {
            $messageData['attachments'] = $message['attachments'];
            $messageData['message_type'] = 'image'; // Simplificado
        }

        $messageId = \App\Models\Message::createMessage($messageData);

        // 6. ATUALIZAR CAMPAIGN_MESSAGE
        CampaignMessage::markAsSent($message['id'], $messageId, $integrationAccountId, $conversationId);

        // 7. INCREMENTAR CONTADORES
        Campaign::incrementSent($campaignId);

        // 8. ADICIONAR TAG (se configurado)
        if (!empty($campaign['tag_on_send']) && $conversationId) {
            try {
                $tag = \App\Models\Tag::whereFirst('name', '=', $campaign['tag_on_send']);
                if ($tag) {
                    \App\Models\Tag::addToConversation($conversationId, $tag['id']);
                }
            } catch (\Exception $e) {
                Logger::campaign("[ERRO] Erro ao adicionar tag: " . $e->getMessage());
            }
        }

        // 9. REGISTRAR NO LOG DE ROTAÇÃO
        self::logRotation($campaignId, $integrationAccountId, $message['id']);

        Logger::campaign("[ENVIADO] CampanhaID={$campaignId}, Contato={$contact['name']} ({$contact['phone']}), ContaID={$integrationAccountId}");

        return [
            'message_id' => $message['id'],
            'contact_id' => $message['contact_id'],
            'conversation_id' => $conversationId,
            'account_id' => $integrationAccountId,
            'status' => 'sent'
        ];
    }

    /**
     * ROTAÇÃO DE CONTAS - ESTRATÉGIAS
     */

    /**
     * ID da campanha atual (para verificar limite por conta)
     */
    private static ?int $currentCampaignId = null;
    
    /**
     * Selecionar conta baseado na estratégia
     */
    private static function selectAccount(array $accountIds, string $strategy = 'round_robin'): ?int
    {
        // Filtrar contas que ainda não atingiram o limite diário
        $availableAccounts = self::filterAccountsByDailyLimit($accountIds);
        
        if (empty($availableAccounts)) {
            Logger::campaign("[ROTAÇÃO] Nenhuma conta disponível - todas atingiram limite diário");
            return null;
        }
        
        switch ($strategy) {
            case 'round_robin':
                return self::selectAccountRoundRobin($availableAccounts);
            
            case 'random':
                return self::selectAccountRandom($availableAccounts);
            
            case 'by_load':
                return self::selectAccountByLoad($availableAccounts);
            
            default:
                return self::selectAccountRoundRobin($availableAccounts);
        }
    }
    
    /**
     * Filtrar contas que ainda não atingiram o limite diário
     */
    private static function filterAccountsByDailyLimit(array $accountIds): array
    {
        if (empty(self::$currentCampaignId)) {
            return $accountIds;
        }
        
        $campaign = Campaign::find(self::$currentCampaignId);
        if (!$campaign || empty($campaign['daily_limit_per_account'])) {
            return $accountIds;
        }
        
        $limit = (int)$campaign['daily_limit_per_account'];
        $availableAccounts = [];
        $today = date('Y-m-d');
        
        foreach ($accountIds as $accountId) {
            // Contar mensagens enviadas hoje por esta conta nesta campanha
            $sql = "SELECT COUNT(*) as total 
                    FROM campaign_messages 
                    WHERE campaign_id = ? 
                    AND integration_account_id = ? 
                    AND DATE(sent_at) = ?
                    AND status = 'sent'";
            
            $result = \App\Helpers\Database::fetch($sql, [self::$currentCampaignId, $accountId, $today]);
            $sentToday = (int)($result['total'] ?? 0);
            
            if ($sentToday < $limit) {
                $availableAccounts[] = $accountId;
            } else {
                Logger::campaign("[ROTAÇÃO] Conta {$accountId} atingiu limite diário ({$sentToday}/{$limit})");
            }
        }
        
        return $availableAccounts;
    }

    /**
     * Round Robin: Revezamento justo
     */
    private static function selectAccountRoundRobin(array $accountIds): ?int
    {
        Logger::campaign("[ROTAÇÃO] Round Robin: Recebido IDs=" . json_encode($accountIds));
        
        if (empty($accountIds)) {
            Logger::campaign("[ROTAÇÃO] Round Robin: Lista de IDs vazia!");
            return null;
        }

        // Usar hash dos IDs como chave única
        $key = md5(json_encode($accountIds));
        
        if (!isset(self::$lastAccountIndex[$key])) {
            self::$lastAccountIndex[$key] = 0;
        }

        // Filtrar apenas contas ativas
        $activeAccounts = [];
        foreach ($accountIds as $accountId) {
            $account = IntegrationAccount::find($accountId);
            if ($account && $account['status'] === 'active') {
                $activeAccounts[] = $accountId;
                Logger::campaign("[ROTAÇÃO] Round Robin: Conta {$accountId} ({$account['name']}) está ATIVA");
            } else {
                $status = $account ? $account['status'] : 'NÃO ENCONTRADA';
                $name = $account ? $account['name'] : 'N/A';
                Logger::campaign("[ROTAÇÃO] Round Robin: Conta {$accountId} ({$name}) IGNORADA - status: {$status}");
            }
        }

        if (empty($activeAccounts)) {
            Logger::campaign("[ROTAÇÃO] Round Robin: NENHUMA conta ativa encontrada!");
            return null;
        }

        $index = self::$lastAccountIndex[$key] % count($activeAccounts);
        $selectedId = $activeAccounts[$index];

        self::$lastAccountIndex[$key]++;

        Logger::campaign("[ROTAÇÃO] Round Robin: Conta selecionada={$selectedId}, Índice={$index}, Total ativas=" . count($activeAccounts));

        return $selectedId;
    }

    /**
     * Random: Aleatório
     */
    private static function selectAccountRandom(array $accountIds): ?int
    {
        if (empty($accountIds)) {
            return null;
        }

        // Filtrar apenas contas ativas
        $activeAccounts = [];
        foreach ($accountIds as $accountId) {
            $account = IntegrationAccount::find($accountId);
            if ($account && $account['status'] === 'active') {
                $activeAccounts[] = $accountId;
            }
        }

        if (empty($activeAccounts)) {
            return null;
        }

        $selectedId = $activeAccounts[array_rand($activeAccounts)];
        
        Logger::campaign("[ROTAÇÃO] Random: Conta selecionada={$selectedId}");
        
        return $selectedId;
    }

    /**
     * By Load: Por carga (menos usada nas últimas 24h)
     */
    private static function selectAccountByLoad(array $accountIds): ?int
    {
        if (empty($accountIds)) {
            return null;
        }

        $loads = [];
        
        foreach ($accountIds as $accountId) {
            $account = IntegrationAccount::find($accountId);
            if (!$account || $account['status'] !== 'active') {
                continue;
            }

            // Contar mensagens enviadas nas últimas 24h
            $sql = "SELECT COUNT(*) as total 
                    FROM campaign_messages 
                    WHERE integration_account_id = ? 
                    AND sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $result = \App\Helpers\Database::fetch($sql, [$accountId]);
            $loads[$accountId] = (int)($result['total'] ?? 0);
        }

        if (empty($loads)) {
            return null;
        }

        // Retornar conta com menor carga
        asort($loads);
        $selectedId = array_key_first($loads);

        Logger::campaign("[ROTAÇÃO] By Load: Conta selecionada={$selectedId}, Carga={$loads[$selectedId]}");

        return $selectedId;
    }

    /**
     * VALIDAÇÕES
     */

    /**
     * Verificar se deve pular este contato
     */
    private static function shouldSkipContact(int $campaignId, int $contactId): array
    {
        $campaign = Campaign::find($campaignId);
        $contact = Contact::find($contactId);

        if (!$campaign || !$contact) {
            return ['skip' => true, 'reason' => 'Campanha ou contato não encontrado'];
        }

        // 1. Verificar blacklist
        if ($campaign['respect_blacklist'] && CampaignBlacklist::isBlacklisted($contactId)) {
            return ['skip' => true, 'reason' => 'Contato na blacklist'];
        }

        // 2. Verificar se já enviou nesta campanha (duplicatas)
        if ($campaign['skip_duplicates'] && CampaignMessage::hasContactReceived($campaignId, $contactId)) {
            return ['skip' => true, 'reason' => 'Já enviou nesta campanha'];
        }

        // 3. Verificar conversas recentes
        if ($campaign['skip_recent_conversations']) {
            $hours = $campaign['skip_recent_hours'] ?? 24;
            $sql = "SELECT COUNT(*) as total 
                    FROM conversations 
                    WHERE contact_id = ? 
                    AND status = 'open'
                    AND updated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            
            $result = \App\Helpers\Database::fetch($sql, [$contactId, $hours]);
            
            if (($result['total'] ?? 0) > 0) {
                return ['skip' => true, 'reason' => "Conversa ativa nas últimas {$hours}h"];
            }
        }

        // 4. Validar telefone
        if (empty($contact['phone'])) {
            return ['skip' => true, 'reason' => 'Contato sem telefone'];
        }

        return ['skip' => false, 'reason' => null];
    }

    /**
     * Verificar se pode enviar agora (janela de horário)
     */
    private static function canSendNow(int $campaignId): bool
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return false;
        }

        // Se não tem janela configurada, pode enviar sempre
        if (empty($campaign['send_window_start']) || empty($campaign['send_window_end'])) {
            return true;
        }

        $timezone = new \DateTimeZone($campaign['timezone'] ?? 'America/Sao_Paulo');
        $now = new \DateTime('now', $timezone);

        $currentTime = $now->format('H:i:s');
        $currentDay = (int)$now->format('N'); // 1 = Segunda, 7 = Domingo

        // Verificar dia da semana
        if (!empty($campaign['send_days'])) {
            $allowedDays = json_decode($campaign['send_days'], true);
            if (is_array($allowedDays) && !in_array($currentDay, $allowedDays)) {
                return false;
            }
        }

        // Verificar horário
        $startTime = $campaign['send_window_start'];
        $endTime = $campaign['send_window_end'];

        if ($currentTime < $startTime || $currentTime > $endTime) {
            return false;
        }

        return true;
    }

    /**
     * CADÊNCIA
     */

    /**
     * Aplicar cadência (delay entre mensagens)
     * Suporta intervalo fixo ou aleatório
     */
    private static function applyCadence(int $campaignId): void
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return;
        }

        // Verificar se usa intervalo aleatório
        if (!empty($campaign['random_interval_enabled'])) {
            $minInterval = (int)($campaign['random_interval_min'] ?? 30);
            $maxInterval = (int)($campaign['random_interval_max'] ?? 120);
            
            // Garantir que min <= max
            if ($minInterval > $maxInterval) {
                $temp = $minInterval;
                $minInterval = $maxInterval;
                $maxInterval = $temp;
            }
            
            // Gerar intervalo aleatório
            $intervalSeconds = rand($minInterval, $maxInterval);
            Logger::campaign("Campanha {$campaignId}: [CADÊNCIA] Intervalo aleatório de {$intervalSeconds}s (range: {$minInterval}s - {$maxInterval}s)");
        } else {
            // Intervalo fixo
            $intervalSeconds = (int)($campaign['send_interval_seconds'] ?? 6);
        }
        
        if ($intervalSeconds > 0) {
            sleep($intervalSeconds);
        }
    }

    /**
     * Registrar no log de rotação
     */
    private static function logRotation(int $campaignId, int $integrationAccountId, int $messageId): void
    {
        try {
            $sql = "INSERT INTO campaign_rotation_log 
                    (campaign_id, integration_account_id, campaign_message_id, messages_sent, last_used_at) 
                    VALUES (?, ?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE 
                    messages_sent = messages_sent + 1,
                    last_used_at = NOW()";
            
            \App\Helpers\Database::execute($sql, [$campaignId, $integrationAccountId, $messageId]);
        } catch (\Exception $e) {
            Logger::campaign("[ERRO] Erro ao registrar log de rotação: " . $e->getMessage());
        }
    }
}
