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
                    <?php $slaBaseTotal = $agentMetrics['total_conversations_with_contact'] ?? ($agentMetrics['total_conversations'] ?? 0); ?>
                    <?= $agentMetrics['first_response_within_sla'] ?? 0 ?> de <?= $slaBaseTotal ?> dentro do SLA
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
                                    <?php $slaBaseTotal = $agentMetrics['total_conversations_with_contact'] ?? ($agentMetrics['total_conversations'] ?? 0); ?>
                                    <span class="fw-bold text-success"><?= $agentMetrics['first_response_within_sla'] ?? 0 ?></span>
                                    <span class="text-muted">/</span>
                                    <span class="fw-bold"><?= $slaBaseTotal ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Card de Conversas com SLA Excedido (1ª Resposta) -->
<div class="row g-5 mb-7">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">
                        <i class="ki-duotone ki-timer text-danger fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Conversas com SLA Excedido (1ª Resposta)
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">
                        Conversas que não tiveram a primeira resposta dentro do prazo
                    </span>
                </h3>
                <div class="card-toolbar">
                    <span class="badge badge-light-danger fs-5" id="sla-breached-count">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Carregando...
                    </span>
                </div>
            </div>
            <div class="card-body py-3">
                <div id="sla-breached-container">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div id="sla-breached-pagination"></div>
            </div>
        </div>
    </div>
</div>

<!-- Card de Conversas com SLA de Respostas Excedido -->
<div class="row g-5 mb-7">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-900">
                        <i class="ki-duotone ki-timer text-warning fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Conversas com SLA de Respostas Excedido
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">
                        Conversas com respostas acima do prazo durante o atendimento
                    </span>
                </h3>
                <div class="card-toolbar">
                    <span class="badge badge-light-warning fs-5" id="sla-ongoing-breached-count">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Carregando...
                    </span>
                </div>
            </div>
            <div class="card-body py-3">
                <div id="sla-ongoing-breached-container">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                <div id="sla-ongoing-breached-pagination"></div>
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

<!-- Alertas de Metas -->
<?php if (!empty($goalAlerts)): ?>
<div class="card card-flush mb-7 border-danger">
    <div class="card-header bg-light-danger">
        <h3 class="card-title text-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Alertas de Metas (<?= count($goalAlerts) ?>)
        </h3>
    </div>
    <div class="card-body">
        <?php foreach ($goalAlerts as $alert): 
            $severityColors = ['info' => 'primary', 'warning' => 'warning', 'critical' => 'danger'];
            $severityIcons = ['info' => 'info-circle', 'warning' => 'exclamation-triangle', 'critical' => 'x-octagon'];
        ?>
        <div class="alert alert-<?= $severityColors[$alert['severity']] ?> d-flex align-items-center mb-3">
            <i class="bi bi-<?= $severityIcons[$alert['severity']] ?>-fill fs-2x me-4"></i>
            <div class="flex-grow-1">
                <h5 class="mb-1"><?= htmlspecialchars($alert['goal_name']) ?></h5>
                <p class="mb-0"><?= htmlspecialchars($alert['message']) ?></p>
                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($alert['created_at'])) ?></small>
            </div>
        </div>
        <?php endforeach; ?>
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
    
<?php
$totalGoals = $goalsSummary['total_goals'] ?? 0;
$conditionLabels = \App\Models\GoalBonusCondition::CONDITION_TYPES;
$operatorLabels = \App\Models\GoalBonusCondition::OPERATORS;
?>
<?php if (!empty($goalsSummary) && $totalGoals > 0): ?>
    <div class="col-lg-<?= !empty($badges) ? '6' : '12' ?>">
        <div class="card h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title">
                    <i class="bi bi-flag-fill fs-2 text-primary me-2"></i>
                    Metas (<?= $totalGoals ?>)
                </h3>
                <div class="card-toolbar">
                    <a href="<?= Url::to('/goals/dashboard') ?>" class="btn btn-sm btn-light-primary">Ver Todas</a>
                </div>
            </div>
            <div class="card-body">
                <!-- Resumo -->
                <div class="row g-3 mb-5">
                    <div class="col-6">
                        <div class="text-center p-3 bg-light-success rounded">
                            <i class="bi bi-trophy-fill text-success fs-2x"></i>
                            <div class="text-gray-900 fw-bold fs-3 mt-2"><?= $goalsSummary['achieved'] ?? 0 ?></div>
                            <div class="text-muted fs-7">Atingidas</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 bg-light-primary rounded">
                            <i class="bi bi-graph-up fs-2x text-primary"></i>
                            <div class="text-gray-900 fw-bold fs-3 mt-2"><?= $goalsSummary['in_progress'] ?? 0 ?></div>
                            <div class="text-muted fs-7">Em Progresso</div>
                        </div>
                    </div>
                </div>
                
                <!-- Metas individuais -->
                <?php 
                $allGoals = [];
                foreach (($goalsSummary['goals_by_level'] ?? []) as $level => $levelGoals) {
                    $allGoals = array_merge($allGoals, $levelGoals);
                }
                if (!empty($allGoals)): 
                ?>
                <div class="separator my-4"></div>
                <?php foreach (array_slice($allGoals, 0, 3) as $goal): 
                    $progress = $goal['progress'] ?? null;
                    $percentage = $progress ? (float)$progress['percentage'] : 0;
                    $currentValue = $progress ? (float)$progress['current_value'] : 0;
                    
                    // Flag status
                    $flagStatus = $progress['flag_status'] ?? 'good';
                    $progressColor = \App\Models\Goal::getFlagColor($flagStatus);
                    $flagIcon = ['critical' => '🔴', 'warning' => '🟡', 'good' => '🟢', 'excellent' => '🔵'][$flagStatus] ?? '⚪';
                    
                    // Projeção
                    $isOnTrack = $progress['is_on_track'] ?? null;
                    $expectedPercentage = $progress['expected_percentage'] ?? null;
                    $projectedPercentage = $progress['projection_percentage'] ?? null;
                ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex align-items-center">
                                <span class="me-2"><?= $flagIcon ?></span>
                                <a href="<?= Url::to('/goals/show?id=' . $goal['id']) ?>" class="text-gray-800 text-hover-primary fw-bold fs-7">
                                    <?= htmlspecialchars($goal['name']) ?>
                                </a>
                            </div>
                            <span class="text-muted fs-8"><?= \App\Models\Goal::TYPES[$goal['type']]['label'] ?? $goal['type'] ?></span>
                            <?php if ($isOnTrack !== null): ?>
                                <span class="badge badge-light-<?= $isOnTrack ? 'success' : 'danger' ?> fs-9 mt-1">
                                    <?= $isOnTrack ? '✓ No ritmo' : '⚠ Fora do ritmo' ?>
                                    <?php if ($projectedPercentage !== null): ?>
                                        (Projeção: <?= number_format($projectedPercentage, 0) ?>%)
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <span class="text-gray-800 fw-bold fs-5"><?= number_format($percentage, 0) ?>%</span>
                            <?php if ($expectedPercentage !== null): ?>
                                <div class="text-muted fs-9">Esperado: <?= number_format($expectedPercentage, 0) ?>%</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="progress h-8px mb-1">
                        <div class="progress-bar bg-<?= $progressColor ?>" style="width: <?= min($percentage, 100) ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between fs-8 text-muted">
                        <span><?= \App\Models\Goal::formatValue($goal['type'], $currentValue) ?></span>
                        <span><?= \App\Models\Goal::formatValue($goal['type'], $goal['target_value']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($allGoals)): ?>
<?php foreach ($allGoals as $goalIndex => $goal): 
    $progress = $goal['progress'] ?? null;
    $percentage = $progress ? (float)$progress['percentage'] : 0;
    $currentValue = $progress ? (float)$progress['current_value'] : 0;
    $targetValue = (float)($goal['target_value'] ?? 0);
    $remainingValue = max(0, $targetValue - $currentValue);
    $flagStatus = $progress['flag_status'] ?? 'good';
    $progressColor = \App\Models\Goal::getFlagColor($flagStatus);
    $bonusPreview = $goal['bonus_preview'] ?? null;
    $tiers = $goal['tiers'] ?? [];
    $conditions = $goal['conditions'] ?? [];
    
    // Calcular projeção correta baseada em dias
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
    
    // Encontrar próximo tier
    $nextTier = null;
    $nextTierGap = 0;
    $nextTierValueGap = 0;
    if (!empty($tiers)) {
        usort($tiers, fn($a, $b) => $a['threshold_percentage'] <=> $b['threshold_percentage']);
        foreach ($tiers as $tier) {
            if ((float)$tier['threshold_percentage'] > $percentage) {
                $nextTier = $tier;
                $nextTierGap = (float)$tier['threshold_percentage'] - $percentage;
                $nextTierValueGap = ($targetValue * (float)$tier['threshold_percentage'] / 100) - $currentValue;
                break;
            }
        }
    }
    
    // Verificar condições bloqueadoras
    $blockedConditions = [];
    if (!empty($conditions)) {
        foreach ($conditions as $cond) {
            if (!empty($cond['is_required']) && empty($cond['is_met'])) {
                $blockedConditions[] = $cond;
            }
        }
    }
?>
<div class="card card-flush mb-5 border-start border-4 border-<?= $progressColor ?>">
    <div class="card-header bg-light-<?= $progressColor ?> py-4">
        <div class="d-flex align-items-center flex-grow-1">
            <div class="symbol symbol-50px me-4">
                <span class="symbol-label bg-<?= $progressColor ?> text-white fs-2 fw-bold">
                    <?= $goalIndex + 1 ?>
                </span>
            </div>
            <div class="flex-grow-1">
                <h3 class="card-title fw-bold mb-1"><?= htmlspecialchars($goal['name']) ?></h3>
                <span class="text-muted fs-7">
                    <?= \App\Models\Goal::TYPES[$goal['type']]['label'] ?? $goal['type'] ?> • 
                    <?= \App\Models\Goal::TARGET_TYPES[$goal['target_type']] ?? $goal['target_type'] ?> •
                    <?= date('d/m/Y', strtotime($goal['start_date'])) ?> → <?= date('d/m/Y', strtotime($goal['end_date'])) ?>
                </span>
            </div>
            <div class="text-end">
                <span class="fs-1 fw-bolder text-<?= $progressColor ?>"><?= number_format($percentage, 1) ?>%</span>
                <div class="text-muted fs-8">do alvo</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- Barra de Progresso Principal -->
        <div class="mb-6">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-gray-800 fw-bold"><?= \App\Models\Goal::formatValue($goal['type'], $currentValue) ?></span>
                <span class="text-gray-500"><?= \App\Models\Goal::formatValue($goal['type'], $targetValue) ?></span>
            </div>
            <div class="progress h-15px mb-2">
                <div class="progress-bar bg-<?= $progressColor ?>" style="width: <?= min($percentage, 100) ?>%"></div>
            </div>
            <div class="d-flex justify-content-between text-muted fs-8">
                <span>Falta: <strong class="text-gray-800"><?= \App\Models\Goal::formatValue($goal['type'], $remainingValue) ?></strong></span>
                <span>Restam <strong class="text-gray-800"><?= number_format($remainingDays, 0) ?></strong> dias</span>
            </div>
        </div>
        
        <!-- KPIs Principais -->
        <div class="row g-4 mb-6">
            <div class="col-md-3">
                <div class="bg-light-primary rounded p-4 text-center h-100">
                    <i class="bi bi-graph-up-arrow fs-2x text-primary mb-2"></i>
                    <div class="fs-2 fw-bold text-primary"><?= number_format($projectedPercentage, 0) ?>%</div>
                    <div class="text-muted fs-8">Projeção Final</div>
                    <div class="badge badge-light-<?= $isOnTrack ? 'success' : 'danger' ?> mt-2">
                        <?= $isOnTrack ? '✓ No ritmo' : '⚠ Abaixo' ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-light-info rounded p-4 text-center h-100">
                    <i class="bi bi-calendar-check fs-2x text-info mb-2"></i>
                    <div class="fs-2 fw-bold text-info"><?= \App\Models\Goal::formatValue($goal['type'], $dailyAverage) ?></div>
                    <div class="text-muted fs-8">Média Diária</div>
                    <div class="text-muted fs-9 mt-2">
                        Necessário: <?= \App\Models\Goal::formatValue($goal['type'], $remainingDays > 0 ? $remainingValue / $remainingDays : 0) ?>/dia
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-light-warning rounded p-4 text-center h-100">
                    <i class="bi bi-speedometer2 fs-2x text-warning mb-2"></i>
                    <div class="fs-2 fw-bold text-warning"><?= number_format($expectedPercentage, 0) ?>%</div>
                    <div class="text-muted fs-8">% Esperado Hoje</div>
                    <div class="text-muted fs-9 mt-2">
                        Diferença: <span class="text-<?= $percentage >= $expectedPercentage ? 'success' : 'danger' ?>">
                            <?= ($percentage >= $expectedPercentage ? '+' : '') . number_format($percentage - $expectedPercentage, 1) ?>%
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php if ($nextTier): ?>
                <div class="bg-light-success rounded p-4 text-center h-100">
                    <i class="bi bi-trophy fs-2x text-success mb-2"></i>
                    <div class="fs-4 fw-bold text-success"><?= htmlspecialchars($nextTier['tier_name']) ?></div>
                    <div class="text-muted fs-8">Próximo Bônus</div>
                    <div class="fs-7 mt-2">
                        Faltam <strong class="text-success"><?= number_format($nextTierGap, 1) ?>%</strong><br>
                        <span class="text-muted">(<?= \App\Models\Goal::formatValue($goal['type'], $nextTierValueGap) ?>)</span>
                    </div>
                    <div class="badge badge-success mt-2">
                        R$ <?= number_format((float)($nextTier['bonus_amount'] ?? 0), 2, ',', '.') ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-light-dark rounded p-4 text-center h-100">
                    <i class="bi bi-check-circle fs-2x text-gray-500 mb-2"></i>
                    <div class="fs-5 fw-bold text-gray-600">
                        <?= !empty($tiers) ? 'Todos Conquistados!' : 'Sem Tiers' ?>
                    </div>
                    <div class="text-muted fs-8">Bônus</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Alerta de Condições Bloqueadoras -->
        <?php if (!empty($blockedConditions)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-6">
            <i class="bi bi-exclamation-triangle-fill fs-2x me-3"></i>
            <div>
                <h5 class="mb-1">⚠️ Condições Bloqueando Bônus</h5>
                <ul class="mb-0 ps-3">
                    <?php foreach ($blockedConditions as $bc): ?>
                    <li><?= $conditionLabels[$bc['condition_type']] ?? $bc['condition_type'] ?>: 
                        precisa ser <?= $operatorLabels[$bc['operator']] ?? $bc['operator'] ?> 
                        <?= number_format((float)$bc['min_value'], 2, ',', '.') ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Detalhes de Bônus e Tiers -->
        <div class="row g-4">
            <!-- Tiers de Bônus -->
            <div class="col-md-6">
                <div class="border rounded p-4 h-100">
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-bar-chart-steps text-primary me-2"></i>
                        Tiers de Bônus
                    </h6>
                    <?php if (!empty($tiers)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-row-dashed mb-0">
                            <thead>
                                <tr class="text-muted fs-8">
                                    <th>Tier</th>
                                    <th class="text-center">Meta</th>
                                    <th class="text-center">Bônus</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tiers as $tier): 
                                    $tierReached = $percentage >= (float)$tier['threshold_percentage'];
                                    $tierTarget = $targetValue * (float)$tier['threshold_percentage'] / 100;
                                ?>
                                <tr class="<?= $tierReached ? 'bg-light-success' : '' ?>">
                                    <td class="fw-bold"><?= htmlspecialchars($tier['tier_name'] ?? 'Tier') ?></td>
                                    <td class="text-center">
                                        <?= number_format((float)$tier['threshold_percentage'], 0) ?>%
                                        <div class="text-muted fs-9">(<?= \App\Models\Goal::formatValue($goal['type'], $tierTarget) ?>)</div>
                                    </td>
                                    <td class="text-center fw-bold text-success">
                                        R$ <?= number_format((float)($tier['bonus_amount'] ?? 0), 2, ',', '.') ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($tierReached): ?>
                                            <span class="badge badge-success">✓ Alcançado</span>
                                        <?php else: ?>
                                            <span class="badge badge-light-warning">
                                                Falta <?= number_format((float)$tier['threshold_percentage'] - $percentage, 1) ?>%
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="text-muted text-center py-4">
                            <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                            Sem tiers de bônus configurados.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Condições e Resumo OTE -->
            <div class="col-md-6">
                <div class="border rounded p-4 h-100">
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-cash-coin text-success me-2"></i>
                        Bônus Estimado
                    </h6>
                    
                    <!-- Resumo do Bônus -->
                    <div class="bg-light-success rounded p-4 mb-4 text-center">
                        <div class="fs-6 text-muted mb-1">Bônus Atual Estimado</div>
                        <div class="fs-1 fw-bolder text-success">
                            R$ <?= number_format((float)($bonusPreview['total_bonus'] ?? 0), 2, ',', '.') ?>
                        </div>
                        <?php if (!empty($bonusPreview['last_tier'])): ?>
                        <div class="text-muted fs-8">
                            Tier atual: <?= htmlspecialchars($bonusPreview['last_tier']['tier_name'] ?? 'N/A') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Condições -->
                    <?php if (!empty($conditions)): ?>
                    <h6 class="fw-bold mb-2 fs-7">Condições de Ativação:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-row-dashed mb-0">
                            <tbody>
                                <?php foreach ($conditions as $cond): 
                                    $condMet = !empty($cond['is_met']);
                                ?>
                                <tr>
                                    <td class="<?= $condMet ? 'text-success' : 'text-danger' ?>">
                                        <?= $condMet ? '✓' : '✗' ?>
                                        <?= $conditionLabels[$cond['condition_type']] ?? $cond['condition_type'] ?>
                                    </td>
                                    <td class="text-end text-muted fs-8">
                                        <?= $operatorLabels[$cond['operator']] ?? $cond['operator'] ?> 
                                        <?= number_format((float)$cond['min_value'], 2, ',', '.') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- OTE Info -->
                    <?php if (!empty($goal['ote_total']) && $goal['ote_total'] > 0): ?>
                    <div class="separator my-3"></div>
                    <div class="d-flex justify-content-between fs-7">
                        <span class="text-muted">OTE Total:</span>
                        <span class="fw-bold">R$ <?= number_format((float)$goal['ote_total'], 2, ',', '.') ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

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
                            <a href="<?= Url::to('/conversations?id=' . $conv['id']) ?>" class="text-gray-900 fw-bold fs-5 text-hover-primary me-2" target="_blank">
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
                            <a href="<?= Url::to('/conversations?id=') ?>${conv.id}" class="text-gray-900 fw-bold fs-5 text-hover-primary me-2" target="_blank">
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
                                
                                ${conv.detailed_analysis ? `
                                <div class="mb-5">
                                    <h5 class="mb-3">📝 Análise Detalhada</h5>
                                    <div class="bg-light p-4 rounded">${conv.detailed_analysis}</div>
                                </div>
                                ` : ''}
                                
                                ${conv.strengths?.length ? `
                                <div class="mb-5">
                                    <h5 class="mb-3">✅ Pontos Fortes</h5>
                                    <div class="bg-light p-4 rounded">
                                        <ul class="mb-0">
                                            ${conv.strengths.map(s => `<li>${s}</li>`).join('')}
                                        </ul>
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${conv.weaknesses?.length ? `
                                <div class="mb-5">
                                    <h5 class="mb-3">⚠️ Pontos a Melhorar</h5>
                                    <div class="bg-light p-4 rounded">
                                        <ul class="mb-0">
                                            ${conv.weaknesses.map(w => `<li>${w}</li>`).join('')}
                                        </ul>
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${conv.improvement_suggestions?.length ? `
                                <div class="mb-5">
                                    <h5 class="mb-3">💡 Sugestões de Melhoria</h5>
                                    <div class="bg-light p-4 rounded">
                                        <ul class="mb-0">
                                            ${conv.improvement_suggestions.map(s => `<li>${s}</li>`).join('')}
                                        </ul>
                                    </div>
                                </div>
                                ` : ''}
                                
                                <div class="d-flex justify-content-end">
                                    <a href="<?= Url::to('/conversations?id=') ?>${conv.id}" target="_blank" class="btn btn-primary">Ver Conversa Completa</a>
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

// ========== CARREGAR CONVERSAS COM SLA EXCEDIDO ==========
let slaBreachedPage = 1;
const slaBreachedPerPage = 20;
let slaOngoingBreachedPage = 1;
const slaOngoingBreachedPerPage = 20;

function renderPagination(containerId, total, page, perPage, onPageChange) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    const prevDisabled = page <= 1 ? 'disabled' : '';
    const nextDisabled = page >= totalPages ? 'disabled' : '';
    
    container.innerHTML = `
        <div class="d-flex justify-content-end align-items-center gap-2 mt-4">
            <button class="btn btn-sm btn-light" ${prevDisabled} data-action="prev">Anterior</button>
            <span class="text-muted fs-7">Página ${page} de ${totalPages}</span>
            <button class="btn btn-sm btn-light" ${nextDisabled} data-action="next">Próxima</button>
        </div>
    `;
    
    container.querySelectorAll('button[data-action]').forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.getAttribute('data-action');
            if (action === 'prev' && page > 1) onPageChange(page - 1);
            if (action === 'next' && page < totalPages) onPageChange(page + 1);
        });
    });
}

function loadSLABreachedConversations(page = 1) {
    slaBreachedPage = page;
    const agentId = <?= $agent['id'] ?? 0 ?>;
    const dateFrom = '<?= $dateFrom ?>';
    const dateTo = '<?= $dateTo ?>';
    
    fetch(`<?= Url::to('/agent-performance/sla-breached') ?>?agent_id=${agentId}&date_from=${dateFrom}&date_to=${dateTo}&page=${slaBreachedPage}&per_page=${slaBreachedPerPage}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('sla-breached-container');
                const countBadge = document.getElementById('sla-breached-count');
                
                // Atualizar contador
                countBadge.innerHTML = `${data.total} conversas`;
                
                if (data.total === 0) {
                    container.innerHTML = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-check-circle fs-4x text-success mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <h3 class="text-gray-700 fw-bold mb-2">Nenhuma conversa com SLA excedido!</h3>
                            <p class="text-muted">Parabéns! Todas as conversas foram respondidas dentro do prazo.</p>
                        </div>
                    `;
                    renderPagination('sla-breached-pagination', data.total, data.page, data.per_page, loadSLABreachedConversations);
                    return;
                }
                
                // Criar tabela
                let html = `
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-4">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Conversa</th>
                                    <th>Contato</th>
                                    <th class="text-center">Tempo Decorrido</th>
                                    <th class="text-center">SLA</th>
                                    <th class="text-center">Excedido em</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.conversations.forEach(conv => {
                    const exceededBy = conv.exceeded_by || 0;
                    const percentage = conv.percentage || 0;
                    
                    // Cor da barra
                    let barColor = 'success';
                    if (percentage >= 100) barColor = 'danger';
                    else if (percentage >= 80) barColor = 'warning';
                    
                    html += `
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-gray-800">#${conv.id}</span>
                                    <span class="text-muted fs-7">${formatDateTime(conv.created_at)}</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-800">${conv.contact_name || 'Sem nome'}</span>
                                    <span class="text-muted fs-7">${conv.contact_phone || '-'}</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-${barColor} fs-6">
                                    ${conv.elapsed_minutes} min
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="text-muted fs-6">${conv.sla_minutes} min</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-danger fs-6">
                                    +${exceededBy.toFixed(0)} min
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-${conv.status_class} fs-6">
                                    ${conv.status_label}
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-light-info me-1" onclick="showSLABreachDetails(${conv.id}, 'first')">
                                    <i class="ki-duotone ki-information fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Detalhes
                                </button>
                                <a href="<?= Url::to('/conversations') ?>?id=${conv.id}" 
                                   class="btn btn-sm btn-light-primary" 
                                   target="_blank">
                                    <i class="ki-duotone ki-eye fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Ver
                                </a>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                container.innerHTML = html;
                renderPagination('sla-breached-pagination', data.total, data.page, data.per_page, loadSLABreachedConversations);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar conversas com SLA excedido:', error);
            document.getElementById('sla-breached-container').innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-cross-circle fs-2x text-danger me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Erro ao carregar dados
                </div>
            `;
        });
}

// ========== CARREGAR CONVERSAS COM SLA DE RESPOSTAS EXCEDIDO ==========
function loadSLAOngoingBreachedConversations() {
    slaOngoingBreachedPage = 1;
    loadSLAOngoingBreachedConversationsPage(slaOngoingBreachedPage);
}

function loadSLAOngoingBreachedConversationsPage(page = 1) {
    slaOngoingBreachedPage = page;
    const agentId = <?= $agent['id'] ?? 0 ?>;
    const dateFrom = '<?= $dateFrom ?>';
    const dateTo = '<?= $dateTo ?>';
    
    fetch(`<?= Url::to('/agent-performance/sla-breached') ?>?agent_id=${agentId}&date_from=${dateFrom}&date_to=${dateTo}&type=ongoing&page=${slaOngoingBreachedPage}&per_page=${slaOngoingBreachedPerPage}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('sla-ongoing-breached-container');
                const countBadge = document.getElementById('sla-ongoing-breached-count');
                
                // Atualizar contador
                countBadge.innerHTML = `${data.total} conversas`;
                
                if (data.total === 0) {
                    container.innerHTML = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-check-circle fs-4x text-success mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <h3 class="text-gray-700 fw-bold mb-2">Nenhuma conversa com SLA de respostas excedido!</h3>
                            <p class="text-muted">Ótimo! As respostas estão dentro do prazo.</p>
                        </div>
                    `;
                    renderPagination('sla-ongoing-breached-pagination', data.total, data.page, data.per_page, loadSLAOngoingBreachedConversationsPage);
                    return;
                }
                
                // Criar tabela
                let html = `
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-4">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                    <th>Conversa</th>
                                    <th>Contato</th>
                                    <th class="text-center">Maior Tempo</th>
                                    <th class="text-center">SLA</th>
                                    <th class="text-center">Excedido em</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.conversations.forEach(conv => {
                    const exceededBy = conv.exceeded_by || 0;
                    const percentage = conv.percentage || 0;
                    
                    // Cor da barra
                    let barColor = 'success';
                    if (percentage >= 100) barColor = 'danger';
                    else if (percentage >= 80) barColor = 'warning';
                    
                    html += `
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-gray-800">#${conv.id}</span>
                                    <span class="text-muted fs-7">${formatDateTime(conv.created_at)}</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-gray-800">${conv.contact_name || 'Sem nome'}</span>
                                    <span class="text-muted fs-7">${conv.contact_phone || '-'}</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-${barColor} fs-6">
                                    ${conv.elapsed_minutes} min
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="text-muted fs-6">${conv.sla_minutes} min</span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-danger fs-6">
                                    +${exceededBy.toFixed(0)} min
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-${conv.status_class} fs-6">
                                    ${conv.status_label}
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-light-info me-1" onclick="showSLABreachDetails(${conv.id}, 'ongoing')">
                                    <i class="ki-duotone ki-information fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Detalhes
                                </button>
                                <a href="<?= Url::to('/conversations') ?>?id=${conv.id}" 
                                   class="btn btn-sm btn-light-primary" 
                                   target="_blank">
                                    <i class="ki-duotone ki-eye fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Ver
                                </a>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                container.innerHTML = html;
                renderPagination('sla-ongoing-breached-pagination', data.total, data.page, data.per_page, loadSLAOngoingBreachedConversationsPage);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar conversas com SLA de respostas excedido:', error);
            document.getElementById('sla-ongoing-breached-container').innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-cross-circle fs-2x text-danger me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Erro ao carregar dados
                </div>
            `;
        });
}

// Helper para formatar data/hora
function formatDateTime(datetime) {
    if (!datetime) return '-';
    const date = new Date(datetime);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
}

// Modal de detalhes do SLA excedido
function showSLABreachDetails(conversationId, type = 'first') {
    const modalId = 'slaBreachDetailsModal';
    document.getElementById(modalId)?.remove();
    
    const title = type === 'ongoing' ? 'Detalhes do SLA de Respostas' : 'Detalhes do SLA de 1ª Resposta';
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="sla-breach-details-container" class="text-center py-8">
                            <div class="spinner-border text-primary"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
    
    const agentId = <?= $agent['id'] ?? 0 ?>;
    fetch(`<?= Url::to('/agent-performance/sla-breached-details') ?>?conversation_id=${conversationId}&type=${type}&agent_id=${agentId}`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('sla-breach-details-container');
            if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger">Erro ao carregar detalhes</div>`;
                return;
            }
            
            if (!data.total) {
                container.innerHTML = `<div class="text-muted">Nenhum intervalo encontrado.</div>`;
                return;
            }
            
            const rows = data.intervals.map((item) => {
                const contactTime = formatDateTime(item.contact_time);
                const agentTime = item.agent_time ? formatDateTime(item.agent_time) : 'Pendente';
                const agentName = item.agent_name || 'Agente';
                const minutes = item.minutes ?? 0;
                const exceededBy = item.exceeded_by ?? Math.max(0, minutes - data.sla_minutes);
                
                return `
                    <tr>
                        <td class="text-center">${contactTime}</td>
                        <td>
                            <div class="fw-semibold">Cliente</div>
                            <div class="text-muted fs-7">${escapeHtml(item.contact_preview || '')}</div>
                        </td>
                        <td class="text-center">${agentTime}</td>
                        <td>
                            <div class="fw-semibold">${escapeHtml(agentName)}</div>
                            <div class="text-muted fs-7">${escapeHtml(item.agent_preview || '')}</div>
                        </td>
                        <td class="text-center"><span class="badge badge-light-danger">${minutes} min</span></td>
                        <td class="text-center"><span class="badge badge-light-danger">+${Number(exceededBy).toFixed(0)} min</span></td>
                    </tr>
                `;
            }).join('');
            
            container.innerHTML = `
                <div class="mb-4 text-muted fs-7">
                    SLA: <strong>${data.sla_minutes} min</strong> • 
                    Delay: <strong>${data.delay_enabled ? (data.delay_minutes + ' min') : 'desativado'}</strong> • 
                    Horário comercial: <strong>${data.working_hours_enabled ? 'sim' : 'não'}</strong>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="text-center">Cliente</th>
                                <th>Mensagem Cliente</th>
                                <th class="text-center">Agente</th>
                                <th>Resposta Agente</th>
                                <th class="text-center">Tempo</th>
                                <th class="text-center">Excedido</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                    </table>
                </div>
            `;
        })
        .catch(() => {
            const container = document.getElementById('sla-breach-details-container');
            if (container) container.innerHTML = `<div class="alert alert-danger">Erro ao carregar detalhes</div>`;
        });
    
    document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Carregar ao abrir página
loadSLABreachedConversations();
loadSLAOngoingBreachedConversations();
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
