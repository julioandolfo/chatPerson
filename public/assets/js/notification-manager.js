/**
 * Notification Manager - Sistema de Notificações Visuais
 * 
 * Gerencia notificações push/toast no canto inferior direito da tela.
 * Integrado com SoundManager para tocar sons junto com as notificações.
 */

(function(window) {
    'use strict';

    const NotificationManager = {
        // Container das notificações
        container: null,
        
        // Fila de notificações
        queue: [],
        
        // Configurações carregadas do servidor
        settings: {
            enabled: true,
            browserNotificationsEnabled: true,
            position: 'bottom-right',
            duration: 8000,
            showPreview: true,
            maxVisible: 5
        },
        
        // Máximo de notificações visíveis simultaneamente (legado, usa settings.maxVisible)
        maxVisible: 5,
        
        // Tempo padrão de exibição (ms) (legado, usa settings.duration)
        defaultDuration: 8000,
        
        // Flag de inicialização
        initialized: false,
        
        // Contador de IDs
        notificationId: 0,
        
        // Tipos de notificação com ícones e cores
        types: {
            new_conversation: {
                icon: 'ki-message-add',
                color: 'primary',
                title: 'Nova Conversa',
                sound: 'new_conversation'
            },
            new_message: {
                icon: 'ki-message-text',
                color: 'info',
                title: 'Nova Mensagem',
                sound: 'new_message'
            },
            conversation_assigned: {
                icon: 'ki-user-tick',
                color: 'success',
                title: 'Conversa Atribuída',
                sound: 'conversation_assigned'
            },
            invite_received: {
                icon: 'ki-notification-on',
                color: 'warning',
                title: 'Convite Recebido',
                sound: 'invite_received'
            },
            sla_warning: {
                icon: 'ki-timer',
                color: 'warning',
                title: 'Aviso de SLA',
                sound: 'sla_warning'
            },
            sla_breached: {
                icon: 'ki-shield-cross',
                color: 'danger',
                title: 'SLA Estourado',
                sound: 'sla_breached'
            },
            mention_received: {
                icon: 'ki-user',
                color: 'primary',
                title: 'Você foi mencionado',
                sound: 'mention_received'
            },
            info: {
                icon: 'ki-information',
                color: 'info',
                title: 'Informação',
                sound: null
            },
            success: {
                icon: 'ki-check-circle',
                color: 'success',
                title: 'Sucesso',
                sound: null
            },
            error: {
                icon: 'ki-cross-circle',
                color: 'danger',
                title: 'Erro',
                sound: null
            }
        },

        /**
         * Inicializar o Notification Manager
         */
        init: function() {
            if (this.initialized) {
                return;
            }
            
            console.log('[NotificationManager] Inicializando...');
            
            // Carregar configurações do servidor
            this.loadSettings();
            
            // Criar container
            this.createContainer();
            
            // Injetar estilos CSS
            this.injectStyles();
            
            // Configurar listeners de eventos
            this.setupEventListeners();
            
            // Solicitar permissão para notificações do navegador (opcional)
            this.requestBrowserPermission();
            
            this.initialized = true;
            console.log('[NotificationManager] ✅ Inicializado com sucesso');
        },

        /**
         * Carregar configurações do servidor
         */
        loadSettings: function() {
            fetch('/settings/sounds', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.settings) {
                    this.settings = {
                        enabled: data.settings.visual_notifications_enabled !== 0,
                        browserNotificationsEnabled: data.settings.browser_notifications_enabled !== 0,
                        position: data.settings.notification_position || 'bottom-right',
                        duration: parseInt(data.settings.notification_duration) || 8000,
                        showPreview: data.settings.show_notification_preview !== 0,
                        maxVisible: parseInt(data.settings.max_visible_notifications) || 5
                    };
                    
                    // Atualizar valores legados
                    this.maxVisible = this.settings.maxVisible;
                    this.defaultDuration = this.settings.duration;
                    
                    // Atualizar posição do container
                    this.updateContainerPosition();
                    
                    console.log('[NotificationManager] ✅ Configurações carregadas:', this.settings);
                }
            })
            .catch(error => {
                console.error('[NotificationManager] ❌ Erro ao carregar configurações:', error);
            });
        },

        /**
         * Recarregar configurações
         */
        reloadSettings: function() {
            this.loadSettings();
        },

        /**
         * Atualizar configurações (para testes)
         */
        updateSettings: function(newSettings) {
            this.settings = { ...this.settings, ...newSettings };
            this.maxVisible = this.settings.maxVisible || this.maxVisible;
            this.defaultDuration = this.settings.duration || this.defaultDuration;
            this.updateContainerPosition();
        },

        /**
         * Atualizar posição do container
         */
        updateContainerPosition: function() {
            if (!this.container) return;
            
            // Remover classes de posição existentes
            this.container.classList.remove('position-bottom-right', 'position-bottom-left', 
                                           'position-top-right', 'position-top-left');
            
            // Adicionar nova classe de posição
            this.container.classList.add('position-' + this.settings.position);
        },

        /**
         * Criar container das notificações
         */
        createContainer: function() {
            this.container = document.createElement('div');
            this.container.id = 'notification-container';
            this.container.className = 'notification-container';
            document.body.appendChild(this.container);
        },

        /**
         * Injetar estilos CSS
         */
        injectStyles: function() {
            const styles = `
                .notification-container {
                    position: fixed;
                    z-index: 9999;
                    display: flex;
                    gap: 10px;
                    max-height: calc(100vh - 40px);
                    overflow: hidden;
                    pointer-events: none;
                }
                
                /* Posições do container */
                .notification-container.position-bottom-right,
                .notification-container:not([class*="position-"]) {
                    bottom: 20px;
                    right: 20px;
                    flex-direction: column-reverse;
                }
                
                .notification-container.position-bottom-left {
                    bottom: 20px;
                    left: 20px;
                    flex-direction: column-reverse;
                }
                
                .notification-container.position-top-right {
                    top: 20px;
                    right: 20px;
                    flex-direction: column;
                }
                
                .notification-container.position-top-left {
                    top: 20px;
                    left: 20px;
                    flex-direction: column;
                }
                
                .notification-toast {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    padding: 16px;
                    background: var(--bs-body-bg, #fff);
                    border-radius: 12px;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(0, 0, 0, 0.1);
                    min-width: 320px;
                    max-width: 420px;
                    cursor: pointer;
                    pointer-events: auto;
                    opacity: 0;
                    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                    border-left: 4px solid transparent;
                    position: relative;
                    overflow: hidden;
                }
                
                /* Animações baseadas na posição */
                .position-bottom-right .notification-toast,
                .position-top-right .notification-toast {
                    transform: translateX(120%);
                }
                
                .position-bottom-left .notification-toast,
                .position-top-left .notification-toast {
                    transform: translateX(-120%);
                }
                
                .notification-toast.show {
                    transform: translateX(0);
                    opacity: 1;
                }
                
                .position-bottom-right .notification-toast.hide,
                .position-top-right .notification-toast.hide {
                    transform: translateX(120%);
                    opacity: 0;
                }
                
                .position-bottom-left .notification-toast.hide,
                .position-top-left .notification-toast.hide {
                    transform: translateX(-120%);
                    opacity: 0;
                }
                
                .notification-toast:hover {
                    transform: translateX(-5px) scale(1.02);
                    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2), 0 4px 12px rgba(0, 0, 0, 0.15);
                }
                
                .notification-toast.type-primary { border-left-color: var(--bs-primary, #3699FF); }
                .notification-toast.type-success { border-left-color: var(--bs-success, #1BC5BD); }
                .notification-toast.type-warning { border-left-color: var(--bs-warning, #FFA800); }
                .notification-toast.type-danger { border-left-color: var(--bs-danger, #F64E60); }
                .notification-toast.type-info { border-left-color: var(--bs-info, #8950FC); }
                
                .notification-icon {
                    width: 44px;
                    height: 44px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }
                
                .notification-icon.bg-primary { background: rgba(54, 153, 255, 0.15); color: var(--bs-primary, #3699FF); }
                .notification-icon.bg-success { background: rgba(27, 197, 189, 0.15); color: var(--bs-success, #1BC5BD); }
                .notification-icon.bg-warning { background: rgba(255, 168, 0, 0.15); color: var(--bs-warning, #FFA800); }
                .notification-icon.bg-danger { background: rgba(246, 78, 96, 0.15); color: var(--bs-danger, #F64E60); }
                .notification-icon.bg-info { background: rgba(137, 80, 252, 0.15); color: var(--bs-info, #8950FC); }
                
                .notification-content {
                    flex: 1;
                    min-width: 0;
                }
                
                .notification-title {
                    font-weight: 600;
                    font-size: 14px;
                    color: var(--bs-gray-900, #181C32);
                    margin-bottom: 4px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }
                
                .notification-time {
                    font-size: 11px;
                    color: var(--bs-gray-500, #A1A5B7);
                    font-weight: 400;
                }
                
                .notification-message {
                    font-size: 13px;
                    color: var(--bs-gray-600, #7E8299);
                    line-height: 1.4;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                }
                
                .notification-sender {
                    font-weight: 600;
                    color: var(--bs-gray-800, #3F4254);
                }
                
                .notification-close {
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    width: 20px;
                    height: 20px;
                    border: none;
                    background: transparent;
                    color: var(--bs-gray-500, #A1A5B7);
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    font-size: 14px;
                    transition: all 0.2s;
                    opacity: 0;
                }
                
                .notification-toast:hover .notification-close {
                    opacity: 1;
                }
                
                .notification-close:hover {
                    background: var(--bs-gray-200, #F5F8FA);
                    color: var(--bs-gray-700, #5E6278);
                }
                
                .notification-progress {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    height: 3px;
                    background: var(--bs-primary, #3699FF);
                    width: 100%;
                    transform-origin: left;
                    animation: notification-progress linear forwards;
                }
                
                @keyframes notification-progress {
                    from { transform: scaleX(1); }
                    to { transform: scaleX(0); }
                }
                
                .notification-toast.paused .notification-progress {
                    animation-play-state: paused;
                }
                
                /* Avatar para notificações de conversa */
                .notification-avatar {
                    width: 44px;
                    height: 44px;
                    border-radius: 50%;
                    object-fit: cover;
                    flex-shrink: 0;
                }
                
                /* Dark mode */
                [data-bs-theme="dark"] .notification-toast {
                    background: var(--bs-gray-800, #1E1E2D);
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                }
                
                [data-bs-theme="dark"] .notification-title {
                    color: var(--bs-gray-100, #F5F8FA);
                }
                
                [data-bs-theme="dark"] .notification-message {
                    color: var(--bs-gray-400, #B5B5C3);
                }
                
                [data-bs-theme="dark"] .notification-close:hover {
                    background: var(--bs-gray-700, #2B2B40);
                }
                
                /* Responsivo */
                @media (max-width: 480px) {
                    .notification-container {
                        left: 10px;
                        right: 10px;
                        bottom: 10px;
                    }
                    
                    .notification-toast {
                        min-width: unset;
                        max-width: unset;
                        width: 100%;
                    }
                }
            `;
            
            const styleSheet = document.createElement('style');
            styleSheet.id = 'notification-styles';
            styleSheet.textContent = styles;
            document.head.appendChild(styleSheet);
        },

        /**
         * Configurar event listeners
         */
        setupEventListeners: function() {
            // Eventos do WebSocket/Realtime
            document.addEventListener('realtime:new_message', (e) => {
                // Os dados podem vir como { conversation_id, message } ou diretamente
                const data = e.detail || {};
                const message = data.message || data;
                
                // Verificar se é mensagem incoming (do contato)
                const isIncoming = message.direction === 'incoming' || 
                                   message.sender_type === 'contact' ||
                                   (message.message && message.message.direction === 'incoming');
                
                if (isIncoming) {
                    this.showMessageNotification({
                        conversation_id: data.conversation_id || message.conversation_id,
                        content: message.content || message.message?.content || 'Nova mensagem recebida',
                        contact_name: message.contact_name || message.sender_name || data.contact_name,
                        contact_avatar: message.contact_avatar || message.avatar || data.avatar
                    });
                }
            });
            
            document.addEventListener('realtime:new_conversation', (e) => {
                const data = e.detail || {};
                const conversation = data.conversation || data;
                
                this.showConversationNotification({
                    conversation_id: conversation.id || data.conversation_id,
                    contact_name: conversation.contact_name || conversation.contact?.name,
                    channel: conversation.channel || 'WhatsApp'
                });
            });
            
            document.addEventListener('realtime:conversation_assigned', (e) => {
                const data = e.detail || {};
                this.showAssignmentNotification({
                    conversation_id: data.conversation_id || data.id,
                    contact_name: data.contact_name || 'Contato'
                });
            });
            
            document.addEventListener('realtime:new_mention', (e) => {
                this.showMentionNotification(e.detail, 'invite_received');
            });
            
            document.addEventListener('realtime:mention_received', (e) => {
                this.showMentionNotification(e.detail, 'mention_received');
            });
            
            document.addEventListener('realtime:sla_warning', (e) => {
                this.showSLANotification(e.detail, 'sla_warning');
            });
            
            document.addEventListener('realtime:sla_breached', (e) => {
                this.showSLANotification(e.detail, 'sla_breached');
            });
            
            // Listener para selecionar conversa quando clicado na notificação
            document.addEventListener('notification:selectConversation', (e) => {
                console.log('[NotificationManager] Selecionando conversa:', e.detail.conversationId);
            });
            
            console.log('[NotificationManager] ✅ Event listeners configurados');
        },

        /**
         * Solicitar permissão para notificações do navegador
         */
        requestBrowserPermission: function() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        },

        /**
         * Mostrar notificação
         */
        show: function(options) {
            // Verificar se notificações estão habilitadas
            if (!this.settings.enabled) {
                console.log('[NotificationManager] Notificações visuais desabilitadas');
                return null;
            }
            
            const {
                type = 'info',
                title = null,
                message = '',
                sender = null,
                avatar = null,
                conversationId = null,
                duration = this.settings.duration || this.defaultDuration,
                playSound = true,
                onClick = null
            } = options;
            
            // Respeitar configuração de preview
            const displayMessage = this.settings.showPreview ? message : '';
            
            const typeConfig = this.types[type] || this.types.info;
            const id = ++this.notificationId;
            
            // Criar elemento
            const toast = document.createElement('div');
            toast.className = `notification-toast type-${typeConfig.color}`;
            toast.dataset.id = id;
            
            // Avatar ou ícone
            let avatarHtml = '';
            if (avatar) {
                avatarHtml = `<img src="${this.escapeHtml(avatar)}" alt="" class="notification-avatar">`;
            } else {
                avatarHtml = `
                    <div class="notification-icon bg-${typeConfig.color}">
                        <i class="ki-duotone ${typeConfig.icon} fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                    </div>
                `;
            }
            
            // Sender e mensagem
            let messageHtml = '';
            if (displayMessage) {
                if (sender) {
                    messageHtml = `<span class="notification-sender">${this.escapeHtml(sender)}:</span> ${this.escapeHtml(displayMessage)}`;
                } else {
                    messageHtml = this.escapeHtml(displayMessage);
                }
            } else if (sender) {
                messageHtml = `<span class="notification-sender">${this.escapeHtml(sender)}</span> enviou uma mensagem`;
            }
            
            toast.innerHTML = `
                ${avatarHtml}
                <div class="notification-content">
                    <div class="notification-title">
                        ${this.escapeHtml(title || typeConfig.title)}
                        <span class="notification-time">Agora</span>
                    </div>
                    <div class="notification-message">${messageHtml}</div>
                </div>
                <button class="notification-close" title="Fechar">×</button>
                <div class="notification-progress" style="animation-duration: ${duration}ms;"></div>
            `;
            
            // Event handlers
            toast.addEventListener('click', (e) => {
                if (e.target.classList.contains('notification-close')) {
                    this.dismiss(id);
                    return;
                }
                
                if (onClick) {
                    onClick();
                } else if (conversationId) {
                    this.navigateToConversation(conversationId);
                }
                
                this.dismiss(id);
            });
            
            // Pausar ao hover
            toast.addEventListener('mouseenter', () => {
                toast.classList.add('paused');
            });
            
            toast.addEventListener('mouseleave', () => {
                toast.classList.remove('paused');
            });
            
            // Adicionar ao container
            this.container.appendChild(toast);
            
            // Limitar quantidade visível
            this.limitVisible();
            
            // Animar entrada
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });
            
            // Tocar som
            if (playSound && typeConfig.sound && window.SoundManager) {
                window.SoundManager.play(typeConfig.sound);
            }
            
            // Auto-dismiss
            const timeoutId = setTimeout(() => {
                this.dismiss(id);
            }, duration);
            
            toast.dataset.timeoutId = timeoutId;
            
            // Notificação do navegador (se permitido, habilitado e aba não está focada)
            if (!document.hasFocus() && this.settings.browserNotificationsEnabled) {
                this.showBrowserNotification(title || typeConfig.title, displayMessage, conversationId);
            }
            
            return id;
        },

        /**
         * Fechar notificação
         */
        dismiss: function(id) {
            const toast = this.container.querySelector(`[data-id="${id}"]`);
            if (!toast) return;
            
            // Cancelar timeout
            if (toast.dataset.timeoutId) {
                clearTimeout(parseInt(toast.dataset.timeoutId));
            }
            
            // Animar saída
            toast.classList.remove('show');
            toast.classList.add('hide');
            
            // Remover após animação
            setTimeout(() => {
                toast.remove();
            }, 400);
        },

        /**
         * Limitar quantidade de notificações visíveis
         */
        limitVisible: function() {
            const toasts = this.container.querySelectorAll('.notification-toast');
            if (toasts.length > this.maxVisible) {
                const toRemove = toasts.length - this.maxVisible;
                for (let i = 0; i < toRemove; i++) {
                    const id = toasts[i].dataset.id;
                    this.dismiss(id);
                }
            }
        },

        /**
         * Navegar para conversa
         */
        navigateToConversation: function(conversationId) {
            const url = `/conversations/${conversationId}`;
            
            // Se já estamos na página de conversas, apenas selecionar a conversa
            if (window.location.pathname.includes('/conversations')) {
                // Emitir evento para o app selecionar a conversa
                document.dispatchEvent(new CustomEvent('notification:selectConversation', {
                    detail: { conversationId: conversationId }
                }));
                
                // Também atualizar a URL
                window.history.pushState({}, '', url);
                
                // Tentar selecionar a conversa na lista
                const conversationItem = document.querySelector(`[data-conversation-id="${conversationId}"]`);
                if (conversationItem) {
                    conversationItem.click();
                } else {
                    // Se não encontrar, recarregar a página
                    window.location.href = url;
                }
            } else {
                window.location.href = url;
            }
        },

        /**
         * Mostrar notificação do navegador
         */
        showBrowserNotification: function(title, message, conversationId) {
            if ('Notification' in window && Notification.permission === 'granted') {
                const notification = new Notification(title, {
                    body: message,
                    icon: '/assets/media/logos/favicon.ico',
                    tag: conversationId ? `conversation-${conversationId}` : undefined
                });
                
                notification.onclick = () => {
                    window.focus();
                    if (conversationId) {
                        this.navigateToConversation(conversationId);
                    }
                    notification.close();
                };
                
                // Auto-fechar após 5 segundos
                setTimeout(() => notification.close(), 5000);
            }
        },

        /**
         * Notificação de nova mensagem
         */
        showMessageNotification: function(data) {
            const contactName = data.contact_name || data.sender_name || 'Contato';
            const messagePreview = data.content || data.message || 'Nova mensagem recebida';
            const avatar = data.contact_avatar || data.avatar || null;
            
            this.show({
                type: 'new_message',
                title: 'Nova Mensagem',
                message: messagePreview,
                sender: contactName,
                avatar: avatar,
                conversationId: data.conversation_id
            });
        },

        /**
         * Notificação de nova conversa
         */
        showConversationNotification: function(data) {
            const contactName = data.contact_name || 'Novo contato';
            const channel = data.channel || 'WhatsApp';
            
            this.show({
                type: 'new_conversation',
                title: 'Nova Conversa',
                message: `${contactName} iniciou uma conversa via ${channel}`,
                conversationId: data.conversation_id || data.id
            });
        },

        /**
         * Notificação de atribuição
         */
        showAssignmentNotification: function(data) {
            const contactName = data.contact_name || 'Contato';
            
            this.show({
                type: 'conversation_assigned',
                title: 'Conversa Atribuída',
                message: `A conversa com ${contactName} foi atribuída a você`,
                conversationId: data.conversation_id || data.id
            });
        },

        /**
         * Notificação de menção
         */
        showMentionNotification: function(data, type) {
            const fromAgent = data.from_agent || data.agent_name || 'Um agente';
            const message = data.message || 'mencionou você em uma conversa';
            
            this.show({
                type: type,
                title: type === 'invite_received' ? 'Convite Recebido' : 'Você foi mencionado',
                message: message,
                sender: fromAgent,
                conversationId: data.conversation_id
            });
        },

        /**
         * Notificação de SLA
         */
        showSLANotification: function(data, type) {
            const contactName = data.contact_name || 'Conversa';
            const message = type === 'sla_warning' 
                ? `O SLA da conversa com ${contactName} está próximo de estourar`
                : `O SLA da conversa com ${contactName} foi estourado!`;
            
            this.show({
                type: type,
                message: message,
                conversationId: data.conversation_id,
                duration: type === 'sla_breached' ? 15000 : 10000 // SLA estourado fica mais tempo
            });
        },

        /**
         * Notificação simples de sucesso
         */
        success: function(message, options = {}) {
            return this.show({
                type: 'success',
                message: message,
                playSound: false,
                ...options
            });
        },

        /**
         * Notificação simples de erro
         */
        error: function(message, options = {}) {
            return this.show({
                type: 'error',
                message: message,
                playSound: false,
                duration: 10000,
                ...options
            });
        },

        /**
         * Notificação simples de informação
         */
        info: function(message, options = {}) {
            return this.show({
                type: 'info',
                message: message,
                playSound: false,
                ...options
            });
        },

        /**
         * Fechar todas as notificações
         */
        dismissAll: function() {
            const toasts = this.container.querySelectorAll('.notification-toast');
            toasts.forEach(toast => {
                this.dismiss(toast.dataset.id);
            });
        },

        /**
         * Escape HTML para prevenir XSS
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Expor globalmente
    window.NotificationManager = NotificationManager;

    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            NotificationManager.init();
        });
    } else {
        NotificationManager.init();
    }

})(window);

