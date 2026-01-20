<?php
/**
 * Model Goal
 * Sistema de Metas
 */

namespace App\Models;

use App\Helpers\Database;

class Goal extends Model
{
    protected string $table = 'goals';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name',
        'description',
        'type',
        'target_type',
        'target_id',
        'target_value',
        'period_type',
        'start_date',
        'end_date',
        'is_active',
        'is_stretch',
        'priority',
        'notify_at_percentage',
        'flag_critical_threshold',
        'flag_warning_threshold',
        'flag_good_threshold',
        'enable_projection',
        'alert_on_risk',
        'template_id',
        'reward_points',
        'reward_badge',
        'created_by'
    ];
    protected bool $timestamps = true;
    
    /**
     * Tipos de meta disponíveis
     */
    const TYPES = [
        'revenue' => ['label' => 'Faturamento Total', 'unit' => 'R$', 'format' => 'currency'],
        'average_ticket' => ['label' => 'Ticket Médio', 'unit' => 'R$', 'format' => 'currency'],
        'conversion_rate' => ['label' => 'Taxa de Conversão', 'unit' => '%', 'format' => 'percentage'],
        'sales_count' => ['label' => 'Quantidade de Vendas', 'unit' => 'vendas', 'format' => 'number'],
        'conversations_count' => ['label' => 'Quantidade de Conversas', 'unit' => 'conversas', 'format' => 'number'],
        'resolution_rate' => ['label' => 'Taxa de Resolução', 'unit' => '%', 'format' => 'percentage'],
        'response_time' => ['label' => 'Tempo Médio de Resposta', 'unit' => 'min', 'format' => 'time'],
        'csat_score' => ['label' => 'CSAT Médio', 'unit' => '/5', 'format' => 'decimal'],
        'messages_sent' => ['label' => 'Mensagens Enviadas', 'unit' => 'mensagens', 'format' => 'number'],
        'sla_compliance' => ['label' => 'Taxa de Cumprimento SLA', 'unit' => '%', 'format' => 'percentage'],
        'first_response_time' => ['label' => 'Tempo de Primeira Resposta', 'unit' => 'min', 'format' => 'time'],
        'resolution_time' => ['label' => 'Tempo de Resolução', 'unit' => 'min', 'format' => 'time']
    ];
    
    /**
     * Níveis de meta
     */
    const TARGET_TYPES = [
        'individual' => 'Agente Individual',
        'team' => 'Time/Equipe',
        'department' => 'Departamento',
        'global' => 'Empresa (Global)'
    ];
    
    /**
     * Períodos
     */
    const PERIODS = [
        'daily' => 'Diário',
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'yearly' => 'Anual',
        'custom' => 'Personalizado'
    ];
    
    /**
     * Obter metas ativas
     */
    public static function getActive(array $filters = []): array
    {
        $sql = "SELECT g.*, 
                       u.name as creator_name,
                       CASE 
                           WHEN g.target_type = 'individual' THEN (SELECT name FROM users WHERE id = g.target_id)
                           WHEN g.target_type = 'team' THEN (SELECT name FROM teams WHERE id = g.target_id)
                           WHEN g.target_type = 'department' THEN (SELECT name FROM departments WHERE id = g.target_id)
                           ELSE 'Empresa'
                       END as target_name
                FROM goals g
                LEFT JOIN users u ON g.created_by = u.id
                WHERE g.is_active = 1";
        
        $params = [];
        
        if (!empty($filters['target_type'])) {
            $sql .= " AND g.target_type = ?";
            $params[] = $filters['target_type'];
        }
        
        if (!empty($filters['target_id'])) {
            $sql .= " AND g.target_id = ?";
            $params[] = $filters['target_id'];
        }
        
        if (!empty($filters['type'])) {
            $sql .= " AND g.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['period'])) {
            $sql .= " AND g.period_type = ?";
            $params[] = $filters['period'];
        }
        
        // Filtrar por período ativo
        if (!empty($filters['active_period'])) {
            $sql .= " AND g.start_date <= CURDATE() AND g.end_date >= CURDATE()";
        }
        
        $sql .= " ORDER BY g.priority DESC, g.end_date ASC";
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Obter meta com detalhes
     */
    public static function findWithDetails(int $id): ?array
    {
        $sql = "SELECT g.*, 
                       u.name as creator_name,
                       u.email as creator_email,
                       CASE 
                           WHEN g.target_type = 'individual' THEN (SELECT name FROM users WHERE id = g.target_id)
                           WHEN g.target_type = 'team' THEN (SELECT name FROM teams WHERE id = g.target_id)
                           WHEN g.target_type = 'department' THEN (SELECT name FROM departments WHERE id = g.target_id)
                           ELSE 'Empresa'
                       END as target_name
                FROM goals g
                LEFT JOIN users u ON g.created_by = u.id
                WHERE g.id = ?";
        return Database::fetch($sql, [$id]);
    }
    
    /**
     * Obter metas de um agente específico
     */
    public static function getAgentGoals(int $agentId): array
    {
        // Buscar metas individuais do agente
        $individualGoals = Database::fetchAll(
            "SELECT * FROM goals WHERE target_type = 'individual' AND target_id = ? AND is_active = 1 
             AND start_date <= CURDATE() AND end_date >= CURDATE()
             ORDER BY priority DESC",
            [$agentId]
        );
        
        // Buscar times do agente
        $teams = Database::fetchAll(
            "SELECT team_id FROM team_members WHERE user_id = ?",
            [$agentId]
        );
        
        $teamGoals = [];
        if (!empty($teams)) {
            $teamIds = array_column($teams, 'team_id');
            $placeholders = str_repeat('?,', count($teamIds) - 1) . '?';
            $teamGoals = Database::fetchAll(
                "SELECT * FROM goals WHERE target_type = 'team' AND target_id IN ($placeholders) 
                 AND is_active = 1 AND start_date <= CURDATE() AND end_date >= CURDATE()
                 ORDER BY priority DESC",
                $teamIds
            );
        }
        
        // Buscar departamentos do agente (relação muitos-para-muitos)
        $departments = Database::fetchAll(
            "SELECT department_id FROM agent_departments WHERE user_id = ?",
            [$agentId]
        );
        
        $departmentGoals = [];
        if (!empty($departments)) {
            $departmentIds = array_column($departments, 'department_id');
            $placeholders = str_repeat('?,', count($departmentIds) - 1) . '?';
            $departmentGoals = Database::fetchAll(
                "SELECT * FROM goals WHERE target_type = 'department' AND target_id IN ($placeholders)
                 AND is_active = 1 AND start_date <= CURDATE() AND end_date >= CURDATE()
                 ORDER BY priority DESC",
                $departmentIds
            );
        }
        
        // Metas globais
        $globalGoals = Database::fetchAll(
            "SELECT * FROM goals WHERE target_type = 'global' AND is_active = 1 
             AND start_date <= CURDATE() AND end_date >= CURDATE()
             ORDER BY priority DESC"
        );
        
        return [
            'individual' => $individualGoals,
            'team' => $teamGoals,
            'department' => $departmentGoals,
            'global' => $globalGoals
        ];
    }
    
    /**
     * Obter metas de um time
     */
    public static function getTeamGoals(int $teamId): array
    {
        return Database::fetchAll(
            "SELECT * FROM goals WHERE target_type = 'team' AND target_id = ? AND is_active = 1 
             AND start_date <= CURDATE() AND end_date >= CURDATE()
             ORDER BY priority DESC",
            [$teamId]
        );
    }
    
    /**
     * Obter progresso atual da meta
     */
    public function getCurrentProgress(): ?array
    {
        return Database::fetch(
            "SELECT * FROM goal_progress WHERE goal_id = ? ORDER BY date DESC LIMIT 1",
            [$this->id ?? 0]
        );
    }
    
    /**
     * Verificar se meta foi atingida
     */
    public function isAchieved(): bool
    {
        $progress = $this->getCurrentProgress();
        return $progress && $progress['percentage'] >= 100;
    }
    
    /**
     * Formatar valor de acordo com o tipo
     */
    public static function formatValue(string $type, float $value): string
    {
        $config = self::TYPES[$type] ?? ['format' => 'number'];
        
        switch ($config['format']) {
            case 'currency':
                return 'R$ ' . number_format($value, 2, ',', '.');
            case 'percentage':
                return number_format($value, 1, ',', '.') . '%';
            case 'time':
                return number_format($value, 0) . ' min';
            case 'decimal':
                return number_format($value, 1, ',', '.');
            default:
                return number_format($value, 0, ',', '.');
        }
    }
    
    /**
     * Determinar flag baseado no percentual e thresholds
     */
    public function getFlagStatus(float $percentage): string
    {
        $critical = $this->data['flag_critical_threshold'] ?? 70.0;
        $warning = $this->data['flag_warning_threshold'] ?? 85.0;
        $good = $this->data['flag_good_threshold'] ?? 95.0;
        
        if ($percentage >= 100) {
            return 'excellent'; // Atingiu ou superou
        } elseif ($percentage >= $good) {
            return 'good'; // Verde - Bom ritmo
        } elseif ($percentage >= $warning) {
            return 'warning'; // Amarelo - Atenção
        } elseif ($percentage >= $critical) {
            return 'warning'; // Amarelo ainda
        } else {
            return 'critical'; // Vermelho - Crítico
        }
    }
    
    /**
     * Cores das flags
     */
    public static function getFlagColor(string $flag): string
    {
        return [
            'critical' => 'danger',
            'warning' => 'warning',
            'good' => 'success',
            'excellent' => 'primary'
        ][$flag] ?? 'secondary';
    }
    
    /**
     * Labels das flags
     */
    public static function getFlagLabel(string $flag): string
    {
        return [
            'critical' => 'Crítico',
            'warning' => 'Atenção',
            'good' => 'No Caminho',
            'excellent' => 'Atingida'
        ][$flag] ?? 'Desconhecido';
    }
    
    /**
     * Duplicar meta (para criar metas mensais recorrentes)
     */
    public static function duplicateAsTemplate(int $goalId, string $newStartDate, string $newEndDate, ?string $newName = null): int
    {
        $original = self::find($goalId);
        if (!$original) {
            throw new \InvalidArgumentException('Meta não encontrada');
        }
        
        $newGoal = $original;
        unset($newGoal['id']);
        unset($newGoal['created_at']);
        unset($newGoal['updated_at']);
        
        $newGoal['name'] = $newName ?? $original['name'];
        $newGoal['start_date'] = $newStartDate;
        $newGoal['end_date'] = $newEndDate;
        $newGoal['template_id'] = $goalId;
        
        return self::create($newGoal);
    }
}
