<?php
/**
 * DevicesController - API v1
 * Registro de dispositivos móveis para push notifications (Expo Push Tokens)
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\DeviceToken;

class DevicesController
{
    /**
     * Registrar dispositivo
     * POST /api/v1/devices
     * Body: { token: string, platform: 'ios'|'android', device_name?: string, app_version?: string }
     */
    public function store(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $errors = [];
        $token = trim((string)($input['token'] ?? ''));
        $platform = strtolower(trim((string)($input['platform'] ?? '')));

        if ($token === '') {
            $errors['token'] = ['Campo obrigatório'];
        }
        if (!in_array($platform, ['ios', 'android'], true)) {
            $errors['platform'] = ['Deve ser "ios" ou "android"'];
        }
        if (!empty($errors)) {
            ApiResponse::validationError('Dados inválidos', $errors);
        }

        try {
            $id = DeviceToken::register(
                ApiAuthMiddleware::userId(),
                $token,
                $platform,
                isset($input['device_name']) ? substr((string)$input['device_name'], 0, 255) : null,
                isset($input['app_version']) ? substr((string)$input['app_version'], 0, 50) : null
            );

            ApiResponse::created(['id' => $id], 'Dispositivo registrado com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao registrar dispositivo', $e);
        }
    }

    /**
     * Remover (revogar) dispositivo — usado no logout
     * DELETE /api/v1/devices/:token
     */
    public function destroy(string $token): void
    {
        try {
            $token = urldecode($token);

            // Só permite revogar tokens do próprio usuário
            $devices = DeviceToken::getActiveByUser(ApiAuthMiddleware::userId());
            $owns = false;
            foreach ($devices as $device) {
                if (hash_equals($device['token'], $token)) {
                    $owns = true;
                    break;
                }
            }

            if (!$owns) {
                ApiResponse::notFound('Dispositivo não encontrado');
            }

            DeviceToken::revoke($token);
            ApiResponse::success(null, 200, 'Dispositivo removido');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao remover dispositivo', $e);
        }
    }
}
