<?php
$layout = 'layouts.metronic.app';
$title = 'Automação - ' . htmlspecialchars($automation['name'] ?? '');

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
    gap: 6px;
}

.automation-canvas-toolbar .btn {
    width: 34px;
    height: 34px;
    padding: 0;
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
    background: repeating-linear-gradient(
        0deg,
        rgba(0, 0, 0, 0.03),
        rgba(0, 0, 0, 0.03) 1px,
        transparent 1px,
        transparent 50px
    ),
    repeating-linear-gradient(
        90deg,
        rgba(0, 0, 0, 0.03),
        rgba(0, 0, 0, 0.03) 1px,
        transparent 1px,
        transparent 50px
    );
}

[data-bs-theme="dark"] #kt_automation_canvas {
    background: repeating-linear-gradient(
        0deg,
        rgba(255, 255, 255, 0.05),
        rgba(255, 255, 255, 0.05) 1px,
        transparent 1px,
        transparent 50px
    ),
    repeating-linear-gradient(
        90deg,
        rgba(255, 255, 255, 0.05),
        rgba(255, 255, 255, 0.05) 1px,
        transparent 1px,
        transparent 50px
    );
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

.connections-overlay line {
    pointer-events: stroke;
    cursor: pointer;
}

.connections-overlay line.connection-line {
    stroke: #009ef7;
    stroke-width: 2;
    pointer-events: stroke;
    cursor: default;
    transition: stroke-width 0.2s ease, opacity 0.2s ease;
}

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

[data-bs-theme="dark"] .connections-overlay line {
    stroke: #50cd89;
}

.node-connection-handle {
    position: absolute;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #009ef7;
    border: 2px solid white;
    cursor: crosshair;
    z-index: 80;
    pointer-events: all;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.node-connection-handle:hover {
    transform: scale(1.3);
    background: #50cd89;
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
}

.node-connection-handle.input {
    top: -6px;
    left: 50%;
    transform: translateX(-50%);
}

.chatbot-menu-options {
    border-top: 1px solid #e4e6ef;
    padding-top: 8px;
}

.chatbot-option-row {
    transition: background-color 0.2s ease;
    border-radius: 4px;
    padding-left: 8px;
}

.chatbot-option-row:hover {
    background-color: rgba(0, 158, 247, 0.1);
}

[data-bs-theme="dark"] .node-connection-handle {
    background: #50cd89;
    border-color: #1e1e2d;
}

[data-bs-theme="dark"] .node-connection-handle:hover {
    background: #009ef7;
}

.connecting-line {
    stroke-dasharray: 5,5;
    opacity: 0.7;
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
                        <button type="button" class="btn btn-light" id="automation_zoom_out" title="Diminuir zoom">
                            <i class="ki-duotone ki-minus fs-2"></i>
                        </button>
                        <div class="btn btn-light disabled px-3" id="automation_zoom_label">100%</div>
                        <button type="button" class="btn btn-light" id="automation_zoom_in" title="Aumentar zoom">
                            <i class="ki-duotone ki-plus fs-2"></i>
                        </button>
                        <button type="button" class="btn btn-light" id="automation_zoom_reset" title="Resetar zoom">
                            <i class="ki-duotone ki-arrows-circle fs-2"></i>
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
                    <button type="submit" id="kt_modal_node_config_submit" class="btn btn-primary">
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
$triggerType = $automation['trigger_type'] ?? 'new_conversation';
$triggerTypeJson = json_encode($triggerType);
$stageIdJson = json_encode($automation['stage_id'] ?? null);
$funnelsUrl = json_encode(\App\Helpers\Url::to('/funnels'));
$layoutUrl = json_encode(\App\Helpers\Url::to('/automations/' . $automation['id'] . '/layout'));
$logsUrl = json_encode(\App\Helpers\Url::to('/automations/' . $automation['id'] . '/logs'));

// Preparar opções HTML
$whatsappOptions = '';
if (!empty($whatsappAccounts)) {
    foreach ($whatsappAccounts as $acc) {
        $whatsappOptions .= '<option value="' . htmlspecialchars($acc['id']) . '">' . htmlspecialchars($acc['name'] . ' (' . $acc['phone_number'] . ')') . '</option>';
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
let nodes = <?= $nodesJson ?>;
// Garantir que nodes seja acessível globalmente
window.nodes = nodes;
let nodeTypes = <?= $nodeTypesJson ?>;
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
const whatsappOptionsHtml = <?= json_encode($whatsappOptions, JSON_UNESCAPED_UNICODE) ?>;
const stageOptionsHtml = <?= json_encode($stageOptions, JSON_UNESCAPED_UNICODE) ?>;
const agentOptionsHtml = <?= json_encode($agentOptions, JSON_UNESCAPED_UNICODE) ?>;
const funnelOptionsHtml = <?= json_encode($funnelOptions, JSON_UNESCAPED_UNICODE) ?>;
const departmentOptionsHtml = <?= json_encode($departmentOptions, JSON_UNESCAPED_UNICODE) ?>;
const aiAgentOptionsHtml = <?= json_encode($aiAgentOptions, JSON_UNESCAPED_UNICODE) ?>;

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
        <div class="d-flex align-items-center gap-3 mb-2">
            <i class="ki-duotone ${config.icon || "ki-gear"} fs-2" style="color: ${config.color || "#009ef7"};">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <span class="fw-bold">${config.label || node.node_type}</span>
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
        innerHtml += '<div class="chatbot-menu-options" style="margin-top: 10px; font-size: 11px; color: #7e8299;">';
        options.forEach(function(opt, idx) {
            const optText = (typeof opt === 'object' ? opt.text : opt) || `Opção ${idx + 1}`;
            innerHtml += `
                <div class="chatbot-option-row" style="position: relative; padding: 4px 0; padding-right: 20px;">
                    <span style="display: inline-block; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${optText}</span>
                    <div class="node-connection-handle output chatbot-option-handle" 
                         data-node-id="${String(node.id || '')}" 
                         data-handle-type="output" 
                         data-option-index="${idx}"
                         style="right: -10px; top: 50%; transform: translateY(-50%); background: ${config.color || '#009ef7'};">
                    </div>
                </div>
            `;
        });
        innerHtml += '</div>';
    } else if (node.node_type === 'action_assign_ai_agent' && 
               node.node_data.ai_branching_enabled && 
               node.node_data.ai_intents && 
               Array.isArray(node.node_data.ai_intents) &&
               node.node_data.ai_intents.length > 0) {
        // Se é AI Agent com ramificação, adicionar handles múltiplos para cada intent
        const intents = node.node_data.ai_intents;
        innerHtml += '<div class="ai-intents-visual" style="margin-top: 10px; font-size: 11px; color: #7e8299;">';
        intents.forEach(function(intent, idx) {
            const intentLabel = intent.description || intent.intent || `Intent ${idx + 1}`;
            innerHtml += `
                <div class="ai-intent-row" style="position: relative; padding: 4px 0; padding-right: 20px;">
                    <span style="display: inline-block; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${intentLabel}">🎯 ${intentLabel}</span>
                    <div class="node-connection-handle output ai-intent-handle" 
                         data-node-id="${String(node.id || '')}" 
                         data-handle-type="output" 
                         data-intent-index="${idx}"
                         style="right: -10px; top: 50%; transform: translateY(-50%); background: #6366f1;">
                    </div>
                </div>
            `;
        });
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
            const optionIndex = outputHandle.getAttribute('data-option-index');
            startConnection(node.id, 'output', e, optionIndex);
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
    const nodeTypes = document.querySelectorAll(".automation-node-type");
    
    nodeTypes.forEach(nodeType => {
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
    
    const config = nodeTypes[node.node_type] || {};
    document.getElementById("kt_modal_node_config_title").textContent = "Configurar: " + config.label;
    document.getElementById("kt_node_id").value = nodeId;
    document.getElementById("kt_node_type").value = node.node_type;
    
    // Gerar conteúdo do formulário baseado no tipo
    let formContent = "";
    
    switch(node.node_type) {
        case "trigger":
            const triggerType = automationTriggerType;
            formContent = `
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Canal</label>
                    <select name="channel" class="form-select form-select-solid">
                        <option value="">Todos os Canais</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="chat">Chat</option>
                    </select>
                </div>
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Conta WhatsApp</label>
                    <select name="whatsapp_account_id" class="form-select form-select-solid">
                        <option value="">Todas as Contas</option>
                        ${whatsappOptionsHtml}
                    </select>
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
                if (typeof populateAIFallbackNodes === 'function') {
                    populateAIFallbackNodes(node.node_data.ai_fallback_node_id);
                }
                if (typeof populateAIIntents === 'function') {
                    populateAIIntents(node.node_data.ai_intents || []);
                }
            }, 100);
            
            break;
        case "action_assign_advanced":
            const assignType = node.node_data.assignment_type || 'auto';
            const distributionMethod = node.node_data.distribution_method || 'round_robin';
            const filterDepartmentId = node.node_data.filter_department_id || '';
            const considerAvailability = node.node_data.consider_availability ?? true;
            const considerMax = node.node_data.consider_max_conversations ?? true;
            const allowAI = node.node_data.allow_ai_agents ?? false;
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
                    <select name="funnel_id" id="kt_node_funnel_select" class="form-select form-select-solid" required onchange="loadStagesForFunnel(this.value, 'kt_node_stage_select')">
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
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Tempo de Espera (segundos)</label>
                    <input type="number" name="chatbot_timeout" class="form-control form-control-solid" value="300" min="10" max="3600" />
                    <div class="form-text">Tempo máximo para aguardar resposta do usuário</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Ação ao Timeout</label>
                    <select name="chatbot_timeout_action" class="form-select form-select-solid">
                        <option value="nothing">Nada</option>
                        <option value="assign_agent">Atribuir a um Agente</option>
                        <option value="send_message">Enviar Mensagem</option>
                        <option value="close">Encerrar Conversa</option>
                    </select>
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
                        <option value="email">Email</option>
                        <option value="chat">Chat</option>
                        <option value="telegram">Telegram</option>
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
                    <input type="text" name="tag" id="kt_tag_name" class="form-control form-control-solid" placeholder="Nome da tag" required />
                    <div class="form-text">Nome da tag que será adicionada à conversa</div>
                </div>
                
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Ação</label>
                    <select name="tag_action" class="form-select form-select-solid">
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
    
    // Popular select de nó fallback para chatbot
    if (node.node_type === 'action_chatbot') {
        const fallbackSelect = document.getElementById('kt_chatbot_fallback_node_id');
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
    }
    
    // Carregar estágios quando funil for selecionado
    const funnelSelect = document.getElementById("kt_node_funnel_select");
    const stageSelect = document.getElementById("kt_node_stage_select");
    if (funnelSelect && stageSelect) {
        funnelSelect.addEventListener("change", function() {
            const funnelId = this.value;
            stageSelect.innerHTML = '<option value="">Selecione um estágio</option>';
            
            if (funnelId) {
                fetch(funnelsBaseUrl + "/" + funnelId + "/stages")
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
                                option.textContent = stage.name;
                                stageSelect.appendChild(option);
                            });
                            
                            // Selecionar estágio atual se houver
                            const currentStageId = node.node_data.stage_id || automationStageId;
                            if (currentStageId) {
                                stageSelect.value = currentStageId;
                            }
                        } else {
                            console.error("Erro ao carregar estágios:", data.message || "Resposta inválida");
                        }
                    })
                    .catch(error => {
                        console.error("Erro ao carregar estágios:", error);
                        stageSelect.innerHTML = '<option value="">Erro ao carregar estágios</option>';
                    });
            }
        });
    }
    
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_node_config"));
    modal.show();
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

function getNodeHandlePosition(nodeId, handleType, optionIndex) {
    if (!canvasViewport) return null;
    
    const nodeElement = document.getElementById(String(nodeId));
    if (!nodeElement) return null;
    
    let handle;
    
    // Se for handle de opção de chatbot
    if (handleType === 'output' && optionIndex !== undefined && optionIndex !== null) {
        handle = nodeElement.querySelector(`.node-connection-handle.${handleType}[data-option-index="${optionIndex}"]`);
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

function startConnection(nodeId, handleType, e, optionIndex) {
    e.stopPropagation();
    e.preventDefault();
    
    // Cancelar conexão anterior se houver
    cancelConnection();
    
    connectingFrom = { 
        nodeId: nodeId, 
        handleType: handleType,
        optionIndex: optionIndex !== undefined ? optionIndex : null
    };
    
    const pos = getNodeHandlePosition(nodeId, handleType, optionIndex);
    if (!pos) {
        connectingFrom = null;
        return;
    }
    
    // Mudar cursor e desabilitar seleção de texto
    document.body.style.cursor = 'crosshair';
    document.body.style.userSelect = 'none';
    
    // Criar linha temporária
    connectingLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    connectingLine.setAttribute('x1', pos.x);
    connectingLine.setAttribute('y1', pos.y);
    connectingLine.setAttribute('x2', pos.x);
    connectingLine.setAttribute('y2', pos.y);
    connectingLine.setAttribute('class', 'connecting-line');
    connectingLine.setAttribute('stroke', '#009ef7');
    connectingLine.setAttribute('stroke-width', '2');
    connectionsSvg.appendChild(connectingLine);
    
    // Atualizar linha ao mover mouse
    updateLineHandler = (e) => {
        if (!canvasViewport) return;
        const viewportRect = canvasViewport.getBoundingClientRect();
        const x = (e.clientX - viewportRect.left - canvasTranslate.x) / canvasScale;
        const y = (e.clientY - viewportRect.top - canvasTranslate.y) / canvasScale;
        if (connectingLine) {
            connectingLine.setAttribute('x2', x);
            connectingLine.setAttribute('y2', y);
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
    
    // Verificar se conexão já existe (mesma origem, destino e opção)
    const exists = fromNode.node_data.connections.some(function(conn) {
        return conn.target_node_id === nodeId && 
               conn.option_index === (connectingFrom.optionIndex !== null ? connectingFrom.optionIndex : undefined);
    });
    
    if (!exists) {
        const newConnection = {
            target_node_id: nodeId,
            type: 'next'
        };
        
        // Adicionar option_index se existir
        if (connectingFrom.optionIndex !== null && connectingFrom.optionIndex !== undefined) {
            newConnection.option_index = parseInt(connectingFrom.optionIndex);
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
            const fromPos = getNodeHandlePosition(node.id, 'output', optionIndex);
            const toPos = getNodeHandlePosition(connection.target_node_id, 'input');
            
            if (fromPos && toPos) {
                // Criar grupo para linha + botão de delete
                const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                group.setAttribute('class', 'connection-group');
                
                // Criar a linha
                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', fromPos.x);
                line.setAttribute('y1', fromPos.y);
                line.setAttribute('x2', toPos.x);
                line.setAttribute('y2', toPos.y);
                line.setAttribute('data-from', String(node.id || ''));
                line.setAttribute('data-to', String(connection.target_node_id || ''));
                line.setAttribute('class', 'connection-line');
                
                // Calcular ponto médio
                const midX = (fromPos.x + toPos.x) / 2;
                const midY = (fromPos.y + toPos.y) / 2;
                
                // Criar botão de delete (círculo + ícone)
                const deleteBtn = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                deleteBtn.setAttribute('class', 'connection-delete-btn');
                deleteBtn.setAttribute('transform', 'translate(' + midX + ',' + midY + ')');
                deleteBtn.style.cursor = 'pointer';
                deleteBtn.setAttribute('data-from', String(node.id || ''));
                deleteBtn.setAttribute('data-to', String(connection.target_node_id || ''));
                
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
                    if (confirm('Deseja remover esta conexão?')) {
                        removeConnection(fromId, toId);
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
                
                // Destacar linha ao passar mouse
                line.addEventListener('mouseenter', function() {
                    this.setAttribute('stroke-width', '3');
                    this.style.opacity = '0.8';
                });
                line.addEventListener('mouseleave', function() {
                    this.setAttribute('stroke-width', '2');
                    this.style.opacity = '1';
                });
                
                // Montar grupo e adicionar ao SVG
                group.appendChild(line);
                group.appendChild(deleteBtn);
                connectionsSvg.appendChild(group);
            }
        });
    });
}

function removeConnection(fromNodeId, toNodeId) {
    console.log('removeConnection chamado:', { fromNodeId, toNodeId, type_from: typeof fromNodeId, type_to: typeof toNodeId });
    
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
    node.node_data.connections = node.node_data.connections.filter(
        conn => String(conn.target_node_id) !== toIdStr
    );
    
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
    const nodeConfigForm = document.getElementById("kt_modal_node_config_form");
    if (nodeConfigForm) {
        nodeConfigForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const nodeId = document.getElementById("kt_node_id").value;
            const node = nodes.find(n => String(n.id) === String(nodeId));
            if (!node) return;
            
            const formData = new FormData(nodeConfigForm);
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
            // Checkboxes que não aparecem no FormData quando desmarcados
            const checkboxKeys = [
                'consider_availability',
                'consider_max_conversations',
                'allow_ai_agents',
                'force_assign'
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
            }
            
            // Tratamento específico para AI Agent: coletar intents
            if (node.node_type === "action_assign_ai_agent") {
                const branchingEnabled = nodeData.ai_branching_enabled === '1' || nodeData.ai_branching_enabled === true;
                console.log('Salvando configuração do AI Agent, branching:', branchingEnabled);
                
                if (branchingEnabled) {
                    const intentInputs = document.querySelectorAll('.ai-intent-item');
                    const intents = [];
                    
                    console.log('Intent items encontrados:', intentInputs.length);
                    
                    intentInputs.forEach((item, idx) => {
                        const intentName = item.querySelector(`input[name="ai_intents[${idx}][intent]"]`)?.value?.trim();
                        const description = item.querySelector(`input[name="ai_intents[${idx}][description]"]`)?.value?.trim();
                        const keywordsRaw = item.querySelector(`input[name="ai_intents[${idx}][keywords]"]`)?.value || '';
                        const targetNodeId = item.querySelector(`select[name="ai_intents[${idx}][target_node_id]"]`)?.value;
                        
                        const keywords = keywordsRaw.split(',').map(k => k.trim()).filter(k => k.length > 0);
                        
                        console.log(`Intent ${idx}: name="${intentName}", desc="${description}", target="${targetNodeId}"`);
                        
                        if (intentName && targetNodeId) {
                            intents.push({
                                intent: intentName,
                                description: description || intentName,
                                keywords: keywords,
                                target_node_id: targetNodeId
                            });
                        }
                    });
                    
                    console.log('Intents coletados:', intents);
                    nodeData.ai_intents = intents;
                }
            }
            
            console.log('node.node_data ANTES de merge:', node.node_data);
            console.log('nodeData coletado do form:', nodeData);
            
            // Merge dos dados (preservar connections)
            const oldConnections = node.node_data.connections || [];
            node.node_data = { ...node.node_data, ...nodeData };
            node.node_data.connections = oldConnections; // Preservar conexões
            
            console.log('node.node_data DEPOIS de merge:', node.node_data);
            
            // Atualizar referência global
            window.nodes = nodes;
            
    // Re-render para refletir handles e dados atualizados
    rerenderNode(node);
    makeNodeDraggable(String(node.id));
            
            console.log('Configuração salva. Fechando modal...');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_node_config"));
            modal.hide();
        });
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
    
    // Operadores numéricos para campos numéricos
    const numericFields = ['unread_count', 'created_days_ago'];
    const isNumeric = numericFields.includes(field);
    
    let operatorOptions = '<option value="">Selecione um operador</option>';
    
    if (isNumeric) {
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
        item.querySelectorAll('input, select').forEach(input => {
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
    if (!select) return;
    
    // Limpar opções existentes (exceto a primeira)
    while (select.options.length > 1) {
        select.remove(1);
    }
    
    // Adicionar nós disponíveis
    const nodes = window.currentAutomation?.nodes || [];
    nodes.forEach(node => {
        if (node.node_type !== 'trigger' && node.node_type !== 'action_assign_ai_agent') {
            const label = node.node_data?.label || node.node_type;
            const option = new Option(label, node.id);
            select.add(option);
            
            if (node.id == selectedNodeId) {
                option.selected = true;
            }
        }
    });
};

// Preencher select de nós disponíveis para target de intent
window.populateAIIntentTargetNodes = function(intentIndex) {
    const select = document.querySelector(`select[name="ai_intents[${intentIndex}][target_node_id]"]`);
    if (!select) return;
    
    // Adicionar nós disponíveis
    const nodes = window.currentAutomation?.nodes || [];
    nodes.forEach(node => {
        if (node.node_type !== 'trigger' && node.node_type !== 'action_assign_ai_agent') {
            const label = node.node_data?.label || node.node_type;
            const option = new Option(label, node.id);
            select.add(option);
        }
    });
};

// Popular intents existentes ao carregar nó
window.populateAIIntents = function(intents) {
    intents = intents || [];
    const list = document.getElementById('ai_intents_list');
    if (!list) return;
    
    // Limpar lista
    list.innerHTML = '';
    
    // Se não há intents, não adicionar nada (usuário pode adicionar manualmente)
    if (!intents || intents.length === 0) {
        return;
    }
    
    // Adicionar cada intent
    intents.forEach((intent, index) => {
        if (typeof addAIIntent === 'function') {
            addAIIntent();
        }
        
        // Preencher valores
        setTimeout(() => {
            const item = list.children[index];
            if (item) {
                const intentInput = item.querySelector(`input[name="ai_intents[${index}][intent]"]`);
                const descInput = item.querySelector(`input[name="ai_intents[${index}][description]"]`);
                const keywordsInput = item.querySelector(`input[name="ai_intents[${index}][keywords]"]`);
                const targetSelect = item.querySelector(`select[name="ai_intents[${index}][target_node_id]"]`);
                
                if (intentInput) intentInput.value = intent.intent || '';
                if (descInput) descInput.value = intent.description || '';
                if (keywordsInput) {
                    // Keywords pode ser array ou string
                    const keywords = Array.isArray(intent.keywords) ? intent.keywords.join(', ') : (intent.keywords || '');
                    keywordsInput.value = keywords;
                }
                if (targetSelect && intent.target_node_id) {
                    targetSelect.value = intent.target_node_id;
                }
            }
        }, 50);
    });
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


