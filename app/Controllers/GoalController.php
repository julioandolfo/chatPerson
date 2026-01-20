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
use App\Services\GoalService;
use App\Models\Goal;
use App\Models\GoalProgress;
use App\Models\GoalAchievement;
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
            'departments' => Department::getActive()
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
                'created_by' => Auth::id()
            ];
            
            $goalId = GoalService::create($data);
            
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
        
        $data = [
            'goal' => $goal,
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
                'reward_badge' => Request::post('reward_badge') ?: null
            ];
            
            GoalService::update($id, $data);
            
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
            $goalId = Request::post('goal_id');
            $startDate = Request::post('start_date');
            $endDate = Request::post('end_date');
            $newName = Request::post('name') ?: null;
            
            $newGoalId = Goal::duplicateAsTemplate($goalId, $startDate, $endDate, $newName);
            
            Response::json([
                'success' => true,
                'goal_id' => $newGoalId,
                'message' => 'Meta duplicada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json(['error' => $e->getMessage()], 400);
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
}
