<?php
$layout = 'layouts.metronic.app';
$title = 'Editar Fonte de Dados Externa';

$searchConfig = json_decode($source['search_config'] ?? '{}', true);
$connectionConfig = json_decode($source['connection_config'] ?? '{}', true);

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Editar Fonte: <?= htmlspecialchars($source['name']) ?></h3>
        <div class="card-toolbar">
            <span class="badge badge-light-<?= $source['type'] === 'google_maps' ? 'success' : ($source['type'] === 'woocommerce' ? 'info' : 'primary') ?>">
                <?= strtoupper($source['type']) ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        
        <form id="edit_source_form">
            <input type="hidden" name="id" value="<?= $source['id'] ?>">
            <input type="hidden" name="type" value="<?= $source['type'] ?>">
            
            <!-- Informações Básicas -->
            <div class="mb-10">
                <h4 class="mb-5">Informações Básicas</h4>
                
                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label required">Nome da Fonte</label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($source['name']) ?>" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= $source['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inactive" <?= $source['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                </div>
                
                <div class="row g-5 mt-3">
                    <div class="col-md-6">
                        <label class="form-label">Frequência de Sincronização</label>
                        <select class="form-select" name="sync_frequency">
                            <option value="manual" <?= ($source['sync_frequency'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual</option>
                            <option value="daily" <?= ($source['sync_frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>Diariamente</option>
                            <option value="weekly" <?= ($source['sync_frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Semanalmente</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php if ($source['type'] === 'google_maps'): ?>
            <!-- Configuração Google Maps -->
            <div class="separator my-10"></div>
            <div class="mb-10">
                <h4 class="mb-5">Configuração de Busca - Google Maps</h4>

                <div class="alert alert-info d-flex align-items-start mb-5">
                    <i class="ki-duotone ki-abstract-26 fs-2 text-info me-3 mt-1"><span class="path1"></span><span class="path2"></span></i>
                    <div class="fs-7">
                        A IA <strong>não navega no Google</strong>. Ela só sugere <strong>palavra-chave + localização</strong> para a API Places.
                        Os contatos continuam sendo buscados pelo Google Maps / Outscraper como hoje.
                    </div>
                </div>

                <div class="card card-bordered bg-light mb-5">
                    <div class="card-body py-4">
                        <label class="form-label fw-bold">Descreva o público (IA sugere termos)</label>
                        <textarea class="form-control mb-2" id="gm_edit_ai_description" rows="2" placeholder="Ex: Oficinas mecânicas especializadas em diesel no Rio Grande do Sul"></textarea>
                        <button type="button" class="btn btn-sm btn-primary" onclick="suggestProspectTermsEdit()">Sugerir com IA</button>
                        <span id="gm_edit_ai_status" class="ms-2 text-muted fs-8"></span>
                        <div id="gm_edit_ai_alternatives" class="mt-3" style="display:none;"></div>
                    </div>
                </div>
                
                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label required">Palavra-chave</label>
                        <input type="text" class="form-control" name="keyword" id="gm_edit_keyword" value="<?= htmlspecialchars($searchConfig['keyword'] ?? '') ?>" placeholder="Ex: dentistas, restaurantes" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Localização</label>
                        <input type="text" class="form-control" name="location" id="gm_edit_location" value="<?= htmlspecialchars($searchConfig['location'] ?? '') ?>" placeholder="Ex: São Paulo, SP" />
                    </div>
                </div>
                
                <div class="row g-5 mt-3">
                    <div class="col-md-3">
                        <label class="form-label">Raio da área total (metros)</label>
                        <input type="number" class="form-control" name="radius" value="<?= (int)($searchConfig['radius'] ?? 50000) ?>" min="100" max="50000" />
                        <div class="form-text text-muted edit-radius-hint">
                            <?= !empty($searchConfig['use_grid']) ? 'Raio total dividido pela grade.' : 'Máx: 50.000m (50km)' ?>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Limite de Resultados</label>
                        <input type="number" class="form-control" name="max_results" value="<?= (int)($searchConfig['max_results'] ?? 60) ?>" min="10" max="500" />
                        <div class="form-text text-muted">Google Places: máx 60</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Provider</label>
                        <select class="form-select" name="provider">
                            <option value="google_places" <?= ($source['provider'] ?? 'google_places') === 'google_places' ? 'selected' : '' ?>>Google Places API</option>
                            <option value="outscraper" <?= ($source['provider'] ?? '') === 'outscraper' ? 'selected' : '' ?>>Outscraper</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label d-block">Busca em Grade</label>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="use_grid" value="1"
                                <?= !empty($searchConfig['use_grid']) ? 'checked' : '' ?>
                                onchange="toggleEditGridOptions(this)">
                            <label class="form-check-label">Ativar</label>
                        </div>
                        <div class="form-text">Para cidades grandes</div>
                    </div>
                    <div class="col-md-2" id="edit_grid_size_wrap" style="<?= !empty($searchConfig['use_grid']) ? '' : 'display:none;' ?>">
                        <label class="form-label">Tamanho da Grade</label>
                        <select class="form-select" name="grid_size">
                            <option value="2" <?= (int)($searchConfig['grid_size'] ?? 3) === 2 ? 'selected' : '' ?>>2×2 (240 res.)</option>
                            <option value="3" <?= (int)($searchConfig['grid_size'] ?? 3) === 3 ? 'selected' : '' ?>>3×3 (540 res.)</option>
                            <option value="4" <?= (int)($searchConfig['grid_size'] ?? 3) === 4 ? 'selected' : '' ?>>4×4 (960 res.)</option>
                            <option value="5" <?= (int)($searchConfig['grid_size'] ?? 3) === 5 ? 'selected' : '' ?>>5×5 (1500 res.)</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label d-block">Sem telefone</label>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="include_no_phone" value="1" <?= !empty($searchConfig['include_no_phone']) ? 'checked' : '' ?>>
                            <label class="form-check-label">Incluir</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label d-block">Ao re-sincronizar</label>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="force_update" value="1" <?= !empty($searchConfig['force_update']) ? 'checked' : '' ?>>
                            <label class="form-check-label">Atualizar existentes</label>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($source['type'] === 'woocommerce'): ?>
            <!-- Configuração WooCommerce -->
            <div class="separator my-10"></div>
            <div class="mb-10">
                <h4 class="mb-5">Configuração - WooCommerce</h4>
                
                <div class="mb-5">
                    <label class="form-label required">URL da Loja</label>
                    <input type="url" class="form-control" name="store_url" value="<?= htmlspecialchars($searchConfig['store_url'] ?? '') ?>" placeholder="https://minhaloja.com.br" />
                </div>
                
                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label required">Consumer Key</label>
                        <input type="text" class="form-control" name="consumer_key" value="<?= htmlspecialchars($searchConfig['consumer_key'] ?? '') ?>" placeholder="ck_..." />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Consumer Secret</label>
                        <input type="password" class="form-control" name="consumer_secret" value="<?= htmlspecialchars($searchConfig['consumer_secret'] ?? '') ?>" placeholder="cs_..." />
                    </div>
                </div>
                
                <div class="row g-5 mt-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo de Importação</label>
                        <select class="form-select" name="import_type">
                            <option value="customers" <?= ($searchConfig['import_type'] ?? '') === 'customers' ? 'selected' : '' ?>>Clientes Cadastrados</option>
                            <option value="orders" <?= ($searchConfig['import_type'] ?? 'orders') === 'orders' ? 'selected' : '' ?>>Clientes que Compraram</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Período (dias)</label>
                        <input type="number" class="form-control" name="days_back" value="<?= (int)($searchConfig['days_back'] ?? 30) ?>" min="1" max="365" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mínimo de Pedidos</label>
                        <input type="number" class="form-control" name="min_orders" value="<?= (int)($searchConfig['min_orders'] ?? 0) ?>" min="0" />
                    </div>
                </div>
                
                <div class="mt-5">
                    <button type="button" class="btn btn-light-primary" onclick="testWooCommerceConnection()">
                        <i class="ki-duotone ki-flash fs-3"></i>
                        Testar Conexão
                    </button>
                    <span id="wc_test_status" class="ms-3"></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($source['type'] === 'mysql' || $source['type'] === 'postgresql'): ?>
            <!-- Configuração Banco de Dados -->
            <div class="separator my-10"></div>
            <div class="mb-10">
                <h4 class="mb-5">Configuração de Conexão</h4>
                
                <div class="row g-5">
                    <div class="col-md-6">
                        <label class="form-label required">Host</label>
                        <input type="text" class="form-control" name="host" value="<?= htmlspecialchars($connectionConfig['host'] ?? '') ?>" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Porta</label>
                        <input type="number" class="form-control" name="port" value="<?= (int)($connectionConfig['port'] ?? 3306) ?>" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Banco</label>
                        <input type="text" class="form-control" name="database" value="<?= htmlspecialchars($connectionConfig['database'] ?? '') ?>" />
                    </div>
                </div>
                
                <div class="row g-5 mt-3">
                    <div class="col-md-6">
                        <label class="form-label required">Usuário</label>
                        <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($connectionConfig['username'] ?? '') ?>" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">Senha</label>
                        <input type="password" class="form-control" name="password" value="<?= htmlspecialchars($connectionConfig['password'] ?? '') ?>" />
                    </div>
                </div>
                
                <div class="row g-5 mt-3">
                    <div class="col-md-6">
                        <label class="form-label">Tabela</label>
                        <input type="text" class="form-control" name="table_name" value="<?= htmlspecialchars($source['table_name'] ?? '') ?>" />
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Estatísticas -->
            <div class="separator my-10"></div>
            <div class="mb-10">
                <h4 class="mb-5">Estatísticas</h4>
                
                <div class="d-flex flex-wrap gap-5">
                    <div class="border border-gray-300 border-dashed rounded py-3 px-5 text-center">
                        <div class="fs-2 fw-bold text-gray-800"><?= number_format($source['total_records'] ?? 0) ?></div>
                        <div class="text-muted fs-7">Registros Totais</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded py-3 px-5 text-center">
                        <div class="fs-2 fw-bold text-gray-800"><?= number_format($source['total_synced'] ?? 0) ?></div>
                        <div class="text-muted fs-7">Sincronizados</div>
                    </div>
                    <div class="border border-gray-300 border-dashed rounded py-3 px-5 text-center">
                        <div class="fs-6 fw-bold text-gray-800">
                            <?= $source['last_sync_at'] ? date('d/m/Y H:i', strtotime($source['last_sync_at'])) : 'Nunca' ?>
                        </div>
                        <div class="text-muted fs-7">Última Sync</div>
                    </div>
                </div>
            </div>
            
            <!-- Botões -->
            <div class="d-flex justify-content-end gap-3">
                <a href="<?= \App\Helpers\Url::to('/external-sources') ?>" class="btn btn-light">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="btn_save">
                    <span class="indicator-label">Salvar Alterações</span>
                    <span class="indicator-progress">Salvando...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </div>
        </form>
        
    </div>
</div>

<script>
document.getElementById('edit_source_form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn_save');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    const formData = new FormData(this);
    const type = formData.get('type');
    
    // Montar dados baseado no tipo
    let data = {
        name: formData.get('name'),
        status: formData.get('status'),
        sync_frequency: formData.get('sync_frequency')
    };
    
    if (type === 'google_maps') {
        data.provider = formData.get('provider');
        data.search_config = {
            keyword: formData.get('keyword'),
            location: formData.get('location'),
            radius: parseInt(formData.get('radius')) || 50000,
            max_results: parseInt(formData.get('max_results')) || 60,
            include_no_phone: formData.get('include_no_phone') === '1',
            force_update: formData.get('force_update') === '1',
            use_grid: formData.get('use_grid') === '1',
            grid_size: parseInt(formData.get('grid_size') || '3'),
            language: 'pt-BR'
        };
    } else if (type === 'woocommerce') {
        data.search_config = {
            store_url: formData.get('store_url'),
            consumer_key: formData.get('consumer_key'),
            consumer_secret: formData.get('consumer_secret'),
            import_type: formData.get('import_type'),
            days_back: parseInt(formData.get('days_back')) || 30,
            min_orders: parseInt(formData.get('min_orders')) || 0
        };
    } else {
        data.connection_config = {
            host: formData.get('host'),
            port: parseInt(formData.get('port')) || 3306,
            database: formData.get('database'),
            username: formData.get('username'),
            password: formData.get('password')
        };
        data.table_name = formData.get('table_name');
    }
    
    fetch('/external-sources/<?= $source['id'] ?>', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (result.success) {
            toastr.success('Fonte atualizada com sucesso!');
            setTimeout(() => {
                window.location.href = '/external-sources';
            }, 1000);
        } else {
            toastr.error(result.message || 'Erro ao atualizar');
        }
    })
    .catch(err => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        toastr.error('Erro de rede: ' + err.message);
    });
});

function testWooCommerceConnection() {
    const storeUrl = document.querySelector('[name="store_url"]').value;
    const consumerKey = document.querySelector('[name="consumer_key"]').value;
    const consumerSecret = document.querySelector('[name="consumer_secret"]').value;
    
    if (!storeUrl || !consumerKey || !consumerSecret) {
        toastr.warning('Preencha todos os campos de conexão');
        return;
    }
    
    const status = document.getElementById('wc_test_status');
    status.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testando...';
    
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
        if (result.success) {
            status.innerHTML = '<span class="badge badge-light-success">Conectado!</span>';
            toastr.success(result.message);
        } else {
            status.innerHTML = '<span class="badge badge-light-danger">Erro</span>';
            toastr.error(result.message);
        }
    })
    .catch(err => {
        status.innerHTML = '<span class="badge badge-light-danger">Erro</span>';
        toastr.error('Erro de rede');
    });
}

function suggestProspectTermsEdit() {
    const desc = document.getElementById('gm_edit_ai_description')?.value?.trim() || '';
    const st = document.getElementById('gm_edit_ai_status');
    const altWrap = document.getElementById('gm_edit_ai_alternatives');
    if (!desc) {
        if (typeof toastr !== 'undefined') toastr.warning('Descreva o público-alvo.');
        return;
    }
    if (st) st.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    if (altWrap) { altWrap.style.display = 'none'; altWrap.innerHTML = ''; }

    fetch('/api/external-sources/suggest-prospect-terms', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ description: desc })
    })
    .then(r => r.json())
    .then(data => {
        if (st) st.textContent = '';
        if (!data.success) {
            if (typeof toastr !== 'undefined') toastr.error(data.message || 'Erro');
            return;
        }
        const kw = document.getElementById('gm_edit_keyword');
        const loc = document.getElementById('gm_edit_location');
        if (kw) kw.value = data.keyword || '';
        if (loc) loc.value = data.location || '';
        if (typeof toastr !== 'undefined') toastr.success('Sugestão aplicada. Salve a fonte para persistir.');

        if (altWrap && Array.isArray(data.alternatives) && data.alternatives.length > 0) {
            altWrap.innerHTML = '';
            const t = document.createElement('div');
            t.className = 'fw-bold mb-2 fs-7';
            t.textContent = 'Alternativas:';
            altWrap.appendChild(t);
            const row = document.createElement('div');
            row.className = 'd-flex flex-wrap gap-2';
            data.alternatives.forEach(a => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-light';
                btn.textContent = (a.keyword || '') + ' · ' + (a.location || '');
                btn.addEventListener('click', () => {
                    if (kw) kw.value = a.keyword || '';
                    if (loc) loc.value = a.location || '';
                    if (typeof toastr !== 'undefined') toastr.info('Aplicado');
                });
                row.appendChild(btn);
            });
            altWrap.appendChild(row);
            altWrap.style.display = 'block';
        }
    })
    .catch(() => { if (st) st.textContent = ''; if (typeof toastr !== 'undefined') toastr.error('Rede'); });
}

function toggleEditGridOptions(checkbox) {
    const wrap = document.getElementById('edit_grid_size_wrap');
    const hint = document.querySelector('.edit-radius-hint');
    if (wrap) wrap.style.display = checkbox.checked ? 'block' : 'none';
    if (hint) {
        hint.textContent = checkbox.checked
            ? 'Raio total dividido pela grade.'
            : 'Máx: 50.000m (50km)';
    }
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
