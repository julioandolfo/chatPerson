<?php
/**
 * Controller CopilotController — Copiloto de Atendimento (RAG sobre conversas).
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Database;
use App\Services\CopilotService;

class CopilotController
{
    private const VIEW_PERMS = ['conversations.view', 'conversations.view.own', 'conversations.view.all'];

    /** Página dedicada do copiloto. */
    public function index(): void
    {
        Permission::abortIfCannotAny(self::VIEW_PERMS);
        Response::view('copilot/index', [
            'stats' => CopilotService::stats(),
            'categories' => CopilotService::categories(),
        ]);
    }

    /** AJAX: perguntar ao copiloto. Usado pela página e pelo painel na conversa. */
    public function ask(): void
    {
        Permission::abortIfCannotAny(self::VIEW_PERMS);
        $question = trim((string)Request::post('question'));

        if ($question === '') {
            Response::json(['success' => false, 'message' => 'Descreva o problema do cliente.'], 400);
            return;
        }

        $filters = [
            'category' => trim((string)Request::post('category')) ?: null,
            'date_from' => trim((string)Request::post('date_from')) ?: null,
            'date_to' => trim((string)Request::post('date_to')) ?: null,
        ];

        @set_time_limit(60);
        try {
            $result = CopilotService::ask($question, 6, $filters);
            Response::json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** AJAX: contexto para pré-preencher o painel (última mensagem do cliente). */
    public function context(): void
    {
        Permission::abortIfCannotAny(self::VIEW_PERMS);
        $conversationId = (int)Request::get('conversation_id');
        $lastClient = '';
        if ($conversationId > 0) {
            $row = Database::fetch(
                "SELECT content FROM messages
                 WHERE conversation_id = ? AND sender_type = 'contact' AND content <> ''
                 ORDER BY created_at DESC, id DESC LIMIT 1",
                [$conversationId]
            );
            $lastClient = $row['content'] ?? '';
        }
        Response::json(['success' => true, 'data' => ['last_client_message' => $lastClient]]);
    }

    /** AJAX: indexar manualmente um lote pendente (botão na página). */
    public function reindex(): void
    {
        Permission::abortIfCannot('conversations.view.all');
        @set_time_limit(0);
        try {
            $done = CopilotService::indexPending(50);
            Response::json(['success' => true, 'indexed' => $done, 'stats' => CopilotService::stats()]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** AJAX: estatísticas do índice. */
    public function stats(): void
    {
        Permission::abortIfCannotAny(['conversations.view', 'conversations.view.own', 'conversations.view.all']);
        Response::json(['success' => true, 'data' => CopilotService::stats()]);
    }
}
