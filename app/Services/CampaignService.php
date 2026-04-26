<?php
/**
 * Service CampaignService
 * Lógica de negócio para campanhas
 */

namespace App\Services;

use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\CampaignMessage;
use App\Models\CampaignVariant;
use App\Models\Tag;
use App\Services\CampaignNotificationService;
use App\Services\ContactSegmentationService;
use App\Helpers\Validator;
use App\Helpers\Logger;

class CampaignService
{
    /**
     * Criar campanha
     */
    public static function create(array $data): int
    {
        // Verificar se está usando IA para gerar mensagem
        $aiMessageEnabled = !empty($data['ai_message_enabled']) && $data['ai_message_enabled'] !== '0';
        $useTemplate = !empty($data['use_template']) && $data['use_template'] !== '0';
        $roundRobinEnabled = !empty($data['round_robin_enabled']) && $data['round_robin_enabled'] !== '0';
        $roundRobinMessages = $data['round_robin_messages'] ?? [];

        // Validar dados básicos
        $validationRules = [
            'name' => 'required|string|max:255',
            'channel' => 'required|string|in:whatsapp',
            'integration_account_ids' => 'required|array',
        ];
        
        // Validação condicional do conteúdo
        if ($roundRobinEnabled) {
            if (empty($roundRobinMessages) || count($roundRobinMessages) < 2) {
                throw new \InvalidArgumentException('Round-robin requer no mínimo 2 mensagens');
            }
        } elseif ($useTemplate) {
            $validationRules['template_name'] = 'required|string';
        } elseif ($aiMessageEnabled) {
            $validationRules['ai_message_prompt'] = 'required|string';
        } else {
            $validationRules['message_content'] = 'required|string';
        }
        
        $errors = Validator::validate($data, $validationRules);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Validar target (lista ou filtro)
        $targetType = $data['target_type'] ?? 'list';
        if ($targetType === 'list' && empty($data['contact_list_id'])) {
            throw new \InvalidArgumentException('É necessário selecionar uma lista');
        }
        if ($targetType === 'filter') {
            $rules = $data['filter_config']['rules'] ?? null;
            if (!is_array($rules) || empty($rules)) {
                throw new \InvalidArgumentException('Segmento dinâmico requer ao menos uma regra de filtro');
            }
            // Não exige contact_list_id quando é filtro
            $data['contact_list_id'] = null;
        }

        // Serializar arrays para JSON
        if (isset($data['integration_account_ids']) && is_array($data['integration_account_ids'])) {
            $data['integration_account_ids'] = json_encode($data['integration_account_ids']);
        }

        if (isset($data['filter_config']) && is_array($data['filter_config'])) {
            $data['filter_config'] = json_encode($data['filter_config']);
        }

        if (isset($data['message_variables']) && is_array($data['message_variables'])) {
            $data['message_variables'] = json_encode($data['message_variables']);
        }

        if (isset($data['attachments']) && is_array($data['attachments'])) {
            $data['attachments'] = json_encode($data['attachments']);
        }

        if (isset($data['send_days']) && is_array($data['send_days'])) {
            $data['send_days'] = json_encode($data['send_days']);
        }

        // Defaults
        $data['status'] = $data['status'] ?? 'draft';
        $data['channel'] = 'whatsapp'; // Forçar WhatsApp por enquanto
        $data['rotation_strategy'] = $data['rotation_strategy'] ?? 'round_robin';
        $data['send_rate_per_minute'] = $data['send_rate_per_minute'] ?? 10;
        $data['send_interval_seconds'] = $data['send_interval_seconds'] ?? 6;
        $data['timezone'] = $data['timezone'] ?? 'America/Sao_Paulo';
        
        // Converter campos de data/FK vazios para NULL
        $nullableFields = [
            'scheduled_at', 'send_window_start', 'send_window_end',
            'funnel_id', 'initial_stage_id', 'reply_stage_id',
            'contact_list_id', 'message_template_id', 'tag_on_send',
        ];
        foreach ($nullableFields as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === '0' || $data[$field] === 0)) {
                $data[$field] = null;
            }
        }
        
        // Processar controles avançados de taxa de envio
        // Limites (converter string vazia para null)
        $data['daily_limit'] = !empty($data['daily_limit']) ? (int)$data['daily_limit'] : null;
        $data['hourly_limit'] = !empty($data['hourly_limit']) ? (int)$data['hourly_limit'] : null;
        $data['daily_limit_per_account'] = !empty($data['daily_limit_per_account']) ? (int)$data['daily_limit_per_account'] : null;
        
        // Intervalo aleatório
        $data['random_interval_enabled'] = (!empty($data['random_interval_enabled']) && $data['random_interval_enabled'] !== '0') ? 1 : 0;
        $data['random_interval_min'] = (int)($data['random_interval_min'] ?? 30);
        $data['random_interval_max'] = (int)($data['random_interval_max'] ?? 120);
        
        // Lotes
        $data['batch_size'] = !empty($data['batch_size']) ? (int)$data['batch_size'] : null;
        $data['batch_pause_minutes'] = (int)($data['batch_pause_minutes'] ?? 5);
        
        // Contadores inicializados em zero
        $data['sent_today'] = 0;
        $data['sent_this_hour'] = 0;
        
        // Geração de mensagem com IA
        $data['ai_message_enabled'] = (!empty($data['ai_message_enabled']) && $data['ai_message_enabled'] !== '0') ? 1 : 0;
        $data['ai_message_prompt'] = $data['ai_message_prompt'] ?? null;
        $data['ai_temperature'] = isset($data['ai_temperature']) ? (float)$data['ai_temperature'] : 0.7;
        
        // Se IA está habilitada e message_content está vazio, usar o prompt como fallback
        if ($data['ai_message_enabled'] && empty($data['message_content'])) {
            $data['message_content'] = $data['ai_reference_message'] ?? '[Mensagem gerada por IA]';
        }
        
        // Round-robin: placeholder no message_content
        if ($roundRobinEnabled) {
            $data['round_robin_enabled'] = 1;
            $data['round_robin_current_index'] = 0;
            if (empty($data['message_content'])) {
                $data['message_content'] = '[Round-Robin]';
            }
        }

        // Template Notificame: salvar dados do template no message_variables
        if (!$roundRobinEnabled && $useTemplate && !empty($data['template_name'])) {
            $templateMeta = [
                'use_template' => true,
                'template_name' => $data['template_name'],
                'template_account_id' => $data['template_account_id'] ?? null,
                'template_params' => $data['template_params'] ?? []
            ];
            $data['message_variables'] = json_encode($templateMeta);
            if (empty($data['message_content'])) {
                $data['message_content'] = '[Template: ' . $data['template_name'] . ']';
            }
        }
        // Limpar campos auxiliares que não pertencem à tabela
        unset($data['use_template'], $data['template_name'], $data['template_params'],
              $data['template_account_id'], $data['message_type'], $data['round_robin_messages']);
        
        // Execução de automações
        $data['execute_automations'] = (!empty($data['execute_automations']) && $data['execute_automations'] !== '0') ? 1 : 0;
        
        // Filtros de contatos
        $data['skip_duplicates'] = (!empty($data['skip_duplicates']) && $data['skip_duplicates'] !== '0') ? 1 : 0;
        $data['skip_recent_conversations'] = (!empty($data['skip_recent_conversations']) && $data['skip_recent_conversations'] !== '0') ? 1 : 0;
        $data['skip_recent_hours'] = (int)($data['skip_recent_hours'] ?? 24);
        $data['respect_blacklist'] = (!empty($data['respect_blacklist']) && $data['respect_blacklist'] !== '0') ? 1 : 0;

        // Modo contínuo
        $data['continuous_mode'] = (!empty($data['continuous_mode']) && $data['continuous_mode'] !== '0') ? 1 : 0;

        // Criar campanha
        $campaignId = Campaign::create($data);

        // Salvar variantes de round-robin
        if ($roundRobinEnabled && !empty($roundRobinMessages)) {
            self::saveRoundRobinVariants($campaignId, $roundRobinMessages);
        }

        Logger::campaign("Campanha criada: ID={$campaignId}, Nome={$data['name']}");
        
        return $campaignId;
    }

    /**
     * Atualizar campanha
     */
    public static function update(int $campaignId, array $data): bool
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        // Não permite editar campanhas rodando ou completas
        if (in_array($campaign['status'], ['running', 'completed'])) {
            throw new \Exception('Não é possível editar campanha em execução ou completa');
        }

        // Round-robin
        $roundRobinEnabled = !empty($data['round_robin_enabled']) && $data['round_robin_enabled'] !== '0';
        $roundRobinMessages = $data['round_robin_messages'] ?? [];

        if ($roundRobinEnabled && !empty($roundRobinMessages) && count($roundRobinMessages) < 2) {
            throw new \InvalidArgumentException('Round-robin requer no mínimo 2 mensagens');
        }

        if ($roundRobinEnabled) {
            $data['round_robin_enabled'] = 1;
            if (empty($data['message_content'])) {
                $data['message_content'] = '[Round-Robin]';
            }
        }

        unset($data['round_robin_messages']);

        // Modo contínuo
        if (isset($data['continuous_mode'])) {
            $data['continuous_mode'] = (!empty($data['continuous_mode']) && $data['continuous_mode'] !== '0') ? 1 : 0;
        }

        // Serializar arrays
        if (isset($data['integration_account_ids']) && is_array($data['integration_account_ids'])) {
            $data['integration_account_ids'] = json_encode($data['integration_account_ids']);
        }

        if (isset($data['filter_config']) && is_array($data['filter_config'])) {
            $data['filter_config'] = json_encode($data['filter_config']);
        }

        if (isset($data['message_variables']) && is_array($data['message_variables'])) {
            $data['message_variables'] = json_encode($data['message_variables']);
        }

        if (isset($data['attachments']) && is_array($data['attachments'])) {
            $data['attachments'] = json_encode($data['attachments']);
        }

        if (isset($data['send_days']) && is_array($data['send_days'])) {
            $data['send_days'] = json_encode($data['send_days']);
        }

        $result = Campaign::update($campaignId, $data);

        // Atualizar variantes de round-robin
        if ($roundRobinEnabled && !empty($roundRobinMessages)) {
            CampaignVariant::deleteRoundRobinByCampaign($campaignId);
            self::saveRoundRobinVariants($campaignId, $roundRobinMessages);
        } elseif (!$roundRobinEnabled) {
            CampaignVariant::deleteRoundRobinByCampaign($campaignId);
        }

        return $result;
    }

    /**
     * Salvar variantes de round-robin na tabela campaign_variants
     */
    private static function saveRoundRobinVariants(int $campaignId, array $messages): void
    {
        foreach ($messages as $index => $msg) {
            $variantName = 'RR' . ($index + 1);
            $msgType = $msg['message_type'] ?? 'text';
            $msgContent = $msg['message_content'] ?? '';
            $msgVariables = null;

            if ($msgType === 'template') {
                $templateData = [
                    'use_template' => true,
                    'template_name' => $msg['template_name'] ?? '',
                    'template_account_id' => $msg['template_account_id'] ?? null,
                    'template_params' => $msg['template_params'] ?? []
                ];
                $msgVariables = json_encode($templateData);
                if (empty($msgContent)) {
                    $msgContent = '[Template: ' . ($msg['template_name'] ?? '') . ']';
                }
            }

            CampaignVariant::create([
                'campaign_id'      => $campaignId,
                'variant_name'     => $variantName,
                'message_content'  => $msgContent,
                'percentage'       => 0,
                'variant_type'     => 'round_robin',
                'message_type'     => $msgType,
                'message_variables' => $msgVariables,
            ]);
        }

        Logger::campaign("Campanha {$campaignId}: " . count($messages) . " variantes round-robin salvas");
    }

    /**
     * Preparar campanha (criar registros de mensagens)
     */
    public static function prepare(int $campaignId): array
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        // Obter contatos baseado no target_type
        $contacts = [];
        
        if ($campaign['target_type'] === 'list') {
            if (empty($campaign['contact_list_id'])) {
                throw new \Exception('Lista de contatos não definida');
            }

            $contacts = ContactList::getContacts($campaign['contact_list_id']);
        } elseif ($campaign['target_type'] === 'filter') {
            $filterConfig = $campaign['filter_config'] ?? null;
            if (is_string($filterConfig)) {
                $filterConfig = json_decode($filterConfig, true) ?: [];
            }
            if (!is_array($filterConfig) || empty($filterConfig['rules'])) {
                throw new \Exception('Segmento dinâmico sem regras configuradas');
            }

            $contactIds = ContactSegmentationService::resolve($filterConfig);
            if (!empty($contactIds)) {
                // Recupera contatos completos preservando o formato esperado
                // (mesmas colunas que ContactList::getContacts retorna +
                // custom_variables/added_at vazios para compatibilidade)
                $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
                $contacts = \App\Helpers\Database::fetchAll(
                    "SELECT c.*, NULL AS custom_variables, NOW() AS added_at
                     FROM contacts c
                     WHERE c.id IN ({$placeholders})",
                    $contactIds
                );
            }
        }

        if (empty($contacts)) {
            throw new \Exception('Nenhum contato encontrado para esta campanha');
        }

        // Criar registros de campaign_messages
        $created = 0;
        $skipped = 0;

        foreach ($contacts as $contact) {
            // Verificar se já existe registro para este contato
            if (CampaignMessage::hasContactReceived($campaignId, $contact['id'])) {
                $skipped++;
                continue;
            }

            // Processar variáveis da mensagem
            $customVars = [];
            if (!empty($contact['custom_variables'])) {
                $customVars = is_string($contact['custom_variables']) 
                    ? json_decode($contact['custom_variables'], true) 
                    : $contact['custom_variables'];
            }

            $processedContent = self::processMessageVariables(
                $campaign['message_content'], 
                $contact['id'],
                $customVars
            );

            // Criar registro
            $messageData = [
                'campaign_id' => $campaignId,
                'contact_id' => $contact['id'],
                'content' => $processedContent,
                'attachments' => $campaign['attachments'],
                'status' => 'pending',
                'scheduled_at' => $campaign['scheduled_at'] ?? date('Y-m-d H:i:s')
            ];

            CampaignMessage::create($messageData);
            $created++;
        }

        // Atualizar total de contatos da campanha
        Campaign::update($campaignId, ['total_contacts' => $created]);

        Logger::campaign("Campanha preparada: ID={$campaignId}, Criadas={$created}, Puladas={$skipped}");

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => $created + $skipped
        ];
    }

    /**
     * Processar variáveis da mensagem
     */
    private static function processMessageVariables(string $content, int $contactId, array $customVars = []): string
    {
        $contact = \App\Models\Contact::find($contactId);
        if (!$contact) {
            return $content;
        }

        // Variáveis padrão
        $variables = [
            'nome' => $contact['name'] ?? '',
            'primeiro_nome' => !empty($contact['name']) ? explode(' ', $contact['name'])[0] : '',
            'sobrenome' => $contact['last_name'] ?? '',
            'email' => $contact['email'] ?? '',
            'telefone' => $contact['phone'] ?? '',
            'cidade' => $contact['city'] ?? '',
            'pais' => $contact['country'] ?? '',
            'empresa' => $contact['company'] ?? '',
        ];

        // Custom attributes do contato
        if (!empty($contact['custom_attributes'])) {
            $customAttrs = json_decode($contact['custom_attributes'], true) ?? [];
            $variables = array_merge($variables, $customAttrs);
        }

        // Variáveis específicas da lista
        $variables = array_merge($variables, $customVars);

        // Processar template
        return \App\Models\MessageTemplate::processTemplate($content, $variables);
    }

    /**
     * Iniciar campanha
     * Prepara automaticamente se ainda não foi preparada
     */
    public static function start(int $campaignId): bool
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        if ($campaign['status'] !== 'draft' && $campaign['status'] !== 'paused' && $campaign['status'] !== 'scheduled') {
            throw new \Exception('Campanha não pode ser iniciada. Status atual: ' . $campaign['status']);
        }

        // Verificar se tem mensagens preparadas
        $pendingCount = CampaignMessage::countByStatus($campaignId, 'pending');
        
        // Se não tem mensagens pendentes, preparar automaticamente
        if ($pendingCount === 0) {
            \App\Helpers\Logger::campaign("Campanha {$campaignId}: Preparando automaticamente antes de iniciar");
            $prepareResult = self::prepare($campaignId);
            
            if ($prepareResult['created'] === 0) {
                throw new \Exception('Nenhum contato disponível para envio');
            }
            
            \App\Helpers\Logger::campaign("Campanha {$campaignId}: {$prepareResult['created']} mensagens criadas");
        }

        return Campaign::update($campaignId, [
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Pausar campanha
     */
    public static function pause(int $campaignId): bool
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        if ($campaign['status'] !== 'running') {
            throw new \Exception('Apenas campanhas em execução podem ser pausadas');
        }

        return Campaign::update($campaignId, [
            'status' => 'paused',
            'paused_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Retomar campanha pausada
     */
    public static function resume(int $campaignId): bool
    {
        return self::start($campaignId); // Mesma lógica
    }

    /**
     * Cancelar campanha
     */
    public static function cancel(int $campaignId): bool
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        if ($campaign['status'] === 'completed' || $campaign['status'] === 'cancelled') {
            throw new \Exception('Campanha já está finalizada');
        }

        return Campaign::update($campaignId, [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Reiniciar campanha
     * Reseta contadores, mensagens e permite reenviar para todos os contatos
     * 
     * @param int $campaignId ID da campanha
     * @param bool $keepSent Se true, mantém registros de enviados com sucesso e só reenvia falhas
     */
    public static function restart(int $campaignId, bool $keepSent = false): array
    {
        Logger::campaign("Service::restart - Iniciando: campaignId={$campaignId}, keepSent=" . ($keepSent ? 'true' : 'false'));
        
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            Logger::campaign("Service::restart - ERRO: Campanha {$campaignId} não encontrada");
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        Logger::campaign("Service::restart - Campanha encontrada: status={$campaign['status']}, nome={$campaign['name']}");

        // Não permite reiniciar campanhas em execução
        if ($campaign['status'] === 'running') {
            Logger::campaign("Service::restart - ERRO: Campanha {$campaignId} está em execução");
            throw new \Exception('Pause a campanha antes de reiniciar');
        }

        // Contar registros antes
        Logger::campaign("Service::restart - Contando mensagens...");
        $totalMessages = CampaignMessage::countByStatus($campaignId, null); // Todos
        $sentMessages = CampaignMessage::countByStatus($campaignId, 'sent');
        $failedMessages = CampaignMessage::countByStatus($campaignId, 'failed');
        $pendingMessages = CampaignMessage::countByStatus($campaignId, 'pending');
        
        Logger::campaign("Service::restart - Contagem: total={$totalMessages}, sent={$sentMessages}, failed={$failedMessages}, pending={$pendingMessages}");
        
        $resetCount = 0;
        $deletedCount = 0;

        if ($keepSent) {
            // Apenas resetar mensagens com falha para pending
            Logger::campaign("Service::restart - Modo: Reenviar apenas falhas");
            
            \App\Helpers\Database::execute(
                "UPDATE campaign_messages 
                 SET status = 'pending', 
                     sent_at = NULL, 
                     delivered_at = NULL, 
                     read_at = NULL, 
                     replied_at = NULL,
                     error_message = NULL,
                     attempts = 0
                 WHERE campaign_id = ? AND status = 'failed'",
                [$campaignId]
            );
            $resetCount = $failedMessages;
            
            Logger::campaign("Campanha {$campaignId}: Reiniciada (apenas falhas) - {$resetCount} mensagens resetadas");
        } else {
            // Deletar todas as mensagens e preparar novamente
            Logger::campaign("Service::restart - Modo: Reinício completo");
            
            \App\Helpers\Database::execute(
                "DELETE FROM campaign_messages WHERE campaign_id = ?",
                [$campaignId]
            );
            $deletedCount = $totalMessages;
            
            Logger::campaign("Campanha {$campaignId}: Reiniciada (completa) - {$deletedCount} mensagens deletadas");
        }

        // Resetar contadores da campanha
        Logger::campaign("Service::restart - Atualizando contadores da campanha...");
        
        Campaign::update($campaignId, [
            'status' => 'draft',
            'total_sent' => $keepSent ? $sentMessages : 0,
            'total_delivered' => $keepSent ? ($campaign['total_delivered'] ?? 0) : 0,
            'total_read' => $keepSent ? ($campaign['total_read'] ?? 0) : 0,
            'total_replied' => $keepSent ? ($campaign['total_replied'] ?? 0) : 0,
            'total_failed' => 0,
            'total_skipped' => 0,
            'sent_today' => 0,
            'sent_this_hour' => 0,
            'last_counter_reset' => null,
            'last_hourly_reset' => null,
            'started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'paused_at' => null
        ]);

        Logger::campaign("Campanha {$campaignId}: Contadores resetados, status=draft");
        Logger::campaign("Service::restart - Concluído com sucesso");

        return [
            'success' => true,
            'mode' => $keepSent ? 'retry_failed' : 'full_restart',
            'messages_reset' => $resetCount,
            'messages_deleted' => $deletedCount,
            'messages_kept' => $keepSent ? $sentMessages : 0
        ];
    }

    /**
     * Obter estatísticas da campanha
     */
    public static function getStats(int $campaignId): array
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        $totalSent = $campaign['total_sent'] ?? 0;
        $totalDelivered = $campaign['total_delivered'] ?? 0;
        $totalRead = $campaign['total_read'] ?? 0;
        $totalReplied = $campaign['total_replied'] ?? 0;
        $totalFailed = $campaign['total_failed'] ?? 0;
        $totalSkipped = $campaign['total_skipped'] ?? 0;
        $totalContacts = $campaign['total_contacts'] ?? 0;

        $deliveryRate = $totalSent > 0 ? ($totalDelivered / $totalSent) * 100 : 0;
        $readRate = $totalDelivered > 0 ? ($totalRead / $totalDelivered) * 100 : 0;
        $replyRate = $totalDelivered > 0 ? ($totalReplied / $totalDelivered) * 100 : 0;
        $failureRate = $totalSent > 0 ? ($totalFailed / $totalSent) * 100 : 0;

        $progress = Campaign::getProgress($campaignId);

        return [
            'total_contacts' => $totalContacts,
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_read' => $totalRead,
            'total_replied' => $totalReplied,
            'total_failed' => $totalFailed,
            'total_skipped' => $totalSkipped,
            'delivery_rate' => round($deliveryRate, 2),
            'read_rate' => round($readRate, 2),
            'reply_rate' => round($replyRate, 2),
            'failure_rate' => round($failureRate, 2),
            'progress' => $progress
        ];
    }

    /**
     * Deletar campanha
     */
    public static function delete(int $campaignId): bool
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        // Não permite deletar campanhas rodando
        if ($campaign['status'] === 'running') {
            throw new \Exception('Não é possível deletar campanha em execução. Pause ou cancele primeiro.');
        }

        return Campaign::delete($campaignId);
    }
}
