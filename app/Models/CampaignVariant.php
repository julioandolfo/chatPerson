<?php
/**
 * Model CampaignVariant
 * Variantes de A/B Testing
 */

namespace App\Models;

use App\Helpers\Database;

class CampaignVariant extends Model
{
    protected string $table = 'campaign_variants';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'campaign_id',
        'variant_name',
        'message_content',
        'percentage',
        'variant_type',
        'message_type',
        'message_variables',
        'total_sent',
        'total_delivered',
        'total_read',
        'total_replied',
        'delivery_rate',
        'read_rate',
        'reply_rate'
    ];
    /**
     * Buscar todas as variantes
     */
    public static function all(): array
    {
        $sql = "SELECT * FROM campaign_variants ORDER BY created_at DESC";
        return Database::fetchAll($sql, []);
    }
    
    /**
     * Buscar por ID
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT * FROM campaign_variants WHERE id = ? LIMIT 1";
        return Database::fetch($sql, [$id]);
    }
    
    /**
     * Criar variante
     */
    public static function create(array $data): int
    {
        $allowed = ['campaign_id', 'variant_name', 'message_content', 'percentage',
                    'variant_type', 'message_type', 'message_variables'];
        $fields = [];
        $values = [];
        $placeholders = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field;
                $values[] = $data[$field];
                $placeholders[] = '?';
            }
        }

        $sql = "INSERT INTO campaign_variants (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        Database::execute($sql, $values);

        return (int)Database::getInstance()->lastInsertId();
    }
    
    /**
     * Buscar variantes de uma campanha
     */
    public static function getByCampaign(int $campaignId): array
    {
        $sql = "SELECT * FROM campaign_variants WHERE campaign_id = ? ORDER BY variant_name ASC";
        return Database::fetchAll($sql, [$campaignId]);
    }

    /**
     * Buscar variantes de uma campanha por tipo (ab_test | round_robin)
     */
    public static function getByCampaignAndType(int $campaignId, string $type): array
    {
        $sql = "SELECT * FROM campaign_variants WHERE campaign_id = ? AND variant_type = ? ORDER BY id ASC";
        return Database::fetchAll($sql, [$campaignId, $type]);
    }

    /**
     * Deletar variantes round-robin de uma campanha (para sincronização no update)
     */
    public static function deleteRoundRobinByCampaign(int $campaignId): bool
    {
        $sql = "DELETE FROM campaign_variants WHERE campaign_id = ? AND variant_type = 'round_robin'";
        return Database::execute($sql, [$campaignId]) >= 0;
    }

    /**
     * Obter estatísticas round-robin computadas a partir de campaign_messages
     */
    public static function getRoundRobinStatsForCampaign(int $campaignId): array
    {
        $sql = "SELECT 
                    cv.id,
                    cv.variant_name,
                    cv.message_content,
                    cv.message_type,
                    cv.message_variables,
                    cv.total_sent,
                    COALESCE(SUM(CASE WHEN cm.status = 'replied' THEN 1 ELSE 0 END), 0) as total_replied,
                    COALESCE(SUM(CASE WHEN cm.status IN ('delivered','read','replied') THEN 1 ELSE 0 END), 0) as total_delivered,
                    COALESCE(SUM(CASE WHEN cm.status = 'read' OR cm.status = 'replied' THEN 1 ELSE 0 END), 0) as total_read,
                    IF(cv.total_sent > 0,
                       ROUND(COALESCE(SUM(CASE WHEN cm.status = 'replied' THEN 1 ELSE 0 END), 0) / cv.total_sent * 100, 2),
                       0) as reply_rate
                FROM campaign_variants cv
                LEFT JOIN campaign_messages cm 
                    ON cm.campaign_id = cv.campaign_id AND cm.variant = cv.variant_name
                WHERE cv.campaign_id = ? AND cv.variant_type = 'round_robin'
                GROUP BY cv.id
                ORDER BY cv.id ASC";
        return Database::fetchAll($sql, [$campaignId]);
    }

    /**
     * Recalcular taxas para as top mensagens round-robin no dashboard
     */
    public static function getTopRoundRobinByReplyRate(int $limit = 10): array
    {
        $sql = "SELECT 
                    cv.id,
                    cv.campaign_id,
                    cv.variant_name,
                    cv.message_content,
                    cv.message_type,
                    cv.total_sent,
                    c.name as campaign_name,
                    COALESCE(SUM(CASE WHEN cm.status = 'replied' THEN 1 ELSE 0 END), 0) as total_replied,
                    IF(cv.total_sent > 0,
                       ROUND(COALESCE(SUM(CASE WHEN cm.status = 'replied' THEN 1 ELSE 0 END), 0) / cv.total_sent * 100, 2),
                       0) as reply_rate
                FROM campaign_variants cv
                INNER JOIN campaigns c ON cv.campaign_id = c.id
                LEFT JOIN campaign_messages cm 
                    ON cm.campaign_id = cv.campaign_id AND cm.variant = cv.variant_name
                WHERE cv.variant_type = 'round_robin'
                  AND cv.total_sent > 0
                GROUP BY cv.id
                ORDER BY reply_rate DESC, cv.total_sent DESC
                LIMIT ?";
        return Database::fetchAll($sql, [$limit]);
    }
    
    /**
     * Incrementar contador de enviadas
     */
    public static function incrementSent(int $campaignId, string $variant, int $count = 1): bool
    {
        $sql = "UPDATE campaign_variants 
                SET total_sent = total_sent + ? 
                WHERE campaign_id = ? AND variant_name = ?";
        
        $result = Database::execute($sql, [$count, $campaignId, $variant]) > 0;
        
        if ($result) {
            self::recalculateRates($campaignId, $variant);
        }
        
        return $result;
    }
    
    /**
     * Incrementar contador de entregues
     */
    public static function incrementDelivered(int $campaignId, string $variant, int $count = 1): bool
    {
        $sql = "UPDATE campaign_variants 
                SET total_delivered = total_delivered + ? 
                WHERE campaign_id = ? AND variant_name = ?";
        
        $result = Database::execute($sql, [$count, $campaignId, $variant]) > 0;
        
        if ($result) {
            self::recalculateRates($campaignId, $variant);
        }
        
        return $result;
    }
    
    /**
     * Incrementar contador de lidas
     */
    public static function incrementRead(int $campaignId, string $variant, int $count = 1): bool
    {
        $sql = "UPDATE campaign_variants 
                SET total_read = total_read + ? 
                WHERE campaign_id = ? AND variant_name = ?";
        
        $result = Database::execute($sql, [$count, $campaignId, $variant]) > 0;
        
        if ($result) {
            self::recalculateRates($campaignId, $variant);
        }
        
        return $result;
    }
    
    /**
     * Incrementar contador de respondidas
     */
    public static function incrementReplied(int $campaignId, string $variant, int $count = 1): bool
    {
        $sql = "UPDATE campaign_variants 
                SET total_replied = total_replied + ? 
                WHERE campaign_id = ? AND variant_name = ?";
        
        $result = Database::execute($sql, [$count, $campaignId, $variant]) > 0;
        
        if ($result) {
            self::recalculateRates($campaignId, $variant);
        }
        
        return $result;
    }
    
    /**
     * Recalcular taxas
     */
    public static function recalculateRates(int $campaignId, string $variant): bool
    {
        $sql = "UPDATE campaign_variants 
                SET delivery_rate = IF(total_sent > 0, (total_delivered / total_sent) * 100, 0),
                    read_rate = IF(total_delivered > 0, (total_read / total_delivered) * 100, 0),
                    reply_rate = IF(total_delivered > 0, (total_replied / total_delivered) * 100, 0)
                WHERE campaign_id = ? AND variant_name = ?";
        
        return Database::execute($sql, [$campaignId, $variant]) > 0;
    }
    
    /**
     * Determinar vencedor (maior reply_rate)
     */
    public static function determineWinner(int $campaignId): ?string
    {
        $sql = "SELECT variant_name FROM campaign_variants 
                WHERE campaign_id = ? 
                ORDER BY reply_rate DESC, read_rate DESC 
                LIMIT 1";
        
        $result = Database::fetch($sql, [$campaignId]);
        return $result ? $result['variant_name'] : null;
    }
}
