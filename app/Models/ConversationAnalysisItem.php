<?php
/**
 * Model ConversationAnalysisItem
 * Resultado da análise de UMA conversa dentro de um lote.
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationAnalysisItem extends Model
{
    protected string $table = 'conversation_analysis_items';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'batch_id',
        'conversation_id',
        'metrics',
        'analysis',
        'outcome',
        'primary_reason',
        'drop_off_stage_id',
        'who_stopped',
        'agent_id',
        'confidence',
        'status',
        'tokens_used',
        'cost',
        'error',
        'created_at',
        'analyzed_at'
    ];
    protected array $jsonFields = ['metrics', 'analysis'];
    protected bool $timestamps = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ANALYZED = 'analyzed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    /** Tempo após o qual um item travado em 'processing' volta para a fila */
    public const STALE_PROCESSING_MINUTES = 30;

    /**
     * Reservar o item para este worker.
     *
     * Sem isso, dois crons rodando ao mesmo tempo analisariam a mesma conversa
     * duas vezes — e pagariam duas vezes por ela.
     *
     * @return bool true se este worker conseguiu a reserva
     */
    public static function claim(int $itemId): bool
    {
        // analyzed_at marca o instante da reserva; é sobrescrito ao concluir
        $affected = Database::execute(
            "UPDATE conversation_analysis_items
             SET status = 'processing', analyzed_at = NOW()
             WHERE id = ? AND status = 'pending'",
            [$itemId]
        );

        return $affected > 0;
    }

    /**
     * Devolver à fila os itens travados em 'processing' (worker morreu no meio)
     */
    public static function releaseStale(int $batchId): int
    {
        return Database::execute(
            "UPDATE conversation_analysis_items
             SET status = 'pending'
             WHERE batch_id = ?
               AND status = 'processing'
               AND analyzed_at < DATE_SUB(NOW(), INTERVAL " . self::STALE_PROCESSING_MINUTES . " MINUTE)",
            [$batchId]
        );
    }

    /**
     * Enfileirar as conversas da coorte (idempotente pela UNIQUE KEY)
     */
    public static function enqueue(int $batchId, array $conversationIds): int
    {
        if (empty($conversationIds)) {
            return 0;
        }

        $inserted = 0;

        // Inserção em blocos para não estourar o limite de placeholders
        foreach (array_chunk($conversationIds, 200) as $chunk) {
            $values = [];
            $params = [];

            foreach ($chunk as $conversationId) {
                $values[] = "(?, ?, 'pending', NOW())";
                $params[] = $batchId;
                $params[] = (int)$conversationId;
            }

            $sql = "INSERT IGNORE INTO conversation_analysis_items
                        (batch_id, conversation_id, status, created_at)
                    VALUES " . implode(', ', $values);

            $inserted += Database::execute($sql, $params);
        }

        return $inserted;
    }

    /**
     * Próximas conversas pendentes do lote
     */
    public static function getPending(int $batchId, int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT * FROM conversation_analysis_items
             WHERE batch_id = ? AND status = 'pending'
             ORDER BY id ASC
             LIMIT " . (int)$limit,
            [$batchId]
        );
    }

    /**
     * Itens que ainda faltam terminar — inclui os reservados por outro worker,
     * para que o lote não seja fechado com trabalho em andamento.
     */
    public static function countUnfinished(int $batchId): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total
             FROM conversation_analysis_items
             WHERE batch_id = ? AND status IN ('pending', 'processing')",
            [$batchId]
        );

        return (int)($row['total'] ?? 0);
    }

    /**
     * Itens analisados com dados da conversa/contato (para o drilldown)
     */
    public static function getAnalyzed(int $batchId, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT i.*,
                       ct.name AS contact_name,
                       ct.phone AS contact_phone,
                       u.name AS agent_name,
                       fs.name AS drop_off_stage_name
                FROM conversation_analysis_items i
                INNER JOIN conversations c ON c.id = i.conversation_id
                LEFT JOIN contacts ct ON ct.id = c.contact_id
                LEFT JOIN users u ON u.id = i.agent_id
                LEFT JOIN funnel_stages fs ON fs.id = i.drop_off_stage_id
                WHERE i.batch_id = ? AND i.status = 'analyzed'";
        $params = [$batchId];

        if (!empty($filters['primary_reason'])) {
            $sql .= " AND i.primary_reason = ?";
            $params[] = $filters['primary_reason'];
        }

        if (!empty($filters['drop_off_stage_id'])) {
            $sql .= " AND i.drop_off_stage_id = ?";
            $params[] = (int)$filters['drop_off_stage_id'];
        }

        if (!empty($filters['agent_id'])) {
            $sql .= " AND i.agent_id = ?";
            $params[] = (int)$filters['agent_id'];
        }

        if (!empty($filters['who_stopped'])) {
            $sql .= " AND i.who_stopped = ?";
            $params[] = $filters['who_stopped'];
        }

        if (!empty($filters['outcome'])) {
            $sql .= " AND i.outcome = ?";
            $params[] = $filters['outcome'];
        }

        $sql .= " ORDER BY i.confidence DESC, i.id ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        return Database::fetchAll($sql, $params);
    }

    /**
     * Decodificar campos JSON
     */
    public static function decode(array $item): array
    {
        foreach (['metrics', 'analysis'] as $field) {
            if (!empty($item[$field]) && is_string($item[$field])) {
                $decoded = json_decode($item[$field], true);
                $item[$field] = is_array($decoded) ? $decoded : [];
            } elseif (empty($item[$field])) {
                $item[$field] = [];
            }
        }

        return $item;
    }
}
