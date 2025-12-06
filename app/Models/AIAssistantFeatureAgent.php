<?php
/**
 * Model AIAssistantFeatureAgent
 * Regras de seleção automática de agentes por funcionalidade
 */

namespace App\Models;

use App\Helpers\Database;

class AIAssistantFeatureAgent extends Model
{
    protected string $table = 'ai_assistant_feature_agents';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'feature_key', 'ai_agent_id', 'priority', 'conditions', 'enabled'
    ];
    protected bool $timestamps = true;

    /**
     * Obter regras para uma funcionalidade (ou todas se featureKey estiver vazio)
     */
    public static function getRulesForFeature(string $featureKey = ''): array
    {
        $sql = "SELECT faa.*, a.name as agent_name, a.agent_type, a.enabled as agent_enabled
                FROM ai_assistant_feature_agents faa
                INNER JOIN ai_agents a ON faa.ai_agent_id = a.id
                WHERE faa.enabled = TRUE AND a.enabled = TRUE";
        
        $params = [];
        if (!empty($featureKey)) {
            $sql .= " AND faa.feature_key = ?";
            $params[] = $featureKey;
        }
        
        $sql .= " ORDER BY faa.priority DESC";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Adicionar regra para funcionalidade
     */
    public static function addRule(string $featureKey, int $aiAgentId, int $priority = 0, array $conditions = []): bool
    {
        $data = [
            'feature_key' => $featureKey,
            'ai_agent_id' => $aiAgentId,
            'priority' => $priority,
            'conditions' => !empty($conditions) 
                ? json_encode($conditions, JSON_UNESCAPED_UNICODE) 
                : null,
            'enabled' => true
        ];

        return self::create($data) > 0;
    }

    /**
     * Verificar se condições são atendidas
     */
    public static function checkConditions(array $rule, array $context): bool
    {
        if (empty($rule['conditions'])) {
            return true; // Sem condições = sempre válido
        }

        $conditions = json_decode($rule['conditions'], true);
        if (!is_array($conditions) || empty($conditions)) {
            return true;
        }

        // Verificar cada condição
        foreach ($conditions as $key => $value) {
            $contextValue = $context[$key] ?? null;

            // Se condição é array (ex: tags contém)
            if (is_array($value)) {
                if ($key === 'tags' && is_array($contextValue)) {
                    // Verificar se alguma tag do contexto está na lista de condições
                    $intersection = array_intersect($value, $contextValue);
                    if (empty($intersection)) {
                        return false;
                    }
                } elseif ($key === 'channels' && is_array($value)) {
                    if (!in_array($contextValue, $value)) {
                        return false;
                    }
                } elseif ($key === 'funnel_stage_id' && is_array($value)) {
                    // Múltiplas etapas permitidas
                    if (!in_array($contextValue, $value)) {
                        return false;
                    }
                }
            } 
            // Comparação simples
            else {
                // Verificações especiais para funis e etapas
                if ($key === 'funnel_id' && $value) {
                    // Se especificou funil, verificar se corresponde
                    if ($contextValue != $value) {
                        return false;
                    }
                    // Se também especificou etapa, verificar etapa também
                    if (isset($conditions['funnel_stage_id']) && !empty($conditions['funnel_stage_id'])) {
                        if (($context['funnel_stage_id'] ?? null) != $conditions['funnel_stage_id']) {
                            return false;
                        }
                    }
                } elseif ($key === 'funnel_stage_id' && $value) {
                    // Se especificou etapa, verificar se corresponde
                    if ($contextValue != $value) {
                        return false;
                    }
                } else {
                    // Comparação padrão
                    if ($contextValue != $value) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Encontrar melhor agente baseado em contexto
     */
    public static function findBestAgent(string $featureKey, array $context): ?int
    {
        $rules = self::getRulesForFeature($featureKey);
        
        // Ordenar por prioridade (maior primeiro)
        usort($rules, function($a, $b) {
            return ($b['priority'] ?? 0) - ($a['priority'] ?? 0);
        });

        // Verificar cada regra
        foreach ($rules as $rule) {
            if (self::checkConditions($rule, $context)) {
                return (int)$rule['ai_agent_id'];
            }
        }

        return null; // Nenhuma regra correspondeu
    }
}

