<?php
/**
 * Controller StageFollowupController
 * Follow-up automático por etapa do funil: configuração da etapa e controle no chat
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Permission;
use App\Helpers\Request;
use App\Helpers\Auth;
use App\Models\Conversation;
use App\Services\StageFollowupService;

class StageFollowupController
{
    /**
     * Tabela/coluna faltando = migrations do follow-up não executadas no servidor
     */
    private static function missingMigrationMessage(\Throwable $e): ?string
    {
        if ($e instanceof \PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
            return 'O banco ainda não tem as tabelas do follow-up. No servidor, execute: '
                . 'php -d variables_order=EGPCS database/run_migrations.php 159 && '
                . 'php -d variables_order=EGPCS database/run_migrations.php 161';
        }
        return null;
    }

    /**
     * Configuração de follow-up da etapa
     */
    public function getStageConfig(int $stageId): void
    {
        Permission::abortIfCannot('funnels.view');

        try {
            Response::json(['success' => true, 'config' => StageFollowupService::getStageConfig($stageId)]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => self::missingMigrationMessage($e) ?? $e->getMessage()], 400);
        }
    }

    /**
     * Salvar configuração de follow-up da etapa
     */
    public function saveStageConfig(int $stageId): void
    {
        Permission::abortIfCannot('funnels.edit');

        try {
            $config = StageFollowupService::saveStageConfig($stageId, Request::json());
            Response::json(['success' => true, 'message' => 'Follow-up salvo', 'config' => $config]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => self::missingMigrationMessage($e) ?? ('Erro ao salvar follow-up: ' . $e->getMessage())], 500);
        }
    }

    /**
     * Limites anti-spam do follow-up (valem para todas as etapas)
     */
    public function getLimits(): void
    {
        Permission::abortIfCannot('funnels.view');
        Response::json([
            'success' => true,
            'limits' => StageFollowupService::getLimits(),
            'defaults' => StageFollowupService::DEFAULT_LIMITS,
        ]);
    }

    public function saveLimits(): void
    {
        Permission::abortIfCannot('funnels.edit');

        try {
            $limits = StageFollowupService::saveLimits(Request::json());
            Response::json(['success' => true, 'message' => 'Limites de envio salvos', 'limits' => $limits]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => self::missingMigrationMessage($e) ?? ('Erro ao salvar limites: ' . $e->getMessage())], 500);
        }
    }

    /**
     * Situação do follow-up na conversa (aviso no chat)
     */
    public function getConversationStatus(int $id): void
    {
        $conversation = Conversation::find($id);
        if (!$conversation || !Permission::canViewConversation($conversation)) {
            Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
            return;
        }

        try {
            Response::json(['success' => true] + StageFollowupService::getConversationStatus($id));
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Agente cancela a sequência desta conversa (até a próxima mensagem enviada)
     */
    public function cancel(int $id): void
    {
        $this->changeState($id, fn() => StageFollowupService::cancel($id, (int)Auth::id()), 'Follow-up cancelado para esta conversa');
    }

    /**
     * Agente desfaz o cancelamento
     */
    public function reactivate(int $id): void
    {
        $this->changeState($id, fn() => StageFollowupService::reactivate($id, (int)Auth::id()), 'Follow-up reativado');
    }

    private function changeState(int $id, callable $action, string $successMessage): void
    {
        $conversation = Conversation::find($id);
        if (!$conversation || !Permission::canViewConversation($conversation)) {
            Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
            return;
        }
        if (!Permission::canSendMessage($conversation) && !Permission::canEditConversation($conversation)) {
            Response::json(['success' => false, 'message' => 'Sem permissão para alterar o follow-up desta conversa'], 403);
            return;
        }

        try {
            Response::json(['success' => true, 'message' => $successMessage] + $action());
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
