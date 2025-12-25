<?php
$layout = 'layouts.metronic.app';
$title = 'Dashboard de IA';

ob_start();
?>
<!--begin::Card - Filtros de Período-->
<div class="card mb-5">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <i class="ki-duotone ki-robot fs-2x text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <h3 class="fw-bold mb-0">Dashboard de Inteligência Artificial</h3>
            </div>
            <div class="d-flex align-items-center gap-3 ms-auto">
                <label class="fw-semibold fs-6 mb-0">Período:</label>
                <input type="date" id="kt_ai_dashboard_date_from" class="form-control form-control-solid" 
                       value="<?= htmlspecialchars($dateFrom) ?>" style="width: 150px;" />
                <span class="text-muted">até</span>
                <input type="date" id="kt_ai_dashboard_date_to" class="form-control form-control-solid" 
                       value="<?= htmlspecialchars(date('Y-m-d', strtotime($dateTo))) ?>" style="width: 150px;" />
                <button type="button" class="btn btn-sm btn-primary" onclick="loadAIDashboard()">
                    <i class="ki-duotone ki-magnifier fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Filtrar
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Card - Filtros de Período-->

<!--begin::Row - Cards Principais-->
<div class="row g-5 mb-5">
    <!--begin::Col - Conversas IA-->
    <div class="col-xl-3">
        <div class="card bg-light-primary h-100">
            <div class="card-body">
                <i class="ki-duotone ki-message-programming fs-2x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($aiMetrics['total_ai_conversations'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Conversas com IA</div>
                <div class="text-muted fs-7 mt-1">Período selecionado</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Mensagens IA-->
    <div class="col-xl-3">
        <div class="card bg-light-info h-100">
            <div class="card-body">
                <i class="ki-duotone ki-message-text-2 fs-2x text-info mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($aiMetrics['total_ai_messages'] ?? 0) ?></div>
                <div class="fw-semibold text-gray-500">Mensagens Enviadas</div>
                <div class="text-muted fs-7 mt-1">Respostas da IA</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Taxa de Resolução IA-->
    <div class="col-xl-3">
        <div class="card bg-light-success h-100">
            <div class="card-body">
                <i class="ki-duotone ki-check-circle fs-2x text-success mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($aiMetrics['ai_resolution_rate'] ?? 0, 1) ?>%</div>
                <div class="fw-semibold text-gray-500">Taxa de Resolução</div>
                <div class="text-muted fs-7 mt-1">Resolvido sem escalonar</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Taxa de Escalonamento-->
    <div class="col-xl-3">
        <div class="card bg-light-warning h-100">
            <div class="card-body">
                <i class="ki-duotone ki-arrow-up-right fs-2x text-warning mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($aiMetrics['ai_escalation_rate'] ?? 0, 1) ?>%</div>
                <div class="fw-semibold text-gray-500">Taxa de Escalonamento</div>
                <div class="text-muted fs-7 mt-1">Transferido para humano</div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Métricas de Custos e Tempo-->
<div class="row g-5 mb-5">
    <!--begin::Col - Tempo de Resposta IA-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">⚡ Tempo de Resposta IA</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-timer fs-3x text-success me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?php
                            $avgResponseAI = $stats['metrics']['avg_first_response_time_ai'] ?? null;
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
                        </div>
                        <div class="text-muted fs-6">Média 1ª resposta</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Tokens Usados-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">🎯 Tokens Usados</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-abstract-26 fs-3x text-primary me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?php
                            $totalTokens = $aiMetrics['total_tokens'] ?? 0;
                            if ($totalTokens >= 1000000) {
                                echo number_format($totalTokens / 1000000, 2) . 'M';
                            } elseif ($totalTokens >= 1000) {
                                echo number_format($totalTokens / 1000, 1) . 'K';
                            } else {
                                echo number_format($totalTokens);
                            }
                            ?>
                        </div>
                        <div class="text-muted fs-6">Total de tokens</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Custo Total-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">💰 Custo Total</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-dollar fs-3x text-danger me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            $<?= number_format($aiMetrics['total_cost'] ?? 0, 2) ?>
                        </div>
                        <div class="text-muted fs-6">Período selecionado</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Agentes de IA Ativos-->
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">🤖 Agentes Ativos</h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex align-items-center">
                    <i class="ki-duotone ki-technology-4 fs-3x text-info me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>
                        <div class="fs-2x fw-bold text-gray-800">
                            <?= number_format($aiMetrics['active_ai_agents'] ?? 0) ?>
                        </div>
                        <div class="text-muted fs-6">Agentes de IA</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Comparação IA vs Humano-->
<div class="row g-5 mb-5">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">📊 Comparação: IA vs Humanos</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Performance comparativa no período</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-5">
                    <!--begin::Col - Métricas IA-->
                    <div class="col-xl-6">
                        <div class="card bg-light-primary">
                            <div class="card-header border-0 pt-5">
                                <h4 class="card-title fw-bold">
                                    <i class="ki-duotone ki-robot fs-2 text-primary me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Atendimento por IA
                                </h4>
                            </div>
                            <div class="card-body pt-3">
                                <div class="d-flex flex-column gap-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Conversas Atendidas:</span>
                                        <span class="fw-bold text-gray-800"><?= number_format($comparison['ai']['total_conversations'] ?? 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Resolvidas:</span>
                                        <span class="fw-bold text-success"><?= number_format($comparison['ai']['resolved_conversations'] ?? 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Taxa de Resolução:</span>
                                        <span class="badge badge-light-success"><?= number_format($comparison['ai']['resolution_rate'] ?? 0, 1) ?>%</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Tempo 1ª Resposta:</span>
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($comparison['ai']['avg_first_response_formatted'] ?? '-') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Col-->
                    
                    <!--begin::Col - Métricas Humanos-->
                    <div class="col-xl-6">
                        <div class="card bg-light-info">
                            <div class="card-header border-0 pt-5">
                                <h4 class="card-title fw-bold">
                                    <i class="ki-duotone ki-profile-user fs-2 text-info me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Atendimento por Humanos
                                </h4>
                            </div>
                            <div class="card-body pt-3">
                                <div class="d-flex flex-column gap-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Conversas Atendidas:</span>
                                        <span class="fw-bold text-gray-800"><?= number_format($comparison['human']['total_conversations'] ?? 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Resolvidas:</span>
                                        <span class="fw-bold text-success"><?= number_format($comparison['human']['resolved_conversations'] ?? 0) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Taxa de Resolução:</span>
                                        <span class="badge badge-light-info"><?= number_format($comparison['human']['resolution_rate'] ?? 0, 1) ?>%</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold text-gray-600">Tempo 1ª Resposta:</span>
                                        <span class="fw-bold text-info"><?= htmlspecialchars($comparison['human']['avg_first_response_formatted'] ?? '-') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Col-->
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->

<!--begin::Row - SLA Separado-->
<div class="row g-5 mb-5">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">⏱️ Cumprimento de SLA</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Taxa de resposta dentro do SLA (<?= $slaCompliance['sla_minutes'] ?? 15 ?> minutos)</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-5">
                    <!--begin::Col - SLA Geral-->
                    <div class="col-xl-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="fs-6 fw-semibold text-gray-600 mb-3">📊 Geral (IA + Humanos)</div>
                            <div class="position-relative d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <svg viewBox="0 0 36 36" class="circular-chart" style="width: 100%; height: 100%;">
                                    <path class="circle-bg" stroke="#3f4254" stroke-width="3" fill="none"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    <path class="circle" stroke="#009EF7" stroke-width="3" stroke-linecap="round" fill="none"
                                          stroke-dasharray="<?= $slaCompliance['general']['rate'] ?? 0 ?>, 100"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                </svg>
                                <div class="position-absolute text-center" style="font-size: 1.5rem; font-weight: 700; color: #009EF7;">
                                    <?= number_format($slaCompliance['general']['rate'] ?? 0, 0) ?>%
                                </div>
                            </div>
                            <div class="text-muted fs-7 mt-2"><?= $slaCompliance['general']['within_sla'] ?? 0 ?> de <?= $slaCompliance['general']['total'] ?? 0 ?></div>
                        </div>
                    </div>
                    <!--end::Col-->
                    
                    <!--begin::Col - SLA IA-->
                    <div class="col-xl-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="fs-6 fw-semibold text-gray-600 mb-3">🤖 Apenas IA</div>
                            <div class="position-relative d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <svg viewBox="0 0 36 36" class="circular-chart" style="width: 100%; height: 100%;">
                                    <path class="circle-bg" stroke="#3f4254" stroke-width="3" fill="none"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    <path class="circle" stroke="#50CD89" stroke-width="3" stroke-linecap="round" fill="none"
                                          stroke-dasharray="<?= $slaCompliance['ai']['rate'] ?? 0 ?>, 100"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                </svg>
                                <div class="position-absolute text-center" style="font-size: 1.5rem; font-weight: 700; color: #50CD89;">
                                    <?= number_format($slaCompliance['ai']['rate'] ?? 0, 0) ?>%
                                </div>
                            </div>
                            <div class="text-muted fs-7 mt-2"><?= $slaCompliance['ai']['within_sla'] ?? 0 ?> de <?= $slaCompliance['ai']['total'] ?? 0 ?></div>
                        </div>
                    </div>
                    <!--end::Col-->
                    
                    <!--begin::Col - SLA Humanos-->
                    <div class="col-xl-4">
                        <div class="d-flex flex-column align-items-center">
                            <div class="fs-6 fw-semibold text-gray-600 mb-3">🧑 Apenas Humanos</div>
                            <div class="position-relative d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <svg viewBox="0 0 36 36" class="circular-chart" style="width: 100%; height: 100%;">
                                    <path class="circle-bg" stroke="#3f4254" stroke-width="3" fill="none"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    <path class="circle" stroke="#7239EA" stroke-width="3" stroke-linecap="round" fill="none"
                                          stroke-dasharray="<?= $slaCompliance['human']['rate'] ?? 0 ?>, 100"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                </svg>
                                <div class="position-absolute text-center" style="font-size: 1.5rem; font-weight: 700; color: #7239EA;">
                                    <?= number_format($slaCompliance['human']['rate'] ?? 0, 0) ?>%
                                </div>
                            </div>
                            <div class="text-muted fs-7 mt-2"><?= $slaCompliance['human']['within_sla'] ?? 0 ?> de <?= $slaCompliance['human']['total'] ?? 0 ?></div>
                        </div>
                    </div>
                    <!--end::Col-->
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->

<!--begin::Row - Ranking de Agentes de IA-->
<?php if (!empty($aiAgentsRanking)): ?>
<div class="row g-5 mb-5">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">🏆 Ranking de Agentes de IA</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Performance dos agentes de IA no período</span>
                </h3>
                <div class="card-toolbar">
                    <a href="<?= \App\Helpers\Url::to('/ai-agents') ?>" class="btn btn-sm btn-light-primary">
                        <i class="ki-duotone ki-setting-2 fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Gerenciar Agentes
                    </a>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Agente</th>
                                <th class="min-w-80px">Tipo</th>
                                <th class="min-w-80px">Conversas</th>
                                <th class="min-w-80px">Resolvidas</th>
                                <th class="min-w-80px">Escalonadas</th>
                                <th class="min-w-80px">Taxa Resolução</th>
                                <th class="min-w-80px">Tokens</th>
                                <th class="min-w-80px">Custo</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($aiAgentsRanking as $agent): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40px me-3">
                                                <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                                                    🤖
                                                </div>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id']) ?>" class="text-gray-800 fw-bold text-hover-primary">
                                                    <?= htmlspecialchars($agent['name'] ?? 'Sem nome') ?>
                                                </a>
                                                <span class="text-muted fs-7"><?= htmlspecialchars($agent['model'] ?? 'gpt-4') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info"><?= htmlspecialchars($agent['agent_type'] ?? 'custom') ?></span>
                                    </td>
                                    <td>
                                        <span class="text-gray-800 fw-bold"><?= number_format($agent['total_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-success fw-bold"><?= number_format($agent['resolved_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-warning fw-bold"><?= number_format($agent['escalated_conversations'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $rate = $agent['resolution_rate'] ?? 0;
                                        $badgeClass = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge badge-light-<?= $badgeClass ?>"><?= number_format($rate, 1) ?>%</span>
                                    </td>
                                    <td>
                                        <?php
                                        $tokens = $agent['total_tokens'] ?? 0;
                                        if ($tokens >= 1000) {
                                            echo number_format($tokens / 1000, 1) . 'K';
                                        } else {
                                            echo number_format($tokens);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="text-gray-800">$<?= number_format($agent['total_cost'] ?? 0, 2) ?></span>
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
<?php endif; ?>
<!--end::Row-->

<style>
.circular-chart {
    display: block;
    max-width: 100%;
    max-height: 120px;
}
.circular-chart .circle-bg {
    fill: none;
}
.circular-chart .circle {
    fill: none;
    animation: progress 1s ease-out forwards;
}
@keyframes progress {
    0% {
        stroke-dasharray: 0, 100;
    }
}
</style>

<script>
function loadAIDashboard() {
    const dateFrom = document.getElementById('kt_ai_dashboard_date_from').value;
    const dateTo = document.getElementById('kt_ai_dashboard_date_to').value;
    
    window.location.href = '<?= \App\Helpers\Url::to('/dashboard/ai') ?>?date_from=' + dateFrom + '&date_to=' + dateTo;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

