<?php
/**
 * Model UserSoundSettings
 * 
 * Configurações de som de notificação por usuário.
 */

namespace App\Models;

use App\Helpers\Database;

class UserSoundSettings extends Model
{
    protected string $table = 'user_sound_settings';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id',
        'sounds_enabled',
        'volume',
        'new_conversation_enabled',
        'new_conversation_sound',
        'new_message_enabled',
        'new_message_sound',
        'conversation_assigned_enabled',
        'conversation_assigned_sound',
        'invite_received_enabled',
        'invite_received_sound',
        'sla_warning_enabled',
        'sla_warning_sound',
        'sla_breached_enabled',
        'sla_breached_sound',
        'mention_received_enabled',
        'mention_received_sound',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end'
    ];
    protected bool $timestamps = true;

    /**
     * Tipos de eventos de som disponíveis
     */
    public const SOUND_EVENTS = [
        'new_conversation' => [
            'label' => 'Nova Conversa',
            'description' => 'Quando uma nova conversa é criada',
            'icon' => 'ki-message-add'
        ],
        'new_message' => [
            'label' => 'Nova Mensagem',
            'description' => 'Quando uma nova mensagem é recebida',
            'icon' => 'ki-message-text'
        ],
        'conversation_assigned' => [
            'label' => 'Conversa Atribuída',
            'description' => 'Quando uma conversa é atribuída a você',
            'icon' => 'ki-user-tick'
        ],
        'invite_received' => [
            'label' => 'Convite para Conversa',
            'description' => 'Quando você recebe um convite para participar',
            'icon' => 'ki-notification-on'
        ],
        'sla_warning' => [
            'label' => 'Aviso de SLA',
            'description' => 'Quando o SLA está próximo de estourar',
            'icon' => 'ki-timer'
        ],
        'sla_breached' => [
            'label' => 'SLA Estourado',
            'description' => 'Quando o SLA foi estourado',
            'icon' => 'ki-shield-cross'
        ],
        'mention_received' => [
            'label' => 'Menção Recebida',
            'description' => 'Quando você é mencionado em uma conversa',
            'icon' => 'ki-user'
        ]
    ];

    /**
     * Obter configurações de um usuário
     */
    public static function getByUser(int $userId): ?array
    {
        $sql = "SELECT * FROM user_sound_settings WHERE user_id = ?";
        return Database::fetch($sql, [$userId]);
    }

    /**
     * Obter ou criar configurações para um usuário
     */
    public static function getOrCreate(int $userId): array
    {
        $settings = self::getByUser($userId);
        
        if (!$settings) {
            // Criar com valores padrão
            $sql = "INSERT INTO user_sound_settings (user_id) VALUES (?)";
            Database::query($sql, [$userId]);
            $settings = self::getByUser($userId);
        }
        
        return $settings ?: self::getDefaults($userId);
    }

    /**
     * Valores padrão
     */
    public static function getDefaults(int $userId): array
    {
        return [
            'user_id' => $userId,
            'sounds_enabled' => 1,
            'volume' => 70,
            'new_conversation_enabled' => 1,
            'new_conversation_sound' => 'new-conversation.mp3',
            'new_message_enabled' => 1,
            'new_message_sound' => 'new-message.mp3',
            'conversation_assigned_enabled' => 1,
            'conversation_assigned_sound' => 'assigned.mp3',
            'invite_received_enabled' => 1,
            'invite_received_sound' => 'invite.mp3',
            'sla_warning_enabled' => 1,
            'sla_warning_sound' => 'sla-warning.mp3',
            'sla_breached_enabled' => 1,
            'sla_breached_sound' => 'sla-breached.mp3',
            'mention_received_enabled' => 1,
            'mention_received_sound' => 'mention.mp3',
            'quiet_hours_enabled' => 0,
            'quiet_hours_start' => '22:00:00',
            'quiet_hours_end' => '08:00:00'
        ];
    }

    /**
     * Atualizar configurações do usuário
     */
    public static function updateSettings(int $userId, array $data): bool
    {
        // Garantir que existe
        self::getOrCreate($userId);
        
        // Construir campos para atualização
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'sounds_enabled', 'volume',
            'new_conversation_enabled', 'new_conversation_sound',
            'new_message_enabled', 'new_message_sound',
            'conversation_assigned_enabled', 'conversation_assigned_sound',
            'invite_received_enabled', 'invite_received_sound',
            'sla_warning_enabled', 'sla_warning_sound',
            'sla_breached_enabled', 'sla_breached_sound',
            'mention_received_enabled', 'mention_received_sound',
            'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end'
        ];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "{$key} = ?";
                $params[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE user_sound_settings SET " . implode(', ', $fields) . " WHERE user_id = ?";
        
        return Database::query($sql, $params) !== false;
    }

    /**
     * Verificar se som está habilitado para um evento
     */
    public static function isSoundEnabled(int $userId, string $event): bool
    {
        $settings = self::getOrCreate($userId);
        
        // Verificar se sons estão habilitados globalmente
        if (!$settings['sounds_enabled']) {
            return false;
        }
        
        // Verificar horário silencioso
        if ($settings['quiet_hours_enabled']) {
            $now = date('H:i:s');
            $start = $settings['quiet_hours_start'];
            $end = $settings['quiet_hours_end'];
            
            // Horário silencioso pode cruzar meia-noite
            if ($start > $end) {
                if ($now >= $start || $now <= $end) {
                    return false;
                }
            } else {
                if ($now >= $start && $now <= $end) {
                    return false;
                }
            }
        }
        
        // Verificar se evento específico está habilitado
        $field = $event . '_enabled';
        return (bool)($settings[$field] ?? true);
    }

    /**
     * Obter arquivo de som para um evento
     */
    public static function getSoundFile(int $userId, string $event): ?string
    {
        $settings = self::getOrCreate($userId);
        $field = $event . '_sound';
        return $settings[$field] ?? null;
    }

    /**
     * Obter volume do usuário
     */
    public static function getVolume(int $userId): int
    {
        $settings = self::getOrCreate($userId);
        return (int)($settings['volume'] ?? 70);
    }
}

