<?php
/**
 * Model Campaign
 * Campanhas de disparo em massa
 */

namespace App\Models;

use App\Helpers\Database;

class Campaign extends Model
{
    protected string $table = 'campaigns';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'description',
        'target_type', 'contact_list_id', 'filter_config',
        'message_template_id', 'message_content', 'message_variables', 'attachments',
        'channel', 'integration_account_ids', 'rotation_strategy',
        'scheduled_at', 'send_strategy', 'send_rate_per_minute', 'send_interval_seconds',
        'send_window_start', 'send_window_end', 'send_days', 'timezone',
        'funnel_id', 'initial_stage_id', 'auto_move_on_reply', 'reply_stage_id',
        'status', 'priority',
        'skip_duplicates', 'skip_recent_conversations', 'skip_recent_hours',
        'respect_blacklist', 'create_conversation', 'tag_on_send',
        'total_contacts', 'total_sent', 'total_delivered', 'total_read',
        'total_replied', 'total_failed', 'total_skipped',
        'started_at', 'completed_at', 'paused_at', 'cancelled_at', 'last_processed_at',
        'created_by', 'updated_by'
    ];
    protected bool $timestamps = true;

    /**
     * Obter campanhas ativas (rodando)
     */
    public static function getActive(): array
    {
        return self::where('status', '=', 'running');
    }

    /**
     * Obter campanhas por status
     */
    public static function getByStatus(string $status): array
    {
        return self::where('status', '=', $status);
    }

    /**
     * Obter campanha com relacionamentos
     */
    public static function findWithRelations(int $id): ?array
    {
        $sql = "SELECT c.*,
                       cl.name as list_name,
                       cl.total_contacts as list_total_contacts,
                       mt.name as template_name,
                       f.name as funnel_name,
                       fs.name as stage_name,
                       u.name as created_by_name
                FROM campaigns c
                LEFT JOIN contact_lists cl ON c.contact_list_id = cl.id
                LEFT JOIN message_templates mt ON c.message_template_id = mt.id
                LEFT JOIN funnels f ON c.funnel_id = f.id
                LEFT JOIN funnel_stages fs ON c.initial_stage_id = fs.id
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.id = ?";
        
        return Database::fetch($sql, [$id]);
    }

    /**
     * Obter contas de integração (array de IDs)
     */
    public static function getIntegrationAccounts(int $campaignId): array
    {
        $campaign = self::find($campaignId);
        if (!$campaign || empty($campaign['integration_account_ids'])) {
            return [];
        }

        $accountIds = json_decode($campaign['integration_account_ids'], true);
        if (!is_array($accountIds)) {
            return [];
        }

        return $accountIds;
    }

    /**
     * Incrementar contador de envios
     */
    public static function incrementSent(int $campaignId, int $count = 1): bool
    {
        $sql = "UPDATE campaigns SET total_sent = total_sent + ? WHERE id = ?";
        return Database::execute($sql, [$count, $campaignId]) > 0;
    }

    /**
     * Incrementar contador de entregas
     */
    public static function incrementDelivered(int $campaignId, int $count = 1): bool
    {
        $sql = "UPDATE campaigns SET total_delivered = total_delivered + ? WHERE id = ?";
        return Database::execute($sql, [$count, $campaignId]) > 0;
    }

    /**
     * Incrementar contador de leituras
     */
    public static function incrementRead(int $campaignId, int $count = 1): bool
    {
        $sql = "UPDATE campaigns SET total_read = total_read + ? WHERE id = ?";
        return Database::execute($sql, [$count, $campaignId]) > 0;
    }

    /**
     * Incrementar contador de respostas
     */
    public static function incrementReplied(int $campaignId, int $count = 1): bool
    {
        $sql = "UPDATE campaigns SET total_replied = total_replied + ? WHERE id = ?";
        return Database::execute($sql, [$count, $campaignId]) > 0;
    }

    /**
     * Incrementar contador de falhas
     */
    public static function incrementFailed(int $campaignId, int $count = 1): bool
    {
        $sql = "UPDATE campaigns SET total_failed = total_failed + ? WHERE id = ?";
        return Database::execute($sql, [$count, $campaignId]) > 0;
    }

    /**
     * Incrementar contador de pulados
     */
    public static function incrementSkipped(int $campaignId, int $count = 1): bool
    {
        $sql = "UPDATE campaigns SET total_skipped = total_skipped + ? WHERE id = ?";
        return Database::execute($sql, [$count, $campaignId]) > 0;
    }

    /**
     * Atualizar último processamento
     */
    public static function updateLastProcessed(int $campaignId): bool
    {
        $sql = "UPDATE campaigns SET last_processed_at = NOW() WHERE id = ?";
        return Database::execute($sql, [$campaignId]) > 0;
    }

    /**
     * Verificar se campanha está completa
     */
    public static function isCompleted(int $campaignId): bool
    {
        $campaign = self::find($campaignId);
        if (!$campaign) {
            return false;
        }

        $totalProcessed = ($campaign['total_sent'] ?? 0) + 
                         ($campaign['total_failed'] ?? 0) + 
                         ($campaign['total_skipped'] ?? 0);

        return $totalProcessed >= ($campaign['total_contacts'] ?? 0);
    }

    /**
     * Calcular progresso (0-100)
     */
    public static function getProgress(int $campaignId): float
    {
        $campaign = self::find($campaignId);
        if (!$campaign || empty($campaign['total_contacts'])) {
            return 0.0;
        }

        $totalProcessed = ($campaign['total_sent'] ?? 0) + 
                         ($campaign['total_failed'] ?? 0) + 
                         ($campaign['total_skipped'] ?? 0);

        return round(($totalProcessed / $campaign['total_contacts']) * 100, 2);
    }
}
