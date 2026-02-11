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
                            
                            <div class="col-md-6" id="target-multi-selection" style="display: none;">
                                <label class="required form-label">Selecionar Agentes</label>
                                <select class="form-select" name="target_agents[]" id="target-agents" multiple>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= $agent['id'] ?>"
                                            <?= in_array($agent['id'], $goalAgentIds ?? [], true) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($agent['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Selecione um ou mais agentes. A meta será aplicada para cada um.</small>
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
                                        ['value' => 'trofeu', 'icon' => '🏆', 'name' => 'Troféu'],
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
                            
                            <!--begin::Tiers de Bônus-->
                            <div class="separator my-5"></div>
                            
                            <h5 class="fs-6 fw-bold mb-5">
                                <i class="bi bi-trophy me-2 text-warning"></i>
                                Níveis de Bonificação (Tiers)
                            </h5>
                            <p class="text-muted fs-7 mb-5">
                                Configure os níveis de bonificação baseados no percentual atingido da meta.
                            </p>
                            
                            <div id="bonus-tiers-container">
                                <?php 
                                $bonusTiers = $bonusTiers ?? [];
                                if (!empty($bonusTiers)): ?>
                                    <?php foreach ($bonusTiers as $index => $tier): ?>
                                    <div class="tier-row card border border-gray-300 mb-3" data-tier-id="<?= $tier['id'] ?? '' ?>">
                                        <div class="card-body py-3">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-2">
                                                    <label class="form-label fs-8">Nome</label>
                                                    <input type="text" class="form-control form-control-sm" 
                                                           name="tiers[<?= $index ?>][tier_name]" 
                                                           value="<?= htmlspecialchars($tier['tier_name'] ?? '') ?>" 
                                                           placeholder="Ex: Bronze">
                                                    <input type="hidden" name="tiers[<?= $index ?>][id]" value="<?= $tier['id'] ?? '' ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label fs-8">% Mínimo</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.1" min="0" max="200" class="form-control" 
                                                               name="tiers[<?= $index ?>][threshold_percentage]" 
                                                               value="<?= $tier['threshold_percentage'] ?? '' ?>" 
                                                               placeholder="50">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label fs-8">Valor Bônus R$</label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">R$</span>
                                                        <input type="number" step="0.01" min="0" class="form-control" 
                                                               name="tiers[<?= $index ?>][bonus_amount]" 
                                                               value="<?= $tier['bonus_amount'] ?? '' ?>" 
                                                               placeholder="500.00">
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label fs-8">Cor</label>
                                                    <select class="form-select form-select-sm" name="tiers[<?= $index ?>][tier_color]">
                                                        <option value="bronze" <?= ($tier['tier_color'] ?? '') === 'bronze' ? 'selected' : '' ?>>🥉 Bronze</option>
                                                        <option value="silver" <?= ($tier['tier_color'] ?? '') === 'silver' ? 'selected' : '' ?>>🥈 Prata</option>
                                                        <option value="gold" <?= ($tier['tier_color'] ?? '') === 'gold' ? 'selected' : '' ?>>🥇 Ouro</option>
                                                        <option value="platinum" <?= ($tier['tier_color'] ?? '') === 'platinum' ? 'selected' : '' ?>>💎 Platina</option>
                                                        <option value="diamond" <?= ($tier['tier_color'] ?? '') === 'diamond' ? 'selected' : '' ?>>💠 Diamante</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label fs-8">Ordem</label>
                                                    <input type="number" min="0" class="form-control form-control-sm" 
                                                           name="tiers[<?= $index ?>][tier_order]" 
                                                           value="<?= $tier['tier_order'] ?? $index ?>" 
                                                           placeholder="0">
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeTier(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex gap-2 mb-5">
                                <button type="button" class="btn btn-sm btn-light-primary" onclick="addTier()">
                                    <i class="bi bi-plus-lg me-1"></i> Adicionar Tier
                                </button>
                                <button type="button" class="btn btn-sm btn-light-success" onclick="createDefaultTiers()">
                                    <i class="bi bi-magic me-1"></i> Criar Tiers Padrão
                                </button>
                            </div>
                            
                            <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-4 mb-5">
                                <i class="bi bi-lightbulb fs-4 text-info me-3"></i>
                                <div class="text-gray-700 fs-7">
                                    <strong>Como funciona:</strong> Ao atingir cada percentual, o colaborador ganha o bônus correspondente.
                                    Ex: Se atingir 75%, ganha o bônus do tier de 70%, não acumula tiers anteriores (exceto se configurado).
                                </div>
                            </div>
                            <!--end::Tiers de Bônus-->
                            
                            <!--begin::Condições de Ativação-->
                            <div class="separator my-8"></div>
                            
                            <h4 class="fs-6 fw-bold mb-5">
                                <i class="ki-duotone ki-shield-tick fs-4 me-2 text-primary"><span class="path1"></span><span class="path2"></span></i>
                                Condições de Ativação do Bônus
                            </h4>
                            <p class="text-muted fs-7 mb-5">
                                Configure condições adicionais que precisam ser atingidas para liberar o bônus.
                                Exemplo: Bônus de faturamento só é liberado se taxa de conversão >= 15%.
                            </p>
                            
                            <div class="form-check form-switch form-check-custom form-check-solid mb-5">
                                <input class="form-check-input" type="checkbox" name="enable_bonus_conditions" value="1" id="enable_bonus_conditions"
                                       <?= ($goal['enable_bonus_conditions'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="enable_bonus_conditions">
                                    Habilitar Condições de Ativação
                                </label>
                            </div>
                            
                            <div id="bonus-conditions-settings" style="display: <?= ($goal['enable_bonus_conditions'] ?? 0) ? 'block' : 'none' ?>;">
                                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6 mb-5">
                                    <i class="ki-duotone ki-information-5 fs-2tx text-warning me-4">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                    <div class="d-flex flex-stack flex-grow-1">
                                        <div class="fw-semibold">
                                            <h4 class="text-gray-900 fw-bold">Como funciona</h4>
                                            <div class="fs-6 text-gray-700">
                                                Se o agente bater a meta principal mas NÃO atingir as condições abaixo:
                                                <ul class="mb-0 mt-2">
                                                    <li><strong>Condições obrigatórias:</strong> Bônus é bloqueado completamente</li>
                                                    <li><strong>Condições opcionais:</strong> Bônus é reduzido pelo modificador configurado</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="conditions-list">
                                    <?php if (!empty($bonusConditions)): ?>
                                        <?php foreach ($bonusConditions as $idx => $condition): ?>
                                        <div class="condition-row card card-bordered mb-3" data-index="<?= $idx ?>">
                                            <div class="card-body py-4">
                                                <div class="row g-3 align-items-center">
                                                    <div class="col-md-3">
                                                        <label class="form-label fs-7">Métrica</label>
                                                        <select class="form-select form-select-sm" name="conditions[<?= $idx ?>][condition_type]">
                                                            <?php foreach (\App\Models\GoalBonusCondition::CONDITION_TYPES as $type => $label): ?>
                                                            <option value="<?= $type ?>" <?= $condition['condition_type'] === $type ? 'selected' : '' ?>><?= $label ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label fs-7">Operador</label>
                                                        <select class="form-select form-select-sm" name="conditions[<?= $idx ?>][operator]">
                                                            <?php foreach (\App\Models\GoalBonusCondition::OPERATORS as $op => $label): ?>
                                                            <option value="<?= $op ?>" <?= $condition['operator'] === $op ? 'selected' : '' ?>><?= $op ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label fs-7">Valor Mínimo</label>
                                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                                               name="conditions[<?= $idx ?>][min_value]" 
                                                               value="<?= $condition['min_value'] ?>" placeholder="15">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label fs-7">
                                                            <span class="form-check form-check-sm form-check-custom">
                                                                <input class="form-check-input" type="checkbox" 
                                                                       name="conditions[<?= $idx ?>][is_required]" value="1"
                                                                       <?= $condition['is_required'] ? 'checked' : '' ?>>
                                                                <span class="form-check-label">Obrigatória</span>
                                                            </span>
                                                        </label>
                                                        <input type="number" step="0.01" min="0" max="1" class="form-control form-control-sm" 
                                                               name="conditions[<?= $idx ?>][bonus_modifier]" 
                                                               value="<?= $condition['bonus_modifier'] ?? 0.5 ?>" 
                                                               placeholder="0.5" title="Modificador se não atender (0.5 = 50% do bônus)">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label fs-7">Descrição</label>
                                                        <input type="text" class="form-control form-control-sm" 
                                                               name="conditions[<?= $idx ?>][description]" 
                                                               value="<?= htmlspecialchars($condition['description'] ?? '') ?>" 
                                                               placeholder="Ex: Conversão mínima">
                                                    </div>
                                                    <div class="col-md-1 d-flex align-items-end">
                                                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeCondition(this)">
                                                            <i class="ki-duotone ki-trash fs-6"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="btn btn-sm btn-light-primary" onclick="addCondition()">
                                    <i class="ki-duotone ki-plus fs-4"><span class="path1"></span><span class="path2"></span></i>
                                    Adicionar Condição
                                </button>
                            </div>
                            <!--end::Condições de Ativação-->
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
// Exibir mensagens flash via query parameters
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const successMsg = urlParams.get('success');
    const errorMsg = urlParams.get('error');
    if (successMsg && typeof toastr !== 'undefined') {
        toastr.success(successMsg);
    }
    if (errorMsg && typeof toastr !== 'undefined') {
        toastr.error(errorMsg);
    }
})();

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
    const targetMultiSelection = document.getElementById('target-multi-selection');
    const targetId = document.getElementById('target-id');
    const targetAgents = document.getElementById('target-agents');
    const targetLabel = document.getElementById('target-label');
    
    if (targetType === 'global') {
        targetSelection.style.display = 'none';
        targetMultiSelection.style.display = 'none';
        targetId.required = false;
        targetAgents.required = false;
        return;
    }
    
    if (targetType === 'multi_agent') {
        targetSelection.style.display = 'none';
        targetMultiSelection.style.display = 'block';
        targetId.required = false;
        targetAgents.required = true;
        return;
    }
    
    targetMultiSelection.style.display = 'none';
    targetSelection.style.display = 'block';
    targetId.required = true;
    targetAgents.required = false;
    
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

// ========== TIERS DE BÔNUS ==========
let tierIndex = document.querySelectorAll('.tier-row').length;

function addTier(name = '', threshold = '', amount = '', color = 'bronze', order = null) {
    const container = document.getElementById('bonus-tiers-container');
    const idx = tierIndex++;
    const tierOrder = order ?? idx;
    
    const html = `
        <div class="tier-row card border border-gray-300 mb-3" data-tier-id="">
            <div class="card-body py-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-2">
                        <label class="form-label fs-8">Nome</label>
                        <input type="text" class="form-control form-control-sm" 
                               name="tiers[${idx}][tier_name]" 
                               value="${name}" 
                               placeholder="Ex: Bronze">
                        <input type="hidden" name="tiers[${idx}][id]" value="">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-8">% Mínimo</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.1" min="0" max="200" class="form-control" 
                                   name="tiers[${idx}][threshold_percentage]" 
                                   value="${threshold}" 
                                   placeholder="50">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-8">Valor Bônus R$</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">R$</span>
                            <input type="number" step="0.01" min="0" class="form-control" 
                                   name="tiers[${idx}][bonus_amount]" 
                                   value="${amount}" 
                                   placeholder="500.00">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-8">Cor</label>
                        <select class="form-select form-select-sm" name="tiers[${idx}][tier_color]">
                            <option value="bronze" ${color === 'bronze' ? 'selected' : ''}>🥉 Bronze</option>
                            <option value="silver" ${color === 'silver' ? 'selected' : ''}>🥈 Prata</option>
                            <option value="gold" ${color === 'gold' ? 'selected' : ''}>🥇 Ouro</option>
                            <option value="platinum" ${color === 'platinum' ? 'selected' : ''}>💎 Platina</option>
                            <option value="diamond" ${color === 'diamond' ? 'selected' : ''}>💠 Diamante</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-8">Ordem</label>
                        <input type="number" min="0" class="form-control form-control-sm" 
                               name="tiers[${idx}][tier_order]" 
                               value="${tierOrder}" 
                               placeholder="0">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeTier(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
}

function removeTier(btn) {
    const row = btn.closest('.tier-row');
    if (confirm('Remover este tier?')) {
        row.remove();
    }
}

// Criar tiers padrão automaticamente
function createDefaultTiers() {
    const targetCommission = parseFloat(document.getElementById('ote_target_commission')?.value || 0);
    
    // Limpar tiers existentes
    const container = document.getElementById('bonus-tiers-container');
    if (container.children.length > 0) {
        if (!confirm('Isso irá substituir os tiers existentes. Continuar?')) {
            return;
        }
        container.innerHTML = '';
        tierIndex = 0;
    }
    
    const tiers = [
        { name: 'Bronze', threshold: 50, multiplier: 0.3, color: 'bronze' },
        { name: 'Prata', threshold: 70, multiplier: 0.5, color: 'silver' },
        { name: 'Ouro', threshold: 90, multiplier: 0.8, color: 'gold' },
        { name: 'Platina', threshold: 100, multiplier: 1.0, color: 'platinum' },
        { name: 'Diamante', threshold: 120, multiplier: 1.5, color: 'diamond' }
    ];
    
    tiers.forEach((tier, index) => {
        const bonus = targetCommission > 0 ? (targetCommission * tier.multiplier).toFixed(2) : '';
        addTier(tier.name, tier.threshold, bonus, tier.color, index);
    });
    
    if (targetCommission <= 0) {
        toastr.info('Preencha a "Comissão Target (100%)" para calcular os valores automaticamente.');
    } else {
        toastr.success('Tiers padrão criados! Ajuste os valores conforme necessário.');
    }
}

// ========== CONDIÇÕES DE ATIVAÇÃO ==========
// Toggle da seção de condições
document.getElementById('enable_bonus_conditions')?.addEventListener('change', function() {
    const conditionsSettings = document.getElementById('bonus-conditions-settings');
    if (this.checked) {
        conditionsSettings.style.display = 'block';
    } else {
        conditionsSettings.style.display = 'none';
    }
});

// Contador de condições
let conditionIndex = document.querySelectorAll('.condition-row').length;

// Tipos de condição disponíveis
const conditionTypes = {
    'revenue': 'Faturamento',
    'average_ticket': 'Ticket Médio',
    'conversion_rate': 'Taxa de Conversão',
    'sales_count': 'Qtd de Vendas',
    'conversations_count': 'Qtd de Conversas',
    'resolution_rate': 'Taxa de Resolução',
    'response_time': 'Tempo de Resposta',
    'csat_score': 'CSAT',
    'messages_sent': 'Mensagens Enviadas',
    'sla_compliance': 'SLA'
};

const operators = ['>=', '>', '<=', '<', '=', '!=', 'between'];

function addCondition() {
    const list = document.getElementById('conditions-list');
    const idx = conditionIndex++;
    
    const html = `
        <div class="condition-row card card-bordered mb-3" data-index="${idx}">
            <div class="card-body py-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fs-7">Métrica</label>
                        <select class="form-select form-select-sm" name="conditions[${idx}][condition_type]">
                            ${Object.entries(conditionTypes).map(([k, v]) => `<option value="${k}">${v}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-7">Operador</label>
                        <select class="form-select form-select-sm" name="conditions[${idx}][operator]">
                            ${operators.map(op => `<option value="${op}">${op}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-7">Valor Mínimo</label>
                        <input type="number" step="0.01" class="form-control form-control-sm" 
                               name="conditions[${idx}][min_value]" placeholder="15">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-7">
                            <span class="form-check form-check-sm form-check-custom">
                                <input class="form-check-input" type="checkbox" 
                                       name="conditions[${idx}][is_required]" value="1" checked>
                                <span class="form-check-label">Obrigatória</span>
                            </span>
                        </label>
                        <input type="number" step="0.01" min="0" max="1" class="form-control form-control-sm" 
                               name="conditions[${idx}][bonus_modifier]" value="0.5" 
                               placeholder="0.5" title="Modificador se não atender (0.5 = 50% do bônus)">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fs-7">Descrição</label>
                        <input type="text" class="form-control form-control-sm" 
                               name="conditions[${idx}][description]" placeholder="Ex: Conversão mínima">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeCondition(this)">
                            <i class="ki-duotone ki-trash fs-6"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    list.insertAdjacentHTML('beforeend', html);
}

function removeCondition(btn) {
    const row = btn.closest('.condition-row');
    if (row) {
        row.remove();
    }
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
