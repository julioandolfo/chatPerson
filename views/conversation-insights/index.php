<?php
/**
 * View: Lista de Análises de Conversas por Coorte
 */
$title = 'Análise de Conversas';
ob_start();

$statusBadges = [
    'pending'   => ['label' => 'Na fila',      'class' => 'badge-light-warning'],
    'running'   => ['label' => 'Processando',  'class' => 'badge-light-primary'],
    'completed' => ['label' => 'Concluída',    'class' => 'badge-light-success'],
    'failed'    => ['label' => 'Falhou',       'class' => 'badge-light-danger'],
    'cancelled' => ['label' => 'Cancelada',    'class' => 'badge-light-dark'],
];
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    🔎 Análise de Conversas
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">Conversas que passaram por etapas ou agentes específicos</li>
                </ul>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="/conversation-insights/report?days=30&amp;min_messages=0&amp;limit=2000&amp;transcripts=1&amp;transcript_limit=50"
                   class="btn btn-sm btn-light-success"
                   data-bs-toggle="tooltip"
                   title="Todas as conversas dos últimos 30 dias, com métricas e transcrições, sem consumir a API">
                    PDF dos últimos 30 dias
                </a>
                <?php if (!empty($canRun)): ?>
                    <a href="/conversation-insights/new" class="btn btn-sm btn-primary">
                        <i class="ki-duotone ki-plus fs-4"></i> Nova análise
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">

            <?php if (empty($batches)): ?>
                <div class="card">
                    <div class="card-body text-center py-15">
                        <div class="fs-1 mb-4">📊</div>
                        <h3 class="fs-3 fw-bold text-dark mb-3">Nenhuma análise ainda</h3>
                        <p class="text-muted fs-6 mb-6">
                            Selecione as conversas que passaram por determinadas etapas ou agentes,<br>
                            escreva o que quer entender, e a IA analisa o conjunto todo.
                        </p>
                        <?php if (!empty($canRun)): ?>
                            <a href="/conversation-insights/new" class="btn btn-primary">Criar primeira análise</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3 class="fw-bold">Análises realizadas</h3>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th class="min-w-250px">Análise</th>
                                        <th class="min-w-100px">Período</th>
                                        <th class="min-w-100px">Conversas</th>
                                        <th class="min-w-120px">Progresso</th>
                                        <th class="min-w-80px">Custo</th>
                                        <th class="min-w-100px">Status</th>
                                        <th class="min-w-80px text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch):
                                        $total = (int)$batch['total_conversations'];
                                        $done = (int)$batch['analyzed_conversations'] + (int)$batch['failed_conversations'];
                                        // Lote encerrado sempre mostra 100%: conversas puladas
                                        // (curtas demais) não entram em analyzed nem em failed
                                        $finished = in_array($batch['status'], ['completed', 'failed', 'cancelled'], true);
                                        $progress = $finished ? 100 : ($total > 0 ? (int)round($done / $total * 100) : 0);
                                        $badge = $statusBadges[$batch['status']] ?? ['label' => $batch['status'], 'class' => 'badge-light'];
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="/conversation-insights/<?= (int)$batch['id'] ?>" class="text-dark fw-bold text-hover-primary fs-6">
                                                    <?= htmlspecialchars($batch['name'] ?? 'Análise #' . $batch['id']) ?>
                                                </a>
                                                <span class="text-muted fw-semibold d-block fs-7">
                                                    <?= htmlspecialchars(mb_substr($batch['context_question'] ?? '', 0, 90)) ?><?= mb_strlen($batch['context_question'] ?? '') > 90 ? '…' : '' ?>
                                                </span>
                                                <span class="text-muted fw-semibold d-block fs-8 mt-1">
                                                    por <?= htmlspecialchars($batch['created_by_name'] ?? 'sistema') ?>
                                                    · <?= date('d/m/Y H:i', strtotime($batch['created_at'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-muted fw-semibold fs-7">
                                                <?= $batch['date_from'] ? date('d/m/y', strtotime($batch['date_from'])) : '—' ?>
                                                a
                                                <?= $batch['date_to'] ? date('d/m/y', strtotime($batch['date_to'])) : '—' ?>
                                            </td>
                                            <td class="fw-bold"><?= $total ?></td>
                                            <td>
                                                <div class="d-flex flex-column w-100 me-2">
                                                    <div class="d-flex flex-stack mb-2">
                                                        <span class="text-muted fs-8 fw-semibold"><?= $done ?>/<?= $total ?></span>
                                                        <span class="text-muted fs-8 fw-bold"><?= $progress ?>%</span>
                                                    </div>
                                                    <div class="progress h-6px w-100">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $progress ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-muted fw-semibold fs-7">
                                                US$ <?= number_format((float)$batch['cost'], 2) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                                            </td>
                                            <td class="text-end">
                                                <a href="/conversation-insights/<?= (int)$batch['id'] ?>" class="btn btn-sm btn-light-primary">
                                                    Ver
                                                </a>
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

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
