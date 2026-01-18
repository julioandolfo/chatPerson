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

/* ============================================================================
   FILTROS DO KANBAN
   ============================================================================ */

/* Painel de filtros */
#kt_kanban_filters {
    transition: all 0.3s ease-in-out;
}

#kt_kanban_filters .card-body {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

/* Conversas filtradas (ocultas) */
.conversation-item.filtered-out {
    display: none !important;
}

/* Highlight quando filtros estão ativos */
.btn-light-primary[data-bs-toggle="collapse"][aria-expanded="true"] {
    background-color: #009ef7 !important;
    color: white !important;
}

/* Badge de contador de filtros */
#kt_filters_count {
    animation: pulse-badge 2s ease-in-out infinite;
}

@keyframes pulse-badge {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
}

/* Select2 customizado para filtros */
#kt_kanban_filters .select2-container {
    width: 100% !important;
}

#kt_kanban_filters .form-control:focus,
#kt_kanban_filters .form-select:focus {
    border-color: #009ef7;
    box-shadow: 0 0 0 0.2rem rgba(0, 158, 247, 0.15);
}

/* Ícones nos labels dos filtros */
#kt_kanban_filters .form-label i {
    opacity: 0.7;
}

/* Animação de transição suave ao filtrar */
.kanban-item.conversation-item {
    transition: opacity 0.3s ease, transform 0.3s ease, display 0.3s ease;
}

/* Estado de carregamento dos filtros */
.kanban-board.filtering {
    opacity: 0.6;
    pointer-events: none;
}

.kanban-board.filtering::after {
    content: "Aplicando filtros...";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 20px 40px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    font-weight: 600;
    color: #009ef7;
    z-index: 1000;
}

/* ============================================================================
   MODAL DE ORDENAÇÃO DE ETAPAS
   ============================================================================ */

.stage-order-item {
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.stage-order-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
    border-color: #e4e6ef;
}

.sortable-ghost {
    opacity: 0.4;
    background-color: #f1f1f2;
}

.sortable-chosen {
    background-color: #f9f9f9;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    border-color: #009ef7 !important;
}

.sortable-drag {
    opacity: 0.8;
    transform: rotate(2deg);
}

#kt_stage_order_list {
    min-height: 200px;
}

/* Animação ao mover etapas */
.stage-order-item {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* ============================================================================
   MODAL DE DETALHES DA CONVERSA
   ============================================================================ */

#kt_modal_conversation_details .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

#kt_modal_conversation_details .card {
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 60px;
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
}

.timeline-line {
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #e4e6ef;
}

.timeline-item:last-child .timeline-line {
    display: none;
}

.timeline-icon {
    position: absolute;
    left: 0;
    top: 0;
}

.timeline-content {
    padding-left: 20px;
}

/* Smooth scroll */
#kt_modal_conversation_details .modal-body::-webkit-scrollbar {
    width: 8px;
}

#kt_modal_conversation_details .modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#kt_modal_conversation_details .modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

#kt_modal_conversation_details .modal-body::-webkit-scrollbar-thumb:hover {
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
            
            <!-- Botão de Filtros -->
            <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="collapse" data-bs-target="#kt_kanban_filters">
                <i class="ki-duotone ki-filter fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Filtros
                <span class="badge badge-circle badge-primary ms-2" id="kt_filters_count" style="display: none;">0</span>
            </button>
            
            <?php if (!empty($currentFunnelId)): ?>
            <button type="button" class="btn btn-sm btn-light-info" onclick="showFunnelMetrics(<?= $currentFunnelId ?>)">
                <i class="ki-duotone ki-chart-simple fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Métricas do Funil
            </button>
            
            <?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
            <button type="button" class="btn btn-sm btn-light-primary" onclick="openStageOrderModal(<?= $currentFunnelId ?>)">
                <i class="ki-duotone ki-row-vertical fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Ordenar Etapas
            </button>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Painel de Filtros Avançados -->
    <div class="collapse" id="kt_kanban_filters">
        <div class="card-body border-top pt-6">
            <div class="row g-5">
                <!-- Coluna 1 -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-6">
                        <i class="ki-duotone ki-magnifier fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Buscar por Nome/Telefone
                    </label>
                    <input type="text" class="form-control form-control-solid" id="filter_search" placeholder="Digite para buscar...">
                </div>
                
                <!-- Coluna 2 -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-6">
                        <i class="ki-duotone ki-user fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Agente
                    </label>
                    <select class="form-select form-select-solid" id="filter_agent" data-control="select2" data-placeholder="Todos os agentes" data-allow-clear="true">
                        <option value="">Todos os agentes</option>
                        <option value="unassigned">Não atribuídas</option>
                        <?php
                        $agents = \App\Models\User::getAgents();
                        foreach ($agents as $agent):
                        ?>
                            <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Coluna 3 -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-6">
                        <i class="ki-duotone ki-status fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Status
                    </label>
                    <select class="form-select form-select-solid" id="filter_status" data-control="select2" data-placeholder="Todos os status" data-allow-clear="true">
                        <option value="">Todos os status</option>
                        <option value="open">Abertas</option>
                        <option value="pending">Pendentes</option>
                        <option value="resolved">Resolvidas</option>
                        <option value="closed">Fechadas</option>
                    </select>
                </div>
                
                <!-- Coluna 4 -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-6">
                        <i class="ki-duotone ki-flag fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Prioridade
                    </label>
                    <select class="form-select form-select-solid" id="filter_priority" data-control="select2" data-placeholder="Todas as prioridades" data-allow-clear="true">
                        <option value="">Todas as prioridades</option>
                        <option value="low">Baixa</option>
                        <option value="normal">Normal</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                </div>
            </div>
            
            <div class="row g-5 mt-2">
                <!-- Coluna 1 -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-6">
                        <i class="ki-duotone ki-tag fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Tags
                    </label>
                    <select class="form-select form-select-solid" id="filter_tags" data-control="select2" data-placeholder="Todas as tags" data-allow-clear="true" multiple>
                        <?php
                        $tags = \App\Services\TagService::getAll();
                        foreach ($tags as $tag):
                        ?>
                            <option value="<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Coluna 2 -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-6">
                        <i class="ki-duotone ki-time fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        SLA
                    </label>
                    <select class="form-select form-select-solid" id="filter_sla" data-control="select2" data-placeholder="Todos" data-allow-clear="true">
                        <option value="">Todos</option>
                        <option value="ok">Dentro do prazo</option>
                        <option value="warning">Próximo do vencimento</option>
                        <option value="exceeded">Vencido</option>
                    </select>
                </div>
                
                <!-- Coluna 3 -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-6">
                        <i class="ki-duotone ki-calendar fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Período de Criação
                    </label>
                    <select class="form-select form-select-solid" id="filter_period" data-control="select2" data-placeholder="Todos os períodos" data-allow-clear="true">
                        <option value="">Todos os períodos</option>
                        <option value="today">Hoje</option>
                        <option value="yesterday">Ontem</option>
                        <option value="last_7_days">Últimos 7 dias</option>
                        <option value="last_30_days">Últimos 30 dias</option>
                        <option value="last_90_days">Últimos 90 dias</option>
                    </select>
                </div>
                
                <!-- Coluna 4 -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-6">
                        <i class="ki-duotone ki-message-text-2 fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Mensagens Não Lidas
                    </label>
                    <select class="form-select form-select-solid" id="filter_unread" data-control="select2" data-placeholder="Todas" data-allow-clear="true">
                        <option value="">Todas</option>
                        <option value="yes">Com não lidas</option>
                        <option value="no">Sem não lidas</option>
                    </select>
                </div>
            </div>
            
            <div class="separator my-5"></div>
            
            <!-- Guia de Atalhos -->
            <div class="alert alert-dismissible bg-light-info d-flex flex-column flex-sm-row p-5 mb-5">
                <i class="ki-duotone ki-keyboard fs-2hx text-info me-4 mb-5 mb-sm-0">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="d-flex flex-column pe-0 pe-sm-10">
                    <h5 class="mb-2">Atalhos de Teclado</h5>
                    <div class="fs-7 text-gray-700">
                        <kbd>Ctrl+F</kbd> Buscar &nbsp;|&nbsp; 
                        <kbd>Ctrl+Enter</kbd> Aplicar &nbsp;|&nbsp; 
                        <kbd>Esc</kbd> Limpar &nbsp;|&nbsp; 
                        <kbd>Ctrl+S</kbd> Salvar &nbsp;|&nbsp; 
                        <kbd>Ctrl+E</kbd> Exportar
                    </div>
                </div>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            
            <!-- Botões de Ação -->
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted fs-7">
                    <span id="kt_kanban_total_results">Mostrando todas as conversas</span>
                </div>
                <div class="d-flex gap-2">
                    <!-- Salvar Filtro -->
                    <button type="button" class="btn btn-sm btn-light-success" onclick="saveCurrentFilters()" title="Salvar filtros atuais">
                        <i class="ki-duotone ki-save-2 fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Salvar
                    </button>
                    
                    <!-- Carregar Filtros Salvos -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light-info dropdown-toggle" data-bs-toggle="dropdown" title="Carregar filtros salvos">
                            <i class="ki-duotone ki-folder-down fs-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Carregar
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" id="saved_filters_list">
                            <li><a class="dropdown-item text-muted">Nenhum filtro salvo</a></li>
                        </ul>
                    </div>
                    
                    <!-- Exportar -->
                    <button type="button" class="btn btn-sm btn-light-warning" onclick="exportFilteredConversations()" title="Exportar conversas filtradas">
                        <i class="ki-duotone ki-file-down fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Exportar
                    </button>
                    
                    <!-- Limpar -->
                    <button type="button" class="btn btn-sm btn-light-danger" onclick="clearFilters()">
                        <i class="ki-duotone ki-cross fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Limpar
                    </button>
                    
                    <!-- Aplicar -->
                    <button type="button" class="btn btn-sm btn-primary" onclick="applyFilters()">
                        <i class="ki-duotone ki-check fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Aplicar
                    </button>
                </div>
            </div>
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
                                    <div></div>
                                    
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
                                             data-contact-name="<?= htmlspecialchars($conv['contact_name'] ?? '', ENT_QUOTES) ?>"
                                             data-contact-phone="<?= htmlspecialchars($conv['contact_phone'] ?? '', ENT_QUOTES) ?>"
                                             data-agent-id="<?= $conv['agent_id'] ?? '' ?>"
                                             data-agent-name="<?= htmlspecialchars($conv['agent_name'] ?? '', ENT_QUOTES) ?>"
                                             data-status="<?= $conv['status'] ?? 'open' ?>"
                                             data-priority="<?= $conv['priority'] ?? 'normal' ?>"
                                             data-sla-status="<?= $conv['sla_status'] ?? 'ok' ?>"
                                             data-unread-count="<?= $conv['unread_count'] ?? 0 ?>"
                                             data-created-at="<?= $conv['created_at'] ?? '' ?>"
                                             data-tags="<?= htmlspecialchars(json_encode(array_column($conv['tags'] ?? [], 'id')), ENT_QUOTES) ?>"
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
                                                            <li><a class="dropdown-item" href="#" onclick="showConversationDetails(<?= $conv['id'] ?>); return false;">
                                                                <i class="ki-duotone ki-information fs-4 me-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                </i>
                                                                Ver Detalhes
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="<?= \App\Helpers\Url::to('/conversations?id=' . $conv['id']) ?>">
                                                                <i class="ki-duotone ki-messages fs-4 me-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                    <span class="path4"></span>
                                                                    <span class="path5"></span>
                                                                </i>
                                                                Ver Conversa
                                                            </a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item" href="#" onclick="quickAssignAgent(<?= $conv['id'] ?>); return false;">
                                                                <i class="ki-duotone ki-user-tick fs-4 me-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                    <span class="path3"></span>
                                                                </i>
                                                                Atribuir Agente
                                                            </a></li>
                                                            <li><a class="dropdown-item text-success" href="#" onclick="quickResolve(<?= $conv['id'] ?>); return false;">
                                                                <i class="ki-duotone ki-check-circle fs-4 me-2">
                                                                    <span class="path1"></span>
                                                                    <span class="path2"></span>
                                                                </i>
                                                                Resolver
                                                            </a></li>
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
                            
                            <!--begin::IA Fields-->
                            <div class="separator separator-dashed my-5"></div>
                            <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4 mb-5">
                                <i class="ki-duotone ki-abstract-24 fs-2tx text-primary me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="d-flex flex-stack flex-grow-1">
                                    <div class="fw-semibold">
                                        <h4 class="text-gray-900 fw-bold">🧠 Configurações para IA Inteligente</h4>
                                        <div class="fs-7 text-gray-600">Estas descrições ajudam a IA a entender quando mover conversas para esta etapa automaticamente.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">
                                    <i class="ki-duotone ki-abstract-26 fs-5 me-1 text-primary"><span class="path1"></span><span class="path2"></span></i>
                                    Descrição para IA
                                </label>
                                <textarea name="ai_description" id="kt_stage_ai_description" class="form-control form-control-solid" rows="4" placeholder="Descreva quando uma conversa deve estar nesta etapa. Ex: 'Cliente demonstrou interesse em comprar mas ainda não informou o que deseja. Precisa de qualificação inicial.'"></textarea>
                                <div class="form-text text-muted">
                                    Seja específico sobre o contexto e intenção do cliente que caracteriza esta etapa.
                                </div>
                            </div>
                            
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">
                                    <i class="ki-duotone ki-tag fs-5 me-1 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    Palavras-chave para IA
                                </label>
                                <input type="text" name="ai_keywords" id="kt_stage_ai_keywords" class="form-control form-control-solid" placeholder="comprar, orçamento, preço, valor, interesse" />
                                <div class="form-text text-muted">
                                    Palavras-chave separadas por vírgula que indicam esta etapa.
                                </div>
                            </div>
                            <!--end::IA Fields-->
                            
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

<!--begin::Modal - Ordenar Etapas-->
<?php if (\App\Helpers\Permission::can('funnels.edit')): ?>
<div class="modal fade" id="kt_modal_stage_order" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">
                    <i class="ki-duotone ki-row-vertical fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Ordenar Etapas
                </h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                    <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Como usar</h4>
                        <span>Arraste e solte as etapas para reordená-las. A ordem será salva automaticamente ao clicar em "Salvar Ordem".</span>
                    </div>
                </div>
                
                <div id="kt_stage_order_list" class="d-flex flex-column gap-3">
                    <!-- Preenchido via JavaScript -->
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                <button type="button" onclick="saveStageOrder()" class="btn btn-primary">
                    <i class="ki-duotone ki-check fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Salvar Ordem
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Ordenar Etapas-->

<!--begin::Modal - Detalhes da Conversa-->
<div class="modal fade" id="kt_modal_conversation_details" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">
                    <i class="ki-duotone ki-information-5 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Detalhes da Conversa
                </h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y">
                <div id="conversation_details_content">
                    <!-- Conteúdo carregado via JavaScript -->
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="text-muted mt-3">Carregando detalhes...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                <a href="#" id="btn_open_conversation" class="btn btn-primary" target="_blank">
                    <i class="ki-duotone ki-messages fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    Abrir Conversa
                </a>
            </div>
        </div>
    </div>
</div>
<!--end::Modal - Detalhes da Conversa-->

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

$scripts = <<<'SCRIPTS'
<!-- Sortable.js para Drag and Drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<!-- Configurações do Kanban -->
<script>
SCRIPTS;

$scripts .= '
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
<script src="' . \App\Helpers\Url::asset('js/conversation-details.js') . '?v=' . time() . '"></script>
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
<script src="' . \App\Helpers\Url::asset('js/conversation-details.js') . '?v=' . time() . '"></script>
';

$scripts .= <<<'SCRIPTS_INLINE'
<script>
// ============================================================================
// SISTEMA DE FILTROS DO KANBAN
// ============================================================================

// Estado dos filtros
let activeFilters = {};
let allConversations = {}; // Armazena todas as conversas por etapa

// Inicializar sistema de filtros
document.addEventListener("DOMContentLoaded", function() {
    console.log('Inicializando sistema de filtros do Kanban');
    
    // Armazenar conversas originais
    storeOriginalConversations();
    
    // Aplicar filtros em tempo real (debounced)
    let filterTimeout;
    document.getElementById('filter_search')?.addEventListener('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => applyFilters(), 300);
    });
    
    // Detectar mudanças nos selects
    ['filter_agent', 'filter_status', 'filter_priority', 'filter_tags', 'filter_sla', 'filter_period', 'filter_unread'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', () => {
                // Aplicar automaticamente após selecionar
                setTimeout(() => applyFilters(), 100);
            });
        }
    });
    
    // Enter no campo de busca aplica filtros
    document.getElementById('filter_search')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
});

// Armazenar conversas originais
function storeOriginalConversations() {
    const stages = document.querySelectorAll('.kanban-items');
    stages.forEach(stage => {
        const stageId = stage.getAttribute('data-stage-id');
        const conversations = Array.from(stage.querySelectorAll('.conversation-item'));
        allConversations[stageId] = conversations.map(conv => ({
            element: conv,
            data: extractConversationData(conv)
        }));
    });
    console.log('Conversas armazenadas:', allConversations);
}

// Extrair dados da conversa do DOM (usando data-attributes)
function extractConversationData(element) {
    const messageEl = element.querySelector('.text-gray-700.fs-7');
    
    return {
        id: element.getAttribute('data-conversation-id'),
        name: (element.getAttribute('data-contact-name') || '').toLowerCase(),
        phone: (element.getAttribute('data-contact-phone') || '').toLowerCase(),
        message: messageEl?.textContent.trim().toLowerCase() || '',
        agentId: element.getAttribute('data-agent-id') || '',
        agentName: (element.getAttribute('data-agent-name') || '').toLowerCase(),
        status: element.getAttribute('data-status') || 'open',
        priority: element.getAttribute('data-priority') || 'normal',
        sla: element.getAttribute('data-sla-status') || 'ok',
        unread: parseInt(element.getAttribute('data-unread-count')) || 0,
        tags: JSON.parse(element.getAttribute('data-tags') || '[]'),
        created_at: element.getAttribute('data-created-at') || '',
        element: element
    };
}

// Obter status SLA do elemento
function getSlaStatus(element) {
    if (!element) return 'ok';
    if (element.classList.contains('badge-light-danger')) return 'exceeded';
    if (element.classList.contains('badge-light-warning')) return 'warning';
    return 'ok';
}

// Aplicar filtros
function applyFilters() {
    console.log('Aplicando filtros...');
    
    // Adicionar indicador visual de carregamento
    const kanbanBoard = document.getElementById('kt_kanban_board');
    if (kanbanBoard) {
        kanbanBoard.classList.add('filtering');
    }
    
    // Pequeno delay para melhor UX (mostrar feedback visual)
    setTimeout(() => {
        // Coletar valores dos filtros
        activeFilters = {
            search: document.getElementById('filter_search')?.value.toLowerCase().trim() || '',
            agent: document.getElementById('filter_agent')?.value || '',
            status: document.getElementById('filter_status')?.value || '',
            priority: document.getElementById('filter_priority')?.value || '',
            tags: Array.from(document.getElementById('filter_tags')?.selectedOptions || []).map(opt => opt.text.toLowerCase()),
            sla: document.getElementById('filter_sla')?.value || '',
            period: document.getElementById('filter_period')?.value || '',
            unread: document.getElementById('filter_unread')?.value || ''
        };
        
        console.log('Filtros ativos:', activeFilters);
        
        let totalVisible = 0;
        let totalHidden = 0;
    
    // Aplicar filtros em cada etapa
    Object.keys(allConversations).forEach(stageId => {
        const conversations = allConversations[stageId];
        const stageContainer = document.querySelector(`.kanban-items[data-stage-id="${stageId}"]`);
        
        if (!stageContainer) return;
        
        let visibleInStage = 0;
        
        conversations.forEach(conv => {
            const shouldShow = matchesAllFilters(conv.data);
            
            if (shouldShow) {
                conv.element.style.display = 'block';
                conv.element.classList.remove('filtered-out');
                visibleInStage++;
                totalVisible++;
            } else {
                conv.element.style.display = 'none';
                conv.element.classList.add('filtered-out');
                totalHidden++;
            }
        });
        
        // Atualizar contador da etapa
        const countBadge = document.getElementById(`stage_count_${stageId}`);
        if (countBadge) {
            countBadge.textContent = visibleInStage;
        }
    });
    
        // Atualizar contador de filtros ativos
        updateFiltersCount();
        
        // Atualizar texto de resultados
        const resultsText = document.getElementById('kt_kanban_total_results');
        if (resultsText) {
            if (totalHidden > 0) {
                resultsText.innerHTML = `<strong>${totalVisible}</strong> conversas encontradas <span class="text-muted">(${totalHidden} ocultas pelos filtros)</span>`;
            } else {
                resultsText.textContent = 'Mostrando todas as conversas';
            }
        }
        
        // Remover indicador de carregamento
        if (kanbanBoard) {
            kanbanBoard.classList.remove('filtering');
        }
        
        console.log(`Filtros aplicados: ${totalVisible} visíveis, ${totalHidden} ocultas`);
        
        // Mostrar notificação de sucesso se houver filtros ativos
        const activeFilterCount = Object.values(activeFilters).filter(v => 
            Array.isArray(v) ? v.length > 0 : v !== ''
        ).length;
        
        if (activeFilterCount > 0) {
            // Toastr notification (se disponível)
            if (typeof toastr !== 'undefined') {
                toastr.success(`${totalVisible} conversas encontradas com os filtros aplicados`, 'Filtros Aplicados', {
                    timeOut: 2000,
                    positionClass: 'toast-top-right'
                });
            }
        }
    }, 100); // Delay de 100ms para feedback visual
}

// Verificar se conversa atende todos os filtros
function matchesAllFilters(data) {
    // Filtro de busca (nome, telefone ou mensagem)
    if (activeFilters.search) {
        const searchLower = activeFilters.search;
        if (!data.name.includes(searchLower) && 
            !data.phone.includes(searchLower) &&
            !data.message.includes(searchLower)) {
            return false;
        }
    }
    
    // Filtro de agente
    if (activeFilters.agent) {
        if (activeFilters.agent === 'unassigned') {
            if (data.agentId !== '' && data.agentId !== null) {
                return false;
            }
        } else {
            if (data.agentId !== activeFilters.agent) {
                return false;
            }
        }
    }
    
    // Filtro de status
    if (activeFilters.status && data.status !== activeFilters.status) {
        return false;
    }
    
    // Filtro de prioridade
    if (activeFilters.priority && data.priority !== activeFilters.priority) {
        return false;
    }
    
    // Filtro de SLA
    if (activeFilters.sla && data.sla !== activeFilters.sla) {
        return false;
    }
    
    // Filtro de tags (verificar se tem TODAS as tags selecionadas)
    if (activeFilters.tags && activeFilters.tags.length > 0) {
        const tagIds = activeFilters.tags.map(tagName => {
            // Encontrar ID da tag pelo nome
            const option = document.querySelector(`#filter_tags option[selected]`);
            return option?.value;
        });
        
        const hasAllTags = activeFilters.tags.every(tagName => {
            // Buscar pelo nome da tag nos elementos visuais
            const tagElements = data.element.querySelectorAll('.badge.fs-8[style*="background-color"]');
            return Array.from(tagElements).some(el => 
                el.textContent.trim().toLowerCase().includes(tagName)
            );
        });
        
        if (!hasAllTags) {
            return false;
        }
    }
    
    // Filtro de período de criação
    if (activeFilters.period && data.created_at) {
        const createdDate = new Date(data.created_at);
        const now = new Date();
        const diffDays = Math.floor((now - createdDate) / (1000 * 60 * 60 * 24));
        
        switch(activeFilters.period) {
            case 'today':
                if (diffDays > 0) return false;
                break;
            case 'yesterday':
                if (diffDays !== 1) return false;
                break;
            case 'last_7_days':
                if (diffDays > 7) return false;
                break;
            case 'last_30_days':
                if (diffDays > 30) return false;
                break;
            case 'last_90_days':
                if (diffDays > 90) return false;
                break;
        }
    }
    
    // Filtro de mensagens não lidas
    if (activeFilters.unread) {
        if (activeFilters.unread === 'yes' && data.unread === 0) {
            return false;
        }
        if (activeFilters.unread === 'no' && data.unread > 0) {
            return false;
        }
    }
    
    return true;
}

// Atualizar contador de filtros ativos
function updateFiltersCount() {
    let count = 0;
    if (activeFilters.search) count++;
    if (activeFilters.agent) count++;
    if (activeFilters.status) count++;
    if (activeFilters.priority) count++;
    if (activeFilters.tags && activeFilters.tags.length > 0) count += activeFilters.tags.length;
    if (activeFilters.sla) count++;
    if (activeFilters.period) count++;
    if (activeFilters.unread) count++;
    
    const badge = document.getElementById('kt_filters_count');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Limpar filtros
function clearFilters() {
    console.log('Limpando filtros...');
    
    // Limpar campos
    document.getElementById('filter_search').value = '';
    
    // Limpar selects
    ['filter_agent', 'filter_status', 'filter_priority', 'filter_sla', 'filter_period', 'filter_unread'].forEach(id => {
        const select = document.getElementById(id);
        if (select) {
            $(select).val('').trigger('change'); // Usar jQuery para Select2
        }
    });
    
    // Limpar tags (multiselect)
    const tagsSelect = document.getElementById('filter_tags');
    if (tagsSelect) {
        $(tagsSelect).val([]).trigger('change');
    }
    
    // Resetar filtros ativos
    activeFilters = {};
    
    // Mostrar todas as conversas
    Object.keys(allConversations).forEach(stageId => {
        const conversations = allConversations[stageId];
        conversations.forEach(conv => {
            conv.element.style.display = 'block';
            conv.element.classList.remove('filtered-out');
        });
        
        // Restaurar contador original
        const countBadge = document.getElementById(`stage_count_${stageId}`);
        if (countBadge) {
            countBadge.textContent = conversations.length;
        }
    });
    
    // Atualizar UI
    updateFiltersCount();
    document.getElementById('kt_kanban_total_results').textContent = 'Mostrando todas as conversas';
    
    console.log('Filtros limpos');
}

// Função auxiliar para fechar painel de filtros
function toggleFilters() {
    const filterPanel = document.getElementById('kt_kanban_filters');
    if (filterPanel) {
        const bsCollapse = new bootstrap.Collapse(filterPanel, {
            toggle: true
        });
    }
}

// ============================================================================
// SALVAR E CARREGAR FILTROS
// ============================================================================

// Salvar filtros atuais como favorito
function saveCurrentFilters() {
    // Verificar se há filtros ativos
    const activeFilterCount = Object.values(activeFilters).filter(v => 
        Array.isArray(v) ? v.length > 0 : v !== ''
    ).length;
    
    if (activeFilterCount === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Nenhum filtro ativo',
            text: 'Configure alguns filtros antes de salvar',
            timer: 2000
        });
        return;
    }
    
    // Pedir nome para o filtro
    Swal.fire({
        title: 'Salvar Filtros',
        input: 'text',
        inputLabel: 'Nome do filtro',
        inputPlaceholder: 'Ex: Conversas urgentes não atribuídas',
        showCancelButton: true,
        confirmButtonText: 'Salvar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!value) {
                return 'Digite um nome para o filtro!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const filterName = result.value;
            
            // Buscar filtros salvos
            let savedFilters = JSON.parse(localStorage.getItem('kanban_saved_filters') || '[]');
            
            // Adicionar novo filtro
            savedFilters.push({
                name: filterName,
                filters: {...activeFilters},
                created_at: new Date().toISOString(),
                funnel_id: window.KANBAN_CONFIG?.funnelId || 0
            });
            
            // Salvar no localStorage
            localStorage.setItem('kanban_saved_filters', JSON.stringify(savedFilters));
            
            // Atualizar lista
            loadSavedFiltersList();
            
            Swal.fire({
                icon: 'success',
                title: 'Filtro salvo!',
                text: `Filtro "${filterName}" salvo com sucesso`,
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Carregar lista de filtros salvos
function loadSavedFiltersList() {
    const savedFilters = JSON.parse(localStorage.getItem('kanban_saved_filters') || '[]');
    const currentFunnelId = window.KANBAN_CONFIG?.funnelId || 0;
    
    // Filtrar por funil atual
    const funnelFilters = savedFilters.filter(f => f.funnel_id === currentFunnelId);
    
    const listElement = document.getElementById('saved_filters_list');
    if (!listElement) return;
    
    if (funnelFilters.length === 0) {
        listElement.innerHTML = '<li><a class="dropdown-item text-muted">Nenhum filtro salvo</a></li>';
        return;
    }
    
    listElement.innerHTML = funnelFilters.map((filter, index) => `
        <li>
            <a class="dropdown-item d-flex justify-content-between align-items-center" href="#" onclick="loadSavedFilter(${index}); return false;">
                <span>
                    <i class="ki-duotone ki-filter fs-4 me-2 text-primary">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    ${filter.name}
                </span>
                <button class="btn btn-sm btn-icon btn-light-danger" onclick="event.stopPropagation(); deleteSavedFilter(${index}); return false;" title="Deletar">
                    <i class="ki-duotone ki-trash fs-6">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                </button>
            </a>
        </li>
    `).join('') + `
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" onclick="deleteAllSavedFilters(); return false;">
            <i class="ki-duotone ki-trash fs-4 me-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            Deletar Todos
        </a></li>
    `;
}

// Carregar filtro salvo específico
function loadSavedFilter(index) {
    const savedFilters = JSON.parse(localStorage.getItem('kanban_saved_filters') || '[]');
    const currentFunnelId = window.KANBAN_CONFIG?.funnelId || 0;
    const funnelFilters = savedFilters.filter(f => f.funnel_id === currentFunnelId);
    
    if (!funnelFilters[index]) return;
    
    const filter = funnelFilters[index];
    
    // Aplicar valores aos campos
    document.getElementById('filter_search').value = filter.filters.search || '';
    
    // Aplicar selects
    $('#filter_agent').val(filter.filters.agent || '').trigger('change');
    $('#filter_status').val(filter.filters.status || '').trigger('change');
    $('#filter_priority').val(filter.filters.priority || '').trigger('change');
    $('#filter_sla').val(filter.filters.sla || '').trigger('change');
    $('#filter_period').val(filter.filters.period || '').trigger('change');
    $('#filter_unread').val(filter.filters.unread || '').trigger('change');
    
    // Aplicar tags (multiselect) - precisa de tratamento especial
    // Por enquanto, vamos apenas aplicar os filtros
    
    // Aplicar filtros
    applyFilters();
    
    // Notificar
    if (typeof toastr !== 'undefined') {
        toastr.info(`Filtro "${filter.name}" carregado`, 'Filtros', {
            timeOut: 2000
        });
    }
}

// Deletar filtro salvo
function deleteSavedFilter(index) {
    const savedFilters = JSON.parse(localStorage.getItem('kanban_saved_filters') || '[]');
    const currentFunnelId = window.KANBAN_CONFIG?.funnelId || 0;
    const funnelFilters = savedFilters.filter(f => f.funnel_id === currentFunnelId);
    
    if (!funnelFilters[index]) return;
    
    const filterName = funnelFilters[index].name;
    
    Swal.fire({
        title: 'Confirmar exclusão',
        text: `Deseja deletar o filtro "${filterName}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f1416c'
    }).then((result) => {
        if (result.isConfirmed) {
            // Encontrar índice no array completo
            const filterToDelete = funnelFilters[index];
            const globalIndex = savedFilters.findIndex(f => 
                f.name === filterToDelete.name && 
                f.created_at === filterToDelete.created_at
            );
            
            if (globalIndex !== -1) {
                savedFilters.splice(globalIndex, 1);
                localStorage.setItem('kanban_saved_filters', JSON.stringify(savedFilters));
                loadSavedFiltersList();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Filtro deletado!',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }
    });
}

// Deletar todos os filtros salvos
function deleteAllSavedFilters() {
    Swal.fire({
        title: 'Confirmar exclusão',
        text: 'Deseja deletar TODOS os filtros salvos?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar todos',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f1416c'
    }).then((result) => {
        if (result.isConfirmed) {
            localStorage.removeItem('kanban_saved_filters');
            loadSavedFiltersList();
            
            Swal.fire({
                icon: 'success',
                title: 'Todos os filtros foram deletados!',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

// ============================================================================
// EXPORTAR CONVERSAS FILTRADAS
// ============================================================================

// Exportar conversas filtradas para CSV
function exportFilteredConversations() {
    // Coletar conversas visíveis
    const visibleConversations = [];
    
    Object.keys(allConversations).forEach(stageId => {
        allConversations[stageId].forEach(conv => {
            if (conv.element.style.display !== 'none' && !conv.element.classList.contains('filtered-out')) {
                visibleConversations.push({
                    id: conv.data.id,
                    nome: conv.data.name,
                    telefone: conv.data.phone,
                    agente: conv.data.agentName || 'Não atribuído',
                    status: conv.data.status,
                    prioridade: conv.data.priority,
                    sla: conv.data.sla,
                    nao_lidas: conv.data.unread,
                    criado_em: conv.data.created_at
                });
            }
        });
    });
    
    if (visibleConversations.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Nenhuma conversa para exportar',
            text: 'Não há conversas visíveis com os filtros atuais',
            timer: 2000
        });
        return;
    }
    
    // Gerar CSV
    const headers = ['ID', 'Nome', 'Telefone', 'Agente', 'Status', 'Prioridade', 'SLA', 'Não Lidas', 'Criado Em'];
    const csvContent = [
        headers.join(','),
        ...visibleConversations.map(conv => [
            conv.id,
            `"${conv.nome}"`,
            conv.telefone,
            `"${conv.agente}"`,
            conv.status,
            conv.prioridade,
            conv.sla,
            conv.nao_lidas,
            conv.criado_em
        ].join(','))
    ].join('\n');
    
    // Download
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
    
    link.setAttribute('href', url);
    link.setAttribute('download', `conversas_kanban_${timestamp}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Notificar
    Swal.fire({
        icon: 'success',
        title: 'Exportação concluída!',
        text: `${visibleConversations.length} conversas exportadas`,
        timer: 2000,
        showConfirmButton: false
    });
}

// Carregar lista de filtros salvos ao inicializar
document.addEventListener('DOMContentLoaded', function() {
    loadSavedFiltersList();
});

// ============================================================================
// ORDENAÇÃO DE ETAPAS (DRAG AND DROP)
// ============================================================================

let stageOrderSortable = null;
let currentFunnelIdForOrder = null;

// Abrir modal de ordenação de etapas
async function openStageOrderModal(funnelId) {
    currentFunnelIdForOrder = funnelId;
    
    try {
        // Buscar etapas do funil
        const response = await fetch(`${window.KANBAN_CONFIG.BASE_URL}/funnels/${funnelId}/stages`);
        const data = await response.json();
        
        if (!data.success || !data.stages) {
            throw new Error('Erro ao carregar etapas');
        }
        
        const stages = data.stages;
        
        // Ordenar por stage_order, position e id (mesma lógica do backend)
        stages.sort((a, b) => {
            const orderA = a.stage_order || a.position || 0;
            const orderB = b.stage_order || b.position || 0;
            if (orderA === orderB) {
                return (a.id || 0) - (b.id || 0);
            }
            return orderA - orderB;
        });
        
        // Renderizar lista de etapas
        const listElement = document.getElementById('kt_stage_order_list');
        listElement.innerHTML = stages.map(stage => `
            <div class="card shadow-sm stage-order-item" data-stage-id="${stage.id}" style="cursor: move;">
                <div class="card-body d-flex align-items-center p-4">
                    <i class="ki-duotone ki-menu fs-2 text-gray-500 me-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="symbol symbol-40px me-3">
                            <div class="symbol-label" style="background-color: ${stage.color || '#009ef7'}20;">
                                <i class="ki-duotone ki-category fs-2" style="color: ${stage.color || '#009ef7'};">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                        <div>
                            <div class="fw-bold text-gray-800 fs-6">${stage.name}</div>
                            ${stage.is_system_stage ? '<span class="badge badge-success badge-sm mt-1">Etapa do Sistema</span>' : ''}
                        </div>
                    </div>
                    <div class="text-muted fs-7">
                        <i class="ki-duotone ki-arrow-up-down fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Inicializar Sortable.js
        if (stageOrderSortable) {
            stageOrderSortable.destroy();
        }
        
        stageOrderSortable = new Sortable(listElement, {
            animation: 150,
            handle: '.stage-order-item',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            easing: 'cubic-bezier(0.4, 0.0, 0.2, 1)',
            onEnd: function(evt) {
                console.log('Etapa movida da posição', evt.oldIndex, 'para', evt.newIndex);
            }
        });
        
        // Abrir modal
        const modal = new bootstrap.Modal(document.getElementById('kt_modal_stage_order'));
        modal.show();
        
    } catch (error) {
        console.error('Erro ao abrir modal de ordenação:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Não foi possível carregar as etapas'
        });
    }
}

// Salvar ordem das etapas
async function saveStageOrder() {
    if (!currentFunnelIdForOrder) return;
    
    try {
        // Obter IDs na ordem atual
        const listElement = document.getElementById('kt_stage_order_list');
        const stageItems = listElement.querySelectorAll('.stage-order-item');
        const stageIds = Array.from(stageItems).map(item => parseInt(item.dataset.stageId));
        
        console.log('Salvando ordem:', stageIds);
        
        // Enviar para o backend
        const response = await fetch(`${window.KANBAN_CONFIG.BASE_URL}/funnels/${currentFunnelIdForOrder}/stages/reorder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                stage_ids: stageIds
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_stage_order'));
            modal.hide();
            
            // Mostrar sucesso
            Swal.fire({
                icon: 'success',
                title: 'Ordem salva!',
                text: 'As etapas foram reordenadas com sucesso',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Recarregar página para atualizar Kanban
                location.reload();
            });
        } else {
            throw new Error(data.message || 'Erro ao salvar ordem');
        }
    } catch (error) {
        console.error('Erro ao salvar ordem:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro ao salvar',
            text: error.message || 'Não foi possível salvar a ordem das etapas'
        });
    }
}

// Exportar função global
window.openStageOrderModal = openStageOrderModal;
window.saveStageOrder = saveStageOrder;

// ============================================================================
// DETALHES DA CONVERSA (MODAL) - implementado em conversation-details.js
// ============================================================================

// ============================================================================
// ATALHOS DE TECLADO
// ============================================================================

// Adicionar atalhos de teclado
document.addEventListener('keydown', function(e) {
    // Verificar se não está em um input/textarea (exceto o campo de busca dos filtros)
    const isInputActive = document.activeElement.tagName === 'INPUT' || 
                          document.activeElement.tagName === 'TEXTAREA' ||
                          document.activeElement.tagName === 'SELECT';
    
    // Ctrl/Cmd + F: Focar no campo de busca dos filtros
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const filterSearch = document.getElementById('filter_search');
        if (filterSearch) {
            // Expandir painel se estiver fechado
            const filterPanel = document.getElementById('kt_kanban_filters');
            if (filterPanel && !filterPanel.classList.contains('show')) {
                toggleFilters();
            }
            
            // Focar no campo
            setTimeout(() => {
                filterSearch.focus();
                filterSearch.select();
            }, 300);
        }
    }
    
    // Ctrl/Cmd + Enter: Aplicar filtros (quando estiver em campos de filtro)
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && isInputActive) {
        if (e.target.id && e.target.id.startsWith('filter_')) {
            e.preventDefault();
            applyFilters();
        }
    }
    
    // Esc: Limpar filtros (quando estiver nos campos de filtro)
    if (e.key === 'Escape' && isInputActive) {
        if (e.target.id && e.target.id.startsWith('filter_')) {
            e.preventDefault();
            clearFilters();
        }
    }
    
    // Ctrl/Cmd + S: Salvar filtros atuais
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        // Verificar se há filtros ativos
        const activeFilterCount = Object.values(activeFilters).filter(v => 
            Array.isArray(v) ? v.length > 0 : v !== ''
        ).length;
        
        if (activeFilterCount > 0) {
            e.preventDefault();
            saveCurrentFilters();
        }
    }
    
    // Ctrl/Cmd + E: Exportar conversas filtradas
    if ((e.ctrlKey || e.metaKey) && e.key === 'e' && !isInputActive) {
        e.preventDefault();
        exportFilteredConversations();
    }
});

// ============================================================================
// INDICADOR DE ATALHOS (TOOLTIP)
// ============================================================================

// Adicionar tooltips com atalhos
document.addEventListener('DOMContentLoaded', function() {
    // Campo de busca
    const searchField = document.getElementById('filter_search');
    if (searchField && typeof bootstrap !== 'undefined') {
        searchField.setAttribute('title', 'Atalho: Ctrl+F para focar');
        searchField.setAttribute('data-bs-toggle', 'tooltip');
        searchField.setAttribute('data-bs-placement', 'top');
    }
    
    // Atualizar placeholder do campo de busca
    if (searchField) {
        searchField.placeholder = 'Digite para buscar... (Ctrl+F)';
    }
});
</script>
SCRIPTS_INLINE;
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
