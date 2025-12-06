<?php
$layout = 'layouts.metronic.app';
$title = 'Role - ' . htmlspecialchars($role['name'] ?? '');

ob_start();
?>
<!--begin::Layout-->
<div class="d-flex flex-column flex-xl-row">
    <!--begin::Sidebar-->
    <div class="flex-column flex-lg-row-auto w-100 w-xl-350px mb-10">
        <!--begin::Card-->
        <div class="card mb-5 mb-xl-8">
            <div class="card-body pt-15">
                <div class="d-flex flex-center flex-column mb-5">
                    <h3 class="text-gray-800 fw-bold mb-3"><?= htmlspecialchars($role['name']) ?></h3>
                    <div class="mb-9">
                        <span class="badge badge-lg badge-light-primary"><?= htmlspecialchars($role['slug']) ?></span>
                        <span class="badge badge-lg badge-light-info ms-2">Nível <?= $role['level'] ?></span>
                    </div>
                    <?php if (!empty($role['description'])): ?>
                        <p class="text-gray-600 text-center"><?= htmlspecialchars($role['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!--end::Card-->
    </div>
    <!--end::Sidebar-->

    <!--begin::Content-->
    <div class="flex-lg-row-fluid ms-xl-10">
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Permissões</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Gerencie as permissões desta role</span>
                </h3>
                <?php if (\App\Helpers\Permission::can('roles.edit')): ?>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_assign_permission">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Adicionar Permissão
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body py-3">
                <?php if (empty($role['permissions'])): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-shield-cross fs-3x text-gray-400 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-800 fw-bold mb-2">Nenhuma permissão atribuída</h3>
                        <div class="text-gray-500 fs-6">Adicione permissões a esta role.</div>
                    </div>
                <?php else: ?>
                    <div class="row g-5">
                        <?php 
                        // Obter todas as permissões (incluindo herdadas)
                        $allRolePermissions = \App\Models\Role::getAllPermissions($role['id']);
                        $allRolePermissionIds = array_column($allRolePermissions, 'id');
                        ?>
                        <?php foreach ($allPermissions as $module => $modulePermissions): ?>
                            <div class="col-12">
                                <div class="d-flex align-items-center mb-3">
                                    <h4 class="text-gray-800 fw-bold mb-0 me-3"><?= ucfirst($module) ?></h4>
                                    <?php
                                    $moduleDirectCount = 0;
                                    $moduleInheritedCount = 0;
                                    foreach ($modulePermissions as $perm) {
                                        $isDirect = false;
                                        foreach ($role['permissions'] as $rolePerm) {
                                            if ($rolePerm['id'] == $perm['id']) {
                                                $isDirect = true;
                                                $moduleDirectCount++;
                                                break;
                                            }
                                        }
                                        if (!$isDirect && in_array($perm['id'], $allRolePermissionIds)) {
                                            $moduleInheritedCount++;
                                        }
                                    }
                                    ?>
                                    <span class="badge badge-light-success"><?= $moduleDirectCount ?> diretas</span>
                                    <?php if ($moduleInheritedCount > 0): ?>
                                        <span class="badge badge-light-info ms-2"><?= $moduleInheritedCount ?> herdadas</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($modulePermissions as $perm): ?>
                                        <?php
                                        $hasDirectPermission = false;
                                        $hasInheritedPermission = false;
                                        
                                        // Verificar permissão direta
                                        foreach ($role['permissions'] as $rolePerm) {
                                            if ($rolePerm['id'] == $perm['id']) {
                                                $hasDirectPermission = true;
                                                break;
                                            }
                                        }
                                        
                                        // Verificar permissão herdada
                                        if (!$hasDirectPermission && in_array($perm['id'], $allRolePermissionIds)) {
                                            $hasInheritedPermission = true;
                                        }
                                        
                                        $hasPermission = $hasDirectPermission || $hasInheritedPermission;
                                        ?>
                                        <span class="badge badge-lg <?= $hasDirectPermission ? 'badge-light-success' : ($hasInheritedPermission ? 'badge-light-info' : 'badge-light-secondary') ?> position-relative">
                                            <?php if ($hasInheritedPermission): ?>
                                                <i class="ki-duotone ki-arrow-down fs-7 me-1" title="Permissão herdada">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($perm['name']) ?>
                                            <?php if ($hasDirectPermission && \App\Helpers\Permission::can('roles.edit')): ?>
                                                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary ms-2" 
                                                        onclick="removePermission(<?= $role['id'] ?>, <?= $perm['id'] ?>)"
                                                        title="Remover permissão">
                                                    <i class="ki-duotone ki-cross fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </button>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card-->
    </div>
    <!--end::Content-->
</div>
<!--end::Layout-->

<!--begin::Modal - Adicionar Permissão-->
<?php if (\App\Helpers\Permission::can('roles.edit')): ?>
<div class="modal fade" id="kt_modal_assign_permission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Permissão</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_assign_permission_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Permissão</label>
                        <select name="permission_id" class="form-select form-select-solid" required>
                            <option value="">Selecione uma permissão</option>
                            <?php foreach ($allPermissions as $module => $modulePermissions): ?>
                                <optgroup label="<?= ucfirst($module) ?>">
                                    <?php foreach ($modulePermissions as $perm): ?>
                                        <?php
                                        $hasPermission = false;
                                        foreach ($role['permissions'] as $rolePerm) {
                                            if ($rolePerm['id'] == $perm['id']) {
                                                $hasPermission = true;
                                                break;
                                            }
                                        }
                                        if (!$hasPermission):
                                        ?>
                                            <option value="<?= $perm['id'] ?>"><?= htmlspecialchars($perm['name']) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_assign_permission_submit" class="btn btn-primary">
                        <span class="indicator-label">Adicionar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Adicionar Permissão-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_modal_assign_permission_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const permissionId = this.querySelector("[name=\"permission_id\"]").value;
            if (!permissionId) return;
            
            const submitBtn = document.getElementById("kt_modal_assign_permission_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append("permission_id", permissionId);
            
            fetch("' . \App\Helpers\Url::to('/roles/' . $role['id'] . '/permissions') . '", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_assign_permission"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao adicionar permissão"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao adicionar permissão");
            });
        });
    }
});

function removePermission(roleId, permissionId) {
    if (!confirm("Tem certeza que deseja remover esta permissão?")) return;
    
    const formData = new FormData();
    formData.append("permission_id", permissionId);
    
    fetch("' . \App\Helpers\Url::to('/roles/' . $role['id'] . '/permissions/remove') . '", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao remover permissão"));
        }
    });
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

