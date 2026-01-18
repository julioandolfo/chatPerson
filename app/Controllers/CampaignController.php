<?php
/**
 * Controller CampaignController
 * Gerenciamento de campanhas
 */

namespace App\Controllers;

use App\Services\CampaignService;
use App\Services\ContactListService;
use App\Services\CampaignNotificationService;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\IntegrationAccount;
use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;

class CampaignController
{
    /**
     * Lista de campanhas
     */
    public function index(): void
    {
        Permission::abortIfCannot('campaigns.view');

        $campaigns = Campaign::all();
        
        // Adicionar estatísticas para cada campanha
        foreach ($campaigns as &$campaign) {
            $campaign['progress'] = Campaign::getProgress($campaign['id']);
            $campaign['stats'] = CampaignService::getStats($campaign['id']);
        }

        Response::view('campaigns/index', [
            'campaigns' => $campaigns,
            'title' => 'Campanhas'
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        Permission::abortIfCannot('campaigns.create');

        // Buscar listas disponíveis
        $lists = ContactList::all();

        // Buscar contas WhatsApp ativas
        $sql = "SELECT * FROM integration_accounts WHERE channel = 'whatsapp' AND status = 'active' ORDER BY name";
        $whatsappAccounts = \App\Helpers\Database::fetchAll($sql, []);

        Response::view('campaigns/create', [
            'lists' => $lists,
            'whatsappAccounts' => $whatsappAccounts,
            'title' => 'Nova Campanha'
        ]);
    }

    /**
     * Salvar nova campanha
     */
    public function store(): void
    {
        Permission::abortIfCannot('campaigns.create');

        try {
            $data = Request::all();
            $data['created_by'] = Auth::id();

            $campaignId = CampaignService::create($data);

            Response::json([
                'success' => true,
                'message' => 'Campanha criada com sucesso!',
                'campaign_id' => $campaignId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Visualizar campanha
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('campaigns.view');

        $campaign = Campaign::findWithRelations($id);
        if (!$campaign) {
            Response::json(['error' => 'Campanha não encontrada'], 404);
            return;
        }

        $stats = CampaignService::getStats($id);

        Response::view('campaigns/show', [
            'campaign' => $campaign,
            'stats' => $stats,
            'title' => $campaign['name']
        ]);
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        $campaign = Campaign::find($id);
        if (!$campaign) {
            Response::json(['error' => 'Campanha não encontrada'], 404);
            return;
        }

        // Buscar listas disponíveis
        $lists = ContactList::all();

        // Buscar contas WhatsApp ativas
        $whatsappAccounts = IntegrationAccount::where('channel', '=', 'whatsapp')
            ->where('status', '=', 'active')
            ->get();

        Response::view('campaigns/edit', [
            'campaign' => $campaign,
            'lists' => $lists,
            'whatsappAccounts' => $whatsappAccounts,
            'title' => 'Editar Campanha'
        ]);
    }

    /**
     * Atualizar campanha
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            $data = Request::all();
            $data['updated_by'] = Auth::id();

            CampaignService::update($id, $data);

            Response::json([
                'success' => true,
                'message' => 'Campanha atualizada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deletar campanha
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('campaigns.delete');

        try {
            CampaignService::delete($id);

            Response::json([
                'success' => true,
                'message' => 'Campanha deletada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Preparar campanha (criar mensagens)
     */
    public function prepare(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            $result = CampaignService::prepare($id);

            Response::json([
                'success' => true,
                'message' => "Campanha preparada: {$result['created']} mensagens criadas",
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Iniciar campanha
     */
    public function start(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            CampaignService::start($id);

            Response::json([
                'success' => true,
                'message' => 'Campanha iniciada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Pausar campanha
     */
    public function pause(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            CampaignService::pause($id);

            Response::json([
                'success' => true,
                'message' => 'Campanha pausada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Retomar campanha
     */
    public function resume(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            CampaignService::resume($id);

            Response::json([
                'success' => true,
                'message' => 'Campanha retomada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Cancelar campanha
     */
    public function cancel(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            CampaignService::cancel($id);

            Response::json([
                'success' => true,
                'message' => 'Campanha cancelada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obter estatísticas (API JSON)
     */
    public function stats(int $id): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $campaign = Campaign::find($id);
            if (!$campaign) {
                Response::json(['error' => 'Campanha não encontrada'], 404);
                return;
            }

            $stats = CampaignService::getStats($id);

            Response::json([
                'success' => true,
                'campaign' => $campaign,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Listar todas as campanhas (API JSON)
     */
    public function list(): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $campaigns = Campaign::all();

            // Adicionar progresso
            foreach ($campaigns as &$campaign) {
                $campaign['progress'] = Campaign::getProgress($campaign['id']);
            }

            Response::json([
                'success' => true,
                'campaigns' => $campaigns
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Criar campanha A/B Test
     */
    public function createABTest(): void
    {
        Permission::abortIfCannot('campaigns.create');
        
        $lists = \App\Models\ContactList::all();
        
        $sql = "SELECT * FROM integration_accounts WHERE channel = 'whatsapp' AND status = 'active' ORDER BY name";
        $whatsappAccounts = \App\Helpers\Database::fetchAll($sql, []);
        
        Response::view('campaigns/ab-test', [
            'lists' => $lists,
            'whatsappAccounts' => $whatsappAccounts,
            'title' => 'Editor A/B Testing'
        ]);
    }
    
    /**
     * Salvar campanha A/B Test
     */
    public function storeABTest(): void
    {
        Permission::abortIfCannot('campaigns.create');
        
        try {
            $data = Request::all();
            $data['created_by'] = Auth::id();
            $data['is_ab_test'] = true;
            
            // Criar campanha
            $campaignId = CampaignService::create($data);
            
            // Criar variantes
            if (!empty($data['variants'])) {
                foreach ($data['variants'] as $variant) {
                    $variant['campaign_id'] = $campaignId;
                    \App\Models\CampaignVariant::create($variant);
                }
            }
            
            Response::json([
                'success' => true,
                'message' => 'Teste A/B criado com sucesso!',
                'campaign_id' => $campaignId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Exportar relatório da campanha (PDF)
     */
    public function exportReport(int $id): void
    {
        Permission::abortIfCannot('campaigns.view');
        
        try {
            $campaign = Campaign::findWithRelations($id);
            if (!$campaign) {
                Response::json(['error' => 'Campanha não encontrada'], 404);
                return;
            }
            
            $stats = CampaignService::getStats($id);
            
            // Gerar HTML do relatório
            $html = self::generateReportHTML($campaign, $stats);
            
            // Por simplicidade, retornar HTML que pode ser impresso
            // Em produção, usar biblioteca como TCPDF ou DomPDF
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Gerar HTML do relatório
     */
    private static function generateReportHTML(array $campaign, array $stats): string
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Relatório - <?php echo htmlspecialchars($campaign['name']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                h1 { color: #333; border-bottom: 2px solid #009EF7; padding-bottom: 10px; }
                .info-box { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .stat-row { display: flex; justify-content: space-between; margin: 10px 0; }
                .stat-label { font-weight: bold; }
                .stat-value { color: #009EF7; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                th { background-color: #009EF7; color: white; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button onclick="window.print()" style="padding: 10px 20px; background: #009EF7; color: white; border: none; cursor: pointer;">
                    Imprimir / Salvar PDF
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #ccc; border: none; cursor: pointer; margin-left: 10px;">
                    Fechar
                </button>
            </div>
            
            <h1>Relatório de Campanha</h1>
            
            <div class="info-box">
                <h2><?php echo htmlspecialchars($campaign['name']); ?></h2>
                <p><strong>ID:</strong> <?php echo $campaign['id']; ?></p>
                <p><strong>Descrição:</strong> <?php echo htmlspecialchars($campaign['description'] ?? '-'); ?></p>
                <p><strong>Status:</strong> <?php echo strtoupper($campaign['status']); ?></p>
                <p><strong>Canal:</strong> <?php echo strtoupper($campaign['channel']); ?></p>
                <p><strong>Criada em:</strong> <?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?></p>
                <?php if ($campaign['started_at']): ?>
                <p><strong>Iniciada em:</strong> <?php echo date('d/m/Y H:i', strtotime($campaign['started_at'])); ?></p>
                <?php endif; ?>
            </div>
            
            <h2>Estatísticas</h2>
            <table>
                <tr>
                    <th>Métrica</th>
                    <th>Valor</th>
                    <th>Taxa</th>
                </tr>
                <tr>
                    <td>Total de Contatos</td>
                    <td><?php echo number_format($stats['total_contacts']); ?></td>
                    <td>-</td>
                </tr>
                <tr>
                    <td>Mensagens Enviadas</td>
                    <td><?php echo number_format($stats['total_sent']); ?></td>
                    <td><?php echo number_format($stats['progress'], 1); ?>%</td>
                </tr>
                <tr>
                    <td>Mensagens Entregues</td>
                    <td><?php echo number_format($stats['total_delivered']); ?></td>
                    <td><?php echo number_format($stats['delivery_rate'], 1); ?>%</td>
                </tr>
                <tr>
                    <td>Mensagens Lidas</td>
                    <td><?php echo number_format($stats['total_read']); ?></td>
                    <td><?php echo number_format($stats['read_rate'], 1); ?>%</td>
                </tr>
                <tr>
                    <td>Respostas Recebidas</td>
                    <td><?php echo number_format($stats['total_replied']); ?></td>
                    <td><?php echo number_format($stats['reply_rate'], 1); ?>%</td>
                </tr>
                <tr>
                    <td>Falhas</td>
                    <td><?php echo number_format($stats['total_failed']); ?></td>
                    <td><?php echo number_format($stats['failure_rate'], 1); ?>%</td>
                </tr>
                <tr>
                    <td>Puladas</td>
                    <td><?php echo number_format($stats['total_skipped']); ?></td>
                    <td>-</td>
                </tr>
            </table>
            
            <div style="margin-top: 40px; text-align: center; color: #999; font-size: 12px;">
                <p>Relatório gerado em <?php echo date('d/m/Y H:i:s'); ?></p>
                <p>Sistema de Campanhas WhatsApp</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * API: Buscar notificações
     */
    public function getNotifications(): void
    {
        Permission::abortIfCannot('campaigns.view');
        
        try {
            $notifications = CampaignNotificationService::getRecent(20);
            
            Response::json([
                'success' => true,
                'notifications' => $notifications
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * API: Marcar notificações como lidas
     */
    public function markNotificationsRead(): void
    {
        Permission::abortIfCannot('campaigns.view');
        
        try {
            $data = Request::all();
            $ids = $data['notification_ids'] ?? [];
            
            CampaignNotificationService::markAsRead($ids);
            
            Response::json(['success' => true]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * API: Quick stats (para widgets)
     */
    public function quickStats(): void
    {
        Permission::abortIfCannot('campaigns.view');
        
        try {
            $sqlTotal = "SELECT COUNT(*) as total FROM campaigns";
            $sqlRunning = "SELECT COUNT(*) as running FROM campaigns WHERE status = 'running'";
            $sqlToday = "SELECT SUM(total_sent) as sent FROM campaigns WHERE DATE(started_at) = CURDATE()";
            $sqlAvgReply = "SELECT AVG(total_replied / NULLIF(total_delivered, 0) * 100) as avg_rate 
                           FROM campaigns WHERE total_delivered > 0";
            
            $total = \App\Helpers\Database::fetch($sqlTotal, [])['total'] ?? 0;
            $running = \App\Helpers\Database::fetch($sqlRunning, [])['running'] ?? 0;
            $sentToday = \App\Helpers\Database::fetch($sqlToday, [])['sent'] ?? 0;
            $avgReply = \App\Helpers\Database::fetch($sqlAvgReply, [])['avg_rate'] ?? 0;
            
            Response::json([
                'success' => true,
                'total' => $total,
                'running' => $running,
                'sent_today' => $sentToday,
                'avg_reply_rate' => $avgReply
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Exportar dados (CSV)
     */
    public function exportCSV(): void
    {
        Permission::abortIfCannot('campaigns.view');
        
        try {
            $period = Request::get('period', 30);
            
            $sql = "SELECT 
                c.id,
                c.name,
                c.status,
                c.total_contacts,
                c.total_sent,
                c.total_delivered,
                c.total_read,
                c.total_replied,
                c.total_failed,
                c.created_at,
                c.started_at,
                c.completed_at
            FROM campaigns c
            WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY c.created_at DESC";
            
            $campaigns = \App\Helpers\Database::fetchAll($sql, [$period]);
            
            // Calcular taxas
            foreach ($campaigns as &$camp) {
                $camp['delivery_rate'] = $camp['total_sent'] > 0 
                    ? round(($camp['total_delivered'] / $camp['total_sent']) * 100, 2) 
                    : 0;
                $camp['read_rate'] = $camp['total_delivered'] > 0 
                    ? round(($camp['total_read'] / $camp['total_delivered']) * 100, 2) 
                    : 0;
                $camp['reply_rate'] = $camp['total_delivered'] > 0 
                    ? round(($camp['total_replied'] / $camp['total_delivered']) * 100, 2) 
                    : 0;
            }
            
            // Gerar CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="campanhas_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalho
            fputcsv($output, [
                'ID', 'Nome', 'Status', 'Total Contatos', 'Enviadas', 'Entregues', 
                'Lidas', 'Respondidas', 'Falhas', 'Taxa Entrega (%)', 'Taxa Leitura (%)', 
                'Taxa Resposta (%)', 'Criada em', 'Iniciada em', 'Concluída em'
            ], ';');
            
            // Dados
            foreach ($campaigns as $camp) {
                fputcsv($output, [
                    $camp['id'],
                    $camp['name'],
                    $camp['status'],
                    $camp['total_contacts'],
                    $camp['total_sent'],
                    $camp['total_delivered'],
                    $camp['total_read'],
                    $camp['total_replied'],
                    $camp['total_failed'],
                    $camp['delivery_rate'],
                    $camp['read_rate'],
                    $camp['reply_rate'],
                    $camp['created_at'],
                    $camp['started_at'],
                    $camp['completed_at']
                ], ';');
            }
            
            fclose($output);
            exit;
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * View do dashboard
     */
    public function dashboardView(): void
    {
        Permission::abortIfCannot('campaigns.view');
        
        Response::view('campaigns/dashboard', [
            'title' => 'Dashboard de Campanhas'
        ]);
    }
    
    /**
     * API: Dashboard com analytics
     */
    public function dashboard(): void
    {
        Permission::abortIfCannot('campaigns.view');
        
        try {
            $period = Request::get('period', 30);
            
            // KPIs gerais
            $sql = "SELECT 
                SUM(total_sent) as total_sent,
                SUM(total_delivered) as total_delivered,
                SUM(total_read) as total_read,
                SUM(total_replied) as total_replied,
                SUM(total_contacts) as total_contacts
            FROM campaigns 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $kpis = \App\Helpers\Database::fetch($sql, [$period]);
            
            $kpis['delivery_rate'] = $kpis['total_sent'] > 0 
                ? ($kpis['total_delivered'] / $kpis['total_sent']) * 100 
                : 0;
            $kpis['read_rate'] = $kpis['total_delivered'] > 0 
                ? ($kpis['total_read'] / $kpis['total_delivered']) * 100 
                : 0;
            $kpis['reply_rate'] = $kpis['total_delivered'] > 0 
                ? ($kpis['total_replied'] / $kpis['total_delivered']) * 100 
                : 0;
            
            // Evolução diária
            $sqlEvolution = "SELECT 
                DATE(created_at) as date,
                SUM(total_sent) as sent,
                SUM(total_delivered) as delivered,
                SUM(total_read) as read,
                SUM(total_replied) as replied
            FROM campaigns
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
            
            $evolution = \App\Helpers\Database::fetchAll($sqlEvolution, [$period]);
            
            $evolutionData = [
                'dates' => array_column($evolution, 'date'),
                'sent' => array_column($evolution, 'sent'),
                'delivered' => array_column($evolution, 'delivered'),
                'read' => array_column($evolution, 'read'),
                'replied' => array_column($evolution, 'replied')
            ];
            
            // Distribuição por status
            $sqlStatus = "SELECT status, COUNT(*) as count 
                         FROM campaigns 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                         GROUP BY status";
            $statusData = \App\Helpers\Database::fetchAll($sqlStatus, [$period]);
            
            $statusDistribution = [
                'labels' => array_column($statusData, 'status'),
                'values' => array_column($statusData, 'count')
            ];
            
            // Top 10 campanhas por taxa de resposta
            $sqlTop = "SELECT id, name, total_sent, total_replied,
                      (total_replied / NULLIF(total_sent, 0) * 100) as reply_rate
                      FROM campaigns
                      WHERE total_sent > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                      ORDER BY reply_rate DESC
                      LIMIT 10";
            $topCampaigns = \App\Helpers\Database::fetchAll($sqlTop, [$period]);
            
            $topData = [
                'names' => array_column($topCampaigns, 'name'),
                'reply_rates' => array_column($topCampaigns, 'reply_rate')
            ];
            
            // Uso de contas
            $sqlAccounts = "SELECT 
                ia.name,
                COUNT(cm.id) as total
            FROM campaign_messages cm
            INNER JOIN integration_accounts ia ON cm.integration_account_id = ia.id
            WHERE cm.status IN ('sent', 'delivered', 'read', 'replied')
            GROUP BY ia.id
            ORDER BY total DESC
            LIMIT 10";
            $accountsData = \App\Helpers\Database::fetchAll($sqlAccounts, []);
            
            $accountUsage = [
                'names' => array_column($accountsData, 'name'),
                'totals' => array_column($accountsData, 'total')
            ];
            
            // Campanhas recentes
            $recentCampaigns = \App\Helpers\Database::fetchAll(
                "SELECT * FROM campaigns ORDER BY created_at DESC LIMIT 10",
                []
            );
            
            foreach ($recentCampaigns as &$camp) {
                $camp['delivery_rate'] = $camp['total_sent'] > 0 
                    ? ($camp['total_delivered'] / $camp['total_sent']) * 100 
                    : 0;
                $camp['read_rate'] = $camp['total_delivered'] > 0 
                    ? ($camp['total_read'] / $camp['total_delivered']) * 100 
                    : 0;
            }
            
            Response::json([
                'success' => true,
                'kpis' => $kpis,
                'evolution' => $evolutionData,
                'status_distribution' => $statusDistribution,
                'top_campaigns' => $topData,
                'account_usage' => $accountUsage,
                'recent_campaigns' => $recentCampaigns
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
