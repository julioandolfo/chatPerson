<?php
$layout = 'layouts.metronic.app';
$title = 'Agentes';

ob_start();
?>
<script>
// Funções globais para dropdown de ações (definidas antes do HTML)
window.editAgent = function(element) {
    try {
        const agentId = element.getAttribute("data-agent-id");
        const agentName = element.getAttribute("data-agent-name") || "";
        const agentEmail = element.getAttribute("data-agent-email") || "";
        const agentRole = element.getAttribute("data-agent-role") || "agent";
        const agentStatus = element.getAttribute("data-agent-status") || "active";
        const agentAvailability = element.getAttribute("data-agent-availability") || "offline";
        const agentMaxConversations = element.getAttribute("data-agent-max-conversations") || "";
        const agentQueueEnabled = element.getAttribute("data-agent-queue-enabled") || "1";
        const agentWooCommerceSellerId = element.getAttribute("data-agent-woocommerce-seller-id") || "";

        const editIdField = document.getElementById("edit_agent_id");
        const editNameField = document.getElementById("edit_agent_name");
        const editEmailField = document.getElementById("edit_agent_email");
        const editPasswordField = document.getElementById("edit_agent_password");
        const editRoleField = document.getElementById("edit_agent_role");
        const editStatusField = document.getElementById("edit_agent_status");
        const editAvailabilityField = document.getElementById("edit_agent_availability_status");
        const editMaxConversationsField = document.getElementById("edit_agent_max_conversations");
        const editQueueEnabledField = document.getElementById("edit_agent_queue_enabled");
        const editWooCommerceSellerIdField = document.getElementById("edit_agent_woocommerce_seller_id");

        if (editIdField) editIdField.value = agentId;
        if (editNameField) editNameField.value = agentName;
        if (editEmailField) editEmailField.value = agentEmail;
        if (editPasswordField) editPasswordField.value = "";
        if (editRoleField) editRoleField.value = agentRole;
        if (editStatusField) editStatusField.value = agentStatus;
        if (editAvailabilityField) editAvailabilityField.value = agentAvailability;
        if (editMaxConversationsField) editMaxConversationsField.value = agentMaxConversations;
        if (editQueueEnabledField) editQueueEnabledField.checked = (agentQueueEnabled === "1" || agentQueueEnabled === "true");
        if (editWooCommerceSellerIdField) editWooCommerceSellerIdField.value = agentWooCommerceSellerId;

        const form = document.getElementById("kt_modal_edit_agent_form");
        if (form) {
            form.action = "<?= \App\Helpers\Url::to('/users') ?>/" + agentId;
        }

        const modalElement = document.getElementById("kt_modal_edit_agent");
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    } catch (e) {
        console.error("Erro ao abrir modal de edição:", e);
    }
};

window.quickAssignRole = function(link) {
    const agentId = link.getAttribute("data-agent-id");
    document.getElementById("quick_role_agent_user_id").value = agentId;
    document.getElementById("quick_role_agent_select").value = "";

    const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_assign_role_agent"));
    modal.show();
};

window.quickAssignDepartment = function(link) {
    const agentId = link.getAttribute("data-agent-id");
    document.getElementById("quick_dept_agent_user_id").value = agentId;
    document.getElementById("quick_dept_agent_select").value = "";

    const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_assign_department_agent"));
    modal.show();
};

window.quickChangeAvailability = function(link) {
    const agentId = link.getAttribute("data-agent-id");
    const currentAvailability = link.getAttribute("data-agent-availability") || "offline";

    document.getElementById("quick_avail_agent_id").value = agentId;
    document.getElementById("quick_avail_select").value = currentAvailability;

    const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_change_availability"));
    modal.show();
};

window.reassignConversations = function(element) {
    const agentId = element.getAttribute("data-agent-id");
    const agentName = element.getAttribute("data-agent-name") || "";
    const conversationsCount = element.getAttribute("data-agent-conversations") || "0";

    document.getElementById("reassign_agent_id").value = agentId;
    document.getElementById("reassign_warning_text").textContent = 
        `Esta ação irá redistribuir TODAS as conversas do agente "${agentName}" (incluindo ${conversationsCount} ativa(s) + histórico fechado) e atualizar os contatos.`;

    // Desmarcar todas as checkboxes
    const checkboxes = document.querySelectorAll('.reassign-agent-checkbox');
    checkboxes.forEach(checkbox => {
        // Esconder o próprio agente da lista
        if (checkbox.value === agentId) {
            checkbox.closest('.form-check').style.display = 'none';
            checkbox.checked = false;
        } else {
            checkbox.closest('.form-check').style.display = 'block';
            checkbox.checked = false;
        }
    });

    const modal = new bootstrap.Modal(document.getElementById("kt_modal_reassign_conversations"));
    modal.show();
};

window.reassignConversations = function(element) {
    const agentId = element.getAttribute("data-agent-id");
    const agentName = element.getAttribute("data-agent-name") || "";
    const conversationsCount = element.getAttribute("data-agent-conversations") || "0";

    document.getElementById("reassign_agent_id").value = agentId;
    document.getElementById("reassign_warning_text").textContent = 
        `Esta ação irá redistribuir TODAS as conversas do agente "${agentName}" (incluindo ${conversationsCount} ativa(s) + histórico fechado) e atualizar os contatos.`;

    // Desmarcar todas as checkboxes
    const checkboxes = document.querySelectorAll('.reassign-agent-checkbox');
    checkboxes.forEach(checkbox => {
        // Esconder o próprio agente da lista
        if (checkbox.value === agentId) {
            checkbox.closest('.form-check').style.display = 'none';
            checkbox.checked = false;
        } else {
            checkbox.closest('.form-check').style.display = 'block';
            checkbox.checked = false;
        }
    });

    const modal = new bootstrap.Modal(document.getElementById("kt_modal_reassign_conversations"));
    modal.show();
};

window.deleteAgent = function(agentId, agentName) {
    if (!confirm('Tem certeza que deseja deletar o agente "' + agentName + '"?\n\nEsta ação não pode ser desfeita.')) {
        return;
    }

    fetch("<?= \App\Helpers\Url::to('/users') ?>/" + agentId, {
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
            alert("Erro: " + (data.message || "Erro ao deletar agente"));
        }
    })
    .catch(() => {
        alert("Erro ao deletar agente");
    });
};
</script>
<style>
/* Garantir que dropdowns da tabela apareçam acima do card */
.table-responsive {
    overflow: visible !important;
}

#kt_agents_table tbody tr {
    position: relative;
}

#kt_agents_table .dropdown-menu {
    z-index: 1050 !important;
    position: absolute !important;
}

/* Quando dropdown está aberto, aumentar z-index da linha da tabela */
#kt_agents_table tbody tr:has(.dropdown-menu.show) {
    z-index: 1050;
}

/* Alternativa para navegadores que não suportam :has() */
#kt_agents_table tbody tr .btn-group.show {
    position: relative;
    z-index: 1050;
}

#kt_agents_table tbody tr .dropdown.show {
    position: relative;
    z-index: 1050;
}

#kt_agents_table .btn-group .dropdown-menu {
    z-index: 1051 !important;
}

/* Evitar que o card esconda o dropdown */
.card {
    overflow: visible !important;
}

.card-body {
    overflow: visible !important;
}
</style>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Agentes</h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <input type="text" data-kt-filter="search" class="form-control form-control-solid w-250px" placeholder="Buscar agentes..." />
                <?php if (\App\Helpers\Permission::can('agents.create')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_agent">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Novo Agente
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($agents)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-people fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum agente encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo agente.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_agents_table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-200px">Agente</th>
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
                        <?php foreach ($agents as $agent): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-45px me-5">
                                            <?php if (!empty($agent['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($agent['avatar']) ?>" alt="<?= htmlspecialchars($agent['name']) ?>" />
                                            <?php else: ?>
                                                <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                                                    <?= mb_substr(htmlspecialchars($agent['name'] ?? 'A'), 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-800 fw-bold"><?= htmlspecialchars($agent['name'] ?? 'Sem nome') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($agent['email'] ?? '') ?></td>
                                <td>
                                    <span class="badge badge-light-info"><?= htmlspecialchars($agent['role'] ?? 'agent') ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = ($agent['status'] ?? 'active') === 'active' ? 'success' : 'danger';
                                    $statusText = ($agent['status'] ?? 'active') === 'active' ? 'Ativo' : 'Inativo';
                                    ?>
                                    <span class="badge badge-light-<?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($agent['availability_status'])): ?>
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
                                        $availStatus = $agent['availability_status'] ?? 'offline';
                                        $availColor = $availColors[$availStatus] ?? 'secondary';
                                        $availLabel = $availLabels[$availStatus] ?? 'Desconhecido';
                                        ?>
                                        <span class="badge badge-light-<?= $availColor ?>"><?= $availLabel ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-bold">
                                            <?= $agent['current_conversations'] ?? 0 ?>
                                            <?php if (!empty($agent['max_conversations'])): ?>
                                                / <?= $agent['max_conversations'] ?>
                                            <?php else: ?>
                                                <span class="text-muted fs-7">(sem limite)</span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if (!empty($agent['max_conversations'])): ?>
                                            <?php
                                            $percentage = ($agent['current_conversations'] ?? 0) / $agent['max_conversations'] * 100;
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
                                </td>
                                <td>
                                    <?php if (!empty($agent['roles_names'])): ?>
                                        <?php
                                        $roleNames = explode(',', $agent['roles_names']);
                                        foreach ($roleNames as $roleName):
                                        ?>
                                            <span class="badge badge-light-primary me-1"><?= htmlspecialchars(trim($roleName)) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($agent['departments_names'])): ?>
                                        <?php
                                        $deptNames = explode(',', $agent['departments_names']);
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
                                        <a href="<?= \App\Helpers\Url::to('/users/' . $agent['id']) ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                            Ver
                                        </a>
                                        <?php if (\App\Helpers\Permission::can('agents.edit')): ?>
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
                                                       data-agent-id="<?= $agent['id'] ?>"
                                                       data-agent-name="<?= htmlspecialchars($agent['name'] ?? '', ENT_QUOTES) ?>"
                                                       data-agent-email="<?= htmlspecialchars($agent['email'] ?? '', ENT_QUOTES) ?>"
                                                       data-agent-role="<?= htmlspecialchars($agent['role'] ?? 'agent', ENT_QUOTES) ?>"
                                                       data-agent-status="<?= htmlspecialchars($agent['status'] ?? 'active', ENT_QUOTES) ?>"
                                                       data-agent-availability="<?= htmlspecialchars($agent['availability_status'] ?? 'offline', ENT_QUOTES) ?>"
                                                       data-agent-max-conversations="<?= htmlspecialchars($agent['max_conversations'] ?? '', ENT_QUOTES) ?>"
                                                       data-agent-queue-enabled="<?= htmlspecialchars(($agent['queue_enabled'] ?? 1) ? '1' : '0', ENT_QUOTES) ?>"
                                                       data-agent-woocommerce-seller-id="<?= htmlspecialchars($agent['woocommerce_seller_id'] ?? '', ENT_QUOTES) ?>"
                                                       onclick="editAgent(this); return false;">
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
                                                       data-agent-id="<?= $agent['id'] ?>"
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
                                                       data-agent-id="<?= $agent['id'] ?>"
                                                       onclick="quickAssignDepartment(this); return false;">
                                                        <i class="ki-duotone ki-abstract-26 fs-5 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Atribuir Setor
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" 
                                                       data-agent-id="<?= $agent['id'] ?>"
                                                       data-agent-availability="<?= htmlspecialchars($agent['availability_status'] ?? 'offline', ENT_QUOTES) ?>"
                                                       onclick="quickChangeAvailability(this); return false;">
                                                        <i class="ki-duotone ki-status fs-5 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Alterar Disponibilidade
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-warning" href="#" 
                                                       data-agent-id="<?= $agent['id'] ?>"
                                                       data-agent-name="<?= htmlspecialchars($agent['name'] ?? '', ENT_QUOTES) ?>"
                                                       data-agent-conversations="<?= $agent['current_conversations'] ?? 0 ?>"
                                                       onclick="reassignConversations(this); return false;">
                                                        <i class="ki-duotone ki-arrow-mix fs-5 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Reatribuir Conversas
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (\App\Helpers\Permission::can('agents.delete')): ?>
                                        <button type="button" class="btn btn-sm btn-light-danger" 
                                                onclick="deleteAgent(<?= $agent['id'] ?>, '<?= htmlspecialchars($agent['name'] ?? '', ENT_QUOTES) ?>')">
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

<!--begin::Modal - Novo Agente-->
<?php if (\App\Helpers\Permission::can('agents.create')): ?>
<div class="modal fade" id="kt_modal_new_agent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Agente</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_agent_form" class="form" action="<?= \App\Helpers\Url::to('/users') ?>" method="POST" enctype="multipart/form-data">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Avatar</label>
                        <input type="file" name="avatar_file" class="form-control form-control-solid" accept="image/*" />
                        <div class="form-text">JPG, PNG, GIF ou WEBP. Máximo 2MB</div>
                    </div>
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
                        <div class="form-text">Status inicial de disponibilidade do agente</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" class="form-control form-control-solid" 
                               min="1" placeholder="Deixe vazio para ilimitado" />
                        <div class="form-text">Número máximo de conversas que este agente pode atender simultaneamente</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Fila de Distribuição</label>
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input type="hidden" name="queue_enabled" value="0" />
                            <input class="form-check-input" type="checkbox" name="queue_enabled" value="1" id="new_agent_queue_enabled" checked />
                            <label class="form-check-label fw-semibold text-gray-700" for="new_agent_queue_enabled">
                                Receber novas conversas automaticamente
                            </label>
                        </div>
                        <div class="form-text">Quando desabilitado, o agente não receberá novas conversas via automações ou distribuição automática</div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2 d-flex align-items-center">
                            <i class="ki-duotone ki-shop fs-2 text-success me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            ID do WooCommerce
                        </label>
                        <input type="number" name="woocommerce_seller_id" class="form-control form-control-solid" 
                               min="1" placeholder="Ex: 1" />
                        <div class="form-text">
                            <strong>Métricas de Conversão:</strong> Informe o ID do vendedor no WooCommerce para rastrear conversões e vendas.
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_agent_submit" class="btn btn-primary">
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
<!--end::Modal - Novo Agente-->

<!--begin::Modal - Editar Agente-->
<?php if (\App\Helpers\Permission::can('agents.edit')): ?>
<div class="modal fade" id="kt_modal_edit_agent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Agente</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_agent_form" class="form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="user_id" id="edit_agent_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Avatar</label>
                        <input type="file" name="avatar_file" class="form-control form-control-solid" accept="image/*" />
                        <div class="form-text">JPG, PNG, GIF ou WEBP. Máximo 2MB. Deixe vazio para manter o atual.</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" id="edit_agent_name" class="form-control form-control-solid" placeholder="Nome completo" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Email</label>
                        <input type="email" name="email" id="edit_agent_email" class="form-control form-control-solid" placeholder="email@exemplo.com" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Nova Senha</label>
                        <input type="password" name="password" id="edit_agent_password" class="form-control form-control-solid" placeholder="Deixe vazio para não alterar" />
                        <div class="form-text">Deixe vazio se não quiser alterar a senha</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Role</label>
                        <select name="role" id="edit_agent_role" class="form-select form-select-solid">
                            <option value="agent">Agente</option>
                            <option value="admin">Admin</option>
                            <option value="supervisor">Supervisor</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" id="edit_agent_status" class="form-select form-select-solid">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status de Disponibilidade</label>
                        <select name="availability_status" id="edit_agent_availability_status" class="form-select form-select-solid">
                            <option value="offline">Offline</option>
                            <option value="online">Online</option>
                            <option value="away">Ausente</option>
                            <option value="busy">Ocupado</option>
                        </select>
                        <div class="form-text">Status de disponibilidade do agente</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" id="edit_agent_max_conversations" class="form-control form-control-solid" 
                               min="1" placeholder="Deixe vazio para ilimitado" />
                        <div class="form-text">Número máximo de conversas que este agente pode atender simultaneamente</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Fila de Distribuição</label>
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input type="hidden" name="queue_enabled" value="0" />
                            <input class="form-check-input" type="checkbox" name="queue_enabled" value="1" id="edit_agent_queue_enabled" />
                            <label class="form-check-label fw-semibold text-gray-700" for="edit_agent_queue_enabled">
                                Receber novas conversas automaticamente
                            </label>
                        </div>
                        <div class="form-text">Quando desabilitado, o agente não receberá novas conversas via automações ou distribuição automática</div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2 d-flex align-items-center">
                            <i class="ki-duotone ki-shop fs-2 text-success me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            ID do WooCommerce
                        </label>
                        <input type="number" name="woocommerce_seller_id" id="edit_agent_woocommerce_seller_id" class="form-control form-control-solid" 
                               min="1" placeholder="Ex: 1" />
                        <div class="form-text">
                            <strong>Métricas de Conversão:</strong> Informe o ID do vendedor no WooCommerce para rastrear conversões e vendas.
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_agent_submit" class="btn btn-primary">
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
<!--end::Modal - Editar Agente-->

<!--begin::Modal - Atribuir Role Rápido-->
<?php if (\App\Helpers\Permission::can('agents.edit')): ?>
<div class="modal fade" id="kt_modal_quick_assign_role_agent" tabindex="-1" aria-hidden="true">
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
            <form id="kt_modal_quick_assign_role_agent_form" class="form">
                <input type="hidden" name="user_id" id="quick_role_agent_user_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Role</label>
                        <select name="role_id" id="quick_role_agent_select" class="form-select form-select-solid" required>
                            <option value="">Selecione uma role</option>
                            <?php foreach ($roles ?? [] as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_quick_assign_role_agent_submit" class="btn btn-primary">
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
<?php if (\App\Helpers\Permission::can('agents.edit')): ?>
<div class="modal fade" id="kt_modal_quick_assign_department_agent" tabindex="-1" aria-hidden="true">
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
            <form id="kt_modal_quick_assign_department_agent_form" class="form">
                <input type="hidden" name="user_id" id="quick_dept_agent_user_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Setor</label>
                        <select name="department_id" id="quick_dept_agent_select" class="form-select form-select-solid" required>
                            <option value="">Selecione um setor</option>
                            <?php foreach ($departments ?? [] as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_quick_assign_department_agent_submit" class="btn btn-primary">
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

<!--begin::Modal - Alterar Disponibilidade Rápido-->
<?php if (\App\Helpers\Permission::can('agents.edit')): ?>
<div class="modal fade" id="kt_modal_quick_change_availability" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Alterar Disponibilidade</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_quick_change_availability_form" class="form">
                <input type="hidden" name="agent_id" id="quick_avail_agent_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Status de Disponibilidade</label>
                        <select name="availability_status" id="quick_avail_select" class="form-select form-select-solid" required>
                            <option value="offline">Offline</option>
                            <option value="online">Online</option>
                            <option value="away">Ausente</option>
                            <option value="busy">Ocupado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_quick_change_availability_submit" class="btn btn-primary">
                        <span class="indicator-label">Alterar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Alterar Disponibilidade Rápido-->

<!--begin::Modal - Reatribuir Conversas-->
<?php if (\App\Helpers\Permission::can('agents.edit')): ?>
<div class="modal fade" id="kt_modal_reassign_conversations" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-700px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Reatribuir Conversas</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_reassign_conversations_form" class="form">
                <input type="hidden" name="agent_id" id="reassign_agent_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="alert alert-warning d-flex align-items-center mb-7">
                        <i class="ki-duotone ki-information-5 fs-2x text-warning me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h5 class="mb-1">Atenção: Reatribuição em Massa</h5>
                            <span id="reassign_warning_text">Esta ação irá redistribuir TODAS as conversas (incluindo fechadas) e contatos do agente.</span>
                        </div>
                    </div>
                    
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6 mb-7">
                        <i class="ki-duotone ki-information fs-2tx text-primary me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-stack flex-grow-1">
                            <div class="fw-semibold">
                                <h4 class="text-gray-900 fw-bold">O que será reatribuído?</h4>
                                <div class="fs-6 text-gray-700">
                                    <div class="mb-2">
                                        <i class="ki-duotone ki-check fs-5 text-success me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <strong>Todas as Conversas:</strong> Abertas, pendentes e fechadas (histórico completo)
                                    </div>
                                    <div class="mb-2">
                                        <i class="ki-duotone ki-check fs-5 text-success me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <strong>Agente do Contato:</strong> Registros de contact_agents serão atualizados
                                    </div>
                                    <div>
                                        <i class="ki-duotone ki-information-4 fs-5 text-info me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <strong>Distribuição:</strong> As conversas serão distribuídas igualmente entre os agentes selecionados
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Selecione os Agentes para Redistribuição</label>
                        <div class="border border-gray-300 rounded p-5" style="max-height: 300px; overflow-y: auto;">
                            <?php 
                            $activeAgents = \App\Models\User::getActiveAgents();
                            foreach ($activeAgents as $activeAgent): 
                            ?>
                                <div class="form-check form-check-custom form-check-solid mb-3">
                                    <input class="form-check-input reassign-agent-checkbox" 
                                           type="checkbox" 
                                           name="target_agent_ids[]" 
                                           value="<?= $activeAgent['id'] ?>" 
                                           id="target_agent_<?= $activeAgent['id'] ?>" />
                                    <label class="form-check-label d-flex align-items-center" for="target_agent_<?= $activeAgent['id'] ?>">
                                        <div class="symbol symbol-30px me-3">
                                            <?php if (!empty($activeAgent['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($activeAgent['avatar']) ?>" alt="" />
                                            <?php else: ?>
                                                <div class="symbol-label fs-6 fw-semibold text-primary bg-light-primary">
                                                    <?= mb_substr(htmlspecialchars($activeAgent['name'] ?? 'A'), 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold"><?= htmlspecialchars($activeAgent['name']) ?></span>
                                            <span class="text-muted fs-7"><?= htmlspecialchars($activeAgent['email']) ?></span>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Selecione um ou mais agentes que receberão as conversas redistribuídas</div>
                        <div id="reassign_agents_error" class="text-danger fs-7 mt-2" style="display: none;"></div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="also_deactivate" value="1" id="reassign_also_deactivate" />
                            <label class="form-check-label fw-semibold text-gray-700" for="reassign_also_deactivate">
                                Desativar agente após reatribuição
                            </label>
                        </div>
                        <div class="form-text">Marca o agente como inativo automaticamente após a redistribuição</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_reassign_conversations_submit" class="btn btn-warning">
                        <span class="indicator-label">
                            <i class="ki-duotone ki-arrow-mix fs-3 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Reatribuir Conversas
                        </span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Reatribuir Conversas-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
// Definir funções globais ANTES de qualquer código
window.editAgent = function(element) {
    try {
        const agentId = element.getAttribute("data-agent-id");
        const agentName = element.getAttribute("data-agent-name") || "";
        const agentEmail = element.getAttribute("data-agent-email") || "";
        const agentRole = element.getAttribute("data-agent-role") || "agent";
        const agentStatus = element.getAttribute("data-agent-status") || "active";
        const agentAvailability = element.getAttribute("data-agent-availability") || "offline";
        const agentMaxConversations = element.getAttribute("data-agent-max-conversations") || "";
        const agentQueueEnabled = element.getAttribute("data-agent-queue-enabled") || "1";
        const agentWooCommerceSellerId = element.getAttribute("data-agent-woocommerce-seller-id") || "";
        
        const editIdField = document.getElementById("edit_agent_id");
        const editNameField = document.getElementById("edit_agent_name");
        const editEmailField = document.getElementById("edit_agent_email");
        const editPasswordField = document.getElementById("edit_agent_password");
        const editRoleField = document.getElementById("edit_agent_role");
        const editStatusField = document.getElementById("edit_agent_status");
        const editAvailabilityField = document.getElementById("edit_agent_availability_status");
        const editMaxConversationsField = document.getElementById("edit_agent_max_conversations");
        const editQueueEnabledField = document.getElementById("edit_agent_queue_enabled");
        const editWooCommerceSellerIdField = document.getElementById("edit_agent_woocommerce_seller_id");
        
        if (editIdField) editIdField.value = agentId;
        if (editNameField) editNameField.value = agentName;
        if (editEmailField) editEmailField.value = agentEmail;
        if (editPasswordField) editPasswordField.value = "";
        if (editRoleField) editRoleField.value = agentRole;
        if (editStatusField) editStatusField.value = agentStatus;
        if (editAvailabilityField) editAvailabilityField.value = agentAvailability;
        if (editMaxConversationsField) editMaxConversationsField.value = agentMaxConversations;
        if (editQueueEnabledField) editQueueEnabledField.checked = (agentQueueEnabled === "1" || agentQueueEnabled === "true");
        if (editWooCommerceSellerIdField) editWooCommerceSellerIdField.value = agentWooCommerceSellerId;
        
        const form = document.getElementById("kt_modal_edit_agent_form");
        if (form) {
            form.action = "' . \App\Helpers\Url::to('/users') . '/" + agentId;
        }
        
        const modalElement = document.getElementById("kt_modal_edit_agent");
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else {
            console.warn("Modal de edição não encontrado");
        }
    } catch(e) {
        console.error("Erro ao abrir modal de edição:", e);
    }
};

// Definir outras funções globais ANTES do DOMContentLoaded
window.quickAssignRole = function(link) {
    const agentId = link.getAttribute("data-agent-id");
    document.getElementById("quick_role_agent_user_id").value = agentId;
    document.getElementById("quick_role_agent_select").value = "";
    
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_assign_role_agent"));
    modal.show();
};

window.quickAssignDepartment = function(link) {
    const agentId = link.getAttribute("data-agent-id");
    document.getElementById("quick_dept_agent_user_id").value = agentId;
    document.getElementById("quick_dept_agent_select").value = "";
    
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_assign_department_agent"));
    modal.show();
};

window.quickChangeAvailability = function(link) {
    const agentId = link.getAttribute("data-agent-id");
    const currentAvailability = link.getAttribute("data-agent-availability") || "offline";
    
    document.getElementById("quick_avail_agent_id").value = agentId;
    document.getElementById("quick_avail_select").value = currentAvailability;
    
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_change_availability"));
    modal.show();
};

// Função para reatribuir conversas
window.reassignConversations = function(element) {
    const agentId = element.getAttribute("data-agent-id");
    const agentName = element.getAttribute("data-agent-name") || "";
    const conversationsCount = element.getAttribute("data-agent-conversations") || "0";

    document.getElementById("reassign_agent_id").value = agentId;
    document.getElementById("reassign_warning_text").textContent = 
        \`Esta ação irá redistribuir TODAS as conversas do agente "\${agentName}" (incluindo \${conversationsCount} ativa(s) + histórico fechado) e atualizar os contatos.\`;

    // Desmarcar todas as checkboxes
    const checkboxes = document.querySelectorAll(".reassign-agent-checkbox");
    checkboxes.forEach(checkbox => {
        // Esconder o próprio agente da lista
        if (checkbox.value === agentId) {
            checkbox.closest(".form-check").style.display = "none";
            checkbox.checked = false;
        } else {
            checkbox.closest(".form-check").style.display = "block";
            checkbox.checked = false;
        }
    });

    const modal = new bootstrap.Modal(document.getElementById("kt_modal_reassign_conversations"));
    modal.show();
};

window.deleteAgent = function(agentId, agentName) {
    if (!confirm("Tem certeza que deseja deletar o agente \\"" + agentName + "\\"?\\n\\nEsta ação não pode ser desfeita.")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/users') . '/" + agentId, {
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
            alert("Erro: " + (data.message || "Erro ao deletar agente"));
        }
    })
    .catch(error => {
        alert("Erro ao deletar agente");
    });
};

document.addEventListener("DOMContentLoaded", function() {
    const table = document.querySelector("#kt_agents_table");
    const searchInput = document.querySelector("[data-kt-filter=\"search\"]");
    
    if (table && searchInput) {
        const datatable = $(table).DataTable({
            "info": false,
            "order": [],
            "pageLength": 10,
            "lengthChange": false,
            "columnDefs": [
                { "orderable": false, "targets": 8 } // Disable ordering on actions column
            ]
        });
        
        searchInput.addEventListener("keyup", function(e) {
            datatable.search(e.target.value).draw();
        });
    }
    
    // Form de criação de agente
    const form = document.getElementById("kt_modal_new_agent_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            fetch(form.action, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new FormData(form)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_agent"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar agente"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar agente");
            });
        });
    }
    
    // Form de edição de agente
    const editForm = document.getElementById("kt_modal_edit_agent_form");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_edit_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(editForm);
            
            // Remover password se estiver vazio
            if (!formData.get("password")) {
                formData.delete("password");
            }
            
            fetch(editForm.action, {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_agent"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar agente"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atualizar agente");
            });
        });
    }
    
    // Form de atribuição rápida de role
    const quickRoleForm = document.getElementById("kt_modal_quick_assign_role_agent_form");
    if (quickRoleForm) {
        quickRoleForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_quick_assign_role_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const agentId = document.getElementById("quick_role_agent_user_id").value;
            const roleId = document.getElementById("quick_role_agent_select").value;
            
            if (!roleId) {
                alert("Selecione uma role");
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                return;
            }
            
            const formData = new FormData();
            formData.append("role_id", roleId);
            
            fetch("' . \App\Helpers\Url::to('/users') . '/" + agentId + "/roles", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_quick_assign_role_agent"));
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
    
    // Função para atribuir setor rapidamente
    window.quickAssignDepartment = function(link) {
        const agentId = link.getAttribute("data-agent-id");
        document.getElementById("quick_dept_agent_user_id").value = agentId;
        document.getElementById("quick_dept_agent_select").value = "";
        
        const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_assign_department_agent"));
        modal.show();
    };
    
    // Form de atribuição rápida de setor
    const quickDeptForm = document.getElementById("kt_modal_quick_assign_department_agent_form");
    if (quickDeptForm) {
        quickDeptForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_quick_assign_department_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const agentId = document.getElementById("quick_dept_agent_user_id").value;
            const departmentId = document.getElementById("quick_dept_agent_select").value;
            
            if (!departmentId) {
                alert("Selecione um setor");
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                return;
            }
            
            const formData = new FormData();
            formData.append("department_id", departmentId);
            
            fetch("' . \App\Helpers\Url::to('/users') . '/" + agentId + "/departments", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_quick_assign_department_agent"));
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
    
    // Função para alterar disponibilidade rapidamente
    window.quickChangeAvailability = function(link) {
        const agentId = link.getAttribute("data-agent-id");
        const currentAvailability = link.getAttribute("data-agent-availability") || "offline";
        
        document.getElementById("quick_avail_agent_id").value = agentId;
        document.getElementById("quick_avail_select").value = currentAvailability;
        
        const modal = new bootstrap.Modal(document.getElementById("kt_modal_quick_change_availability"));
        modal.show();
    };
    
    // Form de alteração rápida de disponibilidade
    const quickAvailForm = document.getElementById("kt_modal_quick_change_availability_form");
    if (quickAvailForm) {
        quickAvailForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_quick_change_availability_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const agentId = document.getElementById("quick_avail_agent_id").value;
            const availabilityStatus = document.getElementById("quick_avail_select").value;
            
            const formData = new FormData();
            formData.append("availability_status", availabilityStatus);
            
            fetch("' . \App\Helpers\Url::to('/agents') . '/" + agentId + "/availability", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_quick_change_availability"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao alterar disponibilidade"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao alterar disponibilidade");
            });
        });
    }
    
    // Função para deletar agente
    window.deleteAgent = function(agentId, agentName) {
        if (!confirm("Tem certeza que deseja deletar o agente \"" + agentName + "\"?\n\nEsta ação não pode ser desfeita.")) {
            return;
        }
        
        fetch("' . \App\Helpers\Url::to('/users') . '/" + agentId, {
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
                alert("Erro: " + (data.message || "Erro ao deletar agente"));
            }
        })
        .catch(error => {
            alert("Erro ao deletar agente");
        });
    };
    
    // Form de reatribuição de conversas
    const reassignForm = document.getElementById("kt_modal_reassign_conversations_form");
    if (reassignForm) {
        reassignForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            // Validar que pelo menos um agente foi selecionado
            const checkboxes = document.querySelectorAll(".reassign-agent-checkbox:checked");
            const errorDiv = document.getElementById("reassign_agents_error");
            
            if (checkboxes.length === 0) {
                errorDiv.textContent = "Selecione pelo menos um agente para redistribuição";
                errorDiv.style.display = "block";
                return;
            }
            
            errorDiv.style.display = "none";
            
            const submitBtn = document.getElementById("kt_modal_reassign_conversations_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const agentId = document.getElementById("reassign_agent_id").value;
            const formData = new FormData(reassignForm);
            
            fetch("' . \App\Helpers\Url::to('/agents') . '/" + agentId + "/reassign-conversations", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_reassign_conversations"));
                    modal.hide();
                    
                    // Mostrar mensagem de sucesso
                    alert(data.message || "Conversas reatribuídas com sucesso!");
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao reatribuir conversas"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao reatribuir conversas");
                console.error(error);
            });
        });
    }
});
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
