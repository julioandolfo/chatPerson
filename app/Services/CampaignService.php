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
use App\Helpers\Validator;
use App\Helpers\Logger;

class CampaignService
{
    /**
     * Criar campanha
     */
    public static function create(array $data): int
    {
        // Validar dados básicos
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'channel' => 'required|string|in:whatsapp',
            'integration_account_ids' => 'required|array',
            'message_content' => 'required|string'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Validar target (lista ou filtro)
        if ($data['target_type'] === 'list' && empty($data['contact_list_id'])) {
            throw new \InvalidArgumentException('É necessário selecionar uma lista');
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
        
        // Criar campanha
        $campaignId = Campaign::create($data);
        
        Logger::info("Campanha criada: ID={$campaignId}, Nome={$data['name']}");
        
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

        return Campaign::update($campaignId, $data);
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
            // TODO: Implementar filtros dinâmicos
            throw new \Exception('Filtros dinâmicos ainda não implementados');
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

        Logger::info("Campanha preparada: ID={$campaignId}, Criadas={$created}, Puladas={$skipped}");

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
     */
    public static function start(int $campaignId): bool
    {
        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            throw new \InvalidArgumentException('Campanha não encontrada');
        }

        if ($campaign['status'] !== 'draft' && $campaign['status'] !== 'paused') {
            throw new \Exception('Campanha não pode ser iniciada. Status atual: ' . $campaign['status']);
        }

        // Validar se tem mensagens preparadas
        $pendingCount = CampaignMessage::countByStatus($campaignId, 'pending');
        if ($pendingCount === 0) {
            throw new \Exception('Nenhuma mensagem pendente. Prepare a campanha primeiro.');
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
