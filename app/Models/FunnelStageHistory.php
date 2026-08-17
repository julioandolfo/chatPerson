<?php
/**
 * Model FunnelStageHistory
 * Histórico de transições de etapa das conversas.
 *
 * Fonte de verdade para responder "a conversa passou pela etapa X".
 * Toda mudança de funnel_stage_id deve passar por FunnelService::recordStageTransition().
 */

namespace App\Models;

use App\Helpers\Database;

class FunnelStageHistory extends Model
{
    protected string $table = 'funnel_stage_history';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id',
        'funnel_id',
        'from_stage_id',
        'to_stage_id',
        'changed_by',
        'changed_by_ai_agent_id',
        'source',
        'created_at'
    ];
    protected bool $timestamps = false;

    /**
     * Origens válidas de uma transição
     */
    public const SOURCES = ['manual', 'automation', 'kanban_agent', 'ai_tool', 'api', 'system', 'backfill'];

    /**
     * Verificar se a tabela existe (instalações antigas podem não ter rodado a migration)
     */
    public static function tableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = !empty(Database::fetch("SHOW TABLES LIKE 'funnel_stage_history'"));
            } catch (\Exception $e) {
                \App\Helpers\Logger::error("FunnelStageHistory::tableExists - " . $e->getMessage());
                $exists = false;
            }
        }

        return $exists;
    }

    /**
     * Registrar uma transição de etapa
     *
     * @return int ID do registro criado (0 se não gravou)
     */
    public static function record(
        int $conversationId,
        ?int $fromStageId,
        int $toStageId,
        ?int $changedBy = null,
        string $source = 'system',
        ?int $changedByAiAgentId = null,
        ?int $funnelId = null
    ): int {
        if (!self::tableExists()) {
            return 0;
        }

        // Movimento nulo não é transição
        if ($fromStageId !== null && $fromStageId === $toStageId) {
            return 0;
        }

        try {
            if ($funnelId === null) {
                $stage = Database::fetch("SELECT funnel_id FROM funnel_stages WHERE id = ?", [$toStageId]);
                $funnelId = $stage ? (int)$stage['funnel_id'] : null;
            }

            return Database::insert(
                "INSERT INTO funnel_stage_history
                    (conversation_id, funnel_id, from_stage_id, to_stage_id, changed_by, changed_by_ai_agent_id, source, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $conversationId,
                    $funnelId,
                    $fromStageId ?: null,
                    $toStageId,
                    $changedBy ?: null,
                    $changedByAiAgentId ?: null,
                    in_array($source, self::SOURCES, true) ? $source : 'system'
                ]
            );
        } catch (\Exception $e) {
            // Histórico nunca pode derrubar a movimentação em si
            \App\Helpers\Logger::error(
                "FunnelStageHistory::record - Falha ao registrar transição da conversa {$conversationId}: " . $e->getMessage()
            );
            return 0;
        }
    }

    /**
     * Trilha completa de etapas de uma conversa
     */
    public static function getConversationPath(int $conversationId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return Database::fetchAll(
            "SELECT h.*,
                    fs_from.name AS from_stage_name,
                    fs_to.name AS to_stage_name,
                    fs_to.color AS to_stage_color,
                    u.name AS changed_by_name
             FROM funnel_stage_history h
             LEFT JOIN funnel_stages fs_from ON fs_from.id = h.from_stage_id
             LEFT JOIN funnel_stages fs_to ON fs_to.id = h.to_stage_id
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.conversation_id = ?
             ORDER BY h.created_at ASC, h.id ASC",
            [$conversationId]
        );
    }

    /**
     * Etapa vigente em um dado instante (para saber onde a conversa estava quando parou)
     */
    public static function getStageAt(int $conversationId, string $datetime): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        return Database::fetch(
            "SELECT h.to_stage_id AS stage_id, fs.name AS stage_name, h.created_at
             FROM funnel_stage_history h
             LEFT JOIN funnel_stages fs ON fs.id = h.to_stage_id
             WHERE h.conversation_id = ? AND h.created_at <= ?
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT 1",
            [$conversationId, $datetime]
        );
    }

    /**
     * Data do registro mais antigo — usada para avisar ao usuário até onde o
     * filtro "passou pela etapa" realmente enxerga.
     */
    public static function getCoverageStart(): ?string
    {
        if (!self::tableExists()) {
            return null;
        }

        $row = Database::fetch("SELECT MIN(created_at) AS first_event FROM funnel_stage_history");
        return $row['first_event'] ?? null;
    }
}
