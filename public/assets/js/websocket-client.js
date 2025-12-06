/**
 * Cliente WebSocket para tempo real
 */

class WebSocketClient {
    constructor() {
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
        this.userId = null;
        this.subscribedConversations = [];
        this.eventHandlers = {};
        this.isConnected = false;
    }

    /**
     * Conectar ao servidor WebSocket
     */
    connect(userId) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            return; // Já conectado
        }

        this.userId = userId;
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.hostname}:8080`;

        try {
            this.ws = new WebSocket(wsUrl);

            this.ws.onopen = () => {
                console.log('WebSocket conectado');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                
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
                this.emit('error', error);
            };

            this.ws.onclose = () => {
                console.log('WebSocket desconectado');
                this.isConnected = false;
                this.attemptReconnect();
            };
        } catch (error) {
            console.error('Erro ao conectar WebSocket:', error);
            this.attemptReconnect();
        }
    }

    /**
     * Tentar reconectar
     */
    attemptReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Máximo de tentativas de reconexão atingido');
            this.emit('reconnect_failed');
            return;
        }

        this.reconnectAttempts++;
        console.log(`Tentando reconectar (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);

        setTimeout(() => {
            if (this.userId) {
                this.connect(this.userId);
            }
        }, this.reconnectDelay);
    }

    /**
     * Autenticar usuário
     */
    authenticate(userId) {
        this.send({
            type: 'auth',
            user_id: userId
        });
    }

    /**
     * Inscrever em conversa
     */
    subscribe(conversationId) {
        if (!this.subscribedConversations.includes(conversationId)) {
            this.subscribedConversations.push(conversationId);
        }

        this.send({
            type: 'subscribe',
            conversation_id: conversationId
        });
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
        this.send({
            type: 'typing',
            conversation_id: conversationId,
            is_typing: isTyping
        });
    }

    /**
     * Enviar mensagem
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
        this.isConnected = false;
        this.subscribedConversations = [];
    }

    /**
     * Verificar se está conectado
     */
    get connected() {
        return this.isConnected && this.ws && this.ws.readyState === WebSocket.OPEN;
    }
}

// Criar instância global
window.wsClient = new WebSocketClient();

