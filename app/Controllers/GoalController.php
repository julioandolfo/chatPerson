<?php
/**
 * Controller GoalController
 * Gerenciamento de Metas
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Logger;
use App\Services\GoalService;
use App\Models\Goal;
use App\Models\GoalProgress;
use App\Models\GoalAchievement;
use App\Models\GoalBonusCondition;
use App\Models\User;
use App\Models\Team;
use App\Models\Department;

class GoalController
{
    /**
     * Listar metas
     */
    public function index(): void
    {
        Permission::abortIfCannot('goals.view');
        
        $filters = [
            'target_type' => Request::get('target_type'),
            'type' => Request::get('type'),
            'period' => Request::get('period_type'),
            'active_period' => Request::get('active_only', '1') === '1'
        ];
        
        $goals = Goal::getActive($filters);
        
        // Adicionar progresso para cada meta
        foreach ($goals as &$goal) {
            $progress = GoalProgress::getLatest($goal['id']);
            $goal['progress'] = $progress;
        }
        
        $data = [
            'goals' => $goals,
            'filters' => $filters,
            'types' => Goal::TYPES,
            'target_types' => Goal::TARGET_TYPES,
            'periods' => Goal::PERIODS
        ];
        
        Response::view('goals/index', $data);
    }
    
    /**
     * Formulário criar meta
     */
    public function create(): void
    {
        Permission::abortIfCannot('goals.create');
        
        $data = [
            'types' => Goal::TYPES,
            'target_types' => Goal::TARGET_TYPES,
            'periods' => Goal::PERIODS,
            'agents' => User::getActiveAgents(),
            'teams' => Team::getActive(),
            'departments' => Department::getActive(),
            'bonusConditions' => [], // Array vazio para nova meta
            'bonusTiers' => [] // Array vazio para nova meta
        ];
        
        Response::view('goals/form', $data);
    }
    
    /**
     * Salvar nova meta
     */
    public function store(): void
    {
        Permission::abortIfCannot('goals.create');
        
        try {
            $data = [
                'name' => Request::post('name'),
                'description' => Request::post('description'),
                'type' => Request::post('type'),
                'target_type' => Request::post('target_type'),
                'target_id' => Request::post('target_id') ?: null,
                'target_value' => Request::post('target_value'),
                'period_type' => Request::post('period_type'),
                'start_date' => Request::post('start_date'),
                'end_date' => Request::post('end_date'),
                'is_stretch' => Request::post('is_stretch', '0') === '1' ? 1 : 0,
                'priority' => Request::post('priority', 'medium'),
                'notify_at_percentage' => Request::post('notify_at_percentage', 90),
                'flag_critical_threshold' => Request::post('flag_critical_threshold', 70.0),
                'flag_warning_threshold' => Request::post('flag_warning_threshold', 85.0),
                'flag_good_threshold' => Request::post('flag_good_threshold', 95.0),
                'enable_projection' => Request::post('enable_projection', '1') === '1' ? 1 : 0,
                'alert_on_risk' => Request::post('alert_on_risk', '1') === '1' ? 1 : 0,
                'reward_points' => Request::post('reward_points', 0),
                'reward_badge' => Request::post('reward_badge') ?: null,
                'enable_bonus' => Request::post('enable_bonus', '0') === '1' ? 1 : 0,
                'enable_bonus_conditions' => Request::post('enable_bonus_conditions', '0') === '1' ? 1 : 0,
                'ote_base_salary' => Request::post('ote_base_salary') ?: null,
                'ote_target_commission' => Request::post('ote_target_commission') ?: null,
                'ote_total' => Request::post('ote_base_salary') && Request::post('ote_target_commission') 
                    ? floatval(Request::post('ote_base_salary')) + floatval(Request::post('ote_target_commission'))
                    : null,
                'bonus_calculation_type' => Request::post('bonus_calculation_type', 'tiered'),
                'created_by' => Auth::id()
            ];
            
            $goalId = GoalService::create($data);
            
            // Processar tiers de bônus (se bonificação habilitada)
            if ($data['enable_bonus']) {
                $this->saveBonusTiers($goalId);
            }
            
            // Processar condições de bônus (se habilitadas)
            if ($data['enable_bonus_conditions']) {
                $this->saveGoalConditions($goalId);
            }
            
            Response::redirect('/goals', 'success', 'Meta criada com sucesso!');
        } catch (\Exception $e) {
            Response::redirect('/goals/create', 'error', 'Erro ao criar meta: ' . $e->getMessage());
        }
    }
    
    /**
     * Visualizar meta
     */
    public function show(): void
    {
        Permission::abortIfCannot('goals.view');
        
        $id = Request::get('id');
        $goal = Goal::findWithDetails($id);
        
        if (!$goal) {
            Response::redirect('/goals', 'error', 'Meta não encontrada');
            return;
        }
        
        // Progresso atual
        $currentProgress = GoalProgress::getLatest($id);
        
        // Histórico (últimos 30 dias)
        $history = GoalProgress::getHistory($id, 30);
        
        // Estatísticas
        $stats = GoalProgress::getStats($id);
        
        // Conquista (se atingida)
        $achievement = GoalAchievement::isAchieved($id) 
            ? \App\Helpers\Database::fetch("SELECT * FROM goal_achievements WHERE goal_id = ?", [$id])
            : null;
        
        $data = [
            'goal' => $goal,
            'progress' => $currentProgress,
            'history' => $history,
            'stats' => $stats,
            'achievement' => $achievement
        ];
        
        Response::view('goals/show', $data);
    }
    
    /**
     * Formulário editar meta
     */
    public function edit(): void
    {
        Permission::abortIfCannot('goals.edit');
        
        $id = Request::get('id');
        $goal = Goal::find($id);
        
        if (!$goal) {
            Response::redirect('/goals', 'error', 'Meta não encontrada');
            return;
        }
        
        // Carregar condições de bônus existentes
        $bonusConditions = GoalBonusCondition::getByGoal((int)$id);
        
        // Carregar tiers de bônus existentes
        $bonusTiers = Database::fetchAll(
            "SELECT * FROM goal_bonus_tiers WHERE goal_id = ? ORDER BY tier_order, threshold_percentage",
            [$id]
        );
        
        $data = [
            'goal' => $goal,
            'bonusConditions' => $bonusConditions,
            'bonusTiers' => $bonusTiers,
            'types' => Goal::TYPES,
            'target_types' => Goal::TARGET_TYPES,
            'periods' => Goal::PERIODS,
            'agents' => User::getActiveAgents(),
            'teams' => Team::getActive(),
            'departments' => Department::getActive()
        ];
        
        Response::view('goals/form', $data);
    }
    
    /**
     * Atualizar meta
     */
    public function update(): void
    {
        Permission::abortIfCannot('goals.edit');
        
        try {
            $id = Request::post('id');
            $data = [
                'name' => Request::post('name'),
                'description' => Request::post('description'),
                'type' => Request::post('type'),
                'target_type' => Request::post('target_type'),
                'target_id' => Request::post('target_id') ?: null,
                'target_value' => Request::post('target_value'),
                'period_type' => Request::post('period_type'),
                'start_date' => Request::post('start_date'),
                'end_date' => Request::post('end_date'),
                'is_active' => Request::post('is_active', '1') === '1' ? 1 : 0,
                'is_stretch' => Request::post('is_stretch', '0') === '1' ? 1 : 0,
                'priority' => Request::post('priority', 'medium'),
                'notify_at_percentage' => Request::post('notify_at_percentage', 90),
                'flag_critical_threshold' => Request::post('flag_critical_threshold', 70.0),
                'flag_warning_threshold' => Request::post('flag_warning_threshold', 85.0),
                'flag_good_threshold' => Request::post('flag_good_threshold', 95.0),
                'enable_projection' => Request::post('enable_projection', '1') === '1' ? 1 : 0,
                'alert_on_risk' => Request::post('alert_on_risk', '1') === '1' ? 1 : 0,
                'reward_points' => Request::post('reward_points', 0),
                'reward_badge' => Request::post('reward_badge') ?: null,
                'enable_bonus' => Request::post('enable_bonus', '0') === '1' ? 1 : 0,
                'enable_bonus_conditions' => Request::post('enable_bonus_conditions', '0') === '1' ? 1 : 0,
                'ote_base_salary' => Request::post('ote_base_salary') ?: null,
                'ote_target_commission' => Request::post('ote_target_commission') ?: null,
                'ote_total' => Request::post('ote_base_salary') && Request::post('ote_target_commission') 
                    ? floatval(Request::post('ote_base_salary')) + floatval(Request::post('ote_target_commission'))
                    : null,
                'bonus_calculation_type' => Request::post('bonus_calculation_type', 'tiered')
            ];
            
            GoalService::update($id, $data);
            
            // Processar tiers de bônus (sempre, pois pode estar editando)
            $this->saveBonusTiers((int)$id);
            
            // Processar condições de bônus (se habilitadas)
            $this->saveGoalConditions((int)$id);
            
            Response::redirect('/goals', 'success', 'Meta atualizada com sucesso!');
        } catch (\Exception $e) {
            Response::redirect('/goals/edit?id=' . Request::post('id'), 'error', 'Erro ao atualizar meta: ' . $e->getMessage());
        }
    }
    
    /**
     * Deletar meta
     */
    public function delete(): void
    {
        Permission::abortIfCannot('goals.delete');
        
        try {
            $id = Request::post('id');
            GoalService::delete($id);
            
            Response::redirect('/goals', 'success', 'Meta deletada com sucesso!');
        } catch (\Exception $e) {
            Response::redirect('/goals', 'error', 'Erro ao deletar meta: ' . $e->getMessage());
        }
    }
    
    /**
     * Dashboard de metas
     */
    public function dashboard(): void
    {
        Permission::abortIfCannot('goals.view');
        
        $userId = Auth::id();
        $summary = GoalService::getDashboardSummary($userId);
        
        // Conquistas recentes
        $recentAchievements = GoalAchievement::getAgentAchievements($userId);
        
        // Metas globais e de time
        $globalGoals = Goal::getActive(['target_type' => 'global', 'active_period' => true]);
        foreach ($globalGoals as &$goal) {
            $goal['progress'] = GoalProgress::getLatest($goal['id']);
        }
        
        $data = [
            'summary' => $summary,
            'achievements' => $recentAchievements,
            'global_goals' => $globalGoals
        ];
        
        Response::view('goals/dashboard', $data);
    }
    
    /**
     * API: Calcular progresso de uma meta
     */
    public function calculateProgress(): void
    {
        Permission::abortIfCannot('goals.view');
        
        try {
            $id = Request::get('id');
            $progress = GoalService::calculateProgress($id);
            
            Response::json($progress);
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * API: Calcular progresso de todas as metas
     */
    public function calculateAllProgress(): void
    {
        Permission::abortIfCannot('goals.edit');
        
        try {
            $results = GoalService::calculateAllProgress();
            
            Response::json([
                'success' => true,
                'calculated' => count($results),
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * API: Obter metas de um agente
     */
    public function getAgentGoals(): void
    {
        Permission::abortIfCannot('goals.view');
        
        $agentId = Request::get('agent_id', Auth::id());
        $goals = Goal::getAgentGoals($agentId);
        
        // Adicionar progresso
        foreach ($goals as $level => &$levelGoals) {
            foreach ($levelGoals as &$goal) {
                $goal['progress'] = GoalProgress::getLatest($goal['id']);
            }
        }
        
        Response::json($goals);
    }
    
    /**
     * Duplicar meta (para criar metas mensais recorrentes)
     */
    public function duplicate(): void
    {
        Permission::abortIfCannot('goals.create');
        
        try {
            // Aceitar tanto POST form quanto JSON
            $data = Request::json() ?: [];
            
            $goalId = $data['goal_id'] ?? Request::post('goal_id');
            $startDate = $data['start_date'] ?? Request::post('start_date');
            $endDate = $data['end_date'] ?? Request::post('end_date');
            $newName = $data['name'] ?? Request::post('name') ?: null;
            $periodType = $data['period_type'] ?? Request::post('period_type') ?: null;
            
            if (empty($goalId) || empty($startDate) || empty($endDate)) {
                throw new \InvalidArgumentException('Dados obrigatórios não informados');
            }
            
            $newGoalId = Goal::duplicateAsTemplate((int)$goalId, $startDate, $endDate, $newName);
            
            // Atualizar o period_type se informado
            if ($periodType && $newGoalId) {
                Goal::update($newGoalId, ['period_type' => $periodType]);
            }
            
            Response::json([
                'success' => true,
                'goal_id' => $newGoalId,
                'message' => 'Meta duplicada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Criar metas mensais (Janeiro a Dezembro)
     */
    public function createMonthlyGoals(): void
    {
        Permission::abortIfCannot('goals.create');
        
        try {
            $goalId = Request::post('goal_id');
            $year = Request::post('year', date('Y'));
            
            $created = [];
            
            for ($month = 1; $month <= 12; $month++) {
                $monthName = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'][$month];
                
                $startDate = sprintf('%s-%02d-01', $year, $month);
                $endDate = date('Y-m-t', strtotime($startDate)); // Último dia do mês
                
                $original = Goal::find($goalId);
                $newName = str_replace(
                    ['{mes}', '{MES}', '{ano}', '{ANO}'],
                    [$monthName, strtoupper($monthName), $year, $year],
                    $original['name']
                ) . " - {$monthName}/{$year}";
                
                $newGoalId = Goal::duplicateAsTemplate($goalId, $startDate, $endDate, $newName);
                $created[] = ['month' => $monthName, 'goal_id' => $newGoalId];
            }
            
            Response::json([
                'success' => true,
                'created' => $created,
                'message' => '12 metas mensais criadas com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Salvar condições de bônus de uma meta
     */
    /**
     * Salvar tiers de bônus
     */
    private function saveBonusTiers(int $goalId): void
    {
        $tiers = Request::post('tiers');
        
        Logger::info("saveBonusTiers - goalId: {$goalId}, tiers: " . json_encode($tiers), 'goals');
        
        // Obter IDs existentes para saber quais remover
        $existingIds = Database::fetchAll(
            "SELECT id FROM goal_bonus_tiers WHERE goal_id = ?",
            [$goalId]
        );
        $existingIdsList = array_column($existingIds, 'id');
        $processedIds = [];
        
        if (!empty($tiers) && is_array($tiers)) {
            foreach ($tiers as $tier) {
                // Pular tiers sem dados essenciais
                if (empty($tier['tier_name']) && empty($tier['threshold_percentage'])) {
                    continue;
                }
                
                $tierData = [
                    'goal_id' => $goalId,
                    'tier_name' => $tier['tier_name'] ?? 'Tier',
                    'threshold_percentage' => floatval($tier['threshold_percentage'] ?? 0),
                    'bonus_amount' => floatval($tier['bonus_amount'] ?? 0),
                    'tier_color' => $tier['tier_color'] ?? 'bronze',
                    'tier_order' => intval($tier['tier_order'] ?? 0),
                    'is_cumulative' => isset($tier['is_cumulative']) ? 1 : 0
                ];
                
                if (!empty($tier['id'])) {
                    // Atualizar tier existente
                    Database::execute(
                        "UPDATE goal_bonus_tiers SET 
                            tier_name = ?, threshold_percentage = ?, bonus_amount = ?, 
                            tier_color = ?, tier_order = ?, is_cumulative = ?
                         WHERE id = ? AND goal_id = ?",
                        [
                            $tierData['tier_name'],
                            $tierData['threshold_percentage'],
                            $tierData['bonus_amount'],
                            $tierData['tier_color'],
                            $tierData['tier_order'],
                            $tierData['is_cumulative'],
                            $tier['id'],
                            $goalId
                        ]
                    );
                    $processedIds[] = $tier['id'];
                } else {
                    // Inserir novo tier
                    Database::execute(
                        "INSERT INTO goal_bonus_tiers 
                            (goal_id, tier_name, threshold_percentage, bonus_amount, tier_color, tier_order, is_cumulative) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [
                            $tierData['goal_id'],
                            $tierData['tier_name'],
                            $tierData['threshold_percentage'],
                            $tierData['bonus_amount'],
                            $tierData['tier_color'],
                            $tierData['tier_order'],
                            $tierData['is_cumulative']
                        ]
                    );
                    $processedIds[] = Database::lastInsertId();
                }
            }
        }
        
        // Remover tiers que não estão mais na lista
        $toRemove = array_diff($existingIdsList, $processedIds);
        if (!empty($toRemove)) {
            $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
            Database::execute(
                "DELETE FROM goal_bonus_tiers WHERE id IN ({$placeholders})",
                array_values($toRemove)
            );
        }
    }
    
    /**
     * Salvar condições de ativação de bônus
     */
    private function saveGoalConditions(int $goalId): void
    {
        $conditions = Request::post('conditions');
        
        Logger::info("saveGoalConditions - goalId: {$goalId}, conditions: " . json_encode($conditions), 'goals');
        
        if (empty($conditions) || !is_array($conditions)) {
            // Se não tem condições, remover existentes
            Database::execute(
                "DELETE FROM goal_bonus_conditions WHERE goal_id = ?",
                [$goalId]
            );
            Logger::info("saveGoalConditions - Nenhuma condição, removidas as existentes", 'goals');
            return;
        }
        
        // Remover condições anteriores
        Database::execute(
            "DELETE FROM goal_bonus_conditions WHERE goal_id = ?",
            [$goalId]
        );
        
        // Inserir novas condições
        $order = 0;
        $inserted = 0;
        foreach ($conditions as $condition) {
            if (empty($condition['condition_type']) || !isset($condition['min_value'])) {
                Logger::info("saveGoalConditions - Condição ignorada (dados incompletos): " . json_encode($condition), 'goals');
                continue;
            }
            
            try {
                $data = [
                    'goal_id' => $goalId,
                    'condition_type' => $condition['condition_type'],
                    'operator' => $condition['operator'] ?? '>=',
                    'min_value' => floatval($condition['min_value']),
                    'max_value' => !empty($condition['max_value']) ? floatval($condition['max_value']) : null,
                    'is_required' => isset($condition['is_required']) ? 1 : 0,
                    'bonus_modifier' => floatval($condition['bonus_modifier'] ?? 0.5),
                    'description' => $condition['description'] ?? null,
                    'check_order' => $order++,
                    'is_active' => 1
                ];
                
                // Usar insert direto ao invés do Model::create para mais controle
                Database::execute(
                    "INSERT INTO goal_bonus_conditions 
                        (goal_id, condition_type, operator, min_value, max_value, is_required, bonus_modifier, description, check_order, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $data['goal_id'],
                        $data['condition_type'],
                        $data['operator'],
                        $data['min_value'],
                        $data['max_value'],
                        $data['is_required'],
                        $data['bonus_modifier'],
                        $data['description'],
                        $data['check_order'],
                        $data['is_active']
                    ]
                );
                $inserted++;
                Logger::info("saveGoalConditions - Condição inserida: " . json_encode($data), 'goals');
            } catch (\Exception $e) {
                Logger::error("saveGoalConditions - Erro ao inserir condição: " . $e->getMessage(), 'goals');
            }
        }
        
        Logger::info("saveGoalConditions - Total inseridas: {$inserted}", 'goals');
    }
}
