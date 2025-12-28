<?php
$layout = 'layouts.metronic.app';
$title = 'Agentes de IA';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Agentes de IA</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('ai_agents.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_ai_agent">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Agente de IA
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
        
        <?php if (empty($agents)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-robot fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum agente de IA encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo agente de IA.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-100px">Tipo</th>
                            <th class="min-w-100px">Modelo</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-150px">Conversas</th>
                            <th class="min-w-100px">Tools</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        <?php foreach ($agents as $agent): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($agent['name']) ?></span>
                                        <?php if (!empty($agent['description'])): ?>
                                            <span class="text-muted fs-7"><?= htmlspecialchars(mb_substr($agent['description'], 0, 50)) ?><?= mb_strlen($agent['description']) > 50 ? '...' : '' ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-info"><?= htmlspecialchars($agent['agent_type']) ?></span>
                                </td>
                                <td>
                                    <span class="text-gray-800"><?= htmlspecialchars($agent['model'] ?? 'gpt-4') ?></span>
                                </td>
                                <td>
                                    <?php if ($agent['enabled']): ?>
                                        <span class="badge badge-light-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $current = $agent['current_conversations'] ?? 0;
                                    $max = $agent['max_conversations'] ?? null;
                                    ?>
                                    <span class="text-gray-800"><?= $current ?> / <?= $max ?? '∞' ?></span>
                                </td>
                                <td>
                                    <?php
                                    $tools = \App\Models\AIAgent::getTools($agent['id']);
                                    ?>
                                    <span class="badge badge-light-primary"><?= count($tools) ?> tools</span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= \App\Helpers\Url::to('/ai-agents/' . $agent['id']) ?>" class="btn btn-sm btn-light-primary me-2">
                                        Ver
                                    </a>
                                    <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
                                    <button type="button" class="btn btn-sm btn-light-info" onclick="editAgent(<?= $agent['id'] ?>)">
                                        Editar
                                    </button>
                                    <?php endif; ?>
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

<!--begin::Modal - Novo Agente de IA-->
<div class="modal fade" id="kt_modal_new_ai_agent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Novo Agente de IA</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_ai_agent_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Nome do agente" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Descrição do agente"></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tipo</label>
                        <select name="agent_type" class="form-select form-select-solid" required>
                            <option value="SDR">SDR (Sales Development Representative)</option>
                            <option value="CS">CS (Customer Success)</option>
                            <option value="CLOSER">CLOSER (Fechamento de Vendas)</option>
                            <option value="FOLLOWUP">FOLLOWUP (Follow-up Automático)</option>
                            <option value="SUPPORT">SUPPORT (Suporte Técnico)</option>
                            <option value="ONBOARDING">ONBOARDING (Onboarding)</option>
                            <option value="GENERAL">GENERAL (Geral)</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">
                            Prompt do Sistema
                            <span class="badge badge-light-info ms-2" id="prompt_char_count">0 caracteres</span>
                        </label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="togglePromptPreview('new')">
                                <i class="ki-duotone ki-eye fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Preview
                            </button>
                            <button type="button" class="btn btn-sm btn-light-info" onclick="insertPromptTemplate('new')">
                                <i class="ki-duotone ki-file-up fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Templates
                            </button>
                        </div>
                        <textarea name="prompt" id="new_prompt" class="form-control form-control-solid" rows="8" placeholder="Digite o prompt do sistema que define o comportamento do agente..." required></textarea>
                        <div class="form-text">Este prompt será usado como instrução para o agente de IA. Seja específico sobre o papel, tom e comportamento esperado.</div>
                        <!-- Preview do Prompt -->
                        <div id="new_prompt_preview" class="mt-3 p-4 bg-light rounded" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold">Preview do Prompt:</span>
                                <button type="button" class="btn btn-sm btn-icon btn-light" onclick="togglePromptPreview('new')">
                                    <i class="ki-duotone ki-cross fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </button>
                            </div>
                            <div id="new_prompt_preview_content" class="text-gray-800" style="white-space: pre-wrap; font-family: monospace; font-size: 0.9rem;"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Modelo</label>
                                <select name="model" class="form-select form-select-solid">
                                    <option value="gpt-4o">GPT-4o</option>
                                    <option value="gpt-4">GPT-4</option>
                                    <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                    <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Temperature</label>
                                <input type="number" name="temperature" class="form-control form-control-solid" value="0.7" step="0.1" min="0" max="2" />
                                <div class="form-text">0.0 = Determinístico, 2.0 = Criativo</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Max Tokens</label>
                                <input type="number" name="max_tokens" class="form-control form-control-solid" value="2000" min="1" />
                            </div>
                        </div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" class="form-control form-control-solid" placeholder="Deixe em branco para ilimitado" min="1" />
                    </div>
                    
                    <!--begin::Delay Humanizado-->
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">
                            <i class="ki-duotone ki-timer fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Delay Humanizado (segundos)
                        </label>
                        <div class="row">
                            <div class="col-6">
                                <input type="number" name="response_delay_min" class="form-control form-control-solid" placeholder="Mínimo" min="0" max="60" value="0" />
                                <div class="form-text">Delay mínimo</div>
                            </div>
                            <div class="col-6">
                                <input type="number" name="response_delay_max" class="form-control form-control-solid" placeholder="Máximo" min="0" max="60" value="0" />
                                <div class="form-text">Delay máximo</div>
                            </div>
                        </div>
                        <div class="form-text text-info mt-2">
                            <i class="ki-duotone ki-information-5 fs-7 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Tempo aleatório antes de responder para parecer mais natural. Ex: 5-10 segundos.
                        </div>
                    </div>
                    <!--end::Delay Humanizado-->
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="enabled" class="form-check-input me-2" checked />
                            Agente ativo
                        </label>
                    </div>
                    
                    <!--begin::Seleção de Tools-->
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-4">Tools Disponíveis</label>
                        <div class="row g-3" id="ai_agent_tools_selection">
                            <?php if (!empty($allTools)): ?>
                                <?php foreach ($allTools as $tool): ?>
                                    <div class="col-md-6">
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input tool-checkbox" type="checkbox" 
                                                   name="tools[]" value="<?= $tool['id'] ?>" 
                                                   id="tool_<?= $tool['id'] ?>" />
                                            <label class="form-check-label" for="tool_<?= $tool['id'] ?>">
                                                <div class="d-flex flex-column">
                                                    <span class="fw-semibold"><?= htmlspecialchars($tool['name']) ?></span>
                                                    <span class="text-muted fs-7"><?= htmlspecialchars(mb_substr($tool['description'] ?? '', 0, 60)) ?><?= mb_strlen($tool['description'] ?? '') > 60 ? '...' : '' ?></span>
                                                    <span class="badge badge-light-info badge-sm mt-1"><?= htmlspecialchars($tool['tool_type']) ?></span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Nenhuma tool disponível. Crie tools em <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>">Tools de IA</a> primeiro.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!--end::Seleção de Tools-->
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_ai_agent_submit" class="btn btn-primary">
                        <span class="indicator-label">Criar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Novo Agente de IA-->

<!--begin::Modal - Editar Agente de IA-->
<div class="modal fade" id="kt_modal_edit_ai_agent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Agente de IA</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_ai_agent_form" class="form">
                <input type="hidden" name="agent_id" id="edit_agent_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" id="edit_name" class="form-control form-control-solid" placeholder="Nome do agente" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" id="edit_description" class="form-control form-control-solid" rows="3" placeholder="Descrição do agente"></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tipo</label>
                        <select name="agent_type" id="edit_agent_type" class="form-select form-select-solid" required>
                            <option value="SDR">SDR (Sales Development Representative)</option>
                            <option value="CS">CS (Customer Success)</option>
                            <option value="CLOSER">CLOSER (Fechamento de Vendas)</option>
                            <option value="FOLLOWUP">FOLLOWUP (Follow-up Automático)</option>
                            <option value="SUPPORT">SUPPORT (Suporte Técnico)</option>
                            <option value="ONBOARDING">ONBOARDING (Onboarding)</option>
                            <option value="GENERAL">GENERAL (Geral)</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">
                            Prompt do Sistema
                            <span class="badge badge-light-info ms-2" id="edit_prompt_char_count">0 caracteres</span>
                        </label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="togglePromptPreview('edit')">
                                <i class="ki-duotone ki-eye fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Preview
                            </button>
                            <button type="button" class="btn btn-sm btn-light-info" onclick="insertPromptTemplate('edit')">
                                <i class="ki-duotone ki-file-up fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Templates
                            </button>
                        </div>
                        <textarea name="prompt" id="edit_prompt" class="form-control form-control-solid" rows="8" placeholder="Digite o prompt do sistema que define o comportamento do agente..." required></textarea>
                        <div class="form-text">Este prompt será usado como instrução para o agente de IA. Seja específico sobre o papel, tom e comportamento esperado.</div>
                        <!-- Preview do Prompt -->
                        <div id="edit_prompt_preview" class="mt-3 p-4 bg-light rounded" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold">Preview do Prompt:</span>
                                <button type="button" class="btn btn-sm btn-icon btn-light" onclick="togglePromptPreview('edit')">
                                    <i class="ki-duotone ki-cross fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </button>
                            </div>
                            <div id="edit_prompt_preview_content" class="text-gray-800" style="white-space: pre-wrap; font-family: monospace; font-size: 0.9rem;"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Modelo</label>
                                <select name="model" id="edit_model" class="form-select form-select-solid">
                                    <option value="gpt-4o">GPT-4o</option>
                                    <option value="gpt-4">GPT-4</option>
                                    <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                    <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Temperature</label>
                                <input type="number" name="temperature" id="edit_temperature" class="form-control form-control-solid" value="0.7" step="0.1" min="0" max="2" />
                                <div class="form-text">0.0 = Determinístico, 2.0 = Criativo</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Max Tokens</label>
                                <input type="number" name="max_tokens" id="edit_max_tokens" class="form-control form-control-solid" value="2000" min="1" />
                            </div>
                        </div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" id="edit_max_conversations" class="form-control form-control-solid" placeholder="Deixe em branco para ilimitado" min="1" />
                    </div>
                    
                    <!--begin::Delay Humanizado-->
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">
                            <i class="ki-duotone ki-timer fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Delay Humanizado (segundos)
                        </label>
                        <div class="row">
                            <div class="col-6">
                                <input type="number" name="response_delay_min" id="edit_response_delay_min" class="form-control form-control-solid" placeholder="Mínimo" min="0" max="60" value="0" />
                                <div class="form-text">Delay mínimo</div>
                            </div>
                            <div class="col-6">
                                <input type="number" name="response_delay_max" id="edit_response_delay_max" class="form-control form-control-solid" placeholder="Máximo" min="0" max="60" value="0" />
                                <div class="form-text">Delay máximo</div>
                            </div>
                        </div>
                        <div class="form-text text-info mt-2">
                            <i class="ki-duotone ki-information-5 fs-7 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Tempo aleatório antes de responder para parecer mais natural. Ex: 5-10 segundos.
                        </div>
                    </div>
                    <!--end::Delay Humanizado-->
                    
                    <!--begin::Timer de Contexto (Edição)-->
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">
                            <i class="ki-duotone ki-clock fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Timer de Contexto (segundos)
                        </label>
                        <input type="number" name="context_timer_seconds" id="edit_context_timer_seconds" class="form-control form-control-solid" placeholder="5" min="0" max="30" value="5" />
                        <div class="form-text text-info mt-2">
                            <i class="ki-duotone ki-information-5 fs-7 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            Tempo para agrupar múltiplas mensagens rápidas do cliente antes de responder. Se o cliente enviar 2-3 mensagens em sequência, aguarda este tempo e responde uma única vez. Padrão: 5 segundos. Use 0 para desabilitar.
                        </div>
                    </div>
                    <!--end::Timer de Contexto (Edição)-->
                    
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="enabled" id="edit_enabled" class="form-check-input me-2" />
                            Agente ativo
                        </label>
                    </div>
                    
                    <!--begin::Seleção de Tools-->
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-4">Tools Disponíveis</label>
                        <div class="row g-3" id="ai_agent_edit_tools_selection">
                            <?php if (!empty($allTools)): ?>
                                <?php foreach ($allTools as $tool): ?>
                                    <div class="col-md-6">
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input tool-checkbox-edit" type="checkbox" 
                                                   name="tools[]" value="<?= $tool['id'] ?>" 
                                                   id="edit_tool_<?= $tool['id'] ?>" />
                                            <label class="form-check-label" for="edit_tool_<?= $tool['id'] ?>">
                                                <div class="d-flex flex-column">
                                                    <span class="fw-semibold"><?= htmlspecialchars($tool['name']) ?></span>
                                                    <span class="text-muted fs-7"><?= htmlspecialchars(mb_substr($tool['description'] ?? '', 0, 60)) ?><?= mb_strlen($tool['description'] ?? '') > 60 ? '...' : '' ?></span>
                                                    <span class="badge badge-light-info badge-sm mt-1"><?= htmlspecialchars($tool['tool_type']) ?></span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        Nenhuma tool disponível. Crie tools em <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>">Tools de IA</a> primeiro.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!--end::Seleção de Tools-->
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_ai_agent_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Editar Agente de IA-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
// Função global para editar agente
function editAgent(id) {
    // Carregar dados do agente
    fetch("' . \App\Helpers\Url::to('/ai-agents') . '/" + id, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json"
        }
    })
    .then(response => {
        if (response.ok) {
            return response.json();
        }
        throw new Error("Erro ao carregar agente");
    })
    .then(data => {
        if (data.success && data.agent) {
            const agent = data.agent;
            
            // Preencher formulário
            document.getElementById("edit_agent_id").value = agent.id;
            document.getElementById("edit_name").value = agent.name || "";
            document.getElementById("edit_description").value = agent.description || "";
            document.getElementById("edit_agent_type").value = agent.agent_type || "GENERAL";
            document.getElementById("edit_prompt").value = agent.prompt || "";
            document.getElementById("edit_model").value = agent.model || "gpt-4";
            document.getElementById("edit_temperature").value = agent.temperature || 0.7;
            document.getElementById("edit_max_tokens").value = agent.max_tokens || 2000;
            document.getElementById("edit_max_conversations").value = agent.max_conversations || "";
            document.getElementById("edit_enabled").checked = agent.enabled == 1 || agent.enabled === true;
            
            // Carregar delay humanizado das settings
            const settings = agent.settings ? (typeof agent.settings === "string" ? JSON.parse(agent.settings) : agent.settings) : {};
            document.getElementById("edit_response_delay_min").value = settings.response_delay_min || 0;
            document.getElementById("edit_response_delay_max").value = settings.response_delay_max || 0;
            document.getElementById("edit_context_timer_seconds").value = settings.context_timer_seconds || 5;
            
            // Atualizar contador de caracteres do prompt
            updatePromptCharCount('edit');
            
            // Carregar tools do agente
            if (agent.tools && agent.tools.length > 0) {
                agent.tools.forEach(tool => {
                    const checkbox = document.getElementById("edit_tool_" + tool.id);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            } else {
                // Desmarcar todos
                document.querySelectorAll(".tool-checkbox-edit").forEach(cb => cb.checked = false);
            }
            
            // Abrir modal
            const modal = new bootstrap.Modal(document.getElementById("kt_modal_edit_ai_agent"));
            modal.show();
        } else {
            Swal.fire({
                icon: "error",
                title: "Erro",
                text: data.message || "Erro ao carregar dados do agente"
            });
        }
    })
    .catch(error => {
        console.error("Erro:", error);
        Swal.fire({
            icon: "error",
            title: "Erro",
            text: "Não foi possível carregar os dados do agente"
        });
    });
}

// Funções para preview e templates de prompt
function togglePromptPreview(mode) {
    const previewDiv = document.getElementById(mode + '_prompt_preview');
    const previewContent = document.getElementById(mode + '_prompt_preview_content');
    const promptTextarea = document.getElementById(mode + '_prompt');
    
    if (previewDiv.style.display === 'none') {
        previewContent.textContent = promptTextarea.value || '(vazio)';
        previewDiv.style.display = 'block';
    } else {
        previewDiv.style.display = 'none';
    }
}

function insertPromptTemplate(mode) {
    const templates = {
        'SDR': `Você é um SDR (Sales Development Representative) especializado em qualificação de leads e primeiro contato.

OBJETIVOS:
- Qualificar leads rapidamente
- Identificar necessidades e pain points
- Agendar reuniões ou demos quando apropriado
- Criar interesse genuíno no produto/serviço

TOM:
- Profissional mas amigável
- Consultivo, não agressivo
- Focado em ajudar, não em vender
- Respeitoso com o tempo do cliente

REGRAS:
- Faça perguntas abertas para entender necessidades
- Escute mais do que fala
- Seja honesto sobre limitações do produto
- Escale para humano se cliente pedir ou se houver objeções complexas
- Use as tools disponíveis para buscar informações quando necessário`,

        'CS': `Você é um agente de Customer Success focado em garantir satisfação e sucesso dos clientes.

OBJETIVOS:
- Resolver dúvidas e problemas rapidamente
- Proativar soluções antes que problemas ocorram
- Manter relacionamento positivo
- Identificar oportunidades de upsell quando apropriado

TOM:
- Empático e compreensivo
- Solucionador de problemas
- Paciente e educado
- Proativo

REGRAS:
- Priorize resolver problemas do cliente
- Use as tools para buscar informações de pedidos, produtos, etc
- Se não souber algo, seja honesto e busque a informação
- Escale para humano se problema for complexo ou cliente pedir
- Sempre confirme se problema foi resolvido`,

        'SUPPORT': `Você é um agente de suporte técnico especializado em resolver problemas técnicos.

OBJETIVOS:
- Diagnosticar problemas técnicos rapidamente
- Fornecer soluções passo a passo
- Educar clientes sobre o produto
- Reduzir tempo de resolução

TOM:
- Técnico mas acessível
- Paciente e didático
- Claro e objetivo
- Profissional

REGRAS:
- Faça perguntas específicas para diagnosticar
- Use as tools para buscar informações técnicas
- Forneça soluções passo a passo quando possível
- Documente problemas recorrentes
- Escale para humano se problema for crítico ou não conseguir resolver`
    };
    
    Swal.fire({
        title: 'Escolha um Template',
        html: `
            <div class="text-start">
                <button class="btn btn-light-primary w-100 mb-2" onclick="selectTemplate('${mode}', 'SDR')">
                    <strong>SDR</strong> - Sales Development Representative
                </button>
                <button class="btn btn-light-info w-100 mb-2" onclick="selectTemplate('${mode}', 'CS')">
                    <strong>CS</strong> - Customer Success
                </button>
                <button class="btn btn-light-success w-100 mb-2" onclick="selectTemplate('${mode}', 'SUPPORT')">
                    <strong>SUPPORT</strong> - Suporte Técnico
                </button>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        width: '500px'
    });
    
    window.selectTemplate = function(mode, templateKey) {
        const promptTextarea = document.getElementById(mode + '_prompt');
        promptTextarea.value = templates[templateKey] || '';
        updatePromptCharCount(mode);
        Swal.close();
    };
}

function updatePromptCharCount(mode) {
    const promptTextarea = document.getElementById(mode + '_prompt');
    const charCount = document.getElementById(mode + '_prompt_char_count');
    if (promptTextarea && charCount) {
        const count = promptTextarea.value.length;
        charCount.textContent = count.toLocaleString('pt-BR') + ' caracteres';
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Contador de caracteres do prompt
    const newPrompt = document.getElementById("new_prompt");
    const editPrompt = document.getElementById("edit_prompt");
    
    if (newPrompt) {
        newPrompt.addEventListener("input", () => updatePromptCharCount('new'));
        updatePromptCharCount('new');
    }
    
    if (editPrompt) {
        editPrompt.addEventListener("input", () => updatePromptCharCount('edit'));
    }
    
    // Formulário de criação
    const form = document.getElementById("kt_modal_new_ai_agent_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("kt_modal_new_ai_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            // Converter enabled checkbox para boolean
            formData.set("enabled", form.querySelector("[name=enabled]").checked ? "1" : "0");
            
            // Separar tools dos outros dados
            const tools = [];
            formData.getAll("tools[]").forEach(toolId => {
                tools.push(parseInt(toolId));
            });
            
            // Remover tools[] do FormData e criar objeto
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key !== "tools[]") {
                    data[key] = value;
                }
            }
            data.tools = tools;
            
            fetch("' . \App\Helpers\Url::to('/ai-agents') . '", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso!",
                        text: data.message || "Agente de IA criado com sucesso!",
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: data.message || "Erro ao criar agente de IA"
                    });
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                Swal.fire({
                    icon: "error",
                    title: "Erro",
                    text: "Erro ao criar agente de IA"
                });
            });
        });
    }
    
    // Formulário de edição
    const editForm = document.getElementById("kt_modal_edit_ai_agent_form");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("kt_modal_edit_ai_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(editForm);
            const agentId = document.getElementById("edit_agent_id").value;
            
            // Converter enabled checkbox para boolean
            formData.set("enabled", document.getElementById("edit_enabled").checked ? "1" : "0");
            
            // Separar tools dos outros dados
            const tools = [];
            formData.getAll("tools[]").forEach(toolId => {
                tools.push(parseInt(toolId));
            });
            
            // Remover tools[] do FormData e criar objeto
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key !== "tools[]") {
                    data[key] = value;
                }
            }
            data.tools = tools;
            
            fetch("' . \App\Helpers\Url::to('/ai-agents') . '/" + agentId, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso!",
                        text: data.message || "Agente de IA atualizado com sucesso!",
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: data.message || "Erro ao atualizar agente de IA"
                    });
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                Swal.fire({
                    icon: "error",
                    title: "Erro",
                    text: "Erro ao atualizar agente de IA"
                });
            });
        });
    }
});
</script>
';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

