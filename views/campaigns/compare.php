<?php
$layout = 'layouts.metronic.app';
$title = 'Comparar Campanhas';
$pageTitle = 'Comparação';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Comparação de Campanhas
                </h1>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Seleção de Campanhas -->
            <div class="card mb-5">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Campanha A</label>
                            <select class="form-select" id="campaign_a" onchange="loadComparison()">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-center">
                            <label class="form-label">&nbsp;</label>
                            <div class="fs-2 text-muted">VS</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Campanha B</label>
                            <select class="form-select" id="campaign_b" onchange="loadComparison()">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="comparison_results" style="display: none;">
                <!-- Cards Comparativos -->
                <div class="row g-5 mb-5">
                    <div class="col-xl-6">
                        <div class="card h-100" style="border: 3px solid #009EF7;">
                            <div class="card-header" style="background: linear-gradient(135deg, #009EF7 0%, #0077B5 100%);">
                                <h3 class="card-title text-white" id="campaign_a_name">Campanha A</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-bold">Total Contatos:</span>
                                    <span id="a_total_contacts" class="badge badge-light-primary">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-bold">Enviadas:</span>
                                    <span id="a_total_sent" class="badge badge-light-primary">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-bold">Taxa Entrega:</span>
                                    <span id="a_delivery_rate" class="badge badge-light-success">0%</span>
                                </div>
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-bold">Taxa Leitura:</span>
                                    <span id="a_read_rate" class="badge badge-light-info">0%</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold">Taxa Resposta:</span>
                                    <span id="a_reply_rate" class="badge badge-light-warning">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-6">
                        <div class="card h-100" style="border: 3px solid #50CD89;">
                            <div class="card-header" style="background: linear-gradient(135deg, #50CD89 0%, #28A745 100%);">
                                <h3 class="card-title text-white" id="campaign_b_name">Campanha B</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-bold">Total Contatos:</span>
                                    <span id="b_total_contacts" class="badge badge-light-primary">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-bold">Enviadas:</span>
                                    <span id="b_total_sent" class="badge badge-light-primary">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-bold">Taxa Entrega:</span>
                                    <span id="b_delivery_rate" class="badge badge-light-success">0%</span>
                                </div>
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-bold">Taxa Leitura:</span>
                                    <span id="b_read_rate" class="badge badge-light-info">0%</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold">Taxa Resposta:</span>
                                    <span id="b_reply_rate" class="badge badge-light-warning">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico Comparativo -->
                <div class="row g-5 mb-5">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Comparação Visual</h3>
                            </div>
                            <div class="card-body">
                                <div id="chart_comparison_radar" style="height: 400px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Vencedor -->
                <div class="row g-5">
                    <div class="col-xl-12">
                        <div class="card" id="winner_card" style="display: none;">
                            <div class="card-body text-center py-10">
                                <i class="ki-duotone ki-award fs-5x text-warning mb-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <h2 class="mb-3" id="winner_text">Vencedor</h2>
                                <p class="text-muted fs-4" id="winner_reason"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
function loadCampaignsList() {
    fetch('/api/campaigns')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.campaigns) {
                const optionsA = document.getElementById('campaign_a');
                const optionsB = document.getElementById('campaign_b');
                
                data.campaigns.forEach(camp => {
                    const option = `<option value="${camp.id}">${camp.name}</option>`;
                    optionsA.insertAdjacentHTML('beforeend', option);
                    optionsB.insertAdjacentHTML('beforeend', option);
                });
            }
        });
}

function loadComparison() {
    const campaignA = document.getElementById('campaign_a').value;
    const campaignB = document.getElementById('campaign_b').value;
    
    if (!campaignA || !campaignB) return;
    
    if (campaignA === campaignB) {
        toastr.error('Selecione campanhas diferentes');
        return;
    }
    
    Promise.all([
        fetch(`/api/campaigns/${campaignA}/stats`).then(r => r.json()),
        fetch(`/api/campaigns/${campaignB}/stats`).then(r => r.json())
    ])
    .then(([dataA, dataB]) => {
        if (dataA.success && dataB.success) {
            showComparison(dataA, dataB);
        }
    })
    .catch(err => toastr.error('Erro ao carregar dados'));
}

function showComparison(dataA, dataB) {
    document.getElementById('comparison_results').style.display = 'block';
    
    // Preencher dados
    document.getElementById('campaign_a_name').textContent = dataA.campaign.name;
    document.getElementById('campaign_b_name').textContent = dataB.campaign.name;
    
    document.getElementById('a_total_contacts').textContent = dataA.stats.total_contacts;
    document.getElementById('a_total_sent').textContent = dataA.stats.total_sent;
    document.getElementById('a_delivery_rate').textContent = dataA.stats.delivery_rate.toFixed(1) + '%';
    document.getElementById('a_read_rate').textContent = dataA.stats.read_rate.toFixed(1) + '%';
    document.getElementById('a_reply_rate').textContent = dataA.stats.reply_rate.toFixed(1) + '%';
    
    document.getElementById('b_total_contacts').textContent = dataB.stats.total_contacts;
    document.getElementById('b_total_sent').textContent = dataB.stats.total_sent;
    document.getElementById('b_delivery_rate').textContent = dataB.stats.delivery_rate.toFixed(1) + '%';
    document.getElementById('b_read_rate').textContent = dataB.stats.read_rate.toFixed(1) + '%';
    document.getElementById('b_reply_rate').textContent = dataB.stats.reply_rate.toFixed(1) + '%';
    
    // Gráfico radar comparativo
    const radarOptions = {
        series: [{
            name: dataA.campaign.name,
            data: [
                dataA.stats.delivery_rate,
                dataA.stats.read_rate,
                dataA.stats.reply_rate,
                dataA.stats.progress,
                100 - dataA.stats.failure_rate
            ]
        }, {
            name: dataB.campaign.name,
            data: [
                dataB.stats.delivery_rate,
                dataB.stats.read_rate,
                dataB.stats.reply_rate,
                dataB.stats.progress,
                100 - dataB.stats.failure_rate
            ]
        }],
        chart: {
            type: 'radar',
            height: 400,
            toolbar: { show: false }
        },
        xaxis: {
            categories: ['Taxa Entrega', 'Taxa Leitura', 'Taxa Resposta', 'Progresso', 'Confiabilidade']
        },
        colors: ['#009EF7', '#50CD89'],
        markers: { size: 4 }
    };
    
    const radarChart = new ApexCharts(document.querySelector("#chart_comparison_radar"), radarOptions);
    radarChart.render();
    
    // Determinar vencedor
    const scoreA = calculateScore(dataA.stats);
    const scoreB = calculateScore(dataB.stats);
    
    const winnerCard = document.getElementById('winner_card');
    winnerCard.style.display = 'block';
    
    if (scoreA > scoreB) {
        document.getElementById('winner_text').textContent = `🏆 ${dataA.campaign.name}`;
        document.getElementById('winner_reason').textContent = 
            `Score: ${scoreA.toFixed(1)} vs ${scoreB.toFixed(1)} - Melhor taxa de resposta e engajamento`;
        winnerCard.style.borderTop = '5px solid #009EF7';
    } else if (scoreB > scoreA) {
        document.getElementById('winner_text').textContent = `🏆 ${dataB.campaign.name}`;
        document.getElementById('winner_reason').textContent = 
            `Score: ${scoreB.toFixed(1)} vs ${scoreA.toFixed(1)} - Melhor taxa de resposta e engajamento`;
        winnerCard.style.borderTop = '5px solid #50CD89';
    } else {
        document.getElementById('winner_text').textContent = 'Empate!';
        document.getElementById('winner_reason').textContent = 
            `Ambas campanhas tiveram performance similar (Score: ${scoreA.toFixed(1)})`;
        winnerCard.style.borderTop = '5px solid #FFC700';
    }
}

function calculateScore(stats) {
    // Fórmula de score ponderado
    return (
        stats.delivery_rate * 0.2 +
        stats.read_rate * 0.3 +
        stats.reply_rate * 0.5
    );
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    loadCampaignsList();
});
</script>
