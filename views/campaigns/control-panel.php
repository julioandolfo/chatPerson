<?php
$layout = 'layouts.metronic.app';
$title = 'Painel de Controle - Campanhas';
$pageTitle = 'Painel de Controle';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Painel de Controle Central
                </h1>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <!-- Navegação Rápida -->
            <div class="row g-5 mb-5">
                <div class="col-xl-12">
                    <div class="card card-flush">
                        <div class="card-body py-5">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <a href="/campaigns/create" class="btn btn-flex btn-light-primary w-100 h-100 flex-column p-5">
                                        <i class="ki-duotone ki-send fs-3x mb-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="fw-bold fs-4">Nova Campanha</span>
                                        <span class="text-muted fs-7 mt-1">Disparo simples</span>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="/campaigns/ab-test" class="btn btn-flex btn-light-success w-100 h-100 flex-column p-5">
                                        <i class="ki-duotone ki-chart-line-up-2 fs-3x mb-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="fw-bold fs-4">Teste A/B</span>
                                        <span class="text-muted fs-7 mt-1">Compare versões</span>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="/drip-sequences/create" class="btn btn-flex btn-light-warning w-100 h-100 flex-column p-5">
                                        <i class="ki-duotone ki-abstract-26 fs-3x mb-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="fw-bold fs-4">Sequência Drip</span>
                                        <span class="text-muted fs-7 mt-1">Múltiplas etapas</span>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="/contact-lists/create" class="btn btn-flex btn-light-info w-100 h-100 flex-column p-5">
                                        <i class="ki-duotone ki-profile-user fs-3x mb-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="fw-bold fs-4">Nova Lista</span>
                                        <span class="text-muted fs-7 mt-1">Gerenciar contatos</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status Atual -->
            <div class="row g-5 mb-5">
                <div class="col-xl-3">
                    <div class="card card-flush">
                        <div class="card-body d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, #009EF7 0%, #0077B5 100%);">
                            <div class="text-white opacity-75 fw-semibold fs-6 mb-3">Em Execução</div>
                            <div class="text-white fw-bold fs-2hx" id="status_running">0</div>
                            <a href="/campaigns?status=running" class="text-white opacity-75 text-hover-white fs-7 mt-3">Ver campanhas →</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3">
                    <div class="card card-flush">
                        <div class="card-body d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, #FFC700 0%, #F59E0B 100%);">
                            <div class="text-white opacity-75 fw-semibold fs-6 mb-3">Agendadas</div>
                            <div class="text-white fw-bold fs-2hx" id="status_scheduled">0</div>
                            <a href="/campaigns?status=scheduled" class="text-white opacity-75 text-hover-white fs-7 mt-3">Ver campanhas →</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3">
                    <div class="card card-flush">
                        <div class="card-body d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, #50CD89 0%, #28A745 100%);">
                            <div class="text-white opacity-75 fw-semibold fs-6 mb-3">Concluídas (30d)</div>
                            <div class="text-white fw-bold fs-2hx" id="status_completed">0</div>
                            <a href="/campaigns?status=completed" class="text-white opacity-75 text-hover-white fs-7 mt-3">Ver campanhas →</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3">
                    <div class="card card-flush">
                        <div class="card-body d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, #E4E6EF 0%, #B5B5C3 100%);">
                            <div class="text-gray-700 fw-semibold fs-6 mb-3">Rascunhos</div>
                            <div class="text-gray-900 fw-bold fs-2hx" id="status_draft">0</div>
                            <a href="/campaigns?status=draft" class="text-gray-700 text-hover-primary fs-7 mt-3">Ver campanhas →</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ações Rápidas -->
            <div class="row g-5">
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Ações Rápidas</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-5">
                                <div class="col-md-6">
                                    <a href="/campaigns/realtime" class="card card-flush bg-light-primary hover-elevate-up h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="ki-duotone ki-pulse fs-3x text-primary me-5"></i>
                                            <div>
                                                <h4 class="mb-1">Monitor em Tempo Real</h4>
                                                <p class="text-muted mb-0 fs-7">Veja envios ao vivo</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="/campaigns/analytics" class="card card-flush bg-light-success hover-elevate-up h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="ki-duotone ki-chart-simple fs-3x text-success me-5"></i>
                                            <div>
                                                <h4 class="mb-1">Analytics Avançado</h4>
                                                <p class="text-muted mb-0 fs-7">Relatórios detalhados</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="/campaigns/compare" class="card card-flush bg-light-warning hover-elevate-up h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="ki-duotone ki-graph-up fs-3x text-warning me-5"></i>
                                            <div>
                                                <h4 class="mb-1">Comparar Campanhas</h4>
                                                <p class="text-muted mb-0 fs-7">Lado a lado</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-6">
                                    <a href="/contact-lists" class="card card-flush bg-light-info hover-elevate-up h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="ki-duotone ki-profile-user fs-3x text-info me-5"></i>
                                            <div>
                                                <h4 class="mb-1">Gerenciar Listas</h4>
                                                <p class="text-muted mb-0 fs-7">Upload CSV</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Links Úteis</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-3">
                                <a href="/campaigns/quick-start" class="d-flex align-items-center text-gray-800 text-hover-primary">
                                    <i class="ki-duotone ki-rocket fs-2 me-3 text-primary"></i>
                                    <span class="fw-semibold">Guia de Início Rápido</span>
                                </a>
                                <a href="/campaigns/help" class="d-flex align-items-center text-gray-800 text-hover-primary">
                                    <i class="ki-duotone ki-question-2 fs-2 me-3 text-success"></i>
                                    <span class="fw-semibold">Central de Ajuda</span>
                                </a>
                                <a href="/campaigns/templates" class="d-flex align-items-center text-gray-800 text-hover-primary">
                                    <i class="ki-duotone ki-document fs-2 me-3 text-warning"></i>
                                    <span class="fw-semibold">Templates Prontos</span>
                                </a>
                                <a href="/integrations" class="d-flex align-items-center text-gray-800 text-hover-primary">
                                    <i class="ki-duotone ki-whatsapp fs-2 me-3 text-info"></i>
                                    <span class="fw-semibold">Configurar WhatsApp</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Carregar status
function loadStatus() {
    fetch('/api/campaigns')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.campaigns) {
                const campaigns = data.campaigns;
                
                document.getElementById('status_running').textContent = 
                    campaigns.filter(c => c.status === 'running').length;
                    
                document.getElementById('status_scheduled').textContent = 
                    campaigns.filter(c => c.status === 'scheduled').length;
                    
                document.getElementById('status_completed').textContent = 
                    campaigns.filter(c => c.status === 'completed').length;
                    
                document.getElementById('status_draft').textContent = 
                    campaigns.filter(c => c.status === 'draft').length;
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    loadStatus();
    
    // Atualizar a cada 30s
    setInterval(loadStatus, 30000);
});
</script>

<style>
.hover-elevate-up:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
</style>
