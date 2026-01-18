<?php
$layout = 'layouts.metronic.app';
$title = 'Ações em Massa';
$pageTitle = 'Ações em Massa';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Ações em Massa
                </h1>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Selecione as Campanhas</h3>
                            <div class="card-toolbar">
                                <span class="badge badge-primary" id="selected_count">0 selecionadas</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-row-bordered align-middle gs-0 gy-3">
                                    <thead>
                                        <tr class="fw-bold text-muted bg-light">
                                            <th class="ps-4 w-50px">
                                                <input type="checkbox" id="select_all" onchange="toggleSelectAll(this.checked)">
                                            </th>
                                            <th>Campanha</th>
                                            <th>Status</th>
                                            <th>Enviadas</th>
                                            <th>Taxa Resposta</th>
                                        </tr>
                                    </thead>
                                    <tbody id="campaigns_tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-5">
                        <div class="card-header">
                            <h3 class="card-title">Ações Disponíveis</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <button class="btn btn-success w-100" onclick="bulkAction('start')">
                                        <i class="ki-duotone ki-play fs-3"></i>
                                        Iniciar Selecionadas
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-warning w-100" onclick="bulkAction('pause')">
                                        <i class="ki-duotone ki-pause fs-3"></i>
                                        Pausar Selecionadas
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-info w-100" onclick="bulkAction('export')">
                                        <i class="ki-duotone ki-file-down fs-3"></i>
                                        Exportar Selecionadas
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-danger w-100" onclick="bulkAction('delete')">
                                        <i class="ki-duotone ki-trash fs-3"></i>
                                        Deletar Selecionadas
                                    </button>
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
let allCampaigns = [];
let selectedCampaigns = new Set();

function loadCampaignsList() {
    fetch('/api/campaigns')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allCampaigns = data.campaigns || [];
                renderCampaignsList();
            }
        });
}

function renderCampaignsList() {
    const tbody = document.getElementById('campaigns_tbody');
    
    tbody.innerHTML = allCampaigns.map(camp => `
        <tr>
            <td class="ps-4">
                <input type="checkbox" class="campaign-checkbox" value="${camp.id}" 
                       onchange="toggleSelection(${camp.id}, this.checked)">
            </td>
            <td>
                <div class="fw-bold">${camp.name}</div>
                ${camp.description ? `<div class="text-muted fs-7">${camp.description}</div>` : ''}
            </td>
            <td>${getStatusBadge(camp.status)}</td>
            <td>${camp.total_sent || 0}</td>
            <td>${(camp.stats?.reply_rate || 0).toFixed(1)}%</td>
        </tr>
    `).join('');
}

function toggleSelectAll(checked) {
    document.querySelectorAll('.campaign-checkbox').forEach(checkbox => {
        checkbox.checked = checked;
        toggleSelection(parseInt(checkbox.value), checked);
    });
}

function toggleSelection(campaignId, selected) {
    if (selected) {
        selectedCampaigns.add(campaignId);
    } else {
        selectedCampaigns.delete(campaignId);
    }
    
    document.getElementById('selected_count').textContent = `${selectedCampaigns.size} selecionadas`;
}

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge badge-light-secondary">Rascunho</span>',
        'running': '<span class="badge badge-light-primary">Em Execução</span>',
        'completed': '<span class="badge badge-light-success">Concluída</span>'
    };
    return badges[status] || '<span class="badge badge-light">-</span>';
}

function bulkAction(action) {
    if (selectedCampaigns.size === 0) {
        toastr.error('Selecione pelo menos 1 campanha');
        return;
    }
    
    const ids = Array.from(selectedCampaigns);
    
    if (action === 'delete') {
        if (!confirm(`Deseja deletar ${ids.length} campanhas? Esta ação não pode ser desfeita.`)) return;
    }
    
    if (action === 'export') {
        window.open(`/api/campaigns/export?ids=${ids.join(',')}`, '_blank');
        return;
    }
    
    // Processar ações
    Promise.all(ids.map(id => 
        fetch(`/campaigns/${id}/${action}`, { method: 'POST' })
    ))
    .then(() => {
        toastr.success(`Ação "${action}" executada em ${ids.length} campanhas`);
        setTimeout(() => location.reload(), 2000);
    })
    .catch(err => toastr.error('Erro ao executar ação'));
}

document.addEventListener('DOMContentLoaded', () => {
    loadCampaignsList();
});
</script>
