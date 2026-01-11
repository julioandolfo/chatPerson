<?php
/**
 * Model WooCommerceOrderCache
 * Cache de pedidos do WooCommerce
 */

namespace App\Models;

use App\Helpers\Database;

class WooCommerceOrderCache extends Model
{
    protected string $table = 'woocommerce_order_cache';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'woocommerce_integration_id',
        'contact_id',
        'order_id',
        'order_data',
        'order_status',
        'order_total',
        'order_date',
        'seller_id',
        'cached_at',
        'expires_at'
    ];
    protected bool $timestamps = false; // Usa campos manuais

    /**
     * Obter pedidos em cache de um contato
     */
    public static function getByContact(int $contactId, ?int $integrationId = null): array
    {
        $sql = "SELECT * FROM woocommerce_order_cache 
                WHERE contact_id = ? 
                AND expires_at > NOW()";
        
        $params = [$contactId];
        
        if ($integrationId !== null) {
            $sql .= " AND woocommerce_integration_id = ?";
            $params[] = $integrationId;
        }
        
        $sql .= " ORDER BY order_date DESC";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Limpar cache expirado
     */
    public static function clearExpired(): int
    {
        $sql = "DELETE FROM woocommerce_order_cache WHERE expires_at <= NOW()";
        $stmt = Database::query($sql);
        return $stmt->rowCount();
    }

    /**
     * Limpar cache de um contato
     */
    public static function clearByContact(int $contactId, ?int $integrationId = null): bool
    {
        $sql = "DELETE FROM woocommerce_order_cache WHERE contact_id = ?";
        $params = [$contactId];
        
        if ($integrationId !== null) {
            $sql .= " AND woocommerce_integration_id = ?";
            $params[] = $integrationId;
        }
        
        Database::query($sql, $params);
        return true;
    }

    /**
     * Salvar pedido no cache
     */
    /**
     * Salvar pedido no cache, incluindo seller_id quando disponível
     */
    public static function cacheOrder(int $integrationId, int $contactId, array $order, int $ttlMinutes = 5, ?int $sellerId = null): int
    {
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));
        
        $data = [
            'woocommerce_integration_id' => $integrationId,
            'contact_id' => $contactId,
            'order_id' => $order['id'],
            'order_data' => json_encode($order),
            'order_status' => $order['status'] ?? 'pending',
            'order_total' => $order['total'] ?? '0.00',
            'order_date' => $order['date_created'] ?? date('Y-m-d H:i:s'),
            'seller_id' => $sellerId,
            'expires_at' => $expiresAt
        ];

        // Verificar se já existe
        $existing = Database::fetch(
            "SELECT id FROM woocommerce_order_cache 
             WHERE woocommerce_integration_id = ? 
             AND contact_id = ? 
             AND order_id = ?",
            [$integrationId, $contactId, $order['id']]
        );

        if ($existing) {
            return self::update($existing['id'], $data);
        }

        return self::create($data);
    }
}

