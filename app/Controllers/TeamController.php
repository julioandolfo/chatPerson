<?php
/**
 * Controller TeamController
 * Gerenciamento de Times/Equipes
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\TeamService;
use App\Services\TeamPerformanceService;
use App\Models\Team;
use App\Models\User;
use App\Models\Department;

class TeamController
{
    /**
     * Listar todos os times
     */
    public function index(): void
    {
        Permission::abortIfCannot('teams.view');
        
        try {
            $teams = TeamService::list(true);
            
            Response::view('teams/index', [
                'teams' => $teams,
                'title' => 'Times'
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao listar times: " . $e->getMessage());
            Response::redirect('/dashboard', 'error', 'Erro ao carregar times');
        }
    }
    
    /**
     * Exibir formulário de criação
     */
    public function create(): void
    {
        Permission::abortIfCannot('teams.create');
        
        try {
            $agents = User::getAgents();
            $departments = Department::all();
            
            Response::view('teams/form', [
                'team' => null,
                'agents' => $agents,
                'departments' => $departments,
                'title' => 'Criar Time'
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao exibir formulário de criação: " . $e->getMessage());
            Response::redirect('/teams', 'error', 'Erro ao carregar formulário');
        }
    }
    
    /**
     * Salvar novo time
     */
    public function store(): void
    {
        Permission::abortIfCannot('teams.create');
        
        try {
            $data = [
                'name' => Request::post('name'),
                'description' => Request::post('description'),
                'color' => Request::post('color'),
                'leader_id' => Request::post('leader_id') ? (int)Request::post('leader_id') : null,
                'department_id' => Request::post('department_id') ? (int)Request::post('department_id') : null,
                'is_active' => Request::post('is_active', 1)
            ];
            
            $teamId = TeamService::create($data);
            
            // Adicionar membros se informados
            $memberIds = Request::post('member_ids', []);
            if (!empty($memberIds) && is_array($memberIds)) {
                TeamService::addMembers($teamId, $memberIds);
            }
            
            Response::redirect('/teams', 'success', 'Time criado com sucesso!');
        } catch (\Exception $e) {
            error_log("Erro ao criar time: " . $e->getMessage());
            Response::redirect('/teams/create', 'error', 'Erro ao criar time: ' . $e->getMessage());
        }
    }
    
    /**
     * Exibir detalhes do time
     */
    public function show(): void
    {
        Permission::abortIfCannot('teams.view');
        
        $id = (int)Request::get('id');
        
        try {
            $team = TeamService::getDetails($id);
            
            if (!$team) {
                Response::redirect('/teams', 'error', 'Time não encontrado');
                return;
            }
            
            // Obter métricas do time
            $dateFrom = Request::get('date_from', date('Y-m-01'));
            $dateTo = Request::get('date_to', date('Y-m-d'));
            
            $performance = TeamPerformanceService::getPerformanceStats($id, $dateFrom, $dateTo);
            
            Response::view('teams/show', [
                'team' => $team,
                'performance' => $performance,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'title' => 'Time: ' . $team['name']
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao exibir time: " . $e->getMessage());
            Response::redirect('/teams', 'error', 'Erro ao carregar time');
        }
    }
    
    /**
     * Exibir formulário de edição
     */
    public function edit(): void
    {
        Permission::abortIfCannot('teams.edit');
        
        $id = (int)Request::get('id');
        
        try {
            $team = TeamService::getDetails($id);
            
            if (!$team) {
                Response::redirect('/teams', 'error', 'Time não encontrado');
                return;
            }
            
            $agents = User::getAgents();
            $departments = Department::all();
            
            Response::view('teams/form', [
                'team' => $team,
                'agents' => $agents,
                'departments' => $departments,
                'title' => 'Editar Time'
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao exibir formulário de edição: " . $e->getMessage());
            Response::redirect('/teams', 'error', 'Erro ao carregar formulário');
        }
    }
    
    /**
     * Atualizar time
     */
    public function update(): void
    {
        Permission::abortIfCannot('teams.edit');
        
        $id = (int)Request::post('id');
        
        try {
            $data = [
                'name' => Request::post('name'),
                'description' => Request::post('description'),
                'color' => Request::post('color'),
                'leader_id' => Request::post('leader_id') ? (int)Request::post('leader_id') : null,
                'department_id' => Request::post('department_id') ? (int)Request::post('department_id') : null,
                'is_active' => Request::post('is_active', 1)
            ];
            
            TeamService::update($id, $data);
            
            // Sincronizar membros se informados
            $memberIds = Request::post('member_ids');
            if (is_array($memberIds)) {
                TeamService::syncMembers($id, $memberIds);
            }
            
            Response::redirect('/teams?id=' . $id, 'success', 'Time atualizado com sucesso!');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar time: " . $e->getMessage());
            Response::redirect('/teams/edit?id=' . $id, 'error', 'Erro ao atualizar time: ' . $e->getMessage());
        }
    }
    
    /**
     * Deletar time
     */
    public function delete(): void
    {
        Permission::abortIfCannot('teams.delete');
        
        $id = (int)Request::post('id');
        
        try {
            TeamService::delete($id);
            Response::json(['success' => true, 'message' => 'Time deletado com sucesso!']);
        } catch (\Exception $e) {
            error_log("Erro ao deletar time: " . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao deletar time'], 500);
        }
    }
    
    /**
     * Dashboard de times com métricas comparativas
     */
    public function dashboard(): void
    {
        Permission::abortIfCannot('teams.view');
        
        try {
            $dateFrom = Request::get('date_from', date('Y-m-01'));
            $dateTo = Request::get('date_to', date('Y-m-d'));
            
            // Ranking de times
            $teamsRanking = TeamPerformanceService::getTeamsRanking($dateFrom, $dateTo, 20);
            
            // Times ativos
            $teams = TeamService::list(true);
            
            // Métricas de conversão WooCommerce por time (se usuário tiver permissão)
            $conversionByTeam = [];
            if (Permission::can('conversion.view')) {
                try {
                    foreach ($teams as $team) {
                        // Buscar membros do time que são vendedores (têm woocommerce_seller_id)
                        $members = TeamService::getMembers($team['id']);
                        $sellerMembers = array_filter($members, function($member) {
                            return !empty($member['woocommerce_seller_id']);
                        });
                        
                        if (empty($sellerMembers)) {
                            continue; // Pular times sem vendedores
                        }
                        
                        $totalConversations = 0;
                        $totalOrders = 0;
                        $totalRevenue = 0;
                        
                        foreach ($sellerMembers as $seller) {
                            $metrics = \App\Services\AgentConversionService::getConversionMetrics(
                                $seller['id'],
                                $dateFrom,
                                $dateTo
                            );
                            
                            $totalConversations += $metrics['total_conversations'];
                            $totalOrders += $metrics['total_orders'];
                            $totalRevenue += $metrics['total_revenue'];
                        }
                        
                        if ($totalConversations > 0 || $totalOrders > 0) {
                            $conversionRate = $totalConversations > 0 
                                ? round(($totalOrders / $totalConversations) * 100, 2) 
                                : 0;
                            
                            $avgTicket = $totalOrders > 0 
                                ? round($totalRevenue / $totalOrders, 2) 
                                : 0;
                            
                            $conversionByTeam[] = [
                                'team_id' => $team['id'],
                                'team_name' => $team['name'],
                                'team_color' => $team['color'],
                                'sellers_count' => count($sellerMembers),
                                'total_conversations' => $totalConversations,
                                'total_orders' => $totalOrders,
                                'conversion_rate' => $conversionRate,
                                'total_revenue' => $totalRevenue,
                                'avg_ticket' => $avgTicket
                            ];
                        }
                    }
                    
                    // Ordenar por taxa de conversão (decrescente)
                    usort($conversionByTeam, function($a, $b) {
                        return $b['conversion_rate'] <=> $a['conversion_rate'];
                    });
                } catch (\Exception $e) {
                    error_log("Erro ao carregar métricas de conversão por time: " . $e->getMessage());
                }
            }
            
            Response::view('teams/dashboard', [
                'teamsRanking' => $teamsRanking,
                'teams' => $teams,
                'conversionByTeam' => $conversionByTeam,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'title' => 'Dashboard de Times'
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao carregar dashboard de times: " . $e->getMessage());
            Response::redirect('/teams', 'error', 'Erro ao carregar dashboard');
        }
    }
    
    /**
     * API: Obter performance de um time (JSON)
     */
    public function getPerformance(): void
    {
        Permission::abortIfCannot('teams.view');
        
        $id = (int)Request::get('id');
        $dateFrom = Request::get('date_from', date('Y-m-01'));
        $dateTo = Request::get('date_to', date('Y-m-d'));
        
        try {
            $performance = TeamPerformanceService::getPerformanceStats($id, $dateFrom, $dateTo);
            Response::json(['success' => true, 'data' => $performance]);
        } catch (\Exception $e) {
            error_log("Erro ao obter performance do time: " . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao obter performance'], 500);
        }
    }
    
    /**
     * API: Comparar múltiplos times (JSON)
     */
    public function compareTeams(): void
    {
        Permission::abortIfCannot('teams.view');
        
        $teamIds = Request::post('team_ids', []);
        $dateFrom = Request::post('date_from', date('Y-m-01'));
        $dateTo = Request::post('date_to', date('Y-m-d'));
        
        try {
            if (empty($teamIds) || !is_array($teamIds)) {
                Response::json(['success' => false, 'message' => 'IDs dos times não informados'], 400);
                return;
            }
            
            $comparison = TeamPerformanceService::compareTeams($teamIds, $dateFrom, $dateTo);
            Response::json(['success' => true, 'data' => $comparison]);
        } catch (\Exception $e) {
            error_log("Erro ao comparar times: " . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Erro ao comparar times'], 500);
        }
    }
}
