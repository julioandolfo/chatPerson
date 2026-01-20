<?php
/**
 * View: Listagem de Metas
 */
use App\Helpers\Url;

$pageTitle = 'Gerenciar Metas';
$breadcrumbs = [
    ['title' => 'Início', 'url' => Url::to('/')],
    ['title' => 'Metas', 'url' => '']
];

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
                                    $status = $progress ? $progress['status'] : 'not_started';
                                    
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
                                        'not_started' => '<span class="badge badge-light">Não Iniciada</span>',
                                        'in_progress' => '<span class="badge badge-primary">Em Progresso</span>',
                                        'achieved' => '<span class="badge badge-success">Atingida</span>',
                                        'exceeded' => '<span class="badge badge-success">Superada</span>',
                                        'failed' => '<span class="badge badge-danger">Falhou</span>'
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
                                            <?= \App\Models\Goal::formatValue($goal['type'], $goal['target_value']) ?>
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
function filterGoals() {
    const targetType = document.getElementById('filter-target-type').value;
    const type = document.getElementById('filter-type').value;
    const period = document.getElementById('filter-period').value;
    
    const params = new URLSearchParams();
    if (targetType) params.set('target_type', targetType);
    if (type) params.set('type', type);
    if (period) params.set('period_type', period);
    
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/main.php';
?>
