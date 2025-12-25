<?php
/**
 * Controller de Integrações
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\WhatsAppService;
use App\Services\NotificameService;
use App\Models\WhatsAppAccount;
use App\Models\IntegrationAccount;

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
     * Configurar Api4Com
     */
    public function api4com(): void
    {
        Permission::abortIfCannot('api4com.view');
        
        $accounts = \App\Models\Api4ComAccount::all();
        
        Response::view('integrations/api4com/index', [
            'accounts' => $accounts
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

    /**
     * Configurar WavoIP para conta WhatsApp
     */
    public function configureWavoip(int $id): void
    {
        Permission::abortIfCannot('whatsapp.edit');
        
        try {
            $data = Request::post();
            
            // Validar dados
            $errors = \App\Helpers\Validator::validate($data, [
                'wavoip_token' => 'nullable|string|max:255',
                'wavoip_enabled' => 'nullable|boolean'
            ]);

            if (!empty($errors)) {
                throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
            }
            
            $updateData = [];
            
            if (isset($data['wavoip_token'])) {
                $updateData['wavoip_token'] = $data['wavoip_token'] ?: null;
            }
            
            if (isset($data['wavoip_enabled'])) {
                $updateData['wavoip_enabled'] = $data['wavoip_enabled'] ? 1 : 0;
            }
            
            if (empty($updateData)) {
                throw new \InvalidArgumentException('Nenhum dado para atualizar');
            }

            if (WhatsAppAccount::update($id, $updateData)) {
                Response::json([
                    'success' => true,
                    'message' => 'Configuração WavoIP atualizada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar configuração'
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
                'message' => 'Erro ao atualizar configuração: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // MÉTODOS NOTIFICAME
    // ============================================

    /**
     * Listar contas Notificame
     */
    public function notificame(): void
    {
        Permission::abortIfCannot('notificame.view');
        
        try {
            $channel = Request::get('channel');
            $accounts = NotificameService::listAccounts($channel);
            
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
            
            Response::view('integrations/notificame/index', [
                'accounts' => $accounts,
                'funnels' => $funnels,
                'channels' => NotificameService::CHANNELS
            ]);
        } catch (\Exception $e) {
            Response::view('integrations/notificame/index', [
                'accounts' => [],
                'funnels' => [],
                'channels' => NotificameService::CHANNELS,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Criar conta Notificame
     */
    public function createNotificameAccount(): void
    {
        Permission::abortIfCannot('notificame.create');
        
        try {
            $data = Request::post();
            
            $accountId = NotificameService::createAccount($data);
            
            Response::json([
                'success' => true,
                'message' => 'Conta Notificame criada com sucesso!',
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
     * Atualizar conta Notificame
     */
    public function updateNotificameAccount(int $id): void
    {
        Permission::abortIfCannot('notificame.edit');
        
        try {
            $data = Request::post();
            
            // Validar dados
            $errors = \App\Helpers\Validator::validate($data, [
                'name' => 'nullable|string|max:255',
                'api_token' => 'nullable|string',
                'account_id' => 'nullable|string|max:255',
                'default_funnel_id' => 'nullable|integer',
                'default_stage_id' => 'nullable|integer'
            ]);
            
            if (!empty($errors)) {
                throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
            }
            
            if (NotificameService::updateAccount($id, $data)) {
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
     * Deletar conta Notificame
     */
    public function deleteNotificameAccount(int $id): void
    {
        Permission::abortIfCannot('notificame.delete');
        
        try {
            if (NotificameService::deleteAccount($id)) {
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
     * Verificar status da conexão Notificame
     */
    public function checkNotificameStatus(int $id): void
    {
        Permission::abortIfCannot('notificame.view');
        
        try {
            $status = NotificameService::checkConnection($id);
            
            Response::json([
                'success' => true,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao verificar status Notificame: " . $e->getMessage());
            Response::json([
                'success' => false,
                'status' => [
                    'status' => 'error',
                    'connected' => false,
                    'message' => $e->getMessage()
                ]
            ], 400);
        }
    }

    /**
     * Enviar mensagem de teste Notificame
     */
    public function sendNotificameTestMessage(int $id): void
    {
        Permission::abortIfCannot('notificame.send');
        
        try {
            $to = Request::post('to');
            $message = Request::post('message', 'Mensagem de teste do sistema');
            
            if (!$to) {
                throw new \InvalidArgumentException('Destinatário é obrigatório');
            }
            
            if (empty(trim($message))) {
                throw new \InvalidArgumentException('Mensagem não pode estar vazia');
            }

            $result = NotificameService::sendMessage($id, $to, $message);
            
            Response::json([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso!',
                'data' => $result
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao enviar mensagem de teste Notificame: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configurar webhook Notificame
     */
    public function configureNotificameWebhook(int $id): void
    {
        Permission::abortIfCannot('notificame.edit');
        
        try {
            $webhookUrl = Request::post('webhook_url');
            $events = Request::post('events', []);
            
            if (!$webhookUrl || empty(trim($webhookUrl))) {
                // Gerar URL do webhook automaticamente
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $webhookUrl = "{$protocol}://{$host}/webhooks/notificame";
            }
            
            // Validar URL
            if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('URL do webhook inválida');
            }
            
            if (NotificameService::configureWebhook($id, $webhookUrl, $events)) {
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
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao configurar webhook Notificame: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro ao configurar webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar templates Notificame
     */
    public function listNotificameTemplates(int $id): void
    {
        Permission::abortIfCannot('notificame.view');
        
        try {
            $templates = NotificameService::listTemplates($id);
            
            Response::json([
                'success' => true,
                'templates' => $templates
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
