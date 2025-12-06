<?php
/**
 * Controller de Integrações
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\WhatsAppService;
use App\Models\WhatsAppAccount;

class IntegrationController
{
    /**
     * Listar integrações
     */
    public function index(): void
    {
        Permission::abortIfCannot('integrations.view');
        Response::view('integrations/index', [
            'integrations' => []
        ]);
    }

    /**
     * Configurar WhatsApp
     */
    public function whatsapp(): void
    {
        Permission::abortIfCannot('whatsapp.view');
        
        try {
            $accounts = WhatsAppAccount::all();
            Response::view('integrations/whatsapp', [
                'whatsapp_accounts' => $accounts
            ]);
        } catch (\Exception $e) {
            Response::view('integrations/whatsapp', [
                'whatsapp_accounts' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Criar conta WhatsApp
     */
    public function createWhatsAppAccount(): void
    {
        Permission::abortIfCannot('whatsapp.create');
        
        try {
            $data = Request::post();
            
            // Validar campos obrigatórios para Quepasa
            if ($data['provider'] === 'quepasa' && empty($data['quepasa_user'])) {
                throw new \InvalidArgumentException('Quepasa User é obrigatório para Quepasa API');
            }
            
            $accountId = WhatsAppService::createAccount($data);
            
            Response::json([
                'success' => true,
                'message' => 'Conta WhatsApp criada com sucesso!',
                'id' => $accountId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar conta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter QR Code para conexão
     */
    public function getQRCode(int $id): void
    {
        Permission::abortIfCannot('whatsapp.view');
        
        try {
            $qrData = WhatsAppService::getQRCode($id);
            
            Response::json([
                'success' => true,
                'qrcode' => $qrData['qrcode'],
                'base64' => $qrData['base64'] ?? null,
                'expires_in' => $qrData['expires_in'] ?? 60
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Verificar status da conexão
     */
    public function getConnectionStatus(int $id): void
    {
        Permission::abortIfCannot('whatsapp.view');
        
        try {
            $status = WhatsAppService::getConnectionStatus($id);
            
            Response::json([
                'success' => true,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Desconectar WhatsApp
     */
    public function disconnect(int $id): void
    {
        Permission::abortIfCannot('whatsapp.edit');
        
        try {
            if (WhatsAppService::disconnect($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'WhatsApp desconectado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao desconectar'
                ], 500);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar conta WhatsApp
     */
    public function updateWhatsAppAccount(int $id): void
    {
        Permission::abortIfCannot('whatsapp.edit');
        
        try {
            $data = Request::post();
            
            // Validar dados
            $errors = \App\Helpers\Validator::validate($data, [
                'name' => 'nullable|string|max:255',
                'api_url' => 'nullable|string|max:500',
                'quepasa_user' => 'nullable|string|max:255',
                'quepasa_trackid' => 'nullable|string|max:255',
                'api_key' => 'nullable|string|max:255',
                'instance_id' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:active,inactive,disconnected'
            ]);

            if (!empty($errors)) {
                throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
            }

            if (isset($data['config']) && is_array($data['config'])) {
                $data['config'] = json_encode($data['config']);
            }

            if (WhatsAppAccount::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Conta atualizada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar conta'
                ], 404);
            }
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar conta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configurar webhook automaticamente
     */
    public function configureWebhook(int $id): void
    {
        Permission::abortIfCannot('whatsapp.edit');
        
        try {
            $webhookUrl = Request::post('webhook_url');
            if (!$webhookUrl) {
                // Gerar URL do webhook automaticamente
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $webhookUrl = "{$protocol}://{$host}/whatsapp-webhook";
            }
            
            if (WhatsAppService::configureWebhook($id, $webhookUrl)) {
                Response::json([
                    'success' => true,
                    'message' => 'Webhook configurado com sucesso!',
                    'webhook_url' => $webhookUrl
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao configurar webhook'
                ], 500);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deletar conta WhatsApp
     */
    public function deleteWhatsAppAccount(int $id): void
    {
        Permission::abortIfCannot('whatsapp.delete');
        
        try {
            // Desconectar antes de deletar
            try {
                WhatsAppService::disconnect($id);
            } catch (\Exception $e) {
                // Ignorar erro de desconexão
            }

            if (WhatsAppAccount::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Conta deletada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar conta'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Enviar mensagem de teste
     */
    public function sendTestMessage(int $id): void
    {
        Permission::abortIfCannot('whatsapp.edit');
        
        try {
            $to = Request::post('to');
            $message = Request::post('message', 'Mensagem de teste do sistema');
            
            if (!$to) {
                throw new \InvalidArgumentException('Número de destino é obrigatório');
            }

            $result = WhatsAppService::sendMessage($id, $to, $message);
            
            Response::json([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso!',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
