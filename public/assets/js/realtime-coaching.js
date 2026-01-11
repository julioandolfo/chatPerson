/**
 * Sistema de Coaching em Tempo Real
 * 
 * Gerencia dicas de IA durante conversas ativas.
 * Usa WebSocket (primário) + Polling (fallback).
 */

// Proteger contra redeclaração
if (typeof RealtimeCoaching !== 'undefined') {
    console.warn('[Coaching] RealtimeCoaching já está definido, pulando redeclaração');
} else {

class RealtimeCoaching {
    constructor() {
        this.currentConversationId = null;
        this.currentAgentId = null;
        this.pollingInterval = null;
        this.pollingFrequency = 5000; // 5 segundos
        this.displayedHints = new Set(); // IDs de hints já exibidos
        this.settings = {
            enabled: false,
            auto_show_hint: true,
            hint_display_duration: 30,
            play_sound: false
        };
        
        this.init();
    }
    
    /**
     * Inicializar sistema
     */
    init() {
        console.log('[Coaching] Inicializando sistema de coaching em tempo real');
        
        // Carregar configurações
        this.loadSettings();
        
        // Configurar WebSocket listener
        this.setupWebSocketListener();
        
        // Iniciar polling (fallback)
        this.startPolling();
        
        // Listener para mudança de conversa
        document.addEventListener('conversationChanged', (e) => {
            this.onConversationChanged(e.detail.conversationId);
        });
    }
    
    /**
     * Carregar configurações
     */
    async loadSettings() {
        try {
            // Em produção, carregar do backend
            // Por enquanto, usar padrões
            this.settings = {
                enabled: true, // Será controlado pelas configs do sistema
                auto_show_hint: true,
                hint_display_duration: 30,
                play_sound: false
            };
        } catch (error) {
            console.error('[Coaching] Erro ao carregar configurações:', error);
        }
    }
    
    /**
     * Configurar listener do WebSocket
     */
    setupWebSocketListener() {
        if (window.websocketClient) {
            window.websocketClient.on('coaching_hint', (data) => {
                console.log('[Coaching] Hint recebido via WebSocket:', data);
                this.handleNewHint(data);
            });
            console.log('[Coaching] WebSocket listener configurado');
        } else {
            console.warn('[Coaching] WebSocket não disponível, usando apenas polling');
        }
    }
    
    /**
     * Iniciar polling
     */
    startPolling() {
        // Limpar polling anterior
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
        
        // Iniciar novo polling
        this.pollingInterval = setInterval(() => {
            this.pollPendingHints();
        }, this.pollingFrequency);
        
        console.log('[Coaching] Polling iniciado (a cada ' + (this.pollingFrequency / 1000) + 's)');
    }
    
    /**
     * Polling de hints pendentes
     */
    async pollPendingHints() {
        if (!this.currentConversationId) {
            return;
        }
        
        try {
            const response = await fetch(`/coaching/pending-hints?conversation_id=${this.currentConversationId}&seconds=10`);
            const data = await response.json();
            
            if (data.success && data.hints && data.hints.length > 0) {
                console.log('[Coaching] Polling encontrou ' + data.hints.length + ' hint(s)');
                
                // Processar cada hint
                data.hints.forEach(hint => {
                    if (!this.displayedHints.has(hint.id)) {
                        this.handleNewHint(hint);
                    }
                });
            }
        } catch (error) {
            console.error('[Coaching] Erro no polling:', error);
        }
    }
    
    /**
     * Mudança de conversa
     */
    onConversationChanged(conversationId) {
        console.log('[Coaching] Conversa mudou para:', conversationId);
        this.currentConversationId = conversationId;
        this.displayedHints.clear(); // Limpar hints exibidos
        
        // Buscar hints imediatamente
        this.pollPendingHints();
    }
    
    /**
     * Processar novo hint
     */
    handleNewHint(hint) {
        // Verificar se já foi exibido
        if (this.displayedHints.has(hint.id)) {
            return;
        }
        
        // Marcar como exibido
        this.displayedHints.add(hint.id);
        
        // Exibir hint
        if (this.settings.auto_show_hint) {
            this.showHint(hint);
        }
        
        // Tocar som (se habilitado)
        if (this.settings.play_sound) {
            this.playNotificationSound();
        }
    }
    
    /**
     * Exibir hint na tela
     */
    showHint(hint) {
        console.log('[Coaching] Exibindo hint:', hint);
        
        // Criar elemento HTML
        const hintElement = this.createHintElement(hint);
        
        // Adicionar ao DOM
        const container = this.getOrCreateContainer();
        container.appendChild(hintElement);
        
        // Animar entrada
        setTimeout(() => {
            hintElement.classList.add('show');
        }, 100);
        
        // Auto-remover após duração configurada
        setTimeout(() => {
            this.removeHint(hintElement);
        }, this.settings.hint_display_duration * 1000);
    }
    
    /**
     * Criar elemento HTML do hint
     */
    createHintElement(hint) {
        const div = document.createElement('div');
        div.className = 'coaching-hint';
        div.dataset.hintId = hint.id;
        div.dataset.hintType = hint.hint_type;
        
        // Ícone baseado no tipo
        const icon = this.getIconForType(hint.hint_type);
        const color = this.getColorForType(hint.hint_type);
        
        // Parsear sugestões
        let suggestions = hint.suggestions;
        if (typeof suggestions === 'string') {
            try {
                suggestions = JSON.parse(suggestions);
            } catch (e) {
                suggestions = [];
            }
        }
        
        // HTML
        div.innerHTML = `
            <div class="coaching-hint-header" style="background-color: ${color}">
                <i class="${icon}"></i>
                <span class="coaching-hint-title">${this.getTypeLabel(hint.hint_type)}</span>
                <button class="coaching-hint-close" onclick="realtimeCoaching.closeHint(${hint.id})">
                    <i class="ki-duotone ki-cross fs-2"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="coaching-hint-body">
                <p class="coaching-hint-text">${hint.hint_text}</p>
                ${suggestions && suggestions.length > 0 ? `
                    <div class="coaching-hint-suggestions">
                        <strong>💡 Sugestões:</strong>
                        <ul>
                            ${suggestions.map(s => `<li>${s}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            </div>
            <div class="coaching-hint-footer">
                <button class="btn btn-sm btn-light-success" onclick="realtimeCoaching.markHelpful(${hint.id}, true)">
                    <i class="ki-duotone ki-like fs-3"><span class="path1"></span><span class="path2"></span></i>
                    Útil
                </button>
                <button class="btn btn-sm btn-light-danger" onclick="realtimeCoaching.markHelpful(${hint.id}, false)">
                    <i class="ki-duotone ki-dislike fs-3"><span class="path1"></span><span class="path2"></span></i>
                    Não útil
                </button>
            </div>
        `;
        
        return div;
    }
    
    /**
     * Obter ou criar container de hints
     */
    getOrCreateContainer() {
        let container = document.getElementById('coaching-hints-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'coaching-hints-container';
            container.className = 'coaching-hints-container';
            document.body.appendChild(container);
        }
        
        return container;
    }
    
    /**
     * Remover hint
     */
    removeHint(element) {
        element.classList.remove('show');
        setTimeout(() => {
            element.remove();
        }, 300);
    }
    
    /**
     * Fechar hint
     */
    closeHint(hintId) {
        const element = document.querySelector(`[data-hint-id="${hintId}"]`);
        if (element) {
            this.removeHint(element);
        }
        
        // Marcar como visualizado
        this.markAsViewed(hintId);
    }
    
    /**
     * Marcar hint como visualizado
     */
    async markAsViewed(hintId) {
        try {
            await fetch('/coaching/mark-viewed', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ hint_id: hintId })
            });
        } catch (error) {
            console.error('[Coaching] Erro ao marcar como visto:', error);
        }
    }
    
    /**
     * Marcar hint como útil/não útil
     */
    async markHelpful(hintId, helpful) {
        try {
            await fetch('/coaching/feedback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    hint_id: hintId,
                    helpful: helpful
                })
            });
            
            // Fechar hint após feedback
            this.closeHint(hintId);
            
            // Mostrar toast
            if (helpful) {
                this.showToast('Obrigado pelo feedback! 👍', 'success');
            } else {
                this.showToast('Feedback registrado. Vamos melhorar! 🔧', 'info');
            }
        } catch (error) {
            console.error('[Coaching] Erro ao enviar feedback:', error);
        }
    }
    
    /**
     * Tocar som de notificação
     */
    playNotificationSound() {
        try {
            const audio = new Audio('/assets/sounds/coaching-hint.mp3');
            audio.volume = 0.3;
            audio.play().catch(e => {
                console.warn('[Coaching] Não foi possível tocar som:', e);
            });
        } catch (error) {
            console.error('[Coaching] Erro ao tocar som:', error);
        }
    }
    
    /**
     * Obter ícone para tipo de hint
     */
    getIconForType(type) {
        const icons = {
            'objection': 'ki-duotone ki-shield-cross fs-2',
            'opportunity': 'ki-duotone ki-rocket fs-2',
            'question': 'ki-duotone ki-question-2 fs-2',
            'negative_sentiment': 'ki-duotone ki-emoji-sad fs-2',
            'buying_signal': 'ki-duotone ki-dollar fs-2',
            'closing_opportunity': 'ki-duotone ki-check-circle fs-2',
            'escalation_needed': 'ki-duotone ki-arrow-up fs-2',
        };
        return icons[type] || 'ki-duotone ki-information fs-2';
    }
    
    /**
     * Obter cor para tipo de hint
     */
    getColorForType(type) {
        const colors = {
            'objection': '#f1416c',
            'opportunity': '#50cd89',
            'question': '#009ef7',
            'negative_sentiment': '#ffc700',
            'buying_signal': '#7239ea',
            'closing_opportunity': '#00a3ff',
            'escalation_needed': '#f1416c',
        };
        return colors[type] || '#009ef7';
    }
    
    /**
     * Obter label para tipo de hint
     */
    getTypeLabel(type) {
        const labels = {
            'objection': '🛡️ Objeção Detectada',
            'opportunity': '🚀 Oportunidade',
            'question': '❓ Pergunta Importante',
            'negative_sentiment': '😟 Cliente Insatisfeito',
            'buying_signal': '💰 Sinal de Compra',
            'closing_opportunity': '✅ Momento de Fechar',
            'escalation_needed': '⬆️ Escalar Conversa',
        };
        return labels[type] || '💡 Dica';
    }
    
    /**
     * Mostrar toast
     */
    showToast(message, type = 'info') {
        // Usar sistema de toast existente ou criar um simples
        if (window.Swal) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type,
                title: message,
                showConfirmButton: false,
                timer: 3000
            });
        } else {
            console.log('[Coaching] Toast:', message);
        }
    }
}

} // Fim da proteção contra redeclaração

// Inicializar quando DOM estiver pronto (apenas se ainda não existe instância)
if (typeof window.realtimeCoaching === 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof RealtimeCoaching !== 'undefined') {
            window.realtimeCoaching = new RealtimeCoaching();
            console.log('[Coaching] ✅ Instância criada e atribuída a window.realtimeCoaching');
        }
    });
}
