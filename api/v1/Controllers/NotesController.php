<?php
/**
 * NotesController - API v1
 * Notas internas de conversas
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\Conversation;
use App\Services\ConversationNoteService;
use App\Services\ConversationService;

class NotesController
{
    /**
     * Listar notas de uma conversa
     * GET /api/v1/conversations/:id/notes
     */
    public function index(string $conversationId): void
    {
        $conversationId = (int)$conversationId;
        $this->assertCanView($conversationId);

        try {
            $notes = ConversationNoteService::list($conversationId, ApiAuthMiddleware::userId());
            ApiResponse::success(['items' => $notes]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar notas', $e);
        }
    }

    /**
     * Criar nota
     * POST /api/v1/conversations/:id/notes
     * Body: { content: string, is_private?: bool }
     */
    public function store(string $conversationId): void
    {
        $conversationId = (int)$conversationId;
        $this->assertCanView($conversationId);

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $content = trim((string)($input['content'] ?? ''));

        if ($content === '') {
            ApiResponse::validationError('Dados inválidos', ['content' => ['Conteúdo da nota não pode estar vazio']]);
        }

        try {
            $isPrivate = !empty($input['is_private']);
            $note = ConversationNoteService::create($conversationId, ApiAuthMiddleware::userId(), $content, $isPrivate);

            ApiResponse::created($note, 'Nota criada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }

    private function assertCanView(int $conversationId): void
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            ApiResponse::notFound('Conversa não encontrada');
        }

        if (!ConversationService::canView($conversationId, ApiAuthMiddleware::userId())) {
            ApiResponse::forbidden('Você não tem permissão para acessar esta conversa');
        }
    }
}
