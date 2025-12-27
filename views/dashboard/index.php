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
    <div class="col-xl-3">
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
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Tempo 1ª Resposta (Geral)</h3>
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
                            $avgFirstSeconds = $stats['metrics']['avg_first_response_time_seconds'] ?? 0;
                            $avgFirstMinutes = $stats['metrics']['avg_first_response_time_minutes'] ?? 0;
                            if ($avgFirstSeconds > 0) {
                                if ($avgFirstSeconds < 60) {
                                    echo number_format($avgFirstSeconds, 0) . 's';
                                } elseif ($avgFirstMinutes < 60) {
                                    echo number_format($avgFirstMinutes, 1) . ' min';
                                } else {
                                    echo number_format($avgFirstMinutes / 60, 1) . 'h';
                                }
                            } else {
                                $hasMessages = ($stats['messages']['total'] ?? 0) > 0;
                                if (!$hasMessages) {
                                    echo '<span class="fs-6 text-muted">Sem dados</span>';
                                } else {
                                    echo '-';
                                }
                            }
                            ?>
                        </div>
                        <div class="text-muted fs-6">Média (IA + Humanos)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">Tempo de Resposta (Geral)</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-message-text fs-3x text-info me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?php
                            $avgRespSeconds = $stats['metrics']['avg_response_time_seconds'] ?? 0;
                            $avgRespMinutes = $stats['metrics']['avg_response_time_minutes'] ?? 0;
                            if ($avgRespSeconds > 0) {
                                if ($avgRespSeconds < 60) {
                                    echo number_format($avgRespSeconds, 0) . 's';
                                } elseif ($avgRespMinutes < 60) {
                                    echo number_format($avgRespMinutes, 1) . ' min';
                                } else {
                                    echo number_format($avgRespMinutes / 60, 1) . 'h';
                                }
                            } elseif (($stats['messages']['total'] ?? 0) == 0) {
                                echo '<span class="fs-6 text-muted">Sem dados</span>';
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                        <div class="text-muted fs-6">Média (IA + Humanos)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-xl-3">
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

<!--begin::Row - Métricas Separadas (Humanos vs IA)-->
<div class="row g-5 mb-5">
    <!--begin::Col - Tempo Humanos-->
    <div class="col-xl-6">
        <div class="card h-100 bg-light-info">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-profile-user fs-2 text-info me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    🧑 Tempo de Atendimento - HUMANOS
                </h3>
            </div>
            <div class="card-body pt-3">
                <div class="row">
                    <div class="col-6">
                        <div class="d-flex flex-column">
                            <span class="fw-semibold text-gray-600 fs-7">1ª Resposta</span>
                            <span class="fw-bold text-gray-800 fs-2">
                                <?php
                                $avgFirstResponseHuman = $stats['metrics']['avg_first_response_time_human'] ?? null;
                                if ($avgFirstResponseHuman !== null && $avgFirstResponseHuman > 0) {
                                    echo \App\Services\AgentPerformanceService::formatTime($avgFirstResponseHuman);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column">
                            <span class="fw-semibold text-gray-600 fs-7">Resposta Média</span>
                            <span class="fw-bold text-gray-800 fs-2">
                                <?php
                                $avgResponseHuman = $stats['metrics']['avg_response_time_human'] ?? null;
                                if ($avgResponseHuman !== null && $avgResponseHuman > 0) {
                                    echo \App\Services\AgentPerformanceService::formatTime($avgResponseHuman);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Tempo IA-->
    <div class="col-xl-6">
        <div class="card h-100 bg-light-primary">
            <div class="card-header border-0 pt-5 d-flex justify-content-between align-items-center">
                <h3 class="card-title fw-bold mb-0">
                    <i class="ki-duotone ki-robot fs-2 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    🤖 Tempo de Atendimento - IA
                </h3>
                <a href="<?= \App\Helpers\Url::to('/dashboard/ai') ?>" class="btn btn-sm btn-light-primary">
                    Ver Dashboard IA
                </a>
            </div>
            <div class="card-body pt-3">
                <div class="row">
                    <div class="col-6">
                        <div class="d-flex flex-column">
                            <span class="fw-semibold text-gray-600 fs-7">1ª Resposta</span>
                            <span class="fw-bold text-gray-800 fs-2">
                                <?php
                                $avgFirstResponseAI = $stats['metrics']['avg_first_response_time_ai'] ?? null;
                                if ($avgFirstResponseAI !== null && $avgFirstResponseAI > 0) {
                                    if ($avgFirstResponseAI < 60) {
                                        echo number_format($avgFirstResponseAI, 0) . 's';
                                    } else {
                                        echo number_format($avgFirstResponseAI / 60, 1) . 'min';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column">
                            <span class="fw-semibold text-gray-600 fs-7">Resposta Média</span>
                            <span class="fw-bold text-gray-800 fs-2">
                                <?php
                                $avgResponseAI = $stats['metrics']['avg_response_time_ai'] ?? null;
                                if ($avgResponseAI !== null && $avgResponseAI > 0) {
                                    if ($avgResponseAI < 60) {
                                        echo number_format($avgResponseAI, 0) . 's';
                                    } else {
                                        echo number_format($avgResponseAI / 60, 1) . 'min';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Resumo IA (se houver dados)-->
<?php if (!empty($stats['ai_metrics']) && ($stats['ai_metrics']['total_ai_conversations'] ?? 0) > 0): ?>
<div class="row g-5 mb-5">
    <div class="col-xl-12">
        <div class="card bg-light-success">
            <div class="card-body py-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="symbol symbol-50px">
                            <div class="symbol-label fs-2x bg-success text-white">🤖</div>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0">Resumo de IA no Período</h4>
                            <span class="text-muted fs-7">Métricas consolidadas de Agentes de IA</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-5">
                        <div class="d-flex flex-column align-items-center">
                            <span class="fw-bold text-gray-800 fs-3"><?= number_format($stats['ai_metrics']['total_ai_conversations'] ?? 0) ?></span>
                            <span class="text-muted fs-7">Conversas IA</span>
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <span class="fw-bold text-success fs-3"><?= number_format($stats['ai_metrics']['ai_resolution_rate'] ?? 0, 1) ?>%</span>
                            <span class="text-muted fs-7">Taxa Resolução</span>
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <span class="fw-bold text-warning fs-3"><?= number_format($stats['ai_metrics']['ai_escalation_rate'] ?? 0, 1) ?>%</span>
                            <span class="text-muted fs-7">Escalonadas</span>
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <span class="fw-bold text-gray-800 fs-3">$<?= number_format($stats['ai_metrics']['total_cost'] ?? 0, 2) ?></span>
                            <span class="text-muted fs-7">Custo Total</span>
                        </div>
                    </div>
                    <a href="<?= \App\Helpers\Url::to('/dashboard/ai') ?>" class="btn btn-success">
                        <i class="ki-duotone ki-chart-simple fs-2 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Ver Dashboard Completo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
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
            <div class="card-body py-3" style="max-height: 400px; overflow-y: auto;">
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
<div class="row gy-5 g-xl-10 mb-5 mt-10">
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
<?php if (!empty($allAgentsMetrics)): 
    // Buscar configurações de SLA dinâmicas
    $slaSettings = \App\Services\ConversationSettingsService::getSettings()['sla'] ?? [];
    $slaFirstResponse = $slaSettings['first_response_time'] ?? 15; // minutos
    $slaOngoingResponse = $slaSettings['ongoing_response_time'] ?? $slaFirstResponse; // SLA para respostas contínuas
?>
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Métricas Individuais dos Agentes</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">SLA 1ª Resposta: <?= $slaFirstResponse ?>min | SLA Respostas: <?= $slaOngoingResponse ?>min</span>
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
                                        $avgFirstSec = $agent['avg_first_response_seconds'] ?? 0;
                                        $avgFirstMin = $agent['avg_first_response_minutes'] ?? 0;
                                        if ($avgFirstSec > 0) {
                                            if ($avgFirstSec < 60) {
                                                echo number_format($avgFirstSec, 0) . 's';
                                            } elseif ($avgFirstMin < 60) {
                                                echo number_format($avgFirstMin, 1) . ' min';
                                            } else {
                                                echo number_format($avgFirstMin / 60, 1) . 'h';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="separator separator-dashed my-4"></div>
                                
                                <!-- Estatísticas de Disponibilidade -->
                                <div class="mb-3">
                                    <span class="text-muted fs-7 fw-bold d-block mb-2">Tempo em Cada Status</span>
                                    <?php
                                    $availabilityStats = $agent['availability_stats'] ?? [];
                                    $statusConfig = [
                                        'online' => ['label' => 'Online', 'color' => 'success', 'icon' => 'check-circle'],
                                        'away' => ['label' => 'Ausente', 'color' => 'info', 'icon' => 'clock'],
                                        'busy' => ['label' => 'Ocupado', 'color' => 'warning', 'icon' => 'pause-circle'],
                                        'offline' => ['label' => 'Offline', 'color' => 'secondary', 'icon' => 'x-circle']
                                    ];
                                    
                                    foreach ($statusConfig as $status => $config):
                                        $stats = $availabilityStats[$status] ?? ['formatted' => '0s', 'percentage' => 0];
                                        if ($stats['seconds'] > 0 || $status === 'online'): // Sempre mostrar online, mesmo se 0
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted fs-8">
                                            <i class="ki-duotone ki-<?= $config['icon'] ?> fs-7 text-<?= $config['color'] ?> me-1">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <?= $config['label'] ?>
                                        </span>
                                        <div class="text-end">
                                            <span class="fw-bold text-gray-800 fs-7"><?= $stats['formatted'] ?? '0s' ?></span>
                                            <?php if (($stats['percentage'] ?? 0) > 0): ?>
                                            <span class="text-muted fs-8 ms-1">(<?= number_format($stats['percentage'], 1) ?>%)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-7">Tempo Médio de Resposta</span>
                                    <span class="fw-bold text-gray-800">
                                        <?php
                                        $avgRespSec = $agent['avg_response_seconds'] ?? 0;
                                        $avgRespMin = $agent['avg_response_minutes'] ?? 0;
                                        if ($avgRespSec > 0) {
                                            if ($avgRespSec < 60) {
                                                echo number_format($avgRespSec, 0) . 's';
                                            } elseif ($avgRespMin < 60) {
                                                echo number_format($avgRespMin, 1) . ' min';
                                            } else {
                                                echo number_format($avgRespMin / 60, 1) . 'h';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="separator separator-dashed my-4"></div>
                                
                                <!-- SLA 1ª Resposta - Proposta 1: Indicadores Visuais Claros -->
                                <?php
                                // Dados do SLA de primeira resposta
                                $slaFirstRespMinutes = $agent['sla_first_response_minutes'] ?? $slaFirstResponse;
                                $slaFirstRespRate = $agent['sla_first_response_rate'] ?? 0;
                                $avgFirstResponseSeconds = $agent['avg_first_response_seconds'] ?? 0;
                                $avgFirstResponseMinutes = $agent['avg_first_response_minutes'] ?? 0;
                                $firstRespWithinSla = $agent['first_response_within_sla'] ?? 0;
                                $firstRespOutSla = $agent['total_conversations'] - $firstRespWithinSla;
                                
                                // Definir status SLA 1ª Resposta
                                if ($agent['total_conversations'] == 0) {
                                    $sla1Status = 'secondary';
                                    $sla1Icon = '⚪';
                                    $sla1Label = 'Sem dados';
                                } elseif ($slaFirstRespRate >= 80) {
                                    $sla1Status = 'success';
                                    $sla1Icon = '🟢';
                                    $sla1Label = 'Excelente';
                                } elseif ($slaFirstRespRate >= 50) {
                                    $sla1Status = 'warning';
                                    $sla1Icon = '🟡';
                                    $sla1Label = 'Regular';
                                } else {
                                    $sla1Status = 'danger';
                                    $sla1Icon = '🔴';
                                    $sla1Label = 'Crítico';
                                }
                                
                                $avgWithinSla = $avgFirstResponseMinutes <= $slaFirstRespMinutes;
                                ?>
                                <div class="mb-3">
                                    <div class="mb-2">
                                        <span class="text-muted fs-7 fw-bold">SLA 1ª Resposta (<?= $slaFirstRespMinutes ?>min)</span>
                                    </div>
                                    
                                    <?php if ($agent['total_conversations'] > 0): ?>
                                    <!-- Indicador Principal -->
                                    <div class="d-flex align-items-center justify-content-between p-3 rounded bg-light-<?= $sla1Status ?> mb-2">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-2 me-2"><?= $sla1Icon ?></span>
                                            <div>
                                                <div class="fs-2 fw-bold text-<?= $sla1Status ?>"><?= number_format($slaFirstRespRate, 0) ?>%</div>
                                                <div class="text-muted fs-8"><?= $sla1Label ?></div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-6 fw-bold">
                                                <span class="text-success">✅ <?= $firstRespWithinSla ?></span>
                                            </div>
                                            <div class="text-muted fs-8">no prazo</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tempo Médio -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted fs-8">Tempo médio:</span>
                                        <span class="fw-bold fs-7 <?= $avgWithinSla ? 'text-success' : 'text-danger' ?>">
                                            <?php
                                            if ($avgFirstResponseSeconds > 0) {
                                                if ($avgFirstResponseSeconds < 60) {
                                                    echo number_format($avgFirstResponseSeconds, 0) . 's';
                                                } else {
                                                    echo number_format($avgFirstResponseMinutes, 1) . 'min';
                                                }
                                                echo $avgWithinSla ? ' ✓' : ' ✗';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Alerta de Conversas Fora do SLA -->
                                    <?php if ($firstRespOutSla > 0): ?>
                                    <div class="alert alert-<?= $sla1Status === 'danger' ? 'danger' : 'warning' ?> d-flex align-items-center p-2 mb-0">
                                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <span class="fs-8"><?= $firstRespOutSla ?> conversa<?= $firstRespOutSla > 1 ? 's' : '' ?> fora do SLA</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php else: ?>
                                    <div class="text-center text-muted fs-7 py-3">
                                        <i class="ki-duotone ki-information fs-2x mb-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div>Sem conversas no período</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- SLA Respostas - Proposta 1: Indicadores Visuais Claros -->
                                <?php
                                // Dados do SLA de respostas contínuas
                                $slaRespMinutes = $agent['sla_response_minutes'] ?? $slaOngoingResponse;
                                $slaRespRate = $agent['sla_response_rate'] ?? 0;
                                $avgResponseSeconds = $agent['avg_response_seconds'] ?? 0;
                                $avgResponseMinutes = $agent['avg_response_minutes'] ?? 0;
                                $totalResponses = $agent['total_responses'] ?? 0;
                                $responsesWithinSla = $agent['responses_within_sla'] ?? 0;
                                $responsesOutSla = $totalResponses - $responsesWithinSla;
                                
                                // Definir status SLA Respostas
                                if ($totalResponses == 0) {
                                    $sla2Status = 'secondary';
                                    $sla2Icon = '⚪';
                                    $sla2Label = 'Sem dados';
                                } elseif ($slaRespRate >= 80) {
                                    $sla2Status = 'success';
                                    $sla2Icon = '🟢';
                                    $sla2Label = 'Excelente';
                                } elseif ($slaRespRate >= 50) {
                                    $sla2Status = 'warning';
                                    $sla2Icon = '🟡';
                                    $sla2Label = 'Regular';
                                } else {
                                    $sla2Status = 'danger';
                                    $sla2Icon = '🔴';
                                    $sla2Label = 'Crítico';
                                }
                                
                                $avgRespWithinSla = $avgResponseMinutes <= $slaRespMinutes;
                                ?>
                                <div class="mb-2">
                                    <div class="mb-2">
                                        <span class="text-muted fs-7 fw-bold">SLA Respostas (<?= $slaRespMinutes ?>min)</span>
                                    </div>
                                    
                                    <?php if ($totalResponses > 0): ?>
                                    <!-- Indicador Principal -->
                                    <div class="d-flex align-items-center justify-content-between p-3 rounded bg-light-<?= $sla2Status ?> mb-2">
                                        <div class="d-flex align-items-center">
                                            <span class="fs-2 me-2"><?= $sla2Icon ?></span>
                                            <div>
                                                <div class="fs-2 fw-bold text-<?= $sla2Status ?>"><?= number_format($slaRespRate, 0) ?>%</div>
                                                <div class="text-muted fs-8"><?= $sla2Label ?></div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-6 fw-bold">
                                                <span class="text-success">✅ <?= $responsesWithinSla ?></span>
                                            </div>
                                            <div class="text-muted fs-8">no prazo</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tempo Médio -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted fs-8">Tempo médio:</span>
                                        <span class="fw-bold fs-7 <?= $avgRespWithinSla ? 'text-success' : 'text-danger' ?>">
                                            <?php
                                            if ($avgResponseSeconds > 0) {
                                                if ($avgResponseSeconds < 60) {
                                                    echo number_format($avgResponseSeconds, 0) . 's';
                                                } else {
                                                    echo number_format($avgResponseMinutes, 1) . 'min';
                                                }
                                                echo $avgRespWithinSla ? ' ✓' : ' ✗';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Alerta de Respostas Fora do SLA -->
                                    <?php if ($responsesOutSla > 0): ?>
                                    <div class="alert alert-<?= $sla2Status === 'danger' ? 'danger' : 'warning' ?> d-flex align-items-center p-2 mb-0">
                                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <span class="fs-8"><?= $responsesOutSla ?> resposta<?= $responsesOutSla > 1 ? 's' : '' ?> fora do SLA</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php else: ?>
                                    <div class="text-center text-muted fs-7 py-3">
                                        <i class="ki-duotone ki-information fs-2x mb-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div>Sem respostas no período</div>
                                    </div>
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

<!-- Cards "Ações Rápidas" e "Funcionalidades" removidos -->

<?php 
$content = ob_get_clean(); 

// Definir URLs antes da string JavaScript
$chartDataUrl = \App\Helpers\Url::to('/dashboard/chart-data');
$dashboardUrl = \App\Helpers\Url::to('/dashboard');
$exportUrl = \App\Helpers\Url::to('/dashboard/export');

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
    
    const url = new URL(' . json_encode($chartDataUrl) . ', window.location.origin);
    url.searchParams.append("type", chartType);
    url.searchParams.append("date_from", dateFrom);
    url.searchParams.append("date_to", dateTo);
    if (groupBy) url.searchParams.append("group_by", groupBy);
    
    fetch(url)
        .then(response => {
            console.log("Chart response status:", response.status, "for", chartType);
            return response.json();
        })
        .then(data => {
            console.log("Chart data received:", chartType, data);
            
            if (data.success && data.data) {
                // Verificar se há dados
                if (!data.data || (Array.isArray(data.data) && data.data.length === 0)) {
                    console.warn("Sem dados para o gráfico:", chartType);
                    // Mostrar mensagem "Sem dados" no canvas
                    const canvas = document.getElementById(canvasId);
                    if (!canvas) {
                        console.error("Canvas não encontrado:", canvasId);
                        return;
                    }
                    const ctx = canvas.getContext("2d");
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.font = "16px Arial";
                    ctx.textAlign = "center";
                    ctx.fillStyle = "#999";
                    ctx.fillText("Sem dados para exibir", canvas.width / 2, canvas.height / 2);
                    return;
                }
                
                const canvas = document.getElementById(canvasId);
                if (!canvas) {
                    console.error("Canvas não encontrado:", canvasId);
                    return;
                }
                const ctx = canvas.getContext("2d");
                
                // Destruir gráfico existente se houver
                const chartVarName = canvasId.replace("kt_chart_", "");
                const chartInstanceName = "chart" + chartVarName.charAt(0).toUpperCase() + chartVarName.slice(1);
                if (window[chartInstanceName]) {
                    window[chartInstanceName].destroy();
                }
                
                // Criar novo gráfico
                const chart = new Chart(ctx, configCallback(data.data));
                window[chartInstanceName] = chart;
                console.log("Gráfico criado com sucesso:", chartType);
            } else {
                console.error("Erro nos dados do gráfico:", chartType, data);
                // Mostrar mensagem de erro no canvas
                const canvas = document.getElementById(canvasId);
                if (canvas) {
                    const ctx = canvas.getContext("2d");
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.font = "16px Arial";
                    ctx.textAlign = "center";
                    ctx.fillStyle = "#f14c4c";
                    ctx.fillText("Erro ao carregar dados", canvas.width / 2, canvas.height / 2);
                }
            }
        })
        .catch(error => {
            console.error("Erro ao carregar dados do gráfico:", chartType, error);
            // Mostrar mensagem de erro no canvas
            const canvas = document.getElementById(canvasId);
            if (canvas) {
                const ctx = canvas.getContext("2d");
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.font = "16px Arial";
                ctx.textAlign = "center";
                ctx.fillStyle = "#f14c4c";
                ctx.fillText("Erro de conexão", canvas.width / 2, canvas.height / 2);
            }
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
    
    window.location.href = ' . json_encode($dashboardUrl) . ' + \'?date_from=\' + dateFrom + \'&date_to=\' + dateTo;
}

// Função para exportar relatório
function exportReport(format) {
    const dateFrom = document.getElementById("kt_dashboard_date_from").value;
    const dateTo = document.getElementById("kt_dashboard_date_to").value;
    
    const url = new URL(' . json_encode($exportUrl) . ', window.location.origin);
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

<!--begin::Row - Análise de Dias e Horários-->
<?php if (!empty($timeAnalysis)): ?>
<div class="row gy-5 g-xl-10 mb-5 mt-10">
    <!--begin::Col - Dias da Semana-->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Conversas por Dia da Semana</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Distribuição semanal</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <?php if (!empty($timeAnalysis['by_weekday'])): ?>
                    <?php 
                    $maxWeekdayCount = max(array_column($timeAnalysis['by_weekday'], 'count'));
                    ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($timeAnalysis['by_weekday'] as $day): ?>
                            <?php 
                            $percentage = $maxWeekdayCount > 0 ? ($day['count'] / $maxWeekdayCount) * 100 : 0;
                            $barColor = 'primary';
                            if ($percentage >= 80) {
                                $barColor = 'success';
                            } elseif ($percentage >= 50) {
                                $barColor = 'info';
                            } elseif ($percentage >= 30) {
                                $barColor = 'warning';
                            } else {
                                $barColor = 'secondary';
                            }
                            ?>
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold fs-6"><?= htmlspecialchars($day['name']) ?></span>
                                    <span class="badge badge-light-<?= $barColor ?> fs-7"><?= $day['count'] ?> conversas</span>
                                </div>
                                <div class="progress h-15px">
                                    <div class="progress-bar bg-<?= $barColor ?>" role="progressbar" 
                                         style="width: <?= number_format($percentage, 1) ?>%"
                                         aria-valuenow="<?= $day['count'] ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="<?= $maxWeekdayCount ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-10">
                        <i class="ki-duotone ki-calendar fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div>Sem dados disponíveis</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!--end::Col-->

    <!--begin::Col - Horários do Dia-->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Conversas por Horário</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Distribuição ao longo do dia</span>
                </h3>
            </div>
            <div class="card-body pt-5" style="max-height: 500px; overflow-y: auto;">
                <?php if (!empty($timeAnalysis['by_hour'])): ?>
                    <?php 
                    $maxHourCount = max(array_column($timeAnalysis['by_hour'], 'count'));
                    ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($timeAnalysis['by_hour'] as $hour): ?>
                            <?php 
                            $percentage = $maxHourCount > 0 ? ($hour['count'] / $maxHourCount) * 100 : 0;
                            $barColor = 'primary';
                            if ($percentage >= 80) {
                                $barColor = 'danger';
                            } elseif ($percentage >= 60) {
                                $barColor = 'warning';
                            } elseif ($percentage >= 40) {
                                $barColor = 'info';
                            } elseif ($percentage >= 20) {
                                $barColor = 'success';
                            } else {
                                $barColor = 'secondary';
                            }
                            ?>
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold fs-7"><?= htmlspecialchars($hour['label']) ?></span>
                                    <span class="badge badge-light-<?= $barColor ?> fs-8"><?= $hour['count'] ?></span>
                                </div>
                                <div class="progress h-10px">
                                    <div class="progress-bar bg-<?= $barColor ?>" role="progressbar" 
                                         style="width: <?= number_format($percentage, 1) ?>%"
                                         aria-valuenow="<?= $hour['count'] ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="<?= $maxHourCount ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-10">
                        <i class="ki-duotone ki-time fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div>Sem dados disponíveis</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->
<?php endif; ?>

<!--begin::Row - Horários de Pico-->
<?php if (!empty($timeAnalysis) && !empty($timeAnalysis['peak_times'])): ?>
<div class="row gy-5 g-xl-10 mb-5 mt-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">🔥 Horários de Pico</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Top 10 combinações de dia e horário com mais conversas</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-50px">#</th>
                                <th class="min-w-150px">Dia da Semana</th>
                                <th class="min-w-100px">Horário</th>
                                <th class="min-w-100px text-end">Conversas</th>
                                <th class="min-w-200px">Volume</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $maxPeakCount = max(array_column($timeAnalysis['peak_times'], 'count'));
                            foreach ($timeAnalysis['peak_times'] as $index => $peak): 
                                $percentage = $maxPeakCount > 0 ? ($peak['count'] / $maxPeakCount) * 100 : 0;
                                $badgeColor = 'danger';
                                if ($index >= 7) {
                                    $badgeColor = 'info';
                                } elseif ($index >= 4) {
                                    $badgeColor = 'warning';
                                }
                            ?>
                            <tr>
                                <td>
                                    <span class="badge badge-light-<?= $badgeColor ?> fs-7"><?= $index + 1 ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($peak['weekday_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary"><?= htmlspecialchars($peak['hour_label']) ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold fs-6"><?= $peak['count'] ?></span>
                                </td>
                                <td>
                                    <div class="progress h-20px">
                                        <div class="progress-bar bg-<?= $badgeColor ?>" role="progressbar" 
                                             style="width: <?= number_format($percentage, 1) ?>%"
                                             aria-valuenow="<?= $peak['count'] ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="<?= $maxPeakCount ?>">
                                            <?= number_format($percentage, 0) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->
<?php endif; ?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
