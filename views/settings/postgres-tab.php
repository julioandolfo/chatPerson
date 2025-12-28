<?php
/**
 * Aba de Configurações do PostgreSQL para Sistema RAG
 */
$postgresSettings = $postgresSettings ?? [];
?>
<!--begin::Form PostgreSQL-->
<form id="kt_settings_postgres_form" class="form">
    <div class="alert alert-info d-flex align-items-center p-5 mb-7">
        <i class="ki-duotone ki-information fs-2x text-primary me-4">
            <span class="path1"></span>
            <span class="path2"></span>
            <span class="path3"></span>
        </i>
        <div class="d-flex flex-column">
            <h4 class="mb-1">PostgreSQL + pgvector para Sistema RAG</h4>
            <span>Configure as credenciais do PostgreSQL com extensão pgvector para habilitar o sistema RAG (Retrieval-Augmented Generation). Isso permite que os agentes de IA tenham memória de longo prazo e acesso a base de conhecimento.</span>
        </div>
    </div>
    
    <div class="fv-row mb-7">
        <label class="fw-semibold fs-6 mb-2">Habilitar PostgreSQL</label>
        <div class="form-check form-switch form-check-custom form-check-solid">
            <input class="form-check-input" type="checkbox" name="postgres_enabled" 
                   id="postgres_enabled" <?= ($postgresSettings['postgres_enabled'] ?? false) ? 'checked' : '' ?> />
            <label class="form-check-label" for="postgres_enabled">
                Ativar PostgreSQL para sistema RAG
            </label>
        </div>
        <div class="form-text">Quando desativado, o sistema RAG não estará disponível</div>
    </div>
    
    <div id="postgres_config_section" style="display: <?= ($postgresSettings['postgres_enabled'] ?? false) ? 'block' : 'none' ?>;">
        <div class="separator my-6"></div>
        
        <h4 class="fw-bold mb-4">Configurações de Conexão</h4>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Host</label>
                    <input type="text" name="postgres_host" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($postgresSettings['postgres_host'] ?? 'localhost') ?>" 
                           placeholder="localhost ou nome do serviço no Coolify" required />
                    <div class="form-text">No Coolify, use o nome do serviço (ex: postgres-pgvector) ou IP do servidor</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Porta</label>
                    <input type="number" name="postgres_port" class="form-control form-control-solid" 
                           value="<?= (int)($postgresSettings['postgres_port'] ?? 5432) ?>" 
                           min="1" max="65535" required />
                    <div class="form-text">Porta padrão do PostgreSQL: 5432</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Database</label>
                    <input type="text" name="postgres_database" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($postgresSettings['postgres_database'] ?? 'chat_rag') ?>" 
                           placeholder="chat_rag" required />
                    <div class="form-text">Nome do banco de dados criado no PostgreSQL</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Usuário</label>
                    <input type="text" name="postgres_username" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($postgresSettings['postgres_username'] ?? 'chat_user') ?>" 
                           placeholder="chat_user" required />
                    <div class="form-text">Usuário do PostgreSQL</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="fv-row mb-7">
                    <label class="required fw-semibold fs-6 mb-2">Senha</label>
                    <div class="input-group">
                        <input type="password" name="postgres_password" id="postgres_password" 
                               class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($postgresSettings['postgres_password'] ?? '') ?>" 
                               placeholder="••••••••" autocomplete="new-password" required />
                        <button class="btn btn-icon btn-light" type="button" id="toggle_password">
                            <i class="ki-duotone ki-eye fs-2" id="password_icon">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        </button>
                    </div>
                    <div class="form-text">Senha do usuário do PostgreSQL</div>
                </div>
            </div>
        </div>
        
        <div class="separator my-6"></div>
        
        <div class="alert alert-warning d-flex align-items-center p-5 mb-7">
            <i class="ki-duotone ki-information fs-2x text-warning me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1">Extensão pgvector</h4>
                <span>Certifique-se de que a extensão <code>pgvector</code> está instalada no PostgreSQL. Se não estiver, conecte ao banco como superuser e execute: <code>CREATE EXTENSION vector;</code></span>
            </div>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-7">
            <div>
                <h4 class="fw-bold mb-1">Testar Conexão</h4>
                <span class="text-muted">Verifique se as configurações estão corretas antes de salvar</span>
            </div>
            <button type="button" class="btn btn-light-primary" id="test_postgres_connection">
                <i class="ki-duotone ki-check fs-2 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Testar Conexão
            </button>
        </div>
        
        <div id="postgres_test_result" style="display: none;"></div>
    </div>
    
    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <span class="indicator-label">Salvar Configurações</span>
            <span class="indicator-progress">Aguarde...
            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
        </button>
    </div>
</form>
<!--end::Form PostgreSQL-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const postgresEnabled = document.getElementById('postgres_enabled');
    const postgresConfigSection = document.getElementById('postgres_config_section');
    const togglePassword = document.getElementById('toggle_password');
    const passwordInput = document.getElementById('postgres_password');
    const passwordIcon = document.getElementById('password_icon');
    const testConnectionBtn = document.getElementById('test_postgres_connection');
    const testResult = document.getElementById('postgres_test_result');
    
    // Mostrar/ocultar configurações baseado no checkbox
    if (postgresEnabled) {
        postgresEnabled.addEventListener('change', function() {
            if (this.checked) {
                postgresConfigSection.style.display = 'block';
            } else {
                postgresConfigSection.style.display = 'none';
            }
        });
    }
    
    // Toggle mostrar/ocultar senha
    if (togglePassword && passwordInput && passwordIcon) {
        togglePassword.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('ki-eye');
                passwordIcon.classList.add('ki-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('ki-eye-slash');
                passwordIcon.classList.add('ki-eye');
            }
        });
    }
    
    // Testar conexão PostgreSQL
    if (testConnectionBtn) {
        testConnectionBtn.addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Desabilitar botão e mostrar loading
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testando...';
            
            // Limpar resultado anterior
            testResult.style.display = 'none';
            testResult.innerHTML = '';
            
            // Coletar dados do formulário
            const formData = new FormData(document.getElementById('kt_settings_postgres_form'));
            
            // Fazer requisição
            fetch('<?= \App\Helpers\Url::to('/settings/postgres/test') ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                testResult.style.display = 'block';
                
                if (data.success) {
                    const pgvectorStatus = data.pgvector_installed 
                        ? '<div class="mt-2"><span class="badge badge-success">✅ pgvector instalado</span></div>'
                        : '<div class="mt-2"><span class="badge badge-warning">⚠️ pgvector não instalado</span><div class="mt-1"><code>CREATE EXTENSION vector;</code></div></div>';
                    
                    testResult.className = 'alert alert-success d-flex align-items-center p-5 mb-7';
                    testResult.innerHTML = `
                        <i class="ki-duotone ki-check-circle fs-2x text-success me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1">Conexão estabelecida com sucesso!</h4>
                            <span>${data.message || 'PostgreSQL está configurado corretamente.'}</span>
                            ${data.version ? `<div class="mt-2"><strong>Versão PostgreSQL:</strong> ${data.version}</div>` : ''}
                            ${pgvectorStatus}
                        </div>
                    `;
                } else {
                    testResult.className = 'alert alert-danger d-flex align-items-center p-5 mb-7';
                    testResult.innerHTML = `
                        <i class="ki-duotone ki-cross-circle fs-2x text-danger me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1">Erro ao conectar</h4>
                            <span>${data.message || 'Verifique as configurações e tente novamente.'}</span>
                        </div>
                    `;
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                testResult.style.display = 'block';
                testResult.className = 'alert alert-danger d-flex align-items-center p-5 mb-7';
                testResult.innerHTML = `
                    <i class="ki-duotone ki-cross-circle fs-2x text-danger me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1">Erro ao testar conexão</h4>
                        <span>Erro de rede ou servidor. Verifique sua conexão e tente novamente.</span>
                    </div>
                `;
            });
        });
    }
    
    // O evento de submit do formulário é configurado no script principal (index.php)
});
</script>

