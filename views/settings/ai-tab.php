<?php
/**
 * Aba de Configurações de IA/Fallback
 */
$aiSettings = $aiSettings ?? [];
?>

<form id="kt_settings_ai_form" class="form">
    <!--begin::Card - Fallback de IA-->
    <div class="card mb-5">
        <div class="card-header border-0 pt-5">
            <h3 class="card-title fw-bold">
                <i class="ki-duotone ki-shield-check fs-2x text-warning me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Monitoramento de Fallback de IA
            </h3>
            <div class="card-toolbar">
                <span class="badge badge-light-warning fs-7">Sistema de Segurança</span>
            </div>
        </div>
        <div class="card-body pt-5">
            <div class="alert alert-info d-flex align-items-center p-5 mb-5">
                <i class="ki-duotone ki-information-5 fs-2x text-info me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1">Sistema de Fallback de Segurança</h4>
                    <span>Este sistema monitora conversas onde a IA não respondeu e tenta reprocessar automaticamente ou escalar para um agente humano quando necessário.</span>
                </div>
            </div>
            
            <!-- Habilitar Fallback -->
            <div class="fv-row mb-7">
                <label class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="ai_fallback_enabled" 
                           value="1" <?= ($aiSettings['ai_fallback_enabled'] ?? true) ? 'checked' : '' ?> />
                    <span class="form-check-label fw-semibold">
                        Habilitar Monitoramento de Fallback
                    </span>
                </label>
                <div class="form-text">Ativa o sistema de monitoramento e reprocessamento automático de conversas travadas</div>
            </div>
            
            <div class="separator my-5"></div>
            
            <!-- Intervalo de Verificação -->
            <div class="fv-row mb-7">
                <label class="required fw-semibold fs-6 mb-2">
                    Intervalo de Verificação (minutos)
                </label>
                <input type="number" name="ai_fallback_check_interval_minutes" 
                       class="form-control form-control-solid" 
                       value="<?= (int)($aiSettings['ai_fallback_check_interval_minutes'] ?? 15) ?>" 
                       min="1" max="60" required />
                <div class="form-text">Frequência de verificação de conversas travadas. Padrão: 15 minutos</div>
            </div>
            
            <!-- Delay Mínimo -->
            <div class="fv-row mb-7">
                <label class="required fw-semibold fs-6 mb-2">
                    Delay Mínimo (minutos)
                </label>
                <input type="number" name="ai_fallback_min_delay_minutes" 
                       class="form-control form-control-solid" 
                       value="<?= (int)($aiSettings['ai_fallback_min_delay_minutes'] ?? 5) ?>" 
                       min="1" max="60" required />
                <div class="form-text">Tempo mínimo antes de considerar uma conversa como travada. Padrão: 5 minutos</div>
            </div>
            
            <!-- Delay Máximo -->
            <div class="fv-row mb-7">
                <label class="required fw-semibold fs-6 mb-2">
                    Delay Máximo (horas)
                </label>
                <input type="number" name="ai_fallback_max_delay_hours" 
                       class="form-control form-control-solid" 
                       value="<?= (int)($aiSettings['ai_fallback_max_delay_hours'] ?? 24) ?>" 
                       min="1" max="168" required />
                <div class="form-text">Tempo máximo para considerar uma conversa como travada. Padrão: 24 horas</div>
            </div>
            
            <!-- Máximo de Tentativas -->
            <div class="fv-row mb-7">
                <label class="required fw-semibold fs-6 mb-2">
                    Máximo de Tentativas
                </label>
                <input type="number" name="ai_fallback_max_retries" 
                       class="form-control form-control-solid" 
                       value="<?= (int)($aiSettings['ai_fallback_max_retries'] ?? 3) ?>" 
                       min="1" max="10" required />
                <div class="form-text">Número máximo de tentativas de reprocessamento antes de escalar para humano. Padrão: 3</div>
            </div>
            
            <!-- Escalar Após -->
            <div class="fv-row mb-7">
                <label class="required fw-semibold fs-6 mb-2">
                    Escalar Após (horas)
                </label>
                <input type="number" name="ai_fallback_escalate_after_hours" 
                       class="form-control form-control-solid" 
                       value="<?= (int)($aiSettings['ai_fallback_escalate_after_hours'] ?? 2) ?>" 
                       min="1" max="48" required />
                <div class="form-text">Tempo após o qual escalar para humano mesmo sem exceder tentativas. Padrão: 2 horas</div>
            </div>
            
            <div class="separator my-5"></div>
            
            <!-- Detectar Mensagens de Encerramento -->
            <div class="fv-row mb-7">
                <label class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="ai_fallback_detect_closing_messages" 
                           value="1" <?= ($aiSettings['ai_fallback_detect_closing_messages'] ?? true) ? 'checked' : '' ?> />
                    <span class="form-check-label fw-semibold">
                        Detectar Mensagens de Encerramento
                    </span>
                </label>
                <div class="form-text">Ignorar mensagens de despedida/encerramento (ex: "obrigado", "tchau", "ok")</div>
            </div>
            
            <!-- Usar IA para Detecção de Encerramento -->
            <div class="fv-row mb-7">
                <label class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="ai_fallback_use_ai_for_closing_detection" 
                           value="1" <?= ($aiSettings['ai_fallback_use_ai_for_closing_detection'] ?? false) ? 'checked' : '' ?> />
                    <span class="form-check-label fw-semibold">
                        Usar IA para Detecção de Encerramento
                    </span>
                </label>
                <div class="form-text">Usar OpenAI para detectar mensagens de encerramento com mais precisão (requer API Key configurada)</div>
            </div>
        </div>
    </div>
    <!--end::Card-->
    
    <!--begin::Actions-->
    <div class="d-flex justify-content-end">
        <button type="reset" class="btn btn-light me-3">Cancelar</button>
        <button type="submit" class="btn btn-primary">
            <span class="indicator-label">Salvar Configurações</span>
            <span class="indicator-progress">Aguarde...
            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
        </button>
    </div>
    <!--end::Actions-->
</form>

<script>
// Form IA
const aiForm = document.getElementById('kt_settings_ai_form');
if (aiForm) {
    aiForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm(this, '<?= \App\Helpers\Url::to('/settings/ai') ?>');
    });
}
</script>

