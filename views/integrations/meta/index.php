<?php
/**
 * View: Integrações Meta (Instagram + WhatsApp Cloud API)
 * 
 * Gerenciamento centralizado de integrações oficiais da Meta
 */

$instagramAccounts = $instagramAccounts ?? [];
$whatsappPhones = $whatsappPhones ?? [];
$tokens = $tokens ?? [];
?>

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <!--begin::Content container-->
    <div id="kt_app_content_container" class="app-container container-fluid">
        
        <!--begin::Page header-->
        <div class="card mb-5">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h1 class="mb-2">🎯 Integrações Meta (Instagram + WhatsApp)</h1>
                        <p class="text-muted mb-0">
                            Conecte suas contas Instagram e números WhatsApp oficiais via APIs da Meta
                        </p>
                    </div>
                    <div>
                        <a href="/integrations/meta/logs" class="btn btn-sm btn-secondary me-2">
                            <i class="bi bi-file-text"></i> Ver Logs
                        </a>
                        <button class="btn btn-sm btn-primary" onclick="connectAccount()">
                            <i class="bi bi-plus-circle"></i> Conectar Conta Meta
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <strong>Sucesso!</strong> Conta conectada com sucesso.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!--begin::Instagram Accounts-->
        <div class="card mb-5">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-instagram text-danger fs-3 me-2"></i>
                    Instagram Accounts (<?= count($instagramAccounts) ?>)
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($instagramAccounts)): ?>
                    <div class="text-center py-10">
                        <i class="bi bi-instagram text-muted" style="font-size: 48px;"></i>
                        <p class="text-muted mt-3">Nenhuma conta Instagram conectada</p>
                        <button class="btn btn-primary btn-sm" onclick="connectAccount('instagram')">
                            <i class="bi bi-plus-circle"></i> Conectar Instagram
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-row-bordered gy-5">
                            <thead>
                                <tr class="fw-bold fs-6 text-gray-800">
                                    <th>Conta</th>
                                    <th>Usuário</th>
                                    <th>Seguidores</th>
                                    <th>Status</th>
                                    <th>Última Sync</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($instagramAccounts as $account): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($account['profile_picture_url'])): ?>
                                                <img src="<?= htmlspecialchars($account['profile_picture_url']) ?>" 
                                                     class="rounded-circle me-3" style="width: 40px; height: 40px;" 
                                                     alt="Avatar">
                                            <?php else: ?>
                                                <div class="symbol symbol-40px me-3">
                                                    <span class="symbol-label bg-light-primary text-primary fs-6 fw-bold">
                                                        <?= strtoupper(substr($account['name'] ?? 'IG', 0, 2)) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($account['name'] ?? 'Sem nome') ?></strong>
                                                <div class="text-muted small">
                                                    ID: <?= htmlspecialchars($account['instagram_user_id']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="https://instagram.com/<?= htmlspecialchars($account['username']) ?>" 
                                           target="_blank" class="text-hover-primary">
                                            @<?= htmlspecialchars($account['username']) ?>
                                        </a>
                                    </td>
                                    <td><?= number_format($account['followers_count']) ?></td>
                                    <td>
                                        <?php if ($account['has_valid_token'] && $account['is_connected']): ?>
                                            <span class="badge badge-success">Conectado</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Desconectado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($account['last_synced_at']): ?>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($account['last_synced_at'])) ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">Nunca</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-icon btn-light-primary" 
                                                onclick="syncInstagram(<?= $account['id'] ?>)" 
                                                title="Sincronizar">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <button class="btn btn-sm btn-icon btn-light-info" 
                                                onclick="testMessage('instagram', <?= $account['id'] ?>)" 
                                                title="Testar Mensagem">
                                            <i class="bi bi-send"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!--begin::WhatsApp Phones-->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-whatsapp text-success fs-3 me-2"></i>
                    WhatsApp Phones (<?= count($whatsappPhones) ?>)
                </h3>
                <div class="card-toolbar">
                    <button class="btn btn-sm btn-success" onclick="addWhatsAppPhone()">
                        <i class="bi bi-plus-circle"></i> Adicionar Número
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($whatsappPhones)): ?>
                    <div class="text-center py-10">
                        <i class="bi bi-whatsapp text-muted" style="font-size: 48px;"></i>
                        <p class="text-muted mt-3">Nenhum número WhatsApp conectado</p>
                        <button class="btn btn-success btn-sm" onclick="connectAccount('whatsapp')">
                            <i class="bi bi-plus-circle"></i> Conectar WhatsApp
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-row-bordered gy-5">
                            <thead>
                                <tr class="fw-bold fs-6 text-gray-800">
                                    <th>Número</th>
                                    <th>Nome Verificado</th>
                                    <th>Qualidade</th>
                                    <th>Modo</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($whatsappPhones as $phone): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($phone['display_phone_number'] ?? $phone['phone_number']) ?></strong>
                                        <div class="text-muted small">
                                            ID: <?= htmlspecialchars($phone['phone_number_id']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($phone['verified_name'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $qualityColors = [
                                            'GREEN' => 'success',
                                            'YELLOW' => 'warning',
                                            'RED' => 'danger',
                                            'UNKNOWN' => 'secondary'
                                        ];
                                        $color = $qualityColors[$phone['quality_rating']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $color ?>">
                                            <?= $phone['quality_rating'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $phone['account_mode'] === 'LIVE' ? 'primary' : 'warning' ?>">
                                            <?= $phone['account_mode'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($phone['has_valid_token'] && $phone['is_connected']): ?>
                                            <span class="badge badge-success">Conectado</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Desconectado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-icon btn-light-primary" 
                                                onclick="syncWhatsApp(<?= $phone['id'] ?>)" 
                                                title="Sincronizar">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <button class="btn btn-sm btn-icon btn-light-info" 
                                                onclick="testMessage('whatsapp', <?= $phone['id'] ?>)" 
                                                title="Testar Mensagem">
                                            <i class="bi bi-send"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<script>
function connectAccount(type = 'both') {
    Swal.fire({
        title: 'Conectar Conta Meta',
        html: `
            <p class="text-muted mb-3">
                Você será redirecionado para autenticação OAuth da Meta.
            </p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="connectType" id="typeBoth" value="both" ${type === 'both' ? 'checked' : ''}>
                <label class="form-check-label" for="typeBoth">
                    Instagram + WhatsApp
                </label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="connectType" id="typeInstagram" value="instagram" ${type === 'instagram' ? 'checked' : ''}>
                <label class="form-check-label" for="typeInstagram">
                    Apenas Instagram
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="connectType" id="typeWhatsApp" value="whatsapp" ${type === 'whatsapp' ? 'checked' : ''}>
                <label class="form-check-label" for="typeWhatsApp">
                    Apenas WhatsApp
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            return document.querySelector('input[name="connectType"]:checked').value;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `/integrations/meta/oauth/authorize?type=${result.value}`;
        }
    });
}

function syncInstagram(id) {
    Swal.fire({
        title: 'Sincronizando...',
        text: 'Aguarde enquanto sincronizamos os dados do Instagram',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('/integrations/meta/instagram/sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sucesso!', 'Perfil sincronizado com sucesso', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao sincronizar', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Erro', 'Erro na requisição', 'error');
    });
}

function syncWhatsApp(id) {
    Swal.fire({
        title: 'Sincronizando...',
        text: 'Aguarde enquanto sincronizamos os dados do WhatsApp',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('/integrations/meta/whatsapp/sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sucesso!', 'Número sincronizado com sucesso', 'success')
                .then(() => location.reload());
        } else {
            Swal.fire('Erro', data.error || 'Erro ao sincronizar', 'error');
        }
    })
    .catch(err => {
        Swal.fire('Erro', 'Erro na requisição', 'error');
    });
}

function addWhatsAppPhone() {
    Swal.fire({
        title: 'Adicionar Número WhatsApp',
        html: `
            <div class="mb-3">
                <label class="form-label">Phone Number ID (Meta)</label>
                <input type="text" id="phoneNumberId" class="form-control" placeholder="123456789012345">
            </div>
            <div class="mb-3">
                <label class="form-label">Número de Telefone</label>
                <input type="text" id="phoneNumber" class="form-control" placeholder="+5511999999999">
            </div>
            <div class="mb-3">
                <label class="form-label">WABA ID</label>
                <input type="text" id="wabaId" class="form-control" placeholder="123456789012345">
            </div>
            <div class="mb-3">
                <label class="form-label">Meta User ID (do token OAuth)</label>
                <input type="text" id="metaUserId" class="form-control" placeholder="123456789012345">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Adicionar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            return {
                phone_number_id: document.getElementById('phoneNumberId').value,
                phone_number: document.getElementById('phoneNumber').value,
                waba_id: document.getElementById('wabaId').value,
                meta_user_id: document.getElementById('metaUserId').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Adicionando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('/integrations/meta/whatsapp/add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(result.value)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Número adicionado com sucesso', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Erro', data.error || 'Erro ao adicionar', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Erro', 'Erro na requisição', 'error');
            });
        }
    });
}

function testMessage(type, accountId) {
    Swal.fire({
        title: `Testar Mensagem ${type === 'instagram' ? 'Instagram' : 'WhatsApp'}`,
        html: `
            <div class="mb-3">
                <label class="form-label">${type === 'instagram' ? 'Instagram User ID' : 'Número WhatsApp'}</label>
                <input type="text" id="testTo" class="form-control" 
                       placeholder="${type === 'instagram' ? 'Instagram User ID (numérico)' : '+5511999999999'}">
            </div>
            <div class="mb-3">
                <label class="form-label">Mensagem</label>
                <textarea id="testMessage" class="form-control" rows="3" placeholder="Digite sua mensagem..."></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            return {
                type,
                account_id: accountId,
                to: document.getElementById('testTo').value,
                message: document.getElementById('testMessage').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Enviando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('/integrations/meta/test-message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(result.value)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', 'Mensagem enviada com sucesso', 'success');
                } else {
                    Swal.fire('Erro', data.error || 'Erro ao enviar', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Erro', 'Erro na requisição', 'error');
            });
        }
    });
}
</script>

