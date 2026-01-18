<!-- Widget de Notificações de Campanhas -->
<div class="position-fixed bottom-0 end-0 mb-5 me-5" style="z-index: 1050; max-width: 400px;" id="notifications_container">
    <!-- Notificações aparecerão aqui -->
</div>

<script>
// Sistema de notificações em tempo real
class CampaignNotifications {
    constructor() {
        this.container = document.getElementById('notifications_container');
        this.notifications = [];
        this.maxVisible = 5;
        
        // Polling a cada 10 segundos
        this.startPolling();
    }
    
    startPolling() {
        this.fetchNotifications();
        setInterval(() => this.fetchNotifications(), 10000);
    }
    
    fetchNotifications() {
        fetch('/api/campaigns/notifications')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.notifications) {
                    data.notifications.forEach(notification => {
                        if (!this.notifications.find(n => n.id === notification.id)) {
                            this.show(notification);
                            this.notifications.push(notification);
                        }
                    });
                }
            })
            .catch(err => console.error('Erro ao buscar notificações:', err));
    }
    
    show(notification) {
        const data = typeof notification.data === 'string' 
            ? JSON.parse(notification.data) 
            : notification.data;
        
        const icon = data.icon || 'information';
        const color = data.color || 'primary';
        
        const html = `
            <div class="alert alert-dismissible bg-light-${color} border border-${color} d-flex flex-column flex-sm-row p-5 mb-3 notification-item" 
                 id="notification_${notification.id}" 
                 style="animation: slideInRight 0.5s ease-out;">
                <i class="ki-duotone ki-${icon} fs-2hx text-${color} me-4 mb-5 mb-sm-0">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="d-flex flex-column pe-0 pe-sm-10">
                    <h5 class="mb-1">${data.type}</h5>
                    <span>${data.message}</span>
                    ${data.stats ? `<div class="mt-2"><small class="text-muted">Taxa de resposta: ${data.stats.reply_rate}%</small></div>` : ''}
                </div>
                <button type="button" class="position-absolute position-sm-relative m-2 m-sm-0 top-0 end-0 btn btn-icon ms-sm-auto" 
                        onclick="campaignNotifications.dismiss(${notification.id})">
                    <i class="ki-duotone ki-cross fs-1 text-${color}">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </button>
            </div>
        `;
        
        this.container.insertAdjacentHTML('afterbegin', html);
        
        // Auto-dismiss após 10 segundos
        setTimeout(() => this.dismiss(notification.id), 10000);
        
        // Limitar quantidade visível
        const items = this.container.querySelectorAll('.notification-item');
        if (items.length > this.maxVisible) {
            items[items.length - 1].remove();
        }
    }
    
    dismiss(notificationId) {
        const element = document.getElementById(`notification_${notificationId}`);
        if (element) {
            element.style.animation = 'slideOutRight 0.5s ease-in';
            setTimeout(() => element.remove(), 500);
        }
        
        // Marcar como lida
        fetch('/api/campaigns/notifications/read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_ids: [notificationId] })
        });
    }
}

// Inicializar
let campaignNotifications;
document.addEventListener('DOMContentLoaded', () => {
    campaignNotifications = new CampaignNotifications();
});
</script>

<style>
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.notification-item {
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
</style>
