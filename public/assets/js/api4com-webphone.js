/**
 * API4Com WebPhone Integration
 * Componente de softphone WebRTC integrado usando SIP.js
 */

class Api4ComWebPhone {
    constructor(options = {}) {
        this.options = {
            renderTarget: options.renderTarget || 'api4com-webphone',
            autoAnswer: options.autoAnswer !== false,
            onStatusChange: options.onStatusChange || null,
            onCallStart: options.onCallStart || null,
            onCallEnd: options.onCallEnd || null,
            onError: options.onError || null,
            ...options
        };
        
        this.userAgent = null;
        this.registerer = null;
        this.credentials = null;
        this.isRegistered = false;
        this.currentSession = null;
        this.audioElement = null;
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
            
            // Carregar biblioteca SIP.js
            await this.loadLibrary();
            
            // Inicializar SIP User Agent
            await this.initUserAgent();
            
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
     * Carregar biblioteca SIP.js dinamicamente
     */
    loadLibrary() {
        return new Promise((resolve, reject) => {
            // Verificar se já está carregada
            if (window.SIP) {
                this.libLoaded = true;
                console.log('[API4Com WebPhone] SIP.js já carregada');
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            // Usar CDN do SIP.js versão estável
            script.src = 'https://unpkg.com/sip.js@0.21.2/lib/bundle.min.js';
            script.async = true;
            
            script.onload = () => {
                setTimeout(() => {
                    if (window.SIP) {
                        this.libLoaded = true;
                        console.log('[API4Com WebPhone] SIP.js carregada com sucesso');
                        resolve();
                    } else {
                        reject(new Error('SIP.js não encontrada após carregamento'));
                    }
                }, 100);
            };
            
            script.onerror = (error) => {
                console.error('[API4Com WebPhone] Erro ao carregar SIP.js:', error);
                reject(new Error('Falha ao carregar SIP.js'));
            };
            
            document.head.appendChild(script);
        });
    }
    
    /**
     * Inicializar SIP User Agent
     */
    async initUserAgent() {
        if (!this.libLoaded || !this.credentials) {
            console.warn('[API4Com WebPhone] Biblioteca ou credenciais não disponíveis');
            this.updateStatus('error', 'Biblioteca não carregada');
            return;
        }
        
        const { domain, port, extension, password, realm } = this.credentials;
        
        try {
            // Criar elemento de áudio para chamadas
            this.createAudioElement();
            
            const uri = SIP.UserAgent.makeURI(`sip:${extension}@${domain}`);
            if (!uri) {
                throw new Error('URI SIP inválida');
            }
            
            const transportOptions = {
                server: `wss://${domain}:${port}`,
                traceSip: false,
                connectionTimeout: 10,
            };
            
            const userAgentOptions = {
                uri: uri,
                transportOptions: transportOptions,
                authorizationUsername: extension,
                authorizationPassword: password,
                displayName: `Ramal ${extension}`,
                sessionDescriptionHandlerFactoryOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    },
                    peerConnectionConfiguration: {
                        iceServers: [
                            { urls: 'stun:stun.l.google.com:19302' }
                        ]
                    }
                },
                delegate: {
                    onInvite: (invitation) => this.handleIncomingCall(invitation)
                },
                logLevel: 'warn'
            };
            
            console.log('[API4Com WebPhone] Conectando a:', `wss://${domain}:${port}`);
            this.updateStatus('connecting', 'Conectando...');
            
            this.userAgent = new SIP.UserAgent(userAgentOptions);
            
            // Iniciar o User Agent
            await this.userAgent.start();
            console.log('[API4Com WebPhone] User Agent iniciado');
            
            // Registrar no servidor SIP
            await this.register();
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao inicializar User Agent:', error);
            this.updateStatus('error', 'Erro de conexão');
            this.handleError(error);
        }
    }
    
    /**
     * Registrar no servidor SIP
     */
    async register() {
        if (!this.userAgent) return;
        
        try {
            const registerOptions = {
                expires: 600,
                extraContactHeaderParams: ['transport=wss']
            };
            
            this.registerer = new SIP.Registerer(this.userAgent, registerOptions);
            
            // Eventos do registrador
            this.registerer.stateChange.addListener((state) => {
                console.log('[API4Com WebPhone] Estado do registro:', state);
                
                switch (state) {
                    case SIP.RegistererState.Registered:
                        this.isRegistered = true;
                        this.updateStatus('registered', 'Conectado');
                        break;
                    case SIP.RegistererState.Unregistered:
                        this.isRegistered = false;
                        this.updateStatus('unregistered', 'Desconectado');
                        break;
                    case SIP.RegistererState.Terminated:
                        this.isRegistered = false;
                        this.updateStatus('terminated', 'Encerrado');
                        break;
                }
            });
            
            await this.registerer.register();
            console.log('[API4Com WebPhone] Registro enviado');
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao registrar:', error);
            this.updateStatus('error', 'Falha no registro');
            this.handleError(error);
        }
    }
    
    /**
     * Criar elemento de áudio para reproduzir chamadas
     */
    createAudioElement() {
        if (this.audioElement) return;
        
        this.audioElement = document.createElement('audio');
        this.audioElement.id = 'api4com-webphone-audio';
        this.audioElement.autoplay = true;
        document.body.appendChild(this.audioElement);
    }
    
    /**
     * Tratar chamada recebida
     */
    handleIncomingCall(invitation) {
        console.log('[API4Com WebPhone] Chamada recebida');
        
        this.currentSession = invitation;
        
        // Verificar se é chamada da API (Click-to-Call)
        const customHeaders = invitation.request.getHeaders('X-Api4comintegratedcall');
        const isApiCall = customHeaders && customHeaders.length > 0 && customHeaders[0] === 'true';
        
        if (this.options.autoAnswer && isApiCall) {
            console.log('[API4Com WebPhone] Auto-atendendo chamada da API');
            this.answer();
        } else {
            // Mostrar indicador de chamada recebida
            this.updateStatus('ringing', 'Chamada recebida');
            this.showIncomingCall();
        }
        
        // Configurar eventos da sessão
        this.setupSessionEvents(invitation);
        
        if (this.options.onCallStart) {
            this.options.onCallStart(invitation);
        }
    }
    
    /**
     * Configurar eventos de uma sessão de chamada
     */
    setupSessionEvents(session) {
        session.stateChange.addListener((state) => {
            console.log('[API4Com WebPhone] Estado da chamada:', state);
            
            switch (state) {
                case SIP.SessionState.Establishing:
                    this.updateStatus('establishing', 'Conectando chamada...');
                    break;
                case SIP.SessionState.Established:
                    this.updateStatus('in_call', 'Em chamada');
                    this.setupRemoteAudio(session);
                    break;
                case SIP.SessionState.Terminated:
                    this.updateStatus('registered', 'Conectado');
                    this.currentSession = null;
                    this.hideIncomingCall();
                    if (this.options.onCallEnd) {
                        this.options.onCallEnd(session);
                    }
                    break;
            }
        });
    }
    
    /**
     * Configurar áudio remoto
     */
    setupRemoteAudio(session) {
        const sessionDescriptionHandler = session.sessionDescriptionHandler;
        if (!sessionDescriptionHandler) return;
        
        const peerConnection = sessionDescriptionHandler.peerConnection;
        if (!peerConnection) return;
        
        peerConnection.getReceivers().forEach((receiver) => {
            if (receiver.track && receiver.track.kind === 'audio') {
                const remoteStream = new MediaStream([receiver.track]);
                this.audioElement.srcObject = remoteStream;
            }
        });
    }
    
    /**
     * Fazer chamada para um número
     */
    async call(number) {
        if (!this.userAgent || !this.isRegistered) {
            console.error('[API4Com WebPhone] Não registrado');
            return false;
        }
        
        try {
            const target = SIP.UserAgent.makeURI(`sip:${number}@${this.credentials.domain}`);
            if (!target) {
                throw new Error('Número inválido');
            }
            
            const inviter = new SIP.Inviter(this.userAgent, target, {
                sessionDescriptionHandlerOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    }
                }
            });
            
            this.currentSession = inviter;
            this.setupSessionEvents(inviter);
            
            await inviter.invite();
            console.log('[API4Com WebPhone] Chamada iniciada para:', number);
            
            this.updateStatus('calling', `Ligando para ${number}...`);
            
            if (this.options.onCallStart) {
                this.options.onCallStart(inviter);
            }
            
            return true;
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao fazer chamada:', error);
            this.handleError(error);
            return false;
        }
    }
    
    /**
     * Atender chamada recebida
     */
    async answer() {
        if (!this.currentSession || !(this.currentSession instanceof SIP.Invitation)) {
            console.warn('[API4Com WebPhone] Nenhuma chamada para atender');
            return false;
        }
        
        try {
            await this.currentSession.accept({
                sessionDescriptionHandlerOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    }
                }
            });
            console.log('[API4Com WebPhone] Chamada atendida');
            this.hideIncomingCall();
            return true;
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao atender:', error);
            return false;
        }
    }
    
    /**
     * Rejeitar chamada recebida
     */
    reject() {
        if (!this.currentSession || !(this.currentSession instanceof SIP.Invitation)) {
            return false;
        }
        
        try {
            this.currentSession.reject();
            this.currentSession = null;
            this.hideIncomingCall();
            console.log('[API4Com WebPhone] Chamada rejeitada');
            return true;
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao rejeitar:', error);
            return false;
        }
    }
    
    /**
     * Desligar chamada atual
     */
    hangup() {
        if (!this.currentSession) {
            return false;
        }
        
        try {
            if (this.currentSession.state === SIP.SessionState.Established) {
                this.currentSession.bye();
            } else if (this.currentSession instanceof SIP.Invitation) {
                this.currentSession.reject();
            } else if (this.currentSession instanceof SIP.Inviter) {
                this.currentSession.cancel();
            }
            
            this.currentSession = null;
            console.log('[API4Com WebPhone] Chamada encerrada');
            return true;
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao encerrar:', error);
            return false;
        }
    }
    
    /**
     * Enviar DTMF
     */
    sendDTMF(digit) {
        if (!this.currentSession || this.currentSession.state !== SIP.SessionState.Established) {
            return false;
        }
        
        try {
            const options = {
                requestOptions: {
                    body: {
                        contentType: 'application/dtmf-relay',
                        content: `Signal=${digit}\r\nDuration=100`
                    }
                }
            };
            this.currentSession.info(options);
            console.log('[API4Com WebPhone] DTMF enviado:', digit);
            return true;
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao enviar DTMF:', error);
            return false;
        }
    }
    
    /**
     * Colocar/tirar do mudo
     */
    toggleMute() {
        if (!this.currentSession) return false;
        
        const sessionDescriptionHandler = this.currentSession.sessionDescriptionHandler;
        if (!sessionDescriptionHandler) return false;
        
        const peerConnection = sessionDescriptionHandler.peerConnection;
        if (!peerConnection) return false;
        
        peerConnection.getSenders().forEach((sender) => {
            if (sender.track && sender.track.kind === 'audio') {
                sender.track.enabled = !sender.track.enabled;
            }
        });
        
        return true;
    }
    
    /**
     * Verificar se está no mudo
     */
    isMuted() {
        if (!this.currentSession) return false;
        
        const sessionDescriptionHandler = this.currentSession.sessionDescriptionHandler;
        if (!sessionDescriptionHandler) return false;
        
        const peerConnection = sessionDescriptionHandler.peerConnection;
        if (!peerConnection) return false;
        
        let muted = false;
        peerConnection.getSenders().forEach((sender) => {
            if (sender.track && sender.track.kind === 'audio') {
                muted = !sender.track.enabled;
            }
        });
        
        return muted;
    }
    
    /**
     * Atualizar status visual
     */
    updateStatus(status, message) {
        console.log(`[API4Com WebPhone] Status: ${status} - ${message}`);
        
        // Atualizar elementos visuais
        const statusEl = document.getElementById('webphone-status');
        const indicatorEl = document.getElementById('webphone-status-indicator');
        const textEl = document.getElementById('webphone-status-text');
        
        if (statusEl) {
            statusEl.className = `webphone-status ${status}`;
        }
        
        if (indicatorEl) {
            // Cores baseadas no status
            const colors = {
                'not_configured': '#6c757d',
                'connecting': '#ffc107',
                'connected': '#17a2b8',
                'registered': '#28a745',
                'unregistered': '#dc3545',
                'error': '#dc3545',
                'ringing': '#ffc107',
                'in_call': '#28a745',
                'calling': '#17a2b8',
                'establishing': '#17a2b8',
                'terminated': '#6c757d'
            };
            indicatorEl.style.backgroundColor = colors[status] || '#6c757d';
        }
        
        if (textEl) {
            textEl.textContent = message;
        }
        
        if (this.options.onStatusChange) {
            this.options.onStatusChange(status, message);
        }
    }
    
    /**
     * Mostrar indicador de chamada recebida
     */
    showIncomingCall() {
        const indicator = document.getElementById('webphone-incoming-call');
        if (indicator) {
            indicator.style.display = 'block';
        }
        
        // Tocar som de chamada
        this.playRingtone();
    }
    
    /**
     * Esconder indicador de chamada recebida
     */
    hideIncomingCall() {
        const indicator = document.getElementById('webphone-incoming-call');
        if (indicator) {
            indicator.style.display = 'none';
        }
        
        this.stopRingtone();
    }
    
    /**
     * Tocar toque de chamada
     */
    playRingtone() {
        // Implementar toque de chamada se necessário
    }
    
    /**
     * Parar toque de chamada
     */
    stopRingtone() {
        // Implementar parada do toque se necessário
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
     * Desconectar
     */
    async disconnect() {
        try {
            if (this.currentSession) {
                this.hangup();
            }
            
            if (this.registerer) {
                await this.registerer.unregister();
            }
            
            if (this.userAgent) {
                await this.userAgent.stop();
            }
            
            this.isRegistered = false;
            this.updateStatus('disconnected', 'Desconectado');
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao desconectar:', error);
        }
    }
    
    /**
     * Reconectar
     */
    async reconnect() {
        await this.disconnect();
        await this.init();
    }
    
    /**
     * Verificar se está pronto para fazer chamadas
     */
    isReady() {
        return this.isRegistered && this.userAgent !== null;
    }
    
    /**
     * Obter estado atual
     */
    getState() {
        return {
            isRegistered: this.isRegistered,
            hasSession: this.currentSession !== null,
            credentials: this.credentials ? {
                domain: this.credentials.domain,
                extension: this.credentials.extension
            } : null
        };
    }
}

// Expor globalmente
window.Api4ComWebPhone = Api4ComWebPhone;
