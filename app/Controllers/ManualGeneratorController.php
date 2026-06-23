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
     * Criar o job e iniciar o processamento em background.
     * Responde imediatamente com o job_id; a UI acompanha via /manuals/status.
     */
    public function generate(): void
    {
        Permission::abortIfCannot(self::PERMISSION);

        $agentId = (int)Request::post('agent_id') ?: null;
        $dateFrom = Request::post('date_from', date('Y-m-01'));
        $dateTo = Request::post('date_to', date('Y-m-d'));
        $limit = min((int)Request::post('limit', 50), 100);
        $title = trim((string)Request::post('title')) ?: ('Manual de Processos — ' . date('d/m/Y'));

        try {
            $jobId = ManualGeneratorService::createJob([
                'title' => $title,
                'agent_id' => $agentId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'conversation_limit' => $limit,
                'created_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            return;
        }

        // Responder imediatamente e processar em background no mesmo processo (php-fpm).
        // Se fastcgi_finish_request não existir, o worker cron (process-manual-jobs.php)
        // pega o job pendente em seguida.
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'job_id' => $jobId, 'status' => 'pending']);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            @set_time_limit(0);
            @ignore_user_abort(true);
            try {
                ManualGeneratorService::runJob($jobId);
            } catch (\Throwable $e) {
                \App\Helpers\Logger::error('Manual job ' . $jobId . ' falhou: ' . $e->getMessage());
            }
        }
        return;
    }

    /**
     * AJAX: progresso do job (polling pela UI).
     */
    public function status(): void
    {
        Permission::abortIfCannot(self::PERMISSION);

        $jobId = (int)Request::get('job_id');
        $job = Database::fetch(
            "SELECT id, status, total_conversations, processed_conversations, error_message, cost
             FROM manual_jobs WHERE id = ?",
            [$jobId]
        );
        if (!$job) {
            Response::json(['success' => false, 'message' => 'Job não encontrado'], 404);
            return;
        }

        $manual = Database::fetch(
            "SELECT id FROM generated_manuals WHERE job_id = ? ORDER BY id DESC LIMIT 1",
            [$jobId]
        );
        $manualId = $manual['id'] ?? null;

        Response::json(['success' => true, 'data' => [
            'status' => $job['status'],
            'total' => (int)$job['total_conversations'],
            'processed' => (int)$job['processed_conversations'],
            'cost' => (float)$job['cost'],
            'error' => $job['error_message'],
            'manual_id' => $manualId,
            'redirect' => $manualId ? \App\Helpers\Url::to('/manuals/view?id=' . $manualId) : null,
        ]]);
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
