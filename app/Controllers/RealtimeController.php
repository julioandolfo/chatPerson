<?php
/**
 * Controller de Tempo Real (WebSocket/Polling)
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\SettingService;
use App\Services\ConversationService;
use App\Models\Message;
use App\Models\Conversation;

class RealtimeController
{
    /**
     * Obter configurações de tempo real
     */
    public function getConfig(): void
    {
        // Limpar qualquer output buffer antes de processar
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        try {
            // Permitir acesso público para configurações (necessário para cliente JS carregar)
            // Mas verificar se usuário está autenticado para retornar dados completos
            
            $settings = SettingService::getDefaultWebSocketSettings();

            // Se o WebSocket estiver desabilitado, forçar modo polling (mas manter tempo real ativo)
            $enabled = $settings['websocket_enabled'] ?? true;
            $connectionType = $settings['websocket_connection_type'] ?? 'auto';
            if (!$enabled && $connectionType === 'auto') {
                $connectionType = 'polling';
            }

            Response::json([
                'success' => true,
                'config' => [
                    // Mantém tempo real ativo se polling estiver disponível, mesmo com websocket desabilitado
                    'enabled' => $enabled || $connectionType === 'polling',
                    'connectionType' => $connectionType,
                    'websocketPort' => (int)($settings['websocket_port'] ?? 8080),
                    'websocketPath' => $settings['websocket_path'] ?? '/ws',
                    'websocketCustomUrl' => $settings['websocket_custom_url'] ?? '',
                    'pollingInterval' => (int)($settings['websocket_polling_interval'] ?? 3000)
                ]
            ]);
        } catch (\Exception $e) {
            // Limpar output buffer antes de retornar erro
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            \App\Helpers\Logger::error("RealtimeController::getConfig Error: " . $e->getMessage());
            
            Response::json([
                'success' => false,
                'message' => 'Erro ao obter configurações: ' . $e->getMessage(),
                'config' => [
                    'enabled' => true,
                    'connectionType' => 'auto',
                    'websocketPort' => 8080,
                    'websocketPath' => '/ws',
                    'websocketCustomUrl' => '',
                    'pollingInterval' => 3000
                ]
            ], 500);
        }
    }

    /**
     * Endpoint de polling para verificar atualizações
     */
    public function poll(): void
    {
        // Limpar qualquer output buffer antes de processar
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        try {
            // Verificar autenticação primeiro
            $userId = \App\Helpers\Auth::id();
            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
                return;
            }
            
            // Verificar permissão sem usar abortIfCannot (que pode gerar HTML)
            // Permitir quem tem view.own ou view.all
            if (!\App\Helpers\Permission::can('conversations.view.own') && !\App\Helpers\Permission::can('conversations.view.all')) {
                Response::json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
                return;
            }

            // Obter dados do POST
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                // Se não conseguir decodificar JSON, tentar usar Request::post()
                $data = Request::post();
            }
            $subscribedConversations = isset($data['subscribed_conversations']) && is_array($data['subscribed_conversations'])
                ? $data['subscribed_conversations']
                : [];

            // Garantir que last_update_time seja inteiro (timestamp em ms)
            if (isset($data['last_update_time']) && !is_array($data['last_update_time'])) {
                $lastUpdateTime = (int)$data['last_update_time'];
            } else {
                $lastUpdateTime = 0;
            }

            // Processar heartbeat/atividade do usuário
            if (isset($data['activity_type'])) {
                try {
                    \App\Services\AvailabilityService::updateActivity($userId, (string)$data['activity_type']);
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error("Erro ao processar atividade no polling: " . $e->getMessage());
                }
            }

            if (isset($data['last_activity']) || isset($data['activity_type'])) {
                try {
                    \App\Services\AvailabilityService::processHeartbeat($userId);
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error("Erro ao processar heartbeat no polling: " . $e->getMessage());
                }
            }

            $updates = [
                'new_messages' => [],
                'conversation_updates' => [],
                'new_conversations' => [], // Novas conversas criadas
                'agent_status' => [],
                'message_status_updates' => []
            ];

            // Verificar novas mensagens nas conversas inscritas
            if (!empty($subscribedConversations) && is_array($subscribedConversations)) {
                foreach ($subscribedConversations as $convId) {
                    $convId = (int)$convId;
                    if ($convId <= 0) continue;
                    
                    try {
                        // Buscar mensagens novas desde o último update
                        $lastMessageTime = $lastUpdateTime > 0 ? date('Y-m-d H:i:s', (int)($lastUpdateTime / 1000)) : null;
                        
                        $messages = Message::getNewMessagesSince($convId, $lastMessageTime);
                    
                        foreach ($messages as $msg) {
                            if (!isset($msg['id'])) continue;
                            
                            $senderType = $msg['sender_type'] ?? 'contact';
                            
                            // Determinar direction baseado em sender_type
                            // Mensagens de agentes são sempre outgoing (enviadas pelo sistema/agente)
                            // Mensagens de contatos são sempre incoming (recebidas)
                            $direction = ($senderType === 'agent') ? 'outgoing' : 'incoming';
                            
                            // Determinar type baseado em message_type
                            $messageType = $msg['message_type'] ?? 'text';
                            if ($messageType === 'note') {
                                $type = 'note';
                            } elseif ($messageType === 'system' || $senderType === 'system') {
                                // Logs de automação / eventos do sistema
                                $type = 'system';
                            } else {
                                $type = 'message';
                            }
                            
                            // Log para debug
                            \App\Helpers\Logger::info("📨 Polling: Nova mensagem - convId={$convId}, msgId={$msg['id']}, sender_type={$senderType}, direction={$direction}", 'conversas.log');
                            
                            $messageData = [
                                'conversation_id' => $convId,
                                'id' => $msg['id'],
                                'content' => $msg['content'] ?? '',
                                'sender_type' => $senderType,
                                'sender_id' => $msg['sender_id'] ?? null,
                                'sender_name' => $msg['sender_name'] ?? null,
                                'sender_avatar' => $msg['sender_avatar'] ?? null,
                                'created_at' => $msg['created_at'] ?? date('Y-m-d H:i:s'),
                                'message_type' => $messageType,
                                'type' => $type,
                                'direction' => $direction,
                                'attachments' => $msg['attachments'] ?? [],
                                'status' => $msg['status'] ?? 'sent',
                                'external_id' => $msg['external_id'] ?? null,
                                'ai_agent_id' => $msg['ai_agent_id'] ?? null,
                                'ai_agent_name' => $msg['ai_agent_name'] ?? null,
                                'quoted_message_id' => $msg['quoted_message_id'] ?? null,
                                'quoted_text' => $msg['quoted_text'] ?? null,
                                'quoted_sender_name' => $msg['quoted_sender_name'] ?? null,
                                'delivered_at' => $msg['delivered_at'] ?? null,
                                'read_at' => $msg['read_at'] ?? null,
                                'error_message' => $msg['error_message'] ?? null
                            ];
                            
                            // Log do que será enviado
                            \App\Helpers\Logger::info("📤 Polling: Enviando para frontend - " . json_encode($messageData), 'conversas.log');
                            
                            $updates['new_messages'][] = $messageData;
                        }

                        // Verificar se a conversa foi atualizada
                        $conversation = Conversation::find($convId);
                        if ($conversation && isset($conversation['updated_at'])) {
                            $updatedAt = strtotime($conversation['updated_at']);
                            if ($updatedAt > (int)($lastUpdateTime / 1000)) {
                                // Buscar nome do funil e etapa se existirem
                                $funnelName = null;
                                $stageName = null;
                                $stageColor = null;
                                
                                if (!empty($conversation['funnel_stage_id'])) {
                                    $stage = \App\Models\FunnelStage::find($conversation['funnel_stage_id']);
                                    if ($stage) {
                                        $stageName = $stage['name'] ?? null;
                                        $stageColor = $stage['color'] ?? null;
                                        
                                        if (!empty($stage['funnel_id'])) {
                                            $funnel = \App\Models\Funnel::find($stage['funnel_id']);
                                            $funnelName = $funnel['name'] ?? null;
                                        }
                                    }
                                }
                                
                                $updates['conversation_updates'][] = [
                                    'id' => $conversation['id'],
                                    'status' => $conversation['status'] ?? 'open',
                                    'unread_count' => $conversation['unread_count'] ?? 0,
                                    'updated_at' => $conversation['updated_at'],
                                    'agent_id' => $conversation['agent_id'] ?? null,
                                    'department_id' => $conversation['department_id'] ?? null,
                                    'funnel_id' => $conversation['funnel_id'] ?? null,
                                    'funnel_stage_id' => $conversation['funnel_stage_id'] ?? null,
                                    'funnel_name' => $funnelName,
                                    'stage_name' => $stageName,
                                    'stage_color' => $stageColor,
                                    'last_message' => $conversation['last_message'] ?? null,
                                    'last_message_at' => $conversation['last_message_at'] ?? null
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        // Logar erro mas continuar processando outras conversas
                        \App\Helpers\Logger::error("Erro ao processar conversa {$convId} no polling: " . $e->getMessage());
                        continue;
                    }
                }
            }

            // Verificar atualizações na lista de conversas do usuário (limitado para performance)
            // Apenas verificar se há conversas atualizadas recentemente
            try {
                $userConversations = ConversationService::getUserConversations($userId);
                $checkedCount = 0;
                $maxChecks = 50; // Limitar para não sobrecarregar
                
                // Coletar mensagens com status alterado desde last_update_time
                $statusUpdates = \App\Models\Message::getStatusUpdatesSince($lastUpdateTime > 0 ? date('Y-m-d H:i:s', (int)($lastUpdateTime / 1000)) : null);
                foreach ($statusUpdates as $su) {
                    $updates['message_status_updates'][] = [
                        'conversation_id' => $su['conversation_id'],
                        'message_id' => $su['id'],
                        'status' => $su['status'],
                        'delivered_at' => $su['delivered_at'] ?? null,
                        'read_at' => $su['read_at'] ?? null
                    ];
                }

                // Se lastUpdateTime for 0 ou muito antigo, verificar conversas criadas nos últimos 30 segundos
                $checkRecentThreshold = time() - 30; // Últimos 30 segundos
                $shouldCheckRecent = ($lastUpdateTime === 0 || (int)($lastUpdateTime / 1000) < $checkRecentThreshold);
                
                if (is_array($userConversations)) {
                    foreach ($userConversations as $conv) {
                        if ($checkedCount >= $maxChecks) break;
                        $checkedCount++;
                        
                        if (!isset($conv['id']) || !isset($conv['updated_at'])) {
                            continue;
                        }
                        
                        $updatedAt = strtotime($conv['updated_at']);
                        if ($updatedAt === false) {
                            continue;
                        }

                        if ($lastUpdateTime < 0) {
                            $lastUpdateTime = 0;
                        }

                        $isNewConversation = false;
                        if (isset($conv['created_at'])) {
                            $createdAt = strtotime($conv['created_at']);
                            if ($createdAt !== false) {
                                // Se for verificação recente, considerar nova se criada nos últimos 30s
                                if ($shouldCheckRecent && $createdAt > $checkRecentThreshold) {
                                    $isNewConversation = true;
                                } elseif ($lastUpdateTime > 0 && $createdAt > (int)($lastUpdateTime / 1000)) {
                                    // Só considerar nova se lastUpdateTime > 0 (não é primeira requisição)
                                    // E se a conversa foi criada depois do último update
                                    $isNewConversation = true;
                                }
                                // Se lastUpdateTime = 0 (primeira requisição), não considerar como nova
                                // para evitar notificar conversas que já existiam quando o usuário entrou
                            }
                        }

                        // Verificar se conversa foi atualizada ou é nova
                        $shouldInclude = false;
                        if ($isNewConversation) {
                            $shouldInclude = true;
                        } elseif ($updatedAt > (int)($lastUpdateTime / 1000)) {
                            $shouldInclude = true;
                        } elseif ($shouldCheckRecent && $updatedAt > $checkRecentThreshold) {
                            // Se estamos verificando conversas recentes, incluir se atualizada nos últimos 30s
                            $shouldInclude = true;
                        }

                        if ($shouldInclude) {
                            // ✅ FILTRO: Apenas incluir conversas com status 'open' em new_conversations
                            // Conversas fechadas/resolvidas com mensagens novas NÃO devem aparecer na lista
                            $conversationStatus = $conv['status'] ?? 'open';
                            
                            // Se for nova conversa mas está fechada, NÃO incluir
                            if ($isNewConversation && !in_array($conversationStatus, ['open'])) {
                                continue; // Pular esta conversa
                            }
                            
                            // Se for atualização mas está fechada, NÃO incluir
                            if (!$isNewConversation && !in_array($conversationStatus, ['open'])) {
                                continue; // Pular esta conversa
                            }
                            
                            // Verificar se já não está na lista de updates
                            $exists = false;
                            foreach ($updates['conversation_updates'] as $update) {
                                if ($update['id'] == $conv['id']) {
                                    $exists = true;
                                    break;
                                }
                            }
                            foreach ($updates['new_conversations'] as $update) {
                                if ($update['id'] == $conv['id']) {
                                    $exists = true;
                                    break;
                                }
                            }
                            
                            if (!$exists) {
                                // Buscar nome do funil e etapa se não estiverem disponíveis
                                $funnelName = $conv['funnel_name'] ?? null;
                                $stageName = $conv['stage_name'] ?? null;
                                $stageColor = $conv['stage_color'] ?? null;
                                
                                // Se não tiver nome mas tiver ID, buscar
                                if (!$stageName && !empty($conv['funnel_stage_id'])) {
                                    $stage = \App\Models\FunnelStage::find($conv['funnel_stage_id']);
                                    if ($stage) {
                                        $stageName = $stage['name'] ?? null;
                                        $stageColor = $stage['color'] ?? null;
                                        
                                        if (!$funnelName && !empty($stage['funnel_id'])) {
                                            $funnel = \App\Models\Funnel::find($stage['funnel_id']);
                                            $funnelName = $funnel['name'] ?? null;
                                        }
                                    }
                                }
                                
                                $conversationData = [
                                    'id' => $conv['id'],
                                    'status' => $conversationStatus,
                                    'unread_count' => $conv['unread_count'] ?? 0,
                                    'updated_at' => $conv['updated_at'],
                                    'created_at' => $conv['created_at'] ?? $conv['updated_at'],
                                    'contact_name' => $conv['contact_name'] ?? null,
                                    'contact_phone' => $conv['contact_phone'] ?? null,
                                    'contact_avatar' => $conv['contact_avatar'] ?? null,
                                    'last_message' => $conv['last_message'] ?? null,
                                    'last_message_at' => $conv['last_message_at'] ?? null,
                                    'channel' => $conv['channel'] ?? 'whatsapp',
                                    'agent_id' => $conv['agent_id'] ?? null,
                                    'agent_name' => $conv['agent_name'] ?? null,
                                    'department_id' => $conv['department_id'] ?? null,
                                    'funnel_id' => $conv['funnel_id'] ?? null,
                                    'funnel_stage_id' => $conv['funnel_stage_id'] ?? null,
                                    'funnel_name' => $funnelName,
                                    'stage_name' => $stageName,
                                    'stage_color' => $stageColor,
                                    'tags_data' => $conv['tags_data'] ?? null,
                                    'pinned' => $conv['pinned'] ?? 0
                                ];
                                
                                if ($isNewConversation) {
                                    $updates['new_conversations'][] = $conversationData;
                                } else {
                                    $updates['conversation_updates'][] = $conversationData;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Logar erro mas não interromper o processo
                \App\Helpers\Logger::error("Erro ao obter conversas do usuário no polling: " . $e->getMessage());
            }

            // Limpar output buffer novamente antes de retornar JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json([
                'success' => true,
                'updates' => $updates,
                'timestamp' => time() * 1000 // Retornar em milissegundos
            ]);
        } catch (\Exception $e) {
            // Limpar output buffer antes de retornar erro
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            \App\Helpers\Logger::error("RealtimeController::poll Error: " . $e->getMessage());
            \App\Helpers\Logger::error("RealtimeController::poll Stack: " . $e->getTraceAsString());
            
            Response::json([
                'success' => false,
                'message' => 'Erro ao verificar atualizações: ' . $e->getMessage()
            ], 500);
        }
    }
}

