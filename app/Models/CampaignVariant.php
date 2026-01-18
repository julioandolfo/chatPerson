<?php
/**
 * Model CampaignVariant
 * Variantes de A/B Testing
 */

namespace App\Models;

use App\Helpers\Database;

class CampaignVariant
{
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
        $fields = ['campaign_id', 'variant_name', 'message_content', 'percentage'];
        $values = [];
        $placeholders = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $values[] = $data[$field];
                $placeholders[] = '?';
            }
        }
        
        $sql = "INSERT INTO campaign_variants (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        Database::execute($sql, $values);
        
        return Database::getInstance()->lastInsertId();
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
