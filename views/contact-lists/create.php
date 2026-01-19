<?php
$layout = 'layouts.metronic.app';
$title = 'Nova Lista de Contatos';

// Content
ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Criar Nova Lista de Contatos</h3>
    </div>
    <div class="card-body">
        
        <form id="list_form">
            <div class="mb-10">
                <label class="form-label required">Nome da Lista</label>
                <input type="text" class="form-control" name="name" placeholder="Ex: Clientes VIP" required />
            </div>
            
            <div class="mb-10">
                <label class="form-label">Descrição</label>
                <textarea class="form-control" name="description" rows="3" placeholder="Descreva o propósito desta lista..."></textarea>
            </div>
            
            <div class="separator my-10"></div>
            
            <div class="mb-10">
                <h4 class="mb-5">Fonte de Dados Externa (Opcional)</h4>
                <div class="alert alert-info d-flex align-items-center mb-5">
                    <i class="ki-duotone ki-information-5 fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    <div>
                        Conecte um banco de dados externo para sincronização automática de contatos.
                        <a href="/external-sources/create" target="_blank">Configure uma fonte aqui</a>.
                    </div>
                </div>
            </div>
            
            <div class="separator my-10"></div>
            
            <div class="mb-10">
                <h4 class="mb-5">Ordem de Envio</h4>
                <select class="form-select" name="send_order">
                    <option value="default">Padrão (ordem de adição)</option>
                    <option value="random">Aleatório</option>
                    <option value="asc">Crescente por ID</option>
                    <option value="desc">Decrescente por ID</option>
                </select>
                <div class="form-text">Define a ordem que os contatos serão enviados nas campanhas</div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-light me-3" onclick="window.history.back()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btn_save">
                    <span class="indicator-label">Criar Lista</span>
                    <span class="indicator-progress">Salvando...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
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
    
    fetch('<?= \App\Helpers\Url::to('/contact-lists') ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Lista criada com sucesso!');
            setTimeout(() => {
                window.location.href = '<?= \App\Helpers\Url::to('/contact-lists/') ?>' + result.list_id;
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao criar lista');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede');
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
