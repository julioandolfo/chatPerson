<?php
$layout = 'layouts.metronic.app';
$title = 'Dashboard de Campanhas';
$pageTitle = 'Dashboard';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Dashboard de Campanhas
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">
                        <a href="/campaigns" class="text-muted text-hover-primary">Campanhas</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">Dashboard</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm w-150px" id="period-filter" onchange="loadDashboard()">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30" selected>Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                </select>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- KPI Cards -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3">
                    <div class="card card-flush" style="background: linear-gradient(112.14deg, #00D2FF 0%, #3A7BD5 100%)">
                        <div class="card-body">
                            <span class="svg-icon svg-icon-white svg-icon-3x ms-n1">
                                <i class="ki-duotone ki-message-text-2 text-white fs-3x">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5" id="kpi-total-sent">0</div>
                            <div class="fw-semibold text-white">Total Enviadas</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3">
                    <div class="card card-flush" style="background: linear-gradient(112.14deg, #FF6FD8 0%, #3813C2 100%)">
                        <div class="card-body">
                            <span class="svg-icon svg-icon-white svg-icon-3x ms-n1">
                                <i class="ki-duotone ki-verify text-white fs-3x">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5" id="kpi-delivery-rate">0%</div>
                            <div class="fw-semibold text-white">Taxa de Entrega</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3">
                    <div class="card card-flush" style="background: linear-gradient(112.14deg, #FFA400 0%, #FF5722 100%)">
                        <div class="card-body">
                            <span class="svg-icon svg-icon-white svg-icon-3x ms-n1">
                                <i class="ki-duotone ki-eye text-white fs-3x">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5" id="kpi-read-rate">0%</div>
                            <div class="fw-semibold text-white">Taxa de Leitura</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-6 col-xl-6 col-xxl-3">
                    <div class="card card-flush" style="background: linear-gradient(112.14deg, #28C76F 0%, #00A86B 100%)">
                        <div class="card-body">
                            <span class="svg-icon svg-icon-white svg-icon-3x ms-n1">
                                <i class="ki-duotone ki-message-text text-white fs-3x">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </span>
                            <div class="text-white fw-bold fs-2 mb-2 mt-5" id="kpi-reply-rate">0%</div>
                            <div class="fw-semibold text-white">Taxa de Resposta</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos Principais -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-xl-8">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Evolução de Mensagens</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Últimos 30 dias</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="chart_messages_evolution" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Status das Campanhas</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="chart_campaign_status" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos Secundários -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Top 10 Campanhas</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Por taxa de resposta</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="chart_top_campaigns" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Uso de Contas WhatsApp</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Distribuição de envios</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="chart_account_usage" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos Adicionais -->
            <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Heatmap de Envios</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Por hora do dia</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="chart_heatmap" style="height: 300px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Timeline de Atividades</span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6">Últimas 24 horas</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="timeline_list" style="max-height: 300px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de Campanhas Recentes -->
            <div class="row g-5 g-xl-10">
                <div class="col-xl-12">
                    <div class="card card-flush">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Campanhas Recentes</span>
                            </h3>
                            <div class="card-toolbar">
                                <button class="btn btn-sm btn-light-primary" onclick="exportAllData()">
                                    <i class="ki-duotone ki-file-down fs-3"></i>
                                    Exportar Tudo
                                </button>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <div class="table-responsive">
                                <table class="table table-row-bordered align-middle gs-0 gy-3">
                                    <thead>
                                        <tr class="fw-bold text-muted bg-light">
                                            <th class="ps-4">Campanha</th>
                                            <th>Status</th>
                                            <th>Enviadas</th>
                                            <th>Entrega</th>
                                            <th>Leitura</th>
                                            <th>Respostas</th>
                                            <th class="pe-4">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent_campaigns_tbody">
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
let dashboardData = null;

// Load dashboard data
function loadDashboard() {
    const period = document.getElementById('period-filter').value;
    
    fetch(`/api/campaigns/dashboard?period=${period}`)
        .then(r => r.json())
        .then(data => {
            dashboardData = data;
            updateKPIs(data);
            renderCharts(data);
            renderRecentCampaigns(data.recent_campaigns || []);
        })
        .catch(err => {
            console.error('Error loading dashboard:', err);
            toastr.error('Erro ao carregar dashboard');
        });
}

// Update KPIs
function updateKPIs(data) {
    document.getElementById('kpi-total-sent').textContent = (data.kpis.total_sent || 0).toLocaleString();
    document.getElementById('kpi-delivery-rate').textContent = (data.kpis.delivery_rate || 0).toFixed(1) + '%';
    document.getElementById('kpi-read-rate').textContent = (data.kpis.read_rate || 0).toFixed(1) + '%';
    document.getElementById('kpi-reply-rate').textContent = (data.kpis.reply_rate || 0).toFixed(1) + '%';
}

// Render Charts
function renderCharts(data) {
    // 1. Messages Evolution Chart
    const evolutionOptions = {
        series: [{
            name: 'Enviadas',
            data: data.evolution.sent || []
        }, {
            name: 'Entregues',
            data: data.evolution.delivered || []
        }, {
            name: 'Lidas',
            data: data.evolution.read || []
        }, {
            name: 'Respondidas',
            data: data.evolution.replied || []
        }],
        chart: {
            type: 'area',
            height: 350,
            toolbar: { show: false }
        },
        dataLabels: { enabled: false },
        stroke: {
            curve: 'smooth',
            width: 2
        },
        xaxis: {
            categories: data.evolution.dates || [],
            labels: {
                rotate: -45,
                rotateAlways: true
            }
        },
        colors: ['#009EF7', '#50CD89', '#FFC700', '#7239EA'],
        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0.6,
                opacityTo: 0.1
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left'
        },
        tooltip: {
            x: { format: 'dd/MM/yyyy' }
        }
    };
    
    const evolutionChart = new ApexCharts(document.querySelector("#chart_messages_evolution"), evolutionOptions);
    evolutionChart.render();
    
    // 2. Campaign Status Donut
    const statusOptions = {
        series: data.status_distribution.values || [],
        chart: {
            type: 'donut',
            height: 350
        },
        labels: data.status_distribution.labels || [],
        colors: ['#E4E6EF', '#009EF7', '#7239EA', '#FFC700', '#50CD89', '#F1416C'],
        legend: {
            position: 'bottom'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: { show: true },
                        value: { show: true },
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function(w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                            }
                        }
                    }
                }
            }
        }
    };
    
    const statusChart = new ApexCharts(document.querySelector("#chart_campaign_status"), statusOptions);
    statusChart.render();
    
    // 3. Top Campaigns Bar
    const topOptions = {
        series: [{
            name: 'Taxa de Resposta',
            data: data.top_campaigns.reply_rates || []
        }],
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: false }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val.toFixed(1) + '%';
            }
        },
        xaxis: {
            categories: data.top_campaigns.names || [],
            max: 100
        },
        colors: ['#009EF7'],
        tooltip: {
            y: {
                formatter: function(val) {
                    return val.toFixed(1) + '%';
                }
            }
        }
    };
    
    const topChart = new ApexCharts(document.querySelector("#chart_top_campaigns"), topOptions);
    topChart.render();
    
    // 4. Account Usage Pie
    const accountOptions = {
        series: data.account_usage.totals || [],
        chart: {
            type: 'pie',
            height: 350
        },
        labels: data.account_usage.names || [],
        colors: ['#009EF7', '#50CD89', '#FFC700', '#7239EA', '#F1416C', '#181C32', '#00D2FF', '#FF6FD8'],
        legend: {
            position: 'bottom'
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return val.toLocaleString() + ' mensagens';
                }
            }
        }
    };
    
    const accountChart = new ApexCharts(document.querySelector("#chart_account_usage"), accountOptions);
    accountChart.render();
    
    // 5. Heatmap de horários
    const heatmapOptions = {
        series: [{
            name: 'Seg',
            data: generateHeatmapData()
        }, {
            name: 'Ter',
            data: generateHeatmapData()
        }, {
            name: 'Qua',
            data: generateHeatmapData()
        }, {
            name: 'Qui',
            data: generateHeatmapData()
        }, {
            name: 'Sex',
            data: generateHeatmapData()
        }],
        chart: {
            type: 'heatmap',
            height: 300,
            toolbar: { show: false }
        },
        dataLabels: { enabled: false },
        colors: ["#009EF7"],
        xaxis: {
            categories: ['00h', '02h', '04h', '06h', '08h', '10h', '12h', '14h', '16h', '18h', '20h', '22h']
        }
    };
    
    const heatmapChart = new ApexCharts(document.querySelector("#chart_heatmap"), heatmapOptions);
    heatmapChart.render();
    
    // 6. Timeline de atividades
    renderTimeline(data.recent_activities || []);
}

function generateHeatmapData() {
    // Gerar dados simulados - em produção viria do backend
    return Array.from({length: 12}, () => Math.floor(Math.random() * 100));
}

function renderTimeline(activities) {
    const container = document.getElementById('timeline_list');
    
    if (!activities || activities.length === 0) {
        container.innerHTML = '<div class="text-muted text-center py-5">Nenhuma atividade recente</div>';
        return;
    }
    
    container.innerHTML = activities.map(activity => `
        <div class="d-flex align-items-center mb-5">
            <div class="symbol symbol-40px me-3">
                <div class="symbol-label bg-light-${activity.type === 'sent' ? 'primary' : activity.type === 'replied' ? 'success' : 'info'}">
                    <i class="ki-duotone ki-message-text-2 fs-2 text-${activity.type === 'sent' ? 'primary' : activity.type === 'replied' ? 'success' : 'info'}"></i>
                </div>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold">${activity.description}</div>
                <div class="text-muted fs-7">${activity.time}</div>
            </div>
        </div>
    `).join('');
}

// Render Recent Campaigns Table
function renderRecentCampaigns(campaigns) {
    const tbody = document.getElementById('recent_campaigns_tbody');
    
    if (!campaigns || campaigns.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5">Nenhuma campanha encontrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = campaigns.map(camp => `
        <tr>
            <td class="ps-4">
                <a href="/campaigns/${camp.id}" class="text-gray-900 fw-bold text-hover-primary">${camp.name}</a>
            </td>
            <td><span class="badge badge-light-${getStatusColor(camp.status)}">${camp.status}</span></td>
            <td>${camp.total_sent || 0}</td>
            <td>${(camp.delivery_rate || 0).toFixed(1)}%</td>
            <td>${(camp.read_rate || 0).toFixed(1)}%</td>
            <td>${camp.total_replied || 0}</td>
            <td class="pe-4">
                <a href="/campaigns/${camp.id}" class="btn btn-sm btn-light">Ver</a>
            </td>
        </tr>
    `).join('');
}

function getStatusColor(status) {
    const colors = {
        'draft': 'secondary',
        'scheduled': 'info',
        'running': 'primary',
        'paused': 'warning',
        'completed': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function exportAllData() {
    const period = document.getElementById('period-filter').value;
    window.open(`/api/campaigns/export?period=${period}&format=csv`, '_blank');
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
});
</script>
