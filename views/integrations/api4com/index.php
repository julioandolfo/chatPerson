<?php
$layout = 'layouts.metronic.app';
$title = 'Api4Com - Integrações';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Contas Api4Com</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('api4com.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_api4com">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Conta Api4Com
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($accounts)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-phone fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma conta Api4Com configurada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova conta Api4Com para habilitar chamadas telefônicas.</div>
            </div>
        <?php else: ?>
            <div class="row g-5">
                <?php foreach ($accounts as $account): ?>
                    <div class="col-xl-4">
                        <div class="card card-flush h-100">
                            <div class="card-header pt-5">
                                <div class="card-title">
                                    <h3 class="fw-bold"><?= htmlspecialchars($account['name']) ?></h3>
                                </div>
                                <div class="card-toolbar">
                                    <?php
                                    $statusClass = $account['enabled'] ? 'success' : 'warning';
                                    $statusText = $account['enabled'] ? 'Habilitado' : 'Desabilitado';
                                    ?>
                                    <span class="badge badge-light-<?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <div class="mb-5">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">API URL:</span>
                                        <span class="fw-bold fs-7"><?= htmlspecialchars($account['api_url']) ?></span>
                                    </div>
                                    <?php if (!empty($account['domain'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">Domínio:</span>
                                        <span class="fw-semibold fs-7"><?= htmlspecialchars($account['domain']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($account['webhook_url'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">Webhook:</span>
                                        <span class="fw-semibold fs-7"><?= htmlspecialchars($account['webhook_url']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if (\App\Helpers\Permission::can('api4com.edit')): ?>
                                    <button type="button" class="btn btn-light-primary btn-sm flex-grow-1" 
                                            onclick="editAccount(<?= $account['id'] ?>, <?= htmlspecialchars(json_encode($account), ENT_QUOTES) ?>)"
                                            title="Editar">
                                        <i class="ki-duotone ki-notepad-edit fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Editar
                                    </button>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('api4com.delete')): ?>
                                    <button type="button" class="btn btn-light-danger btn-sm" 
                                            onclick="deleteAccount(<?= $account['id'] ?>, '<?= htmlspecialchars($account['name'], ENT_QUOTES) ?>')"
                                            title="Deletar">
                                        <i class="ki-duotone ki-trash fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<!--begin::Modal - Nova Conta Api4Com-->
<?php if (\App\Helpers\Permission::can('api4com.create')): ?>
<div class="modal fade" id="kt_modal_new_api4com" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Conta Api4Com</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_api4com_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome da Conta</label>
                        <input type="text" name="name" class="form-control form-control-solid" 
                               placeholder="Ex: Api4Com Principal" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">API URL</label>
                        <input type="url" name="api_url" class="form-control form-control-solid" 
                               value="https://api.api4com.com" 
                               placeholder="https://api.api4com.com" required />
                        <div class="form-text">URL base da API Api4Com</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Token de Autenticação</label>
                        <input type="text" name="api_token" class="form-control form-control-solid" 
                               placeholder="Seu token Api4Com" required />
                        <div class="form-text">Token obtido no painel Api4Com</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Domínio</label>
                        <input type="text" name="domain" class="form-control form-control-solid" 
                               placeholder="seudominio.api4com.com" />
                        <div class="form-text">Domínio da sua conta Api4Com (opcional)</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Webhook URL</label>
                        <input type="url" name="webhook_url" class="form-control form-control-solid" 
                               value="<?= \App\Helpers\Url::fullUrl('/api4com-calls/webhook') ?>" 
                               readonly />
                        <div class="form-text">URL para receber webhooks da Api4Com (gerada automaticamente)</div>
                    </div>
                    <div class="fv-row mb-7">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="enabled" id="kt_api4com_enabled" value="1" checked />
                            <label class="form-check-label" for="kt_api4com_enabled">
                                <span class="fw-semibold">Habilitar conta</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_api4com_submit" class="btn btn-primary">
                        <span class="indicator-label">Criar Conta</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Nova Conta Api4Com-->

<?php 
$content = ob_get_clean();
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_modal_new_api4com_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_api4com_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_api4com"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar conta"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar conta");
            });
        });
    }
});

function editAccount(id, account) {
    // Implementar edição se necessário
    alert("Edição de conta em desenvolvimento");
}

function deleteAccount(id, name) {
    if (!confirm("Tem certeza que deseja deletar a conta \\"" + name + "\\"?\\n\\nEsta ação não pode ser desfeita.")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + id, {
        method: "DELETE",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao deletar conta"));
        }
    })
    .catch(error => {
        alert("Erro ao deletar conta");
    });
}
</script>';
?>

<?php include __DIR__ . '/../../layouts/metronic/app.php'; ?>

