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
<div class="row g-5 g-xl-10 mb-5 mb-xl-10">
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
                                
                                <!-- SLA 1ª Resposta -->
                                <?php
                                // Dados do SLA de primeira resposta
                                $slaFirstRespMinutes = $agent['sla_first_response_minutes'] ?? $slaFirstResponse;
                                $slaFirstRespRate = $agent['sla_first_response_rate'] ?? 0;
                                $avgFirstResponseSeconds = $agent['avg_first_response_seconds'] ?? 0;
                                $avgFirstResponseMinutes = $agent['avg_first_response_minutes'] ?? 0;
                                $firstRespWithinSla = $agent['first_response_within_sla'] ?? 0;
                                $firstRespOutsideSla = ($agent['total_conversations'] ?? 0) - $firstRespWithinSla;
                                
                                // Definir status SLA 1ª Resposta
                                if ($agent['total_conversations'] == 0) {
                                    $sla1Status = 'secondary';
                                    $sla1Icon = 'minus-circle';
                                    $sla1Label = 'N/A';
                                } elseif ($slaFirstRespRate >= 80) {
                                    $sla1Status = 'success';
                                    $sla1Icon = 'check-circle';
                                    $sla1Label = 'Excelente';
                                } elseif ($slaFirstRespRate >= 50) {
                                    $sla1Status = 'warning';
                                    $sla1Icon = 'information-circle';
                                    $sla1Label = 'Regular';
                                } else {
                                    $sla1Status = 'danger';
                                    $sla1Icon = 'cross-circle';
                                    $sla1Label = 'Crítico';
                                }
                                
                                $isFirstRespOnTime = $avgFirstResponseMinutes <= $slaFirstRespMinutes;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-muted fs-7 fw-bold">SLA 1ª Resposta (Meta: <?= $slaFirstRespMinutes ?>min)</span>
                                    </div>
                                    <?php if ($agent['total_conversations'] > 0): ?>
                                    <div class="d-flex align-items-center justify-content-between mb-2 p-3 bg-light-<?= $sla1Status ?> rounded">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-<?= $sla1Icon ?> fs-2x text-<?= $sla1Status ?> me-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <div>
                                                <div class="fs-2 fw-bold text-<?= $sla1Status ?>"><?= number_format($slaFirstRespRate, 0) ?>%</div>
                                                <div class="fs-8 text-muted"><?= $sla1Label ?></div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-6 fw-bold text-gray-800">
                                                <span class="text-success"><?= $firstRespWithinSla ?></span>
                                                <span class="text-muted">/</span>
                                                <span class="text-gray-600"><?= $agent['total_conversations'] ?></span>
                                            </div>
                                            <div class="fs-8 text-muted">no prazo</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted fs-8">Tempo médio:</span>
                                        <span class="fw-bold fs-7 <?= $isFirstRespOnTime ? 'text-success' : 'text-danger' ?>">
                                            <?php
                                            if ($avgFirstResponseSeconds > 0) {
                                                if ($avgFirstResponseSeconds < 60) {
                                                    echo number_format($avgFirstResponseSeconds, 0) . 's';
                                                } elseif ($avgFirstResponseMinutes < 60) {
                                                    echo number_format($avgFirstResponseMinutes, 1) . 'min';
                                                } else {
                                                    echo number_format($avgFirstResponseMinutes / 60, 1) . 'h';
                                                }
                                                echo $isFirstRespOnTime ? ' ✓' : ' ✗';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($firstRespOutsideSla > 0): ?>
                                    <div class="alert alert-warning d-flex align-items-center p-2 mt-2 mb-0">
                                        <i class="ki-duotone ki-information fs-2x text-warning me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <span class="fs-8 fw-semibold"><?= $firstRespOutsideSla ?> conversa<?= $firstRespOutsideSla > 1 ? 's' : '' ?> fora do SLA</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="text-center text-muted fs-7 py-3">
                                        <i class="ki-duotone ki-information-2 fs-2x mb-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div>Sem dados no período</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- SLA Respostas -->
                                <?php
                                // Dados do SLA de respostas contínuas
                                $slaRespMinutes = $agent['sla_response_minutes'] ?? $slaOngoingResponse;
                                $slaRespRate = $agent['sla_response_rate'] ?? 0;
                                $avgResponseSeconds = $agent['avg_response_seconds'] ?? 0;
                                $avgResponseMinutes = $agent['avg_response_minutes'] ?? 0;
                                $totalResponses = $agent['total_responses'] ?? 0;
                                $responsesWithinSla = $agent['responses_within_sla'] ?? 0;
                                $responsesOutsideSla = $totalResponses - $responsesWithinSla;
                                
                                // Definir status SLA Respostas
                                if ($totalResponses == 0) {
                                    $sla2Status = 'secondary';
                                    $sla2Icon = 'minus-circle';
                                    $sla2Label = 'N/A';
                                } elseif ($slaRespRate >= 80) {
                                    $sla2Status = 'success';
                                    $sla2Icon = 'check-circle';
                                    $sla2Label = 'Excelente';
                                } elseif ($slaRespRate >= 50) {
                                    $sla2Status = 'warning';
                                    $sla2Icon = 'information-circle';
                                    $sla2Label = 'Regular';
                                } else {
                                    $sla2Status = 'danger';
                                    $sla2Icon = 'cross-circle';
                                    $sla2Label = 'Crítico';
                                }
                                
                                $isRespOnTime = $avgResponseMinutes <= $slaRespMinutes;
                                ?>
                                <div class="mb-2">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="text-muted fs-7 fw-bold">SLA Respostas (Meta: <?= $slaRespMinutes ?>min)</span>
                                    </div>
                                    <?php if ($totalResponses > 0): ?>
                                    <div class="d-flex align-items-center justify-content-between mb-2 p-3 bg-light-<?= $sla2Status ?> rounded">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-<?= $sla2Icon ?> fs-2x text-<?= $sla2Status ?> me-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <div>
                                                <div class="fs-2 fw-bold text-<?= $sla2Status ?>"><?= number_format($slaRespRate, 0) ?>%</div>
                                                <div class="fs-8 text-muted"><?= $sla2Label ?></div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-6 fw-bold text-gray-800">
                                                <span class="text-success"><?= $responsesWithinSla ?></span>
                                                <span class="text-muted">/</span>
                                                <span class="text-gray-600"><?= $totalResponses ?></span>
                                            </div>
                                            <div class="fs-8 text-muted">no prazo</div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted fs-8">Tempo médio:</span>
                                        <span class="fw-bold fs-7 <?= $isRespOnTime ? 'text-success' : 'text-danger' ?>">
                                            <?php
                                            if ($avgResponseSeconds > 0) {
                                                if ($avgResponseSeconds < 60) {
                                                    echo number_format($avgResponseSeconds, 0) . 's';
                                                } elseif ($avgResponseMinutes < 60) {
                                                    echo number_format($avgResponseMinutes, 1) . 'min';
                                                } else {
                                                    echo number_format($avgResponseMinutes / 60, 1) . 'h';
                                                }
                                                echo $isRespOnTime ? ' ✓' : ' ✗';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($responsesOutsideSla > 0): ?>
                                    <div class="alert alert-warning d-flex align-items-center p-2 mt-2 mb-0">
                                        <i class="ki-duotone ki-information fs-2x text-warning me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <span class="fs-8 fw-semibold"><?= $responsesOutsideSla ?> resposta<?= $responsesOutsideSla > 1 ? 's' : '' ?> fora do SLA</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="text-center text-muted fs-7 py-3">
                                        <i class="ki-duotone ki-information-2 fs-2x mb-2">
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

<!--begin::Row - Métricas de Times-->
<?php if (!empty($teamsMetrics)): ?>
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Performance dos Times</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($teamsMetrics) ?> time(s) - Período: <?= date('d/m', strtotime($dateFrom)) ?> a <?= date('d/m/Y', strtotime($dateTo)) ?></span>
                </h3>
                <div class="card-toolbar">
                    <a href="/teams/dashboard?date_from=<?= $dateFrom ?>&date_to=<?= date('Y-m-d', strtotime($dateTo)) ?>" class="btn btn-sm btn-primary">
                        <i class="ki-duotone ki-chart-simple fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                        Dashboard Completo
                    </a>
                </div>
            </div>
            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Time</th>
                                <th class="min-w-80px text-center">Membros</th>
                                <th class="min-w-100px text-center">Conversas</th>
                                <th class="min-w-100px text-center">Resolvidas</th>
                                <th class="min-w-100px text-center">Taxa Resolução</th>
                                <th class="min-w-120px text-center">TM Resposta</th>
                                <?php if (\App\Helpers\Permission::can('conversion.view')): ?>
                                <th class="min-w-100px text-center">Vendas</th>
                                <th class="min-w-100px text-center">Taxa Conversão</th>
                                <th class="min-w-120px text-end">Faturamento</th>
                                <th class="min-w-100px text-end">Ticket Médio</th>
                                <?php endif; ?>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($teamsMetrics as $index => $team): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-40px me-3" style="background-color: <?= htmlspecialchars($team['team_color'] ?? '#009ef7') ?>20;">
                                            <span class="symbol-label" style="color: <?= htmlspecialchars($team['team_color'] ?? '#009ef7') ?>;">
                                                <i class="ki-duotone ki-people fs-2x">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                    <span class="path5"></span>
                                                </i>
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <a href="/teams/show?id=<?= $team['team_id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= date('Y-m-d', strtotime($dateTo)) ?>" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                                <?= htmlspecialchars($team['team_name']) ?>
                                            </a>
                                            <?php if (!empty($team['leader_name'])): ?>
                                            <span class="text-muted fs-7">Líder: <?= htmlspecialchars($team['leader_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light fs-7"><?= $team['members_count'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-gray-800 fs-6"><?= $team['total_conversations'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-success fs-7"><?= $team['closed_conversations'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="fw-bold text-gray-800 fs-6 mb-1"><?= number_format($team['resolution_rate'] ?? 0, 1) ?>%</span>
                                        <div class="progress h-6px w-100px">
                                            <div class="progress-bar bg-<?= ($team['resolution_rate'] ?? 0) >= 80 ? 'success' : (($team['resolution_rate'] ?? 0) >= 50 ? 'warning' : 'danger') ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= min(100, $team['resolution_rate'] ?? 0) ?>%" 
                                                 aria-valuenow="<?= $team['resolution_rate'] ?? 0 ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-gray-800">
                                        <?= \App\Services\TeamPerformanceService::formatTime($team['avg_first_response_time'] ?? null) ?>
                                    </span>
                                </td>
                                <?php if (\App\Helpers\Permission::can('conversion.view')): ?>
                                <td class="text-center">
                                    <span class="badge badge-light-success"><?= $team['total_orders'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $convRate = $team['conversion_rate_sales'] ?? 0;
                                    $convColor = 'danger';
                                    if ($convRate >= 30) $convColor = 'success';
                                    elseif ($convRate >= 15) $convColor = 'warning';
                                    ?>
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="fw-bold text-gray-800 fs-7 mb-1"><?= number_format($convRate, 1) ?>%</span>
                                        <div class="progress h-5px w-60px">
                                            <div class="progress-bar bg-<?= $convColor ?>" style="width: <?= min(100, $convRate) ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-success">
                                        <?= \App\Services\AgentConversionService::formatCurrency($team['total_revenue'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-gray-800">
                                        <?= \App\Services\AgentConversionService::formatCurrency($team['avg_ticket'] ?? 0) ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td class="text-end">
                                    <a href="/teams/show?id=<?= $team['team_id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= date('Y-m-d', strtotime($dateTo)) ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                        <i class="ki-duotone ki-eye fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Ver Detalhes
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (count($teamsMetrics) >= 10): ?>
                <div class="text-center mt-5">
                    <a href="/teams/dashboard?date_from=<?= $dateFrom ?>&date_to=<?= date('Y-m-d', strtotime($dateTo)) ?>" class="btn btn-link">
                        Ver todos os times →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->
<?php endif; ?>

<?php if (!empty($conversionRanking) && \App\Helpers\Permission::can('conversion.view')): ?>
<!--begin::Row - Conversão WooCommerce-->
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">
                        <i class="ki-duotone ki-chart-line-up fs-2 text-success me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Conversão WooCommerce
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Top vendedores por taxa de conversão</span>
                </h3>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-light-primary me-2" data-bs-toggle="modal" data-bs-target="#modal_wc_sync">
                        <i class="ki-duotone ki-arrows-circle fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Sincronizar Agora
                    </button>
                    <button type="button" class="btn btn-sm btn-light me-2" data-bs-toggle="modal" data-bs-target="#modal_wc_webhook">
                        <i class="ki-duotone ki-setting-2 fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Configurar Webhook
                    </button>
                    <a href="<?= \App\Helpers\Url::to('/agent-conversion', ['date_from' => $dateFrom, 'date_to' => date('Y-m-d', strtotime($dateTo))]) ?>" class="btn btn-sm btn-primary">
                        <i class="ki-duotone ki-chart-simple fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                        Ver Relatório Completo
                    </a>
                </div>
            </div>
            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Vendedor</th>
                                <th class="min-w-80px text-center">Conversas</th>
                                <th class="min-w-80px text-center">Vendas</th>
                                <th class="min-w-120px text-center">Taxa Conversão</th>
                                <th class="min-w-120px text-end">Valor Total</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($conversionRanking as $seller): ?>
                                <?php
                                    $conversionRate = $seller['conversion_rate'] ?? 0;
                                    $progressColor = 'danger';
                                    if ($conversionRate >= 30) {
                                        $progressColor = 'success';
                                    } elseif ($conversionRate >= 15) {
                                        $progressColor = 'warning';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="d-flex flex-column">
                                                <a href="<?= \App\Helpers\Url::to('/agent-conversion/agent', ['id' => $seller['agent_id'], 'date_from' => $dateFrom, 'date_to' => date('Y-m-d', strtotime($dateTo))]) ?>" class="text-gray-800 text-hover-primary fs-6 fw-bold">
                                                    <?= htmlspecialchars($seller['agent_name']) ?>
                                                </a>
                                                <span class="text-muted fs-7">ID WC: <?= $seller['seller_id'] ?? '-' ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-gray-800"><?= $seller['total_conversations'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light-success"><?= $seller['total_orders'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="fw-bold text-gray-800 fs-6 mb-1"><?= number_format($conversionRate, 1) ?>%</span>
                                            <div class="progress h-6px w-100px">
                                                <div class="progress-bar bg-<?= $progressColor ?>" role="progressbar" style="width: <?= min(100, $conversionRate) ?>%" aria-valuenow="<?= $conversionRate ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-success fw-bold">
                                            <?= \App\Services\AgentConversionService::formatCurrency($seller['total_revenue'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= \App\Helpers\Url::to('/agent-conversion/agent', ['id' => $seller['agent_id'], 'date_from' => $dateFrom, 'date_to' => date('Y-m-d', strtotime($dateTo))]) ?>" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm">
                                            <i class="ki-duotone ki-arrow-right fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </a>
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

<?php if (!empty($rankingByRevenue) || !empty($rankingByConversion) || !empty($rankingByTicket)): ?>
<!--begin::Row - Rankings de Vendas-->
<div class="row g-5 g-xl-10 mb-5 mb-xl-10">
    
    <?php if (!empty($rankingByRevenue)): ?>
    <!--begin::Col - Ranking por Faturamento-->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-4 mb-1">
                        <i class="ki-duotone ki-dollar fs-2 text-success me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Top Faturamento
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Maiores faturamentos</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($rankingByRevenue as $index => $seller): ?>
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40px me-3">
                            <span class="symbol-label bg-light-<?= $index === 0 ? 'warning' : ($index === 1 ? 'info' : 'primary') ?>">
                                <span class="fw-bold fs-4 text-<?= $index === 0 ? 'warning' : ($index === 1 ? 'info' : 'primary') ?>">
                                    #<?= $index + 1 ?>
                                </span>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <a href="/agent-conversion/agent?id=<?= $seller['agent_id'] ?>" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                <?= htmlspecialchars($seller['agent_name']) ?>
                            </a>
                            <div class="text-muted fs-7"><?= $seller['total_orders'] ?> vendas</div>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-success fs-5">
                                <?= \App\Services\AgentConversionService::formatCurrency($seller['total_revenue']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    <?php endif; ?>
    
    <?php if (!empty($rankingByConversion)): ?>
    <!--begin::Col - Ranking por Taxa de Conversão-->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-4 mb-1">
                        <i class="ki-duotone ki-chart-line-up fs-2 text-primary me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Top Conversão
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Melhores taxas de conversão</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($rankingByConversion as $index => $seller): ?>
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40px me-3">
                            <span class="symbol-label bg-light-<?= $index === 0 ? 'warning' : ($index === 1 ? 'info' : 'primary') ?>">
                                <span class="fw-bold fs-4 text-<?= $index === 0 ? 'warning' : ($index === 1 ? 'info' : 'primary') ?>">
                                    #<?= $index + 1 ?>
                                </span>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <a href="/agent-conversion/agent?id=<?= $seller['agent_id'] ?>" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                <?= htmlspecialchars($seller['agent_name']) ?>
                            </a>
                            <div class="text-muted fs-7"><?= $seller['total_conversations'] ?> conversas → <?= $seller['total_orders'] ?> vendas</div>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-primary fs-4">
                                <?= number_format($seller['conversion_rate'], 1) ?>%
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    <?php endif; ?>
    
    <?php if (!empty($rankingByTicket)): ?>
    <!--begin::Col - Ranking por Ticket Médio-->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-4 mb-1">
                        <i class="ki-duotone ki-chart-simple fs-2 text-warning me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                        </i>
                        Top Ticket Médio
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Maiores tickets médios</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($rankingByTicket as $index => $seller): ?>
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40px me-3">
                            <span class="symbol-label bg-light-<?= $index === 0 ? 'warning' : ($index === 1 ? 'info' : 'primary') ?>">
                                <span class="fw-bold fs-4 text-<?= $index === 0 ? 'warning' : ($index === 1 ? 'info' : 'primary') ?>">
                                    #<?= $index + 1 ?>
                                </span>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <a href="/agent-conversion/agent?id=<?= $seller['agent_id'] ?>" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                <?= htmlspecialchars($seller['agent_name']) ?>
                            </a>
                            <div class="text-muted fs-7"><?= $seller['total_orders'] ?> vendas</div>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-warning fs-5">
                                <?= \App\Services\AgentConversionService::formatCurrency($seller['avg_ticket']) ?>
                            </span>
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
<?php endif; ?>

<!--begin::Row - Gráficos-->
<div class="row g-5 mb-5">
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

<!-- Modal: Configurar Webhook WooCommerce -->
<div class="modal fade" id="modal_wc_webhook" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">
                    <i class="ki-duotone ki-setting-2 fs-2 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Configurar Webhook WooCommerce
                </h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div class="alert alert-info d-flex align-items-center mb-5">
                    <i class="ki-duotone ki-information-5 fs-2x text-info me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Configure o Webhook no WooCommerce</h4>
                        <span>Vá em <strong>WooCommerce → Configurações → Avançado → Webhooks</strong> e adicione um novo webhook com a URL abaixo.</span>
                    </div>
                </div>
                
                <div class="mb-7">
                    <label class="form-label fw-bold fs-6 mb-2">URL do Webhook</label>
                    <div class="input-group">
                        <input type="text" id="webhook_url" class="form-control form-control-solid" 
                               value="<?php echo rtrim(\App\Helpers\Url::to('/'), '/'); ?>/webhooks/woocommerce" readonly>
                        <button class="btn btn-primary" type="button" onclick="copyWebhookUrl()">
                            <i class="ki-duotone ki-copy fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Copiar
                        </button>
                    </div>
                    <div class="form-text">Esta é a URL que deve ser configurada no WooCommerce</div>
                </div>
                
                <div class="separator separator-dashed my-7"></div>
                
                <div class="mb-5">
                    <h4 class="fw-bold mb-4">Configurações do Webhook</h4>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="fw-semibold">Nome:</label>
                            <p class="text-gray-600">Chat System - Pedidos</p>
                        </div>
                        <div class="col-6">
                            <label class="fw-semibold">Status:</label>
                            <p class="text-gray-600">Ativo</p>
                        </div>
                        <div class="col-6">
                            <label class="fw-semibold">Tópico:</label>
                            <p class="text-gray-600">Pedido criado / Pedido atualizado</p>
                        </div>
                        <div class="col-6">
                            <label class="fw-semibold">API Version:</label>
                            <p class="text-gray-600">WP REST API Integration v3</p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="ki-duotone ki-shield-tick fs-2x text-warning me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <span><strong>Importante:</strong> Configure webhooks para os eventos <strong>"Order created"</strong> e <strong>"Order updated"</strong> para receber atualizações em tempo real.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Sincronizar Pedidos WooCommerce -->
<div class="modal fade" id="modal_wc_sync" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">
                    <i class="ki-duotone ki-arrows-circle fs-2 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Sincronizar Pedidos WooCommerce
                </h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div class="alert alert-info d-flex align-items-center mb-7">
                    <i class="ki-duotone ki-information-5 fs-2x text-info me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Sincronização Manual</h4>
                        <span>Esta sincronização irá buscar os pedidos mais recentes do WooCommerce e atualizar o cache local.</span>
                    </div>
                </div>
                
                <div class="mb-7">
                    <label class="form-label fw-bold fs-6 required">Limite de Pedidos</label>
                    <input type="number" class="form-control form-control-solid" id="orders_limit" 
                           value="100" min="1" max="500" placeholder="Ex: 100">
                    <div class="form-text">Quantidade máxima de pedidos a sincronizar (máx: 500)</div>
                </div>
                
                <div class="mb-7">
                    <label class="form-label fw-bold fs-6 required">Período (dias)</label>
                    <input type="number" class="form-control form-control-solid" id="days_back" 
                           value="7" min="1" max="90" placeholder="Ex: 7">
                    <div class="form-text">Buscar pedidos dos últimos X dias (máx: 90 dias)</div>
                </div>
                
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="ki-duotone ki-information-4 fs-2x text-warning me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <span><strong>Atenção:</strong> Sincronizações com muitos pedidos podem levar alguns minutos. O sistema irá processar todas as integrações WooCommerce ativas.</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_sync_wc" onclick="syncWooCommerceOrders()">
                    <i class="ki-duotone ki-arrows-circle fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Sincronizar
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 

// Definir URLs antes da string JavaScript
$chartDataUrl = \App\Helpers\Url::to('/dashboard/chart-data');
$dashboardUrl = \App\Helpers\Url::to('/dashboard');
$exportUrl = \App\Helpers\Url::to('/dashboard/export');

// URLs codificadas para uso no JS
$chartDataUrlJson = json_encode($chartDataUrl);
$dashboardUrlJson = json_encode($dashboardUrl);
$exportUrlJson = json_encode($exportUrl);

// Adicionar Chart.js via CDN
$scripts = <<<SCRIPT
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
    
    const url = new URL({$chartDataUrlJson}, window.location.origin);
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
    
    window.location.href = {$dashboardUrlJson} + '?date_from=' + dateFrom + '&date_to=' + dateTo;
}

// Função para exportar relatório
function exportReport(format) {
    const dateFrom = document.getElementById("kt_dashboard_date_from").value;
    const dateTo = document.getElementById("kt_dashboard_date_to").value;
    
    const url = new URL({$exportUrlJson}, window.location.origin);
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

// Função para copiar URL do webhook
function copyWebhookUrl() {
    const webhookUrl = document.getElementById("webhook_url").value;
    navigator.clipboard.writeText(webhookUrl).then(() => {
        Swal.fire({
            icon: "success",
            title: "Copiado!",
            text: "URL do webhook copiada para a área de transferência",
            timer: 2000,
            showConfirmButton: false
        });
    });
}

// Função para sincronizar pedidos WooCommerce
function syncWooCommerceOrders() {
    const ordersLimit = document.getElementById("orders_limit").value;
    const daysBack = document.getElementById("days_back").value;
    const btnSync = document.getElementById("btn_sync_wc");
    
    // Validação
    if (!ordersLimit || ordersLimit < 1 || ordersLimit > 500) {
        Swal.fire({
            icon: "warning",
            title: "Atenção",
            text: "Limite de pedidos deve ser entre 1 e 500"
        });
        return;
    }
    
    if (!daysBack || daysBack < 1 || daysBack > 90) {
        Swal.fire({
            icon: "warning",
            title: "Atenção",
            text: "Período deve ser entre 1 e 90 dias"
        });
        return;
    }
    
    // Desabilitar botão e mostrar loading
    btnSync.disabled = true;
    btnSync.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Sincronizando...";
    
    // Fazer requisição
    fetch("/api/woocommerce/sync-orders", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            orders_limit: parseInt(ordersLimit),
            days_back: parseInt(daysBack)
        })
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            return {
                success: false,
                message: "Resposta inválida do servidor",
                details: text
            };
        }
    })
    .then(data => {
        btnSync.disabled = false;
        btnSync.innerHTML = "<i class=\"ki-duotone ki-arrows-circle fs-2\"><span class=\"path1\"></span><span class=\"path2\"></span></i> Sincronizar";
        
        if (data.success) {
            Swal.fire({
                icon: "success",
                title: "Sucesso!",
                html: `<div class="text-start">
                    <p><strong>Sincronização concluída:</strong></p>
                    <ul class="mb-0">
                        <li>Integrações processadas: \${data.integrations_processed || 0}</li>
                        <li>Pedidos processados: \${data.orders_processed || 0}</li>
                        <li>Novos contatos: \${data.new_contacts || 0}</li>
                    </ul>
                </div>`,
                confirmButtonText: "Recarregar Dashboard"
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
            
            // Fechar modal
            bootstrap.Modal.getInstance(document.getElementById("modal_wc_sync")).hide();
        } else {
            Swal.fire({
                icon: "error",
                title: "Erro",
                html: (data.message || "Erro ao sincronizar pedidos") + (data.details ? "<br><small>" + data.details + "</small>" : "")
            });
        }
    })
    .catch(error => {
        btnSync.disabled = false;
        btnSync.innerHTML = "<i class=\"ki-duotone ki-arrows-circle fs-2\"><span class=\"path1\"></span><span class=\"path2\"></span></i> Sincronizar";
        
        Swal.fire({
            icon: "error",
            title: "Erro",
            text: "Erro na comunicação com o servidor"
        });
        console.error("Erro:", error);
    });
}
</script>
SCRIPT;
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
