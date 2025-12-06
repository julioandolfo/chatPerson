<?php
$layout = 'layouts.metronic.app';
$title = 'Templates de Mensagens';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Templates de Mensagens</h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <input type="text" data-kt-filter="search" class="form-control form-control-solid w-250px" placeholder="Buscar templates..." />
                <?php if (\App\Helpers\Permission::can('message_templates.create')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_template">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Novo Template
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($templates)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-document fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum template encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo template de mensagem.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_templates_table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-150px">Categoria</th>
                            <th class="min-w-200px">Conteúdo</th>
                            <th class="min-w-100px">Setor</th>
                            <th class="min-w-100px">Canal</th>
                            <th class="min-w-80px">Uso</th>
                            <th class="min-w-80px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($template['name']) ?></span>
                                        <?php if (!empty($template['description'])): ?>
                                            <span class="text-muted fs-7"><?= htmlspecialchars(mb_substr($template['description'], 0, 50)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($template['category'])): ?>
                                        <span class="badge badge-light-info"><?= htmlspecialchars($template['category']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-gray-800" title="<?= htmlspecialchars($template['content']) ?>">
                                        <?= htmlspecialchars(mb_substr($template['content'], 0, 60)) ?>
                                        <?= mb_strlen($template['content']) > 60 ? '...' : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($template['department_name'])): ?>
                                        <span class="badge badge-light-success"><?= htmlspecialchars($template['department_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Global</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($template['channel'])): ?>
                                        <span class="badge badge-light-primary"><?= htmlspecialchars($template['channel']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Todos</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-gray-600"><?= (int)($template['usage_count'] ?? 0) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = $template['is_active'] ? 'success' : 'danger';
                                    $statusText = $template['is_active'] ? 'Ativo' : 'Inativo';
                                    ?>
                                    <span class="badge badge-light-<?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if (\App\Helpers\Permission::can('message_templates.edit')): ?>
                                    <button type="button" class="btn btn-sm btn-light btn-active-light-primary me-2" 
                                            onclick="editTemplate(<?= $template['id'] ?>)">
                                        <i class="ki-duotone ki-pencil fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('message_templates.delete')): ?>
                                    <button type="button" class="btn btn-sm btn-light-danger" 
                                            onclick="deleteTemplate(<?= $template['id'] ?>, '<?= htmlspecialchars($template['name'], ENT_QUOTES) ?>')">
                                        <i class="ki-duotone ki-trash fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
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

<!--begin::Modal - Novo Template-->
<?php if (\App\Helpers\Permission::can('message_templates.create')): ?>
<div class="modal fade" id="kt_modal_new_template" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Template</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_template_form" class="form" action="<?= \App\Helpers\Url::to('/message-templates') ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Nome do template" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Categoria</label>
                        <input type="text" name="category" class="form-control form-control-solid" placeholder="Ex: welcome, followup, support" list="categories" />
                        <datalist id="categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Conteúdo</label>
                        <textarea name="content" class="form-control form-control-solid" rows="5" placeholder="Conteúdo do template. Use {{variavel}} para variáveis." required></textarea>
                        <div class="form-text">
                            Variáveis disponíveis: {{contact.name}}, {{contact.phone}}, {{agent.name}}, {{date}}, {{time}}
                        </div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="2" placeholder="Descrição opcional"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Setor</label>
                                <select name="department_id" class="form-select form-select-solid">
                                    <option value="">Global (todos os setores)</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Canal</label>
                                <select name="channel" class="form-select form-select-solid">
                                    <option value="">Todos os canais</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">Email</option>
                                    <option value="chat">Chat</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_template_submit" class="btn btn-primary">
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
<!--end::Modal - Novo Template-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const table = document.querySelector("#kt_templates_table");
    const searchInput = document.querySelector("[data-kt-filter=\"search\"]");
    
    if (table && searchInput) {
        const datatable = $(table).DataTable({
            "info": false,
            "order": [],
            "pageLength": 10,
            "lengthChange": false,
            "columnDefs": [
                { "orderable": false, "targets": 7 } // Disable ordering on actions column
            ]
        });
        
        searchInput.addEventListener("keyup", function(e) {
            datatable.search(e.target.value).draw();
        });
    }
    
    const form = document.getElementById("kt_modal_new_template_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_template_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            fetch(form.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(new FormData(form))
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_template"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar template"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar template");
            });
        });
    }
    
    window.editTemplate = function(templateId) {
        // TODO: Implementar edição
        alert("Edição de template será implementada em breve");
    };
    
    window.deleteTemplate = function(templateId, templateName) {
        if (!confirm("Tem certeza que deseja deletar o template \"" + templateName + "\"?\\n\\nEsta ação não pode ser desfeita.")) {
            return;
        }
        
        fetch("' . \App\Helpers\Url::to('/message-templates') . '/" + templateId, {
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
                alert("Erro: " + (data.message || "Erro ao deletar template"));
            }
        })
        .catch(error => {
            alert("Erro ao deletar template");
        });
    };
});
</script>';

include __DIR__ . '/../layouts/metronic/app.php';
?>

