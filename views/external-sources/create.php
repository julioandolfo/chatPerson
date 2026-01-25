<?php
$layout = 'layouts.metronic.app';
$title = 'Nova Fonte de Dados Externa';

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Configurar Fonte de Dados Externa</h3>
    </div>
    <div class="card-body">
        
        <div class="alert alert-info d-flex align-items-center mb-10">
            <i class="ki-duotone ki-information-5 fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            <div>
                <strong>Sincronização Automática:</strong> Conecte bancos de dados externos ou busque leads do Google Maps para importar contatos automaticamente.
            </div>
        </div>
        
        <form id="source_form">
            
            <!-- Passo 1: Informações Básicas -->
            <div class="mb-10">
                <h4 class="mb-5">1. Informações Básicas</h4>
                
                <div class="mb-5">
                    <label class="form-label required">Nome da Fonte</label>
                    <input type="text" class="form-control" name="name" placeholder="Ex: Restaurantes SP" required />
                </div>
                
                <div class="mb-5">
                    <label class="form-label required">Tipo de Fonte</label>
                    <select class="form-select" name="type" id="db_type" required>
                        <option value="">Selecione...</option>
                        <option value="google_maps">Google Maps (Prospecção de Leads)</option>
                        <option value="woocommerce">WooCommerce (Clientes de E-commerce)</option>
                        <option value="mysql">MySQL (Banco de Dados)</option>
                        <option value="postgresql">PostgreSQL (Banco de Dados)</option>
                    </select>
                </div>
            </div>
            
            <!-- ========== SEÇÃO GOOGLE MAPS ========== -->
            <div id="section_google_maps" style="display:none;">
                
                <div class="separator my-10"></div>
                
                <div class="mb-10">
                    <h4 class="mb-5">2. Configuração de Busca no Google Maps</h4>
                    
                    <div class="alert alert-warning mb-5">
                        <i class="ki-duotone ki-information fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <strong>Prospecção B2B:</strong> Busque empresas por categoria e região. Apenas contatos com telefone serão importados.
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Palavra-chave</label>
                        <input type="text" class="form-control" id="gm_keyword" placeholder="Ex: dentistas, restaurantes, advogados" />
                        <div class="form-text">Categoria ou tipo de negócio que deseja prospectar</div>
                    </div>
                    
                    <div class="row g-3 mb-5">
                        <div class="col-md-8">
                            <label class="form-label required">Localização</label>
                            <input type="text" class="form-control" id="gm_location" placeholder="Ex: São Paulo, SP ou CEP 01310-100" />
                            <div class="form-text">Cidade, bairro, CEP ou endereço de referência</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Raio (metros)</label>
                            <input type="number" class="form-control" id="gm_radius" value="5000" min="100" max="50000" />
                            <div class="form-text">5000 = 5km</div>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Limite de Resultados por Sync</label>
                            <input type="number" class="form-control" id="gm_max_results" value="60" min="10" max="500" />
                            <div class="form-text">Máximo 60 por busca (Google Places API)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Provider</label>
                            <select class="form-select" id="gm_provider">
                                <option value="google_places">Google Places API (Oficial)</option>
                                <option value="outscraper">Outscraper (Terceiros - Mais Barato)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-light-primary" onclick="testGoogleMapsConnection()">
                            <i class="ki-duotone ki-flash fs-3"></i>
                            Testar Conexão API
                        </button>
                        <button type="button" class="btn btn-light-info" onclick="previewGoogleMaps()">
                            <i class="ki-duotone ki-eye fs-3"></i>
                            Preview de Busca
                        </button>
                    </div>
                    <span id="gm_connection_status" class="ms-3"></span>
                    
                    <div id="gm_preview_container" class="mt-5" style="display:none;"></div>
                </div>
                
                <div class="separator my-10"></div>
                
                <div class="mb-10">
                    <h4 class="mb-5">3. Configurar Sincronização Automática</h4>
                    
                    <div class="mb-5">
                        <label class="form-label">Frequência de Sincronização</label>
                        <select class="form-select" id="gm_sync_frequency">
                            <option value="manual">Manual (sob demanda)</option>
                            <option value="daily" selected>Diariamente</option>
                            <option value="weekly">Semanalmente</option>
                        </select>
                        <div class="form-text">A cada sincronização, novos leads serão adicionados automaticamente</div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mt-10">
                    <button type="button" class="btn btn-light me-3" onclick="window.history.back()">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn_save_gm" onclick="saveGoogleMapsSource()">
                        <span class="indicator-label">Criar Fonte Google Maps</span>
                        <span class="indicator-progress">Salvando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </div>
            <!-- ========== FIM SEÇÃO GOOGLE MAPS ========== -->
            
            <!-- ========== SEÇÃO WOOCOMMERCE ========== -->
            <div id="section_woocommerce" style="display:none;">
                
                <div class="separator my-10"></div>
                
                <div class="mb-10">
                    <h4 class="mb-5">2. Configuração da Loja WooCommerce</h4>
                    
                    <div class="alert alert-info mb-5">
                        <i class="ki-duotone ki-information fs-2 me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <strong>Importação de Clientes:</strong> Conecte sua loja WooCommerce para importar automaticamente os clientes que fizeram compras.
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">URL da Loja</label>
                        <input type="url" class="form-control" id="wc_store_url" placeholder="https://minhaloja.com.br" />
                        <div class="form-text">Endereço completo da sua loja WooCommerce (com https://)</div>
                    </div>
                    
                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <label class="form-label required">Consumer Key</label>
                            <input type="text" class="form-control" id="wc_consumer_key" placeholder="ck_..." />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Consumer Secret</label>
                            <input type="password" class="form-control" id="wc_consumer_secret" placeholder="cs_..." />
                        </div>
                    </div>
                    <div class="form-text mb-5">
                        <strong>Como obter:</strong> No painel do WooCommerce, vá em <code>Configurações → Avançado → REST API</code> e crie uma chave com permissão de <strong>Leitura</strong>.
                    </div>
                    
                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Importação</label>
                            <select class="form-select" id="wc_import_type">
                                <option value="customers">Clientes Cadastrados</option>
                                <option value="orders" selected>Clientes que Compraram</option>
                            </select>
                            <div class="form-text">Pedidos importa apenas clientes com compras finalizadas</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Período (dias)</label>
                            <input type="number" class="form-control" id="wc_days_back" value="30" min="1" max="365" />
                            <div class="form-text">Importar clientes dos últimos X dias</div>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Mínimo de Pedidos</label>
                        <input type="number" class="form-control" id="wc_min_orders" value="0" min="0" max="100" style="max-width: 150px;" />
                        <div class="form-text">Importar apenas clientes com pelo menos X pedidos (0 = todos)</div>
                    </div>
                    
                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-light-primary" onclick="testWooCommerceConnection()">
                            <i class="ki-duotone ki-flash fs-3"></i>
                            Testar Conexão
                        </button>
                        <button type="button" class="btn btn-light-info" onclick="previewWooCommerce()">
                            <i class="ki-duotone ki-eye fs-3"></i>
                            Preview de Clientes
                        </button>
                    </div>
                    <span id="wc_connection_status" class="ms-3"></span>
                    
                    <div id="wc_preview_container" class="mt-5" style="display:none;"></div>
                </div>
                
                <div class="separator my-10"></div>
                
                <div class="mb-10">
                    <h4 class="mb-5">3. Configurar Sincronização Automática</h4>
                    
                    <div class="mb-5">
                        <label class="form-label">Frequência de Sincronização</label>
                        <select class="form-select" id="wc_sync_frequency">
                            <option value="manual">Manual (sob demanda)</option>
                            <option value="daily" selected>Diariamente</option>
                            <option value="weekly">Semanalmente</option>
                        </select>
                        <div class="form-text">A cada sincronização, novos clientes serão adicionados automaticamente</div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mt-10">
                    <button type="button" class="btn btn-light me-3" onclick="window.history.back()">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn_save_wc" onclick="saveWooCommerceSource()">
                        <span class="indicator-label">Criar Fonte WooCommerce</span>
                        <span class="indicator-progress">Salvando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </div>
            <!-- ========== FIM SEÇÃO WOOCOMMERCE ========== -->
            
            <!-- ========== SEÇÃO BANCO DE DADOS ========== -->
            <div id="section_database" style="display:none;">
            
            <div class="separator my-10"></div>
            
            <!-- Passo 2: Conexão -->
            <div class="mb-10">
                <h4 class="mb-5">2. Configuração de Conexão</h4>
                
                <div class="row g-3 mb-5">
                    <div class="col-md-6">
                        <label class="form-label required">Host</label>
                        <input type="text" class="form-control" id="db_host" placeholder="localhost" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Porta</label>
                        <input type="number" class="form-control" id="db_port" value="3306" required />
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="form-label required">Nome do Banco</label>
                    <input type="text" class="form-control" id="db_database" placeholder="nome_banco" required />
                </div>
                
                <div class="row g-3 mb-5">
                    <div class="col-md-6">
                        <label class="form-label required">Usuário</label>
                        <input type="text" class="form-control" id="db_username" placeholder="root" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Senha</label>
                        <input type="password" class="form-control" id="db_password" placeholder="senha" />
                    </div>
                </div>
                
                <button type="button" class="btn btn-light-primary" onclick="testConnection()">
                    <i class="ki-duotone ki-flash fs-3"></i>
                    Testar Conexão
                </button>
                <span id="connection_status" class="ms-3"></span>
            </div>
            
            <div class="separator my-10"></div>
            
            <!-- Passo 3: Seleção de Tabela -->
            <div class="mb-10" id="step_table" style="display:none;">
                <h4 class="mb-5">3. Selecionar Tabela</h4>
                
                <div class="mb-5">
                    <label class="form-label required">Tabela</label>
                    <select class="form-select" id="db_table" name="table_name" onchange="loadColumns()">
                        <option value="">Selecione...</option>
                    </select>
                </div>
            </div>
            
            <div class="separator my-10" id="sep_mapping" style="display:none;"></div>
            
            <!-- Passo 4: Mapeamento de Colunas -->
            <div class="mb-10" id="step_mapping" style="display:none;">
                <h4 class="mb-5">4. Mapear Colunas</h4>
                
                <div class="alert alert-warning">
                    <strong>Importante:</strong> Mapeie as colunas do banco externo para os campos do sistema.
                </div>
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label required">Nome do Contato</label>
                        <select class="form-select" id="map_name" required>
                            <option value="">Selecione a coluna...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">Telefone</label>
                        <select class="form-select" id="map_phone" required>
                            <option value="">Selecione a coluna...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email (opcional)</label>
                        <select class="form-select" id="map_email">
                            <option value="">Selecione a coluna...</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-5">
                    <button type="button" class="btn btn-light-info" onclick="showPreview()">
                        <i class="ki-duotone ki-eye fs-3"></i>
                        Preview dos Dados
                    </button>
                </div>
                
                <div id="preview_container" class="mt-5" style="display:none;"></div>
            </div>
            
            <div class="separator my-10" id="sep_schedule" style="display:none;"></div>
            
            <!-- Passo 5: Sincronização -->
            <div class="mb-10" id="step_schedule" style="display:none;">
                <h4 class="mb-5">5. Configurar Sincronização</h4>
                
                <div class="mb-5">
                    <label class="form-label">Frequência de Sincronização</label>
                    <select class="form-select" name="sync_frequency">
                        <option value="manual">Manual (sob demanda)</option>
                        <option value="hourly">A cada hora</option>
                        <option value="daily">Diariamente</option>
                        <option value="weekly">Semanalmente</option>
                    </select>
                </div>
                
                <div class="mb-5">
                    <label class="form-label">Filtro WHERE (opcional)</label>
                    <input type="text" class="form-control font-monospace" id="query_where" 
                           placeholder="Ex: status = 'ativo' AND cidade = 'São Paulo'" />
                    <div class="form-text">SQL WHERE sem a palavra WHERE</div>
                </div>
                
                <div class="mb-5">
                    <label class="form-label">Ordenação (opcional)</label>
                    <input type="text" class="form-control font-monospace" id="query_order" 
                           placeholder="Ex: created_at DESC" />
                    <div class="form-text">SQL ORDER BY sem as palavras ORDER BY</div>
                </div>
                
                <div class="mb-5">
                    <label class="form-label">Limite de Registros (opcional)</label>
                    <input type="number" class="form-control" id="query_limit" placeholder="Ex: 1000" />
                </div>
            </div>
            
            <div class="d-flex justify-content-end mt-10">
                <button type="button" class="btn btn-light me-3" onclick="window.history.back()">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_save" onclick="saveSource()" disabled>
                    <span class="indicator-label">Criar Fonte de Banco</span>
                    <span class="indicator-progress">Salvando...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </div>
            </div>
            <!-- ========== FIM SEÇÃO BANCO DE DADOS ========== -->
            
        </form>
        
    </div>
</div>

<script>
let tempSourceId = null;
let availableColumns = [];

// Mudar visibilidade das seções ao trocar tipo
document.getElementById('db_type').addEventListener('change', function() {
    const type = this.value;
    const sectionGoogleMaps = document.getElementById('section_google_maps');
    const sectionWooCommerce = document.getElementById('section_woocommerce');
    const sectionDatabase = document.getElementById('section_database');
    
    // Esconder todas primeiro
    sectionGoogleMaps.style.display = 'none';
    sectionWooCommerce.style.display = 'none';
    sectionDatabase.style.display = 'none';
    
    if (type === 'google_maps') {
        sectionGoogleMaps.style.display = 'block';
    } else if (type === 'woocommerce') {
        sectionWooCommerce.style.display = 'block';
    } else if (type === 'mysql' || type === 'postgresql') {
        sectionDatabase.style.display = 'block';
        
        // Atualizar porta padrão
        const port = type === 'postgresql' ? 5432 : 3306;
        document.getElementById('db_port').value = port;
    }
});

// ========== FUNÇÕES GOOGLE MAPS ==========

// Testar conexão com API do Google Maps
function testGoogleMapsConnection() {
    const btn = event.target;
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const provider = document.getElementById('gm_provider').value;
    
    fetch('/api/external-sources/test-google-maps', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ provider: provider })
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        const status = document.getElementById('gm_connection_status');
        if (result.success) {
            status.innerHTML = '<span class="badge badge-light-success"><i class="ki-duotone ki-check fs-6"></i> API Conectada</span>';
            toastr.success(result.message);
        } else {
            status.innerHTML = '<span class="badge badge-light-danger"><i class="ki-duotone ki-cross fs-6"></i> Erro</span>';
            toastr.error(result.message);
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede: ' + err.message);
    });
}

// Preview de busca no Google Maps
function previewGoogleMaps() {
    const keyword = document.getElementById('gm_keyword').value;
    const location = document.getElementById('gm_location').value;
    
    if (!keyword || !location) {
        toastr.warning('Preencha a palavra-chave e localização');
        return;
    }
    
    const btn = event.target;
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const searchConfig = {
        keyword: keyword,
        location: location,
        radius: parseInt(document.getElementById('gm_radius').value) || 5000,
        max_results: 5
    };
    
    const provider = document.getElementById('gm_provider').value;
    
    fetch('/api/external-sources/preview-google-maps', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ search_config: searchConfig, provider: provider })
    })
    .then(r => {
        if (r.status === 401) {
            toastr.error('Sessão expirada. Recarregue a página.');
            return { success: false, message: 'Sessão expirada' };
        }
        return r.json();
    })
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            renderGoogleMapsPreview(result.results);
            if (result.results.length > 0) {
                toastr.success(`${result.results.length} empresa(s) encontrada(s)!`);
            } else {
                toastr.info('Nenhuma empresa com telefone encontrada. Tente outra busca.');
            }
        } else {
            toastr.error(result.message || 'Erro ao buscar preview');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro: ' + err.message);
    });
}

// Renderizar preview do Google Maps
function renderGoogleMapsPreview(results) {
    const container = document.getElementById('gm_preview_container');
    
    if (!results || results.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">Nenhuma empresa com telefone encontrada. Tente outra palavra-chave ou localização.</div>';
        container.style.display = 'block';
        return;
    }
    
    let html = '<div class="card card-flush bg-light">';
    html += '<div class="card-header"><h5 class="card-title">Preview: ' + results.length + ' empresa(s) encontrada(s)</h5></div>';
    html += '<div class="card-body p-0"><div class="table-responsive">';
    html += '<table class="table table-sm table-row-bordered align-middle gs-0 gy-3 mb-0">';
    html += '<thead><tr class="fw-bold text-muted bg-light">';
    html += '<th class="ps-4">Nome</th><th>Telefone</th><th>Endereço</th><th>Categoria</th><th>Avaliação</th>';
    html += '</tr></thead><tbody>';
    
    results.forEach(r => {
        const rating = r.rating ? '⭐ ' + r.rating : '-';
        html += '<tr>';
        html += '<td class="ps-4"><strong>' + escapeHtml(r.name || '') + '</strong></td>';
        html += '<td><code>' + escapeHtml(r.phone || r.international_phone || '-') + '</code></td>';
        html += '<td class="text-muted">' + escapeHtml(r.address || '-').substring(0, 50) + '</td>';
        html += '<td><span class="badge badge-light">' + escapeHtml(r.category || '-') + '</span></td>';
        html += '<td>' + rating + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table></div></div></div>';
    
    container.innerHTML = html;
    container.style.display = 'block';
}

// Salvar fonte Google Maps
function saveGoogleMapsSource() {
    const keyword = document.getElementById('gm_keyword').value;
    const location = document.getElementById('gm_location').value;
    const name = document.querySelector('[name="name"]').value;
    
    if (!name || !keyword || !location) {
        toastr.warning('Preencha todos os campos obrigatórios');
        return;
    }
    
    const btn = document.getElementById('btn_save_gm');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const data = {
        name: name,
        type: 'google_maps',
        provider: document.getElementById('gm_provider').value,
        search_config: {
            keyword: keyword,
            location: location,
            radius: parseInt(document.getElementById('gm_radius').value) || 5000,
            max_results: parseInt(document.getElementById('gm_max_results').value) || 60,
            language: 'pt-BR'
        },
        sync_frequency: document.getElementById('gm_sync_frequency').value,
        status: 'active'
    };
    
    fetch('/external-sources', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Fonte Google Maps criada com sucesso!');
            setTimeout(() => {
                window.location.href = '/external-sources';
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao criar fonte');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede: ' + err.message);
    });
}

// ========== FUNÇÕES WOOCOMMERCE ==========

// Testar conexão com WooCommerce
function testWooCommerceConnection() {
    const btn = event.target;
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const storeUrl = document.getElementById('wc_store_url').value;
    const consumerKey = document.getElementById('wc_consumer_key').value;
    const consumerSecret = document.getElementById('wc_consumer_secret').value;
    
    if (!storeUrl || !consumerKey || !consumerSecret) {
        toastr.warning('Preencha todos os campos de conexão');
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        return;
    }
    
    fetch('/api/external-sources/test-woocommerce', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({
            store_url: storeUrl,
            consumer_key: consumerKey,
            consumer_secret: consumerSecret
        })
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        const status = document.getElementById('wc_connection_status');
        if (result.success) {
            status.innerHTML = '<span class="badge badge-light-success"><i class="ki-duotone ki-check fs-6"></i> Conectado</span>';
            toastr.success(result.message);
        } else {
            status.innerHTML = '<span class="badge badge-light-danger"><i class="ki-duotone ki-cross fs-6"></i> Erro</span>';
            toastr.error(result.message);
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede: ' + err.message);
    });
}

// Preview de clientes WooCommerce
function previewWooCommerce() {
    const storeUrl = document.getElementById('wc_store_url').value;
    const consumerKey = document.getElementById('wc_consumer_key').value;
    const consumerSecret = document.getElementById('wc_consumer_secret').value;
    
    if (!storeUrl || !consumerKey || !consumerSecret) {
        toastr.warning('Preencha os dados de conexão primeiro');
        return;
    }
    
    const container = document.getElementById('wc_preview_container');
    container.style.display = 'block';
    container.innerHTML = '<div class="d-flex align-items-center"><span class="spinner-border spinner-border-sm me-2"></span> Buscando clientes...</div>';
    
    fetch('/api/external-sources/preview-woocommerce', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({
            store_url: storeUrl,
            consumer_key: consumerKey,
            consumer_secret: consumerSecret
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            renderWooCommercePreview(result);
        } else {
            container.innerHTML = '<div class="alert alert-danger">' + (result.message || 'Erro ao buscar') + '</div>';
        }
    })
    .catch(err => {
        container.innerHTML = '<div class="alert alert-danger">Erro de rede: ' + err.message + '</div>';
    });
}

// Renderizar preview WooCommerce
function renderWooCommercePreview(result) {
    const container = document.getElementById('wc_preview_container');
    
    let html = '<div class="alert alert-success mb-3">';
    html += '<strong>Total de clientes na loja:</strong> ' + (result.total || 0);
    html += '</div>';
    
    if (result.customers && result.customers.length > 0) {
        html += '<div class="table-responsive">';
        html += '<table class="table table-sm table-bordered">';
        html += '<thead><tr><th>Nome</th><th>Email</th><th>Telefone</th><th>Cidade</th><th>Pedidos</th><th>Total Gasto</th></tr></thead>';
        html += '<tbody>';
        
        result.customers.forEach(c => {
            html += '<tr>';
            html += '<td>' + (c.name || '-') + '</td>';
            html += '<td>' + (c.email || '-') + '</td>';
            html += '<td>' + (c.phone || '<span class="text-muted">Não informado</span>') + '</td>';
            html += '<td>' + (c.city || '-') + '</td>';
            html += '<td class="text-center">' + (c.orders_count || 0) + '</td>';
            html += '<td class="text-end">R$ ' + parseFloat(c.total_spent || 0).toFixed(2) + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table></div>';
    }
    
    container.innerHTML = html;
}

// Salvar fonte WooCommerce
function saveWooCommerceSource() {
    const name = document.querySelector('[name="name"]').value;
    const storeUrl = document.getElementById('wc_store_url').value;
    const consumerKey = document.getElementById('wc_consumer_key').value;
    const consumerSecret = document.getElementById('wc_consumer_secret').value;
    
    if (!name || !storeUrl || !consumerKey || !consumerSecret) {
        toastr.warning('Preencha todos os campos obrigatórios');
        return;
    }
    
    const btn = document.getElementById('btn_save_wc');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const data = {
        name: name,
        type: 'woocommerce',
        search_config: {
            store_url: storeUrl,
            consumer_key: consumerKey,
            consumer_secret: consumerSecret,
            import_type: document.getElementById('wc_import_type').value,
            days_back: parseInt(document.getElementById('wc_days_back').value) || 30,
            min_orders: parseInt(document.getElementById('wc_min_orders').value) || 0
        },
        sync_frequency: document.getElementById('wc_sync_frequency').value,
        status: 'active'
    };
    
    fetch('/external-sources', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Fonte WooCommerce criada com sucesso!');
            setTimeout(() => {
                window.location.href = '/external-sources';
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao criar fonte');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede: ' + err.message);
    });
}

// ========== FUNÇÕES BANCO DE DADOS ==========

// Testar conexão
function testConnection() {
    const btn = event.target;
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const config = {
        host: document.getElementById('db_host').value,
        port: parseInt(document.getElementById('db_port').value),
        database: document.getElementById('db_database').value,
        username: document.getElementById('db_username').value,
        password: document.getElementById('db_password').value
    };
    
    const type = document.getElementById('db_type').value;
    
    console.log('Testando conexão com:', {
        type: type,
        host: config.host,
        port: config.port,
        database: config.database,
        username: config.username,
        has_password: !!config.password
    });
    
    fetch('/api/external-sources/test-connection', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({connection_config: config, type: type})
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.text().then(text => {
            console.log('Response raw:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Erro ao fazer parse do JSON:', e);
                throw new Error('Resposta não é um JSON válido: ' + text.substring(0, 200));
            }
        });
    })
    .then(result => {
        console.log('Resultado do teste:', result);
        
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        const status = document.getElementById('connection_status');
        if (result.success) {
            status.innerHTML = '<span class="badge badge-light-success"><i class="ki-duotone ki-check fs-6"></i> Conectado</span>';
            toastr.success(result.message);
            
            // Criar fonte temporária para listar tabelas
            createTempSource(config, type);
        } else {
            status.innerHTML = '<span class="badge badge-light-danger"><i class="ki-duotone ki-cross fs-6"></i> Erro</span>';
            const errorMsg = result.message || 'Erro desconhecido';
            toastr.error(errorMsg + (result.error_detail ? ' - ' + result.error_detail : ''));
            console.error('Erro ao testar conexão:', result);
        }
    })
    .catch(err => {
        console.error('Erro de rede ou parse:', err);
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        const status = document.getElementById('connection_status');
        status.innerHTML = '<span class="badge badge-light-danger"><i class="ki-duotone ki-cross fs-6"></i> Erro</span>';
        
        toastr.error('Erro de rede: ' + err.message + ' - Verifique os logs em view-all-logs.php');
    });
}

// Criar fonte temporária para buscar tabelas/colunas
function createTempSource(config, type) {
    const data = {
        name: 'TEMP_CONFIG_' + Date.now(),
        type: type,
        connection_config: config,
        status: 'inactive',
        table_name: null,
        column_mapping: null,
        query_config: null
    };
    
    fetch('/external-sources', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            tempSourceId = result.source_id;
            console.log('Fonte temporária criada:', tempSourceId);
            loadTables();
        } else {
            toastr.error('Erro ao criar configuração temporária');
        }
    })
    .catch(err => {
        console.error('Erro ao criar temp source:', err);
        toastr.error('Erro ao preparar listagem de tabelas');
    });
}

// Carregar tabelas
function loadTables() {
    if (!tempSourceId) return;
    
    toastr.info('Carregando tabelas do banco externo...');
    
    fetch(`/api/external-sources/${tempSourceId}/tables`)
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                const select = document.getElementById('db_table');
                select.innerHTML = '<option value="">Selecione uma tabela...</option>';
                
                if (result.tables && result.tables.length > 0) {
                    result.tables.forEach(table => {
                        const option = document.createElement('option');
                        option.value = table;
                        option.textContent = table;
                        select.appendChild(option);
                    });
                    
                    document.getElementById('step_table').style.display = 'block';
                    toastr.success(`${result.tables.length} tabela(s) encontrada(s)!`);
                    
                    // Scroll suave até o próximo passo
                    setTimeout(() => {
                        document.getElementById('step_table').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                } else {
                    toastr.warning('Nenhuma tabela encontrada neste banco');
                }
            } else {
                toastr.error(result.message || 'Erro ao listar tabelas');
            }
        })
        .catch(err => {
            toastr.error('Erro ao buscar tabelas');
            console.error(err);
        });
}

// Carregar colunas
function loadColumns() {
    const table = document.getElementById('db_table').value;
    if (!table || !tempSourceId) return;
    
    toastr.info('Carregando colunas da tabela...');
    
    fetch(`/api/external-sources/${tempSourceId}/columns?table=${encodeURIComponent(table)}`)
        .then(r => r.json())
        .then(result => {
            if (result.success && result.columns) {
                availableColumns = result.columns;
                
                // Preencher selects de mapeamento
                ['map_name', 'map_phone', 'map_email'].forEach(selectId => {
                    const select = document.getElementById(selectId);
                    select.innerHTML = '<option value="">Selecione a coluna...</option>';
                    
                    result.columns.forEach(col => {
                        const option = document.createElement('option');
                        option.value = col.name;
                        option.textContent = `${col.name} (${col.type})`;
                        select.appendChild(option);
                    });
                });
                
                // Mostrar próximos passos
                document.getElementById('sep_mapping').style.display = 'block';
                document.getElementById('step_mapping').style.display = 'block';
                document.getElementById('sep_schedule').style.display = 'block';
                document.getElementById('step_schedule').style.display = 'block';
                document.getElementById('btn_save').disabled = false;
                
                toastr.success(`${result.columns.length} coluna(s) encontrada(s)!`);
                
                // Scroll suave até o mapeamento
                setTimeout(() => {
                    document.getElementById('step_mapping').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 300);
            } else {
                toastr.error(result.message || 'Erro ao listar colunas');
            }
        })
        .catch(err => {
            toastr.error('Erro ao buscar colunas');
            console.error(err);
        });
}

// Preview dos dados
function showPreview() {
    if (!tempSourceId) return;
    
    const table = document.getElementById('db_table').value;
    if (!table) {
        toastr.warning('Selecione uma tabela primeiro');
        return;
    }
    
    const btn = event.target;
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    // Passa a tabela como parâmetro (já que a fonte temporária não tem tabela configurada)
    fetch(`/api/external-sources/${tempSourceId}/preview?table=${encodeURIComponent(table)}`)
        .then(r => r.json())
        .then(result => {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            
            if (result.success && result.rows) {
                renderPreview(result.rows);
                toastr.success(`${result.rows.length} registro(s) carregado(s)`);
            } else {
                toastr.error(result.message || 'Erro ao buscar preview');
            }
        })
        .catch(err => {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            toastr.error('Erro ao buscar preview');
            console.error(err);
        });
}

function renderPreview(rows) {
    const container = document.getElementById('preview_container');
    
    if (!rows || rows.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">Nenhum dado encontrado na tabela</div>';
        container.style.display = 'block';
        return;
    }
    
    const columns = Object.keys(rows[0]);
    
    let html = '<div class="card card-flush bg-light">';
    html += '<div class="card-header">';
    html += '<h5 class="card-title">Preview dos Dados (primeiras 10 linhas)</h5>';
    html += '</div>';
    html += '<div class="card-body p-0">';
    html += '<div class="table-responsive">';
    html += '<table class="table table-sm table-row-bordered table-row-gray-100 align-middle gs-0 gy-3 mb-0">';
    html += '<thead>';
    html += '<tr class="fw-bold text-muted bg-light">';
    html += columns.map(col => `<th class="ps-4 min-w-100px text-nowrap">${col}</th>`).join('');
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    rows.forEach((row, idx) => {
        html += '<tr>';
        html += columns.map(col => {
            const value = row[col];
            const displayValue = value === null ? '<span class="text-muted">NULL</span>' : 
                                (value === '' ? '<span class="text-muted">vazio</span>' : 
                                escapeHtml(String(value)));
            return `<td class="ps-4 text-nowrap"><span class="text-gray-800 fw-normal">${displayValue}</span></td>`;
        }).join('');
        html += '</tr>';
    });
    
    html += '</tbody></table></div></div></div>';
    
    container.innerHTML = html;
    container.style.display = 'block';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Salvar fonte
function saveSource() {
    const btn = document.getElementById('btn_save');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const connectionConfig = {
        host: document.getElementById('db_host').value,
        port: parseInt(document.getElementById('db_port').value),
        database: document.getElementById('db_database').value,
        username: document.getElementById('db_username').value,
        password: document.getElementById('db_password').value
    };
    
    const columnMapping = {
        name: document.getElementById('map_name').value,
        phone: document.getElementById('map_phone').value,
        email: document.getElementById('map_email').value || null
    };
    
    const queryConfig = {
        where: document.getElementById('query_where').value || null,
        order_by: document.getElementById('query_order').value || null,
        limit: document.getElementById('query_limit').value ? parseInt(document.getElementById('query_limit').value) : null
    };
    
    const data = {
        name: document.querySelector('[name="name"]').value,
        type: document.querySelector('[name="type"]').value,
        table_name: document.getElementById('db_table').value,
        connection_config: connectionConfig,
        column_mapping: columnMapping,
        query_config: queryConfig,
        sync_frequency: document.querySelector('[name="sync_frequency"]').value,
        status: 'active'
    };
    
    // Se já criou fonte temporária, deletar ela e criar nova com dados completos
    if (tempSourceId) {
        fetch(`/external-sources/${tempSourceId}`, { method: 'DELETE' })
            .catch(err => console.log('Erro ao deletar temp:', err));
    }
    
    fetch('/external-sources', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Fonte criada com sucesso!');
            setTimeout(() => {
                window.location.href = '/external-sources';
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao criar fonte');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede: ' + err.message);
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
