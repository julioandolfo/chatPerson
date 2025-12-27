<?php
$as = $availabilitySettings ?? [];
$bhs = $businessHoursSettings ?? [];
?>

<form id="availability_form" method="POST" action="<?= \App\Helpers\Url::to('/settings/availability') ?>">
    <?= \App\Helpers\Csrf::field() ?>
    
    <!-- Configurações de Disponibilidade -->
    <div class="mb-10">
        <h4 class="fw-bold mb-5">Configurações de Disponibilidade</h4>
        
        <div class="row mb-5">
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Marcar como online automaticamente ao fazer login</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="auto_online_on_login" id="auto_online_on_login" 
                           <?= ($as['auto_online_on_login'] ?? true) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="auto_online_on_login">
                        Habilitar
                    </label>
                </div>
                <div class="form-text">Quando habilitado, o agente será marcado como online automaticamente ao fazer login</div>
            </div>
            
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Marcar como offline automaticamente ao fazer logout</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="auto_offline_on_logout" id="auto_offline_on_logout" 
                           <?= ($as['auto_offline_on_logout'] ?? true) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="auto_offline_on_logout">
                        Habilitar
                    </label>
                </div>
                <div class="form-text">Quando habilitado, o agente será marcado como offline automaticamente ao fazer logout</div>
            </div>
        </div>
        
        <div class="row mb-5">
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Habilitar mudança automática para Ausente</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="auto_away_enabled" id="auto_away_enabled" 
                           <?= ($as['auto_away_enabled'] ?? true) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="auto_away_enabled">
                        Habilitar
                    </label>
                </div>
                <div class="form-text">Quando habilitado, o agente será marcado como ausente após período de inatividade</div>
            </div>
            
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Minutos de inatividade para mudar para Ausente</label>
                <input type="number" class="form-control form-control-solid" name="away_timeout_minutes" 
                       value="<?= htmlspecialchars($as['away_timeout_minutes'] ?? 15) ?>" min="1" max="120" />
                <div class="form-text">Tempo em minutos sem atividade para mudar automaticamente para status "Ausente"</div>
            </div>
        </div>
        
        <div class="row mb-5">
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Rastrear atividade do usuário</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="activity_tracking_enabled" id="activity_tracking_enabled" 
                           <?= ($as['activity_tracking_enabled'] ?? true) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="activity_tracking_enabled">
                        Habilitar
                    </label>
                </div>
                <div class="form-text">Quando habilitado, o sistema rastreia atividade do usuário (mouse, teclado, etc)</div>
            </div>
            
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Intervalo de heartbeat (segundos)</label>
                <input type="number" class="form-control form-control-solid" name="heartbeat_interval_seconds" 
                       value="<?= htmlspecialchars($as['heartbeat_interval_seconds'] ?? 30) ?>" min="10" max="300" />
                <div class="form-text">Intervalo em segundos para envio de heartbeat (WebSocket/Polling)</div>
            </div>
        </div>
        
        <div class="row mb-5">
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Timeout para offline (minutos)</label>
                <input type="number" class="form-control form-control-solid" name="offline_timeout_minutes" 
                       value="<?= htmlspecialchars($as['offline_timeout_minutes'] ?? 5) ?>" min="1" max="60" />
                <div class="form-text">Tempo em minutos sem heartbeat para marcar como offline</div>
            </div>
        </div>
        
        <div class="separator separator-dashed my-5"></div>
        
        <h5 class="fw-bold mb-4">Rastreamento de Atividade</h5>
        
        <div class="row mb-5">
            <div class="col-lg-4">
                <label class="form-label fw-semibold">Rastrear movimento do mouse</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="track_mouse_movement" id="track_mouse_movement" 
                           <?= ($as['track_mouse_movement'] ?? false) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="track_mouse_movement">
                        Habilitar
                    </label>
                </div>
                <div class="form-text">Quando habilitado, movimento do mouse é considerado como atividade</div>
            </div>
            
            <div class="col-lg-4">
                <label class="form-label fw-semibold">Rastrear digitação</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="track_keyboard" id="track_keyboard" 
                           <?= ($as['track_keyboard'] ?? true) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="track_keyboard">
                        Habilitar
                    </label>
                </div>
                <div class="form-text">Quando habilitado, digitação é considerada como atividade</div>
            </div>
            
            <div class="col-lg-4">
                <label class="form-label fw-semibold">Considerar visibilidade da aba</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="track_page_visibility" id="track_page_visibility" 
                           <?= ($as['track_page_visibility'] ?? true) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="track_page_visibility">
                        Habilitar
                    </label>
                </div>
                <div class="form-text">Quando habilitado, mudanças na visibilidade da aba são consideradas</div>
            </div>
        </div>
    </div>
    
    <!-- Configurações de Horário Comercial -->
    <div class="mb-10">
        <h4 class="fw-bold mb-5">Horário Comercial</h4>
        <p class="text-muted mb-5">Configure o horário comercial para cálculo de tempo em cada status. Quando habilitado, apenas o tempo dentro do horário comercial será considerado nos cálculos.</p>
        
        <div class="row mb-5">
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Habilitar horário comercial</label>
                <div class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="business_hours_enabled" id="business_hours_enabled" 
                           <?= ($bhs['enabled'] ?? false) ? 'checked' : '' ?> />
                    <label class="form-check-label" for="business_hours_enabled">
                        Habilitar
                    </label>
                </div>
                <div class="form-text">Quando habilitado, apenas o tempo dentro do horário comercial será considerado</div>
            </div>
            
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Fuso horário</label>
                <select class="form-select form-select-solid" name="business_hours_timezone" id="business_hours_timezone">
                    <option value="America/Sao_Paulo" <?= ($bhs['timezone'] ?? 'America/Sao_Paulo') === 'America/Sao_Paulo' ? 'selected' : '' ?>>America/Sao_Paulo (Brasil)</option>
                    <option value="America/New_York" <?= ($bhs['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>America/New_York (EUA)</option>
                    <option value="Europe/Lisbon" <?= ($bhs['timezone'] ?? '') === 'Europe/Lisbon' ? 'selected' : '' ?>>Europe/Lisbon (Portugal)</option>
                    <option value="UTC" <?= ($bhs['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                </select>
                <div class="form-text">Fuso horário para cálculo do horário comercial</div>
            </div>
        </div>
        
        <div class="separator separator-dashed my-5"></div>
        
        <h5 class="fw-bold mb-4">Horários por Dia da Semana</h5>
        
        <?php
        $days = [
            'monday' => 'Segunda-feira',
            'tuesday' => 'Terça-feira',
            'wednesday' => 'Quarta-feira',
            'thursday' => 'Quinta-feira',
            'friday' => 'Sexta-feira',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo'
        ];
        
        foreach ($days as $dayKey => $dayLabel):
            $start = $bhs["{$dayKey}_start"] ?? '';
            $end = $bhs["{$dayKey}_end"] ?? '';
        ?>
        <div class="row mb-4">
            <div class="col-lg-3">
                <label class="form-label fw-semibold"><?= $dayLabel ?></label>
            </div>
            <div class="col-lg-4">
                <label class="form-label">Início</label>
                <input type="time" class="form-control form-control-solid" 
                       name="<?= $dayKey ?>_start" value="<?= htmlspecialchars($start) ?>" />
            </div>
            <div class="col-lg-4">
                <label class="form-label">Fim</label>
                <input type="time" class="form-control form-control-solid" 
                       name="<?= $dayKey ?>_end" value="<?= htmlspecialchars($end) ?>" />
            </div>
            <div class="col-lg-1 d-flex align-items-end">
                <div class="form-text text-muted small">Deixe vazio para não atender neste dia</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Botões -->
    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <i class="ki-duotone ki-check fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Salvar Configurações
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('availability_form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: data.message || 'Configurações salvas com sucesso!',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert(data.message || 'Configurações salvas com sucesso!');
                    }
                } else {
                    throw new Error(data.message || 'Erro ao salvar configurações');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: error.message || 'Erro ao salvar configurações'
                    });
                } else {
                    alert(error.message || 'Erro ao salvar configurações');
                }
            });
        });
    }
});
</script>

