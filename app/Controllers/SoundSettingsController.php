<?php
/**
 * Controller SoundSettingsController
 * 
 * Gerenciamento de configurações de som de notificação.
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;
use App\Services\SoundNotificationService;
use App\Models\UserSoundSettings;

class SoundSettingsController
{
    /**
     * Obter configurações de som do usuário logado
     * GET /settings/sounds
     */
    public function getUserSettings(): void
    {
        try {
            $userId = Auth::id();
            $settings = SoundNotificationService::getUserSettings($userId);
            $availableSounds = SoundNotificationService::getAvailableSounds($userId);
            $soundEvents = SoundNotificationService::getSoundEvents();
            
            Response::json([
                'success' => true,
                'settings' => $settings,
                'available_sounds' => $availableSounds,
                'sound_events' => $soundEvents
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualizar configurações de som do usuário logado
     * POST /settings/sounds
     */
    public function updateUserSettings(): void
    {
        try {
            $userId = Auth::id();
            $data = Request::json() ?: $_POST;
            
            // Converter valores booleanos
            $boolFields = [
                'sounds_enabled',
                'new_conversation_enabled',
                'new_message_enabled',
                'conversation_assigned_enabled',
                'invite_received_enabled',
                'sla_warning_enabled',
                'sla_breached_enabled',
                'mention_received_enabled',
                'quiet_hours_enabled'
            ];
            
            foreach ($boolFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                }
            }
            
            // Validar volume
            if (isset($data['volume'])) {
                $data['volume'] = max(0, min(100, (int)$data['volume']));
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
     * Obter configurações padrão do sistema (admin)
     * GET /settings/sounds/system
     */
    public function getSystemSettings(): void
    {
        Permission::abortIfCannot('settings.view');
        
        try {
            $settings = SoundNotificationService::getSystemSettings();
            $availableSounds = SoundNotificationService::getAvailableSounds();
            
            Response::json([
                'success' => true,
                'settings' => $settings,
                'available_sounds' => $availableSounds
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualizar configurações padrão do sistema (admin)
     * POST /settings/sounds/system
     */
    public function updateSystemSettings(): void
    {
        Permission::abortIfCannot('settings.edit');
        
        try {
            $data = Request::json() ?: $_POST;
            $success = SoundNotificationService::updateSystemSettings($data);
            
            Response::json([
                'success' => $success,
                'message' => $success ? 'Configurações do sistema salvas!' : 'Erro ao salvar'
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar sons disponíveis
     * GET /settings/sounds/available
     */
    public function getAvailableSounds(): void
    {
        try {
            $userId = Auth::id();
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
     * Upload de som personalizado
     * POST /settings/sounds/upload
     */
    public function uploadSound(): void
    {
        try {
            $userId = Auth::id();
            
            if (!isset($_FILES['sound']) || $_FILES['sound']['error'] !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Arquivo não enviado ou com erro');
            }
            
            $name = $_POST['name'] ?? 'Som personalizado';
            $sound = SoundNotificationService::uploadCustomSound($_FILES['sound'], $userId, $name);
            
            Response::json([
                'success' => true,
                'message' => 'Som enviado com sucesso!',
                'sound' => $sound
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remover som personalizado
     * DELETE /settings/sounds/{id}
     */
    public function deleteSound(int $id): void
    {
        try {
            $userId = Auth::id();
            $success = SoundNotificationService::deleteCustomSound($id, $userId);
            
            Response::json([
                'success' => $success,
                'message' => $success ? 'Som removido!' : 'Erro ao remover'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Testar som
     * POST /settings/sounds/test
     */
    public function testSound(): void
    {
        try {
            $data = Request::json() ?: $_POST;
            $soundFile = $data['sound'] ?? null;
            $volume = isset($data['volume']) ? (int)$data['volume'] : 70;
            
            if (!$soundFile) {
                throw new \InvalidArgumentException('Arquivo de som não especificado');
            }
            
            // Retornar dados para o frontend tocar o som
            Response::json([
                'success' => true,
                'sound' => $soundFile,
                'url' => '/assets/sounds/' . $soundFile,
                'volume' => $volume / 100
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obter dados de som para um evento específico
     * GET /settings/sounds/event/{event}
     */
    public function getSoundForEvent(string $event): void
    {
        try {
            $userId = Auth::id();
            $soundData = SoundNotificationService::getSoundDataForEvent($userId, $event);
            
            Response::json([
                'success' => true,
                'sound_data' => $soundData
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

