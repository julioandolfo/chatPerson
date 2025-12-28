<?php
$layout = 'layouts.metronic.app';
$title = 'Memórias - ' . htmlspecialchars($agent['name'] ?? '');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold m-0">Memórias do Agente</h3>
                <span class="text-muted fs-7 mt-1"><?= htmlspecialchars($agent['name']) ?> - <?= number_format($totalCount ?? 0) ?> memórias</span>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id']) ?>" class="btn btn-sm btn-light me-3">
                <i class="ki-duotone ki-arrow-left fs-2"></i>
                Voltar
            </a>
        </div>
    </div>
    <div class="card-body pt-0">
        <!--begin::Filtros-->
        <div class="d-flex gap-2 mb-5">
            <a href="?" class="btn btn-sm <?= !$memoryType ? 'btn-primary' : 'btn-light' ?>">
                Todas
            </a>
            <a href="?memory_type=fact" class="btn btn-sm <?= $memoryType === 'fact' ? 'btn-primary' : 'btn-light' ?>">
                Fatos
            </a>
            <a href="?memory_type=preference" class="btn btn-sm <?= $memoryType === 'preference' ? 'btn-primary' : 'btn-light' ?>">
                Preferências
            </a>
            <a href="?memory_type=context" class="btn btn-sm <?= $memoryType === 'context' ? 'btn-primary' : 'btn-light' ?>">
                Contexto
            </a>
            <a href="?memory_type=extracted_info" class="btn btn-sm <?= $memoryType === 'extracted_info' ? 'btn-primary' : 'btn-light' ?>">
                Informações Extraídas
            </a>
        </div>
        <!--end::Filtros-->

        <!--begin::Lista de Memórias-->
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th class="min-w-100px">Tipo</th>
                        <th class="min-w-150px">Chave</th>
                        <th class="min-w-300px">Valor</th>
                        <th class="min-w-100px">Importância</th>
                        <th class="min-w-150px">Conversa</th>
                        <th class="min-w-150px">Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($memories)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-10">
                            <div class="text-muted">Nenhuma memória encontrada.</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($memories as $memory): ?>
                    <tr>
                        <td>
                            <span class="badge badge-light-info"><?= htmlspecialchars($memory['memory_type']) ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-gray-800"><?= htmlspecialchars($memory['key'] ?? '-') ?></div>
                        </td>
                        <td>
                            <div class="text-gray-600"><?= htmlspecialchars(mb_substr($memory['value'], 0, 200)) ?><?= mb_strlen($memory['value']) > 200 ? '...' : '' ?></div>
                        </td>
                        <td>
                            <?php
                            $importance = (float)$memory['importance'];
                            $badgeColor = $importance >= 0.8 ? 'success' : ($importance >= 0.5 ? 'warning' : 'secondary');
                            ?>
                            <span class="badge badge-light-<?= $badgeColor ?>">
                                <?= number_format($importance * 100, 0) ?>%
                            </span>
                        </td>
                        <td>
                            <?php if ($memory['conversation_id']): ?>
                            <a href="<?= \App\Helpers\Url::to('/conversations/' . $memory['conversation_id']) ?>" class="text-primary">
                                #<?= $memory['conversation_id'] ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-muted"><?= date('d/m/Y H:i', strtotime($memory['created_at'])) ?></span>
                            <?php if ($memory['expires_at']): ?>
                            <br><small class="text-muted">Expira: <?= date('d/m/Y', strtotime($memory['expires_at'])) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!--end::Lista de Memórias-->
    </div>
</div>
<!--end::Card-->

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

