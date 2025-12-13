<?php
/**
 * Model ContactAgent
 */

namespace App\Models;

use App\Helpers\Database;

class ContactAgent extends Model
{
    protected string $table = 'contact_agents';
    protected string $primaryKey = 'id';
    protected array $fillable = ['contact_id', 'agent_id', 'is_primary', 'priority', 'auto_assign_on_reopen'];
    protected array $hidden = [];
    protected bool $timestamps = true;

    /**
     * Obter agentes de um contato
     */
    public static function getByContact(int $contactId): array
    {
        $sql = "SELECT ca.*, 
                       u.name as agent_name, u.email as agent_email, u.avatar as agent_avatar
                FROM contact_agents ca
                LEFT JOIN users u ON ca.agent_id = u.id
                WHERE ca.contact_id = ?
                ORDER BY ca.is_primary DESC, ca.priority DESC, ca.created_at ASC";
        
        return Database::fetchAll($sql, [$contactId]);
    }

    /**
     * Obter agente principal de um contato
     */
    public static function getPrimaryAgent(int $contactId): ?array
    {
        $sql = "SELECT ca.*, 
                       u.name as agent_name, u.email as agent_email, u.avatar as agent_avatar
                FROM contact_agents ca
                LEFT JOIN users u ON ca.agent_id = u.id
                WHERE ca.contact_id = ? AND ca.is_primary = 1
                LIMIT 1";
        
        return Database::fetch($sql, [$contactId]);
    }

    /**
     * Definir agente principal
     */
    public static function setPrimaryAgent(int $contactId, int $agentId): bool
    {
        try {
            Database::beginTransaction();
            
            // Remover primary de todos os agentes deste contato
            Database::execute("UPDATE contact_agents SET is_primary = 0 WHERE contact_id = ?", [$contactId]);
            
            // Verificar se já existe
            $sql = "SELECT id FROM contact_agents WHERE contact_id = ? AND agent_id = ? LIMIT 1";
            $exists = Database::fetch($sql, [$contactId, $agentId]);
            
            if ($exists) {
                self::update($exists['id'], ['is_primary' => 1]);
            } else {
                self::create([
                    'contact_id' => $contactId,
                    'agent_id' => $agentId,
                    'is_primary' => 1,
                    'auto_assign_on_reopen' => 1
                ]);
            }
            
            // Atualizar campo primary_agent_id na tabela contacts
            Database::execute("UPDATE contacts SET primary_agent_id = ? WHERE id = ?", [$agentId, $contactId]);
            
            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Erro ao definir agente principal: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adicionar agente a contato
     */
    public static function addAgent(int $contactId, int $agentId, bool $isPrimary = false, int $priority = 0): int
    {
        // Verificar se já existe
        $sql = "SELECT id, is_primary FROM contact_agents WHERE contact_id = ? AND agent_id = ? LIMIT 1";
        $existing = Database::fetch($sql, [$contactId, $agentId]);
        
        if ($existing) {
            // Atualizar existente
            self::update($existing['id'], [
                'is_primary' => $isPrimary ? 1 : $existing['is_primary'],
                'priority' => $priority,
                'auto_assign_on_reopen' => 1
            ]);
            return $existing['id'];
        }
        
        // Se está marcando como primary, remover primary de outros
        if ($isPrimary) {
            Database::execute(
                "UPDATE contact_agents SET is_primary = 0 WHERE contact_id = ?",
                [$contactId]
            );
            // Atualizar campo na tabela contacts
            Database::execute(
                "UPDATE contacts SET primary_agent_id = ? WHERE id = ?",
                [$agentId, $contactId]
            );
        }
        
        return self::create([
            'contact_id' => $contactId,
            'agent_id' => $agentId,
            'is_primary' => $isPrimary ? 1 : 0,
            'priority' => $priority,
            'auto_assign_on_reopen' => 1
        ]);
    }

    /**
     * Remover agente de contato
     */
    public static function removeAgent(int $contactId, int $agentId): bool
    {
        // Verificar se era primary antes de remover
        $sql = "SELECT id, is_primary FROM contact_agents WHERE contact_id = ? AND agent_id = ? LIMIT 1";
        $existing = Database::fetch($sql, [$contactId, $agentId]);
        
        if (!$existing) {
            return false;
        }
        
        $wasPrimary = $existing['is_primary'] == 1;
        
        // Remover
        $result = self::delete($existing['id']);
        
        // Se era o primary, verificar se ainda há outro primary ou limpar campo na tabela contacts
        if ($wasPrimary) {
            $primary = self::getPrimaryAgent($contactId);
            if (!$primary) {
                Database::execute("UPDATE contacts SET primary_agent_id = NULL WHERE id = ?", [$contactId]);
            } else {
                Database::execute("UPDATE contacts SET primary_agent_id = ? WHERE id = ?", [$primary['agent_id'], $contactId]);
            }
        }
        
        return $result;
    }

    /**
     * Verificar se agente deve ser atribuído automaticamente ao reabrir
     */
    public static function shouldAutoAssignOnReopen(int $contactId, int $agentId): bool
    {
        $sql = "SELECT auto_assign_on_reopen FROM contact_agents 
                WHERE contact_id = ? AND agent_id = ? AND auto_assign_on_reopen = 1
                LIMIT 1";
        
        $result = Database::fetch($sql, [$contactId, $agentId]);
        return $result !== null && $result['auto_assign_on_reopen'] == 1;
    }
}

