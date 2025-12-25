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
            <?php if (\App\Helpers\Permission::can('notificame.create')): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_new_notificame">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Conta Notificame
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($accounts)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-chat-dots fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma conta Notificame configurada</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando uma nova conta Notificame para integrar múltiplos canais.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th class="min-w-150px">Nome</th>
                            <th class="min-w-100px">Canal</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-150px">Identificador</th>
                            <th class="min-w-150px">Funil/Etapa</th>
                            <th class="text-end min-w-100px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td>
                                <span class="text-dark fw-bold d-block fs-6"><?= htmlspecialchars($account['name']) ?></span>
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
                                    <span class="text-dark fw-semibold d-block fs-7"><?= htmlspecialchars($account['phone_number']) ?></span>
                                <?php elseif (!empty($account['username'])): ?>
                                    <span class="text-dark fw-semibold d-block fs-7">@<?= htmlspecialchars($account['username']) ?></span>
                                <?php elseif (!empty($account['account_id'])): ?>
                                    <span class="text-dark fw-semibold d-block fs-7"><?= htmlspecialchars($account['account_id']) ?></span>
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
                            <td class="text-end">
                                <a href="#" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" onclick="checkStatus(<?= $account['id'] ?>)">
                                    <i class="ki-duotone ki-information-5 fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </a>
                                <?php if (\App\Helpers\Permission::can('notificame.edit')): ?>
                                <a href="#" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" onclick="editAccount(<?= $account['id'] ?>)">
                                    <i class="ki-duotone ki-pencil fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </a>
                                <?php endif; ?>
                                <?php if (\App\Helpers\Permission::can('notificame.delete')): ?>
                                <a href="#" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm" onclick="deleteAccount(<?= $account['id'] ?>)">
                                    <i class="ki-duotone ki-trash fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                </a>
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
                        <select name="channel" class="form-select form-select-solid" required>
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
                    <div class="mb-5">
                        <label class="fw-semibold fs-6 mb-2">Identificador</label>
                        <input type="text" name="account_id" class="form-control form-control-solid" placeholder="ID da conta na plataforma (opcional)">
                        <div class="form-text">Para WhatsApp: número de telefone. Para outros canais: username ou ID da conta.</div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
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

<script>
function checkStatus(id) {
    // Implementar verificação de status
    alert('Verificar status: ' + id);
}

function editAccount(id) {
    // Implementar edição
    alert('Editar conta: ' + id);
}

function deleteAccount(id) {
    if (confirm('Tem certeza que deseja deletar esta conta?')) {
        fetch('/integrations/notificame/accounts/' + id, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro: ' + data.message);
            }
        });
    }
}

<?php if (\App\Helpers\Permission::can('notificame.create')): ?>
document.getElementById('kt_form_new_notificame').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    
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
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        submitBtn.removeAttribute('data-kt-indicator');
        alert('Erro ao criar conta: ' + error.message);
    });
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/metronic/app.php';
?>

