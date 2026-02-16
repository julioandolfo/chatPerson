<?php
$layout = 'layouts.metronic.app';
$title = 'Automação - ' . htmlspecialchars($automation['name'] ?? '');

// Extrair configurações do trigger para o formulário de edição (definir ANTES do modal)
$triggerType = $automation['trigger_type'] ?? 'new_conversation';
$triggerConfig = $automation['trigger_config'] ?? [];
if (is_string($triggerConfig)) {
    $triggerConfig = json_decode($triggerConfig, true) ?? [];
}
$waitTimeValue = $triggerConfig['wait_time_value'] ?? 30;
$waitTimeUnit = $triggerConfig['wait_time_unit'] ?? 'minutes';
$scheduleType = $triggerConfig['schedule_type'] ?? 'daily';
$scheduleHour = $triggerConfig['schedule_hour'] ?? 9;
$scheduleMinute = $triggerConfig['schedule_minute'] ?? 0;
$scheduleDayOfWeek = $triggerConfig['schedule_day_of_week'] ?? 1;

$scriptsPreload = <<<HTML
<script>
// Fallback para evitar ReferenceError caso scripts principais não carreguem
if (typeof window.validateAutomationConnections === 'undefined') {
    window.validateAutomationConnections = function() { return true; };
}
// Garantir que botões de teste não quebrem antes do script principal carregar
if (typeof window.__realTestAutomation === 'undefined') window.__realTestAutomation = null;
if (typeof window.__realAdvancedTestAutomation === 'undefined') window.__realAdvancedTestAutomation = null;
window.testAutomation = function() {
    if (typeof window.__realTestAutomation === 'function') return window.__realTestAutomation();
    console.warn('testAutomation ainda não carregou. Aguarde...');
};
window.advancedTestAutomation = function() {
    if (typeof window.__realAdvancedTestAutomation === 'function') return window.__realAdvancedTestAutomation();
    console.warn('advancedTestAutomation ainda não carregou. Aguarde...');
};
</script>
HTML;

$styles = <<<HTML
<style>
.automation-editor {
    position: relative;
    width: 100%;
    height: 600px;
    background: #f9f9f9;
    border: 1px solid #e4e6ef;
    border-radius: 0.475rem;
    overflow: hidden;
}

[data-bs-theme="dark"] .automation-editor {
    background: #1e1e2d !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}

.automation-canvas-toolbar {
    position: absolute;
    top: 15px;
    left: 15px;
    z-index: 20;
    display: flex;
    gap: 4px;
    align-items: center;
    background: rgba(255, 255, 255, 0.95);
    padding: 4px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

[data-bs-theme="dark"] .automation-canvas-toolbar {
    background: rgba(30, 30, 45, 0.95);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.automation-canvas-toolbar .btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.automation-canvas-toolbar #automation_zoom_label {
    min-width: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    text-align: center;
    background: transparent;
    border: none;
    color: #3f4254;
    padding: 0 8px;
    font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
    letter-spacing: 0.5px;
}

[data-bs-theme="dark"] .automation-canvas-toolbar #automation_zoom_label {
    color: #b5b5c3;
}

.automation-canvas-viewport {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    cursor: grab;
}

.automation-canvas-viewport.is-panning {
    cursor: grabbing;
}

.automation-canvas-content {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: 0 0;
    will-change: transform;
}

#kt_automation_canvas {
    position: relative;
    width: 2000px;
    height: 1200px;
    background-color: transparent;
    background-image: 
        linear-gradient(rgba(0, 0, 0, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 0, 0, 0.03) 1px, transparent 1px);
    background-size: 50px 50px;
    background-position: 0 0;
}

[data-bs-theme="dark"] #kt_automation_canvas {
    background-image: 
        linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
    background-size: 50px 50px;
}

.automation-node {
    background: white;
    color: #181c32;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    position: relative;
    z-index: 2;
}

.automation-node:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

.automation-node .node-action-btn {
    position: relative;
    z-index: 150;
    pointer-events: all;
}

.automation-node .chatbot-menu-options {
    position: relative;
    z-index: 50;
}

[data-bs-theme="dark"] .automation-node {
    background: #1e1e2d !important;
    color: #f1f1f2 !important;
}

[data-bs-theme="dark"] .automation-node .text-muted {
    color: #92929f !important;
}

[data-bs-theme="dark"] .automation-node .fw-bold {
    color: #f1f1f2 !important;
}

.automation-node .badge.badge-light-secondary {
    background-color: rgba(0, 0, 0, 0.08);
    color: #5e6278;
    font-size: 0.7rem;
    font-weight: 500;
}

[data-bs-theme="dark"] .automation-node .badge.badge-light-secondary {
    background-color: rgba(255, 255, 255, 0.1);
    color: #92929f;
}

.connections-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
    overflow: visible;
}

.connections-overlay path.connection-line {
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

.connection-arrow {
    transition: opacity 0.2s ease, fill 0.2s ease;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.15));
}

[data-bs-theme="dark"] .connections-overlay path.connection-line {
    filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.4));
}

[data-bs-theme="dark"] .connection-arrow {
    fill: #50cd89;
    filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.5));
}

.connections-overlay path,
.connections-overlay line {
    pointer-events: stroke;
    cursor: pointer;
}

.connections-overlay path.connection-line,
.connections-overlay line.connection-line {
    stroke: #009ef7;
    stroke-width: 2;
    pointer-events: stroke;
    cursor: default;
    transition: stroke-width 0.2s ease, opacity 0.2s ease;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.connections-overlay path.connection-line:hover,
.connections-overlay line.connection-line:hover {
    stroke-width: 3;
    opacity: 0.8;
}

.connection-delete-btn {
    opacity: 0.9;
    transition: opacity 0.2s ease;
}

.connection-delete-btn:hover {
    opacity: 1;
}

.connection-group:hover .connection-delete-btn {
    opacity: 1;
}

.connection-delete-btn circle {
    transition: fill 0.2s ease, stroke-width 0.2s ease;
}

[data-bs-theme="dark"] .connections-overlay path,
[data-bs-theme="dark"] .connections-overlay line {
    stroke: #50cd89;
}

.node-connection-handle {
    position: absolute;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #009ef7;
    border: 3px solid white;
    cursor: crosshair;
    z-index: 80;
    pointer-events: all;
    transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.node-connection-handle:hover {
    transform: scale(1.4);
    background: #50cd89;
    box-shadow: 0 3px 10px rgba(80, 205, 137, 0.4);
}

.automation-canvas-tip {
    position: absolute;
    bottom: 15px;
    left: 15px;
    z-index: 20;
    max-width: 360px;
}

.automation-canvas-tip .alert {
    padding: 12px 14px;
}

.node-connection-handle.output {
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
}

.node-connection-handle.output.chatbot-option-handle {
    bottom: auto;
    left: auto;
    transform: translateY(-50%);
    background: #009ef7 !important;
    width: 14px;
    height: 14px;
}

.node-connection-handle.output.chatbot-option-handle:hover {
    background: #50cd89 !important;
    transform: translateY(-50%) scale(1.5);
}

.node-connection-handle.input {
    top: -6px;
    left: 50%;
    transform: translateX(-50%);
}

.chatbot-menu-options {
    border-top: 1px solid #e4e6ef;
    padding-top: 8px;
    font-size: 11px;
    color: #7e8299;
}

.chatbot-option-row {
    transition: background-color 0.2s ease, border-left 0.2s ease;
    border-radius: 6px;
    padding: 6px 8px;
    margin: 3px 0;
    border-left: 3px solid transparent;
    position: relative;
}

.chatbot-option-row:hover {
    background-color: rgba(0, 158, 247, 0.1);
    border-left-color: #009ef7;
}

[data-bs-theme="dark"] .chatbot-option-row:hover {
    background-color: rgba(0, 158, 247, 0.15);
    border-left-color: #0dcaf0;
}

[data-bs-theme="dark"] .node-connection-handle {
    background: #50cd89;
    border-color: #1e1e2d;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

[data-bs-theme="dark"] .node-connection-handle:hover {
    background: #009ef7;
    box-shadow: 0 3px 12px rgba(0, 158, 247, 0.5);
}

.node-connection-handle.ai-intent-handle {
    background: #6366f1 !important;
    border: 3px solid white;
    width: 14px;
    height: 14px;
    box-shadow: 0 2px 6px rgba(99, 102, 241, 0.4);
    transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}

.node-connection-handle.ai-intent-handle:hover {
    background: #4f46e5 !important;
    transform: translateY(-50%) scale(1.5);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.6);
}

[data-bs-theme="dark"] .node-connection-handle.ai-intent-handle {
    border-color: #1e1e2d;
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.6);
}

.ai-intents-visual {
    border-top: 1px solid #e4e6ef;
    padding-top: 8px;
    font-size: 11px;
    color: #7e8299;
}

.ai-intent-label,
.chatbot-option-label {
    display: inline-block;
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
    font-weight: 500;
    color: #3f4254;
    line-height: 1.5;
}

[data-bs-theme="dark"] .ai-intent-label,
[data-bs-theme="dark"] .chatbot-option-label {
    color: #92929f;
}

.ai-intent-row {
    transition: background-color 0.2s ease, border-left 0.2s ease;
    border-radius: 6px;
    padding: 6px 8px;
    margin: 3px 0;
    border-left: 3px solid transparent;
    position: relative;
}

.ai-intent-row:hover {
    background-color: rgba(99, 102, 241, 0.08);
    border-left-color: #6366f1;
}

.ai-intent-row:hover .node-connection-handle.ai-intent-handle {
    transform: translateY(-50%) scale(1.3);
}

[data-bs-theme="dark"] .ai-intents-visual {
    border-top-color: rgba(255, 255, 255, 0.1);
}

[data-bs-theme="dark"] .ai-intent-row:hover {
    background-color: rgba(99, 102, 241, 0.15);
    border-left-color: #818cf8;
}

.connecting-line {
    stroke-dasharray: 5,5;
    opacity: 0.7;
    fill: none;
    stroke-linecap: round;
    animation: dash 0.5s linear infinite;
}

@keyframes dash {
    to {
        stroke-dashoffset: -10;
    }
}

/* Animação de "desenho" para linhas permanentes */
@keyframes drawLine {
    from {
        stroke-dashoffset: var(--path-length);
    }
    to {
        stroke-dashoffset: 0;
    }
}
</style>
HTML;

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold m-0"><?= htmlspecialchars($automation['name']) ?></h3>
                <?php if (!empty($automation['funnel_id']) || !empty($automation['stage_id'])): ?>
                    <div class="text-muted fs-7 mt-1">
                        <?php if (!empty($automation['funnel_id'])): ?>
                            <span class="badge badge-light-primary">Funil: <?= htmlspecialchars($automation['funnel_name'] ?? 'N/A') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($automation['stage_id'])): ?>
                            <span class="badge badge-light-info ms-2">Estágio: <?= htmlspecialchars($automation['stage_name'] ?? 'N/A') ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-toolbar d-flex gap-2">
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-light-info" onclick="testAutomation()">
                    <i class="ki-duotone ki-play fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Teste Rápido
                </button>
                <button type="button" class="btn btn-sm btn-light-info dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                    <span class="sr-only">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="testAutomation(); return false;">
                        <i class="ki-duotone ki-flash-circle fs-2 me-2 text-info">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Teste Rápido
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="advancedTestAutomation(); return false;">
                        <i class="ki-duotone ki-setting-2 fs-2 me-2 text-primary">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Teste Avançado
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="validateAutomationConnections(); return false;">
                        <i class="ki-duotone ki-check-circle fs-2 me-2 text-success">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Validar Automação
                    </a></li>
                </ul>
            </div>
            
            <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_edit_automation">
                <i class="ki-duotone ki-pencil fs-2"></i>
                Editar Configuração
            </button>
            <button type="button" class="btn btn-sm btn-light-primary" onclick="if(validateAutomationConnections()){saveLayout();}">
                <i class="ki-duotone ki-check fs-2"></i>
                Salvar Layout
            </button>
        </div>
    </div>
    <div class="card-body pt-0">
        <!--begin::Editor de Fluxo-->
        <div>
            <!--begin::Canvas Principal-->
                <div class="automation-editor">
                    <div class="automation-canvas-toolbar">
                        <button type="button" class="btn btn-light btn-sm" id="automation_zoom_out" title="Diminuir zoom (Ctrl + Scroll)">
                            <i class="ki-duotone ki-minus fs-3"></i>
                        </button>
                        <div id="automation_zoom_label">100%</div>
                        <button type="button" class="btn btn-light btn-sm" id="automation_zoom_in" title="Aumentar zoom (Ctrl + Scroll)">
                            <i class="ki-duotone ki-plus fs-3"></i>
                        </button>
                        <div style="width: 1px; height: 20px; background: rgba(0,0,0,0.1); margin: 0 2px;"></div>
                        <button type="button" class="btn btn-light btn-sm" id="automation_zoom_reset" title="Resetar zoom e posição">
                            <i class="ki-duotone ki-arrows-circle fs-3"></i>
                        </button>
                    </div>
                    <div class="automation-canvas-viewport" id="automation_canvas_viewport">
                        <div class="automation-canvas-content" id="automation_canvas_content">
                            <svg id="kt_connections_svg" class="connections-overlay"></svg>
                            <div id="kt_automation_canvas">
                                <!-- Nós serão adicionados aqui via JavaScript -->
                            </div>
                        </div>
                    </div>
                    <div class="automation-canvas-tip">
                <div class="alert alert-info d-flex align-items-center mb-0" style="font-size: 0.875rem;">
                    <i class="ki-duotone ki-information fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <strong>Dica:</strong> Arraste o fundo para mover o canvas. Use os botões ou Ctrl + scroll para dar zoom.
                        Arraste do ponto inferior de um nó para o superior de outro para conectar. Duplo clique em uma linha remove a conexão.
                    </div>
                </div>
            </div>
            </div>
            <!--end::Canvas Principal-->
            
            <!--begin::Paleta de Nós-->
            <div class="automation-palette position-absolute end-0 m-5" style="z-index: 100; top: 80px;">
                <div class="card shadow-lg">
                    <div class="card-header">
                        <h3 class="card-title">Componentes</h3>
                    </div>
                    <div class="card-body p-5">
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($nodeTypes as $type => $config): ?>
                                <button type="button" class="btn btn-light-primary d-flex align-items-center gap-3 p-3" 
                                        onclick="addNode('<?= $type ?>')"
                                        style="text-align: left;">
                                    <i class="ki-duotone <?= $config['icon'] ?> fs-2" style="color: <?= $config['color'] ?>;">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <span class="fw-semibold"><?= htmlspecialchars($config['label']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Paleta de Nós-->
        </div>
        <!--end::Editor de Fluxo-->
    </div>
</div>
<!--end::Card-->

<!--begin::Card - Logs de Execução-->
<div class="card mt-5" id="logs">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Logs de Execução</h3>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-sm btn-light-primary" onclick="refreshLogs()">
                <i class="ki-duotone ki-arrows-circle fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Atualizar
            </button>
        </div>
    </div>
    <div class="card-body pt-0">
        <div id="kt_automation_logs">
            <div class="text-center py-10">
                <span class="spinner-border spinner-border-sm text-primary"></span>
                <span class="ms-2">Carregando logs...</span>
            </div>
        </div>
    </div>
</div>
<!--end::Card - Logs de Execução-->

<!--begin::Modal - Configurar Nó-->
<div class="modal fade" id="kt_modal_node_config" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="kt_modal_node_config_title">Configurar Nó</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_node_config_form" class="form">
                <input type="hidden" id="kt_node_id" name="node_id" value="" />
                <input type="hidden" id="kt_node_type" name="node_type" value="" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7" id="kt_node_config_content">
                    <!-- Conteúdo dinâmico baseado no tipo de nó -->
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_node_config_submit" class="btn btn-primary" onclick="console.log('🖱️ BOTÃO SALVAR CLICADO!')">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Configurar Nó-->

<!--begin::Modal - Editar Automação-->
<div class="modal fade" id="kt_modal_edit_automation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Automação</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_automation_form" class="form" action="<?= \App\Helpers\Url::to('/automations/' . $automation['id']) ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" value="<?= htmlspecialchars($automation['name']) ?>" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3"><?= htmlspecialchars($automation['description'] ?? '') ?></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Gatilho</label>
                        <select name="trigger_type" id="kt_edit_trigger_type" class="form-select form-select-solid" required>
                            <option value="new_conversation" <?= $triggerType === 'new_conversation' ? 'selected' : '' ?>>Nova Conversa</option>
                            <option value="message_received" <?= $triggerType === 'message_received' ? 'selected' : '' ?>>Mensagem do Cliente (Instantâneo)</option>
                            <option value="agent_message_sent" <?= $triggerType === 'agent_message_sent' ? 'selected' : '' ?>>Mensagem do Agente (Instantâneo)</option>
                            <option value="conversation_updated" <?= $triggerType === 'conversation_updated' ? 'selected' : '' ?>>Conversa Atualizada</option>
                            <option value="conversation_moved" <?= $triggerType === 'conversation_moved' ? 'selected' : '' ?>>Conversa Movida no Funil</option>
                            <option value="conversation_resolved" <?= $triggerType === 'conversation_resolved' ? 'selected' : '' ?>>Conversa Resolvida</option>
                            <option value="no_customer_response" <?= $triggerType === 'no_customer_response' ? 'selected' : '' ?>>Tempo sem Resposta do Cliente</option>
                            <option value="no_agent_response" <?= $triggerType === 'no_agent_response' ? 'selected' : '' ?>>Tempo sem Resposta do Agente</option>
                            <option value="time_based" <?= $triggerType === 'time_based' ? 'selected' : '' ?>>Baseado em Tempo (Agendado)</option>
                            <option value="contact_created" <?= $triggerType === 'contact_created' ? 'selected' : '' ?>>Contato Criado</option>
                            <option value="contact_updated" <?= $triggerType === 'contact_updated' ? 'selected' : '' ?>>Contato Atualizado</option>
                            <option value="agent_activity" <?= $triggerType === 'agent_activity' ? 'selected' : '' ?>>Atividade do Agente</option>
                            <option value="webhook" <?= $triggerType === 'webhook' ? 'selected' : '' ?>>Webhook Externo</option>
                        </select>
                    </div>
                    <!-- Configuração de Tempo (para gatilhos de tempo sem resposta) -->
                    <div class="fv-row mb-7" id="kt_edit_time_config_container" style="display: <?= in_array($triggerType, ['no_customer_response', 'no_agent_response']) ? 'block' : 'none' ?>;">
                        <label class="required fw-semibold fs-6 mb-2">Tempo de Espera</label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="number" name="trigger_config[wait_time_value]" id="kt_edit_wait_time_value" class="form-control form-control-solid" placeholder="Quantidade" value="<?= (int)$waitTimeValue ?>" min="1" />
                            </div>
                            <div class="col-md-6">
                                <select name="trigger_config[wait_time_unit]" id="kt_edit_wait_time_unit" class="form-select form-select-solid">
                                    <option value="minutes" <?= $waitTimeUnit === 'minutes' ? 'selected' : '' ?>>Minutos</option>
                                    <option value="hours" <?= $waitTimeUnit === 'hours' ? 'selected' : '' ?>>Horas</option>
                                    <option value="days" <?= $waitTimeUnit === 'days' ? 'selected' : '' ?>>Dias</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-text mt-2" id="kt_edit_time_config_help">Executar automação após este tempo sem resposta</div>
                    </div>
                    <!-- Configuração de Agendamento (para time_based) -->
                    <div class="fv-row mb-7" id="kt_edit_schedule_config_container" style="display: <?= $triggerType === 'time_based' ? 'block' : 'none' ?>;">
                        <label class="required fw-semibold fs-6 mb-2">Tipo de Agendamento</label>
                        <select name="trigger_config[schedule_type]" id="kt_edit_schedule_type" class="form-select form-select-solid mb-3">
                            <option value="daily" <?= $scheduleType === 'daily' ? 'selected' : '' ?>>Diário</option>
                            <option value="weekly" <?= $scheduleType === 'weekly' ? 'selected' : '' ?>>Semanal</option>
                        </select>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Hora</label>
                                <input type="number" name="trigger_config[schedule_hour]" class="form-control form-control-solid" placeholder="Hora (0-23)" value="<?= (int)$scheduleHour ?>" min="0" max="23" />
                            </div>
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Minuto</label>
                                <input type="number" name="trigger_config[schedule_minute]" class="form-control form-control-solid" placeholder="Minuto (0-59)" value="<?= (int)$scheduleMinute ?>" min="0" max="59" />
                            </div>
                        </div>
                        <div id="kt_edit_schedule_day_container" style="display: <?= $scheduleType === 'weekly' ? 'block' : 'none' ?>;" class="mt-3">
                            <label class="fw-semibold fs-6 mb-2">Dia da Semana</label>
                            <select name="trigger_config[schedule_day_of_week]" class="form-select form-select-solid">
                                <option value="1" <?= $scheduleDayOfWeek == 1 ? 'selected' : '' ?>>Segunda-feira</option>
                                <option value="2" <?= $scheduleDayOfWeek == 2 ? 'selected' : '' ?>>Terça-feira</option>
                                <option value="3" <?= $scheduleDayOfWeek == 3 ? 'selected' : '' ?>>Quarta-feira</option>
                                <option value="4" <?= $scheduleDayOfWeek == 4 ? 'selected' : '' ?>>Quinta-feira</option>
                                <option value="5" <?= $scheduleDayOfWeek == 5 ? 'selected' : '' ?>>Sexta-feira</option>
                                <option value="6" <?= $scheduleDayOfWeek == 6 ? 'selected' : '' ?>>Sábado</option>
                                <option value="7" <?= $scheduleDayOfWeek == 7 ? 'selected' : '' ?>>Domingo</option>
                            </select>
                        </div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Vincular a Funil/Estágio</label>
                        <div class="text-muted fs-7 mb-3">Deixe vazio para aplicar a todos os funis/estágios</div>
                        <select name="funnel_id" id="kt_edit_funnel_select" class="form-select form-select-solid mb-3">
                            <option value="">Todos os Funis</option>
                            <?php foreach ($funnels as $funnel): ?>
                                <option value="<?= $funnel['id'] ?>" <?= ($automation['funnel_id'] ?? null) == $funnel['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($funnel['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="stage_id" id="kt_edit_stage_select" class="form-select form-select-solid" <?= empty($automation['funnel_id']) ? 'disabled' : '' ?>>
                            <option value="">Todos os Estágios</option>
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?= $stage['id'] ?>" <?= ($automation['stage_id'] ?? null) == $stage['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($stage['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="active" <?= ($automation['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativa</option>
                            <option value="inactive" <?= ($automation['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inativa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_automation_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Editar Automação-->

<!--begin::Modal - Variáveis Disponíveis-->
<div class="modal fade" id="kt_modal_variables" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Variáveis Disponíveis</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div class="alert alert-info mb-5">
                    <i class="ki-duotone ki-information fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <strong>Como usar:</strong> Digite as variáveis entre chaves duplas no formato <code>{{variavel}}</code>. 
                        Elas serão substituídas automaticamente quando a automação for executada.
                    </div>
                </div>
                
                <div class="row g-5">
                    <div class="col-md-6">
                        <h4 class="fw-bold mb-3">Variáveis de Contato</h4>
                        <div class="table-responsive">
                            <table class="table table-row-dashed fs-6">
                                <tbody>
                                    <tr>
                                        <td><code>{{contact.name}}</code></td>
                                        <td>Nome do contato</td>
                                    </tr>
                                    <tr>
                                        <td><code>{{contact.phone}}</code></td>
                                        <td>Telefone do contato</td>
                                    </tr>
                                    <tr>
                                        <td><code>{{contact.email}}</code></td>
                                        <td>Email do contato</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h4 class="fw-bold mb-3">Variáveis de Conversa</h4>
                        <div class="table-responsive">
                            <table class="table table-row-dashed fs-6">
                                <tbody>
                                    <tr>
                                        <td><code>{{conversation.id}}</code></td>
                                        <td>ID da conversa</td>
                                    </tr>
                                    <tr>
                                        <td><code>{{conversation.subject}}</code></td>
                                        <td>Assunto da conversa</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h4 class="fw-bold mb-3">Variáveis de Agente</h4>
                        <div class="table-responsive">
                            <table class="table table-row-dashed fs-6">
                                <tbody>
                                    <tr>
                                        <td><code>{{agent.name}}</code></td>
                                        <td>Nome do agente atribuído</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h4 class="fw-bold mb-3">Variáveis de Data/Hora</h4>
                        <div class="table-responsive">
                            <table class="table table-row-dashed fs-6">
                                <tbody>
                                    <tr>
                                        <td><code>{{date}}</code></td>
                                        <td>Data atual (dd/mm/yyyy)</td>
                                    </tr>
                                    <tr>
                                        <td><code>{{time}}</code></td>
                                        <td>Hora atual (HH:mm)</td>
                                    </tr>
                                    <tr>
                                        <td><code>{{datetime}}</code></td>
                                        <td>Data e hora (dd/mm/yyyy HH:mm)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="mt-5">
                    <h4 class="fw-bold mb-3">Exemplo de Uso</h4>
                    <div class="bg-light p-4 rounded">
                        <code class="d-block mb-2">Olá {{contact.name}}!</code>
                        <code class="d-block mb-2">Sua conversa #{{conversation.id}} foi atribuída ao agente {{agent.name}}.</code>
                        <code class="d-block">Data: {{date}} às {{time}}</code>
                    </div>
                    <div class="mt-3 text-muted fs-7">
                        Será renderizado como: <br>
                        <strong>Olá João Silva!<br>
                        Sua conversa #123 foi atribuída ao agente Maria Santos.<br>
                        Data: 27/01/2025 às 14:30</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal - Variáveis Disponíveis-->

<?php 
$content = ob_get_clean(); 

// Preparar variáveis para JavaScript
// Debug: verificar nós recebidos
\App\Helpers\Logger::automation("show.php - Automation ID: " . ($automation['id'] ?? 'N/A'));
\App\Helpers\Logger::automation("show.php - Nós recebidos: " . (isset($automation['nodes']) ? count($automation['nodes']) : 'NULL'));
if (!empty($automation['nodes'])) {
    \App\Helpers\Logger::automation("show.php - Primeiro nó: " . json_encode($automation['nodes'][0]));
} else {
    \App\Helpers\Logger::automation("show.php - Nenhum nó encontrado para automação ID: " . ($automation['id'] ?? 'N/A'));
}

// Garantir que os IDs dos nós sejam números no JSON
$nodesForJson = [];
if (!empty($automation['nodes']) && is_array($automation['nodes'])) {
    foreach ($automation['nodes'] as $node) {
        $node['id'] = isset($node['id']) ? (int)$node['id'] : null;
        $node['position_x'] = isset($node['position_x']) ? (int)$node['position_x'] : 0;
        $node['position_y'] = isset($node['position_y']) ? (int)$node['position_y'] : 0;
        $nodesForJson[] = $node;
    }
}
$nodesJson = json_encode($nodesForJson, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
\App\Helpers\Logger::automation("show.php - JSON gerado (primeiros 200 chars): " . substr($nodesJson, 0, 200));
\App\Helpers\Logger::automation("show.php - Total de nós no JSON: " . count($nodesForJson));
$nodeTypesJson = json_encode($nodeTypes, JSON_UNESCAPED_UNICODE);
// Calcular próximo ID para novos nós (usar o maior ID numérico + 1, ou começar em 1)
$nextNodeId = 1;
if (!empty($automation['nodes']) && is_array($automation['nodes'])) {
    $numericIds = array_filter(array_column($automation['nodes'], 'id'), function($id) {
        return is_numeric($id);
    });
    if (!empty($numericIds)) {
        $nextNodeId = max($numericIds) + 1;
    }
}
$triggerTypeJson = json_encode($triggerType);
$stageIdJson = json_encode($automation['stage_id'] ?? null);
$funnelsUrl = json_encode(\App\Helpers\Url::to('/funnels'));
$layoutUrl = json_encode(\App\Helpers\Url::to('/automations/' . $automation['id'] . '/layout'));
$logsUrl = json_encode(\App\Helpers\Url::to('/automations/' . $automation['id'] . '/logs'));

// Preparar opções HTML para contas de integração (novo sistema unificado)
// Priorizar integration_accounts, usar whatsapp_accounts apenas se não houver migração
$integrationAccountsOptions = [];
$usedPhoneNumbers = []; // Para evitar duplicatas

// Primeiro, adicionar contas de integração
if (!empty($integrationAccounts)) {
    foreach ($integrationAccounts as $acc) {
        $channel = $acc['channel'] ?? 'whatsapp';
        if (!isset($integrationAccountsOptions[$channel])) {
            $integrationAccountsOptions[$channel] = '';
        }
        $identifier = $acc['phone_number'] ?? $acc['username'] ?? $acc['account_id'] ?? 'N/A';
        $integrationAccountsOptions[$channel] .= '<option value="integration_' . htmlspecialchars($acc['id']) . '">' . htmlspecialchars($acc['name'] . ' (' . $identifier . ')') . '</option>';
        
        // Marcar número como usado para evitar duplicata
        if (!empty($acc['phone_number'])) {
            $usedPhoneNumbers[] = $acc['phone_number'];
        }
    }
}

// Depois, adicionar contas WhatsApp legacy apenas se não foram migradas
$whatsappOptions = '';
if (!empty($whatsappAccounts)) {
    foreach ($whatsappAccounts as $acc) {
        // Pular se já foi migrada para integration_accounts
        if (in_array($acc['phone_number'], $usedPhoneNumbers)) {
            continue;
        }
        
        $whatsappOptions .= '<option value="whatsapp_' . htmlspecialchars($acc['id']) . '">' . htmlspecialchars($acc['name'] . ' (' . $acc['phone_number'] . ') [Legacy]') . '</option>';
        
        // Adicionar também às opções de WhatsApp
        if (!isset($integrationAccountsOptions['whatsapp'])) {
            $integrationAccountsOptions['whatsapp'] = '';
        }
        $integrationAccountsOptions['whatsapp'] .= '<option value="whatsapp_' . htmlspecialchars($acc['id']) . '">' . htmlspecialchars($acc['name'] . ' (' . $acc['phone_number'] . ') [Legacy]') . '</option>';
    }
}

$stageOptions = '<option value="">Qualquer estágio</option>';
if (!empty($funnels)) {
    foreach ($funnels as $funnel) {
        $stages = \App\Models\Funnel::getStages($funnel['id']);
        foreach ($stages as $stage) {
            $stageOptions .= '<option value="' . htmlspecialchars($stage['id']) . '">' . htmlspecialchars($funnel['name'] . ' - ' . $stage['name']) . '</option>';
        }
    }
}

$agentOptions = '<option value="">Selecione um agente</option>';
if (!empty($agents)) {
    foreach ($agents as $agent) {
        $agentOptions .= '<option value="' . htmlspecialchars($agent['id']) . '">' . htmlspecialchars($agent['name']) . '</option>';
    }
}

$funnelOptions = '<option value="">Selecione um funil</option>';
if (!empty($funnels)) {
    foreach ($funnels as $funnel) {
        $funnelOptions .= '<option value="' . htmlspecialchars($funnel['id']) . '">' . htmlspecialchars($funnel['name']) . '</option>';
    }
}

$departments = \App\Models\Department::all();
$departmentOptions = '<option value="">Selecione um setor</option>';
if (!empty($departments)) {
    foreach ($departments as $dept) {
        $departmentOptions .= '<option value="' . htmlspecialchars($dept['id']) . '">' . htmlspecialchars($dept['name']) . '</option>';
    }
}

$aiAgents = \App\Models\AIAgent::getAvailableAgents();
$aiAgentOptions = '<option value="">Automático (primeiro disponível)</option>';
if (!empty($aiAgents)) {
    foreach ($aiAgents as $aiAgent) {
        $aiAgentOptions .= '<option value="' . htmlspecialchars($aiAgent['id']) . '">' . htmlspecialchars($aiAgent['name'] . ' (' . $aiAgent['agent_type'] . ')') . '</option>';
    }
}

ob_start();
?>
<script>
// Usar var para evitar erro de redeclaração caso o script seja injetado mais de uma vez
var nodes = <?= $nodesJson ?>;
// Garantir que nodes seja acessível globalmente
window.nodes = nodes;
// Usar var para evitar erro de redeclaração se outros scripts também definirem nodeTypes
var nodeTypes = <?= $nodeTypesJson ?>;
let nextNodeId = <?= $nextNodeId ?>;
let selectedNode = null;
let canvas = null;
let canvasViewport = null;
let canvasContent = null;
let connectionsSvg = null;
let connectingFrom = null;
let connectingLine = null;
let updateLineHandler = null;
let handleMouseUpGlobal = null;
let handleKeyDownGlobal = null;
let canvasScale = 1;
let canvasTranslate = { x: 0, y: 0 };
const MIN_CANVAS_SCALE = 0.5;
const MAX_CANVAS_SCALE = 1.5;
let isPanning = false;
let panStart = { x: 0, y: 0 };
let panInitialTranslate = { x: 0, y: 0 };
let zoomLabel = null;

const automationTriggerType = <?= $triggerTypeJson ?>;
const automationStageId = <?= $stageIdJson ?>;
const funnelsBaseUrl = <?= $funnelsUrl ?>;
const layoutSaveUrl = <?= $layoutUrl ?>;
const logsEndpoint = <?= $logsUrl ?>;
const integrationAccountsByChannel = <?= json_encode($integrationAccountsOptions ?? [], JSON_UNESCAPED_UNICODE) ?>;

// Função para atualizar opções de contas baseado no canal selecionado
function updateAccountOptions(channel, preserveSelection = false) {
    const select = document.getElementById('kt_trigger_integration_account');
    if (!select) return;
    
    // Preservar seleções atuais se necessário
    let currentSelections = [];
    if (preserveSelection && $(select).data('select2')) {
        currentSelections = $(select).val() || [];
    }
    
    // Destruir Select2 antes de modificar as opções
    if ($(select).data('select2')) {
        $(select).select2('destroy');
    }
    
    let options = '';
    
    // 🔥 Para instagram_comment, usar contas de instagram
    let effectiveChannel = channel;
    if (channel === 'instagram_comment') {
        effectiveChannel = 'instagram';
    }
    
    // Adicionar contas de integração do canal selecionado (já inclui legacy sem duplicatas)
    if (effectiveChannel && integrationAccountsByChannel[effectiveChannel]) {
        options += integrationAccountsByChannel[effectiveChannel];
    } else if (!channel) {
        // Se nenhum canal selecionado, mostrar todas as contas
        Object.keys(integrationAccountsByChannel).forEach(ch => {
            options += integrationAccountsByChannel[ch];
        });
    }
    
    select.innerHTML = options;
    
    // Reinicializar Select2
    initAccountSelect2(select);
    
    // Restaurar seleções que ainda são válidas
    if (preserveSelection && currentSelections.length > 0) {
        const validSelections = currentSelections.filter(val => {
            return select.querySelector(`option[value="${val}"]`) !== null;
        });
        $(select).val(validSelections).trigger('change');
    }
}

// Função para inicializar Select2 no campo de contas
function initAccountSelect2(selectElement) {
    if (!selectElement) return;
    
    $(selectElement).select2({
        dropdownParent: $('#kt_modal_node_config'),
        placeholder: 'Selecione as contas (deixe vazio para todas)',
        allowClear: true,
        closeOnSelect: false,
        width: '100%'
    });
}
const stageOptionsHtml = <?= json_encode($stageOptions, JSON_UNESCAPED_UNICODE) ?>;
const agentOptionsHtml = <?= json_encode($agentOptions, JSON_UNESCAPED_UNICODE) ?>;
const funnelOptionsHtml = <?= json_encode($funnelOptions, JSON_UNESCAPED_UNICODE) ?>;
const departmentOptionsHtml = <?= json_encode($departmentOptions, JSON_UNESCAPED_UNICODE) ?>;
const aiAgentOptionsHtml = <?= json_encode($aiAgentOptions, JSON_UNESCAPED_UNICODE) ?>;
const whatsappOptionsHtml = <?= json_encode($whatsappOptions, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener("DOMContentLoaded", function() {
    canvas = document.getElementById("kt_automation_canvas");
    canvasViewport = document.getElementById("automation_canvas_viewport");
    canvasContent = document.getElementById("automation_canvas_content");
    connectionsSvg = document.getElementById("kt_connections_svg");
    zoomLabel = document.getElementById("automation_zoom_label");
    
    // Debug: verificar nós carregados
    console.log('DOMContentLoaded - Nós carregados:', nodes);
    console.log('DOMContentLoaded - Total de nós:', nodes.length);
    console.log('DOMContentLoaded - Tipo de nodes:', typeof nodes, Array.isArray(nodes));
    
    applyCanvasTransform();
    updateSvgSize();
    
    // Limpar canvas antes de renderizar
    if (canvas) {
        canvas.innerHTML = '';
    }
    
    // Renderizar nós existentes
    renderNodes();
    
    // Renderizar conexões existentes
    renderConnections();
    
    // Configurar interações do canvas
    setupCanvasInteractions();
    
    // Configurar drag de tipos de nós do painel lateral
    setupNodeTypeDrag();
    
    // Permitir arrastar nós
    makeNodesDraggable();
    
    // Atualizar SVG quando canvas mudar de tamanho
    const resizeObserver = new ResizeObserver(() => {
        updateSvgSize();
        renderConnections();
    });
    resizeObserver.observe(canvas);
});

function applyCanvasTransform() {
    if (!canvasContent) return;
    const matrix = `matrix(${canvasScale}, 0, 0, ${canvasScale}, ${canvasTranslate.x}, ${canvasTranslate.y})`;
    canvasContent.style.transform = matrix;
    if (zoomLabel) {
        zoomLabel.textContent = Math.round(canvasScale * 100) + '%';
    }
    
    // Atualizar o tamanho do grid para se adaptar ao zoom
    // Dividir por canvasScale para manter o grid visualmente do mesmo tamanho
    if (canvas) {
        const gridSize = 50 / canvasScale;
        canvas.style.backgroundSize = `${gridSize}px ${gridSize}px`;
    }
}

function setCanvasScale(newScale, focalX, focalY) {
    newScale = Math.max(MIN_CANVAS_SCALE, Math.min(MAX_CANVAS_SCALE, newScale));
    if (newScale === canvasScale || !canvasViewport) return;
    
    const prevScale = canvasScale;
    const viewportRect = canvasViewport.getBoundingClientRect();
    focalX = focalX ?? viewportRect.width / 2;
    focalY = focalY ?? viewportRect.height / 2;
    
    canvasTranslate.x = focalX - ((focalX - canvasTranslate.x) * (newScale / prevScale));
    canvasTranslate.y = focalY - ((focalY - canvasTranslate.y) * (newScale / prevScale));
    
    canvasScale = newScale;
    applyCanvasTransform();
    renderConnections();
}

function setupCanvasInteractions() {
    const zoomInBtn = document.getElementById("automation_zoom_in");
    const zoomOutBtn = document.getElementById("automation_zoom_out");
    const zoomResetBtn = document.getElementById("automation_zoom_reset");
    
    zoomInBtn?.addEventListener("click", () => setCanvasScale(canvasScale + 0.1));
    zoomOutBtn?.addEventListener("click", () => setCanvasScale(canvasScale - 0.1));
    zoomResetBtn?.addEventListener("click", () => {
        canvasScale = 1;
        canvasTranslate = { x: 0, y: 0 };
        applyCanvasTransform();
        renderConnections();
    });
    
    if (canvasViewport) {
        canvasViewport.addEventListener("wheel", (e) => {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                const delta = e.deltaY > 0 ? -0.1 : 0.1;
                setCanvasScale(canvasScale + delta, e.offsetX, e.offsetY);
            }
        }, { passive: false });
        
        const startPan = (e) => {
            if (e.button !== 0 || connectingFrom) return;
            if (e.target.closest(".automation-node") || e.target.classList.contains("node-connection-handle")) return;
            e.preventDefault();
            isPanning = true;
            panStart = { x: e.clientX, y: e.clientY };
            panInitialTranslate = { ...canvasTranslate };
            canvasViewport.classList.add("is-panning");
            document.body.style.userSelect = "none";
        };
        
        canvasViewport.addEventListener("mousedown", startPan);
        canvas.addEventListener("mousedown", (e) => {
            if (e.target === canvas) {
                startPan(e);
            }
        });
        
        document.addEventListener("mousemove", (e) => {
            if (!isPanning) return;
            const deltaX = e.clientX - panStart.x;
            const deltaY = e.clientY - panStart.y;
            canvasTranslate.x = panInitialTranslate.x + deltaX;
            canvasTranslate.y = panInitialTranslate.y + deltaY;
            applyCanvasTransform();
            renderConnections();
        });
        
        const stopPan = () => {
            if (!isPanning) return;
            isPanning = false;
            canvasViewport.classList.remove("is-panning");
            document.body.style.userSelect = "";
        };
        
        document.addEventListener("mouseup", stopPan);
        document.addEventListener("mouseleave", stopPan);
    }
}

function addNode(nodeType, x, y) {
    const config = nodeTypes[nodeType];
    if (!config) return;
    
    // Se x e y não foram fornecidos, usar posição central do canvas
    if (x === undefined || y === undefined) {
        if (canvasViewport) {
            const rect = canvasViewport.getBoundingClientRect();
            x = (rect.width / 2 - canvasTranslate.x) / canvasScale;
            y = (rect.height / 2 - canvasTranslate.y) / canvasScale;
        } else {
            x = Math.random() * 400 + 50;
            y = Math.random() * 300 + 50;
        }
    }
    
    const nodeId = "node_" + nextNodeId++;
    const node = {
        id: nodeId,
        node_type: nodeType,
        node_data: {
            label: config.label,
            connections: []
        },
        position_x: x,
        position_y: y
    };
    
    nodes.push(node);
    // Atualizar referência global
    window.nodes = nodes;
    console.log('addNode - Nó adicionado:', node);
    console.log('addNode - Total de nós no array:', nodes.length);
    console.log('addNode - Array completo:', nodes);
    console.log('addNode - window.nodes atualizado:', window.nodes.length);
    
    renderNode(node);
    makeNodeDraggable(nodeId);
    openNodeConfig(nodeId);
}

function renderNodes() {
    nodes.forEach(function(node) {
        renderNode(node);
    });
}

function renderNode(node) {
    // Verificar se o nó já existe no DOM
    const existingElement = document.getElementById(String(node.id));
    if (existingElement) {
        console.warn('Nó já existe no DOM:', node.id);
        return; // Não renderizar novamente
    }
    
    console.log('🎨 renderNode chamado para:', node.id, 'Tipo:', node.node_type);
    if (node.node_type === 'action_assign_ai_agent') {
        console.log('  -> É AI Agent, verificando intents...');
        console.log('  -> ai_intents existe?', !!node.node_data.ai_intents);
        console.log('  -> É array?', Array.isArray(node.node_data.ai_intents));
        console.log('  -> Tamanho:', node.node_data.ai_intents?.length);
        console.log('  -> Conteúdo:', node.node_data.ai_intents);
    }
    
    const config = nodeTypes[node.node_type] || {};
    const nodeElement = document.createElement("div");
    nodeElement.id = String(node.id); // Garantir que o ID seja string para o DOM
    nodeElement.className = "automation-node";
    nodeElement.style.cssText = `
        position: absolute;
        left: ${node.position_x}px;
        top: ${node.position_y}px;
        width: 200px;
        padding: 15px;
        border: 2px solid ${config.color || "#009ef7"};
        border-radius: 8px;
        cursor: move;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    `;
    
    // Determinar se é chatbot com menu
    const isChatbotMenu = node.node_type === 'action_chatbot' && 
                          node.node_data && 
                          node.node_data.chatbot_type === 'menu' &&
                          node.node_data.chatbot_options &&
                          Array.isArray(node.node_data.chatbot_options);
    
    // HTML básico do nó
    let innerHtml = `
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-3">
            <i class="ki-duotone ${config.icon || "ki-gear"} fs-2" style="color: ${config.color || "#009ef7"};">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <span class="fw-bold">${config.label || node.node_type}</span>
            </div>
            <span class="badge badge-light-secondary fs-9 px-2 py-1" style="font-family: monospace; opacity: 0.7;">${String(node.id || '')}</span>
        </div>
        <div class="text-muted fs-7">${node.node_data.label || ""}</div>
        <div class="mt-3 d-flex gap-2" style="position: relative; z-index: 100;">
            <button type="button" class="btn btn-sm btn-light-primary node-action-btn" onclick="openNodeConfig('${String(node.id || '')}')">
                <i class="ki-duotone ki-pencil fs-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </button>
            <button type="button" class="btn btn-sm btn-light-danger node-action-btn" onclick="deleteNode('${String(node.id || '')}')">
                <i class="ki-duotone ki-trash fs-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
            </button>
        </div>
        <div class="node-connection-handle input" data-node-id="${String(node.id || '')}" data-handle-type="input"></div>
    `;
    
    // Se é chatbot menu, adicionar handles múltiplos
    if (isChatbotMenu) {
        const options = node.node_data.chatbot_options;
        innerHtml += '<div class="chatbot-menu-options" style="margin-top: 10px;">';
        options.forEach(function(opt, idx) {
            const optText = (typeof opt === 'object' ? opt.text : opt) || `Opção ${idx + 1}`;
            innerHtml += `
                <div class="chatbot-option-row">
                    <span class="chatbot-option-label" style="display: inline-block; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${optText}</span>
                    <div class="node-connection-handle output chatbot-option-handle" 
                         data-node-id="${String(node.id || '')}" 
                         data-handle-type="output" 
                         data-option-index="${idx}"
                         style="position: absolute; right: -11px; top: 50%; transform: translateY(-50%);">
                    </div>
                </div>
            `;
        });
        innerHtml += '</div>';
    } else if (node.node_type === 'action_assign_ai_agent' && 
               node.node_data.ai_intents && 
               Array.isArray(node.node_data.ai_intents) &&
               node.node_data.ai_intents.length > 0) {
        // Se é AI Agent e há intents configurados, renderizar handles
        const intents = node.node_data.ai_intents;
        console.log('🎯 Renderizando AI Agent com intents:', node.id, 'Total:', intents.length);
        console.log('   Intents:', intents);
        
        innerHtml += '<div class="ai-intents-visual" style="margin-top: 10px;">';
        intents.forEach(function(intent, idx) {
            const intentLabel = intent.description || intent.intent || `Intent ${idx + 1}`;
            console.log(`   -> Intent ${idx}: ${intentLabel}`);
            innerHtml += `
                <div class="ai-intent-row">
                    <span class="ai-intent-label" title="${intentLabel}">🎯 ${intentLabel}</span>
                    <div class="node-connection-handle output ai-intent-handle" 
                         data-node-id="${String(node.id || '')}" 
                         data-handle-type="output" 
                         data-intent-index="${idx}"
                         style="position: absolute; right: -11px; top: 50%; transform: translateY(-50%);">
                    </div>
                </div>
            `;
        });
        innerHtml += '</div>';
    } else if (node.node_type === 'condition') {
        // Nó de Condição: mostrar handles TRUE e FALSE
        innerHtml += '<div class="condition-outputs" style="margin-top: 10px;">';
        innerHtml += `
            <div class="condition-output-row" style="position: relative; display: flex; align-items: center; justify-content: flex-end; margin-bottom: 8px; padding-right: 5px;">
                <span class="condition-output-label" style="font-size: 12px; color: #50cd89; font-weight: 600;">✓ TRUE</span>
                <div class="node-connection-handle output condition-handle" 
                     data-node-id="${String(node.id || '')}" 
                     data-handle-type="output" 
                     data-connection-type="true"
                     style="position: absolute; right: -11px; top: 50%; transform: translateY(-50%); background: #50cd89 !important;">
                </div>
            </div>
            <div class="condition-output-row" style="position: relative; display: flex; align-items: center; justify-content: flex-end; padding-right: 5px;">
                <span class="condition-output-label" style="font-size: 12px; color: #f1416c; font-weight: 600;">✗ FALSE</span>
                <div class="node-connection-handle output condition-handle" 
                     data-node-id="${String(node.id || '')}" 
                     data-handle-type="output" 
                     data-connection-type="false"
                     style="position: absolute; right: -11px; top: 50%; transform: translateY(-50%); background: #f1416c !important;">
                </div>
            </div>
        `;
        innerHtml += '</div>';
    } else if (node.node_type === 'condition_business_hours') {
        // Nó de Horário de Atendimento: mostrar handles DENTRO e FORA
        const mode = node.node_data?.business_hours_mode || 'global';
        const modeLabel = mode === 'global' ? '⚙️ Configuração Global' : '✏️ Manual';
        innerHtml += `<div class="text-muted fs-8 mb-2" style="text-align: center;">${modeLabel}</div>`;
        innerHtml += '<div class="condition-outputs" style="margin-top: 5px;">';
        innerHtml += `
            <div class="condition-output-row" style="position: relative; display: flex; align-items: center; justify-content: flex-end; margin-bottom: 8px; padding-right: 5px;">
                <span class="condition-output-label" style="font-size: 12px; color: #50cd89; font-weight: 600;">☀️ Dentro do Horário</span>
                <div class="node-connection-handle output condition-handle" 
                     data-node-id="${String(node.id || '')}" 
                     data-handle-type="output" 
                     data-connection-type="within"
                     style="position: absolute; right: -11px; top: 50%; transform: translateY(-50%); background: #50cd89 !important;">
                </div>
            </div>
            <div class="condition-output-row" style="position: relative; display: flex; align-items: center; justify-content: flex-end; padding-right: 5px;">
                <span class="condition-output-label" style="font-size: 12px; color: #f1416c; font-weight: 600;">🌙 Fora do Horário</span>
                <div class="node-connection-handle output condition-handle" 
                     data-node-id="${String(node.id || '')}" 
                     data-handle-type="output" 
                     data-connection-type="outside"
                     style="position: absolute; right: -11px; top: 50%; transform: translateY(-50%); background: #f1416c !important;">
                </div>
            </div>
        `;
        innerHtml += '</div>';
    } else {
        // Handle de saída normal para outros tipos
        innerHtml += `<div class="node-connection-handle output" data-node-id="${String(node.id || '')}" data-handle-type="output"></div>`;
    }
    
    nodeElement.innerHTML = innerHtml;
    
    canvas.appendChild(nodeElement);
    
    // Adicionar eventos aos handles de conexão
    const outputHandles = nodeElement.querySelectorAll('.node-connection-handle.output');
    const inputHandle = nodeElement.querySelector('.node-connection-handle.input');
    
    outputHandles.forEach(function(outputHandle) {
        outputHandle.addEventListener('mousedown', function(e) {
            e.stopPropagation();
            // Suportar option-index (chatbot), intent-index (AI agent) e connection-type (condition)
            const optionIndex = outputHandle.getAttribute('data-option-index') || outputHandle.getAttribute('data-intent-index');
            const connectionType = outputHandle.getAttribute('data-connection-type'); // 'true' ou 'false' para condições
            startConnection(node.id, 'output', e, optionIndex, connectionType);
        });
    });
    
    inputHandle.addEventListener('mouseup', function(e) {
        e.stopPropagation();
        if (connectingFrom) {
            endConnection(node.id, 'input', e);
        }
    });
}

// Re-renderizar nó (remove e renderiza novamente)
function rerenderNode(node) {
    const existing = document.getElementById(String(node.id));
    if (existing) {
        existing.remove();
    }
    renderNode(node);
    renderConnections();
    makeNodeDraggable(String(node.id));
}

// Configurar drag de tipos de nós do painel lateral
function setupNodeTypeDrag() {
    const nodeTypeElements = document.querySelectorAll(".automation-node-type");
    
    nodeTypeElements.forEach(nodeType => {
        nodeType.addEventListener("dragstart", function(e) {
            const nodeTypeData = this.dataset.nodeType;
            const actionType = this.dataset.actionType || null;
            
            e.dataTransfer.setData("text/plain", JSON.stringify({
                nodeType: nodeTypeData,
                actionType: actionType
            }));
            e.dataTransfer.effectAllowed = "copy";
            
            this.classList.add("dragging");
        });
        
        nodeType.addEventListener("dragend", function() {
            this.classList.remove("dragging");
        });
    });
    
    // Permitir drop no canvas
    if (canvas) {
        canvas.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "copy";
        });
        
        canvas.addEventListener("drop", function(e) {
            e.preventDefault();
            
            try {
                const data = JSON.parse(e.dataTransfer.getData("text/plain"));
                const viewportRect = canvasViewport.getBoundingClientRect();
                
                // Calcular posição no canvas considerando zoom e pan
                const x = (e.clientX - viewportRect.left - canvasTranslate.x) / canvasScale;
                const y = (e.clientY - viewportRect.top - canvasTranslate.y) / canvasScale;
                
                // Criar novo nó usando a função addNode existente
                if (data.nodeType === "action" && data.actionType) {
                    addNode(data.actionType, x, y);
                } else {
                    addNode(data.nodeType, x, y);
                }
            } catch (error) {
                console.error("Erro ao criar nó:", error);
            }
        });
    }
}

function makeNodesDraggable() {
    nodes.forEach(node => {
        makeNodeDraggable(node.id);
    });
}

function makeNodeDraggable(nodeId) {
    const nodeElement = document.getElementById(nodeId);
    if (!nodeElement) return;
    
    let isDragging = false;
    let startX, startY, initialX, initialY;
    
    nodeElement.addEventListener("mousedown", function(e) {
        if (
            e.target.tagName === "BUTTON" ||
            e.target.classList.contains("node-connection-handle") ||
            e.target.closest(".node-connection-handle")
        ) {
            return;
        }
        if (isPanning) return;
        const node = nodes.find(n => n.id === nodeId);
        if (!node) return;
        e.preventDefault();
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        initialX = Number(node.position_x) || 0;
        initialY = Number(node.position_y) || 0;
        nodeElement.style.zIndex = "1000";
        nodeElement.classList.add("dragging");
        document.body.style.userSelect = "none";
    });
    
    document.addEventListener("mousemove", function(e) {
        if (!isDragging) return;
        const node = nodes.find(n => n.id === nodeId);
        if (node) {
            const deltaX = e.clientX - startX;
            const deltaY = e.clientY - startY;
            node.position_x = initialX + (deltaX / canvasScale);
            node.position_y = initialY + (deltaY / canvasScale);
            nodeElement.style.left = node.position_x + "px";
            nodeElement.style.top = node.position_y + "px";
            
            // Atualizar conexões quando nó é arrastado
            renderConnections();
        }
    });
    
    document.addEventListener("mouseup", function() {
        if (isDragging) {
            isDragging = false;
            nodeElement.style.zIndex = "";
            nodeElement.classList.remove("dragging");
            document.body.style.userSelect = "";
        }
    });
}

function openNodeConfig(nodeId) {
    const node = nodes.find(n => String(n.id) === String(nodeId));
    if (!node) return;
    
    // Guardar referência global para uso no submit (evitar sobrescrita do hidden)
    window.currentNodeIdForModal = nodeId;
    window.currentNodeRefForModal = node;
    
    console.log('=== openNodeConfig chamado ===');
    console.log('Node ID:', nodeId);
    console.log('Node Type:', node.node_type);
    console.log('Node Data completo:', JSON.parse(JSON.stringify(node.node_data)));
    
    if (node.node_type === 'action_assign_ai_agent') {
        console.log('AI Agent - Dados ao abrir:');
        console.log('  ai_branching_enabled:', node.node_data.ai_branching_enabled);
        console.log('  ai_intents:', node.node_data.ai_intents);
        console.log('  ai_max_interactions:', node.node_data.ai_max_interactions);
        console.log('  ai_fallback_node_id:', node.node_data.ai_fallback_node_id);
    }
    
    const config = nodeTypes[node.node_type] || {};
    document.getElementById("kt_modal_node_config_title").textContent = "Configurar: " + config.label;
    
    const nodeIdInput = document.getElementById("kt_node_id");
    console.log('📝 Definindo Node ID no campo hidden...');
    console.log('  Valor ANTES:', nodeIdInput.value);
    console.log('  Novo valor:', nodeId);
    nodeIdInput.value = nodeId;
    console.log('  Valor DEPOIS:', nodeIdInput.value);
    
    // Guard rails: manter o valor enquanto o modal estiver aberto
    if (window.nodeIdGuardInterval) clearInterval(window.nodeIdGuardInterval);
    window.nodeIdGuardInterval = setInterval(() => {
        if (nodeIdInput.value !== String(nodeId)) {
            console.warn('⚠️ Node ID alterado externamente para', nodeIdInput.value, '-> restaurando para', nodeId);
            nodeIdInput.value = nodeId;
        }
    }, 100);
    
    document.getElementById("kt_node_type").value = node.node_type;
    
    // Gerar conteúdo do formulário baseado no tipo
    let formContent = "";
    
    switch(node.node_type) {
        case "trigger":
            const triggerType = automationTriggerType;
            // Construir opções de canais
            const channelOptions = `
                        <option value="">Todos os Canais</option>
                        <option value="whatsapp">WhatsApp</option>
                <option value="instagram">Instagram</option>
                <option value="instagram_comment">Comentário Instagram</option>
                <option value="facebook">Facebook</option>
                <option value="telegram">Telegram</option>
                <option value="mercadolivre">Mercado Livre</option>
                <option value="webchat">WebChat</option>
                        <option value="email">Email</option>
                <option value="olx">OLX</option>
                <option value="linkedin">LinkedIn</option>
                <option value="google_business">Google Business</option>
                <option value="youtube">Youtube</option>
                <option value="tiktok">TikTok</option>
                        <option value="chat">Chat</option>
            `;
            
            // Construir opções de contas (legacy WhatsApp + novas integrações)
            // Com seleção múltipla, deixar vazio = todas as contas
            let accountOptions = '';
            accountOptions += whatsappOptionsHtml || '';
            
            // Adicionar contas de integração
            const integrationAccountsByChannel = <?= json_encode($integrationAccountsOptions ?? [], JSON_UNESCAPED_UNICODE) ?>;
            Object.keys(integrationAccountsByChannel).forEach(channel => {
                accountOptions += integrationAccountsByChannel[channel];
            });
            
            // Obter valores existentes do node
            const existingChannel = node.node_data.channel || '';
            
            // Suporte a múltiplas contas de integração (array ou valor único)
            let existingIntegrationAccountIds = node.node_data.integration_account_ids || [];
            const existingIntegrationAccountId = node.node_data.integration_account_id || '';
            let existingWhatsappAccountIds = node.node_data.whatsapp_account_ids || [];
            const existingWhatsappAccountId = node.node_data.whatsapp_account_id || '';
            
            // Normalizar arrays de IDs existentes para o formato do select (com prefixo)
            let selectedAccountValues = [];
            
            // Processar integration_account_ids
            if (Array.isArray(existingIntegrationAccountIds) && existingIntegrationAccountIds.length > 0) {
                existingIntegrationAccountIds.forEach(id => {
                    if (id) {
                        const strId = String(id);
                        if (!strId.includes('_')) {
                            selectedAccountValues.push('integration_' + strId);
                        } else {
                            selectedAccountValues.push(strId);
                        }
                    }
                });
            } else if (existingIntegrationAccountId) {
                selectedAccountValues.push('integration_' + existingIntegrationAccountId);
            }
            
            // Processar whatsapp_account_ids
            if (Array.isArray(existingWhatsappAccountIds) && existingWhatsappAccountIds.length > 0) {
                existingWhatsappAccountIds.forEach(id => {
                    if (id) {
                        const strId = String(id);
                        if (!strId.includes('_')) {
                            selectedAccountValues.push('whatsapp_' + strId);
                        } else {
                            selectedAccountValues.push(strId);
                        }
                    }
                });
            } else if (existingWhatsappAccountId && selectedAccountValues.length === 0) {
                // Só adicionar whatsapp_account_id legado se não tiver outras seleções
                selectedAccountValues.push('whatsapp_' + existingWhatsappAccountId);
            }
            
            // Marcar opções selecionadas
            let accountOptionsWithSelected = accountOptions;
            selectedAccountValues.forEach(selectedVal => {
                accountOptionsWithSelected = accountOptionsWithSelected.replace(
                    `value="${selectedVal}"`,
                    `value="${selectedVal}" selected`
                );
            });
            
            formContent = `
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Canal</label>
                    <select name="channel" id="kt_trigger_channel" class="form-select form-select-solid" onchange="updateAccountOptions(this.value)">
                        ${channelOptions.replace(`value="${existingChannel}"`, `value="${existingChannel}" selected`)}
                    </select>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Contas de Integração</label>
                    <select name="integration_account_ids[]" id="kt_trigger_integration_account" class="form-select form-select-solid" multiple data-control="select2" data-placeholder="Selecione as contas (deixe vazio para todas)" data-allow-clear="true" data-close-on-select="false">
                        ${accountOptionsWithSelected}
                    </select>
                    <div class="form-text">Selecione uma ou mais contas específicas, ou deixe vazio para aplicar a todas as contas do canal</div>
                </div>
            `;
            
            // Configurações específicas por tipo de trigger
            if (triggerType === "message_received") {
                formContent += `
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Palavra-chave (opcional)</label>
                        <input type="text" name="keyword" class="form-control form-control-solid" placeholder="Mensagem deve conter esta palavra" />
                    </div>
                `;
            } else if (triggerType === "conversation_updated") {
                formContent += `
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Campo que mudou</label>
                        <select name="field" class="form-select form-select-solid">
                            <option value="">Qualquer campo</option>
                            <option value="status">Status</option>
                            <option value="agent_id">Agente</option>
                            <option value="priority">Prioridade</option>
                            <option value="funnel_stage_id">Estágio</option>
                        </select>
                    </div>
                `;
            } else if (triggerType === "conversation_moved") {
                formContent += `
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Estágio de destino</label>
                        <select name="stage_id" class="form-select form-select-solid">
                        ${stageOptionsHtml}
                        </select>
                    </div>
                `;
            } else if (triggerType === "time_based") {
                formContent += `
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tipo de Agendamento</label>
                        <select name="schedule_type" class="form-select form-select-solid" required>
                            <option value="daily">Diário</option>
                            <option value="weekly">Semanal</option>
                            <option value="monthly">Mensal</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Hora</label>
                        <input type="time" name="time" class="form-control form-control-solid" value="09:00" required />
                    </div>
                    <div class="fv-row mb-7" id="schedule_day_container" style="display: none;">
                        <label class="fw-semibold fs-6 mb-2">Dia</label>
                        <select name="day" class="form-select form-select-solid">
                            <option value="1">Segunda-feira</option>
                            <option value="2">Terça-feira</option>
                            <option value="3">Quarta-feira</option>
                            <option value="4">Quinta-feira</option>
                            <option value="5">Sexta-feira</option>
                            <option value="6">Sábado</option>
                            <option value="7">Domingo</option>
                        </select>
                    </div>
                `;
            } else if (triggerType === "webhook") {
                formContent += `
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">URL do Webhook</label>
                        <input type="text" name="webhook_url" class="form-control form-control-solid" placeholder="URL específica (opcional)" />
                        <div class="form-text">Deixe vazio para aceitar qualquer webhook</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Headers (JSON)</label>
                        <textarea name="headers" class="form-control form-control-solid" rows="3" placeholder="{&quot;Authorization&quot;: &quot;Bearer token&quot;}"></textarea>
                        <div class="form-text">Headers que devem estar presentes no webhook (opcional)</div>
                    </div>
                `;
            } else if (triggerType === "no_customer_response") {
                formContent += `
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tempo sem Resposta</label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="number" name="wait_time_value" class="form-control form-control-solid" placeholder="Quantidade" value="30" min="1" required />
                            </div>
                            <div class="col-md-6">
                                <select name="wait_time_unit" class="form-select form-select-solid" required>
                                    <option value="minutes">Minutos</option>
                                    <option value="hours">Horas</option>
                                    <option value="days">Dias</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-text mt-2">A automação será executada se o cliente não responder dentro deste prazo</div>
                    </div>
                    <div class="fv-row mb-7">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="only_open_conversations" value="1" id="kt_only_open" checked />
                            <label class="form-check-label" for="kt_only_open">
                                Apenas conversas abertas
                            </label>
                        </div>
                        <div class="form-text">Marque para executar apenas em conversas com status "open" ou "pending"</div>
                    </div>
                    <div class="alert alert-info d-flex align-items-center p-5 mt-5">
                        <i class="ki-duotone ki-information-5 fs-2x text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-dark">Como Funciona</h4>
                            <span>Esta automação verifica periodicamente conversas onde a última mensagem foi enviada pelo agente e o cliente não respondeu no tempo especificado.</span>
                        </div>
                    </div>
                `;
            } else if (triggerType === "no_agent_response") {
                formContent += `
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tempo sem Resposta</label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="number" name="wait_time_value" class="form-control form-control-solid" placeholder="Quantidade" value="15" min="1" required />
                            </div>
                            <div class="col-md-6">
                                <select name="wait_time_unit" class="form-select form-select-solid" required>
                                    <option value="minutes" selected>Minutos</option>
                                    <option value="hours">Horas</option>
                                    <option value="days">Dias</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-text mt-2">A automação será executada se o agente não responder dentro deste prazo</div>
                    </div>
                    <div class="fv-row mb-7">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="only_assigned" value="1" id="kt_only_assigned" checked />
                            <label class="form-check-label" for="kt_only_assigned">
                                Apenas conversas atribuídas
                            </label>
                        </div>
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="only_open_conversations" value="1" id="kt_only_open_agent" checked />
                            <label class="form-check-label" for="kt_only_open_agent">
                                Apenas conversas abertas
                            </label>
                        </div>
                        <div class="form-text mt-2">Configure quais conversas devem ser verificadas</div>
                    </div>
                    <div class="alert alert-warning d-flex align-items-center p-5 mt-5">
                        <i class="ki-duotone ki-information-5 fs-2x text-warning me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-dark">Como Funciona</h4>
                            <span>Esta automação verifica periodicamente conversas onde a última mensagem foi enviada pelo cliente e o agente não respondeu no tempo especificado. Útil para notificar supervisores ou escalar conversas.</span>
                        </div>
                    </div>
                `;
            }
            break;
        case "action_send_message":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Mensagem</label>
                    <div class="d-flex flex-column gap-2">
                        <textarea name="message" id="kt_node_message_textarea" class="form-control form-control-solid" rows="5" placeholder="Digite a mensagem... Use variáveis como {{contact.name}}, {{date}}, etc." required></textarea>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="showVariablesModal()">
                                <i class="ki-duotone ki-information fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Ver Variáveis Disponíveis
                            </button>
                            <button type="button" class="btn btn-sm btn-light-info" onclick="previewMessageVariables()">
                                <i class="ki-duotone ki-eye fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Preview
                            </button>
                        </div>
                        <div id="kt_message_preview" class="mt-2 p-3 bg-light-info rounded d-none">
                            <div class="fw-bold mb-2">Preview da Mensagem:</div>
                            <div id="kt_message_preview_content" class="text-gray-800"></div>
                        </div>
                    </div>
                </div>
            `;
            break;
        case "action_assign_agent":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Agente</label>
                    <select name="agent_id" class="form-select form-select-solid" required>
                        <option value="">Selecione um agente</option>
                        ${agentOptionsHtml}
                    </select>
                    <div class="form-text">O agente será automaticamente atribuído à conversa</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Notificar Agente?</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="notify_agent" value="1" checked id="kt_notify_agent" />
                        <label class="form-check-label" for="kt_notify_agent">
                            Enviar notificação ao agente sobre a atribuição
                        </label>
                    </div>
                </div>
            `;
            break;
        case "action_assign_ai_agent":
            const aiAgentId = node.node_data.ai_agent_id || '';
            const processImmediately = node.node_data.process_immediately ?? false;
            const assumeConversation = node.node_data.assume_conversation ?? false;
            const onlyIfUnassigned = node.node_data.only_if_unassigned ?? false;
            
            formContent = `
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Agente de IA</label>
                    <select name="ai_agent_id" class="form-select form-select-solid">
                        ${aiAgentOptionsHtml}
                    </select>
                    <div class="form-text">Selecione um agente de IA específico ou deixe "Automático" para usar o primeiro disponível</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="process_immediately" class="form-check-input me-2" ${processImmediately ? 'checked' : ''} />
                        <span class="fw-semibold fs-6">Processar mensagens imediatamente</span>
                    </label>
                    <div class="form-text">Se habilitado, a IA processará a última mensagem do contato assim que for adicionada</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="assume_conversation" class="form-check-input me-2" ${assumeConversation ? 'checked' : ''} />
                        <span class="fw-semibold fs-6">Assumir conversa (remover agente humano)</span>
                    </label>
                    <div class="form-text">Se habilitado, remove o agente humano da conversa para a IA assumir completamente</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="only_if_unassigned" class="form-check-input me-2" ${onlyIfUnassigned ? 'checked' : ''} />
                        <span class="fw-semibold fs-6">Apenas se não tiver agente atribuído</span>
                    </label>
                    <div class="form-text">Se habilitado, só adiciona a IA se a conversa não tiver um agente humano atribuído</div>
                </div>
                
                <div class="separator my-7"></div>
                
                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="ai_branching_enabled" id="kt_ai_branching_enabled" class="form-check-input me-2" onchange="toggleAIBranchingContainer()" ${node.node_data.ai_branching_enabled ? 'checked' : ''} />
                        <span class="fw-semibold fs-6">Habilitar ramificação baseada em intent</span>
                    </label>
                    <div class="form-text">Permite que a IA roteie a conversa para diferentes nós baseado no entendimento da resposta</div>
                </div>
                
                <div id="ai_branching_container" style="display: ${node.node_data.ai_branching_enabled ? 'block' : 'none'};">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">
                            <i class="ki-duotone ki-route fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Intents / Condições de Ramificação
                        </label>
                        <div class="form-text mb-3">Configure os intents que a IA pode detectar e para qual nó cada um deve direcionar</div>
                        
                        <div id="ai_intents_list" class="mb-3">
                            <!-- Items serão adicionados dinamicamente via JavaScript -->
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-light-primary" onclick="addAIIntent()">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Adicionar Intent
                        </button>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Máximo de Interações</label>
                        <input type="number" name="ai_max_interactions" id="kt_ai_max_interactions" class="form-control form-control-solid" value="${node.node_data.ai_max_interactions || 5}" min="1" max="100" />
                        <div class="form-text">Número máximo de mensagens da IA antes de escalar para um agente humano</div>
                    </div>

                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="ai_intent_semantic_enabled" id="kt_ai_intent_semantic_enabled" class="form-check-input me-2" ${node.node_data.ai_intent_semantic_enabled !== false ? 'checked' : ''} />
                            <span class="fw-semibold fs-6">Usar interpretação IA (semântica) para intents</span>
                        </label>
                        <div class="form-text">Permite que a IA escolha o intent pela descrição (sem depender apenas de palavras-chave)</div>
                    </div>

                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Confiança mínima da interpretação IA</label>
                        <input type="number" step="0.05" name="ai_intent_confidence" id="kt_ai_intent_confidence" class="form-control form-control-solid" value="${node.node_data.ai_intent_confidence ?? 0.35}" min="0.1" max="1" />
                        <div class="form-text">Valores mais altos exigem maior certeza para escolher um intent. Padrão: 0.35</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="ai_escalate_on_stuck" id="kt_ai_escalate_on_stuck" class="form-check-input me-2" ${node.node_data.ai_escalate_on_stuck !== false ? 'checked' : ''} />
                            <span class="fw-semibold fs-6">Escalar automaticamente se ficar preso</span>
                        </label>
                        <div class="form-text">Se a IA não conseguir resolver após o máximo de interações, escalará para um agente humano</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Nó de Fallback (Opcional)</label>
                        <select name="ai_fallback_node_id" id="kt_ai_fallback_node_id" class="form-select form-select-solid">
                            <option value="">Nenhum (apenas escalar para humano)</option>
                        </select>
                        <div class="form-text">Nó a ser executado quando escalar para humano (ex: enviar mensagem de transição)</div>
                    </div>
                </div>
            `;
            
            // Preencher select de fallback node com nós disponíveis (após renderizar)
            setTimeout(() => {
                console.log('Timeout executado - populando fallback e intents');
                console.log('ai_fallback_node_id:', node.node_data.ai_fallback_node_id);
                console.log('ai_intents:', node.node_data.ai_intents);
                
                if (typeof populateAIFallbackNodes === 'function') {
                    populateAIFallbackNodes(node.node_data.ai_fallback_node_id);
                } else {
                    console.error('populateAIFallbackNodes não disponível');
                }
                
                if (typeof populateAIIntents === 'function') {
                    populateAIIntents(node.node_data.ai_intents || []);
                } else {
                    console.error('populateAIIntents não disponível');
                }
            }, 200); // Aumentado de 100 para 200ms
            
            break;
        case "action_assign_advanced":
            const assignType = node.node_data.assignment_type || 'auto';
            const distributionMethod = node.node_data.distribution_method || 'round_robin';
            const filterDepartmentId = node.node_data.filter_department_id || '';
            const considerAvailability = node.node_data.consider_availability ?? true;
            const considerMax = node.node_data.consider_max_conversations ?? true;
            const allowAI = node.node_data.allow_ai_agents ?? false;
            const ignoreContactAgent = node.node_data.ignore_contact_agent ?? false;
            const percentageAgentIds = node.node_data.percentage_agent_ids || [];
            const percentageValues = node.node_data.percentage_values || [];

            // Montar itens de porcentagem existentes
            let percentageItemsHtml = '';
            if (Array.isArray(percentageAgentIds) && Array.isArray(percentageValues) && percentageAgentIds.length) {
                percentageAgentIds.forEach((agId, idx) => {
                    const val = percentageValues[idx] ?? '';
                    percentageItemsHtml += `
                        <div class="d-flex gap-2 mb-2 percentage-rule-item">
                            <select name="percentage_agent_ids[]" class="form-select form-select-solid" style="flex: 1;">
                                <option value="">Selecione um agente</option>
                                ${agentOptionsHtml}
                            </select>
                            <input type="number" name="percentage_values[]" class="form-control form-control-solid" placeholder="%" min="1" max="100" style="width: 100px;" value="${val}"/>
                            <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removePercentageRule(this)">
                                <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span></i>
                            </button>
                        </div>
                    `;
                });
            } else {
                percentageItemsHtml = `
                    <div class="d-flex gap-2 mb-2 percentage-rule-item">
                        <select name="percentage_agent_ids[]" class="form-select form-select-solid" style="flex: 1;">
                            <option value="">Selecione um agente</option>
                            ${agentOptionsHtml}
                        </select>
                        <input type="number" name="percentage_values[]" class="form-control form-control-solid" placeholder="%" min="1" max="100" style="width: 100px;" />
                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removePercentageRule(this)">
                            <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span></i>
                        </button>
                    </div>
                `;
            }

            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Tipo de Atribuição</label>
                    <select name="assignment_type" id="kt_assignment_type" class="form-select form-select-solid" required onchange="updateAssignmentFields(this.value)">
                        <option value="auto" ${assignType === 'auto' ? 'selected' : ''}>Automática (Usar método do sistema)</option>
                        <option value="specific_agent" ${assignType === 'specific_agent' ? 'selected' : ''}>Agente Específico</option>
                        <option value="department" ${assignType === 'department' ? 'selected' : ''}>Setor Específico</option>
                        <option value="custom_method" ${assignType === 'custom_method' ? 'selected' : ''}>Método Personalizado</option>
                    </select>
                    <div class="form-text">Escolha como a conversa será atribuída</div>
                </div>

                <div class="fv-row mb-7">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="ignore_contact_agent" class="form-check-input me-2" ${ignoreContactAgent ? 'checked' : ''} />
                        <span class="fw-semibold fs-6">Ignorar agente do contato</span>
                    </label>
                    <div class="form-text text-warning">
                        <i class="ki-duotone ki-information-5 fs-6 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Se habilitado, ignora o agente principal vinculado ao contato e usa as regras desta automação
                    </div>
                </div>

                <!-- Container: Agente Específico -->
                <div id="specific_agent_container" style="display: none;">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Agente</label>
                        <select name="agent_id" class="form-select form-select-solid">
                            <option value="">Selecione um agente</option>
                            ${agentOptionsHtml}
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="force_assign" class="form-check-input me-2" />
                            <span class="fw-semibold fs-6">Forçar atribuição (ignorar limites)</span>
                        </label>
                        <div class="form-text">Se habilitado, ignora limite máximo e status de disponibilidade</div>
                    </div>
                </div>

                <!-- Container: Setor Específico -->
                <div id="department_container" style="display: none;">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Setor</label>
                        <select name="department_id" class="form-select form-select-solid">
                            <option value="">Selecione um setor</option>
                            ${departmentOptionsHtml}
                        </select>
                        <div class="form-text">Atribui a um agente disponível do setor selecionado</div>
                    </div>
                </div>

                <!-- Container: Método Personalizado -->
                <div id="custom_method_container" style="display: none;">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Método de Distribuição</label>
                        <select name="distribution_method" id="kt_distribution_method" class="form-select form-select-solid" onchange="updatePercentageFields(this.value)">
                            <option value="round_robin" ${distributionMethod === 'round_robin' ? 'selected' : ''}>Round-Robin (Distribuição igual)</option>
                            <option value="by_load" ${distributionMethod === 'by_load' ? 'selected' : ''}>Por Carga (Menor carga primeiro)</option>
                            <option value="by_performance" ${distributionMethod === 'by_performance' ? 'selected' : ''}>Por Performance</option>
                            <option value="by_specialty" ${distributionMethod === 'by_specialty' ? 'selected' : ''}>Por Especialidade</option>
                            <option value="percentage" ${distributionMethod === 'percentage' ? 'selected' : ''}>Por Porcentagem</option>
                        </select>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Filtrar por Setor</label>
                        <select name="filter_department_id" class="form-select form-select-solid">
                            <option value="">Todos os setores</option>
                            ${departmentOptionsHtml}
                        </select>
                        <div class="form-text">Limita candidatos a agentes de um setor específico</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="consider_availability" class="form-check-input me-2" ${considerAvailability ? 'checked' : ''} />
                            <span class="fw-semibold fs-6">Considerar status de disponibilidade</span>
                        </label>
                        <div class="form-text">Apenas agentes online/disponíveis</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="consider_max_conversations" class="form-check-input me-2" ${considerMax ? 'checked' : ''} />
                            <span class="fw-semibold fs-6">Considerar limite máximo</span>
                        </label>
                        <div class="form-text">Respeita limite máximo de conversas do agente</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="allow_ai_agents" class="form-check-input me-2" ${allowAI ? 'checked' : ''} />
                            <span class="fw-semibold fs-6">Permitir agentes de IA</span>
                        </label>
                        <div class="form-text">Inclui agentes de IA na seleção</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="force_reassign" class="form-check-input me-2" ${node.node_data.force_reassign ? 'checked' : ''} />
                            <span class="fw-semibold fs-6">Forçar reatribuição</span>
                        </label>
                        <div class="form-text">Reatribui mesmo se já houver um agente atribuído (ignora limites)</div>
                    </div>
                    
                    <!-- Container: Distribuição por Porcentagem -->
                    <div id="percentage_container" style="display: none;">
                        <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                            <i class="ki-duotone ki-information fs-2x text-info me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <h4 class="mb-1 text-dark">Distribuição por Porcentagem</h4>
                                <span>Defina a porcentagem de distribuição para cada agente. O total deve somar 100%.</span>
                            </div>
                        </div>
                        
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Regras de Distribuição</label>
                            <div id="percentage_rules_list">
                                ${percentageItemsHtml}
                            </div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" onclick="addPercentageRule()">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Adicionar Regra
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Fallback -->
                <div class="separator separator-dashed my-7"></div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Se não conseguir atribuir</label>
                    <select name="fallback_action" id="kt_fallback_action" class="form-select form-select-solid" onchange="updateFallbackFields(this.value)">
                        <option value="leave_unassigned">Deixar sem atribuição</option>
                        <option value="try_any_agent">Tentar qualquer agente disponível</option>
                        <option value="assign_to_ai">Atribuir a IA</option>
                        <option value="move_to_stage">Mover para estágio específico</option>
                    </select>
                </div>

                <div id="fallback_stage_container" style="display: none;">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Estágio de Fallback</label>
                        <select name="fallback_stage_id" class="form-select form-select-solid">
                            <option value="">Selecione um estágio</option>
                            ${stageOptionsHtml}
                        </select>
                    </div>
                </div>
            `;
            break;
        case "action_move_stage":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Funil</label>
                    <select name="funnel_id" id="kt_node_funnel_select" class="form-select form-select-solid" required>
                        <option value="">Selecione um funil</option>
                        ${funnelOptionsHtml}
                    </select>
                    <div class="form-text">Escolha o funil de destino</div>
                </div>
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Estágio</label>
                    <select name="stage_id" id="kt_node_stage_select" class="form-select form-select-solid" required disabled>
                        <option value="">Primeiro selecione um funil</option>
                    </select>
                    <div class="form-text">A conversa será movida para este estágio automaticamente</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Validar Regras?</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="validate_rules" value="1" checked id="kt_validate_rules" />
                        <label class="form-check-label" for="kt_validate_rules">
                            Verificar regras de validação do estágio (limites, etc)
                        </label>
                    </div>
                </div>
            `;
            break;
        case "condition":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Campo</label>
                    <select name="field" id="kt_condition_field" class="form-select form-select-solid" required onchange="updateConditionOperators(this.value)">
                        <option value="">Selecione um campo</option>
                        <optgroup label="Sistema">
                            <option value="business_hours">Horário de Atendimento</option>
                        </optgroup>
                        <optgroup label="Conversa">
                            <option value="channel">Canal</option>
                            <option value="status">Status</option>
                            <option value="priority">Prioridade</option>
                            <option value="unread_count">Mensagens não lidas</option>
                            <option value="created_days_ago">Dias desde criação</option>
                        </optgroup>
                        <optgroup label="Contato">
                            <option value="contact.name">Nome do Contato</option>
                            <option value="contact.phone">Telefone do Contato</option>
                            <option value="contact.email">Email do Contato</option>
                        </optgroup>
                        <optgroup label="Agente">
                            <option value="agent.id">ID do Agente</option>
                            <option value="agent.name">Nome do Agente</option>
                        </optgroup>
                        <optgroup label="Tags">
                            <option value="has_tag">Possui Tag</option>
                        </optgroup>
                    </select>
                    <div class="form-text">Campo que será avaliado na condição</div>
                </div>
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Operador</label>
                    <select name="operator" id="kt_condition_operator" class="form-select form-select-solid" required>
                        <option value="">Selecione um operador</option>
                        <option value="equals">Igual a (=)</option>
                        <option value="not_equals">Diferente de (≠)</option>
                        <option value="contains">Contém</option>
                        <option value="not_contains">Não contém</option>
                        <option value="starts_with">Começa com</option>
                        <option value="ends_with">Termina com</option>
                        <option value="greater_than">Maior que (>)</option>
                        <option value="less_than">Menor que (<)</option>
                        <option value="greater_or_equal">Maior ou igual (≥)</option>
                        <option value="less_or_equal">Menor ou igual (≤)</option>
                        <option value="is_empty">Está vazio</option>
                        <option value="is_not_empty">Não está vazio</option>
                        <option value="in">Está em (lista)</option>
                        <option value="not_in">Não está em (lista)</option>
                    </select>
                    <div class="form-text">Como o valor será comparado</div>
                </div>
                <div class="fv-row mb-7" id="kt_condition_value_container">
                    <label class="required fw-semibold fs-6 mb-2">Valor</label>
                    <input type="text" name="value" id="kt_condition_value" class="form-control form-control-solid" required placeholder="Digite o valor..." />
                    <div class="form-text">Valor para comparação. Para listas, separe por vírgula</div>
                </div>
                
                <div class="alert alert-primary d-flex align-items-center p-5 mb-7">
                    <i class="ki-duotone ki-shield-tick fs-2x text-primary me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Exemplo</h4>
                        <span>Campo: <strong>Canal</strong> | Operador: <strong>Igual a</strong> | Valor: <strong>whatsapp</strong></span>
                    </div>
                </div>
            `;
            break;
        case "action_chatbot":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Tipo de Chatbot</label>
                    <select name="chatbot_type" id="kt_chatbot_type" class="form-select form-select-solid" required onchange="updateChatbotFields(this.value)">
                        <option value="simple">Mensagem Simples</option>
                        <option value="menu">Menu com Opções</option>
                        <option value="conditional">Condicional (baseado em resposta)</option>
                    </select>
                    <div class="form-text">Escolha o tipo de interação do chatbot</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Mensagem Inicial</label>
                    <textarea name="chatbot_message" class="form-control form-control-solid" rows="3" placeholder="Olá {{contact.name}}! Como posso ajudar?" required></textarea>
                    <div class="form-text">Use variáveis como {{contact.name}}, {{agent.name}}, etc.</div>
                </div>
                
                <div id="kt_chatbot_options_container" style="display: none;">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Opções do Menu</label>
                        <div class="form-text mb-2">Para cada opção, informe palavras-chave (separadas por vírgula) que também disparam essa opção. (Conexões agora são feitas pelas bolinhas no diagrama.)</div>
                        <div id="kt_chatbot_options_list">
                            <div class="d-flex flex-column gap-2 mb-3 chatbot-option-item">
                                <div class="d-flex gap-2">
                                    <input type="text" name="chatbot_options[]" class="form-control form-control-solid" placeholder="Ex: 1 - Suporte Técnico" />
                                    <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeChatbotOption(this)">
                                        <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                </div>
                                <input type="text" name="chatbot_option_keywords[]" class="form-control form-control-solid" placeholder="Palavras-chave: 1, comercial, vendas" />
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light-primary mt-2" onclick="addChatbotOption()">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Adicionar Opção
                        </button>
                    </div>
                </div>
                
                <div id="kt_chatbot_conditional_container" style="display: none;">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Palavras-chave para Detectar</label>
                        <input type="text" name="chatbot_keywords" class="form-control form-control-solid" placeholder="suporte, ajuda, problema (separado por vírgula)" />
                        <div class="form-text">O chatbot responderá quando detectar estas palavras</div>
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                <h4 class="fw-bold mb-4">⏱️ Comportamento de Inatividade</h4>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Modo de Inatividade</label>
                    <select name="chatbot_inactivity_mode" id="kt_chatbot_inactivity_mode" class="form-select form-select-solid" onchange="toggleInactivityMode()">
                        <option value="timeout">Timeout Simples (ação única após tempo)</option>
                        <option value="reconnect">Tentativas de Reconexão (múltiplas mensagens antes da ação final)</option>
                    </select>
                    <div class="form-text">Escolha como o chatbot deve reagir quando o usuário não responde</div>
                </div>
                
                <!-- === MODO TIMEOUT SIMPLES === -->
                <div id="kt_timeout_simple_container">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tempo de Espera (segundos)</label>
                        <input type="number" name="chatbot_timeout" class="form-control form-control-solid" value="300" min="10" max="86400" />
                        <div class="form-text">Tempo máximo para aguardar resposta do usuário</div>
                    </div>
                </div>
                
                <!-- === MODO RECONEXÃO === -->
                <div id="kt_reconnect_container" style="display: none;">
                    <div class="alert alert-light-info d-flex align-items-center p-5 mb-7">
                        <i class="ki-duotone ki-information fs-2x text-info me-4">
                            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-dark">Como funciona a reconexão</h4>
                            <span>O chatbot envia mensagens de acompanhamento em intervalos configuráveis para tentar reengajar o usuário. Se o usuário responder a qualquer momento, o fluxo normal continua. Após todas as tentativas, a ação final é executada.</span>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tempo antes da 1ª tentativa (segundos)</label>
                        <input type="number" name="chatbot_reconnect_first_delay" class="form-control form-control-solid" value="120" min="10" max="86400" />
                        <div class="form-text">Tempo de inatividade antes de enviar a primeira mensagem de reconexão</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tentativas de Reconexão</label>
                        <div id="kt_reconnect_attempts_list">
                            <!-- Será preenchido dinamicamente -->
                        </div>
                        <button type="button" class="btn btn-sm btn-light-primary mt-2" onclick="addReconnectAttempt()">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Adicionar Tentativa
                        </button>
                        <div class="form-text mt-2">Cada tentativa envia uma mensagem diferente. Configure o intervalo entre cada tentativa.</div>
                    </div>
                </div>
                
                <!-- === AÇÃO FINAL (compartilhada) === -->
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Ação ao Timeout</label>
                    <select name="chatbot_timeout_action" id="kt_chatbot_timeout_action" class="form-select form-select-solid" onchange="toggleTimeoutNodeSelect()">
                        <option value="nothing">Nada</option>
                        <option value="assign_agent">Atribuir a um Agente</option>
                        <option value="send_message">Enviar Mensagem</option>
                        <option value="close">Encerrar Conversa</option>
                        <option value="go_to_node">Seguir para Nó Específico</option>
                    </select>
                    <div class="form-text" id="kt_timeout_action_hint">Ação executada quando o tempo de espera expirar</div>
                </div>
                
                <div class="fv-row mb-7" id="kt_chatbot_timeout_node_container" style="display: none;">
                    <label class="fw-semibold fs-6 mb-2">⏭️ Nó de Destino (Timeout)</label>
                    <select name="chatbot_timeout_node_id" id="kt_chatbot_timeout_node_id" class="form-select form-select-solid">
                        <option value="">Selecione um nó</option>
                        <!-- Será preenchido dinamicamente com os nós disponíveis -->
                    </select>
                    <div class="form-text">Nó a ser executado quando o tempo de espera expirar</div>
                </div>
                
                <div class="separator my-5"></div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">🔁 Máximo de Tentativas Inválidas</label>
                    <input type="number" name="chatbot_max_attempts" class="form-control form-control-solid" value="3" min="1" max="10" />
                    <div class="form-text">Número de vezes que o usuário pode responder com opção inválida antes de desistir</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">💬 Mensagem de Feedback (Resposta Inválida)</label>
                    <textarea name="chatbot_invalid_feedback" class="form-control form-control-solid" rows="2" placeholder="Opção inválida. Por favor, escolha uma das opções disponíveis.">Opção inválida. Por favor, escolha uma das opções disponíveis.</textarea>
                    <div class="form-text">Mensagem enviada quando o usuário responde algo que não está nas opções</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">⚠️ Nó de Fallback (Tentativas Excedidas)</label>
                    <select name="chatbot_fallback_node_id" id="kt_chatbot_fallback_node_id" class="form-select form-select-solid">
                        <option value="">Nenhum (enviar mensagem padrão)</option>
                        <!-- Será preenchido dinamicamente com os nós disponíveis -->
                    </select>
                    <div class="form-text">Nó a ser executado quando o usuário exceder o máximo de tentativas inválidas</div>
                </div>
                
                <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                    <i class="ki-duotone ki-information fs-2x text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Dica: Variáveis Disponíveis</h4>
                        <span>{{contact.name}}, {{contact.phone}}, {{agent.name}}, {{date}}, {{time}}</span>
                    </div>
                </div>
            `;
            break;
        case "action_create_conversation":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Canal</label>
                    <select name="channel" class="form-select form-select-solid" required>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="instagram">Instagram</option>
                        <option value="instagram_comment">Comentário Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="telegram">Telegram</option>
                        <option value="mercadolivre">Mercado Livre</option>
                        <option value="webchat">WebChat</option>
                        <option value="email">Email</option>
                        <option value="olx">OLX</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="google_business">Google Business</option>
                        <option value="youtube">Youtube</option>
                        <option value="tiktok">TikTok</option>
                        <option value="chat">Chat</option>
                    </select>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Assunto</label>
                    <input type="text" name="subject" class="form-control form-control-solid" placeholder="Assunto da conversa" />
                </div>
            `;
            break;
        case "action_set_tag":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Tag</label>
                    <select name="tag_id" id="kt_tag_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Selecione uma tag" required>
                        <option value="">Selecione uma tag...</option>
                    </select>
                    <div class="form-text">Selecione a tag que será adicionada/removida da conversa</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Ação</label>
                    <select name="tag_action" id="kt_tag_action" class="form-select form-select-solid">
                        <option value="add">Adicionar Tag</option>
                        <option value="remove">Remover Tag</option>
                    </select>
                    <div class="form-text">Adicionar ou remover a tag da conversa</div>
                </div>
                
                <div class="alert alert-info d-flex align-items-center p-5">
                    <i class="ki-duotone ki-tag fs-2x text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Tags</h4>
                        <span>Use tags para categorizar e filtrar conversas facilmente</span>
                    </div>
                </div>
            `;
            
            // Carregar tags do sistema via AJAX após o modal abrir
            setTimeout(() => {
                const tagSelect = document.getElementById('kt_tag_id');
                const tagActionSelect = document.getElementById('kt_tag_action');
                
                if (tagSelect) {
                    // Buscar tag_id salva (se editando nó existente)
                    const savedTagId = currentNodeRefForModal?.node_data?.tag_id || null;
                    const savedTagAction = currentNodeRefForModal?.node_data?.tag_action || 'add';
                    
                    console.log('🏷️ Carregando tags... Tag salva:', savedTagId, 'Ação:', savedTagAction);
                    
                    fetch('<?= \App\Helpers\Url::to('/tags/all') ?>', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.tags) {
                            console.log('✅ Tags carregadas:', data.tags.length);
                            
                            // Adicionar opções ao select
                            data.tags.forEach(tag => {
                                const option = document.createElement('option');
                                option.value = tag.id;
                                option.textContent = tag.name;
                                if (tag.color) {
                                    option.setAttribute('data-color', tag.color);
                                }
                                tagSelect.appendChild(option);
                            });
                            
                            // Inicializar select2
                            $(tagSelect).select2({
                                dropdownParent: $('#kt_modal_node'),
                                templateResult: function(tag) {
                                    if (!tag.element) return tag.text;
                                    const color = tag.element.getAttribute('data-color');
                                    if (color) {
                                        return $('<span><span class="badge" style="background-color: ' + color + '20; color: ' + color + '; border: 1px solid ' + color + ';">' + tag.text + '</span></span>');
                                    }
                                    return tag.text;
                                },
                                templateSelection: function(tag) {
                                    if (!tag.element) return tag.text;
                                    const color = tag.element.getAttribute('data-color');
                                    if (color) {
                                        return $('<span class="badge" style="background-color: ' + color + '20; color: ' + color + '; border: 1px solid ' + color + ';">' + tag.text + '</span>');
                                    }
                                    return tag.text;
                                }
                            });
                            
                            // Selecionar tag salva (se houver)
                            if (savedTagId) {
                                $(tagSelect).val(savedTagId).trigger('change');
                                console.log('🏷️ Tag salva selecionada:', savedTagId);
                            }
                            
                            // Selecionar ação salva
                            if (tagActionSelect && savedTagAction) {
                                tagActionSelect.value = savedTagAction;
                                console.log('🏷️ Ação salva selecionada:', savedTagAction);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar tags:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Não foi possível carregar as tags. Verifique sua conexão.'
                        });
                    });
                }
            }, 100);
            break;
        case "condition_business_hours":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Modo de Verificação</label>
                    <select id="kt_business_hours_mode" name="business_hours_mode" class="form-select form-select-solid" onchange="toggleBusinessHoursMode(this.value)">
                        <option value="global">Usar Configuração Global (Configurações do sistema)</option>
                        <option value="manual">Definir Horários Manualmente</option>
                    </select>
                    <div class="form-text mt-2">Escolha se deseja usar os horários de atendimento configurados nas Configurações ou definir horários personalizados para este nó.</div>
                </div>

                <div id="kt_business_hours_manual_container" style="display: none;">
                    <div class="separator separator-dashed my-5"></div>
                    <h4 class="fw-bold mb-4">Horários Personalizados</h4>
                    
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle gs-3 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Dia</th>
                                    <th class="text-center">Ativo</th>
                                    <th class="text-center">Início</th>
                                    <th class="text-center">Fim</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-semibold">Domingo</td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input bh-day-toggle" data-day="0" /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="0" data-field="start" value="08:00" disabled /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="0" data-field="end" value="18:00" disabled /></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Segunda-feira</td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input bh-day-toggle" data-day="1" checked /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="1" data-field="start" value="08:00" /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="1" data-field="end" value="18:00" /></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Terça-feira</td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input bh-day-toggle" data-day="2" checked /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="2" data-field="start" value="08:00" /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="2" data-field="end" value="18:00" /></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Quarta-feira</td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input bh-day-toggle" data-day="3" checked /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="3" data-field="start" value="08:00" /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="3" data-field="end" value="18:00" /></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Quinta-feira</td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input bh-day-toggle" data-day="4" checked /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="4" data-field="start" value="08:00" /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="4" data-field="end" value="18:00" /></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Sexta-feira</td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input bh-day-toggle" data-day="5" checked /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="5" data-field="start" value="08:00" /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="5" data-field="end" value="17:00" /></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Sábado</td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input bh-day-toggle" data-day="6" /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="6" data-field="start" value="08:00" disabled /></td>
                                    <td class="text-center"><input type="time" class="form-control form-control-sm form-control-solid bh-time-input" data-day="6" data-field="end" value="12:00" disabled /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="fv-row mb-7 mt-5">
                        <label class="fw-semibold fs-6 mb-2">Fuso Horário</label>
                        <select id="kt_business_hours_timezone" name="business_hours_timezone" class="form-select form-select-solid">
                            <option value="America/Sao_Paulo">América/São Paulo (BRT)</option>
                            <option value="America/Manaus">América/Manaus (AMT)</option>
                            <option value="America/Belem">América/Belém (BRT)</option>
                            <option value="America/Cuiaba">América/Cuiabá (AMT)</option>
                            <option value="America/Fortaleza">América/Fortaleza (BRT)</option>
                            <option value="America/Recife">América/Recife (BRT)</option>
                            <option value="America/Rio_Branco">América/Rio Branco (ACT)</option>
                        </select>
                    </div>

                    <div class="fv-row mb-3">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input type="checkbox" class="form-check-input" id="kt_business_hours_check_holidays" name="check_holidays" />
                            <label class="form-check-label fw-semibold" for="kt_business_hours_check_holidays">
                                Considerar feriados cadastrados como fora do horário
                            </label>
                        </div>
                    </div>
                </div>

                <div class="separator separator-dashed my-5"></div>
                <div class="alert alert-info d-flex align-items-center p-5 mb-0">
                    <i class="ki-duotone ki-information fs-2x text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Como funciona</h4>
                        <span>Este nó verifica se o momento atual está dentro ou fora do horário de atendimento e direciona o fluxo pela saída correspondente: <strong class="text-success">☀️ Dentro do Horário</strong> ou <strong class="text-danger">🌙 Fora do Horário</strong>.</span>
                    </div>
                </div>
            `;
            break;
        case "delay":
            formContent = `
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Tempo de Espera</label>
                    <div class="row">
                        <div class="col-md-6">
                            <input type="number" name="delay_value" class="form-control form-control-solid" placeholder="Quantidade" value="5" min="1" required />
                        </div>
                        <div class="col-md-6">
                            <select name="delay_unit" class="form-select form-select-solid" required>
                                <option value="seconds">Segundos</option>
                                <option value="minutes" selected>Minutos</option>
                                <option value="hours">Horas</option>
                                <option value="days">Dias</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text mt-2">A automação aguardará este tempo antes de continuar</div>
                </div>
                
                <div class="alert alert-warning d-flex align-items-center p-5 mb-7">
                    <i class="ki-duotone ki-information fs-2x text-warning me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Atenção</h4>
                        <span>Delays superiores a 60 segundos serão processados de forma assíncrona</span>
                    </div>
                </div>
            `;
            break;
        default:
            formContent = `<div class="text-muted">Nenhuma configuração necessária para este tipo de nó.</div>`;
    }
    
    document.getElementById("kt_node_config_content").innerHTML = formContent;
    
    // Verificar se o Node ID ainda está correto após innerHTML
    const nodeIdAfterInnerHTML = document.getElementById("kt_node_id").value;
    console.log('🔍 Node ID após innerHTML:', nodeIdAfterInnerHTML);
    if (String(nodeIdAfterInnerHTML) !== String(nodeId)) {
        console.error('❌ ALERTA: Node ID foi alterado de', nodeId, 'para', nodeIdAfterInnerHTML);
        document.getElementById("kt_node_id").value = nodeId; // Corrigir
        console.log('✅ Node ID corrigido de volta para:', nodeId);
    }
    
    // Popular select de nó fallback e timeout para chatbot
    if (node.node_type === 'action_chatbot') {
        const fallbackSelect = document.getElementById('kt_chatbot_fallback_node_id');
        const timeoutNodeSelect = document.getElementById('kt_chatbot_timeout_node_id');
        
        // Popular fallback select
        if (fallbackSelect) {
            // Limpar opções existentes (manter apenas a primeira)
            while (fallbackSelect.options.length > 1) {
                fallbackSelect.remove(1);
            }
            
            // Adicionar todos os nós disponíveis (exceto o atual)
            nodes.forEach(n => {
                if (String(n.id) !== String(nodeId)) {
                    const nodeConfig = nodeTypes[n.node_type] || {};
                    const nodeLabel = n.node_data?.label || nodeConfig.label || n.node_type;
                    const option = document.createElement('option');
                    option.value = String(n.id);
                    option.textContent = `${nodeLabel} (ID: ${n.id})`;
                    fallbackSelect.appendChild(option);
                }
            });
        }
        
        // Popular timeout node select
        if (timeoutNodeSelect) {
            // Limpar opções existentes (manter apenas a primeira)
            while (timeoutNodeSelect.options.length > 1) {
                timeoutNodeSelect.remove(1);
            }
            
            // Adicionar todos os nós disponíveis (exceto o atual)
            nodes.forEach(n => {
                if (String(n.id) !== String(nodeId)) {
                    const nodeConfig = nodeTypes[n.node_type] || {};
                    const nodeLabel = n.node_data?.label || nodeConfig.label || n.node_type;
                    const option = document.createElement('option');
                    option.value = String(n.id);
                    option.textContent = `${nodeLabel} (ID: ${n.id})`;
                    timeoutNodeSelect.appendChild(option);
                }
            });
        }
    }
    
    // Preencher valores existentes
    if (node.node_data) {
        Object.keys(node.node_data).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = node.node_data[key] || "";
            }
        });
        // Tratamento para Atribuição Avançada: garantir exibição dos blocos certos ao abrir
        if (node.node_type === 'action_assign_advanced') {
            const assignSelect = document.getElementById('kt_assignment_type');
            const methodSelect = document.getElementById('kt_distribution_method');
            if (assignSelect) {
                updateAssignmentFields(assignSelect.value || 'auto');
            }
            if (methodSelect) {
                updatePercentageFields(methodSelect.value || 'round_robin');
            }
        }
        
        // Tratamento especial para chatbot
        if (node.node_type === 'action_chatbot') {
            const chatbotType = node.node_data.chatbot_type || 'simple';
            
            // Mostrar/ocultar containers baseado no tipo
            updateChatbotFields(chatbotType);
            
            // Mostrar/ocultar campo de nó de timeout se necessário
            if (typeof toggleTimeoutNodeSelect === 'function') {
                toggleTimeoutNodeSelect();
            }
            
            // Restaurar modo de inatividade e tentativas de reconexão
            const inactivityMode = node.node_data.chatbot_inactivity_mode || 'timeout';
            const inactivitySelect = document.getElementById('kt_chatbot_inactivity_mode');
            if (inactivitySelect) {
                inactivitySelect.value = inactivityMode;
                toggleInactivityMode();
            }
            
            // Restaurar dados de reconexão
            if (inactivityMode === 'reconnect') {
                const firstDelay = node.node_data.chatbot_reconnect_first_delay;
                if (firstDelay) {
                    const firstDelayInput = document.querySelector('input[name="chatbot_reconnect_first_delay"]');
                    if (firstDelayInput) firstDelayInput.value = firstDelay;
                }
                
                if (node.node_data.chatbot_reconnect_attempts && Array.isArray(node.node_data.chatbot_reconnect_attempts)) {
                    loadReconnectAttempts(node.node_data.chatbot_reconnect_attempts);
                }
            }
            
            // Preencher opções do menu (se existirem)
            if (chatbotType === 'menu' && node.node_data.chatbot_options) {
                const optionsList = document.getElementById('kt_chatbot_options_list');
                if (optionsList) {
                    optionsList.innerHTML = ''; // Limpar opções padrão
                    
                    const options = node.node_data.chatbot_options;
                    if (Array.isArray(options)) {
                        options.forEach(function(opt) {
                            const optionItem = document.createElement('div');
                            optionItem.className = 'd-flex flex-column gap-2 mb-3 chatbot-option-item';
                            const optText = (typeof opt === 'object' ? opt.text : opt) || '';
                            const optKeywords = (typeof opt === 'object' && Array.isArray(opt.keywords)) ? opt.keywords.join(', ') : ((typeof opt === 'object' && opt.keywords) ? opt.keywords : '');
                            
                            optionItem.innerHTML = `
                                <div class="d-flex gap-2">
                                    <input type="text" name="chatbot_options[]" class="form-control form-control-solid" placeholder="Ex: 1 - Suporte" />
                                    <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeChatbotOption(this)">
                                        <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                </div>
                                <input type="text" name="chatbot_option_keywords[]" class="form-control form-control-solid" placeholder="Palavras-chave: 1, comercial, vendas" />
                            `;
                            optionsList.appendChild(optionItem);
                            
                            const textInput = optionItem.querySelector('input[name="chatbot_options[]"]');
                            if (textInput) {
                                textInput.value = optText;
                            }
                            
                            const keywordsInput = optionItem.querySelector('input[name="chatbot_option_keywords[]"]');
                            if (keywordsInput) {
                                keywordsInput.value = optKeywords;
                            }
                        });
                        
                        // Conexões são feitas pelos handles; sem selects de target
                    }
                }
            }
        }
        
        // Tratamento especial para condição: chamar updateConditionOperators
        if (node.node_type === 'condition' && node.node_data.field) {
            setTimeout(() => {
                updateConditionOperators(node.node_data.field);
                // Pré-selecionar operador após atualizar opções
                if (node.node_data.operator) {
                    const operatorSelect = document.getElementById('kt_condition_operator');
                    if (operatorSelect) {
                        operatorSelect.value = node.node_data.operator;
                    }
                }
            }, 50);
        }

        // Tratamento especial para Horário de Atendimento
        if (node.node_type === 'condition_business_hours') {
            setTimeout(() => {
                loadBusinessHoursNodeConfig(node.node_data || {});
            }, 50);
        }
    }
    
    // Carregar estágios quando funil for selecionado (action_move_stage)
    setTimeout(() => {
    const funnelSelect = document.getElementById("kt_node_funnel_select");
    const stageSelect = document.getElementById("kt_node_stage_select");
        
    if (funnelSelect && stageSelect) {
            console.log('🔄 Configurando listener para action_move_stage');
            console.log('   node.node_data.funnel_id:', node.node_data.funnel_id);
            console.log('   node.node_data.stage_id:', node.node_data.stage_id);
            
            // Função para carregar estágios
            const loadStages = (funnelId, selectedStageId = null) => {
                console.log('📋 loadStages chamado:', { funnelId, selectedStageId });
                
                if (!funnelId) {
                    stageSelect.innerHTML = '<option value="">Primeiro selecione um funil</option>';
                    stageSelect.disabled = true;
                    return;
                }
                
                stageSelect.innerHTML = '<option value="">Carregando...</option>';
                stageSelect.disabled = true;
                
                fetch(funnelsBaseUrl + "/" + funnelId + "/stages")
                    .then(response => {
                        if (!response.ok) {
                            throw new Error("Erro ao carregar estágios: " + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('✅ Estágios carregados:', data);
                        console.log('   Total de estágios recebidos:', data.stages?.length || 0);
                        
                        // Limpar completamente antes de adicionar
                        stageSelect.innerHTML = '';
                        
                        // Adicionar opção padrão
                        const defaultOption = document.createElement("option");
                        defaultOption.value = "";
                        defaultOption.textContent = "Selecione um estágio";
                        stageSelect.appendChild(defaultOption);
                        
                        if (data.success && data.stages && data.stages.length > 0) {
                            console.log('   Adicionando opções ao select...');
                            data.stages.forEach((stage, idx) => {
                                console.log(`   [${idx}] ID: ${stage.id}, Nome: ${stage.name}`);
                                const option = document.createElement("option");
                                option.value = stage.id;
                                option.textContent = stage.name;
                                stageSelect.appendChild(option);
                            });
                            
                            console.log('   Total de options no select:', stageSelect.options.length);
                            
                            stageSelect.disabled = false;
                            
                            // Pré-selecionar estágio se fornecido (sem setTimeout para evitar race condition)
                            if (selectedStageId) {
                                console.log('🎯 Pré-selecionando estágio:', selectedStageId);
                                const stageIdStr = String(selectedStageId);
                                stageSelect.value = stageIdStr;
                                
                                // Verificar se o estágio foi encontrado na lista
                                if (stageSelect.value !== stageIdStr) {
                                    console.warn('⚠️ Estágio ID', selectedStageId, 'não encontrado na lista de estágios do funil!');
                                    console.warn('   Opções disponíveis:', Array.from(stageSelect.options).map(o => ({value: o.value, text: o.textContent})));
                                    
                                    // Adicionar opção temporária para preservar o valor
                                    const tempOption = document.createElement("option");
                                    tempOption.value = stageIdStr;
                                    tempOption.textContent = `⚠️ Estágio ID ${stageIdStr} (não encontrado - reselecione)`;
                                    tempOption.style.color = '#e74c3c';
                                    stageSelect.appendChild(tempOption);
                                    stageSelect.value = stageIdStr;
                                }
                                console.log('   Valor selecionado:', stageSelect.value);
                            }
                        } else {
                            console.error("Nenhum estágio encontrado");
                            stageSelect.innerHTML = '<option value="">Nenhum estágio disponível</option>';
                        }
                    })
                    .catch(error => {
                        console.error("Erro ao carregar estágios:", error);
                        stageSelect.innerHTML = '<option value="">Erro ao carregar estágios</option>';
                        stageSelect.disabled = false;
                    });
            };
            
            // Adicionar listener para mudanças futuras
            funnelSelect.addEventListener("change", function() {
                loadStages(this.value);
            });
            
            // Pré-selecionar funil e carregar estágios se já configurado
            if (node.node_data.funnel_id) {
                console.log('🔧 Pré-configurando funil:', node.node_data.funnel_id);
                funnelSelect.value = node.node_data.funnel_id;
                loadStages(node.node_data.funnel_id, node.node_data.stage_id);
            }
        }
    }, 150); // Aumentado timeout para garantir que o DOM está pronto
    
    // Preencher campos do trigger após inserir HTML
    if (node.node_type === 'trigger' && node.node_data) {
        setTimeout(() => {
            const channelSelect = document.getElementById('kt_trigger_channel');
            const accountSelect = document.getElementById('kt_trigger_integration_account');
            
            // Inicializar Select2 primeiro
            if (accountSelect) {
                initAccountSelect2(accountSelect);
            }
            
            // Função helper para obter valores selecionados do node
            const getSelectedAccountValues = () => {
                let selectedValues = [];
                
                // Processar integration_account_ids
                if (node.node_data.integration_account_ids && Array.isArray(node.node_data.integration_account_ids)) {
                    node.node_data.integration_account_ids.forEach(id => {
                        if (id) {
                            const strId = String(id);
                            if (!strId.includes('_')) {
                                selectedValues.push('integration_' + strId);
                            } else {
                                selectedValues.push(strId);
                            }
                        }
                    });
                } else if (node.node_data.integration_account_id) {
                    selectedValues.push('integration_' + node.node_data.integration_account_id);
                }
                
                // Processar whatsapp_account_ids
                if (node.node_data.whatsapp_account_ids && Array.isArray(node.node_data.whatsapp_account_ids)) {
                    node.node_data.whatsapp_account_ids.forEach(id => {
                        if (id) {
                            const strId = String(id);
                            if (!strId.includes('_')) {
                                selectedValues.push('whatsapp_' + strId);
                            } else {
                                selectedValues.push(strId);
                            }
                        }
                    });
                } else if (node.node_data.whatsapp_account_id && selectedValues.length === 0) {
                    selectedValues.push('whatsapp_' + node.node_data.whatsapp_account_id);
                }
                
                return selectedValues;
            };
            
            if (channelSelect && node.node_data.channel) {
                channelSelect.value = node.node_data.channel;
                updateAccountOptions(node.node_data.channel, false);
                
                setTimeout(() => {
                    if (accountSelect) {
                        const selectedValues = getSelectedAccountValues();
                        if (selectedValues.length > 0) {
                            $(accountSelect).val(selectedValues).trigger('change');
                        }
                    }
                }, 300);
            } else if (accountSelect) {
                // Mesmo sem canal, preencher valores se existirem
                const selectedValues = getSelectedAccountValues();
                if (selectedValues.length > 0) {
                    $(accountSelect).val(selectedValues).trigger('change');
                }
            }
        }, 200);
    }
    
    console.log('✅ Abrindo modal de configuração...');
    const modalElement = document.getElementById("kt_modal_node_config");
    console.log('Modal element:', modalElement ? 'ENCONTRADO' : 'NÃO ENCONTRADO');
    
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
    
    console.log('✅ Modal aberto!');
    console.log('=== FIM openNodeConfig ===');
}

function deleteNode(nodeId) {
    if (!confirm("Tem certeza que deseja deletar este nó?")) return;
    
    console.log('deleteNode - Deletando nó:', nodeId, 'tipo:', typeof nodeId);
    console.log('deleteNode - Array antes:', nodes.length, nodes);
    console.log('deleteNode - IDs no array:', nodes.map(function(n) { return n.id + ' (' + typeof n.id + ')'; }));
    
    // Normalizar nodeId para comparação
    const nodeIdStr = String(nodeId);
    const nodeIdNum = isNaN(nodeId) ? nodeId : Number(nodeId);
    
    // Remover conexões relacionadas
    nodes.forEach(function(node) {
        if (node.node_data && node.node_data.connections) {
            node.node_data.connections = node.node_data.connections.filter(function(conn) {
                const targetId = conn.target_node_id;
                return targetId != nodeId && String(targetId) !== nodeIdStr && Number(targetId) !== nodeIdNum;
            });
        }
    });
    
    // Filtrar o nó (comparação fraca para pegar string e number)
    nodes = nodes.filter(function(n) {
        return n.id != nodeId && String(n.id) !== nodeIdStr && (isNaN(n.id) || Number(n.id) !== nodeIdNum);
    });
    
    // Atualizar referência global
    window.nodes = nodes;
    
    console.log('deleteNode - Array depois:', nodes.length, nodes);
    console.log('deleteNode - IDs restantes:', nodes.map(function(n) { return n.id; }));
    console.log('deleteNode - window.nodes atualizado:', window.nodes.length);
    
    const nodeElement = document.getElementById(String(nodeId));
    if (nodeElement) {
        nodeElement.remove();
    }
    
    renderConnections();
}

function updateSvgSize() {
    if (!connectionsSvg || !canvas) return;
    const width = canvas.offsetWidth;
    const height = canvas.offsetHeight;
    connectionsSvg.setAttribute('width', width);
    connectionsSvg.setAttribute('height', height);
}

function getNodeHandlePosition(nodeId, handleType, optionIndex, connectionType) {
    if (!canvasViewport) return null;
    
    const nodeElement = document.getElementById(String(nodeId));
    if (!nodeElement) return null;
    
    let handle;
    
    // Se for handle de condição (TRUE/FALSE)
    if (handleType === 'output' && connectionType) {
        handle = nodeElement.querySelector(`.node-connection-handle.${handleType}[data-connection-type="${connectionType}"]`);
    }
    // Se for handle de opção (chatbot ou AI intent)
    else if (handleType === 'output' && optionIndex !== undefined && optionIndex !== null) {
        // Tentar primeiro com data-option-index (chatbot), depois data-intent-index (AI agent)
        handle = nodeElement.querySelector(`.node-connection-handle.${handleType}[data-option-index="${optionIndex}"]`) ||
                 nodeElement.querySelector(`.node-connection-handle.${handleType}[data-intent-index="${optionIndex}"]`);
    } else {
        // Handle normal (primeiro encontrado)
        handle = nodeElement.querySelector(`.node-connection-handle.${handleType}`);
    }
    
    if (!handle) return null;
    
    const viewportRect = canvasViewport.getBoundingClientRect();
    const handleRect = handle.getBoundingClientRect();
    const centerX = handleRect.left + handleRect.width / 2;
    const centerY = handleRect.top + handleRect.height / 2;
    
    return {
        x: (centerX - viewportRect.left - canvasTranslate.x) / canvasScale,
        y: (centerY - viewportRect.top - canvasTranslate.y) / canvasScale
    };
}

function cancelConnection() {
    // Remover linha temporária
    if (connectingLine && connectingLine.parentNode) {
        connectingLine.parentNode.removeChild(connectingLine);
        connectingLine = null;
    }
    
    // Remover listeners
    if (updateLineHandler) {
        document.removeEventListener('mousemove', updateLineHandler);
        updateLineHandler = null;
    }
    
    // Remover todos os listeners de mouseup e keydown relacionados
    if (handleMouseUpGlobal) {
        document.removeEventListener('mouseup', handleMouseUpGlobal, true);
        handleMouseUpGlobal = null;
    }
    if (handleKeyDownGlobal) {
        document.removeEventListener('keydown', handleKeyDownGlobal);
        handleKeyDownGlobal = null;
    }
    
    // Limpar estado
    connectingFrom = null;
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
}

function startConnection(nodeId, handleType, e, optionIndex, connectionType) {
    e.stopPropagation();
    e.preventDefault();
    
    // Cancelar conexão anterior se houver
    cancelConnection();
    
    connectingFrom = { 
        nodeId: nodeId, 
        handleType: handleType,
        optionIndex: optionIndex !== undefined ? optionIndex : null,
        connectionType: connectionType || null // 'true' ou 'false' para condições
    };
    
    const pos = getNodeHandlePosition(nodeId, handleType, optionIndex, connectionType);
    if (!pos) {
        connectingFrom = null;
        return;
    }
    
    // Mudar cursor e desabilitar seleção de texto
    document.body.style.cursor = 'crosshair';
    document.body.style.userSelect = 'none';
    
    // Criar path temporário para curva Bézier
    connectingLine = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    connectingLine.setAttribute('d', `M ${pos.x} ${pos.y} L ${pos.x} ${pos.y}`);
    connectingLine.setAttribute('class', 'connecting-line');
    connectingLine.setAttribute('stroke', '#009ef7');
    connectingLine.setAttribute('stroke-width', '2');
    connectingLine.setAttribute('fill', 'none');
    connectionsSvg.appendChild(connectingLine);
    
    // Armazenar posição inicial
    connectingLine._startX = pos.x;
    connectingLine._startY = pos.y;
    
    // Atualizar linha ao mover mouse (com curva Bézier)
    updateLineHandler = (e) => {
        if (!canvasViewport) return;
        const viewportRect = canvasViewport.getBoundingClientRect();
        const x = (e.clientX - viewportRect.left - canvasTranslate.x) / canvasScale;
        const y = (e.clientY - viewportRect.top - canvasTranslate.y) / canvasScale;
        if (connectingLine) {
            const startX = connectingLine._startX;
            const startY = connectingLine._startY;
            
            // Calcular pontos de controle (mesmos parâmetros da linha permanente)
            const dx = x - startX;
            const dy = y - startY;
            
            // Offset horizontal: maior para distâncias maiores
            const offsetX = Math.max(80, Math.min(Math.abs(dx) * 0.6, 150));
            
            // Offset vertical adicional para curvas mais "orgânicas"
            const offsetY = Math.abs(dy) * 0.2;
            
            // Pontos de controle para curva suave (saindo horizontalmente)
            const cp1x = startX + offsetX;
            const cp1y = startY + offsetY;
            const cp2x = x - offsetX;
            const cp2y = y - offsetY;
            
            // Atualizar path com curva Bézier
            const pathData = `M ${startX} ${startY} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${x} ${y}`;
            connectingLine.setAttribute('d', pathData);
        }
    };
    
    // Handler para cancelar conexão ao soltar mouse
    handleMouseUpGlobal = (e) => {
        // Pequeno delay para permitir que endConnection seja processado primeiro
        setTimeout(() => {
            if (!connectingFrom) return; // Já foi processado
            
            const target = e.target;
            const isInputHandle = target.classList.contains('node-connection-handle') && 
                                 target.classList.contains('input');
            
            // Se não foi solto em um handle de entrada válido, cancelar
            if (!isInputHandle) {
                cancelConnection();
            }
        }, 10);
    };
    
    // Handler para ESC
    handleKeyDownGlobal = (e) => {
        if (e.key === 'Escape' && connectingFrom) {
            e.preventDefault();
            cancelConnection();
        }
    };
    
    document.addEventListener('mousemove', updateLineHandler);
    document.addEventListener('mouseup', handleMouseUpGlobal, { capture: true });
    document.addEventListener('keydown', handleKeyDownGlobal);
}

function endConnection(nodeId, handleType, e) {
    e.stopPropagation();
    e.preventDefault();
    
    if (!connectingFrom || connectingFrom.nodeId === nodeId) {
        cancelConnection();
        return;
    }
    
    const fromNode = nodes.find(n => n.id === connectingFrom.nodeId);
    const toNode = nodes.find(n => n.id === nodeId);
    
    if (!fromNode || !toNode) {
        cancelConnection();
        return;
    }
    
    // Adicionar conexão
    if (!fromNode.node_data.connections) {
        fromNode.node_data.connections = [];
    }
    
    // Verificar se conexão já existe (mesma origem, destino, opção e tipo de conexão)
    const exists = fromNode.node_data.connections.some(function(conn) {
        const optionMatch = conn.option_index === (connectingFrom.optionIndex !== null ? connectingFrom.optionIndex : undefined);
        const typeMatch = conn.connection_type === (connectingFrom.connectionType || undefined);
        return conn.target_node_id === nodeId && optionMatch && typeMatch;
    });
    
    if (!exists) {
        const newConnection = {
            target_node_id: nodeId,
            type: 'next'
        };
        
        // Adicionar option_index se existir (para chatbot/AI)
        if (connectingFrom.optionIndex !== null && connectingFrom.optionIndex !== undefined) {
            newConnection.option_index = parseInt(connectingFrom.optionIndex);
        }
        
        // Adicionar connection_type se existir (para condições: 'true' ou 'false')
        if (connectingFrom.connectionType) {
            newConnection.connection_type = connectingFrom.connectionType;
        }
        
        fromNode.node_data.connections.push(newConnection);
        
        console.log('Conexão criada:', newConnection);
        
        renderConnections();
    }
    
    // Limpar estado de conexão
    cancelConnection();
}

function renderConnections() {
    if (!connectionsSvg) return;
    
    // Limpar conexões existentes
    connectionsSvg.innerHTML = '';
    
    nodes.forEach(function(node) {
        if (!node.node_data.connections || !Array.isArray(node.node_data.connections)) return;
        
        node.node_data.connections.forEach(function(connection) {
            const optionIndex = connection.option_index !== undefined ? connection.option_index : null;
            const connectionType = connection.connection_type || null; // 'true' ou 'false' para condições
            const fromPos = getNodeHandlePosition(node.id, 'output', optionIndex, connectionType);
            const toPos = getNodeHandlePosition(connection.target_node_id, 'input');
            
            if (fromPos && toPos) {
                // Criar grupo para linha + botão de delete
                const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                group.setAttribute('class', 'connection-group');
                
                // Calcular pontos de controle para curva Bézier SUAVE
                const dx = toPos.x - fromPos.x;
                const dy = toPos.y - fromPos.y;
                
                // Distância entre os pontos
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                // Offset horizontal: maior para distâncias maiores
                // Mínimo de 80px, máximo de 150px, ou 60% da distância horizontal
                const offsetX = Math.max(80, Math.min(Math.abs(dx) * 0.6, 150));
                
                // Offset vertical adicional para curvas mais "orgânicas"
                const offsetY = Math.abs(dy) * 0.2;
                
                // Pontos de controle para curva suave (saindo horizontalmente)
                const cp1x = fromPos.x + offsetX;
                const cp1y = fromPos.y + offsetY;
                const cp2x = toPos.x - offsetX;
                const cp2y = toPos.y - offsetY;
                
                // Criar path com curva Bézier cúbica
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                const pathData = `M ${fromPos.x} ${fromPos.y} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${toPos.x} ${toPos.y}`;
                path.setAttribute('d', pathData);
                path.setAttribute('data-from', String(node.id || ''));
                path.setAttribute('data-to', String(connection.target_node_id || ''));
                path.setAttribute('class', 'connection-line');
                path.setAttribute('fill', 'none');
                
                // Cor da linha baseada no tipo de conexão
                let lineColor = '#009ef7'; // Cor padrão (azul)
                if (connectionType === 'true' || connectionType === 'within') {
                    lineColor = '#50cd89'; // Verde para TRUE / Dentro do Horário
                } else if (connectionType === 'false' || connectionType === 'outside') {
                    lineColor = '#f1416c'; // Vermelho para FALSE / Fora do Horário
                }
                path.setAttribute('stroke', lineColor);
                path.setAttribute('stroke-width', '2');
                
                // Remover animação após renderização inicial (opcional - descomente para animar)
                // const pathLength = path.getTotalLength();
                // path.style.strokeDasharray = pathLength;
                // path.style.strokeDashoffset = pathLength;
                // setTimeout(() => { path.style.transition = 'stroke-dashoffset 0.4s ease-out'; path.style.strokeDashoffset = '0'; }, 10);
                
                // Calcular ponto médio na curva (aproximação usando t=0.5)
                const tMid = 0.5;
                const midX = Math.pow(1-tMid, 3) * fromPos.x + 3 * Math.pow(1-tMid, 2) * tMid * cp1x + 3 * (1-tMid) * Math.pow(tMid, 2) * cp2x + Math.pow(tMid, 3) * toPos.x;
                const midY = Math.pow(1-tMid, 3) * fromPos.y + 3 * Math.pow(1-tMid, 2) * tMid * cp1y + 3 * (1-tMid) * Math.pow(tMid, 2) * cp2y + Math.pow(tMid, 3) * toPos.y;
                
                // Criar botão de delete (círculo + ícone)
                const deleteBtn = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                deleteBtn.setAttribute('class', 'connection-delete-btn');
                deleteBtn.setAttribute('transform', 'translate(' + midX + ',' + midY + ')');
                deleteBtn.style.cursor = 'pointer';
                deleteBtn.setAttribute('data-from', String(node.id || ''));
                deleteBtn.setAttribute('data-to', String(connection.target_node_id || ''));
                if (optionIndex !== null) deleteBtn.setAttribute('data-option-index', optionIndex);
                if (connectionType) deleteBtn.setAttribute('data-connection-type', connectionType);
                
                // Círculo de fundo
                const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('r', '10');
                circle.setAttribute('fill', '#f1416c');
                circle.setAttribute('stroke', '#ffffff');
                circle.setAttribute('stroke-width', '2');
                
                // Ícone X (duas linhas cruzadas)
                const xLine1 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                xLine1.setAttribute('x1', '-4');
                xLine1.setAttribute('y1', '-4');
                xLine1.setAttribute('x2', '4');
                xLine1.setAttribute('y2', '4');
                xLine1.setAttribute('stroke', '#ffffff');
                xLine1.setAttribute('stroke-width', '2');
                xLine1.setAttribute('stroke-linecap', 'round');
                
                const xLine2 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                xLine2.setAttribute('x1', '4');
                xLine2.setAttribute('y1', '-4');
                xLine2.setAttribute('x2', '-4');
                xLine2.setAttribute('y2', '4');
                xLine2.setAttribute('stroke', '#ffffff');
                xLine2.setAttribute('stroke-width', '2');
                xLine2.setAttribute('stroke-linecap', 'round');
                
                // Montar botão
                deleteBtn.appendChild(circle);
                deleteBtn.appendChild(xLine1);
                deleteBtn.appendChild(xLine2);
                
                // Evento de clique no botão de delete
                deleteBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const fromId = this.getAttribute('data-from');
                    const toId = this.getAttribute('data-to');
                    const optIdx = this.getAttribute('data-option-index');
                    const connType = this.getAttribute('data-connection-type');
                    if (confirm('Deseja remover esta conexão?')) {
                        removeConnection(fromId, toId, optIdx, connType);
                    }
                });
                
                // Hover no botão (só mudar cor, não tamanho para evitar "saltos")
                deleteBtn.addEventListener('mouseenter', function() {
                    circle.setAttribute('fill', '#d9214e');
                    circle.setAttribute('stroke-width', '3');
                });
                deleteBtn.addEventListener('mouseleave', function() {
                    circle.setAttribute('fill', '#f1416c');
                    circle.setAttribute('stroke-width', '2');
                });
                
                // Adicionar seta indicando direção do fluxo
                const arrowSize = 8;
                const arrowPos = 0.7; // 70% do caminho
                const arrowX = Math.pow(1-arrowPos, 3) * fromPos.x + 3 * Math.pow(1-arrowPos, 2) * arrowPos * cp1x + 3 * (1-arrowPos) * Math.pow(arrowPos, 2) * cp2x + Math.pow(arrowPos, 3) * toPos.x;
                const arrowY = Math.pow(1-arrowPos, 3) * fromPos.y + 3 * Math.pow(1-arrowPos, 2) * arrowPos * cp1y + 3 * (1-arrowPos) * Math.pow(arrowPos, 2) * cp2y + Math.pow(arrowPos, 3) * toPos.y;
                
                // Calcular ângulo da tangente (derivada da curva Bézier)
                const tArrow = arrowPos;
                const dxdt = 3 * Math.pow(1-tArrow, 2) * (cp1x - fromPos.x) + 6 * (1-tArrow) * tArrow * (cp2x - cp1x) + 3 * Math.pow(tArrow, 2) * (toPos.x - cp2x);
                const dydt = 3 * Math.pow(1-tArrow, 2) * (cp1y - fromPos.y) + 6 * (1-tArrow) * tArrow * (cp2y - cp1y) + 3 * Math.pow(tArrow, 2) * (toPos.y - cp2y);
                const angle = Math.atan2(dydt, dxdt) * 180 / Math.PI;
                
                // Criar seta (triângulo)
                const arrow = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                const arrowPoints = `0,0 ${-arrowSize},${-arrowSize/2} ${-arrowSize},${arrowSize/2}`;
                arrow.setAttribute('points', arrowPoints);
                arrow.setAttribute('fill', lineColor); // Mesma cor da linha
                arrow.setAttribute('transform', `translate(${arrowX},${arrowY}) rotate(${angle})`);
                arrow.setAttribute('class', 'connection-arrow');
                
                // Destacar linha ao passar mouse
                path.addEventListener('mouseenter', function() {
                    this.setAttribute('stroke-width', '3');
                    this.style.opacity = '0.8';
                    arrow.style.opacity = '0.8';
                });
                path.addEventListener('mouseleave', function() {
                    this.setAttribute('stroke-width', '2');
                    this.style.opacity = '1';
                    arrow.style.opacity = '1';
                });
                
                // Montar grupo e adicionar ao SVG
                group.appendChild(path);
                group.appendChild(arrow);
                group.appendChild(deleteBtn);
                connectionsSvg.appendChild(group);
            }
        });
    });
}

function removeConnection(fromNodeId, toNodeId, optionIndex, connectionType) {
    console.log('removeConnection chamado:', { fromNodeId, toNodeId, optionIndex, connectionType, type_from: typeof fromNodeId, type_to: typeof toNodeId });
    
    // Converter para string para garantir comparação consistente
    const fromIdStr = String(fromNodeId);
    const toIdStr = String(toNodeId);
    
    const node = nodes.find(n => String(n.id) === fromIdStr);
    console.log('Nó encontrado:', node);
    
    if (!node || !node.node_data.connections) {
        console.log('Nó não encontrado ou sem conexões');
        return;
    }
    
    const oldConnectionsCount = node.node_data.connections.length;
    node.node_data.connections = node.node_data.connections.filter(function(conn) {
        // Comparar target_node_id
        if (String(conn.target_node_id) !== toIdStr) return true;
        
        // Se tem optionIndex, comparar também
        if (optionIndex !== null && optionIndex !== undefined) {
            if (String(conn.option_index) !== String(optionIndex)) return true;
        }
        
        // Se tem connectionType (condition), comparar também
        if (connectionType) {
            if (conn.connection_type !== connectionType) return true;
        }
        
        // Se todos os critérios correspondem, remover esta conexão
        return false;
    });
    
    const newConnectionsCount = node.node_data.connections.length;
    console.log('Conexões removidas:', oldConnectionsCount - newConnectionsCount);
    console.log('Conexões restantes:', node.node_data.connections);
    
    // Atualizar visualmente
    renderConnections();
    
    // Salvar automaticamente no servidor
    if (oldConnectionsCount > newConnectionsCount) {
        console.log('Salvando alteração no servidor...');
        saveLayout();
    }
}

function saveLayout() {
    // Debug: verificar estado do array antes de salvar
    console.log('=== saveLayout CHAMADO ===');
    
    // SEMPRE usar window.nodes se disponível
    if (typeof window.nodes !== 'undefined' && Array.isArray(window.nodes)) {
        console.log('saveLayout - Usando window.nodes');
        nodes = window.nodes;
    } else {
        console.warn('saveLayout - window.nodes não disponível, usando variável local');
    }
    
    console.log('saveLayout - Array nodes antes de processar:', nodes);
    console.log('saveLayout - Total de nós no array:', nodes.length);
    console.log('saveLayout - Tipo de nodes:', typeof nodes, Array.isArray(nodes));
    console.log('saveLayout - window.nodes existe?', typeof window.nodes);
    console.log('saveLayout - window.nodes.length:', window.nodes ? window.nodes.length : 'N/A');
    console.log('saveLayout - IDs dos nós que serão enviados:', nodes.map(function(n) { return n.id; }));
    
    if (!Array.isArray(nodes)) {
        console.error('saveLayout - ERRO: nodes não é um array!', nodes);
        alert('Erro: Array de nós inválido. Por favor, recarregue a página.');
        return;
    }
    
    if (nodes.length === 0) {
        console.warn('saveLayout - AVISO: Array de nós está vazio!');
        if (!confirm('Nenhum nó foi adicionado. Deseja salvar mesmo assim?')) {
            return;
        }
    }
    
    // Converter nós para formato do backend
    const nodesData = nodes.map(function(node) {
        const nodeData = {
            node_type: node.node_type,
            node_data: node.node_data || {},
            position_x: parseInt(node.position_x) || 0,
            position_y: parseInt(node.position_y) || 0
        };
        
        // SEMPRE incluir o ID (mesmo que temporário) para mapeamento de conexões
        if (node.id) {
            nodeData.id = node.id;
            // Guardar o id no próprio node_data para uso em runtime (chatbot)
            nodeData.node_data.node_id = node.id;
        }
        
        // Debug: verificar conexões
        if (node.node_data && node.node_data.connections && node.node_data.connections.length > 0) {
            console.log('saveLayout - Nó ' + String(node.id || '') + ' tem ' + node.node_data.connections.length + ' conexões:', node.node_data.connections);
        }
        
        return nodeData;
    });
    
    // Debug: verificar o que está sendo enviado
    console.log('Salvando nós:', nodesData);
    
    fetch(layoutSaveUrl, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: JSON.stringify({ nodes: nodesData })
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                console.error('Erro HTTP:', text);
                throw new Error(`HTTP error! status: ${response.status}, body: ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Resposta do servidor:', data);
        if (data.success) {
            alert('Layout salvo com sucesso! Total de nós salvos: ' + (data.nodes_count || 0));
            // Aguardar um pouco antes de recarregar para garantir que o banco foi atualizado
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            alert('Erro: ' + (data.message || 'Erro ao salvar layout'));
        }
    })
    .catch(error => {
        console.error('Erro ao salvar layout:', error);
        alert('Erro ao salvar layout: ' + error.message);
    });
}

// Formulário de configuração do nó
document.addEventListener("DOMContentLoaded", function() {
    console.log('🔧 DOMContentLoaded - Configurando handler do formulário de nó');
    
    const nodeConfigForm = document.getElementById("kt_modal_node_config_form");
    console.log('🔧 Formulário encontrado:', nodeConfigForm ? 'SIM' : 'NÃO');
    
    // Adicionar listener para quando o modal for mostrado
    const modalElement = document.getElementById("kt_modal_node_config");
    if (modalElement) {
        modalElement.addEventListener('shown.bs.modal', function () {
            console.log('📋 MODAL MOSTRADO - Formulário pronto para interação');
            const form = document.getElementById("kt_modal_node_config_form");
            console.log('📋 Formulário no evento shown:', form ? 'ENCONTRADO' : 'NÃO ENCONTRADO');
            
            // DEBUG: Monitorar mudanças no campo node_id
            const nodeIdField = document.getElementById("kt_node_id");
            if (nodeIdField) {
                console.log('🔍 Node ID no modal shown:', nodeIdField.value);
                
                // Criar um observer para detectar mudanças
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                            console.log('⚠️ Node ID foi alterado via atributo para:', nodeIdField.value);
                            console.trace('Stack trace:');
                        }
                    });
                });
                observer.observe(nodeIdField, { attributes: true });
                
                // Também monitorar via propriedade
                let lastValue = nodeIdField.value;
                setInterval(function() {
                    if (nodeIdField.value !== lastValue) {
                        console.log('⚠️ Node ID foi alterado de', lastValue, 'para', nodeIdField.value);
                        console.trace('Stack trace:');
                        lastValue = nodeIdField.value;
                    }
                }, 100);
            }
        });
        modalElement.addEventListener('hidden.bs.modal', function () {
            if (window.nodeIdGuardInterval) {
                clearInterval(window.nodeIdGuardInterval);
                window.nodeIdGuardInterval = null;
            }
        });
    }
    
    if (nodeConfigForm) {
        console.log('🔧 Adicionando listener de submit ao formulário');
        nodeConfigForm.addEventListener("submit", function(e) {
            console.log('💾 ===== SUBMIT DO FORMULÁRIO =====');
            e.preventDefault();
            
            // Usar sempre o ID guardado ao abrir para evitar sobrescrita do hidden
            const nodeIdFromHidden = document.getElementById("kt_node_id").value;
            const nodeId = window.currentNodeIdForModal ?? nodeIdFromHidden;
            document.getElementById("kt_node_id").value = nodeId; // força o valor correto
            console.log('💾 Node ID (from hidden):', nodeIdFromHidden);
            console.log('💾 Node ID (current/global):', nodeId);
            
            const node = window.currentNodeRefForModal || nodes.find(n => String(n.id) === String(nodeId));
            console.log('💾 Node encontrado:', node ? `ID ${node.id} - ${node.node_type}` : 'NÃO');
            
            if (!node) {
                console.error('❌ Node não encontrado!');
                return;
            }
            
            const formData = new FormData(nodeConfigForm);

            // Forçar node_id e node_type corretos no FormData (ignora hidden alterado)
            formData.set('node_id', node.id);
            formData.set('node_type', node.node_type);
            
            // Incluir selects desabilitados no FormData (ex: stage_id durante loading)
            const disabledSelects = nodeConfigForm.querySelectorAll('select[name]:disabled');
            disabledSelects.forEach(sel => {
                if (!formData.has(sel.name)) {
                    formData.set(sel.name, sel.value || '');
                    console.log(`  ⚠️ Select desabilitado incluído no FormData: ${sel.name} = "${sel.value}"`);
                }
            });
            
            // DEBUG: Mostrar TODOS os campos do FormData
            console.log('📋 FormData completo:');
            for (let [key, value] of formData.entries()) {
                console.log(`  ${key}: ${value}`);
            }
            
            const nodeData = {};
            // Suporte a campos array (name="field[]") e checkboxes
            for (let [key, value] of formData.entries()) {
                if (key === "node_id" || key === "node_type") continue;
                
                // Arrays: campos com [] no nome
                if (key.endsWith("[]")) {
                    const baseKey = key.slice(0, -2);
                    if (!nodeData[baseKey]) nodeData[baseKey] = [];
                    nodeData[baseKey].push(value);
                } else {
                    nodeData[key] = value;
                }
            }
            
            // Processar integration_account_ids (array) do trigger
            if (node.node_type === "trigger") {
                const integrationAccountIds = nodeData.integration_account_ids || [];
                const whatsappAccountId = nodeData.whatsapp_account_id || '';
                
                console.log('🔍 TRIGGER - Valores originais:', {
                    integration_account_ids: integrationAccountIds,
                    whatsapp_account_id: whatsappAccountId
                });
                
                // Processar array de IDs selecionados
                if (Array.isArray(integrationAccountIds) && integrationAccountIds.length > 0) {
                    const processedIntegrationIds = [];
                    const processedWhatsappIds = [];
                    
                    integrationAccountIds.forEach(id => {
                        if (id && id.startsWith('integration_')) {
                            processedIntegrationIds.push(id.replace('integration_', ''));
                        } else if (id && id.startsWith('whatsapp_')) {
                            processedWhatsappIds.push(id.replace('whatsapp_', ''));
                        }
                    });
                    
                    // Salvar como array de IDs de integração
                    if (processedIntegrationIds.length > 0) {
                        nodeData.integration_account_ids = processedIntegrationIds;
                        // Manter compatibilidade: se só um ID, salvar também no campo singular
                        if (processedIntegrationIds.length === 1) {
                            nodeData.integration_account_id = processedIntegrationIds[0];
                        } else {
                            nodeData.integration_account_id = null;
                        }
                    } else {
                        nodeData.integration_account_ids = [];
                        nodeData.integration_account_id = null;
                    }
                    
                    // Processar WhatsApp IDs
                    if (processedWhatsappIds.length > 0) {
                        nodeData.whatsapp_account_ids = processedWhatsappIds;
                        // Manter compatibilidade
                        if (processedWhatsappIds.length === 1) {
                            nodeData.whatsapp_account_id = processedWhatsappIds[0];
                        } else {
                            nodeData.whatsapp_account_id = null;
                        }
                    } else {
                        nodeData.whatsapp_account_ids = [];
                        nodeData.whatsapp_account_id = null;
                    }
                    
                    console.log('🔍 TRIGGER - IDs processados:', {
                        integration_account_ids: nodeData.integration_account_ids,
                        whatsapp_account_ids: nodeData.whatsapp_account_ids
                    });
                } else if (whatsappAccountId) {
                    // Manter compatibilidade com whatsapp_account_id legacy
                    nodeData.whatsapp_account_id = whatsappAccountId;
                    nodeData.whatsapp_account_ids = [whatsappAccountId];
                    nodeData.integration_account_id = null;
                    nodeData.integration_account_ids = [];
                    console.log('🔍 TRIGGER - Usando whatsapp_account_id direto:', nodeData.whatsapp_account_id);
                } else {
                    // Se não selecionou nenhuma conta, limpar todos
                    nodeData.integration_account_id = null;
                    nodeData.integration_account_ids = [];
                    nodeData.whatsapp_account_id = null;
                    nodeData.whatsapp_account_ids = [];
                    console.log('🔍 TRIGGER - Nenhuma conta selecionada, aplicar a todas');
                }
                
                console.log('🔍 TRIGGER - Valores finais:', {
                    integration_account_ids: nodeData.integration_account_ids,
                    whatsapp_account_ids: nodeData.whatsapp_account_ids
                });
            }
            
            // Preservar stage_id e funnel_id se vazios no form mas existentes no node_data
            // Isso evita perda de dados quando o select está desabilitado (loading) ou estágio não carregou
            if (node.node_type === "action_move_stage") {
                console.log('🔀 action_move_stage - Dados coletados:');
                console.log('  funnel_id (form):', nodeData.funnel_id, '| (node_data):', node.node_data.funnel_id);
                console.log('  stage_id (form):', nodeData.stage_id, '| (node_data):', node.node_data.stage_id);
                console.log('  validate_rules:', nodeData.validate_rules);
                
                // Se stage_id veio vazio mas existia antes, preservar o valor anterior
                if ((!nodeData.stage_id || nodeData.stage_id === '') && node.node_data.stage_id) {
                    console.warn('⚠️ stage_id vazio no formulário mas existia no node_data:', node.node_data.stage_id, '— preservando valor anterior');
                    nodeData.stage_id = node.node_data.stage_id;
                }
                // Se funnel_id veio vazio mas existia antes, preservar o valor anterior
                if ((!nodeData.funnel_id || nodeData.funnel_id === '') && node.node_data.funnel_id) {
                    console.warn('⚠️ funnel_id vazio no formulário mas existia no node_data:', node.node_data.funnel_id, '— preservando valor anterior');
                    nodeData.funnel_id = node.node_data.funnel_id;
                }
                
                // Validação: se stage_id e funnel_id ainda estão vazios (nó novo sem seleção), avisar o usuário
                const finalStageId = nodeData.stage_id || node.node_data.stage_id;
                const finalFunnelId = nodeData.funnel_id || node.node_data.funnel_id;
                if (!finalStageId || finalStageId === '') {
                    console.warn('❌ Nenhum estágio selecionado para action_move_stage!');
                    alert('Por favor, selecione um estágio antes de salvar.');
                    return;
                }
                if (!finalFunnelId || finalFunnelId === '') {
                    console.warn('❌ Nenhum funil selecionado para action_move_stage!');
                    alert('Por favor, selecione um funil antes de salvar.');
                    return;
                }
            }
            // Checkboxes que não aparecem no FormData quando desmarcados
            const checkboxKeys = [
                'consider_availability',
                'consider_max_conversations',
                'allow_ai_agents',
                'force_assign',
                'force_reassign',
                'ignore_contact_agent'
            ];
            checkboxKeys.forEach(k => {
                if (!formData.has(k)) {
                    nodeData[k] = '0';
                }
            });
            // Tratamento específico para chatbot menu: coletar opções + targets
            if (node.node_type === "action_chatbot") {
                const chatbotType = nodeData.chatbot_type || 'simple';
                console.log('Salvando configuração do chatbot, tipo:', chatbotType);
                
                if (chatbotType === 'menu') {
                    const optionInputs = Array.from(document.querySelectorAll('input[name="chatbot_options[]"]'));
                    const keywordInputs = Array.from(document.querySelectorAll('input[name="chatbot_option_keywords[]"]'));
                    const combined = [];
                    
                    console.log('Inputs de opções encontrados:', optionInputs.length);
                    
                    optionInputs.forEach(function(inp, idx) {
                        const text = (inp.value || '').trim();
                        const keywordsRaw = keywordInputs[idx] ? keywordInputs[idx].value : '';
                        const keywords = keywordsRaw.split(',').map(function(k){ return k.trim(); }).filter(function(k){ return k.length > 0; });
                        console.log(`Opção ${idx}: text="${text}", keywords="${keywordsRaw}"`);
                        if (text) {
                            combined.push({ text: text, target_node_id: null, keywords: keywords });
                        }
                    });
                    
                    console.log('Opções combinadas:', combined);
                    nodeData.chatbot_options = combined;
                }
                
                // Coletar dados de reconexão
                const inactivityMode = nodeData.chatbot_inactivity_mode || 'timeout';
                nodeData.chatbot_inactivity_mode = inactivityMode;
                
                if (inactivityMode === 'reconnect') {
                    const messageInputs = Array.from(document.querySelectorAll('textarea[name="reconnect_attempt_message[]"]'));
                    const delayInputs = Array.from(document.querySelectorAll('input[name="reconnect_attempt_delay[]"]'));
                    const attempts = [];
                    
                    messageInputs.forEach(function(inp, idx) {
                        const message = (inp.value || '').trim();
                        const delay = parseInt(delayInputs[idx]?.value || '120');
                        if (message) {
                            attempts.push({ message: message, delay: delay });
                        }
                    });
                    
                    nodeData.chatbot_reconnect_attempts = attempts;
                    // O timeout total é calculado no backend (first_delay + soma dos delays das tentativas)
                    console.log('Tentativas de reconexão:', attempts);
                } else {
                    nodeData.chatbot_reconnect_attempts = [];
                }
            }
            
            // Tratamento específico para AI Agent: coletar intents
            if (node.node_type === "action_assign_ai_agent") {
                // Checkbox retorna 'on' quando marcado, ou undefined quando desmarcado
                let branchingEnabled = nodeData.ai_branching_enabled === 'on' || 
                                       nodeData.ai_branching_enabled === '1' || 
                                       nodeData.ai_branching_enabled === true;

                // Configurações de interpretação semântica
                nodeData.ai_intent_semantic_enabled = formData.has('ai_intent_semantic_enabled');
                nodeData.ai_intent_confidence = parseFloat(formData.get('ai_intent_confidence') || '0.35');
                
                console.log('Salvando configuração do AI Agent');
                console.log('  ai_branching_enabled raw:', nodeData.ai_branching_enabled);
                console.log('  branchingEnabled processado (inicial):', branchingEnabled);
                console.log('  ai_intent_semantic_enabled:', nodeData.ai_intent_semantic_enabled);
                console.log('  ai_intent_confidence:', nodeData.ai_intent_confidence);
                
                // Coletar intents sempre, mesmo que o checkbox não esteja marcado
                const intentInputs = document.querySelectorAll('.ai-intent-item');
                const intents = [];
                
                console.log('  Intent items encontrados:', intentInputs.length);
                
                intentInputs.forEach((item, idx) => {
                    const intentName = item.querySelector(`input[name="ai_intents[${idx}][intent]"]`)?.value?.trim();
                    const description = item.querySelector(`input[name="ai_intents[${idx}][description]"]`)?.value?.trim();
                    const keywordsRaw = item.querySelector(`input[name="ai_intents[${idx}][keywords]"]`)?.value || '';
                    const targetNodeId = item.querySelector(`select[name="ai_intents[${idx}][target_node_id]"]`)?.value;
                        const exitMessage = item.querySelector(`textarea[name="ai_intents[${idx}][exit_message]"]`)?.value?.trim();
                    
                    const keywords = keywordsRaw.split(',').map(k => k.trim()).filter(k => k.length > 0);
                    
                    console.log(`  Intent ${idx}:`);
                    console.log(`    - name: "${intentName}"`);
                    console.log(`    - desc: "${description}"`);
                    console.log(`    - keywords: [${keywords.join(', ')}]`);
                    console.log(`    - target: "${targetNodeId}"`);
                        console.log(`    - exit_message: "${exitMessage}"`);
                    
                    // Validar: nome e target obrigatórios
                    if (!intentName || !targetNodeId) {
                        console.warn(`  Intent ${idx} ignorado - faltando nome ou target`);
                        return; // pula para próximo
                    }
                    
                    intents.push({
                        intent: intentName,
                        description: description || intentName,
                        keywords: keywords,
                            target_node_id: targetNodeId,
                            exit_message: exitMessage || ''
                    });
                });
                
                // Se havia intents no formulário mas nenhum válido, bloquear salvamento
                if (intentInputs.length > 0 && intents.length === 0) {
                    console.error('Nenhum intent válido encontrado. Verifique nome e nó de destino.');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Configure os intents',
                        text: 'Cada intent precisa de Nome e Nó de Destino.',
                    });
                    return; // não prosseguir com o merge/salvar
                }
                
                // Se o usuário preencheu intents, ativar ramificação automaticamente
                if (intents.length > 0) {
                    branchingEnabled = true;
                    nodeData.ai_branching_enabled = true;
                    const branchingCheckbox = document.getElementById('kt_ai_branching_enabled');
                    if (branchingCheckbox) branchingCheckbox.checked = true;
                }

                // Converter para boolean para salvar corretamente
                nodeData.ai_branching_enabled = branchingEnabled;
                nodeData.ai_intents = intents;
                
                console.log('  branchingEnabled final:', branchingEnabled);
                console.log('  Total de intents válidos coletados:', intents.length);
                console.log('  Intents:', intents);

                // ✅ Criar conexões automaticamente para cada intent com target definido
                if (branchingEnabled && intents.length > 0) {
                    if (!node.node_data.connections) {
                        node.node_data.connections = [];
                    }
                    intents.forEach((intent, idx) => {
                        if (!intent.target_node_id) return;
                        const exists = node.node_data.connections.some(conn =>
                            String(conn.target_node_id) === String(intent.target_node_id) &&
                            conn.option_index === idx
                        );
                        if (!exists) {
                            node.node_data.connections.push({
                                target_node_id: intent.target_node_id,
                                type: 'next',
                                option_index: idx
                            });
                        }
                    });
                    console.log('  Conexões auto-criadas para intents:', node.node_data.connections);
                } else {
                    console.log('  Ramificação desabilitada ou sem intents, limpando intents e conexões específicas');
                    nodeData.ai_intents = [];
                    // Remover conexões de intents se existirem
                    if (node.node_data.connections && node.node_data.connections.length > 0) {
                        node.node_data.connections = node.node_data.connections.filter(conn => conn.option_index === undefined);
                    }
                }
            }
            
            // Tratamento específico para Horário de Atendimento
            if (node.node_type === "condition_business_hours") {
                const bhData = collectBusinessHoursData();
                Object.assign(nodeData, bhData);
                
                // Checkbox de feriados que não aparece no FormData quando desmarcado
                if (!formData.has('check_holidays')) {
                    nodeData.check_holidays = false;
                }
                
                console.log('⏰ Horário de Atendimento - Dados coletados:', bhData);
            }

            console.log('node.node_data ANTES de merge:', node.node_data);
            console.log('nodeData coletado do form:', nodeData);
            
            // Merge dos dados (preservar connections que já foram atualizadas acima)
            // NÃO sobrescrever connections aqui, pois elas já foram atualizadas para AI intents
            const connectionsToPreserve = node.node_data.connections || [];
            node.node_data = { ...node.node_data, ...nodeData };
            // Manter as conexões já atualizadas (não sobrescrever com as antigas)
            node.node_data.connections = connectionsToPreserve;
            
            console.log('node.node_data DEPOIS de merge:', node.node_data);
            
            // Log específico para AI Agent
            if (node.node_type === "action_assign_ai_agent") {
                console.log('🤖 AI Agent - Verificação final ANTES de re-render:');
                console.log('  ai_branching_enabled:', node.node_data.ai_branching_enabled);
                console.log('  ai_intents (tipo, tamanho):', typeof node.node_data.ai_intents, Array.isArray(node.node_data.ai_intents) ? node.node_data.ai_intents.length : 'N/A');
                console.log('  ai_intents (conteúdo):', JSON.stringify(node.node_data.ai_intents, null, 2));
                console.log('  ai_max_interactions:', node.node_data.ai_max_interactions);
                console.log('  ai_fallback_node_id:', node.node_data.ai_fallback_node_id);
                console.log('  connections:', node.node_data.connections);
            }
            
            // Atualizar referência global
            window.nodes = nodes;
            
    // Re-render para refletir handles e dados atualizados
    console.log('🔄 Re-renderizando nó:', node.id, 'Tipo:', node.node_type);
    rerenderNode(node);
    makeNodeDraggable(String(node.id));
            
            console.log('💾 ===== CONFIGURAÇÃO SALVA =====');
            console.log('💾 Fechando modal...');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_node_config"));
            if (modal) {
                console.log('💾 Modal encontrado, fechando...');
                modal.hide();
            } else {
                console.error('❌ Modal não encontrado!');
            }
            
            console.log('💾 ===== FIM DO SUBMIT =====');
        });
    } else {
        console.error('❌ Formulário "kt_modal_node_config_form" não encontrado no DOM!');
    }
    
    // Carregar estágios quando funil for selecionado (modal de edição)
    const editFunnelSelect = document.getElementById("kt_edit_funnel_select");
    const editStageSelect = document.getElementById("kt_edit_stage_select");
    
    // Função para carregar estágios no modal de edição
    function loadEditStages(funnelId) {
        editStageSelect.innerHTML = '<option value="">Todos os Estágios</option>';
        
        // Se não há funil selecionado, carregar todos os estágios de todos os funis
        const url = funnelId ? 
            `${funnelsBaseUrl}/${funnelId}/stages` : 
            `${funnelsBaseUrl}/0/stages`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Erro ao carregar estágios: " + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.stages) {
                    data.stages.forEach(stage => {
                        const option = document.createElement("option");
                        option.value = stage.id;
                        // Se não há funil selecionado, mostrar também o nome do funil
                        const label = funnelId ? stage.name : (stage.funnel_name + " - " + stage.name);
                        option.textContent = label;
                        editStageSelect.appendChild(option);
                    });
                    
                    editStageSelect.disabled = false;
                    
                    // Selecionar estágio atual se houver
                    const currentStageId = automationStageId;
                    if (currentStageId) {
                        editStageSelect.value = currentStageId;
                    }
                } else {
                    console.error("Erro ao carregar estágios:", data.message || "Resposta inválida");
                    editStageSelect.disabled = true;
                }
            })
            .catch(error => {
                console.error("Erro ao carregar estágios:", error);
                editStageSelect.innerHTML = '<option value="">Erro ao carregar estágios</option>';
                editStageSelect.disabled = true;
            });
    }
    
    if (editFunnelSelect && editStageSelect) {
        // Carregar estágios quando funil mudar
        editFunnelSelect.addEventListener("change", function() {
            const funnelId = this.value;
            loadEditStages(funnelId);
        });
        
        // Carregar estágios iniciais se não há funil selecionado
        if (!editFunnelSelect.value) {
            loadEditStages(null);
        }
    }

    // Controlar visibilidade dos campos condicionais de gatilho no modal de edição
    const editTriggerSelect = document.getElementById("kt_edit_trigger_type");
    const editTimeConfigContainer = document.getElementById("kt_edit_time_config_container");
    const editScheduleConfigContainer = document.getElementById("kt_edit_schedule_config_container");
    const editScheduleType = document.getElementById("kt_edit_schedule_type");
    const editScheduleDayContainer = document.getElementById("kt_edit_schedule_day_container");
    
    if (editTriggerSelect) {
        editTriggerSelect.addEventListener("change", function() {
            const triggerType = this.value;
            
            // Mostrar/ocultar configuração de tempo
            if (editTimeConfigContainer) {
                if (triggerType === "no_customer_response" || triggerType === "no_agent_response") {
                    editTimeConfigContainer.style.display = "block";
                } else {
                    editTimeConfigContainer.style.display = "none";
                }
            }
            
            // Mostrar/ocultar configuração de agendamento
            if (editScheduleConfigContainer) {
                if (triggerType === "time_based") {
                    editScheduleConfigContainer.style.display = "block";
                } else {
                    editScheduleConfigContainer.style.display = "none";
                }
            }
        });
    }
    
    // Controlar visibilidade do dia da semana no agendamento
    if (editScheduleType && editScheduleDayContainer) {
        editScheduleType.addEventListener("change", function() {
            if (this.value === "weekly") {
                editScheduleDayContainer.style.display = "block";
            } else {
                editScheduleDayContainer.style.display = "none";
            }
        });
    }

    // Salvar configurações da automação (modal Editar)
    const editAutomationForm = document.getElementById("kt_modal_edit_automation_form");
    const editSubmitBtn = document.getElementById("kt_modal_edit_automation_submit");
    if (editAutomationForm && editSubmitBtn) {
        editAutomationForm.addEventListener("submit", function(e) {
            e.preventDefault();

            editSubmitBtn.setAttribute("data-kt-indicator", "on");
            editSubmitBtn.disabled = true;

            fetch(editAutomationForm.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                },
                body: new URLSearchParams(new FormData(editAutomationForm))
            })
            .then(res => res.json())
            .then(data => {
                editSubmitBtn.removeAttribute("data-kt-indicator");
                editSubmitBtn.disabled = false;

                if (data.success) {
                    // Fechar modal e feedback
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_automation"));
                    if (modal) modal.hide();

                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            icon: "success",
                            title: "Automação atualizada!",
                            text: data.message || "Alterações salvas com sucesso.",
                            timer: 1800,
                            showConfirmButton: false,
                            toast: true,
                            position: "top-end"
                        });
                    }

                    // Atualizar nome/descrição na UI sem recarregar, se disponível
                    const nameInput = editAutomationForm.querySelector("input[name='name']");
                    if (nameInput) {
                        const titleEl = document.getElementById("automation_title");
                        if (titleEl) titleEl.textContent = nameInput.value;
                    }
                } else {
                    const msg = data.message || "Erro ao salvar automação.";
                    if (typeof Swal !== "undefined") {
                        Swal.fire({ icon: "error", title: "Erro", text: msg });
                    } else {
                        alert(msg);
                    }
                }
            })
            .catch(error => {
                editSubmitBtn.removeAttribute("data-kt-indicator");
                editSubmitBtn.disabled = false;
                console.error("Erro ao salvar automação:", error);
                if (typeof Swal !== "undefined") {
                    Swal.fire({ icon: "error", title: "Erro", text: "Falha ao salvar automação." });
                } else {
                    alert("Falha ao salvar automação.");
                }
            });
        });
    }
    
    // Carregar logs ao iniciar
    refreshLogs();
});

function refreshLogs() {
    const logsContainer = document.getElementById("kt_automation_logs");
    if (!logsContainer) return;
    
    logsContainer.innerHTML = '<div class="text-center py-10"><span class="spinner-border spinner-border-sm text-primary"></span><span class="ms-2">Carregando logs...</span></div>';
    
    fetch(logsEndpoint)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.logs) {
                renderLogs(data.logs, data.stats);
            } else {
                logsContainer.innerHTML = '<div class="text-center py-10"><p class="text-muted">Nenhum log encontrado</p></div>';
            }
        })
        .catch(error => {
            console.error("Erro ao carregar logs:", error);
            logsContainer.innerHTML = '<div class="text-center py-10"><p class="text-danger">Erro ao carregar logs</p></div>';
        });
}

function renderLogs(logs, stats) {
    const logsContainer = document.getElementById("kt_automation_logs");
    if (!logsContainer) return;
    
    let html = "";
    
    // Estatísticas
    if (stats) {
        html += `
            <div class="d-flex flex-wrap gap-5 mb-7">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-45px me-3">
                        <div class="symbol-label bg-light-primary">
                            <i class="ki-duotone ki-chart-simple fs-2 text-primary">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-500 fs-7">Total</div>
                        <div class="fw-bold fs-5">${stats.total || 0}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-45px me-3">
                        <div class="symbol-label bg-light-success">
                            <i class="ki-duotone ki-check fs-2 text-success">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-500 fs-7">Concluídas</div>
                        <div class="fw-bold fs-5 text-success">${stats.completed || 0}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-45px me-3">
                        <div class="symbol-label bg-light-danger">
                            <i class="ki-duotone ki-cross fs-2 text-danger">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-500 fs-7">Falhas</div>
                        <div class="fw-bold fs-5 text-danger">${stats.failed || 0}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-45px me-3">
                        <div class="symbol-label bg-light-warning">
                            <i class="ki-duotone ki-time fs-2 text-warning">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-500 fs-7">Em Execução</div>
                        <div class="fw-bold fs-5 text-warning">${stats.running || 0}</div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Lista de logs
    if (logs.length === 0) {
        html += '<div class="text-center py-10"><p class="text-muted">Nenhum log encontrado</p></div>';
    } else {
        html += '<div class="table-responsive"><table class="table align-middle table-row-dashed fs-6 gy-5"><thead><tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0"><th class="min-w-150px">Data/Hora</th><th class="min-w-150px">Conversa/Contato</th><th class="min-w-100px">Status</th><th class="min-w-200px">Mensagem</th></tr></thead><tbody>';
        
        logs.forEach(log => {
            const statusClass = {
                "completed": "success",
                "failed": "danger",
                "running": "warning",
                "pending": "secondary"
            }[log.status] || "secondary";
            
            const statusText = {
                "completed": "Concluída",
                "failed": "Falhou",
                "running": "Em Execução",
                "pending": "Pendente"
            }[log.status] || log.status;
            
            const date = log.created_at ? new Date(log.created_at).toLocaleString("pt-BR") : "-";
            const contactName = (log.contact_name || "").replace(/`/g, "\\`").replace(/\$/g, "\\$");
            const contactPhone = (log.contact_phone || "").replace(/`/g, "\\`").replace(/\$/g, "\\$");
            const contactSubject = (log.conversation_subject || "N/A").replace(/`/g, "\\`").replace(/\$/g, "\\$");
            const contactInfo = log.contact_name ? `${contactName} (${contactPhone})` : contactSubject;
            const errorMessage = log.error_message ? (log.error_message.replace(/`/g, "\\`").replace(/\$/g, "\\$").replace(/</g, "&lt;").replace(/>/g, "&gt;")) : null;
            
            html += `
                <tr>
                    <td><span class="text-gray-800">${date}</span></td>
                    <td><span class="text-gray-600">${contactInfo}</span></td>
                    <td><span class="badge badge-light-${statusClass}">${statusText}</span></td>
                    <td>
                        ${errorMessage ? `<span class="text-danger">${errorMessage}</span>` : `<span class="text-muted">-</span>`}
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
    }
    
    logsContainer.innerHTML = html;
}

// Tornar funções do primeiro script acessíveis globalmente
window.addNode = addNode;
window.openNodeConfig = openNodeConfig;
window.deleteNode = deleteNode;
window.saveLayout = saveLayout;
window.refreshLogs = refreshLogs;
window.removeConnection = removeConnection;

// Funções para Chatbot Visual
window.updateChatbotFields = function updateChatbotFields(type) {
    const optionsContainer = document.getElementById('kt_chatbot_options_container');
    const conditionalContainer = document.getElementById('kt_chatbot_conditional_container');
    const optionsList = document.getElementById('kt_chatbot_options_list');
    
    if (optionsContainer) optionsContainer.style.display = 'none';
    if (conditionalContainer) conditionalContainer.style.display = 'none';

    if (type === 'menu') {
        if (optionsContainer) optionsContainer.style.display = 'block';
        if (optionsList && optionsList.children.length === 0) {
            // garantir pelo menos uma opção
            addChatbotOption();
        }
    } else if (type === 'conditional') {
        if (conditionalContainer) conditionalContainer.style.display = 'block';
    }
};

window.toggleTimeoutNodeSelect = function toggleTimeoutNodeSelect() {
    const timeoutActionSelect = document.getElementById('kt_chatbot_timeout_action');
    const timeoutNodeContainer = document.getElementById('kt_chatbot_timeout_node_container');
    
    if (!timeoutActionSelect || !timeoutNodeContainer) return;
    
    const selectedAction = timeoutActionSelect.value;
    
    if (selectedAction === 'go_to_node') {
        timeoutNodeContainer.style.display = 'block';
    } else {
        timeoutNodeContainer.style.display = 'none';
    }
};

// === FUNÇÕES DE RECONEXÃO ===

// ========== Funções do Nó Horário de Atendimento ==========
window.toggleBusinessHoursMode = function toggleBusinessHoursMode(mode) {
    const container = document.getElementById('kt_business_hours_manual_container');
    if (container) {
        container.style.display = mode === 'manual' ? 'block' : 'none';
    }
};

window.initBusinessHoursDayToggles = function initBusinessHoursDayToggles() {
    document.querySelectorAll('.bh-day-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const day = this.getAttribute('data-day');
            const inputs = document.querySelectorAll(`.bh-time-input[data-day="${day}"]`);
            inputs.forEach(function(input) {
                input.disabled = !checkbox.checked;
            });
        });
    });
};

window.loadBusinessHoursNodeConfig = function loadBusinessHoursNodeConfig(nodeData) {
    const mode = nodeData.business_hours_mode || 'global';
    const modeSelect = document.getElementById('kt_business_hours_mode');
    if (modeSelect) {
        modeSelect.value = mode;
        toggleBusinessHoursMode(mode);
    }

    // Carregar fuso horário
    const tzSelect = document.getElementById('kt_business_hours_timezone');
    if (tzSelect && nodeData.business_hours_timezone) {
        tzSelect.value = nodeData.business_hours_timezone;
    }

    // Carregar check de feriados
    const holidaysCheck = document.getElementById('kt_business_hours_check_holidays');
    if (holidaysCheck) {
        holidaysCheck.checked = !!nodeData.check_holidays;
    }

    // Carregar horários manuais
    if (nodeData.manual_schedule && Array.isArray(nodeData.manual_schedule)) {
        // Primeiro desmarcar todos
        document.querySelectorAll('.bh-day-toggle').forEach(function(cb) {
            cb.checked = false;
            const day = cb.getAttribute('data-day');
            document.querySelectorAll(`.bh-time-input[data-day="${day}"]`).forEach(function(inp) {
                inp.disabled = true;
            });
        });

        nodeData.manual_schedule.forEach(function(schedule) {
            const day = String(schedule.day);
            const cb = document.querySelector(`.bh-day-toggle[data-day="${day}"]`);
            if (cb && schedule.active) {
                cb.checked = true;
                const startInput = document.querySelector(`.bh-time-input[data-day="${day}"][data-field="start"]`);
                const endInput = document.querySelector(`.bh-time-input[data-day="${day}"][data-field="end"]`);
                if (startInput) { startInput.value = schedule.start || '08:00'; startInput.disabled = false; }
                if (endInput) { endInput.value = schedule.end || '18:00'; endInput.disabled = false; }
            }
        });
    }

    initBusinessHoursDayToggles();
};

window.collectBusinessHoursData = function collectBusinessHoursData() {
    const data = {};
    const modeSelect = document.getElementById('kt_business_hours_mode');
    data.business_hours_mode = modeSelect ? modeSelect.value : 'global';

    if (data.business_hours_mode === 'manual') {
        const tzSelect = document.getElementById('kt_business_hours_timezone');
        data.business_hours_timezone = tzSelect ? tzSelect.value : 'America/Sao_Paulo';

        const holidaysCheck = document.getElementById('kt_business_hours_check_holidays');
        data.check_holidays = holidaysCheck ? holidaysCheck.checked : false;

        const schedule = [];
        document.querySelectorAll('.bh-day-toggle').forEach(function(cb) {
            const day = parseInt(cb.getAttribute('data-day'));
            const active = cb.checked;
            const startInput = document.querySelector(`.bh-time-input[data-day="${day}"][data-field="start"]`);
            const endInput = document.querySelector(`.bh-time-input[data-day="${day}"][data-field="end"]`);
            schedule.push({
                day: day,
                active: active,
                start: startInput ? startInput.value : '08:00',
                end: endInput ? endInput.value : '18:00'
            });
        });
        data.manual_schedule = schedule;
    }

    return data;
};

window.toggleInactivityMode = function toggleInactivityMode() {
    const modeSelect = document.getElementById('kt_chatbot_inactivity_mode');
    const timeoutSimple = document.getElementById('kt_timeout_simple_container');
    const reconnectContainer = document.getElementById('kt_reconnect_container');
    const actionHint = document.getElementById('kt_timeout_action_hint');
    
    if (!modeSelect) return;
    
    const mode = modeSelect.value;
    
    if (mode === 'reconnect') {
        if (timeoutSimple) timeoutSimple.style.display = 'none';
        if (reconnectContainer) reconnectContainer.style.display = 'block';
        if (actionHint) actionHint.textContent = 'Ação executada após todas as tentativas de reconexão falharem';
        
        // Garantir pelo menos uma tentativa
        const list = document.getElementById('kt_reconnect_attempts_list');
        if (list && list.children.length === 0) {
            addReconnectAttempt({ message: 'Olá! Ainda está aí? Posso te ajudar com algo mais?', delay: 120 });
        }
    } else {
        if (timeoutSimple) timeoutSimple.style.display = 'block';
        if (reconnectContainer) reconnectContainer.style.display = 'none';
        if (actionHint) actionHint.textContent = 'Ação executada quando o tempo de espera expirar';
    }
};

window.addReconnectAttempt = function addReconnectAttempt(data = {}) {
    const list = document.getElementById('kt_reconnect_attempts_list');
    if (!list) return;
    
    const index = list.children.length;
    const message = data.message || '';
    const delay = data.delay || 120;
    
    const item = document.createElement('div');
    item.className = 'card card-bordered mb-3 reconnect-attempt-item';
    item.innerHTML = `
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-primary">Tentativa ${index + 1}</h5>
                <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeReconnectAttempt(this)" title="Remover tentativa">
                    <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="fv-row mb-3">
                <label class="fw-semibold fs-7 mb-1">Mensagem de Reconexão</label>
                <textarea name="reconnect_attempt_message[]" class="form-control form-control-solid" rows="2" placeholder="Ex: Olá! Notei que você não respondeu. Posso te ajudar?">${message}</textarea>
            </div>
            <div class="fv-row">
                <label class="fw-semibold fs-7 mb-1">Intervalo até próxima ação (segundos)</label>
                <input type="number" name="reconnect_attempt_delay[]" class="form-control form-control-solid" value="${delay}" min="10" max="86400" />
                <div class="form-text">Tempo após esta tentativa até a próxima tentativa ou ação final</div>
            </div>
        </div>
    `;
    list.appendChild(item);
    updateReconnectAttemptNumbers();
};

window.removeReconnectAttempt = function removeReconnectAttempt(button) {
    const item = button.closest('.reconnect-attempt-item');
    if (item) {
        item.remove();
        updateReconnectAttemptNumbers();
    }
};

window.updateReconnectAttemptNumbers = function updateReconnectAttemptNumbers() {
    const list = document.getElementById('kt_reconnect_attempts_list');
    if (!list) return;
    const items = list.querySelectorAll('.reconnect-attempt-item');
    items.forEach((item, idx) => {
        const title = item.querySelector('h5');
        if (title) title.textContent = `Tentativa ${idx + 1}`;
    });
};

window.loadReconnectAttempts = function loadReconnectAttempts(attempts) {
    const list = document.getElementById('kt_reconnect_attempts_list');
    if (!list) return;
    list.innerHTML = '';
    
    if (Array.isArray(attempts) && attempts.length > 0) {
        attempts.forEach(attempt => {
            addReconnectAttempt({
                message: attempt.message || '',
                delay: attempt.delay || 120
            });
        });
    }
};

window.addChatbotOption = function addChatbotOption(option = {}) {
    const optionsList = document.getElementById('kt_chatbot_options_list');
    if (!optionsList) return;
    const newOption = document.createElement('div');
    newOption.className = 'd-flex flex-column gap-2 mb-3 chatbot-option-item';
    newOption.innerHTML = `
        <div class="d-flex gap-2">
            <input type="text" name="chatbot_options[]" class="form-control form-control-solid" placeholder="Ex: 2 - Vendas" value="${option.text || option || ''}" />
            <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeChatbotOption(this)">
                <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span></i>
            </button>
        </div>
        <input type="text" name="chatbot_option_keywords[]" class="form-control form-control-solid" placeholder="Palavras-chave: 2, vendas, comercial" value="${(option.keywords || []).join(', ')}" />
    `;
    optionsList.appendChild(newOption);
};

window.removeChatbotOption = function removeChatbotOption(button) {
    const optionItem = button.closest('.chatbot-option-item');
    const optionsList = document.getElementById('kt_chatbot_options_list');
    if (!optionsList || !optionItem) return;
    // Manter pelo menos uma opção
    if (optionsList.children.length > 1) {
        optionItem.remove();
    } else {
        alert('É necessário ter pelo menos uma opção no menu.');
    }
};

window.populateChatbotOptionTargets = function populateChatbotOptionTargets() {
    // Conexões agora são feitas pelos handles no nó do chatbot (sem selects)
};

// Funções para Atribuição Avançada
function updateAssignmentFields(type) {
    const specificAgentContainer = document.getElementById('specific_agent_container');
    const departmentContainer = document.getElementById('department_container');
    const customMethodContainer = document.getElementById('custom_method_container');
    
    // Ocultar todos
    if (specificAgentContainer) specificAgentContainer.style.display = 'none';
    if (departmentContainer) departmentContainer.style.display = 'none';
    if (customMethodContainer) customMethodContainer.style.display = 'none';
    
    // Mostrar o relevante
    if (type === 'specific_agent' && specificAgentContainer) {
        specificAgentContainer.style.display = 'block';
    } else if (type === 'department' && departmentContainer) {
        departmentContainer.style.display = 'block';
    } else if (type === 'custom_method' && customMethodContainer) {
        customMethodContainer.style.display = 'block';
    }
}

function updatePercentageFields(method) {
    const percentageContainer = document.getElementById('percentage_container');
    if (percentageContainer) {
        percentageContainer.style.display = method === 'percentage' ? 'block' : 'none';
    }
}

function updateFallbackFields(action) {
    const fallbackStageContainer = document.getElementById('fallback_stage_container');
    if (fallbackStageContainer) {
        fallbackStageContainer.style.display = action === 'move_to_stage' ? 'block' : 'none';
    }
}

function addPercentageRule() {
    const list = document.getElementById('percentage_rules_list');
    if (!list) return;
    
    const newRule = document.createElement('div');
    newRule.className = 'd-flex gap-2 mb-2 percentage-rule-item';
    newRule.innerHTML = `
        <select name="percentage_agent_ids[]" class="form-select form-select-solid" style="flex: 1;">
            <option value="">Selecione um agente</option>
            ${agentOptionsHtml}
        </select>
        <input type="number" name="percentage_values[]" class="form-control form-control-solid" placeholder="%" min="1" max="100" style="width: 100px;" />
        <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removePercentageRule(this)">
            <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span></i>
        </button>
    `;
    list.appendChild(newRule);
}

function removePercentageRule(button) {
    const list = document.getElementById('percentage_rules_list');
    if (!list) return;
    
    // Manter pelo menos uma regra
    if (list.children.length > 1) {
        button.closest('.percentage-rule-item').remove();
    } else {
        alert('É necessário ter pelo menos uma regra de porcentagem.');
    }
}

window.updateAssignmentFields = updateAssignmentFields;
window.updatePercentageFields = updatePercentageFields;
window.updateFallbackFields = updateFallbackFields;
window.addPercentageRule = addPercentageRule;
window.removePercentageRule = removePercentageRule;

// Carregar estágios quando funil é selecionado
function loadStagesForFunnel(funnelId, targetSelectId) {
    const stageSelect = document.getElementById(targetSelectId);
    if (!stageSelect) return;
    
    if (!funnelId) {
        stageSelect.disabled = true;
        stageSelect.innerHTML = '<option value="">Primeiro selecione um funil</option>';
        return;
    }
    
    stageSelect.disabled = false;
    stageSelect.innerHTML = '<option value="">Carregando...</option>';
    
    fetch(`<?= \App\Helpers\Url::to('/funnels/') ?>${funnelId}/stages/json`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stages) {
                let options = '<option value="">Selecione um estágio</option>';
                data.stages.forEach(stage => {
                    options += `<option value="${stage.id}">${stage.name}</option>`;
                });
                stageSelect.innerHTML = options;
            } else {
                stageSelect.innerHTML = '<option value="">Erro ao carregar estágios</option>';
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            stageSelect.innerHTML = '<option value="">Erro ao carregar estágios</option>';
        });
}

// Atualizar operadores de condição baseado no campo
function updateConditionOperators(field) {
    const operatorSelect = document.getElementById('kt_condition_operator');
    const valueContainer = document.getElementById('kt_condition_value_container');
    const valueInput = document.getElementById('kt_condition_value');
    
    if (!operatorSelect || !valueContainer || !valueInput) return;
    
    // Campos especiais do sistema (não precisam de valor)
    const systemFields = ['business_hours'];
    const isSystemField = systemFields.includes(field);
    
    // Operadores numéricos para campos numéricos
    const numericFields = ['unread_count', 'created_days_ago'];
    const isNumeric = numericFields.includes(field);
    
    let operatorOptions = '<option value="">Selecione um operador</option>';
    
    if (isSystemField) {
        // Campos do sistema com operadores específicos
        if (field === 'business_hours') {
            operatorOptions += `
                <option value="is_within">Dentro do horário de atendimento</option>
                <option value="is_outside">Fora do horário de atendimento</option>
            `;
        }
        // Esconder o campo de valor para campos do sistema
        valueContainer.style.display = 'none';
        valueInput.removeAttribute('required');
    } else if (isNumeric) {
        operatorOptions += `
            <option value="equals">Igual a (=)</option>
            <option value="not_equals">Diferente de (≠)</option>
            <option value="greater_than">Maior que (>)</option>
            <option value="less_than">Menor que (<)</option>
            <option value="greater_or_equal">Maior ou igual (≥)</option>
            <option value="less_or_equal">Menor ou igual (≤)</option>
        `;
        valueInput.type = 'number';
        valueInput.placeholder = 'Digite um número...';
        valueContainer.style.display = 'block';
        valueInput.setAttribute('required', 'required');
    } else {
        operatorOptions += `
            <option value="equals">Igual a (=)</option>
            <option value="not_equals">Diferente de (≠)</option>
            <option value="contains">Contém</option>
            <option value="not_contains">Não contém</option>
            <option value="starts_with">Começa com</option>
            <option value="ends_with">Termina com</option>
            <option value="is_empty">Está vazio</option>
            <option value="is_not_empty">Não está vazio</option>
            <option value="in">Está em (lista)</option>
            <option value="not_in">Não está em (lista)</option>
        `;
        valueInput.type = 'text';
        valueInput.placeholder = 'Digite o valor...';
        valueContainer.style.display = 'block';
        valueInput.setAttribute('required', 'required');
    }
    
    operatorSelect.innerHTML = operatorOptions;
}

window.loadStagesForFunnel = loadStagesForFunnel;
window.updateConditionOperators = updateConditionOperators;

// ============================================
// FUNÇÕES PARA RAMIFICAÇÃO DE IA
// ============================================

// Toggle do container de ramificação
window.toggleAIBranchingContainer = function() {
    const checkbox = document.getElementById('kt_ai_branching_enabled');
    const container = document.getElementById('ai_branching_container');
    
    if (checkbox && container) {
        container.style.display = checkbox.checked ? 'block' : 'none';
    }
};

// Adicionar novo intent
window.addAIIntent = function() {
    const list = document.getElementById('ai_intents_list');
    if (!list) return;
    
    const index = list.children.length;
    const intentHtml = `
        <div class="card card-bordered mb-3 ai-intent-item" data-intent-index="${index}">
            <div class="card-body p-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Intent #${index + 1}</h5>
                    <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeAIIntent(this)">
                        <i class="ki-duotone ki-trash fs-2"></i>
                    </button>
                </div>
                
                <div class="fv-row mb-4">
                    <label class="fw-semibold fs-7 mb-2">Nome do Intent</label>
                    <input type="text" name="ai_intents[${index}][intent]" class="form-control form-control-sm form-control-solid" placeholder="Ex: purchase_inquiry, support_request" />
                    <div class="form-text">Identificador único do intent (sem espaços)</div>
                </div>
                
                <div class="fv-row mb-4">
                    <label class="fw-semibold fs-7 mb-2">Descrição</label>
                    <input type="text" name="ai_intents[${index}][description]" class="form-control form-control-sm form-control-solid" placeholder="Ex: Cliente perguntando sobre compra" />
                    <div class="form-text">Descrição legível do que este intent representa</div>
                </div>
                
                <div class="fv-row mb-4">
                    <label class="fw-semibold fs-7 mb-2">Palavras-chave (separadas por vírgula)</label>
                    <input type="text" name="ai_intents[${index}][keywords]" class="form-control form-control-sm form-control-solid" placeholder="Ex: comprar, produto, preço" />
                    <div class="form-text">Palavras que indicam este intent na resposta da IA</div>
                </div>

                <div class="fv-row mb-4">
                    <label class="fw-semibold fs-7 mb-2">Mensagem de saída (opcional)</label>
                    <textarea name="ai_intents[${index}][exit_message]" class="form-control form-control-sm form-control-solid" rows="2" placeholder="Mensagem enviada ao detectar este intent antes de seguir para o próximo nó"></textarea>
                    <div class="form-text">Se preenchido, a IA será removida e esta mensagem será enviada antes de executar o próximo nó.</div>
                </div>
                
                <div class="fv-row">
                    <label class="fw-semibold fs-7 mb-2">Nó de Destino</label>
                    <select name="ai_intents[${index}][target_node_id]" class="form-select form-select-sm form-select-solid ai-intent-target-select">
                        <option value="">Selecione um nó...</option>
                    </select>
                    <div class="form-text">Nó que será executado quando este intent for detectado</div>
                </div>
            </div>
        </div>
    `;
    
    list.insertAdjacentHTML('beforeend', intentHtml);
    
    // Preencher select de nós disponíveis
    if (typeof populateAIIntentTargetNodes === 'function') {
        populateAIIntentTargetNodes(index);
    }
};

// Remover intent
window.removeAIIntent = function(button) {
    const item = button.closest('.ai-intent-item');
    if (item) {
        item.remove();
        // Renumerar intents restantes
        if (typeof renumberAIIntents === 'function') {
            renumberAIIntents();
        }
    }
};

// Renumerar intents após remoção
window.renumberAIIntents = function() {
    const items = document.querySelectorAll('.ai-intent-item');
    items.forEach((item, index) => {
        item.setAttribute('data-intent-index', index);
        item.querySelector('h5').textContent = `Intent #${index + 1}`;
        
        // Atualizar names dos inputs
        item.querySelectorAll('input, select, textarea').forEach(input => {
            const name = input.getAttribute('name');
            if (name && name.startsWith('ai_intents[')) {
                const newName = name.replace(/ai_intents\[\d+\]/, `ai_intents[${index}]`);
                input.setAttribute('name', newName);
            }
        });
    });
};

// Preencher select de nós disponíveis para fallback
window.populateAIFallbackNodes = function(selectedNodeId) {
    selectedNodeId = selectedNodeId || '';
    const select = document.getElementById('kt_ai_fallback_node_id');
    if (!select) {
        console.warn('populateAIFallbackNodes: select kt_ai_fallback_node_id não encontrado');
        return;
    }
    
    // Limpar opções existentes (exceto a primeira)
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    // Adicionar nós disponíveis - usar window.nodes
    const nodes = window.nodes || [];
    console.log('populateAIFallbackNodes: Total de nós disponíveis:', nodes.length);
    
    let addedCount = 0;
    nodes.forEach(node => {
        if (node.node_type !== 'trigger' && node.node_type !== 'action_assign_ai_agent') {
            const label = node.node_data?.label || node.node_type;
            // Adicionar ID do nó no label para facilitar identificação
            const labelWithId = `${label} (ID: ${node.id})`;
            const option = new Option(labelWithId, node.id);
            select.add(option);
            addedCount++;
            
            if (node.id == selectedNodeId) {
                option.selected = true;
            }
        }
    });
    
    console.log('populateAIFallbackNodes: Nós adicionados ao select:', addedCount);
};

// Preencher select de nós disponíveis para target de intent
window.populateAIIntentTargetNodes = function(intentIndex) {
    const select = document.querySelector(`select[name="ai_intents[${intentIndex}][target_node_id]"]`);
    if (!select) {
        console.warn('populateAIIntentTargetNodes: select não encontrado para intent index:', intentIndex);
        return;
    }
    
    // Adicionar nós disponíveis - usar window.nodes
    const nodes = window.nodes || [];
    console.log('populateAIIntentTargetNodes: Total de nós disponíveis:', nodes.length);
    
    let addedCount = 0;
    nodes.forEach(node => {
        if (node.node_type !== 'trigger' && node.node_type !== 'action_assign_ai_agent') {
            const label = node.node_data?.label || node.node_type;
            // Adicionar ID do nó no label para facilitar identificação
            const labelWithId = `${label} (ID: ${node.id})`;
            const option = new Option(labelWithId, node.id);
            select.add(option);
            addedCount++;
        }
    });
    
    console.log('populateAIIntentTargetNodes: Nós adicionados ao select:', addedCount);
};

// Popular intents existentes ao carregar nó
window.populateAIIntents = function(intents) {
    console.log('populateAIIntents chamado com:', intents);
    
    intents = intents || [];
    const list = document.getElementById('ai_intents_list');
    
    if (!list) {
        console.error('populateAIIntents: elemento ai_intents_list não encontrado!');
        return;
    }
    
    // Limpar lista
    list.innerHTML = '';
    console.log('Lista limpa');
    
    // Se não há intents, não adicionar nada (usuário pode adicionar manualmente)
    if (!intents || intents.length === 0) {
        console.log('Nenhum intent para carregar');
        return;
    }
    
    console.log(`Carregando ${intents.length} intent(s)`);
    
    // Adicionar cada intent
    intents.forEach((intent, index) => {
        console.log(`Adicionando intent ${index}:`, intent);
        
        if (typeof addAIIntent === 'function') {
            addAIIntent();
        } else {
            console.error('addAIIntent não está disponível');
            return;
        }
        
        // Preencher valores - aumentar timeout para garantir que o DOM foi atualizado
        setTimeout(() => {
            const item = list.children[index];
            if (!item) {
                console.error(`Item ${index} não encontrado no DOM`);
                return;
            }
            
            console.log(`Preenchendo valores do intent ${index}`);
            
            const intentInput = item.querySelector(`input[name="ai_intents[${index}][intent]"]`);
            const descInput = item.querySelector(`input[name="ai_intents[${index}][description]"]`);
            const keywordsInput = item.querySelector(`input[name="ai_intents[${index}][keywords]"]`);
            const targetSelect = item.querySelector(`select[name="ai_intents[${index}][target_node_id]"]`);
            const exitMessageInput = item.querySelector(`textarea[name="ai_intents[${index}][exit_message]"]`);
            
            if (intentInput) {
                intentInput.value = intent.intent || '';
                console.log(`  - Intent name: ${intent.intent}`);
            }
            if (descInput) {
                descInput.value = intent.description || '';
                console.log(`  - Description: ${intent.description}`);
            }
            if (keywordsInput) {
                // Keywords pode ser array ou string
                const keywords = Array.isArray(intent.keywords) ? intent.keywords.join(', ') : (intent.keywords || '');
                keywordsInput.value = keywords;
                console.log(`  - Keywords: ${keywords}`);
            }
            if (targetSelect && intent.target_node_id) {
                targetSelect.value = intent.target_node_id;
                console.log(`  - Target node: ${intent.target_node_id}`);
            }
            if (exitMessageInput) {
                exitMessageInput.value = intent.exit_message || '';
                console.log(`  - Exit message: ${intent.exit_message}`);
            }
        }, 100); // Aumentado de 50 para 100ms
    });
    
    console.log('populateAIIntents concluído');
};

</script>
<?php
$scripts = $scriptsPreload . ob_get_clean() . <<<'JAVASCRIPT'
<script>
// Fallback imediato para evitar ReferenceError antes dos scripts principais
window.testAutomation = window.testAutomation || function() {
    console.warn('testAutomation ainda não carregou. Aguarde o script principal.');
};
window.advancedTestAutomation = window.advancedTestAutomation || function() {
    console.warn('advancedTestAutomation ainda não carregou. Aguarde o script principal.');
};

// ===== FUNÇÕES GLOBAIS (EXPORT NO TOPO) =====
// Declarar funções como globais imediatamente
window.testAutomation = null;
window.advancedTestAutomation = null;
window.validateAutomationForm = null;
window.validateAutomationConnections = null;
window.validateRequiredField = null;
window.previewVariables = null;
window.showVariablesModal = null;
window.previewMessageVariables = null;

// Constante auxiliar para string vazia (evita problemas com aspas em strings PHP)
const _EMPTY_STR = "";
// Variáveis disponíveis para preview
const availableVariables = {
    "{{contact.name}}": "João Silva",
    "{{contact.phone}}": "+55 11 99999-9999",
    "{{contact.email}}": "joao@exemplo.com",
    "{{agent.name}}": "Maria Santos",
    "{{conversation.id}}": "123",
    "{{conversation.subject}}": "Dúvida sobre produto",
JAVASCRIPT
. '    "{{date}}": "' . date('d/m/Y') . '",' . "\n"
. '    "{{time}}": "' . date('H:i') . '",' . "\n"
. '    "{{datetime}}": "' . date('d/m/Y H:i') . '"' . "\n"
. <<<'JAVASCRIPT'
};

function showVariablesModal() {
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_variables"));
    modal.show();
}

function previewMessageVariables() {
    const textarea = document.getElementById("kt_node_message_textarea");
    const previewDiv = document.getElementById("kt_message_preview");
    const previewContent = document.getElementById("kt_message_preview_content");
    
    if (!textarea || !previewDiv || !previewContent) return;
    
    let message = textarea.value;
    
    if (!message.trim()) {
        previewDiv.classList.add("d-none");
        return;
    }
    
    // Substituir variáveis por valores de exemplo
    Object.keys(availableVariables).forEach(variable => {
        const regex = new RegExp(variable.replace(/[{}]/g, "\\\\$&"), "g");
        message = message.replace(regex, availableVariables[variable]);
    });
    
    // Destacar variáveis não substituídas (não encontradas)
    const remainingVariables = message.match(/{{[^}]+}}/g);
    if (remainingVariables) {
        remainingVariables.forEach(variable => {
            message = message.replace(variable, `<span class="badge badge-light-warning">${variable}</span>`);
        });
    }
    
    previewContent.innerHTML = message.replace(/\\n/g, "<br>");
    previewDiv.classList.remove("d-none");
}

// Adicionar listener para preview em tempo real
document.addEventListener("DOMContentLoaded", function() {
    // Usar event delegation para textarea de mensagem e chatbot
    document.addEventListener("input", function(e) {
        if (e.target && (e.target.id === "kt_node_message_textarea" || e.target.name === "chatbot_message")) {
            previewMessageVariables();
        }
    });
    
    // Validação em tempo real de campos obrigatórios
    document.addEventListener("blur", function(e) {
        if (e.target && e.target.hasAttribute("required")) {
            validateRequiredField(e.target);
        }
    }, true);
    
    // Adicionar botão de inserir variável ao clicar na tabela
    setTimeout(function() {
        document.querySelectorAll("#kt_modal_variables code").forEach(code => {
            code.style.cursor = "pointer";
            code.title = "Clique para copiar";
            code.addEventListener("click", function() {
                const variable = this.textContent.trim();
                const textarea = document.getElementById("kt_node_message_textarea");
                if (textarea) {
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const text = textarea.value;
                    const before = text.substring(0, start);
                    const after = text.substring(end, text.length);
                    textarea.value = before + variable + after;
                    textarea.focus();
                    textarea.setSelectionRange(start + variable.length, start + variable.length);
                    previewMessageVariables();
                    
                    // Fechar modal após copiar
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_variables"));
                    if (modal) {
                        modal.hide();
                    }
                }
            });
        });
    }, 1000);
});

// Testar automação
window.testAutomation = function testAutomation() {
    const automationId = ' . (int)($automation['id'] ?? 0) . ';
    const conversationId = prompt('ID da conversa para teste (deixe vazio para usar dados simulados):');
    
    Swal.fire({
        title: 'Testando automação...',
        text: 'Aguarde enquanto simulamos a execução',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const url = '/automations/' + automationId + '/test' + (conversationId ? '?conversation_id=' + conversationId : '');
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.test_result) {
                const result = data.test_result;
                let html = `
                    <div class="text-start">
                        <h4 class="fw-bold mb-4">Resultado do Teste</h4>
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge badge-light-primary">${result.steps.length} passo(s) executado(s)</span>
                                ${result.errors.length > 0 ? `<span class="badge badge-light-danger">${result.errors.length} erro(s)</span>` : _EMPTY_STR}
                                ${result.simulated_actions.length > 0 ? `<span class="badge badge-light-success">${result.simulated_actions.length} ação(ões) simulada(s)</span>` : _EMPTY_STR}
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Passo</th>
                                        <th>Tipo</th>
                                        <th>Preview</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                result.steps.forEach((step, index) => {
                    let previewHtml = '';
                    if (step.action_preview) {
                        const ap = step.action_preview;
                        switch(ap.type) {
                            case 'send_message':
                                previewHtml = `<div><strong>Mensagem:</strong> ${ap.message}</div>`;
                                if (ap.variables_used && ap.variables_used.length > 0) {
                                    previewHtml += `<div class="text-muted fs-8 mt-1">Variáveis: ${ap.variables_used.join(', ')}</div>`;
                                }
                                break;
                            case 'assign_agent':
                                previewHtml = `<div><strong>Agente:</strong> ${ap.agent_name}</div>`;
                                break;
                            case 'move_stage':
                                previewHtml = `<div><strong>Estágio:</strong> ${ap.stage_name}</div>`;
                                break;
                            case 'set_tag':
                                previewHtml = `<div><strong>Tag:</strong> ${ap.tag_name}</div>`;
                                break;
                            case 'delay':
                                previewHtml = `<div><strong>Aguardar:</strong> ${ap.formatted}</div>`;
                                break;
                        }
                    } else if (step.condition_result) {
                        const cr = step.condition_result;
                        previewHtml = `<div><strong>Resultado:</strong> ${cr.result ? 'Verdadeiro' : 'Falso'}</div>`;
                        previewHtml += `<div class="text-muted fs-8 mt-1">${cr.reason}</div>`;
                        if (cr.conditions_evaluated && cr.conditions_evaluated.length > 0) {
                            previewHtml += `<div class="mt-2"><small>Condições:</small><ul class="mb-0">`;
                            cr.conditions_evaluated.forEach(cond => {
                                previewHtml += `<li><small>${cond.field} ${cond.operator} ${cond.value} → ${cond.result ? '✓' : '✗'}</small></li>`;
                            });
                            previewHtml += `</ul></div>`;
                        }
                    }
                    
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td><span class="badge badge-light-info">${step.node_type}</span></td>
                            <td>${previewHtml || '-'}</td>
                            <td><span class="badge ${step.status === 'error' ? 'badge-light-danger' : 'badge-light-success'}">${step.status === 'error' ? 'Erro' : 'Simulado'}</span></td>
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
                    width: '900px',
                    showConfirmButton: true,
                    confirmButtonText: 'Fechar',
                    customClass: {
                        popup: 'text-start'
                    }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: data.message || 'Erro ao testar automação'
                });
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao testar automação'
            });
        });
}

// Preview de variáveis (para usar em campos de mensagem)
window.previewVariables = function previewVariables(message, conversationId) {
    if (!message) {
        return;
    }
    
    fetch('/automations/preview-variables', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message: message,
            conversation_id: conversationId || null
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.preview) {
                const preview = data.preview;
                let html = `
                    <div class="text-start">
                        <h5 class="fw-bold mb-3">Preview de Variáveis</h5>
                        <div class="mb-3">
                            <div class="text-muted fs-7 mb-1">Mensagem Original:</div>
                            <div class="bg-light p-3 rounded">${preview.original}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted fs-7 mb-1">Mensagem Processada:</div>
                            <div class="bg-light-success p-3 rounded">${preview.processed}</div>
                        </div>
                        ${preview.variables_used && preview.variables_used.length > 0 ? `
                            <div class="mb-3">
                                <div class="text-muted fs-7 mb-1">Variáveis Utilizadas:</div>
                                <div class="d-flex flex-wrap gap-2">
                                    ${preview.variables_used.map(v => `<span class="badge badge-light-primary">${v}</span>`).join(_EMPTY_STR)}
                                </div>
                            </div>
                        ` : _EMPTY_STR}
                    </div>
                `;
                
                Swal.fire({
                    html: html,
                    width: '700px',
                    showConfirmButton: true,
                    confirmButtonText: 'Fechar',
                    customClass: {
                        popup: 'text-start'
                    }
                });
            }
        })
        .catch(error => {
            console.error('Erro:', error);
        });
}

// Validação visual de campos obrigatórios
window.validateRequiredField = function validateRequiredField(field) {
    if (!field) return true;
    
    const isValid = field.value.trim() !== '';
    const feedbackId = field.id + '_feedback';
    let feedback = document.getElementById(feedbackId);
    
    // Remover feedback existente
    if (feedback) {
        feedback.remove();
    }
    
    // Remover classes anteriores
    field.classList.remove('is-invalid', 'is-valid');
    
    if (!isValid) {
        field.classList.add('is-invalid');
        feedback = document.createElement('div');
        feedback.id = feedbackId;
        feedback.className = 'invalid-feedback';
        feedback.textContent = 'Este campo é obrigatório';
        field.parentNode.appendChild(feedback);
        return false;
    } else {
        field.classList.add('is-valid');
        return true;
    }
}

// Validar formulário antes de salvar
window.validateAutomationForm = function validateAutomationForm() {
    const requiredFields = document.querySelectorAll('#kt_node_config_form [required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!validateRequiredField(field)) {
            isValid = false;
        }
    });
    
    if (!isValid) {
        Swal.fire({
            icon: 'error',
            title: 'Campos Obrigatórios',
            text: 'Por favor, preencha todos os campos obrigatórios antes de salvar.',
            confirmButtonText: 'OK'
        });
    }
    
    return isValid;
}

// Validar se automação tem nós conectados
window.validateAutomationConnections = function validateAutomationConnections() {
    if (nodes.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Automação Vazia',
            text: 'Adicione pelo menos um nó à automação antes de ativá-la.',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    // Verificar se há nó trigger
    const hasTrigger = nodes.some(node => node.node_type === 'trigger');
    if (!hasTrigger) {
        Swal.fire({
            icon: 'warning',
            title: 'Sem Gatilho',
            text: 'Adicione um nó de gatilho (trigger) para iniciar a automação.',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    // Verificar nós desconectados (exceto trigger)
    const disconnectedNodes = nodes.filter(node => {
        if (node.node_type === 'trigger') return false;
        
        // Verificar se algum nó se conecta a este
        const hasIncomingConnection = nodes.some(otherNode => {
            const connections = otherNode.node_data?.connections || [];
            return connections.some(conn => conn.target_node_id === node.id);
        });
        
        return !hasIncomingConnection;
    });
    
    if (disconnectedNodes.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Nós Desconectados',
            html: `Existem ${disconnectedNodes.length} nó(s) não conectado(s) na automação. <br><br>Conecte todos os nós ou remova os desnecessários.`,
            confirmButtonText: 'OK'
        });
        return false;
    }

    // Validação extra: chatbot menu deve ter conexões para cada opção
    const chatbotIssues = [];
    nodes.forEach(node => {
        if (node.node_type !== 'action_chatbot') return;
        const type = node.node_data?.chatbot_type || 'simple';
        if (type !== 'menu') return;
        const options = (node.node_data?.chatbot_options || []).filter(o => (o || '').trim() !== '');
        const connections = node.node_data?.connections || [];
        if (options.length > 0 && connections.length < options.length) {
            chatbotIssues.push(node.id || node.node_data?.label || 'Chatbot');
        }
    });

    if (chatbotIssues.length > 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Chatbot incompleto',
            html: `Os chatbots de menu precisam ter conexões para cada opção.<br><br>Corrija os nós: <strong>${chatbotIssues.join(', ')}</strong>`,
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    return true;
}

// Modo de teste avançado
window.advancedTestAutomation = function advancedTestAutomation() {
    if (!window.validateAutomationConnections()) {
        return;
    }
    
    const automationId = ' . (int)($automation['id'] ?? 0) . ';
    
    Swal.fire({
        title: 'Modo de Teste Avançado',
        html: `
            <div class="text-start">
                <p class="mb-4">Configure o teste da automação:</p>
                <div class="fv-row mb-4">
                    <label class="fw-semibold fs-6 mb-2">ID da Conversa (opcional)</label>
                    <input type="number" id="test_conversation_id" class="swal2-input" placeholder="Deixe vazio para dados simulados" />
                    <div class="form-text">Se fornecido, usa dados reais da conversa</div>
                </div>
                <div class="fv-row mb-4">
                    <label class="fw-semibold fs-6 mb-2">Modo de Execução</label>
                    <select id="test_mode" class="swal2-select">
                        <option value="simulate">Simular (sem executar ações)</option>
                        <option value="dry_run">Dry Run (valida mas não executa)</option>
                        <option value="real">Execução Real (CUIDADO!)</option>
                    </select>
                </div>
                <div class="fv-row">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="test_step_by_step" />
                        <label class="form-check-label" for="test_step_by_step">
                            Executar passo-a-passo
                        </label>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Iniciar Teste',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const conversationId = document.getElementById('test_conversation_id').value;
            const mode = document.getElementById('test_mode').value;
            const stepByStep = document.getElementById('test_step_by_step').checked;
            
            return {
                conversation_id: conversationId || null,
                mode: mode,
                step_by_step: stepByStep
};
window.__realAdvancedTestAutomation = window.advancedTestAutomation;
window.__realTestAutomation = window.testAutomation;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            executeAutomationTest(automationId, result.value);
        }
    });
}

// Executar teste da automação
function executeAutomationTest(automationId, testConfig) {
    Swal.fire({
        title: 'Testando automação...',
        html: '<div id="test_progress">Inicializando teste...</div>',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const url = `<?= \App\Helpers\Url::to('/automations/') ?>${automationId}/test`;
    const params = new URLSearchParams(testConfig);
    
    fetch(`${url}?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.test_result) {
                displayTestResults(data.test_result);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro no Teste',
                    text: data.message || 'Não foi possível executar o teste',
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao executar teste: ' + error.message,
                confirmButtonText: 'OK'
            });
        });
}

// Exibir resultados do teste
function displayTestResults(result) {
    let html = `
        <div class="text-start">
            <h4 class="fw-bold mb-4">Resultado do Teste</h4>
            <div class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge badge-light-primary fs-6">${result.steps?.length || 0} passo(s)</span>
                    ${result.errors && result.errors.length > 0 ? `<span class="badge badge-light-danger fs-6">${result.errors.length} erro(s)</span>` : ''}
                    ${result.warnings && result.warnings.length > 0 ? `<span class="badge badge-light-warning fs-6">${result.warnings.length} aviso(s)</span>` : ''}
                    ${result.simulated_actions && result.simulated_actions.length > 0 ? `<span class="badge badge-light-success fs-6">${result.simulated_actions.length} ação(ões)</span>` : ''}
                </div>
            </div>
    `;
    
    // Exibir avisos (warnings) - especialmente sobre chatbots
    if (result.warnings && result.warnings.length > 0) {
        html += '<div class="alert alert-warning d-flex align-items-center"><i class="ki-duotone ki-information fs-2tx text-warning me-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i><div><strong>Avisos Importantes:</strong><ul class="mb-0 mt-2">';
        result.warnings.forEach(warning => {
            const message = warning.message || warning;
            html += `<li>${message}</li>`;
        });
        html += '</ul></div></div>';
    }
    
    if (result.errors && result.errors.length > 0) {
        html += '<div class="alert alert-danger"><strong>Erros Encontrados:</strong><ul class="mb-0">';
        result.errors.forEach(error => {
            const message = error.message || error;
            html += `<li>${message}</li>`;
        });
        html += '</ul></div>';
    }
    
    if (result.steps && result.steps.length > 0) {
        html += `
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th class="min-w-50px">#</th>
                            <th class="min-w-150px">Tipo</th>
                            <th class="min-w-300px">Detalhes</th>
                            <th class="min-w-100px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        result.steps.forEach((step, index) => {
            const stepNum = index + 1;
            
            // Determinar status visual
            let statusBadge = '';
            if (step.status === 'waiting') {
                statusBadge = '<span class="badge badge-light-warning">⏸️ Aguardando</span>';
            } else if (step.status === 'error') {
                statusBadge = '<span class="badge badge-light-danger">✗ Erro</span>';
            } else if (step.status === 'simulated') {
                statusBadge = '<span class="badge badge-light-success">✓ Simulado</span>';
            } else {
                statusBadge = step.success ? '<span class="badge badge-light-success">✓ OK</span>' : '<span class="badge badge-light-danger">✗ Erro</span>';
            }
            
            // Formatar detalhes
            let details = '';
            if (step.action_preview) {
                const preview = step.action_preview;
                
                if (preview.type === 'chatbot') {
                    // Formato especial para chatbot
                    details = `<strong>Tipo:</strong> ${preview.chatbot_type}<br>
                               <strong>Mensagem:</strong> ${preview.message.substring(0, 100)}${preview.message.length > 100 ? '...' : ''}<br>`;
                    
                    if (preview.options && preview.options.length > 0) {
                        details += `<strong>Opções:</strong> ${preview.options.length} opção(ões)<br>`;
                        details += `<ul class="mb-0 mt-1">`;
                        preview.options.forEach(opt => {
                            details += `<li class="fs-8">${opt}</li>`;
                        });
                        details += `</ul>`;
                    }
                    
                    if (preview.note) {
                        details += `<div class="mt-2 p-2 bg-light-warning rounded"><small>${preview.note}</small></div>`;
                    }
                } else if (preview.type === 'send_message') {
                    details = `<strong>Mensagem:</strong><br>${preview.message.substring(0, 150)}${preview.message.length > 150 ? '...' : ''}`;
                } else {
                    // Outros tipos - JSON formatado
                    details = `<pre class="mb-0 fs-8" style="max-height: 100px; overflow-y: auto;">${JSON.stringify(preview, null, 2)}</pre>`;
                }
            } else if (step.condition_result) {
                details = `<strong>Resultado:</strong> ${step.condition_result.result ? '✓ Verdadeiro' : '✗ Falso'}<br>
                          <small>${step.condition_result.reason || ''}</small>`;
            } else if (step.error) {
                details = `<span class="text-danger">${step.error}</span>`;
            } else {
                details = step.node_name || step.node_type || 'N/A';
            }
            
            html += `
                <tr>
                    <td class="fw-bold">${stepNum}</td>
                    <td><span class="badge badge-light-primary">${step.node_type || 'N/A'}</span></td>
                    <td>${details}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
    }
    
    html += '</div>';
    
    Swal.fire({
        html: html,
        width: '1000px',
        confirmButtonText: 'Fechar',
        customClass: {
            popup: 'text-start'
        }
    });
}

// ===== VERIFICAÇÃO FINAL: Funções globais =====
// Todas as funções principais já são window.* em suas definições
// Este bloco apenas confirma que estão disponíveis
console.log('Funções globais de automação carregadas:', {
    testAutomation: typeof window.testAutomation,
    advancedTestAutomation: typeof window.advancedTestAutomation,
    validateAutomationForm: typeof window.validateAutomationForm,
    validateAutomationConnections: typeof window.validateAutomationConnections,
    validateRequiredField: typeof window.validateRequiredField,
    previewVariables: typeof window.previewVariables
});

</script>
JAVASCRIPT;

echo $scripts;
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>


