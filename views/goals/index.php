<?php
/**
 * View: Listagem de Metas
 */
use App\Helpers\Url;

$layout = 'layouts.metronic.app';
$title = 'Gerenciar Metas';

ob_start();
?>

<div class="d-flex flex-column flex-lg-row">
    <!-- Conteúdo Principal -->
    <div class="flex-lg-row-fluid me-lg-15 mb-10 mb-lg-0">
        <!--begin::Card-->
        <div class="card card-flush">
            <!--begin::Card header-->
            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                <div class="card-title">
                    <h2>Metas</h2>
                </div>
                
                <div class="card-toolbar flex-row-fluid justify-content-end gap-5">
                    <!-- Filtros -->
                    <select class="form-select form-select-sm w-150px" id="filter-target-type" onchange="filterGoals()">
                        <option value="">Todos os Níveis</option>
                        <?php foreach ($target_types as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($filters['target_type'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select class="form-select form-select-sm w-150px" id="filter-type" onchange="filterGoals()">
                        <option value="">Todos os Tipos</option>
                        <?php foreach ($types as $value => $config): ?>
                            <option value="<?= $value ?>" <?= ($filters['type'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= $config['label'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select class="form-select form-select-sm w-150px" id="filter-period" onchange="filterGoals()">
                        <option value="">Todos os Períodos</option>
                        <?php foreach ($periods as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($filters['period'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select class="form-select form-select-sm w-150px" id="filter-active-only" onchange="filterGoals()">
                        <option value="0" <?= !($filters['active_period'] ?? false) ? 'selected' : '' ?>>Todas as Metas</option>
                        <option value="1" <?= ($filters['active_period'] ?? false) ? 'selected' : '' ?>>Apenas Ativas</option>
                    </select>
                    
                    <a href="<?= Url::to('/goals/create') ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg fs-4"></i> Nova Meta
                    </a>
                </div>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <?php if (empty($goals)): ?>
                    <div class="text-center py-10">
                        <img src="<?= Url::asset('/assets/media/illustrations/empty-state.png') ?>" alt="Sem metas" class="mw-300px mb-5">
                        <h3 class="text-gray-600">Nenhuma meta encontrada</h3>
                        <p class="text-gray-400">Comece criando uma nova meta para acompanhar o desempenho</p>
                        <a href="<?= Url::to('/goals/create') ?>" class="btn btn-primary">Criar Primeira Meta</a>
                    </div>
                <?php else: ?>
                    <!--begin::Table-->
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th class="min-w-200px">Meta</th>
                                    <th class="min-w-100px">Tipo</th>
                                    <th class="min-w-120px">Nível</th>
                                    <th class="min-w-100px">Alvo</th>
                                    <th class="min-w-100px">Atual</th>
                                    <th class="min-w-150px">Progresso</th>
                                    <th class="min-w-100px">Status</th>
                                    <th class="min-w-120px">Período</th>
                                    <th class="min-w-100px text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($goals as $goal): 
                                    $progress = $goal['progress'] ?? null;
                                    $percentage = $progress ? (float)$progress['percentage'] : 0;
                                    $currentValue = $progress ? (float)$progress['current_value'] : 0;
                                    $status = $progress ? $progress['status'] : 'nao_iniciada';
                                    
                                    // Cor do progresso
                                    if ($percentage >= 100) {
                                        $progressColor = 'success';
                                    } elseif ($percentage >= 75) {
                                        $progressColor = 'primary';
                                    } elseif ($percentage >= 50) {
                                        $progressColor = 'warning';
                                    } else {
                                        $progressColor = 'danger';
                                    }
                                    
                                    // Badge de status
                                    $statusBadges = [
                                        'nao_iniciada' => '<span class="badge badge-light">Não Iniciada</span>',
                                        'em_andamento' => '<span class="badge badge-primary">Em Andamento</span>',
                                        'atingida' => '<span class="badge badge-success">Atingida</span>',
                                        'superada' => '<span class="badge badge-success">Superada</span>',
                                        'falhada' => '<span class="badge badge-danger">Falhada</span>'
                                    ];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <a href="<?= Url::to('/goals/show?id=' . $goal['id']) ?>" class="text-dark fw-bold text-hover-primary fs-6">
                                                    <?= htmlspecialchars($goal['name']) ?>
                                                </a>
                                                <?php if ($goal['is_stretch']): ?>
                                                    <span class="badge badge-light-warning badge-sm mt-1">🎯 Meta Desafiadora</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-gray-800 fw-semibold">
                                                <?= $types[$goal['type']]['label'] ?? $goal['type'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 fw-semibold"><?= $target_types[$goal['target_type']] ?? $goal['target_type'] ?></span>
                                                <?php if (!empty($goal['target_name'])): ?>
                                                    <span class="text-muted fs-7"><?= htmlspecialchars($goal['target_name']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-gray-800 fw-bold">
                                            <?php
                                                $targetCount = (int)($goal['target_count'] ?? 0);
                                                $isMulti = ($goal['target_type'] ?? '') === 'multi_agent';
                                                $targetTotal = $isMulti && $targetCount > 0
                                                    ? ((float)$goal['target_value'] * $targetCount)
                                                    : (float)$goal['target_value'];
                                            ?>
                                            <?= \App\Models\Goal::formatValue($goal['type'], $targetTotal) ?>
                                            <?php if ($isMulti && $targetCount > 0): ?>
                                                <div class="text-muted fs-8"><?= $targetCount ?>x <?= \App\Models\Goal::formatValue($goal['type'], $goal['target_value']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-gray-800">
                                            <?= \App\Models\Goal::formatValue($goal['type'], $currentValue) ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column w-100">
                                                <div class="d-flex justify-content-between w-100 fs-6 fw-semibold mb-1">
                                                    <span><?= number_format($percentage, 1) ?>%</span>
                                                </div>
                                                <div class="progress h-8px w-100">
                                                    <div class="progress-bar bg-<?= $progressColor ?>" role="progressbar" 
                                                         style="width: <?= min($percentage, 100) ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $statusBadges[$status] ?? $status ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800"><?= $periods[$goal['period_type']] ?? $goal['period_type'] ?></span>
                                                <span class="text-muted fs-7"><?= date('d/m/Y', strtotime($goal['start_date'])) ?> - <?= date('d/m/Y', strtotime($goal['end_date'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= Url::to('/goals/show?id=' . $goal['id']) ?>" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" title="Visualizar">
                                                <i class="bi bi-eye fs-4"></i>
                                            </a>
                                            <a href="<?= Url::to('/goals/edit?id=' . $goal['id']) ?>" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" title="Editar">
                                                <i class="bi bi-pencil fs-4"></i>
                                            </a>
                                            <button type="button" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" 
                                                    onclick="openDuplicateModal(<?= $goal['id'] ?>, '<?= htmlspecialchars(addslashes($goal['name'])) ?>', '<?= $goal['period_type'] ?>')" 
                                                    title="Duplicar">
                                                <i class="bi bi-copy fs-4"></i>
                                            </button>
                                            <button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm" 
                                                    onclick="deleteGoal(<?= $goal['id'] ?>, '<?= htmlspecialchars($goal['name']) ?>')" title="Deletar">
                                                <i class="bi bi-trash fs-4"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!--end::Table-->
                <?php endif; ?>
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
    </div>
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
    // Limpar query params de mensagem da URL sem recarregar
    if (successMsg || errorMsg) {
        urlParams.delete('success');
        urlParams.delete('error');
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, '', newUrl);
    }
})();

function filterGoals() {
    const targetType = document.getElementById('filter-target-type').value;
    const type = document.getElementById('filter-type').value;
    const period = document.getElementById('filter-period').value;
    const activeOnly = document.getElementById('filter-active-only').value;
    
    const params = new URLSearchParams();
    if (targetType) params.set('target_type', targetType);
    if (type) params.set('type', type);
    if (period) params.set('period_type', period);
    if (activeOnly) params.set('active_only', activeOnly);
    
    window.location.href = '<?= Url::to('/goals') ?>' + (params.toString() ? '?' + params.toString() : '');
}

function deleteGoal(id, name) {
    if (confirm(`Tem certeza que deseja deletar a meta "${name}"?\n\nIsso irá remover todo o histórico de progresso e conquistas.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= Url::to('/goals/delete') ?>';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id';
        input.value = id;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// ========== DUPLICAR META ==========
let duplicateModal = null;
let currentDuplicateId = null;

document.addEventListener('DOMContentLoaded', function() {
    duplicateModal = new bootstrap.Modal(document.getElementById('duplicateModal'));
});

function openDuplicateModal(goalId, goalName, periodType) {
    currentDuplicateId = goalId;
    document.getElementById('duplicate-goal-name').textContent = goalName;
    document.getElementById('duplicate-new-name').value = goalName + ' (Cópia)';
    document.getElementById('duplicate-period-type').value = periodType;
    
    // Atualizar datas baseado no período
    updateDuplicateDates();
    
    duplicateModal.show();
}

function updateDuplicateDates() {
    const periodType = document.getElementById('duplicate-period-type').value;
    const today = new Date();
    let startDate, endDate;
    
    switch (periodType) {
        case 'daily':
            startDate = new Date(today);
            endDate = new Date(today);
            break;
        case 'weekly':
            // Próxima semana
            const nextWeekStart = new Date(today);
            nextWeekStart.setDate(today.getDate() + (7 - today.getDay()) % 7 + 1);
            startDate = nextWeekStart;
            endDate = new Date(nextWeekStart);
            endDate.setDate(endDate.getDate() + 6);
            break;
        case 'monthly':
            // Próximo mês
            startDate = new Date(today.getFullYear(), today.getMonth() + 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 2, 0);
            break;
        case 'quarterly':
            // Próximo trimestre
            const nextQuarter = Math.floor(today.getMonth() / 3) + 1;
            startDate = new Date(today.getFullYear(), nextQuarter * 3, 1);
            endDate = new Date(today.getFullYear(), nextQuarter * 3 + 3, 0);
            break;
        case 'yearly':
            // Próximo ano
            startDate = new Date(today.getFullYear() + 1, 0, 1);
            endDate = new Date(today.getFullYear() + 1, 11, 31);
            break;
        default:
            // Custom - usar mês atual
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    }
    
    document.getElementById('duplicate-start-date').value = startDate.toISOString().split('T')[0];
    document.getElementById('duplicate-end-date').value = endDate.toISOString().split('T')[0];
}

function duplicateGoal() {
    const btn = document.getElementById('btn-duplicate');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Duplicando...';
    
    const data = {
        goal_id: currentDuplicateId,
        name: document.getElementById('duplicate-new-name').value,
        period_type: document.getElementById('duplicate-period-type').value,
        start_date: document.getElementById('duplicate-start-date').value,
        end_date: document.getElementById('duplicate-end-date').value
    };
    
    fetch('<?= Url::to('/api/goals/duplicate') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            duplicateModal.hide();
            toastr.success('Meta duplicada com sucesso!');
            setTimeout(() => location.reload(), 1000);
        } else {
            toastr.error(result.message || 'Erro ao duplicar meta');
        }
    })
    .catch(err => {
        toastr.error('Erro ao duplicar meta');
        console.error(err);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-copy me-2"></i>Duplicar Meta';
    });
}
</script>

<!-- Modal Duplicar Meta -->
<div class="modal fade" id="duplicateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-copy me-2 text-info"></i>
                    Duplicar Meta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light-info border border-info border-dashed d-flex align-items-center p-5 mb-5">
                    <i class="bi bi-info-circle fs-2 text-info me-4"></i>
                    <div>
                        <span class="text-gray-800">Duplicando:</span>
                        <strong id="duplicate-goal-name" class="d-block"></strong>
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="form-label required">Nome da Nova Meta</label>
                    <input type="text" class="form-control" id="duplicate-new-name" placeholder="Nome da nova meta">
                </div>
                
                <div class="mb-5">
                    <label class="form-label required">Período</label>
                    <select class="form-select" id="duplicate-period-type" onchange="updateDuplicateDates()">
                        <option value="daily">Diário</option>
                        <option value="weekly">Semanal</option>
                        <option value="monthly">Mensal</option>
                        <option value="quarterly">Trimestral</option>
                        <option value="yearly">Anual</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>
                
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label required">Data Início</label>
                        <input type="date" class="form-control" id="duplicate-start-date">
                    </div>
                    <div class="col-6">
                        <label class="form-label required">Data Fim</label>
                        <input type="date" class="form-control" id="duplicate-end-date">
                    </div>
                </div>
                
                <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4 mt-5">
                    <i class="bi bi-lightbulb fs-4 text-primary me-3"></i>
                    <div class="text-gray-700 fs-7">
                        A meta será duplicada com todas as configurações (tipo, valor alvo, bonificações, condições), 
                        alterando apenas o nome e período.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info" id="btn-duplicate" onclick="duplicateGoal()">
                    <i class="bi bi-copy me-2"></i>Duplicar Meta
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
