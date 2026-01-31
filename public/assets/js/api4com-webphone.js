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
                            console.log('[API4Com WebPhone] SIP object keys:', Object.keys(window.SIP));
                            console.log('[API4Com WebPhone] SIP.UA:', typeof window.SIP.UA);
                            console.log('[API4Com WebPhone] SIP.Web:', typeof window.SIP.Web);
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
            
            // Detectar versão do SIP.js e usar construtor apropriado
            let UAConstructor = null;
            
            if (typeof SIP.UA === 'function') {
                // SIP.js 0.15.x e anteriores
                UAConstructor = SIP.UA;
                console.log('[API4Com WebPhone] Usando SIP.UA (versão antiga)');
            } else if (typeof SIP.Web !== 'undefined' && typeof SIP.Web.SimpleUser === 'function') {
                // SIP.js 0.17+ com SimpleUser
                return this.initSimpleUser(domain, port, extension, password);
            } else if (typeof SIP.UserAgent === 'function') {
                // SIP.js 0.16+ com UserAgent
                return this.initUserAgentNew(domain, port, extension, password);
            } else {
                throw new Error('Versão do SIP.js não suportada. Keys: ' + Object.keys(SIP).join(', '));
            }
            
            // Configuração do SIP.js 0.15.x e anteriores
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
            
            this.userAgent = new UAConstructor(configuration);
            
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
     * Inicializar usando SimpleUser (SIP.js 0.17+)
     */
    async initSimpleUser(domain, port, extension, password) {
        try {
            const server = `wss://${domain}:${port}`;
            const aor = `sip:${extension}@${domain}`;
            
            const options = {
                aor: aor,
                media: {
                    constraints: { audio: true, video: false },
                    remote: { audio: this.audioElement }
                },
                userAgentOptions: {
                    authorizationPassword: password,
                    authorizationUsername: extension,
                    displayName: `Ramal ${extension}`,
                }
            };
            
            this.simpleUser = new SIP.Web.SimpleUser(server, options);
            
            // Eventos
            this.simpleUser.delegate = {
                onCallReceived: () => {
                    console.log('[API4Com WebPhone] Chamada recebida');
                    this.updateStatus('ringing', 'Chamada recebida');
                    this.showIncomingCall();
                    if (this.options.onCallStart) {
                        this.options.onCallStart(this.simpleUser);
                    }
                },
                onCallAnswered: () => {
                    console.log('[API4Com WebPhone] Chamada atendida');
                    this.updateStatus('in_call', 'Em chamada');
                    this.hideIncomingCall();
                },
                onCallHangup: () => {
                    console.log('[API4Com WebPhone] Chamada encerrada');
                    this.updateStatus('registered', 'Conectado');
                    this.hideIncomingCall();
                    if (this.options.onCallEnd) {
                        this.options.onCallEnd(this.simpleUser);
                    }
                },
                onRegistered: () => {
                    console.log('[API4Com WebPhone] Registrado');
                    this.isRegistered = true;
                    this.updateStatus('registered', 'Conectado');
                },
                onUnregistered: () => {
                    console.log('[API4Com WebPhone] Desregistrado');
                    this.isRegistered = false;
                    this.updateStatus('unregistered', 'Desconectado');
                },
                onServerConnect: () => {
                    console.log('[API4Com WebPhone] Conectado ao servidor');
                },
                onServerDisconnect: () => {
                    console.log('[API4Com WebPhone] Desconectado do servidor');
                    this.isRegistered = false;
                    this.updateStatus('disconnected', 'Desconectado');
                }
            };
            
            await this.simpleUser.connect();
            await this.simpleUser.register();
            
            console.log('[API4Com WebPhone] SimpleUser inicializado');
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao inicializar SimpleUser:', error);
            this.updateStatus('error', 'Erro de conexão');
            this.handleError(error);
        }
    }
    
    /**
     * Inicializar usando UserAgent (SIP.js 0.16+)
     */
    async initUserAgentNew(domain, port, extension, password) {
        try {
            const uri = SIP.UserAgent.makeURI(`sip:${extension}@${domain}`);
            if (!uri) {
                throw new Error('URI SIP inválida');
            }
            
            const transportOptions = {
                server: `wss://${domain}:${port}`
            };
            
            const userAgentOptions = {
                uri: uri,
                transportOptions: transportOptions,
                authorizationUsername: extension,
                authorizationPassword: password,
                displayName: `Ramal ${extension}`,
                sessionDescriptionHandlerFactoryOptions: {
                    constraints: { audio: true, video: false }
                },
                delegate: {
                    onInvite: (invitation) => this.handleIncomingCall(invitation)
                }
            };
            
            this.userAgent = new SIP.UserAgent(userAgentOptions);
            
            // Iniciar
            await this.userAgent.start();
            
            // Registrar
            const registerer = new SIP.Registerer(this.userAgent);
            registerer.stateChange.addListener((state) => {
                console.log('[API4Com WebPhone] Estado registro:', state);
                if (state === SIP.RegistererState.Registered) {
                    this.isRegistered = true;
                    this.updateStatus('registered', 'Conectado');
                } else if (state === SIP.RegistererState.Unregistered) {
                    this.isRegistered = false;
                    this.updateStatus('unregistered', 'Desconectado');
                }
            });
            await registerer.register();
            
            console.log('[API4Com WebPhone] UserAgent (novo) inicializado');
            
        } catch (error) {
            console.error('[API4Com WebPhone] Erro ao inicializar UserAgent novo:', error);
            this.updateStatus('error', 'Erro de conexão');
            this.handleError(error);
        }
    }

    /**
     * Configurar eventos do User Agent (SIP.js 0.15 e anteriores)
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
     * Fazer chamada para um número
     */
    async call(number) {
        if (!this.isRegistered) {
            console.error('[API4Com WebPhone] Não registrado');
            return false;
        }
        
        try {
            // SimpleUser API
            if (this.simpleUser) {
                const target = `sip:${number}@${this.credentials.domain}`;
                await this.simpleUser.call(target);
                console.log('[API4Com WebPhone] Chamada iniciada para:', number);
                this.updateStatus('calling', `Ligando para ${number}...`);
                if (this.options.onCallStart) {
                    this.options.onCallStart(this.simpleUser);
                }
                return true;
            }
            
            // User Agent API (0.15 e anteriores)
            if (this.userAgent && typeof this.userAgent.invite === 'function') {
                const target = `sip:${number}@${this.credentials.domain}`;
                
                const options = {
                    sessionDescriptionHandlerOptions: {
                        constraints: { audio: true, video: false }
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
            }
            
            console.error('[API4Com WebPhone] Nenhum método de chamada disponível');
            return false;
            
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
        try {
            // SimpleUser API
            if (this.simpleUser) {
                await this.simpleUser.answer();
                console.log('[API4Com WebPhone] Chamada atendida');
                this.hideIncomingCall();
                return true;
            }
            
            // Session API
            if (!this.currentSession) {
                console.warn('[API4Com WebPhone] Nenhuma chamada para atender');
                return false;
            }
            
            const options = {
                sessionDescriptionHandlerOptions: {
                    constraints: { audio: true, video: false }
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
     * Rejeitar chamada recebida
     */
    reject() {
        try {
            // SimpleUser API
            if (this.simpleUser) {
                this.simpleUser.decline();
                this.hideIncomingCall();
                console.log('[API4Com WebPhone] Chamada rejeitada');
                return true;
            }
            
            // Session API
            if (!this.currentSession) {
                return false;
            }
            
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
        try {
            // SimpleUser API
            if (this.simpleUser) {
                this.simpleUser.hangup();
                console.log('[API4Com WebPhone] Chamada encerrada');
                return true;
            }
            
            // Session API
            if (!this.currentSession) {
                return false;
            }
            
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
     * Colocar/tirar do mudo
     */
    toggleMute() {
        try {
            // SimpleUser API
            if (this.simpleUser) {
                if (this.simpleUser.isHeld()) {
                    this.simpleUser.unhold();
                } else {
                    this.simpleUser.hold();
                }
                return true;
            }
            
            // Session API
            if (!this.currentSession) return false;
            
            if (this.isMuted()) {
                this.currentSession.unmute?.();
            } else {
                this.currentSession.mute?.();
            }
            return true;
        } catch (error) {
            // Fallback para manipulação direta do track
            if (!this.currentSession) return false;
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
     * Verificar se está no mudo
     */
    isMuted() {
        try {
            // SimpleUser API
            if (this.simpleUser) {
                return this.simpleUser.isHeld?.() || false;
            }
            
            // Session API
            if (!this.currentSession) return false;
            
            if (typeof this.currentSession.isMuted === 'function') {
                return this.currentSession.isMuted().audio;
            }
            
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
        } catch (error) {
            return false;
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
     * Desconectar
     */
    async disconnect() {
        try {
            if (this.currentSession) {
                this.hangup();
            }
            
            // SimpleUser API
            if (this.simpleUser) {
                await this.simpleUser.unregister();
                await this.simpleUser.disconnect();
                this.simpleUser = null;
            }
            
            // UserAgent API
            if (this.userAgent) {
                if (typeof this.userAgent.unregister === 'function') {
                    this.userAgent.unregister();
                }
                if (typeof this.userAgent.stop === 'function') {
                    this.userAgent.stop();
                }
                this.userAgent = null;
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
        return this.isRegistered && (this.userAgent !== null || this.simpleUser !== null);
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
