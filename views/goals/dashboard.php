<?php
/**
 * View: Dashboard de Metas do Agente
 */
use App\Helpers\Url;

$pageTitle = 'Minhas Metas';
$breadcrumbs = [
    ['title' => 'Início', 'url' => Url::to('/')],
    ['title' => 'Minhas Metas', 'url' => '']
];

ob_start();
?>

<div class="row g-5 g-xl-10 mb-5 mb-xl-10">
    <!--begin::Resumo-->
    <div class="col-md-3">
        <div class="card card-flush h-md-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex flex-column mb-7">
                    <span class="text-gray-600 fw-bold fs-6">Total de Metas</span>
                    <span class="text-gray-800 fw-bolder fs-2x"><?= $summary['total_goals'] ?></span>
                </div>
                <div class="m-0">
                    <i class="bi bi-flag-fill text-primary fs-3x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card card-flush h-md-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex flex-column mb-7">
                    <span class="text-gray-600 fw-bold fs-6">Atingidas</span>
                    <span class="text-success fw-bolder fs-2x"><?= $summary['achieved'] ?></span>
                </div>
                <div class="m-0">
                    <i class="bi bi-trophy-fill text-success fs-3x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card card-flush h-md-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex flex-column mb-7">
                    <span class="text-gray-600 fw-bold fs-6">Em Progresso</span>
                    <span class="text-primary fw-bolder fs-2x"><?= $summary['in_progress'] ?></span>
                </div>
                <div class="m-0">
                    <i class="bi bi-graph-up-arrow text-primary fs-3x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card card-flush h-md-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex flex-column mb-7">
                    <span class="text-gray-600 fw-bold fs-6">Em Risco</span>
                    <span class="text-danger fw-bolder fs-2x"><?= $summary['at_risk'] ?></span>
                </div>
                <div class="m-0">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-3x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!--begin::Metas por Nível-->
<div class="row g-5 g-xl-10">
    <?php 
    $levelIcons = [
        'individual' => 'person-fill',
        'team' => 'people-fill',
        'department' => 'diagram-3-fill',
        'global' => 'globe'
    ];
    
    $levelColors = [
        'individual' => 'primary',
        'team' => 'info',
        'department' => 'warning',
        'global' => 'success'
    ];
    
    foreach ($summary['goals_by_level'] as $level => $goals): 
        if (empty($goals)) continue;
    ?>
        <div class="col-md-6 mb-10">
            <div class="card card-flush h-100">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-<?= $levelIcons[$level] ?> text-<?= $levelColors[$level] ?> me-2"></i>
                        <?= \App\Models\Goal::TARGET_TYPES[$level] ?>
                    </h3>
                </div>
                <div class="card-body pt-5">
                    <?php foreach ($goals as $goal): 
                        $progress = $goal['progress'] ?? null;
                        $percentage = $progress ? (float)$progress['percentage'] : 0;
                        $currentValue = $progress ? (float)$progress['current_value'] : 0;
                        
                        if ($percentage >= 100) {
                            $progressColor = 'success';
                        } elseif ($percentage >= 75) {
                            $progressColor = 'primary';
                        } elseif ($percentage >= 50) {
                            $progressColor = 'warning';
                        } else {
                            $progressColor = 'danger';
                        }
                    ?>
                        <div class="mb-7">
                            <div class="d-flex flex-stack mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="d-flex flex-column">
                                        <a href="<?= Url::to('/goals/show?id=' . $goal['id']) ?>" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                            <?= htmlspecialchars($goal['name']) ?>
                                        </a>
                                        <div class="fs-7 text-muted fw-semibold">
                                            <?= \App\Models\Goal::TYPES[$goal['type']]['label'] ?? $goal['type'] ?>
                                            <?php if ($goal['is_stretch']): ?>
                                                <span class="badge badge-light-warning badge-sm ms-2">🎯 Desafiadora</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column text-end">
                                    <span class="text-gray-800 fw-bold fs-6"><?= number_format($percentage, 1) ?>%</span>
                                    <span class="text-muted fs-7"><?= \App\Models\Goal::formatValue($goal['type'], $currentValue) ?> / <?= \App\Models\Goal::formatValue($goal['type'], $goal['target_value']) ?></span>
                                </div>
                            </div>
                            <div class="progress h-8px">
                                <div class="progress-bar bg-<?= $progressColor ?>" role="progressbar" style="width: <?= min($percentage, 100) ?>%"></div>
                            </div>
                            <div class="fs-8 text-muted mt-1">
                                Período: <?= date('d/m/Y', strtotime($goal['start_date'])) ?> - <?= date('d/m/Y', strtotime($goal['end_date'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($achievements)): ?>
<!--begin::Conquistas Recentes-->
<div class="card card-flush mt-10">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-trophy-fill text-warning me-2"></i>
            Conquistas Recentes
        </h3>
    </div>
    <div class="card-body pt-5">
        <div class="timeline">
            <?php foreach (array_slice($achievements, 0, 10) as $achievement): ?>
                <div class="timeline-item">
                    <div class="timeline-line w-40px"></div>
                    <div class="timeline-icon symbol symbol-circle symbol-40px">
                        <div class="symbol-label bg-light-success">
                            <i class="bi bi-trophy-fill text-success fs-2"></i>
                        </div>
                    </div>
                    <div class="timeline-content mb-10 mt-n1">
                        <div class="pe-3 mb-5">
                            <div class="fs-5 fw-bold mb-2"><?= htmlspecialchars($achievement['goal_name']) ?></div>
                            <div class="d-flex align-items-center mt-1 fs-6">
                                <div class="text-muted me-2 fs-7">
                                    Atingida em <?= date('d/m/Y', strtotime($achievement['achieved_at'])) ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="badge badge-light-success"><?= number_format($achievement['percentage'], 1) ?>% completado</span>
                                <?php if ($achievement['points_awarded'] > 0): ?>
                                    <span class="badge badge-light-primary">+<?= $achievement['points_awarded'] ?> pontos</span>
                                <?php endif; ?>
                                <?php if (!empty($achievement['badge_awarded'])): ?>
                                    <span class="badge badge-light-warning">🏆 <?= htmlspecialchars($achievement['badge_awarded']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/main.php';
?>
