<?php
/**
 * ConversationActionsController - API v1
 * Ações de conversa para o app mobile: leitura, janela 24h Cloud,
 * envio de template e checagem de conversa existente.
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationService;

class ConversationActionsController
{
    /**
     * Marcar conversa como lida
     * POST /api/v1/conversations/:id/mark-read
     */
    public function markRead(string $id): void
    {
        $conversationId = (int)$id;
        $this->assertCanView($conversationId);

        try {
            Message::markAsRead($conversationId, ApiAuthMiddleware::userId());
            ConversationService::invalidateCache($conversationId);

            ApiResponse::success(null, 200, 'Conversa marcada como lida');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao marcar como lida', $e);
        }
    }

    /**
     * Marcar conversa como não lida
     * POST /api/v1/conversations/:id/mark-unread
     */
    public function markUnread(string $id): void
    {
        $conversationId = (int)$id;
        $this->assertCanView($conversationId);

        try {
            \App\Helpers\Database::execute(
                "UPDATE messages SET read_at = NULL WHERE conversation_id = ? AND sender_type = 'contact'",
                [$conversationId]
            );

            ConversationService::invalidateCache($conversationId);
            $conversation = ConversationService::getConversation($conversationId);

            ApiResponse::success([
                'unread_count' => $conversation['unread_count'] ?? 0,
            ], 200, 'Conversa marcada como não lida');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao marcar como não lida', $e);
        }
    }

    /**
     * Estado da janela de 24h (WhatsApp Cloud/CoEx/Notificame) + templates disponíveis
     * GET /api/v1/conversations/:id/cloud-window
     */
    public function cloudWindow(string $id): void
    {
        $conversationId = (int)$id;
        $conversation = $this->assertCanView($conversationId);

        try {
            $integrationAccountId = $conversation['integration_account_id'] ?? null;
            if (!empty($conversation['is_merged']) && !empty($conversation['last_customer_account_id'])) {
                $integrationAccountId = (int)$conversation['last_customer_account_id'];
            }

            if (!$integrationAccountId) {
                ApiResponse::success(['is_cloud' => false, 'within_window' => true, 'templates' => []]);
            }

            $account = \App\Models\IntegrationAccount::find($integrationAccountId);
            $provider = $account['provider'] ?? '';
            $channel = $conversation['channel'] ?? '';

            $isCloudApi = in_array($provider, ['meta_cloud', 'meta_coex']);
            $isNotificameWhatsApp = ($provider === 'notificame' && in_array($channel, ['whatsapp', 'whatsapp_official']));

            if (!$account || (!$isCloudApi && !$isNotificameWhatsApp)) {
                ApiResponse::success(['is_cloud' => false, 'within_window' => true, 'templates' => []]);
            }

            $viaAccountId = !empty($conversation['is_merged']) ? $integrationAccountId : null;
            $withinWindow = \App\Services\WhatsAppCloudService::isWithin24hWindow($conversationId, $viaAccountId);

            $templates = [];
            if (!$withinWindow) {
                if ($isNotificameWhatsApp) {
                    try {
                        $raw = \App\Services\NotificameService::listTemplates($integrationAccountId);
                        foreach ($raw as $tpl) {
                            $body = '';
                            foreach (($tpl['components'] ?? []) as $c) {
                                if (strtoupper($c['type'] ?? '') === 'BODY') {
                                    $body = $c['text'] ?? '';
                                    break;
                                }
                            }
                            $templates[] = [
                                'id' => $tpl['id'] ?? $tpl['name'] ?? '',
                                'name' => $tpl['name'] ?? '',
                                'display_name' => $tpl['name'] ?? '',
                                'language' => $tpl['language'] ?? 'pt_BR',
                                'body_text' => $body ?: ($tpl['body'] ?? $tpl['text'] ?? ''),
                                'source' => 'notificame',
                            ];
                        }
                    } catch (\Exception $e) {
                        \App\Helpers\Logger::error("API cloudWindow: erro Notificame: " . $e->getMessage());
                    }
                } else {
                    $phone = \App\Models\WhatsAppPhone::findByIntegrationAccount($integrationAccountId);
                    $wabaId = $phone['waba_id'] ?? ($account['account_id'] ?? null);
                    if ($wabaId) {
                        foreach (\App\Models\WhatsAppTemplate::getApproved($wabaId) as $tpl) {
                            $templates[] = [
                                'id' => $tpl['id'],
                                'name' => $tpl['name'],
                                'display_name' => $tpl['display_name'] ?: $tpl['name'],
                                'language' => $tpl['language'],
                                'body_text' => $tpl['body_text'],
                                'variables_count' => \App\Models\WhatsAppTemplate::countVariables($tpl),
                                'source' => 'meta',
                            ];
                        }
                    }
                }
            }

            ApiResponse::success([
                'is_cloud' => true,
                'within_window' => $withinWindow,
                'templates' => $templates,
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao verificar janela de 24h', $e);
        }
    }

    /**
     * Enviar template aprovado (fora da janela de 24h)
     * POST /api/v1/conversations/:id/send-cloud-template
     * Body: { template_id?: int (meta), template_name?: string (notificame), parameters?: string[], language?: string, body_text?: string }
     */
    public function sendCloudTemplate(string $id): void
    {
        ApiAuthMiddleware::requirePermission('messages.send');

        $conversationId = (int)$id;
        $conversation = $this->assertCanView($conversationId);
        $userId = ApiAuthMiddleware::userId();

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            $templateId = $input['template_id'] ?? null;
            $templateName = $input['template_name'] ?? null;
            $parameters = is_array($input['parameters'] ?? null) ? $input['parameters'] : [];

            $integrationAccountId = $conversation['integration_account_id'] ?? null;
            if (!empty($conversation['is_merged']) && !empty($conversation['last_customer_account_id'])) {
                $integrationAccountId = (int)$conversation['last_customer_account_id'];
            }

            if (!$integrationAccountId) {
                ApiResponse::badRequest('Conversa sem integração vinculada');
            }

            $account = \App\Models\IntegrationAccount::find($integrationAccountId);
            $provider = $account['provider'] ?? '';
            $isNotificame = ($provider === 'notificame');
            $isMetaCloud = in_array($provider, ['meta_cloud', 'meta_coex']);

            if (!$account || (!$isNotificame && !$isMetaCloud)) {
                ApiResponse::badRequest('Integração não suporta envio de templates');
            }

            $contact = \App\Models\Contact::find($conversation['contact_id']);
            if (!$contact || empty($contact['phone'])) {
                ApiResponse::badRequest('Contato sem número de telefone');
            }

            // Auto-atribuição
            $assignedTo = $conversation['agent_id'] ?? null;
            if (($assignedTo === null || $assignedTo === '' || (int)$assignedTo === 0) && $userId) {
                try {
                    ConversationService::assignToAgent($conversationId, $userId, true);
                } catch (\Exception $e) {
                    // Não bloquear envio
                }
            }

            $to = preg_replace('/[^0-9]/', '', $contact['phone']);

            if ($isNotificame) {
                if (empty($templateName)) {
                    ApiResponse::validationError('Dados inválidos', ['template_name' => ['Obrigatório para Notificame']]);
                }

                $bodyText = $input['body_text'] ?? $templateName;
                foreach ($parameters as $i => $value) {
                    $bodyText = str_replace('{{' . ($i + 1) . '}}', $value, $bodyText);
                }

                $messageId = Message::createMessage([
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'sender_type' => 'agent',
                    'content' => $bodyText,
                    'message_type' => 'text',
                    'status' => 'pending',
                ]);

                $language = $input['language'] ?? 'pt_BR';
                $result = \App\Services\NotificameService::sendTemplate(
                    $integrationAccountId, $to, $templateName, $parameters, $language
                );
            } else {
                $templateId = (int)($templateId ?? 0);
                if (!$templateId) {
                    ApiResponse::validationError('Dados inválidos', ['template_id' => ['Template não informado']]);
                }

                $template = \App\Models\WhatsAppTemplate::find($templateId);
                if (!$template || $template['status'] !== 'APPROVED') {
                    ApiResponse::notFound('Template não encontrado ou não aprovado');
                }

                $bodyText = $template['body_text'] ?? '';
                foreach ($parameters as $i => $value) {
                    $bodyText = str_replace('{{' . ($i + 1) . '}}', $value, $bodyText);
                }

                $messageId = Message::createMessage([
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'sender_type' => 'agent',
                    'content' => $bodyText,
                    'message_type' => 'text',
                    'status' => 'pending',
                ]);

                $service = new \App\Services\WhatsAppCloudApiService();
                $result = $service->sendMessage($integrationAccountId, $to, '', [
                    'template_name' => $template['name'],
                    'template_language' => $template['language'],
                    'template_parameters' => $parameters,
                ]);

                if ($result && ($result['success'] ?? false)) {
                    \App\Models\WhatsAppTemplate::incrementSent($templateId);
                } else {
                    \App\Models\WhatsAppTemplate::incrementFailed($templateId);
                }
            }

            $success = $result && ($result['success'] ?? false);

            Message::update($messageId, $success
                ? ['external_id' => $result['message_id'] ?? null, 'status' => 'sent']
                : ['status' => 'failed', 'error_message' => $result['error'] ?? 'Erro ao enviar template']);

            Conversation::update($conversationId, [
                'last_message_at' => date('Y-m-d H:i:s'),
                'status' => 'open',
            ]);

            if (!$success) {
                ApiResponse::error(
                    'Falha ao enviar template: ' . ($result['error'] ?? 'Erro desconhecido'),
                    502,
                    'TEMPLATE_SEND_FAILED',
                    ['message_id' => $messageId]
                );
            }

            ApiResponse::created([
                'message_id' => $messageId,
                'external_id' => $result['message_id'] ?? null,
                'status' => 'sent',
            ], 'Template enviado com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao enviar template', $e);
        }
    }

    /**
     * Checar se já existe contato/conversa aberta para um telefone
     * POST /api/v1/conversations/check-existing
     * Body: { phone?: string, contact_id?: int, channel?: string }
     */
    public function checkExisting(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            $channel = $input['channel'] ?? 'whatsapp';
            $contact = null;

            if (!empty($input['contact_id'])) {
                $contact = \App\Models\Contact::find((int)$input['contact_id']);
            } elseif (!empty($input['phone'])) {
                $phone = preg_replace('/[^0-9]/', '', (string)$input['phone']);
                if (strlen($phone) >= 8) {
                    $contact = \App\Models\Contact::findByPhoneNormalized($phone);
                }
            } else {
                ApiResponse::validationError('Dados inválidos', ['phone' => ['Informe phone ou contact_id']]);
            }

            if (!$contact) {
                ApiResponse::success(['contact' => null, 'open_conversation' => null]);
            }

            $openConversation = Conversation::findAnyOpenByContact($contact['id'], $channel);

            ApiResponse::success([
                'contact' => [
                    'id' => $contact['id'],
                    'name' => $contact['name'],
                    'phone' => $contact['phone'] ?? null,
                    'avatar' => $contact['avatar'] ?? null,
                ],
                'open_conversation' => $openConversation ? [
                    'id' => $openConversation['id'],
                    'status' => $openConversation['status'],
                    'agent_id' => $openConversation['agent_id'] ?? null,
                ] : null,
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao verificar conversa existente', $e);
        }
    }

    /**
     * Garante que a conversa existe e o usuário pode vê-la. Retorna a conversa.
     */
    private function assertCanView(int $conversationId): array
    {
        $conversation = ConversationService::getConversation($conversationId);
        if (!$conversation) {
            ApiResponse::notFound('Conversa não encontrada');
        }

        if (!ConversationService::canView($conversationId, ApiAuthMiddleware::userId())) {
            ApiResponse::forbidden('Você não tem permissão para acessar esta conversa');
        }

        return $conversation;
    }
}
