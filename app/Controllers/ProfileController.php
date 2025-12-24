<?php
/**
 * Controller de Perfil do Usuário
 * 
 * Gerencia as preferências pessoais do usuário logado.
 */

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Response;
use App\Helpers\Request;
use App\Models\User;
use App\Models\UserSoundSettings;
use App\Services\SoundNotificationService;

class ProfileController
{
    /**
     * Página principal de perfil/preferências
     * GET /profile
     */
    public function index(): void
    {
        $userId = Auth::id();
        $user = User::find($userId);
        
        // Carregar configurações de som
        $soundSettings = UserSoundSettings::getOrCreate($userId);
        $availableSounds = SoundNotificationService::getAvailableSounds($userId);
        
        Response::view('profile/index', [
            'pageTitle' => 'Meu Perfil',
            'user' => $user,
            'soundSettings' => $soundSettings,
            'availableSounds' => $availableSounds,
            'activeTab' => Request::get('tab', 'notifications')
        ]);
    }

    /**
     * Aba de notificações
     * GET /profile/notifications
     */
    public function notifications(): void
    {
        $userId = Auth::id();
        $user = User::find($userId);
        
        // Carregar configurações de som
        $soundSettings = UserSoundSettings::getOrCreate($userId);
        $availableSounds = SoundNotificationService::getAvailableSounds($userId);
        
        Response::view('profile/index', [
            'pageTitle' => 'Minhas Notificações',
            'user' => $user,
            'soundSettings' => $soundSettings,
            'availableSounds' => $availableSounds,
            'activeTab' => 'notifications'
        ]);
    }

    /**
     * Salvar configurações de notificação
     * POST /profile/notifications
     */
    public function saveNotifications(): void
    {
        $userId = Auth::id();
        
        try {
            $data = Request::json() ?: $_POST;
            
            // Processar campos booleanos
            $booleanFields = [
                'sounds_enabled', 
                'new_conversation_enabled', 'new_message_enabled',
                'conversation_assigned_enabled', 'invite_received_enabled',
                'sla_warning_enabled', 'sla_breached_enabled', 'mention_received_enabled',
                'quiet_hours_enabled',
                'visual_notifications_enabled', 'browser_notifications_enabled',
                'show_notification_preview'
            ];
            
            foreach ($booleanFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                }
            }
            
            // Validar volume
            if (isset($data['volume'])) {
                $data['volume'] = max(0, min(100, (int)$data['volume']));
            }
            
            // Validar duração das notificações
            if (isset($data['notification_duration'])) {
                $data['notification_duration'] = max(3000, min(60000, (int)$data['notification_duration']));
            }
            
            // Validar máximo de notificações visíveis
            if (isset($data['max_visible_notifications'])) {
                $data['max_visible_notifications'] = max(1, min(10, (int)$data['max_visible_notifications']));
            }
            
            // Validar posição
            if (isset($data['notification_position'])) {
                $validPositions = ['bottom-right', 'bottom-left', 'top-right', 'top-left'];
                if (!in_array($data['notification_position'], $validPositions)) {
                    $data['notification_position'] = 'bottom-right';
                }
            }
            
            $success = SoundNotificationService::updateUserSettings($userId, $data);
            
            Response::json([
                'success' => $success,
                'message' => $success ? 'Configurações salvas com sucesso!' : 'Erro ao salvar configurações'
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload de som personalizado
     * POST /profile/sounds/upload
     */
    public function uploadSound(): void
    {
        $userId = Auth::id();
        
        try {
            if (!isset($_FILES['sound']) || $_FILES['sound']['error'] !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Nenhum arquivo enviado ou erro no upload');
            }
            
            $name = Request::post('name', 'Meu Som');
            
            $result = SoundNotificationService::uploadCustomSound($_FILES['sound'], $userId, $name);
            
            Response::json([
                'success' => true,
                'sound' => $result,
                'message' => 'Som enviado com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir som personalizado
     * DELETE /profile/sounds/{id}
     */
    public function deleteSound(int $id): void
    {
        $userId = Auth::id();
        
        try {
            $success = SoundNotificationService::deleteCustomSound($id, $userId);
            
            Response::json([
                'success' => $success,
                'message' => $success ? 'Som removido!' : 'Erro ao remover som'
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Listar sons disponíveis
     * GET /profile/sounds/available
     */
    public function getAvailableSounds(): void
    {
        $userId = Auth::id();
        
        try {
            $sounds = SoundNotificationService::getAvailableSounds($userId);
            
            Response::json([
                'success' => true,
                'sounds' => $sounds
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar sons personalizados do usuário
     * GET /profile/sounds/custom
     */
    public function getCustomSounds(): void
    {
        $userId = Auth::id();
        
        try {
            $sounds = SoundNotificationService::getUserCustomSounds($userId);
            
            Response::json([
                'success' => true,
                'sounds' => $sounds
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

