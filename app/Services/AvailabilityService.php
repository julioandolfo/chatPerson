<?php
/**
 * Service AvailabilityService
 * Gerencia disponibilidade dinâmica dos agentes
 */

namespace App\Services;

use App\Models\User;
use App\Models\Setting;
use App\Helpers\Database;
use App\Helpers\WebSocket;

class AvailabilityService
{
    /**
     * Obter configurações de disponibilidade
     */
    public static function getSettings(): array
    {
        return [
            'auto_online_on_login' => Setting::get('availability.auto_online_on_login', true),
            'auto_offline_on_logout' => Setting::get('availability.auto_offline_on_logout', true),
            'auto_away_enabled' => Setting::get('availability.auto_away_enabled', true),
            'away_timeout_minutes' => (int)Setting::get('availability.away_timeout_minutes', 15),
            'activity_tracking_enabled' => Setting::get('availability.activity_tracking_enabled', true),
            'heartbeat_interval_seconds' => (int)Setting::get('availability.heartbeat_interval_seconds', 30),
            'offline_timeout_minutes' => (int)Setting::get('availability.offline_timeout_minutes', 5),
            'track_mouse_movement' => Setting::get('availability.track_mouse_movement', false),
            'track_keyboard' => Setting::get('availability.track_keyboard', true),
            'track_page_visibility' => Setting::get('availability.track_page_visibility', true),
        ];
    }

    /**
     * Obter configurações de horário comercial
     */
    public static function getBusinessHoursSettings(): array
    {
        return [
            'enabled' => Setting::get('business_hours.enabled', false),
            'timezone' => Setting::get('business_hours.timezone', 'America/Sao_Paulo'),
            'monday_start' => Setting::get('business_hours.monday_start', '09:00'),
            'monday_end' => Setting::get('business_hours.monday_end', '18:00'),
            'tuesday_start' => Setting::get('business_hours.tuesday_start', '09:00'),
            'tuesday_end' => Setting::get('business_hours.tuesday_end', '18:00'),
            'wednesday_start' => Setting::get('business_hours.wednesday_start', '09:00'),
            'wednesday_end' => Setting::get('business_hours.wednesday_end', '18:00'),
            'thursday_start' => Setting::get('business_hours.thursday_start', '09:00'),
            'thursday_end' => Setting::get('business_hours.thursday_end', '18:00'),
            'friday_start' => Setting::get('business_hours.friday_start', '09:00'),
            'friday_end' => Setting::get('business_hours.friday_end', '18:00'),
            'saturday_start' => Setting::get('business_hours.saturday_start', null),
            'saturday_end' => Setting::get('business_hours.saturday_end', null),
            'sunday_start' => Setting::get('business_hours.sunday_start', null),
            'sunday_end' => Setting::get('business_hours.sunday_end', null),
        ];
    }

    /**
     * Verificar se está em horário comercial
     */
    public static function isBusinessHours(?string $datetime = null): bool
    {
        $settings = self::getBusinessHoursSettings();
        
        if (!$settings['enabled']) {
            return true; // Se não está habilitado, sempre considera horário comercial
        }

        $datetime = $datetime ?? date('Y-m-d H:i:s');
        $timezone = new \DateTimeZone($settings['timezone']);
        $dt = new \DateTime($datetime, $timezone);
        $dayOfWeek = (int)$dt->format('w'); // 0 = domingo, 6 = sábado
        $time = $dt->format('H:i');

        $dayMap = [
            0 => 'sunday',
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday'
        ];

        $day = $dayMap[$dayOfWeek];
        $startKey = "{$day}_start";
        $endKey = "{$day}_end";

        $start = $settings[$startKey] ?? null;
        $end = $settings[$endKey] ?? null;

        // Se não tem horário configurado para o dia, não é horário comercial
        if ($start === null || $end === null) {
            return false;
        }

        return $time >= $start && $time <= $end;
    }

    /**
     * Atualizar atividade do usuário
     */
    public static function updateActivity(int $userId, ?string $activityType = null): void
    {
        $settings = self::getSettings();
        
        if (!$settings['activity_tracking_enabled']) {
            return;
        }

        $data = [
            'last_activity_at' => date('Y-m-d H:i:s')
        ];

        // Se estava 'away' e teve atividade, voltar para 'online'
        $user = User::find($userId);
        if ($user && ($user['availability_status'] ?? 'offline') === 'away') {
            self::updateAvailabilityStatus($userId, 'online', 'activity_detected');
        } else {
            User::update($userId, $data);
        }
    }

    /**
     * Processar heartbeat
     */
    public static function processHeartbeat(int $userId): void
    {
        self::updateActivity($userId, 'heartbeat');
        self::checkAndUpdateStatus($userId);
    }

    /**
     * Verificar e atualizar status baseado em regras automáticas
     */
    public static function checkAndUpdateStatus(int $userId): void
    {
        $settings = self::getSettings();
        $user = User::find($userId);

        if (!$user) {
            return;
        }

        $currentStatus = $user['availability_status'] ?? 'offline';
        $lastActivity = $user['last_activity_at'] ?? null;

        if (!$lastActivity) {
            return;
        }

        $now = new \DateTime();
        $lastActivityDt = new \DateTime($lastActivity);
        $diffMinutes = ($now->getTimestamp() - $lastActivityDt->getTimestamp()) / 60;

        // Se está online e passou do timeout de away
        if ($currentStatus === 'online' && $settings['auto_away_enabled']) {
            if ($diffMinutes >= $settings['away_timeout_minutes']) {
                self::updateAvailabilityStatus($userId, 'away', 'inactivity_timeout');
            }
        }

        // Se está away ou online e passou do timeout de offline (sem heartbeat)
        if (in_array($currentStatus, ['online', 'away'])) {
            $lastSeen = $user['last_seen_at'] ?? null;
            if ($lastSeen) {
                $lastSeenDt = new \DateTime($lastSeen);
                $offlineDiffMinutes = ($now->getTimestamp() - $lastSeenDt->getTimestamp()) / 60;
                
                if ($offlineDiffMinutes >= $settings['offline_timeout_minutes']) {
                    self::updateAvailabilityStatus($userId, 'offline', 'heartbeat_timeout');
                }
            }
        }
    }

    /**
     * Atualizar status de disponibilidade (com histórico)
     */
    public static function updateAvailabilityStatus(int $userId, string $status, ?string $reason = null): bool
    {
        $allowedStatuses = ['online', 'offline', 'away', 'busy'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        $oldStatus = $user['availability_status'] ?? 'offline';

        // Se não mudou, não fazer nada
        if ($oldStatus === $status) {
            return true;
        }

        // Fechar registro anterior no histórico
        self::closeCurrentHistoryRecord($userId);

        // Atualizar status no usuário
        $data = [
            'availability_status' => $status,
            'last_seen_at' => date('Y-m-d H:i:s')
        ];

        if ($status === 'online') {
            $data['last_activity_at'] = date('Y-m-d H:i:s');
        } else {
            // Manter last_activity_at mesmo quando não está online (para cálculo de inatividade)
            // Não atualizar aqui, apenas quando houver atividade real
        }

        $result = User::update($userId, $data);

        // Criar novo registro no histórico
        self::createHistoryRecord($userId, $status, $reason);

        // Notificar via WebSocket
        try {
            WebSocket::notifyAgentStatus($userId, $status);
        } catch (\Exception $e) {
            error_log("Erro ao notificar status via WebSocket: " . $e->getMessage());
        }

        // Log de atividade se mudou
        if ($oldStatus !== $status) {
            try {
                if (class_exists('\App\Services\ActivityService')) {
                    \App\Services\ActivityService::logAvailabilityChanged($userId, $status, $oldStatus, \App\Helpers\Auth::id());
                }
            } catch (\Exception $e) {
                error_log("Erro ao logar atividade: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Criar registro no histórico
     */
    private static function createHistoryRecord(int $userId, string $status, ?string $reason = null): void
    {
        $isBusinessHours = self::isBusinessHours();
        
        $sql = "INSERT INTO user_availability_history 
                (user_id, status, started_at, is_business_hours, metadata) 
                VALUES (?, ?, NOW(), ?, ?)";
        
        $metadata = [];
        if ($reason) {
            $metadata['reason'] = $reason;
        }
        
        Database::execute($sql, [
            $userId,
            $status,
            $isBusinessHours ? 1 : 0,
            !empty($metadata) ? json_encode($metadata) : null
        ]);
    }

    /**
     * Fechar registro atual no histórico
     */
    private static function closeCurrentHistoryRecord(int $userId): void
    {
        $sql = "UPDATE user_availability_history 
                SET ended_at = NOW(),
                    duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
                WHERE user_id = ? 
                AND ended_at IS NULL
                ORDER BY started_at DESC
                LIMIT 1";
        
        Database::execute($sql, [$userId]);
    }

    /**
     * Calcular tempo em cada status para um período
     */
    public static function getTimeInStatus(int $userId, ?string $dateFrom = null, ?string $dateTo = null, bool $onlyBusinessHours = false): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d 23:59:59');

        // Buscar histórico no período
        $sql = "SELECT status, 
                       SUM(COALESCE(duration_seconds, TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW())))) as total_seconds,
                       COUNT(*) as count
                FROM user_availability_history
                WHERE user_id = ? 
                AND started_at >= ?
                AND started_at <= ?";
        
        $params = [$userId, $dateFrom, $dateTo];

        if ($onlyBusinessHours) {
            $sql .= " AND is_business_hours = 1";
        }

        $sql .= " GROUP BY status";

        $results = Database::fetchAll($sql, $params);

        $timeInStatus = [
            'online' => 0,
            'offline' => 0,
            'away' => 0,
            'busy' => 0
        ];

        foreach ($results as $row) {
            $status = $row['status'];
            if (isset($timeInStatus[$status])) {
                $timeInStatus[$status] = (int)$row['total_seconds'];
            }
        }

        return $timeInStatus;
    }

    /**
     * Formatar tempo em formato legível
     */
    public static function formatTime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $hours . 'h ' . $remainingMinutes . ' min';
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        return $days . 'd ' . $remainingHours . 'h';
    }

    /**
     * Obter estatísticas de disponibilidade para dashboard
     */
    public static function getAvailabilityStats(int $userId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $businessHoursOnly = self::getBusinessHoursSettings()['enabled'];
        
        $timeInStatus = self::getTimeInStatus($userId, $dateFrom, $dateTo, $businessHoursOnly);
        
        $totalSeconds = array_sum($timeInStatus);
        
        $stats = [];
        foreach ($timeInStatus as $status => $seconds) {
            $stats[$status] = [
                'seconds' => $seconds,
                'formatted' => self::formatTime($seconds),
                'percentage' => $totalSeconds > 0 ? round(($seconds / $totalSeconds) * 100, 1) : 0
            ];
        }

        return $stats;
    }

    /**
     * Marcar como online no login
     */
    public static function markOnlineOnLogin(int $userId): void
    {
        $settings = self::getSettings();
        
        if ($settings['auto_online_on_login']) {
            self::updateAvailabilityStatus($userId, 'online', 'login');
        }
    }

    /**
     * Marcar como offline no logout
     */
    public static function markOfflineOnLogout(int $userId): void
    {
        $settings = self::getSettings();
        
        if ($settings['auto_offline_on_logout']) {
            self::updateAvailabilityStatus($userId, 'offline', 'logout');
        }
    }
}

