<?php
/**
 * ManualGeneratorService
 *
 * Gera manuais de processos (CS/Pós-venda) a partir das conversas reais de um
 * agente, usando estratégia map-reduce:
 *   MAP    → extrai unidades de conhecimento (JSON) de cada conversa (modelo barato)
 *   REDUCE → sintetiza o manual em Markdown + divergências (modelo forte)
 *
 * Privacidade: todo conteúdo é anonimizado (PII) antes de ir à OpenAI.
 * Custo: registrado em ai_usage_logs (feature 'manual_generation') via AIUsageLogger.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\Setting;

class ManualGeneratorService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /** Máx. de caracteres de transcrição enviados por conversa (controle de custo). */
    private const MAX_TRANSCRIPT_CHARS = 6000;

    /** Teto de conversas processadas de forma síncrona pela UI. */
    public const SYNC_LIMIT = 30;

    // ----------------------------------------------------------------------
    // SELEÇÃO / PREVIEW
    // ----------------------------------------------------------------------

    /**
     * Buscar conversas elegíveis (resolvidas, com conteúdo) para virar base do manual.
     */
    public static function selectConversations(?int $agentId, string $dateFrom, string $dateTo, int $limit): array
    {
        $limit = max(1, min($limit, 200));
        $params = [];
        $where = "c.created_at >= ? AND c.created_at <= ?";
        $params[] = $dateFrom;
        $params[] = $dateTo . ' 23:59:59';

        if ($agentId) {
            $where .= " AND c.agent_id = ?";
            $params[] = $agentId;
        }

        $sql = "SELECT c.id, c.contact_id, ct.name AS contact_name,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS msg_count
                FROM conversations c
                LEFT JOIN contacts ct ON ct.id = c.contact_id
                WHERE {$where}
                  AND c.status IN ('resolved','closed')
                HAVING msg_count >= 4
                ORDER BY c.created_at DESC
                LIMIT {$limit}";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Estimativa de volume e custo antes de rodar.
     */
    public static function preview(?int $agentId, string $dateFrom, string $dateTo, int $limit): array
    {
        $convs = self::selectConversations($agentId, $dateFrom, $dateTo, $limit);
        $n = count($convs);

        // Estimativa grosseira de tokens: ~1500 in + 350 out por conversa (MAP) + reduce.
        $mapIn = $n * 1500;
        $mapOut = $n * 350;
        $reduceIn = $n * 220 + 500;
        $reduceOut = 3500;

        $mapCost = AIUsageLogger::estimateChatCost('gpt-4o-mini', $mapIn, $mapOut);
        $reduceCost = AIUsageLogger::estimateChatCost('gpt-4o', $reduceIn, $reduceOut);

        return [
            'conversations' => $n,
            'estimated_tokens' => $mapIn + $mapOut + $reduceIn + $reduceOut,
            'estimated_cost' => round($mapCost + $reduceCost, 4),
            'sync_limit' => self::SYNC_LIMIT,
        ];
    }

    // ----------------------------------------------------------------------
    // JOB
    // ----------------------------------------------------------------------

    public static function createJob(array $data): int
    {
        $sql = "INSERT INTO manual_jobs
                    (title, agent_id, date_from, date_to, conversation_limit,
                     status, model_map, model_reduce, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())";
        return Database::insert($sql, [
            $data['title'],
            $data['agent_id'] ?: null,
            $data['date_from'],
            $data['date_to'],
            (int)($data['conversation_limit'] ?? 30),
            $data['model_map'] ?? 'gpt-4o-mini',
            $data['model_reduce'] ?? 'gpt-4o',
            $data['created_by'] ?? null,
        ]);
    }

    /**
     * Executar o job completo (MAP → REDUCE). Retorna o id do manual gerado.
     */
    public static function runJob(int $jobId): int
    {
        $job = Database::fetch("SELECT * FROM manual_jobs WHERE id = ?", [$jobId]);
        if (!$job) {
            throw new \RuntimeException("Job {$jobId} não encontrado");
        }

        $convs = self::selectConversations(
            $job['agent_id'] ? (int)$job['agent_id'] : null,
            $job['date_from'],
            $job['date_to'],
            (int)$job['conversation_limit']
        );

        Database::execute(
            "UPDATE manual_jobs SET status='mapping', total_conversations=?, processed_conversations=0 WHERE id=?",
            [count($convs), $jobId]
        );

        if (empty($convs)) {
            Database::execute("UPDATE manual_jobs SET status='failed', error_message=? WHERE id=?",
                ['Nenhuma conversa elegível no período/filtro.', $jobId]);
            throw new \RuntimeException('Nenhuma conversa elegível encontrada.');
        }

        // ---------- MAP ----------
        $extracts = [];
        $totalTokens = 0;
        $totalCost = 0.0;
        $processed = 0;

        foreach ($convs as $c) {
            $transcript = self::buildTranscript((int)$c['id'], $c['contact_name'] ?? null);
            if ($transcript === '') { $processed++; continue; }

            $result = self::mapConversation($transcript, $job['model_map']);
            if ($result && !empty($result['extract'])) {
                $extracts[] = $result['extract'];
                Database::insert(
                    "INSERT INTO manual_extracts (job_id, conversation_id, extract_json, tokens, cost, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$jobId, (int)$c['id'], json_encode($result['extract'], JSON_UNESCAPED_UNICODE),
                     $result['tokens'], round($result['cost'], 6)]
                );
                $totalTokens += $result['tokens'];
                $totalCost += $result['cost'];

                AIUsageLogger::record('manual_generation', [
                    'model' => $job['model_map'],
                    'tokens_used' => $result['tokens'],
                    'cost' => $result['cost'],
                    'conversation_id' => (int)$c['id'],
                    'metadata' => ['stage' => 'map', 'job_id' => $jobId],
                ]);
            }

            $processed++;
            Database::execute("UPDATE manual_jobs SET processed_conversations=? WHERE id=?", [$processed, $jobId]);
        }

        if (empty($extracts)) {
            Database::execute("UPDATE manual_jobs SET status='failed', error_message=? WHERE id=?",
                ['Não foi possível extrair conhecimento das conversas.', $jobId]);
            throw new \RuntimeException('MAP não produziu extrações.');
        }

        // ---------- REDUCE ----------
        Database::execute("UPDATE manual_jobs SET status='reducing' WHERE id=?", [$jobId]);

        $synthesis = self::synthesize($extracts, $job['title'], $job['model_reduce']);
        $totalTokens += $synthesis['tokens'];
        $totalCost += $synthesis['cost'];

        AIUsageLogger::record('manual_generation', [
            'model' => $job['model_reduce'],
            'tokens_used' => $synthesis['tokens'],
            'cost' => $synthesis['cost'],
            'metadata' => ['stage' => 'reduce', 'job_id' => $jobId],
        ]);

        $manualId = Database::insert(
            "INSERT INTO generated_manuals (job_id, title, content_markdown, divergences_json, status, version, created_at)
             VALUES (?, ?, ?, ?, 'draft', 1, NOW())",
            [$jobId, $job['title'], $synthesis['manual_markdown'],
             json_encode($synthesis['divergences'] ?? [], JSON_UNESCAPED_UNICODE)]
        );

        Database::execute(
            "UPDATE manual_jobs SET status='done', tokens_used=?, cost=? WHERE id=?",
            [$totalTokens, round($totalCost, 6), $jobId]
        );

        return $manualId;
    }

    // ----------------------------------------------------------------------
    // TRANSCRIÇÃO + ANONIMIZAÇÃO
    // ----------------------------------------------------------------------

    public static function buildTranscript(int $conversationId, ?string $contactName = null): string
    {
        $msgs = Database::fetchAll(
            "SELECT sender_type, content, message_type
             FROM messages
             WHERE conversation_id = ?
             ORDER BY created_at ASC, id ASC",
            [$conversationId]
        );

        $lines = [];
        foreach ($msgs as $m) {
            $content = trim((string)($m['content'] ?? ''));
            if ($content === '') continue;
            $who = ($m['sender_type'] === 'contact') ? 'CLIENTE'
                 : (($m['sender_type'] === 'agent') ? 'ATENDENTE' : strtoupper((string)$m['sender_type']));
            $lines[] = "{$who}: {$content}";
        }

        $transcript = implode("\n", $lines);
        $transcript = self::anonymize($transcript, $contactName);

        if (mb_strlen($transcript) > self::MAX_TRANSCRIPT_CHARS) {
            $transcript = mb_substr($transcript, 0, self::MAX_TRANSCRIPT_CHARS) . "\n[...transcrição truncada...]";
        }
        return $transcript;
    }

    /**
     * Mascarar PII (LGPD): nome do contato, telefone, CPF, CNPJ, e-mail.
     */
    public static function anonymize(string $text, ?string $contactName = null): string
    {
        if ($contactName && mb_strlen(trim($contactName)) >= 3) {
            // Mascarar o nome completo e cada parte do nome
            $parts = preg_split('/\s+/', trim($contactName));
            foreach (array_merge([trim($contactName)], $parts) as $token) {
                $token = trim($token);
                if (mb_strlen($token) >= 3) {
                    $text = preg_replace('/\b' . preg_quote($token, '/') . '\b/iu', '[CLIENTE]', $text);
                }
            }
        }

        // E-mail
        $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[EMAIL]', $text);
        // CNPJ
        $text = preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/', '[CNPJ]', $text);
        // CPF
        $text = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '[CPF]', $text);
        // Telefone (BR, com ou sem DDI/DDD)
        $text = preg_replace('/\b(?:\+?55\s?)?\(?\d{2}\)?[\s.-]?\d{4,5}[\s.-]?\d{4}\b/', '[TELEFONE]', $text);

        return $text;
    }

    // ----------------------------------------------------------------------
    // MAP
    // ----------------------------------------------------------------------

    private static function mapConversation(string $transcript, string $model): ?array
    {
        $system = "Você é um analista de processos de atendimento. Extraia conhecimento ÚTIL "
                . "para um manual de novos funcionários a partir da conversa. Responda APENAS com JSON válido.";

        $user = "A partir desta conversa (já anonimizada), extraia em JSON:\n"
              . "{\n"
              . "  \"situacao\": \"o problema/contexto do cliente\",\n"
              . "  \"gatilho_cliente\": \"o que o cliente pediu/reclamou\",\n"
              . "  \"decisao_agente\": \"que decisão o atendente tomou\",\n"
              . "  \"acao_executada\": \"passos/ação realizada para resolver\",\n"
              . "  \"norma_ou_criterio\": \"regra/política aplicada (explícita ou implícita)\",\n"
              . "  \"excecoes\": \"casos-limite ou exceções observadas\",\n"
              . "  \"tom_linguagem\": \"tom/estilo usado pelo atendente\",\n"
              . "  \"categoria\": \"tema curto, ex: troca, reembolso, garantia, atraso\"\n"
              . "}\n"
              . "Se algum campo não se aplicar, use string vazia. Não invente.\n\n"
              . "CONVERSA:\n" . $transcript;

        $resp = self::callOpenAI($model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 600, 0.2, true);

        if (!$resp) return null;

        $extract = self::parseJson($resp['content']);
        if (!is_array($extract)) return null;

        return [
            'extract' => $extract,
            'tokens' => $resp['total_tokens'],
            'cost' => $resp['cost'],
        ];
    }

    // ----------------------------------------------------------------------
    // REDUCE / SÍNTESE
    // ----------------------------------------------------------------------

    private static function synthesize(array $extracts, string $title, string $model): array
    {
        $compact = json_encode($extracts, JSON_UNESCAPED_UNICODE);
        // Proteção de contexto: limitar tamanho do material consolidado.
        if (mb_strlen($compact) > 45000) {
            $compact = mb_substr($compact, 0, 45000) . ' ...';
        }

        $system = "Você é um especialista em processos de CS/Pós-venda que escreve manuais operacionais "
                . "claros para novos funcionários. Baseie-se SOMENTE nos dados fornecidos. Responda em JSON.";

        $user = "Título do manual: \"{$title}\".\n"
              . "Abaixo está uma lista (JSON) de unidades de conhecimento extraídas de conversas reais.\n"
              . "Produza um JSON com:\n"
              . "{\n"
              . "  \"manual_markdown\": \"manual completo em Markdown com as seções: "
              . "1) Normas e Políticas, 2) Procedimentos por Cenário, 3) Critérios de Decisão, "
              . "4) Ações Passo a Passo, 5) Tom de Voz e Scripts, 6) FAQ e Casos-Limite\",\n"
              . "  \"divergences\": [ {\"cenario\": \"...\", \"variacoes\": [\"abordagem A\", \"abordagem B\"], \"recomendacao\": \"...\"} ]\n"
              . "}\n"
              . "Em 'divergences', liste cenários em que conversas diferentes mostraram tratamentos CONTRADITÓRIOS "
              . "(oportunidades de padronização). Consolide repetições. Não invente regras que não estejam nos dados.\n\n"
              . "DADOS:\n" . $compact;

        $resp = self::callOpenAI($model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 4000, 0.4, true);

        if (!$resp) {
            return ['manual_markdown' => "## Falha ao gerar o manual\nTente novamente.", 'divergences' => [], 'tokens' => 0, 'cost' => 0];
        }

        $parsed = self::parseJson($resp['content']);
        $manual = is_array($parsed) ? ($parsed['manual_markdown'] ?? '') : '';
        $divergences = is_array($parsed) ? ($parsed['divergences'] ?? []) : [];

        // Fallback: se não veio JSON, usar o conteúdo bruto como manual.
        if ($manual === '') {
            $manual = $resp['content'];
        }

        return [
            'manual_markdown' => $manual,
            'divergences' => $divergences,
            'tokens' => $resp['total_tokens'],
            'cost' => $resp['cost'],
        ];
    }

    // ----------------------------------------------------------------------
    // PUBLICAR NO RAG
    // ----------------------------------------------------------------------

    /**
     * Publicar o manual na base de conhecimento (RAG) de um agente de IA.
     */
    public static function publishToRag(int $manualId, int $aiAgentId): int
    {
        $manual = Database::fetch("SELECT * FROM generated_manuals WHERE id = ?", [$manualId]);
        if (!$manual) {
            throw new \RuntimeException("Manual {$manualId} não encontrado");
        }

        $chunks = self::chunkMarkdown((string)$manual['content_markdown']);
        $added = 0;
        foreach ($chunks as $i => $chunk) {
            try {
                RAGService::addKnowledge(
                    $aiAgentId,
                    $chunk,
                    'manual',
                    ['manual_id' => $manualId, 'chunk' => $i],
                    $manual['title'] . ' (parte ' . ($i + 1) . ')'
                );
                $added++;
            } catch (\Throwable $e) {
                Logger::error('ManualGenerator::publishToRag chunk falhou: ' . $e->getMessage());
            }
        }

        Database::execute(
            "UPDATE generated_manuals SET status='published', published_to_rag_agent_id=? WHERE id=?",
            [$aiAgentId, $manualId]
        );

        return $added;
    }

    /** Quebrar o markdown em blocos por seção/tamanho para indexar no RAG. */
    private static function chunkMarkdown(string $markdown, int $maxLen = 1500): array
    {
        $blocks = preg_split('/\n(?=#{1,3}\s)/', $markdown) ?: [$markdown];
        $chunks = [];
        foreach ($blocks as $b) {
            $b = trim($b);
            if ($b === '') continue;
            if (mb_strlen($b) <= $maxLen) {
                $chunks[] = $b;
            } else {
                foreach (str_split($b, $maxLen) as $piece) {
                    $chunks[] = $piece;
                }
            }
        }
        return $chunks;
    }

    // ----------------------------------------------------------------------
    // OPENAI
    // ----------------------------------------------------------------------

    /**
     * Chamada genérica ao Chat Completions, com captura de tokens/custo.
     * @return array|null ['content','prompt_tokens','completion_tokens','total_tokens','cost']
     */
    private static function callOpenAI(string $model, array $messages, int $maxTokens, float $temperature, bool $jsonMode = false): ?array
    {
        $apiKey = Setting::get('openai_api_key');
        if (empty($apiKey)) {
            Logger::error('ManualGenerator: openai_api_key não configurada');
            return null;
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200) {
            Logger::error("ManualGenerator: OpenAI HTTP {$httpCode} {$err} :: " . substr((string)$response, 0, 300));
            return null;
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;
        if ($content === null) return null;

        $promptTokens = (int)($data['usage']['prompt_tokens'] ?? 0);
        $completionTokens = (int)($data['usage']['completion_tokens'] ?? 0);
        $totalTokens = (int)($data['usage']['total_tokens'] ?? ($promptTokens + $completionTokens));

        return [
            'content' => $content,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'cost' => AIUsageLogger::estimateChatCost($model, $promptTokens, $completionTokens),
        ];
    }

    /** Parse robusto de JSON (remove cercas ```json). */
    private static function parseJson(string $raw): mixed
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?/i', '', $raw);
        $raw = preg_replace('/```$/', '', trim($raw));
        $decoded = json_decode(trim($raw), true);
        return $decoded;
    }
}
