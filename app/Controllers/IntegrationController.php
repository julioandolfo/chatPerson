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
            // Buscar contas WhatsApp com informações de funil/etapa
            $accounts = WhatsAppAccount::all();
            
            // Enriquecer com nomes de funil e etapa
            foreach ($accounts as &$account) {
                if (!empty($account['default_funnel_id'])) {
                    $funnel = \App\Models\Funnel::find($account['default_funnel_id']);
                    $account['default_funnel_name'] = $funnel['name'] ?? null;
                }
                
                if (!empty($account['default_stage_id'])) {
                    $stage = \App\Models\FunnelStage::find($account['default_stage_id']);
                    $account['default_stage_name'] = $stage['name'] ?? null;
                }
            }
            
            // Buscar funis disponíveis
            $funnels = \App\Models\Funnel::whereActive();
            
            Response::view('integrations/whatsapp', [
                'whatsapp_accounts' => $accounts,
                'funnels' => $funnels
            ]);
        } catch (\Exception $e) {
            Response::view('integrations/whatsapp', [
                'whatsapp_accounts' => [],
                'funnels' => [],
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
            // Limpar qualquer output buffer antes de enviar JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            $qrData = WhatsAppService::getQRCode($id);
            
            // Verificar se os dados foram retornados corretamente
            if (empty($qrData) || empty($qrData['qrcode'])) {
                throw new \Exception('QR Code não foi gerado corretamente');
            }
            
            Response::json([
                'success' => true,
                'qrcode' => $qrData['qrcode'],
                'base64' => $qrData['base64'] ?? null,
                'expires_in' => $qrData['expires_in'] ?? 60
            ]);
        } catch (\Exception $e) {
            // Limpar output buffer antes de enviar erro
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            \App\Helpers\Logger::error("IntegrationController::getQRCode Error: " . $e->getMessage());
            \App\Helpers\Logger::error("IntegrationController::getQRCode Stack: " . $e->getTraceAsString());
            
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
     * Atualizar configurações de funil/etapa da conta WhatsApp
     */
    public function updateWhatsAppAccountSettings(int $id): void
    {
        Permission::abortIfCannot('whatsapp.edit');
        
        try {
            $data = Request::post();
            
            // Validar dados
            $errors = \App\Helpers\Validator::validate($data, [
                'default_funnel_id' => 'nullable|integer',
                'default_stage_id' => 'nullable|integer'
            ]);

            if (!empty($errors)) {
                throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
            }
            
            // Preparar dados (converter vazios em NULL)
            $updateData = [
                'default_funnel_id' => !empty($data['default_funnel_id']) ? (int)$data['default_funnel_id'] : null,
                'default_stage_id' => !empty($data['default_stage_id']) ? (int)$data['default_stage_id'] : null
            ];
            
            // Atualizar
            WhatsAppAccount::update($id, $updateData);
            
            Response::json([
                'success' => true,
                'message' => 'Configurações atualizadas com sucesso!'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar configurações: ' . $e->getMessage()
            ], 500);
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
