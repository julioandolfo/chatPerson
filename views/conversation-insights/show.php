<?php
/**
 * View: Resultado da Análise de Conversas por Coorte
 */
$title = $batch['name'] ?? 'Análise de Conversas';
ob_start();

$statusBadges = [
    'pending'   => ['label' => 'Na fila',     'class' => 'badge-light-warning'],
    'running'   => ['label' => 'Processando', 'class' => 'badge-light-primary'],
    'completed' => ['label' => 'Concluída',   'class' => 'badge-light-success'],
    'failed'    => ['label' => 'Falhou',      'class' => 'badge-light-danger'],
    'cancelled' => ['label' => 'Cancelada',   'class' => 'badge-light-dark'],
];
$badge = $statusBadges[$batch['status']] ?? ['label' => $batch['status'], 'class' => 'badge-light'];

$analyzed = (int)($metrics['analyzed'] ?? 0);

/** Percentual sobre o total analisado */
$pct = static function ($value) use ($analyzed) {
    return $analyzed > 0 ? round($value / $analyzed * 100) : 0;
};

$whoStoppedLabels = [
    'cliente'  => 'Cliente parou de responder',
    'vendedor' => 'Vendedor não respondeu',
    'ninguem'  => 'Conversa ainda ativa',
];
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    <?= htmlspecialchars($batch['name'] ?? 'Análise') ?>
                    <span class="badge <?= $badge['class'] ?> ms-3 fs-8" id="statusBadge"><?= $badge['label'] ?></span>
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted"><a href="/conversation-insights" class="text-muted">Análise de Conversas</a></li>
                    <li class="breadcrumb-item text-dark">
                        <?= $batch['date_from'] ? date('d/m/Y', strtotime($batch['date_from'])) : '' ?>
                        a
                        <?= $batch['date_to'] ? date('d/m/Y', strtotime($batch['date_to'])) : '' ?>
                    </li>
                </ul>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if (in_array($batch['status'], ['pending', 'running'], true) && !empty($canRun)): ?>
                    <button class="btn btn-sm btn-light-danger" id="btnCancel">Cancelar</button>
                <?php endif; ?>
                <?php if ($analyzed > 0): ?>
                    <a href="/conversation-insights/<?= (int)$batch['id'] ?>/export" class="btn btn-sm btn-light-primary">
                        Exportar CSV
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">

            <!-- Contexto da pergunta -->
            <div class="card mb-5">
                <div class="card-body py-4">
                    <div class="d-flex align-items-start">
                        <span class="fs-2 me-4">💬</span>
                        <div>
                            <div class="text-muted fs-8 fw-semibold text-uppercase mb-1">Pergunta analisada</div>
                            <div class="fs-6 text-dark"><?= nl2br(htmlspecialchars($batch['context_question'] ?? '')) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progresso (enquanto processa) -->
            <?php if (in_array($batch['status'], ['pending', 'running'], true)): ?>
                <div class="card mb-5" id="progressCard">
                    <div class="card-body">
                        <div class="d-flex flex-stack mb-3">
                            <span class="fw-bold fs-6">Processando conversas…</span>
                            <span class="text-muted fs-7" id="progressText">
                                <?= (int)$batch['analyzed_conversations'] ?>/<?= (int)$batch['total_conversations'] ?>
                            </span>
                        </div>
                        <div class="progress h-8px">
                            <div class="progress-bar bg-primary" id="progressBar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="text-muted fs-8 mt-3">
                            A análise roda em segundo plano pelo cron. Esta página atualiza sozinha.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($batch['error_message'])): ?>
                <div class="alert alert-warning"><?= htmlspecialchars($batch['error_message']) ?></div>
            <?php endif; ?>

            <?php if ($analyzed === 0): ?>
                <?php if ($batch['status'] === 'completed'): ?>
                    <div class="card">
                        <div class="card-body text-center py-15 text-muted">
                            Nenhuma conversa foi analisada com sucesso neste lote.
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>

                <!-- SÍNTESE EXECUTIVA -->
                <?php if (!empty($summary)): ?>
                    <div class="card mb-5 border border-primary">
                        <div class="card-body">
                            <?php if (!empty($summary['headline'])): ?>
                                <h2 class="fw-bold text-dark mb-5"><?= htmlspecialchars($summary['headline']) ?></h2>
                            <?php endif; ?>

                            <?php if (!empty($summary['who_is_dropping'])): ?>
                                <p class="fs-6 text-gray-700 mb-5"><?= htmlspecialchars($summary['who_is_dropping']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($summary['leak_points'])): ?>
                                <h4 class="fw-bold mb-3">Onde as conversas vazam</h4>
                                <div class="row g-3 mb-5">
                                    <?php foreach ($summary['leak_points'] as $leak): ?>
                                        <div class="col-md-4">
                                            <div class="border border-dashed rounded p-4 h-100">
                                                <div class="d-flex flex-stack mb-2">
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($leak['where'] ?? '') ?></span>
                                                    <?php $impact = $leak['impact'] ?? 'medio'; ?>
                                                    <span class="badge badge-light-<?= $impact === 'alto' ? 'danger' : ($impact === 'baixo' ? 'success' : 'warning') ?> fs-9">
                                                        <?= htmlspecialchars($impact) ?>
                                                    </span>
                                                </div>
                                                <div class="fs-3 fw-bold text-primary mb-2"><?= htmlspecialchars($leak['share'] ?? '') ?></div>
                                                <div class="text-muted fs-7"><?= htmlspecialchars($leak['why'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($summary['recommendations'])): ?>
                                <h4 class="fw-bold mb-3">O que fazer</h4>
                                <div class="mb-5">
                                    <?php foreach ($summary['recommendations'] as $index => $rec): ?>
                                        <div class="d-flex align-items-start mb-4">
                                            <span class="badge badge-circle badge-primary me-3 mt-1"><?= $index + 1 ?></span>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($rec['action'] ?? '') ?></div>
                                                <div class="text-muted fs-7"><?= htmlspecialchars($rec['expected_impact'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($summary['caveats'])): ?>
                                <div class="separator separator-dashed my-4"></div>
                                <div class="text-muted fs-8">
                                    <b>Limitações:</b>
                                    <?= htmlspecialchars(implode(' · ', array_filter($summary['caveats'], 'is_string'))) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-5 mb-5">
                    <!-- QUEM PAROU (dado determinístico, sem IA) -->
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bold fs-5">Quem parou de responder</h3>
                                </div>
                            </div>
                            <div class="card-body pt-2">
                                <div class="text-muted fs-8 mb-4">
                                    Calculado direto das mensagens — não é estimativa da IA.
                                </div>
                                <?php foreach (($metrics['by_who_stopped'] ?? []) as $row): ?>
                                    <?php
                                        $label = $whoStoppedLabels[$row['who_stopped']] ?? $row['who_stopped'];
                                        $percent = $pct((int)$row['total']);
                                        $color = $row['who_stopped'] === 'vendedor' ? 'danger' : ($row['who_stopped'] === 'cliente' ? 'warning' : 'success');
                                    ?>
                                    <div class="mb-4">
                                        <div class="d-flex flex-stack mb-1">
                                            <span class="fw-semibold fs-7"><?= htmlspecialchars($label) ?></span>
                                            <span class="fw-bold fs-7"><?= (int)$row['total'] ?> <span class="text-muted">(<?= $percent ?>%)</span></span>
                                        </div>
                                        <div class="progress h-6px">
                                            <div class="progress-bar bg-<?= $color ?>" style="width: <?= $percent ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ETAPA ONDE TRAVOU -->
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bold fs-5">Etapa onde travou</h3>
                                </div>
                            </div>
                            <div class="card-body pt-2">
                                <?php foreach (array_slice($metrics['by_stage'] ?? [], 0, 8) as $row): ?>
                                    <?php $percent = $pct((int)$row['total']); ?>
                                    <div class="mb-4">
                                        <div class="d-flex flex-stack mb-1">
                                            <a href="#" class="fw-semibold fs-7 text-hover-primary text-dark drilldown"
                                               data-filter="drop_off_stage_id" data-value="<?= (int)$row['drop_off_stage_id'] ?>">
                                                <?= htmlspecialchars($row['stage_name'] ?? 'Sem etapa') ?>
                                            </a>
                                            <span class="fw-bold fs-7"><?= (int)$row['total'] ?> <span class="text-muted">(<?= $percent ?>%)</span></span>
                                        </div>
                                        <div class="progress h-6px">
                                            <div class="progress-bar bg-primary" style="width: <?= $percent ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- MOTIVOS -->
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bold fs-5">Motivos</h3>
                                </div>
                            </div>
                            <div class="card-body pt-2">
                                <?php foreach (array_slice($metrics['by_reason'] ?? [], 0, 8) as $row): ?>
                                    <?php
                                        $percent = $pct((int)$row['total']);
                                        $label = $reasonLabels[$row['primary_reason']] ?? $row['primary_reason'];
                                    ?>
                                    <div class="mb-4">
                                        <div class="d-flex flex-stack mb-1">
                                            <a href="#" class="fw-semibold fs-7 text-hover-primary text-dark drilldown"
                                               data-filter="primary_reason" data-value="<?= htmlspecialchars($row['primary_reason']) ?>">
                                                <?= htmlspecialchars($label) ?>
                                            </a>
                                            <span class="fw-bold fs-7"><?= (int)$row['total'] ?> <span class="text-muted">(<?= $percent ?>%)</span></span>
                                        </div>
                                        <div class="progress h-6px">
                                            <div class="progress-bar bg-warning" style="width: <?= $percent ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- POR VENDEDOR -->
                <?php if (!empty($metrics['by_agent'])): ?>
                    <div class="card mb-5">
                        <div class="card-header">
                            <div class="card-title">
                                <h3 class="fw-bold fs-5">Por vendedor</h3>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle gs-0 gy-3">
                                    <thead>
                                        <tr class="fw-bold text-muted fs-8">
                                            <th class="min-w-150px">Vendedor</th>
                                            <th class="min-w-80px text-center">Conversas</th>
                                            <th class="min-w-120px text-center">Não respondeu</th>
                                            <th class="min-w-120px text-center">Sem follow-up</th>
                                            <th class="min-w-80px text-center">Ganhas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($metrics['by_agent'] as $row): ?>
                                            <?php
                                                $total = (int)$row['total'];
                                                $stoppedRate = $total > 0 ? round((int)$row['stopped_by_agent'] / $total * 100) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <a href="#" class="fw-bold text-dark text-hover-primary drilldown"
                                                       data-filter="agent_id" data-value="<?= (int)$row['agent_id'] ?>">
                                                        <?= htmlspecialchars($row['agent_name'] ?? 'Agente #' . $row['agent_id']) ?>
                                                    </a>
                                                </td>
                                                <td class="text-center fw-semibold"><?= $total ?></td>
                                                <td class="text-center">
                                                    <span class="badge badge-light-<?= $stoppedRate >= 30 ? 'danger' : ($stoppedRate >= 15 ? 'warning' : 'success') ?>">
                                                        <?= (int)$row['stopped_by_agent'] ?> (<?= $stoppedRate ?>%)
                                                    </span>
                                                </td>
                                                <td class="text-center fw-semibold"><?= (int)$row['no_followup'] ?></td>
                                                <td class="text-center fw-semibold text-success"><?= (int)$row['won'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- DRILLDOWN -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="fw-bold fs-5">Conversas</h3>
                            <span class="text-muted fs-8 ms-3" id="drilldownLabel">todas as analisadas</span>
                        </div>
                        <div class="card-toolbar">
                            <button class="btn btn-sm btn-light d-none" id="btnClearFilter">Limpar filtro</button>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted fs-8">
                                        <th class="min-w-150px">Contato</th>
                                        <th class="min-w-120px">Vendedor</th>
                                        <th class="min-w-120px">Etapa</th>
                                        <th class="min-w-140px">Motivo</th>
                                        <th class="min-w-250px">O que aconteceu</th>
                                        <th class="min-w-60px text-end"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                            $decoded = \App\Models\ConversationAnalysisItem::decode($item);
                                            $analysis = $decoded['analysis'] ?: [];
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($item['contact_name'] ?? 'Sem nome') ?></span>
                                                <span class="text-muted d-block fs-8"><?= htmlspecialchars($item['contact_phone'] ?? '') ?></span>
                                            </td>
                                            <td class="fs-7"><?= htmlspecialchars($item['agent_name'] ?? '—') ?></td>
                                            <td class="fs-7"><?= htmlspecialchars($item['drop_off_stage_name'] ?? '—') ?></td>
                                            <td>
                                                <span class="badge badge-light-warning fs-8">
                                                    <?= htmlspecialchars($reasonLabels[$item['primary_reason']] ?? $item['primary_reason']) ?>
                                                </span>
                                            </td>
                                            <td class="fs-8 text-muted">
                                                <?= htmlspecialchars(mb_substr($analysis['reason_explanation'] ?? '', 0, 160)) ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="/conversations/<?= (int)$item['conversation_id'] ?>"
                                                   class="btn btn-sm btn-light-primary" target="_blank">Abrir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const batchId = <?= (int)$batch['id'] ?>;
    const isRunning = <?= in_array($batch['status'], ['pending', 'running'], true) ? 'true' : 'false' ?>;
    const reasonLabels = <?= json_encode($reasonLabels, JSON_UNESCAPED_UNICODE) ?>;

    // ---- Polling do progresso ----
    if (isRunning) {
        const poll = setInterval(function () {
            fetch('/conversation-insights/' + batchId + '/status')
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.success) { return; }

                    const bar = document.getElementById('progressBar');
                    const text = document.getElementById('progressText');

                    if (bar) { bar.style.width = data.progress + '%'; }
                    if (text) { text.textContent = data.analyzed + '/' + data.total; }

                    if (data.finished) {
                        clearInterval(poll);
                        window.location.reload();
                    }
                })
                .catch(function () { /* silencioso — tenta de novo no próximo tick */ });
        }, 5000);
    }

    // ---- Cancelar ----
    const btnCancel = document.getElementById('btnCancel');
    if (btnCancel) {
        btnCancel.addEventListener('click', function () {
            if (!confirm('Cancelar esta análise? As conversas já analisadas serão mantidas.')) { return; }

            fetch('/conversation-insights/' + batchId + '/cancel', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.json(); })
                .then(function () { window.location.reload(); });
        });
    }

    // ---- Drilldown ----
    const itemsBody = document.getElementById('itemsBody');
    const drilldownLabel = document.getElementById('drilldownLabel');
    const btnClear = document.getElementById('btnClearFilter');

    document.querySelectorAll('.drilldown').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            loadItems(this.dataset.filter, this.dataset.value, this.textContent.trim());
        });
    });

    if (btnClear) {
        btnClear.addEventListener('click', function () { loadItems(null, null, null); });
    }

    function loadItems(filter, value, label) {
        let url = '/conversation-insights/' + batchId + '/conversations?limit=100';
        if (filter && value) {
            url += '&' + encodeURIComponent(filter) + '=' + encodeURIComponent(value);
        }

        itemsBody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Carregando…</td></tr>';

        fetch(url)
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success || !data.items.length) {
                    itemsBody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Nenhuma conversa.</td></tr>';
                    return;
                }

                itemsBody.innerHTML = data.items.map(function (item) {
                    const analysis = item.analysis || {};
                    const reason = reasonLabels[item.primary_reason] || item.primary_reason || '';
                    const explanation = (analysis.reason_explanation || '').substring(0, 160);

                    return '<tr>'
                        + '<td><span class="fw-bold text-dark">' + escapeHtml(item.contact_name || 'Sem nome') + '</span>'
                        + '<span class="text-muted d-block fs-8">' + escapeHtml(item.contact_phone || '') + '</span></td>'
                        + '<td class="fs-7">' + escapeHtml(item.agent_name || '—') + '</td>'
                        + '<td class="fs-7">' + escapeHtml(item.drop_off_stage_name || '—') + '</td>'
                        + '<td><span class="badge badge-light-warning fs-8">' + escapeHtml(reason) + '</span></td>'
                        + '<td class="fs-8 text-muted">' + escapeHtml(explanation) + '</td>'
                        + '<td class="text-end"><a href="/conversations/' + item.conversation_id + '" class="btn btn-sm btn-light-primary" target="_blank">Abrir</a></td>'
                        + '</tr>';
                }).join('');

                drilldownLabel.textContent = label ? label : 'todas as analisadas';
                btnClear.classList.toggle('d-none', !label);
            });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
