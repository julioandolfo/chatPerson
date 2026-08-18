<?php
/**
 * Model ConversationAnalysisBatch
 * Um lote de análise de conversas por coorte.
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationAnalysisBatch extends Model
{
    protected string $table = 'conversation_analysis_batches';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name',
        'context_question',
        'filters',
        'date_from',
        'date_to',
        'status',
        'total_conversations',
        'analyzed_conversations',
        'failed_conversations',
        'metrics',
        'summary',
        'model_map',
        'model_reduce',
        'tokens_used',
        'cost',
        'cost_limit',
        'error_message',
        'created_by',
        'created_at',
        'started_at',
        'completed_at'
    ];
    protected array $jsonFields = ['filters', 'metrics', 'summary'];
    protected bool $timestamps = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * As tabelas da análise existem? (migration 157)
     *
     * Sem esta checagem, uma instalação que ainda não rodou a migration
     * derruba a tela inteira com PDOException em vez de avisar o que falta.
     */
    public static function tableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = !empty(Database::fetch("SHOW TABLES LIKE 'conversation_analysis_batches'"));
            } catch (\Exception $e) {
                \App\Helpers\Logger::error('ConversationAnalysisBatch::tableExists - ' . $e->getMessage());
                $exists = false;
            }
        }

        return $exists;
    }

    /**
     * Listar lotes com o nome de quem criou
     */
    public static function listRecent(int $limit = 30, ?int $createdBy = null): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $sql = "SELECT b.*, u.name AS created_by_name
                FROM conversation_analysis_batches b
                LEFT JOIN users u ON u.id = b.created_by
                WHERE 1=1";
        $params = [];

        if ($createdBy !== null) {
            $sql .= " AND b.created_by = ?";
            $params[] = $createdBy;
        }

        $sql .= " ORDER BY b.created_at DESC LIMIT " . (int)$limit;

        return Database::fetchAll($sql, $params);
    }

    /**
     * Próximo lote a processar (fila do cron)
     */
    public static function getNextPending(): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        return Database::fetch(
            "SELECT * FROM conversation_analysis_batches
             WHERE status IN ('pending', 'running')
             ORDER BY (status = 'running') DESC, created_at ASC
             LIMIT 1"
        );
    }

    /**
     * Decodificar campos JSON de um lote
     */
    public static function decode(array $batch): array
    {
        foreach (['filters', 'metrics', 'summary'] as $field) {
            if (!empty($batch[$field]) && is_string($batch[$field])) {
                $decoded = json_decode($batch[$field], true);
                $batch[$field] = is_array($decoded) ? $decoded : [];
            } elseif (empty($batch[$field])) {
                $batch[$field] = [];
            }
        }

        return $batch;
    }

    /**
     * Somar custo/tokens já gastos no lote (a partir dos itens)
     */
    public static function refreshTotals(int $batchId): void
    {
        $totals = Database::fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'analyzed') AS analyzed,
                SUM(status = 'failed') AS failed,
                COALESCE(SUM(tokens_used), 0) AS tokens,
                COALESCE(SUM(cost), 0) AS cost
             FROM conversation_analysis_items
             WHERE batch_id = ?",
            [$batchId]
        );

        if (!$totals) {
            return;
        }

        Database::execute(
            "UPDATE conversation_analysis_batches
             SET analyzed_conversations = ?, failed_conversations = ?, tokens_used = ?, cost = ?
             WHERE id = ?",
            [
                (int)($totals['analyzed'] ?? 0),
                (int)($totals['failed'] ?? 0),
                (int)($totals['tokens'] ?? 0),
                round((float)($totals['cost'] ?? 0), 4),
                $batchId
            ]
        );
    }
}
