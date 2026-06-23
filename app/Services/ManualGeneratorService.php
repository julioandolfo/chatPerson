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
    private const MAX_TRANSCRIPT_CHARS = 8000;

    /** Teto de conversas processadas de forma síncrona pela UI. */
    public const SYNC_LIMIT = 30;

    private static bool $tablesEnsured = false;

    // ----------------------------------------------------------------------
    // BOOTSTRAP
    // ----------------------------------------------------------------------

    /**
     * Cria as tabelas do gerador sob demanda (idempotente). Mantém a tela
     * funcional mesmo que a migration 152 ainda não tenha sido executada.
     */
    public static function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }

        $db = Database::getInstance();

        $db->exec("CREATE TABLE IF NOT EXISTS manual_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            agent_id INT NULL,
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            conversation_limit INT DEFAULT 30,
            status VARCHAR(20) DEFAULT 'pending',
            total_conversations INT DEFAULT 0,
            processed_conversations INT DEFAULT 0,
            model_map VARCHAR(60) DEFAULT 'gpt-4o-mini',
            model_reduce VARCHAR(60) DEFAULT 'gpt-4o',
            tokens_used INT DEFAULT 0,
            cost DECIMAL(12,6) DEFAULT 0,
            error_message TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_agent (agent_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS manual_extracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            conversation_id INT NOT NULL,
            extract_json JSON NULL,
            tokens INT DEFAULT 0,
            cost DECIMAL(12,6) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job (job_id),
            INDEX idx_conversation (conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS generated_manuals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            content_markdown MEDIUMTEXT NULL,
            divergences_json JSON NULL,
            status VARCHAR(20) DEFAULT 'draft',
            version INT DEFAULT 1,
            published_to_rag_agent_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_job (job_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::$tablesEnsured = true;
    }

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

        $sql = "SELECT c.id, c.contact_id, ct.name AS contact_name
                FROM conversations c
                LEFT JOIN contacts ct ON ct.id = c.contact_id
                WHERE {$where}
                  AND c.status IN ('resolved','closed')
                  AND (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) >= 4
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

        // MAP: extração rica por conversa (~2500 in + 900 out) com modelo barato.
        $mapIn = $n * 2500;
        $mapOut = $n * 900;
        $mapCost = AIUsageLogger::estimateChatCost('gpt-4o-mini', $mapIn, $mapOut);

        // REDUCE multi-passo (gpt-4o): visão geral + 1 seção por cenário + divergências.
        // Estimativa de cenários ≈ min(n/4, MAX_SCENARIO_SECTIONS), no mínimo 1.
        $sections = max(1, min((int)ceil($n / 4), self::MAX_SCENARIO_SECTIONS));
        $reduceCalls = $sections + 2; // overview + divergências
        $reduceIn = $reduceCalls * 2500;
        $reduceOut = 3500 + $sections * 1600 + 1500;
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
        self::ensureTables();
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
     * Garante estado terminal: qualquer exceção marca o job como 'failed'.
     */
    public static function runJob(int $jobId): int
    {
        self::ensureTables();
        $job = Database::fetch("SELECT * FROM manual_jobs WHERE id = ?", [$jobId]);
        if (!$job) {
            throw new \RuntimeException("Job {$jobId} não encontrado");
        }

        // Trava atômica: processa quem conseguir mudar de 'pending' para 'mapping'.
        // Também recupera jobs presos em estado intermediário há mais de 15 min
        // (worker morto, fatal, etc.) — evita jobs travados para sempre.
        $claimed = Database::execute(
            "UPDATE manual_jobs SET status='mapping'
             WHERE id=? AND (
                 status='pending'
                 OR (status IN ('mapping','clustering','reducing') AND updated_at < (NOW() - INTERVAL 15 MINUTE))
             )",
            [$jobId]
        );
        if ($claimed < 1) {
            throw new \RuntimeException("Job {$jobId} já está em processamento ou concluído.");
        }

        try {
            return self::process($jobId, $job);
        } catch (\Throwable $e) {
            // Estado terminal garantido em qualquer falha (resolve spinner infinito).
            Database::execute(
                "UPDATE manual_jobs SET status='failed', error_message=? WHERE id=?",
                [mb_substr($e->getMessage(), 0, 1000), $jobId]
            );
            Logger::error("ManualGenerator job {$jobId} falhou: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Pipeline interno do job (MAP → CLUSTER → REDUCE). Lança em caso de erro;
     * o estado 'failed' é definido por runJob().
     */
    private static function process(int $jobId, array $job): int
    {
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
            throw new \RuntimeException('Nenhuma conversa elegível no período/filtro.');
        }

        // ---------- MAP ----------
        $items = [];
        $totalTokens = 0;
        $totalCost = 0.0;
        $processed = 0;

        foreach ($convs as $c) {
            $transcript = self::buildTranscript((int)$c['id'], $c['contact_name'] ?? null);
            if ($transcript !== '') {
                $result = self::mapConversation($transcript, $job['model_map']);
                if ($result && !empty($result['extract'])) {
                    $items[] = ['conversation_id' => (int)$c['id'], 'data' => $result['extract']];
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
            }

            // Progresso sempre persistido (inclusive em transcript vazio).
            $processed++;
            Database::execute("UPDATE manual_jobs SET processed_conversations=? WHERE id=?", [$processed, $jobId]);
        }

        if (empty($items)) {
            throw new \RuntimeException('Não foi possível extrair conhecimento das conversas.');
        }

        // ---------- CLUSTER (dedupe por similaridade semântica) ----------
        Database::execute("UPDATE manual_jobs SET status='clustering' WHERE id=?", [$jobId]);
        $clusters = self::clusterExtracts($items);

        // ---------- REDUCE ----------
        Database::execute("UPDATE manual_jobs SET status='reducing' WHERE id=?", [$jobId]);

        $synthesis = self::synthesize($clusters, $job['title'], $job['model_reduce']);
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
        // Telefone (BR, com ou sem DDI/DDD). Usa lookaround para consumir o '(' do DDD
        // e não deixar parêntese solto (ex.: "(11) 98765-4321").
        $text = preg_replace('/(?<!\d)(?:\+?55[\s.-]?)?\(?\d{2}\)?[\s.-]?\d{4,5}[\s.-]?\d{4}(?!\d)/', '[TELEFONE]', $text);

        return $text;
    }

    // ----------------------------------------------------------------------
    // MAP
    // ----------------------------------------------------------------------

    private static function mapConversation(string $transcript, string $model): ?array
    {
        $system = "Você é um analista sênior de processos de atendimento (CS/Pós-venda). "
                . "Extraia conhecimento DETALHADO e ACIONÁVEL desta conversa para um manual de novos "
                . "funcionários. Capture o passo a passo real, as frases usadas e as políticas aplicadas. "
                . "Responda APENAS com JSON válido. Não invente o que não estiver na conversa.";

        $user = "A partir desta conversa (já anonimizada), extraia em JSON:\n"
              . "{\n"
              . "  \"categoria\": \"tema curto (ex: troca, reembolso, garantia, atraso, rastreio, cancelamento)\",\n"
              . "  \"situacao\": \"contexto/problema do cliente, com detalhes concretos\",\n"
              . "  \"gatilho_cliente\": \"o que o cliente pediu/reclamou, na prática\",\n"
              . "  \"decisao_agente\": \"a decisão central que o atendente tomou\",\n"
              . "  \"passos\": [\"passo a passo concreto do que o atendente fez, um item por etapa\"],\n"
              . "  \"politicas\": [\"regras/políticas aplicadas com detalhes concretos: prazos, valores, condições\"],\n"
              . "  \"scripts\": [\"frases/respostas REAIS e reaproveitáveis ditas pelo atendente (verbatim, anonimizado)\"],\n"
              . "  \"dados_solicitados\": [\"informações que o atendente pediu ao cliente (ex: nº do pedido, e-mail)\"],\n"
              . "  \"ferramentas\": [\"sistemas/ações usadas (ex: abrir ticket, transferir, consultar rastreio)\"],\n"
              . "  \"excecoes\": \"casos-limite, exceções ou ressalvas observadas\",\n"
              . "  \"tom_linguagem\": \"tom/estilo do atendente\",\n"
              . "  \"resultado\": \"como terminou (resolvido, escalado, pendente) e por quê\",\n"
              . "  \"exemplo_dialogo\": \"um trecho CURTO e REAL da conversa (2 a 5 linhas no formato 'CLIENTE: ...\\nATENDENTE: ...') que ilustre bem o atendimento\"\n"
              . "}\n"
              . "Use listas com vários itens quando houver. Campos sem informação: string vazia ou lista vazia.\n\n"
              . "CONVERSA:\n" . $transcript;

        $resp = self::callOpenAI($model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 1400, 0.2, true);

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
    // CLUSTER (dedupe semântico + sinal de divergência)
    // ----------------------------------------------------------------------

    /**
     * Agrupar extrações por similaridade semântica (embeddings) e condensar.
     * Cada cluster vira um "cenário" com as abordagens DISTINTAS observadas —
     * o que reduz repetição no REDUCE e expõe divergências de atendimento.
     *
     * @param array $items  [ ['conversation_id'=>int, 'data'=>array], ... ]
     * @return array        lista de cenários condensados
     */
    private static function clusterExtracts(array $items, float $threshold = 0.82): array
    {
        // Se houver poucos itens, não vale o custo de clusterizar.
        if (count($items) <= 6) {
            return array_map(fn($it) => self::condenseCluster([$it]), $items);
        }

        // 1) Embedding por item (texto = categoria + situação + gatilho)
        $withEmb = [];
        foreach ($items as $it) {
            $d = $it['data'];
            $text = trim(($d['categoria'] ?? '') . '. ' . ($d['situacao'] ?? '') . '. ' . ($d['gatilho_cliente'] ?? ''));
            $emb = $text !== '' ? EmbeddingService::generate($text) : null;
            $withEmb[] = ['cid' => $it['conversation_id'], 'data' => $d, 'emb' => $emb];
        }

        // 2) Clustering guloso por similaridade ao representante
        $clusters = [];
        foreach ($withEmb as $item) {
            $placed = false;
            if ($item['emb']) {
                foreach ($clusters as $idx => $c) {
                    if ($c['emb'] && EmbeddingService::cosineSimilarity($item['emb'], $c['emb']) >= $threshold) {
                        $clusters[$idx]['members'][] = $item;
                        $placed = true;
                        break;
                    }
                }
            }
            if (!$placed) {
                $clusters[] = ['emb' => $item['emb'], 'members' => [$item]];
            }
        }

        // 3) Condensar cada cluster
        return array_map(fn($c) => self::condenseCluster($c['members']), $clusters);
    }

    /** Condensar membros de um cluster em um único "cenário" rico, mantendo exemplos reais. */
    private static function condenseCluster(array $members): array
    {
        $decisoes = $normas = $tons = $cats = $cids = [];
        $passos = $scripts = $dados = $ferramentas = $resultados = $dialogos = [];
        $situacao = '';
        $flatten = function ($v, array &$bucket) {
            foreach ((is_array($v) ? $v : [$v]) as $x) {
                $x = trim((string)$x);
                if ($x !== '') $bucket[] = $x;
            }
        };
        foreach ($members as $m) {
            $d = $m['data'] ?? $m; // tolera item cru
            if ($situacao === '' && !empty($d['situacao'])) $situacao = $d['situacao'];
            if (!empty($d['decisao_agente']))   $decisoes[] = trim($d['decisao_agente']);
            if (!empty($d['tom_linguagem']))    $tons[]     = trim($d['tom_linguagem']);
            if (!empty($d['categoria']))        $cats[]     = trim($d['categoria']);
            if (!empty($d['resultado']))        $resultados[] = trim($d['resultado']);
            if (!empty($d['excecoes']))         $normas[]   = trim($d['excecoes']);
            if (!empty($d['passos']))           $flatten($d['passos'], $passos);
            if (!empty($d['politicas']))        $flatten($d['politicas'], $normas);
            if (!empty($d['scripts']))          $flatten($d['scripts'], $scripts);
            if (!empty($d['dados_solicitados'])) $flatten($d['dados_solicitados'], $dados);
            if (!empty($d['ferramentas']))      $flatten($d['ferramentas'], $ferramentas);
            if (!empty($d['exemplo_dialogo']))  $dialogos[] = trim((string)$d['exemplo_dialogo']);
            // ID da conversa: caminho com embedding usa 'cid'; caminho cru usa 'conversation_id'.
            $cid = $m['cid'] ?? $m['conversation_id'] ?? null;
            if ($cid !== null)                  $cids[]     = $cid;
        }
        $uniq = fn(array $a, int $cap) => array_slice(array_values(array_unique($a)), 0, $cap);
        return [
            'categoria' => self::topValue($cats),
            'situacao_representativa' => $situacao,
            'n_casos' => count($members),
            'decisoes' => $uniq($decisoes, 8),
            'passos' => $uniq($passos, 15),
            'politicas' => $uniq($normas, 12),
            'scripts' => $uniq($scripts, 12),
            'dados_solicitados' => $uniq($dados, 10),
            'ferramentas' => $uniq($ferramentas, 10),
            'resultados' => $uniq($resultados, 6),
            'tons' => $uniq($tons, 6),
            'exemplos_dialogo' => array_slice(array_values(array_filter($dialogos)), 0, 3),
            'exemplos_conversas' => $uniq($cids, 6),
        ];
    }

    /** Valor mais frequente de um array (para a categoria do cluster). */
    private static function topValue(array $values): string
    {
        if (empty($values)) return '';
        $counts = array_count_values($values);
        arsort($counts);
        return (string)array_key_first($counts);
    }

    // ----------------------------------------------------------------------
    // REDUCE / SÍNTESE
    // ----------------------------------------------------------------------

    /** Máx. de cenários que viram seção detalhada própria (controle de custo). */
    private const MAX_SCENARIO_SECTIONS = 18;

    /**
     * Síntese multi-passo: visão geral + uma seção DETALHADA por cenário (com
     * passos, scripts e exemplo real) + divergências. Produz um manual muito mais
     * completo do que uma única chamada conseguiria.
     */
    private static function synthesize(array $clusters, string $title, string $model): array
    {
        // Ordenar por relevância (mais casos primeiro).
        usort($clusters, fn($a, $b) => ((int)($b['n_casos'] ?? 0)) <=> ((int)($a['n_casos'] ?? 0)));

        $tokens = 0;
        $cost = 0.0;
        $parts = [];

        // ---- 1) Visão geral: normas, critérios de decisão e tom de voz ----
        $overview = self::reduceOverview($clusters, $title, $model);
        $tokens += $overview['tokens'];
        $cost += $overview['cost'];
        if (trim($overview['markdown']) === '') {
            // Sem nem a visão geral, é falha real → runJob marca 'failed'.
            throw new \RuntimeException('Falha na síntese (REDUCE): OpenAI indisponível ou resposta inválida.');
        }
        $parts[] = $overview['markdown'];

        // ---- 2) Uma seção detalhada por cenário ----
        $sections = ["## Procedimentos Detalhados por Cenário"];
        $top = array_slice($clusters, 0, self::MAX_SCENARIO_SECTIONS);
        foreach ($top as $i => $cl) {
            $sec = self::reduceScenario($cl, $i + 1, $model);
            $tokens += $sec['tokens'];
            $cost += $sec['cost'];
            if (trim($sec['markdown']) !== '') {
                $sections[] = $sec['markdown'];
            }
        }
        $parts[] = implode("\n\n", $sections);

        // ---- 3) Divergências (data-driven a partir dos clusters) ----
        $div = self::reduceDivergences($clusters, $model);
        $tokens += $div['tokens'];
        $cost += $div['cost'];

        return [
            'manual_markdown' => implode("\n\n", $parts),
            'divergences' => $div['divergences'],
            'tokens' => $tokens,
            'cost' => $cost,
        ];
    }

    /** Passo 1 do REDUCE: visão geral (normas, critérios, tom de voz). */
    private static function reduceOverview(array $clusters, string $title, string $model): array
    {
        // Material agregado, compacto.
        $agg = [];
        foreach ($clusters as $c) {
            $agg[] = [
                'categoria' => $c['categoria'] ?? '',
                'n_casos' => $c['n_casos'] ?? 0,
                'politicas' => $c['politicas'] ?? [],
                'decisoes' => $c['decisoes'] ?? [],
                'tons' => $c['tons'] ?? [],
            ];
        }
        $compact = self::cap(json_encode($agg, JSON_UNESCAPED_UNICODE), 40000);

        $system = "Você é um especialista em CS/Pós-venda que escreve manuais operacionais DETALHADOS "
                . "para novos funcionários. Escreva em Markdown, em português, de forma completa e prática. "
                . "Baseie-se SOMENTE nos dados. Não invente políticas que não apareçam.";

        $user = "Manual: \"{$title}\".\n"
              . "Com base nos cenários agregados (JSON) abaixo, escreva a ABERTURA do manual em Markdown contendo:\n"
              . "## Visão Geral (1 parágrafo sobre o propósito e o panorama dos atendimentos)\n"
              . "## 1) Normas e Políticas (lista detalhada; inclua prazos, valores e condições quando houver)\n"
              . "## 2) Critérios de Decisão (quando escalar, quando trocar/reembolsar, etc. — seja específico)\n"
              . "## 3) Tom de Voz e Diretrizes de Linguagem\n"
              . "Seja abrangente e concreto. NÃO escreva os procedimentos por cenário (virão depois).\n\n"
              . "CENÁRIOS (agregado):\n" . $compact;

        $resp = self::callOpenAI($model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 3500, 0.4, false);

        return [
            'markdown' => $resp['content'] ?? '',
            'tokens' => $resp['total_tokens'] ?? 0,
            'cost' => $resp['cost'] ?? 0,
        ];
    }

    /** Passo 2 do REDUCE: uma seção rica para UM cenário (com exemplo real). */
    private static function reduceScenario(array $cluster, int $index, string $model): array
    {
        $compact = self::cap(json_encode($cluster, JSON_UNESCAPED_UNICODE), 30000);
        $ids = implode(', ', array_map('strval', $cluster['exemplos_conversas'] ?? []));

        $system = "Você é um especialista em CS/Pós-venda que documenta procedimentos operacionais "
                . "DETALHADOS para treinar novos funcionários. Escreva em Markdown, em português. "
                . "Use SOMENTE os dados do cenário. Reaproveite os 'scripts' como frases prontas (verbatim) "
                . "e os 'exemplos_dialogo' como exemplo real. Não invente.";

        $user = "Escreva UMA seção de procedimento detalhada para o cenário a seguir, no formato:\n\n"
              . "### {$index}. <título curto do cenário (use a categoria/situação)>\n"
              . "**Quando acontece:** <descrição do gatilho>\n"
              . "**Passo a passo:**\n1. ...\n2. ...\n(detalhe cada etapa de forma acionável)\n"
              . "**Dados a solicitar:** <lista>\n"
              . "**Políticas aplicáveis:** <lista com prazos/valores/condições>\n"
              . "**Scripts prontos:** use blocos de citação (>) com as frases reaproveitáveis\n"
              . "**Exceções e casos-limite:** <lista>\n"
              . "**📌 Exemplo real:** reproduza um trecho de 'exemplos_dialogo' formatado como diálogo\n"
              . "**Conversas de referência:** {$ids}\n\n"
              . "Seja completo e específico. Se um campo não tiver dados, omita a linha correspondente.\n\n"
              . "CENÁRIO (JSON):\n" . $compact;

        $resp = self::callOpenAI($model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 2200, 0.4, false);

        return [
            'markdown' => $resp['content'] ?? '',
            'tokens' => $resp['total_tokens'] ?? 0,
            'cost' => $resp['cost'] ?? 0,
        ];
    }

    /** Passo 3 do REDUCE: divergências a partir de clusters com >1 decisão distinta. */
    private static function reduceDivergences(array $clusters, string $model): array
    {
        // Candidatos estruturais: cenários com mais de uma decisão distinta.
        $candidatos = [];
        foreach ($clusters as $c) {
            $decisoes = $c['decisoes'] ?? [];
            if (count($decisoes) >= 2) {
                $candidatos[] = [
                    'cenario' => $c['situacao_representativa'] ?: ($c['categoria'] ?? ''),
                    'variacoes' => array_slice($decisoes, 0, 5),
                    'exemplos' => $c['exemplos_conversas'] ?? [],
                ];
            }
        }
        if (empty($candidatos)) {
            return ['divergences' => [], 'tokens' => 0, 'cost' => 0];
        }

        $compact = self::cap(json_encode($candidatos, JSON_UNESCAPED_UNICODE), 20000);
        $system = "Você analisa inconsistências de atendimento. Responda em JSON.";
        $user = "Para cada cenário abaixo (que teve abordagens diferentes entre conversas), escreva uma "
              . "recomendação de padronização. Responda JSON: "
              . "{ \"divergences\": [ {\"cenario\":\"...\",\"variacoes\":[...],\"recomendacao\":\"...\",\"exemplos\":[...]} ] }.\n"
              . "Mantenha 'exemplos' (IDs) de cada item.\n\nCENÁRIOS:\n" . $compact;

        $resp = self::callOpenAI($model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 2000, 0.4, true);

        if (!$resp) {
            // Divergências são secundárias: cair para os candidatos estruturais.
            return ['divergences' => $candidatos, 'tokens' => 0, 'cost' => 0];
        }
        $parsed = self::parseJson($resp['content']);
        $divergences = is_array($parsed) ? ($parsed['divergences'] ?? $candidatos) : $candidatos;

        return [
            'divergences' => $divergences,
            'tokens' => $resp['total_tokens'],
            'cost' => $resp['cost'],
        ];
    }

    /** Truncar string mantendo limite de caracteres. */
    private static function cap(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . ' ...' : $s;
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
