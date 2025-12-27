<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Models\MetaOAuthToken;
use App\Models\InstagramAccount;
use App\Models\WhatsAppPhone;
use App\Models\IntegrationAccount;
use App\Services\InstagramGraphService;
use App\Services\WhatsAppCloudService;

/**
 * MetaIntegrationController
 * 
 * Gerencia interface de configuração das integrações Meta
 */
class MetaIntegrationController
{
    /**
     * Página principal de gerenciamento Meta
     * 
     * GET /integrations/meta
     */
    public function index(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        // Buscar contas Instagram conectadas
        $instagramAccounts = InstagramAccount::getActive();
        
        // Buscar números WhatsApp conectados
        $whatsappPhones = WhatsAppPhone::getActive();
        
        // Buscar tokens
        $tokens = MetaOAuthToken::all();
        
        // Enriquecer dados
        foreach ($instagramAccounts as &$account) {
            $account['has_valid_token'] = InstagramAccount::hasValidToken($account['id']);
        }
        
        foreach ($whatsappPhones as &$phone) {
            $phone['has_valid_token'] = WhatsAppPhone::hasValidToken($phone['id']);
        }
        
        Response::view('integrations/meta/index', [
            'instagramAccounts' => $instagramAccounts,
            'whatsappPhones' => $whatsappPhones,
            'tokens' => $tokens,
        ]);
    }
    
    /**
     * Sincronizar perfil Instagram
     * 
     * POST /integrations/meta/instagram/sync/{id}
     */
    public function syncInstagram(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        $id = Request::post('id');
        
        if (!$id) {
            Response::json([
                'success' => false,
                'error' => 'ID não fornecido'
            ], 400);
            return;
        }
        
        $account = InstagramAccount::find($id);
        
        if (!$account) {
            Response::json([
                'success' => false,
                'error' => 'Conta não encontrada'
            ], 404);
            return;
        }
        
        try {
            $token = InstagramAccount::getOAuthToken($id);
            
            if (!$token || !$token['access_token']) {
                Response::json([
                    'success' => false,
                    'error' => 'Token não encontrado ou inválido'
                ], 400);
                return;
            }
            
            $profile = InstagramGraphService::syncProfile(
                $account['instagram_user_id'],
                $token['access_token']
            );
            
            Response::json([
                'success' => true,
                'message' => 'Perfil sincronizado com sucesso',
                'data' => $profile
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sincronizar número WhatsApp
     * 
     * POST /integrations/meta/whatsapp/sync/{id}
     */
    public function syncWhatsApp(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        $id = Request::post('id');
        
        if (!$id) {
            Response::json([
                'success' => false,
                'error' => 'ID não fornecido'
            ], 400);
            return;
        }
        
        $phone = WhatsAppPhone::find($id);
        
        if (!$phone) {
            Response::json([
                'success' => false,
                'error' => 'Número não encontrado'
            ], 404);
            return;
        }
        
        try {
            $token = WhatsAppPhone::getOAuthToken($id);
            
            if (!$token || !$token['access_token']) {
                Response::json([
                    'success' => false,
                    'error' => 'Token não encontrado ou inválido'
                ], 400);
                return;
            }
            
            $syncedPhone = WhatsAppCloudService::syncPhone(
                $phone['phone_number_id'],
                $token['access_token']
            );
            
            Response::json([
                'success' => true,
                'message' => 'Número sincronizado com sucesso',
                'data' => $syncedPhone
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Adicionar número WhatsApp manualmente
     * 
     * POST /integrations/meta/whatsapp/add
     */
    public function addWhatsAppPhone(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        $phoneNumberId = Request::post('phone_number_id');
        $phoneNumber = Request::post('phone_number');
        $wabaId = Request::post('waba_id');
        $metaUserId = Request::post('meta_user_id');
        
        if (!$phoneNumberId || !$phoneNumber || !$wabaId || !$metaUserId) {
            Response::json([
                'success' => false,
                'error' => 'Dados incompletos'
            ], 400);
            return;
        }
        
        try {
            // Buscar token
            $token = MetaOAuthToken::getByMetaUserId($metaUserId);
            
            if (!$token) {
                Response::json([
                    'success' => false,
                    'error' => 'Token não encontrado. Faça a autenticação OAuth primeiro.'
                ], 400);
                return;
            }
            
            // Criar ou atualizar número WhatsApp
            $existing = WhatsAppPhone::findByPhoneNumberId($phoneNumberId);
            
            $data = [
                'phone_number_id' => $phoneNumberId,
                'phone_number' => $phoneNumber,
                'waba_id' => $wabaId,
                'meta_oauth_token_id' => $token['id'],
                'is_active' => true,
                'is_connected' => true,
            ];
            
            if ($existing) {
                WhatsAppPhone::update($existing['id'], $data);
                $phoneId = $existing['id'];
            } else {
                $phoneId = WhatsAppPhone::create($data);
            }
            
            // Sincronizar dados
            $syncedPhone = WhatsAppCloudService::syncPhone($phoneNumberId, $token['access_token']);
            
            // Criar integration_account
            $integrationData = [
                'provider' => 'meta',
                'channel' => 'whatsapp',
                'account_name' => $syncedPhone['verified_name'] ?? $phoneNumber,
                'account_id' => $phoneNumberId,
                'is_active' => true,
                'status' => 'connected',
            ];
            
            $integrationId = IntegrationAccount::create($integrationData);
            
            // Vincular
            WhatsAppPhone::update($phoneId, [
                'integration_account_id' => $integrationId
            ]);
            
            MetaOAuthToken::update($token['id'], [
                'integration_account_id' => $integrationId
            ]);
            
            Response::json([
                'success' => true,
                'message' => 'Número WhatsApp adicionado com sucesso',
                'data' => $syncedPhone
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Testar envio de mensagem
     * 
     * POST /integrations/meta/test-message
     */
    public function testMessage(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        $type = Request::post('type'); // instagram ou whatsapp
        $accountId = Request::post('account_id');
        $to = Request::post('to');
        $message = Request::post('message');
        
        if (!$type || !$accountId || !$to || !$message) {
            Response::json([
                'success' => false,
                'error' => 'Dados incompletos'
            ], 400);
            return;
        }
        
        try {
            if ($type === 'instagram') {
                $account = InstagramAccount::find($accountId);
                
                if (!$account) {
                    Response::json([
                        'success' => false,
                        'error' => 'Conta Instagram não encontrada'
                    ], 404);
                    return;
                }
                
                $token = InstagramAccount::getOAuthToken($accountId);
                
                if (!$token) {
                    Response::json([
                        'success' => false,
                        'error' => 'Token não encontrado'
                    ], 400);
                    return;
                }
                
                $result = InstagramGraphService::sendMessage($to, $message, $token['access_token']);
                
            } elseif ($type === 'whatsapp') {
                $phone = WhatsAppPhone::find($accountId);
                
                if (!$phone) {
                    Response::json([
                        'success' => false,
                        'error' => 'Número WhatsApp não encontrado'
                    ], 404);
                    return;
                }
                
                $token = WhatsAppPhone::getOAuthToken($accountId);
                
                if (!$token) {
                    Response::json([
                        'success' => false,
                        'error' => 'Token não encontrado'
                    ], 400);
                    return;
                }
                
                $result = WhatsAppCloudService::sendTextMessage(
                    $phone['phone_number_id'],
                    $to,
                    $message,
                    $token['access_token']
                );
                
            } else {
                Response::json([
                    'success' => false,
                    'error' => 'Tipo inválido'
                ], 400);
                return;
            }
            
            Response::json([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Logs da integração Meta
     * 
     * GET /integrations/meta/logs
     */
    public function logs(): void
    {
        Permission::abortIfCannot('integrations.view');
        
        $logFile = __DIR__ . '/../../storage/logs/meta.log';
        
        if (!file_exists($logFile)) {
            $logs = "Nenhum log disponível ainda.";
        } else {
            $logs = file_get_contents($logFile);
        }
        
        Response::view('integrations/meta/logs', [
            'logs' => $logs
        ]);
    }
}

