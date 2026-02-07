<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Services\MetaIntegrationService;
use App\Services\InstagramGraphService;
use App\Services\WhatsAppCloudService;
use App\Services\WhatsAppCoexService;

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
                    // Webhook do WhatsApp - processar por tipo de campo
                    $this->processWhatsAppWebhook($payload);
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
     * Processar webhook do WhatsApp Business Account
     * Roteia para o serviço correto baseado no campo do change
     */
    private function processWhatsAppWebhook(array $payload): void
    {
        $entries = $payload['entry'] ?? [];
        
        // Detectar quais campos estão presentes para rotear
        $hasMessages = false;
        $hasCoexFields = false;
        $coexFields = [];
        
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $field = $change['field'] ?? '';
                
                switch ($field) {
                    case 'messages':
                        $hasMessages = true;
                        break;
                    case 'smb_message_echoes':
                    case 'smb_app_state_sync':
                    case 'business_capability_update':
                    case 'account_update':
                    case 'history':
                        $hasCoexFields = true;
                        $coexFields[] = $field;
                        break;
                    case 'message_template_status_update':
                    case 'message_template_quality_update':
                        // Processar atualizações de status de template
                        $this->processTemplateStatusWebhook($change);
                        break;
                }
            }
        }
        
        // Processar mensagens normais (Cloud API)
        if ($hasMessages) {
            WhatsAppCloudService::processWebhook($payload);
        }
        
        // Processar campos CoEx
        if ($hasCoexFields) {
            foreach ($coexFields as $field) {
                switch ($field) {
                    case 'smb_message_echoes':
                        WhatsAppCoexService::processSmbMessageEchoes($payload);
                        break;
                    case 'smb_app_state_sync':
                        WhatsAppCoexService::processSmbAppStateSync($payload);
                        break;
                    case 'business_capability_update':
                        WhatsAppCoexService::processBusinessCapabilityUpdate($payload);
                        break;
                    case 'account_update':
                        WhatsAppCoexService::processAccountUpdate($payload);
                        break;
                    case 'history':
                        WhatsAppCoexService::processHistoryWebhook($payload);
                        break;
                }
            }
        }
        
        // Se não tinha campos específicos, processar genérico
        if (!$hasMessages && !$hasCoexFields) {
            WhatsAppCloudService::processWebhook($payload);
        }
    }
    
    /**
     * Processar webhook de atualização de status de template
     */
    private function processTemplateStatusWebhook(array $change): void
    {
        try {
            $value = $change['value'] ?? [];
            $templateId = $value['message_template_id'] ?? null;
            $status = $value['event'] ?? null;
            $reason = $value['reason'] ?? null;
            
            if ($templateId) {
                $template = \App\Models\WhatsAppTemplate::findByTemplateId($templateId);
                if ($template) {
                    $updateData = ['last_synced_at' => date('Y-m-d H:i:s')];
                    
                    // Mapear evento para status
                    $statusMap = [
                        'APPROVED' => 'APPROVED',
                        'REJECTED' => 'REJECTED',
                        'PENDING_DELETION' => 'DISABLED',
                        'DISABLED' => 'DISABLED',
                        'FLAGGED' => 'PAUSED',
                        'REINSTATED' => 'APPROVED',
                    ];
                    
                    if (isset($statusMap[$status])) {
                        $updateData['status'] = $statusMap[$status];
                    }
                    
                    if ($reason) {
                        $updateData['rejection_reason'] = $reason;
                    }
                    
                    \App\Models\WhatsAppTemplate::update($template['id'], $updateData);
                    
                    error_log("Template {$template['name']} atualizado via webhook: {$status}");
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao processar template status webhook: {$e->getMessage()}");
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

