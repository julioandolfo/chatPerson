<?php
$layout = 'layouts.metronic.app';
$title = 'Kanban - Funis';

ob_start();
?>

<!-- CSS Personalizado para Kanban Melhorado -->
<style>
/* ============================================================================
   ANIMAÇÕES E MELHORIAS VISUAIS DO KANBAN
   ============================================================================ */

/* Hover effect nos cards */
.kanban-item.conversation-item {
    transition: all 0.2s ease-in-out;
    border-radius: 8px;
}

.kanban-item.conversation-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
}

/* Efeito de drag */
.kanban-item.conversation-item.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
    cursor: grabbing !important;
}

.kanban-item.conversation-item:active {
    cursor: grabbing !important;
}

/* Animação ao mover */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.kanban-item.conversation-item.just-moved {
    animation: slideIn 0.3s ease-out;
    box-shadow: 0 0 0 3px rgba(0, 158, 247, 0.3);
}

/* Zona de drop ativa */
.kanban-column-body.drop-zone-active {
    background-color: rgba(0, 158, 247, 0.05);
    border: 2px dashed #009ef7;
    border-radius: 8px;
}

/* Badges e Tags */
.kanban-item .badge {
    font-weight: 500;
    padding: 4px 8px;
}

/* Cards vazios */
.kanban-items:empty::after {
    content: "Arraste conversas para cá";
    display: block;
    text-align: center;
    padding: 20px;
    color: #b5b5c3;
    font-size: 0.85rem;
    font-style: italic;
}

/* Avatar styles */
.symbol img,
.symbol .symbol-label {
    object-fit: cover;
}

/* Dropdown do card */
.kanban-item .dropdown-menu {
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    border: 1px solid #e4e6ef;
}

/* Loading state */
.kanban-item.loading {
    opacity: 0.6;
    pointer-events: none;
}

.kanban-item.loading::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #009ef7;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsividade */
@media (max-width: 768px) {
    .kanban-column {
        min-width: 280px !important;
        max-width: 280px !important;
    }
    
    .kanban-item.conversation-item {
        font-size: 0.9rem;
    }
}

/* Elevação suave */
.hover-elevate-up {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.hover-elevate-up:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
}

/* Estilo do grab cursor durante drag */
.kanban-board {
    user-select: none;
}

.kanban-item.conversation-item[draggable="true"] {
    cursor: grab;
}

.kanban-item.conversation-item[draggable="true"]:active {
    cursor: grabbing;
}

/* Truncate text elegante */
.text-truncate-2-lines {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
}

/* Badge de SLA pulsando (quando vencido) */
.badge.badge-light-danger.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

/* Smooth scroll no board */
.kanban-board {
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
}

.kanban-board::-webkit-scrollbar {
    height: 8px;
}

.kanban-board::-webkit-scrollbar-track {
    background: #f7fafc;
    border-radius: 4px;
}

.kanban-board::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

.kanban-board::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}
</style>

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
                <?php 
                $stagesData = $kanbanData['stages'];
                foreach ($stagesData as $stageIndex => $stageData): 
                    $stage = $stageData['stage'];
                    // Fallback de cor caso não esteja definida (dados antigos)
                    $stageColor = !empty($stage['color']) ? $stage['color'] : '#009ef7';
                    // Header com cor visível (alpha ~20%). Ex: #009ef7 + 33 => #009ef733
                    $stageColorLight = $stageColor . '33'; // 20% opacity
                    $stageColorLighter = $stageColor . '1a'; // 10% opacity
                    $conversations = $stageData['conversations'];
                ?>
                    <div class="kanban-column flex-shrink-0" data-stage-id="<?= $stage['id'] ?>" style="min-width: 320px; max-width: 320px;">
                        <div class="card h-100">
                            <div class="card-header border-0 py-4 px-5" style="
                                background: linear-gradient(135deg, <?= htmlspecialchars($stageColorLight) ?> 0%, <?= htmlspecialchars($stageColorLighter) ?> 100%);
                                border-left: 4px solid <?= htmlspecialchars($stageColor) ?>;
                                border-bottom: 1px solid <?= htmlspecialchars($stageColorLight) ?>;
                            ">
                                <!-- Linha 1: Nome + Badge Sistema + Contador -->
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1" 
                                         <?php if (!empty($stage['description'])): ?>
                                         title="<?= htmlspecialchars($stage['description']) ?>"
                                         <?php endif; ?>>
                                        <h3 class="text-gray-900 fw-bold mb-0 fs-5" style="line-height: 1.2;">
                                            <?= htmlspecialchars($stage['name']) ?>
                                        </h3>
                                        <?php if (!empty($stage['is_system_stage'])): ?>
                                            <span class="badge badge-success badge-sm fs-8 px-2 py-1" 
                                                  style="font-weight: 500;" 
                                                  title="Etapa obrigatória do sistema">
                                                <i class="ki-duotone ki-shield-tick fs-6 me-1">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Sistema
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="badge badge-primary fs-6 px-2" id="stage_count_<?= $stage['id'] ?>">
                                            <?= count($conversations) ?>
                                        </span>
                                        <?php if (!empty($stage['max_conversations'])): ?>
                                            <span class="text-muted fs-7 fw-semibold">/ <?= $stage['max_conversations'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Linha 2: Botões de Ação -->
                                <div class="d-flex align-items-center justify-content-between">
                                    <!-- Botões de Reordenação -->
                                    <?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($stageIndex > 0): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-icon btn-color-gray-500 btn-active-color-primary" 
                                                        onclick="reorderStage(<?= $stage['id'] ?>, 'up')"
                                                        title="Mover para esquerda"
                                                        style="width: 28px; height: 28px;">
                                                    <i class="ki-duotone ki-arrow-left fs-4">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </button>
                                            <?php else: ?>
                                                <div style="width: 28px;"></div>
                                            <?php endif; ?>
                                            <?php if ($stageIndex < count($stagesData) - 1): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-icon btn-color-gray-500 btn-active-color-primary" 
                                                        onclick="reorderStage(<?= $stage['id'] ?>, 'down')"
                                                        title="Mover para direita"
                                                        style="width: 28px; height: 28px;">
                                                    <i class="ki-duotone ki-arrow-right fs-4">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div></div>
                                    <?php endif; ?>
                                    
                                    <!-- Botões de Métricas e Edição -->
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" 
                                                class="btn btn-sm btn-icon btn-color-gray-500 btn-active-color-info" 
                                                onclick="showStageMetrics(<?= $stage['id'] ?>, <?= htmlspecialchars(json_encode($stage['name']), ENT_QUOTES, 'UTF-8') ?>)" 
                                                title="Ver métricas"
                                                style="width: 28px; height: 28px;">
                                            <i class="ki-duotone ki-chart-simple fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </button>
                                        
                                        <?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
                                            <?php if (!empty($stage['is_system_stage'])): ?>
                                                <!-- Etapa do sistema: apenas editar cor -->
                                                <button type="button" 
                                                        class="btn btn-sm btn-icon btn-color-gray-500 btn-active-color-primary" 
                                                        onclick="editStageColorOnly(<?= $stage['id'] ?>, <?= htmlspecialchars(json_encode($stage['name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($stage['color']), ENT_QUOTES, 'UTF-8') ?>)"
                                                        title="Editar cor"
                                                        style="width: 28px; height: 28px;">
                                                    <i class="ki-duotone ki-color-swatch fs-4">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                        <span class="path4"></span>
                                                    </i>
                                                </button>
                                            <?php else: ?>
                                                <!-- Etapa normal: dropdown com editar e deletar -->
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-icon btn-color-gray-500 btn-active-color-primary" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown"
                                                            style="width: 28px; height: 28px;">
                                                        <i class="ki-duotone ki-setting-2 fs-4">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="editStage(<?= $stage['id'] ?>, <?= htmlspecialchars(json_encode($stage['name']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($stage['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($stage['color']), ENT_QUOTES, 'UTF-8') ?>); return false;">
                                                                <i class="ki-duotone ki-pencil fs-4 me-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                </i>
                                                                Editar
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" onclick="deleteStage(<?= $stage['id'] ?>, <?= htmlspecialchars(json_encode($stage['name']), ENT_QUOTES, 'UTF-8') ?>); return false;">
                                                                <i class="ki-duotone ki-trash fs-4 me-2">
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
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-5 kanban-column-body" style="min-height: 400px; max-height: calc(100vh - 300px); overflow-y: auto;">
                                <div class="kanban-items d-flex flex-column gap-3" data-stage-id="<?= $stage['id'] ?>">
                                    <?php foreach ($conversations as $conv): 
                                        // Calcular indicadores
                                        $slaClass = match($conv['sla_status'] ?? 'ok') {
                                            'exceeded' => 'danger',
                                            'warning' => 'warning',
                                            default => 'success'
                                        };
                                        
                                        $senderIsClient = ($conv['last_message_sender'] ?? 'contact') === 'contact';
                                        $lastSenderName = $senderIsClient ? 'Cliente' : ($conv['last_agent_name'] ?? 'Agente');
                                        $lastSenderIcon = $senderIsClient ? '💬' : '📤';
                                        $lastSenderClass = $senderIsClient ? 'primary' : 'success';
                                    ?>
                                        <div class="kanban-item conversation-item card shadow-sm hover-elevate-up" 
                                             data-conversation-id="<?= $conv['id'] ?>"
                                             draggable="true"
                                             style="border-left: 4px solid <?= htmlspecialchars($stageColor) ?>; cursor: grab; transition: all 0.2s;">
                                             
                                            <!-- Cabeçalho com Avatar e Ações -->
                                            <div class="card-header border-0 px-5 py-3" style="min-height: auto;">
                                                <div class="d-flex align-items-center w-100">
                                                    <!-- Avatar Cliente -->
                                                    <div class="symbol symbol-40px me-3">
                                                        <?php if (!empty($conv['contact_avatar'])): ?>
                                                            <img src="<?= htmlspecialchars($conv['contact_avatar']) ?>" alt="<?= htmlspecialchars($conv['contact_name'] ?? '') ?>" class="rounded" />
                                                        <?php else: ?>
                                                            <div class="symbol-label fs-5 fw-bold text-primary bg-light-primary rounded">
                                                                <?= mb_substr(htmlspecialchars($conv['contact_name'] ?? 'C'), 0, 1) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Informações do Cliente -->
                                                    <div class="flex-grow-1" onclick="window.location.href='<?= \App\Helpers\Url::to('/conversations?id=' . $conv['id']) ?>'" style="cursor: pointer;">
                                                        <div class="fw-bold text-gray-800 fs-6"><?= htmlspecialchars($conv['contact_name'] ?? 'Sem nome') ?></div>
                                                        <div class="text-muted fs-7"><?= htmlspecialchars($conv['contact_phone'] ?? '') ?></div>
                                                    </div>
                                                    
                                                    <!-- Menu de Ações -->
                                                    <div class="dropdown" onclick="event.stopPropagation();">
                                                        <button class="btn btn-sm btn-icon btn-light btn-active-light-primary" type="button" data-bs-toggle="dropdown">
                                                            <i class="ki-duotone ki-dots-vertical fs-3">
                                                                <span class="path1"></span>
                                                                <span class="path2"></span>
                                                                <span class="path3"></span>
                                                            </i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><a class="dropdown-item" href="<?= \App\Helpers\Url::to('/conversations?id=' . $conv['id']) ?>"><i class="ki-duotone ki-eye fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>Ver Detalhes</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="quickAssignAgent(<?= $conv['id'] ?>); return false;"><i class="ki-duotone ki-user-tick fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>Atribuir Agente</a></li>
                                                            <li><a class="dropdown-item text-success" href="#" onclick="quickResolve(<?= $conv['id'] ?>); return false;"><i class="ki-duotone ki-check-circle fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>Resolver</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Corpo do Card -->
                                            <div class="card-body px-5 py-3">
                                                <!-- Preview da Última Mensagem -->
                                                <?php if (!empty($conv['last_message'])): ?>
                                                    <div class="mb-3">
                                                        <p class="text-gray-700 fs-7 mb-1" style="line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                            <?= htmlspecialchars(mb_substr($conv['last_message'], 0, 100)) ?>
                                                        </p>
                                                        <div class="d-flex align-items-center gap-2 mt-1">
                                                            <span class="badge badge-light-<?= $lastSenderClass ?> fs-8"><?= $lastSenderIcon ?> <?= $lastSenderName ?></span>
                                                            <span class="text-muted fs-8">
                                                                <?= $conv['last_message_at'] ? date('d/m H:i', strtotime($conv['last_message_at'])) : '' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Tags -->
                                                <?php if (!empty($conv['tags'])): ?>
                                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                                        <?php foreach (array_slice($conv['tags'], 0, 3) as $tag): ?>
                                                            <span class="badge fs-8" style="background-color: <?= htmlspecialchars($tag['color']) ?>20; color: <?= htmlspecialchars($tag['color']) ?>; border: 1px solid <?= htmlspecialchars($tag['color']) ?>;">
                                                                <?= htmlspecialchars($tag['name']) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($conv['tags']) > 3): ?>
                                                            <span class="badge badge-light fs-8">+<?= count($conv['tags']) - 3 ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Footer com Metadados -->
                                                <div class="d-flex align-items-center justify-content-between pt-2" style="border-top: 1px dashed #e4e6ef;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <!-- Avatar do Agente -->
                                                        <?php if (!empty($conv['agent_name'])): ?>
                                                            <div class="symbol symbol-20px" title="<?= htmlspecialchars($conv['agent_name']) ?>">
                                                                <?php if (!empty($conv['agent_avatar'])): ?>
                                                                    <img src="<?= htmlspecialchars($conv['agent_avatar']) ?>" alt="<?= htmlspecialchars($conv['agent_name']) ?>" class="rounded" />
                                                                <?php else: ?>
                                                                    <div class="symbol-label fs-8 fw-bold text-white bg-primary rounded">
                                                                        <?= mb_substr(htmlspecialchars($conv['agent_name']), 0, 1) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <span class="text-muted fs-8"><?= htmlspecialchars($conv['agent_name']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted fs-8">Não atribuído</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Indicadores -->
                                                    <div class="d-flex align-items-center gap-2">
                                                        <!-- SLA -->
                                                        <span class="badge badge-light-<?= $slaClass ?> fs-8" title="Tempo na etapa: <?= $conv['hours_in_stage'] ?? 0 ?>h">
                                                            ⏱️ <?= $conv['hours_in_stage'] ?? 0 ?>h
                                                        </span>
                                                        
                                                        <!-- Não Lidas -->
                                                        <?php if (($conv['unread_count'] ?? 0) > 0): ?>
                                                            <span class="badge badge-danger fs-8"><?= $conv['unread_count'] ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($conversations)): ?>
                                        <div class="text-center py-10">
                                            <i class="ki-duotone ki-folder-down fs-3x text-gray-300 mb-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <p class="text-muted fs-7">Nenhuma conversa nesta etapa</p>
                                        </div>
                                    <?php endif; ?>
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
$basePath = \App\Helpers\Url::basePath(); // ex: "" ou "/chat"

$scripts = '
<!-- Configurações do Kanban -->
<script>
// Configurações globais para o Kanban.js
const __KANBAN_BASE_PATH = "' . $basePath . '";
const __KANBAN_ORIGIN = window.location.origin || (window.location.protocol + "//" + window.location.host);

window.KANBAN_CONFIG = {
    funnelId: ' . $funnelIdForJs . ',
    moveConversationUrl: "' . \App\Helpers\Url::to('/funnels/' . $funnelIdForJs . '/conversations/move') . '",
    funnelBaseUrl: "' . \App\Helpers\Url::to('/funnels/' . $funnelIdForJs) . '",
    funnelsUrl: "' . \App\Helpers\Url::to('/funnels') . '",
    BASE_URL: __KANBAN_ORIGIN + __KANBAN_BASE_PATH
};
console.log("KANBAN_CONFIG inicializado:", window.KANBAN_CONFIG);
</script>
<!-- Kanban JavaScript -->
<script src="' . \App\Helpers\Url::asset('js/kanban.js') . '?v=' . time() . '"></script>
<script>
// Função fallback caso o arquivo JS ainda não tenha carregado (cache)
if (typeof window.reorderStage === "undefined") {
    console.warn("Função reorderStage não encontrada, definindo fallback...");
    window.reorderStage = async function(stageId, direction) {
        try {
            const BASE_URL = window.KANBAN_CONFIG?.BASE_URL || window.location.origin;
            const response = await fetch(`${BASE_URL}/funnels/stages/${stageId}/reorder`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ direction })
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    icon: "success",
                    title: "Ordem atualizada!",
                    text: "A etapa foi movida com sucesso.",
                    timer: 2000,
                    showConfirmButton: false
                });
                setTimeout(() => location.reload(), 500);
            } else {
                throw new Error(result.message || "Erro ao reordenar etapa");
            }
        } catch (error) {
            console.error("Erro ao reordenar etapa:", error);
            Swal.fire({
                icon: "error",
                title: "Erro",
                text: error.message || "Não foi possível reordenar a etapa"
            });
        }
    };
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
