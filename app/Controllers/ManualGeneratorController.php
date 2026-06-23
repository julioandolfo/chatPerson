<?php
/**
 * Controller ManualGeneratorController
 * Geração de manuais de processo (CS/Pós-venda) a partir de conversas reais.
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\ManualGeneratorService;
use App\Models\User;

class ManualGeneratorController
{
    private const PERMISSION = 'conversations.view.all';

    /**
     * Tela principal: formulário + lista de manuais gerados.
     */
    public function index(): void
    {
        Permission::abortIfCannot(self::PERMISSION);

        $agents = User::getAgents();
        $manuals = Database::fetchAll(
            "SELECT m.id, m.title, m.status, m.version, m.created_at,
                    j.agent_id, j.total_conversations, j.cost, u.name AS agent_name
             FROM generated_manuals m
             INNER JOIN manual_jobs j ON j.id = m.job_id
             LEFT JOIN users u ON u.id = j.agent_id
             ORDER BY m.created_at DESC
             LIMIT 50"
        );
        $aiAgents = Database::fetchAll(
            "SELECT id, name FROM ai_agents WHERE enabled = 1 ORDER BY name ASC"
        );

        Response::view('manuals/index', [
            'agents' => $agents,
            'manuals' => $manuals,
            'aiAgents' => $aiAgents,
        ]);
    }

    /**
     * AJAX: prévia de volume/custo antes de gerar.
     */
    public function preview(): void
    {
        Permission::abortIfCannot(self::PERMISSION);

        $agentId = (int)Request::get('agent_id') ?: null;
        $dateFrom = Request::get('date_from', date('Y-m-01'));
        $dateTo = Request::get('date_to', date('Y-m-d'));
        $limit = (int)Request::get('limit', 30);

        try {
            $data = ManualGeneratorService::preview($agentId, $dateFrom, $dateTo, $limit);
            Response::json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Gerar o manual (síncrono, com teto de conversas para a UI).
     */
    public function generate(): void
    {
        Permission::abortIfCannot(self::PERMISSION);

        $agentId = (int)Request::post('agent_id') ?: null;
        $dateFrom = Request::post('date_from', date('Y-m-01'));
        $dateTo = Request::post('date_to', date('Y-m-d'));
        $limit = min((int)Request::post('limit', 30), ManualGeneratorService::SYNC_LIMIT);
        $title = trim((string)Request::post('title')) ?: ('Manual de Processos — ' . date('d/m/Y'));

        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $jobId = ManualGeneratorService::createJob([
                'title' => $title,
                'agent_id' => $agentId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'conversation_limit' => $limit,
                'created_by' => Auth::id(),
            ]);

            $manualId = ManualGeneratorService::runJob($jobId);
            Response::json(['success' => true, 'manual_id' => $manualId,
                'redirect' => \App\Helpers\Url::to('/manuals/view?id=' . $manualId)]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Ver um manual gerado.
     */
    public function view(): void
    {
        Permission::abortIfCannot(self::PERMISSION);

        $id = (int)Request::get('id');
        $manual = Database::fetch(
            "SELECT m.*, j.agent_id, j.total_conversations, j.cost AS job_cost, u.name AS agent_name
             FROM generated_manuals m
             INNER JOIN manual_jobs j ON j.id = m.job_id
             LEFT JOIN users u ON u.id = j.agent_id
             WHERE m.id = ?",
            [$id]
        );

        if (!$manual) {
            Response::view('manuals/index', ['agents' => User::getAgents(), 'manuals' => [], 'aiAgents' => [],
                'flash_error' => 'Manual não encontrado.']);
            return;
        }

        $aiAgents = Database::fetchAll("SELECT id, name FROM ai_agents WHERE enabled = 1 ORDER BY name ASC");
        $divergences = json_decode((string)($manual['divergences_json'] ?? '[]'), true) ?: [];

        Response::view('manuals/show', [
            'manual' => $manual,
            'divergences' => $divergences,
            'aiAgents' => $aiAgents,
        ]);
    }

    /**
     * Exportar manual como Markdown (download).
     */
    public function export(): void
    {
        Permission::abortIfCannot(self::PERMISSION);

        $id = (int)Request::get('id');
        $manual = Database::fetch("SELECT * FROM generated_manuals WHERE id = ?", [$id]);
        if (!$manual) {
            http_response_code(404);
            echo 'Manual não encontrado';
            return;
        }

        $filename = 'manual-' . $id . '-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($manual['title'])) . '.md';
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "# " . $manual['title'] . "\n\n" . $manual['content_markdown'];
    }

    /**
     * Publicar o manual na base de conhecimento (RAG) de um agente de IA.
     */
    public function publishRag(): void
    {
        Permission::abortIfCannot(self::PERMISSION);

        $manualId = (int)Request::post('manual_id');
        $aiAgentId = (int)Request::post('ai_agent_id');

        if (!$manualId || !$aiAgentId) {
            Response::json(['success' => false, 'message' => 'Informe o manual e o agente de IA.'], 400);
            return;
        }

        try {
            $chunks = ManualGeneratorService::publishToRag($manualId, $aiAgentId);
            Response::json(['success' => true, 'chunks' => $chunks,
                'message' => "Manual publicado no RAG ({$chunks} blocos indexados)."]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
