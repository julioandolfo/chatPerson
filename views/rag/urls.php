<?php
$layout = 'layouts.metronic.app';
$title = 'URLs - ' . htmlspecialchars($agent['name'] ?? '');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold m-0">URLs para Processamento</h3>
                <span class="text-muted fs-7 mt-1"><?= htmlspecialchars($agent['name']) ?></span>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id']) ?>" class="btn btn-sm btn-light me-3">
                <i class="ki-duotone ki-arrow-left fs-2"></i>
                Voltar
            </a>
            <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_add_url">
                <i class="ki-duotone ki-plus fs-2"></i>
                Adicionar URL
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <!--begin::Estatísticas-->
        <div class="row mb-5">
            <div class="col-md-3">
                <div class="card bg-light-warning">
                    <div class="card-body">
                        <div class="fw-bold fs-2"><?= $pendingCount ?></div>
                        <div class="text-muted">Pendentes</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-info">
                    <div class="card-body">
                        <div class="fw-bold fs-2"><?= $processingCount ?></div>
                        <div class="text-muted">Processando</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-success">
                    <div class="card-body">
                        <div class="fw-bold fs-2"><?= $completedCount ?></div>
                        <div class="text-muted">Concluídas</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-danger">
                    <div class="card-body">
                        <div class="fw-bold fs-2"><?= $failedCount ?></div>
                        <div class="text-muted">Falhas</div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Estatísticas-->

        <!--begin::Filtros-->
        <div class="d-flex gap-2 mb-5">
            <a href="?status=all" class="btn btn-sm <?= $status === 'all' ? 'btn-primary' : 'btn-light' ?>">
                Todas
            </a>
            <a href="?status=pending" class="btn btn-sm <?= $status === 'pending' ? 'btn-primary' : 'btn-light' ?>">
                Pendentes
            </a>
            <a href="?status=processing" class="btn btn-sm <?= $status === 'processing' ? 'btn-primary' : 'btn-light' ?>">
                Processando
            </a>
            <a href="?status=completed" class="btn btn-sm <?= $status === 'completed' ? 'btn-primary' : 'btn-light' ?>">
                Concluídas
            </a>
            <a href="?status=failed" class="btn btn-sm <?= $status === 'failed' ? 'btn-primary' : 'btn-light' ?>">
                Falhas
            </a>
        </div>
        <!--end::Filtros-->

        <!--begin::Lista de URLs-->
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th class="min-w-300px">URL</th>
                        <th class="min-w-150px">Título</th>
                        <th class="min-w-100px">Status</th>
                        <th class="min-w-100px">Chunks</th>
                        <th class="min-w-150px">Data</th>
                        <th class="min-w-200px">Erro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($urls)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-10">
                            <div class="text-muted">Nenhuma URL encontrada. Adicione a primeira URL!</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($urls as $url): ?>
                    <tr>
                        <td>
                            <a href="<?= htmlspecialchars($url['url']) ?>" target="_blank" class="text-primary">
                                <?= htmlspecialchars($url['url']) ?>
                            </a>
                        </td>
                        <td>
                            <div class="text-gray-800"><?= htmlspecialchars($url['title'] ?? '-') ?></div>
                        </td>
                        <td>
                            <?php
                            $statusBadge = [
                                'pending' => 'warning',
                                'processing' => 'info',
                                'completed' => 'success',
                                'failed' => 'danger'
                            ];
                            $statusLabel = [
                                'pending' => 'Pendente',
                                'processing' => 'Processando',
                                'completed' => 'Concluída',
                                'failed' => 'Falhou'
                            ];
                            ?>
                            <span class="badge badge-light-<?= $statusBadge[$url['status']] ?? 'secondary' ?>">
                                <?= $statusLabel[$url['status']] ?? $url['status'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-gray-800"><?= $url['chunks_created'] ?? 0 ?></span>
                        </td>
                        <td>
                            <span class="text-muted"><?= date('d/m/Y H:i', strtotime($url['created_at'])) ?></span>
                        </td>
                        <td>
                            <?php if ($url['error_message']): ?>
                            <div class="text-danger small"><?= htmlspecialchars(mb_substr($url['error_message'], 0, 100)) ?></div>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!--end::Lista de URLs-->
    </div>
</div>
<!--end::Card-->

<!--begin::Modal Adicionar URL-->
<div class="modal fade" id="kt_modal_add_url" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar URL</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_add_url">
                <div class="modal-body">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">URL *</label>
                        <input type="url" name="url" class="form-control" required placeholder="https://exemplo.com">
                        <div class="form-text">A URL será processada e adicionada à Knowledge Base automaticamente.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Adicionar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal Adicionar URL-->

<script>
document.getElementById('form_add_url').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;

    fetch(`/ai-agents/<?= $agent['id'] ?>/rag/urls`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;

        if (data.success) {
            Swal.fire('Sucesso!', data.message, 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Erro', data.message, 'error');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        Swal.fire('Erro', 'Erro ao adicionar URL', 'error');
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

