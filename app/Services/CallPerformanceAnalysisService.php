<?php
/**
 * Service CallPerformanceAnalysisService
 * Análise de performance de chamadas telefônicas usando GPT-4
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\Api4ComCall;
use App\Models\Api4ComCallAnalysis;

class CallPerformanceAnalysisService
{
    private static string $openaiEndpoint = 'https://api.openai.com/v1/chat/completions';
    private static string $model = 'gpt-4-turbo';
    
    // Custo aproximado GPT-4-turbo: $0.01/1K input, $0.03/1K output
    private static float $costPerInputToken = 0.00001;
    private static float $costPerOutputToken = 0.00003;

    /**
     * Obter API Key do OpenAI
     */
    private static function getApiKey(): ?string
    {
        $setting = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'openai_api_key' LIMIT 1");
        if (!empty($setting['value'])) {
            return $setting['value'];
        }
        return $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: null;
    }

    /**
     * Analisar uma chamada completa (transcrição + análise)
     */
    public static function analyzeCall(int $callId): array
    {
        Logger::api4com("CallPerformanceAnalysisService - Iniciando análise da chamada #{$callId}");
        
        // Buscar dados da chamada
        $call = Api4ComCall::find($callId);
        if (!$call) {
            return ['success' => false, 'error' => 'Chamada não encontrada'];
        }

        if (empty($call['recording_url'])) {
            return ['success' => false, 'error' => 'Chamada não possui gravação'];
        }

        // Verificar se já existe análise
        $existingAnalysis = Api4ComCallAnalysis::findByCallId($callId);
        if ($existingAnalysis && $existingAnalysis['status'] === 'completed') {
            return ['success' => true, 'analysis_id' => $existingAnalysis['id'], 'message' => 'Análise já existe'];
        }

        // Criar ou atualizar registro de análise
        $analysisId = $existingAnalysis['id'] ?? null;
        if (!$analysisId) {
            $analysisId = Api4ComCallAnalysis::create([
                'call_id' => $callId,
                'agent_id' => $call['agent_id'],
                'conversation_id' => $call['conversation_id'],
                'status' => 'transcribing'
            ]);
        } else {
            Api4ComCallAnalysis::update($analysisId, ['status' => 'transcribing']);
        }

        // Etapa 1: Transcrição
        Logger::api4com("CallPerformanceAnalysisService - Transcrevendo áudio...");
        $transcriptionResult = CallTranscriptionService::transcribe($call['recording_url']);
        
        if (!$transcriptionResult['success']) {
            Api4ComCallAnalysis::update($analysisId, [
                'status' => 'failed',
                'error_message' => 'Falha na transcrição: ' . ($transcriptionResult['error'] ?? 'Erro desconhecido')
            ]);
            return ['success' => false, 'error' => 'Falha na transcrição: ' . ($transcriptionResult['error'] ?? '')];
        }

        // Salvar transcrição
        Api4ComCallAnalysis::update($analysisId, [
            'transcription' => $transcriptionResult['transcription'],
            'transcription_language' => $transcriptionResult['language'],
            'transcription_duration' => $transcriptionResult['duration'],
            'transcription_cost' => $transcriptionResult['cost'],
            'status' => 'analyzing'
        ]);

        // Etapa 2: Análise com GPT-4
        Logger::api4com("CallPerformanceAnalysisService - Analisando com GPT-4...");
        $analysisResult = self::analyzeTranscription($transcriptionResult['transcription'], $call);

        if (!$analysisResult['success']) {
            Api4ComCallAnalysis::update($analysisId, [
                'status' => 'failed',
                'error_message' => 'Falha na análise: ' . ($analysisResult['error'] ?? 'Erro desconhecido')
            ]);
            return ['success' => false, 'error' => 'Falha na análise: ' . ($analysisResult['error'] ?? '')];
        }

        // Salvar análise completa
        $updateData = array_merge($analysisResult['analysis'], [
            'status' => 'completed',
            'model_used' => self::$model,
            'analysis_cost' => $analysisResult['cost'],
            'tokens_used' => $analysisResult['tokens_used'],
            'processing_time_ms' => $analysisResult['processing_time_ms']
        ]);

        Api4ComCallAnalysis::update($analysisId, $updateData);

        Logger::api4com("CallPerformanceAnalysisService - Análise concluída: ID #{$analysisId}, Score: {$analysisResult['analysis']['overall_score']}");

        return [
            'success' => true,
            'analysis_id' => $analysisId,
            'overall_score' => $analysisResult['analysis']['overall_score'],
            'call_outcome' => $analysisResult['analysis']['call_outcome']
        ];
    }

    /**
     * Analisar transcrição com GPT-4
     */
    private static function analyzeTranscription(string $transcription, array $call): array
    {
        $startTime = microtime(true);
        
        $apiKey = self::getApiKey();
        if (!$apiKey) {
            return ['success' => false, 'error' => 'API Key do OpenAI não configurada'];
        }

        // Preparar contexto
        $context = self::buildContext($call);
        $prompt = self::buildPrompt($transcription, $context);

        // Chamar GPT-4
        $messages = [
            [
                'role' => 'system',
                'content' => self::getSystemPrompt()
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        $payload = [
            'model' => self::$model,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init(self::$openaiEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 120
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $processingTime = (int)((microtime(true) - $startTime) * 1000);

        if ($error) {
            return ['success' => false, 'error' => 'Erro de conexão: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "HTTP {$httpCode}: " . substr($response, 0, 200)];
        }

        $data = json_decode($response, true);
        if (!isset($data['choices'][0]['message']['content'])) {
            return ['success' => false, 'error' => 'Resposta inválida do GPT-4'];
        }

        // Extrair tokens usados
        $inputTokens = $data['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $data['usage']['completion_tokens'] ?? 0;
        $totalTokens = $inputTokens + $outputTokens;
        $cost = ($inputTokens * self::$costPerInputToken) + ($outputTokens * self::$costPerOutputToken);

        // Parse da análise
        $analysisJson = $data['choices'][0]['message']['content'];
        $analysis = json_decode($analysisJson, true);

        if (!$analysis) {
            return ['success' => false, 'error' => 'Falha ao parsear análise JSON'];
        }

        // Formatar dados para o banco
        $formattedAnalysis = self::formatAnalysis($analysis);

        return [
            'success' => true,
            'analysis' => $formattedAnalysis,
            'tokens_used' => $totalTokens,
            'cost' => round($cost, 6),
            'processing_time_ms' => $processingTime
        ];
    }

    /**
     * Prompt do sistema
     */
    private static function getSystemPrompt(): string
    {
        return <<<PROMPT
Você é um especialista em análise de vendas telefônicas. Sua tarefa é analisar transcrições de chamadas de vendas e avaliar a performance do vendedor.

Avalie em 10 dimensões, dando nota de 0.0 a 5.0 (com 1 casa decimal) para cada:

1. **Abertura** (opening): Como o vendedor se apresentou e abriu a conversa
2. **Tom de Voz** (tone): Cordialidade, energia, entusiasmo (inferido do texto)
3. **Escuta Ativa** (listening): Se o vendedor ouviu e respondeu às necessidades do cliente
4. **Quebra de Objeções** (objection_handling): Habilidade em lidar com objeções e resistências
5. **Proposta de Valor** (value_proposition): Clareza ao apresentar benefícios e valor
6. **Fechamento** (closing): Técnicas de fechamento, pediu a venda, agendou próximo passo
7. **Qualificação** (qualification): Identificou necessidades, orçamento, timing (BANT)
8. **Controle** (control): Conduziu a conversa sem ser agressivo
9. **Profissionalismo** (professionalism): Linguagem adequada, sem erros, postura profissional
10. **Empatia** (empathy): Rapport, conexão emocional, compreensão

Também identifique:
- Resultado da ligação (positive, negative, neutral, followup_needed)
- Tipo de ligação (sales, support, followup, prospecting, other)
- Sentimento do cliente (very_positive, positive, neutral, negative, very_negative)
- Pontos fortes do vendedor (array de strings)
- Pontos fracos do vendedor (array de strings)
- Sugestões de melhoria (array de strings)
- Momentos-chave da conversa (array de objetos com timestamp e descrição)
- Objeções do cliente (array de strings)
- Interesses demonstrados pelo cliente (array de strings)
- Resumo executivo da ligação (1-2 parágrafos)
- Análise detalhada (2-3 parágrafos)

Responda APENAS em JSON válido com a estrutura especificada.
PROMPT;
    }

    /**
     * Construir contexto da chamada
     */
    private static function buildContext(array $call): array
    {
        return [
            'duration_seconds' => $call['duration'] ?? 0,
            'direction' => $call['direction'] ?? 'outbound',
            'to_number' => $call['to_number'] ?? '',
            'from_number' => $call['from_number'] ?? '',
            'status' => $call['status'] ?? 'ended'
        ];
    }

    /**
     * Construir prompt para análise
     */
    private static function buildPrompt(string $transcription, array $context): string
    {
        $duration = gmdate("i:s", $context['duration_seconds']);
        $direction = $context['direction'] === 'outbound' ? 'realizada pelo vendedor' : 'recebida';
        
        return <<<PROMPT
Analise esta transcrição de uma ligação de vendas:

**Contexto:**
- Duração: {$duration}
- Tipo: Ligação {$direction}
- Número destino: {$context['to_number']}

**Transcrição:**
{$transcription}

**Responda em JSON com a seguinte estrutura:**
```json
{
  "scores": {
    "opening": 0.0,
    "tone": 0.0,
    "listening": 0.0,
    "objection_handling": 0.0,
    "value_proposition": 0.0,
    "closing": 0.0,
    "qualification": 0.0,
    "control": 0.0,
    "professionalism": 0.0,
    "empathy": 0.0
  },
  "overall_score": 0.0,
  "call_outcome": "positive|negative|neutral|followup_needed",
  "call_type": "sales|support|followup|prospecting|other",
  "client_sentiment": "very_positive|positive|neutral|negative|very_negative",
  "summary": "Resumo executivo...",
  "detailed_analysis": "Análise detalhada...",
  "strengths": ["ponto forte 1", "ponto forte 2"],
  "weaknesses": ["ponto fraco 1", "ponto fraco 2"],
  "suggestions": ["sugestão 1", "sugestão 2"],
  "key_moments": [{"time": "00:30", "description": "momento importante"}],
  "client_objections": ["objeção 1"],
  "client_interests": ["interesse 1"]
}
```
PROMPT;
    }

    /**
     * Formatar análise para o banco de dados
     */
    private static function formatAnalysis(array $analysis): array
    {
        $scores = $analysis['scores'] ?? [];
        
        // Calcular score geral se não fornecido
        $overallScore = $analysis['overall_score'] ?? null;
        if (!$overallScore && !empty($scores)) {
            $overallScore = array_sum($scores) / count($scores);
        }

        return [
            'summary' => $analysis['summary'] ?? null,
            'call_outcome' => $analysis['call_outcome'] ?? 'neutral',
            'call_type' => $analysis['call_type'] ?? 'sales',
            
            'score_opening' => round((float)($scores['opening'] ?? 0), 1),
            'score_tone' => round((float)($scores['tone'] ?? 0), 1),
            'score_listening' => round((float)($scores['listening'] ?? 0), 1),
            'score_objection_handling' => round((float)($scores['objection_handling'] ?? 0), 1),
            'score_value_proposition' => round((float)($scores['value_proposition'] ?? 0), 1),
            'score_closing' => round((float)($scores['closing'] ?? 0), 1),
            'score_qualification' => round((float)($scores['qualification'] ?? 0), 1),
            'score_control' => round((float)($scores['control'] ?? 0), 1),
            'score_professionalism' => round((float)($scores['professionalism'] ?? 0), 1),
            'score_empathy' => round((float)($scores['empathy'] ?? 0), 1),
            'overall_score' => round((float)$overallScore, 1),
            
            'strengths' => json_encode($analysis['strengths'] ?? []),
            'weaknesses' => json_encode($analysis['weaknesses'] ?? []),
            'suggestions' => json_encode($analysis['suggestions'] ?? []),
            'key_moments' => json_encode($analysis['key_moments'] ?? []),
            'detailed_analysis' => $analysis['detailed_analysis'] ?? null,
            
            'client_sentiment' => $analysis['client_sentiment'] ?? 'neutral',
            'client_objections' => json_encode($analysis['client_objections'] ?? []),
            'client_interests' => json_encode($analysis['client_interests'] ?? [])
        ];
    }

    /**
     * Processar chamadas pendentes (para cron)
     */
    public static function processPendingCalls(int $limit = 5): array
    {
        Logger::api4com("CallPerformanceAnalysisService - Buscando chamadas pendentes...");
        
        $pendingCalls = Api4ComCallAnalysis::getPendingCalls($limit);
        
        if (empty($pendingCalls)) {
            Logger::api4com("CallPerformanceAnalysisService - Nenhuma chamada pendente");
            return ['processed' => 0, 'success' => 0, 'failed' => 0];
        }

        Logger::api4com("CallPerformanceAnalysisService - " . count($pendingCalls) . " chamadas pendentes");

        $results = ['processed' => 0, 'success' => 0, 'failed' => 0];

        foreach ($pendingCalls as $call) {
            $result = self::analyzeCall($call['id']);
            $results['processed']++;
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                Logger::api4com("CallPerformanceAnalysisService - Falha na chamada #{$call['id']}: " . ($result['error'] ?? ''), 'ERROR');
            }

            // Aguardar entre análises para não sobrecarregar API
            sleep(2);
        }

        Logger::api4com("CallPerformanceAnalysisService - Processamento concluído: " . json_encode($results));

        return $results;
    }

    /**
     * Verificar se análise está habilitada
     */
    public static function isEnabled(): bool
    {
        $setting = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'call_analysis_enabled' LIMIT 1");
        return ($setting['value'] ?? '1') === '1';
    }
}
