<?php
/**
 * View: Formulário de Metas (Criar/Editar)
 */
use App\Helpers\Url;

$isEdit = isset($goal);
$layout = 'layouts.metronic.app';
$title = $isEdit ? 'Editar Meta' : 'Nova Meta';

ob_start();
?>

<div class="d-flex flex-column flex-lg-row">
    <!--begin::Content-->
    <div class="flex-lg-row-fluid me-lg-15 mb-10 mb-lg-0">
        <form action="<?= Url::to($isEdit ? '/goals/update' : '/goals/store') ?>" method="POST" id="goal-form">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= $goal['id'] ?>">
            <?php endif; ?>
            
            <!--begin::Card-->
            <div class="card card-flush">
                <!--begin::Card header-->
                <div class="card-header">
                    <div class="card-title">
                        <h2><?= $title ?></h2>
                    </div>
                </div>
                <!--end::Card header-->
                
                <!--begin::Card body-->
                <div class="card-body pt-0">
                    <!--begin::Informações Básicas-->
                    <div class="mb-10">
                        <h3 class="fs-5 fw-bold mb-5">Informações Básicas</h3>
                        
                        <div class="row mb-5">
                            <div class="col-md-12">
                                <label class="required form-label">Nome da Meta</label>
                                <input type="text" class="form-control" name="name" 
                                       value="<?= htmlspecialchars($goal['name'] ?? '') ?>" 
                                       placeholder="Ex: Meta de Vendas Janeiro 2026" required>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-md-12">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Descreva os detalhes da meta..."><?= htmlspecialchars($goal['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    <!--end::Informações Básicas-->
                    
                    <!--begin::Configuração da Meta-->
                    <div class="mb-10">
                        <h3 class="fs-5 fw-bold mb-5">Configuração da Meta</h3>
                        
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="required form-label">Tipo de Métrica</label>
                                <select class="form-select" name="type" id="goal-type" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($types as $value => $config): ?>
                                        <option value="<?= $value ?>" 
                                                data-unit="<?= $config['unit'] ?>"
                                                <?= ($goal['type'] ?? '') === $value ? 'selected' : '' ?>>
                                            <?= $config['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="required form-label">Valor Alvo</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" name="target_value" 
                                           value="<?= $goal['target_value'] ?? '' ?>" 
                                           placeholder="0" required>
                                    <span class="input-group-text" id="target-unit">unidade</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="required form-label">Nível da Meta</label>
                                <select class="form-select" name="target_type" id="target-type" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($target_types as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($goal['target_type'] ?? '') === $value ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6" id="target-selection" style="display: none;">
                                <label class="required form-label" id="target-label">Selecionar</label>
                                <select class="form-select" name="target_id" id="target-id">
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!--end::Configuração da Meta-->
                    
                    <!--begin::Período-->
                    <div class="mb-10">
                        <h3 class="fs-5 fw-bold mb-5">Período</h3>
                        
                        <div class="row mb-5">
                            <div class="col-md-4">
                                <label class="required form-label">Tipo de Período</label>
                                <select class="form-select" name="period_type" id="period-type" required>
                                    <?php foreach ($periods as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($goal['period_type'] ?? 'monthly') === $value ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="required form-label">Data Início</label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?= $goal['start_date'] ?? date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="required form-label">Data Término</label>
                                <input type="date" class="form-control" name="end_date" 
                                       value="<?= $goal['end_date'] ?? date('Y-m-d', strtotime('+1 month')) ?>" required>
                            </div>
                        </div>
                    </div>
                    <!--end::Período-->
                    
                    <!--begin::Opções Avançadas-->
                    <div class="mb-10">
                        <h3 class="fs-5 fw-bold mb-5">Opções Avançadas</h3>
                        
                        <div class="row mb-5">
                            <div class="col-md-4">
                                <label class="form-label">Prioridade</label>
                                <select class="form-select" name="priority">
                                    <option value="low" <?= ($goal['priority'] ?? 'medium') === 'low' ? 'selected' : '' ?>>Baixa</option>
                                    <option value="medium" <?= ($goal['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Média</option>
                                    <option value="high" <?= ($goal['priority'] ?? 'medium') === 'high' ? 'selected' : '' ?>>Alta</option>
                                    <option value="critical" <?= ($goal['priority'] ?? 'medium') === 'critical' ? 'selected' : '' ?>>Crítica</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Notificar ao atingir (%)</label>
                                <input type="number" min="0" max="100" class="form-control" name="notify_at_percentage" 
                                       value="<?= $goal['notify_at_percentage'] ?? 90 ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Pontos de Recompensa</label>
                                <input type="number" min="0" class="form-control" name="reward_points" 
                                       value="<?= $goal['reward_points'] ?? 0 ?>">
                            </div>
                        </div>
                        
                        <div class="separator my-7"></div>
                        
                        <h4 class="fs-6 fw-bold mb-5">
                            <i class="bi bi-flag-fill text-danger me-2"></i>
                            Configuração de Flags e Alertas
                        </h4>
                        <p class="text-muted fs-7 mb-5">
                            Configure os thresholds para alertas visuais baseados no progresso da meta
                        </p>
                        
                        <div class="row mb-5">
                            <div class="col-md-4">
                                <label class="form-label">
                                    <span class="badge badge-danger me-2">🔴</span>
                                    Flag Crítica (Vermelho)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">Abaixo de</span>
                                    <input type="number" step="0.1" min="0" max="100" class="form-control" 
                                           name="flag_critical_threshold" 
                                           value="<?= $goal['flag_critical_threshold'] ?? 70.0 ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Situação crítica</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">
                                    <span class="badge badge-warning me-2">🟡</span>
                                    Flag Atenção (Amarelo)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">Abaixo de</span>
                                    <input type="number" step="0.1" min="0" max="100" class="form-control" 
                                           name="flag_warning_threshold" 
                                           value="<?= $goal['flag_warning_threshold'] ?? 85.0 ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Requer atenção</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">
                                    <span class="badge badge-success me-2">🟢</span>
                                    Flag Boa (Verde)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">Abaixo de</span>
                                    <input type="number" step="0.1" min="0" max="100" class="form-control" 
                                           name="flag_good_threshold" 
                                           value="<?= $goal['flag_good_threshold'] ?? 95.0 ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">No caminho certo</small>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="enable_projection" value="1"
                                           <?= ($goal['enable_projection'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        Habilitar Projeção de Atingimento
                                    </label>
                                </div>
                                <small class="text-muted">Calcula se está no ritmo esperado</small>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="alert_on_risk" value="1"
                                           <?= ($goal['alert_on_risk'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        Alertar Quando em Risco
                                    </label>
                                </div>
                                <small class="text-muted">Gera alertas automáticos</small>
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-md-12">
                                <label class="form-label">Badge de Recompensa</label>
                                <div class="d-flex flex-wrap gap-3" id="badge-selector">
                                    <?php 
                                    $badges = [
                                        ['value' => 'trophy', 'icon' => '🏆', 'name' => 'Troféu'],
                                        ['value' => 'medal_gold', 'icon' => '🥇', 'name' => 'Medalha Ouro'],
                                        ['value' => 'medal_silver', 'icon' => '🥈', 'name' => 'Medalha Prata'],
                                        ['value' => 'medal_bronze', 'icon' => '🥉', 'name' => 'Medalha Bronze'],
                                        ['value' => 'star', 'icon' => '⭐', 'name' => 'Estrela'],
                                        ['value' => 'fire', 'icon' => '🔥', 'name' => 'Fogo'],
                                        ['value' => 'rocket', 'icon' => '🚀', 'name' => 'Foguete'],
                                        ['value' => 'gem', 'icon' => '💎', 'name' => 'Diamante'],
                                        ['value' => 'crown', 'icon' => '👑', 'name' => 'Coroa'],
                                        ['value' => 'target', 'icon' => '🎯', 'name' => 'Alvo'],
                                        ['value' => 'money', 'icon' => '💰', 'name' => 'Dinheiro'],
                                        ['value' => 'chart', 'icon' => '📈', 'name' => 'Gráfico'],
                                    ];
                                    $currentBadge = $goal['reward_badge'] ?? '';
                                    foreach ($badges as $badge): 
                                    ?>
                                        <label class="badge-option">
                                            <input type="radio" name="reward_badge" value="<?= $badge['value'] ?>" 
                                                   <?= $currentBadge === $badge['value'] ? 'checked' : '' ?>
                                                   class="d-none">
                                            <div class="badge-box" title="<?= $badge['name'] ?>">
                                                <span class="badge-icon"><?= $badge['icon'] ?></span>
                                                <small class="badge-name"><?= $badge['name'] ?></small>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="reward_badge" id="selected-badge" value="<?= htmlspecialchars($currentBadge) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-5">
                            <div class="col-md-12">
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="is_stretch" value="1"
                                           <?= ($goal['is_stretch'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        Meta Desafiadora (Stretch Goal)
                                    </label>
                                </div>
                                <?php if ($isEdit): ?>
                                    <div class="form-check form-switch form-check-custom form-check-solid mt-3">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                               <?= ($goal['is_active'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label">
                                            Meta Ativa
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!--end::Opções Avançadas-->
                    
                    <!--begin::OTE e Bonificações-->
                    <div class="mb-10">
                        <div class="separator my-7"></div>
                        
                        <h3 class="fs-5 fw-bold mb-3">
                            <i class="bi bi-currency-dollar text-success me-2"></i>
                            OTE (On-Target Earnings) e Bonificações
                        </h3>
                        <p class="text-muted fs-7 mb-5">
                            Configure salário base, comissão esperada e níveis de bonificação por desempenho
                        </p>
                        
                        <div class="form-check form-switch form-check-custom form-check-solid mb-5">
                            <input class="form-check-input" type="checkbox" name="enable_bonus" value="1" id="enable_bonus"
                                   <?= ($goal['enable_bonus'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="enable_bonus">
                                Habilitar Sistema de Bonificações
                            </label>
                        </div>
                        
                        <div id="bonus-settings" style="display: <?= ($goal['enable_bonus'] ?? 0) ? 'block' : 'none' ?>;">
                            <div class="row mb-5">
                                <div class="col-md-4">
                                    <label class="form-label">Salário Base Mensal (R$)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               name="ote_base_salary" id="ote_base_salary"
                                               value="<?= $goal['ote_base_salary'] ?? '' ?>" 
                                               placeholder="3000.00">
                                    </div>
                                    <small class="text-muted">Salário fixo</small>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Comissão Target (100%) (R$)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               name="ote_target_commission" id="ote_target_commission"
                                               value="<?= $goal['ote_target_commission'] ?? '' ?>" 
                                               placeholder="2000.00">
                                    </div>
                                    <small class="text-muted">Comissão ao atingir 100%</small>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">OTE Total</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" class="form-control bg-light" id="ote_total" 
                                               value="<?= $goal['ote_total'] ?? '0.00' ?>" readonly>
                                    </div>
                                    <small class="text-muted">Base + Comissão</small>
                                </div>
                            </div>
                            
                            <div class="row mb-5">
                                <div class="col-md-12">
                                    <label class="form-label">Tipo de Cálculo de Bônus</label>
                                    <select class="form-select" name="bonus_calculation_type">
                                        <option value="tiered" <?= ($goal['bonus_calculation_type'] ?? 'tiered') === 'tiered' ? 'selected' : '' ?>>
                                            Escalonado (Tiers) - Substitui o anterior
                                        </option>
                                        <option value="fixed" <?= ($goal['bonus_calculation_type'] ?? 'tiered') === 'fixed' ? 'selected' : '' ?>>
                                            Fixo - Valor único ao atingir meta
                                        </option>
                                        <option value="percentage" <?= ($goal['bonus_calculation_type'] ?? 'tiered') === 'percentage' ? 'selected' : '' ?>>
                                            Percentual - % sobre o valor base
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="bi bi-info-circle fs-2 me-3"></i>
                                <div>
                                    <strong>Configuração de Tiers:</strong> Após salvar a meta, você poderá adicionar níveis de bonificação 
                                    (Bronze 50% = R$600, Prata 70% = R$1.000, Ouro 100% = R$2.000, etc).
                                    Ou <button type="button" class="btn btn-sm btn-light-primary ms-2" onclick="createDefaultTiers()">
                                        Criar Tiers Padrão Automaticamente
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::OTE e Bonificações-->
                </div>
                <!--end::Card body-->
                
                <!--begin::Card footer-->
                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <a href="<?= Url::to('/goals') ?>" class="btn btn-light btn-active-light-primary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? 'Atualizar Meta' : 'Criar Meta' ?>
                    </button>
                </div>
                <!--end::Card footer-->
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content-->
</div>

<script>
// Dados dos targets
const targetsData = {
    individual: <?= json_encode($agents) ?>,
    team: <?= json_encode($teams) ?>,
    department: <?= json_encode($departments) ?>
};

// Atualizar unidade quando tipo de meta muda
document.getElementById('goal-type').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const unit = selectedOption.getAttribute('data-unit') || 'unidade';
    document.getElementById('target-unit').textContent = unit;
});

// Trigger inicial
document.getElementById('goal-type').dispatchEvent(new Event('change'));

// Atualizar seleção de target quando muda o tipo
document.getElementById('target-type').addEventListener('change', function() {
    const targetType = this.value;
    const targetSelection = document.getElementById('target-selection');
    const targetId = document.getElementById('target-id');
    const targetLabel = document.getElementById('target-label');
    
    if (targetType === 'global') {
        targetSelection.style.display = 'none';
        targetId.required = false;
    } else {
        targetSelection.style.display = 'block';
        targetId.required = true;
        
        // Atualizar label
        const labels = {
            individual: 'Selecionar Agente',
            team: 'Selecionar Time',
            department: 'Selecionar Departamento'
        };
        targetLabel.textContent = labels[targetType] || 'Selecionar';
        
        // Preencher options
        targetId.innerHTML = '<option value="">Selecione...</option>';
        if (targetsData[targetType]) {
            targetsData[targetType].forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                <?php if ($isEdit): ?>
                    if (item.id == <?= $goal['target_id'] ?? 0 ?>) {
                        option.selected = true;
                    }
                <?php endif; ?>
                targetId.appendChild(option);
            });
        }
    }
});

// Trigger inicial
document.getElementById('target-type').dispatchEvent(new Event('change'));

// Auto-preencher datas baseado no período
document.getElementById('period-type').addEventListener('change', function() {
    const periodType = this.value;
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');
    
    const today = new Date();
    let start = new Date(today);
    let end = new Date(today);
    
    switch(periodType) {
        case 'daily':
            end.setDate(end.getDate() + 1);
            break;
        case 'weekly':
            end.setDate(end.getDate() + 7);
            break;
        case 'monthly':
            end.setMonth(end.getMonth() + 1);
            break;
        case 'quarterly':
            end.setMonth(end.getMonth() + 3);
            break;
        case 'yearly':
            end.setFullYear(end.getFullYear() + 1);
            break;
    }
    
    if (!startDate.value) {
        startDate.value = start.toISOString().split('T')[0];
    }
    if (!endDate.value || periodType !== 'custom') {
        endDate.value = end.toISOString().split('T')[0];
    }
});

// ========== BADGES ==========
// Seletor visual de badges
document.querySelectorAll('.badge-option input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remove seleção anterior
        document.querySelectorAll('.badge-box').forEach(box => {
            box.classList.remove('selected');
        });
        
        // Adiciona na nova seleção
        if (this.checked) {
            this.nextElementSibling.classList.add('selected');
            document.getElementById('selected-badge').value = this.value;
        }
    });
    
    // Marca o selecionado inicialmente
    if (radio.checked) {
        radio.nextElementSibling.classList.add('selected');
    }
});

// ========== OTE E BONIFICAÇÕES ==========
// Toggle da seção de bonificações
document.getElementById('enable_bonus')?.addEventListener('change', function() {
    const bonusSettings = document.getElementById('bonus-settings');
    if (this.checked) {
        bonusSettings.style.display = 'block';
    } else {
        bonusSettings.style.display = 'none';
    }
});

// Calcular OTE Total automaticamente
function calculateOTE() {
    const baseSalary = parseFloat(document.getElementById('ote_base_salary')?.value || 0);
    const targetCommission = parseFloat(document.getElementById('ote_target_commission')?.value || 0);
    const oteTotal = baseSalary + targetCommission;
    
    const oteTotalField = document.getElementById('ote_total');
    if (oteTotalField) {
        oteTotalField.value = oteTotal.toFixed(2);
    }
}

document.getElementById('ote_base_salary')?.addEventListener('input', calculateOTE);
document.getElementById('ote_target_commission')?.addEventListener('input', calculateOTE);

// Calcular OTE inicial
calculateOTE();

// Criar tiers padrão automaticamente
function createDefaultTiers() {
    const targetCommission = parseFloat(document.getElementById('ote_target_commission')?.value || 0);
    
    if (!targetCommission || targetCommission <= 0) {
        alert('Por favor, configure a Comissão Target (100%) antes de criar tiers padrão.');
        return;
    }
    
    const tiers = [
        { name: 'Bronze 🥉', threshold: 50, multiplier: 0.3 },
        { name: 'Prata 🥈', threshold: 70, multiplier: 0.5 },
        { name: 'Ouro 🥇', threshold: 90, multiplier: 0.8 },
        { name: 'Platina 💎', threshold: 100, multiplier: 1.0 },
        { name: 'Diamante 💠', threshold: 120, multiplier: 1.5 }
    ];
    
    let message = 'Tiers padrão que serão criados:\n\n';
    tiers.forEach(tier => {
        const bonus = (targetCommission * tier.multiplier).toFixed(2);
        message += `${tier.name}: ${tier.threshold}% = R$ ${bonus}\n`;
    });
    
    message += '\n✅ Esses tiers serão criados automaticamente após salvar a meta.';
    
    alert(message);
}
</script>

<style>
/* Badges Selector */
.badge-option {
    cursor: pointer;
    margin: 0;
}

.badge-box {
    border: 2px solid #e4e6ef;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    min-width: 90px;
    background: #fff;
}

.badge-box:hover {
    border-color: #009ef7;
    box-shadow: 0 2px 8px rgba(0, 158, 247, 0.2);
    transform: translateY(-2px);
}

.badge-box.selected {
    border-color: #009ef7;
    background: #f1faff;
    box-shadow: 0 2px 12px rgba(0, 158, 247, 0.3);
}

.badge-icon {
    font-size: 2rem;
    display: block;
    margin-bottom: 5px;
}

.badge-name {
    display: block;
    font-size: 0.75rem;
    color: #7e8299;
    font-weight: 500;
}

.badge-box.selected .badge-name {
    color: #009ef7;
    font-weight: 600;
}

/* Bonus Settings Animation */
#bonus-settings {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
