<?php
/**
 * Aba de Configurações de Notificações do Usuário
 * Reutiliza a lógica de sounds-tab.php mas com URLs de /profile
 */

use App\Models\UserSoundSettings;
use App\Helpers\Auth;

$userId = Auth::id();
$userSettings = $soundSettings ?? UserSoundSettings::getOrCreate($userId);
$soundEvents = UserSoundSettings::SOUND_EVENTS;
$availableSounds = $availableSounds ?? [];
?>

<div class="notifications-settings">
    <div class="d-flex flex-column flex-lg-row gap-7 gap-lg-10">
        <!-- Coluna Esquerda: Configurações Gerais -->
        <div class="flex-lg-row-fluid">
            <div class="card card-flush mb-5">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">
                            <i class="ki-duotone ki-notification-on fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            Sons de Notificação
                        </h3>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-light-primary" onclick="testAllSounds()">
                            <i class="ki-duotone ki-speaker fs-5 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Testar Todos
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="notificationSettingsForm">
                        <!-- Habilitar Sons -->
                        <div class="d-flex align-items-center justify-content-between mb-5 p-4 bg-light-primary rounded">
                            <div>
                                <h5 class="fw-bold mb-1">Sons Habilitados</h5>
                                <span class="text-muted fs-7">Ativar ou desativar todos os sons de notificação</span>
                            </div>
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input type="checkbox" class="form-check-input h-25px w-45px" 
                                       id="sounds_enabled" name="sounds_enabled" 
                                       <?= $userSettings['sounds_enabled'] ? 'checked' : '' ?>
                                       onchange="toggleAllSounds(this.checked)">
                            </div>
                        </div>

                        <!-- Volume -->
                        <div class="mb-7" id="volumeControl">
                            <label class="form-label fw-semibold">
                                <i class="ki-duotone ki-speaker fs-6 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Volume: <span id="volumeValue"><?= $userSettings['volume'] ?>%</span>
                            </label>
                            <input type="range" class="form-range" id="volume" name="volume" 
                                   min="0" max="100" value="<?= $userSettings['volume'] ?>"
                                   oninput="updateVolumeDisplay(this.value)">
                        </div>

                        <div class="separator my-5"></div>

                        <!-- Configuração por Evento -->
                        <h5 class="fw-bold mb-5">
                            <i class="ki-duotone ki-setting-3 fs-5 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                            Configurar Sons por Evento
                        </h5>

                        <?php foreach ($soundEvents as $eventKey => $eventInfo): 
                            $enabledField = $eventKey . '_enabled';
                            $soundField = $eventKey . '_sound';
                        ?>
                        <div class="sound-event-row d-flex align-items-center justify-content-between p-4 mb-3 border rounded hover-elevate-up" 
                             style="transition: all 0.2s;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="symbol symbol-40px symbol-circle bg-light-primary">
                                    <span class="symbol-label">
                                        <i class="ki-duotone <?= $eventInfo['icon'] ?> fs-4 text-primary">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                    </span>
                                </div>
                                <div>
                                    <div class="fw-semibold"><?= $eventInfo['label'] ?></div>
                                    <div class="text-muted fs-7"><?= $eventInfo['description'] ?></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <!-- Select de Som -->
                                <select class="form-select form-select-sm form-select-solid w-150px" 
                                        id="<?= $soundField ?>" name="<?= $soundField ?>"
                                        <?= !$userSettings[$enabledField] ? 'disabled' : '' ?>>
                                    <?php foreach ($availableSounds as $sound): ?>
                                    <option value="<?= htmlspecialchars($sound['filename']) ?>" 
                                            <?= $userSettings[$soundField] === $sound['filename'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sound['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <!-- Botão Testar -->
                                <button type="button" class="btn btn-sm btn-icon btn-light-info" 
                                        onclick="testSound('<?= $eventKey ?>')" 
                                        title="Testar som"
                                        <?= !$userSettings[$enabledField] ? 'disabled' : '' ?>>
                                    <i class="ki-duotone ki-speaker fs-5">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                                
                                <!-- Toggle Ativar/Desativar -->
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input type="checkbox" class="form-check-input h-20px w-35px" 
                                           id="<?= $enabledField ?>" name="<?= $enabledField ?>"
                                           <?= $userSettings[$enabledField] ? 'checked' : '' ?>
                                           onchange="toggleSoundEvent('<?= $eventKey ?>', this.checked)">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="separator my-7"></div>

                        <!-- Notificações Visuais -->
                        <div class="mb-5">
                            <h5 class="fw-bold mb-4">
                                <i class="ki-duotone ki-notification fs-5 me-2 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Notificações Visuais (Push)
                            </h5>
                            
                            <!-- Habilitar notificações visuais -->
                            <div class="d-flex align-items-center justify-content-between p-4 bg-light-info rounded mb-4">
                                <div>
                                    <div class="fw-semibold">Notificações na Tela</div>
                                    <div class="text-muted fs-7">Mostrar alertas visuais no canto da tela</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input type="checkbox" class="form-check-input h-20px w-35px" 
                                           id="visual_notifications_enabled" name="visual_notifications_enabled"
                                           <?= ($userSettings['visual_notifications_enabled'] ?? 1) ? 'checked' : '' ?>
                                           onchange="toggleVisualNotifications(this.checked)">
                                </div>
                            </div>
                            
                            <div id="visualNotificationsConfig" style="<?= !($userSettings['visual_notifications_enabled'] ?? 1) ? 'display:none' : '' ?>">
                                <!-- Notificações do navegador -->
                                <div class="d-flex align-items-center justify-content-between p-4 border rounded mb-3">
                                    <div>
                                        <div class="fw-semibold">Notificações do Navegador</div>
                                        <div class="text-muted fs-7">Mostrar também quando a aba não está focada</div>
                                    </div>
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" class="form-check-input h-20px w-35px" 
                                               id="browser_notifications_enabled" name="browser_notifications_enabled"
                                               <?= ($userSettings['browser_notifications_enabled'] ?? 1) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                
                                <!-- Preview da mensagem -->
                                <div class="d-flex align-items-center justify-content-between p-4 border rounded mb-3">
                                    <div>
                                        <div class="fw-semibold">Mostrar Prévia</div>
                                        <div class="text-muted fs-7">Exibir conteúdo da mensagem na notificação</div>
                                    </div>
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input type="checkbox" class="form-check-input h-20px w-35px" 
                                               id="show_notification_preview" name="show_notification_preview"
                                               <?= ($userSettings['show_notification_preview'] ?? 1) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                
                                <div class="row g-3 mb-3">
                                    <!-- Posição -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Posição na Tela</label>
                                        <select class="form-select form-select-solid" id="notification_position" name="notification_position">
                                            <?php foreach (UserSoundSettings::NOTIFICATION_POSITIONS as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= ($userSettings['notification_position'] ?? 'bottom-right') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Duração -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Duração (segundos)</label>
                                        <select class="form-select form-select-solid" id="notification_duration" name="notification_duration">
                                            <option value="5000" <?= ($userSettings['notification_duration'] ?? 8000) == 5000 ? 'selected' : '' ?>>5 segundos</option>
                                            <option value="8000" <?= ($userSettings['notification_duration'] ?? 8000) == 8000 ? 'selected' : '' ?>>8 segundos</option>
                                            <option value="10000" <?= ($userSettings['notification_duration'] ?? 8000) == 10000 ? 'selected' : '' ?>>10 segundos</option>
                                            <option value="15000" <?= ($userSettings['notification_duration'] ?? 8000) == 15000 ? 'selected' : '' ?>>15 segundos</option>
                                            <option value="30000" <?= ($userSettings['notification_duration'] ?? 8000) == 30000 ? 'selected' : '' ?>>30 segundos</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Testar notificação -->
                                <button type="button" class="btn btn-light-primary btn-sm" onclick="testNotification()">
                                    <i class="ki-duotone ki-notification fs-5 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Testar Notificação
                                </button>
                            </div>
                        </div>

                        <div class="separator my-7"></div>

                        <!-- Horário Silencioso -->
                        <div class="mb-5">
                            <h5 class="fw-bold mb-4">
                                <i class="ki-duotone ki-moon fs-5 me-2 text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Horário Silencioso
                            </h5>
                            
                            <div class="d-flex align-items-center justify-content-between p-4 border rounded mb-3">
                                <div>
                                    <div class="fw-semibold">Ativar Horário Silencioso</div>
                                    <div class="text-muted fs-7">Silenciar notificações em horários específicos</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input type="checkbox" class="form-check-input h-20px w-35px" 
                                           id="quiet_hours_enabled" name="quiet_hours_enabled"
                                           <?= ($userSettings['quiet_hours_enabled'] ?? 0) ? 'checked' : '' ?>
                                           onchange="toggleQuietHours(this.checked)">
                                </div>
                            </div>
                            
                            <div id="quietHoursConfig" class="row g-3" style="<?= !($userSettings['quiet_hours_enabled'] ?? 0) ? 'display:none' : '' ?>">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Início</label>
                                    <input type="time" class="form-control form-control-solid" 
                                           id="quiet_hours_start" name="quiet_hours_start" 
                                           value="<?= substr($userSettings['quiet_hours_start'] ?? '22:00:00', 0, 5) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Fim</label>
                                    <input type="time" class="form-control form-control-solid" 
                                           id="quiet_hours_end" name="quiet_hours_end" 
                                           value="<?= substr($userSettings['quiet_hours_end'] ?? '08:00:00', 0, 5) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Botão Salvar -->
                        <div class="d-flex justify-content-end mt-7">
                            <button type="submit" class="btn btn-primary" id="saveNotificationsBtn">
                                <i class="ki-duotone ki-check fs-4 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Sons Personalizados -->
        <div class="w-lg-350px">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="fw-bold mb-0">
                            <i class="ki-duotone ki-music-square fs-2 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Seus Sons
                        </h3>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Upload de Som -->
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Adicionar Som Personalizado</label>
                        <input type="text" class="form-control form-control-solid mb-3" 
                               id="customSoundName" placeholder="Nome do som">
                        <div class="dropzone dropzone-queue" id="customSoundDropzone">
                            <input type="file" id="customSoundFile" accept=".mp3,.wav,.ogg" hidden 
                                   onchange="showSelectedFile(this.files[0])">
                            <div class="dz-message needsclick align-items-center cursor-pointer" 
                                 onclick="document.getElementById('customSoundFile').click()">
                                <i class="ki-duotone ki-file-up fs-3x text-primary mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="ms-4">
                                    <h3 class="fs-7 fw-bold text-gray-900 mb-1">Arraste ou clique para enviar</h3>
                                    <span class="fs-8 text-muted">MP3, WAV ou OGG até 2MB</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm w-100 mt-3" onclick="uploadCustomSound()">
                            <i class="ki-duotone ki-cloud-upload fs-5 me-1">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Enviar Som
                        </button>
                    </div>

                    <div class="separator my-5"></div>

                    <!-- Lista de Sons Personalizados -->
                    <div id="customSoundsList" class="mh-300px scroll-y">
                        <div class="text-center text-muted py-5">
                            <i class="ki-duotone ki-music-square fs-3x opacity-50 mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <p class="mb-0">Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// URLs base para APIs
const API_BASE = '<?= \App\Helpers\Url::to("/profile") ?>';
const SOUNDS_API = '<?= \App\Helpers\Url::to("/settings/sounds") ?>';

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Carregar sons disponíveis e personalizados
    loadAvailableSounds().then(() => {
        loadCustomSounds();
    });
    
    // Form submit
    document.getElementById('notificationSettingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveNotificationSettings();
    });
});

function showSelectedFile(file) {
    const dropzone = document.getElementById('customSoundDropzone');
    dropzone.querySelector('.dz-message').innerHTML = `
        <i class="ki-duotone ki-check-circle fs-3x text-success mb-3">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
        <div class="ms-4">
            <h3 class="fs-7 fw-bold text-gray-900 mb-1">${file.name}</h3>
            <span class="fs-8 text-muted">${(file.size / 1024).toFixed(1)} KB</span>
        </div>
    `;
}

function updateVolumeDisplay(value) {
    document.getElementById('volumeValue').textContent = value + '%';
}

function toggleAllSounds(enabled) {
    const container = document.getElementById('notificationSettingsForm');
    const elements = container.querySelectorAll('select, button:not([type="submit"]), input[type="checkbox"]:not(#sounds_enabled)');
    elements.forEach(el => {
        if (!enabled) {
            el.disabled = true;
        } else {
            const row = el.closest('.sound-event-row');
            if (row) {
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (checkbox && !checkbox.checked) {
                    el.disabled = true;
                } else {
                    el.disabled = false;
                }
            } else {
                el.disabled = false;
            }
        }
    });
}

function toggleSoundEvent(eventKey, enabled) {
    const select = document.getElementById(eventKey + '_sound');
    const testBtn = select.closest('.sound-event-row').querySelector('button');
    
    if (select) select.disabled = !enabled;
    if (testBtn) testBtn.disabled = !enabled;
}

function toggleQuietHours(enabled) {
    const config = document.getElementById('quietHoursConfig');
    if (config) {
        config.style.display = enabled ? 'flex' : 'none';
    }
}

function toggleVisualNotifications(enabled) {
    const config = document.getElementById('visualNotificationsConfig');
    if (config) {
        config.style.display = enabled ? 'block' : 'none';
    }
}

function testNotification() {
    if (typeof NotificationManager !== 'undefined') {
        NotificationManager.show({
            type: 'info',
            title: 'Teste de Notificação',
            message: 'Esta é uma notificação de teste! Clique para fechar.',
            duration: 5000
        });
    } else {
        Swal.fire({
            icon: 'info',
            title: 'Não disponível',
            text: 'NotificationManager não está carregado'
        });
    }
}

// Cache de sons disponíveis
let availableSoundsCache = [];

function loadAvailableSounds(forceReload = false) {
    if (availableSoundsCache.length > 0 && !forceReload) {
        return Promise.resolve(availableSoundsCache);
    }
    
    return fetch(SOUNDS_API + '/available', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.sounds) {
            availableSoundsCache = data.sounds;
            return data.sounds;
        }
        return [];
    })
    .catch(error => {
        console.error('Erro ao carregar sons:', error);
        return [];
    });
}

function updateAllSoundDropdowns() {
    loadAvailableSounds(true).then(sounds => {
        if (!sounds || sounds.length === 0) return;
        
        const soundEvents = <?= json_encode(array_keys(UserSoundSettings::SOUND_EVENTS)) ?>;
        
        soundEvents.forEach(eventKey => {
            const select = document.getElementById(eventKey + '_sound');
            if (!select) return;
            
            const currentValue = select.value;
            select.innerHTML = '';
            
            sounds.forEach(sound => {
                const option = document.createElement('option');
                option.value = sound.filename;
                option.textContent = sound.name;
                if (sound.filename === currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        });
    });
}

function getSoundUrl(filename) {
    return '/assets/sounds/' + filename;
}

function playSound(url) {
    const audio = new Audio(url);
    audio.volume = (document.getElementById('volume')?.value || 70) / 100;
    audio.play().catch(e => console.error('Erro ao tocar som:', e));
}

function testSound(eventKey) {
    const select = document.getElementById(eventKey + '_sound');
    const soundFile = select ? select.value : null;
    
    if (!soundFile) return;
    
    const url = getSoundUrl(soundFile);
    playSound(url);
}

function testAllSounds() {
    const checkboxes = document.querySelectorAll('.sound-event-row input[type="checkbox"]:checked');
    let delay = 0;
    
    checkboxes.forEach((checkbox, index) => {
        const row = checkbox.closest('.sound-event-row');
        const select = row.querySelector('select');
        if (select && select.value) {
            setTimeout(() => {
                const url = getSoundUrl(select.value);
                playSound(url);
            }, delay);
            delay += 1500;
        }
    });
}

function saveNotificationSettings() {
    const form = document.getElementById('notificationSettingsForm');
    const formData = new FormData(form);
    const data = {};
    
    // Processar checkboxes
    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => {
        data[cb.name] = cb.checked ? 1 : 0;
    });
    
    // Processar outros campos
    formData.forEach((value, key) => {
        if (!key.includes('_enabled')) {
            data[key] = value;
        }
    });
    
    // Processar selects
    const selects = form.querySelectorAll('select');
    selects.forEach(select => {
        if (select.name && select.value) {
            data[select.name] = select.value;
        }
    });
    
    console.log('[NotificationSettings] Salvando:', data);
    
    const btn = document.getElementById('saveNotificationsBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    
    fetch(API_BASE + '/notifications', {
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
            Swal.fire({
                icon: 'success',
                title: 'Configurações salvas!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
            
            // Atualizar configurações globais
            if (typeof window.SoundManager !== 'undefined') {
                window.SoundManager.reloadSettings();
            }
            if (typeof window.NotificationManager !== 'undefined') {
                window.NotificationManager.loadSettings();
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: result.message || 'Erro ao salvar configurações'
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao salvar configurações'
        });
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i> Salvar Configurações';
    });
}

function loadCustomSounds() {
    fetch(SOUNDS_API + '/available', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.sounds) {
            const customSounds = data.sounds.filter(s => s.is_custom);
            renderCustomSoundsList(customSounds);
        }
    })
    .catch(error => console.error('Erro ao carregar sons:', error));
}

function renderCustomSoundsList(sounds) {
    const container = document.getElementById('customSoundsList');
    
    if (!sounds || sounds.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="ki-duotone ki-music-square fs-3x opacity-50 mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <p class="mb-0">Nenhum som personalizado</p>
            </div>
        `;
        return;
    }
    
    const soundEvents = <?= json_encode(UserSoundSettings::SOUND_EVENTS) ?>;
    
    container.innerHTML = sounds.map(sound => {
        const safeUrl = escapeHtml(sound.url);
        const safeFilename = escapeHtml(sound.filename);
        const safeName = escapeHtml(sound.name);
        
        return `
        <div class="d-flex flex-column p-3 border rounded mb-2">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-icon btn-light-primary" onclick="playSound('${safeUrl}')">
                        <i class="ki-duotone ki-speaker fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </button>
                    <span class="fw-semibold">${safeName}</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="deleteCustomSound(${sound.id})">
                    <i class="ki-duotone ki-trash fs-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                </button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm form-select-solid flex-grow-1" onchange="applySoundToEvent('${safeFilename}', this.value)">
                    <option value="">Aplicar a...</option>
                    ${Object.entries(soundEvents).map(([key, info]) => 
                        `<option value="${key}">${info.label}</option>`
                    ).join('')}
                </select>
            </div>
        </div>
        `;
    }).join('');
}

function applySoundToEvent(soundFilename, eventKey) {
    if (!eventKey || !soundFilename) return;
    
    const select = document.getElementById(eventKey + '_sound');
    if (select) {
        let optionExists = false;
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value === soundFilename) {
                optionExists = true;
                break;
            }
        }
        
        if (!optionExists) {
            const soundInfo = availableSoundsCache.find(s => s.filename === soundFilename);
            const soundName = soundInfo ? soundInfo.name : soundFilename;
            
            const newOption = document.createElement('option');
            newOption.value = soundFilename;
            newOption.textContent = soundName;
            select.appendChild(newOption);
        }
        
        select.value = soundFilename;
        
        const checkbox = document.getElementById(eventKey + '_enabled');
        if (checkbox && !checkbox.checked) {
            checkbox.checked = true;
            toggleSoundEvent(eventKey, true);
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Som aplicado!',
            text: 'Clique em Salvar para confirmar.',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });
        
        // Resetar select
        const customSoundRow = event.target.closest('.d-flex.flex-column');
        if (customSoundRow) {
            const selectInRow = customSoundRow.querySelector('select');
            if (selectInRow) selectInRow.value = '';
        }
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function uploadCustomSound() {
    const nameInput = document.getElementById('customSoundName');
    const fileInput = document.getElementById('customSoundFile');
    
    if (!nameInput.value.trim()) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Digite um nome para o som'
        });
        return;
    }
    
    if (!fileInput.files || !fileInput.files[0]) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione um arquivo de som'
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('name', nameInput.value.trim());
    formData.append('sound', fileInput.files[0]);
    
    fetch(SOUNDS_API + '/upload', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Som enviado!',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
            
            nameInput.value = '';
            fileInput.value = '';
            document.getElementById('customSoundDropzone').querySelector('.dz-message').innerHTML = `
                <i class="ki-duotone ki-file-up fs-3x text-primary mb-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="ms-4">
                    <h3 class="fs-7 fw-bold text-gray-900 mb-1">Arraste ou clique para enviar</h3>
                    <span class="fs-8 text-muted">MP3, WAV ou OGG até 2MB</span>
                </div>
            `;
            
            loadCustomSounds();
            updateAllSoundDropdowns();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: result.message || 'Erro ao enviar som'
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao enviar som'
        });
    });
}

function deleteCustomSound(soundId) {
    Swal.fire({
        title: 'Remover som?',
        text: 'Esta ação não pode ser desfeita.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            fetch(`${SOUNDS_API}/${soundId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    loadCustomSounds();
                    updateAllSoundDropdowns();
                    Swal.fire({
                        icon: 'success',
                        title: 'Som removido!',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: result.message || 'Erro ao remover som'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro ao remover som'
                });
            });
        }
    });
}
</script>

