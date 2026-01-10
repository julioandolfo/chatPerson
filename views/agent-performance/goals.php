<?php
$layout = 'layouts.metronic.app';
$title = 'Minhas Metas de Performance';

ob_start();
?>

<div class="d-flex flex-column gap-7 gap-lg-10">
    
    <!-- Header -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h1 class="mb-1">
                        <i class="ki-duotone ki-chart-line-up fs-1 text-success me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Minhas Metas
                    </h1>
                    <p class="text-muted mb-0">
                        Acompanhe seu progresso e alcance suas metas de performance
                    </p>
                </div>
                <?php if ($canManage ?? false): ?>
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_goal">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Nova Meta
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($goals)): ?>
    <!-- Estado vazio -->
    <div class="card">
        <div class="card-body text-center py-20">
            <i class="ki-duotone ki-rocket fs-5x text-muted mb-5">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <h3 class="text-muted mb-3">Nenhuma meta ativa no momento</h3>
            <p class="text-muted mb-5">
                As metas são criadas automaticamente quando você precisa melhorar em alguma dimensão,<br>
                ou podem ser criadas manualmente por supervisores.
            </p>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Estatísticas -->
    <div class="row g-5">
        <?php
        $totalGoals = count($goals);
        $completedGoals = count(array_filter($goals, fn($g) => $g['status'] === 'completed'));
        $activeGoals = count(array_filter($goals, fn($g) => $g['status'] === 'active'));
        $avgProgress = 0;
        if ($totalGoals > 0) {
            $totalProgress = 0;
            foreach ($goals as $goal) {
                $progress = $goal['current_score'] >= $goal['target_score'] ? 100 : 
                           (($goal['current_score'] / $goal['target_score']) * 100);
                $totalProgress += $progress;
            }
            $avgProgress = $totalProgress / $totalGoals;
        }
        ?>
        <div class="col-md-3">
            <div class="card bg-light-primary">
                <div class="card-body">
                    <i class="ki-duotone ki-chart-line-up fs-3x text-primary mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="fs-2x fw-bold text-primary"><?= $totalGoals ?></div>
                    <div class="text-muted">Total de Metas</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-light-success">
                <div class="card-body">
                    <i class="ki-duotone ki-check-circle fs-3x text-success mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="fs-2x fw-bold text-success"><?= $completedGoals ?></div>
                    <div class="text-muted">Concluídas</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-light-warning">
                <div class="card-body">
                    <i class="ki-duotone ki-timer fs-3x text-warning mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="fs-2x fw-bold text-warning"><?= $activeGoals ?></div>
                    <div class="text-muted">Em Andamento</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-light-info">
                <div class="card-body">
                    <i class="ki-duotone ki-graph-up fs-3x text-info mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                    </i>
                    <div class="fs-2x fw-bold text-info"><?= number_format($avgProgress, 0) ?>%</div>
                    <div class="text-muted">Progresso Médio</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Metas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Todas as Metas</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle gs-5 gy-4">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th class="min-w-150px">Dimensão</th>
                            <th class="min-w-100px text-center">Nota Atual</th>
                            <th class="min-w-100px text-center">Meta</th>
                            <th class="min-w-200px">Progresso</th>
                            <th class="min-w-120px">Período</th>
                            <th class="min-w-100px text-center">Status</th>
                            <th class="min-w-150px">Feedback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): 
                            $progress = $goal['current_score'] >= $goal['target_score'] ? 100 : 
                                       (($goal['current_score'] / $goal['target_score']) * 100);
                            $isCompleted = $goal['status'] === 'completed';
                            $isFailed = $goal['status'] === 'failed';
                            $daysRemaining = $goal['end_date'] ? 
                                (new DateTime($goal['end_date']))->diff(new DateTime())->days : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40px me-3">
                                        <span class="symbol-label bg-light-primary">
                                            <i class="ki-duotone ki-chart-simple fs-2 text-primary">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                            </i>
                                        </span>
                                    </div>
                                    <div class="fw-bold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $goal['dimension']))) ?></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light-<?= $goal['current_score'] >= 4 ? 'success' : ($goal['current_score'] >= 3 ? 'warning' : 'danger') ?> fs-7">
                                    <?= number_format($goal['current_score'], 2) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-primary fs-6"><?= number_format($goal['target_score'], 2) ?></span>
                            </td>
                            <td>
                                <div class="d-flex flex-column w-100">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted fs-7">Progresso</span>
                                        <span class="fw-bold fs-7"><?= number_format($progress, 0) ?>%</span>
                                    </div>
                                    <div class="progress h-6px w-100">
                                        <div class="progress-bar bg-<?= 
                                            $progress >= 100 ? 'success' : 
                                            ($progress >= 75 ? 'primary' : 
                                            ($progress >= 50 ? 'warning' : 'danger')) 
                                        ?>" style="width: <?= min($progress, 100) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fs-7 text-muted">Início: <?= date('d/m/Y', strtotime($goal['start_date'])) ?></span>
                                    <span class="fs-7 fw-bold">Fim: <?= date('d/m/Y', strtotime($goal['end_date'])) ?></span>
                                    <?php if (!$isCompleted && !$isFailed): ?>
                                    <span class="badge badge-light-<?= $daysRemaining < 7 ? 'danger' : 'info' ?> mt-1">
                                        <?= $daysRemaining ?> dias restantes
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($isCompleted): ?>
                                <span class="badge badge-success">
                                    <i class="ki-duotone ki-check fs-6"></i>
                                    Concluída
                                </span>
                                <?php elseif ($isFailed): ?>
                                <span class="badge badge-danger">
                                    <i class="ki-duotone ki-cross fs-6"></i>
                                    Expirada
                                </span>
                                <?php else: ?>
                                <span class="badge badge-warning">
                                    <i class="ki-duotone ki-time fs-6"></i>
                                    Em Andamento
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($goal['feedback'])): ?>
                                <div class="text-muted fs-7"><?= htmlspecialchars($goal['feedback']) ?></div>
                                <?php else: ?>
                                <span class="text-muted fs-8 fst-italic">Sem feedback</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
