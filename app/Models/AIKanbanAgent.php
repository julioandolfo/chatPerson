<?php
/**
 * Model AIKanbanAgent
 * Agentes de IA especializados para gestão de funis e etapas Kanban
 */

namespace App\Models;

use App\Helpers\Database;

class AIKanbanAgent extends Model
{
    protected string $table = 'ai_kanban_agents';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name',
        'description',
        'agent_type',
        'prompt',
        'model',
        'temperature',
        'max_tokens',
        'enabled',
        'target_funnel_ids',
        'target_stage_ids',
        'execution_type',
        'execution_interval_hours',
        'execution_schedule',
        'last_execution_at',
        'next_execution_at',
        'conditions',
        'actions',
        'settings',
        'max_conversations_per_execution',
        'cooldown_hours',
        'allow_reexecution_on_change'
    ];
    protected bool $timestamps = true;

    /**
     * Obter agentes prontos para execução
     */
    public static function getReadyForExecution(): array
    {
        $sql = "SELECT * FROM ai_kanban_agents 
                WHERE enabled = TRUE 
                AND execution_type != 'manual'
                AND (next_execution_at IS NULL OR next_execution_at <= NOW())
                ORDER BY next_execution_at ASC";
        return Database::fetchAll($sql);
    }

    /**
     * Obter execuções do agente
     */
    public static function getExecutions(int $agentId, int $limit = 50): array
    {
        $sql = "SELECT * FROM ai_kanban_agent_executions 
                WHERE ai_kanban_agent_id = ? 
                ORDER BY started_at DESC 
                LIMIT ?";
        return Database::fetchAll($sql, [$agentId, $limit]);
    }

    /**
     * Obter logs de ações do agente
     */
    public static function getActionLogs(int $agentId, int $limit = 100): array
    {
        $sql = "SELECT al.*, c.contact_id, ct.name as contact_name
                FROM ai_kanban_agent_actions_log al
                INNER JOIN conversations c ON al.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                WHERE al.ai_kanban_agent_id = ? 
                ORDER BY al.executed_at DESC 
                LIMIT ?";
        return Database::fetchAll($sql, [$agentId, $limit]);
    }

    /**
     * Atualizar próxima execução
     */
    public static function updateNextExecution(int $agentId): bool
    {
        $agent = self::find($agentId);
        if (!$agent) {
            return false;
        }

        $nextExecution = null;
        
        if ($agent['execution_type'] === 'interval' && $agent['execution_interval_hours']) {
            $hours = (int)$agent['execution_interval_hours'];
            $nextExecution = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
        } elseif ($agent['execution_type'] === 'schedule' && $agent['execution_schedule']) {
            $schedule = json_decode($agent['execution_schedule'], true);
            if ($schedule && isset($schedule['days']) && isset($schedule['time'])) {
                $nextExecution = self::calculateNextSchedule($schedule);
            }
        }

        return self::update($agentId, [
            'last_execution_at' => date('Y-m-d H:i:s'),
            'next_execution_at' => $nextExecution
        ]);
    }

    /**
     * Calcular próxima execução baseada em agendamento
     */
    private static function calculateNextSchedule(array $schedule): ?string
    {
        $days = $schedule['days'] ?? [];
        $time = $schedule['time'] ?? '09:00';
        
        if (empty($days)) {
            return null;
        }

        $currentDay = (int)date('w'); // 0 = Domingo, 6 = Sábado
        $currentTime = date('H:i');
        
        // Converter dias da semana (0-6) para formato do sistema
        $scheduleDays = array_map('intval', $days);
        
        // Se hoje está no agendamento e ainda não passou o horário
        if (in_array($currentDay, $scheduleDays) && $currentTime < $time) {
            return date('Y-m-d') . ' ' . $time . ':00';
        }
        
        // Encontrar próximo dia
        for ($i = 1; $i <= 7; $i++) {
            $nextDay = ($currentDay + $i) % 7;
            if (in_array($nextDay, $scheduleDays)) {
                $daysToAdd = $i;
                return date('Y-m-d', strtotime("+{$daysToAdd} days")) . ' ' . $time . ':00';
            }
        }
        
        return null;
    }

    /**
     * Obter agentes ativos
     */
    public static function whereActive(): array
    {
        return self::where('enabled', '=', true);
    }

    /**
     * Decodificar JSON fields
     */
    public static function find(int $id): ?array
    {
        $agent = parent::find($id);
        if ($agent) {
            $agent['target_funnel_ids'] = $agent['target_funnel_ids'] ? json_decode($agent['target_funnel_ids'], true) : null;
            $agent['target_stage_ids'] = $agent['target_stage_ids'] ? json_decode($agent['target_stage_ids'], true) : null;
            $agent['execution_schedule'] = $agent['execution_schedule'] ? json_decode($agent['execution_schedule'], true) : null;
            $agent['conditions'] = $agent['conditions'] ? json_decode($agent['conditions'], true) : [];
            $agent['actions'] = $agent['actions'] ? json_decode($agent['actions'], true) : [];
            $agent['settings'] = $agent['settings'] ? json_decode($agent['settings'], true) : [];
        }
        return $agent;
    }

    /**
     * Criar agente
     */
    public static function create(array $data): int
    {
        // Codificar campos JSON
        if (isset($data['target_funnel_ids'])) {
            $data['target_funnel_ids'] = !empty($data['target_funnel_ids']) ? json_encode($data['target_funnel_ids'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (isset($data['target_stage_ids'])) {
            $data['target_stage_ids'] = !empty($data['target_stage_ids']) ? json_encode($data['target_stage_ids'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (isset($data['execution_schedule'])) {
            $data['execution_schedule'] = !empty($data['execution_schedule']) ? json_encode($data['execution_schedule'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (isset($data['conditions'])) {
            $data['conditions'] = !empty($data['conditions']) ? json_encode($data['conditions'], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['actions'])) {
            $data['actions'] = !empty($data['actions']) ? json_encode($data['actions'], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['settings'])) {
            $data['settings'] = !empty($data['settings']) ? json_encode($data['settings'], JSON_UNESCAPED_UNICODE) : null;
        }

        return parent::create($data);
    }

    /**
     * Atualizar agente
     */
    public static function update(int $id, array $data): bool
    {
        // Codificar campos JSON
        if (isset($data['target_funnel_ids'])) {
            $data['target_funnel_ids'] = !empty($data['target_funnel_ids']) ? json_encode($data['target_funnel_ids'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (isset($data['target_stage_ids'])) {
            $data['target_stage_ids'] = !empty($data['target_stage_ids']) ? json_encode($data['target_stage_ids'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (isset($data['execution_schedule'])) {
            $data['execution_schedule'] = !empty($data['execution_schedule']) ? json_encode($data['execution_schedule'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (isset($data['conditions'])) {
            $data['conditions'] = !empty($data['conditions']) ? json_encode($data['conditions'], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['actions'])) {
            $data['actions'] = !empty($data['actions']) ? json_encode($data['actions'], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['settings'])) {
            $data['settings'] = !empty($data['settings']) ? json_encode($data['settings'], JSON_UNESCAPED_UNICODE) : null;
        }

        return parent::update($id, $data);
    }
}

