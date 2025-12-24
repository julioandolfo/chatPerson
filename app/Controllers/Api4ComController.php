<?php
/**
 * Controller Api4ComController
 * Gerenciamento de contas Api4Com
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
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
            if (isset($data['api_token'])) $updateData['api_token'] = $data['api_token'];
            if (isset($data['domain'])) $updateData['domain'] = $data['domain'];
            if (isset($data['enabled'])) $updateData['enabled'] = (int)$data['enabled'];
            if (isset($data['webhook_url'])) $updateData['webhook_url'] = $data['webhook_url'];
            if (isset($data['config'])) $updateData['config'] = json_encode($data['config']);

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
     * Sincronizar ramais
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

            $data = Request::post();
            $userId = $data['user_id'] ?? null;
            $extensionData = $data['extension_data'] ?? [];

            if (!$userId) {
                throw new \InvalidArgumentException('user_id é obrigatório');
            }

            $extensionId = \App\Services\Api4ComService::syncExtension($userId, $id, $extensionData);
            
            Response::json([
                'success' => true,
                'message' => 'Ramal sincronizado com sucesso!',
                'extension_id' => $extensionId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao sincronizar ramal: ' . $e->getMessage()
            ], 500);
        }
    }
}

