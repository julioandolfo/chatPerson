<?php
/**
 * Aba de Configurações de WebSocket/Tempo Real
 */
?>
<!--begin::Form Tempo Real-->
<form id="kt_settings_websocket_form" class="form">
    <div class="row">
        <div class="col-lg-12">
            <div class="fv-row mb-7">
                <label class="fw-semibold fs-6 mb-2">Habilitar Tempo Real</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="websocket_enabled" 
                           id="websocket_enabled" <?= ($websocketSettings['websocket_enabled'] ?? true) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="websocket_enabled">
                        Ativar atualizações em tempo real (WebSocket ou Polling)
                    </label>
                </div>
                <div class="form-text">Quando desativado, as atualizações serão feitas apenas ao recarregar a página</div>
            </div>
        </div>
    </div>
    
    <div class="separator my-6"></div>
    
    <h4 class="fw-bold mb-4">Tipo de Conexão</h4>
    <div class="row">
        <div class="col-lg-12">
            <div class="fv-row mb-7">
                <label class="required fw-semibold fs-6 mb-2">Modo de Conexão</label>
                <select name="websocket_connection_type" id="websocket_connection_type" class="form-select form-select-solid" required>
                    <option value="auto" <?= ($websocketSettings['websocket_connection_type'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Automático (WebSocket com fallback para Polling)</option>
                    <option value="websocket" <?= ($websocketSettings['websocket_connection_type'] ?? '') === 'websocket' ? 'selected' : '' ?>>Apenas WebSocket</option>
                    <option value="polling" <?= ($websocketSettings['websocket_connection_type'] ?? '') === 'polling' ? 'selected' : '' ?>>Apenas Polling</option>
                </select>
                <div class="form-text">
                    <strong>Automático:</strong> Tenta WebSocket primeiro, usa Polling se falhar<br>
                    <strong>Apenas WebSocket:</strong> Usa apenas WebSocket (requer servidor rodando)<br>
                    <strong>Apenas Polling:</strong> Usa apenas verificação periódica via AJAX
                </div>
            </div>
        </div>
    </div>
    
    <div id="websocket_config_section" style="display: <?= ($websocketSettings['websocket_connection_type'] ?? 'auto') === 'polling' ? 'none' : 'block' ?>;">
        <h4 class="fw-bold mb-4">Configurações de WebSocket</h4>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Porta do WebSocket</label>
                    <input type="number" name="websocket_port" class="form-control form-control-solid" 
                           value="<?= (int)($websocketSettings['websocket_port'] ?? 8080) ?>" min="1" max="65535" />
                    <div class="form-text">Porta padrão: 8080 (usado em desenvolvimento ou conexão direta)</div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Caminho do Proxy Reverso</label>
                    <input type="text" name="websocket_path" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($websocketSettings['websocket_path'] ?? '/ws') ?>" />
                    <div class="form-text">Caminho usado quando WebSocket está atrás de proxy reverso (ex: /ws)</div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">URL Customizada do WebSocket (Opcional)</label>
                    <input type="text" name="websocket_custom_url" class="form-control form-control-solid" 
                           value="<?= htmlspecialchars($websocketSettings['websocket_custom_url'] ?? '') ?>" 
                           placeholder="wss://seu-servidor.com/ws" />
                    <div class="form-text">Deixe vazio para usar detecção automática. Use para URLs customizadas (ex: wss://ws.seudominio.com)</div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="polling_config_section" style="display: <?= ($websocketSettings['websocket_connection_type'] ?? 'auto') === 'polling' ? 'block' : 'none' ?>;">
        <h4 class="fw-bold mb-4">Configurações de Polling</h4>
        <div class="row">
            <div class="col-lg-6">
                <div class="fv-row mb-7">
                    <label class="fw-semibold fs-6 mb-2">Intervalo de Verificação (ms)</label>
                    <input type="number" name="websocket_polling_interval" class="form-control form-control-solid" 
                           value="<?= (int)($websocketSettings['websocket_polling_interval'] ?? 3000) ?>" min="1000" max="60000" step="500" />
                    <div class="form-text">Tempo entre verificações de atualizações (1000ms = 1 segundo). Recomendado: 3000ms (3 segundos)</div>
                </div>
            </div>
        </div>
        <div class="alert alert-info d-flex align-items-center p-5 mb-7">
            <i class="ki-duotone ki-information fs-2x text-primary me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1">Modo Polling</h4>
                <span>O modo Polling verifica atualizações periodicamente via requisições AJAX. Use quando WebSocket não estiver disponível ou não puder ser configurado.</span>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <span class="indicator-label">Salvar Configurações</span>
            <span class="indicator-progress">Aguarde...
            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
        </button>
    </div>
</form>
<!--end::Form Tempo Real-->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const connectionTypeSelect = document.getElementById('websocket_connection_type');
    const websocketSection = document.getElementById('websocket_config_section');
    const pollingSection = document.getElementById('polling_config_section');
    
    if (connectionTypeSelect) {
        connectionTypeSelect.addEventListener('change', function() {
            if (this.value === 'polling') {
                websocketSection.style.display = 'none';
                pollingSection.style.display = 'block';
            } else {
                websocketSection.style.display = 'block';
                pollingSection.style.display = 'none';
            }
        });
    }
    
    const websocketForm = document.getElementById('kt_settings_websocket_form');
    if (websocketForm) {
        websocketForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, '<?= \App\Helpers\Url::to('/settings/websocket') ?>');
        });
    }
});
</script>

