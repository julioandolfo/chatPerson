<?php
$layout = 'layouts.metronic.app';
$title = 'Funis';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Funis</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('funnels.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_funnel">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Funil
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($funnels)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-category fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum funil encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo funil.</div>
            </div>
        <?php else: ?>
            <div class="row g-5">
                <?php foreach ($funnels as $funnel): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center mb-5">
                                    <div class="symbol symbol-50px me-5">
                                        <div class="symbol-label" style="background-color: <?= htmlspecialchars($funnel['status'] === 'active' ? '#009ef7' : '#e4e6ef') ?>">
                                            <i class="ki-duotone ki-category fs-2x text-white">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                            </i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h3 class="text-gray-800 fw-bold mb-1"><?= htmlspecialchars($funnel['name']) ?></h3>
                                        <?php if ($funnel['is_default']): ?>
                                            <span class="badge badge-light-primary">Padrão</span>
                                        <?php endif; ?>
                                        <span class="badge badge-light-<?= $funnel['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
                                            <?= $funnel['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($funnel['description'])): ?>
                                    <p class="text-gray-600 mb-5"><?= htmlspecialchars(mb_substr($funnel['description'], 0, 100)) ?></p>
                                <?php endif; ?>
                                <div class="mt-auto">
                                    <a href="<?= \App\Helpers\Url::to('/funnels/' . $funnel['id'] . '/kanban') ?>" class="btn btn-light-primary w-100">
                                        <i class="ki-duotone ki-grid fs-2"></i>
                                        Ver Kanban
                                    </a>
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

<!--begin::Modal - Novo Funil-->
<?php if (\App\Helpers\Permission::can('funnels.create')): ?>
<div class="modal fade" id="kt_modal_new_funnel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Funil</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_funnel_form" class="form" action="<?= \App\Helpers\Url::to('/funnels') ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Nome do funil" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Descrição do funil"></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="kt_funnel_default" />
                            <label class="form-check-label" for="kt_funnel_default">
                                Funil padrão
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_funnel_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Novo Funil-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_modal_new_funnel_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_funnel_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            if (!form.querySelector("#kt_funnel_default").checked) {
                formData.delete("is_default");
            }
            
            fetch(form.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_funnel"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar funil"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar funil");
            });
        });
    }
});
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
