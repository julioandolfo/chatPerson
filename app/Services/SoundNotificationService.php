<?php
/**
 * Service SoundNotificationService
 * 
 * Gerenciamento de sons de notificação.
 */

namespace App\Services;

use App\Models\UserSoundSettings;
use App\Models\Setting;
use App\Helpers\Database;

class SoundNotificationService
{
    /**
     * Diretório de sons do sistema
     */
    public const SOUNDS_DIR = 'public/assets/sounds';
    
    /**
     * Diretório de sons personalizados (mesmo diretório dos sons do sistema para acesso direto)
     */
    public const CUSTOM_SOUNDS_DIR = 'public/assets/sounds';
    
    /**
     * Extensões permitidas
     */
    public const ALLOWED_EXTENSIONS = ['mp3', 'wav', 'ogg', 'webm'];
    
    /**
     * Tamanho máximo do arquivo (2MB)
     */
    public const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /**
     * Sons padrão do sistema
     */
    public const DEFAULT_SOUNDS = [
        'new-conversation.mp3' => [
            'name' => 'Nova Conversa',
            'category' => 'notification'
        ],
        'new-message.mp3' => [
            'name' => 'Nova Mensagem',
            'category' => 'notification'
        ],
        'assigned.mp3' => [
            'name' => 'Atribuído',
            'category' => 'notification'
        ],
        'invite.mp3' => [
            'name' => 'Convite',
            'category' => 'notification'
        ],
        'sla-warning.mp3' => [
            'name' => 'Aviso SLA',
            'category' => 'alert'
        ],
        'sla-breached.mp3' => [
            'name' => 'SLA Estourado',
            'category' => 'alert'
        ],
        'mention.mp3' => [
            'name' => 'Menção',
            'category' => 'notification'
        ],
        'success.mp3' => [
            'name' => 'Sucesso',
            'category' => 'success'
        ],
        'error.mp3' => [
            'name' => 'Erro',
            'category' => 'error'
        ],
        'pop.mp3' => [
            'name' => 'Pop',
            'category' => 'notification'
        ],
        'chime.mp3' => [
            'name' => 'Sino',
            'category' => 'notification'
        ],
        'ding.mp3' => [
            'name' => 'Ding',
            'category' => 'notification'
        ],
        'alert.mp3' => [
            'name' => 'Alerta',
            'category' => 'alert'
        ],
        'urgent.mp3' => [
            'name' => 'Urgente',
            'category' => 'alert'
        ]
    ];

    /**
     * Obter configurações de som de um usuário
     */
    public static function getUserSettings(int $userId): array
    {
        return UserSoundSettings::getOrCreate($userId);
    }

    /**
     * Atualizar configurações de som de um usuário
     */
    public static function updateUserSettings(int $userId, array $data): bool
    {
        return UserSoundSettings::updateSettings($userId, $data);
    }

    /**
     * Obter configurações padrão do sistema
     */
    public static function getSystemSettings(): array
    {
        try {
            $settings = Setting::get('system_sound_settings');
            if ($settings && is_array($settings)) {
                return $settings;
            }
        } catch (\Exception $e) {
            // Ignorar erro
        }
        
        // Retornar padrão
        return [
            'sounds_enabled' => true,
            'default_volume' => 70,
            'sounds' => UserSoundSettings::SOUND_EVENTS
        ];
    }

    /**
     * Atualizar configurações padrão do sistema
     */
    public static function updateSystemSettings(array $data): bool
    {
        try {
            return Setting::set('system_sound_settings', $data, 'json');
        } catch (\Exception $e) {
            error_log("Erro ao salvar configurações de som: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter lista de sons disponíveis (sistema + personalizados)
     */
    public static function getAvailableSounds(?int $userId = null): array
    {
        $sounds = [];
        
        // Sons do sistema
        foreach (self::DEFAULT_SOUNDS as $filename => $info) {
            $sounds[] = [
                'filename' => $filename,
                'name' => $info['name'],
                'category' => $info['category'],
                'is_system' => true,
                'url' => '/assets/sounds/' . $filename
            ];
        }
        
        // Sons personalizados do banco
        $sql = "SELECT * FROM custom_sounds WHERE user_id IS NULL OR user_id = ? ORDER BY name ASC";
        $customSounds = Database::fetchAll($sql, [$userId]);
        
        foreach ($customSounds as $sound) {
            $sounds[] = [
                'id' => $sound['id'],
                'filename' => $sound['filename'],
                'name' => $sound['name'],
                'category' => $sound['category'],
                'is_system' => (bool)$sound['is_system'],
                'is_custom' => !$sound['is_system'],
                'url' => '/assets/sounds/' . $sound['filename'] // Todos os sons agora ficam em assets/sounds
            ];
        }
        
        return $sounds;
    }

    /**
     * Fazer upload de som personalizado
     */
    public static function uploadCustomSound(array $file, int $userId, string $name): array
    {
        // Validar arquivo
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Arquivo inválido');
        }
        
        // Validar tamanho
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('Arquivo muito grande. Máximo: 2MB');
        }
        
        // Validar extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \InvalidArgumentException('Extensão não permitida. Use: ' . implode(', ', self::ALLOWED_EXTENSIONS));
        }
        
        // Criar diretório se não existir
        $baseDir = dirname(__DIR__, 2); // Volta 2 níveis: app/Services -> raiz
        $uploadDir = $baseDir . '/' . self::CUSTOM_SOUNDS_DIR;
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new \RuntimeException('Não foi possível criar o diretório de upload: ' . $uploadDir);
            }
        }
        
        // Verificar se o diretório é gravável
        if (!is_writable($uploadDir)) {
            throw new \RuntimeException('Diretório de upload não tem permissão de escrita: ' . $uploadDir);
        }
        
        // Gerar nome único
        $filename = 'custom_' . $userId . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filePath = $uploadDir . '/' . $filename;
        
        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new \RuntimeException('Erro ao salvar arquivo');
        }
        
        // Salvar no banco
        $sql = "INSERT INTO custom_sounds (user_id, name, filename, file_path, file_size, category) 
                VALUES (?, ?, ?, ?, ?, 'custom')";
        $id = Database::insert($sql, [$userId, $name, $filename, $filePath, $file['size']]);
        
        if (!$id) {
            unlink($filePath); // Remover arquivo se falhar
            throw new \RuntimeException('Erro ao salvar no banco de dados');
        }
        
        return [
            'id' => $id,
            'filename' => $filename,
            'name' => $name,
            'url' => '/assets/sounds/' . $filename
        ];
    }

    /**
     * Remover som personalizado
     */
    public static function deleteCustomSound(int $soundId, int $userId): bool
    {
        // Buscar som
        $sql = "SELECT * FROM custom_sounds WHERE id = ? AND user_id = ?";
        $sound = Database::fetch($sql, [$soundId, $userId]);
        
        if (!$sound) {
            throw new \InvalidArgumentException('Som não encontrado');
        }
        
        // Não permitir deletar sons do sistema
        if ($sound['is_system']) {
            throw new \InvalidArgumentException('Não é possível deletar sons do sistema');
        }
        
        // Remover arquivo
        if (file_exists($sound['file_path'])) {
            unlink($sound['file_path']);
        }
        
        // Remover do banco
        $sql = "DELETE FROM custom_sounds WHERE id = ?";
        return Database::query($sql, [$soundId]) !== false;
    }

    /**
     * Verificar se um arquivo de som é customizado ou do sistema
     */
    public static function isCustomSound(string $filename): bool
    {
        // Verificar se é som padrão do sistema
        if (isset(self::DEFAULT_SOUNDS[$filename])) {
            return false;
        }
        
        // Verificar no banco se é customizado
        $sql = "SELECT id FROM custom_sounds WHERE filename = ? AND is_system = 0 LIMIT 1";
        $result = Database::fetch($sql, [$filename]);
        return $result !== null;
    }

    /**
     * Obter URL completa de um arquivo de som
     */
    public static function getSoundUrl(string $filename): string
    {
        // Todos os sons agora ficam em /assets/sounds/ (sistema e customizados)
        return '/assets/sounds/' . $filename;
    }

    /**
     * Obter dados para tocar som de um evento
     */
    public static function getSoundDataForEvent(int $userId, string $event): ?array
    {
        // Verificar se está habilitado
        if (!UserSoundSettings::isSoundEnabled($userId, $event)) {
            return null;
        }
        
        // Obter arquivo e volume
        $soundFile = UserSoundSettings::getSoundFile($userId, $event);
        $volume = UserSoundSettings::getVolume($userId);
        
        if (!$soundFile) {
            return null;
        }
        
        return [
            'event' => $event,
            'sound' => $soundFile,
            'url' => self::getSoundUrl($soundFile),
            'volume' => $volume / 100 // Converter para 0-1
        ];
    }

    /**
     * Obter todos os eventos de som disponíveis
     */
    public static function getSoundEvents(): array
    {
        return UserSoundSettings::SOUND_EVENTS;
    }
}

