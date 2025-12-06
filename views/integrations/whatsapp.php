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
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if (\App\Helpers\Permission::can('whatsapp.view')): ?>
                                    <button type="button" class="btn btn-light-primary btn-sm flex-grow-1" 
                                            onclick="getQRCode(<?= $account['id'] ?>)">
                                        <i class="ki-duotone ki-qr-code fs-4"></i>
                                        QR Code
                                    </button>
                                    <button type="button" class="btn btn-light-info btn-sm" 
                                            onclick="checkStatus(<?= $account['id'] ?>)">
                                        <i class="ki-duotone ki-information fs-4"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('whatsapp.edit')): ?>
                                    <?php if ($currentStatus === 'active'): ?>
                                    <button type="button" class="btn btn-light-danger btn-sm" 
                                            onclick="disconnectAccount(<?= $account['id'] ?>)">
                                        <i class="ki-duotone ki-cross fs-4"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Permission::can('whatsapp.delete')): ?>
                                    <button type="button" class="btn btn-light-danger btn-sm" 
                                            onclick="deleteAccount(<?= $account['id'] ?>, '<?= htmlspecialchars($account['name'], ENT_QUOTES) ?>')">
                                        <i class="ki-duotone ki-trash fs-4"></i>
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
                        <select name="provider" id="kt_provider_select" class="form-select form-select-solid" required>
                            <option value="quepasa" selected>Quepasa API</option>
                            <option value="evolution" disabled>Evolution API (Em breve)</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">URL da API</label>
                        <input type="url" name="api_url" class="form-control form-control-solid" 
                               placeholder="https://whats.seudominio.com" required />
                        <div class="form-text">URL base da sua instalação Quepasa (ex: https://whats.seudominio.com)</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Quepasa User</label>
                        <input type="text" name="quepasa_user" class="form-control form-control-solid" 
                               placeholder="julio" required />
                        <div class="form-text">Identificador do usuário (X-QUEPASA-USER). Ex: julio, personizi, etc.</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Track ID</label>
                        <input type="text" name="quepasa_trackid" class="form-control form-control-solid" 
                               placeholder="meu-sistema" />
                        <div class="form-text">ID para rastreamento (X-QUEPASA-TRACKID). Deixe vazio para usar o nome da conta.</div>
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

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
let currentAccountId = null;
let qrCodeStatusInterval = null;

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("kt_modal_new_whatsapp_form");
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_new_whatsapp_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            
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
    const maxAttempts = 300; // 5 minutos (300 * 1 segundo)
    
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
        
        // Verificar status da conexão
        fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId + "/status")
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
                                    <div class="fs-7">${data.status.phone_number ? 'Número: ' + data.status.phone_number : ''}</div>
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
    }, 1000); // Verificar a cada 1 segundo
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

function checkStatus(accountId) {
    fetch("' . \App\Helpers\Url::to('/integrations/whatsapp') . '/" + accountId + "/status")
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const status = data.status;
                let message = "Status: " + (status.connected ? "Conectado" : "Desconectado");
                if (status.phone_number) {
                    message += "\\nNúmero: " + status.phone_number;
                }
                alert(message);
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
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
