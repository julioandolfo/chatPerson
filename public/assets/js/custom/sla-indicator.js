/**
 * Sistema de Indicador Visual de SLA
 * Atualiza progress bars circulares ao redor dos avatares
 */

const SLAIndicator = {
    // Configurações de SLA (serão carregadas do backend)
    config: {
        firstResponseTime: 15, // minutos
        resolutionTime: 60,   // minutos
        enabled: true
    },
    
    /**
     * Inicializar sistema de SLA
     */
    init: function() {
        console.log('[SLA] Inicializando sistema de indicadores...');
        
        // Carregar configurações
        this.loadConfig();
        
        // Atualizar todos os indicadores
        this.updateAllIndicators();
        
        // Atualizar a cada 30 segundos
        setInterval(() => {
            this.updateAllIndicators();
        }, 30000);
        
        console.log('[SLA] Sistema inicializado com sucesso');
    },
    
    /**
     * Carregar configurações de SLA do backend
     */
    loadConfig: async function() {
        try {
            const response = await fetch('/api/settings/sla');
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.sla) {
                    this.config = {
                        firstResponseTime: data.sla.first_response_time || 15,
                        resolutionTime: data.sla.resolution_time || 60,
                        enabled: data.sla.enable_sla_monitoring !== false
                    };
                    console.log('[SLA] Configurações carregadas:', this.config);
                }
            }
        } catch (error) {
            console.warn('[SLA] Erro ao carregar configurações:', error);
            // Usar valores padrão
        }
    },
    
    /**
     * Atualizar todos os indicadores visíveis
     */
    updateAllIndicators: function() {
        if (!this.config.enabled) {
            return;
        }
        
        // Buscar todas as conversas visíveis
        const conversations = document.querySelectorAll('.conversation-item');
        
        conversations.forEach(item => {
            const convId = item.getAttribute('data-conversation-id');
            const avatar = item.querySelector('.symbol-45px, .symbol-50px, .symbol-35px');
            
            if (!avatar || !convId) return;
            
            // Obter dados da conversa (do atributo data ou do objeto global)
            const convData = this.getConversationData(convId);
            
            if (convData) {
                this.updateIndicator(avatar, convData);
            }
        });
    },
    
    /**
     * Obter dados da conversa
     */
    getConversationData: function(convId) {
        // Tentar obter do objeto global de conversas
        if (window.conversationsData && window.conversationsData[convId]) {
            return window.conversationsData[convId];
        }
        
        // Tentar obter do elemento DOM
        const item = document.querySelector(`[data-conversation-id="${convId}"]`);
        if (item) {
            return {
                id: convId,
                status: item.getAttribute('data-status') || 'open',
                created_at: item.getAttribute('data-created-at'),
                first_response_at: item.getAttribute('data-first-response-at'),
                last_message_at: item.getAttribute('data-last-message-at'),
                agent_id: item.getAttribute('data-agent-id')
            };
        }
        
        return null;
    },
    
    /**
     * Atualizar indicador de uma conversa específica
     */
    updateIndicator: function(avatar, conv) {
        // Verificar se já existe indicador
        let existingIndicator = avatar.querySelector('.sla-progress-ring');
        
        // Calcular status do SLA
        const slaStatus = this.calculateSLAStatus(conv);
        
        // Se não houver violação, não mostrar indicador
        if (slaStatus.percentage === 0 && !slaStatus.breached) {
            if (existingIndicator) {
                existingIndicator.remove();
            }
            avatar.classList.remove('symbol-sla');
            return;
        }
        
        // Adicionar classe symbol-sla
        if (!avatar.classList.contains('symbol-sla')) {
            avatar.classList.add('symbol-sla');
        }
        
        // Criar indicador se não existir
        if (!existingIndicator) {
            const indicator = this.createIndicatorSVG(avatar);
            avatar.insertAdjacentHTML('afterbegin', indicator);
            existingIndicator = avatar.querySelector('.sla-progress-ring');
        }
        
        // Atualizar progresso
        this.updateProgress(existingIndicator, slaStatus);
        
        // Atualizar classes de status
        avatar.className = avatar.className.replace(/sla-status-\w+/g, '');
        avatar.classList.add(`sla-status-${slaStatus.status}`);
        
        // Adicionar/atualizar tooltip
        this.updateTooltip(avatar, slaStatus);
        
        // Badge de SLA estourado
        if (slaStatus.breached) {
            if (!avatar.querySelector('.sla-breached-badge')) {
                avatar.insertAdjacentHTML('beforeend', '<div class="sla-breached-badge"><i class="fas fa-exclamation"></i></div>');
            }
        } else {
            const badge = avatar.querySelector('.sla-breached-badge');
            if (badge) badge.remove();
        }
    },
    
    /**
     * Criar SVG do indicador circular
     */
    createIndicatorSVG: function(avatar) {
        // Obter tamanho do avatar
        const size = avatar.classList.contains('symbol-35px') ? 35 : 
                    avatar.classList.contains('symbol-50px') ? 50 : 45;
        
        const viewBoxSize = size + 6;
        const center = viewBoxSize / 2;
        const radius = (viewBoxSize - 6) / 2;
        const circumference = 2 * Math.PI * radius;
        
        return `
            <svg class="sla-progress-ring" width="${viewBoxSize}" height="${viewBoxSize}" viewBox="0 0 ${viewBoxSize} ${viewBoxSize}">
                <circle class="sla-ring-bg" cx="${center}" cy="${center}" r="${radius}"></circle>
                <circle class="sla-ring-progress" 
                        cx="${center}" 
                        cy="${center}" 
                        r="${radius}"
                        stroke-dasharray="${circumference}"
                        stroke-dashoffset="${circumference}"></circle>
            </svg>
        `;
    },
    
    /**
     * Atualizar progresso do círculo
     */
    updateProgress: function(indicator, slaStatus) {
        const circle = indicator.querySelector('.sla-ring-progress');
        if (!circle) return;
        
        const circumference = parseFloat(circle.getAttribute('stroke-dasharray'));
        const progress = slaStatus.breached ? 100 : slaStatus.percentage;
        const offset = circumference - (progress / 100 * circumference);
        
        circle.style.strokeDashoffset = offset;
    },
    
    /**
     * Calcular status do SLA
     */
    calculateSLAStatus: function(conv) {
        const now = new Date();
        const createdAt = new Date(conv.created_at);
        const firstResponseAt = conv.first_response_at ? new Date(conv.first_response_at) : null;
        
        // Se conversa está fechada ou resolvida, não calcular SLA
        if (conv.status === 'closed' || conv.status === 'resolved') {
            return { percentage: 0, status: 'none', breached: false };
        }
        
        // Se ainda não há agente atribuído ou não houve primeira resposta
        const waitingFirstResponse = !firstResponseAt;
        
        if (waitingFirstResponse) {
            // Calcular SLA de primeira resposta
            const minutesSinceCreated = (now - createdAt) / 1000 / 60;
            const slaMinutes = this.config.firstResponseTime;
            const percentage = Math.min((minutesSinceCreated / slaMinutes) * 100, 100);
            const breached = minutesSinceCreated > slaMinutes;
            
            return {
                percentage: percentage,
                status: this.getStatusFromPercentage(percentage, breached),
                breached: breached,
                type: 'first_response',
                elapsed: Math.floor(minutesSinceCreated),
                limit: slaMinutes,
                remaining: Math.max(0, Math.ceil(slaMinutes - minutesSinceCreated))
            };
        } else {
            // Calcular SLA de resolução
            const minutesSinceCreated = (now - createdAt) / 1000 / 60;
            const slaMinutes = this.config.resolutionTime;
            const percentage = Math.min((minutesSinceCreated / slaMinutes) * 100, 100);
            const breached = minutesSinceCreated > slaMinutes;
            
            return {
                percentage: percentage,
                status: this.getStatusFromPercentage(percentage, breached),
                breached: breached,
                type: 'resolution',
                elapsed: Math.floor(minutesSinceCreated),
                limit: slaMinutes,
                remaining: Math.max(0, Math.ceil(slaMinutes - minutesSinceCreated))
            };
        }
    },
    
    /**
     * Obter status baseado na porcentagem
     */
    getStatusFromPercentage: function(percentage, breached) {
        if (breached) return 'breached';
        if (percentage >= 90) return 'danger';
        if (percentage >= 70) return 'critical';
        if (percentage >= 50) return 'warning';
        if (percentage >= 30) return 'good';
        return 'excellent';
    },
    
    /**
     * Atualizar tooltip
     */
    updateTooltip: function(avatar, slaStatus) {
        let tooltip = avatar.querySelector('.sla-tooltip');
        
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'sla-tooltip';
            avatar.appendChild(tooltip);
        }
        
        const typeLabel = slaStatus.type === 'first_response' ? 'Primeira Resposta' : 'Resolução';
        
        if (slaStatus.breached) {
            tooltip.textContent = `SLA ${typeLabel} ESTOURADO! (+${slaStatus.elapsed - slaStatus.limit}min)`;
        } else {
            tooltip.textContent = `SLA ${typeLabel}: ${slaStatus.remaining}min restantes (${Math.round(slaStatus.percentage)}%)`;
        }
    },
    
    /**
     * Atualizar indicador de uma conversa específica (chamado externamente)
     */
    updateConversation: function(convId, convData) {
        const item = document.querySelector(`[data-conversation-id="${convId}"]`);
        if (!item) return;
        
        const avatar = item.querySelector('.symbol-45px, .symbol-50px, .symbol-35px');
        if (!avatar) return;
        
        this.updateIndicator(avatar, convData);
    }
};

// Inicializar quando o documento estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        SLAIndicator.init();
    });
} else {
    SLAIndicator.init();
}

// Expor globalmente para uso em outros scripts
window.SLAIndicator = SLAIndicator;

