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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar URL</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_add_url">
                <div class="modal-body">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">URL *</label>
                        <input type="url" name="url" id="input_url" class="form-control" required placeholder="https://exemplo.com">
                        <div class="form-text">URL para processar e adicionar à Knowledge Base.</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="discover_links" id="discover_links" value="1" onchange="toggleCrawlingOptions()">
                            <label class="form-check-label" for="discover_links">
                                <strong>Descobrir automaticamente todas as URLs do site (Crawling)</strong>
                            </label>
                        </div>
                        <div class="form-text">Ative para fazer crawling e descobrir todas as páginas do site (útil para e-commerce).</div>
                    </div>
                    
                    <div id="crawling_options" style="display: none;">
                        <div class="card bg-light p-4 mb-5">
                            <h5 class="mb-4">Opções de Crawling</h5>
                            
                            <div class="fv-row mb-5">
                                <label class="fw-semibold fs-6 mb-2">Profundidade Máxima</label>
                                <input type="number" name="max_depth" class="form-control" value="3" min="1" max="5">
                                <div class="form-text">Níveis de profundidade para seguir links (1-5). Padrão: 3</div>
                            </div>
                            
                            <div class="fv-row mb-5">
                                <label class="fw-semibold fs-6 mb-2">Máximo de URLs</label>
                                <input type="number" name="max_urls" class="form-control" value="500" min="1" max="2000">
                                <div class="form-text">Número máximo de URLs para descobrir. Padrão: 500</div>
                            </div>
                            
                            <div class="fv-row mb-5">
                                <label class="fw-semibold fs-6 mb-2">Paths Permitidos (opcional)</label>
                                <input type="text" name="allowed_paths" class="form-control" placeholder="/produto/, /categoria/">
                                <div class="form-text">Separados por vírgula. Ex: /produto/, /categoria/ (deixe vazio para todos)</div>
                            </div>
                            
                            <div class="fv-row mb-5">
                                <label class="fw-semibold fs-6 mb-2">Paths Excluídos (opcional)</label>
                                <input type="text" name="excluded_paths" class="form-control" placeholder="/admin/, /checkout/">
                                <div class="form-text">Separados por vírgula. Ex: /admin/, /checkout/, /carrinho/</div>
                            </div>
                        </div>
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

<!--begin::Botão Processar URLs-->
<?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
<div class="card mb-5">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1">Processar URLs Pendentes</h5>
                <p class="text-muted mb-0">Processe URLs pendentes em background para adicionar à Knowledge Base</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="processUrls()">
                <i class="ki-duotone ki-play fs-2"></i>
                Processar URLs
            </button>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Botão Processar URLs-->

<script>
function toggleCrawlingOptions() {
    const checkbox = document.getElementById('discover_links');
    const options = document.getElementById('crawling_options');
    options.style.display = checkbox.checked ? 'block' : 'none';
}

document.getElementById('form_add_url').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;

    const discoverLinks = document.getElementById('discover_links').checked;
    
    if (discoverLinks) {
        Swal.fire({
            title: 'Crawling em andamento...',
            text: 'Isso pode levar alguns minutos. Não feche esta página.',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    fetch(`/ai-agents/<?= $agent['id'] ?>/rag/urls`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;

        if (data.success) {
            Swal.close();
            Swal.fire({
                title: 'Sucesso!',
                html: data.message + (data.urls_discovered ? `<br><br><strong>${data.urls_discovered} URLs descobertas</strong>` : ''),
                icon: 'success',
                confirmButtonText: 'OK'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.close();
            Swal.fire('Erro', data.message, 'error');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        Swal.close();
        Swal.fire('Erro', 'Erro ao adicionar URL', 'error');
    });
});

function processUrls() {
    Swal.fire({
        title: 'Processar URLs?',
        text: 'Isso processará todas as URLs pendentes em background.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, processar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processando...',
                text: 'Aguarde enquanto processamos as URLs.',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/ai-agents/<?= $agent['id'] ?>/rag/urls/process?limit=10`, {
                method: 'POST'
            })
            .then(res => res.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        title: 'Concluído!',
                        html: data.message + '<br><br>' + 
                              `Processadas: ${data.stats.processed}<br>` +
                              `Sucesso: ${data.stats.success}<br>` +
                              `Falhas: ${data.stats.failed}`,
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            })
            .catch(err => {
                Swal.close();
                Swal.fire('Erro', 'Erro ao processar URLs', 'error');
            });
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

