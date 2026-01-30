/**
 * API4Com WebPhone Integration
 * Componente de softphone WebRTC integrado usando libwebphone da API4Com
 */

class Api4ComWebPhone {
    constructor(options = {}) {
        this.options = {
            renderTarget: options.renderTarget || 'api4com-webphone',
            autoAnswer: options.autoAnswer !== false, // Auto-atender chamadas da API
            onStatusChange: options.onStatusChange || null,
            onCallStart: options.onCallStart || null,
            onCallEnd: options.onCallEnd || null,
            onError: options.onError || null,
            ...options
        };
        
        this.webphone = null;
        this.credentials = null;
        this.isRegistered = false;
        this.currentCall = null;
        this.libLoaded = false;
        
        this.init();
    }
    
    /**
     * Inicializar WebPhone
     */
    async init() {
        try {
            // Carregar credenciais do servidor
            await this.loadCredentials();
            
            if (!this.credentials) {
                this.updateStatus('not_configured', 'WebPhone não configurado');
                return;
            }
            
            // Carregar biblioteca libwebphone
            await this.loadLibrary();
            
            // Inicializar webphone
            this.initWebphone();
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro na inicialização:', error);
            this.handleError(error);
        }
    }
    
    /**
     * Carregar credenciais SIP do servidor
     */
    async loadCredentials() {
        try {
            const response = await fetch('/api4com/webphone-credentials', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            if (data.success && data.credentials) {
                this.credentials = data.credentials;
                console.log('[API4Com WebPhone] Credenciais carregadas:', {
                    domain: this.credentials.domain,
                    extension: this.credentials.extension
                });
                return true;
            } else {
                console.warn('[API4Com WebPhone] Credenciais não disponíveis:', data.message);
                return false;
            }
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao carregar credenciais:', error);
            return false;
        }
    }
    
    /**
     * Carregar biblioteca libwebphone dinamicamente
     */
    loadLibrary() {
        return new Promise((resolve, reject) => {
            if (window.libwebphone) {
                this.libLoaded = true;
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = 'https://api.api4com.com/static/libwebphone.js';
            script.async = true;
            
            script.onload = () => {
                this.libLoaded = true;
                console.log('[API4Com WebPhone] Biblioteca carregada');
                resolve();
            };
            
            script.onerror = (error) => {
                console.error('[API4Com WebPhone] Erro ao carregar biblioteca:', error);
                reject(new Error('Falha ao carregar libwebphone.js'));
            };
            
            document.head.appendChild(script);
        });
    }
    
    /**
     * Inicializar webphone com credenciais
     */
    initWebphone() {
        if (!this.libLoaded || !this.credentials) {
            console.warn('[API4Com WebPhone] Biblioteca ou credenciais não disponíveis');
            return;
        }
        
        const { domain, port, extension, password, realm } = this.credentials;
        
        try {
            this.webphone = new libwebphone({
                dialpad: {
                    renderTargets: [this.options.renderTarget + '-dialpad'],
                },
                audioContext: {
                    renderTargets: [this.options.renderTarget + '-audio']
                },
                mediaDevices: {
                    videoinput: {
                        enabled: false,
                    },
                    renderTargets: [this.options.renderTarget + '-devices'],
                },
                userAgent: {
                    renderTargets: [this.options.renderTarget + '-agent'],
                    transport: {
                        sockets: [`wss://${domain}:${port}`],
                        recovery_max_interval: 30,
                        recovery_min_interval: 2,
                    },
                    authentication: {
                        username: extension,
                        password: password,
                        realm: realm || domain,
                    },
                    user_agent: {
                        instance_id: this.generateInstanceId(),
                        no_answer_timeout: 30,
                        register: true,
                        register_expires: 600,
                        user_agent: 'Chat-WebPhone-libwebphone',
                    },
                },
            });
            
            // Configurar eventos
            this.setupEvents();
            
            console.log('[API4Com WebPhone] Webphone inicializado');
            this.updateStatus('connecting', 'Conectando...');
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao inicializar webphone:', error);
            this.handleError(error);
        }
    }
    
    /**
     * Configurar eventos do webphone
     */
    setupEvents() {
        if (!this.webphone) return;
        
        // Evento de chamada criada
        this.webphone.on('call.created', (lwp, currentCall) => {
            console.log('[API4Com WebPhone] Chamada criada:', currentCall);
            
            if (!currentCall.isPrimary()) {
                // Rejeitar chamadas secundárias
                currentCall.reject();
                return;
            }
            
            if (currentCall.isInProgress()) {
                const customHeaders = currentCall.getCustomHeaders();
                
                // Auto-atender chamadas da API (Click-to-Call)
                if (this.options.autoAnswer && 
                    customHeaders && 
                    customHeaders['X-Api4comintegratedcall'] === 'true') {
                    console.log('[API4Com WebPhone] Auto-atendendo chamada da API');
                    currentCall.answer();
                }
            }
            
            this.currentCall = currentCall;
            
            if (this.options.onCallStart) {
                this.options.onCallStart(currentCall);
            }
        });
        
        // Evento de chamada terminada
        this.webphone.on('call.terminated', (lwp, call) => {
            console.log('[API4Com WebPhone] Chamada terminada');
            this.currentCall = null;
            
            if (this.options.onCallEnd) {
                this.options.onCallEnd(call);
            }
        });
        
        // Evento de registro
        this.webphone.on('userAgent.registered', () => {
            console.log('[API4Com WebPhone] Registrado no servidor SIP');
            this.isRegistered = true;
            this.updateStatus('registered', 'Conectado');
        });
        
        // Evento de desregistro
        this.webphone.on('userAgent.unregistered', () => {
            console.log('[API4Com WebPhone] Desregistrado do servidor SIP');
            this.isRegistered = false;
            this.updateStatus('unregistered', 'Desconectado');
        });
        
        // Evento de erro de registro
        this.webphone.on('userAgent.registrationFailed', (lwp, error) => {
            console.error('[API4Com WebPhone] Falha no registro:', error);
            this.isRegistered = false;
            this.updateStatus('error', 'Falha no registro');
            this.handleError(error);
        });
        
        // Evento de conexão
        this.webphone.on('userAgent.connected', () => {
            console.log('[API4Com WebPhone] Conectado ao servidor');
            this.updateStatus('connected', 'Conectado ao servidor');
        });
        
        // Evento de desconexão
        this.webphone.on('userAgent.disconnected', () => {
            console.log('[API4Com WebPhone] Desconectado do servidor');
            this.updateStatus('disconnected', 'Desconectado');
        });
    }
    
    /**
     * Fazer chamada para um número
     */
    call(number) {
        if (!this.webphone || !this.isRegistered) {
            console.error('[API4Com WebPhone] WebPhone não está registrado');
            this.handleError(new Error('WebPhone não está registrado'));
            return false;
        }
        
        try {
            const dialpad = this.webphone.getDialpad();
            if (dialpad) {
                dialpad.dial(number);
                console.log('[API4Com WebPhone] Discando para:', number);
                return true;
            }
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao discar:', error);
            this.handleError(error);
        }
        
        return false;
    }
    
    /**
     * Desligar chamada atual
     */
    hangup() {
        if (this.currentCall) {
            this.currentCall.hangup();
            return true;
        }
        return false;
    }
    
    /**
     * Atender chamada
     */
    answer() {
        if (this.currentCall && this.currentCall.isInProgress()) {
            this.currentCall.answer();
            return true;
        }
        return false;
    }
    
    /**
     * Colocar chamada em espera
     */
    hold() {
        if (this.currentCall) {
            this.currentCall.hold();
            return true;
        }
        return false;
    }
    
    /**
     * Tirar chamada de espera
     */
    unhold() {
        if (this.currentCall) {
            this.currentCall.unhold();
            return true;
        }
        return false;
    }
    
    /**
     * Atualizar status visual
     */
    updateStatus(status, message) {
        console.log(`[API4Com WebPhone] Status: ${status} - ${message}`);
        
        if (this.options.onStatusChange) {
            this.options.onStatusChange(status, message);
        }
        
        // Atualizar indicador visual se existir
        const indicator = document.getElementById(this.options.renderTarget + '-status');
        if (indicator) {
            const statusColors = {
                'registered': 'success',
                'connected': 'info',
                'connecting': 'warning',
                'disconnected': 'secondary',
                'unregistered': 'secondary',
                'error': 'danger',
                'not_configured': 'secondary'
            };
            
            const color = statusColors[status] || 'secondary';
            indicator.className = `badge badge-${color}`;
            indicator.textContent = message;
        }
    }
    
    /**
     * Tratar erros
     */
    handleError(error) {
        console.error('[API4Com WebPhone] Erro:', error);
        
        if (this.options.onError) {
            this.options.onError(error);
        }
    }
    
    /**
     * Gerar ID de instância único
     */
    generateInstanceId() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    /**
     * Destruir webphone
     */
    destroy() {
        if (this.webphone) {
            try {
                const userAgent = this.webphone.getUserAgent();
                if (userAgent) {
                    userAgent.stop();
                }
            } catch (e) {
                console.warn('[API4Com WebPhone] Erro ao destruir:', e);
            }
            this.webphone = null;
        }
        this.isRegistered = false;
        this.currentCall = null;
    }
    
    /**
     * Verificar se está registrado
     */
    isReady() {
        return this.isRegistered;
    }
    
    /**
     * Obter status atual
     */
    getStatus() {
        if (!this.credentials) return 'not_configured';
        if (!this.libLoaded) return 'loading';
        if (this.isRegistered) return 'registered';
        return 'disconnected';
    }
}

// Exportar para uso global
window.Api4ComWebPhone = Api4ComWebPhone;
