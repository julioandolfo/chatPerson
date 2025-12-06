<?php
$layout = 'layouts.metronic.app';
$title = 'Agente de IA - ' . htmlspecialchars($agent['name'] ?? '');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <h3 class="fw-bold m-0"><?= htmlspecialchars($agent['name']) ?></h3>
                <span class="text-muted fs-7 mt-1"><?= htmlspecialchars($agent['description'] ?? '') ?></span>
            </div>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
            <button type="button" class="btn btn-sm btn-light-primary me-3" data-bs-toggle="modal" data-bs-target="#kt_modal_edit_ai_agent">
                <i class="ki-duotone ki-pencil fs-2"></i>
                Editar
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="row">
            <div class="col-lg-8">
                <!--begin::Informações Básicas-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Informações Básicas</h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-7">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Tipo</label>
                                <div>
                                    <span class="badge badge-light-info"><?= htmlspecialchars($agent['agent_type']) ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Modelo</label>
                                <div>
                                    <span class="text-gray-800"><?= htmlspecialchars($agent['model'] ?? 'gpt-4') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 mb-2">Temperature</label>
                                <div>
                                    <span class="text-gray-800"><?= htmlspecialchars($agent['temperature'] ?? '0.7') ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 mb-2">Max Tokens</label>
                                <div>
                                    <span class="text-gray-800"><?= htmlspecialchars($agent['max_tokens'] ?? '2000') ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-semibold fs-6 mb-2">Status</label>
                                <div>
                                    <?php if ($agent['enabled']): ?>
                                        <span class="badge badge-light-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="fw-semibold fs-6 mb-2">Conversas</label>
                                <div>
                                    <?php 
                                    $current = $agent['current_conversations'] ?? 0;
                                    $max = $agent['max_conversations'] ?? null;
                                    ?>
                                    <span class="text-gray-800"><?= $current ?> / <?= $max ?? '∞' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Informações Básicas-->
                
                <!--begin::Prompt-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Prompt do Sistema</h3>
                    </div>
                    <div class="card-body">
                        <div class="bg-light p-5 rounded">
                            <pre class="text-gray-800 fs-6" style="white-space: pre-wrap;"><?= htmlspecialchars($agent['prompt']) ?></pre>
                        </div>
                    </div>
                </div>
                <!--end::Prompt-->
                
                <?php 
                // Calcular tools disponíveis para adicionar
                $availableTools = [];
                foreach ($allTools as $tool) {
                    $alreadyAdded = false;
                    foreach ($agent['tools'] ?? [] as $agentTool) {
                        if ($agentTool['id'] == $tool['id']) {
                            $alreadyAdded = true;
                            break;
                        }
                    }
                    if (!$alreadyAdded) {
                        $availableTools[] = $tool;
                    }
                }
                ?>
                
                <!--begin::Tools-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Tools Disponíveis</h3>
                        <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
                        <div class="card-toolbar">
                            <?php if (!empty($availableTools) || empty($agent['tools'])): ?>
                            <?php if ($hasAvailableTools || empty($agent['tools'])): ?>
                            <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_add_tool">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Adicionar Tool
                            </button>
                            <?php else: ?>
                            <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="btn btn-sm btn-light-info">
                                <i class="ki-duotone ki-setting-2 fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Gerenciar Tools
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($agent['tools'])): ?>
                            <div class="text-center py-10">
                                <i class="ki-duotone ki-setting-2 fs-3x text-gray-400 mb-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma tool configurada</h3>
                                <div class="text-gray-500 fs-6 mb-7">
                                    <?php if (empty($allTools)): ?>
                                        Não há tools disponíveis no sistema. 
                                        <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="text-primary">Criar primeira tool</a>
                                    <?php else: ?>
                                        Adicione tools para este agente clicando no botão acima.
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle table-row-dashed fs-6 gy-5">
                                    <thead>
                                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                            <th class="min-w-200px">Nome</th>
                                            <th class="min-w-100px">Tipo</th>
                                            <th class="min-w-150px">Descrição</th>
                                            <th class="min-w-100px">Status</th>
                                            <th class="text-end min-w-70px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agent['tools'] as $tool): ?>
                                            <tr>
                                                <td>
                                                    <span class="text-gray-800 fw-bold"><?= htmlspecialchars($tool['name']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light-primary"><?= htmlspecialchars($tool['tool_type']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-gray-600"><?= htmlspecialchars(mb_substr($tool['description'] ?? '', 0, 50)) ?><?= mb_strlen($tool['description'] ?? '') > 50 ? '...' : '' ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($tool['tool_enabled'] ?? true): ?>
                                                        <span class="badge badge-light-success">Ativa</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light-secondary">Inativa</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-light-danger" onclick="removeTool(<?= $agent['id'] ?>, <?= $tool['id'] ?>)">
                                                        Remover
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
                <!--end::Tools-->
            </div>
            
            <div class="col-lg-4">
                <!--begin::Estatísticas-->
                <div class="card mb-5">
                    <div class="card-header">
                        <h3 class="card-title">Estatísticas</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($agent['stats'])): ?>
                            <div class="d-flex flex-column gap-5">
                                <div>
                                    <div class="text-gray-600 fs-7 mb-1">Total de Conversas</div>
                                    <div class="text-gray-900 fw-bold fs-2"><?= $agent['stats']['total_conversations'] ?? 0 ?></div>
                                </div>
                                <div>
                                    <div class="text-gray-600 fs-7 mb-1">Tokens Utilizados</div>
                                    <div class="text-gray-900 fw-bold fs-2"><?= number_format($agent['stats']['total_tokens'] ?? 0, 0, ',', '.') ?></div>
                                </div>
                                <div>
                                    <div class="text-gray-600 fs-7 mb-1">Custo Total</div>
                                    <div class="text-gray-900 fw-bold fs-2">$<?= number_format($agent['stats']['total_cost'] ?? 0, 4, ',', '.') ?></div>
                                </div>
                                <div>
                                    <div class="text-gray-600 fs-7 mb-1">Conversas Completadas</div>
                                    <div class="text-gray-900 fw-bold fs-2"><?= $agent['stats']['completed_conversations'] ?? 0 ?></div>
                                </div>
                                <div>
                                    <div class="text-gray-600 fs-7 mb-1">Escaladas</div>
                                    <div class="text-gray-900 fw-bold fs-2"><?= $agent['stats']['escalated_conversations'] ?? 0 ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10">
                                <p class="text-muted">Nenhuma estatística disponível ainda.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Estatísticas-->
            </div>
        </div>
    </div>
</div>
<!--end::Card-->

<?php 
// Calcular tools disponíveis para adicionar
$availableTools = [];
foreach ($allTools as $tool) {
    $alreadyAdded = false;
    foreach ($agent['tools'] ?? [] as $agentTool) {
        if ($agentTool['id'] == $tool['id']) {
            $alreadyAdded = true;
            break;
        }
    }
    if (!$alreadyAdded) {
        $availableTools[] = $tool;
    }
}
?>

<!--begin::Modal - Adicionar Tool-->
<div class="modal fade" id="kt_modal_add_tool" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Tool ao Agente</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_add_tool_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>" />
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tool</label>
                        <?php if (empty($availableTools)): ?>
                            <div class="alert alert-warning">
                                <i class="ki-duotone ki-information-5 fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Todas as tools disponíveis já foram adicionadas a este agente. 
                                <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="alert-link">Criar nova tool</a>
                            </div>
                        <?php else: ?>
                            <select name="tool_id" class="form-select form-select-solid" required>
                                <option value="">Selecione uma tool</option>
                                <?php foreach ($availableTools as $tool): ?>
                                    <option value="<?= $tool['id'] ?>"><?= htmlspecialchars($tool['name']) ?> (<?= htmlspecialchars($tool['tool_type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="enabled" class="form-check-input me-2" checked />
                            Tool ativa para este agente
                        </label>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <?php if (!empty($availableTools)): ?>
                    <button type="submit" id="kt_modal_add_tool_submit" class="btn btn-primary">
                        <span class="indicator-label">Adicionar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                    <?php else: ?>
                    <a href="<?= \App\Helpers\Url::to('/ai-tools') ?>" class="btn btn-primary">
                        Criar Nova Tool
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Adicionar Tool-->

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
                <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" id="edit_agent_name" class="form-control form-control-solid" placeholder="Nome do agente" value="<?= htmlspecialchars($agent['name']) ?>" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" id="edit_agent_description" class="form-control form-control-solid" rows="3" placeholder="Descrição do agente"><?= htmlspecialchars($agent['description'] ?? '') ?></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tipo</label>
                        <select name="agent_type" id="edit_agent_type" class="form-select form-select-solid" required>
                            <option value="SDR" <?= ($agent['agent_type'] ?? '') === 'SDR' ? 'selected' : '' ?>>SDR (Sales Development Representative)</option>
                            <option value="CS" <?= ($agent['agent_type'] ?? '') === 'CS' ? 'selected' : '' ?>>CS (Customer Success)</option>
                            <option value="CLOSER" <?= ($agent['agent_type'] ?? '') === 'CLOSER' ? 'selected' : '' ?>>CLOSER (Fechamento de Vendas)</option>
                            <option value="FOLLOWUP" <?= ($agent['agent_type'] ?? '') === 'FOLLOWUP' ? 'selected' : '' ?>>FOLLOWUP (Follow-up Automático)</option>
                            <option value="SUPPORT" <?= ($agent['agent_type'] ?? '') === 'SUPPORT' ? 'selected' : '' ?>>SUPPORT (Suporte Técnico)</option>
                            <option value="ONBOARDING" <?= ($agent['agent_type'] ?? '') === 'ONBOARDING' ? 'selected' : '' ?>>ONBOARDING (Onboarding)</option>
                            <option value="GENERAL" <?= ($agent['agent_type'] ?? '') === 'GENERAL' ? 'selected' : '' ?>>GENERAL (Geral)</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Prompt do Sistema</label>
                        <textarea name="prompt" id="edit_agent_prompt" class="form-control form-control-solid" rows="8" placeholder="Digite o prompt do sistema que define o comportamento do agente..." required><?= htmlspecialchars($agent['prompt']) ?></textarea>
                        <div class="form-text">Este prompt será usado como instrução para o agente de IA. Seja específico sobre o papel, tom e comportamento esperado.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Modelo</label>
                                <select name="model" id="edit_agent_model" class="form-select form-select-solid">
                                    <option value="gpt-4" <?= ($agent['model'] ?? 'gpt-4') === 'gpt-4' ? 'selected' : '' ?>>GPT-4</option>
                                    <option value="gpt-4-turbo" <?= ($agent['model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                                    <option value="gpt-3.5-turbo" <?= ($agent['model'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Temperature</label>
                                <input type="number" name="temperature" id="edit_agent_temperature" class="form-control form-control-solid" value="<?= htmlspecialchars($agent['temperature'] ?? '0.7') ?>" step="0.1" min="0" max="2" />
                                <div class="form-text">0.0 = Determinístico, 2.0 = Criativo</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="fv-row mb-7">
                                <label class="fw-semibold fs-6 mb-2">Max Tokens</label>
                                <input type="number" name="max_tokens" id="edit_agent_max_tokens" class="form-control form-control-solid" value="<?= htmlspecialchars($agent['max_tokens'] ?? '2000') ?>" min="1" />
                            </div>
                        </div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" id="edit_agent_max_conversations" class="form-control form-control-solid" placeholder="Deixe em branco para ilimitado" value="<?= htmlspecialchars($agent['max_conversations'] ?? '') ?>" min="1" />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="d-flex align-items-center">
                            <input type="checkbox" name="enabled" id="edit_agent_enabled" class="form-check-input me-2" <?= ($agent['enabled'] ?? true) ? 'checked' : '' ?> />
                            Agente ativo
                        </label>
                    </div>
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
function removeTool(agentId, toolId) {
    Swal.fire({
        title: "Tem certeza?",
        text: "Esta tool será removida do agente. Esta ação não pode ser desfeita.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sim, remover",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#d33"
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("' . \App\Helpers\Url::to('/ai-agents') . '/" + agentId + "/tools/" + toolId, {
                method: "DELETE",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso!",
                        text: data.message || "Tool removida com sucesso!",
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: data.message || "Erro ao remover tool"
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: "error",
                    title: "Erro",
                    text: "Erro ao remover tool"
                });
            });
        }
    });
}

document.addEventListener("DOMContentLoaded", function() {
    const addToolForm = document.getElementById("kt_modal_add_tool_form");
    if (addToolForm) {
        addToolForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("kt_modal_add_tool_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(addToolForm);
            
            fetch("' . \App\Helpers\Url::to('/ai-agents') . '/" + formData.get("agent_id") + "/tools", {
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
                    Swal.fire({
                        icon: "success",
                        title: "Sucesso!",
                        text: data.message || "Tool adicionada com sucesso!",
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: data.message || "Erro ao adicionar tool"
                    });
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                Swal.fire({
                    icon: "error",
                    title: "Erro",
                    text: "Erro ao adicionar tool"
                });
            });
        });
    }
    
    const editAgentForm = document.getElementById("kt_modal_edit_ai_agent_form");
    if (editAgentForm) {
        editAgentForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("kt_modal_edit_ai_agent_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(editAgentForm);
            const agentId = formData.get("agent_id");
            
            // Converter enabled checkbox para boolean
            formData.set("enabled", document.getElementById("edit_agent_enabled").checked ? "1" : "0");
            
            fetch("' . \App\Helpers\Url::to('/ai-agents') . '/" + agentId, {
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

