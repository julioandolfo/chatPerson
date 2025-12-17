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
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum estÃ¡gio encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Crie estÃ¡gios para este funil.</div>
                <?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_stage">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Criar Primeiro EstÃ¡gio
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
            <div class="d-flex justify-content-end mb-5">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_stage">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Novo EstÃ¡gio
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
                                                onclick="showStageMetrics(<?= $stage['id'] ?>, <?= htmlspecialchars(json_encode($stage['name']), ENT_QUOTES, 'UTF-8') ?>)" 
                                                title="Ver mÃ©tricas">
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
                                                    <a class="dropdown-item" href="#" onclick="editStage(<?= $stage['id'] ?>, <?= htmlspecialchars(json_encode($stage['name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($stage['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($stage['color']), ENT_QUOTES, 'UTF-8') ?>); return false;">
                                                        <i class="ki-duotone ki-pencil fs-2 me-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Editar
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteStage(<?= $stage['id'] ?>, <?= htmlspecialchars(json_encode($stage['name']), ENT_QUOTES, 'UTF-8') ?>); return false;">
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
                                                    <span class="badge badge-light-danger"><?= $conv['unread_count'] ?> nÃ£o lidas</span>
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

<!--begin::Modal - Novo/Editar EstÃ¡gio-->
<?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
<div class="modal fade" id="kt_modal_stage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="kt_modal_stage_title">Novo EstÃ¡gio</h2>
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
                                BÃ¡sico
                            </a>
                        </li>
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary ms-0 me-10" data-bs-toggle="tab" href="#kt_tab_stage_validation">
                                ValidaÃ§Ãµes
                            </a>
                        </li>
                        <li class="nav-item mt-2">
                            <a class="nav-link text-active-primary ms-0 me-10" data-bs-toggle="tab" href="#kt_tab_stage_auto">
                                Auto-atribuiÃ§Ã£o
                            </a>
                        </li>
                    </ul>
                    <!--end::Tabs-->
                    
                    <!--begin::Tab Content-->
                    <div class="tab-content">
                        <!--begin::Tab Pane - BÃ¡sico-->
                        <div class="tab-pane fade show active" id="kt_tab_stage_basic" role="tabpanel">
                            <div class="fv-row mb-7">
                                <label class="required fw-semibold fs-6 mb-2">Nome</label>
                                <input type="text" name="name" id="kt_stage_name" class="form-control form-control-solid" placeholder="Nome do estÃ¡gio" required />
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">DescriÃ§Ã£o</label>
                                <textarea name="description" id="kt_stage_description" class="form-control form-control-solid" rows="3" placeholder="DescriÃ§Ã£o do estÃ¡gio"></textarea>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Cor</label>
                                <input type="color" name="color" id="kt_stage_color" class="form-control form-control-solid form-control-color" value="#009ef7" />
                            </div>
                            <div class="fv-row mb-7">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="kt_stage_default" />
                                    <label class="form-check-label" for="kt_stage_default">
                                        EstÃ¡gio padrÃ£o (para novas conversas)
                                    </label>
                                </div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Limite de Conversas</label>
                                <input type="number" name="max_conversations" id="kt_stage_max_conversations" class="form-control form-control-solid" min="1" placeholder="Deixe vazio para ilimitado" />
                                <div class="form-text">NÃºmero mÃ¡ximo de conversas simultÃ¢neas neste estÃ¡gio</div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">SLA (horas)</label>
                                <input type="number" name="sla_hours" id="kt_stage_sla_hours" class="form-control form-control-solid" min="1" placeholder="Deixe vazio para sem SLA" />
                                <div class="form-text">Tempo mÃ¡ximo em horas que uma conversa pode ficar neste estÃ¡gio</div>
                            </div>
                        </div>
                        <!--end::Tab Pane - BÃ¡sico-->
                        
                        <!--begin::Tab Pane - ValidaÃ§Ãµes-->
                        <div class="tab-pane fade" id="kt_tab_stage_validation" role="tabpanel">
                            <div class="fv-row mb-7">
                                <div class="form-check form-check-custom form-check-solid mb-5">
                                    <input class="form-check-input" type="checkbox" name="allow_move_back" value="1" id="kt_stage_allow_move_back" checked />
                                    <label class="form-check-label" for="kt_stage_allow_move_back">
                                        Permitir mover conversas para estÃ¡gios anteriores
                                    </label>
                                </div>
                            </div>
                            <div class="fv-row mb-7">
                                <div class="form-check form-check-custom form-check-solid mb-5">
                                    <input class="form-check-input" type="checkbox" name="allow_skip_stages" value="1" id="kt_stage_allow_skip_stages" />
                                    <label class="form-check-label" for="kt_stage_allow_skip_stages">
                                        Permitir pular estÃ¡gios intermediÃ¡rios
                                    </label>
                                    <div class="form-text">Se desmarcado, conversas sÃ³ podem avanÃ§ar um estÃ¡gio por vez</div>
                                </div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">EstÃ¡gios Bloqueados</label>
                                <select name="blocked_stages[]" id="kt_stage_blocked_stages" class="form-select form-select-solid" multiple data-kt-select2="true" data-placeholder="Selecione os estÃ¡gios bloqueados">
                                    <?php
                                    // Carregar todos os estÃ¡gios do funil atual
                                    if (!empty($funnel['stages'])):
                                        foreach ($funnel['stages'] as $stage):
                                    ?>
                                        <option value="<?= $stage['id'] ?>"><?= htmlspecialchars($stage['name']) ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                                <div class="form-text">Conversas destes estÃ¡gios nÃ£o podem ser movidas para este estÃ¡gio</div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">EstÃ¡gios ObrigatÃ³rios</label>
                                <select name="required_stages[]" id="kt_stage_required_stages" class="form-select form-select-solid" multiple data-kt-select2="true" data-placeholder="Selecione os estÃ¡gios obrigatÃ³rios">
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
                                <div class="form-text">Conversas devem passar por estes estÃ¡gios antes de entrar neste estÃ¡gio</div>
                            </div>
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Tags ObrigatÃ³rias</label>
                                <select name="required_tags[]" id="kt_stage_required_tags" class="form-select form-select-solid" multiple data-kt-select2="true" data-placeholder="Selecione as tags obrigatÃ³rias">
                                    <?php
                                    $allTags = \App\Models\Tag::all();
                                    foreach ($allTags as $tag):
                                    ?>
                                        <option value="<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Conversas devem ter estas tags para entrar neste estÃ¡gio</div>
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
                                <div class="form-text">Conversas com estas tags nÃ£o podem entrar neste estÃ¡gio</div>
                            </div>
                        </div>
                        <!--end::Tab Pane - ValidaÃ§Ãµes-->
                        
                        <!--begin::Tab Pane - Auto-atribuiÃ§Ã£o-->
                        <div class="tab-pane fade" id="kt_tab_stage_auto" role="tabpanel">
                            <div class="fv-row mb-7">
                                <div class="form-check form-check-custom form-check-solid mb-5">
                                    <input class="form-check-input" type="checkbox" name="auto_assign" value="1" id="kt_stage_auto_assign" />
                                    <label class="form-check-label" for="kt_stage_auto_assign">
                                        Auto-atribuir conversas ao entrar no estÃ¡gio
                                    </label>
                                </div>
                            </div>
                            <div class="fv-row mb-7" id="kt_auto_assign_fields" style="display: none;">
                                <label class="fw-semibold fs-6 mb-2">Departamento para Auto-atribuiÃ§Ã£o</label>
                                <select name="auto_assign_department_id" id="kt_stage_auto_assign_department" class="form-select form-select-solid">
                                    <option value="">Selecione um departamento</option>
                                    <?php
                                    $allDepartments = \App\Models\Department::all();
                                    foreach ($allDepartments as $dept):
                                    ?>
                                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Deixe vazio para atribuir a qualquer agente disponÃ­vel</div>
                            </div>
                            <div class="fv-row mb-7" id="kt_auto_assign_method_field" style="display: none;">
                                <label class="fw-semibold fs-6 mb-2">MÃ©todo de DistribuiÃ§Ã£o</label>
                                <select name="auto_assign_method" id="kt_stage_auto_assign_method" class="form-select form-select-solid">
                                    <option value="round-robin">Round-Robin (DistribuiÃ§Ã£o igual)</option>
                                    <option value="by-load">Por Carga (Menos conversas)</option>
                                    <option value="by-specialty">Por Especialidade</option>
                                </select>
                                <div class="form-text">Como as conversas serÃ£o distribuÃ­das entre os agentes</div>
                            </div>
                        </div>
                        <!--end::Tab Pane - Auto-atribuiÃ§Ã£o-->
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
<!--end::Modal - Novo/Editar EstÃ¡gio-->

<?php 
$content = ob_get_clean(); 
$styles = '
<style>
.kanban-drop-zone {
    background-color: #f1f1f2 !important;
    border: 2px dashed #009ef7 !important;
}
.kanban-item {
    transition: opacity 0.2s ease, box-shadow 0.2s ease, transform 0.3s ease;
    cursor: move;
}
.kanban-item:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
.kanban-item.dragging {
    opacity: 0.5;
}
.kanban-item.moving {
    opacity: 0.5;
    cursor: wait;
    transform: scale(0.98);
}
.kanban-item.just-moved {
    animation: highlightMoved 2s ease;
    box-shadow: 0 0.5rem 1.5rem rgba(0, 158, 247, 0.3) !important;
}
@keyframes highlightMoved {
    0%, 100% { background-color: inherit; }
    50% { background-color: rgba(0, 158, 247, 0.1); }
}
.kanban-column {
    transition: all 0.2s ease;
}
.conversation-item {
    transition: all 0.3s ease;
}
</style>
';
$funnelIdForJs = isset($currentFunnelId) ? intval($currentFunnelId) : 0;
$scripts = '
<!-- ConfiguraÃ§Ãµes do Kanban -->
<script>
// ConfiguraÃ§Ãµes globais para o Kanban.js
window.KANBAN_CONFIG = {
    funnelId: ' . $funnelIdForJs . ',
    moveConversationUrl: "' . \App\Helpers\Url::to('/funnels/' . $funnelIdForJs . '/conversations/move') . '",
    funnelBaseUrl: "' . \App\Helpers\Url::to('/funnels/' . $funnelIdForJs) . '",
    funnelsUrl: "' . \App\Helpers\Url::to('/funnels') . '"
};
</script>
<!-- Kanban JavaScript -->
<script src="' . \App\Helpers\Url::asset('js/kanban.js') . '"></script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
