<?php
$layout = 'layouts.metronic.app';
$title = 'Tools de IA';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Tools de IA</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('ai_tools.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_ai_tool">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Tool
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($tools)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-setting-2 fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma tool encontrada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova tool.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-100px">Tipo</th>
                            <th class="min-w-200px">Descrição</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($tool['name']) ?></span>
                                        <span class="text-muted fs-7"><?= htmlspecialchars($tool['slug']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary"><?= htmlspecialchars($tool['tool_type']) ?></span>
                                </td>
                                <td>
                                    <span class="text-gray-600"><?= htmlspecialchars(mb_substr($tool['description'] ?? '', 0, 50)) ?><?= mb_strlen($tool['description'] ?? '') > 50 ? '...' : '' ?></span>
                                </td>
                                <td>
                                    <?php if ($tool['enabled']): ?>
                                        <span class="badge badge-light-success">Ativa</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= \App\Helpers\Url::to('/ai-tools/' . $tool['id']) ?>" class="btn btn-sm btn-light-primary">
                                        Ver
                                    </a>
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

<!--begin::Modal - Nova Tool-->
<div class="modal fade" id="kt_modal_new_ai_tool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Tool de IA</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_ai_tool_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Ex: Buscar Pedido WooCommerce" required />
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Slug</label>
                        <input type="text" name="slug" class="form-control form-control-solid" placeholder="Ex: buscar_pedido_woocommerce" required />
                        <div class="form-text">Identificador único da tool (sem espaços, use underscore)</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Descrição da funcionalidade da tool"></textarea>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tipo</label>
                        <select name="tool_type" id="kt_tool_type" class="form-select form-select-solid" required>
                            <option value="">Selecione o tipo</option>
                            <option value="woocommerce">WooCommerce</option>
                            <option value="database">Database</option>
                            <option value="n8n">N8N</option>
                            <option value="document">Document</option>
                            <option value="system">System</option>
                            <option value="api">API</option>
                            <option value="followup">Followup</option>
                            <option value="human_escalation">🧑‍💼 Escalar para Humano</option>
                            <option value="funnel_stage">📊 Mover para Funil/Etapa</option>
                            <option value="funnel_stage_smart">🧠 Mover para Funil/Etapa (Inteligente)</option>
                        </select>
                    </div>
                    
                    <!-- Function Schema Fields -->
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Function Schema</label>
                        <div class="card bg-light p-5">
                            <div class="fv-row mb-5">
                                <label class="required fw-semibold fs-7 mb-2">Nome da Função</label>
                                <input type="text" id="kt_function_name" class="form-control form-control-solid" placeholder="Ex: buscar_pedido_woocommerce" required />
                                <div class="form-text">Nome único da função (use underscore, sem espaços)</div>
                            </div>
                            
                            <div class="fv-row mb-5">
                                <label class="required fw-semibold fs-7 mb-2">Descrição da Função</label>
                                <textarea id="kt_function_description" class="form-control form-control-solid" rows="2" placeholder="Descreva o que esta função faz" required></textarea>
                                <div class="form-text">Descrição clara do propósito da função</div>
                            </div>
                            
                            <div class="fv-row mb-5">
                                <label class="fw-semibold fs-7 mb-3">Parâmetros</label>
                                <div id="kt_function_parameters">
                                    <div class="text-muted fs-7 mb-3">Nenhum parâmetro adicionado. Clique em "Adicionar Parâmetro" para adicionar.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light-primary" onclick="addFunctionParameter()">
                                    <i class="ki-duotone ki-plus fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Adicionar Parâmetro
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Config Fields (dinâmico por tipo) -->
                    <div class="fv-row mb-7" id="kt_config_section" style="display: none;">
                        <label class="fw-semibold fs-6 mb-2">Configurações</label>
                        <div class="card bg-light p-5" id="kt_config_fields">
                            <!-- Campos serão inseridos dinamicamente via JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Hidden fields para JSON -->
                    <input type="hidden" name="function_schema" id="kt_function_schema_json" />
                    <input type="hidden" name="config" id="kt_config_json" />
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="enabled" class="form-check-input me-2" checked />
                            Tool ativa
                        </label>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_ai_tool_submit" class="btn btn-primary">
                        <span class="indicator-label">Criar Tool</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Nova Tool-->

<?php 
$content = ob_get_clean(); 

// Preparar dados para JavaScript
$departmentsJson = json_encode($departments ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$funnelsJson = json_encode($funnels ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$agentsJson = json_encode($agents ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

ob_start();
?>
<script>
let parameterCounter = 0;

// Dados do backend para selects dinâmicos
const availableDepartments = <?= $departmentsJson ?>;
const availableFunnels = <?= $funnelsJson ?>;
const availableAgents = <?= $agentsJson ?>;
const funnelStages = {}; // Será preenchido dinamicamente

// Configurações por tipo de tool
const toolTypeConfigs = {
    woocommerce: {
        fields: [
            { name: "url", label: "URL da Loja WooCommerce", type: "url", required: true, placeholder: "https://loja.exemplo.com" },
            { name: "consumer_key", label: "Consumer Key", type: "text", required: true },
            { name: "consumer_secret", label: "Consumer Secret", type: "password", required: true }
        ]
    },
    database: {
        fields: [
            { name: "host", label: "Host", type: "text", required: true, placeholder: "localhost" },
            { name: "database", label: "Nome do Banco", type: "text", required: true },
            { name: "username", label: "Usuário", type: "text", required: true },
            { name: "password", label: "Senha", type: "password", required: true },
            { name: "port", label: "Porta", type: "number", required: false, placeholder: "3306", default: "3306" },
            { name: "allowed_tables", label: "Tabelas Permitidas (separadas por vírgula)", type: "text", required: false, placeholder: "users,orders,products" }
        ]
    },
    n8n: {
        fields: [
            { name: "n8n_url", label: "URL Base do N8N", type: "url", required: true, placeholder: "https://n8n.exemplo.com" },
            { name: "webhook_id", label: "ID do Webhook Padrão", type: "text", required: false, placeholder: "abc123" },
            { name: "webhook_path", label: "Caminho do Webhook", type: "text", required: false, placeholder: "/webhook", default: "/webhook" },
            { name: "api_key", label: "API Key do N8N", type: "text", required: false, placeholder: "opcional" },
            { name: "timeout", label: "Timeout (segundos)", type: "number", required: false, placeholder: "120", default: "120", help: "Tempo máximo de espera pela resposta do N8N. Use 180+ para fluxos complexos com IA." },
            { name: "custom_headers", label: "Headers Customizados (JSON)", type: "textarea", required: false, placeholder: "{\"X-Custom-Header\": \"value\"}" },
            { name: "use_raw_response", label: "Usar resposta direta (não reenviar para OpenAI)", type: "checkbox", required: false, default: false, help: "Se ativo, a resposta do N8N será enviada diretamente ao cliente sem processamento adicional da IA" },
            { name: "raw_response_field", label: "Campo da resposta direta", type: "text", required: false, placeholder: "output", default: "output", help: "Campo JSON com a mensagem. Suporta: output, message, data.message. Arrays são tratados automaticamente." },
            { name: "include_history", label: "Incluir histórico da conversa", type: "checkbox", required: false, default: true, help: "Envia as últimas mensagens da conversa para o N8N ter contexto" },
            { name: "history_limit", label: "Quantidade de mensagens no histórico", type: "number", required: false, placeholder: "10", default: "10", help: "Número de mensagens anteriores a enviar (máx: 20)" },
            { name: "include_agent_info", label: "Incluir informações do agente", type: "checkbox", required: false, default: true, help: "Envia nome, persona e resumo do prompt do agente para manter consistência" }
        ]
    },
    api: {
        fields: [
            { name: "base_url", label: "Base URL da API", type: "url", required: true, placeholder: "https://api.exemplo.com/v1" },
            { name: "api_key", label: "API Key", type: "text", required: false },
            { name: "auth_type", label: "Tipo de Autenticação", type: "select", required: false, options: ["none", "bearer", "basic", "api_key"], default: "bearer" },
            { name: "headers", label: "Headers Adicionais (JSON)", type: "textarea", required: false, placeholder: '{"X-Custom-Header": "value"}' }
        ]
    },
    document: {
        fields: [
            { name: "path", label: "Caminho dos Documentos", type: "text", required: true, placeholder: "/var/www/documents" },
            { name: "allowed_extensions", label: "Extensões Permitidas (separadas por vírgula)", type: "text", required: false, placeholder: "pdf,doc,docx", default: "pdf,doc,docx,txt" }
        ]
    },
    system: {
        fields: [] // System tools geralmente não precisam de config
    },
    followup: {
        fields: [] // Followup tools geralmente não precisam de config
    },
    human_escalation: {
        fields: [
            { name: "escalation_type", label: "Tipo de Escalação", type: "select", required: true, 
              options: [
                { value: "auto", label: "Automático (usa config do sistema)" },
                { value: "department", label: "Setor Específico" },
                { value: "agent", label: "Agente Específico" },
                { value: "custom", label: "Personalizado" }
              ], default: "auto" },
            { name: "department_id", label: "Setor", type: "department_select", required: false, 
              showIf: "escalation_type:department,custom", help: "Selecione o setor de destino" },
            { name: "agent_id", label: "Agente", type: "agent_select", required: false, 
              showIf: "escalation_type:agent", help: "Selecione o agente específico" },
            { name: "distribution_method", label: "Método de Distribuição", type: "select", required: false,
              showIf: "escalation_type:custom",
              options: [
                { value: "round_robin", label: "Round Robin (sequencial)" },
                { value: "by_load", label: "Por Carga (menos conversas)" },
                { value: "by_performance", label: "Por Performance" },
                { value: "by_specialty", label: "Por Especialidade" },
                { value: "percentage", label: "Por Porcentagem" }
              ], default: "round_robin" },
            { name: "consider_availability", label: "Considerar disponibilidade (online)", type: "checkbox", required: false, default: true,
              showIf: "escalation_type:custom" },
            { name: "consider_limits", label: "Considerar limite máximo de conversas", type: "checkbox", required: false, default: true,
              showIf: "escalation_type:custom" },
            { name: "allow_ai_agents", label: "Permitir agentes de IA", type: "checkbox", required: false, default: false,
              showIf: "escalation_type:custom" },
            { name: "force_assign", label: "Forçar atribuição (ignora regras)", type: "checkbox", required: false, default: false,
              showIf: "escalation_type:agent" },
            { name: "fallback_action", label: "Se não encontrar agente", type: "select", required: false,
              showIf: "escalation_type:custom,department",
              options: [
                { value: "queue", label: "Manter em fila" },
                { value: "any_agent", label: "Atribuir a qualquer agente" },
                { value: "move_stage", label: "Mover para etapa" }
              ], default: "queue" },
            { name: "remove_ai_after", label: "Remover IA após escalação", type: "checkbox", required: false, default: true,
              help: "Remove o agente de IA da conversa após escalar" },
            { name: "send_notification", label: "Notificar agente humano", type: "checkbox", required: false, default: true },
            { name: "escalation_message", label: "Mensagem ao cliente", type: "textarea", required: false,
              placeholder: "Estou transferindo você para um de nossos especialistas...",
              help: "Mensagem enviada ao cliente ao escalar (deixe vazio para não enviar)" }
        ]
    },
    funnel_stage: {
        fields: [
            { name: "funnel_id", label: "Funil", type: "funnel_select", required: true, 
              help: "Selecione o funil de destino" },
            { name: "stage_id", label: "Etapa", type: "stage_select", required: true, 
              dependsOn: "funnel_id", help: "Selecione a etapa de destino" },
            { name: "keep_agent", label: "Manter agente atual", type: "checkbox", required: false, default: true,
              help: "Se desmarcado, remove o agente e usa regras da etapa" },
            { name: "remove_ai_after", label: "Remover IA após mover", type: "checkbox", required: false, default: false },
            { name: "add_note", label: "Adicionar nota interna", type: "checkbox", required: false, default: true },
            { name: "note_template", label: "Template da nota", type: "textarea", required: false,
              showIf: "add_note:true",
              placeholder: "Movido para {stage_name} pela IA. Motivo: {reason}",
              help: "Use {stage_name}, {funnel_name}, {reason} como variáveis" },
            { name: "trigger_automation", label: "Disparar automação da etapa", type: "checkbox", required: false, default: true,
              help: "Executa automações configuradas na etapa de destino" }
        ]
    },
    funnel_stage_smart: {
        fields: [
            { name: "max_options", label: "Máximo de etapas para análise", type: "number", required: false, 
              default: "30", placeholder: "30", 
              help: "Quantidade máxima de funis/etapas para IA analisar (afeta custo de tokens)" },
            { name: "allowed_funnels", label: "Restringir a funis específicos", type: "funnel_multi_select", required: false,
              help: "Deixe vazio para considerar todos os funis ativos" },
            { name: "min_confidence", label: "Confiança mínima (%)", type: "number", required: false,
              default: "70", placeholder: "70", min: 0, max: 100,
              help: "Confiança mínima para mover automaticamente. Abaixo disso, usa fallback" },
            { name: "fallback_funnel_id", label: "Funil Fallback", type: "funnel_select", required: false,
              help: "Funil para usar quando IA não tiver confiança suficiente" },
            { name: "fallback_stage_id", label: "Etapa Fallback", type: "stage_select", required: false,
              dependsOn: "fallback_funnel_id", help: "Etapa fallback" },
            { name: "fallback_action", label: "Ação se baixa confiança", type: "select", required: false,
              options: [
                { value: "use_fallback", label: "Usar funil/etapa fallback" },
                { value: "keep_current", label: "Manter etapa atual" },
                { value: "ask_client", label: "Perguntar ao cliente" },
                { value: "escalate", label: "Escalar para humano" }
              ], default: "use_fallback" },
            { name: "include_history", label: "Incluir histórico na análise", type: "checkbox", required: false, default: true,
              help: "Envia últimas mensagens para IA analisar contexto" },
            { name: "history_limit", label: "Mensagens do histórico", type: "number", required: false,
              default: "10", placeholder: "10", help: "Quantidade de mensagens recentes para análise" },
            { name: "keep_agent", label: "Manter agente atual", type: "checkbox", required: false, default: true },
            { name: "remove_ai_after", label: "Remover IA após mover", type: "checkbox", required: false, default: false },
            { name: "add_note", label: "Adicionar nota com justificativa", type: "checkbox", required: false, default: true,
              help: "Adiciona nota interna com a justificativa da IA" },
            { name: "trigger_automation", label: "Disparar automação da etapa", type: "checkbox", required: false, default: true }
        ]
    }
};

// Adicionar parâmetro à função
function addFunctionParameter() {
    parameterCounter++;
    const container = document.getElementById("kt_function_parameters");
    
    if (container.querySelector(".text-muted")) {
        container.innerHTML = "";
    }
    
    const paramDiv = document.createElement("div");
    paramDiv.className = "card mb-3 p-4";
    paramDiv.id = `param_${parameterCounter}`;
    paramDiv.innerHTML = `
        <div class="row g-3">
            <div class="col-md-4">
                <label class="fw-semibold fs-7 mb-2">Nome do Parâmetro</label>
                <input type="text" class="form-control form-control-sm param-name" placeholder="Ex: order_id" required />
            </div>
            <div class="col-md-3">
                <label class="fw-semibold fs-7 mb-2">Tipo</label>
                <select class="form-select form-select-sm param-type" required>
                    <option value="string">String</option>
                    <option value="integer">Integer</option>
                    <option value="number">Number</option>
                    <option value="boolean">Boolean</option>
                    <option value="array">Array</option>
                    <option value="object">Object</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="fw-semibold fs-7 mb-2">Descrição</label>
                <input type="text" class="form-control form-control-sm param-description" placeholder="Descrição do parâmetro" required />
            </div>
            <div class="col-md-1">
                <label class="fw-semibold fs-7 mb-2">&nbsp;</label>
                <button type="button" class="btn btn-sm btn-light-danger w-100" onclick="removeFunctionParameter(${parameterCounter})">
                    <i class="ki-duotone ki-trash fs-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                </button>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-12">
                <label class="d-flex align-items-center">
                    <input type="checkbox" class="form-check-input me-2 param-required" />
                    <span class="fs-7">Parâmetro obrigatório</span>
                </label>
            </div>
        </div>
    `;
    
    container.appendChild(paramDiv);
}

// Remover parâmetro
function removeFunctionParameter(id) {
    const paramDiv = document.getElementById(`param_${id}`);
    if (paramDiv) {
        paramDiv.remove();
    }
    
    const container = document.getElementById("kt_function_parameters");
    if (container.children.length === 0) {
        container.innerHTML = '<div class="text-muted fs-7 mb-3">Nenhum parâmetro adicionado. Clique em "Adicionar Parâmetro" para adicionar.</div>';
    }
}

// Atualizar campos de config baseado no tipo
function updateConfigFields() {
    const toolType = document.getElementById("kt_tool_type").value;
    const configSection = document.getElementById("kt_config_section");
    const configFields = document.getElementById("kt_config_fields");
    
    if (!toolType || !toolTypeConfigs[toolType] || toolTypeConfigs[toolType].fields.length === 0) {
        configSection.style.display = "none";
        return;
    }
    
    configSection.style.display = "block";
    configFields.innerHTML = "";
    
    const fields = toolTypeConfigs[toolType].fields;
    fields.forEach(field => {
        const fieldDiv = document.createElement("div");
        fieldDiv.className = "fv-row mb-5";
        fieldDiv.id = `field_wrapper_${field.name}`;
        if (field.showIf) fieldDiv.dataset.showIf = field.showIf;
        
        let inputHtml = "";
        let labelHtml = `<label class="fw-semibold fs-7 mb-2">${field.label}${field.required ? " <span class=\\"text-danger\\">*</span>" : ""}</label>`;
        
        if (field.type === "checkbox") {
            inputHtml = `
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input config-field" data-field="${field.name}" id="config_${field.name}" ${field.default ? "checked" : ""} />
                    <label class="form-check-label fw-semibold fs-7" for="config_${field.name}">${field.label}</label>
                </div>
                ${field.help ? `<div class="form-text text-muted">${field.help}</div>` : ""}
            `;
            fieldDiv.innerHTML = inputHtml;
        } else if (field.type === "select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" ${field.required ? "required" : ""}>`;
            inputHtml += `<option value="">Selecione...</option>`;
            if (field.options) {
                field.options.forEach(opt => {
                    if (typeof opt === "object") {
                        inputHtml += `<option value="${opt.value}" ${opt.value === field.default ? "selected" : ""}>${opt.label}</option>`;
                    } else {
                        inputHtml += `<option value="${opt}" ${opt === field.default ? "selected" : ""}>${opt}</option>`;
                    }
                });
            }
            inputHtml += `</select>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "department_select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" ${field.required ? "required" : ""}>`;
            inputHtml += `<option value="">Selecione o setor...</option>`;
            availableDepartments.forEach(dept => {
                inputHtml += `<option value="${dept.id}">${dept.name}</option>`;
            });
            inputHtml += `</select>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "agent_select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" ${field.required ? "required" : ""}>`;
            inputHtml += `<option value="">Selecione o agente...</option>`;
            availableAgents.forEach(agent => {
                inputHtml += `<option value="${agent.id}">${agent.name} (${agent.email})</option>`;
            });
            inputHtml += `</select>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "funnel_select") {
            const stageSelectId = field.dependsOn ? "config_" + field.name.replace("funnel", "stage") : "config_stage_id";
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" id="config_${field.name}" ${field.required ? "required" : ""} onchange="loadFunnelStages(this.value, '${stageSelectId}')">`;
            inputHtml += `<option value="">Selecione o funil...</option>`;
            availableFunnels.forEach(funnel => {
                inputHtml += `<option value="${funnel.id}">${funnel.name}</option>`;
            });
            inputHtml += `</select>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "funnel_multi_select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" id="config_${field.name}" multiple size="5">`;
            availableFunnels.forEach(funnel => {
                inputHtml += `<option value="${funnel.id}">${funnel.name}</option>`;
            });
            inputHtml += `</select>`;
            inputHtml += `<div class="form-text text-muted">Segure Ctrl para selecionar múltiplos</div>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "stage_select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" id="config_${field.name}" ${field.required ? "required" : ""} disabled>`;
            inputHtml += `<option value="">Selecione o funil primeiro...</option>`;
            inputHtml += `</select>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "textarea") {
            inputHtml = `<textarea class="form-control form-control-solid config-field" data-field="${field.name}" rows="3" ${field.required ? "required" : ""} placeholder="${field.placeholder || ""}"></textarea>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else {
            inputHtml = `<input type="${field.type}" class="form-control form-control-solid config-field" data-field="${field.name}" ${field.required ? "required" : ""} placeholder="${field.placeholder || ""}" value="${field.default || ""}" />`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        }
        
        configFields.appendChild(fieldDiv);
    });
    
    // Adicionar event listeners para campos condicionais
    setupConditionalFields();
}

// Configurar campos condicionais (showIf)
function setupConditionalFields() {
    const configFields = document.querySelectorAll(".config-field");
    configFields.forEach(field => {
        field.addEventListener("change", updateConditionalVisibility);
    });
    updateConditionalVisibility();
}

// Atualizar visibilidade de campos condicionais
function updateConditionalVisibility() {
    const wrappers = document.querySelectorAll("[data-show-if]");
    wrappers.forEach(wrapper => {
        const condition = wrapper.dataset.showIf;
        const [fieldName, expectedValues] = condition.split(":");
        const values = expectedValues.split(",");
        
        const field = document.querySelector(`[data-field="${fieldName}"]`);
        if (field) {
            let currentValue = field.type === "checkbox" ? field.checked.toString() : field.value;
            wrapper.style.display = values.includes(currentValue) ? "block" : "none";
        }
    });
}

// Carregar etapas do funil selecionado
async function loadFunnelStages(funnelId, stageSelectId = "config_stage_id") {
    const stageSelect = document.getElementById(stageSelectId);
    if (!stageSelect) {
        // Tentar encontrar qualquer select de stage
        const allStageSelects = document.querySelectorAll("[data-field$='stage_id']");
        if (allStageSelects.length === 0) return;
    }
    
    const targetSelect = stageSelect || document.querySelector("[data-field$='stage_id']");
    if (!targetSelect) return;
    
    if (!funnelId) {
        targetSelect.innerHTML = `<option value="">Selecione o funil primeiro...</option>`;
        targetSelect.disabled = true;
        return;
    }
    
    targetSelect.innerHTML = `<option value="">Carregando...</option>`;
    targetSelect.disabled = true;
    
    try {
        const response = await fetch(`/funnels/${funnelId}/stages/json`);
        const data = await response.json();
        
        targetSelect.innerHTML = `<option value="">Selecione a etapa...</option>`;
        if (data.stages && data.stages.length > 0) {
            data.stages.forEach(stage => {
                targetSelect.innerHTML += `<option value="${stage.id}">${stage.name}</option>`;
            });
            targetSelect.disabled = false;
        } else {
            targetSelect.innerHTML = `<option value="">Nenhuma etapa encontrada</option>`;
        }
    } catch (error) {
        console.error("Erro ao carregar etapas:", error);
        targetSelect.innerHTML = `<option value="">Erro ao carregar etapas</option>`;
    }
}

// Construir JSON do function schema
function buildFunctionSchema() {
    const functionName = document.getElementById("kt_function_name").value;
    const functionDescription = document.getElementById("kt_function_description").value;
    
    if (!functionName || !functionDescription) {
        return null;
    }
    
    const properties = {};
    const required = [];
    
    document.querySelectorAll("#kt_function_parameters .card").forEach(paramDiv => {
        const name = paramDiv.querySelector(".param-name").value.trim();
        const type = paramDiv.querySelector(".param-type").value;
        const description = paramDiv.querySelector(".param-description").value.trim();
        const isRequired = paramDiv.querySelector(".param-required").checked;
        
        if (name && description) {
            properties[name] = {
                type: type,
                description: description
            };
            
            if (isRequired) {
                required.push(name);
            }
        }
    });
    
    // Garantir que properties é sempre um objeto (não array vazio)
    const params = {
        type: "object",
        properties: Object.keys(properties).length > 0 ? properties : {},
        required: required // Sempre incluir required, mesmo vazio
    };
    
    return {
        type: "function",
        function: {
            name: functionName,
            description: functionDescription,
            parameters: params
        }
    };
}

// Construir JSON do config
function buildConfig() {
    const config = {};
    let hasConfig = false;
    
    document.querySelectorAll(".config-field").forEach(field => {
        const fieldName = field.dataset.field;
        
        // Tratar checkbox separadamente
        if (field.type === "checkbox") {
            config[fieldName] = field.checked;
            if (field.checked) hasConfig = true;
            return;
        }
        
        const value = field.value.trim();
        
        if (value) {
            // Tentar converter para número se for campo numérico
            if (field.type === "number") {
                config[fieldName] = value ? parseFloat(value) : null;
            } else if (field.tagName === "TEXTAREA" && (fieldName === "headers" || fieldName === "custom_headers")) {
                // Tentar parsear JSON para headers
                try {
                    config[fieldName] = JSON.parse(value);
                } catch (e) {
                    config[fieldName] = value;
                }
            } else {
                config[fieldName] = value;
            }
            hasConfig = true;
        }
    });
    
    return hasConfig ? config : null;
}

document.addEventListener("DOMContentLoaded", function() {
    // Atualizar campos de config quando tipo mudar
    const toolTypeSelect = document.getElementById("kt_tool_type");
    if (toolTypeSelect) {
        toolTypeSelect.addEventListener("change", updateConfigFields);
    }
    
    const form = document.getElementById("kt_modal_new_ai_tool_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("kt_modal_new_ai_tool_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            // Construir JSONs
            const functionSchema = buildFunctionSchema();
            if (!functionSchema) {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro: Preencha o nome e descrição da função.");
                return;
            }
            
            const config = buildConfig();
            
            // Preencher campos hidden
            document.getElementById("kt_function_schema_json").value = JSON.stringify(functionSchema);
            if (config) {
                document.getElementById("kt_config_json").value = JSON.stringify(config);
            } else {
                document.getElementById("kt_config_json").value = "";
            }
            
            const formData = new FormData(form);
            
            // Converter enabled para boolean
            const enabled = formData.get("enabled") === "on" || formData.get("enabled") === "true";
            formData.set("enabled", enabled ? "1" : "0");
            
            fetch("<?= \App\Helpers\Url::to('/ai-tools') ?>", {
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
                    alert(data.message || "Tool criada com sucesso!");
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar tool"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                console.error("Erro:", error);
                alert("Erro ao criar tool");
            });
        });
    }
});
</script>
<?php 
$scripts = ob_get_clean();
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

