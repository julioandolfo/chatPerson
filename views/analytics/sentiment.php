<?php
/**
 * Página de Analytics de Sentimento
 */
$layout = 'layouts.metronic.app';
$title = 'Analytics de Sentimento';

$filters = $filters ?? [];
$departments = $departments ?? [];
$agents = $agents ?? [];

// Content
ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Analytics de Sentimento</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        
        <!-- Filtros -->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">Filtros</h3>
            </div>
            <div class="card-body">
                <form id="analytics-filters-form" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Data Inicial</label>
                        <input type="date" name="start_date" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'))) ?>" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Final</label>
                        <input type="date" name="end_date" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($filters['end_date'] ?? date('Y-m-d')) ?>" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Setor</label>
                        <select name="department_id" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= ($filters['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Agente</label>
                        <select name="agent_id" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?= $agent['id'] ?>" <?= ($filters['agent_id'] ?? '') == $agent['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($agent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-duotone ki-magnifier fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Filtrar
                        </button>
                        <button type="button" class="btn btn-light" onclick="resetFilters()">
                            Limpar Filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Estatísticas Gerais -->
        <div class="row g-5 mb-5">
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="text-gray-500 fw-semibold fs-6 mb-1">Total de Análises</div>
                                <div class="fs-2hx fw-bold text-gray-800" id="stat-total-analyses">-</div>
                            </div>
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-chart-simple fs-2x text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="text-gray-500 fw-semibold fs-6 mb-1">Sentimento Médio</div>
                                <div class="fs-2hx fw-bold text-gray-800" id="stat-avg-sentiment">-</div>
                            </div>
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-success">
                                    <i class="ki-duotone ki-heart fs-2x text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="text-gray-500 fw-semibold fs-6 mb-1">Conversas Negativas</div>
                                <div class="fs-2hx fw-bold text-danger" id="stat-negative-count">-</div>
                            </div>
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-danger">
                                    <i class="ki-duotone ki-information-5 fs-2x text-danger">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="text-gray-500 fw-semibold fs-6 mb-1">Custo Total</div>
                                <div class="fs-2hx fw-bold text-gray-800" id="stat-total-cost">-</div>
                            </div>
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-warning">
                                    <i class="ki-duotone ki-dollar fs-2x text-warning">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row g-5 mb-5">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Evolução do Sentimento</h3>
                    </div>
                    <div class="card-body">
                        <div id="chart-evolution" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Distribuição por Sentimento</h3>
                    </div>
                    <div class="card-body">
                        <div id="chart-distribution" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Distribuição por Urgência -->
        <div class="row g-5 mb-5">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Distribuição por Urgência</h3>
                    </div>
                    <div class="card-body">
                        <div id="chart-urgency" style="height: 250px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Distribuição Detalhada</h3>
                    </div>
                    <div class="card-body">
                        <div id="distribution-details">
                            <div class="text-center py-5">
                                <p class="text-muted">Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Conversas Negativas -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Top 20 Conversas com Sentimento Negativo</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                        <thead>
                            <tr class="fw-bold text-muted">
                                <th class="min-w-100px">Contato</th>
                                <th class="min-w-100px">Agente</th>
                                <th class="min-w-100px">Setor</th>
                                <th class="min-w-80px">Score</th>
                                <th class="min-w-100px">Urgência</th>
                                <th class="min-w-150px">Data Análise</th>
                                <th class="min-w-100px text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="negative-conversations-table">
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <p class="text-muted">Carregando...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<!--end::Card-->

<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
let evolutionChart, distributionChart, urgencyChart;

document.addEventListener('DOMContentLoaded', function() {
    // Carregar dados iniciais
    loadAnalyticsData();
    
    // Formulário de filtros
    document.getElementById('analytics-filters-form').addEventListener('submit', function(e) {
        e.preventDefault();
        loadAnalyticsData();
    });
});

function resetFilters() {
    document.getElementById('analytics-filters-form').reset();
    document.querySelector('input[name="start_date"]').value = '<?= date('Y-m-d', strtotime('-30 days')) ?>';
    document.querySelector('input[name="end_date"]').value = '<?= date('Y-m-d') ?>';
    loadAnalyticsData();
}

function loadAnalyticsData() {
    const form = document.getElementById('analytics-filters-form');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    fetch(`<?= \App\Helpers\Url::to('/analytics/sentiment/data') ?>?${params.toString()}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('Dados recebidos:', data);
        
        if (!data.success) {
            console.error('Erro ao carregar dados:', data.message);
            return;
        }
        
        console.log('Stats:', data.stats);
        updateStats(data.stats);
        updateEvolutionChart(data.evolution);
        updateDistributionChart(data.distribution);
        updateUrgencyChart(data.urgency_distribution);
        updateDistributionDetails(data.distribution);
        updateNegativeConversations(data.negative_conversations);
    })
    .catch(error => {
        console.error('Erro ao carregar analytics:', error);
    });
}

function updateStats(stats) {
    // Total de análises
    const totalAnalyses = parseInt(stats.total_analyses) || 0;
    document.getElementById('stat-total-analyses').textContent = totalAnalyses.toLocaleString('pt-BR');
    
    // Sentimento médio
    const avgSentiment = parseFloat(stats.avg_sentiment) || 0;
    const avgEl = document.getElementById('stat-avg-sentiment');
    if (avgSentiment > 0.2) {
        avgEl.textContent = avgSentiment.toFixed(2);
        avgEl.className = 'fs-2hx fw-bold text-success';
    } else if (avgSentiment < -0.2) {
        avgEl.textContent = avgSentiment.toFixed(2);
        avgEl.className = 'fs-2hx fw-bold text-danger';
    } else {
        avgEl.textContent = avgSentiment.toFixed(2);
        avgEl.className = 'fs-2hx fw-bold text-warning';
    }
    
    // Conversas negativas
    const negativeCount = parseInt(stats.negative_count) || 0;
    document.getElementById('stat-negative-count').textContent = negativeCount.toLocaleString('pt-BR');
    
    // Custo total
    const totalCost = parseFloat(stats.total_cost) || 0;
    document.getElementById('stat-total-cost').textContent = '$' + totalCost.toFixed(4);
}

function updateEvolutionChart(evolution) {
    const dates = evolution.map(e => e.date);
    const scores = evolution.map(e => parseFloat(e.avg_score || 0));
    const positive = evolution.map(e => parseInt(e.positive || 0));
    const negative = evolution.map(e => parseInt(e.negative || 0));
    
    const options = {
        series: [{
            name: 'Score Médio',
            type: 'line',
            data: scores
        }, {
            name: 'Positivas',
            type: 'column',
            data: positive
        }, {
            name: 'Negativas',
            type: 'column',
            data: negative
        }],
        chart: {
            height: 300,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: [3, 0, 0]
        },
        xaxis: {
            categories: dates
        },
        yaxis: [{
            title: { text: 'Score' },
            min: -1,
            max: 1
        }, {
            opposite: true,
            title: { text: 'Quantidade' }
        }],
        colors: ['#00D9FF', '#50CD89', '#F1416C'],
        legend: {
            position: 'top'
        },
        tooltip: {
            shared: true,
            intersect: false
        }
    };
    
    if (evolutionChart) {
        evolutionChart.updateOptions(options);
    } else {
        evolutionChart = new ApexCharts(document.querySelector("#chart-evolution"), options);
        evolutionChart.render();
    }
}

function updateDistributionChart(distribution) {
    const labels = [];
    const values = [];
    const colors = [];
    
    distribution.forEach(item => {
        labels.push(item.sentiment_label === 'positive' ? 'Positivo' : 
                   item.sentiment_label === 'negative' ? 'Negativo' : 'Neutro');
        values.push(parseInt(item.count || 0));
        colors.push(item.sentiment_label === 'positive' ? '#50CD89' : 
                   item.sentiment_label === 'negative' ? '#F1416C' : '#FFC700');
    });
    
    const options = {
        series: values,
        chart: {
            type: 'donut',
            height: 300
        },
        labels: labels,
        colors: colors,
        legend: {
            position: 'bottom'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '65%'
                }
            }
        }
    };
    
    if (distributionChart) {
        distributionChart.updateSeries(values);
        distributionChart.updateOptions({ labels: labels, colors: colors });
    } else {
        distributionChart = new ApexCharts(document.querySelector("#chart-distribution"), options);
        distributionChart.render();
    }
}

function updateUrgencyChart(urgencyDistribution) {
    const labels = [];
    const values = [];
    
    urgencyDistribution.forEach(item => {
        labels.push(item.urgency_level === 'critical' ? 'Crítica' :
                   item.urgency_level === 'high' ? 'Alta' :
                   item.urgency_level === 'medium' ? 'Média' : 'Baixa');
        values.push(parseInt(item.count || 0));
    });
    
    const options = {
        series: values,
        chart: {
            type: 'bar',
            height: 250,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                distributed: true
            }
        },
        xaxis: {
            categories: labels
        },
        colors: ['#F1416C', '#FF9800', '#FFC700', '#50CD89']
    };
    
    if (urgencyChart) {
        urgencyChart.updateSeries(values);
        urgencyChart.updateOptions({ xaxis: { categories: labels } });
    } else {
        urgencyChart = new ApexCharts(document.querySelector("#chart-urgency"), options);
        urgencyChart.render();
    }
}

function updateDistributionDetails(distribution) {
    const container = document.getElementById('distribution-details');
    
    if (!distribution || distribution.length === 0) {
        container.innerHTML = '<div class="text-center py-5"><p class="text-muted">Nenhum dado disponível</p></div>';
        return;
    }
    
    let html = '';
    distribution.forEach(item => {
        const label = item.sentiment_label === 'positive' ? 'Positivo' : 
                     item.sentiment_label === 'negative' ? 'Negativo' : 'Neutro';
        const color = item.sentiment_label === 'positive' ? 'success' : 
                     item.sentiment_label === 'negative' ? 'danger' : 'warning';
        const count = parseInt(item.count || 0);
        const avgScore = parseFloat(item.avg_score || 0);
        
        html += `
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <span class="badge badge-light-${color} fs-6">${label}</span>
                    <div class="text-muted fs-7 mt-1">Score médio: ${avgScore.toFixed(2)}</div>
                </div>
                <div class="text-end">
                    <div class="fs-2 fw-bold text-gray-800">${count}</div>
                    <div class="text-muted fs-7">análises</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateNegativeConversations(conversations) {
    const tbody = document.getElementById('negative-conversations-table');
    
    if (!conversations || conversations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><p class="text-muted">Nenhuma conversa negativa encontrada</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = conversations.map(conv => {
        const score = parseFloat(conv.sentiment_score || 0);
        const urgency = conv.urgency_level || '-';
        const urgencyBadge = urgency === 'critical' ? 'danger' : 
                            urgency === 'high' ? 'warning' : 
                            urgency === 'medium' ? 'info' : 'secondary';
        const date = new Date(conv.analyzed_at).toLocaleString('pt-BR');
        
        return `
            <tr>
                <td>${escapeHtml(conv.contact_name || '-')}</td>
                <td>${escapeHtml(conv.agent_name || 'Não atribuído')}</td>
                <td>${escapeHtml(conv.department_name || '-')}</td>
                <td>
                    <span class="badge badge-light-danger">${score.toFixed(2)}</span>
                </td>
                <td>
                    <span class="badge badge-light-${urgencyBadge}">${urgency}</span>
                </td>
                <td>${date}</td>
                <td class="text-end">
                    <a href="<?= \App\Helpers\Url::to('/conversations') ?>/${conv.conversation_id}" 
                       class="btn btn-sm btn-light-primary" target="_blank">
                        Ver Conversa
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php 
$content = ob_get_clean(); 
include __DIR__ . '/../layouts/metronic/app.php';
?>
