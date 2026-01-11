<?php
/**
 * Model CoachingConversationImpact
 * Impacto do coaching em conversas individuais
 */

namespace App\Models;

use App\Helpers\Database;

class CoachingConversationImpact extends Model
{
    protected string $table = 'coaching_conversation_impact';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id',
        'agent_id',
        'avg_response_time_before',
        'messages_count_before',
        'avg_response_time_after',
        'messages_count_after',
        'total_hints',
        'hints_helpful',
        'hints_not_helpful',
        'suggestions_used',
        'conversation_outcome',
        'sales_value',
        'conversion_time_minutes',
        'performance_improvement_score',
        'first_hint_at',
        'last_hint_at',
        'conversation_ended_at'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter impacto de uma conversa específica
     */
    public static function getByConversation(int $conversationId): ?array
    {
        $sql = "SELECT * FROM coaching_conversation_impact 
                WHERE conversation_id = :conversation_id 
                LIMIT 1";
        
        return Database::fetch($sql, ['conversation_id' => $conversationId]);
    }
    
    /**
     * Obter conversas com melhor impacto (sucesso)
     */
    public static function getTopImpact(int $limit = 10, ?int $agentId = null): array
    {
        $sql = "SELECT cci.*, c.contact_id, ct.name as contact_name, u.name as agent_name
                FROM coaching_conversation_impact cci
                INNER JOIN conversations c ON cci.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users u ON cci.agent_id = u.id
                WHERE cci.conversation_outcome = 'converted'
                AND cci.performance_improvement_score > 0";
        
        if ($agentId) {
            $sql .= " AND cci.agent_id = :agent_id";
        }
        
        $sql .= " ORDER BY cci.performance_improvement_score DESC, cci.sales_value DESC 
                  LIMIT :limit";
        
        $params = ['limit' => $limit];
        if ($agentId) {
            $params['agent_id'] = $agentId;
        }
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Obter conversas por agente
     */
    public static function getByAgent(int $agentId, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 50): array
    {
        $sql = "SELECT cci.*, c.contact_id, ct.name as contact_name
                FROM coaching_conversation_impact cci
                INNER JOIN conversations c ON cci.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                WHERE cci.agent_id = :agent_id";
        
        $params = ['agent_id' => $agentId];
        
        if ($dateFrom) {
            $sql .= " AND cci.created_at >= :date_from";
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND cci.created_at <= :date_to";
            $params['date_to'] = $dateTo;
        }
        
        $sql .= " ORDER BY cci.created_at DESC LIMIT :limit";
        $params['limit'] = $limit;
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Calcular estatísticas de impacto por agente
     */
    public static function getAgentImpactStats(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_conversations,
                    SUM(CASE WHEN conversation_outcome = 'converted' THEN 1 ELSE 0 END) as converted,
                    SUM(CASE WHEN conversation_outcome = 'closed' THEN 1 ELSE 0 END) as closed,
                    SUM(CASE WHEN conversation_outcome = 'escalated' THEN 1 ELSE 0 END) as escalated,
                    AVG(performance_improvement_score) as avg_improvement_score,
                    SUM(sales_value) as total_sales,
                    AVG(conversion_time_minutes) as avg_conversion_time,
                    SUM(hints_helpful) as total_helpful,
                    SUM(hints_not_helpful) as total_not_helpful,
                    SUM(suggestions_used) as total_suggestions_used
                FROM coaching_conversation_impact
                WHERE agent_id = :agent_id";
        
        $params = ['agent_id' => $agentId];
        
        if ($dateFrom) {
            $sql .= " AND created_at >= :date_from";
            $params['date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND created_at <= :date_to";
            $params['date_to'] = $dateTo;
        }
        
        return Database::fetch($sql, $params) ?? [];
    }
    
    /**
     * Atualizar ou criar impacto
     */
    public static function upsert(array $data): bool
    {
        $existing = self::getByConversation($data['conversation_id']);
        
        if ($existing) {
            // Atualizar
            $sql = "UPDATE coaching_conversation_impact SET ";
            $updates = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if ($key !== 'conversation_id') {
                    $updates[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
            }
            
            $sql .= implode(', ', $updates);
            $sql .= ", updated_at = NOW() WHERE id = :id";
            $params['id'] = $existing['id'];
            
            Database::execute($sql, $params);
            return true;
        } else {
            // Criar
            return self::create($data) > 0;
        }
    }
}
