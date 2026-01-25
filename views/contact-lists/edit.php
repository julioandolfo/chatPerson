<?php
$layout = 'layouts.metronic.app';
$pageTitle = 'Editar Lista de Contatos';

// Content
ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Editar Lista: <?= htmlspecialchars($list['name']) ?></h3>
    </div>
    <div class="card-body">
        
        <form id="list_form">
            <div class="mb-10">
                <label class="form-label required">Nome da Lista</label>
                <input type="text" class="form-control" name="name" 
                       value="<?= htmlspecialchars($list['name']) ?>" 
                       placeholder="Ex: Clientes VIP" required />
            </div>
            
            <div class="mb-10">
                <label class="form-label">Descrição</label>
                <textarea class="form-control" name="description" rows="3" 
                          placeholder="Descreva o propósito desta lista..."><?= htmlspecialchars($list['description'] ?? '') ?></textarea>
            </div>
            
            <div class="separator my-10"></div>
            
            <div class="mb-10">
                <h4 class="mb-5">Ordem de Envio</h4>
                <select class="form-select" name="send_order">
                    <option value="default" <?= ($list['send_order'] ?? '') === 'default' ? 'selected' : '' ?>>Padrão (ordem de adição)</option>
                    <option value="random" <?= ($list['send_order'] ?? '') === 'random' ? 'selected' : '' ?>>Aleatório</option>
                    <option value="asc" <?= ($list['send_order'] ?? '') === 'asc' ? 'selected' : '' ?>>Crescente por ID</option>
                    <option value="desc" <?= ($list['send_order'] ?? '') === 'desc' ? 'selected' : '' ?>>Decrescente por ID</option>
                </select>
                <div class="form-text">Define a ordem que os contatos serão enviados nas campanhas</div>
            </div>
            
            <div class="separator my-10"></div>
            
            <div class="mb-10">
                <h4 class="mb-5">Informações da Lista</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="bg-light-primary rounded p-4 text-center">
                            <div class="fs-2 fw-bold text-primary"><?= $list['total_contacts'] ?? 0 ?></div>
                            <div class="text-muted fs-7">Contatos</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light-info rounded p-4 text-center">
                            <div class="fs-6 fw-bold text-info"><?= date('d/m/Y H:i', strtotime($list['created_at'])) ?></div>
                            <div class="text-muted fs-7">Criada em</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light-success rounded p-4 text-center">
                            <div class="fs-6 fw-bold text-success"><?= !empty($list['updated_at']) ? date('d/m/Y H:i', strtotime($list['updated_at'])) : '-' ?></div>
                            <div class="text-muted fs-7">Atualizada em</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-danger" onclick="deleteList()">
                        <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                        Deletar Lista
                    </button>
                </div>
                <div>
                    <a href="<?= \App\Helpers\Url::to('/contact-lists') ?>" class="btn btn-light me-3">Cancelar</a>
                    <button type="submit" class="btn btn-primary" id="btn_save">
                        <span class="indicator-label">Salvar Alterações</span>
                        <span class="indicator-progress">Salvando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </div>
        </form>
        
    </div>
</div>
<!--end::Card-->

<script>
document.getElementById('list_form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn_save');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    fetch('<?= \App\Helpers\Url::to('/contact-lists/' . $list['id']) ?>', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Lista atualizada com sucesso!');
            setTimeout(() => {
                window.location.href = '<?= \App\Helpers\Url::to('/contact-lists') ?>';
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao atualizar lista');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede');
    });
});

function deleteList() {
    if (!confirm('Tem certeza que deseja deletar esta lista?\n\nEssa ação não pode ser desfeita e todos os contatos serão removidos.')) {
        return;
    }
    
    fetch('<?= \App\Helpers\Url::to('/contact-lists/' . $list['id']) ?>', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            toastr.success('Lista deletada com sucesso!');
            setTimeout(() => {
                window.location.href = '<?= \App\Helpers\Url::to('/contact-lists') ?>';
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao deletar lista');
        }
    })
    .catch(err => {
        toastr.error('Erro de rede');
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
