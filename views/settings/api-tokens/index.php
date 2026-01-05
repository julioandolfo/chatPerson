<?php
/**
 * View: Gerenciamento de Tokens de API
 */

use App\Helpers\Url;

$pageTitle = 'API & Tokens';
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h1 class="fw-bold mb-1">API & Tokens</h1>
            <p class="text-muted mb-0">Gerencie tokens de acesso à API REST</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_create_token">
            <i class="ki-duotone ki-plus fs-2"></i>
            Gerar Novo Token
        </button>
    </div>
    
    <!-- Tabs -->
    <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#kt_tab_tokens">
                <i class="ki-duotone ki-key fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                Meus Tokens
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= Url::to('/settings/api-tokens/logs') ?>">
                <i class="ki-duotone ki-chart-simple fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                Logs de Requisições
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= Url::to('/api/README.md') ?>" target="_blank">
                <i class="ki-duotone ki-document fs-3 me-1"><span class="path1"></span><span class="path2"></span></i>
                Documentação
            </a>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Tokens -->
        <div class="tab-pane fade show active" id="kt_tab_tokens">
            
            <?php if (empty($tokens)): ?>
                <!-- Empty State -->
                <div class="card">
                    <div class="card-body text-center py-15">
                        <i class="ki-duotone ki-key fs-5x text-muted mb-5"><span class="path1"></span><span class="path2"></span></i>
                        <h3 class="fw-bold mb-3">Nenhum Token Criado</h3>
                        <p class="text-muted mb-5">Crie seu primeiro token para começar a usar a API REST</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_create_token">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Gerar Primeiro Token
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Lista de Tokens -->
                <div class="row g-5">
                    <?php foreach ($tokens as $token): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-50px me-4">
                                                <span class="symbol-label bg-light-<?= $token['is_active'] ? 'success' : 'danger' ?>">
                                                    <i class="ki-duotone ki-key fs-2x text-<?= $token['is_active'] ? 'success' : 'danger' ?>">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                    </i>
                                                </span>
                                            </div>
                                            <div>
                                                <h4 class="fw-bold mb-1"><?= htmlspecialchars($token['name']) ?></h4>
                                                <div class="text-muted fs-7">
                                                    <span class="badge badge-light-<?= $token['is_active'] ? 'success' : 'danger' ?>">
                                                        <?= $token['is_active'] ? 'Ativo' : 'Revogado' ?>
                                                    </span>
                                                    <span class="ms-2">Criado em <?= date('d/m/Y H:i', strtotime($token['created_at'])) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($token['is_active']): ?>
                                            <button type="button" class="btn btn-sm btn-light-danger" onclick="revokeToken(<?= $token['id'] ?>)">
                                                <i class="ki-duotone ki-cross fs-2"><span class="path1"></span><span class="path2"></span></i>
                                                Revogar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Token (mascarado) -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold fs-7 text-muted">Token</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm bg-light" 
                                                   value="<?= substr($token['token'], 0, 20) ?>••••••••••••••••••••••••" 
                                                   readonly id="token_<?= $token['id'] ?>">
                                            <button class="btn btn-sm btn-light-primary" onclick="copyToken('<?= $token['token'] ?>')">
                                                <i class="ki-duotone ki-copy fs-3"></i>
                                                Copiar
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Informações -->
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="border border-dashed border-gray-300 rounded p-3">
                                                <span class="text-muted fs-7 d-block mb-1">Rate Limit</span>
                                                <span class="fw-bold fs-5"><?= $token['rate_limit'] ?>/min</span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border border-dashed border-gray-300 rounded p-3">
                                                <span class="text-muted fs-7 d-block mb-1">Requisições Hoje</span>
                                                <span class="fw-bold fs-5"><?= number_format($token['stats']['requests_today'] ?? 0) ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border border-dashed border-gray-300 rounded p-3">
                                                <span class="text-muted fs-7 d-block mb-1">Último Uso</span>
                                                <span class="fw-bold fs-6">
                                                    <?= $token['last_used_at'] ? date('d/m/Y H:i', strtotime($token['last_used_at'])) : 'Nunca' ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border border-dashed border-gray-300 rounded p-3">
                                                <span class="text-muted fs-7 d-block mb-1">Expira em</span>
                                                <span class="fw-bold fs-6">
                                                    <?= $token['expires_at'] ? date('d/m/Y', strtotime($token['expires_at'])) : 'Nunca' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($token['allowed_ips'])): ?>
                                        <div class="mt-3">
                                            <span class="text-muted fs-7">IPs Permitidos:</span>
                                            <span class="fs-7"><?= htmlspecialchars($token['allowed_ips']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- Modal: Criar Token -->
<div class="modal fade" id="kt_modal_create_token" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Gerar Novo Token de API</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <form id="form_create_token" onsubmit="createToken(event)">
                <div class="modal-body">
                    <div class="mb-5">
                        <label class="form-label required">Nome do Token</label>
                        <input type="text" class="form-control" name="name" required placeholder="Ex: Integração CRM">
                        <div class="form-text">Escolha um nome descritivo para identificar este token</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Rate Limit (requisições/minuto)</label>
                        <input type="number" class="form-control" name="rate_limit" value="100" min="1" max="1000">
                        <div class="form-text">Limite de requisições por minuto (padrão: 100)</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">IPs Permitidos</label>
                        <input type="text" class="form-control" name="allowed_ips" placeholder="192.168.1.1, 10.0.0.5">
                        <div class="form-text">Separe múltiplos IPs por vírgula. Deixe vazio para permitir todos.</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Data de Expiração</label>
                        <input type="date" class="form-control" name="expires_at">
                        <div class="form-text">Deixe vazio para token sem expiração</div>
                    </div>
                    
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="ki-duotone ki-information-5 fs-2x text-info me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        <div class="fs-7">
                            <strong>Importante:</strong> O token completo será exibido apenas uma vez após a criação. 
                            Certifique-se de copiá-lo e armazená-lo em local seguro.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Gerar Token</span>
                        <span class="indicator-progress">Gerando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Token Criado -->
<div class="modal fade" id="kt_modal_token_created" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h2 class="fw-bold text-white">Token Criado com Sucesso!</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-light" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1 text-white"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex align-items-center mb-5">
                    <i class="ki-duotone ki-shield-tick fs-2x text-warning me-3"><span class="path1"></span><span class="path2"></span></i>
                    <div class="fs-7">
                        <strong>Atenção!</strong> Copie e guarde este token em local seguro. 
                        Ele não será exibido novamente por questões de segurança.
                    </div>
                </div>
                
                <label class="form-label fw-bold">Seu Token de API:</label>
                <div class="input-group mb-5">
                    <input type="text" class="form-control form-control-lg bg-light" id="new_token_value" readonly>
                    <button class="btn btn-primary" onclick="copyNewToken()">
                        <i class="ki-duotone ki-copy fs-2"></i>
                        Copiar
                    </button>
                </div>
                
                <div class="separator my-5"></div>
                
                <h4 class="fw-bold mb-3">Como usar:</h4>
                <div class="bg-light rounded p-4">
                    <code class="text-dark">
                        curl -X GET "<?= Url::getBaseUrl() ?>/api/v1/conversations" \<br>
                        &nbsp;&nbsp;-H "Authorization: Bearer SEU_TOKEN_AQUI"
                    </code>
                </div>
                
                <div class="mt-5">
                    <a href="<?= Url::to('/api/README.md') ?>" target="_blank" class="btn btn-light-primary">
                        <i class="ki-duotone ki-document fs-2"><span class="path1"></span><span class="path2"></span></i>
                        Ver Documentação Completa
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi, Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Criar token
function createToken(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const indicator = submitBtn.querySelector('.indicator-label');
    const progress = submitBtn.querySelector('.indicator-progress');
    
    submitBtn.setAttribute('data-kt-indicator', 'on');
    indicator.style.display = 'none';
    progress.style.display = 'inline-block';
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    fetch('<?= Url::to('/settings/api-tokens') ?>', {
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
            // Fechar modal de criação
            const createModal = bootstrap.Modal.getInstance(document.getElementById('kt_modal_create_token'));
            createModal.hide();
            
            // Exibir token criado
            document.getElementById('new_token_value').value = result.data.token;
            const tokenModal = new bootstrap.Modal(document.getElementById('kt_modal_token_created'));
            tokenModal.show();
            
            // Recarregar após fechar
            document.getElementById('kt_modal_token_created').addEventListener('hidden.bs.modal', () => {
                location.reload();
            }, { once: true });
        } else {
            throw new Error(result.message || 'Erro ao criar token');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: error.message || 'Erro ao criar token'
        });
    })
    .finally(() => {
        submitBtn.removeAttribute('data-kt-indicator');
        indicator.style.display = 'inline-block';
        progress.style.display = 'none';
    });
}

// Copiar novo token
function copyNewToken() {
    const input = document.getElementById('new_token_value');
    input.select();
    document.execCommand('copy');
    
    Swal.fire({
        icon: 'success',
        title: 'Copiado!',
        text: 'Token copiado para a área de transferência',
        timer: 2000,
        showConfirmButton: false
    });
}

// Copiar token
function copyToken(token) {
    const tempInput = document.createElement('input');
    tempInput.value = token;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    
    Swal.fire({
        icon: 'success',
        title: 'Copiado!',
        text: 'Token copiado para a área de transferência',
        timer: 2000,
        showConfirmButton: false
    });
}

// Revogar token
function revokeToken(id) {
    Swal.fire({
        title: 'Revogar Token?',
        text: 'Esta ação não pode ser desfeita. O token não poderá mais ser usado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, Revogar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`<?= Url::to('/settings/api-tokens') ?>/${id}/revoke`, {
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
                        title: 'Revogado!',
                        text: result.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(result.message || 'Erro ao revogar token');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: error.message || 'Erro ao revogar token'
                });
            });
        }
    });
}
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../../layouts/metronic/app.php'; ?>
