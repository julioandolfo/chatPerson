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
            Logger::automation("=== INICIANDO CHATBOT TIMEOUT JOB ===");
            
            // Buscar conversas com chatbot ativo e timeout configurado
            $conversations = Conversation::query()
                ->where('status', '!=', 'closed')
                ->get();
            
            $processedCount = 0;
            $chatbotActiveCount = 0;
            $now = time();
            
            foreach ($conversations as $conversation) {
                $metadata = json_decode($conversation['metadata'] ?? '{}', true);
                
                // Verificar se chatbot está ativo e tem timeout configurado
                if (empty($metadata['chatbot_active'])) {
                    continue;
                }
                
                $chatbotActiveCount++;
                $timeoutAt = $metadata['chatbot_timeout_at'] ?? null;
                $timeoutAction = $metadata['chatbot_timeout_action'] ?? 'nothing';
                $timeoutNodeId = $metadata['chatbot_timeout_node_id'] ?? null;
                $automationId = $metadata['chatbot_automation_id'] ?? null;
                
                if (!$timeoutAt) {
                    Logger::automation("  ⚠️ Conversa {$conversation['id']}: chatbot_active=true mas SEM chatbot_timeout_at definido!");
                    continue;
                }
                
                $remaining = $timeoutAt - $now;
                
                if ($remaining > 0) {
                    $inactivityMode = $metadata['chatbot_inactivity_mode'] ?? 'timeout';
                    $reconnectInfo = '';
                    if ($inactivityMode === 'reconnect') {
                        $reconnectCurrent = (int)($metadata['chatbot_reconnect_current'] ?? 0);
                        $reconnectTotal = count($metadata['chatbot_reconnect_attempts'] ?? []);
                        $reconnectInfo = ", modo=reconexão ({$reconnectCurrent}/{$reconnectTotal})";
                    }
                    Logger::automation("  ⏳ Conversa {$conversation['id']}: chatbot ativo, timeout em {$remaining}s (expira em " . date('H:i:s', $timeoutAt) . "), ação={$timeoutAction}{$reconnectInfo}");
                    continue;
                }
                
                // Timeout expirado! Processar
                $expiredAgo = abs($remaining);
                Logger::automation("⏰ Timeout de chatbot EXPIRADO para conversa {$conversation['id']} (expirou há {$expiredAgo}s, ação={$timeoutAction}, timeout_node_id={$timeoutNodeId}, automation_id={$automationId})");
                echo "[" . date('Y-m-d H:i:s') . "] Processando timeout para conversa {$conversation['id']} (expirou há {$expiredAgo}s)\n";
                
                self::processTimeout($conversation, $metadata);
                $processedCount++;
            }
            
            $totalConvs = count($conversations);
            echo "[" . date('Y-m-d H:i:s') . "] ChatbotTimeoutJob concluído. {$processedCount} timeout(s) processado(s), {$chatbotActiveCount} chatbot(s) ativos de {$totalConvs} conversas\n";
            Logger::automation("ChatbotTimeoutJob concluído. {$processedCount} timeout(s) processado(s), {$chatbotActiveCount} chatbot(s) ativos de {$totalConvs} conversas abertas");
        } catch (\Throwable $e) {
            $errorMsg = "ERRO FATAL no ChatbotTimeoutJob: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine();
            echo "[" . date('Y-m-d H:i:s') . "] {$errorMsg}\n";
            error_log($errorMsg);
            Logger::error($errorMsg);
            Logger::automation("❌ {$errorMsg}");
            Logger::automation("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Processar timeout de um chatbot específico
     */
    private static function processTimeout(array $conversation, array $metadata): void
    {
        try {
            $conversationId = $conversation['id'];
            $inactivityMode = $metadata['chatbot_inactivity_mode'] ?? 'timeout';
            $timeoutAction = $metadata['chatbot_timeout_action'] ?? 'nothing';
            $timeoutNodeId = $metadata['chatbot_timeout_node_id'] ?? null;
            $automationId = $metadata['chatbot_automation_id'] ?? null;
            
            Logger::automation("  📋 Processando timeout para conversa {$conversationId}:");
            Logger::automation("    - Modo: {$inactivityMode}");
            Logger::automation("    - Ação final: {$timeoutAction}");
            Logger::automation("    - Nó destino (timeout_node_id): " . ($timeoutNodeId ?: 'nenhum'));
            Logger::automation("    - Automação ID: " . ($automationId ?: 'nenhum'));
            
            // Verificar se estamos no modo reconexão e ainda há tentativas
            if ($inactivityMode === 'reconnect') {
                $reconnectAttempts = $metadata['chatbot_reconnect_attempts'] ?? [];
                $currentAttempt = (int)($metadata['chatbot_reconnect_current'] ?? 0);
                $totalAttempts = count($reconnectAttempts);
                
                Logger::automation("    - Reconexão: tentativa {$currentAttempt}/{$totalAttempts}");
                
                if ($currentAttempt < $totalAttempts) {
                    // Ainda há tentativas! Enviar mensagem de reconexão
                    self::processReconnectAttempt($conversationId, $metadata, $reconnectAttempts, $currentAttempt);
                    return; // Não executar ação final ainda
                }
                
                Logger::automation("  🔚 Todas as {$totalAttempts} tentativas de reconexão esgotadas. Executando ação final...");
            }
            
            // Executar ação final de timeout
            self::executeFinalTimeoutAction($conversationId, $metadata, $timeoutAction, $timeoutNodeId, $automationId);
            
        } catch (\Throwable $e) {
            $errorMsg = "Erro ao processar timeout do chatbot para conversa {$conversation['id']}: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine();
            Logger::error($errorMsg);
            Logger::automation("  ❌ {$errorMsg}");
            Logger::automation("  Stack trace: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Processar uma tentativa de reconexão
     */
    private static function processReconnectAttempt(int $conversationId, array $metadata, array $attempts, int $currentAttempt): void
    {
        $attempt = $attempts[$currentAttempt];
        $message = $attempt['message'] ?? '';
        $nextDelay = (int)($attempt['delay'] ?? 120);
        $attemptNumber = $currentAttempt + 1;
        $totalAttempts = count($attempts);
        
        Logger::automation("  🔄 Reconexão #{$attemptNumber}/{$totalAttempts} para conversa {$conversationId}");
        Logger::automation("    Mensagem: " . substr($message, 0, 80) . (strlen($message) > 80 ? '...' : ''));
        Logger::automation("    Próximo timeout em: {$nextDelay}s");
        
        if (empty($message)) {
            Logger::automation("    ⚠️ Mensagem de reconexão vazia, pulando para próxima tentativa...");
            // Avançar para próxima tentativa sem enviar mensagem
            $metadata['chatbot_reconnect_current'] = $currentAttempt + 1;
            $metadata['chatbot_timeout_at'] = time() + $nextDelay;
            Conversation::update($conversationId, [
                'metadata' => json_encode($metadata)
            ]);
            return;
        }
        
        try {
            // Buscar conversa para obter dados de envio
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                Logger::automation("    ❌ Conversa {$conversationId} não encontrada!");
                return;
            }
            
            $contact = \App\Models\Contact::find($conversation['contact_id']);
            if (!$contact) {
                Logger::automation("    ❌ Contato não encontrado para conversa {$conversationId}!");
                return;
            }
            
            // Processar variáveis na mensagem
            $message = AutomationService::processVariablesPublic($message, $conversation);
            
            // Resolver conta para envio
            $integrationAccountId = \App\Models\IntegrationAccount::resolveAccountForSending($conversation);
            
            if (!$integrationAccountId) {
                Logger::automation("    ❌ Sem conta de integração para envio na conversa {$conversationId}!");
                return;
            }
            
            // Enviar mensagem de reconexão via WhatsApp
            Logger::automation("    📤 Enviando mensagem de reconexão para {$contact['phone']} (integration_account_id={$integrationAccountId})...");
            
            $response = \App\Services\WhatsAppService::sendMessage(
                $integrationAccountId,
                $contact['phone'],
                $message
            );
            
            Logger::automation("    ✅ Mensagem de reconexão #{$attemptNumber} enviada com sucesso!");
            
            // Extrair external_id
            $externalId = $response['id'] ?? $response['message_id'] ?? $response['data']['id'] ?? null;
            
            // Salvar mensagem no banco
            \App\Models\Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => null,
                'sender_type' => 'agent',
                'content' => $message,
                'message_type' => 'text',
                'channel' => 'whatsapp',
                'external_id' => $externalId,
                'metadata' => json_encode([
                    'reconnect_attempt' => $attemptNumber,
                    'reconnect_total' => $totalAttempts,
                    'chatbot_message' => true,
                    'sent_at' => time()
                ])
            ]);
            
        } catch (\Throwable $e) {
            Logger::automation("    ❌ Erro ao enviar mensagem de reconexão: " . $e->getMessage());
            // Continuar mesmo com erro - não queremos travar o fluxo
        }
        
        // Avançar para próxima tentativa e definir próximo timeout
        $metadata['chatbot_reconnect_current'] = $currentAttempt + 1;
        $metadata['chatbot_timeout_at'] = time() + $nextDelay;
        
        Conversation::update($conversationId, [
            'metadata' => json_encode($metadata)
        ]);
        
        $nextAttemptNum = $currentAttempt + 2;
        if ($nextAttemptNum <= $totalAttempts) {
            Logger::automation("    ⏳ Próxima tentativa (#{$nextAttemptNum}) em {$nextDelay}s");
        } else {
            Logger::automation("    ⏳ Ação final em {$nextDelay}s (última tentativa)");
        }
    }
    
    /**
     * Executar ação final de timeout (após reconexões ou timeout simples)
     */
    private static function executeFinalTimeoutAction(int $conversationId, array $metadata, string $timeoutAction, ?string $timeoutNodeId, ?string $automationId): void
    {
        Logger::automation("  🎯 Executando ação final '{$timeoutAction}' para conversa {$conversationId}");
        
        switch ($timeoutAction) {
            case 'go_to_node':
                if ($timeoutNodeId && $automationId) {
                    Logger::automation("  🔄 Seguindo para nó {$timeoutNodeId} da automação {$automationId}...");
                    
                    $automation = Automation::findWithNodes((int)$automationId);
                    if ($automation && !empty($automation['nodes'])) {
                        $nodeCount = count($automation['nodes']);
                        Logger::automation("    Automação '{$automation['name']}' carregada com {$nodeCount} nós");
                        
                        $availableIds = array_map(function($n) {
                            return (string)($n['id'] ?? 'null') . " (" . ($n['node_type'] ?? '?') . ")";
                        }, $automation['nodes']);
                        Logger::automation("    Nós disponíveis: " . implode(', ', $availableIds));
                        
                        $targetNode = self::findNodeById($timeoutNodeId, $automation['nodes']);
                        if ($targetNode) {
                            Logger::automation("    ✅ Nó destino encontrado: id={$targetNode['id']}, tipo={$targetNode['node_type']}");
                            
                            self::clearChatbotState($conversationId, $metadata);
                            Logger::automation("    Estado do chatbot limpo, executando nó destino...");
                            
                            AutomationService::executeNodeForDelay(
                                $targetNode,
                                $conversationId,
                                $automation['nodes'],
                                null
                            );
                            
                            Logger::automation("  ✅ Nó de timeout executado com sucesso para conversa {$conversationId}");
                        } else {
                            Logger::automation("  ❌ Nó {$timeoutNodeId} NÃO ENCONTRADO na automação {$automationId}!");
                            self::clearChatbotState($conversationId, $metadata);
                        }
                    } else {
                        Logger::automation("  ❌ Automação {$automationId} não encontrada ou sem nós!");
                        self::clearChatbotState($conversationId, $metadata);
                    }
                } else {
                    Logger::automation("  ⚠️ go_to_node configurado mas faltam dados");
                    self::clearChatbotState($conversationId, $metadata);
                }
                break;
                
            case 'assign_agent':
                Logger::automation("  👤 Preparando para atribuir a um agente...");
                self::clearChatbotState($conversationId, $metadata);
                
                try {
                    ConversationService::sendMessage(
                        $conversationId,
                        "Aguarde, você será atendido por um de nossos atendentes em breve.",
                        'agent',
                        null
                    );
                    Logger::automation("  ✅ Mensagem de atribuição enviada para conversa {$conversationId}");
                } catch (\Throwable $e) {
                    Logger::automation("  ❌ Erro ao enviar mensagem de atribuição: " . $e->getMessage());
                }
                break;
                
            case 'send_message':
                Logger::automation("  💬 Enviando mensagem de timeout...");
                self::clearChatbotState($conversationId, $metadata);
                
                try {
                    ConversationService::sendMessage(
                        $conversationId,
                        "Desculpe, o tempo de espera expirou. Um atendente entrará em contato em breve.",
                        'agent',
                        null
                    );
                    Logger::automation("  ✅ Mensagem de timeout enviada para conversa {$conversationId}");
                } catch (\Throwable $e) {
                    Logger::automation("  ❌ Erro ao enviar mensagem de timeout: " . $e->getMessage());
                }
                break;
                
            case 'close':
                Logger::automation("  🔒 Encerrando conversa {$conversationId}...");
                self::clearChatbotState($conversationId, $metadata);
                
                try {
                    Conversation::update($conversationId, [
                        'status' => 'closed',
                        'closed_at' => date('Y-m-d H:i:s')
                    ]);
                    Logger::automation("  ✅ Conversa {$conversationId} encerrada por timeout");
                } catch (\Throwable $e) {
                    Logger::automation("  ❌ Erro ao encerrar conversa: " . $e->getMessage());
                }
                break;
                
            case 'nothing':
            default:
                Logger::automation("  ⚪ Ação '{$timeoutAction}': limpando estado do chatbot para conversa {$conversationId}");
                self::clearChatbotState($conversationId, $metadata);
                break;
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
        $metadata['chatbot_reconnect_current'] = 0;
        $metadata['chatbot_reconnect_attempts'] = [];
        $metadata['chatbot_inactivity_mode'] = null;
        
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
            if ((string)($node['id'] ?? '') === (string)$nodeId) {
                return $node;
            }
        }
        return null;
    }
}
