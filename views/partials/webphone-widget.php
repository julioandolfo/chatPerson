<?php
/**
 * Widget do WebPhone API4Com
 * Componente flutuante para fazer/receber chamadas
 */

// Verificar se há conta API4Com com WebPhone habilitado
$account = \App\Models\Api4ComAccount::getFirstEnabled();
$webphoneEnabled = $account && ($account['webphone_enabled'] ?? false);

if (!$webphoneEnabled) {
    return; // Não renderizar se WebPhone não está habilitado
}
?>

<!-- WebPhone Widget -->
<div id="api4com-webphone-widget" class="webphone-widget" style="display: none;">
    <!-- Botão flutuante (minimizado) -->
    <div id="webphone-toggle" class="webphone-toggle">
        <i class="ki-duotone ki-phone fs-1">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
        <span id="api4com-webphone-status" class="badge badge-secondary webphone-status-badge">Offline</span>
    </div>
    
    <!-- Painel expandido -->
    <div id="webphone-panel" class="webphone-panel" style="display: none;">
        <div class="webphone-header">
            <span class="fw-bold">WebPhone</span>
            <span id="api4com-webphone-status-text" class="text-muted fs-8">Carregando...</span>
            <button type="button" class="btn btn-sm btn-icon btn-light" onclick="toggleWebphonePanel()">
                <i class="ki-duotone ki-minus fs-4"></i>
            </button>
        </div>
        
        <div class="webphone-body">
            <!-- Status de conexão -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center">
                    <div id="webphone-status-indicator" class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: #6c757d;"></div>
                    <span id="webphone-status-text" class="text-muted fs-7">Iniciando...</span>
                </div>
                <button type="button" id="btn-reconnect" class="btn btn-sm btn-light-primary d-none" onclick="reconnectWebphone()" title="Reconectar">
                    <i class="ki-duotone ki-arrows-circle fs-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
            </div>
            
            <!-- Discador simples -->
            <div class="mb-3">
                <div class="input-group input-group-sm mb-2">
                    <input type="tel" id="webphone-dial-number" class="form-control" placeholder="Digite o número..." />
                    <button type="button" class="btn btn-success" onclick="dialNumber()" title="Ligar">
                        <i class="ki-duotone ki-phone fs-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
                
                <!-- Teclado numérico -->
                <div class="webphone-keypad d-grid gap-1" style="grid-template-columns: repeat(3, 1fr);">
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('1')">1</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('2')">2</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('3')">3</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('4')">4</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('5')">5</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('6')">6</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('7')">7</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('8')">8</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('9')">9</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('*')">*</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('0')">0</button>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="pressKey('#')">#</button>
                </div>
            </div>
            
            <!-- Controles de chamada -->
            <div id="webphone-call-controls" class="d-none">
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-sm btn-light-warning" onclick="toggleMute()" id="btn-mute" title="Mudo">
                        <i class="ki-duotone ki-speaker fs-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="window.api4comWebphone?.hangup()" title="Desligar">
                        <i class="ki-duotone ki-phone fs-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Desligar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Indicador de chamada recebida -->
        <div id="webphone-incoming-call" class="webphone-incoming" style="display: none;">
            <div class="d-flex align-items-center justify-content-between p-3 bg-warning">
                <span class="text-dark fw-bold">📞 Chamada recebida</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-success" onclick="window.api4comWebphone?.answer()">
                        Atender
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="window.api4comWebphone?.reject()">
                        Rejeitar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Indicador de chamada ativa -->
        <div id="webphone-call-indicator" class="webphone-call-indicator" style="display: none;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-white fw-bold">Em chamada</span>
                    <span id="webphone-call-duration" class="text-white-50 ms-2">00:00</span>
                </div>
                <button type="button" class="btn btn-sm btn-danger" onclick="window.api4comWebphone?.hangup()">
                    <i class="ki-duotone ki-phone fs-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Desligar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.webphone-widget {
    position: fixed;
    bottom: 20px;
    left: 20px;
    z-index: 1050;
    font-family: inherit;
}

.webphone-toggle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3699FF 0%, #0062cc 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(54, 153, 255, 0.4);
    transition: all 0.3s ease;
    position: relative;
}

.webphone-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(54, 153, 255, 0.5);
}

.webphone-toggle i {
    color: white;
}

.webphone-status-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 10px;
}

.webphone-panel {
    position: absolute;
    bottom: 70px;
    left: 0;
    width: 320px;
    background: var(--bs-body-bg, #1e1e2d);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    border: 1px solid var(--bs-border-color, #2d2d3a);
}

.webphone-header {
    padding: 12px 15px;
    background: var(--bs-gray-200, #2d2d3a);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.webphone-body {
    padding: 15px;
    max-height: 400px;
    overflow-y: auto;
}

.webphone-devices {
    font-size: 12px;
}

.webphone-call-indicator {
    padding: 12px 15px;
    background: linear-gradient(135deg, #50cd89 0%, #1bc5bd 100%);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

/* Estilos para o estado de chamada ativa */
.webphone-toggle.in-call {
    background: linear-gradient(135deg, #50cd89 0%, #1bc5bd 100%);
    animation: pulse 2s infinite;
}

.webphone-toggle.registered {
    background: linear-gradient(135deg, #50cd89 0%, #1bc5bd 100%);
}

.webphone-toggle.error {
    background: linear-gradient(135deg, #f1416c 0%, #d9214e 100%);
}

.webphone-toggle.connecting {
    background: linear-gradient(135deg, #ffc700 0%, #f1bc00 100%);
}

/* Tema claro */
[data-bs-theme="light"] .webphone-panel {
    background: #ffffff;
    border-color: #e4e6ef;
}

[data-bs-theme="light"] .webphone-header {
    background: #f5f8fa;
}
</style>

<script>
// Variável global para o webphone
window.api4comWebphone = null;
let callTimer = null;
let callSeconds = 0;

document.addEventListener('DOMContentLoaded', function() {
    // Verificar se o widget existe
    const widget = document.getElementById('api4com-webphone-widget');
    if (!widget) return;
    
    // Mostrar widget
    widget.style.display = 'block';
    
    // Inicializar WebPhone
    initWebphone();
    
    // Toggle do painel
    document.getElementById('webphone-toggle').addEventListener('click', toggleWebphonePanel);
});

function initWebphone() {
    // Verificar se a classe existe
    if (typeof Api4ComWebPhone === 'undefined') {
        // Carregar script do webphone
        const script = document.createElement('script');
        script.src = '/assets/js/api4com-webphone.js';
        script.onload = function() {
            createWebphoneInstance();
        };
        document.head.appendChild(script);
    } else {
        createWebphoneInstance();
    }
}

function createWebphoneInstance() {
    window.api4comWebphone = new Api4ComWebPhone({
        renderTarget: 'api4com-webphone',
        autoAnswer: true,
        onStatusChange: function(status, message) {
            updateWebphoneUI(status, message);
            
            // Se recebeu chamada e autoAnswer está ativo, atender automaticamente
            if (status === 'ringing' && window.api4comWebphone) {
                console.log('[WebPhone Widget] Auto-atendendo chamada...');
                setTimeout(function() {
                    if (window.api4comWebphone && typeof window.api4comWebphone.answer === 'function') {
                        window.api4comWebphone.answer();
                    }
                }, 500);
            }
        },
        onCallStart: function(call) {
            showCallIndicator(true);
            showCallControls(true);
            startCallTimer();
        },
        onCallEnd: function(call) {
            showCallIndicator(false);
            showCallControls(false);
            stopCallTimer();
        },
        onError: function(error) {
            console.error('[WebPhone Widget] Erro:', error);
        }
    });
}

function toggleWebphonePanel() {
    const panel = document.getElementById('webphone-panel');
    const toggle = document.getElementById('webphone-toggle');
    
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        toggle.style.display = 'none';
    } else {
        panel.style.display = 'none';
        toggle.style.display = 'flex';
    }
}

function updateWebphoneUI(status, message) {
    const toggle = document.getElementById('webphone-toggle');
    const badge = document.getElementById('api4com-webphone-status');
    const statusText = document.getElementById('api4com-webphone-status-text');
    
    // Atualizar badge
    const badgeClasses = {
        'registered': 'badge-success',
        'connected': 'badge-info',
        'connecting': 'badge-warning',
        'disconnected': 'badge-secondary',
        'unregistered': 'badge-danger',
        'error': 'badge-danger',
        'not_configured': 'badge-secondary'
    };
    
    badge.className = 'badge webphone-status-badge ' + (badgeClasses[status] || 'badge-secondary');
    badge.textContent = message;
    
    // Atualizar texto de status
    if (statusText) {
        statusText.textContent = message;
    }
    
    // Atualizar estilo do botão
    toggle.classList.remove('registered', 'error', 'connecting', 'in-call');
    if (status === 'registered') {
        toggle.classList.add('registered');
    } else if (status === 'error' || status === 'unregistered') {
        toggle.classList.add('error');
    } else if (status === 'connecting') {
        toggle.classList.add('connecting');
    }
    
    // Mostrar botão de reconectar quando desconectado/erro
    const showReconnect = ['error', 'unregistered', 'disconnected', 'not_configured'].includes(status);
    showReconnectButton(showReconnect);
}

function showCallIndicator(show) {
    const indicator = document.getElementById('webphone-call-indicator');
    const toggle = document.getElementById('webphone-toggle');
    
    if (indicator) {
        indicator.style.display = show ? 'block' : 'none';
    }
    
    if (show) {
        toggle.classList.add('in-call');
    } else {
        toggle.classList.remove('in-call');
    }
}

function startCallTimer() {
    callSeconds = 0;
    updateCallDuration();
    callTimer = setInterval(function() {
        callSeconds++;
        updateCallDuration();
    }, 1000);
}

function stopCallTimer() {
    if (callTimer) {
        clearInterval(callTimer);
        callTimer = null;
    }
    callSeconds = 0;
}

function updateCallDuration() {
    const duration = document.getElementById('webphone-call-duration');
    if (duration) {
        const mins = Math.floor(callSeconds / 60);
        const secs = callSeconds % 60;
        duration.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }
}

// Função global para fazer chamada (pode ser chamada de outros lugares)
function makeApi4ComCall(phoneNumber) {
    if (window.api4comWebphone && window.api4comWebphone.isReady()) {
        return window.api4comWebphone.call(phoneNumber);
    } else {
        console.warn('[WebPhone] WebPhone não está pronto para fazer chamadas');
        return false;
    }
}

// Funções do teclado
function pressKey(digit) {
    const input = document.getElementById('webphone-dial-number');
    if (input) {
        input.value += digit;
    }
    
    // Se em chamada, enviar DTMF
    if (window.api4comWebphone && window.api4comWebphone.currentSession) {
        window.api4comWebphone.sendDTMF(digit);
    }
}

function dialNumber() {
    const input = document.getElementById('webphone-dial-number');
    const number = input ? input.value.trim() : '';
    
    if (!number) {
        alert('Digite um número para ligar');
        return;
    }
    
    if (window.api4comWebphone && window.api4comWebphone.isReady()) {
        window.api4comWebphone.call(number);
        showCallControls(true);
    } else {
        alert('WebPhone não está conectado. Aguarde a conexão.');
    }
}

function toggleMute() {
    if (window.api4comWebphone) {
        window.api4comWebphone.toggleMute();
        
        const btn = document.getElementById('btn-mute');
        if (btn) {
            if (window.api4comWebphone.isMuted()) {
                btn.classList.remove('btn-light-warning');
                btn.classList.add('btn-warning');
            } else {
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-light-warning');
            }
        }
    }
}

function showCallControls(show) {
    const controls = document.getElementById('webphone-call-controls');
    if (controls) {
        if (show) {
            controls.classList.remove('d-none');
        } else {
            controls.classList.add('d-none');
        }
    }
}

function showReconnectButton(show) {
    const btn = document.getElementById('btn-reconnect');
    if (btn) {
        if (show) {
            btn.classList.remove('d-none');
        } else {
            btn.classList.add('d-none');
        }
    }
}

async function reconnectWebphone() {
    const btn = document.getElementById('btn-reconnect');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }
    
    try {
        if (window.api4comWebphone) {
            await window.api4comWebphone.reconnect();
        } else {
            createWebphoneInstance();
        }
    } catch (error) {
        console.error('[WebPhone] Erro ao reconectar:', error);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i>';
        }
    }
}
</script>
