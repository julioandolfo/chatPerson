<?php
/**
 * Model SLARule
 * Regras de SLA personalizadas por contexto
 */

namespace App\Models;

use App\Helpers\Database;

class SLARule extends Model
{
    protected string $table = 'sla_rules';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'priority', 'conversation_priority', 'channel', 
        'department_id', 'funnel_id', 'funnel_stage_id',
        'first_response_time', 'resolution_time', 'ongoing_response_time',
        'enabled'
    ];
    protected bool $timestamps = true;
    
    /**
     * Encontrar regra de SLA aplicável para uma conversa
     * Retorna a regra com maior prioridade que match as condições
     */
    public static function findApplicableRule(array $conversation): ?array
    {
        $sql = "SELECT * FROM sla_rules 
                WHERE enabled = 1
                AND (conversation_priority IS NULL OR conversation_priority = ?)
                AND (channel IS NULL OR channel = ?)
                AND (department_id IS NULL OR department_id = ?)
                AND (funnel_id IS NULL OR funnel_id = ?)
                AND (funnel_stage_id IS NULL OR funnel_stage_id = ?)
                ORDER BY priority DESC, id DESC
                LIMIT 1";
        
        $params = [
            $conversation['priority'] ?? 'normal',
            $conversation['channel'] ?? null,
            $conversation['department_id'] ?? null,
            $conversation['funnel_id'] ?? null,
            $conversation['funnel_stage_id'] ?? null
        ];
        
        return Database::fetch($sql, $params);
    }
    
    /**
     * Obter SLA para uma conversa específica
     * Retorna array com first_response_time, resolution_time, ongoing_response_time
     */
    public static function getSLAForConversation(array $conversation): array
    {
        $rule = self::findApplicableRule($conversation);
        
        if ($rule) {
            return [
                'first_response_time' => (int)$rule['first_response_time'],
                'resolution_time' => (int)$rule['resolution_time'],
                'ongoing_response_time' => (int)$rule['ongoing_response_time'],
                'rule_name' => $rule['name']
            ];
        }
        
        // Fallback: usar configuração global
        $settings = \App\Services\ConversationSettingsService::getSettings();
        return [
            'first_response_time' => (int)($settings['sla']['first_response_time'] ?? 15),
            'resolution_time' => (int)($settings['sla']['resolution_time'] ?? 60),
            'ongoing_response_time' => (int)($settings['sla']['ongoing_response_time'] ?? 15),
            'rule_name' => 'Global'
        ];
    }
    
    /**
     * Obter todas as regras ativas
     */
    public static function getActiveRules(): array
    {
        $sql = "SELECT sr.*, 
                       d.name as department_name,
                       f.name as funnel_name,
                       fs.name as stage_name
                FROM sla_rules sr
                LEFT JOIN departments d ON sr.department_id = d.id
                LEFT JOIN funnels f ON sr.funnel_id = f.id
                LEFT JOIN funnel_stages fs ON sr.funnel_stage_id = fs.id
                WHERE sr.enabled = 1
                ORDER BY sr.priority DESC, sr.name ASC";
        
        return Database::fetchAll($sql);
    }
}
