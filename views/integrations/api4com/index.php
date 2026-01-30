<?php
$layout = 'layouts.metronic.app';
$title = 'Api4Com - Integrações';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Contas Api4Com</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('api4com.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_api4com">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Conta Api4Com
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($accounts)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-phone fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma conta Api4Com configurada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova conta Api4Com para habilitar chamadas telefônicas.</div>
            </div>
        <?php else: ?>
            <div class="row g-5">
                <?php foreach ($accounts as $account): ?>
                    <div class="col-xl-4">
                        <div class="card card-flush h-100">
                            <div class="card-header pt-5">
                                <div class="card-title">
                                    <h3 class="fw-bold"><?= htmlspecialchars($account['name']) ?></h3>
                                </div>
                                <div class="card-toolbar">
                                    <?php
                                    $statusClass = $account['enabled'] ? 'success' : 'warning';
                                    $statusText = $account['enabled'] ? 'Habilitado' : 'Desabilitado';
                                    ?>
                                    <span class="badge badge-light-<?= $statusClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <div class="mb-5">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">API URL:</span>
                                        <span class="fw-bold fs-7"><?= htmlspecialchars($account['api_url']) ?></span>
                                    </div>
                                    <?php if (!empty($account['domain'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">Domínio:</span>
                                        <span class="fw-semibold fs-7"><?= htmlspecialchars($account['domain']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($account['webhook_url'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">Webhook:</span>
                                        <span class="fw-semibold fs-7"><?= htmlspecialchars($account['webhook_url']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (\App\Helpers\Permission::can('api4com.edit')): ?>
                                    <button type="button" class="btn btn-light-primary btn-sm" 
                                            onclick="editAccount(<?= $account['id'] ?>)"
                                            title="Editar">
                                        <i class="ki-duotone ki-notepad-edit fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Editar
                                    </button>
                                    <button type="button" class="btn btn-light-info btn-sm" 
                                            onclick="manageExtensions(<?= $account['id'] ?>, '<?= htmlspecialchars($account['name'], ENT_QUOTES) ?>')"
                                            title="Gerenciar Ramais">
                                        <i class="ki-duotone ki-phone fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Ramais
                                    </button>
                                    <button type="button" class="btn btn-light-success btn-sm" 
                                            onclick="syncExtensions(<?= $account['id'] ?>)"
                                            title="Sincronizar Ramais da API">
                                        <i class="ki-duotone ki-arrows-loop fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-light-warning btn-sm" 
                                            onclick="testConnection(<?= $account['id'] ?>)"
                                            title="Testar Conexão com a API">
                                        <i class="ki-duotone ki-check-circle fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Testar
                                    </button>
                                    <?php if (\App\Helpers\Permission::can('api4com.delete')): ?>
                                    <button type="button" class="btn btn-light-danger btn-sm" 
                                            onclick="deleteAccount(<?= $account['id'] ?>, '<?= htmlspecialchars($account['name'], ENT_QUOTES) ?>')"
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
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<!--begin::Modal - Nova Conta Api4Com-->
<?php if (\App\Helpers\Permission::can('api4com.create')): ?>
<div class="modal fade" id="kt_modal_new_api4com" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Conta Api4Com</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_api4com_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome da Conta</label>
                        <input type="text" name="name" class="form-control form-control-solid" 
                               placeholder="Ex: Api4Com Principal" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">API URL</label>
                        <input type="url" name="api_url" class="form-control form-control-solid" 
                               value="https://api.api4com.com" 
                               placeholder="https://api.api4com.com" required />
                        <div class="form-text">URL base da API Api4Com</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Token de Autenticação</label>
                        <input type="text" name="api_token" class="form-control form-control-solid" 
                               placeholder="Seu token Api4Com" required />
                        <div class="form-text">Token obtido no painel Api4Com</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Domínio</label>
                        <input type="text" name="domain" class="form-control form-control-solid" 
                               placeholder="seudominio.api4com.com" />
                        <div class="form-text">Domínio da sua conta Api4Com (opcional)</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Webhook URL</label>
                        <input type="url" name="webhook_url" class="form-control form-control-solid" 
                               value="<?= \App\Helpers\Url::fullUrl('/api4com-calls/webhook') ?>" 
                               readonly />
                        <div class="form-text">URL para receber webhooks da Api4Com (gerada automaticamente)</div>
                    </div>
                    <div class="fv-row mb-7">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="enabled" id="kt_api4com_enabled" value="1" checked />
                            <label class="form-check-label" for="kt_api4com_enabled">
                                <span class="fw-semibold">Habilitar conta</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_api4com_submit" class="btn btn-primary">
                        <span class="indicator-label">Criar Conta</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Nova Conta Api4Com-->

<!--begin::Modal - Editar Conta Api4Com-->
<?php if (\App\Helpers\Permission::can('api4com.edit')): ?>
<div class="modal fade" id="kt_modal_edit_api4com" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Conta Api4Com</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_api4com_form" class="form">
                <input type="hidden" name="account_id" id="edit_account_id" />
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome da Conta</label>
                        <input type="text" name="name" id="edit_name" class="form-control form-control-solid" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">API URL</label>
                        <input type="url" name="api_url" id="edit_api_url" class="form-control form-control-solid" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Token de Autenticação</label>
                        <input type="text" name="api_token" id="edit_api_token" class="form-control form-control-solid" />
                        <div class="form-text">Deixe em branco para manter o token atual</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Domínio</label>
                        <input type="text" name="domain" id="edit_domain" class="form-control form-control-solid" />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Webhook URL</label>
                        <input type="url" name="webhook_url" id="edit_webhook_url" class="form-control form-control-solid" readonly />
                    </div>
                    <div class="fv-row mb-7">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="enabled" id="edit_enabled" value="1" />
                            <label class="form-check-label" for="edit_enabled">
                                <span class="fw-semibold">Habilitar conta</span>
                            </label>
                        </div>
                    </div>
                    
                    <!--begin::Configurações Avançadas-->
                    <div class="separator separator-dashed my-5"></div>
                    <div class="mb-3">
                        <a class="fw-bold text-primary" data-bs-toggle="collapse" href="#editAdvancedSettings" role="button" aria-expanded="false">
                            <i class="ki-duotone ki-setting-2 fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                            Configurações Avançadas da API
                        </a>
                    </div>
                    <div class="collapse" id="editAdvancedSettings">
                        <div class="card card-flush bg-light-warning">
                            <div class="card-body py-4">
                                <div class="text-warning fs-7 mb-4">
                                    <i class="ki-duotone ki-information-5 fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    Altere somente se a API retornar erro. Consulte a documentação da Api4Com.
                                </div>
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <label class="fw-semibold fs-7 mb-2">Endpoint do Discador</label>
                                        <input type="text" name="config_dialer_endpoint" id="edit_config_dialer_endpoint" 
                                               class="form-control form-control-sm" placeholder="/api/v1/dialer" />
                                        <div class="form-text fs-8">Padrão: /api/v1/dialer</div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="fw-semibold fs-7 mb-2">Campo Ramal</label>
                                        <input type="text" name="config_extension_field" id="edit_config_extension_field" 
                                               class="form-control form-control-sm" placeholder="extension" />
                                    </div>
                                    <div class="col-md-2">
                                        <label class="fw-semibold fs-7 mb-2">Campo Telefone</label>
                                        <input type="text" name="config_phone_field" id="edit_config_phone_field" 
                                               class="form-control form-control-sm" placeholder="phone" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="fw-semibold fs-7 mb-2">Usar valor de</label>
                                        <select name="config_extension_value_field" id="edit_config_extension_value_field" 
                                                class="form-select form-select-sm">
                                            <option value="">Automático (prioriza número)</option>
                                            <option value="extension_number">Número do Ramal (ex: 1001)</option>
                                            <option value="extension_id">ID do Ramal (ex: 123)</option>
                                        </select>
                                        <div class="form-text fs-8">Se der erro "extension not found", tente trocar</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Configurações Avançadas-->
                    
                    <!--begin::Configurações WebPhone SIP-->
                    <div class="separator separator-dashed my-5"></div>
                    <div class="mb-3">
                        <a class="fw-bold text-success" data-bs-toggle="collapse" href="#editWebphoneSettings" role="button" aria-expanded="false">
                            <i class="ki-duotone ki-phone fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                            Configurações WebPhone Integrado (SIP)
                        </a>
                    </div>
                    <div class="collapse" id="editWebphoneSettings">
                        <div class="card card-flush bg-light-success">
                            <div class="card-body py-4">
                                <div class="text-success fs-7 mb-4">
                                    <i class="ki-duotone ki-information-5 fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    Configure para habilitar o WebPhone integrado no chat (sem precisar de extensão externa).
                                </div>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="fw-semibold fs-7 mb-2">Domínio SIP</label>
                                        <input type="text" name="sip_domain" id="edit_sip_domain" 
                                               class="form-control form-control-sm" placeholder="seudominio.api4com.com" />
                                        <div class="form-text fs-8">O mesmo domínio da sua conta Api4Com</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-semibold fs-7 mb-2">Porta WebSocket</label>
                                        <input type="number" name="sip_port" id="edit_sip_port" 
                                               class="form-control form-control-sm" value="6443" placeholder="6443" />
                                        <div class="form-text fs-8">Padrão: 6443</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-semibold fs-7 mb-2">WebPhone</label>
                                        <div class="form-check form-switch form-check-custom form-check-solid mt-2">
                                            <input class="form-check-input" type="checkbox" name="webphone_enabled" id="edit_webphone_enabled" value="1" />
                                            <label class="form-check-label" for="edit_webphone_enabled">
                                                <span class="fw-semibold">Habilitar</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Configurações WebPhone SIP-->
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_api4com_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Editar Conta Api4Com-->

<!--begin::Modal - Gerenciar Ramais-->
<?php if (\App\Helpers\Permission::can('api4com.view')): ?>
<div class="modal fade" id="kt_modal_extensions" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Gerenciar Ramais - <span id="extensions_account_name"></span></h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <input type="hidden" id="extensions_account_id" />
                
                <?php if (\App\Helpers\Permission::can('api4com.edit')): ?>
                <!--begin::Adicionar Ramal-->
                <div class="card card-flush bg-light-primary mb-5">
                    <div class="card-header min-h-50px">
                        <h3 class="card-title fw-bold text-primary">
                            <i class="ki-duotone ki-plus-circle fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Adicionar Ramal Manualmente
                        </h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-toggle="collapse" data-bs-target="#collapseAddExtension">
                                <i class="ki-duotone ki-arrow-down fs-3"></i>
                            </button>
                        </div>
                    </div>
                    <div class="collapse" id="collapseAddExtension">
                        <div class="card-body pt-0">
                            <form id="form_add_extension" class="row g-3">
                                <div class="col-md-3">
                                    <label class="required form-label fs-7">Número do Ramal</label>
                                    <input type="text" name="extension_number" class="form-control form-control-sm" placeholder="Ex: 1001" required />
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-7">ID na API (opcional)</label>
                                    <input type="text" name="extension_id" class="form-control form-control-sm" placeholder="ID do ramal" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs-7">Usuário SIP (opcional)</label>
                                    <input type="text" name="sip_username" class="form-control form-control-sm" placeholder="Username SIP" />
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-sm btn-primary w-100">
                                        <i class="ki-duotone ki-plus fs-4"></i> Adicionar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!--end::Adicionar Ramal-->
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div class="text-muted fs-7">
                        <i class="ki-duotone ki-information-5 fs-4 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Associe ramais aos usuários para que possam fazer chamadas.
                    </div>
                    <?php if (\App\Helpers\Permission::can('api4com.edit')): ?>
                    <button type="button" class="btn btn-sm btn-light-success" onclick="syncExtensionsFromModal()" title="Buscar ramais automaticamente da API Api4Com">
                        <i class="ki-duotone ki-arrows-loop fs-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Sincronizar da API
                    </button>
                    <?php endif; ?>
                </div>
                
                <div id="extensions_list" class="table-responsive">
                    <div class="text-center py-10">
                        <span class="spinner-border text-primary"></span>
                        <p class="mt-3">Carregando ramais...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" data-bs-dismiss="modal" class="btn btn-light">Fechar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Gerenciar Ramais-->

<?php 
// Buscar usuários para associar aos ramais
$users = \App\Helpers\Database::fetchAll("SELECT id, name, email FROM users WHERE status = 'active' ORDER BY name ASC");
?>

<?php 
$content = ob_get_clean();
$usersJson = json_encode($users);
$scripts = '
<script>
const api4comUsers = ' . $usersJson . ';

document.addEventListener("DOMContentLoaded", function() {
    // Form de criação
    const form = document.getElementById("kt_modal_new_api4com_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_api4com_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_api4com"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar conta"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao criar conta");
            });
        });
    }
    
    // Form de edição
    const editForm = document.getElementById("kt_modal_edit_api4com_form");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_edit_api4com_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const accountId = document.getElementById("edit_account_id").value;
            const formData = new FormData(editForm);
            
            fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_api4com"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar conta"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atualizar conta");
            });
        });
    }
});

function editAccount(id) {
    // Buscar dados da conta
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + id + "/show", {
        method: "GET",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.account) {
            const account = data.account;
            document.getElementById("edit_account_id").value = account.id;
            document.getElementById("edit_name").value = account.name || "";
            document.getElementById("edit_api_url").value = account.api_url || "";
            document.getElementById("edit_api_token").value = ""; // Não mostrar token atual
            document.getElementById("edit_api_token").placeholder = "Deixe em branco para manter o atual";
            document.getElementById("edit_domain").value = account.domain || "";
            document.getElementById("edit_webhook_url").value = account.webhook_url || "' . \App\Helpers\Url::fullUrl('/api4com-calls/webhook') . '";
            document.getElementById("edit_enabled").checked = account.enabled == 1;
            
            // Configurações avançadas
            let config = {};
            try {
                config = account.config ? JSON.parse(account.config) : {};
            } catch(e) {}
            document.getElementById("edit_config_dialer_endpoint").value = config.dialer_endpoint || "";
            document.getElementById("edit_config_extension_field").value = config.extension_field || "";
            document.getElementById("edit_config_phone_field").value = config.phone_field || "";
            document.getElementById("edit_config_extension_value_field").value = config.extension_value_field || "";
            
            // Configurações WebPhone SIP
            document.getElementById("edit_sip_domain").value = account.sip_domain || account.domain || "";
            document.getElementById("edit_sip_port").value = account.sip_port || 6443;
            document.getElementById("edit_webphone_enabled").checked = account.webphone_enabled == 1;
            
            const modal = new bootstrap.Modal(document.getElementById("kt_modal_edit_api4com"));
            modal.show();
        } else {
            alert("Erro ao carregar dados da conta");
        }
    })
    .catch(error => {
        alert("Erro ao carregar dados da conta");
    });
}

function testConnection(accountId) {
    const btn = event.target.closest("button");
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = \'<span class="spinner-border spinner-border-sm"></span> Testando...\';
    
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId + "/test", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        
        if (data.success) {
            let msg = "✅ " + data.message + "\\n\\n";
            msg += "👤 Usuário: " + (data.user?.name || "N/A") + "\\n";
            msg += "📧 Email: " + (data.user?.email || "N/A") + "\\n";
            msg += "🔑 Perfil: " + (data.user?.role || "N/A") + "\\n";
            msg += "⏱️ Tempo de resposta: " + data.response_time + "ms";
            alert(msg);
        } else {
            let msg = "❌ Falha na conexão\\n\\n";
            msg += data.message || "Erro desconhecido";
            if (data.http_code) {
                msg += "\\n\\nCódigo HTTP: " + data.http_code;
            }
            if (data.response_time) {
                msg += "\\nTempo: " + data.response_time + "ms";
            }
            alert(msg);
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        alert("❌ Erro ao testar conexão: " + error.message);
    });
}

function deleteAccount(id, name) {
    if (!confirm("Tem certeza que deseja deletar a conta \\"" + name + "\\"?\\n\\nEsta ação não pode ser desfeita.")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + id, {
        method: "DELETE",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao deletar conta"));
        }
    })
    .catch(error => {
        alert("Erro ao deletar conta");
    });
}

function syncExtensions(accountId) {
    if (!confirm("Sincronizar ramais da API Api4Com?\\n\\nIsto irá buscar os ramais da sua conta Api4Com e salvá-los no sistema.")) {
        return;
    }
    
    const btn = event.target.closest("button");
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = \'<span class="spinner-border spinner-border-sm"></span>\';
    
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId + "/sync-extensions", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        
        if (data.success) {
            alert(data.message);
        } else {
            alert("Erro: " + (data.message || "Erro ao sincronizar ramais"));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        alert("Erro ao sincronizar ramais");
    });
}

function manageExtensions(accountId, accountName) {
    document.getElementById("extensions_account_id").value = accountId;
    document.getElementById("extensions_account_name").textContent = accountName;
    
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_extensions"));
    modal.show();
    
    loadExtensions(accountId);
}

function loadExtensions(accountId) {
    const listDiv = document.getElementById("extensions_list");
    listDiv.innerHTML = \'<div class="text-center py-10"><span class="spinner-border text-primary"></span><p class="mt-3">Carregando ramais...</p></div>\';
    
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId + "/extensions", {
        method: "GET",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderExtensions(data.extensions, accountId);
        } else {
            listDiv.innerHTML = \'<div class="alert alert-danger">Erro ao carregar ramais: \' + (data.message || "Erro desconhecido") + \'</div>\';
        }
    })
    .catch(error => {
        listDiv.innerHTML = \'<div class="alert alert-danger">Erro ao carregar ramais</div>\';
    });
}

function renderExtensions(extensions, accountId) {
    const listDiv = document.getElementById("extensions_list");
    
    if (!extensions || extensions.length === 0) {
        listDiv.innerHTML = \'<div class="alert alert-info"><i class="ki-duotone ki-information-5 fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>Nenhum ramal encontrado. Adicione ramais manualmente acima ou clique em "Sincronizar da API".</div>\';
        return;
    }
    
    let html = \'<table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">\';
    html += \'<thead><tr class="fw-bold text-muted"><th>Ramal</th><th>ID API</th><th>Usuário Associado</th><th>Senha SIP (WebPhone)</th><th class="text-center">Status</th><th class="text-end">Ações</th></tr></thead>\';
    html += \'<tbody>\';
    
    extensions.forEach(function(ext) {
        let metadata = {};
        try {
            metadata = ext.metadata ? JSON.parse(ext.metadata) : {};
        } catch(e) {}
        const name = metadata.name || ext.extension_number || "Ramal";
        const statusClass = ext.status === "active" ? "success" : "warning";
        const statusText = ext.status === "active" ? "Ativo" : "Inativo";
        const hasSipPassword = ext.sip_password_encrypted ? true : false;
        const sipBadge = hasSipPassword 
            ? \'<span class="badge badge-light-success">Configurada</span>\' 
            : \'<span class="badge badge-light-warning">Não configurada</span>\';
        
        html += \'<tr>\';
        html += \'<td><span class="fw-bold">\' + name + \'</span><br><small class="text-muted">\' + (ext.extension_number || "-") + \'</small></td>\';
        html += \'<td><code>\' + (ext.extension_id || "-") + \'</code></td>\';
        html += \'<td>\';
        html += \'<select class="form-select form-select-sm" onchange="assignExtension(\' + accountId + \', \' + ext.id + \', this.value)">\';
        html += \'<option value="">-- Selecione --</option>\';
        
        api4comUsers.forEach(function(user) {
            const selected = ext.user_id == user.id ? "selected" : "";
            html += \'<option value="\' + user.id + \'" \' + selected + \'>\' + user.name + \'</option>\';
        });
        
        html += \'</select>\';
        if (ext.user_name) {
            html += \'<small class="text-muted d-block">Atual: \' + ext.user_name + \'</small>\';
        }
        html += \'</td>\';
        // Coluna de senha SIP
        html += \'<td>\';
        html += \'<div class="d-flex align-items-center gap-2">\';
        html += \'<input type="password" class="form-control form-control-sm" id="sip_pwd_\' + ext.id + \'" placeholder="Senha SIP" style="width: 120px;" />\';
        html += \'<button type="button" class="btn btn-sm btn-icon btn-light-primary" onclick="saveSipPassword(\' + accountId + \', \' + ext.id + \')" title="Salvar senha SIP"><i class="ki-duotone ki-check fs-4"><span class="path1"></span><span class="path2"></span></i></button>\';
        html += \'</div>\';
        html += \'<small class="d-block mt-1">\' + sipBadge + \'</small>\';
        html += \'</td>\';
        html += \'<td class="text-center"><span class="badge badge-light-\' + statusClass + \'">\' + statusText + \'</span></td>\';
        html += \'<td class="text-end"><button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="deleteExtension(\' + accountId + \', \' + ext.id + \')" title="Deletar ramal"><i class="ki-duotone ki-trash fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i></button></td>\';
        html += \'</tr>\';
    });
    
    html += \'</tbody></table>\';
    listDiv.innerHTML = html;
}

// Salvar senha SIP do ramal
function saveSipPassword(accountId, extensionId) {
    const passwordInput = document.getElementById("sip_pwd_" + extensionId);
    const password = passwordInput.value.trim();
    
    if (!password) {
        alert("Digite a senha SIP");
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId + "/extensions/" + extensionId + "/sip", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ sip_password: password })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            passwordInput.value = "";
            alert("Senha SIP salva com sucesso!");
            loadExtensions(accountId);
        } else {
            alert("Erro: " + (data.message || "Erro ao salvar senha SIP"));
        }
    })
    .catch(error => {
        alert("Erro ao salvar senha SIP");
    });
}

function assignExtension(accountId, extensionId, userId) {
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId + "/extensions/" + extensionId + "/assign", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Opcional: recarregar lista
            loadExtensions(accountId);
        } else {
            alert("Erro: " + (data.message || "Erro ao associar ramal"));
        }
    })
    .catch(error => {
        alert("Erro ao associar ramal");
    });
}

function syncExtensionsFromModal() {
    const accountId = document.getElementById("extensions_account_id").value;
    
    const btn = event.target.closest("button");
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = \'<span class="spinner-border spinner-border-sm"></span> Sincronizando...\';
    
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId + "/sync-extensions", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        
        if (data.success) {
            alert(data.message);
            loadExtensions(accountId);
        } else {
            alert("Erro: " + (data.message || "Erro ao sincronizar ramais") + "\\n\\nDica: Você pode adicionar ramais manualmente usando o formulário acima.");
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        alert("Erro ao sincronizar ramais. Você pode adicionar ramais manualmente.");
    });
}

// Criar ramal manualmente
document.addEventListener("DOMContentLoaded", function() {
    const formAddExtension = document.getElementById("form_add_extension");
    if (formAddExtension) {
        formAddExtension.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const accountId = document.getElementById("extensions_account_id").value;
            const formData = new FormData(formAddExtension);
            
            const submitBtn = formAddExtension.querySelector("button[type=submit]");
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = \'<span class="spinner-border spinner-border-sm"></span>\';
            
            fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId + "/extensions", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
                
                if (data.success) {
                    formAddExtension.reset();
                    loadExtensions(accountId);
                } else {
                    alert("Erro: " + (data.message || "Erro ao criar ramal"));
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
                alert("Erro ao criar ramal");
            });
        });
    }
});

function deleteExtension(accountId, extensionId) {
    if (!confirm("Tem certeza que deseja deletar este ramal?")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/integrations/api4com') . '/" + accountId + "/extensions/" + extensionId, {
        method: "DELETE",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadExtensions(accountId);
        } else {
            alert("Erro: " + (data.message || "Erro ao deletar ramal"));
        }
    })
    .catch(error => {
        alert("Erro ao deletar ramal");
    });
}
</script>';
?>

<?php include __DIR__ . '/../../layouts/metronic/app.php'; ?>

