<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $title ?? 'Sistema Multiatendimento' ?></title>
    <?php 
    $favicon = \App\Services\SettingService::get('app_favicon', '');
    $faviconUrl = !empty($favicon) ? \App\Helpers\Url::to($favicon) : \App\Helpers\Url::asset('media/logos/favicon.ico');
    ?>
    <link rel="icon" type="image/x-icon" href="<?= $faviconUrl ?>" />
    
    <!--begin::Fonts(mandatory for all pages)-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <!--end::Fonts-->
    
    <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
    <link href="<?= \App\Helpers\Url::asset('plugins/global/plugins.bundle.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= \App\Helpers\Url::asset('css/metronic/style.bundle.css') ?>" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
    
    <!-- Custom CSS -->
    <link href="<?= \App\Helpers\Url::asset('css/custom/theme-dark-light-fix.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= \App\Helpers\Url::asset('css/custom/sidebar-toggle.css') ?>" rel="stylesheet" type="text/css" />
    
    <?= $head ?? '' ?>
    <?= $styles ?? '' ?>
</head>
<body id="kt_body" class="header-fixed sidebar-enabled" style="--bs-gutter-x: 0">
    <!--begin::Theme mode setup on page load-->
    <script>
        var defaultThemeMode = "light";
        var themeMode;
        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }
            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
    </script>
    <!--end::Theme mode setup on page load-->
    
    <!--begin::Main-->
    <!--begin::Root-->
    <div class="d-flex flex-column flex-root">
        <!--begin::Page-->
        <div class="page d-flex flex-row flex-column-fluid">
            <!--begin::Aside-->
            <?php include __DIR__ . '/sidebar.php'; ?>
            <!--end::Aside-->
            
            <!--begin::Wrapper-->
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper" style="width: 100%">
                <!--begin::Header-->
                <?php include __DIR__ . '/header.php'; ?>
                <!--end::Header-->
                
                <!--begin::Content-->
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <!--begin::Container-->
                    <div class="container-fluid" id="kt_content_container" style="max-width: 100%; width: 100%">
                        <?= $content ?? '' ?>
                    </div>
                    <!--end::Container-->
                </div>
                <!--end::Content-->
            </div>
            <!--end::Wrapper-->
            
        </div>
        <!--end::Page-->
    </div>
    <!--end::Root-->
    <!--end::Main-->
    
    <!--begin::Javascript-->
    <script>var hostUrl = "<?= \App\Helpers\Url::asset('') ?>";</script>
    
    <!--begin::Prevent Bootstrap Modal Auto-Init Error-->
    <script>
        // Interceptar cliques em botões de modal ANTES do Bootstrap processá-los
        // Isso previne erros quando modais não existem
        (function() {
            // Interceptar cliques na fase de captura (antes do Bootstrap)
            document.addEventListener('click', function(e) {
                var trigger = e.target.closest('[data-bs-toggle="modal"]');
                if (trigger) {
                    var targetId = trigger.getAttribute('data-bs-target');
                    if (targetId) {
                        var modalElement = document.querySelector(targetId);
                        if (!modalElement) {
                            // Modal não existe - prevenir erro
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            console.warn('Modal não encontrado:', targetId);
                            return false;
                        }
                    }
                }
            }, true); // true = usar capture phase para interceptar ANTES
        })();
    </script>
    <!--end::Prevent Bootstrap Modal Auto-Init Error-->
    
    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    <script src="<?= \App\Helpers\Url::asset('plugins/global/plugins.bundle.js') ?>"></script>
    <script src="<?= \App\Helpers\Url::asset('js/metronic/scripts.bundle.js') ?>"></script>
    <script src="<?= \App\Helpers\Url::asset('js/metronic/widgets.bundle.js') ?>"></script>
    <!--end::Global Javascript Bundle-->
    
    <!-- Custom JS -->
    <script src="<?= \App\Helpers\Url::asset('js/custom/modals.js') ?>"></script>
    <?php if (file_exists(__DIR__ . '/../../../../public/assets/js/custom/layout.js')): ?>
    <script src="<?= \App\Helpers\Url::asset('js/custom/layout.js') ?>"></script>
    <?php endif; ?>
    
    <!-- WebSocket Client -->
    <script src="<?= \App\Helpers\Url::asset('js/realtime-client.js') ?>"></script>
    
    <!--begin::Sidebar Toggle Script-->
    <script>
        // Função para toggle do sidebar esquerdo
        function initSidebarToggle() {
            const asideToggleSidebar = document.getElementById('kt_aside_toggle_sidebar');
            const aside = document.getElementById('kt_aside');
            
            if (!aside) return;
            
            // Função para recolher todos os submenus
            function closeAllSubmenus() {
                const openSubmenus = aside.querySelectorAll('.menu-item.menu-accordion.show');
                openSubmenus.forEach(item => {
                    item.classList.remove('show');
                    const submenu = item.querySelector('.menu-sub');
                    if (submenu) {
                        submenu.style.display = 'none';
                    }
                });
            }
            
            // Função para toggle do sidebar
            function toggleSidebar() {
                const isMinimized = aside.classList.contains('aside-minimize');
                
                if (isMinimized) {
                    // Expandir
                    aside.classList.remove('aside-minimize');
                    document.body.classList.remove('aside-minimize');
                    localStorage.setItem('sidebar_collapsed', 'false');
                } else {
                    // Recolher - fechar todos os submenus primeiro
                    closeAllSubmenus();
                    aside.classList.add('aside-minimize');
                    document.body.classList.add('aside-minimize');
                    localStorage.setItem('sidebar_collapsed', 'true');
                }
                
                // Trigger resize event para ajustar layouts
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 300);
            }
            
            // Verificar estado salvo no localStorage
            const sidebarCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
            
            // Aplicar estado inicial
            if (sidebarCollapsed) {
                closeAllSubmenus();
                aside.classList.add('aside-minimize');
                document.body.classList.add('aside-minimize');
            }
            
            // Toggle ao clicar no botão do sidebar
            if (asideToggleSidebar) {
                asideToggleSidebar.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleSidebar();
                });
            }
        }
        
    </script>
    
    <!--begin::Initialize Metronic Components-->
    <script>
        // Aguardar carregamento completo dos scripts
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar toggle do sidebar esquerdo
            initSidebarToggle();
            
            // Verificar se Bootstrap está disponível
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap não está carregado!');
                return;
            }
            
            // Aguardar um pouco para garantir que tudo foi carregado
            setTimeout(function() {
                // Inicializar theme mode do Metronic (deve ser inicializado automaticamente, mas garantimos)
                if (typeof KTThemeMode !== 'undefined') {
                    KTThemeMode.init();
                }
                
                // Os menus e drawers são inicializados automaticamente pelo Metronic
                // Mas podemos forçar a inicialização se necessário
                if (typeof KTMenu !== 'undefined' && typeof KTMenu.createInstances === 'function') {
                    KTMenu.createInstances();
                }
                
                // Drawers são inicializados automaticamente via data-kt-drawer
                
                // Garantir que os modais estão funcionando corretamente
                // Prevenir erro de backdrop undefined
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    // Inicializar todos os modais existentes na página ANTES de qualquer clique
                    var modalElements = document.querySelectorAll('.modal');
                    modalElements.forEach(function(modalElement) {
                        try {
                            // Verificar se já existe uma instância
                            var existingModal = bootstrap.Modal.getInstance(modalElement);
                            if (!existingModal) {
                                // Criar instância com configurações padrão seguras
                                var modal = new bootstrap.Modal(modalElement, {
                                    backdrop: true,
                                    keyboard: true,
                                    focus: true
                                });
                            }
                        } catch (error) {
                            console.warn('Erro ao inicializar modal:', error, modalElement);
                            // Tentar criar sem opções como fallback
                            try {
                                var modal = new bootstrap.Modal(modalElement);
                            } catch (err) {
                                console.error('Erro ao criar modal sem opções:', err);
                            }
                        }
                    });
                    
                    // Interceptar cliques em botões de modal para garantir inicialização
                    document.addEventListener('click', function(e) {
                        var trigger = e.target.closest('[data-bs-toggle="modal"]');
                        if (trigger) {
                            var targetId = trigger.getAttribute('data-bs-target');
                            if (targetId) {
                                var modalElement = document.querySelector(targetId);
                                if (modalElement) {
                                    var existingModal = bootstrap.Modal.getInstance(modalElement);
                                    if (!existingModal) {
                                        try {
                                            var modal = new bootstrap.Modal(modalElement, {
                                                backdrop: true,
                                                keyboard: true,
                                                focus: true
                                            });
                                        } catch (err) {
                                            console.error('Erro ao criar modal no clique:', err);
                                        }
                                    }
                                }
                            }
                        }
                    }, true); // Usar capture phase
                } else {
                    console.error('Bootstrap Modal não está disponível!');
                }
            }, 100);
            
            // Inicializar Cliente de Tempo Real se usuário estiver logado
            <?php if (\App\Helpers\Auth::check()): ?>
            const userId = <?= \App\Helpers\Auth::id() ?>;
            if (typeof window.realtimeClient !== 'undefined') {
                window.realtimeClient.connect(userId);
                
                // Handler para reconexão automática
                window.realtimeClient.on('reconnect_failed', () => {
                    console.warn('Tempo Real: Falha ao reconectar. Recarregue a página.');
                });
                
                // Log do modo atual
                window.realtimeClient.on('connected', () => {
                    console.log('Tempo Real conectado em modo:', window.realtimeClient.currentMode);
                });
            } else if (typeof window.wsClient !== 'undefined') {
                // Fallback para compatibilidade com código antigo
                window.wsClient.connect(userId);
            }
            <?php endif; ?>
        });
    </script>
    <!--end::Initialize Metronic Components-->
    
    <!--begin::Modal: Filtros de Busca Global-->
    <div class="modal fade" id="kt_modal_global_search_filters" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-600px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">Filtros de Busca</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body">
                    <form id="globalSearchFiltersForm">
                        <div class="mb-5">
                            <label class="form-label fw-semibold">Status:</label>
                            <select id="filter_status" class="form-select form-select-solid">
                                <option value="">Todos</option>
                                <option value="open">Abertas</option>
                                <option value="closed">Fechadas</option>
                                <option value="pending">Pendentes</option>
                            </select>
                        </div>
                        
                        <div class="mb-5">
                            <label class="form-label fw-semibold">Canal:</label>
                            <select id="filter_channel" class="form-select form-select-solid">
                                <option value="">Todos</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Email</option>
                                <option value="chat">Chat</option>
                                <option value="telegram">Telegram</option>
                            </select>
                        </div>
                        
                        <div class="mb-5">
                            <label class="form-label fw-semibold">Setor:</label>
                            <select id="filter_department_id" class="form-select form-select-solid">
                                <option value="">Todos</option>
                                <!-- Será preenchido via JavaScript -->
                            </select>
                        </div>
                        
                        <div class="mb-5">
                            <label class="form-label fw-semibold">Agente:</label>
                            <select id="filter_agent_id" class="form-select form-select-solid">
                                <option value="">Todos</option>
                                <!-- Será preenchido via JavaScript -->
                            </select>
                        </div>
                        
                        <div class="mb-5">
                            <label class="form-label fw-semibold">Data Inicial:</label>
                            <input type="date" id="filter_date_from" class="form-control form-control-solid">
                        </div>
                        
                        <div class="mb-5">
                            <label class="form-label fw-semibold">Data Final:</label>
                            <input type="date" id="filter_date_to" class="form-control form-control-solid">
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" onclick="clearGlobalSearchFilters()">Limpar</button>
                            <button type="button" class="btn btn-primary" onclick="applyGlobalSearchFilters()">Aplicar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!--end::Modal: Filtros de Busca Global-->
    
    <!--begin::Modal: Buscas Salvas-->
    <div class="modal fade" id="kt_modal_saved_searches" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-600px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">Buscas Salvas</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body">
                    <div id="savedSearchesList">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Modal: Buscas Salvas-->
    
    <!--begin::Modal: Busca Global-->
    <div class="modal fade" id="kt_modal_global_search" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">Resultados da Busca</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <i class="ki-duotone ki-cross fs-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="mb-5">
                        <div class="d-flex gap-2 mb-3">
                            <input type="text" id="global_search_input" class="form-control form-control-solid" placeholder="Digite sua busca...">
                            <button class="btn btn-primary" onclick="performGlobalSearch()">
                                <i class="ki-duotone ki-magnifier fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Buscar
                            </button>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <select id="global_search_type" class="form-select form-select-sm" style="width: auto;">
                                <option value="all">Tudo</option>
                                <option value="conversations">Conversas</option>
                                <option value="contacts">Contatos</option>
                                <option value="messages">Mensagens</option>
                            </select>
                            <button class="btn btn-sm btn-light" onclick="showGlobalSearchFilters()">
                                <i class="ki-duotone ki-filter fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Filtros
                            </button>
                            <button class="btn btn-sm btn-light" onclick="showSavedSearches()">
                                <i class="ki-duotone ki-bookmark fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Buscas Salvas
                            </button>
                            <button class="btn btn-sm btn-light-primary" onclick="saveCurrentSearch()" title="Salvar busca atual">
                                <i class="ki-duotone ki-check fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Salvar
                            </button>
                        </div>
                    </div>
                    
                    <div id="global_search_results" class="search-results">
                        <div class="text-center text-muted py-10">
                            <i class="ki-duotone ki-magnifier fs-3x mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>Digite um termo de busca para começar</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Modal: Busca Global-->
    
    <script>
    // Busca Global
    let globalSearchDebounce = null;
    let globalSearchFilters = {};
    let globalSearchCache = new Map(); // Cache de resultados
    const CACHE_TTL = 5 * 60 * 1000; // 5 minutos
    
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar indicador de filtros (se modal existir)
        if (document.getElementById('kt_modal_global_search')) {
            updateGlobalSearchFiltersIndicator();
        }
        
        const globalSearchModalInput = document.getElementById('global_search_input');
        
        if (globalSearchModalInput) {
            // Busca ao digitar (com debounce otimizado)
            let lastSearchValue = '';
            globalSearchModalInput.addEventListener('input', function() {
                const currentValue = this.value.trim();
                
                // Se não mudou, não fazer nada
                if (currentValue === lastSearchValue) return;
                lastSearchValue = currentValue;
                
                // Limpar debounce anterior
                if (globalSearchDebounce) {
                    clearTimeout(globalSearchDebounce);
                }
                
                // Se campo vazio, limpar resultados imediatamente
                if (currentValue.length === 0 && Object.keys(globalSearchFilters).length === 0) {
                    document.getElementById('global_search_results').innerHTML = `
                        <div class="text-center text-muted py-10">
                            <div>Digite um termo de busca para começar</div>
                        </div>
                    `;
                    return;
                }
                
                // Debounce: 300ms para busca rápida, 800ms se tiver menos de 3 caracteres
                const debounceTime = currentValue.length < 3 ? 800 : 300;
                
                globalSearchDebounce = setTimeout(() => {
                    if (currentValue.length >= 2 || Object.keys(globalSearchFilters).length > 0) {
                        performGlobalSearch();
                    } else {
                        document.getElementById('global_search_results').innerHTML = `
                            <div class="text-center text-muted py-10">
                                <div>Digite pelo menos 2 caracteres para buscar</div>
                            </div>
                        `;
                    }
                }, debounceTime);
            });
            
            // Busca com Enter
            globalSearchModalInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performGlobalSearch();
                }
            });
        }
    });
    
    function performGlobalSearch() {
        const query = document.getElementById('global_search_input')?.value || '';
        const type = document.getElementById('global_search_type')?.value || 'all';
        const resultsDiv = document.getElementById('global_search_results');
        const loadingDiv = document.getElementById('kt_global_search_loading');
        
        if (!query.trim() && Object.keys(globalSearchFilters).length === 0) {
            resultsDiv.innerHTML = `
                <div class="text-center text-muted py-10">
                    <div>Digite um termo de busca ou aplique filtros</div>
                </div>
            `;
            return;
        }
        
        // Criar chave de cache
        const cacheKey = `${query}|${type}|${JSON.stringify(globalSearchFilters)}`;
        const cachedResult = globalSearchCache.get(cacheKey);
        
        // Verificar cache
        if (cachedResult && (Date.now() - cachedResult.timestamp) < CACHE_TTL) {
            renderGlobalSearchResults(cachedResult.results, query);
            return;
        }
        
        // Mostrar loading
        if (loadingDiv) loadingDiv.classList.remove('d-none');
        resultsDiv.innerHTML = `
            <div class="text-center py-10">
                <span class="spinner-border spinner-border-lg text-primary" role="status"></span>
                <div class="text-muted mt-3">Buscando...</div>
            </div>
        `;
        
        // Construir URL com parâmetros
        const params = new URLSearchParams();
        if (query) params.append('q', query);
        params.append('type', type);
        
        // Adicionar filtros
        Object.keys(globalSearchFilters).forEach(key => {
            if (globalSearchFilters[key]) {
                params.append(key, globalSearchFilters[key]);
            }
        });
        
        const startTime = Date.now();
        
        fetch(`<?= \App\Helpers\Url::to('/search/global') ?>?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (loadingDiv) loadingDiv.classList.add('d-none');
                
                if (!data.success) {
                    resultsDiv.innerHTML = `
                        <div class="text-center text-danger py-10">
                            <div>Erro: ${data.message || 'Erro desconhecido'}</div>
                        </div>
                    `;
                    return;
                }
                
                // Salvar no cache
                globalSearchCache.set(cacheKey, {
                    results: data.results,
                    timestamp: Date.now()
                });
                
                // Limpar cache antigo (manter apenas últimos 50)
                if (globalSearchCache.size > 50) {
                    const firstKey = globalSearchCache.keys().next().value;
                    globalSearchCache.delete(firstKey);
                }
                
                renderGlobalSearchResults(data.results, query);
                
                // Log de performance (apenas em desenvolvimento)
                if (console && console.log) {
                    const duration = Date.now() - startTime;
                    console.log(`Busca concluída em ${duration}ms`);
                }
            })
            .catch(error => {
                if (loadingDiv) loadingDiv.classList.add('d-none');
                console.error('Erro na busca:', error);
                resultsDiv.innerHTML = `
                    <div class="text-center text-danger py-10">
                        <div>Erro ao realizar busca. Tente novamente.</div>
                    </div>
                `;
            });
    }
    
    function renderGlobalSearchResults(results, query) {
        const resultsDiv = document.getElementById('global_search_results');
        let html = '';
        
        const totalResults = (results.conversations?.length || 0) + 
                           (results.contacts?.length || 0) + 
                           (results.messages?.length || 0);
        
        if (totalResults === 0) {
            html = `
                <div class="text-center text-muted py-10">
                    <i class="ki-duotone ki-information-5 fs-3x mb-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div>Nenhum resultado encontrado para "${escapeHtml(query)}"</div>
                </div>
            `;
        } else {
            html = `<div class="mb-5"><strong>${totalResults}</strong> resultado(s) encontrado(s)</div>`;
            
            // Conversas
            if (results.conversations && results.conversations.length > 0) {
                html += `
                    <div class="mb-5">
                        <h5 class="fw-bold mb-3">
                            <i class="ki-duotone ki-message-text-2 fs-4 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Conversas (${results.conversations.length})
                        </h5>
                        <div class="list-group">
                `;
                results.conversations.forEach(conv => {
                    const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${conv.id}`;
                    html += `
                        <a href="${url}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">${escapeHtml(conv.subject || 'Sem assunto')}</div>
                                    <div class="text-muted fs-7 mt-1">
                                        ${escapeHtml(conv.channel)} • ${escapeHtml(conv.status)}
                                        ${conv.unread_count > 0 ? `<span class="badge badge-danger ms-2">${conv.unread_count}</span>` : ''}
                                    </div>
                                </div>
                                <small class="text-muted">${formatDate(conv.updated_at)}</small>
                            </div>
                        </a>
                    `;
                });
                html += `</div></div>`;
            }
            
            // Contatos
            if (results.contacts && results.contacts.length > 0) {
                html += `
                    <div class="mb-5">
                        <h5 class="fw-bold mb-3">
                            <i class="ki-duotone ki-profile-user fs-4 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Contatos (${results.contacts.length})
                        </h5>
                        <div class="list-group">
                `;
                results.contacts.forEach(contact => {
                    const url = `<?= \App\Helpers\Url::to('/contacts') ?>/${contact.id}`;
                    html += `
                        <a href="${url}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">${escapeHtml(contact.name || 'Sem nome')}</div>
                                    <div class="text-muted fs-7 mt-1">
                                        ${contact.email ? escapeHtml(contact.email) + ' • ' : ''}
                                        ${contact.phone ? escapeHtml(contact.phone) : ''}
                                    </div>
                                </div>
                                <small class="text-muted">${formatDate(contact.updated_at)}</small>
                            </div>
                        </a>
                    `;
                });
                html += `</div></div>`;
            }
            
            // Mensagens
            if (results.messages && results.messages.length > 0) {
                html += `
                    <div class="mb-5">
                        <h5 class="fw-bold mb-3">
                            <i class="ki-duotone ki-message fs-4 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Mensagens (${results.messages.length})
                        </h5>
                        <div class="list-group">
                `;
                results.messages.forEach(msg => {
                    const url = `<?= \App\Helpers\Url::to('/conversations') ?>/${msg.conversation_id}`;
                    const content = escapeHtml(msg.content || '').substring(0, 100);
                    html += `
                        <a href="${url}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">${escapeHtml(msg.conversation_subject || 'Sem assunto')}</div>
                                    <div class="text-muted fs-7 mt-1">${content}${msg.content && msg.content.length > 100 ? '...' : ''}</div>
                                    <div class="text-muted fs-7 mt-1">
                                        ${escapeHtml(msg.contact_name || 'Contato')} • ${escapeHtml(msg.sender_type)}
                                    </div>
                                </div>
                                <small class="text-muted">${formatDate(msg.created_at)}</small>
                            </div>
                        </a>
                    `;
                });
                html += `</div></div>`;
            }
        }
        
        resultsDiv.innerHTML = html;
    }
    
    function showGlobalSearchFilters() {
        const modal = new bootstrap.Modal(document.getElementById('kt_modal_global_search_filters'));
        modal.show();
        
        // Carregar opções de setores e agentes se ainda não carregou
        loadGlobalSearchFilterOptions();
        
        // Preencher filtros atuais
        if (globalSearchFilters.status) {
            document.getElementById('filter_status').value = globalSearchFilters.status;
        }
        if (globalSearchFilters.channel) {
            document.getElementById('filter_channel').value = globalSearchFilters.channel;
        }
        if (globalSearchFilters.department_id) {
            document.getElementById('filter_department_id').value = globalSearchFilters.department_id;
        }
        if (globalSearchFilters.agent_id) {
            document.getElementById('filter_agent_id').value = globalSearchFilters.agent_id;
        }
        if (globalSearchFilters.date_from) {
            document.getElementById('filter_date_from').value = globalSearchFilters.date_from;
        }
        if (globalSearchFilters.date_to) {
            document.getElementById('filter_date_to').value = globalSearchFilters.date_to;
        }
    }
    
    function loadGlobalSearchFilterOptions() {
        // Carregar setores usando Model diretamente via endpoint simples
        const departmentSelect = document.getElementById('filter_department_id');
        if (departmentSelect && departmentSelect.options.length <= 1) {
            // Usar endpoint que retorna JSON (se existir) ou criar endpoint simples
            fetch('<?= \App\Helpers\Url::to('/departments') ?>?format=json', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    if (response.ok) return response.json();
                    // Se não retornar JSON, tentar buscar via Model
                    return fetch('<?= \App\Helpers\Url::to('/api/departments') ?>')
                        .then(r => r.ok ? r.json() : Promise.reject('Endpoint não encontrado'));
                })
                .then(data => {
                    const departments = data.departments || data.data || [];
                    departments.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.id;
                        option.textContent = dept.name;
                        departmentSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar setores:', error);
                    // Fallback: adicionar opção de erro
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'Erro ao carregar setores';
                    departmentSelect.appendChild(option);
                });
        }
        
        // Carregar agentes
        const agentSelect = document.getElementById('filter_agent_id');
        if (agentSelect && agentSelect.options.length <= 1) {
            fetch('<?= \App\Helpers\Url::to('/agents') ?>?format=json', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    if (response.ok) return response.json();
                    return fetch('<?= \App\Helpers\Url::to('/api/agents') ?>')
                        .then(r => r.ok ? r.json() : Promise.reject('Endpoint não encontrado'));
                })
                .then(data => {
                    const agents = data.agents || data.data || [];
                    agents.forEach(agent => {
                        const option = document.createElement('option');
                        option.value = agent.id;
                        option.textContent = agent.name || agent.email;
                        agentSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar agentes:', error);
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'Erro ao carregar agentes';
                    agentSelect.appendChild(option);
                });
        }
    }
    
    function applyGlobalSearchFilters() {
        globalSearchFilters = {
            status: document.getElementById('filter_status').value || null,
            channel: document.getElementById('filter_channel').value || null,
            department_id: document.getElementById('filter_department_id').value || null,
            agent_id: document.getElementById('filter_agent_id').value || null,
            date_from: document.getElementById('filter_date_from').value || null,
            date_to: document.getElementById('filter_date_to').value || null
        };
        
        // Remover valores vazios
        Object.keys(globalSearchFilters).forEach(key => {
            if (!globalSearchFilters[key]) {
                delete globalSearchFilters[key];
            }
        });
        
        // Fechar modal
        bootstrap.Modal.getInstance(document.getElementById('kt_modal_global_search_filters')).hide();
        
        // Atualizar indicador de filtros
        updateGlobalSearchFiltersIndicator();
        
        // Refazer busca se houver termo ou filtros
        const query = document.getElementById('global_search_input')?.value || '';
        if (query || Object.keys(globalSearchFilters).length > 0) {
            performGlobalSearch();
        }
    }
    
    function clearGlobalSearchFilters() {
        globalSearchFilters = {};
        document.getElementById('globalSearchFiltersForm').reset();
        updateGlobalSearchFiltersIndicator();
        
        // Refazer busca apenas com termo
        const query = document.getElementById('global_search_input')?.value || '';
        if (query) {
            performGlobalSearch();
        }
    }
    
    function updateGlobalSearchFiltersIndicator() {
        const filterBtn = document.querySelector('button[onclick="showGlobalSearchFilters()"]');
        const filterCount = Object.keys(globalSearchFilters).length;
        
        if (filterBtn) {
            if (filterCount > 0) {
                filterBtn.classList.add('btn-primary');
                filterBtn.classList.remove('btn-light');
                filterBtn.innerHTML = `
                    <i class="ki-duotone ki-filter fs-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Filtros (${filterCount})
                `;
            } else {
                filterBtn.classList.add('btn-light');
                filterBtn.classList.remove('btn-primary');
                filterBtn.innerHTML = `
                    <i class="ki-duotone ki-filter fs-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Filtros
                `;
            }
        }
    }
    
    function showSavedSearches() {
        const modal = new bootstrap.Modal(document.getElementById('kt_modal_saved_searches'));
        modal.show();
        loadSavedSearches();
    }
    
    function loadSavedSearches() {
        const savedSearchesList = document.getElementById('savedSearchesList');
        if (!savedSearchesList) return;
        
        savedSearchesList.innerHTML = `
            <div class="text-center py-10">
                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                <div class="text-muted mt-2">Carregando buscas salvas...</div>
            </div>
        `;
        
        fetch('<?= \App\Helpers\Url::to('/search/saved') ?>')
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.saved_searches || data.saved_searches.length === 0) {
                    savedSearchesList.innerHTML = `
                        <div class="text-center text-muted py-10">
                            <i class="ki-duotone ki-bookmark fs-3x mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>Nenhuma busca salva</div>
                            <div class="text-muted fs-7 mt-2">Salve buscas frequentes para acesso rápido</div>
                        </div>
                    `;
                    return;
                }
                
                let html = '<div class="list-group">';
                data.saved_searches.forEach(search => {
                    const filtersText = Object.keys(search.filters || {}).length > 0 
                        ? `<span class="badge badge-light-primary ms-2">${Object.keys(search.filters).length} filtro(s)</span>`
                        : '';
                    
                    html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">${escapeHtml(search.name)}</div>
                                    <div class="text-muted fs-7 mt-1">
                                        ${search.query ? `<span class="me-2">"${escapeHtml(search.query)}"</span>` : ''}
                                        ${filtersText}
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-light-primary" onclick="useSavedSearch(${search.id})" title="Usar busca">
                                        <i class="ki-duotone ki-check fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button class="btn btn-sm btn-light-danger" onclick="deleteSavedSearch(${search.id})" title="Deletar">
                                        <i class="ki-duotone ki-trash fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                
                savedSearchesList.innerHTML = html;
            })
            .catch(error => {
                console.error('Erro ao carregar buscas salvas:', error);
                savedSearchesList.innerHTML = `
                    <div class="text-center text-danger py-10">
                        <div>Erro ao carregar buscas salvas</div>
                    </div>
                `;
            });
    }
    
    function useSavedSearch(searchId) {
        fetch(`<?= \App\Helpers\Url::to('/search/saved') ?>`)
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.saved_searches) return;
                
                const search = data.saved_searches.find(s => s.id === searchId);
                if (!search) return;
                
                // Preencher campos de busca
                const searchInput = document.getElementById('global_search_input');
                if (searchInput) {
                    searchInput.value = search.query || '';
                }
                
                // Aplicar filtros
                globalSearchFilters = search.filters || {};
                updateGlobalSearchFiltersIndicator();
                
                // Fechar modal de buscas salvas
                bootstrap.Modal.getInstance(document.getElementById('kt_modal_saved_searches')).hide();
                
                // Executar busca
                performGlobalSearch();
            })
            .catch(error => {
                console.error('Erro ao carregar busca salva:', error);
                alert('Erro ao carregar busca salva');
            });
    }
    
    function deleteSavedSearch(searchId) {
        if (!confirm('Deseja realmente deletar esta busca salva?')) return;
        
        fetch(`<?= \App\Helpers\Url::to('/search/saved') ?>/${searchId}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadSavedSearches();
                } else {
                    alert('Erro ao deletar busca: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro ao deletar busca:', error);
                alert('Erro ao deletar busca');
            });
    }
    
    function saveCurrentSearch() {
        const name = prompt('Digite um nome para esta busca:');
        if (!name || !name.trim()) return;
        
        const query = document.getElementById('global_search_input')?.value || '';
        const filters = { ...globalSearchFilters };
        
        fetch('<?= \App\Helpers\Url::to('/search/save') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                name: name.trim(),
                query: query,
                filters: filters
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Busca salva com sucesso!');
                } else {
                    alert('Erro ao salvar busca: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro ao salvar busca:', error);
                alert('Erro ao salvar busca');
            });
    }
    
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Agora';
        if (diffMins < 60) return `${diffMins}min atrás`;
        if (diffHours < 24) return `${diffHours}h atrás`;
        if (diffDays < 7) return `${diffDays}d atrás`;
        
        return date.toLocaleDateString('pt-BR');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
    
    <?= $scripts ?? '' ?>
    <!--end::Javascript-->
</body>
</html>

