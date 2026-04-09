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
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="<?= \App\Helpers\Url::to('/ai-tools/' . $tool['id']) ?>" class="btn btn-sm btn-light btn-active-light-primary" title="Ver detalhes">
                                            <i class="ki-duotone ki-eye fs-5">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                        </a>
                                        <?php if (\App\Helpers\Permission::can('ai_tools.create')): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-light btn-active-light-success duplicate-ai-tool-btn" 
                                                data-id="<?= $tool['id'] ?>"
                                                data-name="<?= htmlspecialchars($tool['name']) ?>"
                                                data-type="<?= htmlspecialchars($tool['tool_type']) ?>"
                                                title="Duplicar tool">
                                            <i class="ki-duotone ki-copy fs-5">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Helpers\Permission::can('ai_tools.delete')): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-light btn-active-light-danger delete-ai-tool-btn" 
                                                data-id="<?= $tool['id'] ?>"
                                                data-name="<?= htmlspecialchars($tool['name']) ?>"
                                                title="Deletar">
                                            <i class="ki-duotone ki-trash fs-5">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                                <span class="path5"></span>
                                            </i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
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

<!--begin::Modal - Nova Tool (Wizard)-->
<div class="modal fade" id="kt_modal_new_ai_tool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bold mb-1">Nova Tool de IA</h2>
                    <span class="text-muted fs-7">Configure sua tool em 3 passos simples</span>
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
                    <div class="stepper stepper-links d-flex flex-column" id="kt_create_tool_stepper">
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
                            <form id="kt_modal_new_ai_tool_form" class="form">
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
                                                <input type="text" name="name" id="wizard_name" class="form-control form-control-lg" placeholder="Ex: Buscar Pedido WooCommerce" required />
                                            </div>
                                            <div class="form-text">Use um nome descritivo que a IA possa entender</div>
                                        </div>
                                        
                                        <div class="fv-row mb-8">
                                            <label class="required fw-semibold fs-6 mb-2 d-flex align-items-center">
                                                <span>Slug (Identificador)</span>
                                                <i class="ki-duotone ki-information-5 ms-2 fs-6" data-bs-toggle="tooltip" title="Identificador técnico único (sem espaços, use underscore)"></i>
                                            </label>
                                            <div class="input-group input-group-solid">
                                                <span class="input-group-text"><i class="ki-duotone ki-code fs-3"></i></span>
                                                <input type="text" name="slug" id="wizard_slug" class="form-control form-control-lg" placeholder="Ex: buscar_pedido_woocommerce" required />
                                            </div>
                                            <div class="form-text">Este identificador é usado internamente pelo sistema</div>
                                        </div>
                                        
                                        <div class="fv-row mb-8">
                                            <label class="fw-semibold fs-6 mb-2">Descrição</label>
                                            <textarea name="description" id="wizard_description" class="form-control form-control-solid" rows="3" placeholder="Descreva o que esta tool faz e quando a IA deve usá-la..."></textarea>
                                            <div class="form-text">Ajuda a IA a entender quando usar esta tool</div>
                                        </div>
                                        
                                        <div class="fv-row mb-8">
                                            <label class="required fw-semibold fs-6 mb-2">Tipo de Tool</label>
                                            <div class="row g-5" id="tool_type_cards">
                                                <!-- Cards serão gerados via JS -->
                                            </div>
                                            <!-- Select hidden para armazenar o valor -->
                                            <select name="tool_type" id="kt_tool_type" class="form-select form-select-solid d-none" required>
                                                <option value="">Selecione</option>
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
                                        <div class="fv-row mb-7" id="kt_auto_schema_notice" style="display: none;">
                                            <div class="alert alert-info d-flex align-items-center p-5">
                                                <i class="ki-duotone ki-information-5 fs-2x text-info me-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                <div>
                                                    <h4 class="mb-1 text-info">Function Schema Automático</h4>
                                                    <span id="kt_auto_schema_message">O function schema será gerado automaticamente.</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Function Schema Manual (para tipos customizáveis) -->
                                        <div class="fv-row mb-7" id="kt_function_schema_section">
                                            <label class="fw-semibold fs-6 mb-4 d-flex align-items-center">
                                                <span>Function Schema</span>
                                                <span class="badge badge-light-danger ms-2">Avançado</span>
                                            </label>
                                            <div class="card border border-dashed border-primary bg-light p-5">
                                                <div class="fv-row mb-5">
                                                    <label class="required fw-semibold fs-7 mb-2">Nome da Função</label>
                                                    <input type="text" id="kt_function_name" class="form-control form-control-solid" placeholder="Ex: buscar_pedido_woocommerce" required />
                                                </div>
                                                
                                                <div class="fv-row mb-5">
                                                    <label class="required fw-semibold fs-7 mb-2">Descrição da Função</label>
                                                    <textarea id="kt_function_description" class="form-control form-control-solid" rows="2" placeholder="Descreva o que esta função faz" required></textarea>
                                                </div>
                                                
                                                <div class="fv-row mb-5">
                                                    <label class="fw-semibold fs-7 mb-3 d-flex justify-content-between">
                                                        <span>Parâmetros</span>
                                                        <span class="text-muted fs-8">Campos que a IA pode preencher</span>
                                                    </label>
                                                    <div id="kt_function_parameters">
                                                        <div class="text-muted fs-7 mb-3 text-center py-4 bg-white rounded">
                                                            <i class="ki-duotone ki-plus-circle fs-2x mb-2 d-block"></i>
                                                            Nenhum parâmetro adicionado
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-light-primary mt-3" onclick="addFunctionParameter()">
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
                                            <label class="fw-semibold fs-6 mb-4 d-flex align-items-center">
                                                <i class="ki-duotone ki-setting-2 fs-4 me-2 text-primary"></i>
                                                <span>Configurações Específicas</span>
                                            </label>
                                            <div class="card bg-light p-5" id="kt_config_fields">
                                                <!-- Campos serão inseridos dinamicamente via JavaScript -->
                                            </div>
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
                                            <div class="text-muted fw-semibold fs-6">Verifique as configurações antes de criar a tool.</div>
                                        </div>
                                        
                                        <!-- Resumo em Cards -->
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
                                                            <div class="fw-semibold fs-6" id="review_name">-</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted fs-7 mb-1">Slug</div>
                                                            <div class="fw-semibold fs-6" id="review_slug">-</div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted fs-7 mb-1">Tipo</div>
                                                            <div class="fw-semibold fs-6"><span class="badge badge-light-primary" id="review_type">-</span></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-muted fs-7 mb-1">Status</div>
                                                            <div class="fw-semibold fs-6"><span class="badge badge-light-success">Ativa</span></div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4">
                                                        <div class="text-muted fs-7 mb-1">Descrição</div>
                                                        <div class="fw-semibold fs-6" id="review_description">-</div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="card bg-light" id="review_config_card" style="display: none;">
                                                <div class="card-body">
                                                    <h4 class="fw-bold mb-4 text-gray-800">
                                                        <i class="ki-duotone ki-setting-2 fs-2 me-2"></i>
                                                        Configurações
                                                    </h4>
                                                    <div id="review_config_content"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-warning d-flex">
                                            <i class="ki-duotone ki-information-5 fs-2x me-4"></i>
                                            <div>
                                                <span class="fw-semibold">Importante:</span> Após criar a tool, você precisará atribuí-la a um ou mais agentes de IA para que ela possa ser usada.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden fields para JSON -->
                                <input type="hidden" name="function_schema" id="kt_function_schema_json" />
                                <input type="hidden" name="config" id="kt_config_json" />
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
                                                Criar Tool
                                            </span>
                                            <span class="indicator-progress">
                                                Criando...
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
<!--end::Modal - Nova Tool-->

<!--begin::Modal - Duplicar Tool-->
<div class="modal fade" id="kt_modal_duplicate_ai_tool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <div class="btn btn-icon btn-light-success btn-sm me-3">
                        <i class="ki-duotone ki-copy fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-0">Duplicar Tool</h2>
                        <span class="text-muted fs-7">Crie uma cópia com configurações pré-preenchidas</span>
                    </div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_duplicate_ai_tool_form" class="form">
                <div class="modal-body">
                    <!-- Origem -->
                    <div class="d-flex align-items-center bg-light rounded p-4 mb-5">
                        <div class="symbol symbol-40px symbol-circle me-4 bg-success">
                            <i class="ki-duotone ki-abstract-26 fs-2 text-white">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Tool de origem</div>
                            <div class="fw-bold fs-6 text-gray-800" id="duplicate-source-name">-</div>
                            <span class="badge badge-light-primary mt-1" id="duplicate-source-type">-</span>
                        </div>
                    </div>
                    
                    <!-- Novo nome e slug -->
                    <div class="fv-row mb-5">
                        <label class="required fw-semibold fs-6 mb-2">Novo Nome</label>
                        <input type="text" name="name" id="duplicate_name" class="form-control form-control-solid" placeholder="Ex: Buscar Pedido WooCommerce (Copia)" required />
                        <div class="form-text">Dê um nome descritivo para identificar a cópia</div>
                    </div>
                    
                    <div class="fv-row mb-5">
                        <label class="required fw-semibold fs-6 mb-2">Novo Slug</label>
                        <input type="text" name="slug" id="duplicate_slug" class="form-control form-control-solid" placeholder="Ex: buscar_pedido_woocommerce_copia" required />
                        <div class="form-text">Identificador único (sem espaços, use underscore)</div>
                    </div>
                    
                    <input type="hidden" name="source_tool_id" id="duplicate_source_id" />
                    <input type="hidden" name="tool_type" id="duplicate_tool_type" />
                    <input type="hidden" name="description" id="duplicate_description" />
                    <input type="hidden" name="function_schema" id="duplicate_function_schema" />
                    <input type="hidden" name="config" id="duplicate_config" />
                    
                    <!-- Preview das configurações -->
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">Configurações que serão copiadas</label>
                        <div class="bg-light rounded p-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="ki-duotone ki-check-circle fs-2x text-success me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div>
                                    <span class="fw-semibold">Function Schema</span>
                                    <div class="text-muted fs-7">Estrutura da função e parâmetros</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <i class="ki-duotone ki-check-circle fs-2x text-success me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div>
                                    <span class="fw-semibold">Configurações</span>
                                    <div class="text-muted fs-7">URL, credenciais, opções específicas do tipo</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="ki-duotone ki-check-circle fs-2x text-success me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div>
                                    <span class="fw-semibold">Status</span>
                                    <div class="text-muted fs-7">Tool será criada como inativa (para revisão)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info d-flex">
                        <i class="ki-duotone ki-information-5 fs-2x me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div>
                            <span class="fw-semibold">Dica:</span> Após duplicar, você pode editar a tool para ajustar as configurações específicas antes de ativá-la.
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_duplicate_ai_tool_submit" class="btn btn-success">
                        <span class="indicator-label">
                            <i class="ki-duotone ki-copy fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Duplicar Tool
                        </span>
                        <span class="indicator-progress">Criando cópia...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Duplicar Tool-->

<?php 
$content = ob_get_clean(); 

// Preparar dados para JavaScript com flags de segurança completas
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$departmentsJson = json_encode($departments ?? [], $jsonFlags) ?: '[]';
$funnelsJson = json_encode($funnels ?? [], $jsonFlags) ?: '[]';
$agentsJson = json_encode($agents ?? [], $jsonFlags) ?: '[]';

ob_start();
?>
<script>
console.log("=== AI TOOLS SCRIPT INICIANDO ===");

let parameterCounter = 0;

// Dados do backend para selects dinâmicos
let availableDepartments, availableFunnels, availableAgents;
try {
    availableDepartments = <?= $departmentsJson ?>;
    console.log("availableDepartments carregado:", availableDepartments.length, "itens");
} catch(e) {
    console.error("ERRO ao carregar departmentsJson:", e);
    availableDepartments = [];
}

try {
    availableFunnels = <?= $funnelsJson ?>;
    console.log("availableFunnels carregado:", availableFunnels.length, "itens");
} catch(e) {
    console.error("ERRO ao carregar funnelsJson:", e);
    availableFunnels = [];
}

try {
    availableAgents = <?= $agentsJson ?>;
    console.log("availableAgents carregado:", availableAgents.length, "itens");
} catch(e) {
    console.error("ERRO ao carregar agentsJson:", e);
    availableAgents = [];
}

const funnelStages = {}; // Será preenchido dinamicamente

console.log("Dados do backend carregados com sucesso!");

// Tipos que têm function schema automático (não precisam de configuração manual)
const autoSchemaTypes = {
    human_escalation: {
        message: "Esta tool irá escalar a conversa para um atendente humano. O function schema será gerado automaticamente.",
        defaultSchema: {
            type: "function",
            function: {
                name: "escalar_para_humano",
                description: "Escala a conversa para um atendente humano quando o cliente solicita ou quando a IA não consegue resolver",
                parameters: {
                    type: "object",
                    properties: {
                        motivo: {
                            type: "string",
                            description: "Motivo da escalação para o atendente humano"
                        }
                    },
                    required: ["motivo"]
                }
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
                parameters: {
                    type: "object",
                    properties: {
                        motivo: {
                            type: "string",
                            description: "Motivo para mover a conversa para esta etapa"
                        }
                    },
                    required: []
                }
            }
        }
    },
    funnel_stage_smart: {
        message: "Esta tool irá analisar a conversa e decidir automaticamente para qual etapa mover. O function schema será gerado automaticamente.",
        defaultSchema: {
            type: "function",
            function: {
                name: "analisar_e_mover_etapa",
                description: "Analisa o contexto da conversa e decide para qual etapa do funil a conversa deve ser movida",
                parameters: {
                    type: "object",
                    properties: {
                        contexto: {
                            type: "string",
                            description: "Resumo do contexto atual da conversa"
                        },
                        intencao_cliente: {
                            type: "string",
                            description: "Intenção identificada do cliente"
                        }
                    },
                    required: ["contexto"]
                }
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
                parameters: {
                    type: "object",
                    properties: {
                        data_hora: {
                            type: "string",
                            description: "Data e hora para o follow-up (formato: YYYY-MM-DD HH:MM)"
                        },
                        mensagem: {
                            type: "string",
                            description: "Mensagem a ser enviada no follow-up"
                        },
                        motivo: {
                            type: "string",
                            description: "Motivo do follow-up"
                        }
                    },
                    required: ["data_hora", "mensagem"]
                }
            }
        }
    }
};

// Configurações por tipo de tool
const toolTypeConfigs = {
    woocommerce: {
        fields: [
            { name: "wc_operation", label: "Operação da Loja", type: "select", required: true,
              options: [
                { value: "buscar_pedido_woocommerce",          label: "Buscar 1 pedido por número/ID" },
                { value: "buscar_pedidos_woocommerce",         label: "Listar pedidos (cliente, status, datas, IDs)" },
                { value: "buscar_produto_woocommerce",         label: "Buscar produto (REST API: preço, estoque, atributos, custom fields)" },
                { value: "buscar_pagina_produto_woocommerce",  label: "Ler página HTML do produto (ressalvas, regras, prazos do front)" },
                { value: "criar_pedido_woocommerce",           label: "Criar pedido" },
                { value: "atualizar_status_pedido",            label: "Atualizar status de pedido" }
              ],
              help: "Escolha a operação. O Function Schema (nome, descrição e parâmetros) será preenchido automaticamente — não precisa adicionar parâmetros manualmente." },
            { name: "url", label: "URL da Loja WooCommerce", type: "url", required: true, placeholder: "https://loja.exemplo.com" },
            { name: "consumer_key", label: "Consumer Key", type: "text", required: true },
            { name: "consumer_secret", label: "Consumer Secret", type: "password", required: true },
            { name: "meta_whitelist", label: "Custom fields ocultos (prefixo _) a incluir", type: "text", required: false,
              placeholder: "_prazo_producao,_data_entrega_prevista,_transportadora",
              help: "Lista separada por vírgula. Por padrão, meta keys com prefixo _ (internas do WC/plugins) são escondidas. Adicione aqui as que você quer enviar ao agente." }
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
            { name: "distribution_method", label: "Método de Distribuição", type: "select", required: false,
              showIf: "escalation_type:department",
              options: [
                { value: "round_robin", label: "Round Robin (sequencial)" },
                { value: "by_load", label: "Por Carga (menos conversas)" }
              ], default: "round_robin", help: "Como distribuir entre agentes do setor" },
            { name: "consider_availability", label: "Considerar disponibilidade (online)", type: "checkbox", required: false, default: true,
              showIf: "escalation_type:department", help: "Só atribui a agentes que estão online" },
            { name: "consider_limits", label: "Considerar limite máximo de conversas", type: "checkbox", required: false, default: true,
              showIf: "escalation_type:department", help: "Respeita o limite de conversas do agente" },
            { name: "force_assign", label: "Forçar atribuição (ignora regras)", type: "checkbox", required: false, default: false,
              showIf: "escalation_type:agent", help: "Atribui mesmo se o agente estiver offline ou no limite" },
            { name: "fallback_action", label: "Se não encontrar agente no setor", type: "select", required: false,
              showIf: "escalation_type:department",
              options: [
                { value: "queue", label: "Manter em fila" },
                { value: "any_agent", label: "Atribuir a qualquer agente (qualquer setor)" },
                { value: "any_agent_same_dept", label: "Atribuir a qualquer agente (mesmo setor, ignorar limites)" }
              ], default: "queue", help: "O que fazer quando nenhum agente do setor está disponível" },
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
              showIf: "escalation_type:department,custom",
              options: [
                { value: "round_robin", label: "Round Robin (sequencial)" },
                { value: "by_load", label: "Por Carga (menos conversas)" },
                { value: "by_pending_response", label: "Por Respostas Pendentes (cliente aguardando)" },
                { value: "by_performance", label: "Por Performance" },
                { value: "by_specialty", label: "Por Especialidade" },
                { value: "percentage", label: "Por Porcentagem" }
              ], default: "round_robin" },
            { name: "consider_availability", label: "Considerar disponibilidade (online)", type: "checkbox", required: false, default: true,
              showIf: "escalation_type:department,custom" },
            { name: "consider_limits", label: "Considerar limite máximo de conversas", type: "checkbox", required: false, default: true,
              showIf: "escalation_type:department,custom" },
            { name: "allow_ai_agents", label: "Permitir agentes de IA", type: "checkbox", required: false, default: false,
              showIf: "escalation_type:custom" },
            { name: "force_assign", label: "Forçar atribuição (ignora regras)", type: "checkbox", required: false, default: false,
              showIf: "escalation_type:agent" },
            { name: "fallback_action", label: "Se não encontrar agente", type: "select", required: false,
              showIf: "escalation_type:custom,department",
              options: [
                { value: "queue", label: "Manter em fila" },
                { value: "any_agent", label: "Atribuir a qualquer agente (qualquer setor)" },
                { value: "any_agent_same_dept", label: "Atribuir a qualquer agente (mesmo setor, ignorar limites)" },
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

// Atualizar visibilidade do Function Schema baseado no tipo
function updateFunctionSchemaVisibility(toolType) {
    const schemaSection = document.getElementById("kt_function_schema_section");
    const autoNotice = document.getElementById("kt_auto_schema_notice");
    const autoMessage = document.getElementById("kt_auto_schema_message");
    const functionNameInput = document.getElementById("kt_function_name");
    const functionDescInput = document.getElementById("kt_function_description");
    
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

// Atualizar campos de config baseado no tipo
function updateConfigFields() {
    console.log("=== updateConfigFields INICIADA ===");
    
    const toolTypeElement = document.getElementById("kt_tool_type");
    
    if (!toolTypeElement) {
        console.error("ERRO: Elemento kt_tool_type não encontrado!");
        return;
    }
    
    const toolType = toolTypeElement.value;
    console.log("Tool type selecionado:", toolType);
    
    // Atualizar visibilidade do function schema
    updateFunctionSchemaVisibility(toolType);
    
    const configSection = document.getElementById("kt_config_section");
    const configFields = document.getElementById("kt_config_fields");
    
    if (!configSection || !configFields) {
        console.error("ERRO: Elementos de config não encontrados!");
        return;
    }
    
    if (!toolType) {
        console.log("Nenhum tipo selecionado - ocultando seção");
        configSection.style.display = "none";
        return;
    }
    
    if (!toolTypeConfigs[toolType]) {
        console.log("Config não encontrada para tipo:", toolType);
        configSection.style.display = "none";
        return;
    }
    
    if (toolTypeConfigs[toolType].fields.length === 0) {
        console.log("Tipo não tem campos de config:", toolType);
        configSection.style.display = "none";
        return;
    }
    
    console.log("✅ Mostrando config para tipo:", toolType, "com", toolTypeConfigs[toolType].fields.length, "campos");
    configSection.style.display = "block";
    configFields.innerHTML = "";
    
    const fields = toolTypeConfigs[toolType].fields;
    fields.forEach(field => {
        const fieldDiv = document.createElement("div");
        fieldDiv.className = "fv-row mb-5";
        fieldDiv.id = `field_wrapper_${field.name}`;
        if (field.showIf) fieldDiv.dataset.showIf = field.showIf;
        
        let inputHtml = "";
        let labelHtml = `<label class="fw-semibold fs-7 mb-2">${field.label}${field.required ? ' <span class="text-danger">*</span>' : ""}</label>`;
        
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
            const stageSelectId = "config_" + field.name.replace("funnel_id", "stage_id");
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
    
    // Lógica especial: quando método é "by_pending_response", desabilita "consider_availability"
    const distributionMethodField = document.querySelector('[data-field="distribution_method"]');
    const considerAvailabilityWrapper = document.getElementById('field_wrapper_consider_availability');
    const considerAvailabilityCheckbox = document.querySelector('[data-field="consider_availability"]');
    
    if (distributionMethodField && considerAvailabilityWrapper) {
        if (distributionMethodField.value === 'by_pending_response') {
            // Desabilita e desmarca o checkbox
            if (considerAvailabilityCheckbox) {
                considerAvailabilityCheckbox.checked = false;
                considerAvailabilityCheckbox.disabled = true;
            }
            considerAvailabilityWrapper.style.opacity = '0.5';
            considerAvailabilityWrapper.title = 'Método "Por Respostas Pendentes" não verifica disponibilidade online';
        } else {
            // Reabilita o checkbox
            if (considerAvailabilityCheckbox) {
                considerAvailabilityCheckbox.disabled = false;
            }
            considerAvailabilityWrapper.style.opacity = '1';
            considerAvailabilityWrapper.title = '';
        }
    }
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
    const toolType = document.getElementById("kt_tool_type").value;
    
    // Se é um tipo com schema automático, usar o schema padrão
    if (autoSchemaTypes[toolType]) {
        console.log("Usando schema automático para tipo:", toolType);
        return autoSchemaTypes[toolType].defaultSchema;
    }
    
    // Caso contrário, construir schema manual
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
    
    const idFields = ['department_id', 'agent_id', 'funnel_id', 'stage_id', 'funnel_stage_id', 'fallback_funnel_id', 'fallback_stage_id'];
    
    document.querySelectorAll(".config-field").forEach(field => {
        const fieldName = field.dataset.field;
        
        if (field.type === "checkbox") {
            config[fieldName] = field.checked;
            if (field.checked) hasConfig = true;
            return;
        }
        
        // Multi-select
        if (field.multiple) {
            const selected = Array.from(field.selectedOptions).map(opt => parseInt(opt.value) || opt.value);
            if (selected.length > 0) {
                config[fieldName] = selected;
                hasConfig = true;
            }
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

document.addEventListener("DOMContentLoaded", function() {
    console.log("=== AI Tools: DOMContentLoaded disparado ===");
    
    // ==================== WIZARD ====================
    const stepperElement = document.querySelector('#kt_create_tool_stepper');
    let stepper = null;
    
    if (stepperElement && typeof KTStepper !== 'undefined') {
        stepper = new KTStepper(stepperElement);
        
        // Handle next button
        // Handle navigation click - Botões próximo/voltar
        const nextBtn = document.querySelector('[data-kt-stepper-action="next"]');
        const prevBtn = document.querySelector('[data-kt-stepper-action="previous"]');
        const submitBtn = document.querySelector('[data-kt-stepper-action="submit"]');
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const currentStep = stepper.getCurrentStepIndex();
                
                if (currentStep === 1 && !validateStep1()) {
                    return;
                }
                if (currentStep === 1) {
                    updateReviewSummary();
                }
                if (currentStep === 2) {
                    buildAndStoreJSONs();
                    updateReviewConfig();
                }
                
                stepper.goNext();
                updateWizardButtons(stepper);
            });
        }
        
        if (prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                stepper.goPrevious();
                updateWizardButtons(stepper);
            });
        }
        
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('kt_modal_new_ai_tool_form').dispatchEvent(new Event('submit'));
            });
        }
    }
    
    function updateWizardButtons(stepper) {
        const currentStep = stepper.getCurrentStepIndex();
        const totalSteps = 3; // Total fixo de 3 steps
        const nextBtn = document.querySelector('[data-kt-stepper-action="next"]');
        const submitBtn = document.querySelector('[data-kt-stepper-action="submit"]');
        
        if (currentStep === totalSteps) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'inline-flex';
        } else {
            if (nextBtn) nextBtn.style.display = 'inline-flex';
            if (submitBtn) submitBtn.style.display = 'none';
        }
        
        // Atualizar visual dos steps
        updateStepperVisuals(currentStep);
    }
    
    function updateStepperVisuals(currentStep) {
        document.querySelectorAll('.stepper-item').forEach((item, index) => {
            const icon = item.querySelector('.stepper-icon');
            const stepNum = index + 1;
            
            if (stepNum < currentStep) {
                // Step completado
                icon.classList.remove('bg-gray-200', 'text-gray-600', 'bg-primary', 'text-white');
                icon.classList.add('bg-success', 'text-white');
                item.classList.add('completed');
            } else if (stepNum === currentStep) {
                // Step atual
                icon.classList.remove('bg-gray-200', 'text-gray-600', 'bg-success', 'text-white');
                icon.classList.add('bg-primary', 'text-white');
                item.classList.add('current');
                item.classList.remove('completed');
            } else {
                // Step futuro
                icon.classList.remove('bg-primary', 'text-white', 'bg-success', 'text-white');
                icon.classList.add('bg-gray-200', 'text-gray-600');
                item.classList.remove('current', 'completed');
            }
        });
    }
    
    function validateStep1() {
        const name = document.getElementById('wizard_name').value.trim();
        const slug = document.getElementById('wizard_slug').value.trim();
        const toolType = document.getElementById('kt_tool_type').value;
        
        if (!name) {
            alert('Por favor, preencha o nome da tool.');
            document.getElementById('wizard_name').focus();
            return false;
        }
        
        if (!slug) {
            alert('Por favor, preencha o slug da tool.');
            document.getElementById('wizard_slug').focus();
            return false;
        }
        
        if (!toolType) {
            alert('Por favor, selecione o tipo da tool.');
            return false;
        }
        
        return true;
    }
    
    function updateReviewSummary() {
        document.getElementById('review_name').textContent = document.getElementById('wizard_name').value;
        document.getElementById('review_slug').textContent = document.getElementById('wizard_slug').value;
        document.getElementById('review_description').textContent = document.getElementById('wizard_description').value || '-';
        
        const toolType = document.getElementById('kt_tool_type').value;
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
        document.getElementById('review_type').textContent = typeLabels[toolType] || toolType;
    }
    
    function updateReviewConfig() {
        const config = buildConfig();
        const card = document.getElementById('review_config_card');
        const content = document.getElementById('review_config_content');
        
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
                
                html += `
                    <div class="col-md-6">
                        <div class="text-muted fs-7 mb-1">${key}</div>
                        <div class="fw-semibold fs-6">${displayValue}</div>
                    </div>
                `;
            });
            html += '</div>';
            content.innerHTML = html;
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    }
    
    function buildAndStoreJSONs() {
        const functionSchema = buildFunctionSchema();
        const config = buildConfig();
        
        if (functionSchema) {
            document.getElementById('kt_function_schema_json').value = JSON.stringify(functionSchema);
        }
        if (config) {
            document.getElementById('kt_config_json').value = JSON.stringify(config);
        }
    }
    
    // Gerar cards de tipo de tool
    renderToolTypeCards();
    
    function renderToolTypeCards() {
        const container = document.getElementById('tool_type_cards');
        const select = document.getElementById('kt_tool_type');
        const types = [
            { value: 'human_escalation', label: '🧑‍💼 Escalar para Humano', desc: 'Transfere para atendente humano', color: 'success' },
            { value: 'funnel_stage', label: '📊 Mover para Funil', desc: 'Move conversa entre etapas', color: 'primary' },
            { value: 'funnel_stage_smart', label: '🧠 Mover (Inteligente)', desc: 'IA decide a melhor etapa', color: 'info' },
            { value: 'n8n', label: '⚡ N8N Webhook', desc: 'Integração com workflows', color: 'warning' },
            { value: 'woocommerce', label: '🛒 WooCommerce', desc: 'Integração com loja', color: 'danger' },
            { value: 'api', label: '🔌 API Externa', desc: 'Conecta com APIs', color: 'secondary' },
            { value: 'database', label: '🗄️ Database', desc: 'Consulta banco de dados', color: 'dark' },
            { value: 'document', label: '📄 Documentos', desc: 'Busca em documentos', color: 'primary' },
            { value: 'followup', label: '⏰ Follow-up', desc: 'Agenda retorno', color: 'success' },
            { value: 'system', label: '⚙️ Sistema', desc: 'Funções internas', color: 'secondary' }
        ];
        
        let html = '<div class="row g-3">';
        types.forEach(type => {
            html += `
                <div class="col-md-6 col-lg-4">
                    <label class="btn btn-flex btn-outline btn-outline-dashed btn-active-light-primary d-flex flex-column align-items-start w-100 h-100 p-6 tool-type-card" 
                           data-type="${type.value}">
                        <span class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="radio" name="tool_type_radio" value="${type.value}">
                        </span>
                        <span class="fs-4 fw-bold mb-2">${type.label}</span>
                        <span class="text-muted fs-7 text-start">${type.desc}</span>
                    </label>
                </div>
            `;
        });
        html += '</div>';
        
        container.innerHTML = html;
        
        // Event listeners para os cards
        document.querySelectorAll('.tool-type-card').forEach(card => {
            card.addEventListener('click', function() {
                const type = this.dataset.type;
                
                // Atualizar select escondido
                select.value = type;
                
                // Atualizar visual
                document.querySelectorAll('.tool-type-card').forEach(c => {
                    c.classList.remove('active');
                    c.querySelector('.form-check-input').checked = false;
                });
                this.classList.add('active');
                this.querySelector('.form-check-input').checked = true;
                
                // Atualizar campos de config
                updateConfigFields();
            });
        });
    }
    
    // Auto-gerar slug do nome
    const nameInput = document.getElementById('wizard_name');
    const slugInput = document.getElementById('wizard_slug');
    
    if (nameInput && slugInput) {
        nameInput.addEventListener('input', function() {
            if (!slugInput.dataset.userModified) {
                slugInput.value = generateSlug(this.value);
            }
        });
        
        slugInput.addEventListener('input', function() {
            this.dataset.userModified = 'true';
        });
    }
    
    function generateSlug(text) {
        return text
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s]/g, '')
            .trim()
            .replace(/\s+/g, '_');
    }
    
    // Atualizar campos de config quando tipo mudar
    const toolTypeSelect = document.getElementById("kt_tool_type");
    if (toolTypeSelect) {
        toolTypeSelect.addEventListener("change", function() {
            console.log("Tipo selecionado:", this.value);
            updateConfigFields();
        });
    }
    
    const form = document.getElementById("kt_modal_new_ai_tool_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.querySelector('[data-kt-stepper-action="submit"]');
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
    
    // ✅ NOVO: Funcionalidade de deletar AI Tool
    document.querySelectorAll('.delete-ai-tool-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const toolId = this.getAttribute('data-id');
            const toolName = this.getAttribute('data-name');
            
            if (!confirm(`Tem certeza que deseja deletar a tool "${toolName}"?\n\nEsta ação não pode ser desfeita.`)) {
                return;
            }
            
            // Desabilitar botão durante requisição
            this.disabled = true;
            const originalHtml = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deletando...';
            
            fetch(`<?= \App\Helpers\Url::to('/ai-tools') ?>/${toolId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remover linha da tabela
                    const row = this.closest('tr');
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        
                        // Verificar se não há mais tools
                        const tbody = document.querySelector('table tbody');
                        if (tbody && tbody.children.length === 0) {
                            location.reload(); // Recarregar para mostrar mensagem de "nenhuma tool"
                        }
                    }, 300);
                    
                    // Mostrar notificação de sucesso (se disponível)
                    if (typeof toastr !== 'undefined') {
                        toastr.success('Tool deletada com sucesso!');
                    } else {
                        alert('Tool deletada com sucesso!');
                    }
                } else {
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                    alert('Erro: ' + (data.message || 'Erro ao deletar tool'));
                }
            })
            .catch(error => {
                this.disabled = false;
                this.innerHTML = originalHtml;
                console.error('Erro:', error);
                alert('Erro ao deletar tool. Verifique o console para mais detalhes.');
            });
        });
    });
    
    // ✅ NOVO: Funcionalidade de duplicar AI Tool
    const duplicateModal = document.getElementById('kt_modal_duplicate_ai_tool');
    const duplicateModalInstance = duplicateModal ? new bootstrap.Modal(duplicateModal) : null;
    let sourceToolData = null;
    
    document.querySelectorAll('.duplicate-ai-tool-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const toolId = this.getAttribute('data-id');
            const toolName = this.getAttribute('data-name');
            const toolType = this.getAttribute('data-type');
            
            // Mostrar loading no botão
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            try {
                // Buscar dados completos da tool
                const response = await fetch(`<?= \App\Helpers\Url::to('/ai-tools') ?>/${toolId}?format=json`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Erro ao buscar dados da tool');
                }
                
                const data = await response.json();
                
                if (!data.success || !data.tool) {
                    throw new Error(data.message || 'Tool não encontrada');
                }
                
                sourceToolData = data.tool;
                
                // Preencher modal
                document.getElementById('duplicate-source-name').textContent = toolName;
                document.getElementById('duplicate-source-type').textContent = toolType;
                document.getElementById('duplicate_name').value = toolName + ' (Cópia)';
                document.getElementById('duplicate_slug').value = (data.tool.slug || '') + '_copia';
                document.getElementById('duplicate_source_id').value = toolId;
                document.getElementById('duplicate_tool_type').value = toolType;
                document.getElementById('duplicate_description').value = data.tool.description || '';
                document.getElementById('duplicate_function_schema').value = JSON.stringify(data.tool.function_schema || {});
                document.getElementById('duplicate_config').value = JSON.stringify(data.tool.config || {});
                
                // Abrir modal
                duplicateModalInstance.show();
                
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro: ' + error.message);
            } finally {
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    });
    
    // Submit do formulário de duplicação
    const duplicateForm = document.getElementById('kt_modal_duplicate_ai_tool_form');
    if (duplicateForm) {
        duplicateForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('kt_modal_duplicate_ai_tool_submit');
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
            
            const formData = new FormData(duplicateForm);
            
            // Converter enabled para boolean (inativa por padrão)
            formData.set('enabled', '0');
            
            try {
                const response = await fetch("<?= \App\Helpers\Url::to('/ai-tools') ?>", {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    duplicateModalInstance.hide();
                    
                    if (typeof toastr !== 'undefined') {
                        toastr.success('Tool duplicada com sucesso!');
                    } else {
                        alert('Tool duplicada com sucesso!');
                    }
                    
                    // Recarregar página para mostrar nova tool
                    location.reload();
                } else {
                    throw new Error(data.message || 'Erro ao duplicar tool');
                }
                
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro: ' + error.message);
            } finally {
                submitBtn.removeAttribute('data-kt-indicator');
                submitBtn.disabled = false;
            }
        });
    }
    
    // Atualizar slug automaticamente baseado no nome
    const duplicateNameInput = document.getElementById('duplicate_name');
    const duplicateSlugInput = document.getElementById('duplicate_slug');
    
    if (duplicateNameInput && duplicateSlugInput) {
        duplicateNameInput.addEventListener('input', function() {
            if (!duplicateSlugInput.dataset.userModified) {
                const slug = this.value
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9_]/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');
                duplicateSlugInput.value = slug;
            }
        });
        
        duplicateSlugInput.addEventListener('input', function() {
            this.dataset.userModified = 'true';
        });
    }
});
</script>
<?php 
$scripts = ob_get_clean();
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

