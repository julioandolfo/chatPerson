<?php
$layout = 'layouts.metronic.app';
$title = 'Roles e Permissões';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Roles e Permissões</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('roles.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_role">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Role
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($roles)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-shield fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma role encontrada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova role.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-150px">Nome</th>
                            <th class="min-w-100px">Slug</th>
                            <th class="min-w-100px">Nível</th>
                            <th class="min-w-150px">Permissões</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($role['name']) ?></span>
                                        <?php if (!empty($role['description'])): ?>
                                            <span class="text-muted fs-7"><?= htmlspecialchars($role['description']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary"><?= htmlspecialchars($role['slug']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-light-info">Nível <?= $role['level'] ?></span>
                                </td>
                                <td>
                                    <?php
                                    $permissions = \App\Models\Role::getPermissions($role['id']);
                                    $count = count($permissions);
                                    ?>
                                    <span class="badge badge-light-success"><?= $count ?> permissões</span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= \App\Helpers\Url::to('/roles/' . $role['id']) ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<!--begin::Modal - Nova Role-->
<?php if (\App\Helpers\Permission::can('roles.create')): ?>
<div class="modal fade" id="kt_modal_new_role" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Role</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_role_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" 
                               placeholder="Ex: Agente Sênior" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Slug</label>
                        <input type="text" name="slug" class="form-control form-control-solid" 
                               placeholder="Ex: agente-senior" required />
                        <div class="form-text">Identificador único (sem espaços, apenas letras, números e hífens)</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nível Hierárquico</label>
                        <select name="level" class="form-select form-select-solid" required>
                            <option value="0">0 - Super Admin (todas as permissões)</option>
                            <option value="1">1 - Admin</option>
                            <option value="2">2 - Supervisor</option>
                            <option value="3">3 - Agente Sênior</option>
                            <option value="4" selected>4 - Agente</option>
                            <option value="5">5 - Agente Júnior</option>
                            <option value="6">6 - Visualizador</option>
                            <option value="7">7 - API User</option>
                        </select>
                        <div class="form-text">Níveis menores têm mais permissões. Nível 0 tem acesso total.</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" 
                                  placeholder="Descreva o propósito desta role"></textarea>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_role_submit" class="btn btn-primary">
                        <span class="indicator-label">Criar Role</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Nova Role-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_modal_new_role_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_role_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            fetch("' . \App\Helpers\Url::to('/roles') . '", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_role"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar role"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar role");
            });
        });
    }
});
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

