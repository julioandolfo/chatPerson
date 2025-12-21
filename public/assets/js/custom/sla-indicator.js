/**
 * Sistema de Indicador Visual de SLA
 * Atualiza progress bars circulares ao redor dos avatares
 */

const SLAIndicator = {
    // Configurações de SLA (serão carregadas do backend)
    config: {
        firstResponseTime: 15, // minutos
        resolutionTime: 60,   // minutos
        ongoingResponseTime: 15,
        enableResolution: true,
        workingHoursEnabled: false,
        workingHoursStart: '08:00',
        workingHoursEnd: '18:00',
        enabled: true
    },

    /**
     * Calcular minutos dentro do horário de atendimento entre duas datas
     */
    getWorkingMinutes: function(start, end) {
        if (!start || !end || isNaN(start.getTime()) || isNaN(end.getTime()) || end <= start) return 0;

        const [sH, sM] = (this.config.workingHoursStart || '08:00').split(':').map(Number);
        const [eH, eM] = (this.config.workingHoursEnd || '18:00').split(':').map(Number);

        let total = 0;
        let cursor = new Date(start);

        while (cursor < end) {
            const dayStart = new Date(cursor);
            dayStart.setHours(sH, sM, 0, 0);
            const dayEnd = new Date(cursor);
            dayEnd.setHours(eH, eM, 0, 0);

            // janela do dia atual
            const windowStart = cursor > dayStart ? cursor : dayStart;
            const windowEnd = end < dayEnd ? end : dayEnd;

            if (windowEnd > windowStart) {
                total += (windowEnd - windowStart) / 60000; // minutos
            }

            // avançar para próximo dia
            cursor = new Date(dayStart);
            cursor.setDate(cursor.getDate() + 1);
            cursor.setHours(0, 0, 0, 0);
        }

        return total;
    },
    
    /**
     * Inicializar sistema de SLA
     */
    init: async function() {
        console.log('[SLA] Inicializando sistema de indicadores...');
        
        // Carregar configurações (aguardar)
        await this.loadConfig();
        
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
                        ongoingResponseTime: data.sla.ongoing_response_time || 15,
                        enableResolution: data.sla.enable_resolution_sla !== false,
                        workingHoursEnabled: data.sla.working_hours_enabled || false,
                        workingHoursStart: data.sla.working_hours_start || '08:00',
                        workingHoursEnd: data.sla.working_hours_end || '18:00',
                        enabled: data.sla.enable_sla_monitoring !== false
                    };
                    console.log('[SLA] Configurações carregadas:', this.config);
                    
                    // Atualizar indicadores imediatamente após carregar config
                    this.updateAllIndicators();
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
                last_contact_message_at: item.getAttribute('data-last-contact-message-at'),
                last_agent_message_at: item.getAttribute('data-last-agent-message-at'),
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
        
        // viewBox ligeiramente maior para ficar fora do avatar
        const viewBoxSize = size + 6;
        const stroke = size >= 50 ? 3.5 : size >= 45 ? 3 : 2.5;
        const inset = stroke / 2;
        const rectSize = viewBoxSize - stroke;
        const rx = Math.max(8, Math.round(size * 0.22)); // cantos arredondados para seguir o avatar
        const dashArray = 2 * (rectSize + rectSize);
        
        return `
            <svg class="sla-progress-ring" width="${viewBoxSize}" height="${viewBoxSize}" viewBox="0 0 ${viewBoxSize} ${viewBoxSize}">
                <rect class="sla-ring-bg"
                      x="${inset}" y="${inset}"
                      width="${rectSize}" height="${rectSize}"
                      rx="${rx}" ry="${rx}">
                </rect>
                <rect class="sla-ring-progress"
                      x="${inset}" y="${inset}"
                      width="${rectSize}" height="${rectSize}"
                      rx="${rx}" ry="${rx}"
                      stroke-dasharray="${dashArray}"
                      stroke-dashoffset="${dashArray}"
                      stroke-width="${stroke}">
                </rect>
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
        const createdAt = conv.created_at ? new Date(conv.created_at) : null;
        const firstResponseAt = conv.first_response_at ? new Date(conv.first_response_at) : null;
        const lastContactAt = conv.last_contact_message_at ? new Date(conv.last_contact_message_at) : null;
        const lastAgentAt = conv.last_agent_message_at ? new Date(conv.last_agent_message_at) : null;
        
        // Falha de dados: sem created_at válido -> não mostra
        if (!createdAt || isNaN(createdAt.getTime())) {
            console.warn('[SLA] Sem created_at válido para conv', conv.id, conv);
            return { percentage: 0, status: 'none', breached: false, show: false };
        }
        
        console.log(`[SLA] Calculando para conversa ${conv.id}:`, {
            created_at: conv.created_at,
            first_response_at: conv.first_response_at,
            last_contact_message_at: conv.last_contact_message_at,
            last_agent_message_at: conv.last_agent_message_at,
            status: conv.status,
            agent_id: conv.agent_id
        });
        
        // Se conversa está fechada ou resolvida, não calcular SLA
        if (conv.status === 'closed' || conv.status === 'resolved') {
            console.log(`[SLA] Conversa ${conv.id} está ${conv.status}, ignorando SLA`);
            return { percentage: 0, status: 'none', breached: false, show: false };
        }
        
        // Se ainda não há agente atribuído ou não houve primeira resposta
        const waitingFirstResponse = !firstResponseAt;
        console.log(`[SLA] Conversa ${conv.id} aguardando primeira resposta: ${waitingFirstResponse}`);
        
        if (waitingFirstResponse) {
            // Calcular SLA de primeira resposta
            const minutesSinceCreated = this.config.workingHoursEnabled
                ? this.getWorkingMinutes(createdAt, now)
                : (now - createdAt) / 1000 / 60;
            const slaMinutes = this.config.firstResponseTime;
            if (!isFinite(minutesSinceCreated) || !isFinite(slaMinutes) || slaMinutes <= 0) {
                return { percentage: 0, status: 'none', breached: false, show: false };
            }
            const percentageRaw = (minutesSinceCreated / slaMinutes) * 100;
            const percentage = Math.min(Math.max(percentageRaw, 0.1), 100); // manter anel visível mesmo <1%
            const breached = minutesSinceCreated > slaMinutes;
            
            return {
                percentage: percentage,
                status: this.getStatusFromPercentage(percentage, breached),
                breached: breached,
                type: 'first_response',
                elapsed: Math.floor(minutesSinceCreated),
                limit: slaMinutes,
                remaining: Math.max(0, Math.ceil(slaMinutes - minutesSinceCreated)),
                show: true
            };
        } else {
            // Se SLA de resolução estiver desativado, usar SLA de resposta contínua
            if (!this.config.enableResolution) {
                // Verificar se há mensagem pendente do contato (última mensagem é do contato)
                const contactVsAgent = lastContactAt && (!lastAgentAt || lastContactAt > lastAgentAt);
                if (contactVsAgent) {
                    const minutesWaiting = this.config.workingHoursEnabled
                        ? this.getWorkingMinutes(lastContactAt, now)
                        : (now - lastContactAt) / 1000 / 60;
                    const slaMinutes = this.config.ongoingResponseTime || this.config.firstResponseTime;
                    if (!isFinite(minutesWaiting) || !isFinite(slaMinutes) || slaMinutes <= 0) {
                        return { percentage: 0, status: 'none', breached: false, show: false };
                    }
                    const percentageRaw = (minutesWaiting / slaMinutes) * 100;
                    const percentage = Math.min(Math.max(percentageRaw, 0.1), 100); // manter anel visível
                    const breached = minutesWaiting > slaMinutes;
                    
                    return {
                        percentage: percentage,
                        status: this.getStatusFromPercentage(percentage, breached),
                        breached: breached,
                        type: 'response',
                        elapsed: Math.floor(minutesWaiting),
                        limit: slaMinutes,
                        remaining: Math.max(0, Math.ceil(slaMinutes - minutesWaiting)),
                        show: true
                    };
                }
                
                // Sem pendências: não mostrar anel
                return { percentage: 0, status: 'none', breached: false, show: false };
            }
            
            // Calcular SLA de resolução (padrão)
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
                remaining: Math.max(0, Math.ceil(slaMinutes - minutesSinceCreated)),
                show: true
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
        
        let typeLabel = 'Resolução';
        if (slaStatus.type === 'first_response') {
            typeLabel = 'Primeira Resposta';
        } else if (slaStatus.type === 'response') {
            typeLabel = 'Resposta em Conversa';
        }
        
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

