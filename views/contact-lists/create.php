<?php
$layout = 'layouts.metronic.app';
$title = 'Nova Lista de Contatos';
$pageTitle = 'Nova Lista';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Nova Lista de Contatos
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">
                        <a href="/contact-lists" class="text-muted text-hover-primary">Listas</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">Nova</li>
                </ul>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <div class="row">
                <div class="col-xl-8">
                    <div class="card mb-5">
                        <div class="card-header">
                            <h3 class="card-title">Informações da Lista</h3>
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
                                
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-light me-3" onclick="window.history.back()">Cancelar</button>
                                    <button type="submit" class="btn btn-primary" id="btn_save">
                                        <span class="indicator-label">Criar Lista</span>
                                        <span class="indicator-progress">Criando...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4">
                    <div class="card card-flush">
                        <div class="card-body">
                            <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-6">
                                <i class="ki-duotone ki-information-5 fs-2x text-info me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="d-flex flex-stack flex-grow-1">
                                    <div class="fw-semibold">
                                        <h4 class="text-gray-900 fw-bold">Próximos passos</h4>
                                        <div class="fs-6 text-gray-700">Após criar a lista, você poderá:
                                        <ul class="mt-3">
                                            <li>Adicionar contatos manualmente</li>
                                            <li>Importar via CSV/Excel</li>
                                            <li>Colar múltiplos contatos</li>
                                        </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
document.getElementById('list_form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn_save');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    data.list_type = 'static';
    
    fetch('/contact-lists', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Lista criada com sucesso!');
            setTimeout(() => {
                window.location.href = `/contact-lists/${result.list_id}`;
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
