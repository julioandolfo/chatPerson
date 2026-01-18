<?php
/**
 * Model DripSequence
 * Sequências de campanhas Drip (múltiplas etapas)
 */

namespace App\Models;

use App\Helpers\Database;

class DripSequence extends Model
{
    protected string $table = 'drip_sequences';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name',
        'description',
        'status',
        'total_steps',
        'total_contacts',
        'created_by'
    ];
    /**
     * Buscar todas as sequências
     */
    public static function all(): array
    {
        $sql = "SELECT * FROM drip_sequences ORDER BY created_at DESC";
        return Database::fetchAll($sql, []);
    }
    
    /**
     * Buscar por ID
     */
    public static function find(int $id): ?array
    {
        $sql = "SELECT * FROM drip_sequences WHERE id = ? LIMIT 1";
        return Database::fetch($sql, [$id]);
    }
    
    /**
     * Criar sequência
     */
    public static function create(array $data): int
    {
        $fields = ['name', 'description', 'status', 'total_steps', 'total_contacts', 'created_by'];
        $values = [];
        $placeholders = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $values[] = $data[$field];
                $placeholders[] = '?';
            }
        }
        
        $sql = "INSERT INTO drip_sequences (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        Database::execute($sql, $values);
        
        return Database::getInstance()->lastInsertId();
    }
    
    /**
     * Deletar sequência
     */
    public static function delete(int $id): bool
    {
        $sql = "DELETE FROM drip_sequences WHERE id = ?";
        return Database::execute($sql, [$id]) > 0;
    }
    
    /**
     * Buscar sequências ativas
     */
    public static function getActive(): array
    {
        $sql = "SELECT * FROM drip_sequences WHERE status = 'active' ORDER BY created_at DESC";
        return Database::fetchAll($sql, []);
    }
    
    /**
     * Buscar passos de uma sequência
     */
    public static function getSteps(int $sequenceId): array
    {
        $sql = "SELECT * FROM drip_steps WHERE sequence_id = ? ORDER BY step_order ASC";
        return Database::fetchAll($sql, [$sequenceId]);
    }
    
    /**
     * Adicionar contato à sequência
     */
    public static function addContact(int $sequenceId, int $contactId): bool
    {
        $sql = "INSERT IGNORE INTO drip_contact_progress (sequence_id, contact_id, current_step, status) 
                VALUES (?, ?, 1, 'active')";
        
        $result = Database::execute($sql, [$sequenceId, $contactId]) > 0;
        
        if ($result) {
            // Incrementar contador
            $sql = "UPDATE drip_sequences SET total_contacts = total_contacts + 1 WHERE id = ?";
            Database::execute($sql, [$sequenceId]);
        }
        
        return $result;
    }
    
    /**
     * Remover contato da sequência (opt-out)
     */
    public static function removeContact(int $sequenceId, int $contactId): bool
    {
        $sql = "UPDATE drip_contact_progress 
                SET status = 'opted_out', opted_out = TRUE, opted_out_at = NOW() 
                WHERE sequence_id = ? AND contact_id = ?";
        
        return Database::execute($sql, [$sequenceId, $contactId]) > 0;
    }
    
    /**
     * Buscar contatos prontos para próximo passo
     */
    public static function getContactsReadyForNextStep(int $sequenceId, int $stepOrder): array
    {
        $sql = "SELECT dcp.*, ds.delay_days, ds.delay_hours
                FROM drip_contact_progress dcp
                INNER JOIN drip_steps ds ON ds.sequence_id = dcp.sequence_id AND ds.step_order = ?
                WHERE dcp.sequence_id = ? 
                  AND dcp.current_step = ?
                  AND dcp.status = 'active'
                  AND dcp.opted_out = FALSE
                  AND (
                    dcp.last_step_at IS NULL OR
                    dcp.last_step_at <= DATE_SUB(NOW(), INTERVAL ds.delay_days DAY) - INTERVAL ds.delay_hours HOUR
                  )";
        
        return Database::fetchAll($sql, [$stepOrder, $sequenceId, $stepOrder]);
    }
    
    /**
     * Avançar contato para próximo passo
     */
    public static function advanceContact(int $sequenceId, int $contactId): bool
    {
        $sql = "UPDATE drip_contact_progress 
                SET current_step = current_step + 1, 
                    last_step_at = NOW() 
                WHERE sequence_id = ? AND contact_id = ?";
        
        return Database::execute($sql, [$sequenceId, $contactId]) > 0;
    }
    
    /**
     * Completar sequência para um contato
     */
    public static function completeForContact(int $sequenceId, int $contactId): bool
    {
        $sql = "UPDATE drip_contact_progress 
                SET status = 'completed', completed_at = NOW() 
                WHERE sequence_id = ? AND contact_id = ?";
        
        return Database::execute($sql, [$sequenceId, $contactId]) > 0;
    }
}
