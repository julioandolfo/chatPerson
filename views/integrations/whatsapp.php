<?php
$layout = 'layouts.metronic.app';
$title = 'WhatsApp - Integrações';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Contas WhatsApp</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('whatsapp.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_whatsapp">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Conta WhatsApp
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($whatsapp_accounts)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-sms fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma conta WhatsApp configurada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova conta WhatsApp usando Quepasa API.</div>
            </div>
        <?php else: ?>
            <div class="row g-5">
                <?php foreach ($whatsapp_accounts as $account): ?>
                    <div class="col-xl-4">
                        <div class="card card-flush h-100">
                            <div class="card-header pt-5">
                                <div class="card-title">
                                    <h3 class="fw-bold"><?= htmlspecialchars($account['name']) ?></h3>
                                </div>
                                <div class="card-toolbar">
                                    <?php
                                    $statusClass = [
                                        'active' => 'success',
                                        'inactive' => 'warning',
                                        'disconnected' => 'danger'
                                    ];
                                    $statusText = [
                                        'active' => 'Conectado',
                                        'inactive' => 'Inativo',
                                        'disconnected' => 'Desconectado'
                                    ];
                                    $currentStatus = $account['status'] ?? 'inactive';
                                    ?>
                                    <span class="badge badge-light-<?= $statusClass[$currentStatus] ?? 'warning' ?>">
                                        <?= $statusText[$currentStatus] ?? 'Desconhecido' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <div class="mb-5">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">Número:</span>
                                        <span class="fw-bold"><?= htmlspecialchars($account['phone_number']) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">Provider:</span>
                                        <span class="badge badge-light-info"><?= htmlspecialchars(strtoupper($account['provider'] ?? 'quepasa')) ?></span>
                                    </div>
                                    <?php if (!empty($account['instance_id'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted fw-semibold fs-7 me-2">Instance ID:</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($account['instance_id']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Mostrar última verificação de conexão
                                    $lastCheck = $account['last_connection_check'] ?? null;
                                    $lastResult = $account['last_connection_result'] ?? null;
                                    $consecutiveFailures = (int)($account['consecutive_failures'] ?? 0);
                                    ?>
                                    <?php if ($lastCheck): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ki-duotone ki-time fs-5 text-gray-500 me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="text-muted fw-semibold fs-7 me-2">Última verificação:</span>
                                        <span class="fs-8 text-gray-600" title="<?= htmlspecialchars($lastCheck) ?>"><?= date('d/m H:i', strtotime($lastCheck)) ?></span>
                                        <?php if ($lastResult === 'connected'): ?>
                                        <span class="badge badge-circle badge-success ms-2" style="width: 8px; height: 8px;"></span>
                                        <?php elseif ($lastResult === 'disconnected'): ?>
                                        <span class="badge badge-circle badge-danger ms-2" style="width: 8px; height: 8px;"></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($consecutiveFailures > 0): ?>
                                    <div class="alert alert-danger d-flex align-items-center p-3 mb-2">
                                        <i class="ki-duotone ki-disconnect fs-3 text-danger me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold fs-7">Conexão com problema</span>
                                            <span class="fs-8"><?= $consecutiveFailures ?> falha(s) consecutiva(s)</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($account['default_funnel_name'])): ?>
                                    <div class="separator separator-dashed my-3"></div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ki-duotone ki-element-11 fs-5 text-primary me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                        </i>
                                        <span class="text-muted fw-semibold fs-7 me-2">Funil Padrão:</span>
                                        <span class="fw-bold fs-7"><?= htmlspecialchars($account['default_funnel_name']) ?></span>
                                    </div>
                                    <?php if (!empty($account['default_stage_name'])): ?>
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-abstract-26 fs-5 text-info me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="text-muted fw-semibold fs-7 me-2">Etapa Padrão:</span>
                                        <span class="fw-bold fs-7"><?= htmlspecialchars($account['default_stage_name']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="separator separator-dashed my-3"></div>
                                    <div class="alert alert-warning d-flex align-items-center p-3">
                                        <i class="ki-duotone ki-information fs-3 text-warning me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <span class="fs-8">Usando funil/etapa padrão do sistema</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($account['proxy_host'])): ?>
                                    <div class="separator separator-dashed my-3"></div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ki-duotone ki-shield fs-5 text-primary me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="text-muted fw-semibold fs-7 me-2">Proxy:</span>
                                        <span class="badge badge-light-primary fs-8"><?= htmlspecialchars($account['proxy_host']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($account['wavoip_enabled'])): ?>
                                    <div class="separator separator-dashed my-3"></div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ki-duotone ki-phone fs-5 text-success me-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <span class="badge badge-light-success">WavoIP Habilitado</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if (\App\Helpers\Permission::can('whatsapp.view')): ?>
                                    <button type="button" class="btn btn-light-primary btn-sm flex-grow-1" 
                                            onclick="getQRCode(<?= $account['id'] ?>)">
                                        <i class="ki-duotone ki-qr-code fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        QR Code
                                    </button>
                                    <button type="button" class="btn btn-light-info btn-sm" 
                                            onclick="checkStatus(<?= $account['id'] ?>)"
                                            title="Verificar Status">
                                        <i class="ki-duotone ki-information fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </button>
                                    <button type="button" class="btn btn-light-warning btn-sm" 
                                            onclick="editAccount(<?= htmlspecialchars(json_encode($account), ENT_QUOTES) ?>)"
                                            title="Editar Conta">
                                        <i class="ki-duotone ki-pencil fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button type="button" class="btn btn-light-success btn-sm" 
                                            onclick="editAccountSettings(<?= $account['id'] ?>, <?= htmlspecialchars(json_encode($account['name']), ENT_QUOTES) ?>, <?= $account['default_funnel_id'] ?? 'null' ?>, <?= $account['default_stage_id'] ?? 'null' ?>)"
                                            title="Configurar Funil/Etapa">
                                        <i class="ki-duotone ki-setting-2 fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button type="button" class="btn btn-light-primary btn-sm" 
                                            onclick="configureWavoip(<?= $account['id'] ?>, <?= htmlspecialchars(json_encode($account['name']), ENT_QUOTES) ?>, <?= $account['wavoip_enabled'] ?? 0 ?>, <?= htmlspecialchars(json_encode($account['wavoip_token'] ?? ''), ENT_QUOTES) ?>)"
                                            title="Configurar WavoIP">
                                        <i class="ki-duotone ki-phone fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('whatsapp.edit')): ?>
                                    <?php if ($currentStatus === 'active'): ?>
                                    <button type="button" class="btn btn-light-danger btn-sm" 
                                            onclick="disconnectAccount(<?= $account['id'] ?>)"
                                            title="Desconectar">
                                        <i class="ki-duotone ki-cross fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('whatsapp.delete')): ?>
                                    <button type="button" class="btn btn-light-danger btn-sm" 
                                            onclick="deleteAccount(<?= $account['id'] ?>, '<?= htmlspecialchars($account['name'], ENT_QUOTES) ?>')"
                                            title="Deletar Conta">
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

<!--begin::Modal - Nova Conta WhatsApp-->
<?php if (\App\Helpers\Permission::can('whatsapp.create')): ?>
<div class="modal fade" id="kt_modal_new_whatsapp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Conta WhatsApp</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_new_whatsapp_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome da Conta</label>
                        <input type="text" name="name" class="form-control form-control-solid" 
                               placeholder="Ex: WhatsApp Principal" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Número do WhatsApp</label>
                        <input type="text" name="phone_number" class="form-control form-control-solid" 
                               placeholder="5511999999999" required />
                        <div class="form-text">Digite o número completo com código do país (ex: 5511999999999)</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Provider</label>
                        <select name="provider" id="kt_provider_select" class="form-select form-select-solid" required onchange="toggleProviderFields(this.value)">
                            <option value="quepasa" selected>Quepasa API</option>
                            <option value="native">WhatsApp Nativo (Baileys)</option>
                            <option value="evolution">Evolution API</option>
                        </select>
                    </div>
                    
                    <!-- Campos Quepasa -->
                    <div id="kt_quepasa_fields">
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">URL da API</label>
                            <input type="url" name="api_url" class="form-control form-control-solid" 
                                   placeholder="https://whats.seudominio.com" />
                            <div class="form-text">URL base da sua instalação Quepasa (ex: https://whats.seudominio.com)</div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">Quepasa User</label>
                            <input type="text" name="quepasa_user" class="form-control form-control-solid" 
                                   placeholder="julio" />
                            <div class="form-text">Identificador do usuário (X-QUEPASA-USER). Ex: julio, personizi, etc.</div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Track ID</label>
                            <input type="text" name="quepasa_trackid" class="form-control form-control-solid" 
                                   placeholder="meu-sistema" />
                            <div class="form-text">ID para rastreamento (X-QUEPASA-TRACKID). Deixe vazio para usar o nome da conta.</div>
                        </div>
                    </div>
                    
                    <!-- Campos Native (Baileys) -->
                    <div id="kt_native_fields" style="display: none;">
                        <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                            <i class="ki-duotone ki-shield-tick fs-2x text-info me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <span class="fw-bold">Conexão Nativa</span>
                                <span class="fs-7">Conecta diretamente ao WhatsApp sem depender de servidor externo. Requer o Baileys Service rodando localmente.</span>
                            </div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">URL do Baileys Service</label>
                            <input type="text" name="native_service_url" class="form-control form-control-solid" 
                                   value="http://127.0.0.1:3100" placeholder="http://127.0.0.1:3100" />
                            <div class="form-text">URL do microserviço Baileys (padrão: http://127.0.0.1:3100)</div>
                        </div>
                        
                        <div class="separator separator-dashed my-5"></div>
                        <h5 class="fw-bold mb-4">
                            <i class="ki-duotone ki-shield fs-4 text-primary me-2"><span class="path1"></span><span class="path2"></span></i>
                            Proxy (Opcional)
                        </h5>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Endereço do Proxy</label>
                            <input type="text" name="proxy_host" class="form-control form-control-solid" 
                                   placeholder="socks5://proxy.exemplo.com:1080" />
                            <div class="form-text">Formato: socks5://host:porta ou http://host:porta. Deixe vazio para usar IP local.</div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-6">
                                <label class="fw-semibold fs-6 mb-2">Usuário do Proxy</label>
                                <input type="text" name="proxy_user" class="form-control form-control-solid" placeholder="user" />
                            </div>
                            <div class="col-6">
                                <label class="fw-semibold fs-6 mb-2">Senha do Proxy</label>
                                <input type="password" name="proxy_pass" class="form-control form-control-solid" placeholder="******" />
                            </div>
                        </div>
                        <div class="fv-row mb-7">
                            <button type="button" class="btn btn-sm btn-light-primary" onclick="testProxy()">
                                <i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i>
                                Testar Proxy
                            </button>
                            <span id="kt_proxy_test_result" class="ms-3 fs-7"></span>
                        </div>
                    </div>
                    
                    <!-- Campos Evolution API -->
                    <div id="kt_evolution_fields" style="display: none;">
                        <div class="alert alert-success d-flex align-items-center p-5 mb-7">
                            <i class="ki-duotone ki-abstract-26 fs-2x text-success me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <span class="fw-bold">Evolution API v2</span>
                                <span class="fs-7">Conecta ao WhatsApp via Evolution API. Requer uma instância da Evolution API rodando.</span>
                            </div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">URL da Evolution API</label>
                            <input type="url" name="evolution_api_url" class="form-control form-control-solid" 
                                   placeholder="http://seu-ip:porta" />
                            <div class="form-text">URL base (sem <code>/manager</code>). Ex: <code>http://168.231.94.128:50725</code></div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="required fw-semibold fs-6 mb-2">API Key (Global)</label>
                            <input type="text" name="evolution_api_key" class="form-control form-control-solid" 
                                   placeholder="sua-api-key-global" />
                            <div class="form-text">Chave <code>AUTHENTICATION_API_KEY</code> configurada no servidor Evolution API. Obrigatória.</div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Nome da Instância</label>
                            <input type="text" name="evolution_instance_id" class="form-control form-control-solid" 
                                   placeholder="minha-instancia" />
                            <div class="form-text">Nome da instância na Evolution API. Deixe vazio para gerar automaticamente.</div>
                        </div>
                    </div>
                    
                    <div class="separator separator-dashed my-7"></div>
                    <h4 class="fw-bold mb-5">Funil e Etapa Padrão</h4>
                    <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                        <i class="ki-duotone ki-information fs-2x text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fs-7">Conversas criadas por esta conta entrarão automaticamente neste funil/etapa quando não houver automação específica.</span>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Funil Padrão</label>
                        <select name="default_funnel_id" id="kt_default_funnel_select" class="form-select form-select-solid" onchange="loadFunnelStages(this.value, 'kt_default_stage_select')">
                            <option value="">Usar padrão do sistema</option>
                            <?php if (!empty($funnels)): ?>
                                <?php foreach ($funnels as $funnel): ?>
                                    <option value="<?= $funnel['id'] ?>" <?= (!empty($default_funnel_id) && $default_funnel_id == $funnel['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($funnel['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Deixe vazio para usar o funil padrão do sistema</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Etapa Padrão</label>
                        <select name="default_stage_id" id="kt_default_stage_select" class="form-select form-select-solid">
                            <option value="">Selecione um funil primeiro</option>
                        </select>
                        <div class="form-text">Deixe vazio para usar a primeira etapa do funil</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_new_whatsapp_submit" class="btn btn-primary">
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
<!--end::Modal - Nova Conta WhatsApp-->

<!--begin::Modal - QR Code-->
<div class="modal fade" id="kt_modal_qrcode" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-400px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">QR Code para Conexão</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body text-center py-10">
                <div id="kt_qrcode_container">
                    <div class="spinner-border text-primary mb-5" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-muted">Gerando QR Code...</p>
                </div>
                <div class="mt-5">
                    <p class="text-gray-600 fs-7">
                        <i class="ki-duotone ki-information fs-5 text-primary me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Escaneie este QR Code com o WhatsApp para conectar
                    </p>
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="refreshQRCode()">
                    <i class="ki-duotone ki-arrows-circle fs-2"></i>
                    Atualizar QR Code
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal - QR Code-->

<!--begin::Modal - Editar Configurações da Conta-->
<?php if (\App\Helpers\Permission::can('whatsapp.edit')): ?>
<div class="modal fade" id="kt_modal_edit_account_settings" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Configurar Funil/Etapa Padrão</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_account_settings_form" class="form">
                <input type="hidden" name="account_id" id="kt_edit_account_id">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <h4 class="fw-bold" id="kt_edit_account_name"></h4>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                        <i class="ki-duotone ki-information fs-2x text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fs-7">Conversas criadas por esta conta entrarão automaticamente neste funil/etapa quando não houver automação específica.</span>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Funil Padrão</label>
                        <select name="default_funnel_id" id="kt_edit_default_funnel_select" class="form-select form-select-solid" onchange="loadFunnelStages(this.value, 'kt_edit_default_stage_select')">
                            <option value="">Usar padrão do sistema</option>
                            <?php if (!empty($funnels)): ?>
                                <?php foreach ($funnels as $funnel): ?>
                                    <option value="<?= $funnel['id'] ?>">
                                        <?= htmlspecialchars($funnel['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Deixe vazio para usar o funil padrão do sistema</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Etapa Padrão</label>
                        <select name="default_stage_id" id="kt_edit_default_stage_select" class="form-select form-select-solid">
                            <option value="">Selecione um funil primeiro</option>
                        </select>
                        <div class="form-text">Deixe vazio para usar a primeira etapa do funil</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_account_settings_submit" class="btn btn-primary">
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
<!--end::Modal - Editar Configurações da Conta-->

<!--begin::Modal - Editar Conta WhatsApp-->
<?php if (\App\Helpers\Permission::can('whatsapp.edit')): ?>
<div class="modal fade" id="kt_modal_edit_whatsapp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Conta WhatsApp</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_whatsapp_form" class="form">
                <input type="hidden" name="account_id" id="kt_edit_whatsapp_id">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome da Conta</label>
                        <input type="text" name="name" id="kt_edit_whatsapp_name" class="form-control form-control-solid" 
                               placeholder="Ex: WhatsApp Principal" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Número do WhatsApp</label>
                        <input type="text" name="phone_number" id="kt_edit_whatsapp_phone" class="form-control form-control-solid" 
                               placeholder="5511999999999" readonly />
                        <div class="form-text text-warning">O número não pode ser alterado após a criação</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Provider</label>
                        <select name="provider" id="kt_edit_provider_select" class="form-select form-select-solid" required onchange="toggleEditProviderFields(this.value)">
                            <option value="quepasa">Quepasa API</option>
                            <option value="native">WhatsApp Nativo (Baileys)</option>
                            <option value="evolution">Evolution API</option>
                        </select>
                    </div>
                    
                    <!-- Campos Quepasa (edição) -->
                    <div id="kt_edit_quepasa_fields">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">URL da API</label>
                            <input type="url" name="api_url" id="kt_edit_whatsapp_api_url" class="form-control form-control-solid" 
                                   placeholder="https://whats.seudominio.com" />
                            <div class="form-text">URL base da sua instalação Quepasa (ex: https://whats.seudominio.com)</div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Quepasa User</label>
                            <input type="text" name="quepasa_user" id="kt_edit_whatsapp_user" class="form-control form-control-solid" 
                                   placeholder="julio" />
                            <div class="form-text">Identificador do usuário (X-QUEPASA-USER). Ex: julio, personizi, etc.</div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Quepasa TrackId</label>
                            <input type="text" name="quepasa_trackid" id="kt_edit_whatsapp_trackid" class="form-control form-control-solid" 
                                   placeholder="nome_da_conta" />
                            <div class="form-text">Identificador único para rastreamento (X-QUEPASA-TRACKID). Deixe vazio para usar o nome da conta.</div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Token da API (opcional)</label>
                            <input type="text" name="api_key" id="kt_edit_whatsapp_api_key" class="form-control form-control-solid" 
                                   placeholder="Token será gerado automaticamente se vazio" />
                            <div class="form-text">Se deixar vazio, um token será gerado automaticamente ao escanear o QR Code</div>
                        </div>
                    </div>
                    
                    <!-- Campos Evolution API (edição) -->
                    <div id="kt_edit_evolution_fields" style="display: none;">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">URL da Evolution API</label>
                            <input type="url" id="kt_edit_evolution_api_url" class="form-control form-control-solid" 
                                   placeholder="http://seu-ip:porta" />
                            <div class="form-text">URL base (sem <code>/manager</code>). Ex: <code>http://168.231.94.128:50725</code></div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">API Key (Global)</label>
                            <input type="text" id="kt_edit_evolution_api_key" class="form-control form-control-solid" 
                                   placeholder="sua-api-key-global" />
                            <div class="form-text">Chave <code>AUTHENTICATION_API_KEY</code> do servidor Evolution API.</div>
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Nome da Instância</label>
                            <input type="text" id="kt_edit_evolution_instance_id" class="form-control form-control-solid" 
                                   placeholder="minha-instancia" />
                            <div class="form-text">Nome da instância na Evolution API.</div>
                        </div>
                    </div>
                    
                    <!-- Campos Native/Baileys (edição) -->
                    <div id="kt_edit_native_fields" style="display: none;">
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">URL da API</label>
                            <input type="url" id="kt_edit_native_api_url" class="form-control form-control-solid" 
                                   placeholder="http://127.0.0.1:3100" />
                        </div>
                    </div>
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <!--begin::Limite de Novas Conversas-->
                    <div class="mb-7">
                        <h5 class="fw-bold mb-3">
                            <i class="ki-duotone ki-shield-tick fs-4 text-warning me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Limite de Novas Conversas (Anti-Spam)
                        </h5>
                        
                        <div class="form-check form-switch form-check-custom form-check-solid mb-4">
                            <input class="form-check-input" type="checkbox" name="new_conv_limit_enabled" 
                                   id="kt_edit_whatsapp_limit_enabled" value="1" />
                            <label class="form-check-label fw-semibold text-gray-700" for="kt_edit_whatsapp_limit_enabled">
                                Habilitar limite de novas conversas manuais
                            </label>
                        </div>
                        
                        <div id="kt_edit_whatsapp_limit_fields" style="display: none;">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="fw-semibold fs-7 mb-2">Máximo de conversas</label>
                                    <input type="number" name="new_conv_limit_count" id="kt_edit_whatsapp_limit_count" 
                                           class="form-control form-control-solid" value="10" min="1" max="1000" />
                                </div>
                                <div class="col-md-4">
                                    <label class="fw-semibold fs-7 mb-2">A cada</label>
                                    <input type="number" name="new_conv_limit_period_value" id="kt_edit_whatsapp_limit_period_value" 
                                           class="form-control form-control-solid" value="1" min="1" max="999" />
                                </div>
                                <div class="col-md-4">
                                    <label class="fw-semibold fs-7 mb-2">Período</label>
                                    <select name="new_conv_limit_period" id="kt_edit_whatsapp_limit_period" class="form-select form-select-solid">
                                        <option value="minutes">Minuto(s)</option>
                                        <option value="hours" selected>Hora(s)</option>
                                        <option value="days">Dia(s)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-text mt-2">
                                <i class="ki-duotone ki-information fs-7 text-muted me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                Exemplo: 10 conversas a cada 1 hora. Isso limita apenas a criação manual de novas conversas, não afeta conversas recebidas.
                            </div>
                        </div>
                    </div>
                    <!--end::Limite de Novas Conversas-->
                    
                    <!--begin::Proxy Config (Native only)-->
                    <div id="kt_edit_proxy_section" style="display: none;">
                        <div class="separator separator-dashed my-5"></div>
                        <h5 class="fw-bold mb-4">
                            <i class="ki-duotone ki-shield fs-4 text-primary me-2"><span class="path1"></span><span class="path2"></span></i>
                            Configuração de Proxy
                        </h5>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">URL do Baileys Service</label>
                            <input type="text" name="native_service_url" id="kt_edit_whatsapp_native_url" class="form-control form-control-solid" 
                                   placeholder="http://127.0.0.1:3100" />
                        </div>
                        <div class="fv-row mb-7">
                            <label class="fw-semibold fs-6 mb-2">Endereço do Proxy</label>
                            <input type="text" name="proxy_host" id="kt_edit_whatsapp_proxy_host" class="form-control form-control-solid" 
                                   placeholder="socks5://proxy.exemplo.com:1080" />
                            <div class="form-text">Formato: socks5://host:porta ou http://host:porta. Deixe vazio para IP local.</div>
                        </div>
                        <div class="row mb-7">
                            <div class="col-6">
                                <label class="fw-semibold fs-6 mb-2">Usuário do Proxy</label>
                                <input type="text" name="proxy_user" id="kt_edit_whatsapp_proxy_user" class="form-control form-control-solid" placeholder="user" />
                            </div>
                            <div class="col-6">
                                <label class="fw-semibold fs-6 mb-2">Senha do Proxy</label>
                                <input type="password" name="proxy_pass" id="kt_edit_whatsapp_proxy_pass" class="form-control form-control-solid" placeholder="******" />
                            </div>
                        </div>
                    </div>
                    <!--end::Proxy Config-->
                    
                    <div class="separator separator-dashed my-5"></div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status Atual</label>
                        <div id="kt_edit_whatsapp_status_display" class="form-control form-control-solid bg-light-secondary"></div>
                    </div>
                    
                    <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-4">
                        <i class="ki-duotone ki-information-5 fs-2tx text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-stack flex-grow-1">
                            <div class="fw-semibold">
                                <div class="fs-6 text-gray-700">
                                    Após alterar o provider ou URL da API, você precisará escanear o QR Code novamente para reconectar.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_whatsapp_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Alterações</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Editar Conta WhatsApp-->

<!--begin::Modal - Configurar WavoIP-->
<?php if (\App\Helpers\Permission::can('whatsapp.edit')): ?>
<div class="modal fade" id="kt_modal_wavoip" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Configurar WavoIP</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_wavoip_form" class="form">
                <input type="hidden" name="account_id" id="kt_wavoip_account_id">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <h4 class="fw-bold" id="kt_wavoip_account_name"></h4>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                        <i class="ki-duotone ki-information fs-2x text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fs-7">Configure o token WavoIP para habilitar chamadas de voz via WhatsApp. Obtenha seu token em <a href="https://app.wavoip.com" target="_blank">app.wavoip.com</a></span>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Token WavoIP</label>
                        <input type="text" name="wavoip_token" id="kt_wavoip_token" class="form-control form-control-solid" 
                               placeholder="Seu token WavoIP" />
                        <div class="form-text">Token de autenticação obtido no painel WavoIP</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="wavoip_enabled" id="kt_wavoip_enabled" value="1" />
                            <label class="form-check-label" for="kt_wavoip_enabled">
                                <span class="fw-semibold">Habilitar chamadas de voz</span>
                            </label>
                        </div>
                        <div class="form-text">Ative para permitir chamadas de voz através desta conta WhatsApp</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_wavoip_submit" class="btn btn-primary">
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
<!--end::Modal - Configurar WavoIP-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
let currentAccountId = null;
let qrCodeStatusInterval = null;

// Toggle campos de provider (Quepasa vs Native vs Evolution)
function toggleProviderFields(provider) {
    const quepasaFields = document.getElementById("kt_quepasa_fields");
    const nativeFields = document.getElementById("kt_native_fields");
    const evolutionFields = document.getElementById("kt_evolution_fields");
    
    // Esconder todos primeiro
    if (quepasaFields) quepasaFields.style.display = "none";
    if (nativeFields) nativeFields.style.display = "none";
    if (evolutionFields) evolutionFields.style.display = "none";
    
    // Remover required de todos
    if (quepasaFields) quepasaFields.querySelectorAll("input").forEach(i => i.removeAttribute("required"));
    if (nativeFields) nativeFields.querySelectorAll("input").forEach(i => i.removeAttribute("required"));
    if (evolutionFields) evolutionFields.querySelectorAll("input").forEach(i => i.removeAttribute("required"));
    
    if (provider === "native") {
        if (nativeFields) nativeFields.style.display = "block";
    } else if (provider === "evolution") {
        if (evolutionFields) evolutionFields.style.display = "block";
    } else {
        if (quepasaFields) quepasaFields.style.display = "block";
    }
}

// Testar proxy
function testProxy() {
    const proxyHost = document.querySelector("input[name=proxy_host]")?.value;
    const proxyUser = document.querySelector("input[name=proxy_user]")?.value;
    const proxyPass = document.querySelector("input[name=proxy_pass]")?.value;
    const resultSpan = document.getElementById("kt_proxy_test_result");
    
    if (!proxyHost) {
        resultSpan.innerHTML = "<span class=\"text-warning\">Informe o endereço do proxy</span>";
        return;
    }
    
    resultSpan.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Testando...";
    
    // Montar proxy string com auth se necessário
    let proxyString = proxyHost;
    if (proxyUser) {
        const url = new URL(proxyHost.startsWith("http") || proxyHost.startsWith("socks") ? proxyHost : "socks5://" + proxyHost);
        url.username = proxyUser;
        url.password = proxyPass || "";
        proxyString = url.toString();
    }
    
    // Obter native_service_url
    const serviceUrl = document.querySelector("input[name=native_service_url]")?.value || "http://127.0.0.1:3100";
    
    fetch(serviceUrl + "/proxy/test", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ proxy: proxyString })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            resultSpan.innerHTML = "<span class=\"text-success\">✅ " + (data.message || "Proxy funcionando") + "</span>";
        } else {
            resultSpan.innerHTML = "<span class=\"text-danger\">❌ " + (data.message || "Falha na conexão") + "</span>";
        }
    })
    .catch(err => {
        resultSpan.innerHTML = "<span class=\"text-danger\">❌ Baileys Service não está acessível</span>";
    });
}

// Carregar etapas do funil
function loadFunnelStages(funnelId, targetSelectId, callback) {
    const stageSelect = document.getElementById(targetSelectId);
    
    if (!funnelId) {
        stageSelect.innerHTML = "<option value=\"\">Selecione um funil primeiro</option>";
        if (callback) callback();
        return;
    }
    
    // Loading
    stageSelect.innerHTML = "<option value=\"\">Carregando etapas...</option>";
    stageSelect.disabled = true;
    
    fetch("' . \App\Helpers\Url::to('/funnels') . '/" + funnelId + "/stages/json", {
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        stageSelect.disabled = false;
        
        if (data.success && data.stages && data.stages.length > 0) {
            let html = "<option value=\"\">Usar primeira etapa do funil</option>";
            data.stages.forEach(stage => {
                html += `<option value="${stage.id}">${stage.name}</option>`;
            });
            stageSelect.innerHTML = html;
        } else {
            stageSelect.innerHTML = "<option value=\"\">Nenhuma etapa encontrada</option>";
        }
        
        if (callback) callback();
    })
    .catch(error => {
        console.error("Erro ao carregar etapas:", error);
        stageSelect.disabled = false;
        stageSelect.innerHTML = "<option value=\"\">Erro ao carregar etapas</option>";
        if (callback) callback();
    });
}

// Toggle campos de provider no modal de edição
function toggleEditProviderFields(provider) {
    const quepasaFields = document.getElementById("kt_edit_quepasa_fields");
    const evolutionFields = document.getElementById("kt_edit_evolution_fields");
    const nativeFields = document.getElementById("kt_edit_native_fields");
    const proxySection = document.getElementById("kt_edit_proxy_section");
    
    // Esconder todos
    if (quepasaFields) quepasaFields.style.display = "none";
    if (evolutionFields) evolutionFields.style.display = "none";
    if (nativeFields) nativeFields.style.display = "none";
    if (proxySection) proxySection.style.display = "none";
    
    if (provider === "evolution") {
        if (evolutionFields) evolutionFields.style.display = "block";
    } else if (provider === "native") {
        if (nativeFields) nativeFields.style.display = "block";
        if (proxySection) proxySection.style.display = "block";
    } else {
        if (quepasaFields) quepasaFields.style.display = "block";
    }
}

// Abrir modal de edição da conta (campos principais)
function editAccount(account) {
    document.getElementById("kt_edit_whatsapp_id").value = account.id;
    document.getElementById("kt_edit_whatsapp_name").value = account.name || "";
    document.getElementById("kt_edit_whatsapp_phone").value = account.phone_number || "";
    
    // Selecionar provider
    const providerSelect = document.getElementById("kt_edit_provider_select");
    const provider = account.provider || "quepasa";
    providerSelect.value = provider;
    toggleEditProviderFields(provider);
    
    // Preencher campos Quepasa
    document.getElementById("kt_edit_whatsapp_api_url").value = account.api_url || "";
    document.getElementById("kt_edit_whatsapp_user").value = account.quepasa_user || "";
    document.getElementById("kt_edit_whatsapp_trackid").value = account.quepasa_trackid || "";
    document.getElementById("kt_edit_whatsapp_api_key").value = "";
    
    // Preencher campos Evolution
    document.getElementById("kt_edit_evolution_api_url").value = account.api_url || "";
    document.getElementById("kt_edit_evolution_api_key").value = account.api_key || "";
    document.getElementById("kt_edit_evolution_instance_id").value = account.instance_id || "";
    
    // Preencher campos Native
    document.getElementById("kt_edit_native_api_url").value = account.api_url || account.native_service_url || "http://127.0.0.1:3100";
    
    // Exibir status atual
    const statusDisplay = document.getElementById("kt_edit_whatsapp_status_display");
    const statusLabels = {
        "active": "Conectado",
        "inactive": "Inativo",
        "disconnected": "Desconectado"
    };
    statusDisplay.textContent = statusLabels[account.status] || account.status || "Desconhecido";
    
    // Preencher proxy (Native)
    if (provider === "native") {
        document.getElementById("kt_edit_whatsapp_native_url").value = account.native_service_url || "http://127.0.0.1:3100";
        document.getElementById("kt_edit_whatsapp_proxy_host").value = account.proxy_host || "";
        document.getElementById("kt_edit_whatsapp_proxy_user").value = account.proxy_user || "";
        document.getElementById("kt_edit_whatsapp_proxy_pass").value = "";
    }
    
    // Preencher campos de limite de novas conversas
    const limitEnabled = account.new_conv_limit_enabled == 1 || account.new_conv_limit_enabled === true;
    const limitCheckbox = document.getElementById("kt_edit_whatsapp_limit_enabled");
    const limitFields = document.getElementById("kt_edit_whatsapp_limit_fields");
    
    limitCheckbox.checked = limitEnabled;
    limitFields.style.display = limitEnabled ? "block" : "none";
    
    document.getElementById("kt_edit_whatsapp_limit_count").value = account.new_conv_limit_count || 10;
    document.getElementById("kt_edit_whatsapp_limit_period_value").value = account.new_conv_limit_period_value || 1;
    document.getElementById("kt_edit_whatsapp_limit_period").value = account.new_conv_limit_period || "hours";
    
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_edit_whatsapp"));
    modal.show();
}

// Toggle para mostrar/ocultar campos de limite
document.addEventListener("DOMContentLoaded", function() {
    const limitCheckbox = document.getElementById("kt_edit_whatsapp_limit_enabled");
    if (limitCheckbox) {
        limitCheckbox.addEventListener("change", function() {
            const limitFields = document.getElementById("kt_edit_whatsapp_limit_fields");
            limitFields.style.display = this.checked ? "block" : "none";
        });
    }
});

// Abrir modal de edição de configurações (funil/etapa)
function editAccountSettings(accountId, accountName, funnelId, stageId) {
    document.getElementById("kt_edit_account_id").value = accountId;
    document.getElementById("kt_edit_account_name").textContent = accountName;
    
    const funnelSelect = document.getElementById("kt_edit_default_funnel_select");
    const stageSelect = document.getElementById("kt_edit_default_stage_select");
    
    // Resetar selects
    stageSelect.innerHTML = "<option value=\"\">Selecione um funil primeiro</option>";
    
    // Selecionar funil atual
    if (funnelId) {
        funnelSelect.value = funnelId;
        
        // Carregar etapas do funil
        loadFunnelStages(funnelId, "kt_edit_default_stage_select", function() {
            // Após carregar, selecionar etapa atual
            if (stageId) {
                stageSelect.value = stageId;
            }
        });
    } else {
        funnelSelect.value = "";
    }
    
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_edit_account_settings"));
    modal.show();
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_modal_new_whatsapp_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_whatsapp_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
            // Mapear campos Evolution para campos padrão do backend
            if (formData.get("provider") === "evolution") {
                const evoUrl = formData.get("evolution_api_url");
                const evoKey = formData.get("evolution_api_key");
                const evoInstance = formData.get("evolution_instance_id");
                if (evoUrl) formData.set("api_url", evoUrl);
                if (evoKey) formData.set("api_key", evoKey);
                if (evoInstance) formData.set("instance_id", evoInstance);
                formData.delete("evolution_api_url");
                formData.delete("evolution_api_key");
                formData.delete("evolution_instance_id");
            }
            
            fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_new_whatsapp"));
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
    
    // Handler do formulário de configuração WavoIP
    const wavoipForm = document.getElementById("kt_modal_wavoip_form");
    if (wavoipForm) {
        wavoipForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_wavoip_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const accountId = document.getElementById("kt_wavoip_account_id").value;
            const formData = new FormData(wavoipForm);
            
            fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId + "/wavoip", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_wavoip"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar configuração WavoIP"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atualizar configuração WavoIP");
            });
        });
    }
    
    // Handler do formulário de edição de configurações
    const editForm = document.getElementById("kt_modal_edit_account_settings_form");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_edit_account_settings_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const accountId = document.getElementById("kt_edit_account_id").value;
            const formData = new FormData(editForm);
            
            fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId + "/settings", {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_account_settings"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao atualizar configurações"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao atualizar configurações");
            });
        });
    }
    
    // Handler do formulário de edição da conta WhatsApp
    const editWhatsappForm = document.getElementById("kt_modal_edit_whatsapp_form");
    if (editWhatsappForm) {
        editWhatsappForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_edit_whatsapp_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const accountId = document.getElementById("kt_edit_whatsapp_id").value;
            const formData = new FormData(editWhatsappForm);
            
            // Garantir que o checkbox de limite seja enviado (0 se não marcado)
            const limitCheckbox = document.getElementById("kt_edit_whatsapp_limit_enabled");
            if (limitCheckbox) {
                formData.set("new_conv_limit_enabled", limitCheckbox.checked ? "1" : "0");
            }
            
            // Mapear campos do provider ativo para os nomes corretos
            const editProvider = formData.get("provider") || "quepasa";
            if (editProvider === "evolution") {
                // Usar valores dos campos Evolution
                formData.set("api_url", document.getElementById("kt_edit_evolution_api_url").value || "");
                formData.set("api_key", document.getElementById("kt_edit_evolution_api_key").value || "");
                formData.set("instance_id", document.getElementById("kt_edit_evolution_instance_id").value || "");
            } else if (editProvider === "native") {
                formData.set("api_url", document.getElementById("kt_edit_native_api_url").value || "");
            }
            
            fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId, {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_whatsapp"));
                    modal.hide();
                    
                    // Mostrar mensagem de sucesso
                    Swal.fire({
                        icon: "success",
                        title: "Conta Atualizada!",
                        text: data.message || "As configurações foram salvas. Se você alterou a URL da API, escaneie o QR Code novamente.",
                        confirmButtonText: "OK"
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Erro",
                        text: data.message || "Erro ao atualizar conta"
                    });
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                Swal.fire({
                    icon: "error",
                    title: "Erro",
                    text: "Erro ao atualizar conta: " + error.message
                });
            });
        });
    }
});

function getQRCode(accountId) {
    currentAccountId = accountId;
    
    // Limpar intervalo anterior se existir
    if (qrCodeStatusInterval) {
        clearInterval(qrCodeStatusInterval);
        qrCodeStatusInterval = null;
    }
    
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_qrcode"));
    modal.show();
    
    const container = document.getElementById("kt_qrcode_container");
    container.innerHTML = `
        <div class="spinner-border text-primary mb-5" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
        <p class="text-muted">Gerando QR Code...</p>
    `;
    
    fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId + "/qrcode")
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = `
                    <img src="${data.qrcode}" alt="QR Code" class="img-fluid mb-5" style="max-width: 300px;" />
                    <p class="text-muted fs-7">QR Code válido por ${data.expires_in || 60} segundos</p>
                    <div id="qrCodeStatusMessage" class="mt-3"></div>
                `;
                
                // Iniciar polling para verificar status da conexão
                startQRCodeStatusPolling(accountId);
            } else {
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="ki-duotone ki-information fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        ${data.message || "Erro ao obter QR Code"}
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="alert alert-danger">
                    Erro ao obter QR Code
                </div>
            `;
        });
    
    // Limpar intervalo quando modal for fechado
    const qrModal = document.getElementById("kt_modal_qrcode");
    qrModal.addEventListener("hidden.bs.modal", function() {
        if (qrCodeStatusInterval) {
            clearInterval(qrCodeStatusInterval);
            qrCodeStatusInterval = null;
        }
    }, { once: true });
}

function startQRCodeStatusPolling(accountId) {
    const statusMessage = document.getElementById("qrCodeStatusMessage");
    let attempts = 0;
    const maxAttempts = 100; // 5 minutos (100 * 3 segundos)
    
    qrCodeStatusInterval = setInterval(function() {
        attempts++;
        
        // Atualizar mensagem de status
        if (statusMessage) {
            statusMessage.innerHTML = `
                <div class="alert alert-info d-flex align-items-center">
                    <i class="ki-duotone ki-loader fs-3 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <div class="fw-semibold">Aguardando conexão...</div>
                        <div class="fs-7">Escaneie o QR Code com o WhatsApp</div>
                    </div>
                </div>
            `;
        }
        
        // Verificar status real da conexão na Evolution API
        fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId + "/status?force_real_check=1", {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.status && data.status.connected) {
                    // Conexão bem-sucedida!
                    clearInterval(qrCodeStatusInterval);
                    qrCodeStatusInterval = null;
                    
                    if (statusMessage) {
                        statusMessage.innerHTML = `
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="ki-duotone ki-check-circle fs-2x me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div>
                                    <div class="fw-bold">WhatsApp conectado com sucesso!</div>
                                    <div class="fs-7">${data.status.phone_number ? "Número: " + data.status.phone_number : ""}</div>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Fechar modal após 2 segundos
                    setTimeout(function() {
                        const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_qrcode"));
                        if (modal) {
                            modal.hide();
                        }
                        // Recarregar página para atualizar status
                        location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                console.error("Erro ao verificar status:", error);
            });
        
        // Parar após máximo de tentativas
        if (attempts >= maxAttempts) {
            clearInterval(qrCodeStatusInterval);
            qrCodeStatusInterval = null;
            if (statusMessage) {
                statusMessage.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="ki-duotone ki-information fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Tempo de espera esgotado. Clique em "Atualizar QR Code" para gerar um novo.
                    </div>
                `;
            }
        }
    }, 3000); // Verificar a cada 3 segundos
}

function refreshQRCode() {
    // Limpar intervalo anterior
    if (qrCodeStatusInterval) {
        clearInterval(qrCodeStatusInterval);
        qrCodeStatusInterval = null;
    }
    
    if (currentAccountId) {
        getQRCode(currentAccountId);
    }
}

function checkStatus(accountId, forceReal = true) {
    // Mostrar indicador de loading
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span>`;
    
    // Usar verificação REAL (force_real_check=1) para verificar de verdade na API Quepasa
    const url = "' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId + "/status" + (forceReal ? "?force_real_check=1" : "");
    
    fetch(url, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json"
        }
    })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            
            if (data.success) {
                const status = data.status;
                const checkType = status.check_type === "real" ? "(verificação real)" : "(cache)";
                
                let title = status.connected ? "✅ WhatsApp Conectado" : "❌ WhatsApp Desconectado";
                let message = checkType + "\\n\\n";
                
                if (status.connected) {
                    message += "A conexão está funcionando corretamente.";
                } else {
                    message += "Motivo: " + (status.message || "Desconhecido");
                    message += "\\n\\n⚠️ Escaneie o QR Code para reconectar.";
                }
                
                if (status.phone_number) {
                    message += "\\n\\nNúmero: " + status.phone_number;
                }
                
                if (status.checked_at) {
                    message += "\\nVerificado em: " + status.checked_at;
                }
                
                alert(title + "\\n" + message);
                location.reload();
            } else {
                alert("Erro: " + (data.message || "Erro ao verificar status"));
            }
        })
        .catch(error => {
            alert("Erro ao verificar status");
        });
}

function disconnectAccount(accountId) {
    if (!confirm("Tem certeza que deseja desconectar esta conta WhatsApp?")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId + "/disconnect", {
        method: "POST",
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao desconectar"));
        }
    })
    .catch(error => {
        alert("Erro ao desconectar");
    });
}

function deleteAccount(accountId, accountName) {
    if (!confirm("Tem certeza que deseja deletar a conta \\"" + accountName + "\\"?\\n\\nEsta ação não pode ser desfeita.")) {
        return;
    }
    
    fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId, {
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

function configureWavoip(accountId, accountName, enabled, token) {
    document.getElementById("kt_wavoip_account_id").value = accountId;
    document.getElementById("kt_wavoip_account_name").textContent = accountName;
    document.getElementById("kt_wavoip_token").value = token || "";
    document.getElementById("kt_wavoip_enabled").checked = enabled == 1;
    
    const modal = new bootstrap.Modal(document.getElementById("kt_modal_wavoip"));
    modal.show();
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
