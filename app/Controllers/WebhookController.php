<?php
/**
 * Controller de Webhooks
 */

namespace App\Controllers;

use App\Services\WhatsAppService;
use App\Helpers\Logger;

class WebhookController
{
    /**
     * Receber webhook do WhatsApp
     */
    public function whatsapp(): void
    {
        // Permitir apenas POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        // Obter payload
        $rawInput = file_get_contents('php://input');
        $payload = json_decode($rawInput, true);

        if (!$payload) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }

        Logger::log("WhatsApp Webhook recebido: " . json_encode($payload));

        try {
            // Detectar evento do Quepasa (message.updated para ACK)
            $event = $payload['event'] ?? null;
            
            // Verificar se é webhook de status (delivered, read, failed) ou ACK (message.updated)
            $status = $payload['status'] ?? null;
            $isStatusWebhook = in_array($status, ['sent', 'delivered', 'read', 'failed']) 
                && (isset($payload['id']) || isset($payload['message_id']))
                && !isset($payload['text']) && !isset($payload['message']);
            
            if ($isStatusWebhook) {
                // Processar webhook de status
                WhatsAppService::processStatusWebhook($payload);
            } elseif ($event === 'message.updated') {
                // Webhook de atualização de ACK (Quepasa)
                WhatsAppService::processAckWebhook($payload);
            } else {
                // Processar webhook de mensagem recebida
                WhatsAppService::processWebhook($payload);
            }
            
            // Responder com sucesso
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            Logger::error("WhatsApp Webhook Error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Receber webhook genérico
     */
    public function receive(string $webhookId): void
    {
        // Permitir apenas POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        // Obter payload
        $rawInput = file_get_contents('php://input');
        $payload = json_decode($rawInput, true);

        if (!$payload) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }

        Logger::log("Webhook recebido [{$webhookId}]: " . json_encode($payload));

        try {
            // Por enquanto, apenas logar
            // Futuramente, pode processar diferentes tipos de webhooks
            
            // Responder com sucesso
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            Logger::error("Webhook Error [{$webhookId}]: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Listar webhooks configurados
     */
    public function index(): void
    {
        \App\Helpers\Permission::abortIfCannot('integrations.view');
        
        \App\Helpers\Response::view('integrations/webhooks', [
            'webhooks' => []
        ]);
    }
}
