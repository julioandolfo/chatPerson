<?php
/**
 * Controller ContactListController
 * Gerenciamento de listas de contatos
 */

namespace App\Controllers;

use App\Services\ContactListService;
use App\Models\ContactList;
use App\Models\Contact;
use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;

class ContactListController
{
    /**
     * Lista de listas
     */
    public function index(): void
    {
        Permission::abortIfCannot('campaigns.view');

        $lists = ContactList::all();

        Response::view('contact-lists/index', [
            'lists' => $lists,
            'title' => 'Listas de Contatos'
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        Permission::abortIfCannot('campaigns.create');

        Response::view('contact-lists/create', [
            'title' => 'Nova Lista de Contatos'
        ]);
    }

    /**
     * Salvar nova lista
     */
    public function store(): void
    {
        Permission::abortIfCannot('campaigns.create');

        try {
            $data = Request::all();
            $data['created_by'] = Auth::id();

            $listId = ContactListService::create($data);

            Response::json([
                'success' => true,
                'message' => 'Lista criada com sucesso!',
                'list_id' => $listId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Visualizar lista
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('campaigns.view');

        $list = ContactList::find($id);
        if (!$list) {
            Response::json(['error' => 'Lista não encontrada'], 404);
            return;
        }

        // Buscar contatos da lista (paginado)
        $page = Request::get('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $contacts = ContactList::getContacts($id, $limit, $offset);
        $total = ContactList::countContacts($id);

        Response::view('contact-lists/show', [
            'list' => $list,
            'contacts' => $contacts,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'title' => $list['name']
        ]);
    }

    /**
     * Formulário de edição
     */
    public function edit(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        $list = ContactList::find($id);
        if (!$list) {
            Response::json(['error' => 'Lista não encontrada'], 404);
            return;
        }

        Response::view('contact-lists/edit', [
            'list' => $list,
            'title' => 'Editar Lista'
        ]);
    }

    /**
     * Atualizar lista
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            $data = Request::all();
            ContactListService::update($id, $data);

            Response::json([
                'success' => true,
                'message' => 'Lista atualizada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deletar lista
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('campaigns.delete');

        try {
            ContactListService::delete($id);

            Response::json([
                'success' => true,
                'message' => 'Lista deletada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Adicionar contato à lista
     */
    public function addContact(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            $contactId = Request::post('contact_id');
            $customVariables = Request::post('custom_variables', []);

            if (!$contactId) {
                throw new \InvalidArgumentException('ID do contato é obrigatório');
            }

            ContactListService::addContact($id, (int)$contactId, $customVariables, Auth::id());

            Response::json([
                'success' => true,
                'message' => 'Contato adicionado à lista!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Remover contato da lista
     */
    public function removeContact(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            $contactId = Request::post('contact_id');

            if (!$contactId) {
                throw new \InvalidArgumentException('ID do contato é obrigatório');
            }

            ContactListService::removeContact($id, (int)$contactId);

            Response::json([
                'success' => true,
                'message' => 'Contato removido da lista!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Upload e import CSV
     */
    public function importCsv(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            if (empty($_FILES['csv_file'])) {
                throw new \InvalidArgumentException('Arquivo não enviado');
            }

            $file = $_FILES['csv_file'];
            $tmpPath = $file['tmp_name'];

            $result = ContactListService::importFromCsv($id, $tmpPath);

            Response::json([
                'success' => true,
                'message' => "Import concluído: {$result['imported']} importados, {$result['skipped']} pulados",
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Limpar lista (remover todos os contatos)
     */
    public function clear(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            ContactListService::clearList($id);

            Response::json([
                'success' => true,
                'message' => 'Lista limpa com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Listar contatos da lista (API JSON paginada)
     */
    public function contacts(int $id): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $page = Request::get('page', 1);
            $limit = Request::get('limit', 50);
            $offset = ($page - 1) * $limit;

            $contacts = ContactList::getContacts($id, (int)$limit, (int)$offset);
            $total = ContactList::countContacts($id);

            Response::json([
                'success' => true,
                'contacts' => $contacts,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Buscar contatos disponíveis (para adicionar à lista)
     */
    public function searchContacts(): void
    {
        Permission::abortIfCannot('campaigns.view');

        try {
            $search = Request::get('q', '');
            $limit = Request::get('limit', 20);

            $sql = "SELECT id, name, phone, email, avatar 
                    FROM contacts 
                    WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
                    ORDER BY name ASC
                    LIMIT ?";

            $searchTerm = "%{$search}%";
            $contacts = \App\Helpers\Database::fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, (int)$limit]);

            Response::json([
                'success' => true,
                'contacts' => $contacts
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
