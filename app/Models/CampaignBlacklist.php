<?php
/**
 * Model CampaignBlacklist
 * Blacklist de campanhas
 */

namespace App\Models;

use App\Helpers\Database;

class CampaignBlacklist extends Model
{
    protected string $table = 'campaign_blacklist';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'contact_id', 'phone', 'reason', 'blacklist_type', 'added_by'
    ];
    protected bool $timestamps = false; // Só tem added_at

    /**
     * Verificar se contato está na blacklist
     */
    public static function isBlacklisted(int $contactId): bool
    {
        $sql = "SELECT COUNT(*) as total 
                FROM campaign_blacklist 
                WHERE contact_id = ?";
        
        $result = Database::fetch($sql, [$contactId]);
        return ((int)($result['total'] ?? 0)) > 0;
    }

    /**
     * Verificar se telefone está na blacklist
     */
    public static function isPhoneBlacklisted(string $phone): bool
    {
        // Normalizar telefone
        $normalizedPhone = \App\Models\Contact::normalizePhoneNumber($phone);
        
        $sql = "SELECT COUNT(*) as total 
                FROM campaign_blacklist 
                WHERE phone = ? OR phone = ?";
        
        $result = Database::fetch($sql, [$phone, $normalizedPhone]);
        return ((int)($result['total'] ?? 0)) > 0;
    }

    /**
     * Adicionar contato à blacklist
     */
    public static function addContact(int $contactId, string $reason, ?int $addedBy = null, string $type = 'manual'): bool
    {
        try {
            // Verificar se já existe
            if (self::isBlacklisted($contactId)) {
                return true; // Já está na blacklist
            }

            $data = [
                'contact_id' => $contactId,
                'reason' => $reason,
                'blacklist_type' => $type,
                'added_by' => $addedBy,
                'added_at' => date('Y-m-d H:i:s')
            ];

            self::create($data);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao adicionar contato à blacklist: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adicionar telefone à blacklist
     */
    public static function addPhone(string $phone, string $reason, ?int $addedBy = null, string $type = 'manual'): bool
    {
        try {
            // Normalizar telefone
            $normalizedPhone = \App\Models\Contact::normalizePhoneNumber($phone);
            
            // Verificar se já existe
            if (self::isPhoneBlacklisted($normalizedPhone)) {
                return true; // Já está na blacklist
            }

            $data = [
                'phone' => $normalizedPhone,
                'reason' => $reason,
                'blacklist_type' => $type,
                'added_by' => $addedBy,
                'added_at' => date('Y-m-d H:i:s')
            ];

            self::create($data);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao adicionar telefone à blacklist: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover da blacklist
     */
    public static function removeContact(int $contactId): bool
    {
        try {
            $sql = "DELETE FROM campaign_blacklist WHERE contact_id = ?";
            Database::execute($sql, [$contactId]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao remover contato da blacklist: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover telefone da blacklist
     */
    public static function removePhone(string $phone): bool
    {
        try {
            $normalizedPhone = \App\Models\Contact::normalizePhoneNumber($phone);
            $sql = "DELETE FROM campaign_blacklist WHERE phone = ? OR phone = ?";
            Database::execute($sql, [$phone, $normalizedPhone]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao remover telefone da blacklist: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter todos da blacklist
     */
    public static function getAll(int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT cb.*, c.name as contact_name, c.phone as contact_phone,
                       u.name as added_by_name
                FROM campaign_blacklist cb
                LEFT JOIN contacts c ON cb.contact_id = c.id
                LEFT JOIN users u ON cb.added_by = u.id
                ORDER BY cb.added_at DESC
                LIMIT ? OFFSET ?";
        
        return Database::fetchAll($sql, [$limit, $offset]);
    }

    /**
     * Contar total na blacklist
     */
    public static function countAll(): int
    {
        $sql = "SELECT COUNT(*) as total FROM campaign_blacklist";
        $result = Database::fetch($sql, []);
        return (int)($result['total'] ?? 0);
    }
}
