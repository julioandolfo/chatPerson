/**
 * ActivityTracker
 * Rastreia atividade do usuário para disponibilidade dinâmica
 */

class ActivityTracker {
    constructor() {
        this.lastActivityTime = Date.now();
        this.heartbeatInterval = null;
        this.activityThrottle = 5000; // Enviar atividade no máximo a cada 5 segundos
        this.lastSentActivity = 0;
        this.settings = {
            enabled: true,
            trackMouse: false,
            trackKeyboard: true,
            trackPageVisibility: true,
            heartbeatInterval: 30000 // 30 segundos padrão
        };
        this.isPageVisible = true;
        this.init();
    }

    /**
     * Inicializar rastreador
     */
    async init() {
        // Carregar configurações
        await this.loadSettings();

        if (!this.settings.enabled) {
            return;
        }

        // Configurar eventos
        this.setupEventListeners();
        
        // Iniciar heartbeat
        this.startHeartbeat();
    }

    /**
     * Carregar configurações do servidor
     */
    async loadSettings() {
        try {
            // Tentar obter do window se disponível (configurado no PHP)
            if (window.availabilitySettings) {
                this.settings = {
                    ...this.settings,
                    ...window.availabilitySettings
                };
                return;
            }

            // Se não estiver disponível, usar padrões
            console.log('ActivityTracker: Usando configurações padrão');
        } catch (e) {
            console.error('ActivityTracker: Erro ao carregar configurações', e);
        }
    }

    /**
     * Configurar listeners de eventos
     */
    setupEventListeners() {
        // Mouse movement (se habilitado)
        if (this.settings.trackMouse) {
            document.addEventListener('mousemove', () => this.recordActivity('mousemove'), { passive: true });
        }

        // Keyboard (se habilitado)
        if (this.settings.trackKeyboard) {
            document.addEventListener('keydown', () => this.recordActivity('keydown'), { passive: true });
        }

        // Click
        document.addEventListener('click', () => this.recordActivity('click'), { passive: true });

        // Scroll
        document.addEventListener('scroll', () => this.recordActivity('scroll'), { passive: true });

        // Page visibility (se habilitado)
        if (this.settings.trackPageVisibility) {
            document.addEventListener('visibilitychange', () => this.handleVisibilityChange());
        }

        // Before unload (para marcar como offline ao fechar)
        window.addEventListener('beforeunload', () => this.handleBeforeUnload());
    }

    /**
     * Registrar atividade
     */
    recordActivity(type = 'activity') {
        const now = Date.now();
        this.lastActivityTime = now;

        // Throttle: só enviar se passou tempo suficiente desde última vez
        if (now - this.lastSentActivity < this.activityThrottle) {
            return;
        }

        this.lastSentActivity = now;
        this.sendActivity(type);
    }

    /**
     * Enviar atividade para servidor
     */
    sendActivity(type = 'activity') {
        // Enviar via WebSocket se disponível
        if (window.wsClient && window.wsClient.isConnected) {
            try {
                window.wsClient.send({
                    type: 'activity',
                    activity_type: type,
                    timestamp: Date.now()
                });
            } catch (e) {
                console.error('ActivityTracker: Erro ao enviar via WebSocket', e);
            }
        }
        // Se não tiver WebSocket, será enviado no próximo polling
    }

    /**
     * Lidar com mudança de visibilidade da página
     */
    handleVisibilityChange() {
        this.isPageVisible = !document.hidden;

        if (!this.isPageVisible) {
            // Página ficou oculta - pode considerar como away depois de um tempo
            // Mas não mudamos status imediatamente
        } else {
            // Página ficou visível - registrar atividade
            this.recordActivity('visibility_change');
        }
    }

    /**
     * Lidar com antes de fechar página
     */
    handleBeforeUnload() {
        // Tentar marcar como offline (mas pode não funcionar se página fechar muito rápido)
        if (window.wsClient && window.wsClient.isConnected) {
            try {
                window.wsClient.send({
                    type: 'activity',
                    activity_type: 'page_unload',
                    timestamp: Date.now()
                });
            } catch (e) {
                // Ignorar erros ao fechar
            }
        }
    }

    /**
     * Iniciar heartbeat periódico
     */
    startHeartbeat() {
        const interval = this.settings.heartbeatInterval || 30000;

        this.heartbeatInterval = setInterval(() => {
            this.sendHeartbeat();
        }, interval);
    }

    /**
     * Parar heartbeat
     */
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }

    /**
     * Enviar heartbeat
     */
    sendHeartbeat() {
        // Enviar via WebSocket se disponível
        if (window.wsClient && window.wsClient.isConnected) {
            try {
                window.wsClient.send({
                    type: 'ping',
                    last_activity: this.lastActivityTime
                });
            } catch (e) {
                console.error('ActivityTracker: Erro ao enviar heartbeat via WebSocket', e);
            }
        }
        // Se não tiver WebSocket, será enviado no próximo polling
    }

    /**
     * Obter última atividade (para polling)
     */
    getLastActivity() {
        return this.lastActivityTime;
    }

    /**
     * Destruir rastreador
     */
    destroy() {
        this.stopHeartbeat();
        // Remover event listeners seria ideal, mas não é crítico
    }
}

// Inicializar quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.activityTracker = new ActivityTracker();
    });
} else {
    window.activityTracker = new ActivityTracker();
}

