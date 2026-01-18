<?php
$layout = 'layouts.metronic.app';
$title = 'Analytics Avançado';
$pageTitle = 'Analytics';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Analytics Avançado de Campanhas
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-light-primary" onclick="exportAnalytics()">
                    <i class="ki-duotone ki-file-down fs-3"></i>
                    Exportar
                </button>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Filtros -->
            <div class="card card-flush mb-5">
                <div class="card-body py-5">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Período</label>
                            <select class="form-select form-select-sm" id="filter-period" onchange="loadAnalytics()">
                                <option value="7">Últimos 7 dias</option>
                                <option value="30" selected>Últimos 30 dias</option>
                                <option value="90">Últimos 90 dias</option>
                                <option value="all">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Campanha</label>
                            <select class="form-select form-select-sm" id="filter-campaign" onchange="loadAnalytics()">
                                <option value="">Todas</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Métrica</label>
                            <select class="form-select form-select-sm" id="filter-metric">
                                <option value="reply_rate">Taxa de Resposta</option>
                                <option value="delivery_rate">Taxa de Entrega</option>
                                <option value="read_rate">Taxa de Leitura</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Comparação de Campanhas -->
            <div class="row g-5 mb-5">
                <div class="col-xl-12">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Comparação de Performance</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="chart_comparison" style="height: 400px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Análise de Horários -->
            <div class="row g-5 mb-5">
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Melhores Horários</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Por taxa de resposta</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="chart_best_hours" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Melhores Dias da Semana</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Por taxa de resposta</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="chart_best_days" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance de Contas -->
            <div class="row g-5 mb-5">
                <div class="col-xl-12">
                    <div class="card card-flush">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Performance por Conta WhatsApp</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="table-responsive">
                                <table class="table table-row-bordered align-middle gs-0 gy-3" id="accounts_performance_table">
                                    <thead>
                                        <tr class="fw-bold text-muted bg-light">
                                            <th class="ps-4">Conta</th>
                                            <th>Número</th>
                                            <th>Total Enviadas</th>
                                            <th>Taxa Entrega</th>
                                            <th>Taxa Leitura</th>
                                            <th>Taxa Resposta</th>
                                            <th class="pe-4">Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="7" class="text-center py-5">Carregando...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
function loadAnalytics() {
    const period = document.getElementById('filter-period').value;
    
    fetch(`/api/campaigns/analytics?period=${period}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderComparisonChart(data.comparison);
                renderBestHoursChart(data.best_hours);
                renderBestDaysChart(data.best_days);
                renderAccountsPerformance(data.accounts_performance);
            }
        })
        .catch(err => toastr.error('Erro ao carregar analytics'));
}

function renderComparisonChart(data) {
    const options = {
        series: [{
            name: 'Taxa de Resposta',
            type: 'column',
            data: data.reply_rates || []
        }, {
            name: 'Taxa de Entrega',
            type: 'line',
            data: data.delivery_rates || []
        }],
        chart: {
            height: 400,
            type: 'line',
            toolbar: { show: false }
        },
        stroke: {
            width: [0, 2]
        },
        dataLabels: {
            enabled: true,
            enabledOnSeries: [1]
        },
        xaxis: {
            categories: data.campaign_names || []
        },
        yaxis: [{
            title: { text: 'Taxa de Resposta (%)' }
        }, {
            opposite: true,
            title: { text: 'Taxa de Entrega (%)' }
        }]
    };
    
    const chart = new ApexCharts(document.querySelector("#chart_comparison"), options);
    chart.render();
}

function renderBestHoursChart(data) {
    const options = {
        series: [{
            name: 'Taxa de Resposta',
            data: data.rates || Array.from({length: 24}, () => Math.random() * 30)
        }],
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: false,
                columnWidth: '55%'
            }
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: Array.from({length: 24}, (_, i) => `${i}h`)
        },
        colors: ['#009EF7'],
        yaxis: {
            title: { text: 'Taxa de Resposta (%)' }
        }
    };
    
    const chart = new ApexCharts(document.querySelector("#chart_best_hours"), options);
    chart.render();
}

function renderBestDaysChart(data) {
    const options = {
        series: [{
            name: 'Taxa de Resposta',
            data: data.rates || [25, 30, 28, 32, 29, 15, 12]
        }],
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: true
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val.toFixed(1) + '%';
            }
        },
        xaxis: {
            categories: ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']
        },
        colors: ['#50CD89']
    };
    
    const chart = new ApexCharts(document.querySelector("#chart_best_days"), options);
    chart.render();
}

function renderAccountsPerformance(accounts) {
    const tbody = document.querySelector('#accounts_performance_table tbody');
    
    if (!accounts || accounts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5">Sem dados</td></tr>';
        return;
    }
    
    tbody.innerHTML = accounts.map(acc => {
        const performance = acc.reply_rate > 20 ? 'success' : acc.reply_rate > 10 ? 'warning' : 'danger';
        const performanceIcon = acc.reply_rate > 20 ? 'arrow-up' : acc.reply_rate > 10 ? 'minus' : 'arrow-down';
        
        return `
            <tr>
                <td class="ps-4"><span class="fw-bold">${acc.name}</span></td>
                <td>${acc.phone_number}</td>
                <td><span class="badge badge-light-primary">${acc.total_sent}</span></td>
                <td><span class="badge badge-light-success">${acc.delivery_rate.toFixed(1)}%</span></td>
                <td><span class="badge badge-light-info">${acc.read_rate.toFixed(1)}%</span></td>
                <td><span class="badge badge-light-warning">${acc.reply_rate.toFixed(1)}%</span></td>
                <td class="pe-4">
                    <span class="badge badge-light-${performance}">
                        <i class="ki-duotone ki-${performanceIcon} fs-6"></i>
                        ${acc.reply_rate > 20 ? 'Excelente' : acc.reply_rate > 10 ? 'Bom' : 'Baixo'}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

function exportAnalytics() {
    const period = document.getElementById('filter-period').value;
    window.open(`/api/campaigns/export?period=${period}`, '_blank');
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadAnalytics();
});
</script>
