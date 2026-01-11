<?php
$layout = 'layouts.metronic.app';
$title = $title ?? 'Conversão WooCommerce';

ob_start();
?>

<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack pb-7">
    <div class="d-flex flex-wrap align-items-center my-1">
        <h3 class="fw-bold me-5 my-1">
            <i class="ki-duotone ki-chart-line-up fs-2 text-success me-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <?= $title ?>
        </h3>
    </div>
    <div class="d-flex align-items-center my-1">
        <!--begin::Filter-->
        <div class="d-flex align-items-center me-2">
            <input type="date" id="filter-date-from" class="form-control form-control-sm me-2" value="<?= $dateFrom ?>" />
            <span class="mx-2">até</span>
            <input type="date" id="filter-date-to" class="form-control form-control-sm me-2" value="<?= $dateTo ?>" />
            <button type="button" class="btn btn-sm btn-primary" onclick="filterByDate()">
                <i class="ki-duotone ki-filter fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Filtrar
            </button>
        </div>
        <!--end::Filter-->
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Row - Overview-->
<div class="row g-5 g-xl-10 mb-5 mb-xl-10">
    <?php
    // Calcular totais gerais
    $totalConversations = 0;
    $totalOrders = 0;
    $totalRevenue = 0;
    
    foreach ($ranking as $agent) {
        $totalConversations += $agent['total_conversations'] ?? 0;
        $totalOrders += $agent['total_orders'] ?? 0;
        $totalRevenue += $agent['total_revenue'] ?? 0;
    }
    
    $avgConversionRate = $totalConversations > 0 
        ? round(($totalOrders / $totalConversations) * 100, 2) 
        : 0;
    
    $avgTicket = $totalOrders > 0 
        ? round($totalRevenue / $totalOrders, 2) 
        : 0;
    ?>
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-profile-user fs-3x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800"><?= count($sellers) ?></span>
                    <span class="text-gray-500 fw-semibold fs-6">Vendedores Ativos</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-message-text-2 fs-3x text-info mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800"><?= $totalConversations ?></span>
                    <span class="text-gray-500 fw-semibold fs-6">Total de Conversas</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-shop fs-3x text-success mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800"><?= $totalOrders ?></span>
                    <span class="text-gray-500 fw-semibold fs-6">Total de Vendas</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-chart-simple fs-3x text-warning mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800"><?= number_format($avgConversionRate, 1) ?>%</span>
                    <span class="text-gray-500 fw-semibold fs-6">Taxa Média de Conversão</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Ranking-->
<div class="row g-5 g-xl-10">
    <div class="col-xl-12">
        <div class="card card-flush">
            <div class="card-header pt-7">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-800">Ranking de Vendedores</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Ordenado por taxa de conversão</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <?php if (empty($ranking)): ?>
                    <div class="text-center py-10">
                        <p class="text-muted">Nenhum dado disponível para o período selecionado</p>
                        <p class="text-muted fs-7">Certifique-se de que há agentes com WooCommerce Seller ID cadastrado</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-6 gy-4">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                    <th class="w-10px pe-2">#</th>
                                    <th class="min-w-200px">Vendedor</th>
                                    <th class="min-w-80px text-center">ID WC</th>
                                    <th class="min-w-100px text-center">Conversas</th>
                                    <th class="min-w-100px text-center">Vendas</th>
                                    <th class="min-w-120px text-center">Taxa Conversão</th>
                                    <th class="min-w-120px text-end">Valor Total</th>
                                    <th class="min-w-120px text-end">Ticket Médio</th>
                                    <th class="text-end min-w-100px">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600">
                                <?php foreach ($ranking as $index => $agent): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-gray-800">#<?= $index + 1 ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="d-flex flex-column">
                                                <a href="/agent-conversion/agent?id=<?= $agent['agent_id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                                    <?= htmlspecialchars($agent['agent_name']) ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light"><?= $agent['seller_id'] ?? '-' ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-gray-800 fw-bold"><?= $agent['total_conversations'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light-success"><?= $agent['total_orders'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="fw-bold text-gray-800 fs-6 mb-1"><?= number_format($agent['conversion_rate'] ?? 0, 1) ?>%</span>
                                            <div class="progress h-6px w-100px">
                                                <div class="progress-bar bg-<?= ($agent['conversion_rate'] ?? 0) >= 30 ? 'success' : (($agent['conversion_rate'] ?? 0) >= 15 ? 'warning' : 'danger') ?>" 
                                                     role="progressbar" 
                                                     style="width: <?= min(100, $agent['conversion_rate'] ?? 0) ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-success fw-bold">
                                            <?= \App\Services\AgentConversionService::formatCurrency($agent['total_revenue'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-gray-800 fw-bold">
                                            <?= \App\Services\AgentConversionService::formatCurrency($agent['avg_ticket'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="/agent-conversion/agent?id=<?= $agent['agent_id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="btn btn-sm btn-light btn-active-light-primary">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->

<script>
// Filtrar por data
function filterByDate() {
    const dateFrom = document.getElementById('filter-date-from').value;
    const dateTo = document.getElementById('filter-date-to').value;
    
    window.location.href = `/agent-conversion?date_from=${dateFrom}&date_to=${dateTo}`;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
