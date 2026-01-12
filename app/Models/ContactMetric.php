<?php
/**
 * Model ContactMetric
 * Métricas pré-calculadas de contatos
 */

namespace App\Models;

use App\Helpers\Database;

class ContactMetric extends Model
{
    protected string $table = 'contact_metrics';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'contact_id',
        'total_conversations',
        'open_conversations',
        'closed_conversations',
        'avg_response_time_minutes',
        'last_message_at',
        'last_calculated_at',
        'needs_recalculation',
        'calculation_priority',
        'has_open_conversations',
        'last_conversation_status'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter métricas de um contato
     */
    public static function getByContact(int $contactId): ?array
    {
        return Database::fetch(
            "SELECT * FROM contact_metrics WHERE contact_id = ?",
            [$contactId]
        );
    }
    
    /**
     * Marcar contato para recálculo
     * Chamado quando há nova mensagem ou mudança de status
     */
    public static function markForRecalculation(int $contactId, int $priority = 1): void
    {
        $existing = self::getByContact($contactId);
        
        if ($existing) {
            Database::execute(
                "UPDATE contact_metrics 
                 SET needs_recalculation = 1,
                     calculation_priority = GREATEST(calculation_priority, ?)
                 WHERE contact_id = ?",
                [$priority, $contactId]
            );
        } else {
            Database::execute(
                "INSERT INTO contact_metrics (contact_id, needs_recalculation, calculation_priority)
                 VALUES (?, 1, ?)",
                [$contactId, $priority]
            );
        }
    }
    
    /**
     * Obter contatos que precisam de recálculo
     * Prioridade:
     * - 3: Conversas abertas com mensagens novas (urgente)
     * - 2: Conversas abertas sem mudanças (normal)
     * - 1: Conversas fechadas nunca calculadas (baixa)
     * - 0: Conversas fechadas já calculadas (não recalcular)
     */
    public static function getContactsNeedingRecalculation(int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT cm.*
             FROM contact_metrics cm
             WHERE cm.needs_recalculation = 1
             ORDER BY cm.calculation_priority DESC, cm.last_calculated_at ASC
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * Obter contatos com conversas abertas que ainda não foram calculados
     */
    public static function getOpenConversationsNeedingCalculation(int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT DISTINCT c.contact_id
             FROM conversations c
             LEFT JOIN contact_metrics cm ON cm.contact_id = c.contact_id
             WHERE c.status IN ('open', 'pending')
               AND (cm.id IS NULL OR cm.needs_recalculation = 1)
             LIMIT ?",
            [$limit]
        );
    }
}
