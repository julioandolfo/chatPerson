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
                        <h2><?= $pageTitle ?></h2>
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
                            <div class="col-md-6">
                                <label class="form-label">Badge de Recompensa</label>
                                <input type="text" class="form-control" name="reward_badge" 
                                       value="<?= htmlspecialchars($goal['reward_badge'] ?? '') ?>" 
                                       placeholder="Ex: top_seller_jan">
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch form-check-custom form-check-solid mt-8">
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
