<?php
$layout = 'layouts.metronic.app';
$title = 'Setor - ' . htmlspecialchars($department['name'] ?? '');

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
                    <h3 class="text-gray-800 fw-bold mb-3"><?= htmlspecialchars($department['name']) ?></h3>
                    <?php if (!empty($department['description'])): ?>
                        <p class="text-gray-600 text-center"><?= htmlspecialchars($department['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($department['parent'])): ?>
                        <div class="mt-3">
                            <span class="text-muted fs-7">Setor Pai:</span>
                            <span class="badge badge-light-info ms-2"><?= htmlspecialchars($department['parent']['name']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (\App\Helpers\Permission::can('departments.edit')): ?>
                <div class="mt-5">
                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#kt_modal_edit_department">
                        <i class="ki-duotone ki-pencil fs-2"></i>
                        Editar Setor
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Estatísticas-->
        <?php if (!empty($stats)): ?>
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Estatísticas</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <div class="d-flex flex-column gap-5">
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-people fs-2x text-primary me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="flex-grow-1">
                            <div class="fs-2x fw-bold text-gray-800"><?= $stats['agents_count'] ?? 0 ?></div>
                            <div class="text-muted fs-6">Agentes</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-chat-text fs-2x text-success me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="flex-grow-1">
                            <div class="fs-2x fw-bold text-gray-800"><?= $stats['conversations_count'] ?? 0 ?></div>
                            <div class="text-muted fs-6">Conversas</div>
                        </div>
                    </div>
                    <?php if (!empty($department['children'])): ?>
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-abstract-26 fs-2x text-info me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="flex-grow-1">
                            <div class="fs-2x fw-bold text-gray-800"><?= count($department['children']) ?></div>
                            <div class="text-muted fs-6">Setores Filhos</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!--end::Card - Estatísticas-->
        <?php endif; ?>
    </div>
    <!--end::Sidebar-->

    <!--begin::Content-->
    <div class="flex-lg-row-fluid ms-xl-10">
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Agentes do Setor</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Gerencie os agentes deste setor</span>
                </h3>
                <?php if (\App\Helpers\Permission::can('departments.assign_agents')): ?>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_add_agent">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Adicionar Agente
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body py-3">
                <?php if (empty($department['agents'])): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-people fs-3x text-gray-400 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-800 fw-bold mb-2">Nenhum agente no setor</h3>
                        <div class="text-gray-500 fs-6">Adicione agentes a este setor.</div>
                    </div>
                <?php else: ?>
                    <div class="mb-5">
                        <div class="d-flex align-items-center position-relative">
                            <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <input type="text" id="kt_agents_search" class="form-control form-control-solid ps-13" placeholder="Buscar agentes por nome ou email..." />
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_agents_table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-200px">Agente</th>
                                    <th class="min-w-150px">Email</th>
                                    <th class="min-w-100px">Status</th>
                                    <th class="min-w-100px">Conversas</th>
                                    <th class="text-end min-w-100px">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold">
                                <?php foreach ($department['agents'] as $agent): ?>
                                    <tr data-agent-name="<?= strtolower(htmlspecialchars($agent['name'])) ?>" data-agent-email="<?= strtolower(htmlspecialchars($agent['email'])) ?>">
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
                                                    <span class="text-muted fs-7"><?= htmlspecialchars($agent['role'] ?? 'agent') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($agent['email'] ?? '') ?></td>
                                        <td>
                                            <?php
                                            $statusClass = ($agent['status'] ?? 'active') === 'active' ? 'success' : 'danger';
                                            $statusText = ($agent['status'] ?? 'active') === 'active' ? 'Ativo' : 'Inativo';
                                            ?>
                                            <span class="badge badge-light-<?= $statusClass ?>"><?= $statusText ?></span>
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
                                                <span class="badge badge-light-<?= $availColor ?> ms-1"><?= $availLabel ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $currentConversations = $agent['current_conversations'] ?? 0;
                                            $maxConversations = $agent['max_conversations'] ?? null;
                                            ?>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 fw-bold">
                                                    <?= $currentConversations ?>
                                                    <?php if ($maxConversations): ?>
                                                        / <?= $maxConversations ?>
                                                    <?php endif; ?>
                                                </span>
                                                <?php if ($maxConversations): ?>
                                                    <?php
                                                    $percentage = $maxConversations > 0 ? ($currentConversations / $maxConversations) * 100 : 0;
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
                                        <td class="text-end">
                                            <a href="<?= \App\Helpers\Url::to('/users/' . $agent['id']) ?>" class="btn btn-sm btn-light btn-active-light-primary me-2">
                                                Ver
                                            </a>
                                            <?php if (\App\Helpers\Permission::can('departments.assign_agents')): ?>
                                            <button type="button" class="btn btn-sm btn-light-danger" onclick="removeAgent(<?= $agent['id'] ?>, '<?= htmlspecialchars($agent['name'], ENT_QUOTES) ?>')">
                                                Remover
                                            </button>
                                            <?php endif; ?>
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
    </div>
    <!--end::Content-->
</div>
<!--end::Layout-->

<!--begin::Modal - Adicionar Agente-->
<?php if (\App\Helpers\Permission::can('departments.assign_agents')): ?>
<div class="modal fade" id="kt_modal_add_agent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Agente ao Setor</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_add_agent_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Agente</label>
                        <select name="user_id" id="kt_add_agent_select" class="form-select form-select-solid" data-control="select2" data-placeholder="Selecione um agente" required>
                            <option value="">Selecione um agente</option>
                            <?php
                            $currentAgentIds = array_column($department['agents'] ?? [], 'id');
                            foreach ($allAgents as $agent):
                                if (!in_array($agent['id'], $currentAgentIds)):
                            ?>
                                <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?> (<?= htmlspecialchars($agent['email']) ?>)</option>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </select>
                        <div class="form-text">Busque e selecione um agente para adicionar ao setor</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_add_agent_submit" class="btn btn-primary">
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
<!--end::Modal - Adicionar Agente-->

<!--begin::Modal - Editar Setor-->
<?php if (\App\Helpers\Permission::can('departments.edit')): ?>
<div class="modal fade" id="kt_modal_edit_department" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Setor</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_department_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <input type="hidden" name="id" id="edit_department_id" value="<?= $department['id'] ?>" />
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" id="edit_department_name" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($department['name']) ?>" placeholder="Nome do setor" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" id="edit_department_description" class="form-control form-control-solid" 
                                  rows="3" placeholder="Descrição do setor"><?= htmlspecialchars($department['description'] ?? '') ?></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Setor Pai</label>
                        <select name="parent_id" id="edit_department_parent" class="form-select form-select-solid">
                            <option value="">Nenhum (Setor raiz)</option>
                            <?php 
                            $allDepartments = \App\Models\Department::all();
                            foreach ($allDepartments as $parent): 
                                if ($parent['id'] == $department['id']) continue; // Não pode ser pai de si mesmo
                            ?>
                                <option value="<?= $parent['id'] ?>" <?= ($department['parent_id'] ?? null) == $parent['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($parent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_department_submit" class="btn btn-primary">
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
<!--end::Modal - Editar Setor-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // DataTable para agentes
    const agentsTable = document.getElementById("kt_agents_table");
    const searchInput = document.getElementById("kt_agents_search");
    
    if (agentsTable && searchInput) {
        const datatable = $(agentsTable).DataTable({
            "info": false,
            "order": [],
            "pageLength": 10,
            "lengthChange": false,
            "columnDefs": [
                { "orderable": false, "targets": 4 } // Disable ordering on actions column
            ]
        });
        
        searchInput.addEventListener("keyup", function(e) {
            datatable.search(this.value).draw();
        });
    }
    
    // Formulário de edição
    const editForm = document.getElementById("kt_modal_edit_department_form");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const departmentId = document.getElementById("edit_department_id").value;
            const submitBtn = document.getElementById("kt_modal_edit_department_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            fetch("' . \App\Helpers\Url::to('/departments/' . $department['id']) . '", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(new FormData(editForm))
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_department"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar setor"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atualizar setor");
            });
        });
    }
});

function removeAgent(agentId, agentName) {
    if (!confirm("Tem certeza que deseja remover o agente \\"" + agentName + "\\" deste setor?")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/departments/' . $department['id']) . '/agents/remove", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: new URLSearchParams({
            user_id: agentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao remover agente"));
        }
    })
    .catch(error => {
        console.error("Erro:", error);
        alert("Erro ao remover agente");
    });
    // Formulário de adicionar agente
    const form = document.getElementById("kt_modal_add_agent_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const userId = document.getElementById("kt_add_agent_select").value;
            if (!userId) {
                alert("Por favor, selecione um agente.");
                return;
            }
            
            const submitBtn = document.getElementById("kt_modal_add_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append("user_id", userId);
            
            fetch("' . \App\Helpers\Url::to('/departments/' . $department['id'] . '/agents') . '", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_add_agent"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao adicionar agente"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                console.error("Erro:", error);
                alert("Erro ao adicionar agente");
            });
        });
    }
    
    // Formulário de edição
    const editForm = document.getElementById("kt_modal_edit_department_form");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_edit_department_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            fetch("' . \App\Helpers\Url::to('/departments/' . $department['id']) . '", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(new FormData(editForm))
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_department"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar setor"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atualizar setor");
            });
        });
    }
});

function removeAgent(agentId, agentName) {
    if (!confirm("Tem certeza que deseja remover o agente \\"" + agentName + "\\" deste setor?")) {
        return;
    }
    
    const formData = new FormData();
    formData.append("user_id", agentId);
    
    fetch("' . \App\Helpers\Url::to('/departments/' . $department['id'] . '/agents/remove') . '", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao remover agente"));
        }
    })
    .catch(error => {
        console.error("Erro:", error);
        alert("Erro ao remover agente");
    });
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

