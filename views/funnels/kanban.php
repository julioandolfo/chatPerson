<?php
$layout = 'layouts.metronic.app';
$title = 'Kanban - Funis';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Kanban</h3>
        </div>
        <div class="card-toolbar d-flex align-items-center gap-3">
            <?php if (!empty($allFunnels)): ?>
            <select class="form-select form-select-solid w-200px" id="kt_funnel_selector" onchange="changeFunnel(this.value)">
                <?php foreach ($allFunnels as $funnel): ?>
                    <option value="<?= $funnel['id'] ?>" <?= ($currentFunnelId ?? null) == $funnel['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($funnel['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if (!empty($currentFunnelId)): ?>
            <button type="button" class="btn btn-sm btn-light-info" onclick="showFunnelMetrics(<?= $currentFunnelId ?>)">
                <i class="ki-duotone ki-chart-simple fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Métricas do Funil
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($kanbanData) || empty($kanbanData['stages'])): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-grid fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum estágio encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Crie estágios para este funil.</div>
                <?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_stage">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Criar Primeiro Estágio
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
            <div class="d-flex justify-content-end mb-5">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_stage">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Novo Estágio
                </button>
            </div>
            <?php endif; ?>
            <div class="kanban-board d-flex gap-5 overflow-auto pb-5" id="kt_kanban_board">
                <?php foreach ($kanbanData['stages'] as $stageData): 
                    $stage = $stageData['stage'];
                    $conversations = $stageData['conversations'];
                ?>
                    <div class="kanban-column flex-shrink-0" data-stage-id="<?= $stage['id'] ?>" style="min-width: 320px; max-width: 320px;">
                        <div class="card h-100">
                            <div class="card-header border-0" style="background-color: <?= htmlspecialchars($stage['color']) ?>20;">
                                <div class="card-title d-flex align-items-center justify-content-between w-100">
                                    <div class="flex-grow-1">
                                        <h3 class="text-gray-800 fw-bold mb-0"><?= htmlspecialchars($stage['name']) ?></h3>
                                        <?php if (!empty($stage['description'])): ?>
                                            <span class="text-muted fs-7"><?= htmlspecialchars($stage['description']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge badge-light-primary" id="stage_count_<?= $stage['id'] ?>"><?= count($conversations) ?></span>
                                            <?php if (!empty($stage['max_conversations'])): ?>
                                                <span class="badge badge-light-warning">/ <?= $stage['max_conversations'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-icon btn-light-info" 
                                                onclick="showStageMetrics(<?= $stage['id'] ?>, <?= json_encode($stage['name']) ?>)" 
                                                title="Ver métricas">
                                            <i class="ki-duotone ki-chart-simple fs-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </button>
                                        <?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon btn-active-color-primary" type="button" data-bs-toggle="dropdown">
                                                <i class="ki-duotone ki-dots-vertical fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="editStage(<?= $stage['id'] ?>, <?= json_encode($stage['name']) ?>, <?= json_encode($stage['description'] ?? '') ?>, <?= json_encode($stage['color']) ?>); return false;">
                                                        <i class="ki-duotone ki-pencil fs-2 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Editar
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteStage(<?= $stage['id'] ?>, <?= json_encode($stage['name']) ?>); return false;">
                                                        <i class="ki-duotone ki-trash fs-2 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                            <span class="path3"></span>
                                                        </i>
                                                        Deletar
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-5 kanban-column-body" style="min-height: 400px; max-height: calc(100vh - 300px); overflow-y: auto;">
                                <div class="kanban-items d-flex flex-column gap-3">
                                    <?php foreach ($conversations as $conv): ?>
                                        <div class="kanban-item card p-5 cursor-move" 
                                             data-conversation-id="<?= $conv['id'] ?>"
                                             draggable="true"
                                             onclick="window.location.href='<?= \App\Helpers\Url::to('/conversations/' . $conv['id']) ?>'">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="symbol symbol-35px me-3">
                                                    <?php if (!empty($conv['contact_avatar'])): ?>
                                                        <img src="<?= htmlspecialchars($conv['contact_avatar']) ?>" alt="<?= htmlspecialchars($conv['contact_name'] ?? '') ?>" />
                                                    <?php else: ?>
                                                        <div class="symbol-label fs-6 fw-semibold text-primary bg-light-primary">
                                                            <?= mb_substr(htmlspecialchars($conv['contact_name'] ?? 'C'), 0, 1) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-gray-800"><?= htmlspecialchars($conv['contact_name'] ?? 'Sem nome') ?></div>
                                                    <div class="text-muted fs-7"><?= htmlspecialchars($conv['contact_phone'] ?? '') ?></div>
                                                </div>
                                            </div>
                                            <?php if (!empty($conv['last_message'])): ?>
                                                <p class="text-gray-600 fs-7 mb-2">
                                                    <?= htmlspecialchars(mb_substr($conv['last_message'], 0, 80)) ?>
                                                    <?= mb_strlen($conv['last_message']) > 80 ? '...' : '' ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="d-flex align-items-center justify-content-between">
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="badge badge-light-danger"><?= $conv['unread_count'] ?> não lidas</span>
                                                <?php endif; ?>
                                                <span class="text-muted fs-7">
                                                    <?= $conv['last_message_at'] ? date('d/m H:i', strtotime($conv['last_message_at'])) : '' ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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

<!--begin::Modal - Novo/Editar Estágio-->
<?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
<div class="modal fade" id="kt_modal_stage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="kt_modal_stage_title">Novo Estágio</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_stage_form" class="form">
                <input type="hidden" name="stage_id" id="kt_stage_id" value="" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <!--begin::Tabs-->
                    <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold mb-5">
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary ms-0 me-10 active" data-bs-toggle="tab" href="#kt_tab_stage_basic">
                                Básico
                            </a>
                        </li>
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary ms-0 me-10" data-bs-toggle="tab" href="#kt_tab_stage_validation">
                                Validações
                            </a>
                        </li>
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary ms-0 me-10" data-bs-toggle="tab" href="#kt_tab_stage_auto">
                                Auto-atribuição
                            </a>
                        </li>
                    </ul>
                    <!--end::Tabs-->
                    
                    <!--begin::Tab Content-->
                    <div class="tab-content">
                        <!--begin::Tab Pane - Básico-->
                        <div class="tab-pane fade show active" id="kt_tab_stage_basic" role="tabpanel">
                            <div class="fv-row mb-7">
                                <label class="required fw-semibold fs-6 mb-2">Nome</label>
                                <input type="text" name="name" id="kt_stage_name" class="form-control form-control-solid" placeholder="Nome do estágio" required />
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Descrição</label>
                                <textarea name="description" id="kt_stage_description" class="form-control form-control-solid" rows="3" placeholder="Descrição do estágio"></textarea>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Cor</label>
                                <input type="color" name="color" id="kt_stage_color" class="form-control form-control-solid form-control-color" value="#009ef7" />
                            </div>
                            <div class="fv-row mb-7">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="kt_stage_default" />
                                    <label class="form-check-label" for="kt_stage_default">
                                        Estágio padrão (para novas conversas)
                                    </label>
                                </div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Limite de Conversas</label>
                                <input type="number" name="max_conversations" id="kt_stage_max_conversations" class="form-control form-control-solid" min="1" placeholder="Deixe vazio para ilimitado" />
                                <div class="form-text">Número máximo de conversas simultâneas neste estágio</div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">SLA (horas)</label>
                                <input type="number" name="sla_hours" id="kt_stage_sla_hours" class="form-control form-control-solid" min="1" placeholder="Deixe vazio para sem SLA" />
                                <div class="form-text">Tempo máximo em horas que uma conversa pode ficar neste estágio</div>
                            </div>
                        </div>
                        <!--end::Tab Pane - Básico-->
                        
                        <!--begin::Tab Pane - Validações-->
                        <div class="tab-pane fade" id="kt_tab_stage_validation" role="tabpanel">
                            <div class="fv-row mb-7">
                                <div class="form-check form-check-custom form-check-solid mb-5">
                                    <input class="form-check-input" type="checkbox" name="allow_move_back" value="1" id="kt_stage_allow_move_back" checked />
                                    <label class="form-check-label" for="kt_stage_allow_move_back">
                                        Permitir mover conversas para estágios anteriores
                                    </label>
                                </div>
                            </div>
                            <div class="fv-row mb-7">
                                <div class="form-check form-check-custom form-check-solid mb-5">
                                    <input class="form-check-input" type="checkbox" name="allow_skip_stages" value="1" id="kt_stage_allow_skip_stages" />
                                    <label class="form-check-label" for="kt_stage_allow_skip_stages">
                                        Permitir pular estágios intermediários
                                    </label>
                                    <div class="form-text">Se desmarcado, conversas só podem avançar um estágio por vez</div>
                                </div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Estágios Bloqueados</label>
                                <select name="blocked_stages[]" id="kt_stage_blocked_stages" class="form-select form-select-solid" multiple data-kt-select2="true" data-placeholder="Selecione os estágios bloqueados">
                                    <?php
                                    // Carregar todos os estágios do funil atual
                                    if (!empty($funnel['stages'])):
                                        foreach ($funnel['stages'] as $stage):
                                    ?>
                                        <option value="<?= $stage['id'] ?>"><?= htmlspecialchars($stage['name']) ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                                <div class="form-text">Conversas destes estágios não podem ser movidas para este estágio</div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Estágios Obrigatórios</label>
                                <select name="required_stages[]" id="kt_stage_required_stages" class="form-select form-select-solid" multiple data-kt-select2="true" data-placeholder="Selecione os estágios obrigatórios">
                                    <?php
                                    if (!empty($funnel['stages'])):
                                        foreach ($funnel['stages'] as $stage):
                                    ?>
                                        <option value="<?= $stage['id'] ?>"><?= htmlspecialchars($stage['name']) ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                                <div class="form-text">Conversas devem passar por estes estágios antes de entrar neste estágio</div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Tags Obrigatórias</label>
                                <select name="required_tags[]" id="kt_stage_required_tags" class="form-select form-select-solid" multiple data-kt-select2="true" data-placeholder="Selecione as tags obrigatórias">
                                    <?php
                                    $allTags = \App\Models\Tag::all();
                                    foreach ($allTags as $tag):
                                    ?>
                                        <option value="<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Conversas devem ter estas tags para entrar neste estágio</div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Tags Bloqueadas</label>
                                <select name="blocked_tags[]" id="kt_stage_blocked_tags" class="form-select form-select-solid" multiple data-kt-select2="true" data-placeholder="Selecione as tags bloqueadas">
                                    <?php
                                    foreach ($allTags as $tag):
                                    ?>
                                        <option value="<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Conversas com estas tags não podem entrar neste estágio</div>
                            </div>
                        </div>
                        <!--end::Tab Pane - Validações-->
                        
                        <!--begin::Tab Pane - Auto-atribuição-->
                        <div class="tab-pane fade" id="kt_tab_stage_auto" role="tabpanel">
                            <div class="fv-row mb-7">
                                <div class="form-check form-check-custom form-check-solid mb-5">
                                    <input class="form-check-input" type="checkbox" name="auto_assign" value="1" id="kt_stage_auto_assign" />
                                    <label class="form-check-label" for="kt_stage_auto_assign">
                                        Auto-atribuir conversas ao entrar no estágio
                                    </label>
                                </div>
                            </div>
                            <div class="fv-row mb-7" id="kt_auto_assign_fields" style="display: none;">
                                <label class="fw-semibold fs-6 mb-2">Departamento para Auto-atribuição</label>
                                <select name="auto_assign_department_id" id="kt_stage_auto_assign_department" class="form-select form-select-solid">
                                    <option value="">Selecione um departamento</option>
                                    <?php
                                    $allDepartments = \App\Models\Department::all();
                                    foreach ($allDepartments as $dept):
                                    ?>
                                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Deixe vazio para atribuir a qualquer agente disponível</div>
                            </div>
                            <div class="fv-row mb-7" id="kt_auto_assign_method_field" style="display: none;">
                                <label class="fw-semibold fs-6 mb-2">Método de Distribuição</label>
                                <select name="auto_assign_method" id="kt_stage_auto_assign_method" class="form-select form-select-solid">
                                    <option value="round-robin">Round-Robin (Distribuição igual)</option>
                                    <option value="by-load">Por Carga (Menos conversas)</option>
                                    <option value="by-specialty">Por Especialidade</option>
                                </select>
                                <div class="form-text">Como as conversas serão distribuídas entre os agentes</div>
                            </div>
                        </div>
                        <!--end::Tab Pane - Auto-atribuição-->
                    </div>
                    <!--end::Tab Content-->
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_stage_submit" class="btn btn-primary">
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
<!--end::Modal - Novo/Editar Estágio-->

<?php 
$content = ob_get_clean(); 
$styles = '
<style>
.kanban-drop-zone {
    background-color: #f1f1f2 !important;
    border: 2px dashed #009ef7 !important;
}
.kanban-item {
    transition: opacity 0.2s ease, box-shadow 0.2s ease;
    cursor: move;
}
.kanban-item:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
.kanban-item.dragging {
    opacity: 0.5;
}
.kanban-column {
    transition: all 0.2s ease;
}
</style>
';
$scripts = '
<script>
let draggedElement = null;
let currentFunnelId = ' . ($currentFunnelId ?? 0) . ';

document.addEventListener("DOMContentLoaded", function() {
    // Drag and Drop
    const kanbanItems = document.querySelectorAll(".kanban-item");
    const kanbanColumns = document.querySelectorAll(".kanban-column-body");
    
    kanbanItems.forEach(item => {
        item.addEventListener("dragstart", function(e) {
            draggedElement = this;
            this.style.opacity = "0.5";
            e.dataTransfer.effectAllowed = "move";
        });
        
        item.addEventListener("dragend", function() {
            this.style.opacity = "1";
            draggedElement = null;
        });
    });
    
    kanbanColumns.forEach(column => {
        column.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            this.classList.add("kanban-drop-zone");
        });
        
        column.addEventListener("dragleave", function() {
            this.classList.remove("kanban-drop-zone");
        });
        
        column.addEventListener("drop", function(e) {
            e.preventDefault();
            this.classList.remove("kanban-drop-zone");
            
            if (draggedElement) {
                const columnElement = this.closest(".kanban-column");
                const newStageId = columnElement ? columnElement.dataset.stageId : null;
                const conversationId = draggedElement.dataset.conversationId;
                
                if (newStageId && conversationId) {
                    // Verificar se não está movendo para o mesmo estágio
                    const currentColumn = draggedElement.closest(".kanban-column");
                    const currentStageId = currentColumn ? currentColumn.dataset.stageId : null;
                    
                    if (currentStageId !== newStageId) {
                        moveConversation(conversationId, newStageId);
                    } else {
                        // Restaurar elemento se for o mesmo estágio
                        draggedElement.style.opacity = "1";
                    }
                }
                
                draggedElement = null;
            }
        });
    });
});

// Validar e mover conversa
function validateAndMoveConversation(conversationId, stageId, draggedElement, currentColumn) {
    // Mostrar loading
    draggedElement.style.opacity = "0.5";
    draggedElement.style.cursor = "wait";
    
    // Validar antes de mover
    fetch("' . \App\Helpers\Url::to('/funnels/' . ($currentFunnelId ?? 0) . '/conversations/move') . '", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: new URLSearchParams({
            conversation_id: conversationId,
            stage_id: stageId,
            validate_only: "1"
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.allowed !== false) {
            // Se permitido, mover
            moveConversation(conversationId, stageId, draggedElement, currentColumn);
        } else {
            // Mostrar erro
            draggedElement.style.opacity = "1";
            draggedElement.style.cursor = "grab";
            
            // Reverter posição visual
            if (currentColumn) {
                currentColumn.querySelector(".kanban-items").appendChild(draggedElement);
            }
            
            // Mostrar mensagem de erro
            Swal.fire({
                icon: "error",
                title: "Movimentação não permitida",
                text: data.message || "Não é possível mover esta conversa para este estágio",
                confirmButtonText: "OK"
            });
        }
    })
    .catch(error => {
        draggedElement.style.opacity = "1";
        draggedElement.style.cursor = "grab";
        
        if (currentColumn) {
            currentColumn.querySelector(".kanban-items").appendChild(draggedElement);
        }
        
        Swal.fire({
            icon: "error",
            title: "Erro",
            text: "Erro ao validar movimentação",
            confirmButtonText: "OK"
        });
    });
}

function moveConversation(conversationId, stageId, draggedElement, currentColumn) {
    const formData = new FormData();
    formData.append("conversation_id", conversationId);
    formData.append("stage_id", stageId);
    
    // Mostrar loading no item
    const item = document.querySelector(`[data-conversation-id="${conversationId}"]`);
    const originalOpacity = item ? item.style.opacity : "1";
    if (item) {
        item.style.opacity = "0.5";
        item.style.pointerEvents = "none";
    }
    
    fetch("' . \App\Helpers\Url::to('/funnels/' . ($currentFunnelId ?? 0) . '/conversations/move') . '", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Sucesso - recarregar página
            location.reload();
        } else {
            // Erro - restaurar item e mostrar mensagem
            if (item) {
                item.style.opacity = originalOpacity;
                item.style.pointerEvents = "";
            }
            alert("Erro: " + (data.message || "Erro ao mover conversa"));
        }
    })
    .catch(error => {
        // Erro de rede - restaurar item
        if (item) {
            item.style.opacity = originalOpacity;
            item.style.pointerEvents = "";
        }
        alert("Erro ao mover conversa. Tente novamente.");
    });
}

function changeFunnel(funnelId) {
    window.location.href = "' . \App\Helpers\Url::to('/funnels') . '/" + funnelId + "/kanban";
}

function editStage(stageId, name, description, color) {
    // Carregar dados completos do estágio via AJAX
    fetch("' . \App\Helpers\Url::to('/funnels/' . ($currentFunnelId ?? 0)) . '/stages/" + stageId + "/json")
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stage) {
                const stage = data.stage;
                
                document.getElementById("kt_modal_stage_title").textContent = "Editar Estágio";
                document.getElementById("kt_stage_id").value = stageId;
                document.getElementById("kt_stage_name").value = stage.name || "";
                document.getElementById("kt_stage_description").value = stage.description || "";
                document.getElementById("kt_stage_color").value = stage.color || "#009ef7";
                document.getElementById("kt_stage_default").checked = stage.is_default == 1;
                document.getElementById("kt_stage_max_conversations").value = stage.max_conversations || "";
                document.getElementById("kt_stage_sla_hours").value = stage.sla_hours || "";
                document.getElementById("kt_stage_allow_move_back").checked = stage.allow_move_back !== false;
                document.getElementById("kt_stage_allow_skip_stages").checked = stage.allow_skip_stages == 1;
                document.getElementById("kt_stage_auto_assign").checked = stage.auto_assign == 1;
                document.getElementById("kt_stage_auto_assign_department").value = stage.auto_assign_department_id || "";
                document.getElementById("kt_stage_auto_assign_method").value = stage.auto_assign_method || "round-robin";
                
                // Preencher arrays (blocked_stages, required_stages, required_tags, blocked_tags)
                if (stage.blocked_stages) {
                    const blockedStages = typeof stage.blocked_stages === "string" ? JSON.parse(stage.blocked_stages) : stage.blocked_stages;
                    if (blockedStages && blockedStages.length > 0) {
                        $("#kt_stage_blocked_stages").val(blockedStages).trigger("change");
                    }
                }
                if (stage.required_stages) {
                    const requiredStages = typeof stage.required_stages === "string" ? JSON.parse(stage.required_stages) : stage.required_stages;
                    if (requiredStages && requiredStages.length > 0) {
                        $("#kt_stage_required_stages").val(requiredStages).trigger("change");
                    }
                }
                if (stage.required_tags) {
                    const requiredTags = typeof stage.required_tags === "string" ? JSON.parse(stage.required_tags) : stage.required_tags;
                    if (requiredTags && requiredTags.length > 0) {
                        $("#kt_stage_required_tags").val(requiredTags).trigger("change");
                    }
                }
                if (stage.blocked_tags) {
                    const blockedTags = typeof stage.blocked_tags === "string" ? JSON.parse(stage.blocked_tags) : stage.blocked_tags;
                    if (blockedTags && blockedTags.length > 0) {
                        $("#kt_stage_blocked_tags").val(blockedTags).trigger("change");
                    }
                }
                
                // Mostrar/ocultar campos de auto-atribuição
                toggleAutoAssignFields();
                
                const modal = new bootstrap.Modal(document.getElementById("kt_modal_stage"));
                modal.show();
            } else {
                // Fallback para dados básicos se não houver endpoint JSON
                document.getElementById("kt_modal_stage_title").textContent = "Editar Estágio";
                document.getElementById("kt_stage_id").value = stageId;
                document.getElementById("kt_stage_name").value = name;
                document.getElementById("kt_stage_description").value = description || "";
                document.getElementById("kt_stage_color").value = color || "#009ef7";
                document.getElementById("kt_stage_default").checked = false;
                
                const modal = new bootstrap.Modal(document.getElementById("kt_modal_stage"));
                modal.show();
            }
        })
        .catch(error => {
            console.error("Erro ao carregar dados do estágio:", error);
            // Fallback
            document.getElementById("kt_modal_stage_title").textContent = "Editar Estágio";
            document.getElementById("kt_stage_id").value = stageId;
            document.getElementById("kt_stage_name").value = name;
            document.getElementById("kt_stage_description").value = description || "";
            document.getElementById("kt_stage_color").value = color || "#009ef7";
            document.getElementById("kt_stage_default").checked = false;
            
            const modal = new bootstrap.Modal(document.getElementById("kt_modal_stage"));
            modal.show();
        });
}

function toggleAutoAssignFields() {
    const autoAssign = document.getElementById("kt_stage_auto_assign");
    const fields = document.getElementById("kt_auto_assign_fields");
    const methodField = document.getElementById("kt_auto_assign_method_field");
    
    if (autoAssign && fields && methodField) {
        if (autoAssign.checked) {
            fields.style.display = "block";
            methodField.style.display = "block";
        } else {
            fields.style.display = "none";
            methodField.style.display = "none";
        }
    }
}

function deleteStage(stageId, stageName) {
    if (!confirm("Tem certeza que deseja deletar o estágio \"" + stageName + "\"?\\n\\nEsta ação não pode ser desfeita.")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/funnels/' . ($currentFunnelId ?? 0)) . '/stages/" + stageId, {
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
            alert("Erro: " + (data.message || "Erro ao deletar estágio"));
        }
    })
    .catch(error => {
        alert("Erro ao deletar estágio");
    });
}

document.addEventListener("DOMContentLoaded", function() {
    const stageForm = document.getElementById("kt_modal_stage_form");
    if (stageForm) {
        stageForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const stageId = document.getElementById("kt_stage_id").value;
            const isEdit = stageId !== "";
            const url = isEdit 
                ? "' . \App\Helpers\Url::to('/funnels/' . ($currentFunnelId ?? 0)) . '/stages/" + stageId
                : "' . \App\Helpers\Url::to('/funnels/' . ($currentFunnelId ?? 0)) . '/stages";
            const method = isEdit ? "POST" : "POST";
            
            const submitBtn = document.getElementById("kt_modal_stage_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(stageForm);
            if (!document.getElementById("kt_stage_default").checked) {
                formData.delete("is_default");
            }
            
            // Processar arrays JSON
            const blockedStagesEl = document.getElementById("kt_stage_blocked_stages");
            const requiredStagesEl = document.getElementById("kt_stage_required_stages");
            const requiredTagsEl = document.getElementById("kt_stage_required_tags");
            const blockedTagsEl = document.getElementById("kt_stage_blocked_tags");
            
            if (blockedStagesEl) {
                const blockedStages = Array.from(blockedStagesEl.selectedOptions).map(opt => parseInt(opt.value));
                formData.set("blocked_stages", JSON.stringify(blockedStages));
            }
            if (requiredStagesEl) {
                const requiredStages = Array.from(requiredStagesEl.selectedOptions).map(opt => parseInt(opt.value));
                formData.set("required_stages", JSON.stringify(requiredStages));
            }
            if (requiredTagsEl) {
                const requiredTags = Array.from(requiredTagsEl.selectedOptions).map(opt => parseInt(opt.value));
                formData.set("required_tags", JSON.stringify(requiredTags));
            }
            if (blockedTagsEl) {
                const blockedTags = Array.from(blockedTagsEl.selectedOptions).map(opt => parseInt(opt.value));
                formData.set("blocked_tags", JSON.stringify(blockedTags));
            }
            
            // Processar checkboxes
            const allowMoveBack = document.getElementById("kt_stage_allow_move_back");
            if (allowMoveBack && !allowMoveBack.checked) {
                formData.set("allow_move_back", "0");
            }
            const allowSkipStages = document.getElementById("kt_stage_allow_skip_stages");
            if (allowSkipStages && !allowSkipStages.checked) {
                formData.delete("allow_skip_stages");
            }
            const autoAssign = document.getElementById("kt_stage_auto_assign");
            if (autoAssign && !autoAssign.checked) {
                formData.delete("auto_assign");
            }
            
            fetch(url, {
                method: method,
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_stage"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao salvar estágio"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao salvar estágio");
            });
        });
        
        // Listener para checkbox de auto-atribuição
        const autoAssignCheckbox = document.getElementById("kt_stage_auto_assign");
        if (autoAssignCheckbox) {
            autoAssignCheckbox.addEventListener("change", toggleAutoAssignFields);
        }
        
        // Resetar formulário ao fechar modal
        document.getElementById("kt_modal_stage").addEventListener("hidden.bs.modal", function() {
            stageForm.reset();
            document.getElementById("kt_modal_stage_title").textContent = "Novo Estágio";
            document.getElementById("kt_stage_id").value = "";
            document.getElementById("kt_stage_color").value = "#009ef7";
        });
    }
    
    // Abrir modal de novo estágio quando clicar no botão
    const newStageBtn = document.querySelector("[data-bs-target=\"#kt_modal_new_stage\"]");
    if (newStageBtn) {
        newStageBtn.addEventListener("click", function() {
            document.getElementById("kt_modal_stage_title").textContent = "Novo Estágio";
            document.getElementById("kt_stage_id").value = "";
            document.getElementById("kt_stage_name").value = "";
            document.getElementById("kt_stage_description").value = "";
            document.getElementById("kt_stage_color").value = "#009ef7";
            document.getElementById("kt_stage_default").checked = false;
        });
    }
});

// Métricas de estágio
function showStageMetrics(stageId, stageName) {
    const dateFrom = new Date();
    dateFrom.setDate(dateFrom.getDate() - 30);
    const dateTo = new Date();
    
    const baseUrl = \'' . \App\Helpers\Url::to('/funnels/' . ($currentFunnelId ?? 0)) . '\';
    fetch(baseUrl + \'/stages/metrics?stage_id=\' + stageId + \'&date_from=\' + dateFrom.toISOString().split(\'T\')[0] + \'&date_to=\' + dateTo.toISOString().split(\'T\')[0])
        .then(response => response.json())
        .then(data => {
            if (data.success && data.metrics) {
                const m = data.metrics;
                let html = `
                    <div class="mb-5">
                        <h3 class="fw-bold mb-3">${stageName}</h3>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card card-flush">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-chat-text fs-2x text-primary me-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                            </i>
                                            <div>
                                                <div class="text-gray-500 fs-7">Conversas Atuais</div>
                                                <div class="fw-bold fs-3">${m.current_count}</div>
                                                ${m.max_conversations ? `<div class="text-muted fs-8">de ${m.max_conversations} máximo</div>` : \'\'}
                                            </div>
                                        </div>
                                        ${m.utilization_rate !== null ? `
                                            <div class="mt-3">
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar ${m.utilization_rate > 90 ? \'bg-danger\' : m.utilization_rate > 70 ? \'bg-warning\' : \'bg-success\'}" 
                                                         style="width: ${m.utilization_rate}%"></div>
                                                </div>
                                                <div class="text-muted fs-8 mt-1">${m.utilization_rate.toFixed(1)}% de utilização</div>
                                            </div>
                                        ` : \'\'}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card card-flush">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-chart-simple fs-2x text-info me-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            <div>
                                                <div class="text-gray-500 fs-7">Taxa de Conversão</div>
                                                <div class="fw-bold fs-3">${m.conversion_rate}%</div>
                                                <div class="text-muted fs-8">Últimos 30 dias</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card card-flush">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-clock fs-2x text-warning me-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <div>
                                                <div class="text-gray-500 fs-7">Tempo Médio</div>
                                                <div class="fw-bold fs-3">${m.avg_time_hours}h</div>
                                                <div class="text-muted fs-8">${m.min_time_hours}h - ${m.max_time_hours}h</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card card-flush">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-check-circle fs-2x text-success me-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <div>
                                                <div class="text-gray-500 fs-7">Resolvidas</div>
                                                <div class="fw-bold fs-3">${m.resolved}</div>
                                                <div class="text-muted fs-8">de ${m.total_in_period} no período</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ${m.sla_compliance !== null ? `
                                <div class="col-md-6">
                                    <div class="card card-flush">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <i class="ki-duotone ki-shield-check fs-2x text-success me-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="text-gray-500 fs-7">Compliance SLA</div>
                                                    <div class="fw-bold fs-3 ${m.sla_compliance >= 90 ? \'text-success\' : m.sla_compliance >= 70 ? \'text-warning\' : \'text-danger\'}">${m.sla_compliance}%</div>
                                                    <div class="text-muted fs-8">SLA: ${m.sla_hours}h</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ` : \'\'}
                        </div>
                    </div>
                `;
                
                Swal.fire({
                    html: html,
                    width: \'800px\',
                    showConfirmButton: true,
                    confirmButtonText: \'Fechar\',
                    customClass: {
                        popup: \'text-start\'
                    }
                });
            } else {
                Swal.fire({
                    icon: \'error\',
                    title: \'Erro\',
                    text: \'Não foi possível carregar as métricas\'
                });
            }
        })
        .catch(error => {
            console.error(\'Erro:\', error);
            Swal.fire({
                icon: \'error\',
                title: \'Erro\',
                text: \'Erro ao carregar métricas\'
            });
        });
}

// Métricas do funil completo
function showFunnelMetrics(funnelId) {
    const dateFrom = new Date();
    dateFrom.setDate(dateFrom.getDate() - 30);
    const dateTo = new Date();
    
    const funnelBaseUrl = \'' . \App\Helpers\Url::to('/funnels') . '\';
    fetch(`${funnelBaseUrl}/${funnelId}/metrics?date_from=${dateFrom.toISOString().split(\'T\')[0]}&date_to=${dateTo.toISOString().split(\'T\')[0]}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.metrics) {
                const m = data.metrics;
                let html = `
                    <div class="mb-5">
                        <h3 class="fw-bold mb-3">${m.funnel_name}</h3>
                        <div class="row g-4 mb-5">
                            <div class="col-md-3">
                                <div class="card card-flush">
                                    <div class="card-body text-center">
                                        <div class="text-gray-500 fs-7 mb-2">Total de Conversas</div>
                                        <div class="fw-bold fs-2x">${m.totals.total_conversations}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-flush">
                                    <div class="card-body text-center">
                                        <div class="text-gray-500 fs-7 mb-2">Abertas</div>
                                        <div class="fw-bold fs-2x text-primary">${m.totals.open_conversations}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-flush">
                                    <div class="card-body text-center">
                                        <div class="text-gray-500 fs-7 mb-2">Resolvidas</div>
                                        <div class="fw-bold fs-2x text-success">${m.totals.resolved_conversations}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-flush">
                                    <div class="card-body text-center">
                                        <div class="text-gray-500 fs-7 mb-2">Taxa de Resolução</div>
                                        <div class="fw-bold fs-2x text-info">${m.totals.resolution_rate}%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h4 class="fw-bold mb-3">Métricas por Estágio</h4>
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Estágio</th>
                                        <th>Atual</th>
                                        <th>Total (30d)</th>
                                        <th>Tempo Médio</th>
                                        <th>Taxa Conversão</th>
                                        <th>Compliance SLA</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                m.stages.forEach(stage => {
                    html += `
                        <tr>
                            <td><span class="fw-bold">${stage.stage_name}</span></td>
                            <td><span class="badge badge-light-primary">${stage.current_count}</span></td>
                            <td>${stage.total_in_period}</td>
                            <td>${stage.avg_time_hours}h</td>
                            <td>${stage.conversion_rate}%</td>
                            <td>${stage.sla_compliance !== null ? `<span class="badge ${stage.sla_compliance >= 90 ? \'badge-light-success\' : stage.sla_compliance >= 70 ? \'badge-light-warning\' : \'badge-light-danger\'}">${stage.sla_compliance}%</span>` : \'-\'}</td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                Swal.fire({
                    html: html,
                    width: \'1000px\',
                    showConfirmButton: true,
                    confirmButtonText: \'Fechar\',
                    customClass: {
                        popup: \'text-start\'
                    }
                });
            } else {
                Swal.fire({
                    icon: \'error\',
                    title: \'Erro\',
                    text: \'Não foi possível carregar as métricas\'
                });
            }
        })
        .catch(error => {
            console.error(\'Erro:\', error);
            Swal.fire({
                icon: \'error\',
                title: \'Erro\',
                text: \'Erro ao carregar métricas\'
            });
        });
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
