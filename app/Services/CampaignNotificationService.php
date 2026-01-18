<?php
/**
 * Service CampaignNotificationService
 * Notificações em tempo real para campanhas
 */

namespace App\Services;

use App\Helpers\Logger;
use App\Helpers\Database;

class CampaignNotificationService
{
    /**
     * Enviar notificação de campanha iniciada
     */
    public static function notifyCampaignStarted(int $campaignId, array $campaign): void
    {
        self::sendNotification([
            'type' => 'campaign_started',
            'campaign_id' => $campaignId,
            'campaign_name' => $campaign['name'],
            'message' => "Campanha '{$campaign['name']}' foi iniciada",
            'icon' => 'play',
            'color' => 'success'
        ]);
    }

    /**
     * Enviar notificação de campanha concluída
     */
    public static function notifyCampaignCompleted(int $campaignId, array $campaign, array $stats): void
    {
        self::sendNotification([
            'type' => 'campaign_completed',
            'campaign_id' => $campaignId,
            'campaign_name' => $campaign['name'],
            'message' => "Campanha '{$campaign['name']}' foi concluída! {$stats['total_sent']} mensagens enviadas",
            'icon' => 'verify',
            'color' => 'primary',
            'stats' => $stats
        ]);
    }

    /**
     * Enviar notificação de erro crítico
     */
    public static function notifyCriticalError(int $campaignId, string $error): void
    {
        self::sendNotification([
            'type' => 'campaign_error',
            'campaign_id' => $campaignId,
            'message' => "Erro crítico na campanha #{$campaignId}: {$error}",
            'icon' => 'cross-circle',
            'color' => 'danger'
        ]);
    }

    /**
     * Enviar notificação de milestone (25%, 50%, 75%, 100%)
     */
    public static function notifyMilestone(int $campaignId, array $campaign, float $progress): void
    {
        $milestones = [25, 50, 75, 100];
        $currentMilestone = null;

        foreach ($milestones as $milestone) {
            if ($progress >= $milestone && $progress < $milestone + 1) {
                $currentMilestone = $milestone;
                break;
            }
        }

        if ($currentMilestone) {
            self::sendNotification([
                'type' => 'campaign_milestone',
                'campaign_id' => $campaignId,
                'campaign_name' => $campaign['name'],
                'message' => "Campanha '{$campaign['name']}' atingiu {$currentMilestone}% de progresso",
                'icon' => 'chart-line',
                'color' => 'info',
                'progress' => $progress
            ]);
        }
    }

    /**
     * Enviar notificação genérica
     */
    private static function sendNotification(array $notification): void
    {
        // Salvar no banco para busca posterior
        $sql = "INSERT INTO campaign_notifications (type, campaign_id, message, data, created_at) 
                VALUES (?, ?, ?, ?, NOW())";

        Database::execute($sql, [
            $notification['type'],
            $notification['campaign_id'] ?? null,
            $notification['message'],
            json_encode($notification)
        ]);

        // Enviar via WebSocket se configurado
        if (class_exists('\App\Services\WebSocketService')) {
            try {
                \App\Services\WebSocketService::broadcast('campaign_notification', $notification);
            } catch (\Exception $e) {
                Logger::error("Erro ao enviar notificação WebSocket: " . $e->getMessage());
            }
        }

        Logger::info("Notificação enviada: " . $notification['type']);
    }

    /**
     * Buscar notificações recentes
     */
    public static function getRecent(int $limit = 20): array
    {
        $sql = "SELECT * FROM campaign_notifications 
                ORDER BY created_at DESC 
                LIMIT ?";

        return Database::fetchAll($sql, [$limit]);
    }

    /**
     * Marcar notificações como lidas
     */
    public static function markAsRead(array $notificationIds): bool
    {
        if (empty($notificationIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $sql = "UPDATE campaign_notifications SET read_at = NOW() WHERE id IN ({$placeholders})";

        return Database::execute($sql, $notificationIds) > 0;
    }
}
