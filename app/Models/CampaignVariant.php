<?php
/**
 * Model CampaignVariant
 * Variantes de A/B Testing em campanhas
 */

namespace App\Models;

use App\Helpers\Database;

class CampaignVariant extends Model
{
    protected static $table = 'campaign_variants';
    
    protected static $fillable = [
        'campaign_id',
        'variant_name',
        'message_content',
        'percentage',
        'total_sent',
        'total_delivered',
        'total_read',
        'total_replied',
        'delivery_rate',
        'read_rate',
        'reply_rate'
    ];
    
    /**
     * Buscar variantes de uma campanha
     */
    public static function getByCampaign(int $campaignId): array
    {
        $sql = "SELECT * FROM " . self::$table . " WHERE campaign_id = ? ORDER BY variant_name";
        return Database::fetchAll($sql, [$campaignId]);
    }
    
    /**
     * Incrementar contador de enviada
     */
    public static function incrementSent(int $campaignId, string $variant, int $count = 1): bool
    {
        $sql = "UPDATE " . self::$table . " 
                SET total_sent = total_sent + ? 
                WHERE campaign_id = ? AND variant_name = ?";
        return Database::execute($sql, [$count, $campaignId, $variant]) > 0;
    }
    
    /**
     * Incrementar contador de entregue
     */
    public static function incrementDelivered(int $campaignId, string $variant, int $count = 1): bool
    {
        $sql = "UPDATE " . self::$table . " 
                SET total_delivered = total_delivered + ?,
                    delivery_rate = (total_delivered + ?) / NULLIF(total_sent, 0) * 100
                WHERE campaign_id = ? AND variant_name = ?";
        return Database::execute($sql, [$count, $count, $campaignId, $variant]) > 0;
    }
    
    /**
     * Incrementar contador de lida
     */
    public static function incrementRead(int $campaignId, string $variant, int $count = 1): bool
    {
        $sql = "UPDATE " . self::$table . " 
                SET total_read = total_read + ?,
                    read_rate = (total_read + ?) / NULLIF(total_delivered, 0) * 100
                WHERE campaign_id = ? AND variant_name = ?";
        return Database::execute($sql, [$count, $count, $campaignId, $variant]) > 0;
    }
    
    /**
     * Incrementar contador de respondida
     */
    public static function incrementReplied(int $campaignId, string $variant, int $count = 1): bool
    {
        $sql = "UPDATE " . self::$table . " 
                SET total_replied = total_replied + ?,
                    reply_rate = (total_replied + ?) / NULLIF(total_delivered, 0) * 100
                WHERE campaign_id = ? AND variant_name = ?";
        return Database::execute($sql, [$count, $count, $campaignId, $variant]) > 0;
    }
    
    /**
     * Determinar variante vencedora
     */
    public static function determineWinner(int $campaignId): ?string
    {
        $sql = "SELECT variant_name, reply_rate 
                FROM " . self::$table . " 
                WHERE campaign_id = ? 
                ORDER BY reply_rate DESC, total_replied DESC 
                LIMIT 1";
        
        $result = Database::fetch($sql, [$campaignId]);
        return $result ? $result['variant_name'] : null;
    }
    
    /**
     * Recalcular taxas
     */
    public static function recalculateRates(int $campaignId, string $variant): bool
    {
        $sql = "UPDATE " . self::$table . " 
                SET delivery_rate = (total_delivered / NULLIF(total_sent, 0) * 100),
                    read_rate = (total_read / NULLIF(total_delivered, 0) * 100),
                    reply_rate = (total_replied / NULLIF(total_delivered, 0) * 100)
                WHERE campaign_id = ? AND variant_name = ?";
        return Database::execute($sql, [$campaignId, $variant]) > 0;
    }
}
