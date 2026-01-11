<?php
$layout = 'layouts.metronic.app';
$title = 'Detalhes de Conversão - ' . htmlspecialchars($agent['name'] ?? 'Agente');

ob_start();
?>

<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack pb-7">
    <div class="d-flex flex-wrap align-items-center my-1">
        <a href="/agent-conversion?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="btn btn-sm btn-light me-3">
            <i class="ki-duotone ki-arrow-left fs-3">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Voltar
        </a>
        <h3 class="fw-bold me-5 my-1">
            <?= htmlspecialchars($agent['name'] ?? 'Agente') ?>
        </h3>
        <?php if (!empty($agent['woocommerce_seller_id'])): ?>
            <span class="badge badge-light-primary me-2">ID WC: <?= $agent['woocommerce_seller_id'] ?></span>
        <?php endif; ?>
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

<!--begin::Row - Métricas-->
<div class="row g-5 g-xl-10 mb-5 mb-xl-10">
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
                    <span class="fs-2hx fw-bold text-gray-800"><?= $metrics['total_conversations'] ?? 0 ?></span>
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
                    <span class="fs-2hx fw-bold text-gray-800"><?= $metrics['total_orders'] ?? 0 ?></span>
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
                    <span class="fs-2hx fw-bold text-gray-800"><?= number_format($metrics['conversion_rate'] ?? 0, 1) ?>%</span>
                    <span class="text-gray-500 fw-semibold fs-6">Taxa de Conversão</span>
                    <div class="progress h-8px w-100 mt-2">
                        <div class="progress-bar bg-<?= ($metrics['conversion_rate'] ?? 0) >= 30 ? 'success' : (($metrics['conversion_rate'] ?? 0) >= 15 ? 'warning' : 'danger') ?>" 
                             style="width: <?= min(100, $metrics['conversion_rate'] ?? 0) ?>%">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-dollar fs-3x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800">
                        <?= \App\Services\AgentConversionService::formatCurrency($metrics['total_revenue'] ?? 0) ?>
                    </span>
                    <span class="text-gray-500 fw-semibold fs-6">Valor Total</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Pedidos Recentes-->
<div class="row g-5 g-xl-10">
    <div class="col-xl-12">
        <div class="card card-flush">
            <div class="card-header pt-7">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-800">Pedidos Recentes</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Últimos pedidos vinculados a este vendedor</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-information-5 fs-3x text-gray-400 mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <p class="text-muted">Nenhum pedido encontrado para o período selecionado</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-6 gy-4">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-100px">Pedido</th>
                                    <th class="min-w-150px">Cliente</th>
                                    <th class="min-w-100px">Data</th>
                                    <th class="min-w-80px">Status</th>
                                    <th class="min-w-100px text-end">Valor</th>
                                    <th class="min-w-150px">Conversa Relacionada</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600">
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="<?= htmlspecialchars($order['woocommerce_url'] ?? '#') ?>" target="_blank" class="text-gray-800 text-hover-primary fw-bold">
                                            #<?= $order['order_id'] ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-800 fw-bold"><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></span>
                                            <span class="text-muted fs-7"><?= htmlspecialchars($order['customer_email'] ?? '') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-gray-800"><?= date('d/m/Y', strtotime($order['order_date'] ?? 'now')) ?></span>
                                        <span class="text-muted fs-7 d-block"><?= date('H:i', strtotime($order['order_date'] ?? 'now')) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'completed' => 'success',
                                            'processing' => 'primary',
                                            'pending' => 'warning',
                                            'on-hold' => 'info',
                                            'cancelled' => 'danger',
                                            'refunded' => 'danger',
                                            'failed' => 'danger',
                                        ];
                                        $statusLabels = [
                                            'completed' => 'Concluído',
                                            'processing' => 'Processando',
                                            'pending' => 'Pendente',
                                            'on-hold' => 'Em espera',
                                            'cancelled' => 'Cancelado',
                                            'refunded' => 'Reembolsado',
                                            'failed' => 'Falhou',
                                        ];
                                        $status = $order['status'] ?? 'pending';
                                        $color = $statusColors[$status] ?? 'secondary';
                                        $label = $statusLabels[$status] ?? ucfirst($status);
                                        ?>
                                        <span class="badge badge-light-<?= $color ?>"><?= $label ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-gray-800 fw-bold">
                                            <?= \App\Services\AgentConversionService::formatCurrency($order['total'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['conversation_id'])): ?>
                                            <a href="/conversations?id=<?= $order['conversation_id'] ?>" class="text-primary text-hover-info">
                                                <i class="ki-duotone ki-message-text-2 fs-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                Ver Conversa
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted fs-7">-</span>
                                        <?php endif; ?>
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
    const agentId = <?= $agent['id'] ?? 0 ?>;
    
    window.location.href = `/agent-conversion/agent?id=${agentId}&date_from=${dateFrom}&date_to=${dateTo}`;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
