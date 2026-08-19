<?php
/**
 * ConversationReportService
 *
 * Gera o relatório da coorte em PDF.
 *
 * Dois modos:
 *
 *  1. DOSSIÊ (sem IA) — `buildCohortReport()` + `renderCohortPdf()`
 *     Roda só a fase 0 (métricas determinísticas) e monta um PDF com os
 *     agregados e as transcrições. Custo ZERO de API: serve para o gestor
 *     baixar e jogar num chat de IA por fora, em vez de pagar pela análise
 *     conversa a conversa.
 *
 *  2. RELATÓRIO DO LOTE (com IA) — `renderBatchPdf()`
 *     PDF do resultado de uma análise já processada, com a síntese executiva.
 *
 * O texto do PDF é extraível (fontes base + WinAnsi), então uma IA consegue
 * ler o arquivo direto, sem OCR.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Helpers\SimplePdf;

class ConversationReportService
{
    /** Conversas com transcrição no PDF, por padrão */
    public const DEFAULT_TRANSCRIPT_LIMIT = 50;

    /** Teto de transcrições (acima disso o PDF fica impraticável) */
    public const MAX_TRANSCRIPT_LIMIT = 400;

    /** Conversas processadas por vez ao carregar mensagens */
    private const CHUNK_SIZE = 100;

    private const WHO_STOPPED_LABELS = [
        'cliente' => 'Cliente parou de responder',
        'vendedor' => 'Vendedor não respondeu a última mensagem do cliente',
        'ninguem' => 'Conversa ainda ativa (menos de 2 dias de silêncio)',
    ];

    // ======================================================================
    // MODO 1 — DOSSIÊ SEM IA
    // ======================================================================

    /**
     * Monta o dataset do relatório a partir dos filtros da coorte.
     *
     * @param array $options ['transcripts' => bool, 'transcript_limit' => int, 'anonymize' => bool]
     */
    public static function buildCohortReport(array $filters, array $options = []): array
    {
        $filters = ConversationCohortService::normalizeFilters($filters);

        $withTranscripts = $options['transcripts'] ?? true;
        $transcriptLimit = (int)($options['transcript_limit'] ?? self::DEFAULT_TRANSCRIPT_LIMIT);
        $transcriptLimit = max(0, min($transcriptLimit, self::MAX_TRANSCRIPT_LIMIT));
        $anonymize = $options['anonymize'] ?? true;

        $conversationIds = ConversationCohortService::getConversationIds($filters);

        if (empty($conversationIds)) {
            return [
                'filters' => $filters,
                'total' => 0,
                'conversations' => [],
                'aggregates' => self::emptyAggregates(),
                'warnings' => ['Nenhuma conversa encontrada com esses filtros.'],
                'transcripts' => [],
            ];
        }

        $details = self::loadConversationDetails($conversationIds);
        $stageEvents = self::loadStageEvents($conversationIds);
        $agentCounts = self::loadAgentCounts($conversationIds);

        $conversations = [];

        // Mensagens em blocos: um relatório de 2.000 conversas não cabe na
        // memória de uma vez se carregarmos tudo junto
        foreach (array_chunk($conversationIds, self::CHUNK_SIZE) as $chunk) {
            $messagesByConversation = self::loadMessages($chunk);

            foreach ($chunk as $conversationId) {
                $detail = $details[$conversationId] ?? null;

                if (!$detail) {
                    continue;
                }

                $messages = $messagesByConversation[$conversationId] ?? [];

                $metrics = ConversationBatchAnalysisService::computeMetrics(
                    $conversationId,
                    $messages,
                    [
                        'stage_events' => $stageEvents[$conversationId] ?? [],
                        'current_stage' => [
                            'stage_id' => $detail['funnel_stage_id'],
                            'stage_name' => $detail['stage_name'],
                        ],
                        'agents_involved' => $agentCounts[$conversationId] ?? 0,
                    ]
                );

                $conversations[] = [
                    'id' => $conversationId,
                    'detail' => $detail,
                    'metrics' => $metrics,
                    'messages' => $withTranscripts ? $messages : [],
                ];
            }

            unset($messagesByConversation);
        }

        $aggregates = self::aggregate($conversations);

        // Transcrições: conversas que travaram primeiro — são o alvo da análise
        $transcripts = [];

        if ($withTranscripts && $transcriptLimit > 0) {
            $ordered = $conversations;

            usort($ordered, static function ($a, $b) {
                $aStalled = $a['metrics']['who_stopped'] !== 'ninguem' ? 1 : 0;
                $bStalled = $b['metrics']['who_stopped'] !== 'ninguem' ? 1 : 0;

                if ($aStalled !== $bStalled) {
                    return $bStalled <=> $aStalled;
                }

                return ($b['metrics']['silence_days'] ?? 0) <=> ($a['metrics']['silence_days'] ?? 0);
            });

            foreach (array_slice($ordered, 0, $transcriptLimit) as $conversation) {
                $transcripts[] = [
                    'id' => $conversation['id'],
                    'detail' => $conversation['detail'],
                    'metrics' => $conversation['metrics'],
                    'text' => ConversationBatchAnalysisService::buildTranscript(
                        $conversation['messages'],
                        $conversation['detail']['contact_name'] ?? null,
                        $anonymize
                    ),
                ];
            }
        }

        // Libera as mensagens: daqui pra frente só os agregados importam
        foreach ($conversations as &$conversation) {
            $conversation['messages'] = [];
        }
        unset($conversation);

        return [
            'filters' => $filters,
            'total' => count($conversations),
            'conversations' => $conversations,
            'aggregates' => $aggregates,
            'warnings' => self::buildWarnings($filters, count($conversationIds), $withTranscripts, count($transcripts)),
            'transcripts' => $transcripts,
            'anonymized' => $anonymize,
        ];
    }

    /**
     * Renderiza o dossiê em PDF
     */
    public static function renderCohortPdf(array $report, array $options = []): SimplePdf
    {
        $filters = $report['filters'];
        $aggregates = $report['aggregates'];
        $total = $report['total'];

        $periodLabel = date('d/m/Y', strtotime($filters['date_from'])) . ' a ' . date('d/m/Y', strtotime($filters['date_to']));

        $pdf = new SimplePdf(
            $options['title'] ?? 'Relatório de Conversas',
            'Dossiê para análise — dados apurados diretamente do banco, sem IA'
        );

        $pdf->cover([
            'Período' => $periodLabel,
            'Conversas no relatório' => number_format($total, 0, ',', '.'),
            'Mensagens somadas' => number_format($aggregates['messages_total'], 0, ',', '.'),
            'Filtros' => self::describeFilters($filters),
            'Gerado em' => date('d/m/Y H:i'),
        ]);

        foreach ($report['warnings'] as $warning) {
            $pdf->paragraph('AVISO: ' . $warning, 8.5, [0.6, 0.35, 0.05]);
        }

        self::renderLegend($pdf, $report['anonymized'] ?? true);
        self::renderAggregates($pdf, $aggregates, $total);
        self::renderConversationTable($pdf, $report['conversations']);

        if (!empty($report['transcripts'])) {
            self::renderTranscripts($pdf, $report['transcripts']);
        }

        return $pdf;
    }

    // ======================================================================
    // MODO 2 — RELATÓRIO DO LOTE ANALISADO
    // ======================================================================

    /**
     * PDF de uma análise já processada pela IA
     */
    public static function renderBatchPdf(array $batch, array $metrics, array $summary, array $items, array $options = []): SimplePdf
    {
        $reasonLabels = ConversationBatchAnalysisService::REASONS;
        $outcomeLabels = ConversationBatchAnalysisService::OUTCOMES;

        $periodLabel = ($batch['date_from'] ? date('d/m/Y', strtotime($batch['date_from'])) : '?')
            . ' a ' . ($batch['date_to'] ? date('d/m/Y', strtotime($batch['date_to'])) : '?');

        $pdf = new SimplePdf(
            $batch['name'] ?: ('Análise de Conversas #' . $batch['id']),
            'Análise de conversas por coorte'
        );

        $analyzed = (int)($metrics['analyzed'] ?? 0);

        $pdf->cover([
            'Período' => $periodLabel,
            'Conversas na coorte' => number_format((int)$batch['total_conversations'], 0, ',', '.'),
            'Analisadas pela IA' => number_format($analyzed, 0, ',', '.'),
            'Custo' => 'US$ ' . number_format((float)$batch['cost'], 2),
            'Modelos' => ($batch['model_map'] ?? '?') . ' / ' . ($batch['model_reduce'] ?? '?'),
            'Gerado em' => date('d/m/Y H:i'),
        ]);

        $pdf->heading('Pergunta analisada');
        $pdf->paragraph($batch['context_question'] ?? '');

        // ---- Síntese executiva ----
        if (!empty($summary)) {
            $pdf->heading('Diagnóstico');

            if (!empty($summary['headline'])) {
                $pdf->paragraph((string)$summary['headline'], 12);
            }

            if (!empty($summary['who_is_dropping'])) {
                $pdf->paragraph((string)$summary['who_is_dropping']);
            }

            if (!empty($summary['leak_points']) && is_array($summary['leak_points'])) {
                $pdf->heading('Onde as conversas vazam', 2);

                $rows = [];
                foreach ($summary['leak_points'] as $leak) {
                    $rows[] = [
                        (string)($leak['where'] ?? ''),
                        (string)($leak['share'] ?? ''),
                        (string)($leak['impact'] ?? ''),
                        (string)($leak['why'] ?? ''),
                    ];
                }

                $pdf->table(['Onde', 'Peso', 'Impacto', 'Por quê'], $rows, [22, 14, 12, 52]);
            }

            if (!empty($summary['recommendations']) && is_array($summary['recommendations'])) {
                $pdf->heading('Recomendações', 2);

                foreach ($summary['recommendations'] as $index => $rec) {
                    $pdf->paragraph(
                        ($index + 1) . '. ' . (string)($rec['action'] ?? '')
                        . (!empty($rec['expected_impact']) ? ' — ' . $rec['expected_impact'] : '')
                    );
                }
            }

            if (!empty($summary['caveats']) && is_array($summary['caveats'])) {
                $pdf->heading('Limitações', 2);
                foreach ($summary['caveats'] as $caveat) {
                    if (is_string($caveat)) {
                        $pdf->paragraph('• ' . $caveat, 8.5, [0.4, 0.4, 0.4]);
                    }
                }
            }
        }

        // ---- Distribuições ----
        if (!empty($metrics['by_who_stopped'])) {
            $pdf->heading('Quem parou de responder');
            $pdf->paragraph('Apurado das mensagens, não inferido pela IA.', 8.5, [0.45, 0.45, 0.45]);

            $totalWho = array_sum(array_map(fn($r) => (int)$r['total'], $metrics['by_who_stopped']));

            foreach ($metrics['by_who_stopped'] as $row) {
                $pdf->bar(self::WHO_STOPPED_LABELS[$row['who_stopped']] ?? $row['who_stopped'], (int)$row['total'], $totalWho);
            }
        }

        if (!empty($metrics['by_reason'])) {
            $pdf->heading('Motivos');

            foreach ($metrics['by_reason'] as $row) {
                $label = $reasonLabels[$row['primary_reason']] ?? $row['primary_reason'];
                $pdf->bar($label, (int)$row['total'], $analyzed);
            }
        }

        if (!empty($metrics['by_stage'])) {
            $pdf->heading('Etapa onde travou');

            foreach ($metrics['by_stage'] as $row) {
                $pdf->bar($row['stage_name'] ?? 'Sem etapa', (int)$row['total'], $analyzed);
            }
        }

        if (!empty($metrics['by_outcome'])) {
            $pdf->heading('Desfechos');

            foreach ($metrics['by_outcome'] as $row) {
                $label = $outcomeLabels[$row['outcome']] ?? $row['outcome'];
                $pdf->bar($label, (int)$row['total'], $analyzed);
            }
        }

        if (!empty($metrics['by_agent'])) {
            $pdf->heading('Por vendedor');

            $rows = [];
            foreach ($metrics['by_agent'] as $row) {
                $agentTotal = (int)$row['total'];
                $stopped = (int)$row['stopped_by_agent'];
                $rate = $agentTotal > 0 ? round($stopped / $agentTotal * 100) : 0;

                $rows[] = [
                    $row['agent_name'] ?? ('Agente #' . $row['agent_id']),
                    (string)$agentTotal,
                    $stopped . ' (' . $rate . '%)',
                    (string)(int)$row['no_followup'],
                    (string)(int)$row['won'],
                ];
            }

            $pdf->table(
                ['Vendedor', 'Conversas', 'Não respondeu', 'Sem follow-up', 'Ganhas'],
                $rows,
                [34, 16, 20, 16, 14]
            );
        }

        // ---- Conversas analisadas ----
        if (!empty($items)) {
            $pdf->addPage();
            $pdf->heading('Conversas analisadas');

            $rows = [];
            foreach ($items as $item) {
                $decoded = \App\Models\ConversationAnalysisItem::decode($item);
                $analysis = $decoded['analysis'] ?: [];

                $rows[] = [
                    '#' . $item['conversation_id'],
                    (string)($item['contact_name'] ?? ''),
                    (string)($item['agent_name'] ?? '—'),
                    (string)($reasonLabels[$item['primary_reason']] ?? $item['primary_reason'] ?? ''),
                    (string)($analysis['reason_explanation'] ?? ''),
                ];
            }

            $pdf->table(['Conversa', 'Contato', 'Vendedor', 'Motivo', 'Explicação'], $rows, [10, 18, 15, 19, 38]);
        }

        return $pdf;
    }

    // ======================================================================
    // SEÇÕES DO PDF
    // ======================================================================

    private static function renderLegend(SimplePdf $pdf, bool $anonymized): void
    {
        $pdf->heading('Como ler este relatório');

        $pdf->paragraph(
            'Os números abaixo são apurados diretamente das mensagens e do histórico do funil — '
            . 'nenhuma inferência de IA foi usada para produzi-los.'
        );

        $pdf->paragraph(
            'QUEM PAROU DE RESPONDER: se a última mensagem da conversa é do contato, quem deixou de '
            . 'responder foi o VENDEDOR; se é do atendimento, quem sumiu foi o CLIENTE. Conversas com '
            . 'menos de 2 dias de silêncio são contadas como ainda ativas.'
        );

        $pdf->paragraph(
            'ETAPA ONDE TRAVOU: etapa do funil vigente no instante da última mensagem, reconstruída '
            . 'pelo histórico de movimentações.'
        );

        $pdf->paragraph(
            'NAS TRANSCRIÇÕES: CLIENTE é o contato, VENDEDOR é o atendente humano e IA é o agente '
            . 'automático. Intervalos longos aparecem marcados como silêncio.'
            . ($anonymized
                ? ' Nome do contato, telefone, e-mail, CPF e CNPJ foram mascarados.'
                : ' ATENÇÃO: os dados pessoais NÃO foram mascarados neste arquivo.')
        );
    }

    private static function renderAggregates(SimplePdf $pdf, array $aggregates, int $total): void
    {
        $pdf->heading('Quem parou de responder');

        foreach ($aggregates['by_who_stopped'] as $key => $count) {
            $pdf->bar(self::WHO_STOPPED_LABELS[$key] ?? $key, $count, $total);
        }

        if (!empty($aggregates['by_stage'])) {
            $pdf->heading('Etapa onde a conversa travou');

            foreach (array_slice($aggregates['by_stage'], 0, 15, true) as $stageName => $count) {
                $pdf->bar($stageName, $count, $total);
            }
        }

        $pdf->heading('Tempo em silêncio');

        foreach ($aggregates['silence_buckets'] as $label => $count) {
            $pdf->bar($label, $count, $total);
        }

        $pdf->heading('Tempo de resposta do atendimento');

        $pdf->keyValue('Tempo médio de resposta', self::humanizeSeconds($aggregates['avg_response_seconds']));
        $pdf->keyValue('Mediana', self::humanizeSeconds($aggregates['median_response_seconds']));
        $pdf->keyValue('Aguardando resposta', $aggregates['unanswered'] . ' de ' . $total . ' conversas com mensagem do cliente sem resposta');

        if (!empty($aggregates['by_agent'])) {
            $pdf->heading('Por vendedor');

            $rows = [];
            foreach ($aggregates['by_agent'] as $agent) {
                $rate = $agent['total'] > 0 ? round($agent['stopped'] / $agent['total'] * 100) : 0;

                $rows[] = [
                    $agent['name'],
                    (string)$agent['total'],
                    $agent['stopped'] . ' (' . $rate . '%)',
                    self::humanizeSeconds($agent['avg_response']),
                ];
            }

            $pdf->table(['Vendedor', 'Conversas', 'Não respondeu', 'Resposta média'], $rows, [40, 18, 22, 20]);
        }

        if (!empty($aggregates['by_channel'])) {
            $pdf->heading('Por canal', 2);

            $rows = [];
            foreach ($aggregates['by_channel'] as $channel => $count) {
                $rows[] = [ucfirst($channel), (string)$count];
            }

            $pdf->table(['Canal', 'Conversas'], $rows, [70, 30]);
        }

        if (!empty($aggregates['by_status'])) {
            $pdf->heading('Por status', 2);

            $rows = [];
            foreach ($aggregates['by_status'] as $status => $count) {
                $rows[] = [$status, (string)$count];
            }

            $pdf->table(['Status', 'Conversas'], $rows, [70, 30]);
        }
    }

    private static function renderConversationTable(SimplePdf $pdf, array $conversations): void
    {
        if (empty($conversations)) {
            return;
        }

        $pdf->addPage();
        $pdf->heading('Conversas');
        $pdf->paragraph('Uma linha por conversa da coorte.', 8.5, [0.45, 0.45, 0.45]);

        $rows = [];

        foreach ($conversations as $conversation) {
            $metrics = $conversation['metrics'];
            $detail = $conversation['detail'];

            $rows[] = [
                '#' . $conversation['id'],
                (string)($detail['contact_name'] ?? 'Sem nome'),
                (string)($detail['agent_name'] ?? '—'),
                (string)($metrics['drop_off_stage_name'] ?? '—'),
                self::shortWhoStopped($metrics['who_stopped']),
                (string)$metrics['silence_days'] . 'd',
                (string)$metrics['messages_total'],
            ];
        }

        $pdf->table(
            ['Conv.', 'Contato', 'Vendedor', 'Etapa', 'Quem parou', 'Silêncio', 'Msgs'],
            $rows,
            [9, 22, 17, 20, 14, 9, 9]
        );
    }

    private static function renderTranscripts(SimplePdf $pdf, array $transcripts): void
    {
        $pdf->addPage();
        $pdf->heading('Transcrições');
        $pdf->paragraph(
            'Conversas que travaram aparecem primeiro, ordenadas pelo tempo de silêncio.',
            8.5,
            [0.45, 0.45, 0.45]
        );

        foreach ($transcripts as $transcript) {
            $detail = $transcript['detail'];
            $metrics = $transcript['metrics'];

            $pdf->spacer(6);
            $pdf->rule(0.7);

            $pdf->heading(
                'Conversa #' . $transcript['id'] . ' — ' . ($detail['contact_name'] ?? 'Sem nome'),
                2
            );

            $pdf->keyValue('Vendedor', (string)($detail['agent_name'] ?? '—'));
            $pdf->keyValue('Etapa quando parou', (string)($metrics['drop_off_stage_name'] ?? '—'));
            $pdf->keyValue('Quem parou', self::WHO_STOPPED_LABELS[$metrics['who_stopped']] ?? $metrics['who_stopped']);
            $pdf->keyValue('Silêncio', $metrics['silence_days'] . ' dias');
            $pdf->keyValue(
                'Mensagens',
                $metrics['messages_total'] . ' (cliente ' . $metrics['messages_contact']
                . ', vendedor ' . $metrics['messages_human']
                . ', IA ' . $metrics['messages_ai'] . ')'
            );

            $pdf->spacer(4);
            $pdf->mono($transcript['text']);
        }
    }

    // ======================================================================
    // AGREGAÇÃO (sem IA)
    // ======================================================================

    private static function aggregate(array $conversations): array
    {
        $aggregates = self::emptyAggregates();

        $responseTimes = [];
        $byAgent = [];

        foreach ($conversations as $conversation) {
            $metrics = $conversation['metrics'];
            $detail = $conversation['detail'];

            $aggregates['messages_total'] += (int)$metrics['messages_total'];

            $who = $metrics['who_stopped'];
            $aggregates['by_who_stopped'][$who] = ($aggregates['by_who_stopped'][$who] ?? 0) + 1;

            $stageName = $metrics['drop_off_stage_name'] ?: 'Sem etapa';
            $aggregates['by_stage'][$stageName] = ($aggregates['by_stage'][$stageName] ?? 0) + 1;

            $channel = $detail['channel'] ?: 'desconhecido';
            $aggregates['by_channel'][$channel] = ($aggregates['by_channel'][$channel] ?? 0) + 1;

            $status = $detail['status'] ?: 'desconhecido';
            $aggregates['by_status'][$status] = ($aggregates['by_status'][$status] ?? 0) + 1;

            $bucket = self::silenceBucket((int)$metrics['silence_days']);
            $aggregates['silence_buckets'][$bucket]++;

            if (!empty($metrics['unanswered_by_agent'])) {
                $aggregates['unanswered']++;
            }

            if ($metrics['avg_response_seconds'] !== null) {
                $responseTimes[] = (int)$metrics['avg_response_seconds'];
            }

            $agentId = (int)($detail['agent_id'] ?? 0);

            if ($agentId > 0) {
                if (!isset($byAgent[$agentId])) {
                    $byAgent[$agentId] = [
                        'name' => $detail['agent_name'] ?? ('Agente #' . $agentId),
                        'total' => 0,
                        'stopped' => 0,
                        'response_times' => [],
                    ];
                }

                $byAgent[$agentId]['total']++;

                if ($who === 'vendedor') {
                    $byAgent[$agentId]['stopped']++;
                }

                if ($metrics['avg_response_seconds'] !== null) {
                    $byAgent[$agentId]['response_times'][] = (int)$metrics['avg_response_seconds'];
                }
            }
        }

        arsort($aggregates['by_stage']);
        arsort($aggregates['by_channel']);
        arsort($aggregates['by_status']);

        $aggregates['avg_response_seconds'] = !empty($responseTimes)
            ? (int)round(array_sum($responseTimes) / count($responseTimes))
            : null;

        $aggregates['median_response_seconds'] = self::median($responseTimes);

        foreach ($byAgent as &$agent) {
            $agent['avg_response'] = !empty($agent['response_times'])
                ? (int)round(array_sum($agent['response_times']) / count($agent['response_times']))
                : null;
            unset($agent['response_times']);
        }
        unset($agent);

        uasort($byAgent, static fn($a, $b) => $b['total'] <=> $a['total']);

        $aggregates['by_agent'] = array_values($byAgent);

        return $aggregates;
    }

    private static function emptyAggregates(): array
    {
        return [
            'messages_total' => 0,
            'unanswered' => 0,
            'by_who_stopped' => ['cliente' => 0, 'vendedor' => 0, 'ninguem' => 0],
            'by_stage' => [],
            'by_channel' => [],
            'by_status' => [],
            'by_agent' => [],
            'silence_buckets' => [
                'Ativa (0-2 dias)' => 0,
                '3 a 7 dias' => 0,
                '8 a 15 dias' => 0,
                '16 a 30 dias' => 0,
                'Mais de 30 dias' => 0,
            ],
            'avg_response_seconds' => null,
            'median_response_seconds' => null,
        ];
    }

    private static function silenceBucket(int $days): string
    {
        if ($days <= 2) {
            return 'Ativa (0-2 dias)';
        }
        if ($days <= 7) {
            return '3 a 7 dias';
        }
        if ($days <= 15) {
            return '8 a 15 dias';
        }
        if ($days <= 30) {
            return '16 a 30 dias';
        }

        return 'Mais de 30 dias';
    }

    private static function median(array $values): ?int
    {
        if (empty($values)) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = (int)floor($count / 2);

        if ($count % 2 === 1) {
            return (int)$values[$middle];
        }

        return (int)round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    // ======================================================================
    // CARGA EM LOTE
    // ======================================================================

    private static function loadConversationDetails(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = Database::fetchAll(
            "SELECT c.id, c.contact_id, c.agent_id, c.channel, c.status,
                    c.funnel_id, c.funnel_stage_id, c.created_at,
                    ct.name AS contact_name, ct.phone AS contact_phone,
                    u.name AS agent_name,
                    fs.name AS stage_name,
                    f.name AS funnel_name
             FROM conversations c
             LEFT JOIN contacts ct ON ct.id = c.contact_id
             LEFT JOIN users u ON u.id = c.agent_id
             LEFT JOIN funnel_stages fs ON fs.id = c.funnel_stage_id
             LEFT JOIN funnels f ON f.id = c.funnel_id
             WHERE c.id IN ({$placeholders})",
            $ids
        );

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(int)$row['id']] = $row;
        }

        return $indexed;
    }

    private static function loadStageEvents(array $ids): array
    {
        if (!\App\Models\FunnelStageHistory::tableExists()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $rows = Database::fetchAll(
                "SELECT h.conversation_id, h.to_stage_id, h.created_at, fs.name AS stage_name
                 FROM funnel_stage_history h
                 LEFT JOIN funnel_stages fs ON fs.id = h.to_stage_id
                 WHERE h.conversation_id IN ({$placeholders})
                 ORDER BY h.conversation_id ASC, h.created_at ASC, h.id ASC",
                $ids
            );
        } catch (\Exception $e) {
            Logger::error('ConversationReportService::loadStageEvents - ' . $e->getMessage());
            return [];
        }

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int)$row['conversation_id']][] = $row;
        }

        return $grouped;
    }

    private static function loadAgentCounts(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $rows = Database::fetchAll(
                "SELECT conversation_id, COUNT(DISTINCT agent_id) AS agents
                 FROM conversation_assignments
                 WHERE conversation_id IN ({$placeholders})
                 GROUP BY conversation_id",
                $ids
            );
        } catch (\Exception $e) {
            Logger::error('ConversationReportService::loadAgentCounts - ' . $e->getMessage());
            return [];
        }

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int)$row['conversation_id']] = (int)$row['agents'];
        }

        return $counts;
    }

    private static function loadMessages(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = Database::fetchAll(
            "SELECT conversation_id, sender_type, sender_id, ai_agent_id, content, message_type, created_at
             FROM messages
             WHERE conversation_id IN ({$placeholders})
             ORDER BY conversation_id ASC, id ASC",
            $ids
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int)$row['conversation_id']][] = $row;
        }

        return $grouped;
    }

    // ======================================================================
    // HELPERS
    // ======================================================================

    private static function buildWarnings(array $filters, int $selected, bool $withTranscripts, int $transcriptCount): array
    {
        $warnings = [];

        $totalInCohort = ConversationCohortService::count($filters);

        if ($totalInCohort > $selected) {
            $warnings[] = "A coorte tem {$totalInCohort} conversas, mas o relatório traz apenas {$selected} "
                . '(as mais recentes). Aumente o parâmetro "limit" para cobrir tudo.';
        }

        if ($withTranscripts && $transcriptCount < $selected) {
            $warnings[] = "As transcrições cobrem {$transcriptCount} das {$selected} conversas. "
                . 'Os números agregados consideram todas.';
        }

        if (!empty($filters['stage_ids'])) {
            $coverageStart = \App\Models\FunnelStageHistory::getCoverageStart();

            if ($coverageStart && strtotime($coverageStart) > strtotime($filters['date_from'])) {
                $warnings[] = 'O histórico de etapas começa em ' . date('d/m/Y', strtotime($coverageStart))
                    . '; movimentações anteriores não entram no filtro por etapa.';
            }
        }

        return $warnings;
    }

    private static function describeFilters(array $filters): string
    {
        $parts = [];

        if (!empty($filters['stage_ids'])) {
            $parts[] = count($filters['stage_ids']) . ' etapa(s)';
        }

        if (!empty($filters['agent_ids'])) {
            $parts[] = count($filters['agent_ids']) . ' agente(s)';
        }

        if (!empty($filters['team_ids'])) {
            $parts[] = count($filters['team_ids']) . ' time(s)';
        }

        if (!empty($filters['funnel_ids'])) {
            $parts[] = count($filters['funnel_ids']) . ' funil(is)';
        }

        if (!empty($filters['channels'])) {
            $parts[] = 'canais: ' . implode(', ', $filters['channels']);
        }

        if (!empty($filters['statuses'])) {
            $parts[] = 'status: ' . implode(', ', $filters['statuses']);
        }

        if ((int)$filters['min_messages'] > 0) {
            $parts[] = 'mín. ' . $filters['min_messages'] . ' mensagens';
        }

        return empty($parts) ? 'Todas as conversas do sistema no período' : implode(' · ', $parts);
    }

    private static function shortWhoStopped(?string $who): string
    {
        return match ($who) {
            'cliente' => 'Cliente',
            'vendedor' => 'Vendedor',
            'ninguem' => 'Ativa',
            default => '—',
        };
    }

    private static function humanizeSeconds(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            return round($seconds / 60) . ' min';
        }

        if ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' h';
        }

        return round($seconds / 86400, 1) . ' dias';
    }
}
