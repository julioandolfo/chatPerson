<?php
$layout = 'layouts.metronic.app';
$title = 'Usuários';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Usuários</h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <input type="text" data-kt-filter="search" class="form-control form-control-solid w-250px" placeholder="Buscar usuários..." />
                <?php if (\App\Helpers\Permission::can('users.create')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_user">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Novo Usuário
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($users)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-people fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum usuário encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo usuário.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_users_table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-200px">Usuário</th>
                            <th class="min-w-150px">Email</th>
                            <th class="min-w-100px">Role</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-100px">Disponibilidade</th>
                            <th class="min-w-120px">Conversas</th>
                            <th class="min-w-150px">Roles</th>
                            <th class="min-w-150px">Setores</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-45px me-5">
                                            <?php if (!empty($user['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" />
                                            <?php else: ?>
                                                <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                                                    <?= mb_substr(htmlspecialchars($user['name']), 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-800 fw-bold"><?= htmlspecialchars($user['name']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge badge-light-info"><?= htmlspecialchars($user['role'] ?? 'agent') ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = $user['status'] === 'active' ? 'success' : 'danger';
                                    $statusText = $user['status'] === 'active' ? 'Ativo' : 'Inativo';
                                    ?>
                                    <span class="badge badge-light-<?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($user['availability_status'])): ?>
                                        <?php
                                        $availColors = [
                                            'online' => 'success',
                                            'offline' => 'secondary',
                                            'away' => 'warning',
                                            'busy' => 'danger'
                                        ];
                                        $availLabels = [
                                            'online' => 'Online',
                                            'offline' => 'Offline',
                                            'away' => 'Ausente',
                                            'busy' => 'Ocupado'
                                        ];
                                        $availStatus = $user['availability_status'] ?? 'offline';
                                        $availColor = $availColors[$availStatus] ?? 'secondary';
                                        $availLabel = $availLabels[$availStatus] ?? 'Desconhecido';
                                        ?>
                                        <span class="badge badge-light-<?= $availColor ?>"><?= $availLabel ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'agent' || $user['role'] === 'admin' || $user['role'] === 'supervisor'): ?>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-800 fw-bold">
                                                <?= $user['current_conversations'] ?? 0 ?>
                                                <?php if (!empty($user['max_conversations'])): ?>
                                                    / <?= $user['max_conversations'] ?>
                                                <?php else: ?>
                                                    <span class="text-muted fs-7">(sem limite)</span>
                                                <?php endif; ?>
                                            </span>
                                            <?php if (!empty($user['max_conversations'])): ?>
                                                <?php
                                                $percentage = ($user['current_conversations'] ?? 0) / $user['max_conversations'] * 100;
                                                $progressColor = $percentage >= 90 ? 'danger' : ($percentage >= 70 ? 'warning' : 'success');
                                                ?>
                                                <div class="progress h-4px mt-1" style="width: 80px;">
                                                    <div class="progress-bar bg-<?= $progressColor ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= min(100, $percentage) ?>%">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($user['roles_names'])): ?>
                                        <?php
                                        $roleNames = explode(',', $user['roles_names']);
                                        foreach ($roleNames as $roleName):
                                        ?>
                                            <span class="badge badge-light-primary me-1"><?= htmlspecialchars(trim($roleName)) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($user['departments_names'])): ?>
                                        <?php
                                        $deptNames = explode(',', $user['departments_names']);
                                        foreach ($deptNames as $deptName):
                                        ?>
                                            <span class="badge badge-light-success me-1"><?= htmlspecialchars(trim($deptName)) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="<?= \App\Helpers\Url::to('/users/' . $user['id']) ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                            Ver
                                        </a>
                                        <?php if (\App\Helpers\Permission::can('users.edit')): ?>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-light btn-active-light-info dropdown-toggle" 
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="ki-duotone ki-setting-3 fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       data-user-id="<?= $user['id'] ?>"
                                                       data-user-name="<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>"
                                                       data-user-email="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>"
                                                       data-user-role="<?= htmlspecialchars($user['role'] ?? 'agent', ENT_QUOTES) ?>"
                                                       data-user-status="<?= htmlspecialchars($user['status'] ?? 'active', ENT_QUOTES) ?>"
                                                       data-user-availability="<?= htmlspecialchars($user['availability_status'] ?? 'offline', ENT_QUOTES) ?>"
                                                       data-user-max-conversations="<?= htmlspecialchars($user['max_conversations'] ?? '', ENT_QUOTES) ?>"
                                                       onclick="editUser(this); return false;">
                                                        <i class="ki-duotone ki-pencil fs-5 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Editar
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       data-user-id="<?= $user['id'] ?>"
                                                       onclick="quickAssignRole(this); return false;">
                                                        <i class="ki-duotone ki-shield fs-5 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Atribuir Role
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       data-user-id="<?= $user['id'] ?>"
                                                       onclick="quickAssignDepartment(this); return false;">
                                                        <i class="ki-duotone ki-abstract-26 fs-5 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Atribuir Setor
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (\App\Helpers\Permission::can('users.delete')): ?>
                                        <button type="button" class="btn btn-sm btn-light-danger" 
                                                onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>')">
                                            <i class="ki-duotone ki-trash fs-5">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
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

<!--begin::Modal - Novo Usuário-->
<?php if (\App\Helpers\Permission::can('users.create')): ?>
<div class="modal fade" id="kt_modal_new_user" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Usuário</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_user_form" class="form" action="<?= \App\Helpers\Url::to('/users') ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Nome completo" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Email</label>
                        <input type="email" name="email" class="form-control form-control-solid" placeholder="email@exemplo.com" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Senha</label>
                        <input type="password" name="password" class="form-control form-control-solid" placeholder="Mínimo 6 caracteres" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Role</label>
                        <select name="role" class="form-select form-select-solid">
                            <option value="agent">Agente</option>
                            <option value="admin">Admin</option>
                            <option value="supervisor">Supervisor</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status de Disponibilidade</label>
                        <select name="availability_status" class="form-select form-select-solid">
                            <option value="offline">Offline</option>
                            <option value="online">Online</option>
                            <option value="away">Ausente</option>
                            <option value="busy">Ocupado</option>
                        </select>
                        <div class="form-text">Apenas para agentes</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" class="form-control form-control-solid" 
                               min="1" placeholder="Deixe vazio para ilimitado" />
                        <div class="form-text">Número máximo de conversas que este agente pode atender simultaneamente</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="button" id="kt_modal_new_user_submit" class="btn btn-primary" onclick="document.getElementById('kt_modal_new_user_form').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));">
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
<!--end::Modal - Novo Usuário-->

<!--begin::Modal - Editar Usuário-->
<?php if (\App\Helpers\Permission::can('users.edit')): ?>
<div class="modal fade" id="kt_modal_edit_user" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Usuário</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_user_form" class="form" method="POST">
                <input type="hidden" name="user_id" id="edit_user_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" id="edit_user_name" class="form-control form-control-solid" placeholder="Nome completo" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Email</label>
                        <input type="email" name="email" id="edit_user_email" class="form-control form-control-solid" placeholder="email@exemplo.com" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Nova Senha</label>
                        <input type="password" name="password" id="edit_user_password" class="form-control form-control-solid" placeholder="Deixe vazio para não alterar" />
                        <div class="form-text">Deixe vazio se não quiser alterar a senha</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Role</label>
                        <select name="role" id="edit_user_role" class="form-select form-select-solid">
                            <option value="agent">Agente</option>
                            <option value="admin">Admin</option>
                            <option value="supervisor">Supervisor</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" id="edit_user_status" class="form-select form-select-solid">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status de Disponibilidade</label>
                        <select name="availability_status" id="edit_user_availability_status" class="form-select form-select-solid">
                            <option value="offline">Offline</option>
                            <option value="online">Online</option>
                            <option value="away">Ausente</option>
                            <option value="busy">Ocupado</option>
                        </select>
                        <div class="form-text">Apenas para agentes</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" id="edit_user_max_conversations" class="form-control form-control-solid" 
                               min="1" placeholder="Deixe vazio para ilimitado" />
                        <div class="form-text">Número máximo de conversas que este agente pode atender simultaneamente</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_user_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Alterações</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Editar Usuário-->

<!--begin::Modal - Atribuir Role Rápido-->
<?php if (\App\Helpers\Permission::can('users.edit')): ?>
<div class="modal fade" id="kt_modal_quick_assign_role" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Atribuir Role</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_quick_assign_role_form" class="form">
                <input type="hidden" name="user_id" id="quick_role_user_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Role</label>
                        <select name="role_id" id="quick_role_select" class="form-select form-select-solid" required>
                            <option value="">Selecione uma role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_quick_assign_role_submit" class="btn btn-primary">
                        <span class="indicator-label">Atribuir</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Atribuir Role Rápido-->

<!--begin::Modal - Atribuir Setor Rápido-->
<?php if (\App\Helpers\Permission::can('users.edit')): ?>
<div class="modal fade" id="kt_modal_quick_assign_department" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Atribuir Setor</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_quick_assign_department_form" class="form">
                <input type="hidden" name="user_id" id="quick_dept_user_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Setor</label>
                        <select name="department_id" id="quick_dept_select" class="form-select form-select-solid" required>
                            <option value="">Selecione um setor</option>
                            <?php foreach ($departments ?? [] as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_quick_assign_department_submit" class="btn btn-primary">
                        <span class="indicator-label">Atribuir</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Atribuir Setor Rápido-->

<?php
$content = ob_get_clean();
$usersUrl = \App\Helpers\Url::to('/users');
$scripts = <<<JAVASCRIPT
<script>
document.addEventListener("DOMContentLoaded", function() {
    const table = document.querySelector("#kt_users_table");
    const searchInput = document.querySelector("[data-kt-filter=\"search\"]");
    
    if (table && searchInput) {
        const datatable = $(table).DataTable({
            "info": false,
            "order": [],
            "pageLength": 10,
            "lengthChange": false,
            "columnDefs": [
                { "orderable": false, "targets": 6 } // Disable ordering on actions column
            ]
        });
        
        searchInput.addEventListener("keyup", function(e) {
            datatable.search(e.target.value).draw();
        });
    }
    
    const form = document.getElementById("kt_modal_new_user_form");
    if (form) {
        console.log("[FORM] Formulario de novo usuario encontrado, registrando handler AJAX");
        
        // Remover o onsubmit inline para usar o listener
        form.onsubmit = null;
        
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log("[OK] Submit interceptado, enviando via AJAX");
            
            const submitBtn = document.getElementById("kt_modal_new_user_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(formData)
            })
            .then(response => {
                console.log("[RESPONSE] Resposta recebida:", response.status, response.statusText);
                return response.json();
            })
            .then(data => {
                console.log("[DATA] Dados:", data);
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    // Usar SweetAlert2 se disponivel, senao usar toast
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            icon: "success",
                            title: "Sucesso!",
                            text: data.message || "Usuario criado com sucesso!",
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_user"));
                            if (modal) modal.hide();
                            location.reload();
                        });
                    } else {
                        alert(data.message || "Usuario criado com sucesso!");
                        const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_user"));
                        if (modal) modal.hide();
                        location.reload();
                    }
                } else {
                    // Mostrar erro de forma mais amigavel
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            icon: "error",
                            title: "Erro",
                            text: data.message || "Erro ao criar usuario"
                        });
                    } else {
                        alert("Erro: " + (data.message || "Erro ao criar usuario"));
                    }
                }
            })
            .catch(error => {
                console.error("[ERROR] Erro:", error);
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: "Erro ao criar usuario. Verifique o console para mais detalhes."
                    });
                } else {
                    alert("Erro ao criar usuario. Verifique o console para mais detalhes.");
                }
            });
        });
    } else {
        console.error("[ERROR] Formulario kt_modal_new_user_form nao encontrado!");
    }
    
    // Funcao para editar usuario (aceita elemento button ou link)
    window.editUser = function(element) {
        const userId = element.getAttribute("data-user-id");
        const userName = element.getAttribute("data-user-name") || "";
        const userEmail = element.getAttribute("data-user-email") || "";
        const userRole = element.getAttribute("data-user-role") || "agent";
        const userStatus = element.getAttribute("data-user-status") || "active";
        const userAvailability = element.getAttribute("data-user-availability") || "offline";
        const userMaxConversations = element.getAttribute("data-user-max-conversations") || "";
        
        document.getElementById("edit_user_id").value = userId;
        document.getElementById("edit_user_name").value = userName;
        document.getElementById("edit_user_email").value = userEmail;
        document.getElementById("edit_user_password").value = "";
        document.getElementById("edit_user_role").value = userRole;
        document.getElementById("edit_user_status").value = userStatus;
        document.getElementById("edit_user_availability_status").value = userAvailability;
        document.getElementById("edit_user_max_conversations").value = userMaxConversations;
        
        const form = document.getElementById("kt_modal_edit_user_form");
        const baseUrl = window.location.origin;
        form.action = baseUrl + "/users/" + userId;
        
        console.log("Editando usuario:", userId, "URL:", form.action);
        
        const modal = new bootstrap.Modal(document.getElementById("kt_modal_edit_user"));
        modal.show();
    };
    
    // Form de edicao
    const editForm = document.getElementById("kt_modal_edit_user_form");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_edit_user_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(editForm);
            
            // Remover password se estiver vazio
            if (!formData.get("password")) {
                formData.delete("password");
            }
            
            console.log("Enviando atualizacao para:", editForm.action);
            console.log("FormData:", Object.fromEntries(formData));
            
            fetch(editForm.action, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => {
                console.log("Response status:", response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Response data:", data);
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso!",
                        text: data.message || "Usuario atualizado com sucesso!",
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_user"));
                        modal.hide();
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro!",
                        text: data.message || "Erro ao atualizar usuario"
                    });
                }
            })
            .catch(error => {
                console.error("Erro ao atualizar usuario:", error);
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                Swal.fire({
                    icon: "error",
                    title: "Erro!",
                    text: error.message || "Erro ao atualizar usuario"
                });
            });
        });
    }
    
    // Funcao para atribuir role rapidamente
    window.quickAssignRole = function(link) {
        const userId = link.getAttribute("data-user-id");
        document.getElementById("quick_role_user_id").value = userId;
        document.getElementById("quick_role_select").value = "";
        
        const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_assign_role"));
        modal.show();
    };
    
    // Funcao para atribuir setor rapidamente
    window.quickAssignDepartment = function(link) {
        const userId = link.getAttribute("data-user-id");
        document.getElementById("quick_dept_user_id").value = userId;
        document.getElementById("quick_dept_select").value = "";
        
        const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_assign_department"));
        modal.show();
    };
    
    // Form de atribuicao rapida de role
    const quickRoleForm = document.getElementById("kt_modal_quick_assign_role_form");
    if (quickRoleForm) {
        quickRoleForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_quick_assign_role_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const userId = document.getElementById("quick_role_user_id").value;
            const roleId = document.getElementById("quick_role_select").value;
            
            if (!roleId) {
                alert("Selecione uma role");
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                return;
            }
            
            const formData = new FormData();
            formData.append("role_id", roleId);
            
            fetch("$usersUrl/" + userId + "/roles", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_quick_assign_role"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atribuir role"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atribuir role");
            });
        });
    }
    
    // Form de atribuicao rapida de setor
    const quickDeptForm = document.getElementById("kt_modal_quick_assign_department_form");
    if (quickDeptForm) {
        quickDeptForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_quick_assign_department_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const userId = document.getElementById("quick_dept_user_id").value;
            const departmentId = document.getElementById("quick_dept_select").value;
            
            if (!departmentId) {
                alert("Selecione um setor");
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                return;
            }
            
            const formData = new FormData();
            formData.append("department_id", departmentId);
            
            fetch("$usersUrl/" + userId + "/departments", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_quick_assign_department"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atribuir setor"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atribuir setor");
            });
        });
    }
    
    // Funcao para deletar usuario
    window.deleteUser = function(userId, userName) {
        if (!confirm("Tem certeza que deseja deletar o usuario \"" + userName + "\"?\n\nEsta acao nao pode ser desfeita.")) {
            return;
        }
        
        fetch("$usersUrl/" + userId, {
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
                alert("Erro: " + (data.message || "Erro ao deletar usuario"));
            }
        })
        .catch(error => {
            alert("Erro ao deletar usuario");
        });
    };
});
</script>
JAVASCRIPT;
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
