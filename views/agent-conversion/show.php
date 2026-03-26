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
<?php
    $clientInitiated = $metrics['conversations_client_initiated'] ?? 0;
    $agentInitiated = $metrics['conversations_agent_initiated'] ?? 0;
    $receptivasAtivas = $metrics['conversations_receptivas_ativas'] ?? 0;
    $interactiveConversations = $metrics['interactive_conversations'] ?? 0;
    $conversionRateClientOnly = $metrics['conversion_rate_client_only'] ?? 0;
    $conversionRateRecAtivas = $metrics['conversion_rate_receptivas_ativas'] ?? 0;
    $conversionRateInteractive = $metrics['conversion_rate_interactive'] ?? 0;

    $progressColorRec = $conversionRateClientOnly >= 30 ? 'success' : ($conversionRateClientOnly >= 15 ? 'warning' : 'danger');
    $progressColorRecAtivas = $conversionRateRecAtivas >= 30 ? 'success' : ($conversionRateRecAtivas >= 15 ? 'warning' : 'danger');
    $progressColorInteractive = $conversionRateInteractive >= 30 ? 'success' : ($conversionRateInteractive >= 15 ? 'warning' : 'danger');
?>
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
    
    <!--begin::Col - Três taxas (igual ao dashboard principal)-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column">
                <i class="ki-duotone ki-chart-simple fs-3x text-warning mb-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <span class="text-gray-500 fw-semibold fs-7 mb-0">Taxas de conversão</span>
                    <button type="button" class="btn btn-sm btn-light-primary py-1 js-wc-metric-breakdown"
                            data-agent-id="<?= (int)($agent['id'] ?? 0) ?>"
                            data-agent-name="<?= htmlspecialchars($agent['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">Ver origem</button>
                </div>
                <div class="d-flex flex-column gap-3 flex-grow-1">
                    <div>
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="fs-8 text-muted">Apenas receptivas</span>
                            <span class="fs-7 fw-bold text-<?= $progressColorRec ?>"><?= number_format($conversionRateClientOnly, 1) ?>%</span>
                        </div>
                        <div class="fs-9 text-muted mb-1"><?= number_format($clientInitiated) ?> conversas · cliente chamou</div>
                        <div class="progress h-4px">
                            <div class="progress-bar bg-<?= $progressColorRec ?>" style="width: <?= min(100, ($conversionRateClientOnly / 30) * 100) ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="fs-8 text-muted">Rec + ativas</span>
                            <span class="fs-7 fw-bold text-<?= $progressColorRecAtivas ?>"><?= number_format($conversionRateRecAtivas, 1) ?>%</span>
                        </div>
                        <div class="fs-9 text-muted mb-1"><?= number_format($receptivasAtivas) ?> novas no período
                            <span class="badge badge-light-info fs-10 py-1 ms-1"><i class="bi bi-chat-fill fs-9"></i> <?= number_format($clientInitiated) ?></span>
                            <span class="badge badge-light-primary fs-10 py-1"><i class="bi bi-person-fill fs-9"></i> <?= number_format($agentInitiated) ?></span>
                        </div>
                        <div class="progress h-4px">
                            <div class="progress-bar bg-<?= $progressColorRecAtivas ?>" style="width: <?= min(100, ($conversionRateRecAtivas / 30) * 100) ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="fs-8 text-muted">Interativas</span>
                            <span class="fs-7 fw-bold text-<?= $progressColorInteractive ?>"><?= number_format($conversionRateInteractive, 1) ?>%</span>
                        </div>
                        <div class="fs-9 text-muted mb-1"><?= number_format($interactiveConversations) ?> atendidas no período</div>
                        <div class="progress h-4px">
                            <div class="progress-bar bg-<?= $progressColorInteractive ?>" style="width: <?= min(100, ($conversionRateInteractive / 30) * 100) ?>%"></div>
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
                                            'producao' => 'info',
                                            'designer' => 'info',
                                            'pedido-enviado' => 'primary',
                                            'pedido-entregue' => 'success',
                                            'pending' => 'warning',
                                            'on-hold' => 'info',
                                            'cancelled' => 'danger',
                                            'refunded' => 'danger',
                                            'failed' => 'danger',
                                        ];
                                        $statusLabels = [
                                            'completed' => 'Concluído',
                                            'processing' => 'Processando',
                                            'producao' => 'Em Produção',
                                            'designer' => 'No Designer',
                                            'pedido-enviado' => 'Enviado',
                                            'pedido-entregue' => 'Entregue',
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

<div class="modal fade" id="modal_wc_metric_breakdown" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_wc_metric_breakdown_title">Detalhes</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal"><i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i></button>
            </div>
            <div class="modal-body" id="modal_wc_metric_breakdown_body">
                <div class="text-center py-10"><span class="spinner-border text-primary"></span></div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const breakdownUrl = <?= json_encode(\App\Helpers\Url::to('/api/agent-conversion/metric-breakdown')) ?>;
    window.openWcMetricBreakdown = function (agentId, agentName) {
        const body = document.getElementById('modal_wc_metric_breakdown_body');
        const title = document.getElementById('modal_wc_metric_breakdown_title');
        function val(id) {
            var el = document.getElementById(id);
            return el && el.value ? el.value : '';
        }
        const df = val('filter-date-from') || val('date_from_agent') || val('kt_dashboard_date_from');
        const dt = val('filter-date-to') || val('date_to_agent') || val('kt_dashboard_date_to');
        title.textContent = 'Origem das métricas — ' + (agentName || '');
        body.innerHTML = '<div class="text-center py-10"><span class="spinner-border text-primary"></span></div>';
        const modal = new bootstrap.Modal(document.getElementById('modal_wc_metric_breakdown'));
        modal.show();
        const u = new URL(breakdownUrl, window.location.origin);
        u.searchParams.set('agent_id', agentId);
        u.searchParams.set('date_from', df);
        u.searchParams.set('date_to', dt);
        fetch(u.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) throw new Error(j.message || 'Erro');
                const d = j.data;
                const ci = d.client_initiated || {};
                const ai = d.agent_initiated || {};
                const ra = d.receptivas_ativas || {};
                const it = d.interactive || {};
                const esc = function (s) {
                    const x = document.createElement('div');
                    x.textContent = s == null ? '' : String(s);
                    return x.innerHTML;
                };
                const row = function (label, val, hint) {
                    return '<tr><td class="text-gray-700">' + esc(label) + '</td><td class="text-end fw-bold">' + esc(val) + '</td></tr>' +
                        (hint ? '<tr><td colspan="2" class="fs-8 text-muted pt-0 pb-2">' + esc(hint) + '</td></tr>' : '');
                };
                let html = '<p class="text-muted fs-7 mb-4">Período: <strong>' + esc((d.period && d.period.from) || '') + '</strong> até <strong>' + esc((d.period && d.period.to) || '') + '</strong>.</p>';
                html += '<h6 class="fw-bold text-primary mb-2">Apenas receptivas</h6>';
                html += '<table class="table table-sm table-row-dashed gs-0 mb-6"><tbody>';
                html += row('Total', ci.total);
                html += row('Primeira conversa do contato', ci.primeira_conversa_do_contato);
                html += row('Retorno', ci.retorno);
                html += row('1ª conversa e agente = principal', ci.primeira_vida_agente_eh_principal);
                html += row('Retorno e agente = principal', ci.retorno_agente_eh_principal);
                html += '</tbody></table>';
                html += '<h6 class="fw-bold text-info mb-2">Ativas</h6>';
                html += '<table class="table table-sm table-row-dashed gs-0 mb-6"><tbody>';
                html += row('Total', ai.total);
                html += row('Primeira conversa do contato', ai.primeira_conversa_do_contato);
                html += row('Retorno', ai.retorno);
                html += '</tbody></table>';
                html += '<h6 class="fw-bold text-gray-800 mb-2">Rec + ativas</h6>';
                html += '<table class="table table-sm table-row-dashed gs-0 mb-6"><tbody>';
                html += row('Total', ra.total);
                html += row('Receptivas', ra.receptivas);
                html += row('Ativas', ra.ativas);
                html += '</tbody></table>';
                html += '<h6 class="fw-bold text-success mb-2">Interativas</h6>';
                html += '<table class="table table-sm table-row-dashed gs-0 mb-0"><tbody>';
                html += row('Total', it.total);
                html += row('Em conversas criadas no período', it.em_conversas_criadas_no_periodo);
                html += row('Em conversas já existentes', it.em_conversas_ja_existentes);
                html += '</tbody></table>';
                body.innerHTML = html;
            })
            .catch(function (e) {
                const msg = (e && e.message) ? String(e.message) : 'Erro ao carregar';
                body.innerHTML = '<div class="alert alert-danger">' + msg.replace(/</g, '&lt;') + '</div>';
            });
    };
    document.addEventListener('click', function (ev) {
        var t = ev.target.closest && ev.target.closest('.js-wc-metric-breakdown');
        if (!t || !document.getElementById('modal_wc_metric_breakdown')) return;
        var id = parseInt(t.getAttribute('data-agent-id'), 10);
        if (!id) return;
        var name = t.getAttribute('data-agent-name') || '';
        ev.preventDefault();
        window.openWcMetricBreakdown(id, name);
    });
})();
</script>
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
