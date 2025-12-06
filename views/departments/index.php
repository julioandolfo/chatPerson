<?php
$layout = 'layouts.metronic.app';
$title = 'Setores';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <input type="text" data-kt-filter="search" class="form-control form-control-solid w-250px ps-13" placeholder="Buscar setores..." />
            </div>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('departments.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_department">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Setor
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($departments)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-abstract-26 fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum setor encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo setor.</div>
            </div>
        <?php else: ?>
            <!--begin::Tabs-->
            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                <li class="nav-item mt-2">
                    <a class="nav-link text-active-primary ms-0 me-10 active" data-bs-toggle="tab" href="#kt_tab_tree">
                        Árvore Hierárquica
                    </a>
                </li>
                <li class="nav-item mt-2">
                    <a class="nav-link text-active-primary ms-0 me-10" data-bs-toggle="tab" href="#kt_tab_list">
                        Lista
                    </a>
                </li>
            </ul>
            <!--end::Tabs-->
            
            <!--begin::Tab Content-->
            <div class="tab-content">
                <!--begin::Tab Pane - Tree-->
                <div class="tab-pane fade show active" id="kt_tab_tree" role="tabpanel">
                    <div class="mt-7">
                        <?php if (!empty($tree)): ?>
                            <div id="kt_departments_tree" class="tree-container">
                                <?php foreach ($tree as $root): ?>
                                    <?php 
                                    $level = 0; // Iniciar no nível 0
                                    include __DIR__ . '/partials/tree-node.php'; 
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10">
                                <p class="text-muted">Nenhum setor encontrado</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Tab Pane - Tree-->
                
                <!--begin::Tab Pane - List-->
                <div class="tab-pane fade" id="kt_tab_list" role="tabpanel">
                    <div class="table-responsive mt-7">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_departments_table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-200px">Nome</th>
                                    <th class="min-w-150px">Setor Pai</th>
                                    <th class="min-w-100px">Agentes</th>
                                    <th class="min-w-100px">Filhos</th>
                                    <th class="text-end min-w-150px">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold">
                                <?php foreach ($departments as $dept): ?>
                                    <tr data-dept-name="<?= strtolower(htmlspecialchars($dept['name'])) ?>" data-dept-description="<?= strtolower(htmlspecialchars($dept['description'] ?? '')) ?>">
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 fw-bold"><?= htmlspecialchars($dept['name']) ?></span>
                                                <?php if (!empty($dept['description'])): ?>
                                                    <span class="text-muted fs-7"><?= htmlspecialchars(mb_substr($dept['description'], 0, 50)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($dept['parent_id']): ?>
                                                <?php
                                                $parent = \App\Models\Department::find($dept['parent_id']);
                                                echo $parent ? htmlspecialchars($parent['name']) : '-';
                                                ?>
                                            <?php else: ?>
                                                <span class="badge badge-light-info">Raiz</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-primary"><?= $dept['agents_count'] ?? 0 ?> agente(s)</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-success"><?= $dept['children_count'] ?? 0 ?> filho(s)</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="<?= \App\Helpers\Url::to('/departments/' . $dept['id']) ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                                    Ver
                                                </a>
                                                <?php if (\App\Helpers\Permission::can('departments.edit')): ?>
                                                <button type="button" class="btn btn-sm btn-light btn-active-light-warning" onclick="editDepartment(<?= $dept['id'] ?>)">
                                                    <i class="ki-duotone ki-pencil fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if (\App\Helpers\Permission::can('departments.delete')): ?>
                                                <button type="button" class="btn btn-sm btn-light-danger" onclick="deleteDepartment(<?= $dept['id'] ?>, '<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>')">
                                                    <i class="ki-duotone ki-trash fs-5">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                        <span class="path4"></span>
                                                        <span class="path5"></span>
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
                </div>
                <!--end::Tab Pane - List-->
            </div>
            <!--end::Tab Content-->
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<!--begin::Modal - Novo Setor-->
<?php if (\App\Helpers\Permission::can('departments.create')): ?>
<div class="modal fade" id="kt_modal_new_department" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Setor</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_department_form" class="form" action="<?= \App\Helpers\Url::to('/departments') ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Nome do setor" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Descrição do setor"></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Setor Pai</label>
                        <select name="parent_id" class="form-select form-select-solid" id="kt_modal_new_department_parent">
                            <option value="">Nenhum (Setor raiz)</option>
                            <?php 
                            $allDepartments = \App\Models\Department::all();
                            foreach ($allDepartments as $parent): 
                            ?>
                                <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Deixe em branco para criar um setor raiz</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_department_submit" class="btn btn-primary">
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
<!--end::Modal - Novo Setor-->

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
                    <input type="hidden" name="id" id="edit_department_id" />
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" id="edit_department_name" class="form-control form-control-solid" placeholder="Nome do setor" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" id="edit_department_description" class="form-control form-control-solid" rows="3" placeholder="Descrição do setor"></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Setor Pai</label>
                        <select name="parent_id" id="edit_department_parent" class="form-select form-select-solid">
                            <option value="">Nenhum (Setor raiz)</option>
                            <?php 
                            $allDepartments = \App\Models\Department::all();
                            foreach ($allDepartments as $parent): 
                            ?>
                                <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['name']) ?></option>
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
    // DataTable para busca e filtros
    const table = document.querySelector("#kt_departments_table");
    const searchInput = document.querySelector("[data-kt-filter=\"search\"]");
    
    if (table && searchInput) {
        const datatable = $(table).DataTable({
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
    
    // Formulário de criação
    const createForm = document.getElementById("kt_modal_new_department_form");
    if (createForm) {
        createForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_department_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            fetch(createForm.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(new FormData(createForm))
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_department"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar setor"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar setor");
            });
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
            
            fetch("' . \App\Helpers\Url::to('/departments') . '/" + departmentId, {
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
    
    // Tree toggle com animação melhorada
    document.querySelectorAll(".tree-toggle").forEach(btn => {
        btn.addEventListener("click", function() {
            const target = document.querySelector(this.getAttribute("data-bs-target"));
            if (target) {
                const isExpanded = this.getAttribute("aria-expanded") === "true";
                // A animação é feita via CSS
            }
        });
    });
    
    // Inicializar tooltips
    setTimeout(function() {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=\\"tooltip\\"]"));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new window.bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }, 100);
});

function editDepartment(id) {
    fetch("' . \App\Helpers\Url::to('/departments') . '/" + id + "/json")
        .then(response => response.json())
        .then(data => {
            if (data.success && data.department) {
                document.getElementById("edit_department_id").value = data.department.id;
                document.getElementById("edit_department_name").value = data.department.name || "";
                document.getElementById("edit_department_description").value = data.department.description || "";
                document.getElementById("edit_department_parent").value = data.department.parent_id || "";
                
                const modal = new bootstrap.Modal(document.getElementById("kt_modal_edit_department"));
                modal.show();
            } else {
                alert("Erro: " + (data.message || "Erro ao carregar dados do setor"));
            }
        })
        .catch(error => {
            console.error("Erro:", error);
            alert("Erro ao carregar dados do setor");
        });
}

function deleteDepartment(id, name) {
    if (!confirm("Tem certeza que deseja deletar o setor \\"" + name + "\\"?\\n\\nEsta ação não pode ser desfeita.\\n\\nO setor não pode ter setores filhos ou agentes atribuídos.")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/departments') . '/" + id, {
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
            alert("Erro: " + (data.message || "Erro ao deletar setor"));
        }
    })
    .catch(error => {
        console.error("Erro:", error);
        alert("Erro ao deletar setor");
    });
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

