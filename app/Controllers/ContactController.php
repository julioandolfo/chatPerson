<?php
/**
 * Controller de Contatos
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Permission;
use App\Services\ContactService;

class ContactController
{
    /**
     * Listar contatos
     */
    public function index(): void
    {
        Permission::abortIfCannot('contacts.view');
        // Obter filtros da requisição
        $filters = [
            'search' => $_GET['search'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0
        ];

        // Remover filtros vazios
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $contacts = ContactService::list($filters);
            
            Response::view('contacts/index', [
                'contacts' => $contacts,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('contacts/index', [
                'contacts' => [],
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mostrar contato específico
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('contacts.view');
        try {
            $contact = \App\Models\Contact::find($id);
            
            if (!$contact) {
                // Se for requisição AJAX, retornar JSON
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    Response::json([
                        'success' => false,
                        'message' => 'Contato não encontrado'
                    ], 404);
                    return;
                }
                Response::notFound('Contato não encontrado');
                return;
            }

            // Se for requisição AJAX, retornar JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                Response::json([
                    'success' => true,
                    'contact' => $contact
                ]);
                return;
            }

            // Obter conversas do contato
            $conversations = \App\Models\Conversation::where('contact_id', '=', $id);

            Response::view('contacts/show', [
                'contact' => $contact,
                'conversations' => $conversations
            ]);
        } catch (\Exception $e) {
            // Se for requisição AJAX, retornar JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                Response::json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
                return;
            }
            Response::view('errors/404', [
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Criar novo contato
     */
    public function store(): void
    {
        Permission::abortIfCannot('contacts.create');
        
        try {
            $request = \App\Helpers\Request::post();
            
            $data = [
                'name' => $request['name'] ?? '',
                'last_name' => $request['last_name'] ?? null,
                'email' => $request['email'] ?? null,
                'phone' => $request['phone'] ?? null,
                'whatsapp_id' => $request['whatsapp_id'] ?? null,
                'city' => $request['city'] ?? null,
                'country' => $request['country'] ?? null,
                'bio' => $request['bio'] ?? null,
                'company' => $request['company'] ?? null,
                'social_media' => $request['social_media'] ?? null,
                'avatar' => $request['avatar'] ?? null
            ];
            
            // Processar social_media se for string JSON
            if (isset($data['social_media']) && is_string($data['social_media'])) {
                $data['social_media'] = json_decode($data['social_media'], true) ?? [];
            } elseif (!isset($data['social_media'])) {
                $data['social_media'] = [];
            }
            
            // Criar contato primeiro
            $contact = ContactService::createOrUpdate($data);
            
            // Processar upload de avatar se houver
            if (!empty($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK && isset($contact['id'])) {
                $data['avatar'] = ContactService::uploadAvatar($contact['id'], $_FILES['avatar_file']);
                $contact = ContactService::update($contact['id'], ['avatar' => $data['avatar']]);
            }
            
            // Atualizar social_media se necessário
            if (isset($contact['id']) && !empty($data['social_media'])) {
                $contact = ContactService::update($contact['id'], ['social_media' => $data['social_media']]);
            }
            
            Response::json([
                'success' => true,
                'message' => 'Contato criado com sucesso',
                'contact' => $contact
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar contato
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('contacts.edit');
        
        try {
            $request = \App\Helpers\Request::post();
            
            $data = [
                'name' => $request['name'] ?? null,
                'last_name' => $request['last_name'] ?? null,
                'email' => $request['email'] ?? null,
                'phone' => $request['phone'] ?? null,
                'whatsapp_id' => $request['whatsapp_id'] ?? null,
                'city' => $request['city'] ?? null,
                'country' => $request['country'] ?? null,
                'bio' => $request['bio'] ?? null,
                'company' => $request['company'] ?? null,
                'social_media' => $request['social_media'] ?? null,
                'avatar' => $request['avatar'] ?? null
            ];
            
            // Processar upload de avatar se houver
            if (!empty($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $data['avatar'] = ContactService::uploadAvatar($id, $_FILES['avatar_file']);
            }
            
            // Processar social_media se for string JSON
            if (isset($data['social_media']) && is_string($data['social_media'])) {
                $data['social_media'] = json_decode($data['social_media'], true) ?? [];
            } elseif (!isset($data['social_media'])) {
                $data['social_media'] = [];
            }

            // Remover campos vazios (exceto arrays vazios que podem ser válidos)
            $data = array_filter($data, function($value) {
                if (is_array($value)) {
                    return true; // Manter arrays mesmo vazios
                }
                return $value !== null && $value !== '';
            });

            $contact = ContactService::update($id, $data);
            
            Response::json([
                'success' => true,
                'message' => 'Contato atualizado com sucesso',
                'contact' => $contact
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Upload de avatar do contato
     */
    public function uploadAvatar(int $id): void
    {
        Permission::abortIfCannot('contacts.edit');
        
        try {
            if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao fazer upload do arquivo'
                ], 400);
                return;
            }

            $avatarPath = ContactService::uploadAvatar($id, $_FILES['avatar']);
            
            Response::json([
                'success' => true,
                'message' => 'Avatar atualizado com sucesso',
                'avatar' => $avatarPath
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deletar contato
     */
    public function destroy(int $id): void
    {
        // Verificar permissão - admin global pode deletar qualquer contato mesmo com conversas
        $user = \App\Helpers\Auth::user();
        $force = false;
        
        if ($user && ($user['role'] === 'super_admin' || $user['role'] === 'admin')) {
            // Admin global pode forçar deleção mesmo com conversas
            $force = true;
        } else {
            Permission::abortIfCannot('contacts.delete');
        }
        
        try {
            ContactService::delete($id, $force);
            
            Response::json([
                'success' => true,
                'message' => 'Contato deletado com sucesso' . ($force ? ' (incluindo conversas associadas)' : '')
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Métricas de histórico do contato (para aba Histórico no sidebar)
     */
    public function getHistoryMetrics(int $id): void
    {
        Permission::abortIfCannot('contacts.view');

        try {
            // Verificar se contato existe
            $contact = \App\Models\Contact::find($id);
            if (!$contact) {
                Response::json([
                    'success' => false,
                    'message' => 'Contato não encontrado'
                ], 404);
                return;
            }

            // Conversas fechadas/resolvidas para métricas
            $stats = \App\Helpers\Database::fetch("
                SELECT 
                    COUNT(*) AS total_conversations,
                    AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) AS avg_duration_seconds
                FROM conversations
                WHERE contact_id = ? AND status IN ('closed', 'resolved')
            ", [$id]);

            // Conversas anteriores (últimas 5 fechadas/resolvidas)
            $previous = \App\Helpers\Database::fetchAll("
                SELECT 
                    c.id,
                    c.status,
                    c.created_at,
                    c.updated_at,
                    (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message
                FROM conversations c
                WHERE c.contact_id = ? AND c.status IN ('closed', 'resolved')
                ORDER BY c.updated_at DESC
                LIMIT 5
            ", [$id]);

            Response::json([
                'success' => true,
                'contact_id' => $id,
                'total_conversations' => (int)($stats['total_conversations'] ?? 0),
                'avg_duration_seconds' => $stats['avg_duration_seconds'] !== null ? (int)$stats['avg_duration_seconds'] : null,
                // Não há CSAT armazenado atualmente; deixar null/--
                'csat_score' => null,
                'previous_conversations' => $previous ?: []
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

