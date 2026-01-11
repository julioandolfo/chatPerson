<?php
$layout = 'layouts.metronic.app';
$title = 'WooCommerce - Integrações';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Integrações WooCommerce</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('integrations.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_woocommerce">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Integração
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="ki-duotone ki-information-5 fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <span class="ms-2"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (empty($integrations)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-shopping-cart fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma integração WooCommerce configurada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova integração com sua loja WooCommerce.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th class="min-w-150px">Nome</th>
                            <th class="min-w-200px">URL da Loja</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-150px">Última Sincronização</th>
                            <th class="min-w-100px text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($integrations as $integration): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-gray-800"><?= htmlspecialchars($integration['name']) ?></span>
                                </td>
                                <td>
                                    <span class="text-muted fs-7"><?= htmlspecialchars($integration['woocommerce_url']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'active' => 'success',
                                        'inactive' => 'warning',
                                        'error' => 'danger'
                                    ];
                                    $statusText = [
                                        'active' => 'Ativa',
                                        'inactive' => 'Inativa',
                                        'error' => 'Erro'
                                    ];
                                    $currentStatus = $integration['status'] ?? 'inactive';
                                    ?>
                                    <span class="badge badge-light-<?= $statusClass[$currentStatus] ?? 'warning' ?>">
                                        <?= $statusText[$currentStatus] ?? 'Desconhecido' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($integration['last_sync_at'])): ?>
                                        <span class="text-muted fs-7">
                                            <?= date('d/m/Y H:i', strtotime($integration['last_sync_at'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fs-7">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-icon btn-light-info btn-sm me-2" 
                                            onclick="testConnection(<?= $integration['id'] ?>)" 
                                            title="Testar Conexão">
                                        <i class="ki-duotone ki-check fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button type="button" class="btn btn-icon btn-light-warning btn-sm me-2" 
                                            onclick="editIntegration(<?= $integration['id'] ?>, <?= htmlspecialchars(json_encode($integration), ENT_QUOTES) ?>)" 
                                            title="Editar">
                                        <i class="ki-duotone ki-pencil fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <?php if (\App\Helpers\Permission::can('integrations.delete')): ?>
                                    <button type="button" class="btn btn-icon btn-light-danger btn-sm" 
                                            onclick="deleteIntegration(<?= $integration['id'] ?>)" 
                                            title="Deletar">
                                        <i class="ki-duotone ki-trash fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<!-- Modal: Nova Integração -->
<div class="modal fade" id="kt_modal_new_woocommerce" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Integração WooCommerce</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="form_new_woocommerce" onsubmit="saveIntegration(event)">
                <div class="modal-body">
                    <div class="mb-5">
                        <label class="form-label required">Nome da Integração</label>
                        <input type="text" class="form-control" name="name" required placeholder="Ex: Loja Principal">
                        <div class="form-text">Nome para identificar esta integração</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">URL da Loja WooCommerce</label>
                        <input type="url" class="form-control" name="woocommerce_url" required placeholder="https://sualoja.com.br">
                        <div class="form-text">URL completa da sua loja (sem barra no final)</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Consumer Key</label>
                            <input type="text" class="form-control" name="consumer_key" required>
                            <div class="form-text">Chave de API do WooCommerce</div>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Consumer Secret</label>
                            <input type="password" class="form-control" name="consumer_secret" required>
                            <div class="form-text">Secret da API do WooCommerce</div>
                        </div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <h4 class="fw-bold mb-4">Mapeamento de Campos</h4>
                    <p class="text-muted fs-7 mb-4">Configure quais campos do contato usar para buscar pedidos no WooCommerce</p>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="field_mapping[phone][enabled]" id="field_phone_enabled" checked>
                            <label class="form-check-label" for="field_phone_enabled">
                                <span class="fw-bold">Telefone</span>
                                <span class="text-muted fs-7 d-block">Buscar pedidos pelo telefone do contato</span>
                            </label>
                        </div>
                        <div class="ms-8">
                            <label class="form-label fs-7">Campo no WooCommerce</label>
                            <input type="text" class="form-control form-control-sm" name="field_mapping[phone][woocommerce_field]" value="billing.phone" placeholder="billing.phone">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="field_mapping[email][enabled]" id="field_email_enabled" checked>
                            <label class="form-check-label" for="field_email_enabled">
                                <span class="fw-bold">Email</span>
                                <span class="text-muted fs-7 d-block">Buscar pedidos pelo email do contato</span>
                            </label>
                        </div>
                        <div class="ms-8">
                            <label class="form-label fs-7">Campo no WooCommerce</label>
                            <input type="text" class="form-control form-control-sm" name="field_mapping[email][woocommerce_field]" value="billing.email" placeholder="billing.email">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="field_mapping[name][enabled]" id="field_name_enabled">
                            <label class="form-check-label" for="field_name_enabled">
                                <span class="fw-bold">Nome</span>
                                <span class="text-muted fs-7 d-block">Buscar pedidos pelo nome do contato</span>
                            </label>
                        </div>
                        <div class="ms-8">
                            <label class="form-label fs-7">Campo no WooCommerce</label>
                            <input type="text" class="form-control form-control-sm" name="field_mapping[name][woocommerce_field]" value="billing.first_name" placeholder="billing.first_name">
                            <div class="form-text text-warning">⚠️ Busca por nome pode retornar pedidos de outras pessoas com mesmo nome</div>
                        </div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <h4 class="fw-bold mb-4">Configurações de Busca</h4>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="search_settings[phone_variations]" id="phone_variations" checked>
                            <label class="form-check-label" for="phone_variations">
                                Buscar variações de telefone (com/sem 9º dígito, com/sem +55)
                            </label>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label">Máximo de Resultados</label>
                            <input type="number" class="form-control" name="search_settings[max_results]" value="50" min="1" max="100">
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label">Cache (minutos)</label>
                            <input type="number" class="form-control" name="cache_ttl_minutes" value="5" min="1">
                        </div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <h4 class="fw-bold mb-4">
                        <i class="ki-duotone ki-chart-line-up fs-2 text-success me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Tracking de Conversão
                    </h4>
                    <p class="text-muted fs-7 mb-4">Configure o campo onde está o ID do vendedor nos pedidos para métricas de conversão</p>
                    
                    <div class="mb-5">
                        <label class="form-label">Meta Key do Vendedor</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="seller_meta_key" id="seller_meta_key_input" value="_vendor_id" placeholder="_vendor_id">
                            <button type="button" class="btn btn-light-primary" onclick="testSellerMetaKey()" id="btn_test_meta_key">
                                <i class="ki-duotone ki-verify fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Testar
                            </button>
                        </div>
                        <div class="form-text">
                            Campo do <code>meta_data</code> onde está o ID do vendedor.<br>
                            Exemplos: <code>_vendor_id</code>, <code>_wcfm_vendor_id</code>, <code>_dokan_vendor_id</code>
                        </div>
                        <div id="test_meta_key_result" class="mt-3" style="display: none;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Salvando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Integração -->
<div class="modal fade" id="kt_modal_edit_woocommerce" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Integração WooCommerce</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="form_edit_woocommerce" onsubmit="updateIntegration(event)">
                <input type="hidden" name="id" id="edit_integration_id">
                <div class="modal-body">
                    <!-- Mesmo conteúdo do modal de criação -->
                    <div class="mb-5">
                        <label class="form-label required">Nome da Integração</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">URL da Loja WooCommerce</label>
                        <input type="url" class="form-control" name="woocommerce_url" id="edit_woocommerce_url" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Consumer Key</label>
                            <input type="text" class="form-control" name="consumer_key" id="edit_consumer_key" required>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Consumer Secret</label>
                            <input type="password" class="form-control" name="consumer_secret" id="edit_consumer_secret" placeholder="Deixe em branco para não alterar">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="active">Ativa</option>
                            <option value="inactive">Inativa</option>
                        </select>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <h4 class="fw-bold mb-4">Mapeamento de Campos</h4>
                    <p class="text-muted fs-7 mb-4">Configure quais campos do contato usar para buscar pedidos no WooCommerce</p>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="field_mapping[phone][enabled]" id="edit_field_phone_enabled">
                            <label class="form-check-label" for="edit_field_phone_enabled">
                                <span class="fw-bold">Telefone</span>
                                <span class="text-muted fs-7 d-block">Buscar pedidos pelo telefone do contato</span>
                            </label>
                        </div>
                        <div class="ms-8">
                            <label class="form-label fs-7">Campo no WooCommerce</label>
                            <input type="text" class="form-control form-control-sm" name="field_mapping[phone][woocommerce_field]" id="edit_field_phone_wc" placeholder="billing.phone">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="field_mapping[email][enabled]" id="edit_field_email_enabled">
                            <label class="form-check-label" for="edit_field_email_enabled">
                                <span class="fw-bold">Email</span>
                                <span class="text-muted fs-7 d-block">Buscar pedidos pelo email do contato</span>
                            </label>
                        </div>
                        <div class="ms-8">
                            <label class="form-label fs-7">Campo no WooCommerce</label>
                            <input type="text" class="form-control form-control-sm" name="field_mapping[email][woocommerce_field]" id="edit_field_email_wc" placeholder="billing.email">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="field_mapping[name][enabled]" id="edit_field_name_enabled">
                            <label class="form-check-label" for="edit_field_name_enabled">
                                <span class="fw-bold">Nome</span>
                                <span class="text-muted fs-7 d-block">Buscar pedidos pelo nome do contato</span>
                            </label>
                        </div>
                        <div class="ms-8">
                            <label class="form-label fs-7">Campo no WooCommerce</label>
                            <input type="text" class="form-control form-control-sm" name="field_mapping[name][woocommerce_field]" id="edit_field_name_wc" placeholder="billing.first_name">
                            <div class="form-text text-warning">⚠️ Busca por nome pode retornar pedidos de outras pessoas com mesmo nome</div>
                        </div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <h4 class="fw-bold mb-4">Configurações de Busca</h4>
                    
                    <div class="mb-5">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="search_settings[phone_variations]" id="edit_phone_variations">
                            <label class="form-check-label" for="edit_phone_variations">
                                Buscar variações de telefone (com/sem 9º dígito, com/sem +55)
                            </label>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-5">
                            <label class="form-label">Máximo de Resultados</label>
                            <input type="number" class="form-control" name="search_settings[max_results]" id="edit_max_results" min="1" max="100">
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label">Cache (minutos)</label>
                            <input type="number" class="form-control" name="cache_ttl_minutes" id="edit_cache_ttl" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Atualizar</span>
                        <span class="indicator-progress">Atualizando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Salvar nova integração
function saveIntegration(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const indicator = submitBtn.querySelector('.indicator-label');
    const progress = submitBtn.querySelector('.indicator-progress');
    
    submitBtn.setAttribute('data-kt-indicator', 'on');
    indicator.style.display = 'none';
    progress.style.display = 'inline-block';
    
    const formData = new FormData(form);
    const data = {};
    
    // Processar dados do formulário
    formData.forEach((value, key) => {
        if (key.startsWith('field_mapping[')) {
            // Processar mapeamento de campos
            const match = key.match(/field_mapping\[(\w+)\]\[(\w+)\]/);
            if (match) {
                const field = match[1];
                const prop = match[2];
                if (!data.contact_field_mapping) {
                    data.contact_field_mapping = {};
                }
                if (!data.contact_field_mapping[field]) {
                    data.contact_field_mapping[field] = {};
                }
                if (prop === 'enabled') {
                    data.contact_field_mapping[field][prop] = value === 'on';
                } else {
                    data.contact_field_mapping[field][prop] = value;
                }
            }
        } else if (key.startsWith('search_settings[')) {
            // Processar configurações de busca
            const match = key.match(/search_settings\[(\w+)\]/);
            if (match) {
                const setting = match[1];
                if (!data.search_settings) {
                    data.search_settings = {};
                }
                data.search_settings[setting] = value === 'on' ? true : value;
            }
        } else {
            data[key] = value;
        }
    });
    
    fetch('<?= \App\Helpers\Url::to('/integrations/woocommerce') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: result.message || 'Integração criada com sucesso!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            throw new Error(result.message || 'Erro ao criar integração');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao criar integração'
        });
    })
    .finally(() => {
        submitBtn.removeAttribute('data-kt-indicator');
        indicator.style.display = 'inline-block';
        progress.style.display = 'none';
    });
}

// Editar integração
function editIntegration(id, integration) {
    // Campos básicos
    document.getElementById('edit_integration_id').value = id;
    document.getElementById('edit_name').value = integration.name || '';
    document.getElementById('edit_woocommerce_url').value = integration.woocommerce_url || '';
    document.getElementById('edit_consumer_key').value = integration.consumer_key || '';
    document.getElementById('edit_consumer_secret').value = '';
    document.getElementById('edit_status').value = integration.status || 'active';
    
    // Parse do mapeamento de campos (JSON)
    let fieldMapping = {};
    try {
        fieldMapping = typeof integration.contact_field_mapping === 'string' 
            ? JSON.parse(integration.contact_field_mapping) 
            : integration.contact_field_mapping || {};
    } catch (e) {
        console.error('Erro ao parsear contact_field_mapping:', e);
        fieldMapping = {};
    }
    
    // Popular campos de telefone
    const phoneEnabled = fieldMapping.phone?.enabled || false;
    const phoneField = fieldMapping.phone?.woocommerce_field || 'billing.phone';
    document.getElementById('edit_field_phone_enabled').checked = phoneEnabled;
    document.getElementById('edit_field_phone_wc').value = phoneField;
    
    // Popular campos de email
    const emailEnabled = fieldMapping.email?.enabled || false;
    const emailField = fieldMapping.email?.woocommerce_field || 'billing.email';
    document.getElementById('edit_field_email_enabled').checked = emailEnabled;
    document.getElementById('edit_field_email_wc').value = emailField;
    
    // Popular campos de nome
    const nameEnabled = fieldMapping.name?.enabled || false;
    const nameField = fieldMapping.name?.woocommerce_field || 'billing.first_name';
    document.getElementById('edit_field_name_enabled').checked = nameEnabled;
    document.getElementById('edit_field_name_wc').value = nameField;
    
    // Parse das configurações de busca (JSON)
    let searchSettings = {};
    try {
        searchSettings = typeof integration.search_settings === 'string' 
            ? JSON.parse(integration.search_settings) 
            : integration.search_settings || {};
    } catch (e) {
        console.error('Erro ao parsear search_settings:', e);
        searchSettings = {};
    }
    
    // Popular configurações de busca
    const phoneVariations = searchSettings.phone_variations !== false; // Default true
    const maxResults = searchSettings.max_results || 50;
    document.getElementById('edit_phone_variations').checked = phoneVariations;
    document.getElementById('edit_max_results').value = maxResults;
    document.getElementById('edit_cache_ttl').value = integration.cache_ttl_minutes || 5;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_edit_woocommerce'));
    modal.show();
}

// Atualizar integração
function updateIntegration(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const indicator = submitBtn.querySelector('.indicator-label');
    const progress = submitBtn.querySelector('.indicator-progress');
    
    submitBtn.setAttribute('data-kt-indicator', 'on');
    indicator.style.display = 'none';
    progress.style.display = 'inline-block';
    
    const formData = new FormData(form);
    const data = {};
    const id = formData.get('id');
    
    // Processar dados do formulário (similar a saveIntegration)
    formData.forEach((value, key) => {
        if (key === 'id') return; // Pular ID
        
        if (key.startsWith('field_mapping[')) {
            // Processar mapeamento de campos
            const match = key.match(/field_mapping\[(\w+)\]\[(\w+)\]/);
            if (match) {
                const field = match[1]; // phone, email, name
                const prop = match[2];  // enabled, woocommerce_field
                
                if (!data.contact_field_mapping) {
                    data.contact_field_mapping = {};
                }
                if (!data.contact_field_mapping[field]) {
                    data.contact_field_mapping[field] = {};
                }
                
                // Checkboxes vêm como 'on', converter para boolean
                if (prop === 'enabled') {
                    data.contact_field_mapping[field][prop] = value === 'on';
                } else {
                    data.contact_field_mapping[field][prop] = value;
                }
            }
        } else if (key.startsWith('search_settings[')) {
            // Processar configurações de busca
            const match = key.match(/search_settings\[(\w+)\]/);
            if (match) {
                const setting = match[1];
                if (!data.search_settings) {
                    data.search_settings = {};
                }
                data.search_settings[setting] = value === 'on' ? true : value;
            }
        } else {
            // Outros campos normais
            if (value !== '') {
                data[key] = value;
            }
        }
    });
    
    fetch(`<?= \App\Helpers\Url::to('/integrations/woocommerce') ?>/${id}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: result.message || 'Integração atualizada com sucesso!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            throw new Error(result.message || 'Erro ao atualizar integração');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao atualizar integração'
        });
    })
    .finally(() => {
        submitBtn.removeAttribute('data-kt-indicator');
        indicator.style.display = 'inline-block';
        progress.style.display = 'none';
    });
}

// Deletar integração
function deleteIntegration(id) {
    Swal.fire({
        title: 'Tem certeza?',
        text: 'Esta ação não pode ser desfeita!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`<?= \App\Helpers\Url::to('/integrations/woocommerce') ?>/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deletado!',
                        text: result.message || 'Integração deletada com sucesso!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(result.message || 'Erro ao deletar integração');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: error.message || 'Erro ao deletar integração'
                });
            });
        }
    });
}

// Testar conexão
function testConnection(id) {
    Swal.fire({
        title: 'Testando conexão...',
        text: 'Aguarde enquanto testamos a conexão com o WooCommerce',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch(`<?= \App\Helpers\Url::to('/integrations/woocommerce') ?>/${id}/test`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Conexão OK!',
                text: result.message || 'Conexão com WooCommerce estabelecida com sucesso!',
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro na Conexão',
                text: result.message || 'Não foi possível conectar com o WooCommerce'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao testar conexão'
        });
    });
}

// Testar Meta Key do Vendedor
function testSellerMetaKey() {
    const metaKey = document.getElementById('seller_meta_key_input').value;
    const resultDiv = document.getElementById('test_meta_key_result');
    const testBtn = document.getElementById('btn_test_meta_key');
    
    if (!metaKey) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Digite um meta key para testar'
        });
        return;
    }
    
    // Verificar se é nova integração ou edição
    const form = document.getElementById('form_new_woocommerce') || document.getElementById('form_edit_woocommerce');
    const integrationIdInput = form.querySelector('input[name="integration_id"]');
    
    if (!integrationIdInput || !integrationIdInput.value) {
        Swal.fire({
            icon: 'info',
            title: 'Atenção',
            text: 'Salve a integração primeiro antes de testar o meta key'
        });
        return;
    }
    
    const integrationId = integrationIdInput.value;
    
    // Loading
    testBtn.disabled = true;
    testBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testando...';
    resultDiv.style.display = 'none';
    
    fetch('<?= \App\Helpers\Url::to('/api/woocommerce/test-meta-key') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `integration_id=${integrationId}&meta_key=${encodeURIComponent(metaKey)}`
    })
    .then(response => response.json())
    .then(result => {
        testBtn.disabled = false;
        testBtn.innerHTML = '<i class="ki-duotone ki-verify fs-2"><span class="path1"></span><span class="path2"></span></i> Testar';
        
        resultDiv.style.display = 'block';
        
        if (result.success) {
            // Sucesso - meta key encontrado
            let detailsHTML = '';
            if (result.details) {
                detailsHTML = `
                    <div class="mt-2">
                        <strong>Detalhes:</strong>
                        <ul class="mb-0 mt-1">
                            <li>Pedidos verificados: ${result.details.total_orders_checked}</li>
                            <li>Vendedores encontrados: ${result.details.sellers_found}</li>
                            ${result.details.seller_ids ? `<li>IDs: ${result.details.seller_ids.join(', ')}</li>` : ''}
                        </ul>
                        ${result.details.example_order ? `
                            <div class="alert alert-info mt-2 mb-0">
                                <strong>Exemplo de pedido:</strong><br>
                                ID: #${result.details.example_order.id} | 
                                Vendedor: ${result.details.example_order.seller_id} | 
                                Total: R$ ${result.details.example_order.total}
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-check-circle fs-2x text-success me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="flex-grow-1">
                            <strong>${result.message}</strong>
                            ${detailsHTML}
                        </div>
                    </div>
                </div>
            `;
            
            // Notificar sucesso
            toastr.success(result.message, 'Meta Key Válido!');
            
        } else {
            // Erro - meta key não encontrado
            let suggestionHTML = '';
            if (result.details) {
                if (result.details.available_meta_keys && result.details.available_meta_keys.length > 0) {
                    suggestionHTML = `
                        <div class="mt-2">
                            <strong>Meta keys disponíveis nos pedidos:</strong>
                            <div class="mt-1">
                                ${result.details.available_meta_keys.slice(0, 10).map(key => 
                                    `<code class="me-2">${key}</code>`
                                ).join('')}
                            </div>
                        </div>
                    `;
                }
            }
            
            resultDiv.innerHTML = `
                <div class="alert alert-warning">
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-information fs-2x text-warning me-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="flex-grow-1">
                            <strong>${result.message}</strong>
                            ${suggestionHTML}
                        </div>
                    </div>
                </div>
            `;
            
            toastr.warning(result.message, 'Meta Key Não Encontrado');
        }
    })
    .catch(error => {
        testBtn.disabled = false;
        testBtn.innerHTML = '<i class="ki-duotone ki-verify fs-2"><span class="path1"></span><span class="path2"></span></i> Testar';
        
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="ki-duotone ki-cross-circle fs-2x text-danger me-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <strong>Erro ao testar:</strong> ${error.message || 'Erro desconhecido'}
            </div>
        `;
        
        toastr.error('Erro ao testar meta key', 'Erro');
    });
}
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../../layouts/metronic/app.php'; ?>

