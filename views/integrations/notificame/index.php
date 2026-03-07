<?php
$layout = 'layouts.metronic.app';
$title = 'Notificame - Integrações';

ob_start();
?>
<!--begin::Tabs Navigation-->
<ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x mb-5 fs-6">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tab_accounts">
            <i class="ki-duotone ki-abstract-26 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
            Contas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab_templates">
            <i class="ki-duotone ki-document fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            Templates
        </a>
    </li>
</ul>
<!--end::Tabs Navigation-->

<!--begin::Tab Content-->
<div class="tab-content">

<!--begin::Tab Contas-->
<div class="tab-pane fade show active" id="tab_accounts">
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
                                <span class="badge badge-light-<?= $statusClass[$currentStatus] ?? 'warning' ?>" id="status_badge_<?= $account['id'] ?>">
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
</div>
<!--end::Tab Contas-->

<!--begin::Tab Templates-->
<div class="tab-pane fade" id="tab_templates">
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Templates Notificame</h3>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <select id="kt_template_account" class="form-select form-select-sm form-select-solid" style="min-width: 200px;" onchange="loadTemplates(this.value)">
                    <option value="">Selecione uma conta</option>
                    <?php foreach ($accounts as $account): ?>
                        <?php if (in_array($account['channel'] ?? '', ['whatsapp', 'instagram', 'facebook', 'telegram'])): ?>
                        <option value="<?= $account['id'] ?>" data-channel="<?= htmlspecialchars($account['channel'] ?? '') ?>">
                            <?= htmlspecialchars($account['name']) ?> (<?= ucfirst($account['channel'] ?? '') ?>)
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-sm btn-light-primary" onclick="loadTemplates(document.getElementById('kt_template_account').value)" id="btn_refresh_templates">
                    <i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i>
                    Sincronizar
                </button>
                <?php if (\App\Helpers\Permission::can('notificame.edit')): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="showCreateTemplateModal()" id="btn_new_template">
                    <i class="ki-duotone ki-plus fs-4"></i>
                    Novo Template
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <div id="templates_empty" class="text-center py-15">
            <i class="ki-duotone ki-document fs-3x text-muted mb-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            <p class="text-muted fs-5">Selecione uma conta acima para visualizar seus templates</p>
        </div>
        <div id="templates_loading" class="text-center py-15" style="display:none;">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-3">Carregando templates...</p>
        </div>
        <div id="templates_error" class="alert alert-danger" style="display:none;"></div>

        <div id="templates_stats" class="row g-5 mb-8" style="display:none;">
            <div class="col-6 col-md-3">
                <div class="border border-dashed rounded p-4 text-center">
                    <div class="fs-2 fw-bold text-primary" id="stat_total">0</div>
                    <div class="fs-7 text-muted">Total</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border border-dashed rounded p-4 text-center">
                    <div class="fs-2 fw-bold text-success" id="stat_approved">0</div>
                    <div class="fs-7 text-muted">Aprovados</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border border-dashed rounded p-4 text-center">
                    <div class="fs-2 fw-bold text-warning" id="stat_pending">0</div>
                    <div class="fs-7 text-muted">Pendentes</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border border-dashed rounded p-4 text-center">
                    <div class="fs-2 fw-bold text-danger" id="stat_rejected">0</div>
                    <div class="fs-7 text-muted">Rejeitados</div>
                </div>
            </div>
        </div>

        <div id="templates_table_container" style="display:none;">
            <div class="table-responsive">
                <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th class="min-w-150px">Nome</th>
                            <th class="min-w-100px">Categoria</th>
                            <th class="min-w-80px">Idioma</th>
                            <th class="min-w-80px">Status</th>
                            <th class="min-w-200px">Conteúdo</th>
                            <th class="text-end min-w-120px">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="templates_tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
<!--end::Tab Templates-->

</div>
<!--end::Tab Content-->

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
                        <input type="text" name="api_url" class="form-control form-control-solid" value="https://api.notificame.com.br/v1/" placeholder="URL base da API">
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
                        <input type="url" name="api_url" id="kt_edit_api_url" class="form-control form-control-solid" placeholder="https://api.notificame.com.br/v1/">
                        <div class="form-text">Use a URL base da API. Padrão: https://api.notificame.com.br/v1/</div>
                    </div>
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">ID da Conta (Token do Canal)</label>
                        <div class="input-group">
                            <input type="text" name="account_id" id="kt_edit_account_id_field" class="form-control form-control-solid" placeholder="Token do canal NotificaMe">
                            <button type="button" class="btn btn-light-primary" id="kt_btn_fetch_subaccounts">
                                <i class="ki-duotone ki-magnifier fs-3"></i> Subcontas
                            </button>
                            <button type="button" class="btn btn-light-success" id="kt_btn_discover_channels">
                                <i class="ki-duotone ki-electricity fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span><span class="path7"></span><span class="path8"></span><span class="path9"></span><span class="path10"></span></i> Auto-detectar
                            </button>
                        </div>
                        <div class="form-text">
                            Token do canal (token_do_canal) do NotificaMe. Usado como "from" para enviar mensagens e na URL de templates.
                            <br>Encontre em: <a href="https://hub.notificame.com.br/" target="_blank" class="text-primary">hub.notificame.com.br</a> → Canais → copie o token/ID do canal.
                        </div>
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

<!--begin::Modal - Criar/Editar Template-->
<div class="modal fade" id="kt_modal_template" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold" id="kt_template_modal_title">
                    <i class="ki-duotone ki-plus-square fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    Novo Template
                </h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <form id="kt_form_template">
                <input type="hidden" name="template_id" id="kt_tpl_id">
                <input type="hidden" name="edit_mode" id="kt_tpl_edit_mode" value="0">
                <div class="modal-body scroll-y" style="max-height: 70vh;">
                    <div class="row g-5">
                        <div class="col-md-6">
                            <label class="form-label required">Conta</label>
                            <select name="account_id" id="kt_tpl_account" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($accounts as $account): ?>
                                    <?php if (in_array($account['channel'] ?? '', ['whatsapp', 'instagram', 'facebook', 'telegram'])): ?>
                                    <option value="<?= $account['id'] ?>" data-channel="<?= htmlspecialchars($account['channel'] ?? '') ?>">
                                        <?= htmlspecialchars($account['name']) ?> (<?= ucfirst($account['channel'] ?? '') ?>)
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Categoria</label>
                            <select name="category" id="kt_tpl_category" class="form-select form-select-solid" required>
                                <option value="UTILITY">Utilidade (notificações, atualizações)</option>
                                <option value="MARKETING">Marketing (promoções, ofertas)</option>
                                <option value="AUTHENTICATION">Autenticação (códigos, verificação)</option>
                            </select>
                            <div class="form-text">A categoria afeta o custo e as regras de envio</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Nome do Template</label>
                            <input type="text" name="name" id="kt_tpl_name" class="form-control form-control-solid"
                                   placeholder="ex: pedido_confirmado" pattern="[a-z0-9_]+" required>
                            <div class="form-text">Apenas letras minúsculas, números e underscores</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Idioma</label>
                            <select name="language" id="kt_tpl_language" class="form-select form-select-solid" required>
                                <option value="pt_BR" selected>Português (BR)</option>
                                <option value="en_US">English (US)</option>
                                <option value="es">Español</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Cabeçalho (opcional)</label>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="header_type" id="kt_tpl_header_type" class="form-select form-select-solid" onchange="toggleTplHeaderFields(this.value)">
                                        <option value="NONE">Sem cabeçalho</option>
                                        <option value="TEXT">Texto</option>
                                        <option value="IMAGE">Imagem</option>
                                        <option value="VIDEO">Vídeo</option>
                                        <option value="DOCUMENT">Documento</option>
                                    </select>
                                </div>
                                <div class="col-md-8" id="kt_tpl_header_text_field" style="display:none;">
                                    <input type="text" name="header_text" id="kt_tpl_header_text" class="form-control form-control-solid"
                                           placeholder="Texto do cabeçalho" maxlength="60">
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label required">Corpo da Mensagem</label>
                            <textarea name="body_text" id="kt_tpl_body" class="form-control form-control-solid" rows="5" required
                                      placeholder="Digite o texto da mensagem. Use {{1}}, {{2}} etc. para variáveis.&#10;&#10;Ex: Olá {{1}}, seu pedido #{{2}} foi confirmado!"
                                      oninput="updateTplPreview()"></textarea>
                            <div class="form-text">Use <code>{{1}}</code>, <code>{{2}}</code> etc. para variáveis dinâmicas (máx. 1024 caracteres)</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Rodapé (opcional)</label>
                            <input type="text" name="footer_text" id="kt_tpl_footer" class="form-control form-control-solid"
                                   placeholder="Ex: Enviado por Sua Empresa" maxlength="60">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Botões (opcional, máx. 3)</label>
                            <div id="kt_tpl_buttons_container"></div>
                            <button type="button" class="btn btn-sm btn-light-primary mt-2" onclick="addTplButton()">
                                <i class="ki-duotone ki-plus fs-4"></i> Adicionar Botão
                            </button>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Pré-visualização</label>
                            <div class="bg-light rounded p-5" id="kt_tpl_preview_container">
                                <div class="d-flex justify-content-end">
                                    <div class="bg-success bg-opacity-15 rounded p-3" style="max-width: 350px; min-width: 250px;">
                                        <div id="kt_tpl_preview_header" class="fw-bold mb-1" style="display:none;"></div>
                                        <div id="kt_tpl_preview_body" class="text-dark fs-7">A mensagem aparecerá aqui...</div>
                                        <div id="kt_tpl_preview_footer" class="text-muted fs-8 mt-1" style="display:none;"></div>
                                        <div id="kt_tpl_preview_buttons" class="mt-2" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="kt_submit_template">
                        <span class="indicator-label">Criar Template</span>
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

<!--begin::Modal - Visualizar Template-->
<div class="modal fade" id="kt_modal_view_template" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold">
                    <i class="ki-duotone ki-document fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    Detalhes do Template
                </h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body scroll-y" style="max-height: 70vh;">
                <div id="kt_view_template_content">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <?php if (\App\Helpers\Permission::can('notificame.edit')): ?>
                <button type="button" class="btn btn-light-primary" id="kt_view_tpl_edit_btn" onclick="editTemplateFromView()">
                    <i class="ki-duotone ki-pencil fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Editar
                </button>
                <?php endif; ?>
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
                content.innerHTML = `<div class="alert alert-danger">A API retornou uma resposta inválida (não-JSON).<br><small>${text.substring(0, 200)}</small></div>`;
                return;
            }
            
            if (data.success && data.status) {
                const status = data.status;
                const statusClass = status.connected ? 'success' : 'danger';
                const statusIcon = status.connected ? 'check-circle' : 'cross-circle';

                // Atualizar badge de status na tabela em tempo real
                const badge = document.getElementById('status_badge_' + id);
                if (badge) {
                    badge.className = 'badge badge-light-' + statusClass;
                    badge.textContent = status.connected ? 'Ativo' : 'Desconectado';
                }
                
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
    document.getElementById('kt_edit_api_url').value = account.api_url || 'https://api.notificame.com.br/v1/';
    document.getElementById('kt_edit_account_id_field').value = account.account_id || '';
    document.getElementById('kt_edit_default_funnel_id').value = account.default_funnel_id || '';
    
    if (account.default_funnel_id) {
        loadFunnelStages(account.default_funnel_id, 'kt_edit_default_stage_id');
        setTimeout(() => {
            document.getElementById('kt_edit_default_stage_id').value = account.default_stage_id || '';
        }, 500);
    }
    
    // Bind buscar subcontas
    const fetchBtn = document.getElementById('kt_btn_fetch_subaccounts');
    if (fetchBtn) {
        fetchBtn.onclick = function() {
            fetch(`/integrations/notificame/accounts/${id}/subaccounts`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire({
                        text: data.message || 'Erro ao buscar subcontas',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                    return;
                }
                const list = Array.isArray(data.data) ? data.data : [];
                if (!list.length) {
                    Swal.fire({
                        text: 'Nenhuma subconta retornada pela API',
                        icon: 'info',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                    return;
                }
                // Montar select no modal
                let html = '<div class="mb-3"><label class="fw-semibold">Selecione a subconta (account_id)</label><select id="kt_subaccounts_select" class="form-select form-select-solid">';
                list.forEach(item => {
                    const accId = item.acccount_id || item.account_id || '';
                    const name = item.name || accId || 'Subconta';
                    html += `<option value="${accId}">${name} (${accId})</option>`;
                });
                html += '</select></div>';
                Swal.fire({
                    title: 'Subcontas',
                    html,
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Usar este ID',
                    cancelButtonText: 'Cancelar',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-light'
                    }
                }).then(res => {
                    if (res.isConfirmed) {
                        const select = document.getElementById('kt_subaccounts_select');
                        if (select) {
                            document.getElementById('kt_edit_account_id_field').value = select.value || '';
                        }
                    }
                });
            })
            .catch(err => {
                Swal.fire({
                    text: 'Erro ao buscar subcontas: ' + err.message,
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            });
        };
    }

    // Bind auto-detectar token do canal
    const discoverBtn = document.getElementById('kt_btn_discover_channels');
    if (discoverBtn) {
        discoverBtn.onclick = function() {
            Swal.fire({ title: 'Buscando token do canal...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch(`/integrations/notificame/accounts/${id}/discover-channels`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire({
                        title: 'Erro',
                        text: data.message || 'Erro ao buscar canais',
                        icon: 'error',
                        buttonsStyling: false,
                        confirmButtonText: 'OK',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                    return;
                }
                const channels = data.channels || [];
                if (!channels.length) {
                    Swal.fire({
                        title: 'Token não encontrado automaticamente',
                        html: `<div class="text-start">
                            <p>Não foi possível auto-detectar o token do canal via API.</p>
                            <p class="fw-bold mt-3">Como encontrar manualmente:</p>
                            <ol class="fs-7">
                                <li>Acesse <a href="https://hub.notificame.com.br/" target="_blank">hub.notificame.com.br</a></li>
                                <li>Vá em <strong>Canais</strong></li>
                                <li>Clique no canal WhatsApp desejado</li>
                                <li>Copie o <strong>Token</strong> ou <strong>ID do Canal</strong></li>
                                <li>Cole no campo "ID da Conta" aqui</li>
                            </ol>
                            <p class="text-muted fs-8 mt-3">O token geralmente é uma string alfanumérica longa (ex: a1b2c3d4-e5f6-...).</p>
                        </div>`,
                        icon: 'info',
                        buttonsStyling: false,
                        confirmButtonText: 'Entendi',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                    return;
                }

                let html = '<div class="mb-3 text-start"><label class="fw-semibold">Token(s) encontrado(s):</label>';
                html += '<select id="kt_discovered_channel_select" class="form-select form-select-solid mt-2">';
                channels.forEach(ch => {
                    const label = ch.name || ch.type || ch.source || 'Canal';
                    const extra = ch.webhook ? ` (webhook: ${ch.webhook.substring(0, 40)}...)` : '';
                    html += `<option value="${ch.token}">${label} — ${ch.token.substring(0, 30)}...${extra}</option>`;
                });
                html += '</select></div>';
                html += '<div class="text-muted fs-8 text-start">Fonte: ' + channels.map(c => c.source).join(', ') + '</div>';

                Swal.fire({
                    title: 'Token do Canal Encontrado',
                    html,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Usar este token',
                    cancelButtonText: 'Cancelar',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light' }
                }).then(res => {
                    if (res.isConfirmed) {
                        const select = document.getElementById('kt_discovered_channel_select');
                        if (select) {
                            document.getElementById('kt_edit_account_id_field').value = select.value || '';
                        }
                    }
                });
            })
            .catch(err => {
                Swal.fire({
                    title: 'Erro',
                    text: 'Erro ao buscar canais: ' + err.message,
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            });
        };
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
    } else if (channel === 'instagram') {
        toLabel.textContent = 'ID do Destinatário (Instagram)';
        toInput.placeholder = '123456789012345';
        toHelp.textContent = 'Use o ID do perfil (recipient id), não o username';
    } else if (['telegram', 'facebook'].includes(channel)) {
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
    // Garantir que api_url vá na requisição (caso o browser não envie se vazio)
    if (!formData.has('api_url')) {
        formData.append('api_url', document.getElementById('kt_edit_api_url').value || '');
    }
    const submitBtn = document.getElementById('kt_submit_edit_notificame');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;
    
    fetch(`/integrations/notificame/accounts/${id}`, {
        method: 'POST', // rota aceita POST para atualização
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
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error(text.substring(0, 200) || 'Resposta inválida da API (não-JSON)');
        }
    })
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

// ========== Templates ==========
let _cachedTemplates = [];

function loadTemplates(accountId) {
    if (!accountId) return;
    const emptyEl = document.getElementById('templates_empty');
    const loadingEl = document.getElementById('templates_loading');
    const errorEl = document.getElementById('templates_error');
    const statsEl = document.getElementById('templates_stats');
    const tableEl = document.getElementById('templates_table_container');

    emptyEl.style.display = 'none';
    errorEl.style.display = 'none';
    statsEl.style.display = 'none';
    tableEl.style.display = 'none';
    loadingEl.style.display = 'block';

    fetch(`/integrations/notificame/accounts/${accountId}/templates`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        loadingEl.style.display = 'none';
        if (!data.success) {
            errorEl.textContent = data.message || 'Erro ao carregar templates';
            errorEl.style.display = 'block';
            return;
        }
        const templates = data.templates || [];
        _cachedTemplates = templates;
        renderTemplates(templates);
    })
    .catch(err => {
        loadingEl.style.display = 'none';
        errorEl.textContent = 'Erro de rede: ' + err.message;
        errorEl.style.display = 'block';
    });
}

function renderTemplates(templates) {
    const statsEl = document.getElementById('templates_stats');
    const tableEl = document.getElementById('templates_table_container');
    const tbody = document.getElementById('templates_tbody');

    const total = templates.length;
    const approved = templates.filter(t => (t.status || '').toLowerCase() === 'approved').length;
    const pending = templates.filter(t => (t.status || '').toLowerCase() === 'pending').length;
    const rejected = templates.filter(t => ['rejected', 'disabled'].includes((t.status || '').toLowerCase())).length;

    document.getElementById('stat_total').textContent = total;
    document.getElementById('stat_approved').textContent = approved;
    document.getElementById('stat_pending').textContent = pending;
    document.getElementById('stat_rejected').textContent = rejected;
    statsEl.style.display = 'flex';

    if (total === 0) {
        document.getElementById('templates_empty').style.display = 'block';
        document.getElementById('templates_empty').innerHTML = '<p class="text-muted fs-5 py-10">Nenhum template encontrado nesta conta</p>';
        tableEl.style.display = 'none';
        return;
    }

    let html = '';
    templates.forEach((t, idx) => {
        const name = t.name || t.templateName || t.id || '-';
        const category = t.category || t.type || '-';
        const language = t.language || t.lang || '-';
        const status = (t.status || 'unknown').toLowerCase();
        const content = t.body || t.text || t.content || t.components?.find(c => c.type === 'BODY')?.text || '-';
        const truncated = content.length > 100 ? content.substring(0, 100) + '...' : content;
        const tplId = t.id || t.templateId || '';

        const statusBadge = {
            'approved': '<span class="badge badge-light-success">Aprovado</span>',
            'pending': '<span class="badge badge-light-warning">Pendente</span>',
            'rejected': '<span class="badge badge-light-danger">Rejeitado</span>',
            'disabled': '<span class="badge badge-light-danger">Desabilitado</span>',
            'draft': '<span class="badge badge-light-info">Rascunho</span>'
        }[status] || `<span class="badge badge-light">${status}</span>`;

        const canEdit = status === 'draft' || status === 'rejected' || status === 'pending';

        html += `<tr>
            <td><span class="fw-bold">${escapeHtml(name)}</span></td>
            <td>${escapeHtml(category)}</td>
            <td>${escapeHtml(language)}</td>
            <td>${statusBadge}</td>
            <td><span class="text-muted fs-7">${escapeHtml(truncated)}</span></td>
            <td class="text-end">
                <div class="d-flex justify-content-end gap-1">
                    <button type="button" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm"
                            onclick="viewTemplate(${idx})" title="Visualizar">
                        <i class="ki-duotone ki-eye fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    </button>
                    ${canEdit ? `<button type="button" class="btn btn-icon btn-bg-light btn-active-color-warning btn-sm"
                            onclick="editTemplate(${idx})" title="Editar">
                        <i class="ki-duotone ki-pencil fs-2"><span class="path1"></span><span class="path2"></span></i>
                    </button>` : ''}
                    <button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm"
                            onclick="deleteTemplate('${escapeAttr(tplId)}', '${escapeAttr(name)}')" title="Excluir">
                        <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                    </button>
                </div>
            </td>
        </tr>`;
    });

    tbody.innerHTML = html;
    tableEl.style.display = 'block';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeAttr(text) {
    return String(text || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// ========== Template CRUD ==========

function getSelectedTemplateAccountId() {
    return document.getElementById('kt_template_account').value;
}

function showCreateTemplateModal() {
    const accountId = getSelectedTemplateAccountId();
    const form = document.getElementById('kt_form_template');
    form.reset();
    document.getElementById('kt_tpl_id').value = '';
    document.getElementById('kt_tpl_edit_mode').value = '0';
    document.getElementById('kt_tpl_name').readOnly = false;
    document.getElementById('kt_template_modal_title').innerHTML = '<i class="ki-duotone ki-plus-square fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i> Novo Template';
    document.getElementById('kt_submit_template').querySelector('.indicator-label').textContent = 'Criar Template';
    document.getElementById('kt_tpl_buttons_container').innerHTML = '';
    toggleTplHeaderFields('NONE');
    updateTplPreview();

    if (accountId) {
        document.getElementById('kt_tpl_account').value = accountId;
    }

    new bootstrap.Modal(document.getElementById('kt_modal_template')).show();
}

function editTemplate(idx) {
    const t = _cachedTemplates[idx];
    if (!t) return;

    const form = document.getElementById('kt_form_template');
    form.reset();

    document.getElementById('kt_tpl_id').value = t.id || t.templateId || '';
    document.getElementById('kt_tpl_edit_mode').value = '1';
    document.getElementById('kt_tpl_name').value = t.name || t.templateName || '';
    document.getElementById('kt_tpl_name').readOnly = true;
    document.getElementById('kt_tpl_category').value = (t.category || 'UTILITY').toUpperCase();
    document.getElementById('kt_tpl_language').value = t.language || t.lang || 'pt_BR';
    document.getElementById('kt_template_modal_title').innerHTML = '<i class="ki-duotone ki-pencil fs-2 me-2"><span class="path1"></span><span class="path2"></span></i> Editar Template';
    document.getElementById('kt_submit_template').querySelector('.indicator-label').textContent = 'Salvar Alterações';

    const accountId = getSelectedTemplateAccountId();
    if (accountId) {
        document.getElementById('kt_tpl_account').value = accountId;
    }

    // Body
    const body = t.body || t.text || t.content || t.components?.find(c => c.type === 'BODY')?.text || '';
    document.getElementById('kt_tpl_body').value = body;

    // Header
    const headerComp = t.components?.find(c => c.type === 'HEADER');
    if (headerComp) {
        const headerType = (headerComp.format || 'TEXT').toUpperCase();
        document.getElementById('kt_tpl_header_type').value = headerType;
        toggleTplHeaderFields(headerType);
        if (headerType === 'TEXT') {
            document.getElementById('kt_tpl_header_text').value = headerComp.text || '';
        }
    } else if (t.header) {
        document.getElementById('kt_tpl_header_type').value = 'TEXT';
        toggleTplHeaderFields('TEXT');
        document.getElementById('kt_tpl_header_text').value = t.header || '';
    } else {
        toggleTplHeaderFields('NONE');
    }

    // Footer
    const footerComp = t.components?.find(c => c.type === 'FOOTER');
    document.getElementById('kt_tpl_footer').value = footerComp?.text || t.footer || '';

    // Buttons
    const buttonsContainer = document.getElementById('kt_tpl_buttons_container');
    buttonsContainer.innerHTML = '';
    const buttonComp = t.components?.find(c => c.type === 'BUTTONS');
    if (buttonComp?.buttons) {
        buttonComp.buttons.forEach(btn => {
            addTplButton(btn.type || 'QUICK_REPLY', btn.text || '', btn.url || btn.phone_number || '');
        });
    } else if (t.buttons && Array.isArray(t.buttons)) {
        t.buttons.forEach(btn => {
            addTplButton(btn.type || 'QUICK_REPLY', btn.text || '', btn.url || btn.phone_number || '');
        });
    }

    updateTplPreview();
    new bootstrap.Modal(document.getElementById('kt_modal_template')).show();
}

let _viewingTemplateIdx = -1;

function viewTemplate(idx) {
    const t = _cachedTemplates[idx];
    if (!t) return;

    _viewingTemplateIdx = idx;
    const content = document.getElementById('kt_view_template_content');

    const name = t.name || t.templateName || t.id || '-';
    const category = t.category || t.type || '-';
    const language = t.language || t.lang || '-';
    const status = (t.status || 'unknown').toLowerCase();
    const body = t.body || t.text || t.content || t.components?.find(c => c.type === 'BODY')?.text || '-';
    const tplId = t.id || t.templateId || '-';

    const statusBadge = {
        'approved': '<span class="badge badge-light-success">Aprovado</span>',
        'pending': '<span class="badge badge-light-warning">Pendente</span>',
        'rejected': '<span class="badge badge-light-danger">Rejeitado</span>',
        'disabled': '<span class="badge badge-light-danger">Desabilitado</span>',
        'draft': '<span class="badge badge-light-info">Rascunho</span>'
    }[status] || `<span class="badge badge-light">${escapeHtml(status)}</span>`;

    const canEdit = status === 'draft' || status === 'rejected' || status === 'pending';
    const editBtn = document.getElementById('kt_view_tpl_edit_btn');
    if (editBtn) editBtn.style.display = canEdit ? '' : 'none';

    // Header
    const headerComp = t.components?.find(c => c.type === 'HEADER');
    let headerHtml = '';
    if (headerComp) {
        headerHtml = `<div class="mb-3"><span class="fw-semibold text-muted d-block mb-1">Cabeçalho (${escapeHtml(headerComp.format || 'TEXT')})</span><span>${escapeHtml(headerComp.text || '-')}</span></div>`;
    } else if (t.header) {
        headerHtml = `<div class="mb-3"><span class="fw-semibold text-muted d-block mb-1">Cabeçalho</span><span>${escapeHtml(t.header)}</span></div>`;
    }

    // Footer
    const footerComp = t.components?.find(c => c.type === 'FOOTER');
    const footer = footerComp?.text || t.footer || '';
    const footerHtml = footer ? `<div class="mb-3"><span class="fw-semibold text-muted d-block mb-1">Rodapé</span><span>${escapeHtml(footer)}</span></div>` : '';

    // Buttons
    const buttonComp = t.components?.find(c => c.type === 'BUTTONS');
    let buttonsHtml = '';
    const buttons = buttonComp?.buttons || t.buttons || [];
    if (buttons.length > 0) {
        buttonsHtml = '<div class="mb-3"><span class="fw-semibold text-muted d-block mb-1">Botões</span>';
        buttons.forEach(btn => {
            const btnType = (btn.type || 'QUICK_REPLY').toUpperCase();
            const typeLabel = btnType === 'URL' ? 'Link' : btnType === 'PHONE_NUMBER' ? 'Telefone' : 'Resposta Rápida';
            buttonsHtml += `<div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge badge-light-info">${typeLabel}</span>
                <span>${escapeHtml(btn.text || '-')}</span>
                ${btn.url ? `<a href="${escapeHtml(btn.url)}" target="_blank" class="text-primary fs-8">${escapeHtml(btn.url)}</a>` : ''}
                ${btn.phone_number ? `<span class="text-muted fs-8">${escapeHtml(btn.phone_number)}</span>` : ''}
            </div>`;
        });
        buttonsHtml += '</div>';
    }

    // Preview WhatsApp style
    const previewHtml = `
        <div class="bg-light rounded p-4 mb-5">
            <div class="d-flex justify-content-end">
                <div class="bg-success bg-opacity-15 rounded p-3" style="max-width: 350px; min-width: 250px;">
                    ${headerComp?.text ? `<div class="fw-bold mb-1">${escapeHtml(headerComp.text)}</div>` : ''}
                    ${t.header ? `<div class="fw-bold mb-1">${escapeHtml(t.header)}</div>` : ''}
                    <div class="text-dark fs-7">${escapeHtml(body).replace(/\n/g, '<br>')}</div>
                    ${footer ? `<div class="text-muted fs-8 mt-1">${escapeHtml(footer)}</div>` : ''}
                    ${buttons.length > 0 ? '<div class="mt-2 border-top pt-2">' + buttons.map(b => `<div class="text-center"><a class="text-primary fs-7">${escapeHtml(b.text || '')}</a></div>`).join('') + '</div>' : ''}
                </div>
            </div>
        </div>`;

    content.innerHTML = `
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">${escapeHtml(name)}</h4>
                ${statusBadge}
            </div>
            <div class="separator separator-dashed mb-4"></div>
            <div class="row mb-3">
                <div class="col-4"><span class="fw-semibold text-muted">ID:</span></div>
                <div class="col-8"><span class="text-dark">${escapeHtml(tplId)}</span></div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><span class="fw-semibold text-muted">Categoria:</span></div>
                <div class="col-8"><span class="text-dark">${escapeHtml(category)}</span></div>
            </div>
            <div class="row mb-3">
                <div class="col-4"><span class="fw-semibold text-muted">Idioma:</span></div>
                <div class="col-8"><span class="text-dark">${escapeHtml(language)}</span></div>
            </div>
        </div>
        <div class="separator separator-dashed mb-5"></div>
        ${headerHtml}
        <div class="mb-3">
            <span class="fw-semibold text-muted d-block mb-1">Corpo</span>
            <div class="bg-light-primary rounded p-3 fs-7">${escapeHtml(body).replace(/\n/g, '<br>')}</div>
        </div>
        ${footerHtml}
        ${buttonsHtml}
        <div class="separator separator-dashed my-5"></div>
        <span class="fw-semibold text-muted d-block mb-2">Pré-visualização</span>
        ${previewHtml}
    `;

    new bootstrap.Modal(document.getElementById('kt_modal_view_template')).show();
}

function editTemplateFromView() {
    bootstrap.Modal.getInstance(document.getElementById('kt_modal_view_template'))?.hide();
    setTimeout(() => editTemplate(_viewingTemplateIdx), 300);
}

function deleteTemplate(templateId, templateName) {
    const accountId = getSelectedTemplateAccountId();
    if (!accountId) {
        Swal.fire('Erro', 'Selecione uma conta primeiro', 'warning');
        return;
    }

    Swal.fire({
        title: 'Excluir Template?',
        html: `Tem certeza que deseja excluir o template <strong>${templateName}</strong>?<br><small class="text-danger">Esta ação pode ser irreversível.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        buttonsStyling: false,
        customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-light' }
    }).then(result => {
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Excluindo...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch(`/integrations/notificame/accounts/${accountId}/templates/delete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ template_id: templateId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ text: data.message || 'Template excluído!', icon: 'success', buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
                loadTemplates(accountId);
            } else {
                Swal.fire({ text: data.message || 'Erro ao excluir', icon: 'error', buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
            }
        })
        .catch(err => {
            Swal.fire({ text: 'Erro: ' + err.message, icon: 'error', buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
        });
    });
}

// Template form submit
document.getElementById('kt_form_template').addEventListener('submit', function(e) {
    e.preventDefault();

    const accountId = document.getElementById('kt_tpl_account').value;
    if (!accountId) {
        Swal.fire('Erro', 'Selecione uma conta', 'warning');
        return;
    }

    const editMode = document.getElementById('kt_tpl_edit_mode').value === '1';
    const templateId = document.getElementById('kt_tpl_id').value;

    const buttons = [];
    document.querySelectorAll('#kt_tpl_buttons_container .tpl-button-row').forEach(row => {
        const type = row.querySelector('[name="btn_type"]').value;
        const text = row.querySelector('[name="btn_text"]').value;
        const extra = row.querySelector('[name="btn_extra"]')?.value || '';
        if (text) {
            const btn = { type, text };
            if (type === 'URL') btn.url = extra;
            if (type === 'PHONE_NUMBER') btn.phone_number = extra;
            buttons.push(btn);
        }
    });

    const data = {
        name: document.getElementById('kt_tpl_name').value,
        category: document.getElementById('kt_tpl_category').value,
        language: document.getElementById('kt_tpl_language').value,
        body_text: document.getElementById('kt_tpl_body').value,
        header_type: document.getElementById('kt_tpl_header_type').value,
        header_text: document.getElementById('kt_tpl_header_text').value || '',
        footer_text: document.getElementById('kt_tpl_footer').value || '',
    };

    if (buttons.length > 0) {
        data.buttons = buttons;
    }

    if (editMode && templateId) {
        data.template_id = templateId;
    }

    const submitBtn = document.getElementById('kt_submit_template');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;

    const url = editMode
        ? `/integrations/notificame/accounts/${accountId}/templates/update`
        : `/integrations/notificame/accounts/${accountId}/templates`;

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        if (result.success) {
            Swal.fire({
                text: result.message || (editMode ? 'Template atualizado!' : 'Template criado!'),
                icon: 'success',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: { confirmButton: 'btn btn-primary' }
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('kt_modal_template'))?.hide();
                document.getElementById('kt_template_account').value = accountId;
                loadTemplates(accountId);
            });
        } else {
            Swal.fire({ text: result.message || 'Erro ao salvar', icon: 'error', buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
        }
    })
    .catch(err => {
        submitBtn.removeAttribute('data-kt-indicator');
        submitBtn.disabled = false;
        Swal.fire({ text: 'Erro: ' + err.message, icon: 'error', buttonsStyling: false, confirmButtonText: 'OK', customClass: { confirmButton: 'btn btn-primary' } });
    });
});

// Template header toggle
function toggleTplHeaderFields(type) {
    document.getElementById('kt_tpl_header_text_field').style.display = type === 'TEXT' ? 'block' : 'none';
    updateTplPreview();
}

// Template preview
function updateTplPreview() {
    const body = document.getElementById('kt_tpl_body').value || 'A mensagem aparecerá aqui...';
    const headerType = document.getElementById('kt_tpl_header_type').value;
    const headerText = document.getElementById('kt_tpl_header_text').value;
    const footer = document.getElementById('kt_tpl_footer').value;

    const previewBody = document.getElementById('kt_tpl_preview_body');
    const previewHeader = document.getElementById('kt_tpl_preview_header');
    const previewFooter = document.getElementById('kt_tpl_preview_footer');
    const previewButtons = document.getElementById('kt_tpl_preview_buttons');

    previewBody.innerHTML = escapeHtml(body).replace(/\n/g, '<br>');

    if (headerType === 'TEXT' && headerText) {
        previewHeader.textContent = headerText;
        previewHeader.style.display = 'block';
    } else if (headerType !== 'NONE' && headerType !== 'TEXT') {
        previewHeader.textContent = `[${headerType}]`;
        previewHeader.style.display = 'block';
    } else {
        previewHeader.style.display = 'none';
    }

    if (footer) {
        previewFooter.textContent = footer;
        previewFooter.style.display = 'block';
    } else {
        previewFooter.style.display = 'none';
    }

    const buttonRows = document.querySelectorAll('#kt_tpl_buttons_container .tpl-button-row');
    if (buttonRows.length > 0) {
        let btnsHtml = '<div class="border-top pt-2">';
        buttonRows.forEach(row => {
            const text = row.querySelector('[name="btn_text"]').value || 'Botão';
            btnsHtml += `<div class="text-center"><a class="text-primary fs-7">${escapeHtml(text)}</a></div>`;
        });
        btnsHtml += '</div>';
        previewButtons.innerHTML = btnsHtml;
        previewButtons.style.display = 'block';
    } else {
        previewButtons.style.display = 'none';
    }
}

// Add button to template form
function addTplButton(type, text, extra) {
    const container = document.getElementById('kt_tpl_buttons_container');
    const count = container.querySelectorAll('.tpl-button-row').length;
    if (count >= 3) {
        Swal.fire('Limite', 'Máximo de 3 botões', 'info');
        return;
    }

    type = type || 'QUICK_REPLY';
    text = text || '';
    extra = extra || '';

    const row = document.createElement('div');
    row.className = 'tpl-button-row d-flex gap-2 mb-2 align-items-center';

    const showExtra = type === 'URL' || type === 'PHONE_NUMBER';
    const extraPlaceholder = type === 'URL' ? 'https://...' : '5511999999999';

    row.innerHTML = `
        <select name="btn_type" class="form-select form-select-sm form-select-solid" style="width: 150px;" onchange="onTplBtnTypeChange(this)">
            <option value="QUICK_REPLY" ${type === 'QUICK_REPLY' ? 'selected' : ''}>Resposta Rápida</option>
            <option value="URL" ${type === 'URL' ? 'selected' : ''}>Link (URL)</option>
            <option value="PHONE_NUMBER" ${type === 'PHONE_NUMBER' ? 'selected' : ''}>Telefone</option>
        </select>
        <input type="text" name="btn_text" class="form-control form-control-sm form-control-solid" placeholder="Texto do botão" value="${escapeHtml(text)}" oninput="updateTplPreview()">
        <input type="text" name="btn_extra" class="form-control form-control-sm form-control-solid" placeholder="${extraPlaceholder}" value="${escapeHtml(extra)}" style="display: ${showExtra ? 'block' : 'none'};">
        <button type="button" class="btn btn-icon btn-sm btn-light-danger" onclick="this.closest('.tpl-button-row').remove(); updateTplPreview();">
            <i class="ki-duotone ki-cross fs-4"><span class="path1"></span><span class="path2"></span></i>
        </button>`;

    container.appendChild(row);
    updateTplPreview();
}

function onTplBtnTypeChange(select) {
    const row = select.closest('.tpl-button-row');
    const extraInput = row.querySelector('[name="btn_extra"]');
    if (select.value === 'URL') {
        extraInput.style.display = 'block';
        extraInput.placeholder = 'https://...';
    } else if (select.value === 'PHONE_NUMBER') {
        extraInput.style.display = 'block';
        extraInput.placeholder = '5511999999999';
    } else {
        extraInput.style.display = 'none';
        extraInput.value = '';
    }
}

// Update preview on form inputs
document.querySelectorAll('#kt_form_template input, #kt_form_template textarea, #kt_form_template select').forEach(el => {
    el.addEventListener('input', updateTplPreview);
    el.addEventListener('change', updateTplPreview);
});

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
