<?php
/**
 * Service ContactSegmentationService
 *
 * Resolve um filter_config (JSON de regras) em uma lista de contact_ids.
 * Usado por campanhas com target_type='filter' para segmentar leads
 * com base no histórico de funis/etapas, tags, canais e datas.
 *
 * Estrutura esperada do filter_config:
 * {
 *   "logic": "AND",                              // AND|OR (entre regras)
 *   "rules": [
 *     {
 *       "type": "passed_through",
 *       "funnel_id": 1,                          // opcional (restringe a um funil)
 *       "stage_ids": [3, 5],                     // obrigatório
 *       "any_of": true,                          // true = qualquer; false = todas
 *       "since": "2026-01-01",                   // opcional
 *       "until": "2026-04-25"                    // opcional
 *     },
 *     {
 *       "type": "not_passed_through",
 *       "funnel_id": 2,
 *       "stage_ids": [7]
 *     },
 *     { "type": "currently_in_stage", "stage_ids": [4] },
 *     { "type": "has_tag", "tag_ids": [12] },
 *     { "type": "not_has_tag", "tag_ids": [99] },
 *     { "type": "channel", "channels": ["whatsapp"] },
 *     { "type": "created_between", "since": "2026-01-01", "until": "2026-04-25" }
 *   ]
 * }
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;

class ContactSegmentationService
{
    /**
     * Resolver filter_config em lista de contact_ids
     *
     * @param array $filterConfig
     * @param int|null $limit Para preview/amostragem (NULL = sem limite)
     * @return int[]
     */
    public static function resolve(array $filterConfig, ?int $limit = null): array
    {
        [$sql, $params] = self::buildQuery($filterConfig, ['select' => 'c.id'], $limit);

        $rows = Database::fetchAll($sql, $params);
        return array_map(static fn($r) => (int)$r['id'], $rows);
    }

    /**
     * Contar contatos que casam com o filter_config
     */
    public static function count(array $filterConfig): int
    {
        [$sql, $params] = self::buildQuery($filterConfig, ['select' => 'COUNT(DISTINCT c.id) AS total'], null);
        $row = Database::fetch($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    /**
     * Preview: retorna {total, sample} para a UI confirmar segmentação
     */
    public static function preview(array $filterConfig, int $sampleSize = 10): array
    {
        $total = self::count($filterConfig);

        if ($total === 0) {
            return ['total' => 0, 'sample' => []];
        }

        [$sql, $params] = self::buildQuery(
            $filterConfig,
            ['select' => 'c.id, c.name, c.phone, c.email'],
            $sampleSize
        );

        return [
            'total' => $total,
            'sample' => Database::fetchAll($sql, $params),
        ];
    }

    /**
     * Constrói a query SQL e seus parâmetros
     *
     * @return array [string $sql, array $params]
     */
    private static function buildQuery(array $filterConfig, array $opts, ?int $limit): array
    {
        $select = $opts['select'] ?? 'c.id';
        $logic = strtoupper($filterConfig['logic'] ?? 'AND');
        $logic = in_array($logic, ['AND', 'OR'], true) ? $logic : 'AND';

        $rules = $filterConfig['rules'] ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }

        $whereParts = [];
        $params = [];

        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['type'])) {
                continue;
            }

            $built = self::buildRule($rule);
            if ($built === null) {
                continue;
            }

            [$clause, $clauseParams] = $built;
            $whereParts[] = $clause;
            $params = array_merge($params, $clauseParams);
        }

        $whereSql = empty($whereParts) ? '1=1' : '(' . implode(") {$logic} (", $whereParts) . ')';

        $limitSql = $limit !== null ? ' LIMIT ' . (int)$limit : '';

        $sql = "SELECT DISTINCT {$select}
                FROM contacts c
                WHERE {$whereSql}
                ORDER BY c.id ASC{$limitSql}";

        return [$sql, $params];
    }

    /**
     * Constrói uma única regra em SQL parcial + params
     *
     * @return array|null [string $clauseSql, array $params]
     */
    private static function buildRule(array $rule): ?array
    {
        $type = $rule['type'];

        switch ($type) {
            case 'passed_through':
                return self::buildPassedThrough($rule, false);

            case 'not_passed_through':
                return self::buildPassedThrough($rule, true);

            case 'currently_in_stage':
                return self::buildCurrentlyInStage($rule);

            case 'has_tag':
                return self::buildHasTag($rule, false);

            case 'not_has_tag':
                return self::buildHasTag($rule, true);

            case 'channel':
                return self::buildChannel($rule);

            case 'created_between':
                return self::buildCreatedBetween($rule);

            default:
                Logger::warning("[ContactSegmentation] Tipo de regra desconhecido: {$type}");
                return null;
        }
    }

    /**
     * Regra: contato passou (ou não) por uma das etapas indicadas
     * Usa funnel_stage_history (registro histórico de cada transição)
     */
    private static function buildPassedThrough(array $rule, bool $negate): ?array
    {
        $stageIds = self::sanitizeIds($rule['stage_ids'] ?? []);
        if (empty($stageIds)) {
            return null;
        }

        $funnelId = !empty($rule['funnel_id']) ? (int)$rule['funnel_id'] : null;
        $since = $rule['since'] ?? null;
        $until = $rule['until'] ?? null;
        $anyOf = !isset($rule['any_of']) || (bool)$rule['any_of']; // default true

        $params = [];

        if ($anyOf || count($stageIds) === 1) {
            // ANY OF: basta UM registro em funnel_stage_history bater
            $placeholders = implode(',', array_fill(0, count($stageIds), '?'));

            $extra = '';
            if ($funnelId !== null) {
                $extra .= ' AND fs.funnel_id = ?';
            }
            if (!empty($since)) {
                $extra .= ' AND fsh.created_at >= ?';
            }
            if (!empty($until)) {
                $extra .= ' AND fsh.created_at <= ?';
            }

            $existsSql = "EXISTS (
                SELECT 1 FROM conversations conv
                JOIN funnel_stage_history fsh ON fsh.conversation_id = conv.id"
                . ($funnelId !== null ? "\n                JOIN funnel_stages fs ON fs.id = fsh.to_stage_id" : '')
                . "\n                WHERE conv.contact_id = c.id
                  AND fsh.to_stage_id IN ({$placeholders}){$extra}
            )";

            $params = $stageIds;
            if ($funnelId !== null) {
                $params[] = $funnelId;
            }
            if (!empty($since)) {
                $params[] = self::normalizeDate($since, false);
            }
            if (!empty($until)) {
                $params[] = self::normalizeDate($until, true);
            }

            $clause = $negate ? "NOT {$existsSql}" : $existsSql;
            return [$clause, $params];
        }

        // ALL OF: contato precisa ter passado por TODAS as etapas listadas
        // Construímos um EXISTS por etapa e juntamos com AND.
        $existsClauses = [];
        foreach ($stageIds as $stageId) {
            $extra = '';
            $localParams = [$stageId];

            if ($funnelId !== null) {
                $extra .= ' AND fs.funnel_id = ?';
                $localParams[] = $funnelId;
            }
            if (!empty($since)) {
                $extra .= ' AND fsh.created_at >= ?';
                $localParams[] = self::normalizeDate($since, false);
            }
            if (!empty($until)) {
                $extra .= ' AND fsh.created_at <= ?';
                $localParams[] = self::normalizeDate($until, true);
            }

            $existsClauses[] = "EXISTS (
                SELECT 1 FROM conversations conv
                JOIN funnel_stage_history fsh ON fsh.conversation_id = conv.id"
                . ($funnelId !== null ? "\n                JOIN funnel_stages fs ON fs.id = fsh.to_stage_id" : '')
                . "\n                WHERE conv.contact_id = c.id
                  AND fsh.to_stage_id = ?{$extra}
            )";

            $params = array_merge($params, $localParams);
        }

        $clause = '(' . implode(' AND ', $existsClauses) . ')';
        return [$negate ? "NOT {$clause}" : $clause, $params];
    }

    /**
     * Regra: contato tem conversa atualmente em uma das etapas indicadas
     */
    private static function buildCurrentlyInStage(array $rule): ?array
    {
        $stageIds = self::sanitizeIds($rule['stage_ids'] ?? []);
        if (empty($stageIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($stageIds), '?'));

        $clause = "EXISTS (
            SELECT 1 FROM conversations conv
            WHERE conv.contact_id = c.id
              AND conv.funnel_stage_id IN ({$placeholders})
        )";

        return [$clause, $stageIds];
    }

    /**
     * Regra: contato (não) tem uma das tags indicadas
     *
     * Tags neste sistema vivem em conversation_tags (não há contact_tags).
     * Considera-se que "o contato tem a tag" se qualquer conversa dele a tem.
     */
    private static function buildHasTag(array $rule, bool $negate): ?array
    {
        $tagIds = self::sanitizeIds($rule['tag_ids'] ?? []);
        if (empty($tagIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));

        $existsSql = "EXISTS (
            SELECT 1
            FROM conversations conv
            JOIN conversation_tags ctag ON ctag.conversation_id = conv.id
            WHERE conv.contact_id = c.id
              AND ctag.tag_id IN ({$placeholders})
        )";

        return [$negate ? "NOT {$existsSql}" : $existsSql, $tagIds];
    }

    /**
     * Regra: contato tem conversa em algum dos canais indicados
     */
    private static function buildChannel(array $rule): ?array
    {
        $channels = $rule['channels'] ?? [];
        if (!is_array($channels) || empty($channels)) {
            return null;
        }

        $channels = array_values(array_filter(array_map('strval', $channels), 'strlen'));
        if (empty($channels)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($channels), '?'));

        $clause = "EXISTS (
            SELECT 1 FROM conversations conv
            WHERE conv.contact_id = c.id
              AND conv.channel IN ({$placeholders})
        )";

        return [$clause, $channels];
    }

    /**
     * Regra: contact.created_at em um intervalo
     */
    private static function buildCreatedBetween(array $rule): ?array
    {
        $since = $rule['since'] ?? null;
        $until = $rule['until'] ?? null;

        if (empty($since) && empty($until)) {
            return null;
        }

        $parts = [];
        $params = [];

        if (!empty($since)) {
            $parts[] = 'c.created_at >= ?';
            $params[] = self::normalizeDate($since, false);
        }
        if (!empty($until)) {
            $parts[] = 'c.created_at <= ?';
            $params[] = self::normalizeDate($until, true);
        }

        return ['(' . implode(' AND ', $parts) . ')', $params];
    }

    /**
     * Aceita só inteiros positivos, devolve array reindexado
     */
    private static function sanitizeIds($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $clean = [];
        foreach ($values as $v) {
            $i = (int)$v;
            if ($i > 0) {
                $clean[] = $i;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * Normaliza data 'YYYY-MM-DD' para datetime de início ou fim do dia
     */
    private static function normalizeDate(string $date, bool $endOfDay): string
    {
        // Já vem com hora?
        if (strpos($date, ' ') !== false || strpos($date, 'T') !== false) {
            return str_replace('T', ' ', $date);
        }
        return $endOfDay ? "{$date} 23:59:59" : "{$date} 00:00:00";
    }
}
