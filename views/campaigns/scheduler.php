<?php
$layout = 'layouts.metronic.app';
$title = 'Agendador de Campanhas';
$pageTitle = 'Agendador';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Agendador de Campanhas
                </h1>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Calendário -->
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">Calendário de Campanhas</h3>
                    <div class="card-toolbar">
                        <button class="btn btn-sm btn-primary" onclick="showScheduleModal()">
                            <i class="ki-duotone ki-calendar-add fs-3"></i>
                            Agendar Nova
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="calendar_container"></div>
                </div>
            </div>
            
            <!-- Campanhas Agendadas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Campanhas Agendadas</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th class="ps-4">Campanha</th>
                                    <th>Agendada para</th>
                                    <th>Contatos</th>
                                    <th>Status</th>
                                    <th class="pe-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="scheduled_campaigns_tbody">
                                <tr><td colspan="5" class="text-center py-5">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Calendário simples (em produção usar FullCalendar)
function renderCalendar() {
    const container = document.getElementById('calendar_container');
    
    // Carregar campanhas agendadas
    fetch('/api/campaigns?status=scheduled')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.campaigns) {
                renderScheduledList(data.campaigns);
                
                // Simulação de calendário
                container.innerHTML = `
                    <div class="alert alert-info">
                        <strong>Calendário Visual:</strong> Integração com FullCalendar será adicionada em breve.
                        Por enquanto, veja a lista de campanhas agendadas abaixo.
                    </div>
                `;
            }
        });
}

function renderScheduledList(campaigns) {
    const tbody = document.getElementById('scheduled_campaigns_tbody');
    
    if (campaigns.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5">Nenhuma campanha agendada</td></tr>';
        return;
    }
    
    tbody.innerHTML = campaigns.map(camp => `
        <tr>
            <td class="ps-4">
                <div class="fw-bold">${camp.name}</div>
                ${camp.description ? `<div class="text-muted fs-7">${camp.description}</div>` : ''}
            </td>
            <td>
                <div class="fw-bold">${formatDateTime(camp.scheduled_at)}</div>
                <div class="text-muted fs-7">${timeUntil(camp.scheduled_at)}</div>
            </td>
            <td><span class="badge badge-light-info">${camp.total_contacts}</span></td>
            <td><span class="badge badge-light-warning">Agendada</span></td>
            <td class="pe-4">
                <a href="/campaigns/${camp.id}" class="btn btn-sm btn-light">Ver</a>
            </td>
        </tr>
    `).join('');
}

function formatDateTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function timeUntil(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = date - now;
    
    if (diff < 0) return 'Atrasada';
    
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(hours / 24);
    
    if (days > 0) return `em ${days} dia${days > 1 ? 's' : ''}`;
    if (hours > 0) return `em ${hours} hora${hours > 1 ? 's' : ''}`;
    return 'em breve';
}

function showScheduleModal() {
    window.location.href = '/campaigns/create';
}

document.addEventListener('DOMContentLoaded', () => {
    renderCalendar();
});
</script>
