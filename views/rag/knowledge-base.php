<?php
$layout = 'layouts.metronic.app';
$title = 'Knowledge Base - ' . htmlspecialchars($agent['name'] ?? '');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold m-0">Knowledge Base</h3>
                <span class="text-muted fs-7 mt-1"><?= htmlspecialchars($agent['name']) ?> - <?= number_format($total ?? 0) ?> conhecimentos</span>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id']) ?>" class="btn btn-sm btn-light me-3">
                <i class="ki-duotone ki-arrow-left fs-2"></i>
                Voltar
            </a>
            <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_add_knowledge">
                <i class="ki-duotone ki-plus fs-2"></i>
                Adicionar Conhecimento
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <!--begin::Busca Semântica-->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Busca Semântica</h3>
            </div>
            <div class="card-body">
                <div class="input-group">
                    <input type="text" id="search_query" class="form-control" placeholder="Digite sua pergunta para buscar conhecimentos relevantes...">
                    <button type="button" class="btn btn-primary" onclick="searchKnowledge()">
                        <i class="ki-duotone ki-magnifier fs-2"></i>
                        Buscar
                    </button>
                </div>
                <div id="search_results" class="mt-4" style="display: none;"></div>
            </div>
        </div>
        <!--end::Busca Semântica-->

        <!--begin::Lista de Conhecimentos-->
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th class="min-w-100px">Título</th>
                        <th class="min-w-150px">Tipo</th>
                        <th class="min-w-200px">Conteúdo</th>
                        <th class="min-w-100px">Fonte</th>
                        <th class="min-w-100px">Data</th>
                        <th class="text-end min-w-100px">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($knowledge)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-10">
                            <div class="text-muted">Nenhum conhecimento encontrado. Adicione o primeiro conhecimento!</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($knowledge as $item): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-gray-800"><?= htmlspecialchars($item['title'] ?? 'Sem título') ?></div>
                        </td>
                        <td>
                            <span class="badge badge-light-info"><?= htmlspecialchars($item['content_type']) ?></span>
                        </td>
                        <td>
                            <div class="text-gray-600"><?= htmlspecialchars(mb_substr($item['content'], 0, 100)) ?><?= mb_strlen($item['content']) > 100 ? '...' : '' ?></div>
                        </td>
                        <td>
                            <?php if ($item['source_url']): ?>
                            <a href="<?= htmlspecialchars($item['source_url']) ?>" target="_blank" class="text-primary">
                                <i class="ki-duotone ki-link fs-5"></i>
                                Ver fonte
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-muted"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></span>
                        </td>
                        <td class="text-end">
                            <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
                            <button type="button" class="btn btn-sm btn-light-danger" onclick="deleteKnowledge(<?= $item['id'] ?>)">
                                <i class="ki-duotone ki-trash fs-5"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!--end::Lista de Conhecimentos-->
    </div>
</div>
<!--end::Card-->

<!--begin::Modal Adicionar Conhecimento-->
<div class="modal fade" id="kt_modal_add_knowledge" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Conhecimento</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form_add_knowledge">
                <div class="modal-body">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Título</label>
                        <input type="text" name="title" class="form-control" placeholder="Título do conhecimento">
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tipo de Conteúdo</label>
                        <select name="content_type" class="form-select">
                            <option value="manual">Manual</option>
                            <option value="product">Produto</option>
                            <option value="faq">FAQ</option>
                            <option value="policy">Política</option>
                            <option value="guide">Guia</option>
                            <option value="other">Outro</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Conteúdo *</label>
                        <textarea name="content" class="form-control" rows="8" required placeholder="Digite o conteúdo do conhecimento..."></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">URL de Origem (opcional)</label>
                        <input type="url" name="source_url" class="form-control" placeholder="https://...">
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
<!--end::Modal Adicionar Conhecimento-->

<script>
function searchKnowledge() {
    const query = document.getElementById('search_query').value.trim();
    if (!query) {
        Swal.fire('Atenção', 'Digite uma pergunta para buscar', 'warning');
        return;
    }

    const resultsDiv = document.getElementById('search_results');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';

    fetch(`/ai-agents/<?= $agent['id'] ?>/rag/knowledge-base/search?query=${encodeURIComponent(query)}&limit=5`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.results.length === 0) {
                    resultsDiv.innerHTML = '<div class="alert alert-info">Nenhum conhecimento relevante encontrado.</div>';
                } else {
                    let html = '<h5 class="mb-4">Resultados da Busca:</h5>';
                    data.results.forEach((result, index) => {
                        html += `
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="fw-bold">${result.title || 'Sem título'}</h6>
                                        <span class="badge badge-light-success">Similaridade: ${(result.similarity * 100).toFixed(1)}%</span>
                                    </div>
                                    <p class="text-gray-600 mb-2">${result.content}</p>
                                    ${result.source_url ? `<small class="text-muted">Fonte: <a href="${result.source_url}" target="_blank">${result.source_url}</a></small>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    resultsDiv.innerHTML = html;
                }
            } else {
                resultsDiv.innerHTML = `<div class="alert alert-danger">Erro: ${data.message}</div>`;
            }
        })
        .catch(err => {
            resultsDiv.innerHTML = `<div class="alert alert-danger">Erro ao buscar: ${err.message}</div>`;
        });
}

document.getElementById('form_add_knowledge').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;

    fetch(`/ai-agents/<?= $agent['id'] ?>/rag/knowledge-base`, {
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
        Swal.fire('Erro', 'Erro ao adicionar conhecimento', 'error');
    });
});

function deleteKnowledge(id) {
    Swal.fire({
        title: 'Confirmar exclusão',
        text: 'Tem certeza que deseja remover este conhecimento?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/ai-agents/<?= $agent['id'] ?>/rag/knowledge-base/${id}`, {
                method: 'DELETE'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro', data.message, 'error');
                }
            });
        }
    });
}

// Enter na busca
document.getElementById('search_query').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchKnowledge();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

