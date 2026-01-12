/**
 * Coaching Inline - Exibe hints da IA abaixo das mensagens
 */

class CoachingInline {
    constructor() {
        this.hints = {};
        this.conversationId = null;
        this.enabled = true; // Será verificado na primeira requisição
        this.init();
    }

    init() {
        console.log('[CoachingInline] Inicializado');
        
        // Verificar se está habilitado antes de iniciar
        this.checkIfEnabled().then(enabled => {
            if (enabled) {
                console.log('[CoachingInline] Coaching habilitado - iniciando observação');
                // Observar mudanças na conversa
                this.observeConversationChanges();
                
                // Polling a cada 10 segundos para buscar novos hints
                this.startPolling();
            } else {
                console.log('[CoachingInline] Coaching desabilitado - não iniciando');
            }
        });
    }
    
    async checkIfEnabled() {
        try {
            // Fazer uma requisição de teste para verificar se está habilitado
            const response = await fetch('/api/coaching/hints/pending');
            const data = await response.json();
            this.enabled = data.enabled !== false;
            return this.enabled;
        } catch (error) {
            console.error('[CoachingInline] Erro ao verificar se está habilitado:', error);
            this.enabled = false;
            return false;
        }
    }

    observeConversationChanges() {
        // Verificar quando a conversa muda no #chatMessages
        const checkInterval = setInterval(() => {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                const newConversationId = chatMessages.dataset.conversationId;
                if (newConversationId && newConversationId !== this.conversationId) {
                    this.conversationId = newConversationId;
                    console.log('[CoachingInline] Nova conversa detectada:', this.conversationId);
                    this.loadHints();
                }
            }
        }, 1000);
    }

    startPolling() {
        // Polling a cada 60 segundos para buscar novos hints (coaching não é tempo-real crítico)
        setInterval(() => {
            if (this.conversationId) {
                console.log('[CoachingInline] Polling - buscando novos hints...');
                this.loadHints();
            }
        }, 60000); // 60 segundos ao invés de 10 (recomendação de performance)
    }

    async loadHints() {
        if (!this.conversationId) return;
        
        // ✅ Não fazer requisição se desabilitado
        if (!this.enabled) {
            console.log('[CoachingInline] Coaching desabilitado - pulando requisição');
            return;
        }

        try {
            const response = await fetch(`/api/coaching/hints/conversation/${this.conversationId}`);
            const data = await response.json();

            // ✅ Verificar se foi desabilitado no meio do caminho
            if (data.enabled === false) {
                console.log('[CoachingInline] Coaching foi desabilitado - parando polling');
                this.enabled = false;
                return;
            }

            if (data.success) {
                this.hints = data.hints_by_message || {};
                console.log('[CoachingInline] Hints carregados:', Object.keys(this.hints).length, 'mensagens com hints');
                this.renderAllHints();
            }
        } catch (error) {
            console.error('[CoachingInline] Erro ao carregar hints:', error);
        }
    }

    renderAllHints() {
        // Renderizar hints para todas as mensagens
        Object.keys(this.hints).forEach(messageId => {
            this.renderHintForMessage(messageId);
        });
    }

    renderHintForMessage(messageId) {
        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageElement) {
            console.warn('[CoachingInline] Mensagem não encontrada:', messageId);
            return;
        }

        // Verificar se já tem hint renderizado
        const existingHint = messageElement.nextElementSibling;
        if (existingHint && existingHint.classList.contains('coaching-hint-inline')) {
            return; // Já existe
        }

        const hints = this.hints[messageId];
        if (!hints || hints.length === 0) return;

        // Pegar o hint mais recente
        const hint = hints[0];

        // Criar elemento do hint
        const hintElement = this.createHintElement(hint);

        // Inserir após a mensagem
        messageElement.insertAdjacentElement('afterend', hintElement);

        // Marcar como visualizado (opcional, pode ser ao clicar)
        // this.markAsViewed(hint.id);
    }

    createHintElement(hint) {
        const div = document.createElement('div');
        div.className = 'coaching-hint-inline';
        if (hint.viewed_at) {
            div.classList.add('viewed');
        }
        div.dataset.hintId = hint.id;

        const hintTypeLabels = {
            'objection': '🛡️ Objeção',
            'opportunity': '🎯 Oportunidade',
            'question': '❓ Pergunta',
            'negative_sentiment': '😟 Sentimento Negativo',
            'buying_signal': '💰 Sinal de Compra',
            'closing_opportunity': '🎉 Fechar Venda',
            'escalation_needed': '⚠️ Escalar'
        };

        const hintTypeLabel = hintTypeLabels[hint.hint_type] || hint.hint_type;

        let suggestionsHtml = '';
        if (hint.suggestions) {
            const suggestions = typeof hint.suggestions === 'string' 
                ? JSON.parse(hint.suggestions) 
                : hint.suggestions;

            if (suggestions && suggestions.length > 0) {
                suggestionsHtml = `
                    <div class="coaching-hint-suggestions">
                        <div class="coaching-hint-suggestions-title">Sugestões</div>
                        ${suggestions.map((suggestion, index) => `
                            <div class="coaching-hint-suggestion" 
                                 data-suggestion-index="${index}"
                                 onclick="window.coachingInline.useSuggestion(${hint.id}, ${index}, this)">
                                ${this.escapeHtml(suggestion)}
                            </div>
                        `).join('')}
                    </div>
                `;
            }
        }

        const feedbackHtml = hint.feedback 
            ? `<span style="color: #ffffff; font-size: 12px;">Feedback: ${hint.feedback === 'helpful' ? '👍 Útil' : '👎 Não útil'}</span>`
            : `
                <button class="coaching-hint-btn helpful" onclick="window.coachingInline.sendFeedback(${hint.id}, 'helpful', this)">
                    👍 Útil
                </button>
                <button class="coaching-hint-btn not-helpful" onclick="window.coachingInline.sendFeedback(${hint.id}, 'not_helpful', this)">
                    👎 Não útil
                </button>
            `;

        div.innerHTML = `
            <div class="coaching-hint-header">
                <span class="coaching-hint-type">${hintTypeLabel}</span>
            </div>
            <div class="coaching-hint-content">
                ${this.escapeHtml(hint.hint_text)}
            </div>
            ${suggestionsHtml}
            <div class="coaching-hint-actions">
                ${feedbackHtml}
            </div>
        `;

        return div;
    }

    async useSuggestion(hintId, suggestionIndex, element) {
        try {
            // Feedback visual
            element.style.background = 'rgba(76, 175, 80, 0.5)';
            element.innerHTML = '✓ ' + element.textContent;

            const response = await fetch(`/api/coaching/hints/${hintId}/use-suggestion`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ suggestion_index: suggestionIndex })
            });

            const data = await response.json();

            if (data.success && data.suggestion) {
                // Copiar para área de transferência
                await navigator.clipboard.writeText(data.suggestion);
                
                // Inserir no campo de mensagem
                const messageInput = document.querySelector('#messageInput, textarea[name="message"]');
                if (messageInput) {
                    messageInput.value = data.suggestion;
                    messageInput.focus();
                }

                // Notificação
                this.showNotification('Sugestão copiada! 📋', 'success');
            }
        } catch (error) {
            console.error('[CoachingInline] Erro ao usar sugestão:', error);
            this.showNotification('Erro ao copiar sugestão', 'error');
        }
    }

    async sendFeedback(hintId, feedback, button) {
        try {
            // Desabilitar botões
            const buttons = button.parentElement.querySelectorAll('button');
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.classList.add('active');
            });

            const response = await fetch(`/api/coaching/hints/${hintId}/feedback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ feedback })
            });

            const data = await response.json();

            if (data.success) {
                // Substituir botões por feedback
                const icon = feedback === 'helpful' ? '👍' : '👎';
                const text = feedback === 'helpful' ? 'Útil' : 'Não útil';
                button.parentElement.innerHTML = `<span style="color: #ffffff; font-size: 12px;">Feedback: ${icon} ${text}</span>`;
                
                this.showNotification('Obrigado pelo feedback! 🙏', 'success');
            }
        } catch (error) {
            console.error('[CoachingInline] Erro ao enviar feedback:', error);
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.classList.remove('active');
            });
        }
    }

    async markAsViewed(hintId) {
        try {
            await fetch(`/api/coaching/hints/${hintId}/view`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
        } catch (error) {
            console.error('[CoachingInline] Erro ao marcar como visualizado:', error);
        }
    }

    showNotification(message, type = 'info') {
        // Criar notificação toast simples
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 99999;
            animation: slideInUp 0.3s ease-out;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Adicionar hint em tempo real (chamado pelo WebSocket)
    addHintRealtime(hint) {
        console.log('[CoachingInline] Novo hint recebido via WebSocket:', hint);
        
        const messageId = hint.message_id;
        if (!this.hints[messageId]) {
            this.hints[messageId] = [];
        }
        this.hints[messageId].unshift(hint);
        
        this.renderHintForMessage(messageId);
        
        // Som e notificação
        this.showNotification(`💡 Nova dica: ${hint.hint_text}`, 'info');
    }
}

// Inicializar quando o DOM estiver pronto
if (typeof window.coachingInline === 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        window.coachingInline = new CoachingInline();
    });
}
