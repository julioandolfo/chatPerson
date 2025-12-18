<?php
$layout = 'layouts.metronic.app';
$title = 'Dashboard';

// Content
ob_start();
?>
<!--begin::Card - Filtros de Período-->
<div class="card mb-5">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3">
            <label class="fw-semibold fs-6 mb-0">Período:</label>
            <input type="date" id="kt_dashboard_date_from" class="form-control form-control-solid" 
                   value="<?= htmlspecialchars($dateFrom) ?>" style="width: 150px;" />
            <span class="text-muted">até</span>
            <input type="date" id="kt_dashboard_date_to" class="form-control form-control-solid" 
                   value="<?= htmlspecialchars(date('Y-m-d', strtotime($dateTo))) ?>" style="width: 150px;" />
            <button type="button" class="btn btn-sm btn-primary" onclick="loadDashboard()">
                <i class="ki-duotone ki-magnifier fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Filtrar
            </button>
        </div>
    </div>
</div>
<!--end::Card - Filtros de Período-->

<!--begin::Row-->
<div class="row g-5 mb-5">
    <!--begin::Col - Total de Conversas-->
    <div class="col-xl-3">
        <div class="card bg-light-primary h-100">
            <div class="card-body">
                <i class="ki-duotone ki-chat fs-2x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($stats['conversations']['total'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Total de Conversas</div>
                <div class="text-muted fs-7 mt-1">Período selecionado</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Conversas Abertas-->
    <div class="col-xl-3">
        <div class="card bg-light-warning h-100">
            <div class="card-body">
                <i class="ki-duotone ki-chat-text fs-2x text-warning mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($stats['conversations']['open'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Conversas Abertas</div>
                <div class="text-muted fs-7 mt-1">Requerem atenção</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Agentes Online-->
    <div class="col-xl-3">
        <div class="card bg-light-success h-100">
            <div class="card-body">
                <i class="ki-duotone ki-profile-user fs-2x text-success mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($stats['agents']['online'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Agentes Online</div>
                <div class="text-muted fs-7 mt-1">de <?= number_format($stats['agents']['active'] ?? 0) ?> ativos</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Taxa de Resolução-->
    <div class="col-xl-3">
        <div class="card bg-light-info h-100">
            <div class="card-body">
                <i class="ki-duotone ki-chart-simple fs-2x text-info mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($stats['metrics']['resolution_rate'] ?? 0, 1) ?>%</div>
                <div class="fw-semibold text-gray-500">Taxa de Resolução</div>
                <div class="text-muted fs-7 mt-1">Conversas resolvidas</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Métricas Adicionais-->
<div class="row g-5 mb-5">
    <!--begin::Col-->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Minhas Conversas</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-chat-text fs-3x text-primary me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?= number_format($stats['conversations']['my_open'] ?? 0) ?> / <?= number_format($stats['conversations']['my_total'] ?? 0) ?>
                        </div>
                        <div class="text-muted fs-6">Abertas / Total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Tempo Médio de Resposta</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-time fs-3x text-success me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?php
                            $avgFirstResponse = $stats['metrics']['avg_first_response_time'] ?? null;
                            if ($avgFirstResponse !== null && $avgFirstResponse > 0) {
                                if (class_exists('\App\Services\AgentPerformanceService')) {
                                    echo \App\Services\AgentPerformanceService::formatTime($avgFirstResponse);
                                } else {
                                    // Fallback manual
                                    if ($avgFirstResponse < 60) {
                                        echo number_format($avgFirstResponse, 0) . ' min';
                                    } else {
                                        echo number_format($avgFirstResponse / 60, 1) . 'h';
                                    }
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                        <div class="text-muted fs-6">Primeira resposta</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Conversas sem Atribuição</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-notification-status fs-3x text-warning me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?= number_format($stats['conversations']['unassigned'] ?? 0) ?>
                        </div>
                        <div class="text-muted fs-6">Aguardando atribuição</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Estatísticas Detalhadas-->
<div class="row gy-5 g-xl-10">
    <!--begin::Col - Top Agentes-->
    <?php if (!empty($topAgents)): ?>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Top Agentes</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Melhores desempenhos no período</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Agente</th>
                                <th class="min-w-100px">Conversas</th>
                                <th class="min-w-100px">Fechadas</th>
                                <th class="min-w-100px">Taxa</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($topAgents as $index => $agent): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40px me-3">
                                                <?php if (!empty($agent['avatar'])): ?>
                                                    <img src="<?= htmlspecialchars($agent['avatar']) ?>" alt="<?= htmlspecialchars($agent['name']) ?>" />
                                                <?php else: ?>
                                                    <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                                                        <?= mb_substr(htmlspecialchars($agent['name'] ?? 'A'), 0, 1) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 fw-bold"><?= htmlspecialchars($agent['name'] ?? 'Sem nome') ?></span>
                                                <span class="text-muted fs-7"><?= htmlspecialchars($agent['email'] ?? '') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-gray-800 fw-bold"><?= number_format($agent['total_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-success fw-bold"><?= number_format($agent['closed_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-success"><?= number_format($agent['resolution_rate'] ?? 0, 1) ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    <?php endif; ?>
    
    <!--begin::Col - Conversas Recentes-->
    <?php if (!empty($recentConversations)): ?>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Conversas Recentes</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Últimas atualizações</span>
                </h3>
                <div class="card-toolbar">
                    <a href="<?= \App\Helpers\Url::to('/conversations') ?>" class="btn btn-sm btn-light btn-active-primary">
                        Ver Todas
                    </a>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="timeline">
                    <?php foreach ($recentConversations as $conversation): ?>
                        <div class="timeline-item">
                            <div class="timeline-line w-40px"></div>
                            <div class="timeline-icon symbol symbol-circle symbol-40px">
                                <div class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-chat fs-2 text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div class="timeline-content mb-10 mt-n1">
                                <div class="pe-3 mb-5">
                                    <div class="fs-5 fw-semibold mb-2">
                                        <a href="<?= \App\Helpers\Url::to('/conversations/' . $conversation['id']) ?>" class="text-gray-800 text-hover-primary">
                                            <?= htmlspecialchars($conversation['contact_name'] ?? 'Contato') ?>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center mt-1 fs-6">
                                        <span class="text-muted me-2 fs-7">
                                            <?= \App\Helpers\Url::timeAgo($conversation['updated_at']) ?>
                                        </span>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="badge badge-light-danger"><?= $conversation['unread_count'] ?> não lidas</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    <?php endif; ?>
</div>
<!--end::Row-->

<!--begin::Row - Estatísticas por Setor e Funil-->
<?php if (!empty($departmentStats) || !empty($funnelStats)): ?>
<div class="row gy-5 g-xl-10 mb-5">
    <!--begin::Col - Estatísticas por Setor-->
    <?php if (!empty($departmentStats)): ?>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Estatísticas por Setor</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Top 10 setores</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Setor</th>
                                <th class="min-w-100px">Conversas</th>
                                <th class="min-w-100px">Abertas</th>
                                <th class="min-w-100px">Agentes</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($departmentStats as $dept): ?>
                                <tr>
                                    <td>
                                        <a href="<?= \App\Helpers\Url::to('/departments/' . $dept['id']) ?>" class="text-gray-800 fw-bold text-hover-primary">
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-gray-800 fw-bold"><?= number_format($dept['conversations_count'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-warning"><?= number_format($dept['open_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary"><?= number_format($dept['agents_count'] ?? 0) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    <?php endif; ?>
    
    <!--begin::Col - Estatísticas por Funil-->
    <?php if (!empty($funnelStats)): ?>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Estatísticas por Funil</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Top 10 funis ativos</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Funil</th>
                                <th class="min-w-100px">Conversas</th>
                                <th class="min-w-100px">Estágios</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($funnelStats as $funnel): ?>
                                <tr>
                                    <td>
                                        <a href="<?= \App\Helpers\Url::to('/funnels/' . $funnel['id'] . '/kanban') ?>" class="text-gray-800 fw-bold text-hover-primary">
                                            <?= htmlspecialchars($funnel['name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-gray-800 fw-bold"><?= number_format($funnel['conversations_count'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info"><?= number_format($funnel['stages_count'] ?? 0) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    <?php endif; ?>
</div>
<!--end::Row-->
<?php endif; ?>

<!--begin::Row - Cards Individuais de Agentes-->
<?php if (!empty($allAgentsMetrics)): ?>
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Métricas Individuais dos Agentes</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Desempenho detalhado de cada agente</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <div class="row g-4">
                    <?php foreach ($allAgentsMetrics as $agent): ?>
                    <div class="col-xl-4 col-lg-6">
                        <div class="card border border-gray-300 h-100">
                            <div class="card-body">
                                <!-- Header do Card -->
                                <div class="d-flex align-items-center mb-4">
                                    <div class="symbol symbol-50px me-3">
                                        <?php if (!empty($agent['agent_avatar'])): ?>
                                            <img src="<?= htmlspecialchars($agent['agent_avatar']) ?>" alt="<?= htmlspecialchars($agent['agent_name']) ?>" class="symbol-label" />
                                        <?php else: ?>
                                            <div class="symbol-label fs-2 fw-semibold text-primary bg-light-primary">
                                                <?= mb_substr(htmlspecialchars($agent['agent_name'] ?? 'A'), 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold fs-5 text-gray-800"><?= htmlspecialchars($agent['agent_name']) ?></div>
                                        <div class="d-flex align-items-center mt-1">
                                            <?php
                                            $statusColors = [
                                                'online' => 'success',
                                                'busy' => 'warning',
                                                'away' => 'info',
                                                'offline' => 'gray-400'
                                            ];
                                            $statusLabels = [
                                                'online' => 'Online',
                                                'busy' => 'Ocupado',
                                                'away' => 'Ausente',
                                                'offline' => 'Offline'
                                            ];
                                            $status = $agent['availability_status'] ?? 'offline';
                                            $color = $statusColors[$status] ?? 'gray-400';
                                            $label = $statusLabels[$status] ?? 'Offline';
                                            ?>
                                            <span class="bullet bullet-dot bg-<?= $color ?> me-2"></span>
                                            <span class="text-muted fs-7"><?= $label ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Métricas -->
                                <div class="separator separator-dashed my-4"></div>
                                
                                <!-- Conversas -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-7">Conversas Totais</span>
                                    <span class="fw-bold fs-5 text-gray-800"><?= number_format($agent['total_conversations'] ?? 0) ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-7">Em Aberto</span>
                                    <span class="fw-bold text-warning"><?= number_format($agent['open_conversations'] ?? 0) ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-7">Fechadas</span>
                                    <span class="fw-bold text-success"><?= number_format($agent['closed_conversations'] ?? 0) ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-7">Resolvidas</span>
                                    <span class="fw-bold text-info"><?= number_format($agent['resolved_conversations'] ?? 0) ?></span>
                                </div>
                                
                                <div class="separator separator-dashed my-4"></div>
                                
                                <!-- SLA e Tempos -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-7">Taxa de Resolução</span>
                                    <span class="badge badge-light-success"><?= number_format($agent['resolution_rate'] ?? 0, 1) ?>%</span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-7">Tempo Médio Resolução</span>
                                    <span class="fw-bold text-gray-800">
                                        <?php
                                        $avgHours = $agent['avg_resolution_hours'] ?? 0;
                                        if ($avgHours > 0) {
                                            if ($avgHours < 1) {
                                                echo number_format($avgHours * 60, 0) . ' min';
                                            } else {
                                                echo number_format($avgHours, 1) . 'h';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-7">Tempo Médio 1ª Resposta</span>
                                    <span class="fw-bold text-gray-800">
                                        <?php
                                        $avgFirst = $agent['avg_first_response_minutes'] ?? 0;
                                        if ($avgFirst > 0) {
                                            if ($avgFirst < 60) {
                                                echo number_format($avgFirst, 0) . ' min';
                                            } else {
                                                echo number_format($avgFirst / 60, 1) . 'h';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="separator separator-dashed my-4"></div>
                                
                                <!-- SLA -->
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted fs-7">SLA 5min</span>
                                    <span class="badge badge-light-<?= ($agent['sla_5min_rate'] ?? 0) >= 80 ? 'success' : (($agent['sla_5min_rate'] ?? 0) >= 50 ? 'warning' : 'danger') ?>">
                                        <?= number_format($agent['sla_5min_rate'] ?? 0, 1) ?>%
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted fs-7">SLA 15min</span>
                                    <span class="badge badge-light-<?= ($agent['sla_15min_rate'] ?? 0) >= 80 ? 'success' : (($agent['sla_15min_rate'] ?? 0) >= 50 ? 'warning' : 'danger') ?>">
                                        <?= number_format($agent['sla_15min_rate'] ?? 0, 1) ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->
<?php endif; ?>

<!--begin::Row - Gráficos-->
<div class="row g-5 mb-5 mt-5">
    <!--begin::Col - Gráfico de Conversas ao Longo do Tempo-->
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Conversas ao Longo do Tempo</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Evolução de conversas no período</span>
                </h3>
                <div class="card-toolbar">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="chart_group_by" id="group_by_day" value="day" checked>
                        <label class="btn btn-sm btn-light" for="group_by_day">Dia</label>
                        
                        <input type="radio" class="btn-check" name="chart_group_by" id="group_by_week" value="week">
                        <label class="btn btn-sm btn-light" for="group_by_week">Semana</label>
                        
                        <input type="radio" class="btn-check" name="chart_group_by" id="group_by_month" value="month">
                        <label class="btn btn-sm btn-light" for="group_by_month">Mês</label>
                    </div>
                </div>
            </div>
            <div class="card-body pt-3">
                <canvas id="kt_chart_conversations_over_time" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Gráficos de Distribuição-->
<div class="row g-5 mb-5">
    <!--begin::Col - Gráfico de Conversas por Canal-->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Conversas por Canal</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Distribuição por canal de comunicação</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <canvas id="kt_chart_conversations_by_channel" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Gráfico de Conversas por Status-->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Conversas por Status</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Distribuição por status</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <canvas id="kt_chart_conversations_by_status" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Performance de Agentes-->
<div class="row g-5 mb-5">
    <!--begin::Col - Gráfico de Performance de Agentes-->
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Performance de Agentes</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Top 10 agentes por conversas atendidas</span>
                </h3>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="exportReport('csv')">
                        <i class="ki-duotone ki-file-down fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                        Exportar CSV
                    </button>
                </div>
            </div>
            <div class="card-body pt-3">
                <canvas id="kt_chart_agents_performance" style="height: 350px;"></canvas>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row-->
<div class="row gy-5 g-xl-10">
    <!--begin::Col-->
    <div class="col-xxl-6">
        <!--begin::List Widget 5-->
        <div class="card card-xl-stretch mb-xl-10">
            <!--begin::Header-->
            <div class="card-header align-items-center border-0 mt-4">
                <h3 class="card-title align-items-start flex-column">
                    <span class="fw-bold mb-2 text-gray-900">Ações Rápidas</span>
                    <span class="text-muted fw-semibold fs-7">Acesso rápido às principais funcionalidades</span>
                </h3>
                <div class="card-toolbar">
                    <!--begin::Menu-->
                    <button type="button" class="btn btn-sm btn-icon btn-color-primary btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                        <i class="ki-duotone ki-category fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                    </button>
                    <!--begin::Menu 1-->
                    <div class="menu menu-sub menu-sub-dropdown w-250px w-md-300px" data-kt-menu="true">
                        <!--begin::Header-->
                        <div class="px-7 py-5">
                            <div class="fs-5 text-gray-900 fw-bold">Opções</div>
                        </div>
                        <!--end::Header-->
                        <!--begin::Menu separator-->
                        <div class="separator border-gray-200"></div>
                        <!--end::Menu separator-->
                        <!--begin::Form-->
                        <div class="px-7 py-5">
                            <!--begin::Menu item-->
                            <div class="menu-item px-3">
                                <a href="<?= \App\Helpers\Url::to('/conversations') ?>" class="menu-link px-3">Ver Conversas</a>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3">Gerenciar Contatos</a>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3">Configurar Funis</a>
                            </div>
                            <!--end::Menu item-->
                        </div>
                        <!--end::Form-->
                    </div>
                    <!--end::Menu 1-->
                    <!--end::Menu-->
                </div>
            </div>
            <!--end::Header-->
            <!--begin::Body-->
            <div class="card-body pt-5">
                <!--begin::Timeline-->
                <div class="timeline-label">
                    <!--begin::Item-->
                    <div class="timeline-item">
                        <!--begin::Label-->
                        <div class="timeline-label fw-bold text-gray-800 fs-6">Conversas</div>
                        <!--end::Label-->
                        <!--begin::Badge-->
                        <div class="timeline-badge">
                            <i class="fa fa-genderless text-primary fs-1"></i>
                        </div>
                        <!--end::Badge-->
                        <!--begin::Text-->
                        <div class="fw-mormal timeline-content text-muted ps-3">
                            <a href="<?= \App\Helpers\Url::to('/conversations') ?>" class="text-primary">Ver todas as conversas</a> e gerenciar atendimentos
                        </div>
                        <!--end::Text-->
                    </div>
                    <!--end::Item-->
                    
                    <!--begin::Item-->
                    <div class="timeline-item">
                        <!--begin::Label-->
                        <div class="timeline-label fw-bold text-gray-800 fs-6">Contatos</div>
                        <!--end::Label-->
                        <!--begin::Badge-->
                        <div class="timeline-badge">
                            <i class="fa fa-genderless text-success fs-1"></i>
                        </div>
                        <!--end::Badge-->
                        <!--begin::Content-->
                        <div class="timeline-content d-flex">
                            <span class="fw-bold text-gray-800 ps-3">Gerenciar contatos</span>
                        </div>
                        <!--end::Content-->
                    </div>
                    <!--end::Item-->
                    
                    <!--begin::Item-->
                    <div class="timeline-item">
                        <!--begin::Label-->
                        <div class="timeline-label fw-bold text-gray-800 fs-6">Funis</div>
                        <!--end::Label-->
                        <!--begin::Badge-->
                        <div class="timeline-badge">
                            <i class="fa fa-genderless text-warning fs-1"></i>
                        </div>
                        <!--end::Badge-->
                        <!--begin::Desc-->
                        <div class="timeline-content fw-bold text-gray-800 ps-3">Configurar funis com 
                        <a href="#" class="text-primary">Kanban</a> para organizar conversas</div>
                        <!--end::Desc-->
                    </div>
                    <!--end::Item-->
                    
                    <!--begin::Item-->
                    <div class="timeline-item">
                        <!--begin::Label-->
                        <div class="timeline-label fw-bold text-gray-800 fs-6">Automações</div>
                        <!--end::Label-->
                        <!--begin::Badge-->
                        <div class="timeline-badge">
                            <i class="fa fa-genderless text-info fs-1"></i>
                        </div>
                        <!--end::Badge-->
                        <!--begin::Text-->
                        <div class="timeline-content fw-mormal text-muted ps-3">Criar automações para otimizar o atendimento</div>
                        <!--end::Text-->
                    </div>
                    <!--end::Item-->
                </div>
                <!--end::Timeline-->
            </div>
            <!--end: Card Body-->
        </div>
        <!--end: List Widget 5-->
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-xxl-6">
        <!--begin::List Widget 4-->
        <div class="card card-xl-stretch mb-5 mb-xl-10">
            <!--begin::Header-->
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Funcionalidades</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Principais recursos do sistema</span>
                </h3>
                <div class="card-toolbar">
                    <!--begin::Menu-->
                    <button type="button" class="btn btn-sm btn-icon btn-color-primary btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                        <i class="ki-duotone ki-category fs-6">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                    </button>
                    <!--begin::Menu 3-->
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold w-200px py-3" data-kt-menu="true">
                        <!--begin::Heading-->
                        <div class="menu-item px-3">
                            <div class="menu-content text-muted pb-2 px-3 fs-7 text-uppercase">Ações</div>
                        </div>
                        <!--end::Heading-->
                        <!--begin::Menu item-->
                        <div class="menu-item px-3">
                            <a href="<?= \App\Helpers\Url::to('/conversations') ?>" class="menu-link px-3">Nova Conversa</a>
                        </div>
                        <!--end::Menu item-->
                        <!--begin::Menu item-->
                        <div class="menu-item px-3">
                            <a href="#" class="menu-link px-3">Novo Contato</a>
                        </div>
                        <!--end::Menu item-->
                        <!--begin::Menu item-->
                        <div class="menu-item px-3">
                            <a href="#" class="menu-link px-3">Criar Funil</a>
                        </div>
                        <!--end::Menu item-->
                    </div>
                    <!--end::Menu 3-->
                    <!--end::Menu-->
                </div>
            </div>
            <!--end::Header-->
            <!--begin::Body-->
            <div class="card-body pt-5">
                <!--begin::Item-->
                <div class="d-flex align-items-sm-center mb-7">
                    <!--begin::Symbol-->
                    <div class="symbol symbol-50px me-5">
                        <span class="symbol-label">
                            <i class="ki-duotone ki-chat fs-2x text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <!--end::Symbol-->
                    <!--begin::Section-->
                    <div class="d-flex align-items-center flex-row-fluid flex-wrap">
                        <div class="flex-grow-1 me-2">
                            <a href="<?= \App\Helpers\Url::to('/conversations') ?>" class="text-gray-800 text-hover-primary fs-6 fw-bold">Conversas</a>
                            <span class="text-muted fw-semibold d-block fs-7">Gerenciar todas as conversas e mensagens</span>
                        </div>
                        <span class="badge badge-light-primary fw-bold my-2"><?= $stats['conversations']['total'] ?? 0 ?></span>
                    </div>
                    <!--end::Section-->
                </div>
                <!--end::Item-->
                
                <!--begin::Item-->
                <div class="d-flex align-items-sm-center mb-7">
                    <!--begin::Symbol-->
                    <div class="symbol symbol-50px me-5">
                        <span class="symbol-label">
                            <i class="ki-duotone ki-profile-user fs-2x text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <!--end::Symbol-->
                    <!--begin::Section-->
                    <div class="d-flex align-items-center flex-row-fluid flex-wrap">
                        <div class="flex-grow-1 me-2">
                            <a href="#" class="text-gray-800 text-hover-primary fs-6 fw-bold">Contatos</a>
                            <span class="text-muted fw-semibold d-block fs-7">Gerenciar base de contatos</span>
                        </div>
                        <span class="badge badge-light-success fw-bold my-2">Em breve</span>
                    </div>
                    <!--end::Section-->
                </div>
                <!--end::Item-->
                
                <!--begin::Item-->
                <div class="d-flex align-items-sm-center mb-7">
                    <!--begin::Symbol-->
                    <div class="symbol symbol-50px me-5">
                        <span class="symbol-label">
                            <i class="ki-duotone ki-category fs-2x text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                    </div>
                    <!--end::Symbol-->
                    <!--begin::Section-->
                    <div class="d-flex align-items-center flex-row-fluid flex-wrap">
                        <div class="flex-grow-1 me-2">
                            <a href="#" class="text-gray-800 text-hover-primary fs-6 fw-bold">Funis Kanban</a>
                            <span class="text-muted fw-semibold d-block fs-7">Organizar conversas em funis</span>
                        </div>
                        <span class="badge badge-light-warning fw-bold my-2">Em breve</span>
                    </div>
                    <!--end::Section-->
                </div>
                <!--end::Item-->
                
                <!--begin::Item-->
                <div class="d-flex align-items-sm-center mb-7">
                    <!--begin::Symbol-->
                    <div class="symbol symbol-50px me-5">
                        <span class="symbol-label">
                            <i class="ki-duotone ki-gear fs-2x text-info">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <!--end::Symbol-->
                    <!--begin::Section-->
                    <div class="d-flex align-items-center flex-row-fluid flex-wrap">
                        <div class="flex-grow-1 me-2">
                            <a href="#" class="text-gray-800 text-hover-primary fs-6 fw-bold">Automações</a>
                            <span class="text-muted fw-semibold d-block fs-7">Criar regras automáticas</span>
                        </div>
                        <span class="badge badge-light-info fw-bold my-2">Em breve</span>
                    </div>
                    <!--end::Section-->
                </div>
                <!--end::Item-->
            </div>
            <!--end::Body-->
        </div>
        <!--end::List Widget 4-->
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<?php 
$content = ob_get_clean(); 

// Adicionar Chart.js via CDN
$scripts = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Variáveis globais para os gráficos
let chartConversationsOverTime = null;
let chartConversationsByChannel = null;
let chartConversationsByStatus = null;
let chartAgentsPerformance = null;

// Função para carregar dados do gráfico
function loadChartData(chartType, canvasId, configCallback) {
    const dateFrom = document.getElementById("kt_dashboard_date_from").value;
    const dateTo = document.getElementById("kt_dashboard_date_to").value;
    const groupBy = document.querySelector("input[name=\"chart_group_by\"]:checked")?.value || "day";
    
    const url = new URL(\'' . \App\Helpers\Url::to('/dashboard/chart-data') . '\', window.location.origin);
    url.searchParams.append("type", chartType);
    url.searchParams.append("date_from", dateFrom);
    url.searchParams.append("date_to", dateTo);
    if (groupBy) url.searchParams.append("group_by", groupBy);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const ctx = document.getElementById(canvasId).getContext("2d");
                
                // Destruir gráfico existente se houver
                const chartVarName = canvasId.replace("kt_chart_", "");
                if (window["chart" + chartVarName.charAt(0).toUpperCase() + chartVarName.slice(1)]) {
                    window["chart" + chartVarName.charAt(0).toUpperCase() + chartVarName.slice(1)].destroy();
                }
                
                // Criar novo gráfico
                const chart = new Chart(ctx, configCallback(data.data));
                window["chart" + chartVarName.charAt(0).toUpperCase() + chartVarName.slice(1)] = chart;
            }
        })
        .catch(error => {
            console.error("Erro ao carregar dados do gráfico:", error);
        });
}

// Configuração do gráfico de conversas ao longo do tempo
function configConversationsOverTime(data) {
    const labels = data.map(item => item.period);
    const totalData = data.map(item => parseInt(item.total || 0));
    const openData = data.map(item => parseInt(item.open_count || 0));
    const closedData = data.map(item => parseInt(item.closed_count || 0));
    
    return {
        type: "line",
        data: {
            labels: labels,
            datasets: [
                {
                    label: "Total",
                    data: totalData,
                    borderColor: "#009ef7",
                    backgroundColor: "rgba(0, 158, 247, 0.1)",
                    tension: 0.4
                },
                {
                    label: "Abertas",
                    data: openData,
                    borderColor: "#ffc700",
                    backgroundColor: "rgba(255, 199, 0, 0.1)",
                    tension: 0.4
                },
                {
                    label: "Fechadas",
                    data: closedData,
                    borderColor: "#50cd89",
                    backgroundColor: "rgba(80, 205, 137, 0.1)",
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: "top"
                },
                tooltip: {
                    mode: "index",
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };
}

// Configuração do gráfico de conversas por canal
function configConversationsByChannel(data) {
    const labels = data.map(item => item.channel || "N/A");
    const counts = data.map(item => parseInt(item.count || 0));
    const colors = ["#009ef7", "#ffc700", "#50cd89", "#7239ea", "#f1416c", "#181c32"];
    
    return {
        type: "doughnut",
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: colors.slice(0, counts.length)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: "right"
                }
            }
        }
    };
}

// Configuração do gráfico de conversas por status
function configConversationsByStatus(data) {
    const labels = data.map(item => {
        const statusMap = {
            "open": "Aberta",
            "pending": "Pendente",
            "closed": "Fechada",
            "resolved": "Resolvida"
        };
        return statusMap[item.status] || item.status;
    });
    const counts = data.map(item => parseInt(item.count || 0));
    const colors = ["#ffc700", "#f1416c", "#50cd89", "#009ef7", "#7239ea"];
    
    return {
        type: "pie",
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: colors.slice(0, counts.length)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: "right"
                }
            }
        }
    };
}

// Configuração do gráfico de performance de agentes
function configAgentsPerformance(data) {
    const labels = data.map(item => item.name);
    const totalData = data.map(item => parseInt(item.total_conversations || 0));
    const closedData = data.map(item => parseInt(item.closed_conversations || 0));
    
    return {
        type: "bar",
        data: {
            labels: labels,
            datasets: [
                {
                    label: "Total de Conversas",
                    data: totalData,
                    backgroundColor: "#009ef7"
                },
                {
                    label: "Conversas Fechadas",
                    data: closedData,
                    backgroundColor: "#50cd89"
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: "top"
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };
}

// Carregar todos os gráficos
function loadAllCharts() {
    loadChartData("conversations_over_time", "kt_chart_conversations_over_time", configConversationsOverTime);
    loadChartData("conversations_by_channel", "kt_chart_conversations_by_channel", configConversationsByChannel);
    loadChartData("conversations_by_status", "kt_chart_conversations_by_status", configConversationsByStatus);
    loadChartData("agents_performance", "kt_chart_agents_performance", configAgentsPerformance);
}

// Função para recarregar dashboard
function loadDashboard() {
    const dateFrom = document.getElementById("kt_dashboard_date_from").value;
    const dateTo = document.getElementById("kt_dashboard_date_to").value;
    
    window.location.href = \'' . \App\Helpers\Url::to('/dashboard') . '?date_from=\' + dateFrom + \'&date_to=\' + dateTo;
}

// Função para exportar relatório
function exportReport(format) {
    const dateFrom = document.getElementById("kt_dashboard_date_from").value;
    const dateTo = document.getElementById("kt_dashboard_date_to").value;
    
    const url = new URL(\'' . \App\Helpers\Url::to('/dashboard/export') . '\', window.location.origin);
    url.searchParams.append("format", format);
    url.searchParams.append("date_from", dateFrom);
    url.searchParams.append("date_to", dateTo);
    
    window.open(url, "_blank");
}

// Carregar gráficos quando a página carregar
document.addEventListener("DOMContentLoaded", function() {
    loadAllCharts();
    
    // Atualizar gráfico de conversas ao longo do tempo quando mudar o agrupamento
    document.querySelectorAll("input[name=\"chart_group_by\"]").forEach(radio => {
        radio.addEventListener("change", function() {
            loadChartData("conversations_over_time", "kt_chart_conversations_over_time", configConversationsOverTime);
        });
    });
});
</script>
';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
