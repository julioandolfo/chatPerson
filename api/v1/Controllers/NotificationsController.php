<?php
/**
 * NotificationsController - API v1
 * Notificações in-app do usuário autenticado
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\Notification;

class NotificationsController
{
    /**
     * Listar notificações
     * GET /api/v1/notifications?page=&per_page=
     */
    public function index(): void
    {
        try {
            $userId = ApiAuthMiddleware::userId();
            $page = max((int)($_GET['page'] ?? 1), 1);
            $perPage = min(max((int)($_GET['per_page'] ?? 30), 1), 100);
            $offset = ($page - 1) * $perPage;

            $notifications = Notification::getByUser($userId, $perPage, $offset);
            $unreadCount = Notification::countUnread($userId);

            ApiResponse::success([
                'items' => $notifications,
                'unread_count' => $unreadCount,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_next' => count($notifications) === $perPage,
                ],
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar notificações', $e);
        }
    }

    /**
     * Notificações não lidas (badge)
     * GET /api/v1/notifications/unread
     */
    public function unread(): void
    {
        try {
            $userId = ApiAuthMiddleware::userId();
            $notifications = Notification::getByUser($userId, 10, 0);
            $unread = array_values(array_filter($notifications, fn($n) => empty($n['read_at']) && empty($n['is_read'])));

            ApiResponse::success([
                'count' => Notification::countUnread($userId),
                'items' => $unread,
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter notificações não lidas', $e);
        }
    }

    /**
     * Marcar notificação como lida
     * POST /api/v1/notifications/:id/read
     */
    public function markRead(string $id): void
    {
        try {
            Notification::markAsRead((int)$id, ApiAuthMiddleware::userId());
            ApiResponse::success(null, 200, 'Notificação marcada como lida');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao marcar notificação', $e);
        }
    }

    /**
     * Marcar todas como lidas
     * POST /api/v1/notifications/read-all
     */
    public function markAllRead(): void
    {
        try {
            Notification::markAllAsRead(ApiAuthMiddleware::userId());
            ApiResponse::success(null, 200, 'Todas as notificações foram marcadas como lidas');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao marcar notificações', $e);
        }
    }
}
