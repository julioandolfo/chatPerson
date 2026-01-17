<?php
/**
 * View: Performance Completa de Agente
 * Métricas de atendimento, coaching, conversão e disponibilidade
 */

$layout = 'layouts.metronic.app';
$title = $title ?? 'Performance do Agente';

use App\Helpers\Url;

// Content
ob_start();

// Extrair dados
$acceptanceRate = $dashboard['acceptance_rate'] ?? [];
$learningSpeed = $dashboard['learning_speed'] ?? [];
$agent = $agent ?? [];
$agentMetrics = $agentMetrics ?? [];
$performanceStats = $performanceStats ?? [];
$availabilityStats = $availabilityStats ?? [];
$conversionMetrics = $conversionMetrics ?? [];
$slaSettings = $slaSettings ?? [];
$impactStats = $impactStats ?? [];

// SLA configurados
$slaFirstResponse = $slaSettings['first_response_time'] ?? 15;
$slaOngoingResponse = $slaSettings['ongoing_response_time'] ?? $slaFirstResponse;
?>

<!-- Cabeçalho -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6">
    <div>
        <a href="<?= Url::to('/coaching/dashboard') ?>" class="btn btn-sm btn-light-primary mb-3">
            <i class="ki-duotone ki-arrow-left fs-3">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Voltar ao Dashboard
        </a>
        <div class="d-flex align-items-center gap-4">
            <div class="symbol symbol-60px symbol-circle">
                <?php if (!empty($agent['avatar'])): ?>
                    <img src="<?= htmlspecialchars($agent['avatar']) ?>" alt="<?= htmlspecialchars($agent['name']) ?>">
                <?php else: ?>
                    <span class="symbol-label bg-light-primary text-primary fs-2x fw-bold">
                        <?= strtoupper(substr($agent['name'] ?? 'A', 0, 1)) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div>
                <h1 class="fs-2x fw-bold text-gray-900 mb-1">
                    <?= htmlspecialchars($agent['name'] ?? 'Agente') ?>
                </h1>
                <div class="d-flex align-items-center gap-3">
                    <?php
                    $statusColors = [
                        'online' => 'success',
                        'busy' => 'danger',
                        'away' => 'warning',
                        'offline' => 'secondary'
                    ];
                    $statusLabels = [
                        'online' => 'Online',
                        'busy' => 'Ocupado',
                        'away' => 'Ausente',
                        'offline' => 'Offline'
                    ];
                    $status = $agent['availability_status'] ?? 'offline';
                    $color = $statusColors[$status] ?? 'secondary';
                    $label = $statusLabels[$status] ?? 'Offline';
                    ?>
                    <span class="badge badge-<?= $color ?>"><?= $label ?></span>
                    <span class="text-muted fs-7"><?= htmlspecialchars($agent['email'] ?? '') ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex gap-2 mt-4 mt-md-0">
        <select id="periodFilter" class="form-select form-select-sm" style="width: 150px;">
            <option value="today" <?= $selectedPeriod === 'today' ? 'selected' : '' ?>>Hoje</option>
            <option value="week" <?= $selectedPeriod === 'week' ? 'selected' : '' ?>>Esta Semana</option>
            <option value="month" <?= $selectedPeriod === 'month' ? 'selected' : '' ?>>Este Mês</option>
        </select>
    </div>
</div>

<!-- KPIs Principais -->
<div class="row g-5 g-xl-8 mb-8">
    <!-- Conversas -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100 bg-light-primary">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol symbol-45px me-3">
                        <span class="symbol-label bg-primary">
                            <i class="ki-duotone ki-chat fs-2 text-white">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Total de Conversas</div>
                        <div class="fs-2x fw-bold text-gray-900"><?= number_format($agentMetrics['total_conversations'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="separator separator-dashed my-3"></div>
                <div class="d-flex justify-content-between text-muted fs-7">
                    <span><i class="ki-duotone ki-like fs-5 text-success"><span class="path1"></span><span class="path2"></span></i> Abertas: <?= number_format($agentMetrics['open_conversations'] ?? 0) ?></span>
                    <span>Fechadas: <?= number_format(($agentMetrics['resolved_conversations'] ?? 0) + ($agentMetrics['closed_conversations'] ?? 0)) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Taxa de Resolução -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100 bg-light-success">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol symbol-45px me-3">
                        <span class="symbol-label bg-success">
                            <i class="ki-duotone ki-check-circle fs-2 text-white">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Taxa de Resolução</div>
                        <div class="fs-2x fw-bold text-gray-900"><?= number_format($agentMetrics['resolution_rate'] ?? 0, 1) ?>%</div>
                    </div>
                </div>
                <div class="progress h-8px mt-3">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= min($agentMetrics['resolution_rate'] ?? 0, 100) ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SLA 1ª Resposta -->
    <div class="col-xl-3 col-md-6">
        <?php
        $slaRate = $agentMetrics['sla_first_response_rate'] ?? 0;
        $slaColor = $slaRate >= 80 ? 'success' : ($slaRate >= 50 ? 'warning' : 'danger');
        ?>
        <div class="card card-flush h-100 bg-light-<?= $slaColor ?>">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol symbol-45px me-3">
                        <span class="symbol-label bg-<?= $slaColor ?>">
                            <i class="ki-duotone ki-timer fs-2 text-white">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">SLA 1ª Resposta</div>
                        <div class="fs-2x fw-bold text-gray-900"><?= number_format($slaRate, 1) ?>%</div>
                    </div>
                </div>
                <div class="fs-7 text-gray-600">
                    Meta: <?= $slaFirstResponse ?> min | Média: <?= number_format($agentMetrics['avg_first_response_minutes'] ?? 0, 1) ?> min
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mensagens Enviadas -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-flush h-100 bg-light-info">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="symbol symbol-45px me-3">
                        <span class="symbol-label bg-info">
                            <i class="ki-duotone ki-sms fs-2 text-white">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                    </div>
                    <div>
                        <div class="fs-7 text-gray-600">Mensagens Enviadas</div>
                        <div class="fs-2x fw-bold text-gray-900"><?= number_format($performanceStats['total_messages'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="fs-7 text-gray-600">
                    Média: <?= number_format($performanceStats['avg_messages_per_conversation'] ?? 0, 1) ?> msg/conversa
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Métricas de Tempo e SLA -->
<div class="row g-5 g-xl-8 mb-8">
    <div class="col-xl-6">
        <div class="card card-flush h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-timer fs-2 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Métricas de Tempo
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex flex-wrap gap-4">
                    <!-- Tempo de 1ª Resposta -->
                    <div class="border border-dashed rounded p-4 flex-grow-1">
                        <div class="fs-7 text-gray-600 mb-1">Tempo 1ª Resposta</div>
                        <div class="fs-2 fw-bold text-gray-800">
                            <?php
                            $avgFirstSec = $agentMetrics['avg_first_response_seconds'] ?? 0;
                            if ($avgFirstSec > 0) {
                                if ($avgFirstSec < 60) {
                                    echo number_format($avgFirstSec, 0) . 's';
                                } else {
                                    echo number_format($avgFirstSec / 60, 1) . ' min';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                        <div class="fs-8 text-muted">Meta: <?= $slaFirstResponse ?> min</div>
                    </div>
                    
                    <!-- Tempo Médio de Resposta -->
                    <div class="border border-dashed rounded p-4 flex-grow-1">
                        <div class="fs-7 text-gray-600 mb-1">Tempo Médio Resposta</div>
                        <div class="fs-2 fw-bold text-gray-800">
                            <?php
                            $avgRespSec = $agentMetrics['avg_response_seconds'] ?? 0;
                            if ($avgRespSec > 0) {
                                if ($avgRespSec < 60) {
                                    echo number_format($avgRespSec, 0) . 's';
                                } else {
                                    echo number_format($avgRespSec / 60, 1) . ' min';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                        <div class="fs-8 text-muted">Meta: <?= $slaOngoingResponse ?> min</div>
                    </div>
                    
                    <!-- Tempo de Resolução -->
                    <div class="border border-dashed rounded p-4 flex-grow-1">
                        <div class="fs-7 text-gray-600 mb-1">Tempo Resolução</div>
                        <div class="fs-2 fw-bold text-gray-800">
                            <?php
                            $avgResHours = $agentMetrics['avg_resolution_hours'] ?? 0;
                            if ($avgResHours > 0) {
                                if ($avgResHours < 1) {
                                    echo number_format($avgResHours * 60, 0) . ' min';
                                } elseif ($avgResHours < 24) {
                                    echo number_format($avgResHours, 1) . 'h';
                                } else {
                                    echo number_format($avgResHours / 24, 1) . 'd';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                        <div class="fs-8 text-muted">Média geral</div>
                    </div>
                </div>
                
                <!-- SLA Detalhado -->
                <div class="separator separator-dashed my-5"></div>
                
                <div class="row g-3">
                    <div class="col-6">
                        <?php
                        $slaFirstRate = $agentMetrics['sla_first_response_rate'] ?? 0;
                        $slaFirstColor = $slaFirstRate >= 80 ? 'success' : ($slaFirstRate >= 50 ? 'warning' : 'danger');
                        ?>
                        <div class="d-flex align-items-center p-3 bg-light-<?= $slaFirstColor ?> rounded">
                            <div class="flex-grow-1">
                                <div class="fs-7 fw-bold text-gray-700">SLA 1ª Resposta</div>
                                <div class="fs-3 fw-bold text-<?= $slaFirstColor ?>"><?= number_format($slaFirstRate, 1) ?>%</div>
                            </div>
                            <div class="text-end">
                                <div class="fs-8 text-success"><?= $agentMetrics['first_response_within_sla'] ?? 0 ?> OK</div>
                                <div class="fs-8 text-danger"><?= ($agentMetrics['total_conversations'] ?? 0) - ($agentMetrics['first_response_within_sla'] ?? 0) ?> Fora</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <?php
                        $slaRespRate = $agentMetrics['sla_response_rate'] ?? 0;
                        $slaRespColor = $slaRespRate >= 80 ? 'success' : ($slaRespRate >= 50 ? 'warning' : 'danger');
                        ?>
                        <div class="d-flex align-items-center p-3 bg-light-<?= $slaRespColor ?> rounded">
                            <div class="flex-grow-1">
                                <div class="fs-7 fw-bold text-gray-700">SLA Respostas</div>
                                <div class="fs-3 fw-bold text-<?= $slaRespColor ?>"><?= number_format($slaRespRate, 1) ?>%</div>
                            </div>
                            <div class="text-end">
                                <div class="fs-8 text-success"><?= $agentMetrics['responses_within_sla'] ?? 0 ?> OK</div>
                                <div class="fs-8 text-danger"><?= ($agentMetrics['total_responses'] ?? 0) - ($agentMetrics['responses_within_sla'] ?? 0) ?> Fora</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Disponibilidade -->
    <div class="col-xl-6">
        <div class="card card-flush h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-profile-user fs-2 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Disponibilidade no Período
                </h3>
            </div>
            <div class="card-body pt-0">
                <?php if (!empty($availabilityStats)): ?>
                <div class="row g-3 mb-4">
                    <!-- Online -->
                    <div class="col-6 col-md-3">
                        <div class="border border-dashed rounded p-3 text-center">
                            <div class="symbol symbol-35px mb-2">
                                <span class="symbol-label bg-light-success">
                                    <i class="ki-duotone ki-check fs-4 text-success"></i>
                                </span>
                            </div>
                            <div class="fs-8 text-gray-600">Online</div>
                            <div class="fs-6 fw-bold text-gray-800"><?= $availabilityStats['online']['formatted'] ?? '0s' ?></div>
                            <div class="fs-8 text-success"><?= $availabilityStats['online']['percentage'] ?? 0 ?>%</div>
                        </div>
                    </div>
                    
                    <!-- Ausente -->
                    <div class="col-6 col-md-3">
                        <div class="border border-dashed rounded p-3 text-center">
                            <div class="symbol symbol-35px mb-2">
                                <span class="symbol-label bg-light-warning">
                                    <i class="ki-duotone ki-time fs-4 text-warning"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div class="fs-8 text-gray-600">Ausente</div>
                            <div class="fs-6 fw-bold text-gray-800"><?= $availabilityStats['away']['formatted'] ?? '0s' ?></div>
                            <div class="fs-8 text-warning"><?= $availabilityStats['away']['percentage'] ?? 0 ?>%</div>
                        </div>
                    </div>
                    
                    <!-- Ocupado -->
                    <div class="col-6 col-md-3">
                        <div class="border border-dashed rounded p-3 text-center">
                            <div class="symbol symbol-35px mb-2">
                                <span class="symbol-label bg-light-danger">
                                    <i class="ki-duotone ki-minus-circle fs-4 text-danger"><span class="path1"></span><span class="path2"></span></i>
                                </span>
                            </div>
                            <div class="fs-8 text-gray-600">Ocupado</div>
                            <div class="fs-6 fw-bold text-gray-800"><?= $availabilityStats['busy']['formatted'] ?? '0s' ?></div>
                            <div class="fs-8 text-danger"><?= $availabilityStats['busy']['percentage'] ?? 0 ?>%</div>
                        </div>
                    </div>
                    
                    <!-- Offline -->
                    <div class="col-6 col-md-3">
                        <div class="border border-dashed rounded p-3 text-center">
                            <div class="symbol symbol-35px mb-2">
                                <span class="symbol-label bg-light-secondary">
                                    <i class="ki-duotone ki-cross fs-4 text-gray-500"></i>
                                </span>
                            </div>
                            <div class="fs-8 text-gray-600">Offline</div>
                            <div class="fs-6 fw-bold text-gray-800"><?= $availabilityStats['offline']['formatted'] ?? '0s' ?></div>
                            <div class="fs-8 text-muted"><?= $availabilityStats['offline']['percentage'] ?? 0 ?>%</div>
                        </div>
                    </div>
                </div>
                
                <!-- Barra de disponibilidade -->
                <?php 
                $totalPerc = ($availabilityStats['online']['percentage'] ?? 0) + 
                             ($availabilityStats['away']['percentage'] ?? 0) + 
                             ($availabilityStats['busy']['percentage'] ?? 0) + 
                             ($availabilityStats['offline']['percentage'] ?? 0);
                if ($totalPerc > 0):
                ?>
                <div class="d-flex h-10px rounded overflow-hidden">
                    <div class="bg-success" style="width: <?= $availabilityStats['online']['percentage'] ?? 0 ?>%"></div>
                    <div class="bg-warning" style="width: <?= $availabilityStats['away']['percentage'] ?? 0 ?>%"></div>
                    <div class="bg-danger" style="width: <?= $availabilityStats['busy']['percentage'] ?? 0 ?>%"></div>
                    <div class="bg-secondary" style="width: <?= $availabilityStats['offline']['percentage'] ?? 0 ?>%"></div>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="ki-duotone ki-information fs-3x text-gray-400 mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>Sem dados de disponibilidade para o período</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Métricas de Conversão (se for vendedor) -->
<?php if (!empty($conversionMetrics) && !empty($conversionMetrics['seller_id'])): ?>
<div class="row g-5 g-xl-8 mb-8">
    <div class="col-12">
        <div class="card card-flush bg-light-success">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-dollar fs-2 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Métricas de Conversão (Vendas)
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="row g-4">
                    <!-- Taxa de Conversão -->
                    <div class="col-xl-3 col-md-6">
                        <div class="bg-white rounded p-4 text-center h-100">
                            <div class="fs-7 text-gray-600 mb-2">Taxa de Conversão</div>
                            <div class="fs-2x fw-bold text-success"><?= number_format($conversionMetrics['conversion_rate'] ?? 0, 1) ?>%</div>
                            <div class="fs-8 text-muted"><?= $conversionMetrics['total_orders'] ?? 0 ?> de <?= $conversionMetrics['total_conversations'] ?? 0 ?> conversas</div>
                        </div>
                    </div>
                    
                    <!-- Total de Vendas -->
                    <div class="col-xl-3 col-md-6">
                        <div class="bg-white rounded p-4 text-center h-100">
                            <div class="fs-7 text-gray-600 mb-2">Total de Vendas</div>
                            <div class="fs-2x fw-bold text-gray-800"><?= $conversionMetrics['total_orders'] ?? 0 ?></div>
                            <div class="fs-8 text-muted">pedidos no período</div>
                        </div>
                    </div>
                    
                    <!-- Faturamento -->
                    <div class="col-xl-3 col-md-6">
                        <div class="bg-white rounded p-4 text-center h-100">
                            <div class="fs-7 text-gray-600 mb-2">Faturamento</div>
                            <div class="fs-2x fw-bold text-success">R$ <?= number_format($conversionMetrics['total_revenue'] ?? 0, 2, ',', '.') ?></div>
                            <div class="fs-8 text-muted">total em vendas</div>
                        </div>
                    </div>
                    
                    <!-- Ticket Médio -->
                    <div class="col-xl-3 col-md-6">
                        <div class="bg-white rounded p-4 text-center h-100">
                            <div class="fs-7 text-gray-600 mb-2">Ticket Médio</div>
                            <div class="fs-2x fw-bold text-gray-800">R$ <?= number_format($conversionMetrics['avg_ticket'] ?? 0, 2, ',', '.') ?></div>
                            <div class="fs-8 text-muted">por venda</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Métricas de Coaching -->
<div class="row g-5 g-xl-8 mb-8">
    <div class="col-xl-6">
        <div class="card card-flush h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-teacher fs-2 text-primary me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Coaching em Tempo Real
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3 mb-4">
                    <!-- Taxa de Aceitação -->
                    <div class="col-6">
                        <div class="border border-dashed rounded p-4 text-center">
                            <div class="fs-2x fw-bold text-primary"><?= $acceptanceRate['acceptance_rate'] ?? 0 ?>%</div>
                            <div class="fs-7 text-gray-600">Taxa de Aceitação</div>
                            <div class="progress h-6px mt-2">
                                <div class="progress-bar bg-primary" style="width: <?= $acceptanceRate['acceptance_rate'] ?? 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Velocidade de Aprendizado -->
                    <div class="col-6">
                        <?php
                        $speed = $learningSpeed['learning_speed'] ?? 0;
                        $speedStatus = $learningSpeed['status'] ?? 'neutral';
                        $speedColor = $speedStatus === 'excellent' ? 'success' : ($speedStatus === 'good' ? 'primary' : 'warning');
                        $speedLabel = $speedStatus === 'excellent' ? '🚀 Excelente' : ($speedStatus === 'good' ? '✓ Bom' : '⚠ Atenção');
                        ?>
                        <div class="border border-dashed rounded p-4 text-center">
                            <div class="fs-2x fw-bold text-<?= $speedColor ?>"><?= $speed ?>%</div>
                            <div class="fs-7 text-gray-600">Velocidade de Aprendizado</div>
                            <div class="badge badge-light-<?= $speedColor ?> mt-2"><?= $speedLabel ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between text-muted fs-7 p-3 bg-light rounded">
                    <span>📝 <?= $acceptanceRate['total_hints'] ?? 0 ?> hints recebidos</span>
                    <span>✅ <?= $acceptanceRate['helpful_hints'] ?? 0 ?> úteis</span>
                    <span>❌ <?= ($acceptanceRate['total_hints'] ?? 0) - ($acceptanceRate['helpful_hints'] ?? 0) ?> ignorados</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Impacto em Vendas -->
    <div class="col-xl-6">
        <div class="card card-flush h-100">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title fw-bold">
                    <i class="ki-duotone ki-chart-simple fs-2 text-success me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Impacto do Coaching
                </h3>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="border border-dashed rounded p-4 text-center">
                            <div class="fs-2x fw-bold text-success">R$ <?= number_format($impactStats['total_sales'] ?? 0, 0, ',', '.') ?></div>
                            <div class="fs-7 text-gray-600">Vendas com Coaching</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border border-dashed rounded p-4 text-center">
                            <div class="fs-2x fw-bold text-gray-800"><?= $impactStats['converted_conversations'] ?? 0 ?></div>
                            <div class="fs-7 text-gray-600">Conversões</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico de Evolução -->
<div class="card card-flush mb-8">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title fw-bold">
            <i class="ki-duotone ki-graph-up fs-2 text-info me-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
                <span class="path6"></span>
            </i>
            Evolução da Performance
        </h3>
        <div class="card-toolbar">
            <span class="badge badge-light-primary">Últimos 30 dias</span>
        </div>
    </div>
    <div class="card-body">
        <canvas id="performanceChart" height="80"></canvas>
    </div>
</div>

<!-- Conversas Recentes -->
<div class="card card-flush">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title fw-bold">
            <i class="ki-duotone ki-message-text fs-2 text-primary me-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            Conversas com Coaching
        </h3>
    </div>
    <div class="card-body">
        <?php if (!empty($conversations)): ?>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-gray-600 fs-7 text-uppercase">
                        <th>Conversa</th>
                        <th class="text-center">Hints</th>
                        <th class="text-center">Resultado</th>
                        <th class="text-end">Valor</th>
                        <th class="text-center">Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conversations as $conv): ?>
                    <tr>
                        <td>
                            <a href="<?= Url::to('/conversations/' . $conv['conversation_id']) ?>" class="text-gray-900 fw-bold text-hover-primary" target="_blank">
                                #<?= $conv['conversation_id'] ?> - <?= htmlspecialchars($conv['contact_name'] ?? 'Sem nome') ?>
                            </a>
                            <div class="text-gray-600 fs-7"><?= date('d/m/Y H:i', strtotime($conv['created_at'])) ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light-primary"><?= $conv['total_hints'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-light-<?= $conv['conversation_outcome'] === 'converted' ? 'success' : ($conv['conversation_outcome'] === 'lost' ? 'danger' : 'warning') ?>">
                                <?= $conv['conversation_outcome'] === 'converted' ? '✓ Convertida' : ($conv['conversation_outcome'] === 'lost' ? '✗ Perdida' : ucfirst($conv['conversation_outcome'] ?? 'pending')) ?>
                            </span>
                        </td>
                        <td class="text-end text-success fw-bold">
                            <?= ($conv['sales_value'] ?? 0) > 0 ? 'R$ ' . number_format($conv['sales_value'], 2, ',', '.') : '-' ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($conv['agent_feedback'])): ?>
                            <span class="badge badge-light-<?= $conv['agent_feedback'] === 'very_helpful' ? 'success' : ($conv['agent_feedback'] === 'helpful' ? 'primary' : 'warning') ?>">
                                <?= $conv['agent_feedback'] === 'very_helpful' ? '🌟 Muito útil' : ($conv['agent_feedback'] === 'helpful' ? '👍 Útil' : '😐 Neutro') ?>
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center text-gray-600 py-10">
            <i class="ki-duotone ki-message-question fs-3x text-gray-400 mb-3">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div>Nenhuma conversa com coaching neste período</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico de Performance
const ctx = document.getElementById('performanceChart');

fetch(`<?= Url::to('/api/coaching/dashboard/history') ?>?agent_id=<?= $agent['id'] ?>&period=daily&limit=30`)
    .then(response => response.json())
    .then(result => {
        if (result.success && result.data) {
            new Chart(ctx, {
                type: 'line',
                data: result.data,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    })
    .catch(error => console.error('Erro ao carregar gráfico:', error));

// Filtro de período
document.getElementById('periodFilter')?.addEventListener('change', function() {
    const period = this.value;
    window.location.href = `<?= Url::to('/coaching/agent/' . $agent['id']) ?>?period=${period}`;
});
</script>

<?php
// Fim do conteúdo
$content = ob_get_clean();

// Incluir layout
include __DIR__ . '/../' . str_replace('.', '/', $layout) . '.php';
?>
