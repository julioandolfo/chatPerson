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
