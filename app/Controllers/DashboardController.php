<?php
/**
 * Controller do Dashboard
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Database;
use App\Helpers\Permission;

class DashboardController
{
    /**
     * Mostrar dashboard
     */
    public function index(): void
    {
        // Dashboard é acessível a todos os usuários autenticados
        // Mas podemos verificar permissão específica se necessário
        // Permission::abortIfCannot('dashboard.view');
        
        $userId = \App\Helpers\Auth::id();
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-01'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d'));
        
        // Garantir que dateTo inclui o dia inteiro (até 23:59:59)
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        try {
            // Estatísticas gerais
            self::logDash("Carregando stats para userId={$userId}, dateFrom={$dateFrom}, dateTo={$dateTo}");
            $generalStats = \App\Services\DashboardService::getGeneralStats($userId, $dateFrom, $dateTo);
            self::logDash("generalStats = " . json_encode($generalStats));
            
            // Estatísticas por setor
            $departmentStats = \App\Services\DashboardService::getDepartmentStats();
            
            // Estatísticas por funil
            $funnelStats = \App\Services\DashboardService::getFunnelStats();
            
            // Top agentes
            $topAgents = \App\Services\DashboardService::getTopAgents($dateFrom, $dateTo, 5);
            
            // Métricas individuais de todos os agentes (para cards)
            $allAgentsMetrics = \App\Services\DashboardService::getAllAgentsMetrics($dateFrom, $dateTo);
            
            // Métricas de times (se usuário tiver permissão)
            $teamsMetrics = [];
            if (\App\Helpers\Permission::can('teams.view')) {
                try {
                    $teamsMetrics = \App\Services\TeamPerformanceService::getTeamsRanking($dateFrom, $dateTo, 10);
                } catch (\Exception $e) {
                    error_log("Erro ao carregar métricas de times: " . $e->getMessage());
                }
            }
            
            // Métricas de conversão WooCommerce (se usuário tiver permissão)
            $conversionRanking = [];
            if (\App\Helpers\Permission::can('conversion.view')) {
                try {
                    // Buscar apenas vendedores que têm woocommerce_seller_id cadastrado
                    $sellers = \App\Models\User::getSellers();
                    $ranking = [];
                    
                    foreach ($sellers as $seller) {
                        $metrics = \App\Services\AgentConversionService::getConversionMetrics(
                            $seller['id'],
                            $dateFrom,
                            str_replace(' 23:59:59', '', $dateTo)
                        );
                        
                        if ($metrics['total_conversations'] > 0 || $metrics['total_orders'] > 0) {
                            $ranking[] = [
                                'agent_id' => $seller['id'],
                                'agent_name' => $seller['name'],
                                'seller_id' => $seller['woocommerce_seller_id'],
                                'total_conversations' => $metrics['total_conversations'],
                                'total_orders' => $metrics['total_orders'],
                                'conversion_rate' => $metrics['conversion_rate'],
                                'total_revenue' => $metrics['total_revenue'],
                                'avg_ticket' => $metrics['avg_ticket']
                            ];
                        }
                    }
                    
                    // Ordenar por taxa de conversão (decrescente)
                    usort($ranking, function($a, $b) {
                        return $b['conversion_rate'] <=> $a['conversion_rate'];
                    });
                    
                    // Pegar apenas os top 5
                    $conversionRanking = array_slice($ranking, 0, 5);
                } catch (\Exception $e) {
                    error_log("Erro ao carregar métricas de conversão: " . $e->getMessage());
                }
            }
            
            // Conversas recentes (apenas 5)
            $recentConversations = \App\Services\DashboardService::getRecentConversations(5);
            
            // Atividade recente
            $recentActivity = \App\Services\DashboardService::getRecentActivity(10);
            
            self::logDash("Passando dados para view");
            Response::view('dashboard/index', [
                'stats' => $generalStats,
                'departmentStats' => $departmentStats,
                'funnelStats' => $funnelStats,
                'topAgents' => $topAgents,
                'allAgentsMetrics' => $allAgentsMetrics,
                'teamsMetrics' => $teamsMetrics,
                'conversionRanking' => $conversionRanking,
                'recentConversations' => $recentConversations,
                'recentActivity' => $recentActivity,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ]);
        } catch (\Exception $e) {
            self::logDash("ERRO CRÍTICO: " . $e->getMessage());
            self::logDash("Stack trace: " . $e->getTraceAsString());
            // Fallback para estatísticas básicas
            $stats = [
                'conversations' => [
                    'total' => Database::fetch("SELECT COUNT(*) as total FROM conversations")['total'] ?? 0,
                    'open' => Database::fetch("SELECT COUNT(*) as total FROM conversations WHERE status = 'open'")['total'] ?? 0,
                    'my_total' => Database::fetch("SELECT COUNT(*) as total FROM conversations WHERE agent_id = ?", [$userId])['total'] ?? 0,
                ],
                'agents' => ['total' => 0, 'active' => 0, 'online' => 0],
                'contacts' => ['total' => 0],
                'messages' => ['total' => 0],
                'metrics' => ['resolution_rate' => 0, 'avg_first_response_time' => null]
            ];
            
            Response::view('dashboard/index', [
                'stats' => $stats,
                'departmentStats' => [],
                'funnelStats' => [],
                'topAgents' => [],
                'recentConversations' => [],
                'recentActivity' => [],
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ]);
        }
    }

    /**
     * Dashboard específico para Inteligência Artificial
     */
    public function aiDashboard(): void
    {
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-01'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d'));
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        try {
            // Estatísticas gerais (inclui métricas separadas)
            $generalStats = \App\Services\DashboardService::getGeneralStats(null, $dateFrom, $dateTo);
            
            // Métricas de IA extraídas do generalStats
            $aiMetrics = $generalStats['ai_metrics'] ?? [];
            
            // Ranking de agentes de IA
            $aiAgentsRanking = \App\Services\AIAgentPerformanceService::getAIAgentsRanking($dateFrom, $dateTo, 10);
            
            // Comparação IA vs Humanos
            $comparison = \App\Services\AIAgentPerformanceService::getComparisonStats($dateFrom, $dateTo);
            
            // Taxa de cumprimento de SLA separada
            $slaCompliance = \App\Services\SLAMonitoringService::getSLAComplianceRates($dateFrom, $dateTo);
            
            // Métricas de fallback de IA
            $fallbackStats = \App\Services\AIFallbackMonitoringService::getFallbackStats($dateFrom, $dateTo);
            
            Response::view('dashboard/ai-dashboard', [
                'stats' => $generalStats,
                'aiMetrics' => $aiMetrics,
                'aiAgentsRanking' => $aiAgentsRanking,
                'comparison' => $comparison,
                'slaCompliance' => $slaCompliance,
                'fallbackStats' => $fallbackStats,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ]);
        } catch (\Exception $e) {
            self::logDash("ERRO CRÍTICO AI Dashboard: " . $e->getMessage());
            self::logDash("Stack trace: " . $e->getTraceAsString());
            
            // Fallback com dados vazios
            Response::view('dashboard/ai-dashboard', [
                'stats' => ['metrics' => [], 'ai_metrics' => []],
                'aiMetrics' => [],
                'aiAgentsRanking' => [],
                'comparison' => ['ai' => [], 'human' => []],
                'slaCompliance' => ['general' => [], 'ai' => [], 'human' => []],
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ]);
        }
    }

    /**
     * Obter dados de gráficos (AJAX)
     */
    public function getChartData(): void
    {
        $chartType = \App\Helpers\Request::get('type', 'conversations_over_time');
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-01'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d'));
        $groupBy = \App\Helpers\Request::get('group_by', 'day');
        
        // Garantir que dateTo inclui o dia inteiro (até 23:59:59)
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        self::logDash("getChartData: type={$chartType}, dateFrom={$dateFrom}, dateTo={$dateTo}");
        
        try {
            $data = [];
            
            switch ($chartType) {
                case 'conversations_over_time':
                    $data = \App\Services\DashboardService::getConversationsOverTime($dateFrom, $dateTo, $groupBy);
                    break;
                    
                case 'conversations_by_channel':
                    $data = \App\Services\DashboardService::getConversationsByChannelChart($dateFrom, $dateTo);
                    break;
                    
                case 'conversations_by_status':
                    $data = \App\Services\DashboardService::getConversationsByStatusChart($dateFrom, $dateTo);
                    break;
                    
                case 'agents_performance':
                    $limit = (int)\App\Helpers\Request::get('limit', 10);
                    $data = \App\Services\DashboardService::getAgentsPerformanceChart($dateFrom, $dateTo, $limit);
                    break;
                    
                case 'messages_over_time':
                    $data = \App\Services\DashboardService::getMessagesOverTime($dateFrom, $dateTo, $groupBy);
                    break;
                    
                case 'sla_metrics':
                    $data = \App\Services\DashboardService::getSLAMetrics($dateFrom, $dateTo);
                    break;
                    
                default:
                    self::logDash("getChartData: Tipo inválido - {$chartType}");
                    Response::json(['error' => 'Tipo de gráfico inválido'], 400);
                    return;
            }
            
            self::logDash("getChartData: {$chartType} retornou " . count($data) . " registros");
            
            Response::json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            self::logDash("ERRO getChartData: {$chartType} - " . $e->getMessage());
            Response::json([
                'success' => false,
                'error' => 'Erro ao carregar dados do gráfico',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log para arquivo logs/dash.log
     */
    private static function logDash(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/dash.log';
        
        // Verificar se pode escrever
        if (!is_writable($logDir)) {
            @chmod($logDir, 0777);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        // Tentar escrever, mas não falhar se não conseguir
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Exportar relatório (PDF/Excel)
     */
    public function exportReport(): void
    {
        $format = \App\Helpers\Request::get('format', 'pdf'); // pdf, excel, csv
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-01'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d H:i:s'));
        
        // Por enquanto, retornamos JSON. Implementação completa de PDF/Excel requer bibliotecas adicionais
        $stats = \App\Services\DashboardService::getGeneralStats(null, $dateFrom, $dateTo);
        $topAgents = \App\Services\DashboardService::getTopAgents($dateFrom, $dateTo, 20);
        $departmentStats = \App\Services\DashboardService::getDepartmentStats();
        $funnelStats = \App\Services\DashboardService::getFunnelStats();
        
        if ($format === 'csv') {
            // Exportar CSV simples
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="relatorio_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalho
            fputcsv($output, ['Relatório de Dashboard', 'Período: ' . $dateFrom . ' até ' . $dateTo], ';');
            fputcsv($output, [], ';');
            
            // Estatísticas gerais
            fputcsv($output, ['Estatísticas Gerais'], ';');
            fputcsv($output, ['Métrica', 'Valor'], ';');
            fputcsv($output, ['Total de Conversas', $stats['conversations']['total']], ';');
            fputcsv($output, ['Conversas Abertas', $stats['conversations']['open']], ';');
            fputcsv($output, ['Conversas Fechadas', $stats['conversations']['closed']], ';');
            fputcsv($output, ['Taxa de Resolução', $stats['metrics']['resolution_rate'] . '%'], ';');
            fputcsv($output, ['Agentes Online', $stats['agents']['online']], ';');
            fputcsv($output, [], ';');
            
            // Top Agentes
            if (!empty($topAgents)) {
                fputcsv($output, ['Top Agentes'], ';');
                fputcsv($output, ['Nome', 'Total Conversas', 'Fechadas', 'Taxa Resolução'], ';');
                foreach ($topAgents as $agent) {
                    fputcsv($output, [
                        $agent['name'] ?? 'Sem nome',
                        $agent['total_conversations'] ?? 0,
                        $agent['closed_conversations'] ?? 0,
                        ($agent['resolution_rate'] ?? 0) . '%'
                    ], ';');
                }
                fputcsv($output, [], ';');
            }
            
            fclose($output);
            exit;
        } else {
            // Para PDF e Excel, retornamos JSON por enquanto
            // Implementação completa requer bibliotecas como TCPDF/FPDF ou PhpSpreadsheet
            Response::json([
                'success' => true,
                'message' => 'Exportação em ' . strtoupper($format) . ' será implementada em breve',
                'data' => [
                    'stats' => $stats,
                    'top_agents' => $topAgents,
                    'departments' => $departmentStats,
                    'funnels' => $funnelStats
                ]
            ]);
        }
    }
}

