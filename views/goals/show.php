<?php
/**
 * View: Detalhes da Meta
 */
use App\Helpers\Url;

$layout = 'layouts.metronic.app';
$title = 'Detalhes da Meta - ' . ($goal['name'] ?? 'Meta');

ob_start();
?>

<div class="d-flex flex-column flex-lg-row">
    <!--begin::Content-->
    <div class="flex-lg-row-fluid me-lg-15 mb-10 mb-lg-0">
        <!--begin::Header-->
        <div class="card mb-5">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="text-gray-900 mb-3"><?= htmlspecialchars($goal['name']) ?></h1>
                        <?php if (!empty($goal['description'])): ?>
                            <p class="text-gray-600"><?= nl2br(htmlspecialchars($goal['description'])) ?></p>
                        <?php endif; ?>
                        
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="badge badge-light-primary"><?= \App\Models\Goal::TYPES[$goal['type']]['label'] ?? $goal['type'] ?></span>
                            <span class="badge badge-light-info"><?= \App\Models\Goal::TARGET_TYPES[$goal['target_type']] ?? $goal['target_type'] ?></span>
                            <span class="badge badge-light-secondary"><?= \App\Models\Goal::PERIODS[$goal['period_type']] ?? $goal['period_type'] ?></span>
                            <?php if ($goal['is_stretch']): ?>
                                <span class="badge badge-light-warning">🎯 Stretch Goal</span>
                            <?php endif; ?>
                            <span class="badge badge-light-<?= ['low' => 'secondary', 'medium' => 'primary', 'high' => 'warning', 'critical' => 'danger'][$goal['priority']] ?? 'secondary' ?>">
                                Prioridade: <?= ucfirst($goal['priority']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <?php if (\App\Helpers\Permission::can('goals.edit')): ?>
                            <a href="<?= Url::to('/goals/edit?id=' . $goal['id']) ?>" class="btn btn-sm btn-primary me-2">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                        <?php endif; ?>
                        <a href="<?= Url::to('/goals') ?>" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Header-->
        
        <!--begin::Progress Card-->
        <?php if ($progress): 
            $percentage = (float)$progress['percentage'];
            $flagStatus = $progress['flag_status'] ?? 'good';
            $flagColor = \App\Models\Goal::getFlagColor($flagStatus);
            $flagIcon = ['critical' => '🔴', 'warning' => '🟡', 'good' => '🟢', 'excellent' => '🔵'][$flagStatus] ?? '⚪';
        ?>
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title"><?= $flagIcon ?> Progresso Atual</h3>
            </div>
            <div class="card-body">
                <div class="row g-5">
                    <div class="col-md-6">
                        <div class="text-center p-5 bg-light-<?= $flagColor ?> rounded">
                            <div class="text-<?= $flagColor ?> fw-bold fs-2x mb-2"><?= number_format($percentage, 1) ?>%</div>
                            <div class="text-muted">Progresso Atual</div>
                            <div class="mt-3">
                                <span class="badge badge-<?= $flagColor ?>"><?= \App\Models\Goal::getFlagLabel($flagStatus) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-5">
                            <label class="text-muted">Valor Atual</label>
                            <div class="text-gray-900 fw-bold fs-2">
                                <?= \App\Models\Goal::formatValue($goal['type'], $progress['current_value']) ?>
                            </div>
                        </div>
                        <div class="mb-5">
                            <label class="text-muted">Valor Alvo</label>
                            <div class="text-gray-900 fw-bold fs-2">
                                <?= \App\Models\Goal::formatValue($goal['type'], $goal['target_value']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="separator my-5"></div>
                
                <div class="progress h-20px">
                    <div class="progress-bar bg-<?= $flagColor ?>" style="width: <?= min($percentage, 100) ?>%"></div>
                </div>
                
                <?php if (!empty($progress['is_on_track']) || $progress['is_on_track'] === '0'): ?>
                <div class="mt-5">
                    <?php if ($progress['is_on_track']): ?>
                        <div class="alert alert-success d-flex align-items-center">
                            <i class="bi bi-check-circle-fill fs-2x me-4"></i>
                            <div>
                                <h5 class="mb-1">✓ No Ritmo Esperado!</h5>
                                <p class="mb-0">Continue assim para atingir a meta no prazo.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-2x me-4"></i>
                            <div>
                                <h5 class="mb-1">⚠ Fora do Ritmo</h5>
                                <p class="mb-0">É necessário aumentar o ritmo para atingir a meta.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <!--end::Progress Card-->
        
        <!--begin::Projection Card-->
        <?php if (!empty($progress['expected_percentage'])): ?>
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">📊 Projeção e Análise</h3>
            </div>
            <div class="card-body">
                <div class="row g-5">
                    <div class="col-md-3">
                        <div class="text-center p-4 bg-light rounded">
                            <div class="text-gray-900 fw-bold fs-3"><?= $progress['days_elapsed'] ?? 0 ?></div>
                            <div class="text-muted fs-7">Dias Decorridos</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-4 bg-light rounded">
                            <div class="text-gray-900 fw-bold fs-3"><?= ($progress['days_total'] ?? 0) - ($progress['days_elapsed'] ?? 0) ?></div>
                            <div class="text-muted fs-7">Dias Restantes</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-4 bg-light rounded">
                            <div class="text-gray-900 fw-bold fs-3"><?= number_format($progress['expected_percentage'] ?? 0, 1) ?>%</div>
                            <div class="text-muted fs-7">% Esperado Hoje</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-4 bg-light-primary rounded">
                            <div class="text-primary fw-bold fs-3"><?= number_format($progress['projection_percentage'] ?? 0, 1) ?>%</div>
                            <div class="text-muted fs-7">Projeção Final</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!--end::Projection Card-->
        
        <!--begin::Details Card-->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">📋 Informações da Meta</h3>
            </div>
            <div class="card-body">
                <table class="table table-row-bordered">
                    <tbody>
                        <tr>
                            <td class="fw-bold text-muted w-200px">Período</td>
                            <td><?= date('d/m/Y', strtotime($goal['start_date'])) ?> até <?= date('d/m/Y', strtotime($goal['end_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Aplica-se a</td>
                            <td>
                                <?= \App\Models\Goal::TARGET_TYPES[$goal['target_type']] ?? $goal['target_type'] ?>
                                <?php if (!empty($goal['target_name'])): ?>
                                    <span class="text-muted">— <?= htmlspecialchars($goal['target_name']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted">Thresholds</td>
                            <td>
                                🔴 Crítico: < <?= $goal['flag_critical_threshold'] ?>% | 
                                🟡 Atenção: < <?= $goal['flag_warning_threshold'] ?>% | 
                                🟢 Bom: < <?= $goal['flag_good_threshold'] ?>%
                            </td>
                        </tr>
                        <?php if ($goal['reward_points'] || $goal['reward_badge']): ?>
                        <tr>
                            <td class="fw-bold text-muted">Recompensas</td>
                            <td>
                                <?php if ($goal['reward_points']): ?>
                                    <span class="badge badge-light-primary">+<?= $goal['reward_points'] ?> pontos</span>
                                <?php endif; ?>
                                <?php if ($goal['reward_badge']): ?>
                                    <span class="badge badge-light-warning">🏆 <?= htmlspecialchars($goal['reward_badge']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="fw-bold text-muted">Criado por</td>
                            <td><?= htmlspecialchars($goal['creator_name'] ?? 'Sistema') ?> em <?= date('d/m/Y H:i', strtotime($goal['created_at'])) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!--end::Details Card-->
        
        <!--begin::History Card-->
        <?php if (!empty($history)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📈 Histórico (Últimos 30 dias)</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-bordered align-middle">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Progresso</th>
                                <th>Flag</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): 
                                $flagColor = \App\Models\Goal::getFlagColor($h['flag_status'] ?? 'good');
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($h['date'])) ?></td>
                                <td class="fw-semibold"><?= \App\Models\Goal::formatValue($goal['type'], $h['current_value']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress h-8px w-100px me-2">
                                            <div class="progress-bar bg-<?= $flagColor ?>" style="width: <?= min($h['percentage'], 100) ?>%"></div>
                                        </div>
                                        <span class="fw-bold"><?= number_format($h['percentage'], 1) ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $flagColor ?>">
                                        <?= \App\Models\Goal::getFlagLabel($h['flag_status'] ?? 'good') ?>
                                    </span>
                                </td>
                                <td><?= ucfirst($h['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!--end::History Card-->
        
        <!--begin::Achievement-->
        <?php if ($achievement): ?>
        <div class="card mt-5 border-success">
            <div class="card-body bg-light-success">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-100px me-5">
                        <span class="symbol-label bg-success text-white fs-1">🏆</span>
                    </div>
                    <div class="flex-grow-1">
                        <h2 class="text-success mb-3">Meta Atingida!</h2>
                        <p class="text-gray-700 mb-2">
                            Conquistada em <?= date('d/m/Y', strtotime($achievement['achieved_at'])) ?> 
                            com <?= number_format($achievement['percentage'], 1) ?>% de atingimento.
                        </p>
                        <p class="text-muted">Levou <?= $achievement['days_to_achieve'] ?> dias para completar.</p>
                        
                        <?php if ($achievement['points_awarded'] || $achievement['badge_awarded']): ?>
                        <div class="mt-3">
                            <?php if ($achievement['points_awarded']): ?>
                                <span class="badge badge-primary fs-5">+<?= $achievement['points_awarded'] ?> pontos</span>
                            <?php endif; ?>
                            <?php if ($achievement['badge_awarded']): ?>
                                <span class="badge badge-warning fs-5">🏆 <?= htmlspecialchars($achievement['badge_awarded']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!--end::Achievement-->
    </div>
    <!--end::Content-->
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
