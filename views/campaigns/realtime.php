<?php
$layout = 'layouts.metronic.app';
$title = 'Monitoramento em Tempo Real';
$pageTitle = 'Tempo Real';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Monitoramento em Tempo Real
                </h1>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="badge badge-light-success pulse pulse-success me-3">
                    <span class="position-absolute start-0 top-0 w-100 h-100 pulse-ring"></span>
                    <span class="pulse-ring"></span>
                    LIVE
                </div>
                <span class="text-muted fs-7" id="last_update">Atualizando...</span>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Campanhas Ativas -->
            <div class="row g-5 mb-5">
                <div class="col-xl-12">
                    <div class="card card-flush">
                        <div class="card-header">
                            <h3 class="card-title">Campanhas em Execução</h3>
                            <div class="card-toolbar">
                                <span class="badge badge-primary" id="active_count">0</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="active_campaigns_container"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stream de Eventos -->
            <div class="row g-5 mb-5">
                <div class="col-xl-8">
                    <div class="card card-flush" style="height: 600px;">
                        <div class="card-header">
                            <h3 class="card-title">Stream de Eventos</h3>
                            <div class="card-toolbar">
                                <button class="btn btn-sm btn-light" onclick="clearStream()">
                                    <i class="ki-duotone ki-trash fs-6"></i>
                                    Limpar
                                </button>
                            </div>
                        </div>
                        <div class="card-body" style="overflow-y: auto; max-height: 550px;">
                            <div id="event_stream" class="timeline timeline-border-dashed"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4">
                    <div class="card card-flush" style="height: 600px;">
                        <div class="card-header">
                            <h3 class="card-title">Métricas Ao Vivo</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-7">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <div class="symbol-label bg-light-primary">
                                            <i class="ki-duotone ki-send fs-2x text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-600 fs-7">Enviadas (último min)</span>
                                        <div class="fw-bold fs-2" id="live_sent">0</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-7">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <div class="symbol-label bg-light-success">
                                            <i class="ki-duotone ki-verify fs-2x text-success"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-600 fs-7">Entregues (último min)</span>
                                        <div class="fw-bold fs-2" id="live_delivered">0</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-7">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <div class="symbol-label bg-light-info">
                                            <i class="ki-duotone ki-message-text-2 fs-2x text-info"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <span class="text-gray-600 fs-7">Respostas (último min)</span>
                                        <div class="fw-bold fs-2" id="live_replied">0</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="separator my-5"></div>
                            
                            <div class="mb-5">
                                <span class="text-gray-600 fs-7">Taxa Média de Resposta</span>
                                <div class="d-flex align-items-center">
                                    <div class="progress h-8px w-100 me-3">
                                        <div class="progress-bar bg-success" id="avg_reply_bar" style="width: 0%"></div>
                                    </div>
                                    <span class="fw-bold" id="avg_reply_rate">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
class RealtimeMonitor {
    constructor() {
        this.lastTimestamp = Date.now();
        this.eventCount = 0;
        this.start();
    }
    
    start() {
        this.fetchActiveCampaigns();
        this.fetchEvents();
        
        // Atualizar a cada 5 segundos
        setInterval(() => {
            this.fetchActiveCampaigns();
            this.fetchEvents();
        }, 5000);
    }
    
    fetchActiveCampaigns() {
        fetch('/api/campaigns?status=running')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.renderActiveCampaigns(data.campaigns || []);
                    document.getElementById('active_count').textContent = data.campaigns?.length || 0;
                }
            });
    }
    
    renderActiveCampaigns(campaigns) {
        const container = document.getElementById('active_campaigns_container');
        
        if (campaigns.length === 0) {
            container.innerHTML = '<div class="text-center py-10 text-muted">Nenhuma campanha em execução</div>';
            return;
        }
        
        container.innerHTML = campaigns.map(camp => {
            const progress = camp.progress || 0;
            return `
                <div class="d-flex align-items-center border-bottom p-5">
                    <div class="flex-grow-1">
                        <a href="/campaigns/${camp.id}" class="fw-bold text-gray-900 text-hover-primary mb-1">
                            ${camp.name}
                        </a>
                        <div class="progress h-6px mt-2">
                            <div class="progress-bar bg-primary" style="width: ${progress}%"></div>
                        </div>
                    </div>
                    <div class="ms-5 text-end">
                        <div class="fw-bold fs-5">${progress.toFixed(1)}%</div>
                        <div class="text-muted fs-7">${camp.total_sent || 0} / ${camp.total_contacts || 0}</div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    fetchEvents() {
        fetch(`/api/campaigns/events?since=${this.lastTimestamp}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.events) {
                    data.events.forEach(event => this.addEvent(event));
                    this.updateLiveMetrics(data.metrics || {});
                }
                
                document.getElementById('last_update').textContent = 
                    'Atualizado: ' + new Date().toLocaleTimeString('pt-BR');
                    
                this.lastTimestamp = Date.now();
            });
    }
    
    addEvent(event) {
        const stream = document.getElementById('event_stream');
        const icon = this.getEventIcon(event.type);
        const color = this.getEventColor(event.type);
        
        const html = `
            <div class="timeline-item mb-5">
                <div class="timeline-line w-40px"></div>
                <div class="timeline-icon symbol symbol-circle symbol-40px">
                    <div class="symbol-label bg-light-${color}">
                        <i class="ki-duotone ki-${icon} fs-2 text-${color}"></i>
                    </div>
                </div>
                <div class="timeline-content mb-5 mt-n1">
                    <div class="pe-3 mb-2">
                        <div class="fs-6 fw-bold mb-1">${event.message}</div>
                        <div class="text-muted fs-7">${new Date(event.timestamp).toLocaleTimeString('pt-BR')}</div>
                    </div>
                </div>
            </div>
        `;
        
        stream.insertAdjacentHTML('afterbegin', html);
        this.eventCount++;
        
        // Limitar a 50 eventos
        if (this.eventCount > 50) {
            stream.lastElementChild?.remove();
            this.eventCount--;
        }
    }
    
    updateLiveMetrics(metrics) {
        document.getElementById('live_sent').textContent = metrics.sent_last_minute || 0;
        document.getElementById('live_delivered').textContent = metrics.delivered_last_minute || 0;
        document.getElementById('live_replied').textContent = metrics.replied_last_minute || 0;
        
        const avgReply = metrics.avg_reply_rate || 0;
        document.getElementById('avg_reply_rate').textContent = avgReply.toFixed(1) + '%';
        document.getElementById('avg_reply_bar').style.width = avgReply + '%';
    }
    
    getEventIcon(type) {
        const icons = {
            'sent': 'send',
            'delivered': 'verify',
            'read': 'eye',
            'replied': 'message-text-2',
            'failed': 'cross-circle'
        };
        return icons[type] || 'information';
    }
    
    getEventColor(type) {
        const colors = {
            'sent': 'primary',
            'delivered': 'success',
            'read': 'info',
            'replied': 'warning',
            'failed': 'danger'
        };
        return colors[type] || 'secondary';
    }
}

function clearStream() {
    document.getElementById('event_stream').innerHTML = '';
}

// Inicializar
let monitor;
document.addEventListener('DOMContentLoaded', () => {
    monitor = new RealtimeMonitor();
});
</script>

<style>
.pulse {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(80, 205, 137, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(80, 205, 137, 0); }
    100% { box-shadow: 0 0 0 0 rgba(80, 205, 137, 0); }
}
</style>
