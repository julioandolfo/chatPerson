<?php
$layout = 'layouts.metronic.app';
$title = 'Notificame - Integrações';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Contas Notificame</h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 gap-lg-3">
                <div class="position-relative">
                    <select id="kt_filter_channel" class="form-select form-select-sm form-select-solid" style="min-width: 150px;" onchange="filterByChannel(this.value)">
                        <option value="">Todos os Canais</option>
                        <?php foreach ($channels as $channel): ?>
                            <option value="<?= htmlspecialchars($channel) ?>"><?= htmlspecialchars(ucfirst($channel)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (\App\Helpers\Permission::can('notificame.create')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_notificame">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Nova Conta
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                <i class="ki-duotone ki-information fs-2x text-danger me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-danger">Erro</h4>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($accounts)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-chat-dots fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma conta Notificame configurada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova conta Notificame para integrar múltiplos canais.</div>
                <?php if (\App\Helpers\Permission::can('notificame.create')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_notificame">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Criar Primeira Conta
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4" id="kt_table_notificame">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th class="min-w-150px">Nome</th>
                            <th class="min-w-100px">Canal</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-150px">Identificador</th>
                            <th class="min-w-150px">Funil/Etapa</th>
                            <th class="min-w-100px">Última Sincronização</th>
                            <th class="text-end min-w-150px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                        <tr data-channel="<?= htmlspecialchars($account['channel']) ?>">
                            <td>
                                <span class="text-dark fw-bold d-block fs-6"><?= htmlspecialchars($account['name']) ?></span>
                                <?php if (!empty($account['error_message'])): ?>
                                    <span class="text-danger fs-7 d-block mt-1">
                                        <i class="ki-duotone ki-information-5 fs-6">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <?= htmlspecialchars(substr($account['error_message'], 0, 50)) ?>...
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-light-primary"><?= htmlspecialchars(ucfirst($account['channel'])) ?></span>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'active' => 'success',
                                    'inactive' => 'warning',
                                    'disconnected' => 'danger',
                                    'error' => 'danger'
                                ];
                                $statusText = [
                                    'active' => 'Ativo',
                                    'inactive' => 'Inativo',
                                    'disconnected' => 'Desconectado',
                                    'error' => 'Erro'
                                ];
                                $currentStatus = $account['status'] ?? 'inactive';
                                ?>
                                <span class="badge badge-light-<?= $statusClass[$currentStatus] ?? 'warning' ?>">
                                    <?= $statusText[$currentStatus] ?? 'Desconhecido' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($account['phone_number'])): ?>
                                    <span class="text-dark fw-semibold d-block fs-7">
                                        <i class="ki-duotone ki-phone fs-6 text-primary me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?= htmlspecialchars($account['phone_number']) ?>
                                    </span>
                                <?php elseif (!empty($account['username'])): ?>
                                    <span class="text-dark fw-semibold d-block fs-7">
                                        <i class="ki-duotone ki-profile-user fs-6 text-info me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        @<?= htmlspecialchars($account['username']) ?>
                                    </span>
                                <?php elseif (!empty($account['account_id'])): ?>
                                    <span class="text-dark fw-semibold d-block fs-7">
                                        <i class="ki-duotone ki-abstract-26 fs-6 text-success me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?= htmlspecialchars($account['account_id']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fs-7">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($account['default_funnel_name'])): ?>
                                    <span class="text-dark fw-semibold d-block fs-7"><?= htmlspecialchars($account['default_funnel_name']) ?></span>
                                    <?php if (!empty($account['default_stage_name'])): ?>
                                        <span class="text-muted fs-8"><?= htmlspecialchars($account['default_stage_name']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted fs-7">Não configurado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($account['last_sync_at'])): ?>
                                    <span class="text-muted fs-7"><?= date('d/m/Y H:i', strtotime($account['last_sync_at'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted fs-7">Nunca</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm" 
                                            onclick="checkStatus(<?= $account['id'] ?>)" 
                                            title="Verificar Status">
                                        <i class="ki-duotone ki-information-5 fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </button>
                                    <?php if (\App\Helpers\Permission::can('notificame.send')): ?>
                                    <button type="button" class="btn btn-icon btn-bg-light btn-active-color-success btn-sm" 
                                            onclick="testMessage(<?= $account['id'] ?>, '<?= htmlspecialchars($account['channel'], ENT_QUOTES) ?>')" 
                                            title="Testar Envio">
                                        <i class="ki-duotone ki-send fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('notificame.edit')): ?>
                                    <button type="button" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm" 
                                            onclick="editAccount(<?= $account['id'] ?>, <?= htmlspecialchars(json_encode($account), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($funnels), ENT_QUOTES) ?>)" 
                                            title="Editar">
                                        <i class="ki-duotone ki-pencil fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <button type="button" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm" 
                                            onclick="configureWebhook(<?= $account['id'] ?>)" 
                                            title="Configurar Webhook">
                                        <i class="ki-duotone ki-code fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('notificame.delete')): ?>
                                    <button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm" 
                                            onclick="deleteAccount(<?= $account['id'] ?>, '<?= htmlspecialchars($account['name'], ENT_QUOTES) ?>')" 
                                            title="Deletar">
                                        <i class="ki-duotone ki-trash fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                    </button>
                                    <?php endif; ?>
                                </div>
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

<?php if (\App\Helpers\Permission::can('notificame.create')): ?>
<!--begin::Modal - Nova Conta-->
<div class="modal fade" id="kt_modal_new_notificame" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Nova Conta Notificame</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_form_new_notificame">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <label class="required fw-semibold fs-6 mb-2">Nome da Conta</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Ex: Notificame WhatsApp Principal" required>
                    </div>
                    <div class="mb-5">
                        <label class="required fw-semibold fs-6 mb-2">Canal</label>
                        <select name="channel" id="kt_channel_select" class="form-select form-select-solid" required onchange="updateChannelFields(this.value)">
                            <option value="">Selecione um canal</option>
                            <?php foreach ($channels as $channel): ?>
                                <option value="<?= htmlspecialchars($channel) ?>"><?= htmlspecialchars(ucfirst($channel)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-5">
                        <label class="required fw-semibold fs-6 mb-2">Token da API</label>
                        <input type="text" name="api_token" class="form-control form-control-solid" placeholder="Seu token da API Notificame" required>
                        <div class="form-text">Obtenha seu token em: <a href="https://app.notificame.com.br" target="_blank">app.notificame.com.br</a></div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">URL da API</label>
                        <input type="text" name="api_url" class="form-control form-control-solid" value="https://app.notificame.com.br/api/v1/" placeholder="URL base da API">
                    </div>
                    <div class="mb-5" id="kt_field_phone_number" style="display: none;">
                        <label class="fw-semibold fs-6 mb-2">Número de Telefone</label>
                        <input type="text" name="phone_number" class="form-control form-control-solid" placeholder="5511999999999">
                        <div class="form-text">Para WhatsApp: número completo com código do país</div>
                    </div>
                    <div class="mb-5" id="kt_field_username" style="display: none;">
                        <label class="fw-semibold fs-6 mb-2">Username</label>
                        <input type="text" name="username" class="form-control form-control-solid" placeholder="@username">
                        <div class="form-text">Para Instagram, Telegram, etc: username sem @</div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">ID da Conta</label>
                        <input type="text" name="account_id" class="form-control form-control-solid" placeholder="ID da conta na plataforma (opcional)">
                        <div class="form-text">ID específico da conta na plataforma Notificame</div>
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
                    
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">Funil Padrão</label>
                        <select name="default_funnel_id" id="kt_default_funnel_select" class="form-select form-select-solid" onchange="loadFunnelStages(this.value, 'kt_default_stage_select')">
                            <option value="">Usar padrão do sistema</option>
                            <?php if (!empty($funnels)): ?>
                                <?php foreach ($funnels as $funnel): ?>
                                    <option value="<?= $funnel['id'] ?>"><?= htmlspecialchars($funnel['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">Etapa Padrão</label>
                        <select name="default_stage_id" id="kt_default_stage_select" class="form-select form-select-solid">
                            <option value="">Selecione um funil primeiro</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="kt_submit_new_notificame">
                        <span class="indicator-label">Criar Conta</span>
                        <span class="indicator-progress">Aguarde...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal-->
<?php endif; ?>

<?php if (\App\Helpers\Permission::can('notificame.edit')): ?>
<!--begin::Modal - Editar Conta-->
<div class="modal fade" id="kt_modal_edit_notificame" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Conta Notificame</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_form_edit_notificame">
                <input type="hidden" name="id" id="kt_edit_account_id">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <label class="required fw-semibold fs-6 mb-2">Nome da Conta</label>
                        <input type="text" name="name" id="kt_edit_name" class="form-control form-control-solid" required>
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">Token da API</label>
                        <input type="text" name="api_token" id="kt_edit_api_token" class="form-control form-control-solid" placeholder="Deixe vazio para não alterar">
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">URL da API</label>
                        <input type="url" name="api_url" id="kt_edit_api_url" class="form-control form-control-solid" placeholder="https://app.notificame.com.br/api/v1/">
                        <div class="form-text">Use a URL base da API. Padrão: https://app.notificame.com.br/api/v1/</div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">ID da Conta</label>
                        <input type="text" name="account_id" id="kt_edit_account_id_field" class="form-control form-control-solid">
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">Funil Padrão</label>
                        <select name="default_funnel_id" id="kt_edit_default_funnel_id" class="form-select form-select-solid" onchange="loadFunnelStages(this.value, 'kt_edit_default_stage_id')">
                            <option value="">Usar padrão do sistema</option>
                            <?php if (!empty($funnels)): ?>
                                <?php foreach ($funnels as $funnel): ?>
                                    <option value="<?= $funnel['id'] ?>"><?= htmlspecialchars($funnel['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">Etapa Padrão</label>
                        <select name="default_stage_id" id="kt_edit_default_stage_id" class="form-select form-select-solid">
                            <option value="">Selecione um funil primeiro</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="kt_submit_edit_notificame">
                        <span class="indicator-label">Salvar Alterações</span>
                        <span class="indicator-progress">Aguarde...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Configurar Webhook-->
<div class="modal fade" id="kt_modal_webhook_notificame" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Configurar Webhook</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_form_webhook_notificame">
                <input type="hidden" name="id" id="kt_webhook_account_id">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="alert alert-info d-flex align-items-center p-5 mb-7">
                        <i class="ki-duotone ki-information fs-2x text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fs-7">Configure o webhook para receber mensagens e eventos do Notificame automaticamente.</span>
                        </div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">URL do Webhook</label>
                        <input type="url" name="webhook_url" id="kt_webhook_url" class="form-control form-control-solid" 
                               value="<?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http' ?>://<?= $_SERVER['HTTP_HOST'] ?? 'localhost' ?>/webhooks/notificame" 
                               placeholder="https://seudominio.com/webhooks/notificame">
                        <div class="form-text">Deixe vazio para usar a URL padrão do sistema</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="kt_submit_webhook_notificame">
                        <span class="indicator-label">Configurar Webhook</span>
                        <span class="indicator-progress">Aguarde...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal-->
<?php endif; ?>

<?php if (\App\Helpers\Permission::can('notificame.send')): ?>
<!--begin::Modal - Testar Mensagem-->
<div class="modal fade" id="kt_modal_test_message" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Testar Envio de Mensagem</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_form_test_message">
                <input type="hidden" name="id" id="kt_test_account_id">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-5">
                        <label class="required fw-semibold fs-6 mb-2" id="kt_test_to_label">Destinatário</label>
                        <input type="text" name="to" id="kt_test_to" class="form-control form-control-solid" 
                               placeholder="" required>
                        <div class="form-text" id="kt_test_to_help">Número de telefone, username ou ID do destinatário</div>
                    </div>
                    <div class="mb-5">
                        <label class="required fw-semibold fs-6 mb-2">Mensagem</label>
                        <textarea name="message" id="kt_test_message" class="form-control form-control-solid" rows="4" 
                                  placeholder="Digite a mensagem de teste..." required>Mensagem de teste do sistema</textarea>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="kt_submit_test_message">
                        <span class="indicator-label">Enviar</span>
                        <span class="indicator-progress">Enviando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal-->
<?php endif; ?>

<!--begin::Modal - Status-->
<div class="modal fade" id="kt_modal_status" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Status da Conexão</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="kt_status_content">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
// Filtrar por canal
function filterByChannel(channel) {
    const rows = document.querySelectorAll('#kt_table_notificame tbody tr');
    rows.forEach(row => {
        if (!channel || row.dataset.channel === channel) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Atualizar campos baseado no canal selecionado
function updateChannelFields(channel) {
    const phoneField = document.getElementById('kt_field_phone_number');
    const usernameField = document.getElementById('kt_field_username');
    
    if (channel === 'whatsapp' || channel === 'email') {
        phoneField.style.display = channel === 'whatsapp' ? 'block' : 'none';
        usernameField.style.display = 'none';
    } else if (['instagram', 'telegram', 'facebook', 'linkedin', 'youtube', 'tiktok'].includes(channel)) {
        phoneField.style.display = 'none';
        usernameField.style.display = 'block';
    } else {
        phoneField.style.display = 'none';
        usernameField.style.display = 'none';
    }
}

// Carregar etapas do funil
function loadFunnelStages(funnelId, selectId) {
    const select = document.getElementById(selectId);
    
    if (!funnelId) {
        select.innerHTML = '<option value="">Selecione um funil primeiro</option>';
        return;
    }
    
    select.innerHTML = '<option value="">Carregando...</option>';
    select.disabled = true;
    
    fetch(`/funnels/${funnelId}/stages/json`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            select.disabled = false;
            select.innerHTML = '<option value="">Usar primeira etapa do funil</option>';
            if (data.success && data.stages && data.stages.length > 0) {
                data.stages.forEach(stage => {
                    const option = document.createElement('option');
                    option.value = stage.id;
                    option.textContent = stage.name;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">Nenhuma etapa encontrada</option>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar etapas:', error);
            select.disabled = false;
            select.innerHTML = '<option value="">Erro ao carregar</option>';
        });
}

// Verificar status
function checkStatus(id) {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_status'));
    const content = document.getElementById('kt_status_content');
    content.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
    modal.show();
    
    fetch(`/integrations/notificame/accounts/${id}/status`)
        .then(async response => {
            const text = await response.text();
            let data = null;
            try {
                data = JSON.parse(text);
            } catch (e) {
                // resposta não é JSON, mostrar preview
                content.innerHTML = `<div class="alert alert-danger">A API retornou uma resposta inválida (não-JSON).<br><small>${text.substring(0, 200)}</small></div>`;
                return;
            }
            
            if (data.success && data.status) {
                const status = data.status;
                const statusClass = status.connected ? 'success' : 'danger';
                const statusIcon = status.connected ? 'check-circle' : 'cross-circle';
                
                let detailsHtml = '';
                if (status.details) {
                    detailsHtml = '<div class="mt-4"><h5 class="fw-bold fs-7 mb-3">Detalhes:</h5><ul class="list-unstyled mb-0">';
                    Object.keys(status.details).forEach(key => {
                        detailsHtml += `<li class="mb-1"><span class="fw-semibold">${key}</span>: <span class="text-muted">${status.details[key]}</span></li>`;
                    });
                    detailsHtml += '</ul></div>';
                }
                
                content.innerHTML = `
                    <div class="text-center mb-7">
                        <i class="ki-duotone ki-${statusIcon} fs-3x text-${statusClass}">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </div>
                    <div class="mb-5">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-semibold">Status:</span>
                            <span class="badge badge-light-${statusClass}">${status.status || 'Desconhecido'}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-semibold">Conectado:</span>
                            <span>${status.connected ? 'Sim' : 'Não'}</span>
                        </div>
                        <div class="mb-2">
                            <span class="fw-semibold d-block mb-1">Mensagem:</span>
                            <span class="text-muted">${status.message || '-'}</span>
                        </div>
                        ${detailsHtml}
                        <div class="mt-4">
                            <span class="fw-semibold d-block">Endpoint usado:</span>
                            <span class="text-muted">${status.endpoint_used || '-'}</span>
                        </div>
                        <div class="mt-2">
                            <span class="fw-semibold d-block">API URL:</span>
                            <span class="text-muted">${status.api_url || '-'}</span>
                        </div>
                    </div>
                `;
            } else {
                const status = data.status || {};
                const details = status.details ? JSON.stringify(status.details) : '';
                content.innerHTML = '<div class="alert alert-danger">Erro ao verificar status: ' + (status.message || data.message || 'Erro desconhecido') + (details ? '<br><small>' + details + '</small>' : '') + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Erro ao verificar status: ' + error.message + '</div>';
        });
}

// Editar conta
function editAccount(id, account, funnels) {
    document.getElementById('kt_edit_account_id').value = id;
    document.getElementById('kt_edit_name').value = account.name || '';
    document.getElementById('kt_edit_api_token').value = '';
    document.getElementById('kt_edit_api_url').value = account.api_url || 'https://app.notificame.com.br/api/v1/';
    document.getElementById('kt_edit_account_id_field').value = account.account_id || '';
    document.getElementById('kt_edit_default_funnel_id').value = account.default_funnel_id || '';
    
    if (account.default_funnel_id) {
        loadFunnelStages(account.default_funnel_id, 'kt_edit_default_stage_id');
        setTimeout(() => {
            document.getElementById('kt_edit_default_stage_id').value = account.default_stage_id || '';
        }, 500);
    }
    
    new bootstrap.Modal(document.getElementById('kt_modal_edit_notificame')).show();
}

// Configurar webhook
function configureWebhook(id) {
    document.getElementById('kt_webhook_account_id').value = id;
    new bootstrap.Modal(document.getElementById('kt_modal_webhook_notificame')).show();
}

// Testar mensagem
function testMessage(id, channel) {
    document.getElementById('kt_test_account_id').value = id;
    const toInput = document.getElementById('kt_test_to');
    const toLabel = document.getElementById('kt_test_to_label');
    const toHelp = document.getElementById('kt_test_to_help');
    
    if (channel === 'whatsapp') {
        toLabel.textContent = 'Número de Telefone';
        toInput.placeholder = '5511999999999';
        toHelp.textContent = 'Número completo com código do país';
    } else if (['instagram', 'telegram', 'facebook'].includes(channel)) {
        toLabel.textContent = 'Username';
        toInput.placeholder = '@username';
        toHelp.textContent = 'Username sem @';
    } else if (channel === 'email') {
        toLabel.textContent = 'E-mail';
        toInput.placeholder = 'email@exemplo.com';
        toHelp.textContent = 'Endereço de e-mail completo';
    } else {
        toLabel.textContent = 'Destinatário';
        toInput.placeholder = 'ID ou identificador';
        toHelp.textContent = 'ID ou identificador do destinatário';
    }
    
    new bootstrap.Modal(document.getElementById('kt_modal_test_message')).show();
}

// Deletar conta
function deleteAccount(id, name) {
    if (confirm(`Tem certeza que deseja deletar a conta "${name}"?`)) {
        fetch(`/integrations/notificame/accounts/${id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    text: data.message || 'Conta deletada com sucesso!',
                    icon: 'success',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: { confirmButton: 'btn btn-primary' }
                }).then(() => location.reload());
            } else {
                Swal.fire({
                    text: data.message || 'Erro ao deletar conta',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            }
        })
        .catch(error => {
            Swal.fire({
                text: 'Erro ao deletar conta: ' + error.message,
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            });
        });
    }
}

<?php if (\App\Helpers\Permission::can('notificame.create')): ?>
// Criar conta
document.getElementById('kt_form_new_notificame').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = document.getElementById('kt_submit_new_notificame');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;
    
    fetch('/integrations/notificame/accounts', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        if (data.success) {
            Swal.fire({
                text: data.message || 'Conta criada com sucesso!',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('kt_modal_new_notificame')).hide();
                location.reload();
            });
        } else {
            Swal.fire({
                text: data.message || 'Erro ao criar conta',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            });
        }
    })
    .catch(error => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        Swal.fire({
            text: 'Erro ao criar conta: ' + error.message,
            icon: 'error',
            buttonsStyling: false,
            confirmButtonText: 'OK',
            customClass: { confirmButton: 'btn btn-primary' }
        });
    });
});
<?php endif; ?>

<?php if (\App\Helpers\Permission::can('notificame.edit')): ?>
// Editar conta
document.getElementById('kt_form_edit_notificame').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const id = formData.get('id');
    const submitBtn = document.getElementById('kt_submit_edit_notificame');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;
    
    fetch(`/integrations/notificame/accounts/${id}`, {
        method: 'PUT',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        if (data.success) {
            Swal.fire({
                text: data.message || 'Conta atualizada com sucesso!',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('kt_modal_edit_notificame')).hide();
                location.reload();
            });
        } else {
            Swal.fire({
                text: data.message || 'Erro ao atualizar conta',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            });
        }
    })
    .catch(error => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        Swal.fire({
            text: 'Erro ao atualizar conta: ' + error.message,
            icon: 'error',
            buttonsStyling: false,
            confirmButtonText: 'OK',
            customClass: { confirmButton: 'btn btn-primary' }
        });
    });
});

// Configurar webhook
document.getElementById('kt_form_webhook_notificame').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const id = formData.get('id');
    const submitBtn = document.getElementById('kt_submit_webhook_notificame');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;
    
    fetch(`/integrations/notificame/accounts/${id}/webhook`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        if (data.success) {
            Swal.fire({
                text: data.message || 'Webhook configurado com sucesso!',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('kt_modal_webhook_notificame')).hide();
            });
        } else {
            Swal.fire({
                text: data.message || 'Erro ao configurar webhook',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            });
        }
    })
    .catch(error => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        Swal.fire({
            text: 'Erro ao configurar webhook: ' + error.message,
            icon: 'error',
            buttonsStyling: false,
            confirmButtonText: 'OK',
            customClass: { confirmButton: 'btn btn-primary' }
        });
    });
});
<?php endif; ?>

<?php if (\App\Helpers\Permission::can('notificame.send')): ?>
// Testar mensagem
document.getElementById('kt_form_test_message').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const id = formData.get('id');
    const submitBtn = document.getElementById('kt_submit_test_message');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;
    
    fetch(`/integrations/notificame/accounts/${id}/test`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        if (data.success) {
            Swal.fire({
                text: data.message || 'Mensagem enviada com sucesso!',
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('kt_modal_test_message')).hide();
            });
        } else {
            Swal.fire({
                text: data.message || 'Erro ao enviar mensagem',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            });
        }
    })
    .catch(error => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        Swal.fire({
            text: 'Erro ao enviar mensagem: ' + error.message,
            icon: 'error',
            buttonsStyling: false,
            confirmButtonText: 'OK',
            customClass: { confirmButton: 'btn btn-primary' }
        });
    });
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/metronic/app.php';
?>
