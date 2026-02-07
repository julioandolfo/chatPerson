<?php
/**
 * Controller CampaignController
 * Gerenciamento de campanhas
 */

namespace App\Controllers;

use App\Services\CampaignService;
use App\Services\CampaignSchedulerService;
use App\Services\ContactListService;
use App\Services\CampaignNotificationService;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\IntegrationAccount;
// WhatsAppAccount removido - usar IntegrationAccount
use App\Models\Funnel;
use App\Models\FunnelStage;
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
        $whatsappAccounts = $this->getWhatsAppAccountsForCampaign();

        // Funis e etapas para criação de conversa
        $funnels = Funnel::whereActive();
        $funnelsWithStages = [];
        foreach ($funnels as $funnel) {
            $funnel['stages'] = Funnel::getStages((int)$funnel['id']);
            $funnelsWithStages[] = $funnel;
        }
        $defaultFunnel = Funnel::getDefault();
        $defaultStage = $defaultFunnel ? FunnelStage::getDefault((int)$defaultFunnel['id']) : null;

        Response::view('campaigns/create', [
            'lists' => $lists,
            'whatsappAccounts' => $whatsappAccounts,
            'funnels' => $funnelsWithStages,
            'defaultFunnelId' => $defaultFunnel['id'] ?? null,
            'defaultStageId' => $defaultStage['id'] ?? null,
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
        
        // Buscar lista de contatos associada
        $contactList = null;
        if (!empty($campaign['contact_list_id'])) {
            $contactList = ContactList::find($campaign['contact_list_id']);
        }
        
        // Buscar mensagens da campanha (paginado)
        $page = (int)Request::get('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $statusFilter = Request::get('status_filter');
        
        $messages = \App\Models\CampaignMessage::getAllWithContacts($id, $limit, $offset, $statusFilter);
        $totalMessages = \App\Models\CampaignMessage::countAll($id, $statusFilter);
        $totalPages = ceil($totalMessages / $limit);
        
        // Contagem por status
        $statusCounts = [
            'pending' => \App\Models\CampaignMessage::countByStatus($id, 'pending'),
            'sent' => \App\Models\CampaignMessage::countByStatus($id, 'sent'),
            'delivered' => \App\Models\CampaignMessage::countByStatus($id, 'delivered'),
            'read' => \App\Models\CampaignMessage::countByStatus($id, 'read'),
            'replied' => \App\Models\CampaignMessage::countByStatus($id, 'replied'),
            'failed' => \App\Models\CampaignMessage::countByStatus($id, 'failed'),
            'skipped' => \App\Models\CampaignMessage::countByStatus($id, 'skipped'),
        ];

        Response::view('campaigns/show', [
            'campaign' => $campaign,
            'stats' => $stats,
            'contactList' => $contactList,
            'messages' => $messages,
            'statusCounts' => $statusCounts,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalMessages' => $totalMessages,
            'statusFilter' => $statusFilter,
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
     * Forçar disparo imediato (para testes)
     * Envia imediatamente a próxima mensagem pendente da campanha
     */
    public function forceSend(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        \App\Helpers\Logger::campaign("Controller::forceSend - Iniciando para campanha {$id}");

        try {
            $result = CampaignSchedulerService::forceSendNext($id);

            \App\Helpers\Logger::campaign("Controller::forceSend - Resultado: " . json_encode($result));

            if ($result['success']) {
                Response::json([
                    'success' => true,
                    'message' => "Mensagem enviada para {$result['contact_name']}!",
                    'result' => $result
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Nenhuma mensagem pendente'
                ], 400);
            }
        } catch (\Throwable $e) {
            \App\Helpers\Logger::campaign("Controller::forceSend - ERRO: " . $e->getMessage());
            \App\Helpers\Logger::campaign("Controller::forceSend - Trace: " . $e->getTraceAsString());
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
     * Reiniciar campanha
     */
    public function restart(int $id): void
    {
        \App\Helpers\Logger::campaign("Controller::restart - Iniciando reinício da campanha ID: {$id}");
        
        try {
            Permission::abortIfCannot('campaigns.edit');
            
            $keepSentRaw = Request::post('keep_sent');
            $keepSent = $keepSentRaw === '1' || $keepSentRaw === 'true';
            
            \App\Helpers\Logger::campaign("Controller::restart - Campanha {$id}: keep_sent_raw={$keepSentRaw}, keep_sent=" . ($keepSent ? 'true' : 'false'));
            
            $result = CampaignService::restart($id, $keepSent);

            \App\Helpers\Logger::campaign("Controller::restart - Campanha {$id}: Sucesso - " . json_encode($result));

            Response::json([
                'success' => true,
                'message' => $keepSent 
                    ? "Campanha reiniciada! {$result['messages_reset']} mensagens com falha serão reenviadas."
                    : "Campanha reiniciada completamente! Pronta para iniciar novamente.",
                'result' => $result
            ]);
        } catch (\Throwable $e) {
            \App\Helpers\Logger::campaign("Controller::restart - Campanha {$id}: ERRO - " . $e->getMessage());
            \App\Helpers\Logger::campaign("Controller::restart - Campanha {$id}: Trace - " . $e->getTraceAsString());
            
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
        
        $whatsappAccounts = $this->getWhatsAppAccountsForCampaign();
        
        Response::view('campaigns/ab-test', [
            'lists' => $lists,
            'whatsappAccounts' => $whatsappAccounts,
            'title' => 'Editor A/B Testing'
        ]);
    }

    /**
     * Obter contas WhatsApp para campanhas (direto de integration_accounts)
     */
    private function getWhatsAppAccountsForCampaign(): array
    {
        return IntegrationAccount::getByChannel('whatsapp');
    }

    /**
     * Criar integration_accounts a partir de whatsapp_accounts (fallback)
     */
    private function ensureIntegrationAccountFromLegacy(array $legacy): void
    {
        $provider = $legacy['provider'] ?? 'quepasa';
        $phoneNumber = $legacy['phone_number'] ?? null;
        if (empty($phoneNumber)) {
            return;
        }

        $existing = IntegrationAccount::findByPhone($phoneNumber, 'whatsapp');
        if ($existing) {
            return;
        }

        $config = [];
        foreach ([
            'quepasa_user',
            'quepasa_token',
            'quepasa_trackid',
            'quepasa_chatid',
            'wavoip_token',
            'wavoip_enabled'
        ] as $key) {
            if (!empty($legacy[$key])) {
                $config[$key] = $legacy[$key];
            }
        }

        $defaultFunnelId = $legacy['default_funnel_id'] ?? null;
        if ($defaultFunnelId && !Funnel::find((int)$defaultFunnelId)) {
            $defaultFunnelId = null;
        }
        $defaultStageId = $legacy['default_stage_id'] ?? null;
        if ($defaultStageId && !FunnelStage::find((int)$defaultStageId)) {
            $defaultStageId = null;
        }

        $data = [
            'name' => $legacy['name'] ?? $phoneNumber,
            'provider' => $provider,
            'channel' => 'whatsapp',
            'api_url' => $legacy['api_url'] ?? null,
            'api_token' => $legacy['api_key'] ?? null,
            'account_id' => $legacy['instance_id'] ?? null,
            'phone_number' => $phoneNumber,
            'status' => $legacy['status'] ?? 'active',
            'config' => !empty($config) ? json_encode($config) : null,
            'default_funnel_id' => $defaultFunnelId,
            'default_stage_id' => $defaultStageId
        ];

        IntegrationAccount::create(array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        }));
    }

    /**
     * @deprecated Não mais necessário - integration_accounts é a tabela primária
     */
    private function syncWhatsAppIntegrationAccounts(): void
    {
        $legacyAccounts = \App\Helpers\Database::fetchAll("SELECT * FROM whatsapp_accounts", []);
        if (empty($legacyAccounts)) {
            return;
        }

        $integrationAccounts = IntegrationAccount::getByChannel('whatsapp');
        $existingPhones = [];
        foreach ($integrationAccounts as $account) {
            $normalized = $this->normalizePhone($account['phone_number'] ?? '');
            if ($normalized) {
                $existingPhones[$normalized] = true;
            }
        }

        foreach ($legacyAccounts as $legacy) {
            $normalized = $this->normalizePhone($legacy['phone_number'] ?? '');
            if (empty($normalized)) {
                continue;
            }
            if (isset($existingPhones[$normalized])) {
                continue;
            }
            $this->ensureIntegrationAccountFromLegacy($legacy);
            $existingPhones[$normalized] = true;
        }
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone);
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

    /**
     * API: Analytics avançado
     */
    public function analytics(): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $period = (int) Request::get('period', 30);

            // Comparação de campanhas (reply/delivery)
            $comparisonRows = \App\Helpers\Database::fetchAll(
                "SELECT name,
                        (total_replied / NULLIF(total_sent, 0) * 100) as reply_rate,
                        (total_delivered / NULLIF(total_sent, 0) * 100) as delivery_rate
                 FROM campaigns
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 ORDER BY created_at DESC
                 LIMIT 10",
                [$period]
            );

            $comparison = [
                'campaign_names' => array_column($comparisonRows, 'name'),
                'reply_rates' => array_map('floatval', array_column($comparisonRows, 'reply_rate')),
                'delivery_rates' => array_map('floatval', array_column($comparisonRows, 'delivery_rate'))
            ];

            // Melhores horários (volume de envios por hora)
            $hoursRows = \App\Helpers\Database::fetchAll(
                "SELECT HOUR(created_at) as hour, COUNT(*) as total
                 FROM campaign_messages
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY HOUR(created_at)
                 ORDER BY hour ASC",
                [$period]
            );

            $hoursMap = array_fill(0, 24, 0);
            foreach ($hoursRows as $row) {
                $hour = (int) $row['hour'];
                $hoursMap[$hour] = (int) $row['total'];
            }
            $bestHours = [
                'rates' => $hoursMap
            ];

            // Melhores dias (volume por dia da semana)
            $daysRows = \App\Helpers\Database::fetchAll(
                "SELECT DAYOFWEEK(created_at) as dow, COUNT(*) as total
                 FROM campaign_messages
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DAYOFWEEK(created_at)
                 ORDER BY dow ASC",
                [$period]
            );

            $daysMap = array_fill(0, 7, 0);
            foreach ($daysRows as $row) {
                $dowIndex = (int) $row['dow'] - 1; // 0=Domingo
                if ($dowIndex >= 0 && $dowIndex <= 6) {
                    $daysMap[$dowIndex] = (int) $row['total'];
                }
            }
            $bestDays = [
                'rates' => $daysMap
            ];

            // Performance por conta
            $accountsRows = \App\Helpers\Database::fetchAll(
                "SELECT ia.name, ia.phone_number,
                        SUM(cm.status = 'sent') as total_sent,
                        SUM(cm.status = 'delivered') as total_delivered,
                        SUM(cm.status = 'read') as total_read,
                        SUM(cm.status = 'replied') as total_replied
                 FROM campaign_messages cm
                 INNER JOIN integration_accounts ia ON ia.id = cm.integration_account_id
                 WHERE cm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY ia.id
                 ORDER BY total_sent DESC",
                [$period]
            );

            $accountsPerformance = array_map(function ($row) {
                $totalSent = (int) ($row['total_sent'] ?? 0);
                $totalDelivered = (int) ($row['total_delivered'] ?? 0);
                $totalRead = (int) ($row['total_read'] ?? 0);
                $totalReplied = (int) ($row['total_replied'] ?? 0);

                $deliveryRate = $totalSent > 0 ? ($totalDelivered / $totalSent) * 100 : 0;
                $readRate = $totalDelivered > 0 ? ($totalRead / $totalDelivered) * 100 : 0;
                $replyRate = $totalDelivered > 0 ? ($totalReplied / $totalDelivered) * 100 : 0;

                return [
                    'name' => $row['name'],
                    'phone_number' => $row['phone_number'],
                    'total_sent' => $totalSent,
                    'delivery_rate' => $deliveryRate,
                    'read_rate' => $readRate,
                    'reply_rate' => $replyRate
                ];
            }, $accountsRows);

            Response::json([
                'success' => true,
                'comparison' => $comparison,
                'best_hours' => $bestHours,
                'best_days' => $bestDays,
                'accounts_performance' => $accountsPerformance
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Preview de mensagem gerada por IA
     * POST /api/campaigns/preview-ai-message
     */
    public function previewAIMessage(): void
    {
        Permission::abortIfCannot('campaigns.create');
        
        try {
            $data = Request::all();
            
            $prompt = $data['prompt'] ?? null;
            if (empty($prompt)) {
                Response::json([
                    'success' => false,
                    'error' => 'Prompt é obrigatório'
                ], 400);
                return;
            }
            
            $contact = $data['contact'] ?? [
                'name' => 'Cliente Exemplo',
                'email' => 'cliente@email.com',
                'phone' => '11999999999',
                'company' => 'Empresa Exemplo'
            ];
            
            $referenceMessage = $data['reference_message'] ?? null;
            $temperature = isset($data['temperature']) ? (float)$data['temperature'] : 0.7;
            
            // Gerar mensagem com IA
            $message = \App\Services\OpenAIService::generateCampaignMessage(
                $prompt,
                $contact,
                $referenceMessage,
                $temperature
            );
            
            if (!$message) {
                Response::json([
                    'success' => false,
                    'error' => 'Não foi possível gerar a mensagem. Verifique se a API Key da OpenAI está configurada.'
                ], 500);
                return;
            }
            
            Response::json([
                'success' => true,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("CampaignController::previewAIMessage - Erro: " . $e->getMessage());
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
