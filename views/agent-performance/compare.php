<?php
$layout = 'layouts.metronic.app';
$title = 'Comparar Agentes';

ob_start();
?>

<div class="d-flex flex-column gap-7 gap-lg-10">
    
    <!-- Header -->
    <div class="card">
        <div class="card-body">
            <h1 class="mb-1">
                <i class="ki-duotone ki-chart-line fs-1 text-primary me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                Comparar Performance
            </h1>
            <p class="text-muted mb-0">
                Compare a performance de até 5 agentes lado a lado
            </p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card">
        <div class="card-body">
            <form method="GET" id="compareForm">
                <div class="row g-5">
                    <div class="col-lg-8">
                        <label class="form-label required">Selecionar Agentes (2-5)</label>
                        <select name="agents[]" class="form-select" multiple required 
                                data-control="select2" data-placeholder="Selecione os agentes">
                            <?php foreach ($allAgents as $agent): ?>
                            <option value="<?= $agent['id'] ?>" 
                                <?= in_array($agent['id'], $selectedAgents) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($agent['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Selecione de 2 a 5 agentes para comparar</div>
                    </div>
                    
                    <div class="col-lg-4">
                        <label class="form-label">Período</label>
                        
                        <!-- Filtros Rápidos -->
                        <div class="btn-group w-100 mb-2" role="group">
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodCompare('today')">Hoje</button>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodCompare('yesterday')">Ontem</button>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodCompare('this_week')">Sem.</button>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodCompare('this_month')">Mês</button>
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriodCompare('last_month')">Mês Pass.</button>
                        </div>
                        
                        <!-- Filtro Personalizado -->
                        <div class="input-group mb-2">
                            <input type="date" name="date_from" id="date_from_compare" class="form-control" 
                                   value="<?= $dateFrom ?>" required>
                            <span class="input-group-text">até</span>
                            <input type="date" name="date_to" id="date_to_compare" class="form-control" 
                                   value="<?= $dateTo ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ki-duotone ki-search-list fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Comparar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($comparison)): ?>
    
    <!-- Tabela de Comparação -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Comparação de Performance</h3>
            <div class="card-toolbar">
                <span class="badge badge-light-primary">
                    <?= date('d/m/Y', strtotime($dateFrom)) ?> - <?= date('d/m/Y', strtotime($dateTo)) ?>
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle gs-5 gy-4">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th class="min-w-200px ps-5">Métrica</th>
                            <?php foreach ($comparison as $agent): ?>
                            <th class="text-center min-w-150px">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-35px mb-2">
                                        <span class="symbol-label bg-light-primary text-primary fw-bold">
                                            <?= strtoupper(substr($agent['agent']['name'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <span><?= htmlspecialchars($agent['agent']['name']) ?></span>
                                </div>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Nota Geral -->
                        <tr class="bg-light-primary">
                            <td class="fw-bold ps-5">
                                <i class="ki-duotone ki-star fs-3 text-primary me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Nota Geral
                            </td>
                            <?php 
                            $maxScore = max(array_column($comparison, 'overall_score'));
                            foreach ($comparison as $agent): 
                                $isMax = $agent['overall_score'] == $maxScore;
                            ?>
                            <td class="text-center">
                                <span class="fs-2x fw-bold <?= $isMax ? 'text-primary' : '' ?>">
                                    <?= number_format($agent['overall_score'], 2) ?>
                                </span>
                                <?php if ($isMax): ?>
                                <i class="ki-duotone ki-crown fs-2 text-warning ms-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <!-- Total de Análises -->
                        <tr>
                            <td class="ps-5 text-muted">Total de Análises</td>
                            <?php foreach ($comparison as $agent): ?>
                            <td class="text-center">
                                <span class="badge badge-light"><?= $agent['total_analyses'] ?></span>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <tr><td colspan="<?= count($comparison) + 1 ?>" class="bg-light"></td></tr>
                        
                        <!-- Dimensões -->
                        <?php
                        $dimensions = [
                            'proactivity' => ['label' => '🚀 Proatividade', 'key' => 'proactivity'],
                            'objection_handling' => ['label' => '🛡️ Quebra de Objeções', 'key' => 'objection_handling'],
                            'rapport' => ['label' => '🤝 Rapport', 'key' => 'rapport'],
                            'closing_techniques' => ['label' => '✅ Técnicas de Fechamento', 'key' => 'closing_techniques'],
                            'qualification' => ['label' => '🔍 Qualificação', 'key' => 'qualification'],
                            'clarity' => ['label' => '💬 Clareza', 'key' => 'clarity'],
                            'value_proposition' => ['label' => '⭐ Proposta de Valor', 'key' => 'value_proposition'],
                            'response_time' => ['label' => '⏱️ Tempo de Resposta', 'key' => 'response_time'],
                            'follow_up' => ['label' => '📅 Follow-up', 'key' => 'follow_up'],
                            'professionalism' => ['label' => '🏆 Profissionalismo', 'key' => 'professionalism']
                        ];
                        
                        foreach ($dimensions as $key => $dim):
                            $scores = array_column(array_column($comparison, 'dimensions'), $key);
                            $maxDimScore = max($scores);
                        ?>
                        <tr>
                            <td class="ps-5"><?= $dim['label'] ?></td>
                            <?php foreach ($comparison as $agent): 
                                $score = $agent['dimensions'][$key];
                                $isMax = $score == $maxDimScore;
                            ?>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <span class="fw-bold <?= $isMax ? 'text-success' : '' ?>">
                                        <?= number_format($score, 2) ?>
                                    </span>
                                    <?php if ($isMax): ?>
                                    <i class="ki-duotone ki-check-circle fs-4 text-success ms-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <?php endif; ?>
                                </div>
                                <div class="progress h-4px w-75px mx-auto mt-1">
                                    <div class="progress-bar bg-<?= $isMax ? 'success' : 'primary' ?>" 
                                         style="width: <?= ($score / 5) * 100 ?>%"></div>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Gráfico Radar (Opcional) -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Visualização Comparativa</h3>
        </div>
        <div class="card-body">
            <div class="row g-5">
                <?php foreach ($comparison as $agent): ?>
                <div class="col-lg-<?= count($comparison) == 2 ? '6' : (count($comparison) == 3 ? '4' : '6') ?>">
                    <div class="border border-gray-300 rounded p-5">
                        <h4 class="mb-5"><?= htmlspecialchars($agent['agent']['name']) ?></h4>
                        <?php foreach ($dimensions as $key => $dim): 
                            $score = $agent['dimensions'][$key];
                            $percentage = ($score / 5) * 100;
                        ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fs-7"><?= $dim['label'] ?></span>
                                <span class="fw-bold"><?= number_format($score, 2) ?></span>
                            </div>
                            <div class="progress h-8px">
                                <div class="progress-bar bg-<?= 
                                    $score >= 4.5 ? 'success' : 
                                    ($score >= 3.5 ? 'primary' : 
                                    ($score >= 2.5 ? 'warning' : 'danger')) 
                                ?>" style="width: <?= $percentage ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Estado vazio -->
    <div class="card">
        <div class="card-body text-center py-20">
            <i class="ki-duotone ki-chart-line fs-5x text-muted mb-5">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
            </i>
            <h3 class="text-muted mb-3">Selecione os agentes para comparar</h3>
            <p class="text-muted">
                Escolha de 2 a 5 agentes acima para ver a comparação de performance
            </p>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Inicializar Select2 se disponível
$(document).ready(function() {
    if ($.fn.select2) {
        $('[data-control="select2"]').select2({
            maximumSelectionLength: 5
        });
    }
});

function setQuickPeriodCompare(period) {
    const today = new Date();
    let dateFrom, dateTo;
    
    switch(period) {
        case 'today':
            dateFrom = dateTo = today;
            break;
            
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            dateFrom = dateTo = yesterday;
            break;
            
        case 'this_week':
            // Segunda-feira da semana atual
            const startOfWeek = new Date(today);
            const dayOfWeek = startOfWeek.getDay();
            const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek; // Se domingo, volta 6 dias
            startOfWeek.setDate(startOfWeek.getDate() + diff);
            dateFrom = startOfWeek;
            dateTo = today;
            break;
            
        case 'last_week':
            // Segunda a domingo da semana passada
            const lastWeekEnd = new Date(today);
            const currentDay = lastWeekEnd.getDay();
            const daysToLastSunday = currentDay === 0 ? 0 : currentDay;
            lastWeekEnd.setDate(lastWeekEnd.getDate() - daysToLastSunday);
            
            const lastWeekStart = new Date(lastWeekEnd);
            lastWeekStart.setDate(lastWeekStart.getDate() - 6);
            
            dateFrom = lastWeekStart;
            dateTo = lastWeekEnd;
            break;
            
        case 'this_month':
            dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
            dateTo = today;
            break;
            
        case 'last_month':
            const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            dateFrom = lastMonth;
            dateTo = lastDayOfLastMonth;
            break;
    }
    
    // Formatar datas para YYYY-MM-DD
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    };
    
    // Atualizar campos
    document.getElementById('date_from_compare').value = formatDate(dateFrom);
    document.getElementById('date_to_compare').value = formatDate(dateTo);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
