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
                            $onlineMinutes = $availabilityStats['total_online_minutes'] ?? 0;
                            echo $onlineMinutes >= 60 ? round($onlineMinutes / 60, 1) . 'h' : $onlineMinutes . 'min';
                            ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">Tempo Online</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-warning">
                            <?php
                            $awayMinutes = $availabilityStats['total_away_minutes'] ?? 0;
                            echo $awayMinutes >= 60 ? round($awayMinutes / 60, 1) . 'h' : $awayMinutes . 'min';
                            ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">Tempo Ausente</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-danger">
                            <?php
                            $busyMinutes = $availabilityStats['total_busy_minutes'] ?? 0;
                            echo $busyMinutes >= 60 ? round($busyMinutes / 60, 1) . 'h' : $busyMinutes . 'min';
                            ?>
                        </div>
                        <div class="fw-semibold text-muted fs-7">Tempo Ocupado</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 text-center">
                        <div class="fs-3 fw-bold text-primary">
                            <?= number_format($availabilityStats['availability_rate'] ?? 0, 1) ?>%
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
                                R$ <?= number_format($conversionMetrics['avg_order_value'] ?? 0, 2, ',', '.') ?>
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

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
