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
        Logger::info("=== FORÇAR DISPARO - Campanha {$campaignId} ===");

        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return ['success' => false, 'message' => 'Campanha não encontrada'];
        }

        // Verificar se a campanha está em um status válido para envio
        if (!in_array($campaign['status'], ['running', 'scheduled', 'paused', 'draft'])) {
            return ['success' => false, 'message' => 'Campanha não está em status válido para envio'];
        }

        // Buscar próxima mensagem pendente
        $message = CampaignMessage::getNextPending($campaignId);
        if (!$message) {
            return ['success' => false, 'message' => 'Nenhuma mensagem pendente na campanha'];
        }

        $contact = Contact::find($message['contact_id']);
        $contactName = $contact['name'] ?? 'Contato #' . $message['contact_id'];

        try {
            // Processar a mensagem imediatamente
            $result = self::processMessage($campaignId, $message);
            
            Logger::info("Forçar disparo: Mensagem {$message['id']} enviada para {$contactName}");

            return [
                'success' => true,
                'message_id' => $message['id'],
                'contact_id' => $message['contact_id'],
                'contact_name' => $contactName,
                'status' => $result['status'] ?? 'sent',
                'external_id' => $result['external_id'] ?? null
            ];
        } catch (\Exception $e) {
            Logger::error("Forçar disparo: Erro - " . $e->getMessage());
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
        Logger::info("=== INICIANDO PROCESSAMENTO DE CAMPANHAS ===");

        $processed = [];

        // 1. Buscar campanhas ativas
        $campaigns = Campaign::getActive();
        
        if (empty($campaigns)) {
            Logger::info("Nenhuma campanha ativa encontrada");
            return $processed;
        }

        Logger::info("Campanhas ativas: " . count($campaigns));

        foreach ($campaigns as $campaign) {
            $campaignId = $campaign['id'];
            
            try {
                // 2. Verificar se pode enviar agora (janela de horário)
                if (!self::canSendNow($campaignId)) {
                    Logger::info("Campanha {$campaignId} fora da janela de envio");
                    continue;
                }

                // 2.1. Resetar contadores se necessário (diário/horário)
                self::resetCountersIfNeeded($campaignId);
                
                // 2.2. Verificar limites antes de continuar
                $limitCheck = self::checkLimits($campaignId);
                if (!$limitCheck['can_send']) {
                    Logger::info("Campanha {$campaignId}: {$limitCheck['reason']}");
                    continue;
                }

                // 3. Calcular quantas mensagens podemos enviar
                $effectiveLimit = self::calculateEffectiveLimit($campaignId, $limit);
                if ($effectiveLimit <= 0) {
                    Logger::info("Campanha {$campaignId}: Limite atingido para este ciclo");
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
                        Logger::info("Campanha {$campaignId} concluída!");
                    }
                    continue;
                }

                Logger::info("Campanha {$campaignId}: " . count($messages) . " mensagens a processar (limite efetivo: {$effectiveLimit})");

                // 5. Processar cada mensagem
                $sentInBatch = 0;
                foreach ($messages as $message) {
                    try {
                        // Verificar limite de lote
                        if (!empty($campaign['batch_size']) && $sentInBatch >= $campaign['batch_size']) {
                            $pauseMinutes = $campaign['batch_pause_minutes'] ?? 5;
                            Logger::info("Campanha {$campaignId}: Lote de {$campaign['batch_size']} msgs enviado, pausando {$pauseMinutes} min");
                            sleep($pauseMinutes * 60);
                            $sentInBatch = 0;
                        }
                        
                        // Re-verificar limites após cada envio (podem ter sido atingidos)
                        $limitCheck = self::checkLimits($campaignId);
                        if (!$limitCheck['can_send']) {
                            Logger::info("Campanha {$campaignId}: {$limitCheck['reason']} - parando processamento");
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
                        Logger::error("Erro ao processar mensagem {$message['id']}: " . $e->getMessage());
                        
                        // Marcar como falha
                        CampaignMessage::markAsFailed($message['id'], $e->getMessage());
                        Campaign::incrementFailed($campaignId);
                    }
                }

                // 7. Atualizar último processamento
                Campaign::updateLastProcessed($campaignId);

            } catch (\Exception $e) {
                Logger::error("Erro ao processar campanha {$campaignId}: " . $e->getMessage());
            }
        }

        Logger::info("=== PROCESSAMENTO CONCLUÍDO: " . count($processed) . " mensagens ===");

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
            Logger::info("Campanha {$campaignId}: Contador diário resetado");
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

        $integrationAccountId = self::selectAccount($accountIds, $campaign['rotation_strategy']);
        if (!$integrationAccountId) {
            throw new \Exception("Nenhuma conta ativa disponível");
        }

        // 3. GERAR MENSAGEM COM IA (se configurado)
        $messageContent = $message['content'];
        if (!empty($campaign['ai_message_enabled']) && !empty($campaign['ai_message_prompt'])) {
            Logger::info("Campanha {$campaignId}: Gerando mensagem com IA para contato {$contact['id']}");
            try {
                $aiMessage = \App\Services\OpenAIService::generateCampaignMessage(
                    $campaign['ai_message_prompt'],
                    $contact,
                    $message['content'], // Mensagem original como referência
                    (float)($campaign['ai_temperature'] ?? 0.7)
                );
                
                if ($aiMessage) {
                    $messageContent = $aiMessage;
                    Logger::info("Campanha {$campaignId}: Mensagem gerada com IA (len=" . strlen($aiMessage) . ")");
                } else {
                    Logger::warning("Campanha {$campaignId}: IA não gerou mensagem, usando conteúdo original");
                }
            } catch (\Exception $e) {
                Logger::error("Campanha {$campaignId}: Erro ao gerar mensagem com IA: " . $e->getMessage());
                // Continua com a mensagem original
            }
        }

        // 4. CRIAR CONVERSA (se configurado)
        $conversationId = null;
        $executeAutomations = !empty($campaign['execute_automations']);
        
        if ($campaign['create_conversation']) {
            $conversationData = [
                'contact_id' => $contact['id'],
                'channel' => 'whatsapp',
                'integration_account_id' => $integrationAccountId,
                'status' => 'open',
                'funnel_id' => $campaign['funnel_id'],
                'stage_id' => $campaign['initial_stage_id']
            ];

            try {
                // Passar executeAutomations para definir se deve executar automações da etapa
                $conversation = ConversationService::create($conversationData, $executeAutomations);
                $conversationId = $conversation['id'];
                
                if ($executeAutomations) {
                    Logger::info("Campanha {$campaignId}: Automações executadas para conversa {$conversationId}");
                }
            } catch (\Exception $e) {
                Logger::error("Erro ao criar conversa: " . $e->getMessage());
            }
        }

        // 5. ENVIAR MENSAGEM
        $sendResult = IntegrationService::sendMessage(
            $integrationAccountId,
            $contact['phone'],
            $messageContent,
            [
                'attachments' => !empty($message['attachments']) ? json_decode($message['attachments'], true) : []
            ]
        );

        if (!$sendResult || empty($sendResult['id'])) {
            throw new \Exception("Falha ao enviar mensagem: " . ($sendResult['error'] ?? 'Erro desconhecido'));
        }

        // 6. CRIAR REGISTRO NA TABELA MESSAGES
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_type' => 'agent',
            'sender_id' => $campaign['created_by'] ?? 1,
            'content' => $messageContent, // Usar mensagem gerada (IA ou original)
            'message_type' => 'text',
            'status' => 'sent',
            'external_id' => $sendResult['id'] ?? null,
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
                Logger::error("Erro ao adicionar tag: " . $e->getMessage());
            }
        }

        // 9. REGISTRAR NO LOG DE ROTAÇÃO
        self::logRotation($campaignId, $integrationAccountId, $message['id']);

        Logger::info("Mensagem enviada: CampanhaID={$campaignId}, ContatoID={$contact['id']}, Conta={$integrationAccountId}");

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
            Logger::info("Nenhuma conta disponível - todas atingiram limite diário");
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
                Logger::info("Conta {$accountId} atingiu limite diário ({$sentToday}/{$limit})");
            }
        }
        
        return $availableAccounts;
    }

    /**
     * Round Robin: Revezamento justo
     */
    private static function selectAccountRoundRobin(array $accountIds): ?int
    {
        if (empty($accountIds)) {
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
            }
        }

        if (empty($activeAccounts)) {
            return null;
        }

        $index = self::$lastAccountIndex[$key] % count($activeAccounts);
        $selectedId = $activeAccounts[$index];

        self::$lastAccountIndex[$key]++;

        Logger::info("Rotação Round Robin: Conta selecionada={$selectedId}, Índice={$index}");

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
        
        Logger::info("Rotação Random: Conta selecionada={$selectedId}");
        
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

        Logger::info("Rotação By Load: Conta selecionada={$selectedId}, Carga={$loads[$selectedId]}");

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
            Logger::info("Campanha {$campaignId}: Intervalo aleatório de {$intervalSeconds}s (range: {$minInterval}s - {$maxInterval}s)");
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
            Logger::error("Erro ao registrar log de rotação: " . $e->getMessage());
        }
    }
}
