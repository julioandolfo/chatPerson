<?php
$layout = 'layouts.metronic.app';
$title = 'Performance de ' . ($report['agent']['name'] ?? 'Agente');

ob_start();
?>

<div class="d-flex flex-column gap-7 gap-lg-10">
    
    <!-- Header -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-50px me-5">
                        <span class="symbol-label bg-light-primary text-primary fs-1 fw-bold">
                            <?= strtoupper(substr($report['agent']['name'] ?? 'A', 0, 1)) ?>
                        </span>
                    </div>
                    <div>
                        <h1 class="mb-1"><?= htmlspecialchars($report['agent']['name'] ?? 'Agente') ?></h1>
                        <span class="badge badge-light-info">
                            <?= $report['total_analyses'] ?? 0 ?> análises no período
                        </span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fs-2x fw-bold text-primary">
                        <?= number_format($report['averages']['avg_overall'] ?? 0, 2) ?>
                        <span class="fs-6 text-muted">/5.00</span>
                    </div>
                    <div class="text-muted">Nota Geral</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card">
        <div class="card-body py-5">
            <form method="GET" class="d-flex gap-3 align-items-end">
                <div class="flex-grow-1">
                    <label class="form-label">Período</label>
                    <div class="input-group">
                        <input type="date" name="date_from" class="form-control" 
                               value="<?= $dateFrom ?>" required>
                        <span class="input-group-text">até</span>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?= $dateTo ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </form>
        </div>
    </div>

    <!-- Cards de Dimensões -->
    <div class="row g-5">
        <?php
        $dimensions = [
            'proactivity' => ['label' => 'Proatividade', 'icon' => 'rocket', 'color' => 'primary'],
            'objection_handling' => ['label' => 'Quebra de Objeções', 'icon' => 'shield-tick', 'color' => 'success'],
            'rapport' => ['label' => 'Rapport', 'icon' => 'people', 'color' => 'info'],
            'closing_techniques' => ['label' => 'Técnicas de Fechamento', 'icon' => 'check-circle', 'color' => 'warning'],
            'qualification' => ['label' => 'Qualificação', 'icon' => 'search-list', 'color' => 'danger'],
            'clarity' => ['label' => 'Clareza', 'icon' => 'message-text', 'color' => 'primary'],
            'value_proposition' => ['label' => 'Proposta de Valor', 'icon' => 'star', 'color' => 'success'],
            'response_time' => ['label' => 'Tempo de Resposta', 'icon' => 'timer', 'color' => 'info'],
            'follow_up' => ['label' => 'Follow-up', 'icon' => 'calendar', 'color' => 'warning'],
            'professionalism' => ['label' => 'Profissionalismo', 'icon' => 'award', 'color' => 'danger']
        ];
        
        foreach ($dimensions as $key => $dim):
            $score = $report['averages']['avg_' . $key] ?? 0;
            $evolutionData = $report['evolution'][$key] ?? [];
            $evolution = $evolutionData['change'] ?? 0;
            $trend = $evolution > 0 ? 'up' : ($evolution < 0 ? 'down' : 'neutral');
            $trendIcon = $evolution > 0 ? 'arrow-up' : ($evolution < 0 ? 'arrow-down' : 'minus');
            $trendColor = $evolution > 0 ? 'success' : ($evolution < 0 ? 'danger' : 'muted');
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="symbol symbol-40px me-3">
                            <span class="symbol-label bg-light-<?= $dim['color'] ?>">
                                <i class="ki-duotone ki-<?= $dim['icon'] ?> fs-2 text-<?= $dim['color'] ?>">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                        </div>
                        <div class="text-end">
                            <div class="fs-2x fw-bold"><?= number_format($score, 2) ?></div>
                            <div class="text-muted fs-7">/5.00</div>
                        </div>
                    </div>
                    <div class="fs-6 fw-semibold mb-2"><?= $dim['label'] ?></div>
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-<?= $trendIcon ?> fs-3 text-<?= $trendColor ?> me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <span class="text-<?= $trendColor ?> fw-bold">
                            <?= $evolution >= 0 ? '+' : '' ?><?= number_format($evolution, 2) ?>
                        </span>
                        <span class="text-muted ms-2 fs-7">vs período anterior</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pontos Fortes e Fracos -->
    <div class="row g-5">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-check-circle fs-2 text-success me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Pontos Fortes
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($report['top_strengths'])): ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($report['top_strengths'] as $strength): ?>
                        <li class="d-flex align-items-start mb-3">
                            <i class="ki-duotone ki-double-check fs-3 text-success me-2 mt-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <span><?= htmlspecialchars($strength) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted mb-0">Nenhum ponto forte identificado ainda.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ki-duotone ki-information fs-2 text-warning me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Áreas de Melhoria
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($report['top_weaknesses'])): ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($report['top_weaknesses'] as $weakness): ?>
                        <li class="d-flex align-items-start mb-3">
                            <i class="ki-duotone ki-arrow-up fs-3 text-warning me-2 mt-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <span><?= htmlspecialchars($weakness) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted mb-0">Nenhuma área de melhoria identificada.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Badges -->
    <?php if (!empty($badges)): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="ki-duotone ki-award fs-2 text-primary me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Conquistas (<?= count($badges) ?>)
            </h3>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-5">
                <?php foreach ($badges as $badge): ?>
                <div class="text-center">
                    <div class="symbol symbol-75px mb-2">
                        <span class="symbol-label bg-light-primary text-primary fs-1">
                            🏆
                        </span>
                    </div>
                    <div class="fw-bold"><?= htmlspecialchars($badge['badge_name']) ?></div>
                    <div class="badge badge-light-<?= 
                        $badge['badge_level'] === 'Platinum' ? 'primary' : 
                        ($badge['badge_level'] === 'Gold' ? 'warning' : 
                        ($badge['badge_level'] === 'Silver' ? 'secondary' : 'info')) 
                    ?>">
                        <?= htmlspecialchars($badge['badge_level']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Metas Ativas -->
    <?php if (!empty($goals)): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="ki-duotone ki-chart-line-up fs-2 text-success me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Metas Ativas (<?= count($goals) ?>)
            </h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Dimensão</th>
                            <th>Meta</th>
                            <th>Atual</th>
                            <th>Progresso</th>
                            <th>Prazo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): 
                            $progress = $goal['current_score'] >= $goal['target_score'] ? 100 : 
                                       (($goal['current_score'] / $goal['target_score']) * 100);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $goal['dimension']))) ?></td>
                            <td><?= number_format($goal['target_score'], 2) ?></td>
                            <td><?= number_format($goal['current_score'], 2) ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="progress h-6px w-150px me-2">
                                        <div class="progress-bar bg-<?= $progress >= 100 ? 'success' : 'primary' ?>" 
                                             style="width: <?= min($progress, 100) ?>%"></div>
                                    </div>
                                    <span class="fw-bold"><?= number_format($progress, 0) ?>%</span>
                                </div>
                            </td>
                            <td><?= date('d/m/Y', strtotime($goal['end_date'])) ?></td>
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
