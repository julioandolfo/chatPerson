<?php
$layout = 'layouts.metronic.app';
$title = 'Campanhas WhatsApp';

// Content
ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h2 class="fw-bold">Campanhas WhatsApp</h2>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= \App\Helpers\Url::to('/contact-lists') ?>" class="btn btn-sm btn-light">
                    <i class="ki-duotone ki-profile-user fs-2"></i>
                    Listas
                </a>
                <a href="<?= \App\Helpers\Url::to('/campaigns/create') ?>" class="btn btn-sm btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Nova Campanha
                </a>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        
        <!--begin::Filters-->
        <div class="mb-5">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="filter-status">
                        <option value="">Todos os Status</option>
                        <option value="draft">Rascunho</option>
                        <option value="scheduled">Agendada</option>
                        <option value="running">Em Execução</option>
                        <option value="paused">Pausada</option>
                        <option value="completed">Concluída</option>
                        <option value="cancelled">Cancelada</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" id="filter-search" placeholder="Buscar campanha...">
                </div>
            </div>
        </div>
        <!--end::Filters-->
        
        <!--begin::Table-->
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="campaigns-table">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th class="min-w-200px">Campanha</th>
                        <th class="min-w-100px">Status</th>
                        <th class="min-w-100px">Contatos</th>
                        <th class="min-w-100px">Enviadas</th>
                        <th class="min-w-100px">Taxa Entrega</th>
                        <th class="min-w-100px">Criada</th>
                        <th class="text-end min-w-70px">Ações</th>
                    </tr>
                </thead>
                <tbody id="campaigns-tbody" class="text-gray-600 fw-semibold">
                    <tr>
                        <td colspan="7" class="text-center py-10">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            <span class="ms-2">Carregando campanhas...</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!--end::Table-->
        
    </div>
</div>
<!--end::Card-->

<script>
let campaigns = [];

function loadCampaigns() {
    fetch('<?= \App\Helpers\Url::to('/api/campaigns') ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                campaigns = data.campaigns || [];
                renderCampaigns();
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            document.getElementById('campaigns-tbody').innerHTML = 
                '<tr><td colspan="7" class="text-center py-5 text-danger">Erro ao carregar campanhas</td></tr>';
        });
}

function renderCampaigns() {
    const tbody = document.getElementById('campaigns-tbody');
    
    if (campaigns.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-10">
                    <div class="text-gray-600">
                        <i class="ki-duotone ki-information fs-3x mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="fw-bold fs-5">Nenhuma campanha encontrada</div>
                        <div class="fs-7 mt-2">Crie sua primeira campanha de disparo em massa</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = campaigns.map(camp => `
        <tr>
            <td>
                <div class="d-flex flex-column">
                    <a href="<?= \App\Helpers\Url::to('/campaigns/') ?>${camp.id}" class="text-gray-800 fw-bold text-hover-primary mb-1">
                        ${escapeHtml(camp.name)}
                    </a>
                    ${camp.description ? `<span class="text-muted fs-7">${escapeHtml(camp.description)}</span>` : ''}
                </div>
            </td>
            <td>${getStatusBadge(camp.status)}</td>
            <td><span class="badge badge-light">${camp.total_contacts || 0}</span></td>
            <td><span class="badge badge-light-primary">${camp.total_sent || 0}</span></td>
            <td><span class="badge badge-light-success">${((camp.total_delivered / camp.total_sent * 100) || 0).toFixed(1)}%</span></td>
            <td>${formatDate(camp.created_at)}</td>
            <td class="text-end">
                <a href="<?= \App\Helpers\Url::to('/campaigns/') ?>${camp.id}" class="btn btn-sm btn-light btn-active-light-primary">
                    Ver
                </a>
            </td>
        </tr>
    `).join('');
}

function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge badge-light-secondary">Rascunho</span>',
        'scheduled': '<span class="badge badge-light-warning">Agendada</span>',
        'running': '<span class="badge badge-light-primary">Em Execução</span>',
        'paused': '<span class="badge badge-light-info">Pausada</span>',
        'completed': '<span class="badge badge-light-success">Concluída</span>',
        'cancelled': '<span class="badge badge-light-danger">Cancelada</span>'
    };
    return badges[status] || '<span class="badge badge-light">-</span>';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    loadCampaigns();
    
    // Auto-refresh a cada 30s se houver campanhas rodando
    setInterval(() => {
        const hasRunning = campaigns.some(c => c.status === 'running');
        if (hasRunning) {
            loadCampaigns();
        }
    }, 30000);
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../' . $layout . '.php';
?>
