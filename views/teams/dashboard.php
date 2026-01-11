<?php
$layout = 'layouts.metronic.app';
$title = 'Dashboard de Times';

ob_start();
?>

<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack pb-7">
    <div class="d-flex flex-wrap align-items-center my-1">
        <h3 class="fw-bold me-5 my-1">Dashboard de Times</h3>
    </div>
    <div class="d-flex align-items-center my-1">
        <a href="/teams" class="btn btn-sm btn-light me-2">
            <i class="ki-duotone ki-arrow-left fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Voltar para Times
        </a>
        <!--begin::Filter-->
        <div class="d-flex align-items-center me-2">
            <input type="date" id="filter-date-from" class="form-control form-control-sm me-2" value="<?= $dateFrom ?>" />
            <span class="mx-2">até</span>
            <input type="date" id="filter-date-to" class="form-control form-control-sm me-2" value="<?= $dateTo ?>" />
            <button type="button" class="btn btn-sm btn-primary" onclick="filterByDate()">
                <i class="ki-duotone ki-filter fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Filtrar
            </button>
        </div>
        <!--end::Filter-->
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Row - Overview-->
<div class="row g-5 g-xl-10 mb-5 mb-xl-10">
    <?php
    // Calcular totais gerais
    $totalMembers = 0;
    $totalConversations = 0;
    $totalClosed = 0;
    $avgResolutionRate = 0;
    
    foreach ($teamsRanking as $team) {
        $totalMembers += $team['members_count'] ?? 0;
        $totalConversations += $team['total_conversations'] ?? 0;
        $totalClosed += $team['closed_conversations'] ?? 0;
    }
    
    if ($totalConversations > 0) {
        $avgResolutionRate = ($totalClosed / $totalConversations) * 100;
    }
    ?>
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-people fs-3x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800"><?= count($teams) ?></span>
                    <span class="text-gray-500 fw-semibold fs-6">Times Ativos</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-profile-user fs-3x text-info mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800"><?= $totalMembers ?></span>
                    <span class="text-gray-500 fw-semibold fs-6">Total de Agentes</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-message-text-2 fs-3x text-success mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800"><?= $totalConversations ?></span>
                    <span class="text-gray-500 fw-semibold fs-6">Total de Conversas</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
    
    <!--begin::Col-->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-flush h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <i class="ki-duotone ki-chart-simple fs-3x text-warning mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <div class="d-flex flex-column">
                    <span class="fs-2hx fw-bold text-gray-800"><?= number_format($avgResolutionRate, 1) ?>%</span>
                    <span class="text-gray-500 fw-semibold fs-6">Taxa Média de Resolução</span>
                </div>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row - Ranking-->
<div class="row g-5 g-xl-10">
    <div class="col-xl-12">
        <div class="card card-flush">
            <div class="card-header pt-7">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-800">Ranking de Times</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Ordenado por conversas resolvidas</span>
                </h3>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#ranking-filters">
                        <i class="ki-duotone ki-filter fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Filtros
                    </button>
                </div>
            </div>
            <div class="card-body pt-5">
                <!--begin::Filters-->
                <div class="collapse mb-5" id="ranking-filters">
                    <div class="border border-dashed border-gray-300 rounded p-5">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Ordenar por</label>
                                <select class="form-select form-select-sm" id="sort-by">
                                    <option value="closed">Conversas Resolvidas</option>
                                    <option value="total">Total de Conversas</option>
                                    <option value="rate">Taxa de Resolução</option>
                                    <option value="response">Tempo de Resposta</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Filters-->
                
                <?php if (empty($teamsRanking)): ?>
                    <div class="text-center py-10">
                        <p class="text-muted">Nenhum dado disponível para o período selecionado</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="ranking-table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th class="w-10px pe-2">#</th>
                                    <th class="min-w-200px">Time</th>
                                    <th class="min-w-100px">Membros</th>
                                    <th class="min-w-100px">Conversas</th>
                                    <th class="min-w-100px">Resolvidas</th>
                                    <th class="min-w-100px">Taxa</th>
                                    <th class="min-w-120px">TM Resposta</th>
                                    <th class="min-w-120px">TM Resolução</th>
                                    <th class="text-end min-w-100px">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold">
                                <?php foreach ($teamsRanking as $index => $team): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-gray-800">#<?= $index + 1 ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40px me-3" style="background-color: <?= htmlspecialchars($team['team_color'] ?? '#009ef7') ?>20;">
                                                <span class="symbol-label" style="color: <?= htmlspecialchars($team['team_color'] ?? '#009ef7') ?>;">
                                                    <i class="ki-duotone ki-people fs-2x">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                        <span class="path4"></span>
                                                        <span class="path5"></span>
                                                    </i>
                                                </span>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <a href="/teams/show?id=<?= $team['team_id'] ?>" class="text-gray-800 text-hover-primary fw-bold">
                                                    <?= htmlspecialchars($team['team_name']) ?>
                                                </a>
                                                <?php if (!empty($team['leader_name'])): ?>
                                                <span class="text-muted fs-7">Líder: <?= htmlspecialchars($team['leader_name']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($team['department_name'])): ?>
                                                <span class="text-muted fs-7">Setor: <?= htmlspecialchars($team['department_name']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $team['members_count'] ?? 0 ?></td>
                                    <td><?= $team['total_conversations'] ?? 0 ?></td>
                                    <td>
                                        <span class="badge badge-light-success"><?= $team['closed_conversations'] ?? 0 ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold me-2"><?= number_format($team['resolution_rate'] ?? 0, 1) ?>%</span>
                                            <div class="progress h-6px w-100px">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $team['resolution_rate'] ?? 0 ?>%" 
                                                     aria-valuenow="<?= $team['resolution_rate'] ?? 0 ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= \App\Services\TeamPerformanceService::formatTime($team['avg_first_response_time'] ?? null) ?></td>
                                    <td><?= \App\Services\TeamPerformanceService::formatTime($team['avg_resolution_time'] ?? null) ?></td>
                                    <td class="text-end">
                                        <a href="/teams/show?id=<?= $team['team_id'] ?>" class="btn btn-sm btn-light btn-active-light-primary">
                                            Ver Detalhes
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->

<?php if (!empty($conversionByTeam) && \App\Helpers\Permission::can('conversion.view')): ?>
<!--begin::Row - Conversão WooCommerce por Time-->
<div class="row g-5 g-xl-10 mb-5 mb-xl-10">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">
                        <i class="ki-duotone ki-chart-line-up fs-2 text-success me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Conversão WooCommerce por Time
                    </span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Métricas de vendas dos membros de cada time</span>
                </h3>
            </div>
            <div class="card-body pt-3">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Time</th>
                                <th class="min-w-80px text-center">Vendedores</th>
                                <th class="min-w-100px text-center">Conversas</th>
                                <th class="min-w-80px text-center">Vendas</th>
                                <th class="min-w-120px text-center">Taxa Conversão</th>
                                <th class="min-w-120px text-end">Valor Total</th>
                                <th class="min-w-120px text-end">Ticket Médio</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <?php foreach ($conversionByTeam as $teamData): ?>
                                <?php
                                    $conversionRate = $teamData['conversion_rate'] ?? 0;
                                    $progressColor = 'danger';
                                    if ($conversionRate >= 30) {
                                        $progressColor = 'success';
                                    } elseif ($conversionRate >= 15) {
                                        $progressColor = 'warning';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-circle symbol-35px me-3" style="background-color: <?= htmlspecialchars($teamData['team_color'] ?? '#000000') ?>;">
                                                <span class="symbol-label text-inverse-light fw-bold fs-7">
                                                    <?= mb_substr(htmlspecialchars($teamData['team_name'] ?? 'T'), 0, 1) ?>
                                                </span>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <a href="/teams/show?id=<?= $teamData['team_id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="text-gray-800 text-hover-primary fs-6 fw-bold">
                                                    <?= htmlspecialchars($teamData['team_name']) ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light"><?= $teamData['sellers_count'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-gray-800"><?= $teamData['total_conversations'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light-success"><?= $teamData['total_orders'] ?? 0 ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="fw-bold text-gray-800 fs-6 mb-1"><?= number_format($conversionRate, 1) ?>%</span>
                                            <div class="progress h-6px w-100px">
                                                <div class="progress-bar bg-<?= $progressColor ?>" role="progressbar" style="width: <?= min(100, $conversionRate) ?>%" aria-valuenow="<?= $conversionRate ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-success fw-bold">
                                            <?= \App\Services\AgentConversionService::formatCurrency($teamData['total_revenue'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-gray-800 fw-bold">
                                            <?= \App\Services\AgentConversionService::formatCurrency($teamData['avg_ticket'] ?? 0) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Row-->
<?php endif; ?>

<script>
// Filtrar por data
function filterByDate() {
    const dateFrom = document.getElementById('filter-date-from').value;
    const dateTo = document.getElementById('filter-date-to').value;
    
    window.location.href = `/teams/dashboard?date_from=${dateFrom}&date_to=${dateTo}`;
}

// Ordenar tabela
document.getElementById('sort-by')?.addEventListener('change', function(e) {
    const sortBy = e.target.value;
    const table = document.getElementById('ranking-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        let aVal, bVal;
        
        switch(sortBy) {
            case 'closed':
                aVal = parseInt(a.cells[4].textContent) || 0;
                bVal = parseInt(b.cells[4].textContent) || 0;
                break;
            case 'total':
                aVal = parseInt(a.cells[3].textContent) || 0;
                bVal = parseInt(b.cells[3].textContent) || 0;
                break;
            case 'rate':
                aVal = parseFloat(a.cells[5].textContent) || 0;
                bVal = parseFloat(b.cells[5].textContent) || 0;
                break;
            case 'response':
                // Simplificado - ordenar por texto
                aVal = a.cells[6].textContent;
                bVal = b.cells[6].textContent;
                return aVal.localeCompare(bVal);
        }
        
        return bVal - aVal; // Ordem decrescente
    });
    
    // Reordenar linhas
    rows.forEach((row, index) => {
        row.cells[0].querySelector('.fw-bold').textContent = '#' + (index + 1);
        tbody.appendChild(row);
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
