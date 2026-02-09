<?php
/**
 * Model UserConversationTab
 * Gerencia as abas personalizadas de cada agente na listagem de conversas.
 * Suporta abas simples (tag) e avançadas (condições múltiplas: tags + funis + etapas + departamentos).
 */

namespace App\Models;

use App\Helpers\Database;

class UserConversationTab extends Model
{
    protected string $table = 'user_conversation_tabs';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'tag_id', 'name', 'color', 'conditions', 'match_type', 'position'];
    protected bool $timestamps = false;

    /**
     * Obter todas as abas de um usuário com dados da tag
     */
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT uct.*, t.name as tag_name, t.color as tag_color, t.description as tag_description
                FROM user_conversation_tabs uct
                LEFT JOIN tags t ON uct.tag_id = t.id
                WHERE uct.user_id = ?
                ORDER BY uct.position ASC, uct.id ASC";
        return Database::fetchAll($sql, [$userId]);
    }

    /**
     * Obter abas com contagem de conversas (apenas abertas)
     * Para abas simples (só tag_id): usa subquery eficiente
     * Para abas avançadas (com conditions): calcula em PHP
     */
    public static function getByUserWithCounts(int $userId): array
    {
        // Buscar todas as abas
        $sql = "SELECT uct.*, t.name as tag_name, t.color as tag_color, t.description as tag_description
                FROM user_conversation_tabs uct
                LEFT JOIN tags t ON uct.tag_id = t.id
                WHERE uct.user_id = ?
                ORDER BY uct.position ASC, uct.id ASC";
        $tabs = Database::fetchAll($sql, [$userId]);

        foreach ($tabs as &$tab) {
            $conditions = !empty($tab['conditions']) ? json_decode($tab['conditions'], true) : null;
            $isAdvanced = !empty($conditions) && self::hasNonEmptyConditions($conditions);

            if (!$isAdvanced && !empty($tab['tag_id'])) {
                // Aba simples: filtrar por tag_id principal
                $countSql = "SELECT COUNT(DISTINCT ct.conversation_id) as cnt
                             FROM conversation_tags ct
                             INNER JOIN conversations c ON ct.conversation_id = c.id
                             WHERE ct.tag_id = ? AND c.status = 'open'";
                $result = Database::fetch($countSql, [$tab['tag_id']]);
                $tab['conversation_count'] = $result ? (int)$result['cnt'] : 0;

                $awaitSql = "SELECT COUNT(DISTINCT ct.conversation_id) as cnt
                             FROM conversation_tags ct
                             INNER JOIN conversations c ON ct.conversation_id = c.id
                             WHERE ct.tag_id = ? AND c.status = 'open'
                               AND (SELECT m.sender_type FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) = 'contact'";
                $awaitResult = Database::fetch($awaitSql, [$tab['tag_id']]);
                $tab['awaiting_count'] = $awaitResult ? (int)$awaitResult['cnt'] : 0;
            } elseif ($isAdvanced || !empty($tab['tag_id'])) {
                // Aba avançada: construir query dinâmica
                $matchType = $tab['match_type'] ?? 'AND';
                $allConditions = self::mergeTabConditions($tab);

                list($whereSql, $whereParams) = self::buildConditionsSQL($allConditions, $matchType);

                if (!empty($whereSql)) {
                    $countSql = "SELECT COUNT(DISTINCT c.id) as cnt FROM conversations c WHERE c.status = 'open' AND ({$whereSql})";
                    $result = Database::fetch($countSql, $whereParams);
                    $tab['conversation_count'] = $result ? (int)$result['cnt'] : 0;

                    $awaitSql = "SELECT COUNT(DISTINCT c.id) as cnt FROM conversations c 
                                 WHERE c.status = 'open' AND ({$whereSql})
                                   AND (SELECT m.sender_type FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) = 'contact'";
                    $awaitResult = Database::fetch($awaitSql, $whereParams);
                    $tab['awaiting_count'] = $awaitResult ? (int)$awaitResult['cnt'] : 0;
                } else {
                    $tab['conversation_count'] = 0;
                    $tab['awaiting_count'] = 0;
                }
            } else {
                // Aba sem tag e sem condições (não deveria existir, mas tratar)
                $tab['conversation_count'] = 0;
                $tab['awaiting_count'] = 0;
            }
        }

        return $tabs;
    }

    /**
     * Verifica se o JSON de condições tem pelo menos uma condição não-vazia
     */
    private static function hasNonEmptyConditions(?array $conditions): bool
    {
        if (empty($conditions)) return false;
        foreach (['tag_ids', 'funnel_ids', 'funnel_stage_ids', 'department_ids'] as $key) {
            if (!empty($conditions[$key]) && is_array($conditions[$key]) && count($conditions[$key]) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mesclar tag_id principal com as condições JSON para gerar um array unificado
     */
    public static function mergeTabConditions(array $tab): array
    {
        $conditions = !empty($tab['conditions']) ? json_decode($tab['conditions'], true) : [];
        if (!is_array($conditions)) $conditions = [];

        $merged = [
            'tag_ids' => $conditions['tag_ids'] ?? [],
            'funnel_ids' => $conditions['funnel_ids'] ?? [],
            'funnel_stage_ids' => $conditions['funnel_stage_ids'] ?? [],
            'department_ids' => $conditions['department_ids'] ?? [],
        ];

        // Adicionar tag_id principal se presente e não já incluso
        if (!empty($tab['tag_id'])) {
            $tagId = (int) $tab['tag_id'];
            if (!in_array($tagId, $merged['tag_ids'])) {
                array_unshift($merged['tag_ids'], $tagId);
            }
        }

        return $merged;
    }

    /**
     * Construir SQL WHERE baseado nas condições e match_type
     * Retorna [string $sql, array $params]
     */
    public static function buildConditionsSQL(array $conditions, string $matchType = 'AND'): array
    {
        $parts = [];
        $params = [];

        // Tags
        if (!empty($conditions['tag_ids'])) {
            $tagIds = array_map('intval', $conditions['tag_ids']);
            if ($matchType === 'AND') {
                // AND: conversa precisa ter TODAS as tags
                foreach ($tagIds as $tagId) {
                    $parts[] = "EXISTS (SELECT 1 FROM conversation_tags ctt WHERE ctt.conversation_id = c.id AND ctt.tag_id = ?)";
                    $params[] = $tagId;
                }
            } else {
                // OR: conversa precisa ter QUALQUER uma das tags
                $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
                $parts[] = "EXISTS (SELECT 1 FROM conversation_tags ctt WHERE ctt.conversation_id = c.id AND ctt.tag_id IN ({$placeholders}))";
                $params = array_merge($params, $tagIds);
            }
        }

        // Funis
        if (!empty($conditions['funnel_ids'])) {
            $funnelIds = array_map('intval', $conditions['funnel_ids']);
            $placeholders = implode(',', array_fill(0, count($funnelIds), '?'));
            $parts[] = "c.funnel_id IN ({$placeholders})";
            $params = array_merge($params, $funnelIds);
        }

        // Etapas do funil
        if (!empty($conditions['funnel_stage_ids'])) {
            $stageIds = array_map('intval', $conditions['funnel_stage_ids']);
            $placeholders = implode(',', array_fill(0, count($stageIds), '?'));
            $parts[] = "c.funnel_stage_id IN ({$placeholders})";
            $params = array_merge($params, $stageIds);
        }

        // Departamentos
        if (!empty($conditions['department_ids'])) {
            $deptIds = array_map('intval', $conditions['department_ids']);
            $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
            $parts[] = "c.department_id IN ({$placeholders})";
            $params = array_merge($params, $deptIds);
        }

        if (empty($parts)) {
            return ['', []];
        }

        $joiner = ($matchType === 'OR') ? ' OR ' : ' AND ';
        $sql = implode($joiner, $parts);

        return [$sql, $params];
    }

    /**
     * Retorna array de filtros compatível com Conversation::getAll()
     * Usado para resolver tab_id no controller
     */
    public static function getTabFilters(array $tab): array
    {
        $conditions = self::mergeTabConditions($tab);
        $matchType = $tab['match_type'] ?? 'AND';

        return [
            'tab_conditions' => array_merge($conditions, ['match_type' => $matchType])
        ];
    }

    /**
     * Adicionar aba para usuário (suporta abas simples e avançadas)
     */
    public static function addTab(int $userId, ?int $tagId = null, ?string $name = null, ?string $color = null, ?array $conditions = null, string $matchType = 'AND', ?int $position = null): bool
    {
        try {
            if ($position === null) {
                $sql = "SELECT COALESCE(MAX(position), -1) + 1 as next_pos 
                        FROM user_conversation_tabs WHERE user_id = ?";
                $result = Database::fetch($sql, [$userId]);
                $position = $result ? (int)$result['next_pos'] : 0;
            }

            $conditionsJson = null;
            if ($conditions !== null && self::hasNonEmptyConditions($conditions)) {
                $conditionsJson = json_encode($conditions);
            }

            $sql = "INSERT INTO user_conversation_tabs (user_id, tag_id, name, color, conditions, match_type, position) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            Database::execute($sql, [
                $userId,
                $tagId,
                $name,
                $color,
                $conditionsJson,
                $matchType,
                $position
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao adicionar aba: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualizar aba existente
     */
    public static function updateTab(int $tabId, int $userId, array $data): bool
    {
        try {
            $sets = [];
            $params = [];

            if (array_key_exists('tag_id', $data)) {
                $sets[] = 'tag_id = ?';
                $params[] = $data['tag_id'];
            }
            if (array_key_exists('name', $data)) {
                $sets[] = 'name = ?';
                $params[] = $data['name'];
            }
            if (array_key_exists('color', $data)) {
                $sets[] = 'color = ?';
                $params[] = $data['color'];
            }
            if (array_key_exists('conditions', $data)) {
                $conditions = $data['conditions'];
                $sets[] = 'conditions = ?';
                $params[] = ($conditions !== null && self::hasNonEmptyConditions($conditions))
                    ? json_encode($conditions)
                    : null;
            }
            if (array_key_exists('match_type', $data)) {
                $sets[] = 'match_type = ?';
                $params[] = in_array($data['match_type'], ['AND', 'OR']) ? $data['match_type'] : 'AND';
            }

            if (empty($sets)) return false;

            $params[] = $tabId;
            $params[] = $userId;

            $sql = "UPDATE user_conversation_tabs SET " . implode(', ', $sets) . " WHERE id = ? AND user_id = ?";
            Database::execute($sql, $params);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao atualizar aba: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover aba do usuário (por ID da aba)
     */
    public static function removeTabById(int $tabId, int $userId): bool
    {
        try {
            $sql = "DELETE FROM user_conversation_tabs WHERE id = ? AND user_id = ?";
            Database::execute($sql, [$tabId, $userId]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao remover aba: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover aba do usuário (por tag_id - compatibilidade)
     */
    public static function removeTab(int $userId, int $tagId): bool
    {
        try {
            $sql = "DELETE FROM user_conversation_tabs WHERE user_id = ? AND tag_id = ?";
            Database::execute($sql, [$userId, $tagId]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao remover aba: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reordenar abas do usuário
     */
    public static function reorder(int $userId, array $tabIds): bool
    {
        try {
            $db = Database::getInstance();
            $db->beginTransaction();

            foreach ($tabIds as $position => $tabId) {
                $sql = "UPDATE user_conversation_tabs SET position = ? WHERE id = ? AND user_id = ?";
                Database::execute($sql, [$position, $tabId, $userId]);
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            if (isset($db)) $db->rollBack();
            error_log("Erro ao reordenar abas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar se tag já é aba do usuário
     */
    public static function isTab(int $userId, int $tagId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM user_conversation_tabs WHERE user_id = ? AND tag_id = ?";
        $result = Database::fetch($sql, [$userId, $tagId]);
        return $result && $result['count'] > 0;
    }

    /**
     * Buscar aba por ID
     */
    public static function findTab(int $tabId): ?array
    {
        $sql = "SELECT uct.*, t.name as tag_name, t.color as tag_color, t.description as tag_description
                FROM user_conversation_tabs uct
                LEFT JOIN tags t ON uct.tag_id = t.id
                WHERE uct.id = ?";
        $result = Database::fetch($sql, [$tabId]);
        return $result ?: null;
    }
}
