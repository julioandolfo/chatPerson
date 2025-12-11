<?php
/**
 * Rotas da Aplicação Web
 */

use App\Helpers\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ConversationController;
use App\Controllers\ContactController;
use App\Controllers\FunnelController;
use App\Controllers\AutomationController;
use App\Controllers\AgentController;
use App\Controllers\UserController;
use App\Controllers\IntegrationController;
use App\Controllers\SettingsController;
use App\Controllers\RoleController;
use App\Controllers\DepartmentController;
use App\Controllers\WebhookController;
use App\Controllers\TagController;
use App\Controllers\NotificationController;
use App\Controllers\MessageTemplateController;
use App\Controllers\AttachmentController;
use App\Controllers\ActivityController;
use App\Controllers\AIAgentController;
use App\Controllers\AIToolController;
use App\Controllers\SearchController;
use App\Controllers\TestController;
use App\Controllers\AIAssistantController;
use App\Controllers\RealtimeController;

// Rotas públicas
Router::get('/', function() {
    \App\Helpers\Response::redirect('/dashboard');
});

Router::get('/login', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::get('/logout', [AuthController::class, 'logout']);

// Rotas protegidas (requerem autenticação)
Router::get('/dashboard', [DashboardController::class, 'index'], ['Authentication']);
Router::get('/dashboard/chart-data', [DashboardController::class, 'getChartData'], ['Authentication']);
Router::get('/dashboard/export', [DashboardController::class, 'exportReport'], ['Authentication']);
Router::get('/conversations', [ConversationController::class, 'index'], ['Authentication']);
Router::post('/conversations', [ConversationController::class, 'store'], ['Authentication']);
Router::post('/conversations/new', [ConversationController::class, 'newConversation'], ['Authentication']);
Router::get('/conversations/{id}', [ConversationController::class, 'show'], ['Authentication']);
Router::delete('/conversations/{id}', [ConversationController::class, 'destroy'], ['Authentication']);
// Rota alternativa para exibir conversa na lista (usado no layout Chatwoot)
Router::get('/conversations/list/{id}', [ConversationController::class, 'index'], ['Authentication']);
Router::post('/conversations/{id}/assign', [ConversationController::class, 'assign'], ['Authentication']);
Router::post('/conversations/{id}/escalate', [ConversationController::class, 'escalate'], ['Authentication']);
Router::post('/conversations/{id}/close', [ConversationController::class, 'close'], ['Authentication']);
Router::post('/conversations/{id}/reopen', [ConversationController::class, 'reopen'], ['Authentication']);
// Rotas de tags movidas para após as rotas de tags (linhas 192-199)
Router::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage'], ['Authentication']);
Router::get('/conversations/for-forwarding', [ConversationController::class, 'listForForwarding'], ['Authentication']);
Router::post('/conversations/{id}/forward', [ConversationController::class, 'forwardMessage'], ['Authentication']);
Router::post('/conversations/{id}/pin', [ConversationController::class, 'pin'], ['Authentication']);
Router::post('/conversations/{id}/unpin', [ConversationController::class, 'unpin'], ['Authentication']);
Router::get('/conversations/{id}/search-messages', [ConversationController::class, 'searchMessages'], ['Authentication']);
Router::get('/conversations/{id}/messages', [ConversationController::class, 'getMessages'], ['Authentication']);
Router::get('/conversations/{id}/participants', [ConversationController::class, 'getParticipants'], ['Authentication']);
Router::post('/conversations/{id}/participants', [ConversationController::class, 'addParticipant'], ['Authentication']);
Router::delete('/conversations/{id}/participants/{userId}', [ConversationController::class, 'removeParticipant'], ['Authentication']);
// Rotas de ações de conversa
Router::post('/conversations/{id}/mark-read', [ConversationController::class, 'markRead'], ['Authentication']);
Router::post('/conversations/{id}/mark-unread', [ConversationController::class, 'markUnread'], ['Authentication']);
// Rotas de mensagens agendadas
Router::post('/conversations/{id}/schedule-message', [ConversationController::class, 'scheduleMessage'], ['Authentication']);
Router::get('/conversations/{id}/scheduled-messages', [ConversationController::class, 'getScheduledMessages'], ['Authentication']);
Router::delete('/conversations/{id}/scheduled-messages/{messageId}', [ConversationController::class, 'cancelScheduledMessage'], ['Authentication']);
// Rotas de lembretes
Router::post('/conversations/{id}/reminders', [ConversationController::class, 'createReminder'], ['Authentication']);
Router::get('/conversations/{id}/reminders', [ConversationController::class, 'getReminders'], ['Authentication']);
Router::post('/reminders/{reminderId}/resolve', [ConversationController::class, 'resolveReminder'], ['Authentication']);

// Rotas de Busca Global
Router::get('/search/global', [SearchController::class, 'global'], ['Authentication']);
Router::post('/search/save', [SearchController::class, 'save'], ['Authentication']);
Router::get('/search/saved', [SearchController::class, 'saved'], ['Authentication']);
Router::delete('/search/saved/{id}', [SearchController::class, 'deleteSaved'], ['Authentication']);

// Rotas de Teste (Demo)
Router::get('/test', [TestController::class, 'index'], ['Authentication']);
Router::get('/test/conversation/{id}', [TestController::class, 'loadConversation'], ['Authentication']);
Router::post('/test/conversation/{id}/message', [TestController::class, 'sendMessage'], ['Authentication']);

// Rotas de Contatos
Router::get('/contacts', [ContactController::class, 'index'], ['Authentication']);
Router::post('/contacts', [ContactController::class, 'store'], ['Authentication']);
Router::get('/contacts/{id}', [ContactController::class, 'show'], ['Authentication']);
Router::post('/contacts/{id}', [ContactController::class, 'update'], ['Authentication']);
Router::post('/contacts/{id}/avatar', [ContactController::class, 'uploadAvatar'], ['Authentication']);
Router::delete('/contacts/{id}', [ContactController::class, 'destroy'], ['Authentication']);

// Rotas de Funis
Router::get('/funnels', [FunnelController::class, 'index'], ['Authentication']);
Router::get('/funnels/kanban', [FunnelController::class, 'kanban'], ['Authentication']);
Router::get('/funnels/{id}/kanban', [FunnelController::class, 'kanban'], ['Authentication']);
Router::get('/funnels/{id}/stages', [FunnelController::class, 'getStages'], ['Authentication']);
Router::get('/funnels/{id}/stages/{stageId}/json', [FunnelController::class, 'getStageJson'], ['Authentication']);
Router::post('/funnels', [FunnelController::class, 'store'], ['Authentication']);
Router::post('/funnels/{id}/stages', [FunnelController::class, 'createStage'], ['Authentication']);
Router::post('/funnels/{id}/stages/{stageId}', [FunnelController::class, 'updateStage'], ['Authentication']);
Router::delete('/funnels/{id}/stages/{stageId}', [FunnelController::class, 'deleteStage'], ['Authentication']);
Router::post('/funnels/{id}/conversations/move', [FunnelController::class, 'moveConversation'], ['Authentication']);
Router::post('/funnels/{id}/stages/reorder', [FunnelController::class, 'reorderStages'], ['Authentication']);
Router::get('/funnels/{id}/metrics', [FunnelController::class, 'getFunnelMetrics'], ['Authentication']);
Router::get('/funnels/{id}/stages/metrics', [FunnelController::class, 'getStageMetrics'], ['Authentication']);

// Rotas de Automações
Router::get('/automations', [AutomationController::class, 'index'], ['Authentication']);
Router::get('/automations/{id}', [AutomationController::class, 'show'], ['Authentication']);
Router::post('/automations', [AutomationController::class, 'store'], ['Authentication']);
Router::post('/automations/{id}', [AutomationController::class, 'update'], ['Authentication']);
Router::post('/automations/{id}/nodes', [AutomationController::class, 'createNode'], ['Authentication']);
Router::post('/automations/{id}/nodes/{nodeId}', [AutomationController::class, 'updateNode'], ['Authentication']);
Router::delete('/automations/{id}/nodes/{nodeId}', [AutomationController::class, 'deleteNode'], ['Authentication']);
Router::post('/automations/{id}/layout', [AutomationController::class, 'saveLayout'], ['Authentication']);
Router::get('/automations/{id}/logs', [AutomationController::class, 'getLogs'], ['Authentication']);
Router::get('/automations/variables', [AutomationController::class, 'getVariables'], ['Authentication']);
Router::get('/automations/{id}/test', [AutomationController::class, 'test'], ['Authentication']);
Router::post('/automations/preview-variables', [AutomationController::class, 'previewVariables'], ['Authentication']);

// Rotas de Agentes
Router::get('/agents', [AgentController::class, 'index'], ['Authentication']);
Router::get('/agents/{id}', [AgentController::class, 'show'], ['Authentication']);
Router::post('/agents/{id}/availability', [AgentController::class, 'updateAvailability'], ['Authentication']);

// Rotas de Usuários
Router::get('/users', [UserController::class, 'index'], ['Authentication']);
Router::get('/users/{id}', [UserController::class, 'show'], ['Authentication']);
Router::post('/users', [UserController::class, 'store'], ['Authentication']);
Router::post('/users/{id}', [UserController::class, 'update'], ['Authentication']);
Router::delete('/users/{id}', [UserController::class, 'destroy'], ['Authentication']);
Router::post('/users/{id}/roles', [UserController::class, 'assignRole'], ['Authentication']);
Router::post('/users/{id}/roles/remove', [UserController::class, 'removeRole'], ['Authentication']);
Router::post('/users/{id}/departments', [UserController::class, 'assignDepartment'], ['Authentication']);
Router::post('/users/{id}/departments/remove', [UserController::class, 'removeDepartment'], ['Authentication']);
Router::post('/users/{id}/funnel-permissions', [UserController::class, 'assignFunnelPermission'], ['Authentication']);
Router::post('/users/{id}/funnel-permissions/remove', [UserController::class, 'removeFunnelPermission'], ['Authentication']);
Router::get('/users/{id}/performance', [UserController::class, 'getPerformanceStats'], ['Authentication']);

// Rotas de Integrações
Router::get('/integrations', [IntegrationController::class, 'index'], ['Authentication']);
Router::get('/integrations/whatsapp', [IntegrationController::class, 'whatsapp'], ['Authentication']);
Router::post('/integrations/whatsapp', [IntegrationController::class, 'createWhatsAppAccount'], ['Authentication']);
Router::post('/integrations/whatsapp/{id}', [IntegrationController::class, 'updateWhatsAppAccount'], ['Authentication']);
Router::delete('/integrations/whatsapp/{id}', [IntegrationController::class, 'deleteWhatsAppAccount'], ['Authentication']);
Router::get('/integrations/whatsapp/{id}/qrcode', [IntegrationController::class, 'getQRCode'], ['Authentication']);
Router::get('/integrations/whatsapp/{id}/status', [IntegrationController::class, 'getConnectionStatus'], ['Authentication']);
Router::post('/integrations/whatsapp/{id}/disconnect', [IntegrationController::class, 'disconnect'], ['Authentication']);
Router::post('/integrations/whatsapp/{id}/test', [IntegrationController::class, 'sendTestMessage'], ['Authentication']);
Router::post('/integrations/whatsapp/{id}/webhook', [IntegrationController::class, 'configureWebhook'], ['Authentication']);

// Webhook público para WhatsApp (sem autenticação)
Router::post('/whatsapp-webhook', [WebhookController::class, 'whatsapp']);

// Rotas de Webhooks (públicas para receber eventos)
Router::post('/webhooks/{webhookId}', [\App\Controllers\WebhookController::class, 'receive']);
Router::get('/webhooks', [\App\Controllers\WebhookController::class, 'index'], ['Authentication']);

// Rotas de Configurações
Router::get('/settings', [SettingsController::class, 'index'], ['Authentication']);
Router::post('/settings/general', [SettingsController::class, 'saveGeneral'], ['Authentication']);
Router::post('/settings/upload-logo', [SettingsController::class, 'uploadLogo'], ['Authentication']);
Router::post('/settings/remove-logo', [SettingsController::class, 'removeLogo'], ['Authentication']);
Router::post('/settings/email', [SettingsController::class, 'saveEmail'], ['Authentication']);
Router::post('/settings/whatsapp', [SettingsController::class, 'saveWhatsApp'], ['Authentication']);
Router::post('/settings/security', [SettingsController::class, 'saveSecurity'], ['Authentication']);
Router::post('/settings/websocket', [SettingsController::class, 'saveWebSocket'], ['Authentication']);
Router::post('/settings/conversations', [SettingsController::class, 'saveConversations'], ['Authentication']);

// Rotas de Agentes de IA
Router::get('/ai-agents', [AIAgentController::class, 'index'], ['Authentication']);
Router::get('/ai-agents/{id}', [AIAgentController::class, 'show'], ['Authentication']);
Router::post('/ai-agents', [AIAgentController::class, 'store'], ['Authentication']);
Router::post('/ai-agents/{id}', [AIAgentController::class, 'update'], ['Authentication']);
Router::delete('/ai-agents/{id}', [AIAgentController::class, 'destroy'], ['Authentication']);
Router::post('/ai-agents/{id}/tools', [AIAgentController::class, 'addTool'], ['Authentication']);
Router::delete('/ai-agents/{id}/tools/{toolId}', [AIAgentController::class, 'removeTool'], ['Authentication']);
Router::get('/ai-agents/{id}/stats', [AIAgentController::class, 'getStats'], ['Authentication']);

// Rotas do Assistente IA (Chat)
Router::get('/ai-assistant/features', [AIAssistantController::class, 'getFeatures'], ['Authentication']);
Router::get('/ai-assistant/check-availability', [AIAssistantController::class, 'checkAvailability'], ['Authentication']);
Router::post('/ai-assistant/generate-response', [AIAssistantController::class, 'generateResponse'], ['Authentication']);
Router::post('/ai-assistant/execute-feature', [AIAssistantController::class, 'executeFeature'], ['Authentication']);
Router::get('/ai-assistant/selected-agent', [AIAssistantController::class, 'getSelectedAgent'], ['Authentication']);
Router::post('/ai-assistant/user-setting', [AIAssistantController::class, 'updateUserSetting'], ['Authentication']);
Router::get('/ai-assistant/response-history', [AIAssistantController::class, 'getResponseHistory'], ['Authentication']);
Router::get('/ai-assistant/favorites', [AIAssistantController::class, 'getFavorites'], ['Authentication']);
Router::post('/ai-assistant/toggle-favorite', [AIAssistantController::class, 'toggleFavorite'], ['Authentication']);
Router::post('/ai-assistant/mark-as-used', [AIAssistantController::class, 'markAsUsed'], ['Authentication']);

// Rotas do Assistente IA (Admin/Configurações)
Router::post('/ai-assistant/features/{featureKey}', [AIAssistantController::class, 'updateFeature'], ['Authentication']);
Router::post('/ai-assistant/features/{featureKey}/settings', [AIAssistantController::class, 'updateFeatureSettings'], ['Authentication']);
Router::get('/ai-assistant/rules', [AIAssistantController::class, 'getRules'], ['Authentication']);
Router::post('/ai-assistant/rules', [AIAssistantController::class, 'createRule'], ['Authentication']);
Router::delete('/ai-assistant/rules/{id}', [AIAssistantController::class, 'deleteRule'], ['Authentication']);
Router::get('/ai-assistant/logs', [AIAssistantController::class, 'getLogs'], ['Authentication']);
Router::get('/ai-assistant/stats', [AIAssistantController::class, 'getStats'], ['Authentication']);

// Rotas de Tools de IA
Router::get('/ai-tools', [AIToolController::class, 'index'], ['Authentication']);
Router::get('/ai-tools/{id}', [AIToolController::class, 'show'], ['Authentication']);
Router::post('/ai-tools', [AIToolController::class, 'store'], ['Authentication']);
Router::post('/ai-tools/{id}', [AIToolController::class, 'update'], ['Authentication']);
Router::delete('/ai-tools/{id}', [AIToolController::class, 'destroy'], ['Authentication']);

// Rotas de Roles e Permissões
Router::get('/roles', [RoleController::class, 'index'], ['Authentication']);
Router::get('/roles/{id}', [RoleController::class, 'show'], ['Authentication']);
Router::post('/roles', [RoleController::class, 'store'], ['Authentication']);
Router::post('/roles/{id}', [RoleController::class, 'update'], ['Authentication']);
Router::post('/roles/{id}/permissions', [RoleController::class, 'assignPermission'], ['Authentication']);
Router::post('/roles/{id}/permissions/remove', [RoleController::class, 'removePermission'], ['Authentication']);

// Rotas de Departments
Router::get('/departments', [DepartmentController::class, 'index'], ['Authentication']);
Router::get('/departments/{id}', [DepartmentController::class, 'show'], ['Authentication']);
Router::get('/departments/{id}/json', [DepartmentController::class, 'getJson'], ['Authentication']);
Router::post('/departments', [DepartmentController::class, 'store'], ['Authentication']);
Router::post('/departments/{id}', [DepartmentController::class, 'update'], ['Authentication']);
Router::delete('/departments/{id}', [DepartmentController::class, 'destroy'], ['Authentication']);
Router::post('/departments/{id}/agents', [DepartmentController::class, 'addAgent'], ['Authentication']);
Router::post('/departments/{id}/agents/remove', [DepartmentController::class, 'removeAgent'], ['Authentication']);

// Rotas de Tags
Router::get('/tags', [TagController::class, 'index'], ['Authentication']);
Router::post('/tags', [TagController::class, 'store'], ['Authentication']);
Router::post('/tags/{id}', [TagController::class, 'update'], ['Authentication']);
Router::delete('/tags/{id}', [TagController::class, 'destroy'], ['Authentication']);
Router::get('/tags/all', [TagController::class, 'getAll'], ['Authentication']);
Router::post('/conversations/{id}/tags', [TagController::class, 'addToConversation'], ['Authentication']);
Router::post('/conversations/{id}/tags/remove', [TagController::class, 'removeFromConversation'], ['Authentication']);
Router::get('/conversations/{id}/tags', [TagController::class, 'getByConversation'], ['Authentication']);

// Rotas de Notificações
Router::get('/notifications', [NotificationController::class, 'index'], ['Authentication']);
Router::get('/notifications/unread', [NotificationController::class, 'getUnread'], ['Authentication']);
Router::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'], ['Authentication']);
Router::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'], ['Authentication']);
Router::delete('/notifications/{id}', [NotificationController::class, 'destroy'], ['Authentication']);

// Rotas de Templates de Mensagens
Router::get('/message-templates', [MessageTemplateController::class, 'index'], ['Authentication']);
Router::post('/message-templates', [MessageTemplateController::class, 'store'], ['Authentication']);
Router::post('/message-templates/{id}', [MessageTemplateController::class, 'update'], ['Authentication']);
Router::delete('/message-templates/{id}', [MessageTemplateController::class, 'destroy'], ['Authentication']);
Router::get('/message-templates/available', [MessageTemplateController::class, 'getAvailable'], ['Authentication']);
Router::get('/message-templates/personal', [MessageTemplateController::class, 'getPersonal'], ['Authentication']);
Router::get('/message-templates/{id}', [MessageTemplateController::class, 'show'], ['Authentication']);
Router::post('/message-templates/{id}/preview', [MessageTemplateController::class, 'preview'], ['Authentication']);
Router::post('/message-templates/{id}/process', [MessageTemplateController::class, 'process'], ['Authentication']);
Router::get('/message-templates/variables', [MessageTemplateController::class, 'getVariables'], ['Authentication']);

// Rotas de Anexos
Router::get('/attachments/{path}', [AttachmentController::class, 'view'], ['Authentication']);
Router::get('/attachments/{path}/download', [AttachmentController::class, 'download'], ['Authentication']);
Router::get('/attachments/conversation/{id}', [AttachmentController::class, 'listByConversation'], ['Authentication']);
Router::delete('/attachments/{path}', [AttachmentController::class, 'delete'], ['Authentication']);

// Rotas de Atividades
Router::get('/activities', [ActivityController::class, 'index'], ['Authentication']);
Router::get('/activities/user/{id}', [ActivityController::class, 'getByUser'], ['Authentication']);
Router::get('/activities/{entityType}/{id}', [ActivityController::class, 'getByEntity'], ['Authentication']);

// Rotas de Tempo Real (WebSocket/Polling)
Router::get('/api/realtime/config', [RealtimeController::class, 'getConfig'], ['Authentication']);
Router::post('/api/realtime/poll', [RealtimeController::class, 'poll'], ['Authentication']);

