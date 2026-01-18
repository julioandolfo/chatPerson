<?php
/**
 * Rotas da Aplicação Web
 */

use App\Helpers\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ConversationController;
use App\Controllers\ContactController;
use App\Controllers\ContactAgentController;
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
use App\Controllers\AnalyticsController;
use App\Controllers\NotificationController;
use App\Controllers\MessageTemplateController;
use App\Controllers\AttachmentController;
use App\Controllers\ActivityController;
use App\Controllers\AIAgentController;
use App\Controllers\AIToolController;
use App\Controllers\RAGController;
use App\Controllers\KanbanAgentController;
use App\Controllers\SearchController;
use App\Controllers\TestController;
use App\Controllers\AIAssistantController;
use App\Controllers\RealtimeController;
use App\Controllers\LogController;
use App\Controllers\SoundSettingsController;
use App\Controllers\ProfileController;
use App\Controllers\Api4ComController;
use App\Controllers\Api4ComCallController;
use App\Controllers\WooCommerceController;
use App\Controllers\AgentPerformanceController;
use App\Controllers\RealtimeCoachingController;
use App\Controllers\CoachingDashboardController;
use App\Controllers\TeamController;
use App\Controllers\AgentConversionController;

// Rotas públicas
Router::get('/', function() {
    \App\Helpers\Response::redirect('/dashboard');
});

Router::get('/login', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login']);
Router::get('/logout', [AuthController::class, 'logout']);

// Rotas protegidas (requerem autenticação)

// Rotas de Perfil do Usuário (acessível a todos os usuários autenticados)
Router::get('/profile', [ProfileController::class, 'index'], ['Authentication']);
Router::get('/profile/notifications', [ProfileController::class, 'notifications'], ['Authentication']);
Router::post('/profile/notifications', [ProfileController::class, 'saveNotifications'], ['Authentication']);
Router::post('/profile/sounds/upload', [ProfileController::class, 'uploadSound'], ['Authentication']);
Router::delete('/profile/sounds/{id}', [ProfileController::class, 'deleteSound'], ['Authentication']);
Router::get('/profile/sounds/available', [ProfileController::class, 'getAvailableSounds'], ['Authentication']);
Router::get('/profile/sounds/custom', [ProfileController::class, 'getCustomSounds'], ['Authentication']);

Router::get('/dashboard', [DashboardController::class, 'index'], ['Authentication']);
Router::get('/dashboard/ai', [DashboardController::class, 'aiDashboard'], ['Authentication']);
Router::get('/dashboard/chart-data', [DashboardController::class, 'getChartData'], ['Authentication']);
Router::get('/dashboard/export', [DashboardController::class, 'exportReport'], ['Authentication']);
Router::get('/conversations', [ConversationController::class, 'index'], ['Authentication']);
Router::get('/conversations/metrics/current-agent', [ConversationController::class, 'getCurrentAgentMetrics'], ['Authentication']);
Router::post('/conversations', [ConversationController::class, 'store'], ['Authentication']);
// Rotas específicas DEVEM vir ANTES das rotas com parâmetros dinâmicos
Router::post('/conversations/new', [ConversationController::class, 'newConversation'], ['Authentication']);
Router::get('/conversations/for-forwarding', [ConversationController::class, 'listForForwarding'], ['Authentication']);
// Rotas com parâmetros dinâmicos {id}
// Rotas específicas devem vir antes das rotas dinâmicas
Router::get('/conversations/invites', [ConversationController::class, 'getInvites'], ['Authentication']);
Router::get('/conversations/invites/count', [ConversationController::class, 'countInvites'], ['Authentication']);
Router::get('/conversations/invites/history', [ConversationController::class, 'getInviteHistory'], ['Authentication']);
Router::get('/conversations/requests/pending', [ConversationController::class, 'getPendingRequests'], ['Authentication']);
Router::get('/conversations/invites/counts', [ConversationController::class, 'getInviteCounts'], ['Authentication']);
Router::post('/conversations/{id}/request-participation', [ConversationController::class, 'requestParticipation'], ['Authentication']);
Router::post('/conversations/requests/{requestId}/approve', [ConversationController::class, 'approveRequest'], ['Authentication']);
Router::post('/conversations/requests/{requestId}/reject', [ConversationController::class, 'rejectRequest'], ['Authentication']);

Router::get('/conversations/{id}/details', [FunnelController::class, 'getConversationDetails'], ['Authentication']);
Router::get('/conversations/{id}', [ConversationController::class, 'show'], ['Authentication']);
Router::delete('/conversations/{id}', [ConversationController::class, 'destroy'], ['Authentication']);
// Rota alternativa para exibir conversa na lista (usado no layout Chatwoot)
Router::get('/conversations/list/{id}', [ConversationController::class, 'index'], ['Authentication']);
Router::post('/conversations/{id}/assign', [ConversationController::class, 'assign'], ['Authentication']);
Router::post('/conversations/{id}/update-department', [ConversationController::class, 'updateDepartment'], ['Authentication']);
Router::post('/conversations/{id}/escalate', [ConversationController::class, 'escalate'], ['Authentication']);
Router::post('/conversations/{id}/close', [ConversationController::class, 'close'], ['Authentication']);
Router::post('/conversations/{id}/reopen', [ConversationController::class, 'reopen'], ['Authentication']);
Router::post('/conversations/{id}/spam', [ConversationController::class, 'spam'], ['Authentication']);
Router::post('/conversations/{id}/notes', [ConversationController::class, 'createNote'], ['Authentication']);
Router::get('/conversations/{id}/notes', [ConversationController::class, 'getNotes'], ['Authentication']);
Router::post('/conversations/{id}/move-stage', [ConversationController::class, 'moveStage'], ['Authentication']);
Router::put('/conversations/{id}/notes/{noteId}', [ConversationController::class, 'updateNote'], ['Authentication']);
Router::delete('/conversations/{id}/notes/{noteId}', [ConversationController::class, 'deleteNote'], ['Authentication']);
Router::get('/conversations/{id}/timeline', [ConversationController::class, 'getTimeline'], ['Authentication']);
Router::get('/conversations/{id}/sentiment', [ConversationController::class, 'getSentiment'], ['Authentication']);
Router::get('/conversations/{id}/performance', [ConversationController::class, 'getPerformance'], ['Authentication']);

// Rotas de Coaching em Tempo Real
Router::get('/coaching/pending-hints', [RealtimeCoachingController::class, 'getPendingHints'], ['Authentication']); // Polling
Router::get('/coaching/stats', [RealtimeCoachingController::class, 'getStats'], ['Authentication']); // Estatísticas
Router::post('/coaching/mark-viewed', [RealtimeCoachingController::class, 'markAsViewed'], ['Authentication']); // Marcar como visto
Router::post('/coaching/feedback', [RealtimeCoachingController::class, 'provideFeedback'], ['Authentication']); // Feedback (útil/não)

Router::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage'], ['Authentication']);
Router::post('/conversations/{id}/forward', [ConversationController::class, 'forwardMessage'], ['Authentication']);

// Analytics
Router::get('/analytics', [AnalyticsController::class, 'index'], ['Authentication']);
Router::get('/analytics/sentiment', [AnalyticsController::class, 'sentiment'], ['Authentication']);
Router::get('/analytics/sentiment/data', [AnalyticsController::class, 'getSentimentData'], ['Authentication']);
Router::get('/analytics/conversations/data', [AnalyticsController::class, 'getConversationsData'], ['Authentication']);
Router::get('/analytics/agents/data', [AnalyticsController::class, 'getAgentsData'], ['Authentication']);
Router::get('/analytics/tags/data', [AnalyticsController::class, 'getTagsData'], ['Authentication']);
Router::get('/analytics/funnel/data', [AnalyticsController::class, 'getFunnelData'], ['Authentication']);
Router::get('/analytics/automations/data', [AnalyticsController::class, 'getAutomationsData'], ['Authentication']);
Router::get('/analytics/ai/data', [AnalyticsController::class, 'getAIData'], ['Authentication']);
Router::get('/analytics/comparison', [AnalyticsController::class, 'getTimeComparison'], ['Authentication']);
Router::post('/conversations/{id}/pin', [ConversationController::class, 'pin'], ['Authentication']);
Router::post('/conversations/{id}/unpin', [ConversationController::class, 'unpin'], ['Authentication']);
Router::get('/conversations/{id}/search-messages', [ConversationController::class, 'searchMessages'], ['Authentication']);
Router::get('/conversations/{id}/messages', [ConversationController::class, 'getMessages'], ['Authentication']);
Router::get('/conversations/{id}/participants', [ConversationController::class, 'getParticipants'], ['Authentication']);
Router::post('/conversations/{id}/participants', [ConversationController::class, 'addParticipant'], ['Authentication']);
Router::delete('/conversations/{id}/participants/{userId}', [ConversationController::class, 'removeParticipant'], ['Authentication']);

// Rotas de menções/convites de agentes
Router::post('/conversations/{id}/mention', [ConversationController::class, 'mention'], ['Authentication']);
Router::get('/conversations/{id}/mentions', [ConversationController::class, 'getMentions'], ['Authentication']);
Router::get('/conversations/{id}/available-agents', [ConversationController::class, 'getAvailableAgents'], ['Authentication']);
Router::get('/conversations/invites', [ConversationController::class, 'getInvites'], ['Authentication']);
Router::get('/conversations/invites/count', [ConversationController::class, 'countInvites'], ['Authentication']);
Router::get('/conversations/invites/history', [ConversationController::class, 'getInviteHistory'], ['Authentication']);
Router::post('/conversations/invites/{mentionId}/accept', [ConversationController::class, 'acceptInvite'], ['Authentication']);
Router::post('/conversations/invites/{mentionId}/decline', [ConversationController::class, 'declineInvite'], ['Authentication']);
Router::post('/conversations/invites/{mentionId}/cancel', [ConversationController::class, 'cancelInvite'], ['Authentication']);

// Rotas de solicitação de participação (rotas estáticas ANTES das dinâmicas)
Router::get('/conversations/requests/pending', [ConversationController::class, 'getPendingRequests'], ['Authentication']);
Router::get('/conversations/invites/counts', [ConversationController::class, 'getInviteCounts'], ['Authentication']);
Router::post('/conversations/{id}/request-participation', [ConversationController::class, 'requestParticipation'], ['Authentication']);
Router::post('/conversations/requests/{requestId}/approve', [ConversationController::class, 'approveRequest'], ['Authentication']);
Router::post('/conversations/requests/{requestId}/reject', [ConversationController::class, 'rejectRequest'], ['Authentication']);

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
// Rotas ESPECÍFICAS devem vir ANTES das rotas genéricas para evitar conflitos
// Rotas de Agentes do Contato
Router::get('/contacts/{id}/agents', [ContactAgentController::class, 'index'], ['Authentication']);
Router::post('/contacts/{id}/agents', [ContactAgentController::class, 'store'], ['Authentication']);
Router::post('/contacts/{id}/agents/set-primary', [ContactAgentController::class, 'setPrimary'], ['Authentication']);
Router::delete('/contacts/{id}/agents/{agentId}', [ContactAgentController::class, 'destroy'], ['Authentication']);
// Rotas específicas de contato
Router::post('/contacts/{id}/avatar', [ContactController::class, 'uploadAvatar'], ['Authentication']);
Router::get('/contacts/{id}/history', [ContactController::class, 'getHistoryMetrics'], ['Authentication']);
// Rotas genéricas de contato (devem vir por último)
Router::get('/contacts/{id}', [ContactController::class, 'show'], ['Authentication']);
Router::post('/contacts/{id}', [ContactController::class, 'update'], ['Authentication']);
Router::delete('/contacts/{id}', [ContactController::class, 'destroy'], ['Authentication']);

// Rotas de Funis
Router::get('/funnels', [FunnelController::class, 'index'], ['Authentication']);
Router::get('/funnels/kanban', [FunnelController::class, 'kanban'], ['Authentication']);
Router::get('/funnels/{id}/kanban', [FunnelController::class, 'kanban'], ['Authentication']);
Router::get('/funnels/{id}/stages', [FunnelController::class, 'getStages'], ['Authentication']);
Router::get('/funnels/{id}/stages/json', [FunnelController::class, 'getStagesJson'], ['Authentication']); // Todas as etapas em JSON
Router::get('/funnels/{id}/stages/{stageId}/json', [FunnelController::class, 'getStageJson'], ['Authentication']); // Etapa específica em JSON
Router::post('/funnels', [FunnelController::class, 'store'], ['Authentication']);
Router::post('/funnels/{id}', [FunnelController::class, 'update'], ['Authentication']); // Atualizar funil
Router::delete('/funnels/{id}', [FunnelController::class, 'delete'], ['Authentication']); // Deletar funil
Router::post('/funnels/{id}/stages', [FunnelController::class, 'createStage'], ['Authentication']);
// IMPORTANTE: Rotas específicas devem vir ANTES das rotas genéricas
Router::post('/funnels/{id}/stages/reorder', [FunnelController::class, 'reorderStages'], ['Authentication']); // ✅ Específica (antes)
Router::post('/funnels/stages/{stageId}/reorder', [FunnelController::class, 'reorderStage'], ['Authentication']);
Router::post('/funnels/{id}/conversations/move', [FunnelController::class, 'moveConversation'], ['Authentication']);
Router::post('/funnels/{id}/stages/{stageId}', [FunnelController::class, 'updateStage'], ['Authentication']); // ⚠️ Genérica (depois)
Router::delete('/funnels/{id}/stages/{stageId}', [FunnelController::class, 'deleteStage'], ['Authentication']);
Router::get('/funnels/{id}/metrics', [FunnelController::class, 'getFunnelMetrics'], ['Authentication']);
Router::get('/funnels/{id}/stages/metrics', [FunnelController::class, 'getStageMetrics'], ['Authentication']);

// Rotas de Automações
Router::get('/automations', [AutomationController::class, 'index'], ['Authentication']);
Router::post('/automations', [AutomationController::class, 'store'], ['Authentication']);
// Rotas específicas DEVEM vir ANTES das rotas com parâmetros dinâmicos
Router::get('/automations/variables', [AutomationController::class, 'getVariables'], ['Authentication']);
Router::post('/automations/preview-variables', [AutomationController::class, 'previewVariables'], ['Authentication']);
// Rotas com parâmetros dinâmicos {id}
Router::get('/automations/{id}', [AutomationController::class, 'show'], ['Authentication']);
Router::post('/automations/{id}', [AutomationController::class, 'update'], ['Authentication']);
Router::delete('/automations/{id}', [AutomationController::class, 'delete'], ['Authentication']);
Router::post('/automations/{id}/nodes', [AutomationController::class, 'createNode'], ['Authentication']);
Router::post('/automations/{id}/nodes/{nodeId}', [AutomationController::class, 'updateNode'], ['Authentication']);
Router::delete('/automations/{id}/nodes/{nodeId}', [AutomationController::class, 'deleteNode'], ['Authentication']);
Router::post('/automations/{id}/layout', [AutomationController::class, 'saveLayout'], ['Authentication']);
Router::get('/automations/{id}/logs', [AutomationController::class, 'getLogs'], ['Authentication']);
Router::get('/automations/{id}/test', [AutomationController::class, 'test'], ['Authentication']);

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
Router::post('/users/update-availability', [UserController::class, 'updateAvailability'], ['Authentication']);

// Rotas de Integrações
Router::get('/integrations', [IntegrationController::class, 'index'], ['Authentication']);
Router::get('/integrations/whatsapp', [IntegrationController::class, 'whatsapp'], ['Authentication']);
Router::post('/integrations/whatsapp', [IntegrationController::class, 'createWhatsAppAccount'], ['Authentication']);
Router::post('/integrations/whatsapp/{id}/settings', [IntegrationController::class, 'updateWhatsAppAccountSettings'], ['Authentication']); // Atualizar funil/etapa padrão
Router::post('/integrations/whatsapp/{id}', [IntegrationController::class, 'updateWhatsAppAccount'], ['Authentication']);
Router::delete('/integrations/whatsapp/{id}', [IntegrationController::class, 'deleteWhatsAppAccount'], ['Authentication']);
Router::get('/integrations/whatsapp/{id}/qrcode', [IntegrationController::class, 'getQRCode'], ['Authentication']);
Router::get('/integrations/whatsapp/{id}/status', [IntegrationController::class, 'getConnectionStatus'], ['Authentication']);
Router::post('/integrations/whatsapp/{id}/disconnect', [IntegrationController::class, 'disconnect'], ['Authentication']);
Router::post('/integrations/whatsapp/{id}/test', [IntegrationController::class, 'sendTestMessage'], ['Authentication']);
Router::post('/integrations/whatsapp/{id}/webhook', [IntegrationController::class, 'configureWebhook'], ['Authentication']);

// Rotas de Integrações Notificame
Router::get('/integrations/notificame', [IntegrationController::class, 'notificame'], ['Authentication']);
Router::post('/integrations/notificame/accounts', [IntegrationController::class, 'createNotificameAccount'], ['Authentication', 'Permission:notificame.create']);
Router::put('/integrations/notificame/accounts/{id}', [IntegrationController::class, 'updateNotificameAccount'], ['Authentication', 'Permission:notificame.edit']);
// Suporte a POST para atualização (compatibilidade)
Router::post('/integrations/notificame/accounts/{id}', [IntegrationController::class, 'updateNotificameAccount'], ['Authentication', 'Permission:notificame.edit']);
Router::delete('/integrations/notificame/accounts/{id}', [IntegrationController::class, 'deleteNotificameAccount'], ['Authentication', 'Permission:notificame.delete']);
Router::get('/integrations/notificame/accounts/{id}/status', [IntegrationController::class, 'checkNotificameStatus'], ['Authentication']);
Router::get('/integrations/notificame/accounts/{id}/subaccounts', [IntegrationController::class, 'listNotificameSubaccounts'], ['Authentication']);
Router::post('/integrations/notificame/accounts/{id}/test', [IntegrationController::class, 'sendNotificameTestMessage'], ['Authentication', 'Permission:notificame.send']);
Router::post('/integrations/notificame/accounts/{id}/webhook', [IntegrationController::class, 'configureNotificameWebhook'], ['Authentication', 'Permission:notificame.edit']);
Router::get('/integrations/notificame/accounts/{id}/templates', [IntegrationController::class, 'listNotificameTemplates'], ['Authentication']);
Router::get('/integrations/notificame/logs', [IntegrationController::class, 'notificameLogs'], ['Authentication']);

// Webhook Notificame (executa script diretamente)
Router::post('/webhooks/notificame', function () {
    require __DIR__ . '/../public/notificame-webhook.php';
}, []); // nenhuma middleware

// ==================== ROTAS META (INSTAGRAM + WHATSAPP) ====================
use App\Controllers\MetaOAuthController;
use App\Controllers\MetaWebhookController;
use App\Controllers\MetaIntegrationController;

// OAuth
Router::get('/integrations/meta/oauth/authorize', [MetaOAuthController::class, 'authorize'], ['Authentication', 'Permission:integrations.manage']);
Router::get('/integrations/meta/oauth/callback', [MetaOAuthController::class, 'callback'], ['Authentication', 'Permission:integrations.manage']);
Router::post('/integrations/meta/oauth/disconnect', [MetaOAuthController::class, 'disconnect'], ['Authentication', 'Permission:integrations.manage']);

// Webhooks (sem autenticação - Meta envia)
Router::get('/webhooks/meta', [MetaWebhookController::class, 'verify'], []); // Verificação GET
Router::post('/webhooks/meta', [MetaWebhookController::class, 'receive'], []); // Receber POST

// Gerenciamento
Router::get('/integrations/meta', [MetaIntegrationController::class, 'index'], ['Authentication', 'Permission:integrations.view']);
Router::post('/integrations/meta/config/save', [MetaIntegrationController::class, 'saveConfig'], ['Authentication', 'Permission:integrations.manage']);
Router::post('/integrations/meta/instagram/sync', [MetaIntegrationController::class, 'syncInstagram'], ['Authentication', 'Permission:integrations.manage']);
Router::post('/integrations/meta/whatsapp/sync', [MetaIntegrationController::class, 'syncWhatsApp'], ['Authentication', 'Permission:integrations.manage']);
Router::post('/integrations/meta/whatsapp/add', [MetaIntegrationController::class, 'addWhatsAppPhone'], ['Authentication', 'Permission:integrations.manage']);
Router::post('/integrations/meta/test-message', [MetaIntegrationController::class, 'testMessage'], ['Authentication', 'Permission:integrations.manage']);
Router::get('/integrations/meta/logs', [MetaIntegrationController::class, 'logs'], ['Authentication', 'Permission:integrations.view']);

// Rotas de Integrações Api4Com
Router::get('/integrations/api4com', [Api4ComController::class, 'index'], ['Authentication']);
Router::post('/integrations/api4com', [Api4ComController::class, 'create'], ['Authentication']);
Router::post('/integrations/api4com/{id}', [Api4ComController::class, 'update'], ['Authentication']);
Router::delete('/integrations/api4com/{id}', [Api4ComController::class, 'delete'], ['Authentication']);
Router::post('/integrations/api4com/{id}/sync-extensions', [Api4ComController::class, 'syncExtensions'], ['Authentication']);
Router::get('/integrations/api4com/{id}/extensions', [Api4ComController::class, 'extensions'], ['Authentication']);
Router::post('/integrations/api4com/{accountId}/extensions/{extensionId}/assign', [Api4ComController::class, 'assignExtension'], ['Authentication']);
Router::post('/integrations/api4com/{accountId}/extensions', [Api4ComController::class, 'createExtension'], ['Authentication']);
Router::delete('/integrations/api4com/{accountId}/extensions/{extensionId}', [Api4ComController::class, 'deleteExtension'], ['Authentication']);
Router::get('/integrations/api4com/{id}/show', [Api4ComController::class, 'show'], ['Authentication']);
Router::post('/integrations/api4com/{id}/test', [Api4ComController::class, 'testConnection'], ['Authentication']);

// Rotas de Integrações WooCommerce
Router::get('/integrations/woocommerce', [WooCommerceController::class, 'index'], ['Authentication']);
Router::post('/integrations/woocommerce', [WooCommerceController::class, 'store'], ['Authentication']);
Router::post('/integrations/woocommerce/{id}', [WooCommerceController::class, 'update'], ['Authentication']);
Router::delete('/integrations/woocommerce/{id}', [WooCommerceController::class, 'delete'], ['Authentication']);
Router::post('/integrations/woocommerce/{id}/test', [WooCommerceController::class, 'testConnection'], ['Authentication']);
Router::get('/integrations/woocommerce/contacts/{contactId}/orders', [WooCommerceController::class, 'getContactOrders'], ['Authentication']);

// Rotas de Chamadas Api4Com
Router::get('/api4com-calls', [Api4ComCallController::class, 'index'], ['Authentication']);
Router::post('/api4com-calls', [Api4ComCallController::class, 'create'], ['Authentication']);
Router::get('/api4com-calls/{id}', [Api4ComCallController::class, 'show'], ['Authentication']);
Router::post('/api4com-calls/{id}/end', [Api4ComCallController::class, 'end'], ['Authentication']);
Router::get('/api4com-calls/conversation/{conversationId}', [Api4ComCallController::class, 'getByConversation'], ['Authentication']);
Router::get('/api4com-calls/statistics', [Api4ComCallController::class, 'statistics'], ['Authentication']);
// Webhook público para Api4Com (sem autenticação)
Router::post('/api4com-calls/webhook', [Api4ComCallController::class, 'webhook']);

// Rotas de Performance de Vendedores
Router::get('/agent-performance', [AgentPerformanceController::class, 'index'], ['Authentication', 'Permission:agent_performance.view.all']);
Router::get('/agent-performance/agent', [AgentPerformanceController::class, 'agent'], ['Authentication']);
Router::get('/agent-performance/ranking', [AgentPerformanceController::class, 'ranking'], ['Authentication', 'Permission:agent_performance.view.all']);
Router::get('/agent-performance/conversation', [AgentPerformanceController::class, 'conversation'], ['Authentication', 'Permission:agent_performance.view.own']);
Router::post('/agent-performance/analyze', [AgentPerformanceController::class, 'analyze'], ['Authentication', 'Permission:agent_performance.analyze']);
Router::get('/agent-performance/best-practices', [AgentPerformanceController::class, 'bestPractices'], ['Authentication', 'Permission:agent_performance.best_practices']);
Router::get('/agent-performance/practice', [AgentPerformanceController::class, 'viewPractice'], ['Authentication', 'Permission:agent_performance.best_practices']);
Router::post('/agent-performance/practice/vote', [AgentPerformanceController::class, 'voteHelpful'], ['Authentication', 'Permission:agent_performance.best_practices']);
Router::get('/agent-performance/goals', [AgentPerformanceController::class, 'goals'], ['Authentication', 'Permission:agent_performance.goals.view']);
Router::post('/agent-performance/goals', [AgentPerformanceController::class, 'createGoal'], ['Authentication', 'Permission:agent_performance.goals.manage']);
Router::get('/agent-performance/compare', [AgentPerformanceController::class, 'compare'], ['Authentication', 'Permission:agent_performance.view.all']);
Router::get('/agent-performance/chart-data', [AgentPerformanceController::class, 'chartData'], ['Authentication', 'Permission:agent_performance.view.all']);

// Rotas de Coaching em Tempo Real (IA)
Router::get('/api/coaching/settings', [RealtimeCoachingController::class, 'getSettings'], ['Authentication']); // ✅ Configurações do coaching
Router::get('/api/coaching/hints/conversation/{conversationId}', [RealtimeCoachingController::class, 'getHintsByConversation'], ['Authentication']);
Router::get('/api/coaching/hints/pending', [RealtimeCoachingController::class, 'getPendingHints'], ['Authentication']);
Router::post('/api/coaching/hints/{hintId}/view', [RealtimeCoachingController::class, 'markAsViewed'], ['Authentication']);
Router::post('/api/coaching/hints/{hintId}/feedback', [RealtimeCoachingController::class, 'sendFeedback'], ['Authentication']);
Router::post('/api/coaching/hints/{hintId}/use-suggestion', [RealtimeCoachingController::class, 'useSuggestion'], ['Authentication']);

// Rotas de Dashboard de Coaching
Router::get('/coaching/dashboard', [CoachingDashboardController::class, 'index'], ['Authentication', 'Permission:coaching.view']);
Router::get('/coaching/agent/{agentId}', [CoachingDashboardController::class, 'agentPerformance'], ['Authentication', 'Permission:coaching.view']);
Router::get('/coaching/top-conversations', [CoachingDashboardController::class, 'topConversations'], ['Authentication', 'Permission:coaching.view']);
Router::get('/api/coaching/dashboard/data', [CoachingDashboardController::class, 'getDashboardData'], ['Authentication', 'Permission:coaching.view']);
Router::get('/api/coaching/dashboard/history', [CoachingDashboardController::class, 'getPerformanceHistory'], ['Authentication', 'Permission:coaching.view']);
Router::get('/coaching/export/csv', [CoachingDashboardController::class, 'exportCSV'], ['Authentication', 'Permission:coaching.view']);

// Rota para iniciar chamada a partir de conversa
Router::post('/conversations/{id}/api4com-call', [ConversationController::class, 'startApi4ComCall'], ['Authentication']);

// Webhook público para WhatsApp (sem autenticação)
Router::post('/whatsapp-webhook', [WebhookController::class, 'whatsapp']);

// Webhook público para WooCommerce (sem autenticação)
Router::post('/webhooks/woocommerce', [WebhookController::class, 'woocommerce']);

// Rotas de Webhooks (públicas para receber eventos)
Router::post('/webhooks/{webhookId}', [\App\Controllers\WebhookController::class, 'receive']);
Router::get('/webhooks', [\App\Controllers\WebhookController::class, 'index'], ['Authentication']);

// Rotas de Configurações
Router::get('/settings', [SettingsController::class, 'index'], ['Authentication']);
Router::post('/settings/general', [SettingsController::class, 'saveGeneral'], ['Authentication']);
Router::post('/settings/upload-logo', [SettingsController::class, 'uploadLogo'], ['Authentication']);
Router::post('/settings/remove-logo', [SettingsController::class, 'removeLogo'], ['Authentication']);
Router::post('/settings/upload-favicon', [SettingsController::class, 'uploadFavicon'], ['Authentication']);
Router::post('/settings/remove-favicon', [SettingsController::class, 'removeFavicon'], ['Authentication']);
Router::post('/settings/email', [SettingsController::class, 'saveEmail'], ['Authentication']);
Router::post('/settings/whatsapp', [SettingsController::class, 'saveWhatsApp'], ['Authentication']);
Router::post('/settings/security', [SettingsController::class, 'saveSecurity'], ['Authentication']);
Router::post('/settings/websocket', [SettingsController::class, 'saveWebSocket'], ['Authentication']);
Router::post('/settings/conversations', [SettingsController::class, 'saveConversations'], ['Authentication']);
Router::post('/settings/availability', [SettingsController::class, 'saveAvailability'], ['Authentication']);
Router::post('/settings/ai', [SettingsController::class, 'saveAI'], ['Authentication']);
Router::post('/settings/postgres', [SettingsController::class, 'savePostgreSQL'], ['Authentication']);
Router::post('/settings/postgres/test', [SettingsController::class, 'testPostgreSQL'], ['Authentication']);
Router::get('/api/settings/sla', [SettingsController::class, 'getSLAConfig'], ['Authentication']); // API para obter config de SLA

// Rotas de API Tokens (Configurações)
Router::get('/settings/api-tokens', [\App\Controllers\ApiTokenController::class, 'index'], ['Authentication']);
Router::post('/settings/api-tokens', [\App\Controllers\ApiTokenController::class, 'store'], ['Authentication']);
Router::post('/settings/api-tokens/{id}/revoke', [\App\Controllers\ApiTokenController::class, 'revoke'], ['Authentication']);
Router::get('/settings/api-tokens/logs', [\App\Controllers\ApiTokenController::class, 'logs'], ['Authentication']);
Router::get('/settings/api-tokens/docs', function() {
    \App\Helpers\Permission::abortIfCannot('settings.manage');
    \App\Helpers\Response::view('settings/api-tokens/documentation', []);
}, ['Authentication']);
Router::get('/settings/api-tokens/stats', [\App\Controllers\ApiTokenController::class, 'stats'], ['Authentication']);

// Botões de Ações
Router::get('/settings/action-buttons', [\App\Controllers\ConversationActionButtonController::class, 'index'], ['Authentication']);
Router::post('/settings/action-buttons', [\App\Controllers\ConversationActionButtonController::class, 'store'], ['Authentication']);
Router::post('/settings/action-buttons/{id}', [\App\Controllers\ConversationActionButtonController::class, 'update'], ['Authentication']);
Router::delete('/settings/action-buttons/{id}', [\App\Controllers\ConversationActionButtonController::class, 'delete'], ['Authentication']);
Router::get('/conversations/{id}/actions', [\App\Controllers\ConversationActionButtonController::class, 'listForConversation'], ['Authentication']);
Router::post('/conversations/{id}/actions/{buttonId}/run', [\App\Controllers\ConversationActionButtonController::class, 'run'], ['Authentication']);

// Rotas de Configurações de Som
Router::get('/settings/sounds', [SoundSettingsController::class, 'getUserSettings'], ['Authentication']);
Router::post('/settings/sounds', [SoundSettingsController::class, 'updateUserSettings'], ['Authentication']);
Router::get('/settings/sounds/system', [SoundSettingsController::class, 'getSystemSettings'], ['Authentication']);
Router::post('/settings/sounds/system', [SoundSettingsController::class, 'updateSystemSettings'], ['Authentication']);
Router::get('/settings/sounds/available', [SoundSettingsController::class, 'getAvailableSounds'], ['Authentication']);
Router::post('/settings/sounds/upload', [SoundSettingsController::class, 'uploadSound'], ['Authentication']);
Router::delete('/settings/sounds/{id}', [SoundSettingsController::class, 'deleteSound'], ['Authentication']);
Router::post('/settings/sounds/test', [SoundSettingsController::class, 'testSound'], ['Authentication']);
Router::get('/settings/sounds/event/{event}', [SoundSettingsController::class, 'getSoundForEvent'], ['Authentication']);
Router::get('/api/elevenlabs/voices', [SettingsController::class, 'getElevenLabsVoices'], ['Authentication']); // API para obter vozes do ElevenLabs

// Rotas de Agentes de IA
// IMPORTANTE: Rotas específicas DEVEM vir ANTES de rotas com parâmetros dinâmicos
Router::get('/ai-agents/available', [ConversationController::class, 'getAvailableAIAgents'], ['Authentication']);
Router::get('/ai-agents', [AIAgentController::class, 'index'], ['Authentication']);
Router::get('/ai-agents/{id}/conversations', [AIAgentController::class, 'getConversations'], ['Authentication']);
Router::get('/ai-agents/{id}/conversations/{conversationId}/history', [AIAgentController::class, 'getConversationHistory'], ['Authentication']);
Router::get('/ai-agents/{id}/tool-executions', [AIAgentController::class, 'getToolExecutions'], ['Authentication']);
Router::get('/ai-agents/{id}/stats', [AIAgentController::class, 'getStats'], ['Authentication']);
Router::get('/ai-agents/{id}', [AIAgentController::class, 'show'], ['Authentication']);
Router::post('/ai-agents', [AIAgentController::class, 'store'], ['Authentication']);
Router::post('/ai-agents/{id}', [AIAgentController::class, 'update'], ['Authentication']);
Router::delete('/ai-agents/{id}', [AIAgentController::class, 'destroy'], ['Authentication']);
Router::post('/ai-agents/{id}/tools', [AIAgentController::class, 'addTool'], ['Authentication']);
Router::delete('/ai-agents/{id}/tools/{toolId}', [AIAgentController::class, 'removeTool'], ['Authentication']);

// Rotas RAG (Knowledge Base, Feedback Loop, URLs, Memórias)
Router::get('/ai-agents/{id}/rag/knowledge-base', [RAGController::class, 'knowledgeBase'], ['Authentication']);
Router::post('/ai-agents/{id}/rag/knowledge-base', [RAGController::class, 'addKnowledge'], ['Authentication']);
Router::get('/ai-agents/{id}/rag/knowledge-base/search', [RAGController::class, 'searchKnowledge'], ['Authentication']);
Router::delete('/ai-agents/{id}/rag/knowledge-base/{knowledgeId}', [RAGController::class, 'deleteKnowledge'], ['Authentication']);

Router::get('/ai-agents/{id}/rag/feedback-loop', [RAGController::class, 'feedbackLoop'], ['Authentication']);
Router::post('/ai-agents/{id}/rag/feedback-loop/{feedbackId}/review', [RAGController::class, 'reviewFeedback'], ['Authentication']);
Router::post('/ai-agents/{id}/rag/feedback-loop/{feedbackId}/ignore', [RAGController::class, 'ignoreFeedback'], ['Authentication']);

Router::get('/ai-agents/{id}/rag/urls', [RAGController::class, 'urls'], ['Authentication']);
Router::post('/ai-agents/{id}/rag/urls', [RAGController::class, 'addUrl'], ['Authentication']);
Router::post('/ai-agents/{id}/rag/urls/process', [RAGController::class, 'processUrls'], ['Authentication']);

Router::get('/ai-agents/{id}/rag/memory', [RAGController::class, 'memory'], ['Authentication']);

// Rotas de Agentes Kanban
Router::get('/kanban-agents', [KanbanAgentController::class, 'index'], ['Authentication']);
Router::get('/kanban-agents/system-data', [KanbanAgentController::class, 'getSystemData'], ['Authentication']);
Router::get('/kanban-agents/create', [KanbanAgentController::class, 'create'], ['Authentication']);
Router::get('/kanban-agents/executions/{id}/details', [KanbanAgentController::class, 'getExecutionDetails'], ['Authentication']);
Router::get('/kanban-agents/{id}', [KanbanAgentController::class, 'show'], ['Authentication']);
Router::get('/kanban-agents/{id}/edit', [KanbanAgentController::class, 'edit'], ['Authentication']);
Router::post('/kanban-agents', [KanbanAgentController::class, 'store'], ['Authentication']);
Router::post('/kanban-agents/{id}', [KanbanAgentController::class, 'update'], ['Authentication']);
Router::delete('/kanban-agents/{id}', [KanbanAgentController::class, 'delete'], ['Authentication']);
Router::post('/kanban-agents/{id}/execute', [KanbanAgentController::class, 'execute'], ['Authentication']);
Router::post('/kanban-agents/{id}/test-conditions', [KanbanAgentController::class, 'testConditions'], ['Authentication']);

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
Router::post('/ai-tools/{id}/test-n8n', [AIToolController::class, 'testN8N'], ['Authentication']);
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
// IMPORTANTE: Rotas específicas DEVEM vir ANTES de rotas com parâmetros dinâmicos
Router::get('/departments', [DepartmentController::class, 'index'], ['Authentication']);
Router::get('/departments/{id}/json', [DepartmentController::class, 'getJson'], ['Authentication']);
Router::get('/departments/{id}', [DepartmentController::class, 'show'], ['Authentication']);
Router::post('/departments', [DepartmentController::class, 'store'], ['Authentication']);
Router::post('/departments/{id}', [DepartmentController::class, 'update'], ['Authentication']);
Router::delete('/departments/{id}', [DepartmentController::class, 'destroy'], ['Authentication']);
Router::post('/departments/{id}/agents', [DepartmentController::class, 'addAgent'], ['Authentication']);
Router::post('/departments/{id}/agents/remove', [DepartmentController::class, 'removeAgent'], ['Authentication']);

// Times/Equipes
Router::get('/teams', [TeamController::class, 'index'], ['Authentication']);
Router::get('/teams/create', [TeamController::class, 'create'], ['Authentication']);
Router::post('/teams', [TeamController::class, 'store'], ['Authentication']);
Router::get('/teams/dashboard', [TeamController::class, 'dashboard'], ['Authentication']);
Router::get('/teams/show', [TeamController::class, 'show'], ['Authentication']);
Router::get('/teams/edit', [TeamController::class, 'edit'], ['Authentication']);
Router::post('/teams/update', [TeamController::class, 'update'], ['Authentication']);
Router::post('/teams/delete', [TeamController::class, 'delete'], ['Authentication']);
Router::get('/teams/performance', [TeamController::class, 'getPerformance'], ['Authentication']);
Router::post('/teams/compare', [TeamController::class, 'compareTeams'], ['Authentication']);

// Conversão WooCommerce (Lead → Venda)
Router::get('/agent-conversion', [AgentConversionController::class, 'index'], ['Authentication']);
Router::get('/agent-conversion/agent', [AgentConversionController::class, 'show'], ['Authentication']);
Router::get('/api/agent-conversion/metrics', [AgentConversionController::class, 'getMetrics'], ['Authentication']);
Router::post('/api/agent-conversion/sync', [AgentConversionController::class, 'syncOrders'], ['Authentication']);
Router::post('/api/woocommerce/test-meta-key', [WooCommerceController::class, 'testSellerMetaKey'], ['Authentication']);
Router::post('/api/woocommerce/sync-orders', [WooCommerceController::class, 'syncOrders'], ['Authentication', 'Permission:conversion.view']);

// Rotas de Tags
// IMPORTANTE: Rotas específicas DEVEM vir ANTES de rotas com parâmetros dinâmicos
Router::get('/tags/all', [TagController::class, 'getAll'], ['Authentication']);
Router::get('/tags', [TagController::class, 'index'], ['Authentication']);
Router::post('/tags', [TagController::class, 'store'], ['Authentication']);
Router::post('/tags/{id}', [TagController::class, 'update'], ['Authentication']);
Router::delete('/tags/{id}', [TagController::class, 'destroy'], ['Authentication']);
Router::post('/conversations/{id}/tags', [TagController::class, 'addToConversation'], ['Authentication']);
Router::post('/conversations/{id}/tags/remove', [TagController::class, 'removeFromConversation'], ['Authentication']);
Router::get('/conversations/{id}/tags', [TagController::class, 'getByConversation'], ['Authentication']);

// Rotas de Agentes de IA em Conversas
Router::get('/conversations/{id}/ai-status', [ConversationController::class, 'getAIStatus'], ['Authentication']);
Router::get('/conversations/{id}/ai-messages', [ConversationController::class, 'getAIMessages'], ['Authentication']);
Router::post('/conversations/{id}/ai-agents', [ConversationController::class, 'addAIAgent'], ['Authentication']);
Router::delete('/conversations/{id}/ai-agents', [ConversationController::class, 'removeAIAgent'], ['Authentication']);
Router::get('/conversations/{id}/automation-status', [ConversationController::class, 'getAutomationStatus'], ['Authentication']);

// Rotas de Notificações
Router::get('/notifications', [NotificationController::class, 'index'], ['Authentication']);
Router::get('/notifications/unread', [NotificationController::class, 'getUnread'], ['Authentication']);
Router::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'], ['Authentication']);
Router::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'], ['Authentication']);
Router::delete('/notifications/{id}', [NotificationController::class, 'destroy'], ['Authentication']);

// Rotas de Templates de Mensagens
Router::get('/message-templates', [MessageTemplateController::class, 'index'], ['Authentication']);
Router::post('/message-templates', [MessageTemplateController::class, 'store'], ['Authentication']);
// Rotas específicas DEVEM vir ANTES das rotas com parâmetros dinâmicos
Router::get('/message-templates/available', [MessageTemplateController::class, 'getAvailable'], ['Authentication']);
Router::get('/message-templates/personal', [MessageTemplateController::class, 'getPersonal'], ['Authentication']);
Router::get('/message-templates/variables', [MessageTemplateController::class, 'getVariables'], ['Authentication']);
// Rotas com parâmetros dinâmicos {id}
Router::get('/message-templates/{id}', [MessageTemplateController::class, 'show'], ['Authentication']);
Router::post('/message-templates/{id}', [MessageTemplateController::class, 'update'], ['Authentication']);
Router::delete('/message-templates/{id}', [MessageTemplateController::class, 'destroy'], ['Authentication']);
Router::post('/message-templates/{id}/preview', [MessageTemplateController::class, 'preview'], ['Authentication']);
Router::post('/message-templates/{id}/process', [MessageTemplateController::class, 'process'], ['Authentication']);

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

// Rotas de Logs do Sistema
Router::get('/logs', [LogController::class, 'index'], ['Authentication', 'Permission:admin.logs']);
Router::get('/logs/view', [LogController::class, 'view'], ['Authentication', 'Permission:admin.logs']);
Router::get('/logs/download', [LogController::class, 'download'], ['Authentication', 'Permission:admin.logs']);
Router::post('/logs/clear', [LogController::class, 'clear'], ['Authentication', 'Permission:admin.logs']);

