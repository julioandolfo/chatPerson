<?php
/**
 * View: Performance Completa de Agente
 * Métricas de atendimento, coaching, conversão e disponibilidade
 */

$layout = 'layouts.metronic.app';
$title = 'Performance de ' . ($agent['name'] ?? ($report['agent']['name'] ?? 'Agente'));

use App\Helpers\Url;

ob_start();

// Extrair dados
$agent = $agent ?? $report['agent'] ?? [];
$agentMetrics = $agentMetrics ?? [];
$performanceStats = $performanceStats ?? [];
$availabilityStats = $availabilityStats ?? [];
$conversionMetrics = $conversionMetrics ?? [];
$slaSettings = $slaSettings ?? [];
$report = $report ?? [];
$badges = $badges ?? [];
$goals = $goals ?? [];

// SLA configurados
$slaFirstResponse = $slaSettings['sla']['first_response_time'] ?? $slaSettings['first_response_time'] ?? 15;
$slaOngoingResponse = $slaSettings['sla']['ongoing_response_time'] ?? $slaSettings['ongoing_response_time'] ?? $slaFirstResponse;

// Helper para formatar tempo
function formatTimeDisplay($seconds, $showUnit = true) {
    if ($seconds <= 0) return '-';
    if ($seconds < 60) {
        return round($seconds) . ($showUnit ? 's' : '');
    } elseif ($seconds < 3600) {
        return round($seconds / 60, 1) . ($showUnit ? 'min' : '');
    } else {
        return round($seconds / 3600, 1) . ($showUnit ? 'h' : '');
    }
}
?>

<!-- Cabeçalho -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-7 gap-4">
    <div class="d-flex align-items-center gap-4">
        <div class="symbol symbol-70px symbol-circle">
            <?php if (!empty($agent['avatar'])): ?>
                <img src="<?= htmlspecialchars($agent['avatar']) ?>" alt="<?= htmlspecialchars($agent['name'] ?? '') ?>">
            <?php else: ?>
                <span class="symbol-label bg-light-primary text-primary fs-1 fw-bold">
                    <?= strtoupper(substr($agent['name'] ?? 'A', 0, 1)) ?>
                </span>
            <?php endif; ?>
        </div>
        <div>
            <h1 class="fs-2 fw-bold mb-1"><?= htmlspecialchars($agent['name'] ?? 'Agente') ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <?php 
                $availStatus = $agent['availability_status'] ?? 'offline';
                $availColors = ['online' => 'success', 'offline' => 'secondary', 'away' => 'warning', 'busy' => 'danger'];
                $availLabels = ['online' => 'Online', 'offline' => 'Offline', 'away' => 'Ausente', 'busy' => 'Ocupado'];
                ?>
                <span class="badge badge-light-<?= $availColors[$availStatus] ?? 'secondary' ?> fs-7">
                    <?= $availLabels[$availStatus] ?? 'Desconhecido' ?>
                </span>
                <?php if (!empty($agent['role'])): ?>
                    <span class="badge badge-light-info fs-7"><?= ucfirst(htmlspecialchars($agent['role'])) ?></span>
                <?php endif; ?>
                <span class="badge badge-light fs-7">
                    Período: <?= date('d/m/Y', strtotime($dateFrom)) ?> - <?= date('d/m/Y', strtotime($dateTo)) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="id" value="<?= $agent['id'] ?? '' ?>">
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            <span class="text-muted">até</span>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="ki-duotone ki-filter fs-4"></i> Filtrar
            </button>
        </form>
    </div>
</div>

<!-- KPIs Principais -->
<div class="row g-5 mb-7">
    <!-- Total de Conversas -->
    <div class="col-md-6 col-lg-3">
        <div class="card bg-light-primary card-xl-stretch">
            <div class="card-body">
                <i class="ki-duotone ki-message-text-2 fs-2x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2">
                    <?= number_format($agentMetrics['total_conversations'] ?? 0) ?>
                </div>
                <div class="fw-semibold text-gray-600">Total de Conversas</div>
                <?php 
                $openConv = $agentMetrics['open_conversations'] ?? 0;
                $closedConv = ($agentMetrics['resolved_conversations'] ?? 0) + ($agentMetrics['closed_conversations'] ?? 0);
                ?>
                <div class="text-muted fs-7 mt-2">
                    <?= $openConv ?> abertas • <?= $closedConv ?> fechadas
                </div>
            </div>
        </div>
    </div>
    
    <!-- Taxa de Resolução -->
    <div class="col-md-6 col-lg-3">
        <div class="card bg-light-success card-xl-stretch">
            <div class="card-body">
                <i class="ki-duotone ki-check-circle fs-2x text-success mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <?php $resolutionRate = $agentMetrics['resolution_rate'] ?? 0; ?>
                <div class="text-gray-900 fw-bold fs-2 mb-2">
                    <?= number_format($resolutionRate, 1) ?>%
                </div>
                <div class="fw-semibold text-gray-600">Taxa de Resolução</div>
                <div class="progress h-6px mt-3">
                    <div class="progress-bar bg-success" style="width: <?= min(100, $resolutionRate) ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SLA 1ª Resposta -->
    <div class="col-md-6 col-lg-3">
        <?php 
        $slaFirstRate = $agentMetrics['sla_first_response_rate'] ?? 0;
        $slaColor = $slaFirstRate >= 90 ? 'success' : ($slaFirstRate >= 70 ? 'warning' : 'danger');
        $slaBg = $slaFirstRate >= 90 ? 'light-success' : ($slaFirstRate >= 70 ? 'light-warning' : 'light-danger');
        ?>
        <div class="card bg-<?= $slaBg ?> card-xl-stretch">
            <div class="card-body">
                <i class="ki-duotone ki-timer fs-2x text-<?= $slaColor ?> mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2">
                    <?= number_format($slaFirstRate, 1) ?>%
                </div>
                <div class="fw-semibold text-gray-600">SLA 1ª Resposta</div>
                <div class="text-muted fs-7 mt-2">
                    <?= $agentMetrics['first_response_within_sla'] ?? 0 ?> de <?= $agentMetrics['total_conversations'] ?? 0 ?> dentro do SLA
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mensagens Enviadas -->
    <div class="col-md-6 col-lg-3">
        <div class="card bg-light-info card-xl-stretch">
            <div class="card-body">
                <i class="ki-duotone ki-sms fs-2x text-info mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="text-gray-900 fw-bold fs-2 mb-2">
                    <?= number_format($agentMetrics['total_messages'] ?? 0) ?>
                </div>
                <div class="fw-semibold text-gray-600">Mensagens Enviadas</div>
                <div class="text-muted fs-7 mt-2">
                    Média: <?= number_format($agentMetrics['avg_messages_per_conversation'] ?? 0, 1) ?> por conversa
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Métricas de Tempo e SLA -->
<div class="row g-5 mb-7">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Métricas de Tempo</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Tempos médios de atendimento</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="table-responsive">
                    <table class="table align-middle gs-0 gy-4">
                        <tbody>
                            <tr>
                                <td class="fw-semibold text-gray-600">
                                    <i class="ki-duotone ki-time text-primary fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Tempo 1ª Resposta
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold fs-5">
                                        <?= formatTimeDisplay($agentMetrics['avg_first_response_seconds'] ?? 0) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-gray-600">
                                    <i class="ki-duotone ki-message-text text-info fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Tempo Médio Resposta
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold fs-5">
                                        <?= formatTimeDisplay($agentMetrics['avg_response_seconds'] ?? 0) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-gray-600">
                                    <i class="ki-duotone ki-calendar-tick text-success fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Tempo de Resolução
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold fs-5">
                                        <?php
                                        $resHours = $agentMetrics['avg_resolution_hours'] ?? 0;
                                        if ($resHours > 0) {
                                            if ($resHours < 1) {
                                                echo round($resHours * 60, 1) . ' min';
                                            } elseif ($resHours < 24) {
                                                echo round($resHours, 1) . ' h';
                                            } else {
                                                echo round($resHours / 24, 1) . ' dias';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-gray-600">
                                    <i class="ki-duotone ki-chart-simple text-warning fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Conversas por Dia
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold fs-5">
                                        <?= number_format($agentMetrics['conversations_per_day'] ?? 0, 1) ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">SLA de Atendimento</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Cumprimento dos acordos de nível de serviço</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="table-responsive">
                    <table class="table align-middle gs-0 gy-4">
                        <tbody>
                            <tr>
                                <td class="fw-semibold text-gray-600">SLA 1ª Resposta (<?= $slaFirstResponse ?>min)</td>
                                <td class="text-end">
                                    <?php $rate1 = $agentMetrics['sla_first_response_rate'] ?? 0; ?>
                                    <span class="badge badge-light-<?= $rate1 >= 90 ? 'success' : ($rate1 >= 70 ? 'warning' : 'danger') ?> fs-6">
                                        <?= number_format($rate1, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-gray-600">SLA Respostas (<?= $slaOngoingResponse ?>min)</td>
                                <td class="text-end">
                                    <?php $rate2 = $agentMetrics['sla_response_rate'] ?? 0; ?>
                                    <span class="badge badge-light-<?= $rate2 >= 90 ? 'success' : ($rate2 >= 70 ? 'warning' : 'danger') ?> fs-6">
                                        <?= number_format($rate2, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-gray-600">Respostas no SLA</td>
                                <td class="text-end">
                                    <span class="fw-bold text-success"><?= $agentMetrics['responses_within_sla'] ?? 0 ?></span>
                                    <span class="text-muted">/</span>
                                    <span class="fw-bold"><?= $agentMetrics['total_responses'] ?? 0 ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-gray-600">1ª Resposta no SLA</td>
                                <td class="text-end">
                                    <span class="fw-bold text-success"><?= $agentMetrics['first_response_within_sla'] ?? 0 ?></span>
                                    <span class="text-muted">/</span>
                                    <span class="fw-bold"><?= $agentMetrics['total_conversations'] ?? 0 ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Disponibilidade e Produtividade -->
<?php if (!empty($availabilityStats)): ?>
<div class="row g-5 mb-7">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Disponibilidade</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Tempo online e produtividade</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="d-flex flex-wrap gap-5">
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-success">
                            <?php
                            $onlineSeconds = $availabilityStats['online']['seconds'] ?? 0;
                            $onlineMinutes = floor($onlineSeconds / 60);
                            echo $onlineMinutes >= 60 ? round($onlineMinutes / 60, 1) . 'h' : $onlineMinutes . 'min';
                            ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">Tempo Online</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-warning">
                            <?php
                            $awaySeconds = $availabilityStats['away']['seconds'] ?? 0;
                            $awayMinutes = floor($awaySeconds / 60);
                            echo $awayMinutes >= 60 ? round($awayMinutes / 60, 1) . 'h' : $awayMinutes . 'min';
                            ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">Tempo Ausente</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-danger">
                            <?php
                            $busySeconds = $availabilityStats['busy']['seconds'] ?? 0;
                            $busyMinutes = floor($busySeconds / 60);
                            echo $busyMinutes >= 60 ? round($busyMinutes / 60, 1) . 'h' : $busyMinutes . 'min';
                            ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">Tempo Ocupado</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-primary">
                            <?= number_format($availabilityStats['online']['percentage'] ?? 0, 1) ?>%
                        </div>
                        <div class="fw-semibold text-muted fs-7">Taxa Disponível</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Produtividade</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Métricas de eficiência</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="d-flex flex-wrap gap-5">
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-primary">
                            <?= number_format($agentMetrics['conversations_per_day'] ?? 0, 1) ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">Conversas/Dia</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-info">
                            <?= number_format($agentMetrics['avg_messages_per_conversation'] ?? 0, 1) ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">Msgs/Conversa</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-success">
                            <?= number_format($agentMetrics['resolution_rate'] ?? 0, 1) ?>%
                        </div>
                        <div class="fw-semibold text-muted fs-7">Taxa Resolução</div>
                    </div>
                    <?php if (!empty($agentMetrics['csat'])): ?>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-warning">
                            <?= number_format($agentMetrics['csat']['average'] ?? 0, 1) ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">CSAT</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Métricas de Conversão (WooCommerce) -->
<?php if (!empty($conversionMetrics) && !empty($conversionMetrics['total_orders'])): ?>
<div class="row g-5 mb-7">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">
                        <i class="ki-duotone ki-shop fs-2 text-success me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                        </i>
                        Métricas de Conversão
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Vendas e conversões via WooCommerce</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div class="row g-5">
                    <div class="col-md-3">
                        <div class="border border-gray-300 border-dashed rounded p-4 text-center">
                            <div class="fs-2x fw-bold text-success"><?= $conversionMetrics['total_orders'] ?? 0 ?></div>
                            <div class="fw-semibold text-muted">Pedidos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border border-gray-300 border-dashed rounded p-4 text-center">
                            <div class="fs-2x fw-bold text-primary">
                                R$ <?= number_format($conversionMetrics['total_revenue'] ?? 0, 2, ',', '.') ?>
                            </div>
                            <div class="fw-semibold text-muted">Receita Total</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border border-gray-300 border-dashed rounded p-4 text-center">
                            <div class="fs-2x fw-bold text-info">
                                R$ <?= number_format($conversionMetrics['avg_ticket'] ?? 0, 2, ',', '.') ?>
                            </div>
                            <div class="fw-semibold text-muted">Ticket Médio</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border border-gray-300 border-dashed rounded p-4 text-center">
                            <div class="fs-2x fw-bold text-warning">
                                <?= number_format($conversionMetrics['conversion_rate'] ?? 0, 1) ?>%
                            </div>
                            <div class="fw-semibold text-muted">Taxa Conversão</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Dimensões de Coaching (Notas de Performance) -->
<?php if (!empty($report['averages'])): ?>
<div class="row g-5 mb-7">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">Análise de Coaching</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Avaliação de dimensões de atendimento baseada em IA</span>
                </h3>
                <div class="card-toolbar">
                    <span class="badge badge-light-primary fs-5">
                        Nota Geral: <?= number_format($report['averages']['avg_overall'] ?? 0, 2) ?>/5.00
                    </span>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="row g-5">
                    <?php
                    $dimensions = [
                        'proactivity' => ['label' => 'Proatividade', 'icon' => 'rocket', 'color' => 'primary'],
                        'objection_handling' => ['label' => 'Quebra de Objeções', 'icon' => 'shield-tick', 'color' => 'success'],
                        'rapport' => ['label' => 'Rapport', 'icon' => 'people', 'color' => 'info'],
                        'closing_techniques' => ['label' => 'Técnicas de Fechamento', 'icon' => 'check-circle', 'color' => 'warning'],
                        'qualification' => ['label' => 'Qualificação', 'icon' => 'search-list', 'color' => 'danger'],
                        'clarity' => ['label' => 'Clareza', 'icon' => 'message-text', 'color' => 'primary'],
                        'value_proposition' => ['label' => 'Proposta de Valor', 'icon' => 'star', 'color' => 'success'],
                        'response_time' => ['label' => 'Tempo de Resposta', 'icon' => 'timer', 'color' => 'info'],
                        'follow_up' => ['label' => 'Follow-up', 'icon' => 'calendar', 'color' => 'warning'],
                        'professionalism' => ['label' => 'Profissionalismo', 'icon' => 'award', 'color' => 'danger']
                    ];
                    
                    foreach ($dimensions as $key => $dim):
                        $score = $report['averages']['avg_' . $key] ?? 0;
                        $evolutionData = $report['evolution'][$key] ?? [];
                        $evolution = $evolutionData['change'] ?? 0;
                        $trendIcon = $evolution > 0 ? 'arrow-up' : ($evolution < 0 ? 'arrow-down' : 'minus');
                        $trendColor = $evolution > 0 ? 'success' : ($evolution < 0 ? 'danger' : 'muted');
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="d-flex align-items-center border border-gray-300 border-dashed rounded p-4">
                            <div class="symbol symbol-40px me-4">
                                <span class="symbol-label bg-light-<?= $dim['color'] ?>">
                                    <i class="ki-duotone ki-<?= $dim['icon'] ?> fs-2 text-<?= $dim['color'] ?>">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold text-gray-600 fs-7"><?= $dim['label'] ?></div>
                                <div class="d-flex align-items-center">
                                    <span class="fs-4 fw-bold"><?= number_format($score, 2) ?></span>
                                    <span class="text-muted fs-7 ms-1">/5.00</span>
                                    <?php if ($evolution != 0): ?>
                                    <span class="badge badge-light-<?= $trendColor ?> ms-2 fs-8">
                                        <i class="ki-duotone ki-<?= $trendIcon ?> fs-7"></i>
                                        <?= $evolution >= 0 ? '+' : '' ?><?= number_format($evolution, 2) ?>
                                    </span>
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
<?php endif; ?>

<!-- Pontos Fortes e Fracos -->
<?php if (!empty($report['top_strengths']) || !empty($report['top_weaknesses'])): ?>
<div class="row g-5 mb-7">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title">
                    <i class="ki-duotone ki-check-circle fs-2 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Pontos Fortes
                </h3>
            </div>
            <div class="card-body">
                <?php if (!empty($report['top_strengths'])): ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($report['top_strengths'] as $strength): ?>
                    <li class="d-flex align-items-start mb-3">
                        <i class="ki-duotone ki-double-check fs-3 text-success me-2 mt-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <span><?= htmlspecialchars($strength) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted mb-0">Nenhum ponto forte identificado ainda.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title">
                    <i class="ki-duotone ki-information fs-2 text-warning me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Áreas de Melhoria
                </h3>
            </div>
            <div class="card-body">
                <?php if (!empty($report['top_weaknesses'])): ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($report['top_weaknesses'] as $weakness): ?>
                    <li class="d-flex align-items-start mb-3">
                        <i class="ki-duotone ki-arrow-up fs-3 text-warning me-2 mt-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <span><?= htmlspecialchars($weakness) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted mb-0">Nenhuma área de melhoria identificada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Badges e Metas -->
<div class="row g-5 mb-7">
    <?php if (!empty($badges)): ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title">
                    <i class="ki-duotone ki-award fs-2 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Conquistas (<?= count($badges) ?>)
                </h3>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-5">
                    <?php foreach ($badges as $badge): ?>
                    <div class="text-center">
                        <div class="symbol symbol-60px mb-2">
                            <span class="symbol-label bg-light-primary text-primary fs-1">
                                🏆
                            </span>
                        </div>
                        <div class="fw-bold fs-7"><?= htmlspecialchars($badge['badge_name']) ?></div>
                        <div class="badge badge-light-<?= 
                            $badge['badge_level'] === 'Platinum' ? 'primary' : 
                            ($badge['badge_level'] === 'Gold' ? 'warning' : 
                            ($badge['badge_level'] === 'Silver' ? 'secondary' : 'info')) 
                        ?> fs-8">
                            <?= htmlspecialchars($badge['badge_level']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($goals)): ?>
    <div class="col-lg-<?= !empty($badges) ? '6' : '12' ?>">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title">
                    <i class="ki-duotone ki-chart-line-up fs-2 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Metas Ativas (<?= count($goals) ?>)
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-bordered align-middle gs-0 gy-3">
                        <thead>
                            <tr class="fw-bold text-muted fs-7">
                                <th>Dimensão</th>
                                <th>Meta</th>
                                <th>Atual</th>
                                <th>Progresso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($goals as $goal): 
                                $progress = $goal['current_score'] >= $goal['target_score'] ? 100 : 
                                           (($goal['current_score'] / max(1, $goal['target_score'])) * 100);
                            ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $goal['dimension']))) ?></td>
                                <td><?= number_format($goal['target_score'], 2) ?></td>
                                <td><?= number_format($goal['current_score'], 2) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress h-6px w-100px me-2">
                                            <div class="progress-bar bg-<?= $progress >= 100 ? 'success' : 'primary' ?>" 
                                                 style="width: <?= min($progress, 100) ?>%"></div>
                                        </div>
                                        <span class="fw-bold fs-7"><?= number_format($progress, 0) ?>%</span>
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
    <?php endif; ?>
</div>

<!-- Conversas Analisadas com Métricas de Coaching -->
<?php if (!empty($analyzedConversations['conversations'])): ?>
<div class="card card-flush mb-7">
    <div class="card-header">
        <h3 class="card-title">📋 Conversas Analisadas - Métricas de Coaching</h3>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fs-7">
                <?= $analyzedConversations['total'] ?> conversas no período
            </span>
        </div>
    </div>
    <div class="card-body">
        <div id="analyzedConversationsList">
            <?php foreach ($analyzedConversations['conversations'] as $conv): ?>
            <div class="border border-gray-300 rounded p-5 mb-4">
                <!-- Cabeçalho da Conversa -->
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <a href="<?= Url::to('/conversations/' . $conv['id']) ?>" class="text-gray-900 fw-bold fs-5 text-hover-primary me-2" target="_blank">
                                #<?= $conv['id'] ?> - <?= htmlspecialchars($conv['contact_name'] ?? 'Sem nome') ?>
                            </a>
                            <span class="badge badge-light-<?= $conv['status_badge']['class'] ?> fs-8">
                                <?= $conv['status_badge']['text'] ?>
                            </span>
                        </div>
                        <div class="text-gray-600 fs-7">
                            <i class="ki-duotone ki-calendar fs-6"><span class="path1"></span><span class="path2"></span></i>
                            <?= date('d/m/Y H:i', strtotime($conv['created_at'])) ?>
                            <span class="mx-2">•</span>
                            <?= ucfirst($conv['channel']) ?>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-light-primary" onclick="showConversationDetails(<?= $conv['id'] ?>)">
                        <i class="ki-duotone ki-eye fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Ver Detalhes
                    </button>
                </div>
                
                <!-- Métricas em Cards -->
                <div class="row g-3 mb-4">
                    <?php if ($conv['overall_score']): ?>
                    <div class="col-md-2">
                        <div class="bg-light-primary rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-primary"><?= $conv['overall_score_formatted'] ?></div>
                            <div class="fs-8 text-gray-600">Score Geral</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($conv['total_hints']): ?>
                    <div class="col-md-2">
                        <div class="bg-light-info rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-info"><?= $conv['total_hints'] ?></div>
                            <div class="fs-8 text-gray-600">Hints Dados</div>
                            <div class="fs-9 text-success">✓ <?= $conv['hints_helpful'] ?? 0 ?> úteis</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($conv['conversation_outcome']): ?>
                    <div class="col-md-2">
                        <div class="bg-light-<?= $conv['outcome_badge']['class'] ?> rounded p-3 text-center">
                            <div class="fs-5 fw-bold text-<?= $conv['outcome_badge']['class'] ?>">
                                <i class="ki-duotone ki-<?= $conv['outcome_badge']['icon'] ?> fs-2x"></i>
                            </div>
                            <div class="fs-8 text-gray-600"><?= $conv['outcome_badge']['text'] ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($conv['sales_value'] > 0): ?>
                    <div class="col-md-2">
                        <div class="bg-light-success rounded p-3 text-center">
                            <div class="fs-5 fw-bold text-success">R$ <?= $conv['sales_value_formatted'] ?></div>
                            <div class="fs-8 text-gray-600">Valor Venda</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($conv['performance_improvement_score']): ?>
                    <div class="col-md-2">
                        <div class="bg-light-warning rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-warning"><?= $conv['performance_improvement_score_formatted'] ?></div>
                            <div class="fs-8 text-gray-600">Melhoria</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($conv['suggestions_used'])): ?>
                    <div class="col-md-2">
                        <div class="bg-light-dark rounded p-3 text-center">
                            <div class="fs-2x fw-bold text-dark"><?= $conv['suggestions_used'] ?></div>
                            <div class="fs-8 text-gray-600">Sugestões Usadas</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- 10 Dimensões de Performance -->
                <?php if ($conv['overall_score']): ?>
                <div class="separator my-3"></div>
                <div class="fs-6 fw-bold text-gray-700 mb-3">📊 Dimensões de Performance</div>
                <div class="row g-2">
                    <?php
                    $dimensions = [
                        'proactivity_score' => '🎯 Proatividade',
                        'objection_handling_score' => '🛡️ Quebra de Objeções',
                        'rapport_score' => '🤝 Rapport',
                        'closing_techniques_score' => '✅ Técnicas de Fechamento',
                        'qualification_score' => '🔍 Qualificação',
                        'clarity_score' => '💬 Clareza',
                        'value_proposition_score' => '💎 Proposta de Valor',
                        'response_time_score' => '⚡ Tempo de Resposta',
                        'follow_up_score' => '📅 Follow-up',
                        'professionalism_score' => '🎩 Profissionalismo'
                    ];
                    
                    foreach ($dimensions as $key => $label):
                        if (!empty($conv[$key])):
                            $score = (float)$conv[$key];
                            $scoreColor = $score >= 4 ? 'success' : ($score >= 3 ? 'primary' : ($score >= 2 ? 'warning' : 'danger'));
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <span class="fs-8 text-gray-700"><?= $label ?></span>
                            <span class="badge badge-light-<?= $scoreColor ?> fs-8"><?= number_format($score, 1) ?>/5</span>
                        </div>
                    </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
                <?php endif; ?>
                
                <!-- Pontos Fortes e Fracos -->
                <?php if (!empty($conv['strengths']) || !empty($conv['weaknesses'])): ?>
                <div class="separator my-3"></div>
                <div class="row g-3">
                    <?php if (!empty($conv['strengths'])): ?>
                    <div class="col-md-6">
                        <div class="fs-7 fw-bold text-success mb-2">✅ Pontos Fortes</div>
                        <ul class="fs-8 text-gray-600 mb-0">
                            <?php foreach (array_slice($conv['strengths'], 0, 3) as $strength): ?>
                            <li><?= htmlspecialchars($strength) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($conv['strengths']) > 3): ?>
                            <li class="text-primary"><em>+ <?= count($conv['strengths']) - 3 ?> mais...</em></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($conv['weaknesses'])): ?>
                    <div class="col-md-6">
                        <div class="fs-7 fw-bold text-danger mb-2">⚠️ Pontos a Melhorar</div>
                        <ul class="fs-8 text-gray-600 mb-0">
                            <?php foreach (array_slice($conv['weaknesses'], 0, 3) as $weakness): ?>
                            <li><?= htmlspecialchars($weakness) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($conv['weaknesses']) > 3): ?>
                            <li class="text-primary"><em>+ <?= count($conv['weaknesses']) - 3 ?> mais...</em></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Botão Carregar Mais -->
        <?php if ($analyzedConversations['has_more']): ?>
        <div class="text-center mt-5">
            <button 
                id="loadMoreConversations" 
                class="btn btn-light-primary" 
                data-page="2"
                data-agent-id="<?= $agent['id'] ?>">
                <i class="ki-duotone ki-arrow-down fs-3"><span class="path1"></span><span class="path2"></span></i>
                Carregar Mais Conversas
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Paginação de conversas analisadas
document.getElementById('loadMoreConversations')?.addEventListener('click', async function() {
    const btn = this;
    const page = parseInt(btn.dataset.page);
    const agentId = btn.dataset.agentId;
    
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Carregando...';
    
    try {
        const url = new URL('<?= Url::to('/api/coaching/analyzed-conversations') ?>', window.location.origin);
        url.searchParams.append('page', page);
        url.searchParams.append('period', 'month');
        url.searchParams.append('agent_id', agentId);
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data.conversations) {
            const conversationsHtml = renderConversations(result.data.conversations);
            document.getElementById('analyzedConversationsList').insertAdjacentHTML('beforeend', conversationsHtml);
            
            if (result.data.has_more) {
                btn.dataset.page = page + 1;
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            } else {
                btn.remove();
            }
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao carregar mais conversas.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});

function renderConversations(conversations) {
    // Mesma função de renderização do dashboard de coaching
    return conversations.map(conv => {
        const dimensions = {
            'proactivity_score': '🎯 Proatividade',
            'objection_handling_score': '🛡️ Quebra de Objeções',
            'rapport_score': '🤝 Rapport',
            'closing_techniques_score': '✅ Técnicas de Fechamento',
            'qualification_score': '🔍 Qualificação',
            'clarity_score': '💬 Clareza',
            'value_proposition_score': '💎 Proposta de Valor',
            'response_time_score': '⚡ Tempo de Resposta',
            'follow_up_score': '📅 Follow-up',
            'professionalism_score': '🎩 Profissionalismo'
        };
        
        let dimensionsHtml = '';
        if (conv.overall_score) {
            dimensionsHtml = '<div class="separator my-3"></div><div class="fs-6 fw-bold text-gray-700 mb-3">📊 Dimensões de Performance</div><div class="row g-2">';
            for (const [key, label] of Object.entries(dimensions)) {
                if (conv[key]) {
                    const score = parseFloat(conv[key]);
                    const scoreColor = score >= 4 ? 'success' : (score >= 3 ? 'primary' : (score >= 2 ? 'warning' : 'danger'));
                    dimensionsHtml += `
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                <span class="fs-8 text-gray-700">${label}</span>
                                <span class="badge badge-light-${scoreColor} fs-8">${score.toFixed(1)}/5</span>
                            </div>
                        </div>`;
                }
            }
            dimensionsHtml += '</div>';
        }
        
        const createdDate = new Date(conv.created_at).toLocaleDateString('pt-BR') + ' ' + new Date(conv.created_at).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        
        return `
            <div class="border border-gray-300 rounded p-5 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <a href="<?= Url::to('/conversations/') ?>${conv.id}" class="text-gray-900 fw-bold fs-5 text-hover-primary me-2" target="_blank">
                                #${conv.id} - ${conv.contact_name || 'Sem nome'}
                            </a>
                            <span class="badge badge-light-${conv.status_badge.class} fs-8">${conv.status_badge.text}</span>
                        </div>
                        <div class="text-gray-600 fs-7">
                            <i class="ki-duotone ki-calendar fs-6"><span class="path1"></span><span class="path2"></span></i>
                            ${createdDate}
                            <span class="mx-2">•</span>
                            ${conv.channel.charAt(0).toUpperCase() + conv.channel.slice(1)}
                        </div>
                    </div>
                    <button class="btn btn-sm btn-light-primary" onclick="showConversationDetails(${conv.id})">
                        <i class="ki-duotone ki-eye fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Ver Detalhes
                    </button>
                </div>
                
                <div class="row g-3 mb-4">
                    ${conv.overall_score ? `<div class="col-md-2"><div class="bg-light-primary rounded p-3 text-center"><div class="fs-2x fw-bold text-primary">${conv.overall_score_formatted}</div><div class="fs-8 text-gray-600">Score Geral</div></div></div>` : ''}
                    ${conv.total_hints ? `<div class="col-md-2"><div class="bg-light-info rounded p-3 text-center"><div class="fs-2x fw-bold text-info">${conv.total_hints}</div><div class="fs-8 text-gray-600">Hints Dados</div><div class="fs-9 text-success">✓ ${conv.hints_helpful || 0} úteis</div></div></div>` : ''}
                    ${conv.conversation_outcome ? `<div class="col-md-2"><div class="bg-light-${conv.outcome_badge.class} rounded p-3 text-center"><div class="fs-5 fw-bold text-${conv.outcome_badge.class}"><i class="ki-duotone ki-${conv.outcome_badge.icon} fs-2x"></i></div><div class="fs-8 text-gray-600">${conv.outcome_badge.text}</div></div></div>` : ''}
                    ${conv.sales_value > 0 ? `<div class="col-md-2"><div class="bg-light-success rounded p-3 text-center"><div class="fs-5 fw-bold text-success">R$ ${conv.sales_value_formatted}</div><div class="fs-8 text-gray-600">Valor Venda</div></div></div>` : ''}
                    ${conv.performance_improvement_score ? `<div class="col-md-2"><div class="bg-light-warning rounded p-3 text-center"><div class="fs-2x fw-bold text-warning">${conv.performance_improvement_score_formatted}</div><div class="fs-8 text-gray-600">Melhoria</div></div></div>` : ''}
                </div>
                
                ${dimensionsHtml}
            </div>
        `;
    }).join('');
}

// Modal de detalhes (mesma função do dashboard de coaching)
async function showConversationDetails(conversationId) {
    try {
        const url = new URL('<?= Url::to('/api/coaching/analyzed-conversations') ?>', window.location.origin);
        url.searchParams.append('conversation_id', conversationId);
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success && result.data.conversations?.length > 0) {
            const conv = result.data.conversations[0];
            
            const modalHtml = `
                <div class="modal fade" id="conversationDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title">Análise Detalhada - Conversa #${conv.id}</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${conv.coaching_hints?.length ? `
                                <div class="mb-5">
                                    <h5 class="mb-3">💡 Hints de Coaching (${conv.coaching_hints.length})</h5>
                                    <div class="accordion" id="hintsAccordion">
                                        ${conv.coaching_hints.map((hint, index) => `
                                            <div class="accordion-item">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button ${index > 0 ? 'collapsed' : ''}" type="button" data-bs-toggle="collapse" data-bs-target="#hint${hint.id}">
                                                        <span class="badge badge-light-${hint.feedback === 'helpful' ? 'success' : (hint.feedback === 'not_helpful' ? 'danger' : 'secondary')} me-2">
                                                            ${hint.feedback === 'helpful' ? '✓ Útil' : (hint.feedback === 'not_helpful' ? '✗ Não útil' : '⚪ Sem feedback')}
                                                        </span>
                                                        ${hint.hint_type.replace(/_/g, ' ').toUpperCase()} - ${new Date(hint.created_at).toLocaleTimeString('pt-BR')}
                                                    </button>
                                                </h2>
                                                <div id="hint${hint.id}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}" data-bs-parent="#hintsAccordion">
                                                    <div class="accordion-body">
                                                        <div class="mb-3">${hint.hint_text}</div>
                                                        ${hint.suggestions ? `<div class="border-top pt-3"><div class="fw-bold mb-2">Sugestões:</div><pre class="bg-light p-3 rounded">${JSON.stringify(JSON.parse(hint.suggestions), null, 2)}</pre></div>` : ''}
                                                    </div>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                                ` : ''}
                                
                                <div class="d-flex justify-content-end">
                                    <a href="<?= Url::to('/conversations/') ?>${conv.id}" target="_blank" class="btn btn-primary">Ver Conversa Completa</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const existingModal = document.getElementById('conversationDetailsModal');
            if (existingModal) existingModal.remove();
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('conversationDetailsModal'));
            modal.show();
            
            document.getElementById('conversationDetailsModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao carregar detalhes da conversa.');
    }
}
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
