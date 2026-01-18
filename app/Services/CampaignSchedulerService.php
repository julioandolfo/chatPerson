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

                // 3. Buscar mensagens pendentes
                $messages = CampaignMessage::getPending($campaignId, $limit);
                
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

                Logger::info("Campanha {$campaignId}: {count} mensagens pendentes", ['count' => count($messages)]);

                // 4. Processar cada mensagem
                foreach ($messages as $message) {
                    try {
                        $result = self::processMessage($campaignId, $message);
                        $processed[] = $result;

                        // 5. Aplicar cadência (delay entre envios)
                        self::applyCadence($campaignId);

                    } catch (\Exception $e) {
                        Logger::error("Erro ao processar mensagem {$message['id']}: " . $e->getMessage());
                        
                        // Marcar como falha
                        CampaignMessage::markAsFailed($message['id'], $e->getMessage());
                        Campaign::incrementFailed($campaignId);
                    }
                }

                // 6. Atualizar último processamento
                Campaign::updateLastProcessed($campaignId);

            } catch (\Exception $e) {
                Logger::error("Erro ao processar campanha {$campaignId}: " . $e->getMessage());
            }
        }

        Logger::info("=== PROCESSAMENTO CONCLUÍDO: " . count($processed) . " mensagens ===");

        return $processed;
    }

    /**
     * Processar uma mensagem individual
     */
    private static function processMessage(int $campaignId, array $message): array
    {
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

        // 3. CRIAR CONVERSA (se configurado)
        $conversationId = null;
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
                $conversation = ConversationService::create($conversationData, false); // Não executar automações
                $conversationId = $conversation['id'];
            } catch (\Exception $e) {
                Logger::error("Erro ao criar conversa: " . $e->getMessage());
            }
        }

        // 4. ENVIAR MENSAGEM
        $sendResult = IntegrationService::sendMessage(
            $integrationAccountId,
            $contact['phone'],
            $message['content'],
            [
                'attachments' => !empty($message['attachments']) ? json_decode($message['attachments'], true) : []
            ]
        );

        if (!$sendResult || empty($sendResult['id'])) {
            throw new \Exception("Falha ao enviar mensagem: " . ($sendResult['error'] ?? 'Erro desconhecido'));
        }

        // 5. CRIAR REGISTRO NA TABELA MESSAGES
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_type' => 'agent',
            'sender_id' => $campaign['created_by'] ?? 1,
            'content' => $message['content'],
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
     * Selecionar conta baseado na estratégia
     */
    private static function selectAccount(array $accountIds, string $strategy = 'round_robin'): ?int
    {
        switch ($strategy) {
            case 'round_robin':
                return self::selectAccountRoundRobin($accountIds);
            
            case 'random':
                return self::selectAccountRandom($accountIds);
            
            case 'by_load':
                return self::selectAccountByLoad($accountIds);
            
            default:
                return self::selectAccountRoundRobin($accountIds);
        }
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
     */
    private static function applyCadence(int $campaignId): void
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return;
        }

        $intervalSeconds = $campaign['send_interval_seconds'] ?? 6;
        
        if ($intervalSeconds > 0) {
            usleep($intervalSeconds * 1000000); // Converter para microsegundos
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
