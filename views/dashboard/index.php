<?php
$layout = 'layouts.metronic.app';
$title = 'Dashboard';

// Content
ob_start();
?>
<!--begin::Card - Filtros de Período e Agentes-->
<div class="card mb-5">
    <div class="card-body">
        <!-- Filtros Rápidos -->
        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <label class="fw-semibold fs-6 mb-0">Filtros Rápidos:</label>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodDashboard('today')">Hoje</button>
                <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodDashboard('yesterday')">Ontem</button>
                <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodDashboard('this_week')">Esta Semana</button>
                <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodDashboard('last_week')">Sem. Passada</button>
                <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodDashboard('this_month')">Este Mês</button>
                <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodDashboard('last_month')">Mês Passado</button>
            </div>
        </div>
        
        <!-- Filtro Personalizado -->
        <div class="d-flex flex-wrap align-items-center gap-3">
            <label class="fw-semibold fs-6 mb-0">Período:</label>
            <input type="date" id="kt_dashboard_date_from" class="form-control form-control-solid" 
                   value="<?= htmlspecialchars($dateFrom) ?>" style="width: 150px;" />
            <span class="text-muted">até</span>
            <input type="date" id="kt_dashboard_date_to" class="form-control form-control-solid" 
                   value="<?= htmlspecialchars(date('Y-m-d', strtotime($dateTo))) ?>" style="width: 150px;" />
            
            <div class="separator separator-dashed mx-3 d-none d-md-block" style="height: 30px;"></div>
            
            <label class="fw-semibold fs-6 mb-0">Agentes:</label>
            <select id="kt_dashboard_agents" class="form-select form-select-solid" 
                    multiple="multiple" data-control="select2" 
                    data-placeholder="Selecione agentes..." 
                    data-allow-clear="true"
                    style="min-width: 250px;">
                <?php 
                // Se não há filtro na URL, todos os agentes vêm selecionados por padrão
                $hasAgentsParam = isset($_GET['agents']);
                $selectedAgents = $hasAgentsParam ? explode(',', $_GET['agents']) : [];
                foreach ($agentsList ?? [] as $agent): 
                    // Seleciona se: não tem param (padrão = todos) ou está na lista explícita
                    $isSelected = (!$hasAgentsParam || in_array($agent['id'], $selectedAgents)) ? 'selected' : '';
                ?>
                <option value="<?= $agent['id'] ?>" <?= $isSelected ?>><?= htmlspecialchars($agent['name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="button" class="btn btn-sm btn-primary" onclick="loadDashboard()">
                <i class="ki-duotone ki-magnifier fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Filtrar
            </button>
            
            <?php if ($hasAgentsParam && !empty($selectedAgents)): ?>
            <button type="button" class="btn btn-sm btn-light-danger" onclick="clearAgentFilter()">
                <i class="ki-duotone ki-cross fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Limpar Filtro
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<!--end::Card - Filtros de Período e Agentes-->

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

<!--begin::Row - Estatísticas de Ligações API4Com-->
<?php if (isset($callStats)): ?>
<div class="row g-5 mb-5">
    <!--begin::Col - Total de Ligações-->
    <div class="col-xl-3">
        <div class="card bg-light-primary h-100">
            <div class="card-body">
                <i class="ki-duotone ki-phone fs-2x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($callStats['total_calls'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Total de Ligações</div>
                <div class="text-muted fs-7 mt-1">Período selecionado</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Ligações Atendidas-->
    <div class="col-xl-3">
        <div class="card bg-light-success h-100">
            <div class="card-body">
                <i class="ki-duotone ki-check-circle fs-2x text-success mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($callStats['answered_calls'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Ligações Atendidas</div>
                <div class="text-muted fs-7 mt-1"><?= number_format($callStats['success_rate'] ?? 0, 1) ?>% de sucesso</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Ligações Falharam-->
    <div class="col-xl-3">
        <div class="card bg-light-danger h-100">
            <div class="card-body">
                <i class="ki-duotone ki-cross-circle fs-2x text-danger mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($callStats['failed_calls'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Ligações Falharam</div>
                <div class="text-muted fs-7 mt-1">Não atendidas/erro</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Duração Média-->
    <div class="col-xl-3">
        <div class="card bg-light-info h-100">
            <div class="card-body">
                <i class="ki-duotone ki-time fs-2x text-info mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2">
                    <?= \App\Models\Api4ComCall::formatDuration($callStats['avg_duration'] ?? 0) ?>
                </div>
                <div class="fw-semibold text-gray-500">Duração Média</div>
                <div class="text-muted fs-7 mt-1">
                    Total: <?= \App\Models\Api4ComCall::formatDuration($callStats['total_duration'] ?? 0) ?>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>

<!--begin::Card - Ligações Recentes-->
<?php if (!empty($recentCalls)): ?>
<div class="card mb-5">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1">
                <i class="ki-duotone ki-phone text-primary me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>Ligações Recentes
            </span>
            <span class="text-muted mt-1 fw-semibold fs-7"><?= $callStats['unique_contacts'] ?? 0 ?> contatos diferentes</span>
        </h3>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/api4com-calls') ?>" class="btn btn-sm btn-light-primary">
                Ver Todas
            </a>
        </div>
    </div>
    <div class="card-body py-3">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th class="min-w-150px">Contato</th>
                        <th class="min-w-100px">Agente</th>
                        <th class="min-w-80px">Status</th>
                        <th class="min-w-80px">Duração</th>
                        <th class="min-w-100px">Data/Hora</th>
                        <th class="min-w-100px">Gravação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCalls as $call): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-35px me-3">
                                    <span class="symbol-label bg-light-primary text-primary">
                                        <i class="ki-duotone ki-profile-user">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-800 fw-bold d-block"><?= htmlspecialchars($call['contact_name'] ?? 'Desconhecido') ?></span>
                                    <span class="text-muted fs-7"><?= htmlspecialchars($call['to_number'] ?? $call['contact_phone'] ?? '') ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-gray-800"><?= htmlspecialchars($call['agent_name'] ?? '-') ?></span>
                        </td>
                        <td>
                            <?php 
                            $statusColor = \App\Models\Api4ComCall::getStatusColor($call['status'] ?? 'unknown');
                            $statusLabel = \App\Models\Api4ComCall::getStatusLabel($call['status'] ?? 'unknown');
                            ?>
                            <span class="badge badge-light-<?= $statusColor ?>"><?= $statusLabel ?></span>
                        </td>
                        <td>
                            <span class="text-gray-600"><?= \App\Models\Api4ComCall::formatDuration((int)($call['duration'] ?? 0)) ?></span>
                        </td>
                        <td>
                            <span class="text-gray-600 fs-7"><?= date('d/m H:i', strtotime($call['created_at'])) ?></span>
                        </td>
                        <td>
                            <?php if (!empty($call['recording_url'])): ?>
                                <button type="button" class="btn btn-sm btn-light-primary px-3" 
                                        onclick="playRecording('<?= htmlspecialchars($call['recording_url']) ?>')" 
                                        title="Ouvir gravação">
                                    <i class="fas fa-play me-1"></i> Ouvir
                                </button>
                            <?php else: ?>
                                <span class="text-muted fs-8">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Card - Ligações Recentes-->
<?php endif; ?>
<!--end::Row - Estatísticas de Ligações API4Com-->

<!--begin::Row - Análise de Performance de Chamadas-->
<?php if (!empty($callAnalysisStats) && $callAnalysisStats['total_analyzed'] > 0): ?>
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">
                        <i class="ki-duotone ki-chart-line-star text-primary me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Análise de Performance em Chamadas
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= $callAnalysisStats['total_analyzed'] ?> chamadas analisadas</span>
                </h3>
                <div class="card-toolbar">
                    <span class="badge badge-light-<?= \App\Models\Api4ComCallAnalysis::getScoreColor($callAnalysisStats['avg_overall_score']) ?> fs-6">
                        Score Médio: <?= number_format($callAnalysisStats['avg_overall_score'], 1) ?>/5.0
                    </span>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="row g-4 mb-5">
                    <!-- Resultados das Chamadas -->
                    <div class="col-xl-3 col-md-6">
                        <div class="d-flex align-items-center bg-light-success rounded p-3">
                            <span class="bullet bullet-vertical h-40px bg-success me-3"></span>
                            <div>
                                <div class="text-gray-900 fw-bold fs-3"><?= $callAnalysisStats['outcomes']['positive'] ?></div>
                                <div class="text-muted fw-semibold">Positivas</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="d-flex align-items-center bg-light-danger rounded p-3">
                            <span class="bullet bullet-vertical h-40px bg-danger me-3"></span>
                            <div>
                                <div class="text-gray-900 fw-bold fs-3"><?= $callAnalysisStats['outcomes']['negative'] ?></div>
                                <div class="text-muted fw-semibold">Negativas</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="d-flex align-items-center bg-light-warning rounded p-3">
                            <span class="bullet bullet-vertical h-40px bg-warning me-3"></span>
                            <div>
                                <div class="text-gray-900 fw-bold fs-3"><?= $callAnalysisStats['outcomes']['followup_needed'] ?></div>
                                <div class="text-muted fw-semibold">Requer Follow-up</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="d-flex align-items-center bg-light-secondary rounded p-3">
                            <span class="bullet bullet-vertical h-40px bg-secondary me-3"></span>
                            <div>
                                <div class="text-gray-900 fw-bold fs-3"><?= $callAnalysisStats['outcomes']['neutral'] ?></div>
                                <div class="text-muted fw-semibold">Neutras</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dimensões de Avaliação -->
                <h6 class="text-gray-800 fw-bold mb-3">
                    <i class="ki-duotone ki-chart-simple-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    Médias por Dimensão
                </h6>
                <div class="row g-3">
                    <?php 
                    $dimensionLabels = \App\Models\Api4ComCallAnalysis::getDimensionLabels();
                    foreach ($callAnalysisStats['dimensions'] as $key => $score): 
                        $color = \App\Models\Api4ComCallAnalysis::getScoreColor($score);
                    ?>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="border rounded p-2 text-center">
                            <div class="fs-4 fw-bold text-<?= $color ?>"><?= number_format($score, 1) ?></div>
                            <div class="text-muted fs-8"><?= $dimensionLabels[$key] ?? ucfirst($key) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Análises Recentes -->
<?php if (!empty($recentCallAnalyses)): ?>
<div class="card mb-5">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1">
                <i class="ki-duotone ki-document text-primary me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Análises Recentes de Chamadas
            </span>
        </h3>
    </div>
    <div class="card-body py-3">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th class="min-w-120px">Agente</th>
                        <th class="min-w-100px">Número</th>
                        <th class="min-w-80px">Score</th>
                        <th class="min-w-100px">Resultado</th>
                        <th class="min-w-150px">Resumo</th>
                        <th class="min-w-80px">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCallAnalyses as $analysis): ?>
                    <tr>
                        <td>
                            <span class="text-gray-800 fw-bold"><?= htmlspecialchars($analysis['agent_name'] ?? 'N/A') ?></span>
                        </td>
                        <td>
                            <span class="text-gray-600 fs-7"><?= htmlspecialchars($analysis['to_number'] ?? '') ?></span>
                        </td>
                        <td>
                            <span class="badge badge-light-<?= \App\Models\Api4ComCallAnalysis::getScoreColor((float)$analysis['overall_score']) ?> fs-6">
                                <?= number_format((float)$analysis['overall_score'], 1) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-light-<?= \App\Models\Api4ComCallAnalysis::getOutcomeColor($analysis['call_outcome'] ?? 'neutral') ?>">
                                <?= \App\Models\Api4ComCallAnalysis::getOutcomeLabel($analysis['call_outcome'] ?? 'neutral') ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-gray-600 fs-7">
                                <?= htmlspecialchars(substr($analysis['summary'] ?? '', 0, 80)) ?>...
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-icon btn-light-info" 
                                    onclick="showCallAnalysis(<?= $analysis['id'] ?>)" 
                                    title="Ver análise completa">
                                <i class="ki-duotone ki-eye fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </button>
                            <?php if (!empty($analysis['recording_url'])): ?>
                            <button type="button" class="btn btn-sm btn-light-primary px-2" 
                                    onclick="playRecording('<?= htmlspecialchars($analysis['recording_url']) ?>')" 
                                    title="Ouvir gravação">
                                <i class="fas fa-play"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
<!--end::Row - Análise de Performance de Chamadas-->

<!--begin::Row - Metas-->
<?php if (!empty($goalsSummary) && $goalsSummary['total_goals'] > 0): ?>
<div class="card mb-5">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold fs-3 mb-1">
                <i class="bi bi-flag-fill text-primary me-2"></i>Minhas Metas
            </span>
            <span class="text-muted mt-1 fw-semibold fs-7"><?= $goalsSummary['total_goals'] ?> metas ativas</span>
        </h3>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/goals/dashboard') ?>" class="btn btn-sm btn-light-primary">
                Ver Todas as Metas
            </a>
        </div>
    </div>
    <div class="card-body py-3">
        <div class="row g-3">
            <!-- Card: Total -->
            <div class="col-md-3">
                <div class="bg-light-primary p-4 rounded-3">
                    <i class="bi bi-flag-fill fs-2x text-primary mb-2"></i>
                    <div class="text-gray-900 fw-bold fs-1"><?= $goalsSummary['total_goals'] ?></div>
                    <div class="text-muted fs-6">Total de Metas</div>
                </div>
            </div>
            
            <!-- Card: Atingidas -->
            <div class="col-md-3">
                <div class="bg-light-success p-4 rounded-3">
                    <i class="bi bi-trophy-fill fs-2x text-success mb-2"></i>
                    <div class="text-gray-900 fw-bold fs-1"><?= $goalsSummary['achieved'] ?></div>
                    <div class="text-muted fs-6">Atingidas</div>
                </div>
            </div>
            
            <!-- Card: Em Progresso -->
            <div class="col-md-3">
                <div class="bg-light-info p-4 rounded-3">
                    <i class="bi bi-graph-up-arrow fs-2x text-info mb-2"></i>
                    <div class="text-gray-900 fw-bold fs-1"><?= $goalsSummary['in_progress'] ?></div>
                    <div class="text-muted fs-6">Em Progresso</div>
                </div>
            </div>
            
            <!-- Card: Em Risco -->
            <div class="col-md-3">
                <div class="bg-light-danger p-4 rounded-3">
                    <i class="bi bi-exclamation-triangle-fill fs-2x text-danger mb-2"></i>
                    <div class="text-gray-900 fw-bold fs-1"><?= $goalsSummary['at_risk'] ?></div>
                    <div class="text-muted fs-6">Em Risco</div>
                </div>
            </div>
        </div>
        
        <?php 
        // Mostrar as metas individuais do usuário
        $individualGoals = $goalsSummary['goals_by_level']['individual'] ?? [];
        if (!empty($individualGoals) && count($individualGoals) > 0): 
        ?>
        <div class="separator my-5"></div>
        <h4 class="fw-bold mb-4">Metas Individuais</h4>
        <div class="row g-3">
            <?php foreach (array_slice($individualGoals, 0, 4) as $goal): 
                $progress = $goal['progress'] ?? null;
                $percentage = $progress ? (float)$progress['percentage'] : 0;
                $currentValue = $progress ? (float)$progress['current_value'] : 0;
                
                if ($percentage >= 100) {
                    $progressColor = 'success';
                } elseif ($percentage >= 75) {
                    $progressColor = 'primary';
                } elseif ($percentage >= 50) {
                    $progressColor = 'warning';
                } else {
                    $progressColor = 'danger';
                }
            ?>
            <div class="col-md-6">
                <div class="card card-flush border">
                    <div class="card-body p-5">
                        <div class="d-flex flex-stack mb-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-bullseye fs-2x text-<?= $progressColor ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <a href="<?= \App\Helpers\Url::to('/goals/show?id=' . $goal['id']) ?>" 
                                       class="text-gray-800 text-hover-primary fw-bold fs-6 d-block">
                                        <?= htmlspecialchars($goal['name']) ?>
                                    </a>
                                    <span class="text-muted fs-7">
                                        <?= \App\Models\Goal::TYPES[$goal['type']]['label'] ?? $goal['type'] ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="text-gray-800 fw-bold fs-4"><?= number_format($percentage, 0) ?>%</span>
                            </div>
                        </div>
                        <div class="progress h-8px mb-2">
                            <div class="progress-bar bg-<?= $progressColor ?>" role="progressbar" 
                                 style="width: <?= min($percentage, 100) ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between fs-7 text-muted">
                            <span><?= \App\Models\Goal::formatValue($goal['type'], $currentValue) ?></span>
                            <span><?= \App\Models\Goal::formatValue($goal['type'], $goal['target_value']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<!--end::Row - Metas-->

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
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Top Agentes</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Melhores desempenhos no período</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <?php if (!empty($topAgents)): ?>
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
                <?php else: ?>
                    <div class="text-muted fs-7">Sem dados de agentes para o período.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
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
<?php
    $hasAgentsMetrics = !empty($allAgentsMetrics);
    // Buscar configurações de SLA dinâmicas
    $slaSettings = \App\Services\ConversationSettingsService::getSettings()['sla'] ?? [];
    $slaFirstResponse = $slaSettings['first_response_time'] ?? 15; // minutos
    $slaOngoingResponse = $slaSettings['ongoing_response_time'] ?? $slaFirstResponse; // SLA para respostas contínuas
?>
<!--begin::Desempenho dos Agentes - Visual Compacto-->
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Desempenho dos Agentes</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Visão geral de performance e SLA</span>
                </h3>
                <div class="card-toolbar">
                    <span class="badge badge-light-primary fs-8">
                        <i class="ki-duotone ki-timer fs-7 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        SLA: <?= $slaFirstResponse ?>min (1ª) | <?= $slaOngoingResponse ?>min (resp)
                    </span>
                </div>
            </div>
            <div class="card-body pt-0">
                <?php if ($hasAgentsMetrics): ?>
                
                <!-- Tabela Compacta -->
                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                        <thead>
                            <tr class="fw-bold text-muted bg-light">
                                <th class="ps-4 rounded-start min-w-200px">Agente</th>
                                <th class="text-center min-w-100px">Conversas</th>
                                <th class="text-center min-w-120px">SLA 1ª Resp</th>
                                <th class="text-center min-w-120px">SLA Respostas</th>
                                <th class="text-center min-w-100px">Tempo Médio</th>
                                <th class="text-center min-w-80px">Resolução</th>
                                <th class="text-center pe-4 rounded-end min-w-80px">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allAgentsMetrics as $agent): 
                                // Status do agente
                                $statusColors = ['online' => 'success', 'busy' => 'warning', 'away' => 'info', 'offline' => 'gray-400'];
                                $status = $agent['availability_status'] ?? 'offline';
                                $statusColor = $statusColors[$status] ?? 'gray-400';
                                
                                // Dados SLA 1ª Resposta
                                $sla1Rate = $agent['sla_first_response_rate'] ?? 0;
                                $sla1Within = $agent['first_response_within_sla'] ?? 0;
                                $sla1Total = $agent['total_conversations_with_contact'] ?? $agent['total_conversations'] ?? 0;
                                $sla1Color = $sla1Total == 0 ? 'secondary' : ($sla1Rate >= 80 ? 'success' : ($sla1Rate >= 50 ? 'warning' : 'danger'));
                                
                                // Dados SLA Respostas
                                $sla2Rate = $agent['sla_response_rate'] ?? 0;
                                $sla2Within = $agent['responses_within_sla'] ?? 0;
                                $sla2Total = $agent['total_responses'] ?? 0;
                                $sla2Color = $sla2Total == 0 ? 'secondary' : ($sla2Rate >= 80 ? 'success' : ($sla2Rate >= 50 ? 'warning' : 'danger'));
                                
                                // Tempo médio de resposta
                                $avgRespMin = $agent['avg_response_minutes'] ?? 0;
                                $avgRespSec = $agent['avg_response_seconds'] ?? 0;
                                $avgRespFormatted = '-';
                                if ($avgRespSec > 0) {
                                    if ($avgRespSec < 60) $avgRespFormatted = number_format($avgRespSec, 0) . 's';
                                    elseif ($avgRespMin < 60) $avgRespFormatted = number_format($avgRespMin, 1) . 'min';
                                    else $avgRespFormatted = number_format($avgRespMin / 60, 1) . 'h';
                                }
                                
                                // Taxa de resolução
                                $resolutionRate = $agent['resolution_rate'] ?? 0;
                                $resColor = $resolutionRate >= 70 ? 'success' : ($resolutionRate >= 40 ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <!-- Agente -->
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-40px me-3">
                                            <?php if (!empty($agent['agent_avatar'])): ?>
                                                <img src="<?= htmlspecialchars($agent['agent_avatar']) ?>" alt="" class="symbol-label" />
                                            <?php else: ?>
                                                <div class="symbol-label fs-5 fw-semibold text-primary bg-light-primary">
                                                    <?= mb_substr(htmlspecialchars($agent['agent_name'] ?? 'A'), 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="symbol-badge bg-<?= $statusColor ?> start-100 top-100 border-4 h-10px w-10px ms-n2 mt-n2"></div>
                                        </div>
                                        <div>
                                            <a href="<?= \App\Helpers\Url::to('/agent-performance/agent?id=' . $agent['agent_id']) ?>" 
                                               class="text-gray-800 fw-bold text-hover-primary fs-6">
                                                <?= htmlspecialchars($agent['agent_name']) ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Conversas -->
                                <td class="text-center">
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="fw-bold fs-5 text-gray-800"><?= number_format($agent['total_conversations'] ?? 0) ?></span>
                                        <div class="d-flex gap-2 mt-1">
                                            <span class="badge badge-light-warning badge-sm" title="Em aberto">
                                                <?= $agent['open_conversations'] ?? 0 ?>
                                            </span>
                                            <span class="badge badge-light-success badge-sm" title="Fechadas">
                                                <?= $agent['closed_conversations'] ?? 0 ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- SLA 1ª Resposta -->
                                <td class="text-center">
                                    <?php if ($sla1Total > 0): ?>
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="badge badge-light-<?= $sla1Color ?> fs-7 fw-bold px-3 py-2">
                                            <?= number_format($sla1Rate, 0) ?>%
                                        </span>
                                        <span class="text-muted fs-8 mt-1"><?= $sla1Within ?>/<?= $sla1Total ?></span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted fs-7">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- SLA Respostas -->
                                <td class="text-center">
                                    <?php if ($sla2Total > 0): ?>
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="badge badge-light-<?= $sla2Color ?> fs-7 fw-bold px-3 py-2">
                                            <?= number_format($sla2Rate, 0) ?>%
                                        </span>
                                        <span class="text-muted fs-8 mt-1"><?= $sla2Within ?>/<?= $sla2Total ?></span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted fs-7">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Tempo Médio -->
                                <td class="text-center">
                                    <span class="fw-bold text-gray-800"><?= $avgRespFormatted ?></span>
                                </td>
                                
                                <!-- Resolução -->
                                <td class="text-center">
                                    <span class="badge badge-light-<?= $resColor ?> fs-8">
                                        <?= number_format($resolutionRate, 0) ?>%
                                    </span>
                                </td>
                                
                                <!-- Ações -->
                                <td class="text-center pe-4">
                                    <a href="<?= \App\Helpers\Url::to('/agent-performance/agent?id=' . $agent['agent_id']) ?>" 
                                       class="btn btn-sm btn-icon btn-light-primary" 
                                       title="Ver detalhes">
                                        <i class="ki-duotone ki-eye fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Legenda -->
                <div class="d-flex flex-wrap gap-4 mt-4 pt-4 border-top">
                    <div class="d-flex align-items-center">
                        <span class="bullet bullet-dot bg-success me-2 h-8px w-8px"></span>
                        <span class="text-muted fs-8">Online</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="bullet bullet-dot bg-warning me-2 h-8px w-8px"></span>
                        <span class="text-muted fs-8">Ocupado</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="bullet bullet-dot bg-info me-2 h-8px w-8px"></span>
                        <span class="text-muted fs-8">Ausente</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="bullet bullet-dot bg-gray-400 me-2 h-8px w-8px"></span>
                        <span class="text-muted fs-8">Offline</span>
                    </div>
                    <div class="border-start ps-4 d-flex align-items-center">
                        <span class="badge badge-light-success badge-sm me-2">80%+</span>
                        <span class="text-muted fs-8">Excelente</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-light-warning badge-sm me-2">50-79%</span>
                        <span class="text-muted fs-8">Regular</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-light-danger badge-sm me-2">&lt;50%</span>
                        <span class="text-muted fs-8">Crítico</span>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="text-center py-10">
                    <i class="ki-duotone ki-people fs-4x text-gray-400 mb-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    <div class="text-muted fs-6">Sem métricas de agentes para o período selecionado</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Desempenho dos Agentes-->

<!--begin::Row - Métricas de Times-->
<?php $teamsMetricsCount = is_array($teamsMetrics) ? count($teamsMetrics) : 0; ?>
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Performance dos Times</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= $teamsMetricsCount ?> time(s) - Período: <?= date('d/m', strtotime($dateFrom)) ?> a <?= date('d/m/Y', strtotime($dateTo)) ?></span>
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
                <?php if (!empty($teamsMetrics)): ?>
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
                    
                    <?php if ($teamsMetricsCount >= 10): ?>
                    <div class="text-center mt-5">
                        <a href="/teams/dashboard?date_from=<?= $dateFrom ?>&date_to=<?= date('Y-m-d', strtotime($dateTo)) ?>" class="btn btn-link">
                            Ver todos os times →
                        </a>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-muted fs-7">Sem dados de times para o período.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->

<?php if (\App\Helpers\Permission::can('conversion.view')): ?>
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
                <?php if (!empty($conversionRanking)): ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-180px">Vendedor</th>
                                    <th class="min-w-100px text-center">
                                        Conversas
                                        <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" title="Total / Iniciadas pelo agente / Iniciadas pelo cliente"></i>
                                    </th>
                                    <th class="min-w-60px text-center">Vendas</th>
                                    <th class="min-w-140px text-center">
                                        Taxa Conversão
                                        <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" title="Geral (todas) / Apenas clientes que chamaram"></i>
                                    </th>
                                    <th class="min-w-100px text-end">Valor Total</th>
                                    <th class="text-end min-w-70px">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold">
                                <?php foreach ($conversionRanking as $seller): ?>
                                    <?php
                                        $conversionRate = $seller['conversion_rate'] ?? 0;
                                        $conversionRateClientOnly = $seller['conversion_rate_client_only'] ?? 0;
                                        $agentInitiated = $seller['conversations_agent_initiated'] ?? 0;
                                        $clientInitiated = $seller['conversations_client_initiated'] ?? 0;
                                        
                                        $progressColor = 'danger';
                                        if ($conversionRate >= 30) {
                                            $progressColor = 'success';
                                        } elseif ($conversionRate >= 15) {
                                            $progressColor = 'warning';
                                        }
                                        
                                        $progressColorClient = 'danger';
                                        if ($conversionRateClientOnly >= 30) {
                                            $progressColorClient = 'success';
                                        } elseif ($conversionRateClientOnly >= 15) {
                                            $progressColorClient = 'warning';
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
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="fw-bold text-gray-800"><?= $seller['total_conversations'] ?? 0 ?></span>
                                                <div class="d-flex gap-2 fs-8 mt-1">
                                                    <span class="badge badge-light-primary" title="Iniciadas pelo agente">
                                                        <i class="bi bi-person-fill fs-9"></i> <?= $agentInitiated ?>
                                                    </span>
                                                    <span class="badge badge-light-info" title="Iniciadas pelo cliente">
                                                        <i class="bi bi-chat-fill fs-9"></i> <?= $clientInitiated ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-light-success fs-6"><?= $seller['total_orders'] ?? 0 ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <!-- Taxa Geral -->
                                                <div class="d-flex align-items-center gap-2 w-100">
                                                    <span class="fs-9 text-muted" style="width: 40px;">Geral:</span>
                                                    <span class="fw-bold text-gray-800"><?= number_format($conversionRate, 1) ?>%</span>
                                                    <div class="progress h-4px flex-grow-1">
                                                        <div class="progress-bar bg-<?= $progressColor ?>" style="width: <?= min(100, $conversionRate) ?>%"></div>
                                                    </div>
                                                </div>
                                                <!-- Taxa só Clientes -->
                                                <div class="d-flex align-items-center gap-2 w-100">
                                                    <span class="fs-9 text-muted" style="width: 40px;">Cliente:</span>
                                                    <span class="fw-bold text-<?= $progressColorClient ?>"><?= number_format($conversionRateClientOnly, 1) ?>%</span>
                                                    <div class="progress h-4px flex-grow-1">
                                                        <div class="progress-bar bg-<?= $progressColorClient ?>" style="width: <?= min(100, $conversionRateClientOnly) ?>%"></div>
                                                    </div>
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
                <?php else: ?>
                    <div class="text-muted fs-7">Sem dados de conversão para o período.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->
<?php endif; ?>

<!--begin::Row - Métricas de Atendimento por Agente-->
<?php 
$attendanceAgents = $agentAttendanceMetrics['agents'] ?? [];
$attendanceTotals = $agentAttendanceMetrics['totals'] ?? [];
?>
<div class="row g-5 mb-5" id="attendance-metrics-section">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">
                        <i class="ki-duotone ki-messages fs-2 text-info me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                        </i>
                        Desempenho de Atendimento
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Métricas de conversas por agente no período</span>
                </h3>
                <div class="card-toolbar">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <!-- Filtros Rápidos de Período -->
                        <div class="btn-group" role="group" id="attendance-period-buttons">
                            <button type="button" class="btn btn-sm btn-light-primary active" data-period="today">Hoje</button>
                            <button type="button" class="btn btn-sm btn-light" data-period="yesterday">Ontem</button>
                            <button type="button" class="btn btn-sm btn-light" data-period="this_week">Esta Semana</button>
                            <button type="button" class="btn btn-sm btn-light" data-period="this_month">Este Mês</button>
                            <button type="button" class="btn btn-sm btn-light" data-period="last_month">Mês Anterior</button>
                        </div>
                        
                        <!-- Filtro Personalizado -->
                        <div class="d-flex align-items-center gap-2">
                            <input type="date" id="attendance-date-from" class="form-control form-control-sm form-control-solid" 
                                   value="<?= date('Y-m-d') ?>" style="width: 130px;">
                            <span class="text-muted fs-8">até</span>
                            <input type="date" id="attendance-date-to" class="form-control form-control-sm form-control-solid" 
                                   value="<?= date('Y-m-d') ?>" style="width: 130px;">
                            <button type="button" class="btn btn-sm btn-icon btn-light-primary" onclick="loadAttendanceMetrics()" title="Filtrar">
                                <i class="ki-duotone ki-magnifier fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cards de Totais -->
            <div class="card-body py-3 border-bottom">
                <div class="d-flex flex-wrap justify-content-center gap-5" id="attendance-totals">
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-4 fw-bold text-gray-800" id="total-new"><?= number_format($attendanceTotals['new_conversations'] ?? 0) ?></div>
                        <div class="fw-semibold text-muted fs-7">Conversas Novas</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-4 fw-bold text-gray-800" id="total-interacted"><?= number_format($attendanceTotals['interacted_conversations'] ?? 0) ?></div>
                        <div class="fw-semibold text-muted fs-7">Interagidas</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-4 fw-bold text-primary" id="total-unique"><?= number_format($attendanceTotals['total_unique_conversations'] ?? 0) ?></div>
                        <div class="fw-semibold text-muted fs-7">Total Único</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-4 fw-bold text-success" id="total-messages"><?= number_format($attendanceTotals['total_messages_sent'] ?? 0) ?></div>
                        <div class="fw-semibold text-muted fs-7">Mensagens</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-4 fw-bold text-info" id="total-avg-response"><?= $attendanceTotals['avg_response_formatted'] ?? '-' ?></div>
                        <div class="fw-semibold text-muted fs-7">Tempo Médio</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-80px py-3 px-4 text-center">
                        <div class="fs-4 fw-bold text-dark" id="total-agents"><?= $attendanceTotals['agents_count'] ?? 0 ?></div>
                        <div class="fw-semibold text-muted fs-7">Agentes</div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-3">
                <!-- Loading Overlay -->
                <div id="attendance-loading" class="d-none">
                    <div class="d-flex justify-content-center align-items-center py-10">
                        <span class="spinner-border text-primary" role="status"></span>
                        <span class="ms-3 text-muted">Carregando métricas...</span>
                    </div>
                </div>
                
                <div class="table-responsive" id="attendance-table-container">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="attendance-table">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-150px">Agente</th>
                                <th class="min-w-80px text-center">
                                    Novas
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                                       title="Conversas criadas no período e atribuídas ao agente"></i>
                                </th>
                                <th class="min-w-80px text-center">
                                    Interagidas
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                                       title="Conversas onde o agente enviou mensagem no período (inclui conversas anteriores)"></i>
                                </th>
                                <th class="min-w-80px text-center">
                                    Total
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                                       title="Total único de conversas (novas + interagidas, sem duplicatas)"></i>
                                </th>
                                <th class="min-w-100px text-center">
                                    Tempo 1ª Resp.
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                                       title="Tempo médio da primeira resposta do agente"></i>
                                </th>
                                <th class="min-w-100px text-center">
                                    Tempo Médio
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                                       title="Tempo médio de todas as respostas do agente"></i>
                                </th>
                                <th class="min-w-70px text-center">Msgs</th>
                                <th class="min-w-80px text-center">
                                    Msgs/Conv
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                                       title="Média de mensagens enviadas por conversa"></i>
                                </th>
                                <th class="min-w-80px text-center">
                                    Conv/Dia
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                                       title="Média de conversas atendidas por dia"></i>
                                </th>
                                <th class="min-w-80px text-center">
                                    Resolução
                                    <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                                       title="Taxa de conversas resolvidas/fechadas"></i>
                                </th>
                                <th class="min-w-100px text-center">Performance</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold" id="attendance-tbody">
                            <?php if (empty($attendanceAgents)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted py-10">
                                    <i class="ki-duotone ki-information-5 fs-2x text-gray-400 mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div>Nenhum dado encontrado para o período selecionado</div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($attendanceAgents as $agent): ?>
                            <?php
                                $perfLevel = $agent['performance_level'] ?? ['color' => 'secondary', 'label' => '-'];
                                $avgResponseColor = 'success';
                                $avgResponseMinutes = $agent['avg_response_minutes'] ?? 0;
                                if ($avgResponseMinutes > 15) {
                                    $avgResponseColor = 'danger';
                                } elseif ($avgResponseMinutes > 10) {
                                    $avgResponseColor = 'warning';
                                } elseif ($avgResponseMinutes > 5) {
                                    $avgResponseColor = 'info';
                                }
                                
                                $firstResponseColor = 'success';
                                $firstResponseMinutes = $agent['avg_first_response_minutes'] ?? 0;
                                if ($firstResponseMinutes > 10) {
                                    $firstResponseColor = 'danger';
                                } elseif ($firstResponseMinutes > 5) {
                                    $firstResponseColor = 'warning';
                                } elseif ($firstResponseMinutes > 2) {
                                    $firstResponseColor = 'info';
                                }
                                
                                $resolutionColor = 'danger';
                                $resolutionRate = $agent['resolution_rate'] ?? 0;
                                if ($resolutionRate >= 80) {
                                    $resolutionColor = 'success';
                                } elseif ($resolutionRate >= 50) {
                                    $resolutionColor = 'warning';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-35px symbol-circle me-3">
                                            <?php if (!empty($agent['agent_avatar'])): ?>
                                                <img src="<?= $agent['agent_avatar'] ?>" alt="<?= htmlspecialchars($agent['agent_name']) ?>">
                                            <?php else: ?>
                                                <span class="symbol-label bg-light-primary text-primary fw-bold">
                                                    <?= strtoupper(substr($agent['agent_name'] ?? '?', 0, 1)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-800 fw-bold"><?= htmlspecialchars($agent['agent_name']) ?></span>
                                            <span class="text-muted fs-8">
                                                <span class="badge badge-light-<?= $agent['availability_status'] === 'online' ? 'success' : ($agent['availability_status'] === 'away' ? 'warning' : 'secondary') ?> fs-9">
                                                    <?= ucfirst($agent['availability_status'] ?? 'offline') ?>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-gray-800"><?= number_format($agent['new_conversations'] ?? 0) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-gray-800"><?= number_format($agent['interacted_conversations'] ?? 0) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-primary fs-6 fw-bold"><?= number_format($agent['total_unique_conversations'] ?? 0) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-<?= $firstResponseColor ?> fs-7">
                                        <?= $agent['avg_first_response_formatted'] ?? '-' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-<?= $avgResponseColor ?> fs-7">
                                        <?= $agent['avg_response_formatted'] ?? '-' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="text-gray-700"><?= number_format($agent['messages_sent'] ?? 0) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="text-gray-700"><?= $agent['messages_per_conversation'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-gray-800"><?= $agent['conversations_per_day'] ?? 0 ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <span class="fw-bold text-<?= $resolutionColor ?>"><?= $resolutionRate ?>%</span>
                                        <div class="progress h-4px w-40px">
                                            <div class="progress-bar bg-<?= $resolutionColor ?>" style="width: <?= min(100, $resolutionRate) ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-column align-items-center">
                                        <span class="badge badge-light-<?= $perfLevel['color'] ?> fs-7 mb-1"><?= $perfLevel['label'] ?></span>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="fs-8 text-muted"><?= $agent['performance_score'] ?? 0 ?>/100</span>
                                            <div class="progress h-3px w-30px">
                                                <div class="progress-bar bg-<?= $perfLevel['color'] ?>" style="width: <?= $agent['performance_score'] ?? 0 ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Legenda -->
                <div class="border-top pt-4 mt-4">
                    <div class="d-flex flex-wrap gap-4 fs-8 text-muted">
                        <div class="d-flex align-items-center">
                            <span class="bullet bullet-dot bg-success me-2"></span>
                            Tempo resposta: &lt;5min (ótimo)
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="bullet bullet-dot bg-info me-2"></span>
                            5-10min (bom)
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="bullet bullet-dot bg-warning me-2"></span>
                            10-15min (regular)
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="bullet bullet-dot bg-danger me-2"></span>
                            &gt;15min (atenção)
                        </div>
                        <span class="separator separator-dashed"></span>
                        <div class="d-flex align-items-center">
                            <strong class="me-1">Performance:</strong>
                            Combinação de tempo de resposta, resolução, volume e consistência
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Row - Métricas de Atendimento-->

<!--begin::Row - Gráfico de Conversas ao Longo do Tempo-->
<div class="row g-5 mb-5">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">
                        <i class="ki-duotone ki-graph-up fs-2 text-primary me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                            <span class="path6"></span>
                        </i>
                        Conversas ao Longo do Tempo
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">
                        Evolução de conversas <strong>novas</strong> (criadas) no período
                        <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" 
                           title="Este gráfico mostra CONVERSAS NOVAS criadas no período, não conversas interagidas. Para ver métricas de interação, consulte a tabela de Desempenho de Atendimento acima."></i>
                    </span>
                </h3>
                <div class="card-toolbar d-flex gap-3">
                    <!--begin::Modo de Visualização-->
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="chart_view_mode" id="view_mode_aggregated" value="aggregated" checked>
                        <label class="btn btn-sm btn-light-primary" for="view_mode_aggregated" title="Agregar todos os dados">
                            <i class="ki-duotone ki-abstract-10 fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Agregado
                        </label>
                        
                        <input type="radio" class="btn-check" name="chart_view_mode" id="view_mode_comparative" value="comparative">
                        <label class="btn btn-sm btn-light-primary" for="view_mode_comparative" title="Comparar separadamente">
                            <i class="ki-duotone ki-chart-line-up fs-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Comparativo
                        </label>
                    </div>
                    <!--end::Modo de Visualização-->
                    
                    <!--begin::Agrupamento Temporal-->
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="chart_group_by" id="group_by_day" value="day" checked>
                        <label class="btn btn-sm btn-light" for="group_by_day">Dia</label>
                        
                        <input type="radio" class="btn-check" name="chart_group_by" id="group_by_week" value="week">
                        <label class="btn btn-sm btn-light" for="group_by_week">Semana</label>
                        
                        <input type="radio" class="btn-check" name="chart_group_by" id="group_by_month" value="month">
                        <label class="btn btn-sm btn-light" for="group_by_month">Mês</label>
                    </div>
                    <!--end::Agrupamento Temporal-->
                </div>
            </div>
            
            <!--begin::Card body - Filtros Avançados-->
            <div class="card-body border-top pt-6">
                <div class="row g-3 mb-5">
                    <div class="col-md-3">
                        <label class="form-label fs-7 fw-semibold mb-2">Setor:</label>
                        <select id="chart_filter_department" class="form-select form-select-sm form-select-solid">
                            <option value="">Todos os Setores</option>
                            <?php
                            $departments = \App\Models\Department::getActive();
                            foreach ($departments as $dept):
                            ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fs-7 fw-semibold mb-2">Times:</label>
                        <select id="chart_filter_teams" class="form-select form-select-sm form-select-solid" multiple data-control="select2" data-placeholder="Selecione times...">
                            <?php
                            $teams = \App\Models\Team::getActive();
                            foreach ($teams as $team):
                            ?>
                                <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fs-7 fw-semibold mb-2">Agentes:</label>
                        <select id="chart_filter_agents" class="form-select form-select-sm form-select-solid" multiple data-control="select2" data-placeholder="Selecione agentes...">
                            <?php
                            $agents = \App\Helpers\Database::fetchAll(
                                "SELECT id, name FROM users WHERE role IN ('agent', 'admin', 'supervisor') AND status = 'active' ORDER BY name ASC"
                            );
                            foreach ($agents as $agent):
                            ?>
                                <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fs-7 fw-semibold mb-2">Canal:</label>
                        <select id="chart_filter_channel" class="form-select form-select-sm form-select-solid">
                            <option value="">Todos os Canais</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="instagram">Instagram</option>
                            <option value="messenger">Messenger</option>
                            <option value="webchat">Webchat</option>
                            <option value="api">API</option>
                        </select>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted fs-7 mb-2">
                            <i class="ki-duotone ki-information-5 fs-6 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Use os filtros acima para segmentar as conversas. Este gráfico mostra apenas conversas <strong>novas</strong> (created_at).
                        </div>
                        <div class="text-muted fs-8">
                            <i class="ki-duotone ki-chart-line-up fs-6 text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <strong>Modo Comparativo:</strong> Selecione times ou agentes para comparar performances com legendas coloridas
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="clearChartFilters()">
                        <i class="ki-duotone ki-arrows-circle fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Limpar Filtros
                    </button>
                </div>
                
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="kt_chart_conversations_over_time"></canvas>
                </div>
            </div>
            <!--end::Card body-->
        </div>
    </div>
</div>
<!--end::Row - Gráfico de Conversas-->

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

<?php if (empty($rankingByRevenue) && empty($rankingByConversion) && empty($rankingByTicket) && \App\Helpers\Permission::can('conversion.view')): ?>
<div class="row g-5 mb-5">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Rankings de Vendas</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Sem dados no período</span>
                </h3>
            </div>
            <div class="card-body">
                <div class="text-muted fs-7">Não há dados suficientes para gerar rankings.</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($goalsOverview)): ?>
<?php
$uniqueTeams = [];
foreach ($goalsOverview as $goalItem) {
    foreach (($goalItem['teams'] ?? []) as $teamRow) {
        $uniqueTeams[$teamRow['team_id']] = $teamRow['team_name'];
    }
}
ksort($uniqueTeams);
?>
<!-- Seção de Metas - Sempre Visível -->
<?php foreach ($goalsOverview as $goalIndex => $goal): 
    $progress = $goal['progress'] ?? null;
    $currentValue = $progress ? (float)$progress['current_value'] : 0;
    
    // Para metas multi-agent: multiplicar o target pelo número de agentes
    $baseTargetValue = (float)($goal['target_value'] ?? 0);
    $agentCount = count($goal['agents'] ?? []);
    $isMultiAgent = ($goal['target_type'] ?? '') === 'multi_agent';
    
    // Se for multi-agent, o alvo total é target * número de agentes
    $targetValue = $isMultiAgent && $agentCount > 1 
        ? $baseTargetValue * $agentCount 
        : $baseTargetValue;
    
    // Recalcular percentage com o target correto
    $percentage = $targetValue > 0 ? ($currentValue / $targetValue) * 100 : 0;
    
    $remainingValue = max(0, $targetValue - $currentValue);
    $flagStatus = $progress['flag_status'] ?? 'good';
    $progressColor = \App\Models\Goal::getFlagColor($flagStatus);
    
    // Calcular projeção
    $startDate = strtotime($goal['start_date']);
    $endDate = strtotime($goal['end_date']);
    $today = time();
    $totalDays = max(1, ($endDate - $startDate) / 86400);
    $elapsedDays = max(1, ($today - $startDate) / 86400);
    $remainingDays = max(0, ($endDate - $today) / 86400);
    $dailyAverage = $elapsedDays > 0 ? $currentValue / $elapsedDays : 0;
    $projectedValue = $dailyAverage * $totalDays;
    $projectedPercentage = $targetValue > 0 ? min(200, ($projectedValue / $targetValue) * 100) : 0;
    $expectedPercentage = ($elapsedDays / $totalDays) * 100;
    $isOnTrack = $percentage >= $expectedPercentage;
?>
<div class="card card-flush mb-5 border-start border-4 border-<?= $progressColor ?>">
    <!-- Header da Meta -->
    <div class="card-header bg-light-<?= $progressColor ?> py-5">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center">
                <div class="symbol symbol-60px me-4">
                    <span class="symbol-label bg-<?= $progressColor ?> text-white fs-1 fw-bolder">
                        <?= number_format($percentage, 0) ?>%
                    </span>
                </div>
                <div>
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($goal['name']) ?></h3>
                    <span class="text-muted fs-7">
                        <?= \App\Models\Goal::TYPES[$goal['type']]['label'] ?? $goal['type'] ?> • 
                        <?= date('d/m', strtotime($goal['start_date'])) ?> → <?= date('d/m/Y', strtotime($goal['end_date'])) ?>
                        <?php if ($isMultiAgent && $agentCount > 1): ?>
                            <span class="badge badge-light-info ms-2"><?= $agentCount ?> agentes × <?= \App\Models\Goal::formatValue($goal['type'], $baseTargetValue) ?></span>
                        <?php endif; ?>
                        <span class="badge badge-light-<?= $isOnTrack ? 'success' : 'danger' ?> ms-2">
                            <?= $isOnTrack ? '✓ No ritmo' : '⚠ Abaixo do ritmo' ?>
                        </span>
                    </span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($uniqueTeams)): ?>
                <select class="form-select form-select-sm" style="width: 150px;" onchange="filterGoalTeam(this, <?= $goal['id'] ?>)">
                    <option value="">Todos os times</option>
                    <?php foreach ($uniqueTeams as $teamId => $teamName): ?>
                        <option value="<?= $teamId ?>"><?= htmlspecialchars($teamName) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <a href="<?= \App\Helpers\Url::to('/goals/show?id=' . $goal['id']) ?>" class="btn btn-sm btn-light-primary">
                    <i class="bi bi-eye"></i> Detalhes
                </a>
            </div>
        </div>
    </div>
    
    <div class="card-body pt-5">
        <!-- KPIs Principais - Sempre Visíveis -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="bg-light-success rounded p-4 text-center h-100">
                    <div class="text-muted fs-8 mb-1">Realizado</div>
                    <div class="fs-2x fw-bolder text-success"><?= \App\Models\Goal::formatValue($goal['type'], $currentValue) ?></div>
                    <div class="text-muted fs-9">de <?= \App\Models\Goal::formatValue($goal['type'], $targetValue) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-light-warning rounded p-4 text-center h-100">
                    <div class="text-muted fs-8 mb-1">Falta</div>
                    <div class="fs-2x fw-bolder text-warning"><?= \App\Models\Goal::formatValue($goal['type'], $remainingValue) ?></div>
                    <div class="text-muted fs-9"><?= number_format($remainingDays, 0) ?> dias restantes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-light-info rounded p-4 text-center h-100">
                    <div class="text-muted fs-8 mb-1">Média Diária</div>
                    <div class="fs-2x fw-bolder text-info"><?= \App\Models\Goal::formatValue($goal['type'], $dailyAverage) ?></div>
                    <div class="text-muted fs-9">Necessário: <?= \App\Models\Goal::formatValue($goal['type'], $remainingDays > 0 ? $remainingValue / $remainingDays : 0) ?>/dia</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-light-primary rounded p-4 text-center h-100">
                    <div class="text-muted fs-8 mb-1">Projeção Final</div>
                    <div class="fs-2x fw-bolder text-primary"><?= number_format($projectedPercentage, 0) ?>%</div>
                    <div class="text-muted fs-9"><?= \App\Models\Goal::formatValue($goal['type'], $projectedValue) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Barra de Progresso com Flags -->
        <?php
            $flagCritical = (float)($goal['flag_critical_threshold'] ?? 60);
            $flagWarning = (float)($goal['flag_warning_threshold'] ?? 70);
            $flagGood = (float)($goal['flag_good_threshold'] ?? 80);
        ?>
        <div class="mb-5">
            <!-- Barra de fundo com zonas de flags -->
            <div class="position-relative">
                <div class="progress h-25px" style="background: linear-gradient(to right, 
                    #f1416c 0%, #f1416c <?= $flagCritical ?>%, 
                    #ffc700 <?= $flagCritical ?>%, #ffc700 <?= $flagWarning ?>%, 
                    #50cd89 <?= $flagWarning ?>%, #50cd89 <?= $flagGood ?>%,
                    #009ef7 <?= $flagGood ?>%, #009ef7 100%);">
                    <!-- Barra de progresso atual (overlay escuro) -->
                    <div class="position-absolute top-0 start-0 h-100 d-flex align-items-center justify-content-center" 
                         style="width: <?= min($percentage, 100) ?>%; background: rgba(0,0,0,0.3); border-radius: 0.475rem 0 0 0.475rem;">
                        <span class="fw-bold text-white fs-6" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                            <?= number_format($percentage, 1) ?>%
                        </span>
                    </div>
                    <!-- Marcador de posição atual -->
                    <div class="position-absolute top-0 h-100" 
                         style="left: <?= min($percentage, 100) ?>%; width: 3px; background: #fff; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>
                </div>
                
                <!-- Marcadores de flags -->
                <div class="position-absolute w-100" style="top: -5px;">
                    <div class="position-absolute" style="left: <?= $flagCritical ?>%; transform: translateX(-50%);">
                        <div class="bg-danger rounded-circle" style="width: 10px; height: 10px;"></div>
                    </div>
                    <div class="position-absolute" style="left: <?= $flagWarning ?>%; transform: translateX(-50%);">
                        <div class="bg-warning rounded-circle" style="width: 10px; height: 10px;"></div>
                    </div>
                    <div class="position-absolute" style="left: <?= $flagGood ?>%; transform: translateX(-50%);">
                        <div class="bg-success rounded-circle" style="width: 10px; height: 10px;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Legenda -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="d-flex gap-4 fs-8">
                    <span><span class="badge badge-danger me-1">🔴</span> Crítico &lt;<?= $flagCritical ?>%</span>
                    <span><span class="badge badge-warning me-1">🟡</span> Atenção <?= $flagCritical ?>-<?= $flagWarning ?>%</span>
                    <span><span class="badge badge-success me-1">🟢</span> Bom <?= $flagWarning ?>-<?= $flagGood ?>%</span>
                    <span><span class="badge badge-primary me-1">🔵</span> Excelente &gt;<?= $flagGood ?>%</span>
                </div>
                <span class="text-muted fs-8">Esperado hoje: <strong><?= number_format($expectedPercentage, 0) ?>%</strong></span>
            </div>
        </div>
        
        <!-- Ranking de Agentes e Times lado a lado -->
        <div class="row g-4">
            <!-- Ranking de Agentes -->
            <div class="col-lg-7">
                <div class="border rounded p-4 h-100">
                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-people-fill text-primary me-2"></i>
                        Ranking de Agentes
                    </h5>
                    <?php if (!empty($goal['agents'])): ?>
                        <?php
                            $totalAchieved = 0;
                            foreach ($goal['agents'] as $agentRow) {
                                $totalAchieved += (float)($agentRow['current_value'] ?? 0);
                            }
                        ?>
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle mb-0">
                                <thead>
                                    <tr class="text-muted fs-8 fw-bold">
                                        <th class="ps-0">#</th>
                                        <th>Agente</th>
                                        <th class="text-end">Realizado</th>
                                        <th class="text-end">% Meta</th>
                                        <th class="text-end">Bônus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($goal['agents'] as $index => $agent): ?>
                                        <?php
                                            $bonusPreview = $agent['bonus_preview'] ?? null;
                                            $bonusBlocked = $bonusPreview['conditions_blocked'] ?? false;
                                            $bonusTotal = $bonusPreview['total_bonus'] ?? 0;
                                            $lastTier = $bonusPreview['last_tier']['tier_name'] ?? null;
                                            $agentPercentage = (float)($agent['percentage'] ?? 0);
                                            
                                            // Cor baseada na posição
                                            $rowBg = '';
                                            $medal = '';
                                            if ($index === 0) { $rowBg = 'bg-light-success'; $medal = '🥇'; }
                                            elseif ($index === 1) { $rowBg = 'bg-light-warning'; $medal = '🥈'; }
                                            elseif ($index === 2) { $rowBg = 'bg-light-info'; $medal = '🥉'; }
                                        ?>
                                        <tr class="<?= $rowBg ?>" data-goal-<?= $goal['id'] ?>-team="<?= $agent['team_id'] ?? '' ?>">
                                            <td class="ps-0 fw-bold"><?= $medal ?: ($index + 1) ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="symbol symbol-35px me-3">
                                                        <span class="symbol-label bg-light-primary text-primary fw-bold">
                                                            <?= strtoupper(substr($agent['name'] ?? 'A', 0, 1)) ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold"><?= htmlspecialchars($agent['name']) ?></span>
                                                        <?php if (!empty($agent['team_name'])): ?>
                                                        <div class="text-muted fs-9"><?= htmlspecialchars($agent['team_name']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold"><?= \App\Models\Goal::formatValue($goal['type'], $agent['current_value'] ?? 0) ?></td>
                                            <td class="text-end">
                                                <div class="d-flex flex-column align-items-end">
                                                    <span class="fw-bold"><?= number_format($agentPercentage, 1) ?>%</span>
                                                    <div class="progress h-5px w-75px mt-1">
                                                        <div class="progress-bar bg-<?= $progressColor ?>" style="width: <?= min($agentPercentage, 100) ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <?php if (!empty($goal['enable_bonus'])): ?>
                                                    <span class="fw-bold fs-6 <?= $bonusBlocked ? 'text-danger' : 'text-success' ?>">
                                                        R$ <?= number_format((float)$bonusTotal, 2, ',', '.') ?>
                                                    </span>
                                                    <?php if ($bonusBlocked): ?>
                                                        <div class="badge badge-light-danger fs-9">Bloqueado</div>
                                                    <?php elseif ($lastTier): ?>
                                                        <div class="text-muted fs-9"><?= htmlspecialchars($lastTier) ?></div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted text-center py-5">
                            <i class="bi bi-people fs-2x d-block mb-2 text-gray-400"></i>
                            Sem agentes vinculados a esta meta.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Ranking de Times -->
            <div class="col-lg-5">
                <div class="border rounded p-4 h-100">
                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-trophy-fill text-warning me-2"></i>
                        Ranking de Times
                    </h5>
                    <?php if (!empty($goal['teams'])): ?>
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle mb-0">
                                <thead>
                                    <tr class="text-muted fs-8 fw-bold">
                                        <th class="ps-0">#</th>
                                        <th>Time</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($goal['teams'] as $index => $team): ?>
                                        <?php
                                            $isLeader = $index === 0;
                                            $rowBg = $isLeader ? 'bg-light-success' : '';
                                            $teamPercentage = (float)($team['percentage'] ?? 0);
                                        ?>
                                        <tr class="<?= $rowBg ?>">
                                            <td class="ps-0">
                                                <?php if ($isLeader): ?>
                                                    <span class="badge badge-success">🏆 1º</span>
                                                <?php else: ?>
                                                    <span class="fw-bold"><?= $index + 1 ?>º</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold"><?= htmlspecialchars($team['team_name']) ?></td>
                                            <td class="text-end fw-bold"><?= \App\Models\Goal::formatValue($goal['type'], $team['total_value'] ?? 0) ?></td>
                                            <td class="text-end">
                                                <div class="d-flex flex-column align-items-end">
                                                    <span class="fw-bold"><?= number_format($teamPercentage, 1) ?>%</span>
                                                    <div class="progress h-5px w-75px mt-1">
                                                        <div class="progress-bar bg-success" style="width: <?= min($teamPercentage, 100) ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted text-center py-5">
                            <i class="bi bi-people fs-2x d-block mb-2 text-gray-400"></i>
                            Sem times vinculados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function filterGoalTeam(select, goalId) {
    const teamId = select.value;
    const rows = document.querySelectorAll('[data-goal-' + goalId + '-team]');
    rows.forEach(row => {
        if (!teamId || row.dataset['goal' + goalId + 'Team'] === teamId) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
<?php endif; ?>

<?php if (!empty($uniqueTeams)): ?>
<script>
document.getElementById('goals-team-filter')?.addEventListener('change', function () {
    const teamId = this.value;
    document.querySelectorAll('[data-team-id]').forEach(row => {
        if (!teamId || row.getAttribute('data-team-id') === teamId) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>
<?php endif; ?>

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
// ✅ ATUALIZADO: Suporta filtros adicionais e modo de visualização
function loadChartData(chartType, canvasId, configCallback, additionalFilters = {}) {
    const dateFrom = document.getElementById("kt_dashboard_date_from").value;
    const dateTo = document.getElementById("kt_dashboard_date_to").value;
    const groupBy = document.querySelector("input[name=\"chart_group_by\"]:checked")?.value || "day";
    const viewMode = document.querySelector("input[name=\"chart_view_mode\"]:checked")?.value || "aggregated";
    
    const url = new URL({$chartDataUrlJson}, window.location.origin);
    url.searchParams.append("type", chartType);
    url.searchParams.append("date_from", dateFrom);
    url.searchParams.append("date_to", dateTo);
    if (groupBy) url.searchParams.append("group_by", groupBy);
    if (viewMode) url.searchParams.append("view_mode", viewMode);
    
    // ✅ NOVO: Adicionar filtros adicionais
    if (additionalFilters.department_id) {
        url.searchParams.append("department_id", additionalFilters.department_id);
    }
    if (additionalFilters.team_ids && additionalFilters.team_ids.length > 0) {
        url.searchParams.append("team_ids", JSON.stringify(additionalFilters.team_ids));
    }
    if (additionalFilters.agent_ids && additionalFilters.agent_ids.length > 0) {
        url.searchParams.append("agent_ids", JSON.stringify(additionalFilters.agent_ids));
    }
    if (additionalFilters.channel) {
        url.searchParams.append("channel", additionalFilters.channel);
    }
    if (additionalFilters.funnel_id) {
        url.searchParams.append("funnel_id", additionalFilters.funnel_id);
    }
    
    // Logs de depuração
    console.debug("[chart] fetch start", {
        chartType,
        canvasId,
        url: url.toString(),
        groupBy,
        viewMode,
        filters: additionalFilters
    });
    
    fetch(url)
        .then(async response => {
            console.log("Chart response status:", response.status, "for", chartType, "url=", url.toString());
            const raw = await response.text();
            try {
                return JSON.parse(raw);
            } catch (err) {
                console.error("[chart] JSON parse error", { chartType, url: url.toString(), raw: raw?.slice(0, 500) });
                throw err;
            }
        })
        .then(data => {
            console.log("Chart data received:", chartType, data);
            if (!data || data.success === false) {
                console.error("[chart] backend error", { chartType, data, url: url.toString() });
            }
            
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
            console.error("[chart] fetch failed", { chartType, url: url.toString(), error });
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
// ✅ ATUALIZADO: Suporta modo comparativo com legendas coloridas
function configConversationsOverTime(data) {
    // ✅ MODO COMPARATIVO: Dados vêm separados por time/agente
    if (data.mode === 'comparative') {
        return configConversationsComparative(data);
    }
    
    // ✅ MODO AGREGADO: Modo tradicional
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

// ✅ NOVO: Configuração do gráfico comparativo (times/agentes separados)
function configConversationsComparative(data) {
    const datasets = data.datasets || [];
    
    if (datasets.length === 0) {
        return configConversationsOverTime([]);
    }
    
    // Coletar todos os períodos únicos
    const allPeriods = new Set();
    datasets.forEach(dataset => {
        Object.keys(dataset.data || {}).forEach(period => allPeriods.add(period));
    });
    const labels = Array.from(allPeriods).sort();
    
    // Criar dataset para cada time/agente
    const chartDatasets = datasets.map(dataset => {
        const name = dataset.team_name || dataset.agent_name || "Sem nome";
        const color = dataset.color || "#009ef7";
        
        // Preencher dados por período
        const periodData = labels.map(period => {
            const dataPoint = dataset.data[period];
            return dataPoint ? parseInt(dataPoint.total || 0) : 0;
        });
        
        // Converter cor hex para rgba
        const rgbaColor = hexToRgba(color, 0.2);
        
        return {
            label: name,
            data: periodData,
            borderColor: color,
            backgroundColor: rgbaColor,
            tension: 0.4,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        };
    });
    
    return {
        type: "line",
        data: {
            labels: labels,
            datasets: chartDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: "top",
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    mode: "index",
                    intersect: false,
                    callbacks: {
                        title: function(context) {
                            return 'Período: ' + context[0].label;
                        },
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y;
                            return label + ': ' + value + ' conversas';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    };
}

// ✅ NOVO: Converter cor hexadecimal para rgba
function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
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

// ✅ NOVO: Obter filtros do gráfico de conversas ao longo do tempo
function getChartFilters() {
    const filters = {};
    
    const departmentId = document.getElementById("chart_filter_department")?.value;
    if (departmentId) {
        filters.department_id = departmentId;
    }
    
    // Times (select2 multiselect)
    const teamSelect = document.getElementById("chart_filter_teams");
    if (teamSelect) {
        const teamIds = $(teamSelect).val(); // Select2 retorna array
        if (teamIds && teamIds.length > 0) {
            filters.team_ids = teamIds;
        }
    }
    
    // Agentes (select2 multiselect)
    const agentSelect = document.getElementById("chart_filter_agents");
    if (agentSelect) {
        const agentIds = $(agentSelect).val(); // Select2 retorna array
        if (agentIds && agentIds.length > 0) {
            filters.agent_ids = agentIds;
        }
    }
    
    const channel = document.getElementById("chart_filter_channel")?.value;
    if (channel) {
        filters.channel = channel;
    }
    
    return filters;
}

// ✅ NOVO: Aplicar filtros ao gráfico
function applyChartFilters() {
    const filters = getChartFilters();
    console.log("[applyChartFilters] Filtros aplicados:", JSON.stringify(filters, null, 2));
    loadChartData("conversations_over_time", "kt_chart_conversations_over_time", configConversationsOverTime, filters);
}

// ✅ NOVO: Limpar todos os filtros
function clearChartFilters() {
    // Limpar select simples
    const departmentSelect = document.getElementById("chart_filter_department");
    if (departmentSelect) departmentSelect.value = "";
    
    const channelSelect = document.getElementById("chart_filter_channel");
    if (channelSelect) channelSelect.value = "";
    
    // Limpar select2 multiselects
    const teamSelect = $("#chart_filter_teams");
    if (teamSelect.length) teamSelect.val(null).trigger("change");
    
    const agentSelect = $("#chart_filter_agents");
    if (agentSelect.length) agentSelect.val(null).trigger("change");
    
    // Resetar para modo agregado
    const aggregatedMode = document.getElementById("view_mode_aggregated");
    if (aggregatedMode) aggregatedMode.checked = true;
    
    // Recarregar gráfico sem filtros
    loadChartData("conversations_over_time", "kt_chart_conversations_over_time", configConversationsOverTime);
}

// Função para recarregar dashboard
function loadDashboard() {
    const dateFrom = document.getElementById("kt_dashboard_date_from").value;
    const dateTo = document.getElementById("kt_dashboard_date_to").value;
    
    // Obter agentes selecionados
    const agentsSelect = document.getElementById("kt_dashboard_agents");
    const selectedAgents = agentsSelect ? Array.from(agentsSelect.selectedOptions).map(opt => opt.value) : [];
    
    let url = {$dashboardUrlJson} + '?date_from=' + dateFrom + '&date_to=' + dateTo;
    
    if (selectedAgents.length > 0) {
        url += '&agents=' + selectedAgents.join(',');
    }
    
    window.location.href = url;
}

// Função para limpar filtro de agentes
function clearAgentFilter() {
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
    // Inicializar Select2 no filtro de agentes do dashboard
    if ($("#kt_dashboard_agents").length) {
        $("#kt_dashboard_agents").select2({
            placeholder: "Todos os agentes",
            allowClear: true,
            width: "100%",
            closeOnSelect: false
        });
    }
    
    // ✅ NOVO: Inicializar Select2 nos filtros multiselect
    $("#chart_filter_teams").select2({
        placeholder: "Selecione times...",
        allowClear: true,
        width: "100%"
    }).on("change", function() {
        console.log("[chart] Times changed:", $(this).val());
        applyChartFilters();
    });
    
    $("#chart_filter_agents").select2({
        placeholder: "Selecione agentes...",
        allowClear: true,
        width: "100%"
    }).on("change", function() {
        console.log("[chart] Agents changed:", $(this).val());
        applyChartFilters();
    });
    
    // ✅ Event listeners para filtros simples (setor e canal)
    document.getElementById("chart_filter_department")?.addEventListener("change", function() {
        console.log("[chart] Department changed:", this.value);
        applyChartFilters();
    });
    
    document.getElementById("chart_filter_channel")?.addEventListener("change", function() {
        console.log("[chart] Channel changed:", this.value);
        applyChartFilters();
    });
    
    loadAllCharts();
    
    // ✅ ATUALIZADO: Atualizar gráfico quando mudar o agrupamento (mantendo filtros)
    document.querySelectorAll("input[name=\"chart_group_by\"]").forEach(radio => {
        radio.addEventListener("change", function() {
            const filters = getChartFilters();
            loadChartData("conversations_over_time", "kt_chart_conversations_over_time", configConversationsOverTime, filters);
        });
    });
    
    // ✅ NOVO: Atualizar gráfico quando mudar o modo de visualização
    document.querySelectorAll("input[name=\"chart_view_mode\"]").forEach(radio => {
        radio.addEventListener("change", function() {
            const filters = getChartFilters();
            const viewMode = this.value;
            
            // Mostrar aviso se modo comparativo sem filtros
            if (viewMode === 'comparative') {
                if (!filters.team_ids?.length && !filters.agent_ids?.length) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Modo Comparativo',
                        text: 'Selecione times ou agentes específicos para comparar suas performances lado a lado.',
                        confirmButtonText: 'Entendi'
                    });
                    // Voltar para modo agregado
                    document.getElementById('view_mode_aggregated').checked = true;
                    return;
                }
            }
            
            loadChartData("conversations_over_time", "kt_chart_conversations_over_time", configConversationsOverTime, filters);
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
                html: '<div class="text-start">' +
                    '<p><strong>Sincronização concluída:</strong></p>' +
                    '<ul class="mb-0">' +
                        '<li>Integrações processadas: ' + (data.integrations_processed || 0) + '</li>' +
                        '<li>Pedidos processados: ' + (data.orders_processed || 0) + '</li>' +
                        '<li>Novos contatos: ' + (data.new_contacts || 0) + '</li>' +
                    '</ul>' +
                '</div>',
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

// ========================================
// MÉTRICAS DE ATENDIMENTO POR AGENTE
// ========================================

// Estado atual do período de atendimento
let currentAttendancePeriod = 'today';

// Inicializar filtros de atendimento
document.addEventListener('DOMContentLoaded', function() {
    initAttendanceFilters();
});

function initAttendanceFilters() {
    const buttons = document.querySelectorAll('#attendance-period-buttons button');
    
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Atualizar estado ativo
            buttons.forEach(b => b.classList.remove('active', 'btn-light-primary'));
            buttons.forEach(b => b.classList.add('btn-light'));
            this.classList.remove('btn-light');
            this.classList.add('active', 'btn-light-primary');
            
            const period = this.dataset.period;
            currentAttendancePeriod = period;
            
            // Calcular datas
            const dates = calculatePeriodDates(period);
            
            // Atualizar inputs de data
            document.getElementById('attendance-date-from').value = dates.from;
            document.getElementById('attendance-date-to').value = dates.to;
            
            // Carregar dados
            loadAttendanceMetrics();
        });
    });
    
    // Listener para mudança manual de datas
    document.getElementById('attendance-date-from').addEventListener('change', function() {
        clearPeriodButtons();
    });
    document.getElementById('attendance-date-to').addEventListener('change', function() {
        clearPeriodButtons();
    });
}

function clearPeriodButtons() {
    const buttons = document.querySelectorAll('#attendance-period-buttons button');
    buttons.forEach(b => {
        b.classList.remove('active', 'btn-light-primary');
        b.classList.add('btn-light');
    });
    currentAttendancePeriod = 'custom';
}

function calculatePeriodDates(period) {
    const today = new Date();
    let from, to;
    
    switch(period) {
        case 'today':
            from = to = formatDate(today);
            break;
            
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            from = to = formatDate(yesterday);
            break;
            
        case 'this_week':
            const weekStart = new Date(today);
            const dayOfWeek = today.getDay();
            const diff = dayOfWeek === 0 ? 6 : dayOfWeek - 1; // Segunda como início da semana
            weekStart.setDate(today.getDate() - diff);
            from = formatDate(weekStart);
            to = formatDate(today);
            break;
            
        case 'this_month':
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            from = formatDate(monthStart);
            to = formatDate(today);
            break;
            
        case 'last_month':
            const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
            from = formatDate(lastMonthStart);
            to = formatDate(lastMonthEnd);
            break;
            
        default:
            from = to = formatDate(today);
    }
    
    return { from, to };
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function loadAttendanceMetrics() {
    const dateFrom = document.getElementById('attendance-date-from').value;
    const dateTo = document.getElementById('attendance-date-to').value;
    
    // Mostrar loading
    document.getElementById('attendance-loading').classList.remove('d-none');
    document.getElementById('attendance-table-container').classList.add('d-none');
    
    fetch("/dashboard/attendance-metrics?date_from=" + dateFrom + "&date_to=" + dateTo)
        .then(response => response.json())
        .then(data => {
            // Esconder loading
            document.getElementById('attendance-loading').classList.add('d-none');
            document.getElementById('attendance-table-container').classList.remove('d-none');
            
            if (data.success) {
                updateAttendanceTotals(data.data.totals);
                updateAttendanceTable(data.data.agents);
                
                // Re-inicializar tooltips
                const tooltips = document.querySelectorAll('#attendance-metrics-section [data-bs-toggle="tooltip"]');
                tooltips.forEach(t => new bootstrap.Tooltip(t));
            } else {
                console.error('Erro ao carregar métricas:', data.message);
            }
        })
        .catch(error => {
            document.getElementById('attendance-loading').classList.add('d-none');
            document.getElementById('attendance-table-container').classList.remove('d-none');
            console.error('Erro:', error);
        });
}

function updateAttendanceTotals(totals) {
    document.getElementById('total-new').textContent = formatNumber(totals.new_conversations || 0);
    document.getElementById('total-interacted').textContent = formatNumber(totals.interacted_conversations || 0);
    document.getElementById('total-unique').textContent = formatNumber(totals.total_unique_conversations || 0);
    document.getElementById('total-messages').textContent = formatNumber(totals.total_messages_sent || 0);
    document.getElementById('total-avg-response').textContent = totals.avg_response_formatted || '-';
    document.getElementById('total-agents').textContent = totals.agents_count || 0;
}

function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num);
}

function updateAttendanceTable(agents) {
    const tbody = document.getElementById('attendance-tbody');
    
    if (!agents || agents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-10">' +
            '<i class="ki-duotone ki-information-5 fs-2x text-gray-400 mb-3">' +
                '<span class="path1"></span><span class="path2"></span><span class="path3"></span>' +
            '</i>' +
            '<div>Nenhum dado encontrado para o período selecionado</div>' +
        '</td></tr>';
        return;
    }
    
    let html = '';
    
    agents.forEach(agent => {
        const perfLevel = agent.performance_level || {color: 'secondary', label: '-'};
        
        // Determinar cores
        const avgResponseMinutes = agent.avg_response_minutes || 0;
        let avgResponseColor = 'success';
        if (avgResponseMinutes > 15) avgResponseColor = 'danger';
        else if (avgResponseMinutes > 10) avgResponseColor = 'warning';
        else if (avgResponseMinutes > 5) avgResponseColor = 'info';
        
        const firstResponseMinutes = agent.avg_first_response_minutes || 0;
        let firstResponseColor = 'success';
        if (firstResponseMinutes > 10) firstResponseColor = 'danger';
        else if (firstResponseMinutes > 5) firstResponseColor = 'warning';
        else if (firstResponseMinutes > 2) firstResponseColor = 'info';
        
        const resolutionRate = agent.resolution_rate || 0;
        let resolutionColor = 'danger';
        if (resolutionRate >= 80) resolutionColor = 'success';
        else if (resolutionRate >= 50) resolutionColor = 'warning';
        
        const statusClass = agent.availability_status === 'online' ? 'success' : 
                           (agent.availability_status === 'away' ? 'warning' : 'secondary');
        
        const avatarHtml = agent.agent_avatar 
            ? '<img src="' + agent.agent_avatar + '" alt="' + escapeHtml(agent.agent_name) + '">'
            : '<span class="symbol-label bg-light-primary text-primary fw-bold">' + (agent.agent_name || '?').charAt(0).toUpperCase() + '</span>';
        
        html += '<tr>' +
            '<td>' +
                '<div class="d-flex align-items-center">' +
                    '<div class="symbol symbol-35px symbol-circle me-3">' + avatarHtml + '</div>' +
                    '<div class="d-flex flex-column">' +
                        '<span class="text-gray-800 fw-bold">' + escapeHtml(agent.agent_name) + '</span>' +
                        '<span class="text-muted fs-8">' +
                            '<span class="badge badge-light-' + statusClass + ' fs-9">' +
                                capitalize(agent.availability_status || 'offline') +
                            '</span>' +
                        '</span>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td class="text-center"><span class="fw-bold text-gray-800">' + formatNumber(agent.new_conversations || 0) + '</span></td>' +
            '<td class="text-center"><span class="fw-bold text-gray-800">' + formatNumber(agent.interacted_conversations || 0) + '</span></td>' +
            '<td class="text-center"><span class="badge badge-light-primary fs-6 fw-bold">' + formatNumber(agent.total_unique_conversations || 0) + '</span></td>' +
            '<td class="text-center"><span class="badge badge-light-' + firstResponseColor + ' fs-7">' + (agent.avg_first_response_formatted || '-') + '</span></td>' +
            '<td class="text-center"><span class="badge badge-light-' + avgResponseColor + ' fs-7">' + (agent.avg_response_formatted || '-') + '</span></td>' +
            '<td class="text-center"><span class="text-gray-700">' + formatNumber(agent.messages_sent || 0) + '</span></td>' +
            '<td class="text-center"><span class="text-gray-700">' + (agent.messages_per_conversation || 0) + '</span></td>' +
            '<td class="text-center"><span class="fw-bold text-gray-800">' + (agent.conversations_per_day || 0) + '</span></td>' +
            '<td class="text-center">' +
                '<div class="d-flex align-items-center justify-content-center gap-2">' +
                    '<span class="fw-bold text-' + resolutionColor + '">' + resolutionRate + '%</span>' +
                    '<div class="progress h-4px w-40px">' +
                        '<div class="progress-bar bg-' + resolutionColor + '" style="width: ' + Math.min(100, resolutionRate) + '%"></div>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td class="text-center">' +
                '<div class="d-flex flex-column align-items-center">' +
                    '<span class="badge badge-light-' + perfLevel.color + ' fs-7 mb-1">' + perfLevel.label + '</span>' +
                    '<div class="d-flex align-items-center gap-1">' +
                        '<span class="fs-8 text-muted">' + (agent.performance_score || 0) + '/100</span>' +
                        '<div class="progress h-3px w-30px">' +
                            '<div class="progress-bar bg-' + perfLevel.color + '" style="width: ' + (agent.performance_score || 0) + '%"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</td>' +
        '</tr>';
    });
    
    tbody.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Player de gravação de chamadas
function playRecording(url) {
    // Criar modal se não existir
    let modal = document.getElementById('recordingPlayerModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'recordingPlayerModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ki-duotone ki-headphones fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Gravação da Chamada
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <audio id="recordingAudioPlayer" controls class="w-100">
                            Seu navegador não suporta áudio.
                        </audio>
                    </div>
                    <div class="modal-footer">
                        <a id="downloadRecordingBtn" href="#" download class="btn btn-light-primary btn-sm">
                            <i class="ki-duotone ki-cloud-download fs-4 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Baixar
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Parar áudio quando modal fechar
        modal.addEventListener('hidden.bs.modal', function() {
            const audio = document.getElementById('recordingAudioPlayer');
            if (audio) {
                audio.pause();
                audio.currentTime = 0;
            }
        });
    }
    
    // Configurar o player
    const audio = document.getElementById('recordingAudioPlayer');
    const downloadBtn = document.getElementById('downloadRecordingBtn');
    
    audio.src = url;
    downloadBtn.href = url;
    
    // Abrir modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Auto-play
    audio.play().catch(() => {});
}

// Mostrar análise completa de chamada
function showCallAnalysis(analysisId) {
    // Buscar dados da análise via AJAX
    fetch('<?= \App\Helpers\Url::to('/api4com-calls/analysis/') ?>' + analysisId)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                Swal.fire('Erro', data.message || 'Erro ao carregar análise', 'error');
                return;
            }
            
            const analysis = data.analysis;
            const dimensionLabels = {
                'opening': 'Abertura',
                'tone': 'Tom de Voz',
                'listening': 'Escuta Ativa',
                'objection_handling': 'Quebra de Objeções',
                'value_proposition': 'Proposta de Valor',
                'closing': 'Fechamento',
                'qualification': 'Qualificação',
                'control': 'Controle',
                'professionalism': 'Profissionalismo',
                'empathy': 'Empatia'
            };
            
            const outcomeLabels = {
                'positive': 'Positivo',
                'negative': 'Negativo',
                'neutral': 'Neutro',
                'followup_needed': 'Requer Follow-up'
            };
            
            const outcomeColors = {
                'positive': 'success',
                'negative': 'danger',
                'neutral': 'secondary',
                'followup_needed': 'warning'
            };
            
            // Construir HTML das dimensões
            let dimensionsHtml = '<div class="row g-2">';
            const dimensions = ['opening', 'tone', 'listening', 'objection_handling', 'value_proposition', 
                               'closing', 'qualification', 'control', 'professionalism', 'empathy'];
            dimensions.forEach(function(dim) {
                const score = parseFloat(analysis['score_' + dim] || 0);
                const color = score >= 4.5 ? 'success' : (score >= 3.5 ? 'primary' : (score >= 2.5 ? 'warning' : 'danger'));
                dimensionsHtml += '<div class="col-6 col-md-4"><div class="border rounded p-2 text-center"><div class="fs-5 fw-bold text-' + color + '">' + score.toFixed(1) + '</div><div class="text-muted fs-8">' + dimensionLabels[dim] + '</div></div></div>';
            });
            dimensionsHtml += '</div>';
            
            // Parse arrays JSON
            const strengths = JSON.parse(analysis.strengths || '[]');
            const weaknesses = JSON.parse(analysis.weaknesses || '[]');
            const suggestions = JSON.parse(analysis.suggestions || '[]');
            
            // Construir listas
            let strengthsHtml = '';
            if (strengths.length > 0) {
                strengths.forEach(function(s) { strengthsHtml += '<li class="text-success">' + escapeHtml(s) + '</li>'; });
            } else {
                strengthsHtml = '<li class="text-muted">Nenhum ponto forte identificado</li>';
            }
            
            let weaknessesHtml = '';
            if (weaknesses.length > 0) {
                weaknesses.forEach(function(w) { weaknessesHtml += '<li class="text-danger">' + escapeHtml(w) + '</li>'; });
            } else {
                weaknessesHtml = '<li class="text-muted">Nenhum ponto fraco identificado</li>';
            }
            
            let suggestionsHtml = '';
            if (suggestions.length > 0) {
                suggestions.forEach(function(s) { suggestionsHtml += '<li class="text-primary">' + escapeHtml(s) + '</li>'; });
            } else {
                suggestionsHtml = '<li class="text-muted">Nenhuma sugestão</li>';
            }
            
            const outcome = analysis.call_outcome || 'neutral';
            const overallScore = parseFloat(analysis.overall_score || 0).toFixed(1);
            
            let modalHtml = '<div class="text-start">' +
                '<div class="d-flex justify-content-between align-items-center mb-4">' +
                    '<div><span class="badge badge-light-' + outcomeColors[outcome] + ' fs-6">' + outcomeLabels[outcome] + '</span></div>' +
                    '<div class="text-end"><span class="fs-1 fw-bold text-primary">' + overallScore + '</span><span class="text-muted">/5.0</span></div>' +
                '</div>' +
                '<h6 class="fw-bold mb-2">📊 Scores por Dimensão</h6>' + dimensionsHtml +
                '<h6 class="fw-bold mt-4 mb-2">📝 Resumo</h6>' +
                '<p class="text-gray-600">' + escapeHtml(analysis.summary || 'Sem resumo') + '</p>' +
                '<div class="row mt-4">' +
                    '<div class="col-md-4"><h6 class="fw-bold text-success mb-2">✅ Pontos Fortes</h6><ul class="ps-3 fs-7">' + strengthsHtml + '</ul></div>' +
                    '<div class="col-md-4"><h6 class="fw-bold text-danger mb-2">❌ Pontos Fracos</h6><ul class="ps-3 fs-7">' + weaknessesHtml + '</ul></div>' +
                    '<div class="col-md-4"><h6 class="fw-bold text-primary mb-2">💡 Sugestões</h6><ul class="ps-3 fs-7">' + suggestionsHtml + '</ul></div>' +
                '</div>';
            
            if (analysis.transcription) {
                modalHtml += '<h6 class="fw-bold mt-4 mb-2">🎙️ Transcrição</h6>' +
                    '<div class="bg-light rounded p-3 fs-7" style="max-height: 200px; overflow-y: auto;">' + escapeHtml(analysis.transcription) + '</div>';
            }
            
            modalHtml += '</div>';
            
            Swal.fire({
                title: '<i class="ki-duotone ki-chart-line-star me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i> Análise da Chamada',
                html: modalHtml,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false
            });
        })
        .catch(function(error) {
            console.error('Erro ao carregar análise:', error);
            Swal.fire('Erro', 'Erro ao carregar análise', 'error');
        });
}

// Função para filtros rápidos de período
function setQuickPeriodDashboard(period) {
    const today = new Date();
    let dateFrom, dateTo;
    
    switch(period) {
        case 'today':
            dateFrom = dateTo = today;
            break;
            
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            dateFrom = dateTo = yesterday;
            break;
            
        case 'this_week':
            // Segunda-feira da semana atual
            const startOfWeek = new Date(today);
            const dayOfWeek = startOfWeek.getDay();
            const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek; // Se domingo, volta 6 dias
            startOfWeek.setDate(startOfWeek.getDate() + diff);
            dateFrom = startOfWeek;
            dateTo = today;
            break;
            
        case 'last_week':
            // Segunda a domingo da semana passada
            const lastWeekEnd = new Date(today);
            const currentDay = lastWeekEnd.getDay();
            const daysToLastSunday = currentDay === 0 ? 0 : currentDay;
            lastWeekEnd.setDate(lastWeekEnd.getDate() - daysToLastSunday);
            
            const lastWeekStart = new Date(lastWeekEnd);
            lastWeekStart.setDate(lastWeekStart.getDate() - 6);
            
            dateFrom = lastWeekStart;
            dateTo = lastWeekEnd;
            break;
            
        case 'this_month':
            dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
            dateTo = today;
            break;
            
        case 'last_month':
            const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            dateFrom = lastMonth;
            dateTo = lastDayOfLastMonth;
            break;
    }
    
    // Formatar datas para YYYY-MM-DD
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    };
    
    // Atualizar campos e recarregar dashboard
    document.getElementById('kt_dashboard_date_from').value = formatDate(dateFrom);
    document.getElementById('kt_dashboard_date_to').value = formatDate(dateTo);
    loadDashboard();
}
</script>
SCRIPT;
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
