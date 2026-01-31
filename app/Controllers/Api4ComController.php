<?php
/**
 * Controller Api4ComController
 * Gerenciamento de contas Api4Com
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Encryption;
use App\Models\Api4ComAccount;
use App\Helpers\Validator;

class Api4ComController
{
    /**
     * Listar contas Api4Com
     */
    public function index(): void
    {
        Permission::abortIfCannot('api4com.view');
        
        $accounts = Api4ComAccount::all();
        
        Response::view('integrations/api4com/index', [
            'accounts' => $accounts
        ]);
    }

    /**
     * Criar conta Api4Com
     */
    public function create(): void
    {
        Permission::abortIfCannot('api4com.create');
        
        try {
            $data = Request::post();
            
            $errors = Validator::validate($data, [
                'name' => 'required|string|max:255',
                'api_url' => 'required|string|max:500',
                'api_token' => 'required|string|max:500',
                'domain' => 'nullable|string|max:255',
                'enabled' => 'nullable|boolean'
            ]);

            if (!empty($errors)) {
                throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
            }

            $accountData = [
                'name' => $data['name'],
                'api_url' => rtrim($data['api_url'], '/'),
                'api_token' => $data['api_token'],
                'domain' => $data['domain'] ?? null,
                'enabled' => isset($data['enabled']) ? (int)$data['enabled'] : 1,
                'webhook_url' => $data['webhook_url'] ?? null,
                'config' => json_encode($data['config'] ?? [])
            ];

            $accountId = Api4ComAccount::create($accountData);
            
            Response::json([
                'success' => true,
                'message' => 'Conta Api4Com criada com sucesso!',
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
     * Atualizar conta Api4Com
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('api4com.edit');
        
        try {
            $data = Request::post();
            
            $errors = Validator::validate($data, [
                'name' => 'nullable|string|max:255',
                'api_url' => 'nullable|string|max:500',
                'api_token' => 'nullable|string|max:500',
                'domain' => 'nullable|string|max:255',
                'enabled' => 'nullable|boolean',
                'webhook_url' => 'nullable|string|max:500'
            ]);

            if (!empty($errors)) {
                throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
            }

            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['api_url'])) $updateData['api_url'] = rtrim($data['api_url'], '/');
            if (!empty($data['api_token'])) $updateData['api_token'] = $data['api_token'];
            if (isset($data['domain'])) $updateData['domain'] = $data['domain'];
            if (isset($data['enabled'])) $updateData['enabled'] = (int)$data['enabled'];
            if (isset($data['webhook_url'])) $updateData['webhook_url'] = $data['webhook_url'];
            
            // Campos WebPhone SIP
            if (isset($data['sip_domain'])) $updateData['sip_domain'] = $data['sip_domain'];
            if (isset($data['sip_port'])) $updateData['sip_port'] = (int)$data['sip_port'];
            if (isset($data['webphone_enabled'])) $updateData['webphone_enabled'] = (int)$data['webphone_enabled'];
            
            // Processar configurações avançadas
            $config = [];
            if (!empty($data['config_dialer_endpoint'])) {
                $config['dialer_endpoint'] = $data['config_dialer_endpoint'];
            }
            if (!empty($data['config_extension_field'])) {
                $config['extension_field'] = $data['config_extension_field'];
            }
            if (!empty($data['config_phone_field'])) {
                $config['phone_field'] = $data['config_phone_field'];
            }
            // Novo: qual campo do ramal usar (extension_number ou extension_id)
            if (!empty($data['config_extension_value_field'])) {
                $config['extension_value_field'] = $data['config_extension_value_field'];
            }
            if (!empty($config)) {
                $updateData['config'] = json_encode($config);
            } elseif (isset($data['config'])) {
                $updateData['config'] = is_string($data['config']) ? $data['config'] : json_encode($data['config']);
            }

            if (Api4ComAccount::update($id, $updateData)) {
                Response::json([
                    'success' => true,
                    'message' => 'Conta atualizada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Conta não encontrada'
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
     * Deletar conta Api4Com
     */
    public function delete(int $id): void
    {
        Permission::abortIfCannot('api4com.delete');
        
        try {
            if (Api4ComAccount::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Conta deletada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Conta não encontrada'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao deletar conta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincronizar ramais da API Api4Com
     */
    public function syncExtensions(int $id): void
    {
        Permission::abortIfCannot('api4com.edit');
        
        try {
            $account = Api4ComAccount::find($id);
            if (!$account) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta não encontrada'
                ], 404);
                return;
            }

            // Tentar sincronizar
            try {
                $result = \App\Services\Api4ComService::syncAllExtensions($id);
                
                $message = "Sincronização concluída: {$result['synced']} de {$result['total']} ramais.";
                if (!empty($result['errors'])) {
                    $message .= " Erros: " . count($result['errors']);
                }
                
                Response::json([
                    'success' => true,
                    'message' => $message,
                    'result' => $result
                ]);
            } catch (\Exception $apiError) {
                // Se falhou na API, talvez precise rodar migration
                // Verificar se é erro de constraint/coluna
                $errorMsg = $apiError->getMessage();
                
                if (strpos($errorMsg, 'Column') !== false || strpos($errorMsg, 'constraint') !== false || strpos($errorMsg, 'user_id') !== false) {
                    Response::json([
                        'success' => false,
                        'message' => 'Erro de banco de dados. Execute a migration: php database/migrate.php'
                    ], 500);
                } else {
                    throw $apiError;
                }
            }
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Api4ComController::syncExtensions - Erro: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro ao sincronizar ramais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar ramais de uma conta
     */
    public function extensions(int $id): void
    {
        Permission::abortIfCannot('api4com.view');
        
        try {
            $account = Api4ComAccount::find($id);
            if (!$account) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta não encontrada'
                ], 404);
                return;
            }

            $extensions = \App\Models\Api4ComExtension::getByAccountWithUser($id);
            
            Response::json([
                'success' => true,
                'extensions' => $extensions
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao listar ramais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Associar ramal a usuário
     */
    public function assignExtension(int $accountId, int $extensionId): void
    {
        Permission::abortIfCannot('api4com.edit');
        
        try {
            $data = Request::post();
            $userId = isset($data['user_id']) && $data['user_id'] !== '' ? (int)$data['user_id'] : null;
            
            \App\Services\Api4ComService::assignExtensionToUser($extensionId, $userId);
            
            Response::json([
                'success' => true,
                'message' => $userId ? 'Ramal associado ao usuário com sucesso!' : 'Ramal desassociado com sucesso!'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao associar ramal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter conta por ID (API)
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('api4com.view');
        
        try {
            $account = Api4ComAccount::find($id);
            if (!$account) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta não encontrada'
                ], 404);
                return;
            }

            Response::json([
                'success' => true,
                'account' => $account
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar conta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testar conexão com a API Api4Com
     */
    public function testConnection(int $id): void
    {
        Permission::abortIfCannot('api4com.view');
        
        try {
            $account = Api4ComAccount::find($id);
            if (!$account) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta não encontrada'
                ], 404);
                return;
            }

            $result = \App\Services\Api4ComService::testConnection($id);
            
            Response::json($result);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar ramal manualmente
     */
    public function createExtension(int $accountId): void
    {
        Permission::abortIfCannot('api4com.edit');
        
        try {
            $account = Api4ComAccount::find($accountId);
            if (!$account) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta não encontrada'
                ], 404);
                return;
            }

            $data = Request::post();
            
            $errors = Validator::validate($data, [
                'extension_id' => 'nullable|string',
                'extension_number' => 'required|string',
                'sip_username' => 'nullable|string',
                'user_id' => 'nullable|integer'
            ]);

            if (!empty($errors)) {
                throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
            }

            // Verificar se já existe
            $existing = \App\Models\Api4ComExtension::findByExtensionId($data['extension_id'] ?? $data['extension_number'], $accountId);
            
            if ($existing) {
                throw new \InvalidArgumentException('Já existe um ramal com este ID/número nesta conta');
            }

            $extensionData = [
                'api4com_account_id' => $accountId,
                'extension_id' => $data['extension_id'] ?? $data['extension_number'],
                'extension_number' => $data['extension_number'],
                'sip_username' => $data['sip_username'] ?? null,
                'user_id' => !empty($data['user_id']) ? (int)$data['user_id'] : null,
                'status' => 'active',
                'metadata' => json_encode([
                    'name' => $data['name'] ?? "Ramal {$data['extension_number']}",
                    'created_manually' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ])
            ];

            $extensionId = \App\Models\Api4ComExtension::create($extensionData);
            
            Response::json([
                'success' => true,
                'message' => 'Ramal criado com sucesso!',
                'extension_id' => $extensionId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Api4ComController::createExtension - Erro: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar ramal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar ramal
     */
    public function deleteExtension(int $accountId, int $extensionId): void
    {
        Permission::abortIfCannot('api4com.delete');
        
        try {
            $extension = \App\Models\Api4ComExtension::find($extensionId);
            if (!$extension || $extension['api4com_account_id'] != $accountId) {
                Response::json([
                    'success' => false,
                    'message' => 'Ramal não encontrado'
                ], 404);
                return;
            }

            \App\Models\Api4ComExtension::delete($extensionId);
            
            Response::json([
                'success' => true,
                'message' => 'Ramal deletado com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao deletar ramal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar configurações SIP de um ramal (para WebPhone)
     */
    public function updateExtensionSip(int $accountId, int $extensionId): void
    {
        Permission::abortIfCannot('api4com.edit');
        
        try {
            $extension = \App\Models\Api4ComExtension::find($extensionId);
            if (!$extension || $extension['api4com_account_id'] != $accountId) {
                Response::json([
                    'success' => false,
                    'message' => 'Ramal não encontrado'
                ], 404);
                return;
            }

            $data = Request::post();
            $updateData = [];
            
            // Atualizar senha SIP (criptografada)
            if (!empty($data['sip_password'])) {
                $updateData['sip_password_encrypted'] = Encryption::encrypt($data['sip_password']);
            }
            
            // Atualizar se WebPhone está habilitado
            if (isset($data['webphone_enabled'])) {
                $updateData['webphone_enabled'] = (int)$data['webphone_enabled'];
            }
            
            if (!empty($updateData)) {
                \App\Models\Api4ComExtension::update($extensionId, $updateData);
            }
            
            Response::json([
                'success' => true,
                'message' => 'Configurações SIP atualizadas com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar configurações SIP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter credenciais SIP para WebPhone do usuário logado
     */
    public function getWebphoneCredentials(): void
    {
        try {
            $userId = \App\Helpers\Auth::id();
            \App\Helpers\Logger::api4com("getWebphoneCredentials - Iniciando para user_id: {$userId}");
            
            // Buscar conta Api4Com habilitada
            $account = Api4ComAccount::getFirstEnabled();
            if (!$account) {
                \App\Helpers\Logger::api4com("getWebphoneCredentials - Nenhuma conta habilitada encontrada", 'WARNING');
                Response::json([
                    'success' => false,
                    'message' => 'Nenhuma conta Api4Com configurada'
                ], 400);
                return;
            }
            \App\Helpers\Logger::api4com("getWebphoneCredentials - Conta encontrada: {$account['id']} - {$account['name']}");
            
            // Buscar ramal do usuário
            $extension = \App\Models\Api4ComExtension::findByUserAndAccount($userId, $account['id']);
            if (!$extension) {
                \App\Helpers\Logger::api4com("getWebphoneCredentials - Nenhum ramal para user_id {$userId} na conta {$account['id']}", 'WARNING');
                Response::json([
                    'success' => false,
                    'message' => 'Nenhum ramal associado ao seu usuário'
                ], 400);
                return;
            }
            \App\Helpers\Logger::api4com("getWebphoneCredentials - Ramal encontrado: {$extension['extension_number']} (ID: {$extension['id']})");
            
            // Verificar se tem senha SIP configurada
            if (empty($extension['sip_password_encrypted'])) {
                \App\Helpers\Logger::api4com("getWebphoneCredentials - Senha SIP não configurada para ramal {$extension['id']}", 'WARNING');
                Response::json([
                    'success' => false,
                    'message' => 'Senha SIP não configurada para este ramal. Configure em Integrações → Api4Com → Ramais'
                ], 400);
                return;
            }
            \App\Helpers\Logger::api4com("getWebphoneCredentials - Senha SIP encontrada (encrypted)");
            
            // Descriptografar senha
            $sipPassword = Encryption::decrypt($extension['sip_password_encrypted']);
            if (!$sipPassword) {
                \App\Helpers\Logger::api4com("getWebphoneCredentials - Falha ao descriptografar senha SIP", 'ERROR');
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao recuperar senha SIP'
                ], 500);
                return;
            }
            \App\Helpers\Logger::api4com("getWebphoneCredentials - Senha SIP descriptografada com sucesso");
            
            // Determinar domínio SIP
            $sipDomain = $account['sip_domain'] ?? $account['domain'] ?? null;
            $logSipDomain = $account['sip_domain'] ?? '(null)';
            $logDomain = $account['domain'] ?? '(null)';
            \App\Helpers\Logger::api4com("getWebphoneCredentials - Domínio: sip_domain={$logSipDomain}, domain={$logDomain}, final=" . ($sipDomain ?? '(null)'));
            if (empty($sipDomain)) {
                \App\Helpers\Logger::api4com("getWebphoneCredentials - Domínio SIP não configurado", 'WARNING');
                Response::json([
                    'success' => false,
                    'message' => 'Domínio SIP não configurado. Configure em Integrações → Api4Com'
                ], 400);
                return;
            }
            
            // Porta WebSocket
            $sipPort = $account['sip_port'] ?? 6443;
            
            \App\Helpers\Logger::api4com("getWebphoneCredentials - Sucesso! Retornando credenciais para ramal {$extension['extension_number']} @ {$sipDomain}:{$sipPort}");
            
            Response::json([
                'success' => true,
                'credentials' => [
                    'domain' => $sipDomain,
                    'port' => $sipPort,
                    'websocket_url' => "wss://{$sipDomain}:{$sipPort}",
                    'extension' => $extension['extension_number'],
                    'username' => $extension['extension_number'],
                    'password' => $sipPassword,
                    'realm' => $sipDomain
                ]
            ]);
        } catch (\Exception $e) {
            \App\Helpers\Logger::api4com("getWebphoneCredentials - Erro: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString(), 'ERROR');
            Response::json([
                'success' => false,
                'message' => 'Erro ao obter credenciais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar configurações WebPhone da conta
     */
    public function updateWebphoneSettings(int $id): void
    {
        Permission::abortIfCannot('api4com.edit');
        
        try {
            $account = Api4ComAccount::find($id);
            if (!$account) {
                Response::json([
                    'success' => false,
                    'message' => 'Conta não encontrada'
                ], 404);
                return;
            }

            $data = Request::post();
            $updateData = [];
            
            if (isset($data['sip_domain'])) {
                $updateData['sip_domain'] = $data['sip_domain'];
            }
            if (isset($data['sip_port'])) {
                $updateData['sip_port'] = (int)$data['sip_port'];
            }
            if (isset($data['webphone_enabled'])) {
                $updateData['webphone_enabled'] = (int)$data['webphone_enabled'];
            }
            
            if (!empty($updateData)) {
                Api4ComAccount::update($id, $updateData);
            }
            
            Response::json([
                'success' => true,
                'message' => 'Configurações WebPhone atualizadas!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar configurações: ' . $e->getMessage()
            ], 500);
        }
    }
}

