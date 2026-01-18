<?php
/**
 * Controller DripSequenceController
 * Gerenciamento de sequências Drip
 */

namespace App\Controllers;

use App\Services\DripSequenceService;
use App\Models\DripSequence;
use App\Models\ContactList;
use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;

class DripSequenceController
{
    /**
     * Lista de sequências
     */
    public function index(): void
    {
        Permission::abortIfCannot('campaigns.view');

        $sequences = DripSequence::all();

        Response::view('drip-sequences/index', [
            'sequences' => $sequences,
            'title' => 'Sequências Drip'
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        Permission::abortIfCannot('campaigns.create');

        Response::view('drip-sequences/create', [
            'title' => 'Nova Sequência Drip'
        ]);
    }

    /**
     * Salvar nova sequência
     */
    public function store(): void
    {
        Permission::abortIfCannot('campaigns.create');

        try {
            $data = Request::all();
            $data['created_by'] = Auth::id();

            $sequenceId = DripSequenceService::create($data);

            Response::json([
                'success' => true,
                'message' => 'Sequência criada com sucesso!',
                'sequence_id' => $sequenceId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Visualizar sequência
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('campaigns.view');

        $sequence = DripSequence::find($id);
        if (!$sequence) {
            Response::json(['error' => 'Sequência não encontrada'], 404);
            return;
        }

        $steps = DripSequence::getSteps($id);

        Response::view('drip-sequences/show', [
            'sequence' => $sequence,
            'steps' => $steps,
            'title' => $sequence['name']
        ]);
    }

    /**
     * Deletar sequência
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('campaigns.delete');

        try {
            DripSequenceService::delete($id);

            Response::json([
                'success' => true,
                'message' => 'Sequência deletada com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Adicionar contatos à sequência
     */
    public function addContacts(int $id): void
    {
        Permission::abortIfCannot('campaigns.edit');

        try {
            $data = Request::all();
            $contactIds = $data['contact_ids'] ?? [];

            $added = DripSequenceService::addContacts($id, $contactIds);

            Response::json([
                'success' => true,
                'message' => "{$added} contatos adicionados à sequência!",
                'added' => $added
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
