<?php
/**
 * Service SentimentAnalysisService
 * Análise de sentimento usando OpenAI
 */

namespace App\Services;

use App\Models\ConversationSentiment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Tag;
use App\Helpers\Database;
use App\Helpers\Logger;

class SentimentAnalysisService
{
    const API_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * Obter configurações de análise de sentimento
     */
    private static function getSettings(): array
    {
        $cs = ConversationSettingsService::getSettings();
        return $cs['sentiment_analysis'] ?? self::getDefaultSettings();
    }

    /**
     * Configurações padrão
     */
    private static function getDefaultSettings(): array
    {
        return [
            'enabled' => false,
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.3,
            'check_interval_hours' => 5,
            'max_conversation_age_days' => 30,
            'analyze_on_new_message' => true,
            'analyze_on_message_count' => 5,
            'min_messages_to_analyze' => 3,
            'analyze_last_messages' => null, // null = toda conversa, número = últimas X mensagens
            'include_emotions' => true,
            'include_urgency' => true,
            'auto_tag_negative' => false,
            'negative_tag_id' => null,
            'cost_limit_per_day' => 5.00,
        ];
    }

    /**
     * Obter API Key da OpenAI
     */
    private static function getApiKey(): ?string
    {
        $apiKey = Setting::get('openai_api_key');
        if (empty($apiKey)) {
            $apiKey = getenv('OPENAI_API_KEY') ?: null;
        }
        return $apiKey;
    }

    /**
     * Verificar limite de custo diário
     */
    private static function checkDailyCostLimit(): bool
    {
        $settings = self::getSettings();
        $limit = (float)($settings['cost_limit_per_day'] ?? 0);
        
        if ($limit <= 0) {
            return true; // Sem limite
        }

        $sql = "SELECT SUM(cost) as total_cost 
                FROM conversation_sentiments 
                WHERE DATE(analyzed_at) = CURDATE()";
        $result = Database::fetch($sql);
        $todayCost = (float)($result['total_cost'] ?? 0);

        return $todayCost < $limit;
    }

    /**
     * Analisar sentimento de uma conversa
     */
    public static function analyzeConversation(int $conversationId, ?int $messageId = null): ?array
    {
        $settings = self::getSettings();
        
        if (!$settings['enabled']) {
            Logger::log("SentimentAnalysisService::analyzeConversation - Análise desabilitada");
            return null;
        }

        // Verificar limite de custo
        if (!self::checkDailyCostLimit()) {
            Logger::log("SentimentAnalysisService::analyzeConversation - Limite de custo diário atingido");
            return null;
        }

        // Verificar se já foi analisada recentemente (cache de 1 hora)
        if (ConversationSentiment::wasAnalyzedRecently($conversationId, 60)) {
            Logger::log("SentimentAnalysisService::analyzeConversation - Conversa {$conversationId} já analisada recentemente");
            return ConversationSentiment::getCurrent($conversationId);
        }

        try {
            // Obter conversa
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                throw new \Exception("Conversa não encontrada: {$conversationId}");
            }

            // Verificar idade máxima
            $maxAge = (int)($settings['max_conversation_age_days'] ?? 30);
            $createdAt = new \DateTime($conversation['created_at']);
            $now = new \DateTime();
            $ageDays = $now->diff($createdAt)->days;

            if ($ageDays > $maxAge) {
                Logger::log("SentimentAnalysisService::analyzeConversation - Conversa muito antiga ({$ageDays} dias)");
                return null;
            }

            // Obter mensagens para análise
            $messages = self::getMessagesForAnalysis($conversationId, $settings);
            
            if (empty($messages)) {
                Logger::log("SentimentAnalysisService::analyzeConversation - Nenhuma mensagem para analisar");
                return null;
            }

            // Contar apenas mensagens do cliente para validação
            $clientMessages = array_filter($messages, function($msg) {
                return ($msg['sender_type'] ?? '') === 'contact';
            });
            $clientMessageCount = count($clientMessages);
            
            $minMessages = (int)($settings['min_messages_to_analyze'] ?? 3);
            if ($clientMessageCount < $minMessages) {
                Logger::log("SentimentAnalysisService::analyzeConversation - Mensagens do cliente insuficientes ({$clientMessageCount} < {$minMessages})");
                return null;
            }

            // Obter API Key
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                throw new \Exception('API Key da OpenAI não configurada');
            }

            // Construir prompt
            $prompt = self::buildSentimentPrompt($messages, $settings);

            // Fazer requisição à OpenAI
            $response = self::makeOpenAIRequest($apiKey, $prompt, $settings);

            // Processar resposta
            $analysis = self::parseOpenAIResponse($response, $conversationId, $messageId, count($messages), $settings['model']);

            // Salvar análise
            $sentiment = ConversationSentiment::create($analysis);

            // Adicionar tag automática se negativo
            if ($settings['auto_tag_negative'] && $analysis['sentiment_label'] === 'negative' && !empty($settings['negative_tag_id'])) {
                self::addNegativeTag($conversationId, (int)$settings['negative_tag_id']);
            }

            Logger::log("SentimentAnalysisService::analyzeConversation - ✅ Análise concluída para conversa {$conversationId}: {$analysis['sentiment_label']} (score: {$analysis['sentiment_score']})");

            return $sentiment;

        } catch (\Exception $e) {
            Logger::error("SentimentAnalysisService::analyzeConversation - Erro: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obter mensagens para análise
     */
    private static function getMessagesForAnalysis(int $conversationId, array $settings): array
    {
        $analyzeLast = $settings['analyze_last_messages'] ?? null;

        if ($analyzeLast && is_numeric($analyzeLast)) {
            // Analisar apenas últimas X mensagens (de TODAS as partes para ter contexto)
            $sql = "SELECT * FROM messages 
                    WHERE conversation_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            $messages = Database::fetchAll($sql, [$conversationId, (int)$analyzeLast]);
            return array_reverse($messages); // Reverter para ordem cronológica
        } else {
            // Analisar toda a conversa (incluindo mensagens do agente para contexto)
            $sql = "SELECT * FROM messages 
                    WHERE conversation_id = ? 
                    ORDER BY created_at ASC";
            return Database::fetchAll($sql, [$conversationId]);
        }
    }

    /**
     * Construir prompt para análise de sentimento
     */
    private static function buildSentimentPrompt(array $messages, array $settings): string
    {
        $history = self::formatMessagesForAnalysis($messages);
        
        $prompt = "Analise o sentimento e emoções do CLIENTE na seguinte conversa de atendimento.\n\n";
        $prompt .= "IMPORTANTE: Analise o sentimento do CLIENTE (não do agente), mas use o contexto completo da conversa para entender melhor:\n";
        $prompt .= "- Como o cliente está se sentindo ao longo da conversa\n";
        $prompt .= "- Se o atendimento melhorou ou piorou o sentimento\n";
        $prompt .= "- O estado emocional final do cliente\n\n";
        $prompt .= "Histórico da conversa:\n{$history}\n\n";
        $prompt .= "Retorne APENAS um JSON válido com a seguinte estrutura:\n";
        $prompt .= "{\n";
        $prompt .= "  \"sentiment_score\": -1.0 a 1.0 (decimal, onde -1.0 é muito negativo e 1.0 é muito positivo),\n";
        $prompt .= "  \"sentiment_label\": \"positive\" | \"neutral\" | \"negative\",\n";
        
        if ($settings['include_emotions'] ?? true) {
            $prompt .= "  \"emotions\": {\n";
            $prompt .= "    \"frustration\": 0.0 a 1.0,\n";
            $prompt .= "    \"satisfaction\": 0.0 a 1.0,\n";
            $prompt .= "    \"anxiety\": 0.0 a 1.0,\n";
            $prompt .= "    \"anger\": 0.0 a 1.0,\n";
            $prompt .= "    \"happiness\": 0.0 a 1.0,\n";
            $prompt .= "    \"confusion\": 0.0 a 1.0\n";
            $prompt .= "  },\n";
        }
        
        if ($settings['include_urgency'] ?? true) {
            $prompt .= "  \"urgency_level\": \"low\" | \"medium\" | \"high\" | \"critical\",\n";
        }
        
        $prompt .= "  \"confidence\": 0.0 a 1.0 (confiança na análise),\n";
        $prompt .= "  \"analysis_text\": \"Breve explicação do sentimento detectado em português\"\n";
        $prompt .= "}\n\n";
        $prompt .= "IMPORTANTE: Retorne APENAS o JSON válido, sem markdown, sem explicações adicionais, sem ```json```.";

        return $prompt;
    }

    /**
     * Formatar mensagens para análise
     */
    private static function formatMessagesForAnalysis(array $messages): string
    {
        $formatted = [];
        foreach ($messages as $msg) {
            $content = $msg['content'] ?? '';
            $date = date('d/m/Y H:i', strtotime($msg['created_at']));
            
            // Identificar quem enviou a mensagem
            $sender = ($msg['sender_type'] === 'contact') ? 'Cliente' : 'Agente';
            
            $formatted[] = "[{$date}] {$sender}: {$content}";
        }
        return implode("\n", $formatted);
    }

    /**
     * Fazer requisição à API OpenAI
     */
    private static function makeOpenAIRequest(string $apiKey, string $prompt, array $settings): array
    {
        $model = $settings['model'] ?? 'gpt-3.5-turbo';
        $temperature = (float)($settings['temperature'] ?? 0.3);

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Você é um especialista em análise de sentimento e emoções em conversas de atendimento ao cliente. Analise o sentimento expresso e retorne APENAS JSON válido.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $temperature,
            'max_tokens' => 500,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Erro de conexão com OpenAI: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'Erro desconhecido da API OpenAI';
            throw new \Exception('Erro da API OpenAI (' . $httpCode . '): ' . $errorMessage);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta inválida da API OpenAI');
        }

        return $data;
    }

    /**
     * Processar resposta da OpenAI
     */
    private static function parseOpenAIResponse(array $response, int $conversationId, ?int $messageId, int $messagesCount, string $model): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];
        
        // Tentar parsear JSON
        $analysisData = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Tentar extrair JSON do texto
            if (preg_match('/\{[^}]+\}/s', $content, $matches)) {
                $analysisData = json_decode($matches[0], true);
            }
        }

        if (!$analysisData || json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta da OpenAI não contém JSON válido: ' . substr($content, 0, 200));
        }

        // Validar e normalizar dados
        $sentimentScore = (float)($analysisData['sentiment_score'] ?? 0);
        $sentimentScore = max(-1.0, min(1.0, $sentimentScore)); // Clamp entre -1 e 1

        $sentimentLabel = $analysisData['sentiment_label'] ?? 'neutral';
        if (!in_array($sentimentLabel, ['positive', 'neutral', 'negative'])) {
            // Determinar label baseado no score
            if ($sentimentScore > 0.2) {
                $sentimentLabel = 'positive';
            } elseif ($sentimentScore < -0.2) {
                $sentimentLabel = 'negative';
            } else {
                $sentimentLabel = 'neutral';
            }
        }

        $confidence = (float)($analysisData['confidence'] ?? 0.5);
        $confidence = max(0.0, min(1.0, $confidence));

        $tokensUsed = (int)($usage['total_tokens'] ?? 0);
        $cost = self::calculateCost($model, $tokensUsed);

        return [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'sentiment_score' => $sentimentScore,
            'sentiment_label' => $sentimentLabel,
            'emotions' => !empty($analysisData['emotions']) ? json_encode($analysisData['emotions']) : null,
            'urgency_level' => $analysisData['urgency_level'] ?? null,
            'confidence' => $confidence,
            'analysis_text' => $analysisData['analysis_text'] ?? null,
            'messages_analyzed' => $messagesCount,
            'tokens_used' => $tokensUsed,
            'cost' => $cost,
            'model_used' => $model,
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Calcular custo baseado no modelo e tokens
     */
    private static function calculateCost(string $model, int $tokens): float
    {
        // Preços por 1K tokens (aproximados)
        $prices = [
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        ];

        $modelPrices = $prices[$model] ?? $prices['gpt-3.5-turbo'];
        
        // Assumir 70% input, 30% output
        $inputTokens = (int)($tokens * 0.7);
        $outputTokens = (int)($tokens * 0.3);

        $cost = ($inputTokens / 1000 * $modelPrices['input']) + ($outputTokens / 1000 * $modelPrices['output']);
        
        return round($cost, 6);
    }

    /**
     * Adicionar tag negativa automaticamente
     */
    private static function addNegativeTag(int $conversationId, int $tagId): void
    {
        try {
            \App\Services\TagService::addToConversation($conversationId, $tagId);
            Logger::log("SentimentAnalysisService::addNegativeTag - Tag {$tagId} adicionada à conversa {$conversationId}");
        } catch (\Exception $e) {
            Logger::error("SentimentAnalysisService::addNegativeTag - Erro: " . $e->getMessage());
        }
    }

    /**
     * Processar conversas pendentes (para cron job)
     */
    public static function processPendingConversations(): array
    {
        $settings = self::getSettings();
        
        if (!$settings['enabled']) {
            return ['processed' => 0, 'errors' => 0, 'cost' => 0];
        }

        $intervalHours = (int)($settings['check_interval_hours'] ?? 5);
        $maxAgeDays = (int)($settings['max_conversation_age_days'] ?? 30);

        // Buscar conversas abertas que precisam ser analisadas
        $sql = "SELECT DISTINCT c.id, c.updated_at 
                FROM conversations c
                LEFT JOIN conversation_sentiments cs ON c.id = cs.conversation_id 
                    AND cs.analyzed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                WHERE c.status = 'open'
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND cs.id IS NULL
                AND (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact') >= ?
                ORDER BY c.updated_at DESC
                LIMIT 50";
        
        $minMessages = (int)($settings['min_messages_to_analyze'] ?? 3);
        $conversations = Database::fetchAll($sql, [$intervalHours, $maxAgeDays, $minMessages]);

        $processed = 0;
        $errors = 0;
        $totalCost = 0.0;

        foreach ($conversations as $conv) {
            // Verificar limite de custo antes de cada análise
            if (!self::checkDailyCostLimit()) {
                Logger::log("SentimentAnalysisService::processPendingConversations - Limite de custo atingido");
                break;
            }

            try {
                $result = self::analyzeConversation($conv['id']);
                if ($result) {
                    $processed++;
                    $totalCost += (float)($result['cost'] ?? 0);
                }
            } catch (\Exception $e) {
                $errors++;
                Logger::error("SentimentAnalysisService::processPendingConversations - Erro na conversa {$conv['id']}: " . $e->getMessage());
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
            'cost' => $totalCost
        ];
    }

    /**
     * Obter sentimento atual de uma conversa
     */
    public static function getCurrentSentiment(int $conversationId): ?array
    {
        return ConversationSentiment::getCurrent($conversationId);
    }

    /**
     * Obter histórico de sentimentos
     */
    public static function getSentimentHistory(int $conversationId, int $limit = 50): array
    {
        return ConversationSentiment::getHistory($conversationId, $limit);
    }

    /**
     * Obter sentimento médio de um contato
     */
    public static function getContactAverageSentiment(int $contactId): ?float
    {
        return ConversationSentiment::getContactAverage($contactId);
    }
}

