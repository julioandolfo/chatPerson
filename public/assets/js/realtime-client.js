/**
 * Cliente de Tempo Real (WebSocket + Polling)
 * Suporta WebSocket e Polling como fallback
 */

class RealtimeClient {
    constructor() {
        this.mode = 'auto'; // 'websocket', 'polling', 'auto'
        this.ws = null;
        this.pollingInterval = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        this.pollingDelay = 3000; // 3 segundos padrão
        this.userId = null;
        this.subscribedConversations = [];
        this.eventHandlers = {};
        this.isConnected = false;
        this.lastUpdateTime = {};
        this.config = {
            enabled: true,
            connectionType: 'auto', // 'auto', 'websocket', 'polling'
            websocketPort: 8080,
            websocketPath: '/ws',
            websocketCustomUrl: '',
            pollingInterval: 3000
        };
    }

    /**
     * Carregar configurações do servidor
     */
    async loadConfig() {
        try {
            // Usar URL relativa começando com / para que o navegador resolva corretamente
            // O navegador automaticamente resolve /api/realtime/config baseado no origin atual
            const configUrl = '/api/realtime/config';

            const response = await fetch(configUrl, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.config = { ...this.config, ...data.config };
                    this.mode = this.config.connectionType === 'auto' ? 'auto' : this.config.connectionType;
                    this.pollingDelay = this.config.pollingInterval || 3000;
                }
            }
        } catch (error) {
            console.warn('Não foi possível carregar configurações de tempo real:', error);
        }
    }

    /**
     * Conectar (WebSocket ou Polling)
     */
    async connect(userId) {
        if (!this.config.enabled) {
            console.log('Tempo real desabilitado nas configurações');
            return;
        }

        await this.loadConfig();
        
        this.userId = userId;
        
        if (this.mode === 'auto') {
            // Tentar WebSocket primeiro, se falhar usar Polling
            await this.connectWebSocket(userId);
            // Se WebSocket falhar, tentarPolling será chamado automaticamente
        } else if (this.mode === 'websocket') {
            await this.connectWebSocket(userId);
        } else if (this.mode === 'polling') {
            this.connectPolling(userId);
        }
    }

    /**
     * Conectar via WebSocket
     */
    async connectWebSocket(userId) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            return; // Já conectado
        }

        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const hostname = window.location.hostname;
        const port = window.location.port;
        
        let wsUrl;
        
        if (this.config.websocketCustomUrl) {
            wsUrl = this.config.websocketCustomUrl;
        } else {
            const isProduction = hostname !== 'localhost' && hostname !== '127.0.0.1';
            
            if (isProduction || protocol === 'wss:') {
                wsUrl = `${protocol}//${hostname}${port ? ':' + port : ''}${this.config.websocketPath}`;
            } else {
                wsUrl = `${protocol}//${hostname}:${this.config.websocketPort}`;
            }
        }

        try {
            this.ws = new WebSocket(wsUrl);
            this.setupWebSocketHandlers(userId, wsUrl, protocol, hostname);
        } catch (error) {
            console.error('Erro ao conectar WebSocket:', error);
            if (this.mode === 'auto') {
                console.log('Falha no WebSocket, usando Polling...');
                this.connectPolling(userId);
            } else {
                this.attemptReconnect();
            }
        }
    }

    /**
     * Conectar via Polling
     */
    connectPolling(userId) {
        // Parar WebSocket se estiver ativo
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }

        // Parar polling anterior se existir
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }

        this.userId = userId;
        this.isConnected = true;
        this.reconnectAttempts = 0;

        console.log('📡 Modo Polling ativado (verificando a cada ' + this.pollingDelay + 'ms)');

        // Iniciar polling
        this.startPolling();

        this.emit('connected');
    }

    /**
     * Iniciar polling
     */
    startPolling() {
        this.pollingInterval = setInterval(() => {
            this.pollForUpdates();
        }, this.pollingDelay);
    }

    /**
     * Parar polling
     */
    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
    }

    /**
     * Verificar atualizações via polling
     */
    async pollForUpdates() {
        if (!this.userId) return;

        try {
            // Usar URL relativa começando com / para que o navegador resolva corretamente
            // O navegador automaticamente resolve /api/realtime/poll baseado no origin atual
            const pollUrl = '/api/realtime/poll';

            const response = await fetch(pollUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    user_id: this.userId,
                    subscribed_conversations: this.subscribedConversations,
                    last_update_time: this.lastUpdateTime
                })
            });

            // Verificar Content-Type antes de tentar parsear JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Resposta não é JSON. Content-Type:', contentType);
                console.error('Resposta recebida:', text.substring(0, 500));
                throw new Error('Resposta do servidor não é JSON. Verifique o console para detalhes.');
            }

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ message: 'Erro desconhecido' }));
                throw new Error(errorData.message || 'Erro na requisição de polling');
            }

            const data = await response.json();
            
            if (data.success && data.updates) {
                this.processPollingUpdates(data.updates);
                this.lastUpdateTime = data.timestamp || Date.now();
            }
        } catch (error) {
            console.error('Erro no polling:', error);
            // Não interromper o polling, apenas logar o erro
        }
    }

    /**
     * Processar atualizações recebidas via polling
     */
    processPollingUpdates(updates) {
        if (updates.new_messages) {
            updates.new_messages.forEach(msg => {
                this.emit('new_message', {
                    conversation_id: msg.conversation_id,
                    message: msg
                });
            });
        }

        if (updates.new_conversations) {
            updates.new_conversations.forEach(conv => {
                this.emit('new_conversation', {
                    conversation: conv
                });
            });
        }

        if (updates.conversation_updates) {
            updates.conversation_updates.forEach(conv => {
                this.emit('conversation_updated', {
                    conversation_id: conv.id,
                    conversation: conv
                });
            });
        }

        if (updates.agent_status) {
            updates.agent_status.forEach(status => {
                this.emit('agent_status', status);
            });
        }
    }

    /**
     * Configurar handlers do WebSocket
     */
    setupWebSocketHandlers(userId, wsUrl, protocol, hostname) {
        if (!this.ws) return;
        
        this.ws.onopen = () => {
            console.log('WebSocket conectado em:', wsUrl);
            this.isConnected = true;
            this.reconnectAttempts = 0;
            
            // Parar polling se estiver ativo
            this.stopPolling();
            
            // Autenticar usuário
            this.authenticate(userId);
            
            // Reinscrever em conversas
            this.subscribedConversations.forEach(convId => {
                this.subscribe(convId);
            });

            this.emit('connected');
        };

        this.ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleMessage(data);
            } catch (e) {
                console.error('Erro ao processar mensagem WebSocket:', e);
            }
        };
        
        this.ws.onerror = (error) => {
            console.error('Erro WebSocket:', error);
            console.error('URL tentada:', wsUrl);
            this.emit('error', error);
            
            // Se falhou com /ws e estamos em auto, tentar polling
            if (this.mode === 'auto' && wsUrl && wsUrl.includes('/ws') && this.reconnectAttempts === 0) {
                console.log('Falha no WebSocket, alternando para Polling...');
                this.connectPolling(userId);
                return;
            }
            
            // Tentar porta direta como fallback
            if (wsUrl && wsUrl.includes('/ws') && this.reconnectAttempts === 0) {
                console.log('Tentando fallback para porta direta 8080...');
                setTimeout(() => {
                    const fallbackUrl = `${protocol}//${hostname}:${this.config.websocketPort}`;
                    console.log('Tentando conectar em:', fallbackUrl);
                    if (this.ws) {
                        this.ws.close();
                    }
                    this.ws = new WebSocket(fallbackUrl);
                    this.setupWebSocketHandlers(userId, fallbackUrl, protocol, hostname);
                }, 1000);
                return;
            }
        };

        this.ws.onclose = (event) => {
            console.log('WebSocket desconectado', event.code, event.reason);
            this.isConnected = false;
            
            // Se estava em modo auto e desconectou, tentar polling
            if (this.mode === 'auto' && event.code !== 1000) {
                console.log('WebSocket desconectado, alternando para Polling...');
                this.connectPolling(userId);
                return;
            }
            
            // Não tentar reconectar se foi fechado intencionalmente (código 1000)
            if (event.code !== 1000 && this.mode === 'websocket') {
                this.attemptReconnect();
            }
        };
    }

    /**
     * Tentar reconectar (apenas WebSocket)
     */
    attemptReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Máximo de tentativas de reconexão atingido');
            if (this.mode === 'auto') {
                console.log('Alternando para Polling...');
                this.connectPolling(this.userId);
            } else {
                this.emit('reconnect_failed');
            }
            return;
        }

        this.reconnectAttempts++;
        console.log(`Tentando reconectar (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);

        setTimeout(() => {
            if (this.userId && this.mode !== 'polling') {
                this.connectWebSocket(this.userId);
            }
        }, this.reconnectDelay);
    }

    /**
     * Autenticar usuário
     */
    authenticate(userId) {
        if (this.mode === 'websocket' || (this.mode === 'auto' && this.ws && this.ws.readyState === WebSocket.OPEN)) {
            this.send({
                type: 'auth',
                user_id: userId
            });
        }
    }

    /**
     * Inscrever em conversa
     */
    subscribe(conversationId) {
        if (!this.subscribedConversations.includes(conversationId)) {
            this.subscribedConversations.push(conversationId);
        }

        if (this.mode === 'websocket' || (this.mode === 'auto' && this.ws && this.ws.readyState === WebSocket.OPEN)) {
            this.send({
                type: 'subscribe',
                conversation_id: conversationId
            });
        }
    }

    /**
     * Cancelar inscrição em conversa
     */
    unsubscribe(conversationId) {
        this.subscribedConversations = this.subscribedConversations.filter(
            id => id !== conversationId
        );
    }

    /**
     * Enviar indicador de digitação
     */
    sendTyping(conversationId, isTyping = true) {
        if (this.mode === 'websocket' || (this.mode === 'auto' && this.ws && this.ws.readyState === WebSocket.OPEN)) {
            this.send({
                type: 'typing',
                conversation_id: conversationId,
                is_typing: isTyping
            });
        }
    }

    /**
     * Enviar mensagem (WebSocket)
     */
    send(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        } else {
            console.warn('WebSocket não está conectado');
        }
    }

    /**
     * Processar mensagem recebida
     */
    handleMessage(data) {
        if (data.type === 'pong') {
            // Heartbeat response
            return;
        }

        if (data.event) {
            this.emit(data.event, data.data);
        }
    }

    /**
     * Registrar handler de evento
     */
    on(event, handler) {
        if (!this.eventHandlers[event]) {
            this.eventHandlers[event] = [];
        }
        this.eventHandlers[event].push(handler);
    }

    /**
     * Remover handler de evento
     */
    off(event, handler) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event] = this.eventHandlers[event].filter(
                h => h !== handler
            );
        }
    }

    /**
     * Emitir evento
     */
    emit(event, data = null) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event].forEach(handler => {
                try {
                    handler(data);
                } catch (e) {
                    console.error(`Erro no handler do evento ${event}:`, e);
                }
            });
        }
    }

    /**
     * Desconectar
     */
    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.stopPolling();
        this.isConnected = false;
        this.subscribedConversations = [];
    }

    /**
     * Verificar se está conectado
     */
    get connected() {
        if (this.mode === 'polling') {
            return this.isConnected && this.pollingInterval !== null;
        }
        return this.isConnected && this.ws && this.ws.readyState === WebSocket.OPEN;
    }

    /**
     * Obter modo atual
     */
    get currentMode() {
        if (this.mode === 'auto') {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                return 'websocket';
            } else if (this.pollingInterval) {
                return 'polling';
            }
        }
        return this.mode;
    }
}

// Criar instância global (compatível com código existente)
window.wsClient = new RealtimeClient();
window.realtimeClient = window.wsClient; // Alias para clareza

