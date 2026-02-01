<?php
/**
 * Job ChatbotTimeoutJob
 * Verifica e processa timeouts de chatbots em conversas
 */

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Automation;
use App\Services\AutomationService;
use App\Services\ConversationService;
use App\Helpers\Logger;

class ChatbotTimeoutJob
{
    /**
     * Executar job de verificação de timeout de chatbot
     */
    public static function run(): void
    {
        try {
            echo "[" . date('Y-m-d H:i:s') . "] Iniciando ChatbotTimeoutJob...\n";
            Logger::log("=== INICIANDO CHATBOT TIMEOUT JOB ===");
            
            // Buscar conversas com chatbot ativo e timeout configurado
            $conversations = Conversation::query()
                ->where('status', '!=', 'closed')
                ->get();
            
            $processedCount = 0;
            $now = time();
            
            foreach ($conversations as $conversation) {
                $metadata = json_decode($conversation['metadata'] ?? '{}', true);
                
                // Verificar se chatbot está ativo e tem timeout configurado
                if (empty($metadata['chatbot_active'])) {
                    continue;
                }
                
                $timeoutAt = $metadata['chatbot_timeout_at'] ?? null;
                if (!$timeoutAt || $timeoutAt > $now) {
                    continue;
                }
                
                // Timeout expirado! Processar
                Logger::automation("⏰ Timeout de chatbot expirado para conversa {$conversation['id']}");
                echo "[" . date('Y-m-d H:i:s') . "] Processando timeout para conversa {$conversation['id']}\n";
                
                self::processTimeout($conversation, $metadata);
                $processedCount++;
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] ChatbotTimeoutJob executado com sucesso. {$processedCount} timeout(s) processado(s)\n";
            Logger::log("ChatbotTimeoutJob executado com sucesso. {$processedCount} timeout(s) processado(s)");
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] ERRO no ChatbotTimeoutJob: " . $e->getMessage() . "\n";
            error_log("Erro ao executar ChatbotTimeoutJob: " . $e->getMessage());
            Logger::error("Erro ao executar ChatbotTimeoutJob: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Processar timeout de um chatbot específico
     */
    private static function processTimeout(array $conversation, array $metadata): void
    {
        try {
            $conversationId = $conversation['id'];
            $timeoutAction = $metadata['chatbot_timeout_action'] ?? 'nothing';
            $timeoutNodeId = $metadata['chatbot_timeout_node_id'] ?? null;
            $automationId = $metadata['chatbot_automation_id'] ?? null;
            
            Logger::automation("  Ação de timeout: {$timeoutAction}");
            Logger::automation("  Nó de timeout: " . ($timeoutNodeId ?: 'nenhum'));
            
            // Processar ação de timeout
            switch ($timeoutAction) {
                case 'go_to_node':
                    if ($timeoutNodeId && $automationId) {
                        Logger::automation("  Seguindo para nó {$timeoutNodeId}...");
                        
                        // Carregar automação e nó
                        $automation = Automation::findWithNodes((int)$automationId);
                        if ($automation && !empty($automation['nodes'])) {
                            $targetNode = self::findNodeById($timeoutNodeId, $automation['nodes']);
                            if ($targetNode) {
                                // Limpar estado do chatbot
                                $metadata['chatbot_active'] = false;
                                $metadata['chatbot_options'] = [];
                                $metadata['chatbot_next_nodes'] = [];
                                $metadata['chatbot_node_id'] = null;
                                $metadata['chatbot_invalid_attempts'] = 0;
                                $metadata['chatbot_timeout_at'] = null;
                                
                                Conversation::update($conversationId, [
                                    'metadata' => json_encode($metadata)
                                ]);
                                
                                // Executar nó de destino
                                AutomationService::executeNodeForDelay(
                                    $targetNode,
                                    $conversationId,
                                    $automation['nodes'],
                                    null
                                );
                                
                                Logger::automation("  ✅ Nó de timeout executado com sucesso");
                            } else {
                                Logger::automation("  ❌ Nó {$timeoutNodeId} não encontrado!");
                                self::clearChatbotState($conversationId, $metadata);
                            }
                        } else {
                            Logger::automation("  ❌ Automação não encontrada!");
                            self::clearChatbotState($conversationId, $metadata);
                        }
                    } else {
                        Logger::automation("  ⚠️ Nó de timeout não configurado, limpando estado...");
                        self::clearChatbotState($conversationId, $metadata);
                    }
                    break;
                    
                case 'assign_agent':
                    Logger::automation("  Preparando para atribuir a um agente...");
                    // Limpar chatbot - a atribuição será feita por automações normais
                    self::clearChatbotState($conversationId, $metadata);
                    
                    // Enviar mensagem informando que será atribuído
                    try {
                        ConversationService::sendMessage(
                            $conversationId,
                            "Aguarde, você será atendido por um de nossos atendentes em breve.",
                            'agent',
                            null
                        );
                        Logger::automation("  ✅ Mensagem de atribuição enviada");
                    } catch (\Exception $e) {
                        Logger::automation("  ❌ Erro ao enviar mensagem: " . $e->getMessage());
                    }
                    break;
                    
                case 'send_message':
                    Logger::automation("  Enviando mensagem de timeout...");
                    self::clearChatbotState($conversationId, $metadata);
                    
                    try {
                        ConversationService::sendMessage(
                            $conversationId,
                            "Desculpe, o tempo de espera expirou. Um atendente entrará em contato em breve.",
                            'agent',
                            null
                        );
                        Logger::automation("  ✅ Mensagem de timeout enviada");
                    } catch (\Exception $e) {
                        Logger::automation("  ❌ Erro ao enviar mensagem: " . $e->getMessage());
                    }
                    break;
                    
                case 'close':
                    Logger::automation("  Encerrando conversa...");
                    self::clearChatbotState($conversationId, $metadata);
                    
                    try {
                        Conversation::update($conversationId, [
                            'status' => 'closed',
                            'closed_at' => date('Y-m-d H:i:s')
                        ]);
                        Logger::automation("  ✅ Conversa encerrada");
                    } catch (\Exception $e) {
                        Logger::automation("  ❌ Erro ao encerrar conversa: " . $e->getMessage());
                    }
                    break;
                    
                case 'nothing':
                default:
                    Logger::automation("  Nenhuma ação configurada, apenas limpando estado...");
                    self::clearChatbotState($conversationId, $metadata);
                    break;
            }
            
        } catch (\Exception $e) {
            Logger::error("Erro ao processar timeout do chatbot para conversa {$conversation['id']}: " . $e->getMessage());
        }
    }
    
    /**
     * Limpar estado do chatbot
     */
    private static function clearChatbotState(int $conversationId, array $metadata): void
    {
        $metadata['chatbot_active'] = false;
        $metadata['chatbot_options'] = [];
        $metadata['chatbot_next_nodes'] = [];
        $metadata['chatbot_automation_id'] = null;
        $metadata['chatbot_node_id'] = null;
        $metadata['chatbot_invalid_attempts'] = 0;
        $metadata['chatbot_timeout_at'] = null;
        
        Conversation::update($conversationId, [
            'metadata' => json_encode($metadata)
        ]);
        
        Logger::automation("  Estado do chatbot limpo");
    }
    
    /**
     * Encontrar nó por ID
     */
    private static function findNodeById($nodeId, array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if (String($node['id']) === String($nodeId)) {
                return $node;
            }
        }
        return null;
    }
}
