<?php
/**
 * Componente de Dropdown de Notificações
 */
$userId = \App\Helpers\Auth::id();
$unreadCount = 0;
$notifications = [];

if ($userId) {
    try {
        $unreadCount = \App\Services\NotificationService::countUnreadForCurrentUser();
        $notifications = \App\Services\NotificationService::getUnreadForCurrentUser(10);
    } catch (\Exception $e) {
        // Ignorar erros se tabela não existir ainda
    }
}
?>
<!--begin::Notifications-->
<div class="btn btn-icon btn-color-gray-700 btn-active-color-primary btn-outline w-40px h-40px position-relative" 
     id="kt_notifications_toggle" 
     data-kt-menu-trigger="click" 
     data-kt-menu-placement="bottom-end"
     data-kt-menu-attach="parent">
    <i class="ki-duotone ki-notification-bing fs-1">
        <span class="path1"></span>
        <span class="path2"></span>
        <span class="path3"></span>
    </i>
    <?php if ($unreadCount > 0): ?>
    <!--begin::Badge-->
    <span class="badge badge-dot bg-danger position-absolute translate-middle top-0 start-50 h-6px w-6px" id="kt_notifications_badge">
        <span class="badge-label"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
    </span>
    <!--end::Badge-->
    <?php endif; ?>
    <!--begin::Menu-->
    <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-400px" 
         data-kt-menu="true" 
         id="kt_notifications_menu">
    <!--begin::Heading-->
    <div class="d-flex flex-stack py-4 px-6">
        <h3 class="fw-bold m-0">Notificações</h3>
        <?php if ($unreadCount > 0): ?>
        <button type="button" class="btn btn-sm btn-light" id="kt_notifications_mark_all_read">
            Marcar todas como lidas
        </button>
        <?php endif; ?>
    </div>
    <!--end::Heading-->
    
    <!--begin::Separator-->
    <div class="separator"></div>
    <!--end::Separator-->
    
    <!--begin::Items-->
    <div class="scroll-y mh-325px my-5 px-8" id="kt_notifications_items">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-10">
                <i class="ki-duotone ki-notification-off fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma notificação</h3>
                <div class="text-gray-500 fs-6">Você está em dia!</div>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="d-flex flex-stack py-4 notification-item" data-notification-id="<?= $notification['id'] ?>">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-35px me-4">
                            <?php
                            $iconClass = 'ki-chat';
                            $iconColor = 'primary';
                            switch($notification['type']) {
                                case 'message':
                                    $iconClass = 'ki-message-text-2';
                                    $iconColor = 'primary';
                                    break;
                                case 'assignment':
                                    $iconClass = 'ki-user';
                                    $iconColor = 'success';
                                    break;
                                case 'conversation':
                                    $iconClass = 'ki-chat';
                                    $iconColor = 'info';
                                    break;
                            }
                            ?>
                            <div class="symbol-label bg-light-<?= $iconColor ?>">
                                <i class="ki-duotone <?= $iconClass ?> fs-2 text-<?= $iconColor ?>">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </div>
                        </div>
                        <div class="d-flex flex-column flex-grow-1 me-2">
                            <a href="<?= htmlspecialchars($notification['link'] ?? '#') ?>" 
                               class="text-gray-800 text-hover-primary fs-6 fw-bold notification-link">
                                <?= htmlspecialchars($notification['title']) ?>
                            </a>
                            <span class="text-muted fs-7 fw-semibold">
                                <?= htmlspecialchars($notification['message']) ?>
                            </span>
                            <span class="text-muted fs-8 mt-1">
                                <?= \App\Helpers\Url::timeAgo($notification['created_at']) ?>
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-icon btn-active-color-primary notification-mark-read" 
                            data-notification-id="<?= $notification['id'] ?>"
                            title="Marcar como lida">
                        <i class="ki-duotone ki-check fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <!--end::Items-->
    
    <!--begin::Footer-->
    <div class="py-3 text-center border-top">
        <a href="#" class="btn btn-color-gray-600 btn-active-color-primary" id="kt_notifications_view_all">
            Ver todas as notificações
        </a>
    </div>
    <!--end::Footer-->
    </div>
    <!--end::Menu-->
</div>
<!--end::Notifications-->

<script>
document.addEventListener("DOMContentLoaded", function() {
    const notificationsToggle = document.getElementById("kt_notifications_toggle");
    const notificationsMenu = document.getElementById("kt_notifications_menu");
    const markAllReadBtn = document.getElementById("kt_notifications_mark_all_read");
    const markReadBtns = document.querySelectorAll(".notification-mark-read");
    
    // Marcar notificação individual como lida
    markReadBtns.forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const notificationId = this.getAttribute("data-notification-id");
            const notificationItem = this.closest(".notification-item");
            
            fetch("<?= \App\Helpers\Url::to('/notifications') ?>/" + notificationId + "/read", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notificationItem.style.opacity = "0.5";
                    setTimeout(() => {
                        notificationItem.remove();
                        updateUnreadCount();
                    }, 300);
                }
            })
            .catch(error => {
                console.error("Erro ao marcar notificação como lida:", error);
            });
        });
    });
    
    // Marcar todas como lidas
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener("click", function(e) {
            e.preventDefault();
            
            fetch("<?= \App\Helpers\Url::to('/notifications/read-all') ?>", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error("Erro ao marcar todas como lidas:", error);
            });
        });
    }
    
    // Atualizar contador de não lidas
    function updateUnreadCount() {
        fetch("<?= \App\Helpers\Url::to('/notifications/unread') ?>", {
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById("kt_notifications_badge");
                if (data.unread_count > 0) {
                    if (!badge) {
                        const toggle = document.getElementById("kt_notifications_toggle");
                        const newBadge = document.createElement("span");
                        newBadge.className = "badge badge-dot bg-danger position-absolute translate-middle top-0 start-50 h-6px w-6px";
                        newBadge.id = "kt_notifications_badge";
                        newBadge.innerHTML = '<span class="badge-label">' + (data.unread_count > 9 ? '9+' : data.unread_count) + '</span>';
                        toggle.appendChild(newBadge);
                    } else {
                        badge.querySelector(".badge-label").textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                    }
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }
            }
        })
        .catch(error => {
            console.error("Erro ao atualizar contador:", error);
        });
    }
    
    // Atualizar a cada 30 segundos
    setInterval(updateUnreadCount, 30000);
    
    // Atualizar quando menu for aberto e ajustar posicionamento
    if (notificationsToggle && notificationsMenu) {
        notificationsToggle.addEventListener("click", function() {
            setTimeout(updateUnreadCount, 100);
            
            // Ajustar posicionamento do menu após ser exibido pelo Metronic
            setTimeout(function() {
                if (notificationsMenu && notificationsMenu.offsetParent !== null) {
                    const toggleRect = notificationsToggle.getBoundingClientRect();
                    const menuRect = notificationsMenu.getBoundingClientRect();
                    
                    // Se o menu estiver muito à direita, ajustar
                    if (menuRect.right > window.innerWidth - 20) {
                        const offset = menuRect.right - (window.innerWidth - 20);
                        notificationsMenu.style.transform = `translateX(-${offset}px)`;
                    }
                    
                    // Se o menu estiver muito à esquerda, ajustar
                    if (menuRect.left < 20) {
                        const offset = 20 - menuRect.left;
                        notificationsMenu.style.transform = `translateX(${offset}px)`;
                    }
                }
            }, 150);
        });
    }
});
</script>

