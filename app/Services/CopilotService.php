<?php
/**
 * CopilotService — Copiloto de Atendimento (RAG sobre conversas resolvidas).
 *
 * Indexa (resumo + embedding) cada conversa resolvida/fechada e responde
 * perguntas do tipo "cliente relatou o problema X, como resolvo?" buscando os
 * casos passados mais parecidos por similaridade semântica.
 *
 * Mantém tudo no banco principal (MySQL): embeddings em JSON + cosseno em PHP.
 * Privacidade: o texto é anonimizado (reusa ManualGeneratorService::anonymize)
 * ANTES de ir à OpenAI.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\Setting;

class CopilotService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /** Status de conversa considerados "encerrados" (ignora apenas as abertas). */
    private const CLOSED_STATUSES = ['resolved', 'closed'];

    /** Teto de candidatos pontuados por pergunta (limita custo/tempo da busca). */
    private const CANDIDATE_CAP = 8000;

    private static bool $tableEnsured = false;

    // ----------------------------------------------------------------------
    // BOOTSTRAP
    // ----------------------------------------------------------------------

    public static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }
        Database::getInstance()->exec("CREATE TABLE IF NOT EXISTS copilot_conversation_index (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            agent_id INT NULL,
            category VARCHAR(80) NULL,
            problem TEXT NULL,
            resolution TEXT NULL,
            summary TEXT NULL,
            embedding MEDIUMTEXT NULL,
            tokens INT DEFAULT 0,
            cost DECIMAL(12,6) DEFAULT 0,
            resolved_at DATETIME NULL,
            indexed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_conversation (conversation_id),
            INDEX idx_category (category),
            INDEX idx_resolved_at (resolved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$tableEnsured = true;
    }

    // ----------------------------------------------------------------------
    // INDEXAÇÃO (retroalimentação incremental)
    // ----------------------------------------------------------------------

    /** Estatísticas simples para a UI. */
    public static function stats(): array
    {
        self::ensureTable();
        $indexed = (int)(Database::fetch("SELECT COUNT(*) AS n FROM copilot_conversation_index")['n'] ?? 0);
        $statusList = "'" . implode("','", self::CLOSED_STATUSES) . "'";
        $pending = (int)(Database::fetch(
            "SELECT COUNT(*) AS n FROM conversations c
             LEFT JOIN copilot_conversation_index k ON k.conversation_id = c.id
             WHERE c.status IN ($statusList) AND k.id IS NULL"
        )['n'] ?? 0);
        return ['indexed' => $indexed, 'pending' => $pending];
    }

    /** Categorias disponíveis no índice (para filtro), mais frequentes primeiro. */
    public static function categories(int $limit = 40): array
    {
        self::ensureTable();
        $rows = Database::fetchAll(
            "SELECT category, COUNT(*) AS n
             FROM copilot_conversation_index
             WHERE category IS NOT NULL AND category <> ''
             GROUP BY category ORDER BY n DESC
             LIMIT " . max(1, min($limit, 100))
        );
        return array_map(fn($r) => $r['category'], $rows);
    }

    /**
     * Indexar um lote de conversas resolvidas ainda não indexadas.
     * @return int quantidade indexada
     */
    public static function indexPending(int $batch = 50): int
    {
        self::ensureTable();
        $statusList = "'" . implode("','", self::CLOSED_STATUSES) . "'";
        $rows = Database::fetchAll(
            "SELECT c.id
             FROM conversations c
             LEFT JOIN copilot_conversation_index k ON k.conversation_id = c.id
             WHERE c.status IN ($statusList)
               AND k.id IS NULL
               AND (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) >= 3
             ORDER BY c.resolved_at DESC, c.id DESC
             LIMIT " . max(1, min($batch, 500))
        );
        $done = 0;
        foreach ($rows as $r) {
            try {
                if (self::indexConversation((int)$r['id'])) {
                    $done++;
                }
            } catch (\Throwable $e) {
                Logger::error('Copilot indexConversation ' . $r['id'] . ': ' . $e->getMessage());
            }
        }
        return $done;
    }

    /**
     * Indexar (ou reindexar) uma conversa. Best-effort: retorna false se pulada.
     */
    public static function indexConversation(int $conversationId): bool
    {
        self::ensureTable();

        $conv = Database::fetch(
            "SELECT c.id, c.agent_id, c.status, c.resolved_at, ct.name AS contact_name
             FROM conversations c
             LEFT JOIN contacts ct ON ct.id = c.contact_id
             WHERE c.id = ?",
            [$conversationId]
        );
        if (!$conv || !in_array($conv['status'], self::CLOSED_STATUSES, true)) {
            return false;
        }

        // Transcript já anonimizado (reusa o builder do gerador de manuais).
        $transcript = ManualGeneratorService::buildTranscript($conversationId, $conv['contact_name'] ?? null);
        if (trim($transcript) === '') {
            return false;
        }

        $summary = self::summarize($transcript);
        if (!$summary) {
            return false;
        }

        $embeddingText = trim(($summary['category'] ?? '') . '. ' . ($summary['problem'] ?? '') . '. ' . ($summary['summary'] ?? ''));
        $embedding = EmbeddingService::generate($embeddingText);
        if (!$embedding) {
            return false;
        }

        Database::execute(
            "INSERT INTO copilot_conversation_index
                (conversation_id, agent_id, category, problem, resolution, summary, embedding, tokens, cost, resolved_at, indexed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                category=VALUES(category), problem=VALUES(problem), resolution=VALUES(resolution),
                summary=VALUES(summary), embedding=VALUES(embedding), tokens=VALUES(tokens),
                cost=VALUES(cost), resolved_at=VALUES(resolved_at), indexed_at=NOW()",
            [
                $conversationId,
                $conv['agent_id'] ?: null,
                mb_substr((string)($summary['category'] ?? ''), 0, 80),
                $summary['problem'] ?? '',
                $summary['resolution'] ?? '',
                $summary['summary'] ?? '',
                json_encode($embedding),
                $summary['tokens'] ?? 0,
                round($summary['cost'] ?? 0, 6),
                $conv['resolved_at'] ?: null,
            ]
        );

        AIUsageLogger::record('copilot_index', [
            'model' => 'gpt-4o-mini',
            'tokens_used' => $summary['tokens'] ?? 0,
            'cost' => $summary['cost'] ?? 0,
            'conversation_id' => $conversationId,
        ]);

        return true;
    }

    /** Resumir a conversa em {category, problem, resolution, summary}. */
    private static function summarize(string $transcript): ?array
    {
        $system = "Você resume conversas de atendimento para uma base de consulta. Responda APENAS JSON.";
        $user = "Resuma a conversa (anonimizada) em JSON:\n"
              . "{\n"
              . "  \"category\": \"tema curto (ex: troca, reembolso, atraso, rastreio)\",\n"
              . "  \"problem\": \"o problema/pedido do cliente, objetivo\",\n"
              . "  \"resolution\": \"o que o atendente fez para resolver, passo a passo resumido\",\n"
              . "  \"summary\": \"resumo de 2-3 frases do caso\"\n"
              . "}\nNão invente.\n\nCONVERSA:\n" . mb_substr($transcript, 0, 7000);

        $resp = self::callOpenAI('gpt-4o-mini', [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 600, 0.2, true);
        if (!$resp) {
            return null;
        }
        $data = self::parseJson($resp['content']);
        if (!is_array($data)) {
            return null;
        }
        $data['tokens'] = $resp['total_tokens'];
        $data['cost'] = $resp['cost'];
        return $data;
    }

    // ----------------------------------------------------------------------
    // PERGUNTAR (busca semântica + resposta)
    // ----------------------------------------------------------------------

    /**
     * Responder a uma pergunta usando os casos passados mais parecidos.
     *
     * @param array $filters ['category'=>?string, 'date_from'=>?string, 'date_to'=>?string]
     * @return array ['answer'=>string, 'sources'=>array, 'tokens'=>int, 'cost'=>float]
     */
    public static function ask(string $question, int $topK = 6, array $filters = []): array
    {
        self::ensureTable();
        $question = trim($question);
        if ($question === '') {
            return ['answer' => 'Descreva o problema do cliente para eu consultar casos parecidos.', 'sources' => []];
        }

        $qEmb = EmbeddingService::generate($question);
        if (!$qEmb) {
            return ['answer' => 'Não consegui processar a busca agora (embedding indisponível).', 'sources' => []];
        }

        // Filtros opcionais (categoria / período) aplicados no SQL, antes do cosseno.
        $where = ['embedding IS NOT NULL'];
        $params = [];
        if (!empty($filters['category'])) {
            $where[] = 'category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'resolved_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'resolved_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        $whereSql = implode(' AND ', $where);

        // Carregar candidatos e pontuar por similaridade (cosseno) em PHP.
        $rows = Database::fetchAll(
            "SELECT conversation_id, category, problem, resolution, summary, embedding
             FROM copilot_conversation_index
             WHERE {$whereSql}
             ORDER BY resolved_at DESC
             LIMIT " . self::CANDIDATE_CAP,
            $params
        );

        $scored = [];
        foreach ($rows as $r) {
            $emb = json_decode($r['embedding'], true);
            if (!is_array($emb)) {
                continue;
            }
            $score = EmbeddingService::cosineSimilarity($qEmb, $emb);
            $r['score'] = $score;
            unset($r['embedding']);
            $scored[] = $r;
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, max(1, $topK));

        if (empty($top) || ($top[0]['score'] ?? 0) < 0.2) {
            return [
                'answer' => "Não encontrei casos parecidos na base de conversas resolvidas. "
                          . "Tente descrever o problema com outras palavras ou consulte um supervisor.",
                'sources' => [],
            ];
        }

        // Montar contexto com os casos parecidos.
        $ctx = [];
        foreach ($top as $i => $c) {
            $ctx[] = "CASO " . ($i + 1) . " (conversa #{$c['conversation_id']}, tema: {$c['category']}, similaridade: "
                   . number_format($c['score'], 2) . ")\n"
                   . "Problema: {$c['problem']}\n"
                   . "Como foi resolvido: {$c['resolution']}";
        }
        $context = implode("\n\n", $ctx);

        $system = "Você é um copiloto de atendimento que ajuda atendentes (inclusive novatos) a resolver "
                . "casos, baseando-se SOMENTE em como casos parecidos foram resolvidos no passado. "
                . "Seja prático e direto, em português.";
        $user = "Pergunta do atendente:\n\"{$question}\"\n\n"
              . "Casos passados mais parecidos:\n{$context}\n\n"
              . "Com base SOMENTE nesses casos, responda:\n"
              . "1) Um passo a passo recomendado para resolver.\n"
              . "2) Frases/abordagens que funcionaram.\n"
              . "3) Pontos de atenção/exceções.\n"
              . "Cite os números das conversas usadas (ex: #12345). Se os casos não cobrirem bem o problema, diga isso claramente.";

        $resp = self::callOpenAI('gpt-4o-mini', [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 1200, 0.3, false);

        $answer = $resp['content'] ?? 'Não consegui gerar a resposta agora. Tente novamente.';

        if ($resp) {
            AIUsageLogger::record('copilot_answer', [
                'model' => 'gpt-4o-mini',
                'tokens_used' => $resp['total_tokens'],
                'cost' => $resp['cost'],
            ]);
        }

        $sources = array_map(fn($c) => [
            'conversation_id' => (int)$c['conversation_id'],
            'category' => $c['category'],
            'summary' => $c['summary'],
            'score' => round($c['score'], 3),
        ], $top);

        return [
            'answer' => $answer,
            'sources' => $sources,
            'tokens' => $resp['total_tokens'] ?? 0,
            'cost' => $resp['cost'] ?? 0,
        ];
    }

    // ----------------------------------------------------------------------
    // OPENAI
    // ----------------------------------------------------------------------

    private static function callOpenAI(string $model, array $messages, int $maxTokens, float $temperature, bool $jsonMode): ?array
    {
        $apiKey = Setting::get('openai_api_key');
        if (empty($apiKey)) {
            Logger::error('Copilot: openai_api_key não configurada');
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
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200) {
            Logger::error("Copilot OpenAI HTTP {$httpCode} {$err} :: " . substr((string)$response, 0, 200));
            return null;
        }
        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            return null;
        }
        $pt = (int)($data['usage']['prompt_tokens'] ?? 0);
        $ct = (int)($data['usage']['completion_tokens'] ?? 0);
        return [
            'content' => $content,
            'total_tokens' => (int)($data['usage']['total_tokens'] ?? ($pt + $ct)),
            'cost' => AIUsageLogger::estimateChatCost($model, $pt, $ct),
        ];
    }

    private static function parseJson(string $raw): mixed
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?/i', '', $raw);
        $raw = preg_replace('/```$/', '', trim($raw));
        return json_decode(trim($raw), true);
    }
}
