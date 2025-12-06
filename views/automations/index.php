<?php
$layout = 'layouts.metronic.app';
$title = 'Automações';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Automações</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('automations.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_automation">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Automação
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($automations)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-gear fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma automação encontrada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova automação.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-150px">Gatilho</th>
                            <th class="min-w-150px">Funil/Estágio</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-150px">Criado</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        <?php foreach ($automations as $automation): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-bold"><?= htmlspecialchars($automation['name']) ?></span>
                                        <?php if (!empty($automation['description'])): ?>
                                            <span class="text-muted fs-7"><?= htmlspecialchars(mb_substr($automation['description'], 0, 50)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $triggerLabels = [
                                        'new_conversation' => 'Nova Conversa',
                                        'message_received' => 'Mensagem Recebida',
                                        'conversation_updated' => 'Conversa Atualizada',
                                        'conversation_moved' => 'Conversa Movida',
                                        'conversation_resolved' => 'Conversa Resolvida',
                                        'time_based' => 'Baseado em Tempo',
                                        'contact_created' => 'Contato Criado',
                                        'contact_updated' => 'Contato Atualizado',
                                        'agent_activity' => 'Atividade do Agente',
                                        'webhook' => 'Webhook Externo'
                                    ];
                                    $triggerLabel = $triggerLabels[$automation['trigger_type']] ?? $automation['trigger_type'];
                                    ?>
                                    <span class="badge badge-light-info"><?= htmlspecialchars($triggerLabel) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($automation['funnel_name']) || !empty($automation['stage_name'])): ?>
                                        <div class="d-flex flex-column">
                                            <?php if (!empty($automation['funnel_name'])): ?>
                                                <span class="text-gray-800 fw-semibold"><?= htmlspecialchars($automation['funnel_name']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($automation['stage_name'])): ?>
                                                <span class="text-muted fs-7">→ <?= htmlspecialchars($automation['stage_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted fs-7">Todos os funis/estágios</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = $automation['status'] === 'active' && $automation['is_active'] ? 'success' : 'secondary';
                                    $statusText = $automation['status'] === 'active' && $automation['is_active'] ? 'Ativa' : 'Inativa';
                                    ?>
                                    <span class="badge badge-light-<?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-muted"><?= date('d/m/Y', strtotime($automation['created_at'])) ?></span>
                                        <?php
                                        try {
                                            $stats = \App\Models\AutomationExecution::getStats($automation['id']);
                                            if ($stats && $stats['total'] > 0):
                                        ?>
                                            <span class="text-muted fs-7">
                                                <?= $stats['completed'] ?> execuções
                                            </span>
                                        <?php
                                            endif;
                                        } catch (\Exception $e) {
                                            // Ignorar erros
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="<?= \App\Helpers\Url::to('/automations/' . $automation['id']) ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                            Editar
                                        </a>
                                        <a href="<?= \App\Helpers\Url::to('/automations/' . $automation['id']) ?>#logs" class="btn btn-sm btn-light btn-active-light-info">
                                            Logs
                                        </a>
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

<!--begin::Modal - Nova Automação-->
<?php if (\App\Helpers\Permission::can('automations.create')): ?>
<div class="modal fade" id="kt_modal_new_automation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Automação</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_automation_form" class="form" action="<?= \App\Helpers\Url::to('/automations') ?>" method="POST">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Nome da automação" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Descrição da automação"></textarea>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Gatilho</label>
                        <select name="trigger_type" id="kt_trigger_type" class="form-select form-select-solid" required>
                            <option value="new_conversation">Nova Conversa</option>
                            <option value="message_received">Mensagem Recebida</option>
                            <option value="conversation_updated">Conversa Atualizada</option>
                            <option value="conversation_moved">Conversa Movida no Funil</option>
                            <option value="conversation_resolved">Conversa Resolvida</option>
                            <option value="time_based">Baseado em Tempo (Agendado)</option>
                            <option value="contact_created">Contato Criado</option>
                            <option value="contact_updated">Contato Atualizado</option>
                            <option value="agent_activity">Atividade do Agente</option>
                            <option value="webhook">Webhook Externo</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7" id="kt_funnel_stage_container">
                        <label class="fw-semibold fs-6 mb-2">Vincular a Funil/Estágio</label>
                        <div class="text-muted fs-7 mb-3">Deixe vazio para aplicar a todos os funis/estágios</div>
                        <select name="funnel_id" id="kt_automation_funnel_select" class="form-select form-select-solid mb-3">
                            <option value="">Todos os Funis</option>
                            <?php
                            $allFunnels = \App\Models\Funnel::whereActive();
                            foreach ($allFunnels as $funnel):
                            ?>
                                <option value="<?= $funnel['id'] ?>"><?= htmlspecialchars($funnel['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="stage_id" id="kt_automation_stage_select" class="form-select form-select-solid">
                            <option value="">Todos os Estágios</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="active">Ativa</option>
                            <option value="inactive">Inativa</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_automation_submit" class="btn btn-primary">
                        <span class="indicator-label">Criar e Editar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Nova Automação-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Carregar estágios quando funil for selecionado
    const funnelSelect = document.getElementById("kt_automation_funnel_select");
    const stageSelect = document.getElementById("kt_automation_stage_select");
    
    // Função para carregar estágios
    function loadStages(funnelId) {
        stageSelect.innerHTML = "<option value=\"\">Todos os Estágios</option>";
        
        // Se não há funil selecionado, carregar todos os estágios de todos os funis
        const url = funnelId ? 
            "' . \App\Helpers\Url::to('/funnels') . '/" + funnelId + "/stages" : 
            "' . \App\Helpers\Url::to('/funnels') . '/0/stages";
        
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
                        stageSelect.appendChild(option);
                    });
                    stageSelect.disabled = false;
                } else {
                    console.error("Erro ao carregar estágios:", data.message || "Resposta inválida");
                    stageSelect.disabled = true;
                }
            })
            .catch(error => {
                console.error("Erro ao carregar estágios:", error);
                stageSelect.innerHTML = "<option value=\"\">Erro ao carregar estágios</option>";
                stageSelect.disabled = true;
            });
    }
    
    if (funnelSelect && stageSelect) {
        // Carregar estágios quando funil mudar
        funnelSelect.addEventListener("change", function() {
            const funnelId = this.value;
            loadStages(funnelId);
        });
        
        // Carregar estágios iniciais se não há funil selecionado
        if (!funnelSelect.value) {
            loadStages(null);
        }
    }
    
    // Mostrar/ocultar container de funil/estágio baseado no trigger
    const triggerTypeSelect = document.getElementById("kt_trigger_type");
    const funnelStageContainer = document.getElementById("kt_funnel_stage_container");
    
    if (triggerTypeSelect && funnelStageContainer) {
        const triggersWithFunnel = ["new_conversation", "conversation_moved", "conversation_updated", "conversation_resolved"];
        
        triggerTypeSelect.addEventListener("change", function() {
            if (triggersWithFunnel.includes(this.value)) {
                funnelStageContainer.style.display = "block";
            } else {
                funnelStageContainer.style.display = "none";
                if (funnelSelect) funnelSelect.value = "";
                if (stageSelect) {
                    stageSelect.innerHTML = "<option value=\"\">Todos os Estágios</option>";
                    stageSelect.disabled = true;
                }
            }
        });
        
        // Verificar estado inicial
        if (!triggersWithFunnel.includes(triggerTypeSelect.value)) {
            funnelStageContainer.style.display = "none";
        }
    }
    
    const form = document.getElementById("kt_modal_new_automation_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_automation_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            fetch(form.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: new URLSearchParams(new FormData(form))
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_automation"));
                    modal.hide();
                    // Redirecionar para editor
                    window.location.href = "' . \App\Helpers\Url::to('/automations') . '/" + data.id;
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar automação"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar automação");
            });
        });
    }
});
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
