<?php
$layout = 'layouts.metronic.app';
$title = 'Tool de IA: ' . htmlspecialchars($tool['name'] ?? 'Tool');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0"><?= htmlspecialchars($tool['name']) ?></h3>
        </div>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="btn btn-sm btn-light me-2">
                <i class="ki-duotone ki-arrow-left fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Voltar
            </a>
            <?php if (\App\Helpers\Permission::can('ai_tools.edit')): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_edit_ai_tool">
                <i class="ki-duotone ki-pencil fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Editar
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="mb-5">
                    <label class="fw-semibold fs-6 mb-2">Slug</label>
                    <div class="text-gray-800"><?= htmlspecialchars($tool['slug']) ?></div>
                </div>
                
                <div class="mb-5">
                    <label class="fw-semibold fs-6 mb-2">Tipo</label>
                    <div>
                        <span class="badge badge-light-primary"><?= htmlspecialchars($tool['tool_type']) ?></span>
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="fw-semibold fs-6 mb-2">Status</label>
                    <div>
                        <?php if ($tool['enabled']): ?>
                            <span class="badge badge-light-success">Ativa</span>
                        <?php else: ?>
                            <span class="badge badge-light-secondary">Inativa</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="mb-5">
                    <label class="fw-semibold fs-6 mb-2">Criado em</label>
                    <div class="text-gray-800"><?= \App\Helpers\Url::formatDateTime($tool['created_at']) ?></div>
                </div>
                
                <div class="mb-5">
                    <label class="fw-semibold fs-6 mb-2">Atualizado em</label>
                    <div class="text-gray-800"><?= \App\Helpers\Url::formatDateTime($tool['updated_at']) ?></div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($tool['description'])): ?>
        <div class="mb-5">
            <label class="fw-semibold fs-6 mb-2">Descrição</label>
            <div class="text-gray-800"><?= nl2br(htmlspecialchars($tool['description'])) ?></div>
        </div>
        <?php endif; ?>
        
        <div class="mb-5">
            <label class="fw-semibold fs-6 mb-2">Function Schema</label>
            <div class="bg-light p-5 rounded">
                <pre class="text-gray-800 fs-6" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;"><?= htmlspecialchars(json_encode($tool['function_schema'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        </div>
        
        <?php if (!empty($tool['config'])): ?>
        <div class="mb-5">
            <label class="fw-semibold fs-6 mb-2">Configuração</label>
            <div class="bg-light p-5 rounded">
                <pre class="text-gray-800 fs-6" style="white-space: pre-wrap; max-height: 300px; overflow-y: auto;"><?= htmlspecialchars(json_encode($tool['config'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($tool['tool_type'] === 'n8n'): ?>
        <!--begin::Seção de Teste N8N-->
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <label class="fw-semibold fs-6 mb-0">Testar Webhook N8N</label>
                <button type="button" class="btn btn-sm btn-primary" id="btn_test_n8n">
                    <i class="ki-duotone ki-play fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Testar Disparo
                </button>
            </div>
            
            <div class="card bg-light" id="n8n_test_panel" style="display: none;">
                <div class="card-body">
                    <!--begin::Simulação de Mensagem-->
                    <div class="alert alert-info d-flex align-items-center mb-5">
                        <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-info">Simulação de Conversa</h4>
                            <span>Preencha a mensagem do cliente para simular como a IA enviaria para o N8N</span>
                        </div>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">💬 Mensagem do Cliente (simulação)</label>
                            <textarea class="form-control" id="test_client_message" rows="2" placeholder="Ex: Qual o preço do produto X?"></textarea>
                            <div class="form-text">Simula a mensagem que o cliente enviaria.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">🔑 ID da Conversa</label>
                            <input type="text" class="form-control" id="test_conversation_id" value="test-conv-12345" placeholder="ID único da conversa">
                            <div class="form-text">Para memória do agente no N8N</div>
                        </div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    <!--end::Simulação de Mensagem-->
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Webhook ID</label>
                        <input type="text" class="form-control" id="test_webhook_id" placeholder="Deixe vazio para usar o padrão">
                        <div class="form-text">ID do webhook ou URL completa. Método: <strong>POST</strong> (igual à execução real)</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Dados Adicionais (JSON) - Opcional</label>
                        <textarea class="form-control" id="test_data" rows="4" placeholder="{&quot;produto&quot;: &quot;caneta azul&quot;, &quot;loja&quot;: &quot;matriz&quot;}"></textarea>
                        <div class="form-text">Dados extras que seriam passados pela IA como argumentos da função</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Headers Customizados (JSON) - Opcional</label>
                        <textarea class="form-control" id="test_headers" rows="3" placeholder="{&quot;X-Custom-Header&quot;: &quot;value&quot;}"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-2" id="btn_cancel_test">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btn_execute_test">
                            <span class="indicator-label">Executar Teste</span>
                            <span class="indicator-progress">Aguardando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                    
                    <div id="test_result" class="mt-5" style="display: none;">
                        <div class="separator separator-dashed mb-5"></div>
                        <h5 class="fw-semibold mb-3">Resultado do Teste</h5>
                        <div id="test_result_content"></div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Seção de Teste N8N-->
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<!--begin::Modal - Editar Tool-->
<?php if (\App\Helpers\Permission::can('ai_tools.edit')): ?>
<div class="modal fade" id="kt_modal_edit_ai_tool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Tool de IA</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_ai_tool_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <input type="hidden" name="tool_id" value="<?= $tool['id'] ?>" />
                    
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" value="<?= htmlspecialchars($tool['name']) ?>" required />
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Slug</label>
                        <input type="text" name="slug" class="form-control form-control-solid" value="<?= htmlspecialchars($tool['slug']) ?>" required />
                        <div class="form-text">Identificador único da tool (sem espaços, use underscore)</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3"><?= htmlspecialchars($tool['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tipo</label>
                        <select name="tool_type" id="kt_edit_tool_type" class="form-select form-select-solid" required>
                            <option value="">Selecione o tipo</option>
                            <option value="woocommerce" <?= ($tool['tool_type'] ?? '') === 'woocommerce' ? 'selected' : '' ?>>WooCommerce</option>
                            <option value="database" <?= ($tool['tool_type'] ?? '') === 'database' ? 'selected' : '' ?>>Database</option>
                            <option value="n8n" <?= ($tool['tool_type'] ?? '') === 'n8n' ? 'selected' : '' ?>>N8N</option>
                            <option value="document" <?= ($tool['tool_type'] ?? '') === 'document' ? 'selected' : '' ?>>Document</option>
                            <option value="system" <?= ($tool['tool_type'] ?? '') === 'system' ? 'selected' : '' ?>>System</option>
                            <option value="api" <?= ($tool['tool_type'] ?? '') === 'api' ? 'selected' : '' ?>>API</option>
                            <option value="followup" <?= ($tool['tool_type'] ?? '') === 'followup' ? 'selected' : '' ?>>Followup</option>
                        </select>
                    </div>
                    
                    <!-- Function Schema Fields -->
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Function Schema</label>
                        <div class="card bg-light p-5">
                            <div class="fv-row mb-5">
                                <label class="required fw-semibold fs-7 mb-2">Nome da Função</label>
                                <input type="text" id="kt_edit_function_name" class="form-control form-control-solid" placeholder="Ex: buscar_pedido_woocommerce" required />
                                <div class="form-text">Nome único da função (use underscore, sem espaços)</div>
                            </div>
                            
                            <div class="fv-row mb-5">
                                <label class="required fw-semibold fs-7 mb-2">Descrição da Função</label>
                                <textarea id="kt_edit_function_description" class="form-control form-control-solid" rows="2" placeholder="Descreva o que esta função faz" required></textarea>
                                <div class="form-text">Descrição clara do propósito da função</div>
                            </div>
                            
                            <div class="fv-row mb-5">
                                <label class="fw-semibold fs-7 mb-3">Parâmetros</label>
                                <div id="kt_edit_function_parameters">
                                    <div class="text-muted fs-7 mb-3">Nenhum parâmetro adicionado. Clique em "Adicionar Parâmetro" para adicionar.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light-primary" onclick="addEditFunctionParameter()">
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
                    <div class="fv-row mb-7" id="kt_edit_config_section" style="display: none;">
                        <label class="fw-semibold fs-6 mb-2">Configurações</label>
                        <div class="card bg-light p-5" id="kt_edit_config_fields">
                            <!-- Campos serão inseridos dinamicamente via JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Hidden fields para JSON -->
                    <input type="hidden" name="function_schema" id="kt_edit_function_schema_json" />
                    <input type="hidden" name="config" id="kt_edit_config_json" />
                    
                    <?php
                    // Preparar dados para JavaScript
                    $functionSchema = $tool['function_schema'] ?? [];
                    if (is_string($functionSchema)) {
                        $functionSchema = json_decode($functionSchema, true) ?? [];
                    }
                    $config = $tool['config'] ?? [];
                    if (is_string($config)) {
                        $config = json_decode($config, true) ?? [];
                    }
                    ?>
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="enabled" class="form-check-input me-2" <?= ($tool['enabled'] ?? false) ? 'checked' : '' ?> />
                            Tool ativa
                        </label>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_ai_tool_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Alterações</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Editar Tool-->

<?php 
$content = ob_get_clean(); 

// Preparar JSON para injetar no script - double encode para usar com JSON.parse()
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
// Double encode: primeiro converte para JSON, depois converte a string JSON para string JS segura
$aiToolsBaseUrl = json_encode(json_encode(\App\Helpers\Url::to('/ai-tools')), $jsonFlags);
$functionSchemaJson = json_encode(json_encode($functionSchema, JSON_UNESCAPED_UNICODE), $jsonFlags);
$configJson = json_encode(json_encode($config, JSON_UNESCAPED_UNICODE), $jsonFlags);
$toolIdJson = json_encode($tool['id'] ?? 0, $jsonFlags);

$scripts = <<<'SCRIPTS'
<script>
// URL base para requisições
const AI_TOOLS_BASE_URL = JSON.parse(__AI_TOOLS_BASE_URL__);

let editParameterCounter = 0;

// Configurações por tipo de tool (mesmo do index.php)
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
            { name: "timeout", label: "Timeout (segundos)", type: "number", required: false, placeholder: "60", default: "60" },
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
        fields: [
            { name: "escalation_type", label: "Tipo de Escalação", type: "select", required: false, options: ["auto", "department", "agent", "round_robin", "funnel_stage"], default: "auto", help: "auto: Sistema decide | department: Setor específico | agent: Agente específico | round_robin: Distribuição | funnel_stage: Automação via etapa" },
            { name: "department_id", label: "Setor (se escalation_type = department)", type: "number", required: false, placeholder: "ID do setor" },
            { name: "agent_id", label: "Agente (se escalation_type = agent)", type: "number", required: false, placeholder: "ID do agente" },
            { name: "funnel_stage_id", label: "Etapa do Funil (se escalation_type = funnel_stage)", type: "number", required: false, placeholder: "ID da etapa", help: "Mover para esta etapa e usar automação dela" },
            { name: "priority", label: "Prioridade da Conversa", type: "select", required: false, options: ["low", "normal", "high", "urgent"], default: "normal", help: "Prioridade ao escalar para humano" },
            { name: "add_escalation_note", label: "Adicionar nota de escalação", type: "checkbox", required: false, default: true, help: "Adiciona nota interna explicando motivo da escalação" },
            { name: "notify_agent", label: "Notificar agente via WhatsApp/Email", type: "checkbox", required: false, default: false, help: "Envia notificação externa ao agente atribuído" },
            { name: "send_transition_message", label: "Enviar mensagem de transição ao cliente", type: "checkbox", required: false, default: true, help: "Envia mensagem informando que será transferido" },
            { name: "transition_message", label: "Mensagem de transição", type: "textarea", required: false, placeholder: "Vou transferir você para um de nossos especialistas...", default: "Vou transferir você para um de nossos especialistas. Aguarde um momento, por favor.", help: "Mensagem enviada ao cliente antes da transferência" }
        ]
    },
    followup: {
        fields: []
    }
};

// Adicionar parâmetro à função (edição)
function addEditFunctionParameter() {
    editParameterCounter++;
    const container = document.getElementById("kt_edit_function_parameters");
    
    if (container.querySelector(".text-muted")) {
        container.innerHTML = "";
    }
    
    const paramDiv = document.createElement("div");
    paramDiv.className = "card mb-3 p-4";
    paramDiv.id = `edit_param_${editParameterCounter}`;
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
                <button type="button" class="btn btn-sm btn-light-danger w-100" onclick="removeEditFunctionParameter(${editParameterCounter})">
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

// Remover parâmetro (edição)
function removeEditFunctionParameter(id) {
    const paramDiv = document.getElementById(`edit_param_${id}`);
    if (paramDiv) {
        paramDiv.remove();
    }
    
    const container = document.getElementById("kt_edit_function_parameters");
    if (container.children.length === 0) {
        container.innerHTML = '<div class="text-muted fs-7 mb-3">Nenhum parâmetro adicionado. Clique em "Adicionar Parâmetro" para adicionar.</div>';
    }
}

// Atualizar campos de config baseado no tipo (edição)
function updateEditConfigFields() {
    const toolType = document.getElementById("kt_edit_tool_type").value;
    const configSection = document.getElementById("kt_edit_config_section");
    const configFields = document.getElementById("kt_edit_config_fields");
    
    console.log("updateEditConfigFields chamado para tipo:", toolType);
    console.log("Configs disponíveis:", toolTypeConfigs[toolType]);
    
    if (!toolType || !toolTypeConfigs[toolType] || toolTypeConfigs[toolType].fields.length === 0) {
        console.log("Sem campos de config para este tipo");
        configSection.style.display = "none";
        return;
    }
    
    configSection.style.display = "block";
    configFields.innerHTML = "";
    
    const fields = toolTypeConfigs[toolType].fields;
    console.log(`Renderizando ${fields.length} campos de config para ${toolType}`);
    
    fields.forEach(field => {
        const fieldDiv = document.createElement("div");
        fieldDiv.className = "fv-row mb-5";
        
        let inputHtml = "";
        let labelHtml = "";
        
        if (field.type === "checkbox") {
            // Checkbox com label inline
            inputHtml = `
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input config-field" data-field="${field.name}" id="edit_config_${field.name}" ${field.default ? "checked" : ""} />
                    <label class="form-check-label fw-semibold fs-7" for="edit_config_${field.name}">${field.label}</label>
                </div>
                ${field.help ? `<div class="form-text text-muted">${field.help}</div>` : ""}
            `;
            fieldDiv.innerHTML = inputHtml;
        } else if (field.type === "select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" ${field.required ? "required" : ""}>`;
            if (field.options) {
                field.options.forEach(opt => {
                    inputHtml += `<option value="${opt}" ${opt === field.default ? "selected" : ""}>${opt}</option>`;
                });
            }
            inputHtml += `</select>`;
            labelHtml = `<label class="fw-semibold fs-7 mb-2">${field.label}${field.required ? " <span class=\"text-danger\">*</span>" : ""}</label>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "textarea") {
            inputHtml = `<textarea class="form-control form-control-solid config-field" data-field="${field.name}" rows="3" ${field.required ? "required" : ""} placeholder="${field.placeholder || ""}"></textarea>`;
            labelHtml = `<label class="fw-semibold fs-7 mb-2">${field.label}${field.required ? " <span class=\"text-danger\">*</span>" : ""}</label>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else {
            inputHtml = `<input type="${field.type}" class="form-control form-control-solid config-field" data-field="${field.name}" ${field.required ? "required" : ""} placeholder="${field.placeholder || ""}" value="${field.default || ""}" />`;
            labelHtml = `<label class="fw-semibold fs-7 mb-2">${field.label}${field.required ? " <span class=\"text-danger\">*</span>" : ""}</label>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        }
        
        configFields.appendChild(fieldDiv);
        console.log(`Campo ${field.name} adicionado`);
    });
    
    console.log("Campos de config renderizados com sucesso");
}

// Construir JSON do function schema (edição)
function buildEditFunctionSchema() {
    const functionName = document.getElementById("kt_edit_function_name").value;
    const functionDescription = document.getElementById("kt_edit_function_description").value;
    
    if (!functionName || !functionDescription) {
        return null;
    }
    
    const properties = {};
    const required = [];
    
    document.querySelectorAll("#kt_edit_function_parameters .card").forEach(paramDiv => {
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

// Construir JSON do config (edição)
function buildEditConfig() {
    const config = {};
    let hasConfig = false;
    
    document.querySelectorAll("#kt_edit_config_fields .config-field").forEach(field => {
        const fieldName = field.dataset.field;
        
        // Tratar checkbox separadamente
        if (field.type === "checkbox") {
            config[fieldName] = field.checked;
            if (field.checked) hasConfig = true;
            return;
        }
        
        const value = field.value.trim();
        
        if (value) {
            if (field.type === "number") {
                config[fieldName] = value ? parseFloat(value) : null;
            } else if (field.tagName === "TEXTAREA" && (fieldName === "headers" || fieldName === "custom_headers")) {
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

// Preencher campos ao abrir modal de edição
function populateEditFields() {
    const functionSchema = JSON.parse(__FUNCTION_SCHEMA__);
    const config = JSON.parse(__CONFIG__);
    
    if (functionSchema && functionSchema.function) {
        document.getElementById("kt_edit_function_name").value = functionSchema.function.name || "";
        document.getElementById("kt_edit_function_description").value = functionSchema.function.description || "";
        
        // Limpar parâmetros existentes
        document.getElementById("kt_edit_function_parameters").innerHTML = '<div class="text-muted fs-7 mb-3">Nenhum parâmetro adicionado. Clique em "Adicionar Parâmetro" para adicionar.</div>';
        editParameterCounter = 0;
        
        // Preencher parâmetros
        if (functionSchema.function.parameters && functionSchema.function.parameters.properties) {
            const properties = functionSchema.function.parameters.properties;
            const required = functionSchema.function.parameters.required || [];
            
            Object.keys(properties).forEach(paramName => {
                const param = properties[paramName];
                addEditFunctionParameter();
                const container = document.getElementById("kt_edit_function_parameters");
                const lastParam = container.lastElementChild;
                if (lastParam && lastParam.classList.contains("card")) {
                    lastParam.querySelector(".param-name").value = paramName;
                    lastParam.querySelector(".param-type").value = param.type || "string";
                    lastParam.querySelector(".param-description").value = param.description || "";
                    lastParam.querySelector(".param-required").checked = required.includes(paramName);
                }
            });
        }
    }
    
    // Atualizar campos de config - FORÇAR atualização
    const toolType = document.getElementById("kt_edit_tool_type").value;
    console.log("Tool Type no modal:", toolType);
    console.log("Config a preencher:", config);
    
    if (toolType) {
        // Renderizar campos de configuração
        updateEditConfigFields();
        
        // Preencher valores de config após renderizar os campos
        if (config && Object.keys(config).length > 0) {
            setTimeout(() => {
                console.log("Preenchendo campos de config...");
                Object.keys(config).forEach(key => {
                    const field = document.querySelector(`#kt_edit_config_fields .config-field[data-field="${key}"]`);
                    console.log(`Campo ${key}:`, field, "Valor:", config[key]);
                    if (field) {
                        // Tratar checkbox separadamente
                        if (field.type === "checkbox") {
                            field.checked = !!config[key];
                        } else if (field.tagName === "TEXTAREA" && typeof config[key] === "object") {
                            field.value = JSON.stringify(config[key], null, 2);
                        } else if (field.tagName === "TEXTAREA" && typeof config[key] === "string") {
                            // Se já é string, usar direto
                            field.value = config[key];
                        } else {
                            field.value = config[key] || "";
                        }
                    } else {
                        console.warn(`Campo não encontrado: ${key}`);
                    }
                });
            }, 200);
        } else {
            console.log("Nenhuma config para preencher");
        }
    } else {
        console.warn("Tool type não definido!");
    }
}

// Funções para teste N8N
function toggleN8NTestPanel() {
    const panel = document.getElementById("n8n_test_panel");
    if (panel) {
        panel.style.display = panel.style.display === "none" ? "block" : "none";
    }
}

function executeN8NTest() {
    const toolId = __TOOL_ID__;
    const webhookId = document.getElementById("test_webhook_id").value.trim();
    const method = "POST"; // Sempre POST, igual à execução real
    const clientMessage = document.getElementById("test_client_message").value.trim();
    const conversationId = document.getElementById("test_conversation_id").value.trim() || "test-conv-" + Date.now();
    const dataStr = document.getElementById("test_data").value.trim();
    const queryParamsStr = ""; // Não usado em POST
    const headersStr = document.getElementById("test_headers").value.trim();
    
    const executeBtn = document.getElementById("btn_execute_test");
    const resultDiv = document.getElementById("test_result");
    const resultContent = document.getElementById("test_result_content");
    
    // Validar e parsear JSONs
    let data = {};
    let queryParams = {};
    let headers = {};
    
    // Incluir ID da conversa (para memória do agente no N8N)
    data.conversation_id = conversationId;
    data.session_id = conversationId;
    data.thread_id = conversationId;
    
    // Incluir mensagem do cliente nos dados
    if (clientMessage) {
        data.message = clientMessage;
        data.client_message = clientMessage;
        data.text = clientMessage;
    }
    
    try {
        if (dataStr) {
            // Mesclar dados adicionais com a mensagem
            const additionalData = JSON.parse(dataStr);
            data = { ...data, ...additionalData };
        }
    } catch (e) {
        alert("Erro: Dados inválidos (não é um JSON válido)");
        return;
    }
    
    try {
        if (queryParamsStr) {
            queryParams = JSON.parse(queryParamsStr);
        }
    } catch (e) {
        alert("Erro: Query Params inválidos (não é um JSON válido)");
        return;
    }
    
    try {
        if (headersStr) {
            headers = JSON.parse(headersStr);
        }
    } catch (e) {
        alert("Erro: Headers inválidos (não é um JSON válido)");
        return;
    }
    
    // Preparar requisição
    executeBtn.setAttribute("data-kt-indicator", "on");
    executeBtn.disabled = true;
    resultDiv.style.display = "none";
    
    fetch(AI_TOOLS_BASE_URL + "/" + toolId + "/test-n8n", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            webhook_id: webhookId || null,
            method: method,
            data: data,
            query_params: queryParams,
            headers: headers
        })
    })
    .then(response => response.json())
    .then(result => {
        executeBtn.removeAttribute("data-kt-indicator");
        executeBtn.disabled = false;
        
        resultDiv.style.display = "block";
        
        let html = `
            <div class="card ${result.success ? "bg-light-success" : "bg-light-danger"}">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <i class="ki-duotone ${result.success ? "ki-check-circle" : "ki-cross-circle"} fs-2x ${result.success ? "text-success" : "text-danger"} me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div>
                            <h5 class="mb-0">${result.success ? "Sucesso!" : "Erro"}</h5>
                            <div class="text-muted">HTTP ${result.http_code || "N/A"}</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>URL:</strong> <code>${result.url || "N/A"}</code><br>
                        <strong>Método:</strong> <code>${result.method || "N/A"}</code><br>
                        ${result.curl_info ? `<strong>Tempo:</strong> ${(result.curl_info.total_time * 1000).toFixed(2)}ms<br>` : ""}
                        ${result.curl_info ? `<strong>Tamanho:</strong> ${(result.curl_info.size_download / 1024).toFixed(2)} KB` : ""}
                    </div>
                    
                    ${result.request ? `
                    <div class="mb-3">
                        <strong>Requisição Enviada:</strong>
                        <pre class="bg-white p-3 rounded mt-2" style="max-height: 200px; overflow-y: auto;">${JSON.stringify(result.request, null, 2)}</pre>
                    </div>
                    ` : ""}
                    
                    <div>
                        <strong>Resposta:</strong>
                        <pre class="bg-white p-3 rounded mt-2" style="max-height: 400px; overflow-y: auto;">${JSON.stringify(result.response || result, null, 2)}</pre>
                    </div>
                    
                    ${result.message ? `<div class="alert alert-${result.success ? "success" : "danger"} mt-3">${result.message}</div>` : ""}
                </div>
            </div>
        `;
        
        resultContent.innerHTML = html;
        
        // Scroll para resultado
        resultDiv.scrollIntoView({ behavior: "smooth", block: "nearest" });
    })
    .catch(error => {
        executeBtn.removeAttribute("data-kt-indicator");
        executeBtn.disabled = false;
        
        resultDiv.style.display = "block";
        resultContent.innerHTML = `
            <div class="alert alert-danger">
                <strong>Erro:</strong> ${error.message || "Erro ao executar teste"}
            </div>
        `;
    });
}

document.addEventListener("DOMContentLoaded", function() {
    // Preencher campos quando modal de edição for aberto
    const editModal = document.getElementById("kt_modal_edit_ai_tool");
    if (editModal) {
        editModal.addEventListener("shown.bs.modal", populateEditFields);
    }
    
    // Atualizar campos de config quando tipo mudar (edição)
    const toolTypeSelect = document.getElementById("kt_edit_tool_type");
    if (toolTypeSelect) {
        toolTypeSelect.addEventListener("change", updateEditConfigFields);
    }
    
    // Event listeners para teste N8N
    const btnTestN8N = document.getElementById("btn_test_n8n");
    if (btnTestN8N) {
        btnTestN8N.addEventListener("click", toggleN8NTestPanel);
    }
    
    const btnCancelTest = document.getElementById("btn_cancel_test");
    if (btnCancelTest) {
        btnCancelTest.addEventListener("click", function() {
            document.getElementById("n8n_test_panel").style.display = "none";
            document.getElementById("test_result").style.display = "none";
        });
    }
    
    const btnExecuteTest = document.getElementById("btn_execute_test");
    if (btnExecuteTest) {
        btnExecuteTest.addEventListener("click", executeN8NTest);
    }
    
    const form = document.getElementById("kt_modal_edit_ai_tool_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("kt_modal_edit_ai_tool_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            // Construir JSONs
            const functionSchema = buildEditFunctionSchema();
            if (!functionSchema) {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro: Preencha o nome e descrição da função.");
                return;
            }
            
            const config = buildEditConfig();
            
            // Preencher campos hidden
            document.getElementById("kt_edit_function_schema_json").value = JSON.stringify(functionSchema);
            if (config) {
                document.getElementById("kt_edit_config_json").value = JSON.stringify(config);
            } else {
                document.getElementById("kt_edit_config_json").value = "";
            }
            
            const formData = new FormData(form);
            const toolId = formData.get("tool_id");
            
            // Converter enabled para boolean
            const enabled = formData.get("enabled") === "on" || formData.get("enabled") === "true";
            formData.set("enabled", enabled ? "1" : "0");
            
            fetch(AI_TOOLS_BASE_URL + "/" + toolId, {
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
                    alert(data.message || "Tool atualizada com sucesso!");
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar tool"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                console.error("Erro:", error);
                alert("Erro ao atualizar tool");
            });
        });
    }
});
</script>
SCRIPTS;

$scripts = str_replace(
    ['__AI_TOOLS_BASE_URL__', '__FUNCTION_SCHEMA__', '__CONFIG__', '__TOOL_ID__'],
    [$aiToolsBaseUrl, $functionSchemaJson, $configJson, $toolIdJson],
    $scripts
);

// Debug: Log dos dados enviados
error_log("AI Tool Show - Tool ID: " . ($tool['id'] ?? 'N/A'));
error_log("AI Tool Show - Tool Type: " . ($tool['tool_type'] ?? 'N/A'));
error_log("AI Tool Show - Config JSON: " . $configJson);
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

