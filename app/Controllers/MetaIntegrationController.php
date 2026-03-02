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
            // Buscar dados da integration_account vinculada (funil/etapa padrão)
            if (!empty($phone['integration_account_id'])) {
                $ia = IntegrationAccount::find($phone['integration_account_id']);
                if ($ia) {
                    $phone['integration_account'] = $ia;
                    if (!empty($ia['default_funnel_id'])) {
                        $funnel = \App\Models\Funnel::find($ia['default_funnel_id']);
                        $phone['default_funnel_name'] = $funnel['name'] ?? null;
                    }
                    if (!empty($ia['default_stage_id'])) {
                        $stage = \App\Helpers\Database::fetch("SELECT name FROM funnel_stages WHERE id = ?", [$ia['default_stage_id']]);
                        $phone['default_stage_name'] = $stage['name'] ?? null;
                    }
                }
            }
        }
        
        // Carregar configurações Meta e funis
        $metaConfig = self::getMetaConfig();
        $funnels = \App\Models\Funnel::whereActive();
        
        Response::view('integrations/meta/index', [
            'instagramAccounts' => $instagramAccounts,
            'whatsappPhones' => $whatsappPhones,
            'tokens' => $tokens,
            'metaConfig' => $metaConfig,
            'funnels' => $funnels,
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
            $configId = $data['config_id'] ?? '';
            
            error_log("Meta saveConfig - app_id: " . ($appId ? 'OK' : 'VAZIO'));
            error_log("Meta saveConfig - app_secret: " . ($appSecret ? 'OK' : 'VAZIO'));
            error_log("Meta saveConfig - webhook_verify_token: " . ($webhookVerifyToken ? 'OK' : 'VAZIO'));
            error_log("Meta saveConfig - config_id: " . ($configId ? 'OK' : 'VAZIO'));
            
            if (empty($appId) || empty($appSecret) || empty($webhookVerifyToken)) {
                Response::json([
                    'success' => false,
                    'error' => 'Todos os campos são obrigatórios (App ID, App Secret e Webhook Verify Token)'
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
                'config_id' => $configId,
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
                'config_id' => $metaConfig['whatsapp']['config_id'] ?? '',
            ];
        }
        
        // Retornar vazio se não encontrar nenhuma configuração
        return [
            'app_id' => '',
            'app_secret' => '',
            'webhook_verify_token' => '',
            'config_id' => '',
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
    
    /**
     * Embedded Signup - Conectar WhatsApp via Facebook Login (OAuth visual)
     * 
     * POST /integrations/meta/whatsapp/signup
     */
    public function embeddedSignup(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        try {
            $code = Request::post('code', '');
            
            if (empty($code)) {
                Response::json(['success' => false, 'error' => 'Código de autorização não fornecido'], 400);
                return;
            }
            
            $metaConfig = self::getMetaConfig();
            $phpConfig = [];
            $phpConfigFile = __DIR__ . '/../../config/meta.php';
            if (file_exists($phpConfigFile)) {
                $phpConfig = require $phpConfigFile;
            }
            $apiVersion = $phpConfig['whatsapp']['api_version'] ?? 'v21.0';
            
            // 1. Trocar authorization code por access token
            $tokenUrl = "https://graph.facebook.com/{$apiVersion}/oauth/access_token?" . http_build_query([
                'client_id' => $metaConfig['app_id'],
                'client_secret' => $metaConfig['app_secret'],
                'code' => $code,
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $tokenUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $tokenResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $tokenData = json_decode($tokenResponse, true);
            
            if ($httpCode !== 200 || empty($tokenData['access_token'])) {
                $error = $tokenData['error']['message'] ?? 'Falha ao obter token de acesso';
                Response::json(['success' => false, 'error' => "OAuth falhou: {$error}"], 400);
                return;
            }
            
            $accessToken = $tokenData['access_token'];
            
            // 2. Buscar WABAs vinculadas ao token
            $wabasUrl = "https://graph.facebook.com/{$apiVersion}/me/whatsapp_business_accounts?" . http_build_query([
                'fields' => 'id,name,account_review_status,message_template_namespace',
                'access_token' => $accessToken,
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $wabasUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $wabasResponse = curl_exec($ch);
            curl_close($ch);
            
            $wabasData = json_decode($wabasResponse, true);
            $wabas = $wabasData['data'] ?? [];
            
            if (empty($wabas)) {
                Response::json(['success' => false, 'error' => 'Nenhuma conta WhatsApp Business encontrada nesta conta Meta. Verifique se o WhatsApp Business está configurado.'], 400);
                return;
            }
            
            $registered = [];
            
            foreach ($wabas as $waba) {
                $wabaId = $waba['id'];
                $wabaName = $waba['name'] ?? '';
                
                // 3. Buscar números de cada WABA
                $phonesUrl = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/phone_numbers?" . http_build_query([
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,account_mode,code_verification_status',
                    'access_token' => $accessToken,
                ]);
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $phonesUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_TIMEOUT => 30,
                ]);
                $phonesResponse = curl_exec($ch);
                curl_close($ch);
                
                $phonesData = json_decode($phonesResponse, true);
                $phones = $phonesData['data'] ?? [];
                
                foreach ($phones as $phone) {
                    $phoneNumberId = $phone['id'];
                    $displayPhone = $phone['display_phone_number'] ?? '';
                    $verifiedName = $phone['verified_name'] ?? $wabaName ?: 'WhatsApp';
                    
                    // 4. Salvar/atualizar token OAuth
                    $existingToken = MetaOAuthToken::getByMetaUserId($phoneNumberId);
                    if ($existingToken) {
                        MetaOAuthToken::update($existingToken['id'], [
                            'access_token' => $accessToken,
                            'is_valid' => true,
                            'expires_at' => !empty($tokenData['expires_in'])
                                ? date('Y-m-d H:i:s', time() + $tokenData['expires_in'])
                                : null,
                        ]);
                        $tokenId = $existingToken['id'];
                    } else {
                        $tokenId = MetaOAuthToken::create([
                            'meta_user_id' => $phoneNumberId,
                            'app_type' => 'whatsapp',
                            'access_token' => $accessToken,
                            'token_type' => 'bearer',
                            'expires_at' => !empty($tokenData['expires_in'])
                                ? date('Y-m-d H:i:s', time() + $tokenData['expires_in'])
                                : null,
                            'scopes' => 'whatsapp_business_management,whatsapp_business_messaging',
                            'is_valid' => true,
                        ]);
                    }
                    
                    // 5. Criar/atualizar WhatsAppPhone
                    $existingPhone = WhatsAppPhone::findByPhoneNumberId($phoneNumberId);
                    $phoneData = [
                        'phone_number_id' => $phoneNumberId,
                        'phone_number' => $displayPhone,
                        'display_phone_number' => $displayPhone,
                        'waba_id' => $wabaId,
                        'verified_name' => $verifiedName,
                        'quality_rating' => $phone['quality_rating'] ?? 'UNKNOWN',
                        'account_mode' => $phone['account_mode'] ?? 'SANDBOX',
                        'meta_oauth_token_id' => $tokenId,
                        'is_active' => true,
                        'is_connected' => true,
                    ];
                    
                    if ($existingPhone) {
                        WhatsAppPhone::update($existingPhone['id'], $phoneData);
                        $phoneId = $existingPhone['id'];
                    } else {
                        $phoneId = WhatsAppPhone::create($phoneData);
                    }
                    
                    // 6. Criar/atualizar IntegrationAccount
                    $existingIA = IntegrationAccount::findByPhone($displayPhone, 'whatsapp');
                    if ($existingIA) {
                        IntegrationAccount::update($existingIA['id'], [
                            'name' => $verifiedName,
                            'status' => 'active',
                            'provider' => 'meta_cloud',
                            'config' => json_encode([
                                'waba_id' => $wabaId,
                                'phone_number_id' => $phoneNumberId,
                            ]),
                        ]);
                        $iaId = $existingIA['id'];
                    } else {
                        $iaId = IntegrationAccount::create([
                            'name' => $verifiedName,
                            'provider' => 'meta_cloud',
                            'channel' => 'whatsapp',
                            'phone_number' => $displayPhone,
                            'account_id' => $wabaId,
                            'status' => 'active',
                            'config' => json_encode([
                                'waba_id' => $wabaId,
                                'phone_number_id' => $phoneNumberId,
                            ]),
                        ]);
                    }
                    
                    // 7. Vincular tudo
                    WhatsAppPhone::update($phoneId, ['integration_account_id' => $iaId]);
                    MetaOAuthToken::update($tokenId, ['integration_account_id' => $iaId]);
                    
                    $registered[] = [
                        'phone' => $displayPhone,
                        'name' => $verifiedName,
                    ];
                }
            }
            
            if (empty($registered)) {
                Response::json(['success' => false, 'error' => 'Nenhum número de telefone encontrado nas contas WhatsApp Business.'], 400);
                return;
            }
            
            Response::json([
                'success' => true,
                'registered' => $registered,
                'message' => count($registered) . ' número(s) conectado(s) com sucesso',
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => 'Erro ao processar: ' . $e->getMessage(),
            ], 500);
        }
    }
}

