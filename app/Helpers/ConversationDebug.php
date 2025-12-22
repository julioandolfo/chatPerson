<?php

namespace App\Helpers;

/**
 * Helper para debug detalhado de conversas específicas
 * Ativa logs detalhados apenas para a conversa monitorada
 */
class ConversationDebug
{
    private static ?int $monitoredConversationId = null;
    private static bool $initialized = false;
    private static string $logFile = '';

    /**
     * Inicializar (carrega ID da conversa monitorada do arquivo)
     */
    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        
        self::$logFile = dirname(__DIR__, 2) . '/logs/conversation-debug.log';
        $idFile = dirname(__DIR__, 2) . '/logs/debug-conversation-id.txt';
        
        if (file_exists($idFile)) {
            self::$monitoredConversationId = (int)trim(file_get_contents($idFile));
        }
        
        self::$initialized = true;
    }

    /**
     * Verificar se a conversa está sendo monitorada
     */
    public static function isMonitored(int $conversationId): bool
    {
        self::init();
        return self::$monitoredConversationId !== null && self::$monitoredConversationId === $conversationId;
    }

    /**
     * Obter ID da conversa monitorada
     */
    public static function getMonitoredId(): ?int
    {
        self::init();
        return self::$monitoredConversationId;
    }

    /**
     * Log principal - só loga se a conversa for monitorada
     */
    public static function log(int $conversationId, string $type, string $message, $data = null): void
    {
        if (!self::isMonitored($conversationId)) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$type}] [Conv:{$conversationId}] {$message}";
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $logEntry .= "\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                $logEntry .= " | Data: {$data}";
            }
        }
        
        $logEntry .= "\n";
        
        // Criar diretório se não existir
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }

    // ============================================================
    // MÉTODOS DE CONVENIÊNCIA PARA TIPOS ESPECÍFICOS
    // ============================================================

    /**
     * Log de mensagem recebida
     */
    public static function messageReceived(int $conversationId, string $content, string $senderType, array $extra = []): void
    {
        self::log($conversationId, 'MSG_RECV', "Mensagem recebida de {$senderType}: " . substr($content, 0, 100), $extra);
    }

    /**
     * Log de requisição à OpenAI
     */
    public static function openAIRequest(int $conversationId, string $action, array $payload): void
    {
        // Truncar mensagens longas para não poluir o log
        $truncatedPayload = self::truncatePayload($payload);
        self::log($conversationId, 'OPENAI_REQ', "OpenAI {$action}", $truncatedPayload);
    }

    /**
     * Log de resposta da OpenAI
     */
    public static function openAIResponse(int $conversationId, string $action, $response, int $tokensUsed = 0): void
    {
        $message = "OpenAI {$action} Response";
        if ($tokensUsed > 0) {
            $message .= " (tokens: {$tokensUsed})";
        }
        
        $data = $response;
        if (is_array($response)) {
            $data = self::truncatePayload($response);
        }
        
        self::log($conversationId, 'OPENAI_RES', $message, $data);
    }

    /**
     * Log de chamada de tool
     */
    public static function toolCall(int $conversationId, string $toolName, array $arguments): void
    {
        self::log($conversationId, 'TOOL_CALL', "Chamando tool: {$toolName}", $arguments);
    }

    /**
     * Log de resposta de tool
     */
    public static function toolResponse(int $conversationId, string $toolName, $response, bool $success = true): void
    {
        $status = $success ? 'sucesso' : 'FALHA';
        self::log($conversationId, 'TOOL_RES', "Tool {$toolName} ({$status})", $response);
    }

    /**
     * Log de ação do agente de IA
     */
    public static function aiAgent(int $conversationId, string $action, $data = null): void
    {
        self::log($conversationId, 'AI_AGENT', $action, $data);
    }

    /**
     * Log de mensagem enviada
     */
    public static function messageSent(int $conversationId, string $content, string $channel = 'internal'): void
    {
        self::log($conversationId, 'SEND_MSG', "Enviando via {$channel}: " . substr($content, 0, 100));
    }

    /**
     * Log de erro
     */
    public static function error(int $conversationId, string $context, string $error, $extra = null): void
    {
        self::log($conversationId, 'ERROR', "[{$context}] {$error}", $extra);
    }

    /**
     * Log de intent detection
     */
    public static function intentDetection(int $conversationId, string $phase, $data = null): void
    {
        self::log($conversationId, 'INTENT', $phase, $data);
    }

    /**
     * Log de automação
     */
    public static function automation(int $conversationId, string $action, $data = null): void
    {
        self::log($conversationId, 'AUTOMATION', $action, $data);
    }

    /**
     * Log genérico de info
     */
    public static function info(int $conversationId, string $message, $data = null): void
    {
        self::log($conversationId, 'INFO', $message, $data);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Truncar payloads grandes para o log não ficar enorme
     */
    private static function truncatePayload(array $payload, int $maxLength = 500): array
    {
        $result = [];
        
        foreach ($payload as $key => $value) {
            if (is_string($value) && strlen($value) > $maxLength) {
                $result[$key] = substr($value, 0, $maxLength) . '... [TRUNCATED]';
            } elseif (is_array($value)) {
                // Para arrays de mensagens, mostrar apenas resumo
                if ($key === 'messages' && count($value) > 3) {
                    $result[$key] = [
                        '_count' => count($value),
                        '_first' => isset($value[0]) ? self::truncatePayload($value[0], 200) : null,
                        '_last' => isset($value[count($value)-1]) ? self::truncatePayload($value[count($value)-1], 200) : null,
                    ];
                } else {
                    $result[$key] = self::truncatePayload($value, $maxLength);
                }
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
}

