<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Services\MetaIntegrationService;
use App\Services\InstagramGraphService;
use App\Services\WhatsAppCloudService;

/**
 * MetaWebhookController
 * 
 * Gerencia webhooks unificados da Meta (Instagram + WhatsApp)
 */
class MetaWebhookController
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
     * Verificação do webhook (Meta envia GET para verificar)
     * 
     * GET /webhooks/meta?hub.mode=subscribe&hub.challenge=...&hub.verify_token=...
     */
    public function verify(): void
    {
        self::initConfig();
        
        $mode = Request::get('hub_mode');
        $token = Request::get('hub_verify_token');
        $challenge = Request::get('hub_challenge');
        
        $expectedToken = self::$config['webhooks']['verify_token'] ?? '';
        
        if ($mode === 'subscribe' && $token === $expectedToken) {
            // Retornar o challenge para confirmar
            echo $challenge;
            exit;
        } else {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
    
    /**
     * Receber webhook (Meta envia POST com eventos)
     * 
     * POST /webhooks/meta
     */
    public function receive(): void
    {
        self::initConfig();
        
        // Obter payload
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }
        
        // Validar signature (obrigatório para segurança)
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        
        if ($signature) {
            $appSecret = self::$config['app_secret'] ?? '';
            
            if (!MetaIntegrationService::validateWebhookSignature($signature, $rawPayload, $appSecret)) {
                http_response_code(403);
                echo json_encode(['error' => 'Invalid signature']);
                exit;
            }
        }
        
        // Processar webhook
        $this->processWebhook($payload);
        
        // Retornar 200 OK (SEMPRE retornar 200 para Meta saber que recebemos)
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }
    
    /**
     * Processar webhook baseado no tipo
     */
    private function processWebhook(array $payload): void
    {
        $object = $payload['object'] ?? '';
        
        try {
            switch ($object) {
                case 'instagram':
                    // Webhook do Instagram
                    InstagramGraphService::processWebhook($payload);
                    break;
                    
                case 'whatsapp_business_account':
                    // Webhook do WhatsApp
                    WhatsAppCloudService::processWebhook($payload);
                    break;
                    
                case 'page':
                    // Webhook de página do Facebook (pode ser usado para Instagram às vezes)
                    $this->processFacebookPageWebhook($payload);
                    break;
                    
                default:
                    // Log tipo desconhecido
                    error_log("Meta webhook com object desconhecido: {$object}");
            }
            
        } catch (\Exception $e) {
            error_log("Erro ao processar webhook Meta: {$e->getMessage()}");
        }
    }
    
    /**
     * Processar webhook de Facebook Page (pode incluir Instagram)
     */
    private function processFacebookPageWebhook(array $payload): void
    {
        // Estrutura de Facebook Page pode incluir mensagens do Instagram
        // dependendo de como a integração foi configurada
        
        $entry = $payload['entry'] ?? [];
        
        foreach ($entry as $item) {
            $messaging = $item['messaging'] ?? [];
            
            foreach ($messaging as $event) {
                // Verificar se é do Instagram
                if (isset($event['sender']['id']) && isset($event['recipient']['id'])) {
                    // Pode ser Instagram Direct via Page
                    // Processar similar ao Instagram
                    InstagramGraphService::processWebhook([
                        'object' => 'instagram',
                        'entry' => [$item]
                    ]);
                }
            }
        }
    }
}

