<?php
/**
 * ConversationInsightController
 * Análise de Conversas por Coorte — "o que aconteceu com as conversas que
 * passaram pela etapa X / pelo time Y nos últimos N dias".
 */

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Logger;
use App\Helpers\Permission;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\ConversationAnalysisBatch;
use App\Models\ConversationAnalysisItem;
use App\Models\Funnel;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\ConversationBatchAnalysisService;
use App\Services\ConversationCohortService;

class ConversationInsightController
{
    /**
     * Listagem das análises
     */
    public function index(): void
    {
        Permission::abortIfCannot('conversation_insights.view');

        $user = Auth::user();
        $canSeeAll = Permission::isAdmin() || Permission::isSuperAdmin();

        $batches = ConversationAnalysisBatch::listRecent(50, $canSeeAll ? null : (int)$user['id']);

        Response::view('conversation-insights/index', [
            'batches' => $batches,
            'canRun' => Permission::can('conversation_insights.run'),
        ]);
    }

    /**
     * Formulário de nova análise
     */
    public function createForm(): void
    {
        Permission::abortIfCannot('conversation_insights.run');

        Response::view('conversation-insights/create', [
            'funnels' => self::getFunnelsWithStages(),
            'agents' => User::getActiveAgents(),
            'teams' => Team::getActive(),
            'tags' => Tag::all(),
            'departments' => Database::fetchAll("SELECT id, name FROM departments ORDER BY name ASC"),
            'channels' => self::getChannels(),
            'reasons' => ConversationBatchAnalysisService::REASONS,
        ]);
    }

    /**
     * Prévia da coorte — quantas conversas e quanto vai custar.
     * Chamado por AJAX conforme o usuário mexe nos filtros.
     */
    public function preview(): void
    {
        Permission::abortIfCannot('conversation_insights.view');

        try {
            $filters = self::readFilters();
            $preview = ConversationCohortService::preview($filters);

            $estimate = ConversationBatchAnalysisService::estimateCost(
                $preview['selected'],
                $preview['avg_messages']
            );

            Response::json([
                'success' => true,
                'total' => $preview['total'],
                'selected' => $preview['selected'],
                'avg_messages' => $preview['avg_messages'],
                'warnings' => $preview['warnings'],
                'estimate' => $estimate,
            ]);
        } catch (\Exception $e) {
            Logger::error('ConversationInsightController::preview - ' . $e->getMessage());
            Response::error('Erro ao calcular a prévia: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Criar (enfileirar) uma análise
     */
    public function create(): void
    {
        Permission::abortIfCannot('conversation_insights.run');

        try {
            $input = Request::all();
            $contextQuestion = trim((string)($input['context_question'] ?? ''));

            if ($contextQuestion === '') {
                Response::error('Descreva o que você quer entender com esta análise.', 422);
                return;
            }

            $filters = self::readFilters();
            $user = Auth::user();

            $costLimit = isset($input['cost_limit']) && $input['cost_limit'] !== ''
                ? (float)$input['cost_limit']
                : null;

            $batchId = ConversationBatchAnalysisService::createBatch(
                $filters,
                $contextQuestion,
                !empty($input['name']) ? (string)$input['name'] : null,
                (int)$user['id'],
                $costLimit
            );

            Response::json([
                'success' => true,
                'batch_id' => $batchId,
                'redirect' => '/conversation-insights/' . $batchId,
                'message' => 'Análise criada. O processamento acontece em segundo plano.',
            ]);
        } catch (\Exception $e) {
            Logger::error('ConversationInsightController::create - ' . $e->getMessage());
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Resultado da análise
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('conversation_insights.view');

        $batch = ConversationAnalysisBatch::find($id);

        if (!$batch) {
            Response::notFound('Análise não encontrada');
            return;
        }

        if (!self::canAccess($batch)) {
            Response::forbidden('Você não tem acesso a esta análise');
            return;
        }

        $batch = ConversationAnalysisBatch::decode($batch);

        // Enquanto roda, os agregados são calculados na hora para dar feedback
        $metrics = $batch['metrics'];
        if (empty($metrics) && $batch['status'] === ConversationAnalysisBatch::STATUS_RUNNING) {
            $metrics = ConversationBatchAnalysisService::aggregate($id);
        }

        Response::view('conversation-insights/show', [
            'batch' => $batch,
            'metrics' => $metrics ?: [],
            'summary' => $batch['summary'] ?: [],
            'items' => ConversationAnalysisItem::getAnalyzed($id, [], 50),
            'reasonLabels' => ConversationBatchAnalysisService::REASONS,
            'outcomeLabels' => ConversationBatchAnalysisService::OUTCOMES,
            'canRun' => Permission::can('conversation_insights.run'),
        ]);
    }

    /**
     * Status para polling da tela
     */
    public function status(int $id): void
    {
        Permission::abortIfCannot('conversation_insights.view');

        $batch = ConversationAnalysisBatch::find($id);

        if (!$batch || !self::canAccess($batch)) {
            Response::error('Análise não encontrada', 404);
            return;
        }

        $total = (int)$batch['total_conversations'];
        $finished = in_array($batch['status'], ['completed', 'failed', 'cancelled'], true);

        // O progresso vem do que ainda falta, não de analyzed+failed: conversas
        // puladas (curtas demais) também estão concluídas e travariam a barra
        $unfinished = ConversationAnalysisItem::countUnfinished($id);
        $done = max(0, $total - $unfinished);

        Response::json([
            'success' => true,
            'status' => $batch['status'],
            'total' => $total,
            'analyzed' => (int)$batch['analyzed_conversations'],
            'failed' => (int)$batch['failed_conversations'],
            'progress' => $finished ? 100 : ($total > 0 ? (int)round($done / $total * 100) : 0),
            'cost' => (float)$batch['cost'],
            'finished' => $finished,
        ]);
    }

    /**
     * Drilldown: conversas por motivo / etapa / vendedor
     */
    public function conversations(int $id): void
    {
        Permission::abortIfCannot('conversation_insights.view');

        $batch = ConversationAnalysisBatch::find($id);

        if (!$batch || !self::canAccess($batch)) {
            Response::error('Análise não encontrada', 404);
            return;
        }

        $filters = [
            'primary_reason' => Request::get('primary_reason'),
            'drop_off_stage_id' => Request::get('drop_off_stage_id'),
            'agent_id' => Request::get('agent_id'),
            'who_stopped' => Request::get('who_stopped'),
            'outcome' => Request::get('outcome'),
        ];

        $items = ConversationAnalysisItem::getAnalyzed(
            $id,
            array_filter($filters),
            (int)(Request::get('limit', 50)),
            (int)(Request::get('offset', 0))
        );

        $items = array_map([ConversationAnalysisItem::class, 'decode'], $items);

        Response::json(['success' => true, 'items' => $items]);
    }

    /**
     * Cancelar análise em andamento
     */
    public function cancel(int $id): void
    {
        Permission::abortIfCannot('conversation_insights.run');

        $batch = ConversationAnalysisBatch::find($id);

        if (!$batch || !self::canAccess($batch)) {
            Response::error('Análise não encontrada', 404);
            return;
        }

        $ok = ConversationBatchAnalysisService::cancelBatch($id);

        Response::json([
            'success' => $ok,
            'message' => $ok ? 'Análise cancelada.' : 'Não foi possível cancelar (já concluída).',
        ]);
    }

    /**
     * Exportar resultado em CSV
     */
    public function export(int $id): void
    {
        Permission::abortIfCannot('conversation_insights.view');

        $batch = ConversationAnalysisBatch::find($id);

        if (!$batch || !self::canAccess($batch)) {
            Response::notFound('Análise não encontrada');
            return;
        }

        $items = ConversationAnalysisItem::getAnalyzed($id, [], 5000);
        $reasonLabels = ConversationBatchAnalysisService::REASONS;
        $outcomeLabels = ConversationBatchAnalysisService::OUTCOMES;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analise-conversas-' . $id . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel

        fputcsv($out, [
            'Conversa', 'Contato', 'Telefone', 'Vendedor', 'Desfecho', 'Motivo',
            'Etapa onde travou', 'Quem parou', 'Dias em silêncio', 'Confiança',
            'Explicação', 'Ação sugerida'
        ], ';');

        foreach ($items as $item) {
            $item = ConversationAnalysisItem::decode($item);
            $analysis = $item['analysis'] ?: [];
            $metrics = $item['metrics'] ?: [];

            fputcsv($out, [
                $item['conversation_id'],
                $item['contact_name'] ?? '',
                $item['contact_phone'] ?? '',
                $item['agent_name'] ?? '',
                $outcomeLabels[$item['outcome']] ?? $item['outcome'],
                $reasonLabels[$item['primary_reason']] ?? $item['primary_reason'],
                $item['drop_off_stage_name'] ?? '',
                $item['who_stopped'] ?? '',
                $metrics['silence_days'] ?? '',
                $item['confidence'],
                $analysis['reason_explanation'] ?? '',
                $analysis['recovery_action'] ?? '',
            ], ';');
        }

        fclose($out);
        exit;
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

    /**
     * Ler os filtros da requisição (aceita form-data e JSON)
     */
    private static function readFilters(): array
    {
        // Request::all() já resolve JSON, POST e GET nessa ordem
        $input = Request::all();
        $user = Auth::user();

        return ConversationCohortService::normalizeFilters([
            'days' => $input['days'] ?? null,
            'date_from' => $input['date_from'] ?? null,
            'date_to' => $input['date_to'] ?? null,
            'date_basis' => $input['date_basis'] ?? null,
            'stage_ids' => $input['stage_ids'] ?? [],
            'stage_match' => $input['stage_match'] ?? 'any',
            'stage_window' => $input['stage_window'] ?? 'any_time',
            'agent_ids' => $input['agent_ids'] ?? [],
            'agent_match' => $input['agent_match'] ?? 'any',
            'agent_basis' => $input['agent_basis'] ?? 'both',
            'team_ids' => $input['team_ids'] ?? [],
            'funnel_ids' => $input['funnel_ids'] ?? [],
            'department_ids' => $input['department_ids'] ?? [],
            'tag_ids' => $input['tag_ids'] ?? [],
            'channels' => $input['channels'] ?? [],
            'statuses' => $input['statuses'] ?? [],
            'exclude_spam' => $input['exclude_spam'] ?? true,
            'min_messages' => $input['min_messages'] ?? 4,
            'limit' => $input['limit'] ?? ConversationCohortService::DEFAULT_LIMIT,
            'current_user_id' => (int)($user['id'] ?? 0),
        ]);
    }

    /**
     * Quem criou vê a própria análise; admin vê todas.
     */
    private static function canAccess(array $batch): bool
    {
        if (Permission::isAdmin() || Permission::isSuperAdmin()) {
            return true;
        }

        $user = Auth::user();
        return (int)($batch['created_by'] ?? 0) === (int)($user['id'] ?? -1);
    }

    private static function getFunnelsWithStages(): array
    {
        $funnels = Funnel::whereActive();

        foreach ($funnels as &$funnel) {
            $funnel['stages'] = Funnel::getStages((int)$funnel['id']);
        }
        unset($funnel);

        return $funnels;
    }

    private static function getChannels(): array
    {
        $rows = Database::fetchAll(
            "SELECT DISTINCT channel FROM conversations WHERE channel IS NOT NULL AND channel <> '' ORDER BY channel ASC"
        );

        return array_map(static fn($row) => $row['channel'], $rows);
    }
}
