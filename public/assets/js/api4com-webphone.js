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
            
            // Lista de CDNs para tentar
            const cdnUrls = [
                'https://sipjs.com/download/sip-0.16.0.min.js', // URL oficial
                '/assets/js/sip.min.js', // Local como fallback
            ];
            
            let currentIndex = 0;
            
            const tryLoadScript = () => {
                if (currentIndex >= cdnUrls.length) {
                    reject(new Error('Falha ao carregar SIP.js de todos os CDNs'));
                    return;
                }
                
                const script = document.createElement('script');
                script.src = cdnUrls[currentIndex];
                script.async = true;
                
                script.onload = () => {
                    setTimeout(() => {
                        if (window.SIP) {
                            this.libLoaded = true;
                            console.log('[API4Com WebPhone] SIP.js carregada com sucesso de:', cdnUrls[currentIndex]);
                            resolve();
                        } else {
                            console.warn('[API4Com WebPhone] SIP.js não encontrada após carregar de:', cdnUrls[currentIndex]);
                            currentIndex++;
                            tryLoadScript();
                        }
                    }, 200);
                };
                
                script.onerror = () => {
                    console.warn('[API4Com WebPhone] Falha ao carregar de:', cdnUrls[currentIndex]);
                    currentIndex++;
                    tryLoadScript();
                };
                
                document.head.appendChild(script);
            };
            
            tryLoadScript();
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
            
            console.log('[API4Com WebPhone] Conectando a:', `wss://${domain}:${port}`);
            this.updateStatus('connecting', 'Conectando...');
            
            // Configuração do SIP.js 0.16.x
            const configuration = {
                uri: `sip:${extension}@${domain}`,
                transportOptions: {
                    wsServers: [`wss://${domain}:${port}`],
                    traceSip: false
                },
                authorizationUser: extension,
                password: password,
                displayName: `Ramal ${extension}`,
                register: true,
                registerExpires: 600,
                sessionDescriptionHandlerFactoryOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    },
                    peerConnectionOptions: {
                        iceCheckingTimeout: 5000,
                        rtcConfiguration: {
                            iceServers: [
                                { urls: 'stun:stun.l.google.com:19302' }
                            ]
                        }
                    }
                },
                userAgentString: 'Chat-WebPhone-SIPjs',
                log: {
                    level: 'warn'
                }
            };
            
            this.userAgent = new SIP.UA(configuration);
            
            // Configurar eventos
            this.setupEvents();
            
            // Iniciar conexão
            this.userAgent.start();
            console.log('[API4Com WebPhone] User Agent iniciado');
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao inicializar User Agent:', error);
            this.updateStatus('error', 'Erro de conexão');
            this.handleError(error);
        }
    }
    
    /**
     * Configurar eventos do User Agent (SIP.js 0.16)
     */
    setupEvents() {
        if (!this.userAgent) return;
        
        // Evento de registro bem-sucedido
        this.userAgent.on('registered', () => {
            console.log('[API4Com WebPhone] Registrado no servidor SIP');
            this.isRegistered = true;
            this.updateStatus('registered', 'Conectado');
        });
        
        // Evento de desregistro
        this.userAgent.on('unregistered', () => {
            console.log('[API4Com WebPhone] Desregistrado do servidor SIP');
            this.isRegistered = false;
            this.updateStatus('unregistered', 'Desconectado');
        });
        
        // Evento de falha no registro
        this.userAgent.on('registrationFailed', (response, cause) => {
            console.error('[API4Com WebPhone] Falha no registro:', cause);
            this.isRegistered = false;
            this.updateStatus('error', 'Falha no registro');
        });
        
        // Evento de conexão WebSocket
        this.userAgent.on('connected', () => {
            console.log('[API4Com WebPhone] Conectado ao servidor WebSocket');
        });
        
        // Evento de desconexão WebSocket
        this.userAgent.on('disconnected', () => {
            console.log('[API4Com WebPhone] Desconectado do servidor WebSocket');
            this.isRegistered = false;
            this.updateStatus('disconnected', 'Desconectado');
        });
        
        // Evento de chamada recebida
        this.userAgent.on('invite', (session) => {
            this.handleIncomingCall(session);
        });
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
     * Tratar chamada recebida (SIP.js 0.16)
     */
    handleIncomingCall(session) {
        console.log('[API4Com WebPhone] Chamada recebida');
        
        this.currentSession = session;
        
        // Verificar se é chamada da API (Click-to-Call)
        let isApiCall = false;
        try {
            const customHeader = session.request.getHeader('X-Api4comintegratedcall');
            isApiCall = customHeader === 'true';
        } catch (e) {}
        
        if (this.options.autoAnswer && isApiCall) {
            console.log('[API4Com WebPhone] Auto-atendendo chamada da API');
            this.answer();
        } else {
            // Mostrar indicador de chamada recebida
            this.updateStatus('ringing', 'Chamada recebida');
            this.showIncomingCall();
        }
        
        // Configurar eventos da sessão
        this.setupSessionEvents(session);
        
        if (this.options.onCallStart) {
            this.options.onCallStart(session);
        }
    }
    
    /**
     * Configurar eventos de uma sessão de chamada (SIP.js 0.16)
     */
    setupSessionEvents(session) {
        session.on('progress', () => {
            console.log('[API4Com WebPhone] Chamada em progresso');
            this.updateStatus('ringing', 'Chamando...');
        });
        
        session.on('accepted', () => {
            console.log('[API4Com WebPhone] Chamada aceita');
            this.updateStatus('in_call', 'Em chamada');
            this.setupRemoteAudio(session);
        });
        
        session.on('trackAdded', () => {
            console.log('[API4Com WebPhone] Track de áudio adicionado');
            this.setupRemoteAudio(session);
        });
        
        session.on('terminated', (message, cause) => {
            console.log('[API4Com WebPhone] Chamada terminada:', cause);
            this.updateStatus('registered', 'Conectado');
            this.currentSession = null;
            this.hideIncomingCall();
            if (this.options.onCallEnd) {
                this.options.onCallEnd(session);
            }
        });
        
        session.on('failed', (response, cause) => {
            console.log('[API4Com WebPhone] Chamada falhou:', cause);
            this.updateStatus('registered', 'Conectado');
            this.currentSession = null;
            this.hideIncomingCall();
            if (this.options.onCallEnd) {
                this.options.onCallEnd(session);
            }
        });
        
        session.on('rejected', (response, cause) => {
            console.log('[API4Com WebPhone] Chamada rejeitada:', cause);
            this.updateStatus('registered', 'Conectado');
            this.currentSession = null;
            this.hideIncomingCall();
        });
    }
    
    /**
     * Configurar áudio remoto (SIP.js 0.16)
     */
    setupRemoteAudio(session) {
        try {
            const pc = session.sessionDescriptionHandler?.peerConnection;
            if (!pc) return;
            
            // Para SIP.js 0.16, usar getRemoteStreams ou getReceivers
            const remoteStreams = pc.getRemoteStreams ? pc.getRemoteStreams() : [];
            if (remoteStreams.length > 0) {
                this.audioElement.srcObject = remoteStreams[0];
                this.audioElement.play().catch(e => console.warn('Audio play error:', e));
            } else {
                // Fallback para getReceivers
                pc.getReceivers().forEach((receiver) => {
                    if (receiver.track && receiver.track.kind === 'audio') {
                        const remoteStream = new MediaStream([receiver.track]);
                        this.audioElement.srcObject = remoteStream;
                        this.audioElement.play().catch(e => console.warn('Audio play error:', e));
                    }
                });
            }
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao configurar áudio:', error);
        }
    }
    
    /**
     * Fazer chamada para um número (SIP.js 0.16)
     */
    call(number) {
        if (!this.userAgent || !this.isRegistered) {
            console.error('[API4Com WebPhone] Não registrado');
            return false;
        }
        
        try {
            const target = `sip:${number}@${this.credentials.domain}`;
            
            const options = {
                sessionDescriptionHandlerOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    }
                }
            };
            
            const session = this.userAgent.invite(target, options);
            
            this.currentSession = session;
            this.setupSessionEvents(session);
            
            console.log('[API4Com WebPhone] Chamada iniciada para:', number);
            this.updateStatus('calling', `Ligando para ${number}...`);
            
            if (this.options.onCallStart) {
                this.options.onCallStart(session);
            }
            
            return true;
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao fazer chamada:', error);
            this.handleError(error);
            return false;
        }
    }
    
    /**
     * Atender chamada recebida (SIP.js 0.16)
     */
    answer() {
        if (!this.currentSession) {
            console.warn('[API4Com WebPhone] Nenhuma chamada para atender');
            return false;
        }
        
        try {
            const options = {
                sessionDescriptionHandlerOptions: {
                    constraints: {
                        audio: true,
                        video: false
                    }
                }
            };
            
            this.currentSession.accept(options);
            console.log('[API4Com WebPhone] Chamada atendida');
            this.hideIncomingCall();
            return true;
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao atender:', error);
            return false;
        }
    }
    
    /**
     * Rejeitar chamada recebida (SIP.js 0.16)
     */
    reject() {
        if (!this.currentSession) {
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
     * Desligar chamada atual (SIP.js 0.16)
     */
    hangup() {
        if (!this.currentSession) {
            return false;
        }
        
        try {
            this.currentSession.terminate();
            this.currentSession = null;
            console.log('[API4Com WebPhone] Chamada encerrada');
            return true;
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao encerrar:', error);
            return false;
        }
    }
    
    /**
     * Enviar DTMF (SIP.js 0.16)
     */
    sendDTMF(digit) {
        if (!this.currentSession) {
            return false;
        }
        
        try {
            this.currentSession.dtmf(digit);
            console.log('[API4Com WebPhone] DTMF enviado:', digit);
            return true;
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao enviar DTMF:', error);
            return false;
        }
    }
    
    /**
     * Colocar/tirar do mudo (SIP.js 0.16)
     */
    toggleMute() {
        if (!this.currentSession) return false;
        
        try {
            if (this.isMuted()) {
                this.currentSession.unmute();
            } else {
                this.currentSession.mute();
            }
            return true;
        } catch (error) {
            // Fallback para manipulação direta do track
            const pc = this.currentSession.sessionDescriptionHandler?.peerConnection;
            if (!pc) return false;
            
            pc.getSenders().forEach((sender) => {
                if (sender.track && sender.track.kind === 'audio') {
                    sender.track.enabled = !sender.track.enabled;
                }
            });
            return true;
        }
    }
    
    /**
     * Verificar se está no mudo (SIP.js 0.16)
     */
    isMuted() {
        if (!this.currentSession) return false;
        
        try {
            return this.currentSession.isMuted().audio;
        } catch (error) {
            // Fallback
            const pc = this.currentSession.sessionDescriptionHandler?.peerConnection;
            if (!pc) return false;
            
            let muted = false;
            pc.getSenders().forEach((sender) => {
                if (sender.track && sender.track.kind === 'audio') {
                    muted = !sender.track.enabled;
                }
            });
            return muted;
        }
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
     * Desconectar (SIP.js 0.16)
     */
    disconnect() {
        try {
            if (this.currentSession) {
                this.hangup();
            }
            
            if (this.userAgent) {
                this.userAgent.unregister();
                this.userAgent.stop();
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
