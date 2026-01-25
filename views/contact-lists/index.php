<?php
$layout = 'layouts.metronic.app';
$title = 'Listas de Contatos';

// Content
ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h2 class="fw-bold">Listas de Contatos</h2>
        </div>
        <div class="card-toolbar">
            <div class="d-flex gap-2">
                <a href="<?= \App\Helpers\Url::to('/external-sources/create') ?>" class="btn btn-sm btn-light-info">
                    <i class="ki-duotone ki-technology-2 fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Fonte Externa
                </a>
                <a href="<?= \App\Helpers\Url::to('/contact-lists/create') ?>" class="btn btn-sm btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Nova Lista
                </a>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        
        <div class="row g-6 g-xl-9" id="lists-container">
            <div class="col-12 text-center py-10">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span class="ms-2">Carregando listas...</span>
            </div>
        </div>
        
    </div>
</div>
<!--end::Card-->

<script>
function loadLists() {
    fetch('<?= \App\Helpers\Url::to('/api/contact-lists') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderLists(data.lists || []);
            }
        })
        .catch(err => {
            document.getElementById('lists-container').innerHTML = 
                '<div class="col-12 text-center py-5 text-danger">Erro ao carregar listas</div>';
        });
}

function renderLists(lists) {
    const container = document.getElementById('lists-container');
    
    if (lists.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-10">
                <i class="ki-duotone ki-information fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma lista encontrada</h3>
                <div class="text-gray-500 fs-6 mb-7">Crie sua primeira lista de contatos para usar em campanhas</div>
                <a href="<?= \App\Helpers\Url::to('/contact-lists/create') ?>" class="btn btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Nova Lista
                </a>
            </div>
        `;
        return;
    }
    
    container.innerHTML = lists.map(list => `
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold">${escapeHtml(list.name)}</h3>
                    </div>
                </div>
                <div class="card-body">
                    ${list.description ? `<p class="text-gray-600 fs-6 mb-5">${escapeHtml(list.description)}</p>` : ''}
                    <div class="d-flex align-items-center mb-3">
                        <i class="ki-duotone ki-profile-user fs-1 text-primary me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div>
                            <div class="fs-2 fw-bold">${list.total_contacts || 0}</div>
                            <div class="text-muted fs-7">Contatos</div>
                        </div>
                    </div>
                    <div class="text-muted fs-7">
                        Criada em ${formatDate(list.created_at)}
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="<?= \App\Helpers\Url::to('/contact-lists/') ?>${list.id}" class="btn btn-light-primary btn-sm flex-fill" title="Ver Contatos">
                        <i class="ki-duotone ki-eye fs-6"><span class="path1"></span><span class="path2"></span></i>
                    </a>
                    <a href="<?= \App\Helpers\Url::to('/contact-lists/') ?>${list.id}/edit" class="btn btn-light-warning btn-sm" title="Editar">
                        <i class="ki-duotone ki-pencil fs-6"><span class="path1"></span><span class="path2"></span></i>
                    </a>
                    <button class="btn btn-light-info btn-sm" onclick="openSendOrderModal(${list.id})" title="Ordem de Envio">
                        <i class="ki-duotone ki-sort fs-6"><span class="path1"></span><span class="path2"></span></i>
                    </button>
                    <button class="btn btn-light-danger btn-sm" onclick="deleteList(${list.id}, '${escapeHtml(list.name)}')" title="Deletar">
                        <i class="ki-duotone ki-trash fs-6"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function deleteList(listId, listName) {
    if (!confirm(`Tem certeza que deseja deletar a lista "${listName}"?\n\nIsso removerá todos os contatos vinculados a esta lista.`)) {
        return;
    }
    
    fetch(`<?= \App\Helpers\Url::to('/contact-lists/') ?>${listId}`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            toastr.success('Lista deletada com sucesso!');
            loadLists(); // Recarrega a lista
        } else {
            toastr.error(data.message || 'Erro ao deletar lista');
        }
    })
    .catch(err => {
        toastr.error('Erro de rede ao deletar');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadLists();
});
</script>

<?php include __DIR__ . '/send-order.php'; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
