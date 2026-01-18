<?php
$layout = 'layouts.metronic.app';
$title = 'Campanhas WhatsApp';
$pageTitle = 'Campanhas';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Campanhas WhatsApp
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">Campanhas</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2 gap-lg-3">
                <div class="btn-group">
                    <a href="/campaigns/create" class="btn btn-sm btn-primary">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Nova Campanha
                    </a>
                    <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/campaigns/create"><i class="ki-duotone ki-message-text-2 fs-6 me-2"></i>Campanha Normal</a></li>
                        <li><a class="dropdown-item" href="/campaigns/ab-test"><i class="ki-duotone ki-chart-line-up-2 fs-6 me-2"></i>Teste A/B</a></li>
                        <li><a class="dropdown-item" href="/drip-sequences/create"><i class="ki-duotone ki-abstract-26 fs-6 me-2"></i>Sequência Drip</a></li>
                    </ul>
                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="ki-duotone ki-chart-line fs-2"></i>
                        Analytics
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/campaigns/analytics"><i class="ki-duotone ki-chart-simple fs-6 me-2"></i>Analytics Avançado</a></li>
                        <li><a class="dropdown-item" href="/campaigns/compare"><i class="ki-duotone ki-graph-up fs-6 me-2"></i>Comparar Campanhas</a></li>
                        <li><a class="dropdown-item" href="/campaigns/realtime"><i class="ki-duotone ki-pulse fs-6 me-2"></i>Tempo Real</a></li>
                    </ul>
                </div>
                
                <a href="/contact-lists" class="btn btn-sm btn-light">
                    <i class="ki-duotone ki-profile-user fs-2"></i>
                    Listas
                </a>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Filtros -->
            <div class="card card-flush mb-5">
                <div class="card-body py-5">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select form-select-sm" id="filter-status" onchange="filterCampaigns()">
                                <option value="">Todos</option>
                                <option value="draft">Rascunho</option>
                                <option value="scheduled">Agendada</option>
                                <option value="running">Em Execução</option>
                                <option value="paused">Pausada</option>
                                <option value="completed">Concluída</option>
                                <option value="cancelled">Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipo</label>
                            <select class="form-select form-select-sm" id="filter-type" onchange="filterCampaigns()">
                                <option value="">Todos</option>
                                <option value="normal">Normal</option>
                                <option value="ab_test">A/B Test</option>
                                <option value="drip">Drip</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ordenar</label>
                            <select class="form-select form-select-sm" id="filter-sort" onchange="filterCampaigns()">
                                <option value="created_desc">Mais Recentes</option>
                                <option value="created_asc">Mais Antigas</option>
                                <option value="reply_rate">Taxa Resposta</option>
                                <option value="delivery_rate">Taxa Entrega</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control form-control-sm" id="filter-search" 
                                   placeholder="Nome da campanha..." onkeyup="filterCampaigns()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-light-primary flex-grow-1" onclick="resetFilters()">
                                    <i class="ki-duotone ki-filter-search fs-3"></i>
                                    Limpar
                                </button>
                                <button class="btn btn-sm btn-light-success" onclick="exportFiltered()">
                                    <i class="ki-duotone ki-file-down fs-3"></i>
                                    Exportar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lista de Campanhas -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-300 align-middle gs-0 gy-3" id="campaigns-table">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th class="ps-4 min-w-200px">Campanha</th>
                                    <th class="min-w-100px">Status</th>
                                    <th class="min-w-150px">Progresso</th>
                                    <th class="min-w-100px">Contatos</th>
                                    <th class="min-w-100px">Enviadas</th>
                                    <th class="min-w-100px">Taxa Entrega</th>
                                    <th class="min-w-100px">Taxa Leitura</th>
                                    <th class="min-w-100px">Respostas</th>
                                    <th class="min-w-100px">Criada</th>
                                    <th class="text-end pe-4 min-w-100px">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="campaigns-tbody">
                                <tr>
                                    <td colspan="10" class="text-center py-10">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Carregando...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
let campaigns = <?php echo json_encode($campaigns ?? []); ?>;

function loadCampaigns() {
    const tbody = document.getElementById('campaigns-tbody');
    
    if (!campaigns || campaigns.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-10">
                    <div class="text-gray-600">
                        <i class="ki-duotone ki-information fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="fw-bold fs-5">Nenhuma campanha encontrada</div>
                        <div class="fs-7 mt-2">Crie sua primeira campanha para começar</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = campaigns.map(camp => {
        const progress = parseFloat(camp.progress || 0);
        const statusBadge = getStatusBadge(camp.status);
        const deliveryRate = parseFloat(camp.stats?.delivery_rate || 0);
        const readRate = parseFloat(camp.stats?.read_rate || 0);
        
        return `
            <tr>
                <td class="ps-4">
                    <div class="d-flex align-items-center">
                        <div class="d-flex flex-column">
                            <a href="/campaigns/${camp.id}" class="text-gray-900 fw-bold text-hover-primary mb-1 fs-6">
                                ${escapeHtml(camp.name)}
                            </a>
                            ${camp.description ? `<span class="text-muted fw-semibold d-block fs-7">${escapeHtml(camp.description)}</span>` : ''}
                        </div>
                    </div>
                </td>
                <td>${statusBadge}</td>
                <td>
                    <div class="d-flex flex-column w-100">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted fs-7">${progress.toFixed(1)}%</span>
                        </div>
                        <div class="progress h-6px w-100">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: ${progress}%"></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-light-info">${camp.total_contacts || 0}</span></td>
                <td><span class="badge badge-light-primary">${camp.total_sent || 0}</span></td>
                <td><span class="badge badge-light-success">${deliveryRate.toFixed(1)}%</span></td>
                <td><span class="badge badge-light-warning">${readRate.toFixed(1)}%</span></td>
                <td><span class="badge badge-light-dark">${camp.total_replied || 0}</span></td>
                <td class="text-muted fs-7">${formatDate(camp.created_at)}</td>
                <td class="text-end pe-4">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light btn-active-light-primary" type="button" data-bs-toggle="dropdown">
                            Ações
                            <i class="ki-duotone ki-down fs-5 ms-1"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/campaigns/${camp.id}"><i class="ki-duotone ki-eye fs-6 me-2"></i>Visualizar</a></li>
                            ${camp.status === 'draft' ? `<li><a class="dropdown-item" href="/campaigns/${camp.id}/edit"><i class="ki-duotone ki-pencil fs-6 me-2"></i>Editar</a></li>` : ''}
                            ${camp.status === 'draft' || camp.status === 'scheduled' ? `<li><a class="dropdown-item" href="#" onclick="startCampaign(${camp.id})"><i class="ki-duotone ki-play fs-6 me-2"></i>Iniciar</a></li>` : ''}
                            ${camp.status === 'running' ? `<li><a class="dropdown-item" href="#" onclick="pauseCampaign(${camp.id})"><i class="ki-duotone ki-pause fs-6 me-2"></i>Pausar</a></li>` : ''}
                            ${camp.status === 'paused' ? `<li><a class="dropdown-item" href="#" onclick="resumeCampaign(${camp.id})"><i class="ki-duotone ki-play fs-6 me-2"></i>Retomar</a></li>` : ''}
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteCampaign(${camp.id})"><i class="ki-duotone ki-trash fs-6 me-2"></i>Deletar</a></li>
                        </ul>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge badge-light-secondary">Rascunho</span>',
        'scheduled': '<span class="badge badge-light-info">Agendada</span>',
        'running': '<span class="badge badge-light-primary"><span class="spinner-border spinner-border-sm me-1"></span>Em Execução</span>',
        'paused': '<span class="badge badge-light-warning">Pausada</span>',
        'completed': '<span class="badge badge-light-success">Concluída</span>',
        'cancelled': '<span class="badge badge-light-danger">Cancelada</span>'
    };
    return badges[status] || '<span class="badge badge-light">Desconhecido</span>';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function startCampaign(id) {
    if (!confirm('Deseja iniciar esta campanha?')) return;
    
    fetch(`/campaigns/${id}/start`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha iniciada com sucesso!');
                location.reload();
            } else {
                toastr.error(data.message || 'Erro ao iniciar campanha');
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function pauseCampaign(id) {
    if (!confirm('Deseja pausar esta campanha?')) return;
    
    fetch(`/campaigns/${id}/pause`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha pausada!');
                location.reload();
            } else {
                toastr.error(data.message || 'Erro ao pausar campanha');
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function resumeCampaign(id) {
    if (!confirm('Deseja retomar esta campanha?')) return;
    
    fetch(`/campaigns/${id}/resume`, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha retomada!');
                location.reload();
            } else {
                toastr.error(data.message || 'Erro ao retomar campanha');
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

function deleteCampaign(id) {
    if (!confirm('Deseja deletar esta campanha? Esta ação não pode ser desfeita.')) return;
    
    fetch(`/campaigns/${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Campanha deletada!');
                location.reload();
            } else {
                toastr.error(data.message || 'Erro ao deletar campanha');
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}

let originalCampaigns = campaigns;

function filterCampaigns() {
    const status = document.getElementById('filter-status').value;
    const type = document.getElementById('filter-type').value;
    const sort = document.getElementById('filter-sort').value;
    const search = document.getElementById('filter-search').value.toLowerCase();
    
    let filtered = [...originalCampaigns];
    
    // Filtrar por status
    if (status) {
        filtered = filtered.filter(c => c.status === status);
    }
    
    // Filtrar por tipo
    if (type) {
        if (type === 'ab_test') {
            filtered = filtered.filter(c => c.is_ab_test);
        } else if (type === 'drip') {
            filtered = filtered.filter(c => c.is_drip_campaign);
        } else {
            filtered = filtered.filter(c => !c.is_ab_test && !c.is_drip_campaign);
        }
    }
    
    // Buscar
    if (search) {
        filtered = filtered.filter(c => 
            c.name.toLowerCase().includes(search) || 
            (c.description && c.description.toLowerCase().includes(search))
        );
    }
    
    // Ordenar
    if (sort === 'created_desc') {
        filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    } else if (sort === 'created_asc') {
        filtered.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    } else if (sort === 'reply_rate') {
        filtered.sort((a, b) => (b.stats?.reply_rate || 0) - (a.stats?.reply_rate || 0));
    } else if (sort === 'delivery_rate') {
        filtered.sort((a, b) => (b.stats?.delivery_rate || 0) - (a.stats?.delivery_rate || 0));
    }
    
    campaigns = filtered;
    loadCampaigns();
}

function resetFilters() {
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-type').value = '';
    document.getElementById('filter-sort').value = 'created_desc';
    document.getElementById('filter-search').value = '';
    campaigns = originalCampaigns;
    loadCampaigns();
}

function exportFiltered() {
    window.open('/api/campaigns/export', '_blank');
}

// Auto-refresh a cada 30 segundos se houver campanhas rodando
setInterval(() => {
    const hasRunning = originalCampaigns.some(c => c.status === 'running');
    if (hasRunning) {
        location.reload();
    }
}, 30000);

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    loadCampaigns();
});
</script>
