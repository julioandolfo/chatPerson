<?php
/**
 * ContactsController - API v1
 * Gerenciamento de contatos
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Services\ContactService;
use App\Models\Contact;
use App\Models\Conversation;

class ContactsController
{
    /**
     * Listar contatos
     * GET /api/v1/contacts
     */
    public function index(): void
    {
        ApiAuthMiddleware::requirePermission('contacts.view');
        
        // Filtros
        $search = $_GET['search'] ?? null;
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 50), 100);
        
        try {
            $contacts = Contact::all();
            
            // Aplicar busca se fornecida
            if ($search) {
                $search = strtolower($search);
                $contacts = array_filter($contacts, function($contact) use ($search) {
                    return strpos(strtolower($contact['name'] ?? ''), $search) !== false ||
                           strpos(strtolower($contact['email'] ?? ''), $search) !== false ||
                           strpos(strtolower($contact['phone'] ?? ''), $search) !== false;
                });
            }
            
            // Paginação
            $total = count($contacts);
            $offset = ($page - 1) * $perPage;
            $contacts = array_slice($contacts, $offset, $perPage);
            
            ApiResponse::paginated(array_values($contacts), $total, $page, $perPage);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar contatos', $e);
        }
    }
    
    /**
     * Criar contato
     * POST /api/v1/contacts
     */
    public function store(): void
    {
        ApiAuthMiddleware::requirePermission('contacts.create');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        try {
            $contact = ContactService::create($input);
            ApiResponse::created($contact, 'Contato criado com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Obter contato
     * GET /api/v1/contacts/:id
     */
    public function show(string $id): void
    {
        ApiAuthMiddleware::requirePermission('contacts.view');
        
        try {
            $contact = Contact::find((int)$id);
            
            if (!$contact) {
                ApiResponse::notFound('Contato não encontrado');
            }
            
            ApiResponse::success($contact);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter contato', $e);
        }
    }
    
    /**
     * Atualizar contato
     * PUT /api/v1/contacts/:id
     */
    public function update(string $id): void
    {
        ApiAuthMiddleware::requirePermission('contacts.edit');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        try {
            $contact = Contact::find((int)$id);
            
            if (!$contact) {
                ApiResponse::notFound('Contato não encontrado');
            }
            
            ContactService::update((int)$id, $input);
            
            $updated = Contact::find((int)$id);
            ApiResponse::success($updated, 'Contato atualizado com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Deletar contato
     * DELETE /api/v1/contacts/:id
     */
    public function destroy(string $id): void
    {
        ApiAuthMiddleware::requirePermission('contacts.delete');
        
        try {
            $contact = Contact::find((int)$id);
            
            if (!$contact) {
                ApiResponse::notFound('Contato não encontrado');
            }
            
            Contact::delete((int)$id);
            ApiResponse::success(null, 'Contato deletado com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao deletar contato', $e);
        }
    }
    
    /**
     * Listar conversas do contato
     * GET /api/v1/contacts/:id/conversations
     */
    public function conversations(string $id): void
    {
        ApiAuthMiddleware::requirePermission('contacts.view');
        
        try {
            $contact = Contact::find((int)$id);
            
            if (!$contact) {
                ApiResponse::notFound('Contato não encontrado');
            }
            
            $conversations = Conversation::getByContact((int)$id);
            
            ApiResponse::success($conversations);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar conversas do contato', $e);
        }
    }
}
