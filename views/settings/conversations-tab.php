<?php
/**
 * Aba de Configurações Avançadas de Conversas
 */
$cs = $conversationSettings ?? [];
$gl = $cs['global_limits'] ?? [];
$sla = $cs['sla'] ?? [];
$dist = $cs['distribution'] ?? [];
$pctDist = $cs['percentage_distribution'] ?? [];
$reassign = $cs['reassignment'] ?? [];
?>
<form id="kt_settings_conversations_form" class="form">
    <!--begin::Limites Globais-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Limites Globais</h4>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. Conversas por Agente</label>
                    <input type="number" name="max_conversations_per_agent" class="form-control form-control-solid" 
                           value="<?= $gl['max_conversations_per_agent'] ?? '' ?>" min="1" 
                           placeholder="Deixe vazio para ilimitado" />
                    <div class="form-text">Limite global de conversas simultâneas por agente (pode ser sobrescrito por limite individual)</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. Conversas por Setor</label>
                    <input type="number" name="max_conversations_per_department" class="form-control form-control-solid" 
                           value="<?= $gl['max_conversations_per_department'] ?? '' ?>" min="1" 
                           placeholder="Deixe vazio para ilimitado" />
                    <div class="form-text">Limite total de conversas simultâneas por setor</div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. Conversas por Funil</label>
                    <input type="number" name="max_conversations_per_funnel" class="form-control form-control-solid" 
                           value="<?= $gl['max_conversations_per_funnel'] ?? '' ?>" min="1" 
                           placeholder="Deixe vazio para ilimitado" />
                    <div class="form-text">Limite total de conversas simultâneas por funil</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máx. Conversas por Estágio</label>
                    <input type="number" name="max_conversations_per_stage" class="form-control form-control-solid" 
                           value="<?= $gl['max_conversations_per_stage'] ?? '' ?>" min="1" 
                           placeholder="Deixe vazio para ilimitado" />
                    <div class="form-text">Limite total de conversas simultâneas por estágio</div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Limites Globais-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::SLA-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">SLA (Service Level Agreement)</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="enable_sla_monitoring" class="form-check-input me-2" 
                       <?= ($sla['enable_sla_monitoring'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar monitoramento de SLA</span>
            </label>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Tempo de Primeira Resposta (minutos)</label>
                    <input type="number" name="sla_first_response_time" class="form-control form-control-solid" 
                           value="<?= $sla['first_response_time'] ?? 15 ?>" min="1" required />
                    <div class="form-text">Tempo máximo para primeira resposta do agente</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Tempo de Resolução (minutos)</label>
                    <input type="number" name="sla_resolution_time" class="form-control form-control-solid" 
                           value="<?= $sla['resolution_time'] ?? 60 ?>" min="1" required />
                    <div class="form-text">Tempo máximo para resolução da conversa</div>
                </div>
            </div>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="auto_reassign_on_sla_breach" class="form-check-input me-2" 
                       <?= ($sla['auto_reassign_on_sla_breach'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Reatribuir automaticamente quando SLA for excedido</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="fw-semibold fs-6 mb-2">Reatribuir após (minutos)</label>
            <input type="number" name="reassign_after_minutes" class="form-control form-control-solid" 
                   value="<?= $sla['reassign_after_minutes'] ?? 30 ?>" min="1" />
            <div class="form-text">Tempo após exceder SLA para reatribuir conversa</div>
        </div>
    </div>
    <!--end::SLA-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Distribuição-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Distribuição e Atribuição</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="enable_auto_assignment" class="form-check-input me-2" 
                       <?= ($dist['enable_auto_assignment'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar atribuição automática</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="fw-semibold fs-6 mb-2">Método de Distribuição</label>
            <select name="distribution_method" class="form-select form-select-solid">
                <option value="round_robin" <?= ($dist['method'] ?? 'round_robin') === 'round_robin' ? 'selected' : '' ?>>Round-Robin (Distribuição igual)</option>
                <option value="by_load" <?= ($dist['method'] ?? '') === 'by_load' ? 'selected' : '' ?>>Por Carga (Menor carga primeiro)</option>
                <option value="by_performance" <?= ($dist['method'] ?? '') === 'by_performance' ? 'selected' : '' ?>>Por Performance (Melhor performance primeiro)</option>
                <option value="by_specialty" <?= ($dist['method'] ?? '') === 'by_specialty' ? 'selected' : '' ?>>Por Especialidade</option>
                <option value="percentage" <?= ($dist['method'] ?? '') === 'percentage' ? 'selected' : '' ?>>Por Porcentagem</option>
            </select>
            <div class="form-text">Método usado para distribuir conversas automaticamente</div>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="consider_availability" class="form-check-input me-2" 
                       <?= ($dist['consider_availability'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Considerar status de disponibilidade (online/offline)</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="consider_max_conversations" class="form-check-input me-2" 
                       <?= ($dist['consider_max_conversations'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Considerar limite máximo de conversas</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="assign_to_ai_agent" class="form-check-input me-2" 
                       <?= ($dist['assign_to_ai_agent'] ?? false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Permitir atribuição a agentes de IA</span>
            </label>
            <div class="form-text">Se habilitado, conversas podem ser atribuídas a agentes de IA quando não houver agentes humanos disponíveis</div>
        </div>
    </div>
    <!--end::Distribuição-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Distribuição por Porcentagem-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Distribuição por Porcentagem</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="percentage_distribution_enabled" class="form-check-input me-2" 
                       id="percentage_distribution_enabled"
                       <?= ($pctDist['enabled'] ?? false) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar distribuição por porcentagem</span>
            </label>
            <div class="form-text">Permite definir porcentagens específicas de distribuição por agente ou setor</div>
        </div>
        <div id="percentage_distribution_rules_container" style="display: <?= ($pctDist['enabled'] ?? false) ? 'block' : 'none' ?>;">
            <div class="fv-row mb-7">
                <label class="fw-semibold fs-6 mb-2">Regras de Distribuição</label>
                <div id="percentage_rules_list" class="mb-3">
                    <?php 
                    $rules = $pctDist['rules'] ?? [];
                    foreach ($rules as $index => $rule): 
                    ?>
                    <div class="d-flex gap-2 mb-2 percentage-rule-item" data-index="<?= $index ?>">
                        <select name="percentage_rule_type[]" class="form-select form-select-solid" style="width: 150px;">
                            <option value="agent" <?= ($rule['agent_id'] ?? null) ? 'selected' : '' ?>>Agente</option>
                            <option value="department" <?= ($rule['department_id'] ?? null) ? 'selected' : '' ?>>Setor</option>
                        </select>
                        <select name="percentage_rule_id[]" class="form-select form-select-solid percentage-rule-select" style="flex: 1;">
                            <?php if (isset($rule['agent_id'])): ?>
                                <?php foreach ($users ?? [] as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $rule['agent_id'] == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif (isset($rule['department_id'])): ?>
                                <?php foreach ($departments ?? [] as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= $rule['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <input type="number" name="percentage_rule_value[]" class="form-control form-control-solid" 
                               value="<?= $rule['percentage'] ?? 0 ?>" min="0" max="100" 
                               placeholder="%" style="width: 100px;" />
                        <button type="button" class="btn btn-sm btn-light-danger remove-percentage-rule">
                            <i class="ki-duotone ki-trash fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-light-primary" id="add_percentage_rule">
                    <i class="ki-duotone ki-plus fs-5 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Adicionar Regra
                </button>
                <input type="hidden" name="percentage_distribution_rules" id="percentage_distribution_rules" value="" />
            </div>
        </div>
    </div>
    <!--end::Distribuição por Porcentagem-->
    
    <div class="separator separator-dashed my-10"></div>
    
    <!--begin::Reatribuição-->
    <div class="mb-10">
        <h4 class="fw-bold mb-4">Reatribuição Automática</h4>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="enable_auto_reassignment" class="form-check-input me-2" 
                       <?= ($reassign['enable_auto_reassignment'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Habilitar reatribuição automática</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="reassign_on_sla_breach" class="form-check-input me-2" 
                       <?= ($reassign['reassign_on_sla_breach'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Reatribuir quando SLA for excedido</span>
            </label>
        </div>
        <div class="fv-row mb-7">
            <label class="d-flex align-items-center">
                <input type="checkbox" name="reassign_on_agent_offline" class="form-check-input me-2" 
                       <?= ($reassign['reassign_on_agent_offline'] ?? true) ? 'checked' : '' ?> />
                <span class="fw-semibold fs-6">Reatribuir quando agente ficar offline</span>
            </label>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Reatribuir por Inatividade (minutos)</label>
                    <input type="number" name="reassign_on_inactivity_minutes" class="form-control form-control-solid" 
                           value="<?= $reassign['reassign_on_inactivity_minutes'] ?? 60 ?>" min="1" />
                    <div class="form-text">Tempo sem resposta do agente para reatribuir</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Máximo de Reatribuições</label>
                    <input type="number" name="max_reassignments" class="form-control form-control-solid" 
                           value="<?= $reassign['max_reassignments'] ?? 3 ?>" min="1" />
                    <div class="form-text">Número máximo de reatribuições por conversa</div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Reatribuição-->
    
    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <span class="indicator-label">Salvar Configurações</span>
            <span class="indicator-progress">Aguarde...
            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
        </button>
    </div>
</form>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_settings_conversations_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            submitConversationsForm(this);
        });
    }
    
    // Toggle distribuição por porcentagem
    const percentageEnabled = document.getElementById("percentage_distribution_enabled");
    const percentageContainer = document.getElementById("percentage_distribution_rules_container");
    if (percentageEnabled && percentageContainer) {
        percentageEnabled.addEventListener("change", function() {
            percentageContainer.style.display = this.checked ? "block" : "none";
        });
    }
    
    // Adicionar regra de porcentagem
    const addRuleBtn = document.getElementById("add_percentage_rule");
    const rulesList = document.getElementById("percentage_rules_list");
    if (addRuleBtn && rulesList) {
        addRuleBtn.addEventListener("click", function() {
            const index = rulesList.children.length;
            const ruleHtml = `
                <div class="d-flex gap-2 mb-2 percentage-rule-item" data-index="${index}">
                    <select name="percentage_rule_type[]" class="form-select form-select-solid percentage-rule-type" style="width: 150px;">
                        <option value="agent">Agente</option>
                        <option value="department">Setor</option>
                    </select>
                    <select name="percentage_rule_id[]" class="form-select form-select-solid percentage-rule-select" style="flex: 1;">
                        <option value="">Selecione...</option>
                    </select>
                    <input type="number" name="percentage_rule_value[]" class="form-control form-control-solid" 
                           value="0" min="0" max="100" placeholder="%" style="width: 100px;" />
                    <button type="button" class="btn btn-sm btn-light-danger remove-percentage-rule">
                        <i class="ki-duotone ki-trash fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </button>
                </div>
            `;
            rulesList.insertAdjacentHTML("beforeend", ruleHtml);
            updatePercentageRuleSelect(rulesList.lastElementChild);
        });
    }
    
    // Remover regra de porcentagem
    document.addEventListener("click", function(e) {
        if (e.target.closest(".remove-percentage-rule")) {
            e.target.closest(".percentage-rule-item").remove();
            updatePercentageRulesHidden();
        }
    });
    
    // Atualizar select quando tipo mudar
    document.addEventListener("change", function(e) {
        if (e.target.classList.contains("percentage-rule-type")) {
            updatePercentageRuleSelect(e.target.closest(".percentage-rule-item"));
        }
    });
    
    function updatePercentageRuleSelect(ruleItem) {
        const typeSelect = ruleItem.querySelector(".percentage-rule-type");
        const idSelect = ruleItem.querySelector(".percentage-rule-select");
        const type = typeSelect.value;
        
        idSelect.innerHTML = '<option value="">Selecione...</option>';
        
        if (type === "agent") {
            <?php foreach ($users ?? [] as $user): ?>
            idSelect.innerHTML += `<option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></option>`;
            <?php endforeach; ?>
        } else if (type === "department") {
            <?php foreach ($departments ?? [] as $dept): ?>
            idSelect.innerHTML += `<option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name'], ENT_QUOTES) ?></option>`;
            <?php endforeach; ?>
        }
    }
    
    function updatePercentageRulesHidden() {
        const rules = [];
        document.querySelectorAll(".percentage-rule-item").forEach(function(item) {
            const type = item.querySelector(".percentage-rule-type").value;
            const id = item.querySelector(".percentage-rule-select").value;
            const percentage = item.querySelector("input[type=\"number\"]").value;
            
            if (id && percentage) {
                const rule = { percentage: parseInt(percentage) };
                if (type === "agent") {
                    rule.agent_id = parseInt(id);
                } else {
                    rule.department_id = parseInt(id);
                }
                rules.push(rule);
            }
        });
        
        document.getElementById("percentage_distribution_rules").value = JSON.stringify(rules);
    }
    
    // Atualizar hidden input quando valores mudarem
    document.addEventListener("input", function(e) {
        if (e.target.closest(".percentage-rule-item")) {
            updatePercentageRulesHidden();
        }
    });
    
    // Atualizar na inicialização
    updatePercentageRulesHidden();
    
    function submitConversationsForm(form) {
        // Atualizar regras de porcentagem antes de enviar
        updatePercentageRulesHidden();
        
        const submitBtn = form.querySelector("button[type=\"submit\"]");
        submitBtn.setAttribute("data-kt-indicator", "on");
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        fetch("<?= \App\Helpers\Url::to('/settings/conversations') ?>", {
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
                alert(data.message || "Configurações salvas com sucesso!");
            } else {
                alert("Erro: " + (data.message || "Erro ao salvar configurações"));
            }
        })
        .catch(error => {
            submitBtn.removeAttribute("data-kt-indicator");
            submitBtn.disabled = false;
            alert("Erro ao salvar configurações");
        });
    }
});
</script>
