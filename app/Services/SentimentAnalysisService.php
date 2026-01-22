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
            $sentimentId = ConversationSentiment::create($analysis);
            
            // Adicionar ID ao array de análise
            $analysis['id'] = $sentimentId;

            // Adicionar tag automática se negativo
            if ($settings['auto_tag_negative'] && $analysis['sentiment_label'] === 'negative' && !empty($settings['negative_tag_id'])) {
                self::addNegativeTag($conversationId, (int)$settings['negative_tag_id']);
            }

            Logger::log("SentimentAnalysisService::analyzeConversation - ✅ Análise concluída para conversa {$conversationId}: {$analysis['sentiment_label']} (score: {$analysis['sentiment_score']})");

            return $analysis;

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
        $messageCount = count($messages);
        
        $prompt = <<<PROMPT
Você é um especialista em análise de sentimento em conversas de atendimento ao cliente. Sua tarefa é analisar o SENTIMENTO GERAL do CLIENTE ao longo de toda a conversa.

## REGRAS CRÍTICAS DE ANÁLISE

### 1. CONTEXTO É TUDO
- Analise a conversa COMPLETA, do início ao fim
- O sentimento final deve refletir a JORNADA EMOCIONAL do cliente
- Uma conversa que começou mal mas foi resolvida = sentimento POSITIVO ou NEUTRO
- Uma conversa que começou bem mas terminou mal = sentimento NEGATIVO

### 2. NÃO REAJA A MENSAGENS ISOLADAS
- Um emoji triste (😢😔😞) isolado NÃO significa automaticamente sentimento negativo
- Pode ser empatia, humor, ou expressão casual
- Avalie o CONTEXTO: o que foi dito antes e depois?
- O problema foi resolvido? O cliente agradeceu no final?

### 3. SINAIS DE SENTIMENTO POSITIVO
- Cliente agradece ("obrigado", "valeu", "muito obrigado")
- Problema foi resolvido ou encaminhado satisfatoriamente
- Cliente demonstra satisfação com o atendimento
- Uso de emojis positivos no contexto de resolução (👍😊🙏)
- Cliente elogia o agente ou a empresa
- Despedida amigável

### 4. SINAIS DE SENTIMENTO NEGATIVO (CONFIRME COM CONTEXTO)
- Cliente reclama repetidamente sem resolução
- Frustração explícita que NÃO foi resolvida
- Ameaças (cancelar, reclamar, processar)
- Insatisfação com a resolução oferecida
- Cliente abandona a conversa irritado
- Múltiplas mensagens de cobrança sem resposta adequada

### 5. SINAIS DE SENTIMENTO NEUTRO
- Conversa informativa sem carga emocional forte
- Problema resolvido de forma funcional (sem entusiasmo nem frustração)
- Cliente objetivo e direto, sem demonstrar emoções
- Interação transacional simples

### 6. PESO DAS MENSAGENS
- Mensagens FINAIS têm MAIS PESO que as iniciais
- Se o cliente estava frustrado mas terminou satisfeito = POSITIVO
- Se o cliente estava ok mas terminou frustrado = NEGATIVO
- Considere a EVOLUÇÃO do sentimento

### 7. EMOJIS - INTERPRETE COM CUIDADO
- 😢😔😞 podem ser: tristeza real, empatia, brincadeira
- 😊😄👍 geralmente são positivos
- 😡🤬 são claramente negativos
- Sempre avalie o TEXTO que acompanha o emoji

## CONVERSA PARA ANÁLISE ({$messageCount} mensagens)

{$history}

## FORMATO DE RESPOSTA (JSON VÁLIDO)

{
  "sentiment_score": <número decimal de -1.0 a 1.0>,
  "sentiment_label": "<positive|neutral|negative>",
PROMPT;

        if ($settings['include_emotions'] ?? true) {
            $prompt .= <<<PROMPT

  "emotions": {
    "frustration": <0.0 a 1.0>,
    "satisfaction": <0.0 a 1.0>,
    "anxiety": <0.0 a 1.0>,
    "anger": <0.0 a 1.0>,
    "happiness": <0.0 a 1.0>,
    "confusion": <0.0 a 1.0>
  },
PROMPT;
        }

        if ($settings['include_urgency'] ?? true) {
            $prompt .= <<<PROMPT

  "urgency_level": "<low|medium|high|critical>",
PROMPT;
        }

        $prompt .= <<<PROMPT

  "confidence": <0.0 a 1.0>,
  "sentiment_evolution": "<improved|stable|worsened>",
  "resolution_status": "<resolved|partially_resolved|unresolved|unknown>",
  "analysis_text": "<Explicação em português de 1-2 frases do sentimento detectado, mencionando o contexto>"
}

## CRITÉRIOS PARA SCORE

- **-1.0 a -0.6**: Cliente muito insatisfeito, problema não resolvido, frustração clara
- **-0.6 a -0.2**: Cliente insatisfeito, mas sem extremos
- **-0.2 a 0.2**: Neutro, conversa funcional sem emoções fortes
- **0.2 a 0.6**: Cliente satisfeito, problema resolvido
- **0.6 a 1.0**: Cliente muito satisfeito, elogiou o atendimento

IMPORTANTE: Retorne APENAS o JSON válido, sem markdown, sem ```json```, sem explicações fora do JSON.
PROMPT;

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
     * Verificar se o modelo suporta response_format json_object
     */
    private static function supportsJsonResponseFormat(string $model): bool
    {
        // Modelos que suportam response_format: json_object
        $supportedModels = [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4-turbo-preview',
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-1106',
            'gpt-3.5-turbo-0125',
        ];
        
        foreach ($supportedModels as $supported) {
            if (stripos($model, $supported) === 0) {
                return true;
            }
        }
        
        // gpt-4 base (sem sufixo turbo/o) não suporta
        return false;
    }

    /**
     * Fazer requisição à API OpenAI com retry para rate limits
     */
    private static function makeOpenAIRequest(string $apiKey, string $prompt, array $settings): array
    {
        $model = $settings['model'] ?? 'gpt-3.5-turbo';
        $temperature = (float)($settings['temperature'] ?? 0.3);
        
        $maxRetries = 3;
        $retryDelay = 3; // segundos inicial

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Você é um especialista em análise de sentimento e emoções em conversas de atendimento ao cliente. Analise o sentimento expresso e retorne APENAS JSON válido, sem markdown.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $temperature,
            'max_tokens' => 800,
        ];
        
        // Só adicionar response_format se o modelo suportar
        if (self::supportsJsonResponseFormat($model)) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 15
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception('Erro de conexão com OpenAI: ' . $error);
            }

            // Sucesso
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Resposta inválida da API OpenAI');
                }
                return $data;
            }

            // Rate limit - tentar novamente com backoff
            if ($httpCode === 429) {
                if ($attempt < $maxRetries) {
                    Logger::log("SentimentAnalysisService - Rate limit (429), tentativa {$attempt}/{$maxRetries}, aguardando {$retryDelay}s...");
                    sleep($retryDelay);
                    $retryDelay *= 2; // Backoff exponencial
                    continue;
                }
            }

            // Erro definitivo
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'Erro desconhecido da API OpenAI';
            throw new \Exception('Erro da API OpenAI (' . $httpCode . '): ' . $errorMessage);
        }

        throw new \Exception('Falha após ' . $maxRetries . ' tentativas devido a rate limit');
    }

    /**
     * Processar resposta da OpenAI
     */
    private static function parseOpenAIResponse(array $response, int $conversationId, ?int $messageId, int $messagesCount, string $model): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];
        
        // Limpar possíveis caracteres extras
        $content = trim($content);
        
        // Tentar parsear JSON
        $analysisData = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Tentar extrair JSON do texto (incluindo JSONs aninhados)
            if (preg_match('/\{(?:[^{}]|(?:\{[^{}]*\}))*\}/s', $content, $matches)) {
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

        // Construir análise textual enriquecida com contexto
        $analysisText = $analysisData['analysis_text'] ?? '';
        
        // Adicionar informações de evolução e resolução ao texto
        $sentimentEvolution = $analysisData['sentiment_evolution'] ?? null;
        $resolutionStatus = $analysisData['resolution_status'] ?? null;
        
        $evolutionLabels = [
            'improved' => 'Sentimento melhorou ao longo da conversa',
            'stable' => 'Sentimento manteve-se estável',
            'worsened' => 'Sentimento piorou ao longo da conversa'
        ];
        
        $resolutionLabels = [
            'resolved' => 'Problema resolvido',
            'partially_resolved' => 'Parcialmente resolvido',
            'unresolved' => 'Não resolvido',
            'unknown' => 'Status desconhecido'
        ];
        
        // Enriquecer análise com metadados
        $metadata = [];
        if ($sentimentEvolution && isset($evolutionLabels[$sentimentEvolution])) {
            $metadata[] = $evolutionLabels[$sentimentEvolution];
        }
        if ($resolutionStatus && isset($resolutionLabels[$resolutionStatus])) {
            $metadata[] = $resolutionLabels[$resolutionStatus];
        }
        
        if (!empty($metadata)) {
            $analysisText = $analysisText . ' [' . implode(' | ', $metadata) . ']';
        }

        return [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'sentiment_score' => $sentimentScore,
            'sentiment_label' => $sentimentLabel,
            'emotions' => !empty($analysisData['emotions']) ? json_encode($analysisData['emotions']) : null,
            'urgency_level' => $analysisData['urgency_level'] ?? null,
            'confidence' => $confidence,
            'analysis_text' => $analysisText,
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
        // Preços por 1K tokens (aproximados - Janeiro 2025)
        $prices = [
            'gpt-4o' => ['input' => 0.0025, 'output' => 0.01],
            'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
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

