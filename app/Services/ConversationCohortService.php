<?php
/**
 * ConversationCohortService
 *
 * Monta a "coorte" de conversas a analisar: quais conversas PASSARAM por
 * determinadas etapas do funil e/ou por determinados agentes, dentro de um
 * período.
 *
 * Diferença central para os filtros já existentes em Conversation::getAll():
 * lá os filtros de funil/etapa/agente olham o ESTADO ATUAL da conversa
 * (c.funnel_stage_id = X). Aqui olhamos o HISTÓRICO — a conversa entra na
 * coorte mesmo que já tenha saído da etapa ou trocado de agente.
 *
 * Fontes de verdade:
 *   - etapas  -> funnel_stage_history (populada por FunnelService::recordStageTransition)
 *   - agentes -> conversation_assignments (atribuição) OU messages (fala real)
 *
 * A dupla fonte para agentes é proposital: a atribuição sozinha ignora quem
 * atendeu sem estar atribuído, e a mensagem sozinha ignora quem recebeu a
 * conversa e nunca respondeu — que é justamente um dos casos que queremos ver.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\FunnelStageHistory;

class ConversationCohortService
{
    /** Teto absoluto de conversas por análise (proteção de custo/tempo) */
    public const MAX_LIMIT = 2000;

    /** Padrão de conversas por análise */
    public const DEFAULT_LIMIT = 300;

    /** Base de data padrão */
    public const DEFAULT_DATE_BASIS = 'activity';

    /**
     * Normalizar filtros vindos da UI/API
     */
    public static function normalizeFilters(array $raw): array
    {
        $days = isset($raw['days']) ? (int)$raw['days'] : null;

        $dateFrom = $raw['date_from'] ?? null;
        $dateTo = $raw['date_to'] ?? null;

        if ($days && $days > 0) {
            $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
            $dateTo = date('Y-m-d');
        }

        $limit = isset($raw['limit']) ? (int)$raw['limit'] : self::DEFAULT_LIMIT;
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $dateBasis = in_array($raw['date_basis'] ?? '', ['activity', 'created'], true)
            ? $raw['date_basis']
            : self::DEFAULT_DATE_BASIS;

        return [
            'date_from' => $dateFrom ?: date('Y-m-d', strtotime('-30 days')),
            'date_to' => $dateTo ?: date('Y-m-d'),
            'date_basis' => $dateBasis,
            'stage_ids' => self::sanitizeIds($raw['stage_ids'] ?? []),
            'stage_match' => ($raw['stage_match'] ?? 'any') === 'all' ? 'all' : 'any',
            // any_time: passou pela etapa em qualquer momento até o fim do período
            // in_period: a passagem em si ocorreu dentro do período
            'stage_window' => ($raw['stage_window'] ?? 'any_time') === 'in_period' ? 'in_period' : 'any_time',
            'agent_ids' => self::sanitizeIds($raw['agent_ids'] ?? []),
            'agent_match' => ($raw['agent_match'] ?? 'any') === 'all' ? 'all' : 'any',
            'agent_basis' => in_array($raw['agent_basis'] ?? '', ['assignment', 'message', 'both'], true)
                ? $raw['agent_basis']
                : 'both',
            'team_ids' => self::sanitizeIds($raw['team_ids'] ?? []),
            'funnel_ids' => self::sanitizeIds($raw['funnel_ids'] ?? []),
            'department_ids' => self::sanitizeIds($raw['department_ids'] ?? []),
            'tag_ids' => self::sanitizeIds($raw['tag_ids'] ?? []),
            'channels' => self::sanitizeStrings($raw['channels'] ?? []),
            'statuses' => self::sanitizeStrings($raw['statuses'] ?? []),
            'exclude_spam' => !isset($raw['exclude_spam']) || (bool)$raw['exclude_spam'],
            'min_messages' => isset($raw['min_messages']) ? max(0, (int)$raw['min_messages']) : 4,
            'limit' => $limit,
            'current_user_id' => isset($raw['current_user_id']) ? (int)$raw['current_user_id'] : null,
        ];
    }

    /**
     * Montar a query da coorte
     *
     * @return array [sql, params] — o SELECT retorna c.id e colunas de apoio
     */
    public static function buildQuery(array $filters, bool $countOnly = false): array
    {
        // normalizeFilters é idempotente — chamar de novo aqui garante que a
        // query nunca seja montada com entrada crua
        $filters = self::normalizeFilters($filters);

        $from = $filters['date_from'] . ' 00:00:00';
        $to = $filters['date_to'] . ' 23:59:59';

        $select = $countOnly
            ? "SELECT COUNT(DISTINCT c.id) AS total"
            : "SELECT c.id, c.contact_id, c.agent_id, c.funnel_id, c.funnel_stage_id,
                      c.channel, c.status, c.created_at, c.moved_at";

        $sql = "{$select}
                FROM conversations c
                WHERE 1=1";
        $params = [];

        // ------------------------------------------------------------------
        // Janela temporal (o que define "conversa do período")
        // ------------------------------------------------------------------
        if ($filters['date_basis'] === 'created') {
            $sql .= " AND c.created_at BETWEEN ? AND ?";
            $params[] = $from;
            $params[] = $to;
        } else {
            // 'activity': teve pelo menos uma mensagem no período
            $sql .= " AND EXISTS (
                        SELECT 1 FROM messages m_win
                        WHERE m_win.conversation_id = c.id
                          AND m_win.created_at BETWEEN ? AND ?
                      )";
            $params[] = $from;
            $params[] = $to;
        }

        // ------------------------------------------------------------------
        // PASSOU PELA(S) ETAPA(S)
        // ------------------------------------------------------------------
        if (!empty($filters['stage_ids'])) {
            if (!FunnelStageHistory::tableExists()) {
                // Degradação explícita: sem histórico, cai para o estado atual
                Logger::warning("ConversationCohortService - funnel_stage_history ausente, usando estado atual das conversas");
                $placeholders = self::placeholders($filters['stage_ids']);
                $sql .= " AND c.funnel_stage_id IN ({$placeholders})";
                $params = array_merge($params, $filters['stage_ids']);
            } else {
                // Janela da passagem pela etapa:
                //  - any_time:  passou em qualquer momento até o fim do período
                //               (a conversa já é do período pelo filtro de atividade)
                //  - in_period: a própria movimentação ocorreu dentro do período
                $inPeriod = $filters['stage_window'] === 'in_period';
                $windowClause = $inPeriod ? 'h.created_at BETWEEN ? AND ?' : 'h.created_at <= ?';
                $windowParams = $inPeriod ? [$from, $to] : [$to];

                if ($filters['stage_match'] === 'all') {
                    foreach ($filters['stage_ids'] as $stageId) {
                        $sql .= " AND EXISTS (
                                    SELECT 1 FROM funnel_stage_history h
                                    WHERE h.conversation_id = c.id
                                      AND h.to_stage_id = ?
                                      AND {$windowClause}
                                  )";
                        $params[] = $stageId;
                        $params = array_merge($params, $windowParams);
                    }
                } else {
                    $placeholders = self::placeholders($filters['stage_ids']);
                    $sql .= " AND EXISTS (
                                SELECT 1 FROM funnel_stage_history h
                                WHERE h.conversation_id = c.id
                                  AND h.to_stage_id IN ({$placeholders})
                                  AND {$windowClause}
                              )";
                    $params = array_merge($params, $filters['stage_ids'], $windowParams);
                }
            }
        }

        // ------------------------------------------------------------------
        // PASSOU PELO(S) AGENTE(S) — inclui expansão de times
        // ------------------------------------------------------------------
        $agentIds = self::resolveAgentIds($filters);

        if (!empty($agentIds)) {
            if ($filters['agent_match'] === 'all') {
                foreach ($agentIds as $agentId) {
                    [$clause, $clauseParams] = self::agentPredicate([$agentId], $filters['agent_basis'], $from, $to);
                    $sql .= " AND {$clause}";
                    $params = array_merge($params, $clauseParams);
                }
            } else {
                [$clause, $clauseParams] = self::agentPredicate($agentIds, $filters['agent_basis'], $from, $to);
                $sql .= " AND {$clause}";
                $params = array_merge($params, $clauseParams);
            }
        }

        // ------------------------------------------------------------------
        // Filtros simples de estado
        // ------------------------------------------------------------------
        if (!empty($filters['funnel_ids'])) {
            $placeholders = self::placeholders($filters['funnel_ids']);
            $sql .= " AND c.funnel_id IN ({$placeholders})";
            $params = array_merge($params, $filters['funnel_ids']);
        }

        if (!empty($filters['department_ids'])) {
            $placeholders = self::placeholders($filters['department_ids']);
            $sql .= " AND c.department_id IN ({$placeholders})";
            $params = array_merge($params, $filters['department_ids']);
        }

        if (!empty($filters['channels'])) {
            $placeholders = self::placeholders($filters['channels']);
            $sql .= " AND c.channel IN ({$placeholders})";
            $params = array_merge($params, $filters['channels']);
        }

        if (!empty($filters['statuses'])) {
            $placeholders = self::placeholders($filters['statuses']);
            $sql .= " AND c.status IN ({$placeholders})";
            $params = array_merge($params, $filters['statuses']);
        }

        if (!empty($filters['tag_ids'])) {
            $placeholders = self::placeholders($filters['tag_ids']);
            $sql .= " AND EXISTS (
                        SELECT 1 FROM conversation_tags ct
                        WHERE ct.conversation_id = c.id AND ct.tag_id IN ({$placeholders})
                      )";
            $params = array_merge($params, $filters['tag_ids']);
        }

        if ($filters['exclude_spam']) {
            $sql .= " AND (c.is_spam IS NULL OR c.is_spam = 0)";
        }

        // Conversas curtas demais não têm o que analisar
        if ($filters['min_messages'] > 0) {
            $sql .= " AND (
                        SELECT COUNT(*) FROM messages m_cnt WHERE m_cnt.conversation_id = c.id
                      ) >= ?";
            $params[] = $filters['min_messages'];
        }

        // ------------------------------------------------------------------
        // Escopo de permissão (não-admin só enxerga o que já enxergaria na lista)
        // ------------------------------------------------------------------
        [$permClause, $permParams] = self::permissionScope($filters['current_user_id'] ?? null);
        if ($permClause !== '') {
            $sql .= " AND {$permClause}";
            $params = array_merge($params, $permParams);
        }

        if (!$countOnly) {
            $sql .= " ORDER BY c.updated_at DESC, c.id DESC LIMIT " . (int)$filters['limit'];
        }

        return [$sql, $params];
    }

    /**
     * Predicado "passou pelo agente": atribuição OU mensagem enviada
     */
    private static function agentPredicate(array $agentIds, string $basis, string $from, string $to): array
    {
        $placeholders = self::placeholders($agentIds);

        $assignment = "EXISTS (
            SELECT 1 FROM conversation_assignments ca
            WHERE ca.conversation_id = c.id
              AND ca.agent_id IN ({$placeholders})
              AND ca.assigned_at <= ?
              AND (ca.removed_at IS NULL OR ca.removed_at >= ?)
        )";

        $message = "EXISTS (
            SELECT 1 FROM messages m_ag
            WHERE m_ag.conversation_id = c.id
              AND m_ag.sender_type = 'agent'
              AND m_ag.ai_agent_id IS NULL
              AND m_ag.sender_id IN ({$placeholders})
              AND m_ag.created_at BETWEEN ? AND ?
        )";

        switch ($basis) {
            case 'assignment':
                return ['(' . $assignment . ')', array_merge($agentIds, [$to, $from])];

            case 'message':
                return ['(' . $message . ')', array_merge($agentIds, [$from, $to])];

            case 'both':
            default:
                return [
                    '(' . $assignment . ' OR ' . $message . ')',
                    array_merge($agentIds, [$to, $from], $agentIds, [$from, $to])
                ];
        }
    }

    /**
     * Times viram lista de agentes (um time é um conjunto de usuários)
     */
    private static function resolveAgentIds(array $filters): array
    {
        $agentIds = $filters['agent_ids'] ?? [];

        if (!empty($filters['team_ids'])) {
            $placeholders = self::placeholders($filters['team_ids']);
            $members = Database::fetchAll(
                "SELECT DISTINCT user_id FROM team_members WHERE team_id IN ({$placeholders})",
                $filters['team_ids']
            );

            foreach ($members as $member) {
                $agentIds[] = (int)$member['user_id'];
            }
        }

        return array_values(array_unique(array_map('intval', $agentIds)));
    }

    /**
     * Escopo de permissão — espelha a regra de Conversation::getAll()
     */
    private static function permissionScope(?int $userId): array
    {
        if (!$userId) {
            return ['', []];
        }

        try {
            if (PermissionService::isAdmin($userId) || PermissionService::isSuperAdmin($userId)) {
                return ['', []];
            }
        } catch (\Exception $e) {
            Logger::error("ConversationCohortService::permissionScope - " . $e->getMessage());
            return ['', []];
        }

        $allowedFunnelIds = null;
        if (class_exists('\App\Models\AgentFunnelPermission')) {
            $allowedFunnelIds = \App\Models\AgentFunnelPermission::getAllowedFunnelIds($userId);
        }

        // Usuário comum: conversas dele, não atribuídas dentro dos funis permitidos,
        // ou onde ele é agente do contato
        $clause = "(
            c.agent_id = ?
            OR EXISTS (
                SELECT 1 FROM conversation_assignments ca_perm
                WHERE ca_perm.conversation_id = c.id AND ca_perm.agent_id = ?
            )
            OR EXISTS (
                SELECT 1 FROM contact_agents ca2
                WHERE ca2.contact_id = c.contact_id AND ca2.agent_id = ?
            )";
        $params = [$userId, $userId, $userId];

        if ($allowedFunnelIds !== null && !empty($allowedFunnelIds)) {
            $placeholders = self::placeholders($allowedFunnelIds);
            $clause .= " OR ((c.agent_id IS NULL OR c.agent_id = 0) AND (c.funnel_id IS NULL OR c.funnel_id IN ({$placeholders})))";
            $params = array_merge($params, $allowedFunnelIds);
        } elseif ($allowedFunnelIds !== null && empty($allowedFunnelIds)) {
            $clause .= " OR ((c.agent_id IS NULL OR c.agent_id = 0) AND c.funnel_id IS NULL)";
        } else {
            $clause .= " OR (c.agent_id IS NULL OR c.agent_id = 0)";
        }

        $clause .= ")";

        return [$clause, $params];
    }

    /**
     * Contar conversas da coorte
     */
    public static function count(array $filters): int
    {
        $filters = self::normalizeFilters($filters);
        [$sql, $params] = self::buildQuery($filters, true);

        $result = Database::fetch($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * IDs das conversas da coorte (respeitando o limite)
     */
    public static function getConversationIds(array $filters): array
    {
        $filters = self::normalizeFilters($filters);
        [$sql, $params] = self::buildQuery($filters, false);

        $rows = Database::fetchAll($sql, $params);
        return array_map(static fn($row) => (int)$row['id'], $rows);
    }

    /**
     * Prévia da coorte: quantas conversas, quantas mensagens e o alcance real
     * do histórico — usada para o usuário decidir antes de gastar com IA.
     */
    public static function preview(array $filters): array
    {
        $filters = self::normalizeFilters($filters);

        $total = self::count($filters);
        $selected = min($total, $filters['limit']);

        // Volume de mensagens da amostra que será efetivamente analisada
        $avgMessages = 0;
        if ($selected > 0) {
            $ids = self::getConversationIds($filters);
            if (!empty($ids)) {
                $placeholders = self::placeholders($ids);
                $row = Database::fetch(
                    "SELECT AVG(cnt) AS avg_messages FROM (
                        SELECT COUNT(*) AS cnt FROM messages
                        WHERE conversation_id IN ({$placeholders})
                        GROUP BY conversation_id
                     ) t",
                    $ids
                );
                $avgMessages = (int)round((float)($row['avg_messages'] ?? 0));
            }
        }

        $warnings = [];

        if (!empty($filters['stage_ids'])) {
            if (!FunnelStageHistory::tableExists()) {
                $warnings[] = 'O histórico de etapas não existe neste banco (migration 158 não executada). '
                    . 'O filtro por etapa está usando a etapa ATUAL da conversa, não a trajetória.';
            } else {
                $coverageStart = FunnelStageHistory::getCoverageStart();
                if ($coverageStart && strtotime($coverageStart) > strtotime($filters['date_from'])) {
                    $warnings[] = 'O histórico de etapas começa em ' . date('d/m/Y', strtotime($coverageStart))
                        . '. Movimentações anteriores a essa data não são consideradas.';
                }
            }
        }

        if ($total > $filters['limit']) {
            $warnings[] = "A coorte tem {$total} conversas, mas apenas {$filters['limit']} serão analisadas "
                . '(as mais recentes). Aumente o limite ou reduza o período para cobrir tudo.';
        }

        return [
            'total' => $total,
            'selected' => $selected,
            'avg_messages' => $avgMessages,
            'filters' => $filters,
            'warnings' => $warnings,
        ];
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

    private static function sanitizeIds($value): array
    {
        if (!is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = (int)$item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function sanitizeStrings($value): array
    {
        if (!is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        $out = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }

    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
