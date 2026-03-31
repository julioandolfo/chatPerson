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

<!--begin::Modal - Editar Tool (Wizard)-->
<?php if (\App\Helpers\Permission::can('ai_tools.edit')): ?>
<div class="modal fade" id="kt_modal_edit_ai_tool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bold mb-1">Editar Tool de IA</h2>
                    <span class="text-muted fs-7">Edite sua tool em 3 passos simples</span>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            
            <!-- Wizard Steps -->
            <div class="modal-body px-0 pb-0">
                <div class="px-10">
                    <!-- Stepper Horizontal -->
                    <div class="stepper stepper-links d-flex flex-column" id="kt_edit_tool_stepper">
                        <!-- Stepper Header (Horizontal) -->
                        <div class="stepper-nav d-flex justify-content-center py-5 mb-5 border-bottom">
                            <!-- Step 1 -->
                            <div class="stepper-item current mx-4" data-kt-stepper-element="nav" data-kt-stepper-item="1">
                                <div class="stepper-wrapper d-flex flex-column align-items-center">
                                    <div class="stepper-icon w-40px h-40px rounded-circle d-flex align-items-center justify-content-center bg-primary text-white mb-2">
                                        <i class="ki-duotone ki-check fs-2 stepper-check"></i>
                                        <span class="stepper-number">1</span>
                                    </div>
                                    <div class="stepper-label text-center">
                                        <h3 class="stepper-title fs-6 mb-1">Informações</h3>
                                        <div class="stepper-desc fs-7 text-muted">Nome e tipo</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stepper Line -->
                            <div class="stepper-line h-2px w-100px bg-gray-200 mt-5 mx-2"></div>
                            
                            <!-- Step 2 -->
                            <div class="stepper-item mx-4" data-kt-stepper-element="nav" data-kt-stepper-item="2">
                                <div class="stepper-wrapper d-flex flex-column align-items-center">
                                    <div class="stepper-icon w-40px h-40px rounded-circle d-flex align-items-center justify-content-center bg-gray-200 text-gray-600 mb-2">
                                        <i class="ki-duotone ki-check fs-2 stepper-check"></i>
                                        <span class="stepper-number">2</span>
                                    </div>
                                    <div class="stepper-label text-center">
                                        <h3 class="stepper-title fs-6 mb-1">Configurações</h3>
                                        <div class="stepper-desc fs-7 text-muted">Parâmetros</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stepper Line -->
                            <div class="stepper-line h-2px w-100px bg-gray-200 mt-5 mx-2"></div>
                            
                            <!-- Step 3 -->
                            <div class="stepper-item mx-4" data-kt-stepper-element="nav" data-kt-stepper-item="3">
                                <div class="stepper-wrapper d-flex flex-column align-items-center">
                                    <div class="stepper-icon w-40px h-40px rounded-circle d-flex align-items-center justify-content-center bg-gray-200 text-gray-600 mb-2">
                                        <i class="ki-duotone ki-check fs-2 stepper-check"></i>
                                        <span class="stepper-number">3</span>
                                    </div>
                                    <div class="stepper-label text-center">
                                        <h3 class="stepper-title fs-6 mb-1">Revisão</h3>
                                        <div class="stepper-desc fs-7 text-muted">Finalize</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Content -->
                        <div class="py-5 px-lg-10">
                            <form id="kt_modal_edit_ai_tool_form" class="form">
                                <input type="hidden" name="tool_id" value="<?= $tool['id'] ?>" />
                                
                                <!-- Step 1: Informações Básicas -->
                                <div class="current" data-kt-stepper-element="content" data-kt-stepper-item="1">
                                    <div class="w-100">
                                        <div class="pb-10 pb-lg-15">
                                            <h2 class="fw-bold d-flex align-items-center text-gray-900">
                                                <span class="me-3">Informações Básicas</span>
                                                <span class="badge badge-light-primary fs-7">Etapa 1 de 3</span>
                                            </h2>
                                            <div class="text-muted fw-semibold fs-6">Defina o nome, identificador e tipo da tool.</div>
                                        </div>
                                        
                                        <div class="fv-row mb-8">
                                            <label class="required fw-semibold fs-6 mb-2 d-flex align-items-center">
                                                <span>Nome da Tool</span>
                                                <i class="ki-duotone ki-information-5 ms-2 fs-6" data-bs-toggle="tooltip" title="Nome amigável que a IA usará para identificar esta tool"></i>
                                            </label>
                                            <div class="input-group input-group-solid">
                                                <span class="input-group-text"><i class="ki-duotone ki-tag fs-3"></i></span>
                                                <input type="text" name="name" id="edit_wizard_name" class="form-control form-control-lg" value="<?= htmlspecialchars($tool['name']) ?>" required />
                                            </div>
                                        </div>
                                        
                                        <div class="fv-row mb-8">
                                            <label class="required fw-semibold fs-6 mb-2 d-flex align-items-center">
                                                <span>Slug (Identificador)</span>
                                                <i class="ki-duotone ki-information-5 ms-2 fs-6" data-bs-toggle="tooltip" title="Identificador técnico único (sem espaços, use underscore)"></i>
                                            </label>
                                            <div class="input-group input-group-solid">
                                                <span class="input-group-text"><i class="ki-duotone ki-code fs-3"></i></span>
                                                <input type="text" name="slug" id="edit_wizard_slug" class="form-control form-control-lg" value="<?= htmlspecialchars($tool['slug']) ?>" required />
                                            </div>
                                        </div>
                                        
                                        <div class="fv-row mb-8">
                                            <label class="fw-semibold fs-6 mb-2">Descrição</label>
                                            <textarea name="description" id="edit_wizard_description" class="form-control form-control-solid" rows="3"><?= htmlspecialchars($tool['description'] ?? '') ?></textarea>
                                            <div class="form-text">Ajuda a IA a entender quando usar esta tool</div>
                                        </div>
                                        
                                        <div class="fv-row mb-8">
                                            <label class="required fw-semibold fs-6 mb-2">Tipo de Tool</label>
                                            <div class="row g-3" id="edit_tool_type_cards">
                                                <!-- Cards serão gerados via JS -->
                                            </div>
                                            <!-- Select hidden para armazenar o valor -->
                                            <select name="tool_type" id="kt_edit_tool_type" class="form-select form-select-solid d-none" required>
                                                <option value="">Selecione</option>
                                                <option value="woocommerce" <?= ($tool['tool_type'] ?? '') === 'woocommerce' ? 'selected' : '' ?>>WooCommerce</option>
                                                <option value="database" <?= ($tool['tool_type'] ?? '') === 'database' ? 'selected' : '' ?>>Database</option>
                                                <option value="n8n" <?= ($tool['tool_type'] ?? '') === 'n8n' ? 'selected' : '' ?>>N8N</option>
                                                <option value="document" <?= ($tool['tool_type'] ?? '') === 'document' ? 'selected' : '' ?>>Document</option>
                                                <option value="system" <?= ($tool['tool_type'] ?? '') === 'system' ? 'selected' : '' ?>>System</option>
                                                <option value="api" <?= ($tool['tool_type'] ?? '') === 'api' ? 'selected' : '' ?>>API</option>
                                                <option value="followup" <?= ($tool['tool_type'] ?? '') === 'followup' ? 'selected' : '' ?>>Followup</option>
                                                <option value="human_escalation" <?= ($tool['tool_type'] ?? '') === 'human_escalation' ? 'selected' : '' ?>>🧑‍💼 Escalar para Humano</option>
                                                <option value="funnel_stage" <?= ($tool['tool_type'] ?? '') === 'funnel_stage' ? 'selected' : '' ?>>📊 Mover para Funil/Etapa</option>
                                                <option value="funnel_stage_smart" <?= ($tool['tool_type'] ?? '') === 'funnel_stage_smart' ? 'selected' : '' ?>>🧠 Mover para Funil/Etapa (Inteligente)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Step 2: Configurações -->
                                <div data-kt-stepper-element="content" data-kt-stepper-item="2">
                                    <div class="w-100">
                                        <div class="pb-10 pb-lg-15">
                                            <h2 class="fw-bold d-flex align-items-center text-gray-900">
                                                <span class="me-3">Configurações</span>
                                                <span class="badge badge-light-primary fs-7">Etapa 2 de 3</span>
                                            </h2>
                                            <div class="text-muted fw-semibold fs-6">Configure os parâmetros específicos do tipo selecionado.</div>
                                        </div>
                                        
                                        <!-- Aviso de Function Schema Automático -->
                                        <div class="fv-row mb-7" id="kt_edit_auto_schema_notice" style="display: none;">
                                            <div class="alert alert-info d-flex align-items-center p-5">
                                                <i class="ki-duotone ki-information-5 fs-2x text-info me-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                <div>
                                                    <h4 class="mb-1 text-info">Function Schema Automático</h4>
                                                    <span id="kt_edit_auto_schema_message">O function schema será gerado automaticamente.</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Function Schema Manual -->
                                        <div class="fv-row mb-7" id="kt_edit_function_schema_section">
                                            <label class="fw-semibold fs-6 mb-4 d-flex align-items-center">
                                                <span>Function Schema</span>
                                                <span class="badge badge-light-danger ms-2">Avançado</span>
                                            </label>
                                            <div class="card border border-dashed border-primary bg-light p-5">
                                                <div class="fv-row mb-5">
                                                    <label class="required fw-semibold fs-7 mb-2">Nome da Função</label>
                                                    <input type="text" id="kt_edit_function_name" class="form-control form-control-solid" required />
                                                </div>
                                                
                                                <div class="fv-row mb-5">
                                                    <label class="required fw-semibold fs-7 mb-2">Descrição da Função</label>
                                                    <textarea id="kt_edit_function_description" class="form-control form-control-solid" rows="2" required></textarea>
                                                </div>
                                                
                                                <div class="fv-row mb-5">
                                                    <label class="fw-semibold fs-7 mb-3 d-flex justify-content-between">
                                                        <span>Parâmetros</span>
                                                        <span class="text-muted fs-8">Campos que a IA pode preencher</span>
                                                    </label>
                                                    <div id="kt_edit_function_parameters">
                                                        <div class="text-muted fs-7 mb-3 text-center py-4 bg-white rounded">
                                                            <i class="ki-duotone ki-plus-circle fs-2x mb-2 d-block"></i>
                                                            Nenhum parâmetro adicionado
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-light-primary mt-3" onclick="addEditFunctionParameter()">
                                                        <i class="ki-duotone ki-plus fs-2">
                                                            <span class="path1"></span>
                                                            <span class="path2"></span>
                                                        </i>
                                                        Adicionar Parâmetro
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Config Fields -->
                                        <div class="fv-row mb-7" id="kt_edit_config_section" style="display: none;">
                                            <label class="fw-semibold fs-6 mb-4 d-flex align-items-center">
                                                <i class="ki-duotone ki-setting-2 fs-4 me-2 text-primary"></i>
                                                <span>Configurações Específicas</span>
                                            </label>
                                            <div class="card bg-light p-5" id="kt_edit_config_fields"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Step 3: Revisão -->
                                <div data-kt-stepper-element="content" data-kt-stepper-item="3">
                                    <div class="w-100">
                                        <div class="pb-10 pb-lg-15">
                                            <h2 class="fw-bold d-flex align-items-center text-gray-900">
                                                <span class="me-3">Revisão</span>
                                                <span class="badge badge-light-success fs-7">Etapa 3 de 3</span>
                                            </h2>
                                            <div class="text-muted fw-semibold fs-6">Verifique as configurações antes de salvar.</div>
                                        </div>
                                        
                                        <!-- Resumo -->
                                        <div class="mb-8">
                                            <div class="card bg-light mb-5">
                                                <div class="card-body">
                                                    <h4 class="fw-bold mb-4 text-gray-800">
                                                        <i class="ki-duotone ki-information fs-2 me-2"></i>
                                                        Informações Básicas
                                                    </h4>
                                                    <div class="row g-5">
                                                        <div class="col-md-6">
                                                            <div class="text-muted fs-7 mb-1">Nome</div>
                                                            <div class="fw-semibold fs-6" id="edit_review_name">-</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted fs-7 mb-1">Slug</div>
                                                            <div class="fw-semibold fs-6" id="edit_review_slug">-</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted fs-7 mb-1">Tipo</div>
                                                            <div class="fw-semibold fs-6"><span class="badge badge-light-primary" id="edit_review_type">-</span></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted fs-7 mb-1">Status</div>
                                                            <div class="fw-semibold fs-6"><span class="badge badge-light-success">Ativa</span></div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4">
                                                        <div class="text-muted fs-7 mb-1">Descrição</div>
                                                        <div class="fw-semibold fs-6" id="edit_review_description">-</div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="card bg-light" id="edit_review_config_card" style="display: none;">
                                                <div class="card-body">
                                                    <h4 class="fw-bold mb-4 text-gray-800">
                                                        <i class="ki-duotone ki-setting-2 fs-2 me-2"></i>
                                                        Configurações
                                                    </h4>
                                                    <div id="edit_review_config_content"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields -->
                                <input type="hidden" name="function_schema" id="kt_edit_function_schema_json" />
                                <input type="hidden" name="config" id="kt_edit_config_json" />
                                <input type="hidden" name="enabled" value="1" />
                                
                                <!-- Actions -->
                                <div class="d-flex flex-stack pt-10">
                                    <div class="me-2">
                                        <button type="button" class="btn btn-lg btn-light-primary me-3" data-kt-stepper-action="previous">
                                            <i class="ki-duotone ki-arrow-left fs-3 me-1">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            Voltar
                                        </button>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-lg btn-primary" data-kt-stepper-action="next">
                                            <span class="indicator-label">
                                                Próximo
                                                <i class="ki-duotone ki-arrow-right fs-3 ms-2 me-0">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </span>
                                            <span class="indicator-progress">
                                                Aguarde...
                                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                            </span>
                                        </button>
                                        <button type="submit" class="btn btn-lg btn-success" data-kt-stepper-action="submit" style="display: none;">
                                            <span class="indicator-label">
                                                <i class="ki-duotone ki-check fs-3 me-2"></i>
                                                Salvar Alterações
                                            </span>
                                            <span class="indicator-progress">
                                                Salvando...
                                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
<?php endif; ?>
<!--end::Modal - Editar Tool-->

<?php 
$content = ob_get_clean(); 

// Preparar JavaScript para injetar no script
// Usar flags de segurança para evitar quebra de sintaxe JavaScript
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES;

ob_start();
?>
<script>
// URL base para requisições
const AI_TOOLS_BASE_URL = <?= json_encode(\App\Helpers\Url::to('/ai-tools'), $jsonFlags) ?>;
const FUNCTION_SCHEMA_DATA = <?= json_encode(!empty($functionSchema) ? $functionSchema : null, $jsonFlags) ?>;
const CONFIG_DATA = <?= json_encode(!empty($config) ? $config : null, $jsonFlags) ?>;
const TOOL_ID = <?= json_encode($tool['id'] ?? 0, $jsonFlags) ?>;

// Dados do backend para selects dinâmicos
<?php
$departmentsJson = json_encode($departments ?? [], $jsonFlags) ?: '[]';
$funnelsJson = json_encode($funnels ?? [], $jsonFlags) ?: '[]';
$agentsJson = json_encode($agents ?? [], $jsonFlags) ?: '[]';
?>
const availableDepartments = <?= $departmentsJson ?>;
const availableFunnels = <?= $funnelsJson ?>;
const availableAgents = <?= $agentsJson ?>;

let editParameterCounter = 0;

// Tipos que têm function schema automático (não precisam de configuração manual)
const autoSchemaTypes = {
    human_escalation: {
        message: "Esta tool irá escalar a conversa para um atendente humano. O function schema será gerado automaticamente.",
        defaultSchema: {
            type: "function",
            function: {
                name: "escalar_para_humano",
                description: "Escala a conversa para um atendente humano quando o cliente solicita ou quando a IA não consegue resolver",
                parameters: { type: "object", properties: { motivo: { type: "string", description: "Motivo da escalação" } }, required: ["motivo"] }
            }
        }
    },
    funnel_stage: {
        message: "Esta tool irá mover a conversa para uma etapa específica do funil. O function schema será gerado automaticamente.",
        defaultSchema: {
            type: "function",
            function: {
                name: "mover_para_etapa",
                description: "Move a conversa para uma etapa específica do funil de vendas",
                parameters: { type: "object", properties: { motivo: { type: "string", description: "Motivo para mover" } }, required: [] }
            }
        }
    },
    funnel_stage_smart: {
        message: "Esta tool irá analisar a conversa e decidir para qual etapa mover. O function schema será gerado automaticamente.",
        defaultSchema: {
            type: "function",
            function: {
                name: "analisar_e_mover_etapa",
                description: "Analisa o contexto e decide para qual etapa mover",
                parameters: { type: "object", properties: { contexto: { type: "string", description: "Contexto da conversa" } }, required: ["contexto"] }
            }
        }
    },
    followup: {
        message: "Esta tool irá agendar um follow-up para a conversa. O function schema será gerado automaticamente.",
        defaultSchema: {
            type: "function",
            function: {
                name: "agendar_followup",
                description: "Agenda um follow-up para retomar contato com o cliente",
                parameters: { type: "object", properties: { data_hora: { type: "string", description: "Data/hora do follow-up" }, mensagem: { type: "string", description: "Mensagem" } }, required: ["data_hora", "mensagem"] }
            }
        }
    }
};

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
            { name: "timeout", label: "Timeout (segundos)", type: "number", required: false, placeholder: "120", default: "120", help: "Tempo máximo de espera pela resposta do N8N. Use 180+ para fluxos complexos com IA." },
            { name: "custom_headers", label: "Headers Customizados (JSON)", type: "textarea", required: false, placeholder: '{"X-Custom-Header": "value"}' },
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
            { name: "escalation_type", label: "Tipo de Escalação", type: "select", required: false, 
              options: [
                { value: "auto", label: "Automático (fila geral)" },
                { value: "department", label: "Setor Específico" },
                { value: "agent", label: "Agente Específico" }
              ], default: "auto", help: "Automático: conversa vai para fila geral | Setor: distribui entre agentes do setor | Agente: atribui a agente específico" },
            { name: "department_id", label: "Setor", type: "department_select", required: false, 
              showIf: "escalation_type:department", help: "Selecione o setor de destino" },
            { name: "agent_id", label: "Agente", type: "agent_select", required: false, 
              showIf: "escalation_type:agent", help: "Selecione o agente que receberá a conversa" },
            { name: "priority", label: "Prioridade da Conversa", type: "select", required: false, 
              options: [
                { value: "low", label: "Baixa" },
                { value: "normal", label: "Normal" },
                { value: "high", label: "Alta" },
                { value: "urgent", label: "Urgente" }
              ], default: "normal", help: "Prioridade ao escalar para humano" },
            { name: "consider_availability", label: "Considerar disponibilidade (online)", type: "checkbox", required: false, default: true,
              showIf: "escalation_type:department", help: "Só atribui a agentes que estão online" },
            { name: "consider_limits", label: "Considerar limite máximo de conversas", type: "checkbox", required: false, default: true,
              showIf: "escalation_type:department", help: "Respeita o limite de conversas do agente" },
            { name: "force_assign", label: "Forçar atribuição (ignora regras)", type: "checkbox", required: false, default: false,
              showIf: "escalation_type:agent", help: "Atribui mesmo se o agente estiver offline ou no limite" },
            { name: "remove_ai_after", label: "Remover IA após escalação", type: "checkbox", required: false, default: true,
              help: "Remove o agente de IA da conversa após escalar" },
            { name: "send_notification", label: "Notificar agente humano", type: "checkbox", required: false, default: true },
            { name: "escalation_message", label: "Mensagem de transição ao cliente", type: "textarea", required: false,
              placeholder: "Vou transferir você para um de nossos especialistas...",
              default: "Vou transferir você para um de nossos especialistas. Aguarde um momento, por favor.",
              help: "Mensagem enviada ao cliente ao escalar (deixe vazio para a IA decidir)" }
        ]
    },
    followup: {
        fields: []
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
                { value: "by_pending_response", label: "Por Respostas Pendentes (cliente aguardando)" },
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

// Atualizar visibilidade do Function Schema baseado no tipo (edição)
function updateEditFunctionSchemaVisibility(toolType) {
    const schemaSection = document.getElementById("kt_edit_function_schema_section");
    const autoNotice = document.getElementById("kt_edit_auto_schema_notice");
    const autoMessage = document.getElementById("kt_edit_auto_schema_message");
    const functionNameInput = document.getElementById("kt_edit_function_name");
    const functionDescInput = document.getElementById("kt_edit_function_description");
    
    if (!schemaSection || !autoNotice) return;
    
    if (autoSchemaTypes[toolType]) {
        // Tipo com schema automático - ocultar campos manuais
        schemaSection.style.display = "none";
        autoNotice.style.display = "block";
        autoMessage.textContent = autoSchemaTypes[toolType].message;
        
        // Remover required dos inputs
        if (functionNameInput) functionNameInput.removeAttribute("required");
        if (functionDescInput) functionDescInput.removeAttribute("required");
        
        console.log("Schema automático para tipo:", toolType);
    } else {
        // Tipo customizável - mostrar campos manuais
        schemaSection.style.display = "block";
        autoNotice.style.display = "none";
        
        // Adicionar required nos inputs
        if (functionNameInput) functionNameInput.setAttribute("required", "required");
        if (functionDescInput) functionDescInput.setAttribute("required", "required");
        
        console.log("Schema manual para tipo:", toolType);
    }
}

// Atualizar campos de config baseado no tipo (edição)
function updateEditConfigFields() {
    const toolType = document.getElementById("kt_edit_tool_type").value;
    const configSection = document.getElementById("kt_edit_config_section");
    const configFields = document.getElementById("kt_edit_config_fields");
    
    console.log("updateEditConfigFields chamado para tipo:", toolType);
    
    // Atualizar visibilidade do function schema
    updateEditFunctionSchemaVisibility(toolType);
    
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
        fieldDiv.id = `edit_field_wrapper_${field.name}`;
        if (field.showIf) fieldDiv.dataset.showIf = field.showIf;
        
        let inputHtml = "";
        let labelHtml = `<label class="fw-semibold fs-7 mb-2">${field.label}${field.required ? ' <span class="text-danger">*</span>' : ""}</label>`;
        
        if (field.type === "checkbox") {
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
            (typeof availableDepartments !== 'undefined' ? availableDepartments : []).forEach(dept => {
                inputHtml += `<option value="${dept.id}">${dept.name}</option>`;
            });
            inputHtml += `</select>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "agent_select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" ${field.required ? "required" : ""}>`;
            inputHtml += `<option value="">Selecione o agente...</option>`;
            (typeof availableAgents !== 'undefined' ? availableAgents : []).forEach(agent => {
                inputHtml += `<option value="${agent.id}">${agent.name}${agent.email ? ` (${agent.email})` : ''}</option>`;
            });
            inputHtml += `</select>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "funnel_select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" id="edit_config_${field.name}" ${field.required ? "required" : ""}>`;
            inputHtml += `<option value="">Selecione o funil...</option>`;
            (typeof availableFunnels !== 'undefined' ? availableFunnels : []).forEach(funnel => {
                inputHtml += `<option value="${funnel.id}">${funnel.name}</option>`;
            });
            inputHtml += `</select>`;
            fieldDiv.innerHTML = labelHtml + inputHtml + (field.help ? `<div class="form-text text-muted">${field.help}</div>` : "");
        } else if (field.type === "stage_select") {
            inputHtml = `<select class="form-control form-control-solid config-field" data-field="${field.name}" id="edit_config_${field.name}" ${field.required ? "required" : ""} disabled>`;
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
    
    // Configurar campos condicionais (showIf)
    setupEditConditionalFields();
    
    // Adicionar listener para desabilitar "consider_availability" quando método é "by_pending_response"
    const distributionMethodField = document.querySelector('[data-field="distribution_method"]');
    if (distributionMethodField) {
        distributionMethodField.addEventListener('change', function() {
            const considerAvailabilityCheckbox = document.querySelector('[data-field="consider_availability"]');
            const considerAvailabilityWrapper = considerAvailabilityCheckbox?.closest('.fv-row');
            
            if (this.value === 'by_pending_response') {
                if (considerAvailabilityCheckbox) {
                    considerAvailabilityCheckbox.checked = false;
                    considerAvailabilityCheckbox.disabled = true;
                }
                if (considerAvailabilityWrapper) {
                    considerAvailabilityWrapper.style.opacity = '0.5';
                    considerAvailabilityWrapper.title = 'Método "Por Respostas Pendentes" não verifica disponibilidade online';
                }
            } else {
                if (considerAvailabilityCheckbox) {
                    considerAvailabilityCheckbox.disabled = false;
                }
                if (considerAvailabilityWrapper) {
                    considerAvailabilityWrapper.style.opacity = '1';
                    considerAvailabilityWrapper.title = '';
                }
            }
        });
        
        // Disparar evento inicial para configurar estado correto
        distributionMethodField.dispatchEvent(new Event('change'));
    }
    
    console.log("Campos de config renderizados com sucesso");
}

// Configurar campos condicionais (showIf) na edição
function setupEditConditionalFields() {
    const configFields = document.querySelectorAll("#kt_edit_config_fields .config-field");
    configFields.forEach(field => {
        field.addEventListener("change", updateEditConditionalVisibility);
    });
    updateEditConditionalVisibility();
}

function updateEditConditionalVisibility() {
    const wrappers = document.querySelectorAll("#kt_edit_config_fields [data-show-if]");
    wrappers.forEach(wrapper => {
        const condition = wrapper.dataset.showIf;
        const [fieldName, expectedValues] = condition.split(":");
        const values = expectedValues.split(",");
        
        const field = document.querySelector(`#kt_edit_config_fields [data-field="${fieldName}"]`);
        if (field) {
            let currentValue = field.type === "checkbox" ? field.checked.toString() : field.value;
            wrapper.style.display = values.includes(currentValue) ? "block" : "none";
        }
    });
}

// Construir JSON do function schema (edição)
function buildEditFunctionSchema() {
    const toolType = document.getElementById("kt_edit_tool_type").value;
    
    // Se é um tipo com schema automático, usar o schema padrão
    if (autoSchemaTypes[toolType]) {
        console.log("Usando schema automático para tipo:", toolType);
        return autoSchemaTypes[toolType].defaultSchema;
    }
    
    // Caso contrário, construir schema manual
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
    
    const idFields = ['department_id', 'agent_id', 'funnel_id', 'stage_id', 'funnel_stage_id'];
    
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
            if (field.type === "number" || idFields.includes(fieldName)) {
                config[fieldName] = parseInt(value) || null;
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
        } else if (idFields.includes(fieldName)) {
            config[fieldName] = null;
        }
    });
    
    return hasConfig ? config : null;
}

// Preencher campos ao abrir modal de edição
function populateEditFields() {
    const functionSchema = FUNCTION_SCHEMA_DATA;
    const config = CONFIG_DATA;
    
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
                // Preencher selects/inputs primeiro (para que showIf funcione)
                Object.keys(config).forEach(key => {
                    const field = document.querySelector(`#kt_edit_config_fields .config-field[data-field="${key}"]`);
                    if (field) {
                        if (field.type === "checkbox") {
                            field.checked = !!config[key];
                        } else if (field.tagName === "TEXTAREA" && typeof config[key] === "object") {
                            field.value = JSON.stringify(config[key], null, 2);
                        } else if (field.tagName === "TEXTAREA" && typeof config[key] === "string") {
                            field.value = config[key];
                        } else {
                            field.value = config[key] || "";
                        }
                        // Disparar change para atualizar campos condicionais (showIf)
                        field.dispatchEvent(new Event('change'));
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
    const toolId = TOOL_ID;
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
    console.log("AI Tools Show: DOMContentLoaded disparado");
    console.log("FUNCTION_SCHEMA_DATA:", FUNCTION_SCHEMA_DATA);
    console.log("CONFIG_DATA:", CONFIG_DATA);
    console.log("TOOL_ID:", TOOL_ID);
    
    // ==================== WIZARD DE EDIÇÃO ====================
    const editStepperElement = document.querySelector('#kt_edit_tool_stepper');
    let editStepper = null;
    
    if (editStepperElement && typeof KTStepper !== 'undefined') {
        editStepper = new KTStepper(editStepperElement);
        
        // Handle next button
        const nextBtn = document.querySelector('#kt_modal_edit_ai_tool [data-kt-stepper-action="next"]');
        const prevBtn = document.querySelector('#kt_modal_edit_ai_tool [data-kt-stepper-action="previous"]');
        const submitBtn = document.querySelector('#kt_modal_edit_ai_tool [data-kt-stepper-action="submit"]');
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const currentStep = editStepper.getCurrentStepIndex();
                
                if (currentStep === 1) {
                    if (!validateEditStep1()) return;
                    updateEditReviewSummary();
                }
                if (currentStep === 2) {
                    buildAndStoreEditJSONs();
                    updateEditReviewConfig();
                }
                
                editStepper.goNext();
                updateEditWizardButtons(editStepper);
            });
        }
        
        if (prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                editStepper.goPrevious();
                updateEditWizardButtons(editStepper);
            });
        }
        
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('kt_modal_edit_ai_tool_form').dispatchEvent(new Event('submit'));
            });
        }
    }
    
    function updateEditWizardButtons(stepper) {
        const currentStep = stepper.getCurrentStepIndex();
        const totalSteps = 3; // Total fixo de 3 steps
        const nextBtn = document.querySelector('#kt_modal_edit_ai_tool [data-kt-stepper-action="next"]');
        const submitBtn = document.querySelector('#kt_modal_edit_ai_tool [data-kt-stepper-action="submit"]');
        
        if (currentStep === totalSteps) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'inline-flex';
        } else {
            if (nextBtn) nextBtn.style.display = 'inline-flex';
            if (submitBtn) submitBtn.style.display = 'none';
        }
        
        updateEditStepperVisuals(currentStep);
    }
    
    function updateEditStepperVisuals(currentStep) {
        document.querySelectorAll('#kt_edit_tool_stepper .stepper-item').forEach((item, index) => {
            const icon = item.querySelector('.stepper-icon');
            const stepNum = index + 1;
            
            if (stepNum < currentStep) {
                icon.classList.remove('bg-gray-200', 'text-gray-600', 'bg-primary', 'text-white');
                icon.classList.add('bg-success', 'text-white');
            } else if (stepNum === currentStep) {
                icon.classList.remove('bg-gray-200', 'text-gray-600', 'bg-success', 'text-white');
                icon.classList.add('bg-primary', 'text-white');
            } else {
                icon.classList.remove('bg-primary', 'text-white', 'bg-success', 'text-white');
                icon.classList.add('bg-gray-200', 'text-gray-600');
            }
        });
    }
    
    function validateEditStep1() {
        const name = document.getElementById('edit_wizard_name').value.trim();
        const slug = document.getElementById('edit_wizard_slug').value.trim();
        const toolType = document.getElementById('kt_edit_tool_type').value;
        
        if (!name) {
            alert('Por favor, preencha o nome da tool.');
            return false;
        }
        if (!slug) {
            alert('Por favor, preencha o slug da tool.');
            return false;
        }
        if (!toolType) {
            alert('Por favor, selecione o tipo da tool.');
            return false;
        }
        return true;
    }
    
    function updateEditReviewSummary() {
        document.getElementById('edit_review_name').textContent = document.getElementById('edit_wizard_name').value;
        document.getElementById('edit_review_slug').textContent = document.getElementById('edit_wizard_slug').value;
        document.getElementById('edit_review_description').textContent = document.getElementById('edit_wizard_description').value || '-';
        
        const toolType = document.getElementById('kt_edit_tool_type').value;
        const typeLabels = {
            woocommerce: 'WooCommerce',
            database: 'Database',
            n8n: 'N8N',
            document: 'Document',
            system: 'System',
            api: 'API',
            followup: 'Followup',
            human_escalation: '🧑‍💼 Escalar para Humano',
            funnel_stage: '📊 Mover para Funil',
            funnel_stage_smart: '🧠 Mover (Inteligente)'
        };
        document.getElementById('edit_review_type').textContent = typeLabels[toolType] || toolType;
    }
    
    function updateEditReviewConfig() {
        const config = buildEditConfig();
        const card = document.getElementById('edit_review_config_card');
        const content = document.getElementById('edit_review_config_content');
        
        if (config && Object.keys(config).length > 0) {
            let html = '<div class="row g-5">';
            Object.keys(config).forEach(key => {
                const value = config[key];
                let displayValue = value;
                if (typeof value === 'boolean') {
                    displayValue = value ? '✅ Sim' : '❌ Não';
                } else if (typeof value === 'object') {
                    displayValue = JSON.stringify(value).substring(0, 50) + '...';
                }
                html += `<div class="col-md-6"><div class="text-muted fs-7 mb-1">${key}</div><div class="fw-semibold fs-6">${displayValue}</div></div>`;
            });
            html += '</div>';
            content.innerHTML = html;
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    }
    
    function buildAndStoreEditJSONs() {
        const functionSchema = buildEditFunctionSchema();
        const config = buildEditConfig();
        
        if (functionSchema) {
            document.getElementById('kt_edit_function_schema_json').value = JSON.stringify(functionSchema);
        }
        if (config) {
            document.getElementById('kt_edit_config_json').value = JSON.stringify(config);
        }
    }
    
    // Gerar cards de tipo para edição
    renderEditToolTypeCards();
    
    function renderEditToolTypeCards() {
        const container = document.getElementById('edit_tool_type_cards');
        const select = document.getElementById('kt_edit_tool_type');
        const currentType = select.value;
        
        const types = [
            { value: 'human_escalation', label: '🧑‍💼 Escalar para Humano', desc: 'Transfere para atendente humano' },
            { value: 'funnel_stage', label: '📊 Mover para Funil', desc: 'Move conversa entre etapas' },
            { value: 'funnel_stage_smart', label: '🧠 Mover (Inteligente)', desc: 'IA decide a melhor etapa' },
            { value: 'n8n', label: '⚡ N8N Webhook', desc: 'Integração com workflows' },
            { value: 'woocommerce', label: '🛒 WooCommerce', desc: 'Integração com loja' },
            { value: 'api', label: '🔌 API Externa', desc: 'Conecta com APIs' },
            { value: 'database', label: '🗄️ Database', desc: 'Consulta banco de dados' },
            { value: 'document', label: '📄 Documentos', desc: 'Busca em documentos' },
            { value: 'followup', label: '⏰ Follow-up', desc: 'Agenda retorno' },
            { value: 'system', label: '⚙️ Sistema', desc: 'Funções internas' }
        ];
        
        let html = '<div class="row g-3">';
        types.forEach(type => {
            const isSelected = type.value === currentType;
            html += `
                <div class="col-md-6 col-lg-4">
                    <label class="btn btn-flex btn-outline btn-outline-dashed btn-active-light-primary d-flex flex-column align-items-start w-100 h-100 p-6 edit-tool-type-card ${isSelected ? 'active' : ''}" 
                           data-type="${type.value}">
                        <span class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="radio" name="edit_tool_type_radio" value="${type.value}" ${isSelected ? 'checked' : ''}>
                        </span>
                        <span class="fs-4 fw-bold mb-2">${type.label}</span>
                        <span class="text-muted fs-7 text-start">${type.desc}</span>
                    </label>
                </div>
            `;
        });
        html += '</div>';
        
        container.innerHTML = html;
        
        // Event listeners
        document.querySelectorAll('.edit-tool-type-card').forEach(card => {
            card.addEventListener('click', function() {
                const type = this.dataset.type;
                select.value = type;
                
                document.querySelectorAll('.edit-tool-type-card').forEach(c => {
                    c.classList.remove('active');
                    c.querySelector('.form-check-input').checked = false;
                });
                this.classList.add('active');
                this.querySelector('.form-check-input').checked = true;
                
                updateEditConfigFields();
            });
        });
    }
    
    // Preencher campos quando modal de edição for aberto
    const editModal = document.getElementById("kt_modal_edit_ai_tool");
    if (editModal) {
        editModal.addEventListener("shown.bs.modal", function() {
            console.log("Modal de edição aberto");
            populateEditFields();
            // Reset stepper para etapa 1
            if (editStepper) {
                editStepper.goTo(1);
                updateEditWizardButtons(editStepper);
            }
        });
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
            const submitBtn = document.querySelector('#kt_modal_edit_ai_tool [data-kt-stepper-action="submit"]');
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
<?php
$scripts = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php'; 
?>

