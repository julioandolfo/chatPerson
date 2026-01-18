<?php
/**
 * Model ContactList
 * Listas de contatos para campanhas
 */

namespace App\Models;

use App\Helpers\Database;

class ContactList extends Model
{
    protected string $table = 'contact_lists';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'description',
        'is_dynamic', 'filter_config',
        'total_contacts', 'last_calculated_at',
        'created_by'
    ];
    protected bool $timestamps = true;

    /**
     * Obter contatos da lista
     */
    public static function getContacts(int $listId, int $limit = 1000, int $offset = 0): array
    {
        $sql = "SELECT c.*, cli.custom_variables
                FROM contact_list_items cli
                INNER JOIN contacts c ON cli.contact_id = c.id
                WHERE cli.contact_list_id = ?
                ORDER BY cli.added_at DESC
                LIMIT ? OFFSET ?";
        
        return Database::fetchAll($sql, [$listId, $limit, $offset]);
    }

    /**
     * Contar contatos da lista
     */
    public static function countContacts(int $listId): int
    {
        $sql = "SELECT COUNT(*) as total 
                FROM contact_list_items 
                WHERE contact_list_id = ?";
        
        $result = Database::fetch($sql, [$listId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Adicionar contato à lista
     */
    public static function addContact(int $listId, int $contactId, array $customVariables = [], ?int $addedBy = null): bool
    {
        try {
            $sql = "INSERT INTO contact_list_items (contact_list_id, contact_id, custom_variables, added_by) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE custom_variables = VALUES(custom_variables)";
            
            $variablesJson = !empty($customVariables) ? json_encode($customVariables) : null;
            
            Database::execute($sql, [$listId, $contactId, $variablesJson, $addedBy]);
            
            // Atualizar contador
            self::recalculateTotal($listId);
            
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao adicionar contato à lista: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remover contato da lista
     */
    public static function removeContact(int $listId, int $contactId): bool
    {
        try {
            $sql = "DELETE FROM contact_list_items 
                    WHERE contact_list_id = ? AND contact_id = ?";
            
            Database::execute($sql, [$listId, $contactId]);
            
            // Atualizar contador
            self::recalculateTotal($listId);
            
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao remover contato da lista: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpar lista (remover todos os contatos)
     */
    public static function clearList(int $listId): bool
    {
        try {
            $sql = "DELETE FROM contact_list_items WHERE contact_list_id = ?";
            Database::execute($sql, [$listId]);
            
            // Atualizar contador
            self::update($listId, ['total_contacts' => 0]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao limpar lista: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recalcular total de contatos
     */
    public static function recalculateTotal(int $listId): bool
    {
        $total = self::countContacts($listId);
        
        return self::update($listId, [
            'total_contacts' => $total,
            'last_calculated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obter listas do usuário
     */
    public static function getByUser(int $userId): array
    {
        return self::where('created_by', '=', $userId);
    }

    /**
     * Verificar se contato está na lista
     */
    public static function hasContact(int $listId, int $contactId): bool
    {
        $sql = "SELECT COUNT(*) as total 
                FROM contact_list_items 
                WHERE contact_list_id = ? AND contact_id = ?";
        
        $result = Database::fetch($sql, [$listId, $contactId]);
        return ((int)($result['total'] ?? 0)) > 0;
    }

    /**
     * Obter variáveis customizadas de um contato na lista
     */
    public static function getContactVariables(int $listId, int $contactId): array
    {
        $sql = "SELECT custom_variables 
                FROM contact_list_items 
                WHERE contact_list_id = ? AND contact_id = ?";
        
        $result = Database::fetch($sql, [$listId, $contactId]);
        
        if (!$result || empty($result['custom_variables'])) {
            return [];
        }

        return json_decode($result['custom_variables'], true) ?? [];
    }
}
