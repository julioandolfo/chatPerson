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
        
        // Carregar configurações Meta
        $metaConfig = self::getMetaConfig();
        
        Response::view('integrations/meta/index', [
            'instagramAccounts' => $instagramAccounts,
            'whatsappPhones' => $whatsappPhones,
            'tokens' => $tokens,
            'metaConfig' => $metaConfig,
        ]);
    }
    
    /**
     * Salvar configurações Meta
     * 
     * POST /integrations/meta/config/save
     */
    public function saveConfig(): void
    {
        try {
            Permission::abortIfCannot('integrations.manage');
            
            // Ler JSON do corpo da requisição
            $jsonBody = file_get_contents('php://input');
            $data = json_decode($jsonBody, true);
            
            error_log("Meta saveConfig - JSON recebido: " . $jsonBody);
            
            if (!$data || !is_array($data)) {
                Response::json([
                    'success' => false,
                    'error' => 'Dados inválidos'
                ], 400);
                return;
            }
            
            $appId = $data['app_id'] ?? null;
            $appSecret = $data['app_secret'] ?? null;
            $webhookVerifyToken = $data['webhook_verify_token'] ?? null;
            
            error_log("Meta saveConfig - app_id: " . ($appId ? 'OK' : 'VAZIO'));
            error_log("Meta saveConfig - app_secret: " . ($appSecret ? 'OK' : 'VAZIO'));
            error_log("Meta saveConfig - webhook_verify_token: " . ($webhookVerifyToken ? 'OK' : 'VAZIO'));
            
            if (empty($appId) || empty($appSecret) || empty($webhookVerifyToken)) {
                Response::json([
                    'success' => false,
                    'error' => 'Todos os campos são obrigatórios'
                ], 400);
                return;
            }
            
            // Salvar em arquivo JSON para não expor no código
            // Usar caminho absoluto baseado no root do projeto
            $rootPath = dirname(dirname(__DIR__)); // De app/Controllers para o root
            $configDir = $rootPath . '/storage/config';
            $configFile = $configDir . '/meta.json';
            
            error_log("Meta saveConfig - rootPath: {$rootPath}");
            error_log("Meta saveConfig - configDir: {$configDir}");
            error_log("Meta saveConfig - configFile: {$configFile}");
            
            // Verificar se storage existe
            $storageDir = $rootPath . '/storage';
            if (!is_dir($storageDir)) {
                error_log("Meta saveConfig - ⚠️ Diretório storage não existe! Criando...");
                if (!mkdir($storageDir, 0755, true)) {
                    throw new \Exception("Erro ao criar diretório storage");
                }
            }
            
            // Criar diretório config se não existir
            if (!is_dir($configDir)) {
                error_log("Meta saveConfig - Criando diretório config: {$configDir}");
                $created = mkdir($configDir, 0755, true);
                error_log("Meta saveConfig - Diretório criado: " . ($created ? 'SIM' : 'NÃO'));
                
                if (!$created) {
                    // Verificar permissões
                    $storagePerms = substr(sprintf('%o', fileperms($storageDir)), -4);
                    throw new \Exception("Erro ao criar diretório config. Permissões de storage: {$storagePerms}");
                }
            }
            
            $config = [
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'webhook_verify_token' => $webhookVerifyToken,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            error_log("Meta saveConfig - Salvando arquivo...");
            $result = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            if ($result === false) {
                $error = error_get_last();
                error_log("Meta saveConfig - ERRO file_put_contents: " . json_encode($error));
                throw new \Exception('Erro ao salvar arquivo de configuração: ' . ($error['message'] ?? 'Desconhecido'));
            }
            
            error_log("Meta saveConfig - Arquivo salvo com sucesso ({$result} bytes)");
            
            // Proteger o arquivo
            if (file_exists($configFile)) {
                @chmod($configFile, 0600);
            }
            
            Response::json([
                'success' => true,
                'message' => 'Configurações salvas com sucesso'
            ]);
            
        } catch (\Exception $e) {
            error_log("Meta saveConfig - EXCEPTION: " . $e->getMessage());
            error_log("Meta saveConfig - TRACE: " . $e->getTraceAsString());
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obter configurações Meta (do arquivo JSON ou config/meta.php)
     */
    private static function getMetaConfig(): array
    {
        // Tentar ler do arquivo JSON primeiro (configurações salvas pela interface)
        // Usar caminho absoluto baseado no root do projeto
        $rootPath = dirname(dirname(__DIR__)); // De app/Controllers para o root
        $configFile = $rootPath . '/storage/config/meta.json';
        
        if (file_exists($configFile)) {
            $json = file_get_contents($configFile);
            $config = json_decode($json, true);
            
            if ($config && !empty($config['app_id'])) {
                return $config;
            }
        }
        
        // Fallback para config/meta.php
        $metaConfigFile = $rootPath . '/config/meta.php';
        if (file_exists($metaConfigFile)) {
            $metaConfig = require $metaConfigFile;
            
            return [
                'app_id' => $metaConfig['app_id'] ?? '',
                'app_secret' => $metaConfig['app_secret'] ?? '',
                'webhook_verify_token' => $metaConfig['webhooks']['verify_token'] ?? '',
            ];
        }
        
        // Retornar vazio se não encontrar nenhuma configuração
        return [
            'app_id' => '',
            'app_secret' => '',
            'webhook_verify_token' => '',
        ];
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

