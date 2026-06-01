<?php
/**
 * View: Minha Meta
 * Visão dedicada das metas do agente — progresso, projeção e bônus,
 * com filtro de períodos para consultar metas anteriores.
 */

use App\Helpers\Url;
use App\Models\Goal;
use App\Models\GoalBonusCondition;

$layout = 'layouts.metronic.app';
$title = 'Minha Meta';

// Dados vindos do controller
$goals = $goals ?? [];
$stats = $stats ?? ['total' => 0, 'achieved' => 0, 'in_progress' => 0, 'at_risk' => 0, 'total_bonus' => 0, 'ote_total' => 0];
$periods = $periods ?? [];
$selectedPeriod = $selected_period ?? null;
$isCurrentPeriod = $is_current_period ?? true;
$bonusSummary = $bonus_summary ?? [];

$conditionLabels = GoalBonusCondition::CONDITION_TYPES;
$operatorLabels = GoalBonusCondition::OPERATORS;

$fmt = fn($type, $value) => Goal::formatValue($type, (float)$value);

ob_start();
?>

<!--begin::Cabeçalho-->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-7 gap-4">
    <div>
        <h1 class="text-gray-900 fw-bold mb-1">🎯 Minha Meta</h1>
        <span class="text-muted fs-6">
            <?php if ($isCurrentPeriod): ?>
                Acompanhamento do período atual e do seu bônus estimado.
            <?php else: ?>
                Visualizando um período anterior.
            <?php endif; ?>
        </span>
    </div>

    <!--begin::Filtro de período-->
    <form method="get" action="<?= Url::to('/goals/my') ?>" class="d-flex align-items-center gap-2">
        <label class="text-muted fw-semibold fs-7 d-none d-sm-block">Período:</label>
        <select name="period" class="form-select form-select-sm w-200px" onchange="this.form.submit()">
            <option value="">Período atual</option>
            <?php foreach ($periods as $p): ?>
                <option value="<?= htmlspecialchars($p['value']) ?>" <?= $selectedPeriod === $p['value'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['label']) ?><?= $p['is_current'] ? ' (atual)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($selectedPeriod): ?>
            <a href="<?= Url::to('/goals/my') ?>" class="btn btn-sm btn-light" title="Limpar filtro">
                <i class="bi bi-x-lg"></i>
            </a>
        <?php endif; ?>
    </form>
    <!--end::Filtro de período-->
</div>
<!--end::Cabeçalho-->

<!--begin::Resumo-->
<div class="row g-4 mb-7">
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-flush h-100">
            <div class="card-body text-center py-5">
                <div class="fs-2hx fw-bold text-gray-900"><?= (int)$stats['total'] ?></div>
                <div class="text-muted fw-semibold fs-7">Metas no período</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-flush h-100 bg-light-success">
            <div class="card-body text-center py-5">
                <div class="fs-2hx fw-bold text-success"><?= (int)$stats['achieved'] ?></div>
                <div class="text-muted fw-semibold fs-7">🏆 Atingidas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-flush h-100 bg-light-primary">
            <div class="card-body text-center py-5">
                <div class="fs-2hx fw-bold text-primary"><?= (int)$stats['in_progress'] ?></div>
                <div class="text-muted fw-semibold fs-7">📈 Em progresso</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-flush h-100 bg-light-warning">
            <div class="card-body text-center py-5">
                <div class="fs-2hx fw-bold text-warning"><?= (int)$stats['at_risk'] ?></div>
                <div class="text-muted fw-semibold fs-7">⚠️ Em risco</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-8 col-xl-2">
        <div class="card card-flush h-100 bg-light-success border border-success border-dashed">
            <div class="card-body text-center py-5">
                <div class="fs-2x fw-bolder text-success">R$ <?= number_format((float)$stats['total_bonus'], 2, ',', '.') ?></div>
                <div class="text-muted fw-semibold fs-7">💰 Bônus estimado total</div>
            </div>
        </div>
    </div>
</div>
<!--end::Resumo-->

<?php if (empty($goals)): ?>
    <!--begin::Estado vazio-->
    <div class="card card-flush">
        <div class="card-body text-center py-20">
            <div class="fs-3x mb-4">🎯</div>
            <h3 class="text-gray-800 mb-2">Nenhuma meta neste período</h3>
            <p class="text-muted">
                <?php if ($selectedPeriod): ?>
                    Você não possui metas para o período selecionado. Tente outro período no filtro acima.
                <?php else: ?>
                    Você ainda não tem metas ativas. Assim que uma meta for atribuída a você, ela aparecerá aqui.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <!--end::Estado vazio-->
<?php else: ?>

<?php foreach ($goals as $index => $goal):
    $c = $goal['computed'];
    $type = $goal['type'];
    $color = $c['flag_color'];
    $bonusPreview = $goal['bonus_preview'] ?? [];
    $tiers = $goal['tiers'] ?? [];
    $conditions = $goal['conditions'] ?? [];
    $blocked = $goal['blocked_conditions'] ?? [];
    $nextTier = $c['next_tier'] ?? null;
    $hasBonus = !empty($goal['enable_bonus']) || !empty($tiers);
?>
<!--begin::Meta-->
<div class="card card-flush mb-6 border-start border-4 border-<?= $color ?>">
    <!--begin::Cabeçalho da meta-->
    <div class="card-header bg-light-<?= $color ?> align-items-center py-5 flex-wrap gap-3">
        <div class="d-flex align-items-center flex-grow-1">
            <div class="symbol symbol-50px me-4">
                <span class="symbol-label bg-<?= $color ?> text-white fs-2 fw-bold"><?= $index + 1 ?></span>
            </div>
            <div>
                <h2 class="fw-bold text-gray-900 mb-1"><?= htmlspecialchars($goal['name']) ?></h2>
                <div class="text-muted fs-7">
                    <?= Goal::TYPES[$type]['label'] ?? $type ?>
                    <span class="mx-1">•</span>
                    <?= Goal::TARGET_TYPES[$goal['target_type']] ?? $goal['target_type'] ?>
                    <span class="mx-1">•</span>
                    <?= date('d/m/Y', strtotime($goal['start_date'])) ?> → <?= date('d/m/Y', strtotime($goal['end_date'])) ?>
                </div>
            </div>
        </div>
        <div class="text-end">
            <div class="fs-2hx fw-bolder text-<?= $color ?> lh-1"><?= number_format($c['percentage'], 1) ?>%</div>
            <span class="badge badge-<?= $color ?> mt-2"><?= Goal::getFlagLabel($c['flag_status']) ?></span>
            <?php if ($c['is_ended']): ?>
                <span class="badge badge-light-secondary mt-2">Encerrada</span>
            <?php endif; ?>
        </div>
    </div>
    <!--end::Cabeçalho da meta-->

    <div class="card-body">
        <!--begin::Barra de progresso-->
        <div class="d-flex justify-content-between mb-2 fs-7 fw-semibold">
            <span class="text-gray-700">Atual: <?= $fmt($type, $c['current_value']) ?></span>
            <span class="text-muted">Meta: <?= $fmt($type, $c['target_value']) ?></span>
        </div>
        <div class="position-relative mb-1">
            <div class="progress h-25px bg-light-<?= $color ?>">
                <div class="progress-bar bg-<?= $color ?> fw-bold" role="progressbar"
                     style="width: <?= min($c['percentage'], 100) ?>%">
                    <?= $c['percentage'] >= 8 ? number_format($c['percentage'], 0) . '%' : '' ?>
                </div>
            </div>
            <?php if (!$c['is_ended'] && $c['expected_percentage'] > 0 && $c['expected_percentage'] < 100): ?>
                <!-- Marcador do ritmo esperado hoje -->
                <div class="position-absolute top-0 h-25px border-start border-2 border-gray-800"
                     style="left: <?= min($c['expected_percentage'], 100) ?>%;"
                     title="Ritmo esperado hoje: <?= number_format($c['expected_percentage'], 1) ?>%">
                    <span class="position-absolute fs-9 text-gray-800 fw-bold" style="top: 26px; transform: translateX(-50%); white-space: nowrap;">
                        esperado <?= number_format($c['expected_percentage'], 0) ?>%
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <!--end::Barra de progresso-->

        <div class="mt-8 row g-4">
            <!--begin::Indicadores-->
            <div class="col-lg-7">
                <div class="row g-3">
                    <div class="col-4">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fs-4 fw-bold text-gray-900"><?= $fmt($type, $c['remaining_value']) ?></div>
                            <div class="text-muted fs-8">Falta atingir</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fs-4 fw-bold text-gray-900"><?= $c['remaining_days'] ?></div>
                            <div class="text-muted fs-8">Dias restantes</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fs-4 fw-bold text-info"><?= $fmt($type, $c['daily_average']) ?></div>
                            <div class="text-muted fs-8">Média / dia</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fs-4 fw-bold text-primary"><?= number_format($c['projected_percentage'], 0) ?>%</div>
                            <div class="text-muted fs-8">Projeção final</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fs-4 fw-bold <?= $c['is_on_track'] ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($c['expected_percentage'], 0) ?>%
                            </div>
                            <div class="text-muted fs-8">Esperado hoje</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="fs-4 fw-bold text-warning"><?= $fmt($type, $c['daily_needed']) ?></div>
                            <div class="text-muted fs-8">Necessário / dia</div>
                        </div>
                    </div>
                </div>

                <!--begin::Status do ritmo-->
                <?php if (!$c['is_ended']): ?>
                    <?php if ($c['is_on_track']): ?>
                        <div class="alert alert-success d-flex align-items-center mt-4 mb-0 py-3">
                            <i class="bi bi-check-circle-fill fs-3 me-3"></i>
                            <span><strong>No ritmo!</strong> Mantendo este desempenho, você atinge a meta no prazo.</span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning d-flex align-items-center mt-4 mb-0 py-3">
                            <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                            <span><strong>Atenção ao ritmo.</strong> É preciso acelerar para alcançar a meta no prazo.</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <!--end::Status do ritmo-->
            </div>
            <!--end::Indicadores-->

            <!--begin::Bônus-->
            <div class="col-lg-5">
                <div class="border border-dashed rounded p-4 h-100 bg-light">
                    <h4 class="fw-bold mb-4">
                        <i class="bi bi-cash-coin text-success me-2"></i> Quanto você vai receber
                    </h4>

                    <?php if (!$hasBonus): ?>
                        <div class="text-muted fs-7">Esta meta não possui bônus configurado.</div>
                    <?php else: ?>
                        <!-- Bônus atual estimado -->
                        <div class="bg-light-success rounded p-4 text-center mb-4">
                            <div class="text-muted fs-7 mb-1">Bônus estimado agora</div>
                            <div class="fs-2hx fw-bolder text-success">
                                R$ <?= number_format((float)($bonusPreview['total_bonus'] ?? 0), 2, ',', '.') ?>
                            </div>
                            <?php if (!empty($bonusPreview['last_tier'])): ?>
                                <span class="badge badge-success mt-2">
                                    Faixa atual: <?= htmlspecialchars($bonusPreview['last_tier']['tier_name'] ?? 'N/A') ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Condições bloqueando -->
                        <?php if (!empty($blocked)): ?>
                            <div class="alert alert-danger py-3 fs-8 mb-4">
                                <strong>⚠️ Bônus bloqueado:</strong> existem condições obrigatórias não cumpridas (veja abaixo).
                            </div>
                        <?php endif; ?>

                        <!-- Próximo bônus -->
                        <?php if ($nextTier): ?>
                            <div class="d-flex align-items-center bg-light-primary rounded p-3 mb-4">
                                <i class="bi bi-arrow-up-circle-fill text-primary fs-2x me-3"></i>
                                <div class="flex-grow-1">
                                    <div class="fs-8 text-muted">Próximo bônus: <strong><?= htmlspecialchars($nextTier['tier_name'] ?? 'Próxima faixa') ?></strong></div>
                                    <div class="fs-7 text-gray-800">
                                        Faltam <strong><?= number_format($c['next_tier_gap'], 1) ?>%</strong>
                                        (<?= $fmt($type, $c['next_tier_value_gap']) ?>) para
                                        <strong class="text-primary">R$ <?= number_format((float)($nextTier['bonus_amount'] ?? 0), 2, ',', '.') ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Faixas de bônus -->
                        <?php if (!empty($tiers)): ?>
                            <h6 class="fw-bold fs-7 text-muted text-uppercase mb-2">Faixas de bônus</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-row-dashed align-middle mb-0">
                                    <tbody>
                                        <?php foreach ($tiers as $tier):
                                            $reached = $c['percentage'] >= (float)$tier['threshold_percentage'];
                                        ?>
                                        <tr class="<?= $reached ? 'fw-bold' : 'text-muted' ?>">
                                            <td class="ps-0">
                                                <?= $reached ? '✅' : '⬜' ?>
                                                <?= htmlspecialchars($tier['tier_name'] ?? 'Faixa') ?>
                                            </td>
                                            <td class="text-center"><?= number_format((float)$tier['threshold_percentage'], 0) ?>%</td>
                                            <td class="text-end pe-0">R$ <?= number_format((float)($tier['bonus_amount'] ?? 0), 2, ',', '.') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Condições de ativação -->
                        <?php if (!empty($conditions)): ?>
                            <h6 class="fw-bold fs-7 text-muted text-uppercase mb-2">Condições de ativação</h6>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-row-dashed align-middle mb-0">
                                    <tbody>
                                        <?php foreach ($conditions as $cond):
                                            $met = !empty($cond['is_met']);
                                        ?>
                                        <tr>
                                            <td class="ps-0 <?= $met ? 'text-success' : 'text-danger' ?>">
                                                <?= $met ? '✓' : '✗' ?>
                                                <?= $conditionLabels[$cond['condition_type']] ?? $cond['condition_type'] ?>
                                                <?php if (!empty($cond['is_required'])): ?>
                                                    <span class="badge badge-light-danger fs-9">obrigatória</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-0 text-muted fs-8">
                                                <?= $operatorLabels[$cond['operator']] ?? $cond['operator'] ?>
                                                <?= number_format((float)$cond['min_value'], 2, ',', '.') ?>
                                                <?php if (isset($cond['current_metric_value']) && $cond['current_metric_value'] !== null): ?>
                                                    <br><span class="fs-9">(atual: <?= number_format((float)$cond['current_metric_value'], 2, ',', '.') ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- OTE -->
                        <?php if (!empty($goal['ote_total']) && (float)$goal['ote_total'] > 0): ?>
                            <div class="separator my-3"></div>
                            <div class="d-flex justify-content-between fs-7">
                                <span class="text-muted">OTE total da meta:</span>
                                <span class="fw-bold">R$ <?= number_format((float)$goal['ote_total'], 2, ',', '.') ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!--end::Bônus-->
        </div>

        <div class="text-end mt-5">
            <a href="<?= Url::to('/goals/show?id=' . $goal['id']) ?>" class="btn btn-sm btn-light-primary">
                <i class="bi bi-eye"></i> Ver detalhes completos
            </a>
        </div>
    </div>
</div>
<!--end::Meta-->
<?php endforeach; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
