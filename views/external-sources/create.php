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
                <strong>Sincronização Automática:</strong> Conecte bancos de dados externos (MySQL, PostgreSQL) para importar contatos automaticamente.
            </div>
        </div>
        
        <form id="source_form">
            
            <!-- Passo 1: Informações Básicas -->
            <div class="mb-10">
                <h4 class="mb-5">1. Informações Básicas</h4>
                
                <div class="mb-5">
                    <label class="form-label required">Nome da Fonte</label>
                    <input type="text" class="form-control" name="name" placeholder="Ex: CRM Principal" required />
                </div>
                
                <div class="mb-5">
                    <label class="form-label required">Tipo de Banco</label>
                    <select class="form-select" name="type" id="db_type" required>
                        <option value="mysql">MySQL</option>
                        <option value="postgresql">PostgreSQL</option>
                    </select>
                </div>
            </div>
            
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
                    <span class="indicator-label">Criar Fonte</span>
                    <span class="indicator-progress">Salvando...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </div>
        </form>
        
    </div>
</div>

<script>
let tempSourceId = null;
let availableColumns = [];

// Mudar porta padrão ao trocar tipo
document.getElementById('db_type').addEventListener('change', function() {
    const port = this.value === 'postgresql' ? 5432 : 3306;
    document.getElementById('db_port').value = port;
});

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
    
    fetch('/api/external-sources/test-connection', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({connection_config: config, type: type})
    })
    .then(r => r.json())
    .then(result => {
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
            toastr.error(result.message);
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede');
    });
}

// Criar fonte temporária
function createTempSource(config, type) {
    const data = {
        name: 'TEMP_' + Date.now(),
        type: type,
        connection_config: config,
        status: 'inactive'
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
            loadTables();
        }
    });
}

// Carregar tabelas
function loadTables() {
    if (!tempSourceId) return;
    
    fetch(`/api/external-sources/${tempSourceId}/tables`)
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                const select = document.getElementById('db_table');
                select.innerHTML = '<option value="">Selecione...</option>';
                
                result.tables.forEach(table => {
                    const option = document.createElement('option');
                    option.value = table;
                    option.textContent = table;
                    select.appendChild(option);
                });
                
                document.getElementById('step_table').style.display = 'block';
            }
        });
}

// Carregar colunas
function loadColumns() {
    const table = document.getElementById('db_table').value;
    if (!table || !tempSourceId) return;
    
    fetch(`/api/external-sources/${tempSourceId}/columns?table=${encodeURIComponent(table)}`)
        .then(r => r.json())
        .then(result => {
            if (result.success) {
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
                
                document.getElementById('sep_mapping').style.display = 'block';
                document.getElementById('step_mapping').style.display = 'block';
                document.getElementById('sep_schedule').style.display = 'block';
                document.getElementById('step_schedule').style.display = 'block';
                document.getElementById('btn_save').disabled = false;
            }
        });
}

// Preview dos dados
function showPreview() {
    if (!tempSourceId) return;
    
    const btn = event.target;
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    fetch(`/api/external-sources/${tempSourceId}/preview`)
        .then(r => r.json())
        .then(result => {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            
            if (result.success && result.rows) {
                renderPreview(result.rows);
            } else {
                toastr.error(result.message || 'Erro ao buscar preview');
            }
        })
        .catch(err => {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            toastr.error('Erro ao buscar preview');
        });
}

function renderPreview(rows) {
    const container = document.getElementById('preview_container');
    
    if (!rows || rows.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">Nenhum dado encontrado</div>';
        container.style.display = 'block';
        return;
    }
    
    const columns = Object.keys(rows[0]);
    
    let html = '<div class="card card-flush bg-light"><div class="card-body"><h5 class="mb-5">Preview (10 primeiras linhas)</h5>';
    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
    html += '<thead><tr>' + columns.map(col => `<th class="text-nowrap">${col}</th>`).join('') + '</tr></thead>';
    html += '<tbody>';
    
    rows.forEach(row => {
        html += '<tr>' + columns.map(col => `<td class="text-nowrap">${row[col] || '-'}</td>`).join('') + '</tr>';
    });
    
    html += '</tbody></table></div></div></div>';
    
    container.innerHTML = html;
    container.style.display = 'block';
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
    
    // Se já criou temp, atualizar, senão criar
    const method = tempSourceId ? 'PUT' : 'POST';
    const url = tempSourceId ? `/external-sources/${tempSourceId}` : '/external-sources';
    
    fetch(url, {
        method: method,
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
                window.location.href = `/external-sources/${result.source_id || tempSourceId}`;
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao criar fonte');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede');
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
