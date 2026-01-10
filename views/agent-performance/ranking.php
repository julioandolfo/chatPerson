<?php
/**
 * View: Ranking de Vendedores
 */
$title = 'Ranking de Performance';
ob_start();
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    🏆 Ranking de Vendedores
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                    <select name="dimension" class="form-select form-select-sm">
                        <option value="overall" <?= $dimension === 'overall' ? 'selected' : '' ?>>Geral</option>
                        <option value="proactivity" <?= $dimension === 'proactivity' ? 'selected' : '' ?>>Proatividade</option>
                        <option value="objection_handling" <?= $dimension === 'objection_handling' ? 'selected' : '' ?>>Objeções</option>
                        <option value="rapport" <?= $dimension === 'rapport' ? 'selected' : '' ?>>Rapport</option>
                        <option value="closing_techniques" <?= $dimension === 'closing_techniques' ? 'selected' : '' ?>>Fechamento</option>
                        <option value="qualification" <?= $dimension === 'qualification' ? 'selected' : '' ?>>Qualificação</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                </form>
            </div>
        </div>
    </div>
    
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-4">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th class="ps-4 min-w-50px rounded-start">Posição</th>
                                    <th class="min-w-200px">Vendedor</th>
                                    <th class="min-w-100px text-center">Nota</th>
                                    <th class="min-w-100px text-center">Conversas</th>
                                    <th class="min-w-100px text-center">Mín/Máx</th>
                                    <th class="min-w-150px text-end pe-4 rounded-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ranking as $index => $agent): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <?php if ($index === 0): ?>
                                                <span class="badge badge-circle badge-warning me-2">🥇</span>
                                            <?php elseif ($index === 1): ?>
                                                <span class="badge badge-circle badge-secondary me-2">🥈</span>
                                            <?php elseif ($index === 2): ?>
                                                <span class="badge badge-circle badge-primary me-2">🥉</span>
                                            <?php else: ?>
                                                <span class="fw-bold fs-5 text-gray-600"><?= $index + 1 ?>º</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?= \App\Helpers\Url::to('/agent-performance/agent?id=' . $agent['agent_id']) ?>" class="text-dark fw-bold text-hover-primary d-block mb-1 fs-6">
                                            <?= htmlspecialchars($agent['agent_name']) ?>
                                        </a>
                                        <span class="text-muted fw-semibold text-muted d-block fs-7">ID: <?= $agent['agent_id'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $score = $agent['avg_score'];
                                        $stars = str_repeat('⭐', min(5, round($score)));
                                        ?>
                                        <span class="badge badge-light-success fs-7 fw-bold"><?= number_format($score, 2) ?> / 5.0</span>
                                        <div class="mt-1"><?= $stars ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-dark fw-bold fs-6"><?= $agent['total_conversations'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted fs-7"><?= number_format($agent['min_score'] ?? 0, 1) ?></span>
                                        <span class="text-muted"> / </span>
                                        <span class="text-success fs-7 fw-bold"><?= number_format($agent['max_score'] ?? 0, 1) ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="<?= \App\Helpers\Url::to('/agent-performance/agent?id=' . $agent['agent_id']) ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                            Ver Detalhes
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($ranking)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-gray-500 py-10">
                                        Nenhum vendedor encontrado no período
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/layout.php';
?>
