<?php
/**
 * Service FeedbackDetectionService
 * Detecção automática de respostas inadequadas da IA
 */

namespace App\Services;

use App\Models\AIFeedbackLoop;
use App\Models\Message;
use App\Helpers\Logger;

class FeedbackDetectionService
{
    /**
     * Detectar se resposta da IA foi inadequada e registrar feedback
     * 
     * @param int $agentId ID do agente
     * @param int $conversationId ID da conversa
     * @param int $messageId ID da mensagem da IA
     * @param string $userQuestion Pergunta do usuário
     * @param string $aiResponse Resposta da IA
     * @return bool True se feedback foi registrado
     */
    public static function detectAndRegister(int $agentId, int $conversationId, int $messageId, string $userQuestion, string $aiResponse): bool
    {
        try {
            // Verificar se PostgreSQL está disponível
            if (!\App\Helpers\PostgreSQL::isAvailable()) {
                return false;
            }

            // Verificar sinais de resposta inadequada
            if (!self::isResponseInadequate($userQuestion, $aiResponse, $conversationId)) {
                return false;
            }

            // Registrar feedback
            $feedbackId = AIFeedbackLoop::create([
                'ai_agent_id' => $agentId,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'user_question' => $userQuestion,
                'ai_response' => $aiResponse,
                'status' => 'pending'
            ]);

            Logger::info("FeedbackDetectionService::detectAndRegister - Feedback registrado: ID {$feedbackId}, Agente {$agentId}, Conversa {$conversationId}");

            return true;

        } catch (\Exception $e) {
            Logger::error("FeedbackDetectionService::detectAndRegister - Erro: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar se resposta foi inadequada
     */
    private static function isResponseInadequate(string $userQuestion, string $aiResponse, int $conversationId, ?int $messageId = null): bool
    {
        // 1. Verificar palavras-chave de insatisfação na pergunta do usuário
        $userQuestionLower = mb_strtolower($userQuestion);
        $insatisfactionKeywords = [
            'não entendi',
            'não entendi nada',
            'pode explicar',
            'explica melhor',
            'não ficou claro',
            'confuso',
            'não compreendi',
            'não entendi o que',
            'pode repetir',
            'não compreendi',
            'não entendi direito',
            'não ficou claro',
            'não consegui entender',
            'não compreendi nada'
        ];

        foreach ($insatisfactionKeywords as $keyword) {
            if (strpos($userQuestionLower, $keyword) !== false) {
                return true;
            }
        }

        // 2. Verificar se resposta é muito curta ou genérica
        if (self::isResponseTooShortOrGeneric($aiResponse)) {
            return true;
        }

        // 3. Verificar se usuário pediu esclarecimento na próxima mensagem (se messageId fornecido)
        if ($messageId && self::userAskedForClarification($conversationId, $messageId)) {
            return true;
        }

        // 4. Verificar se usuário escalou para humano após resposta
        if (self::userEscalatedToHuman($conversationId, $messageId)) {
            return true;
        }

        return false;
    }

    /**
     * Verificar se usuário pediu esclarecimento na próxima mensagem
     */
    private static function userAskedForClarification(int $conversationId, ?int $afterMessageId): bool
    {
        try {
            // Buscar próxima mensagem do usuário após a resposta da IA
            $sql = "SELECT content FROM messages 
                    WHERE conversation_id = ? 
                    AND sender_type = 'contact' 
                    " . ($afterMessageId ? "AND id > ?" : "") . "
                    ORDER BY id ASC 
                    LIMIT 1";
            
            $params = [$conversationId];
            if ($afterMessageId) {
                $params[] = $afterMessageId;
            }
            
            $nextMessage = \App\Helpers\Database::fetch($sql, $params);
            
            if (!$nextMessage || empty($nextMessage['content'])) {
                return false;
            }

            $content = mb_strtolower($nextMessage['content']);
            $clarificationKeywords = [
                'não entendi',
                'pode explicar',
                'explica melhor',
                'não ficou claro',
                'confuso',
                'não compreendi',
                'pode repetir'
            ];

            foreach ($clarificationKeywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Logger::warning("FeedbackDetectionService::userAskedForClarification - Erro: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar se resposta é muito curta ou genérica
     */
    private static function isResponseTooShortOrGeneric(string $response): bool
    {
        // Resposta muito curta (menos de 20 caracteres)
        if (mb_strlen(trim($response)) < 20) {
            return true;
        }

        // Respostas genéricas comuns
        $genericResponses = [
            'não sei',
            'não tenho certeza',
            'não posso ajudar',
            'não consigo',
            'desculpe, não entendi',
            'não compreendi',
            'pode repetir',
            'não sei responder'
        ];

        $responseLower = mb_strtolower(trim($response));
        foreach ($genericResponses as $generic) {
            if ($responseLower === $generic || strpos($responseLower, $generic) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar se usuário escalou para humano
     */
    private static function userEscalatedToHuman(int $conversationId, ?int $afterMessageId): bool
    {
        try {
            // Verificar se conversa foi atribuída a humano após a mensagem da IA
            $conversation = \App\Models\Conversation::find($conversationId);
            if (!$conversation) {
                return false;
            }

            // Se foi atribuída a um agente humano (não IA) após a resposta
            $agentId = $conversation['agent_id'] ?? null;
            if ($agentId && $agentId > 0) {
                // Verificar se foi atribuída recentemente (últimos 5 minutos)
                $assignedAt = $conversation['assigned_at'] ?? null;
                if ($assignedAt) {
                    $assignedTime = strtotime($assignedAt);
                    $now = time();
                    if (($now - $assignedTime) < 300) { // 5 minutos
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            Logger::warning("FeedbackDetectionService::userEscalatedToHuman - Erro: " . $e->getMessage());
            return false;
        }
    }
}

