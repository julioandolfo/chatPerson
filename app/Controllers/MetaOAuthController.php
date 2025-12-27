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
 * MetaOAuthController
 * 
 * Gerencia autenticação OAuth 2.0 com Meta (Instagram + WhatsApp)
 */
class MetaOAuthController
{
    private static array $config = [];
    
    /**
     * Inicializar configurações
     */
    private static function initConfig(): void
    {
        if (empty(self::$config)) {
            $configFile = __DIR__ . '/../../config/meta.php';
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            }
        }
    }
    
    /**
     * Redirecionar para autorização (Passo 1 do OAuth)
     * 
     * GET /integrations/meta/oauth/authorize?type=instagram|whatsapp
     */
    public function authorize(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        self::initConfig();
        
        $type = Request::get('type', 'both'); // instagram, whatsapp ou both
        
        // Definir scopes baseado no tipo
        $scopes = [];
        if ($type === 'instagram' || $type === 'both') {
            $scopes = array_merge($scopes, self::$config['instagram']['scopes'] ?? []);
        }
        if ($type === 'whatsapp' || $type === 'both') {
            $scopes = array_merge($scopes, self::$config['whatsapp']['scopes'] ?? []);
        }
        
        // Gerar state para segurança
        $state = bin2hex(random_bytes(16));
        $_SESSION['meta_oauth_state'] = $state;
        $_SESSION['meta_oauth_type'] = $type;
        
        // Montar URL de autorização
        $params = [
            'client_id' => self::$config['app_id'],
            'redirect_uri' => self::$config['oauth']['redirect_uri'],
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'state' => $state,
        ];
        
        $authUrl = 'https://www.facebook.com/dialog/oauth?' . http_build_query($params);
        
        Response::redirect($authUrl);
    }
    
    /**
     * Callback OAuth (Passo 2 do OAuth)
     * 
     * GET /integrations/meta/oauth/callback?code=...&state=...
     */
    public function callback(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        self::initConfig();
        
        $code = Request::get('code');
        $state = Request::get('state');
        $error = Request::get('error');
        
        // Verificar se houve erro
        if ($error) {
            $errorDescription = Request::get('error_description', 'Erro desconhecido');
            Response::json([
                'success' => false,
                'error' => "Erro na autorização: {$errorDescription}"
            ], 400);
            return;
        }
        
        // Validar state
        if (!isset($_SESSION['meta_oauth_state']) || $state !== $_SESSION['meta_oauth_state']) {
            Response::json([
                'success' => false,
                'error' => 'State inválido. Possível ataque CSRF.'
            ], 400);
            return;
        }
        
        $type = $_SESSION['meta_oauth_type'] ?? 'both';
        
        // Limpar sessão
        unset($_SESSION['meta_oauth_state']);
        unset($_SESSION['meta_oauth_type']);
        
        // Trocar code por access_token
        try {
            $tokenData = $this->exchangeCodeForToken($code);
            
            // Obter informações do usuário
            $userData = $this->getUserData($tokenData['access_token']);
            
            // Salvar token no banco
            $this->saveToken($tokenData, $userData, $type);
            
            // Redirecionar para página de sucesso
            $_SESSION['meta_oauth_success'] = true;
            Response::redirect('/integrations/meta?success=1');
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => "Erro ao processar OAuth: {$e->getMessage()}"
            ], 500);
        }
    }
    
    /**
     * Trocar code por access_token
     */
    private function exchangeCodeForToken(string $code): array
    {
        $url = 'https://graph.facebook.com/oauth/access_token';
        
        $params = [
            'client_id' => self::$config['app_id'],
            'client_secret' => self::$config['app_secret'],
            'redirect_uri' => self::$config['oauth']['redirect_uri'],
            'code' => $code,
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Erro ao trocar code por token: HTTP {$httpCode}");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            throw new \Exception("Token não retornado pela Meta API");
        }
        
        return $data;
    }
    
    /**
     * Obter dados do usuário autenticado
     */
    private function getUserData(string $accessToken): array
    {
        $url = 'https://graph.facebook.com/me?fields=id,name,email&access_token=' . urlencode($accessToken);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Salvar token no banco
     */
    private function saveToken(array $tokenData, array $userData, string $type): void
    {
        $metaUserId = $userData['id'];
        
        // Verificar se já existe token
        $existingToken = MetaOAuthToken::getByMetaUserId($metaUserId);
        
        // Calcular expiração (geralmente 60 dias)
        $expiresIn = $tokenData['expires_in'] ?? (60 * 24 * 3600); // 60 dias em segundos
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        
        $data = [
            'meta_user_id' => $metaUserId,
            'app_type' => $type,
            'access_token' => $tokenData['access_token'],
            'token_type' => $tokenData['token_type'] ?? 'bearer',
            'expires_at' => $expiresAt,
            'is_valid' => true,
            'revoked_at' => null,
            'meta_app_id' => self::$config['app_id'],
        ];
        
        if ($existingToken) {
            MetaOAuthToken::update($existingToken['id'], $data);
            $tokenId = $existingToken['id'];
        } else {
            $tokenId = MetaOAuthToken::create($data);
        }
        
        // Sincronizar dados baseado no tipo
        if ($type === 'instagram' || $type === 'both') {
            try {
                $profile = InstagramGraphService::syncProfile($metaUserId, $tokenData['access_token']);
                
                // Criar/atualizar integration_account
                $this->createOrUpdateIntegrationAccount('instagram', $profile, $tokenId);
                
            } catch (\Exception $e) {
                // Log erro mas não falhar
                error_log("Erro ao sincronizar Instagram: {$e->getMessage()}");
            }
        }
        
        // Para WhatsApp, não podemos sincronizar automaticamente pois precisamos do phone_number_id
        // O usuário terá que configurar manualmente
    }
    
    /**
     * Criar ou atualizar Integration Account
     */
    private function createOrUpdateIntegrationAccount(string $channel, array $accountData, int $tokenId): void
    {
        $existingAccount = null;
        
        // Buscar conta existente
        $allAccounts = IntegrationAccount::all();
        foreach ($allAccounts as $account) {
            if ($account['provider'] === 'meta' && $account['channel'] === $channel) {
                if ($channel === 'instagram' && isset($accountData['instagram_user_id'])) {
                    if ($account['account_id'] === $accountData['instagram_user_id']) {
                        $existingAccount = $account;
                        break;
                    }
                }
            }
        }
        
        $data = [
            'provider' => 'meta',
            'channel' => $channel,
            'account_name' => $accountData['name'] ?? $accountData['username'] ?? 'Conta Meta',
            'is_active' => true,
            'status' => 'connected',
        ];
        
        if ($channel === 'instagram') {
            $data['account_id'] = $accountData['instagram_user_id'] ?? null;
            $data['username'] = $accountData['username'] ?? null;
        }
        
        if ($existingAccount) {
            IntegrationAccount::update($existingAccount['id'], $data);
            $accountId = $existingAccount['id'];
        } else {
            $accountId = IntegrationAccount::create($data);
        }
        
        // Vincular token à integration_account
        MetaOAuthToken::update($tokenId, [
            'integration_account_id' => $accountId
        ]);
        
        // Vincular instagram_account ou whatsapp_phone à integration_account
        if ($channel === 'instagram' && isset($accountData['id'])) {
            InstagramAccount::update($accountData['id'], [
                'integration_account_id' => $accountId,
                'meta_oauth_token_id' => $tokenId,
            ]);
        }
    }
    
    /**
     * Desconectar conta (revogar token)
     * 
     * POST /integrations/meta/oauth/disconnect
     */
    public function disconnect(): void
    {
        Permission::abortIfCannot('integrations.manage');
        
        $metaUserId = Request::post('meta_user_id');
        
        if (!$metaUserId) {
            Response::json([
                'success' => false,
                'error' => 'Meta User ID não fornecido'
            ], 400);
            return;
        }
        
        $token = MetaOAuthToken::getByMetaUserId($metaUserId);
        
        if (!$token) {
            Response::json([
                'success' => false,
                'error' => 'Token não encontrado'
            ], 404);
            return;
        }
        
        // Revogar token
        MetaOAuthToken::revoke($token['id']);
        
        // Desconectar contas Instagram
        $allInstagram = InstagramAccount::all();
        foreach ($allInstagram as $ig) {
            if ($ig['meta_oauth_token_id'] === $token['id']) {
                InstagramAccount::disconnect($ig['id']);
            }
        }
        
        // Desconectar números WhatsApp
        $allWhatsApp = WhatsAppPhone::all();
        foreach ($allWhatsApp as $wa) {
            if ($wa['meta_oauth_token_id'] === $token['id']) {
                WhatsAppPhone::disconnect($wa['id']);
            }
        }
        
        Response::json([
            'success' => true,
            'message' => 'Conta desconectada com sucesso'
        ]);
    }
}

