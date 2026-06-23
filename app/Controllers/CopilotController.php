<?php
/**
 * Controller CopilotController — Copiloto de Atendimento (RAG sobre conversas).
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\CopilotService;

class CopilotController
{
    /** Página dedicada do copiloto. */
    public function index(): void
    {
        Permission::abortIfCannotAny(['conversations.view', 'conversations.view.own', 'conversations.view.all']);
        $stats = CopilotService::stats();
        Response::view('copilot/index', ['stats' => $stats]);
    }

    /** AJAX: perguntar ao copiloto. Usado pela página e pelo painel na conversa. */
    public function ask(): void
    {
        Permission::abortIfCannotAny(['conversations.view', 'conversations.view.own', 'conversations.view.all']);
        $question = trim((string)Request::post('question'));

        if ($question === '') {
            Response::json(['success' => false, 'message' => 'Descreva o problema do cliente.'], 400);
            return;
        }

        @set_time_limit(60);
        try {
            $result = CopilotService::ask($question);
            Response::json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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
