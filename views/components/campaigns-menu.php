<!-- Menu Flutuante de Acesso Rápido - Campanhas -->
<div class="position-fixed bottom-0 start-0 mb-5 ms-5" style="z-index: 1000;">
    <div class="dropdown dropup">
        <button class="btn btn-lg btn-primary rounded-circle" type="button" id="quick_menu" 
                data-bs-toggle="dropdown" aria-expanded="false"
                style="width: 60px; height: 60px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <i class="ki-duotone ki-lots-shopping fs-2x">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </button>
        
        <ul class="dropdown-menu dropdown-menu-start mb-2" style="min-width: 300px;">
            <li class="px-3 py-2">
                <div class="fw-bold fs-5 text-gray-900 mb-1">Campanhas WhatsApp</div>
                <div class="text-muted fs-7">Acesso rápido</div>
            </li>
            <li><hr class="dropdown-divider"></li>
            
            <li><h6 class="dropdown-header">Criar</h6></li>
            <li>
                <a class="dropdown-item" href="/campaigns/create">
                    <i class="ki-duotone ki-send fs-3 me-3 text-primary"></i>
                    <div>
                        <div class="fw-bold">Nova Campanha</div>
                        <div class="text-muted fs-7">Disparo simples</div>
                    </div>
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/campaigns/ab-test">
                    <i class="ki-duotone ki-chart-line-up-2 fs-3 me-3 text-success"></i>
                    <div>
                        <div class="fw-bold">Teste A/B</div>
                        <div class="text-muted fs-7">Compare versões</div>
                    </div>
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/drip-sequences/create">
                    <i class="ki-duotone ki-abstract-26 fs-3 me-3 text-warning"></i>
                    <div>
                        <div class="fw-bold">Sequência Drip</div>
                        <div class="text-muted fs-7">Múltiplas etapas</div>
                    </div>
                </a>
            </li>
            
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Gerenciar</h6></li>
            <li>
                <a class="dropdown-item" href="/campaigns">
                    <i class="ki-duotone ki-element-11 fs-3 me-3 text-info"></i>
                    <div>
                        <div class="fw-bold">Todas Campanhas</div>
                        <div class="text-muted fs-7">Ver lista completa</div>
                    </div>
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/contact-lists">
                    <i class="ki-duotone ki-profile-user fs-3 me-3 text-dark"></i>
                    <div>
                        <div class="fw-bold">Listas de Contatos</div>
                        <div class="text-muted fs-7">Gerenciar listas</div>
                    </div>
                </a>
            </li>
            
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Analytics</h6></li>
            <li>
                <a class="dropdown-item" href="/campaigns/realtime">
                    <i class="ki-duotone ki-pulse fs-3 me-3 text-danger"></i>
                    <div>
                        <div class="fw-bold">Tempo Real</div>
                        <div class="text-muted fs-7">Monitor ao vivo</div>
                    </div>
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/campaigns/analytics">
                    <i class="ki-duotone ki-chart-simple fs-3 me-3 text-primary"></i>
                    <div>
                        <div class="fw-bold">Analytics</div>
                        <div class="text-muted fs-7">Relatórios avançados</div>
                    </div>
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
#quick_menu {
    transition: all 0.3s ease;
}
#quick_menu:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 30px rgba(0,0,0,0.4) !important;
}
</style>
