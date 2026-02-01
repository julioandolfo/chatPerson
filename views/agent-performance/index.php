<?php
/**
 * View: Dashboard de Performance de Vendedores
 */
$title = 'Performance de Vendedores';
ob_start();
?>

<div class="d-flex flex-column flex-column-fluid">
    <!--begin::Toolbar-->
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    📊 Performance de Vendedores
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">Dashboard</li>
                </ul>
            </div>
            
            <!-- Filtro de período -->
            <div class="d-flex align-items-center gap-2 gap-lg-3">
                <!-- Filtros Rápidos -->
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriod('today')">Hoje</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriod('yesterday')">Ontem</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriod('this_week')">Esta Semana</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriod('last_week')">Sem. Passada</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriod('this_month')">Este Mês</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="setQuickPeriod('last_month')">Mês Passado</button>
                </div>
                
                <!-- Filtro Personalizado -->
                <form method="GET" id="dateFilterForm" class="d-flex gap-2">
                    <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                    <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="ki-duotone ki-filter fs-4"></i> Filtrar
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!--begin::Content-->
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Cards de estatísticas -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-md-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-md-100 mb-5 mb-xl-10" style="background-color: #F1416C">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?= number_format($stats['avg_overall_score'] ?? 0, 1) ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Média Geral</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span><?= $stats['total_analyses'] ?? 0 ?> Análises</span>
                                    <span><?= $stats['total_agents'] ?? 0 ?> Vendedores</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card card-flush h-md-100" style="background: linear-gradient(112.14deg, #00D2FF 0%, #3A7BD5 100%)">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white"><?= number_format($stats['max_score'] ?? 0, 1) ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Melhor Nota</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Excelência</span>
                                    <span>🏆</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card card-flush h-md-100" style="background: linear-gradient(112.14deg, #FF6B35 0%, #F7931E 100%)">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white">$<?= number_format($stats['total_cost'] ?? 0, 2) ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Custo Total</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span><?= number_format($stats['total_tokens'] ?? 0) ?> tokens</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card card-flush h-md-100" style="background: linear-gradient(112.14deg, #56CCF2 0%, #2F80ED 100%)">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white"><?= count($ranking) ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Ativos</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Vendedores</span>
                                    <span>👥</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ranking -->
            <div class="row g-5 g-xl-10">
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-dark">🏆 Top 10 Vendedores</span>
                                <span class="text-gray-400 mt-1 fw-semibold fs-6">Ranking do período</span>
                            </h3>
                            <div class="card-toolbar">
                                <a href="<?= \App\Helpers\Url::to('/agent-performance/ranking') ?>" class="btn btn-sm btn-light">Ver Todos</a>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle gs-0 gy-3 my-0">
                                    <thead>
                                        <tr class="fs-7 fw-bold text-gray-400 border-bottom-0">
                                            <th class="p-0 pb-3 min-w-50px text-start">#</th>
                                            <th class="p-0 pb-3 min-w-150px text-start">VENDEDOR</th>
                                            <th class="p-0 pb-3 min-w-100px text-end">NOTA</th>
                                            <th class="p-0 pb-3 min-w-100px text-end">CONVERSAS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ranking as $index => $agent): ?>
                                        <tr>
                                            <td class="text-start">
                                                <?php if ($index === 0): ?>
                                                    <span class="badge badge-light-warning fs-7 fw-bold">🥇</span>
                                                <?php elseif ($index === 1): ?>
                                                    <span class="badge badge-light-secondary fs-7 fw-bold">🥈</span>
                                                <?php elseif ($index === 2): ?>
                                                    <span class="badge badge-light-primary fs-7 fw-bold">🥉</span>
                                                <?php else: ?>
                                                    <span class="text-gray-600 fw-bold"><?= $index + 1 ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-start">
                                                <a href="<?= \App\Helpers\Url::to('/agent-performance/agent?id=' . $agent['agent_id']) ?>" class="text-dark fw-bold text-hover-primary mb-1 fs-6">
                                                    <?= htmlspecialchars($agent['agent_name']) ?>
                                                </a>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-light-success fs-7 fw-bold"><?= number_format($agent['avg_score'], 1) ?> / 5.0</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-gray-600 fw-bold fs-6"><?= $agent['total_conversations'] ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($ranking)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-gray-500 py-10">
                                                Nenhuma análise encontrada no período
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Médias por dimensão -->
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-dark">📊 Médias do Time</span>
                                <span class="text-gray-400 mt-1 fw-semibold fs-6">Por dimensão</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <?php
                            $dimensions = [
                                ['key' => 'proactivity', 'name' => 'Proatividade', 'icon' => '🚀'],
                                ['key' => 'objection_handling', 'name' => 'Quebra de Objeções', 'icon' => '💪'],
                                ['key' => 'rapport', 'name' => 'Rapport', 'icon' => '🤝'],
                                ['key' => 'closing_techniques', 'name' => 'Fechamento', 'icon' => '🎯'],
                                ['key' => 'qualification', 'name' => 'Qualificação', 'icon' => '🎓'],
                                ['key' => 'clarity', 'name' => 'Clareza', 'icon' => '💬'],
                                ['key' => 'value_proposition', 'name' => 'Valor', 'icon' => '💎'],
                                ['key' => 'response_time', 'name' => 'Tempo de Resposta', 'icon' => '⚡'],
                                ['key' => 'follow_up', 'name' => 'Follow-up', 'icon' => '📅'],
                                ['key' => 'professionalism', 'name' => 'Profissionalismo', 'icon' => '🎩'],
                            ];
                            
                            foreach ($dimensions as $dim):
                                $avgKey = 'avg_' . $dim['key'];
                                $value = $teamReport['team_averages'][$avgKey] ?? 0;
                                $percent = ($value / 5) * 100;
                                
                                $color = 'success';
                                if ($value < 3) $color = 'danger';
                                elseif ($value < 4) $color = 'warning';
                            ?>
                            <div class="d-flex flex-stack mb-5">
                                <div class="d-flex align-items-center me-2">
                                    <span class="me-2"><?= $dim['icon'] ?></span>
                                    <span class="fw-bold text-gray-800 fs-6"><?= $dim['name'] ?></span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="progress h-6px w-100px me-2">
                                        <div class="progress-bar bg-<?= $color ?>" role="progressbar" style="width: <?= $percent ?>%"></div>
                                    </div>
                                    <span class="badge badge-light-<?= $color ?> fs-7 fw-bold"><?= number_format($value, 1) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
function setQuickPeriod(period) {
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
    
    // Atualizar campos e submeter form
    document.getElementById('date_from').value = formatDate(dateFrom);
    document.getElementById('date_to').value = formatDate(dateTo);
    document.getElementById('dateFilterForm').submit();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
