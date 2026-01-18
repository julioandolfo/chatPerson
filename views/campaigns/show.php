<?php
$layout = 'layouts.metronic.app';
$title = $campaign['name'] ?? 'Campanha';
$pageTitle = 'Detalhes da Campanha';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    <?php echo htmlspecialchars($campaign['name']); ?>
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
                    <li class="breadcrumb-item text-muted">#<?php echo $campaign['id']; ?></li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="/campaigns/<?php echo $campaign['id']; ?>/export" target="_blank" class="btn btn-sm btn-light">
                    <i class="ki-duotone ki-file-down fs-3"></i>
                    Exportar Relatório
                </a>
                
                <?php if ($campaign['status'] === 'draft' || $campaign['status'] === 'scheduled'): ?>
                <button class="btn btn-sm btn-success" onclick="prepareCampaign()">
                    <i class="ki-duotone ki-verify fs-3"></i>
                    Preparar
                </button>
                <button class="btn btn-sm btn-primary" onclick="startCampaign()">
                    <i class="ki-duotone ki-play fs-3"></i>
                    Iniciar
                </button>
                <?php endif; ?>
                
                <?php if ($campaign['status'] === 'running'): ?>
                <button class="btn btn-sm btn-warning" onclick="pauseCampaign()">
                    <i class="ki-duotone ki-pause fs-3"></i>
                    Pausar
                </button>
                <?php endif; ?>
                
                <?php if ($campaign['status'] === 'paused'): ?>
                <button class="btn btn-sm btn-primary" onclick="resumeCampaign()">
                    <i class="ki-duotone ki-play fs-3"></i>
                    Retomar
                </button>
                <?php endif; ?>
                
                <button class="btn btn-sm btn-light" onclick="location.reload()">
                    <i class="ki-duotone ki-arrows-circle fs-3"></i>
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Status e Progress -->
            <div class="row g-5 g-xl-8 mb-5">
                <div class="col-xl-12">
                    <div class="card card-flush">
                        <div class="card-body d-flex align-items-center py-5">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="fs-4 fw-bold me-3">Status:</span>
                                    <?php 
                                    $statusBadges = [
                                        'draft' => '<span class="badge badge-light-secondary fs-5">Rascunho</span>',
                                        'scheduled' => '<span class="badge badge-light-info fs-5">Agendada</span>',
                                        'running' => '<span class="badge badge-light-primary fs-5"><span class="spinner-border spinner-border-sm me-2"></span>Em Execução</span>',
                                        'paused' => '<span class="badge badge-light-warning fs-5">Pausada</span>',
                                        'completed' => '<span class="badge badge-light-success fs-5">Concluída</span>',
                                        'cancelled' => '<span class="badge badge-light-danger fs-5">Cancelada</span>'
                                    ];
                                    echo $statusBadges[$campaign['status']] ?? '';
                                    ?>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="fs-6 text-muted me-3">Progresso:</span>
                                    <div class="progress h-8px w-300px me-3">
                                        <div class="progress-bar bg-primary" role="progressbar" 
                                             style="width: <?php echo $stats['progress']; ?>%"></div>
                                    </div>
                                    <span class="fw-bold fs-6"><?php echo number_format($stats['progress'], 1); ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Cards de Estatísticas -->
            <div class="row g-5 g-xl-8 mb-5">
                <!-- Total Contatos -->
                <div class="col-xl-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" 
                         style="background-color: #F1416C;background-image:url('assets/media/patterns/vector-1.png')">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?php echo $stats['total_contacts']; ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Total de Contatos</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Progresso</span>
                                    <span><?php echo number_format($stats['progress'], 1); ?>%</span>
                                </div>
                                <div class="h-8px mx-3 w-100 bg-white bg-opacity-50 rounded">
                                    <div class="bg-white rounded h-8px" role="progressbar" 
                                         style="width: <?php echo $stats['progress']; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Enviadas -->
                <div class="col-xl-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" 
                         style="background-color: #7239EA;background-image:url('assets/media/patterns/vector-1.png')">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?php echo $stats['total_sent']; ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Mensagens Enviadas</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Taxa</span>
                                    <span><?php echo $stats['total_contacts'] > 0 ? number_format(($stats['total_sent'] / $stats['total_contacts']) * 100, 1) : 0; ?>%</span>
                                </div>
                                <div class="h-8px mx-3 w-100 bg-white bg-opacity-50 rounded">
                                    <div class="bg-white rounded h-8px" role="progressbar" 
                                         style="width: <?php echo $stats['total_contacts'] > 0 ? ($stats['total_sent'] / $stats['total_contacts']) * 100 : 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Entregues -->
                <div class="col-xl-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" 
                         style="background-color: #50CD89;background-image:url('assets/media/patterns/vector-1.png')">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?php echo $stats['total_delivered']; ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Entregues</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Taxa</span>
                                    <span><?php echo number_format($stats['delivery_rate'], 1); ?>%</span>
                                </div>
                                <div class="h-8px mx-3 w-100 bg-white bg-opacity-50 rounded">
                                    <div class="bg-white rounded h-8px" role="progressbar" 
                                         style="width: <?php echo $stats['delivery_rate']; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Respondidas -->
                <div class="col-xl-3">
                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" 
                         style="background-color: #009EF7;background-image:url('assets/media/patterns/vector-1.png')">
                        <div class="card-header pt-5">
                            <div class="card-title d-flex flex-column">
                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?php echo $stats['total_replied']; ?></span>
                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Respostas</span>
                            </div>
                        </div>
                        <div class="card-body d-flex align-items-end pt-0">
                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                    <span>Taxa</span>
                                    <span><?php echo number_format($stats['reply_rate'], 1); ?>%</span>
                                </div>
                                <div class="h-8px mx-3 w-100 bg-white bg-opacity-50 rounded">
                                    <div class="bg-white rounded h-8px" role="progressbar" 
                                         style="width: <?php echo min($stats['reply_rate'], 100); ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Funil -->
            <div class="row g-5 mb-5">
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Funil de Conversão</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="funnel_chart" style="height: 350px;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-6">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800">Detalhes da Campanha</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="mb-7">
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Descrição:</span>
                                    <span class="text-gray-800"><?php echo htmlspecialchars($campaign['description'] ?? '-'); ?></span>
                                </div>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Canal:</span>
                                    <span class="badge badge-light-primary"><?php echo strtoupper($campaign['channel']); ?></span>
                                </div>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Estratégia de Rotação:</span>
                                    <span class="text-gray-800"><?php echo ucfirst(str_replace('_', ' ', $campaign['rotation_strategy'])); ?></span>
                                </div>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Cadência:</span>
                                    <span class="text-gray-800"><?php echo $campaign['send_rate_per_minute']; ?> msgs/min</span>
                                </div>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Criada em:</span>
                                    <span class="text-gray-800"><?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?></span>
                                </div>
                                <?php if ($campaign['started_at']): ?>
                                <div class="d-flex flex-stack mb-3">
                                    <span class="fw-bold text-gray-600">Iniciada em:</span>
                                    <span class="text-gray-800"><?php echo date('d/m/Y H:i', strtotime($campaign['started_at'])); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
const campaignId = <?php echo $campaign['id']; ?>;
const stats = <?php echo json_encode($stats); ?>;

// Funnel Chart
var funnelOptions = {
    series: [{
        name: 'Quantidade',
        data: [
            stats.total_contacts,
            stats.total_sent,
            stats.total_delivered,
            stats.total_read,
            stats.total_replied
        ]
    }],
    chart: {
        type: 'bar',
        height: 350
    },
    plotOptions: {
        bar: {
            borderRadius: 0,
            horizontal: true,
            barHeight: '80%',
            isFunnel: true
        }
    },
    dataLabels: {
        enabled: true,
        formatter: function(val, opt) {
            return opt.w.globals.labels[opt.dataPointIndex] + ':  ' + val;
        },
        dropShadow: {
            enabled: true
        }
    },
    xaxis: {
        categories: ['Total Contatos', 'Enviadas', 'Entregues', 'Lidas', 'Respondidas']
    },
    legend: {
        show: false
    },
    colors: ['#F1416C', '#7239EA', '#50CD89', '#FFC700', '#009EF7']
};

var funnelChart = new ApexCharts(document.querySelector("#funnel_chart"), funnelOptions);
funnelChart.render();

// Funções de controle
function prepareCampaign() {
    if (!confirm('Deseja preparar esta campanha? Isso irá criar as mensagens individuais.')) return;
    
    fetch(`/campaigns/${campaignId}/prepare`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success(data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function startCampaign() {
    if (!confirm('Deseja iniciar esta campanha?')) return;
    
    fetch(`/campaigns/${campaignId}/start`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha iniciada!');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function pauseCampaign() {
    if (!confirm('Deseja pausar esta campanha?')) return;
    
    fetch(`/campaigns/${campaignId}/pause`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha pausada!');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function resumeCampaign() {
    if (!confirm('Deseja retomar esta campanha?')) return;
    
    fetch(`/campaigns/${campaignId}/resume`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha retomada!');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

// Auto-refresh se estiver rodando
<?php if ($campaign['status'] === 'running'): ?>
setInterval(() => location.reload(), 30000); // 30 segundos
<?php endif; ?>
</script>
