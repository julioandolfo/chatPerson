<?php
/**
 * Controller de Notificações
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Models\Notification;
use App\Services\NotificationService;

class NotificationController
{
    /**
     * Listar notificações do usuário atual
     */
    public function index(): void
    {
        $userId = \App\Helpers\Auth::id();
        if (!$userId) {
            Response::json([
                'success' => false,
                'message' => 'Usuário não autenticado',
                'notifications' => []
            ], 401);
            return;
        }
        
        $limit = Request::get('limit', 50);
        $offset = Request::get('offset', 0);
        
        try {
            $notifications = Notification::getByUser($userId, $limit, $offset);
            $unreadCount = Notification::countUnread($userId);
            
            Response::json([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'notifications' => [],
                'unread_count' => 0
            ], 500);
        }
    }

    /**
     * Obter notificações não lidas (para dropdown)
     */
    public function getUnread(): void
    {
        $userId = \App\Helpers\Auth::id();
        if (!$userId) {
            Response::json([
                'success' => false,
                'notifications' => [],
                'unread_count' => 0
            ], 401);
            return;
        }
        
        try {
            $notifications = NotificationService::getUnreadForCurrentUser(10);
            $unreadCount = NotificationService::countUnreadForCurrentUser();
            
            Response::json([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'notifications' => [],
                'unread_count' => 0
            ], 500);
        }
    }

    /**
     * Marcar notificação como lida
     */
    public function markAsRead(int $id): void
    {
        $userId = \App\Helpers\Auth::id();
        if (!$userId) {
            Response::json([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ], 401);
            return;
        }
        
        try {
            if (Notification::markAsRead($id, $userId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Notificação marcada como lida'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Notificação não encontrada ou já estava lida'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar todas as notificações como lidas
     */
    public function markAllAsRead(): void
    {
        $userId = \App\Helpers\Auth::id();
        if (!$userId) {
            Response::json([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ], 401);
            return;
        }
        
        try {
            if (Notification::markAllAsRead($userId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Todas as notificações foram marcadas como lidas'
                ]);
            } else {
                Response::json([
                    'success' => true,
                    'message' => 'Nenhuma notificação não lida encontrada'
                ]);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar notificação
     */
    public function destroy(int $id): void
    {
        $userId = \App\Helpers\Auth::id();
        if (!$userId) {
            Response::json([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ], 401);
            return;
        }
        
        try {
            // Verificar se a notificação pertence ao usuário
            $notification = Notification::find($id);
            if (!$notification || $notification['user_id'] != $userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Notificação não encontrada'
                ], 404);
                return;
            }
            
            if (Notification::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Notificação deletada com sucesso'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao deletar notificação'
                ], 500);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

