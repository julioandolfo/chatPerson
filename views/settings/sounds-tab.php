<?php
/**
 * Aba de Configurações de Sons de Notificação
 */

use App\Models\UserSoundSettings;
use App\Services\SoundNotificationService;
use App\Helpers\Auth;

$userId = Auth::id();
$userSettings = UserSoundSettings::getOrCreate($userId);
$soundEvents = UserSoundSettings::SOUND_EVENTS;
$availableSounds = SoundNotificationService::getAvailableSounds($userId);
?>

<div class="sounds-settings">
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
                    <form id="soundSettingsForm">
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

                        <!-- Horário Silencioso -->
                        <div class="mb-5">
                            <h5 class="fw-bold mb-4">
                                <i class="ki-duotone ki-moon fs-5 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Horário Silencioso
                            </h5>
                            
                            <div class="d-flex align-items-center justify-content-between p-4 bg-light rounded mb-4">
                                <div>
                                    <div class="fw-semibold">Ativar Horário Silencioso</div>
                                    <div class="text-muted fs-7">Silenciar sons em determinados horários</div>
                                </div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input type="checkbox" class="form-check-input h-20px w-35px" 
                                           id="quiet_hours_enabled" name="quiet_hours_enabled"
                                           <?= $userSettings['quiet_hours_enabled'] ? 'checked' : '' ?>
                                           onchange="toggleQuietHours(this.checked)">
                                </div>
                            </div>
                            
                            <div id="quietHoursConfig" class="row g-3" style="<?= !$userSettings['quiet_hours_enabled'] ? 'display:none' : '' ?>">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Início</label>
                                    <input type="time" class="form-control form-control-solid" 
                                           id="quiet_hours_start" name="quiet_hours_start"
                                           value="<?= substr($userSettings['quiet_hours_start'], 0, 5) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Fim</label>
                                    <input type="time" class="form-control form-control-solid" 
                                           id="quiet_hours_end" name="quiet_hours_end"
                                           value="<?= substr($userSettings['quiet_hours_end'], 0, 5) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="separator my-5"></div>

                        <!-- Botão Salvar -->
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light me-3" onclick="resetToDefaults()">
                                <i class="ki-duotone ki-arrows-circle fs-5 me-1">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Restaurar Padrões
                            </button>
                            <button type="submit" class="btn btn-primary" id="saveSoundsBtn">
                                <i class="ki-duotone ki-check fs-5 me-1">
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
        <div class="flex-lg-row-auto w-lg-350px">
            <div class="card card-flush">
                <div class="card-header">
                    <div class="card-title">
                        <h4 class="fw-bold mb-0">
                            <i class="ki-duotone ki-music fs-3 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Sons Personalizados
                        </h4>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted fs-7 mb-5">
                        Faça upload de seus próprios sons para usar como notificações.
                        Formatos aceitos: MP3, WAV, OGG. Máximo: 2MB.
                    </p>
                    
                    <!-- Upload Form -->
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Nome do Som</label>
                        <input type="text" class="form-control form-control-solid mb-3" 
                               id="customSoundName" placeholder="Ex: Meu Som Personalizado">
                        
                        <label class="form-label fw-semibold">Arquivo de Áudio</label>
                        <div class="dropzone dz-clickable" id="customSoundDropzone">
                            <div class="dz-message needsclick">
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
                        <input type="file" id="customSoundFile" accept=".mp3,.wav,.ogg,.webm" style="display:none">
                    </div>
                    
                    <button type="button" class="btn btn-primary w-100 mb-5" onclick="uploadCustomSound()">
                        <i class="ki-duotone ki-cloud-add fs-5 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Enviar Som
                    </button>

                    <div class="separator my-5"></div>

                    <!-- Lista de Sons Personalizados -->
                    <h5 class="fw-semibold mb-4">Seus Sons</h5>
                    <div id="customSoundsList">
                        <div class="text-center text-muted py-5">
                            <i class="ki-duotone ki-music-square fs-3x opacity-50 mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <p class="mb-0">Nenhum som personalizado</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Elemento de áudio para reproduzir sons -->
<audio id="soundPlayer" preload="auto"></audio>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar dropzone simulado
    const dropzone = document.getElementById('customSoundDropzone');
    const fileInput = document.getElementById('customSoundFile');
    
    if (dropzone) {
        dropzone.addEventListener('click', () => fileInput.click());
        
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dz-drag-hover');
        });
        
        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dz-drag-hover');
        });
        
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dz-drag-hover');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                showSelectedFile(e.dataTransfer.files[0]);
            }
        });
    }
    
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            showSelectedFile(e.target.files[0]);
        }
    });
    
    // Carregar sons personalizados
    loadCustomSounds();
    
    // Form submit
    document.getElementById('soundSettingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveSoundSettings();
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
    const container = document.getElementById('soundSettingsForm');
    const elements = container.querySelectorAll('select, button:not([type="submit"]), input[type="checkbox"]:not(#sounds_enabled)');
    elements.forEach(el => {
        if (!enabled) {
            el.disabled = true;
        } else {
            // Restaurar estado baseado no checkbox individual
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

function testSound(eventKey) {
    const select = document.getElementById(eventKey + '_sound');
    const soundFile = select ? select.value : null;
    
    if (!soundFile) return;
    
    playSound('/assets/sounds/' + soundFile);
}

function testAllSounds() {
    const checkboxes = document.querySelectorAll('.sound-event-row input[type="checkbox"]:checked');
    let delay = 0;
    
    checkboxes.forEach((checkbox, index) => {
        const row = checkbox.closest('.sound-event-row');
        const select = row.querySelector('select');
        if (select && select.value) {
            setTimeout(() => {
                playSound('/assets/sounds/' + select.value);
            }, delay);
            delay += 1500; // 1.5 segundos entre cada som
        }
    });
}

function playSound(url) {
    const player = document.getElementById('soundPlayer');
    const volume = document.getElementById('volume').value / 100;
    
    player.volume = volume;
    player.src = url;
    player.play().catch(e => console.error('Erro ao tocar som:', e));
}

function saveSoundSettings() {
    const form = document.getElementById('soundSettingsForm');
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
    
    const btn = document.getElementById('saveSoundsBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    
    fetch('<?= \App\Helpers\Url::to("/settings/sounds") ?>', {
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
            
            // Atualizar configurações globais do sistema de som
            if (typeof window.SoundManager !== 'undefined') {
                window.SoundManager.reloadSettings();
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
        btn.innerHTML = '<i class="ki-duotone ki-check fs-5 me-1"><span class="path1"></span><span class="path2"></span></i> Salvar Configurações';
    });
}

function loadCustomSounds() {
    fetch('<?= \App\Helpers\Url::to("/settings/sounds/available") ?>', {
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
    
    container.innerHTML = sounds.map(sound => `
        <div class="d-flex align-items-center justify-content-between p-3 border rounded mb-2">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-icon btn-light-primary" onclick="playSound('${sound.url}')">
                    <i class="ki-duotone ki-speaker fs-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                </button>
                <span class="fw-semibold">${escapeHtml(sound.name)}</span>
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
    `).join('');
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
    
    if (!fileInput.files || fileInput.files.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Atenção',
            text: 'Selecione um arquivo de áudio'
        });
        return;
    }
    
    const formData = new FormData();
    formData.append('name', nameInput.value.trim());
    formData.append('sound', fileInput.files[0]);
    
    fetch('<?= \App\Helpers\Url::to("/settings/sounds/upload") ?>', {
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
            
            // Limpar form
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
            
            // Recarregar lista
            loadCustomSounds();
            
            // Recarregar página para atualizar selects
            setTimeout(() => location.reload(), 1000);
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
            fetch(`<?= \App\Helpers\Url::to("/settings/sounds") ?>/${soundId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    loadCustomSounds();
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
                        text: result.message
                    });
                }
            });
        }
    });
}

function resetToDefaults() {
    Swal.fire({
        title: 'Restaurar padrões?',
        text: 'Todas as suas configurações de som serão resetadas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, restaurar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            // Resetar valores no form
            document.getElementById('sounds_enabled').checked = true;
            document.getElementById('volume').value = 70;
            document.getElementById('volumeValue').textContent = '70%';
            document.getElementById('quiet_hours_enabled').checked = false;
            document.getElementById('quietHoursConfig').style.display = 'none';
            
            // Resetar cada evento para valores padrão
            const defaults = {
                new_conversation: 'new-conversation.mp3',
                new_message: 'new-message.mp3',
                conversation_assigned: 'assigned.mp3',
                invite_received: 'invite.mp3',
                sla_warning: 'sla-warning.mp3',
                sla_breached: 'sla-breached.mp3',
                mention_received: 'mention.mp3'
            };
            
            Object.keys(defaults).forEach(key => {
                const checkbox = document.getElementById(key + '_enabled');
                const select = document.getElementById(key + '_sound');
                
                if (checkbox) checkbox.checked = true;
                if (select) {
                    select.value = defaults[key];
                    select.disabled = false;
                }
                
                // Habilitar botão de teste
                const row = checkbox?.closest('.sound-event-row');
                if (row) {
                    const btn = row.querySelector('button');
                    if (btn) btn.disabled = false;
                }
            });
            
            toggleAllSounds(true);
            
            Swal.fire({
                icon: 'info',
                title: 'Padrões restaurados',
                text: 'Clique em Salvar para aplicar as alterações.',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}
</script>

<style>
.sound-event-row:hover {
    background-color: var(--bs-gray-100);
    border-color: var(--bs-primary) !important;
}

.dropzone {
    border: 2px dashed var(--bs-gray-300);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.dropzone:hover, .dropzone.dz-drag-hover {
    border-color: var(--bs-primary);
    background-color: var(--bs-light-primary);
}

.form-range {
    height: 8px;
}

.form-range::-webkit-slider-thumb {
    width: 20px;
    height: 20px;
}
</style>

