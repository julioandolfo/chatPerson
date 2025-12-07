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
            if (!\App\Helpers\Permission::can('conversations.view.own')) {
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

            $updates = [
                'new_messages' => [],
                'conversation_updates' => [],
                'new_conversations' => [], // Novas conversas criadas
                'agent_status' => []
            ];

            // Verificar novas mensagens nas conversas inscritas
            if (!empty($subscribedConversations) && is_array($subscribedConversations)) {
                foreach ($subscribedConversations as $convId) {
                    $convId = (int)$convId;
                    if ($convId <= 0) continue;
                    
                    try {
                        // Buscar mensagens novas desde o último update
                        $lastMessageTime = $lastUpdateTime > 0 ? date('Y-m-d H:i:s', $lastUpdateTime / 1000) : null;
                        
                        $messages = Message::getNewMessagesSince($convId, $lastMessageTime);
                    
                        foreach ($messages as $msg) {
                            if (!isset($msg['id'])) continue;
                            
                            $updates['new_messages'][] = [
                                'conversation_id' => $convId,
                                'id' => $msg['id'],
                                'content' => $msg['content'] ?? '',
                                'sender_type' => $msg['sender_type'] ?? 'contact',
                                'sender_id' => $msg['sender_id'] ?? null,
                                'sender_name' => $msg['sender_name'] ?? null,
                                'created_at' => $msg['created_at'] ?? date('Y-m-d H:i:s'),
                                'message_type' => $msg['message_type'] ?? 'text'
                            ];
                        }

                        // Verificar se a conversa foi atualizada
                        $conversation = Conversation::find($convId);
                        if ($conversation && isset($conversation['updated_at'])) {
                            $updatedAt = strtotime($conversation['updated_at']);
                            if ($updatedAt > ($lastUpdateTime / 1000)) {
                                $updates['conversation_updates'][] = [
                                    'id' => $conversation['id'],
                                    'status' => $conversation['status'] ?? 'open',
                                    'unread_count' => $conversation['unread_count'] ?? 0,
                                    'updated_at' => $conversation['updated_at']
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
                
                // Se lastUpdateTime for 0 ou muito antigo, verificar conversas criadas nos últimos 30 segundos
                $checkRecentThreshold = time() - 30; // Últimos 30 segundos
                $shouldCheckRecent = ($lastUpdateTime === 0 || ($lastUpdateTime / 1000) < $checkRecentThreshold);
                
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
                                } elseif ($createdAt > ($lastUpdateTime / 1000)) {
                                    $isNewConversation = true;
                                }
                            }
                        }

                        // Verificar se conversa foi atualizada ou é nova
                        $shouldInclude = false;
                        if ($isNewConversation) {
                            $shouldInclude = true;
                        } elseif ($updatedAt > ($lastUpdateTime / 1000)) {
                            $shouldInclude = true;
                        } elseif ($shouldCheckRecent && $updatedAt > $checkRecentThreshold) {
                            // Se estamos verificando conversas recentes, incluir se atualizada nos últimos 30s
                            $shouldInclude = true;
                        }

                        if ($shouldInclude) {
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
                                $conversationData = [
                                    'id' => $conv['id'],
                                    'status' => $conv['status'] ?? 'open',
                                    'unread_count' => $conv['unread_count'] ?? 0,
                                    'updated_at' => $conv['updated_at'],
                                    'created_at' => $conv['created_at'] ?? $conv['updated_at'],
                                    'contact_name' => $conv['contact_name'] ?? null,
                                    'contact_phone' => $conv['contact_phone'] ?? null,
                                    'contact_avatar' => $conv['contact_avatar'] ?? null,
                                    'last_message' => $conv['last_message'] ?? null,
                                    'last_message_at' => $conv['last_message_at'] ?? null,
                                    'channel' => $conv['channel'] ?? 'whatsapp',
                                    'agent_name' => $conv['agent_name'] ?? null,
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

