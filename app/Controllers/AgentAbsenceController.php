<?php
/**
 * Controller AgentAbsenceController
 * Mensagem de ausência: o agente cadastra os próprios períodos (Perfil → Ausência)
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Auth;
use App\Helpers\Permission;
use App\Services\AgentAbsenceService;

class AgentAbsenceController
{
    private static function fail(\Throwable $e, string $prefix): void
    {
        if ($e instanceof \InvalidArgumentException) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
            return;
        }
        if ($e instanceof \PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
            Response::json(['success' => false, 'message' => 'O banco ainda não tem as tabelas da mensagem de ausência. No servidor, execute: php -d variables_order=EGPCS database/run_migrations.php 163 && php -d variables_order=EGPCS database/run_migrations.php 164'], 500);
            return;
        }
        Response::json(['success' => false, 'message' => $prefix . ': ' . $e->getMessage()], 500);
    }

    public function index(): void
    {
        try {
            Response::json([
                'success' => true,
                'absences' => AgentAbsenceService::listForUser((int)Auth::id()),
                'default_message' => AgentAbsenceService::DEFAULT_MESSAGE,
                'server_now' => date('Y-m-d\TH:i'),
            ]);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao carregar ausências');
        }
    }

    public function store(): void
    {
        try {
            $absence = AgentAbsenceService::save((int)Auth::id(), Request::json());
            Response::json(['success' => true, 'message' => 'Ausência agendada', 'absence' => $absence]);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao salvar ausência');
        }
    }

    public function update(int $id): void
    {
        try {
            $absence = AgentAbsenceService::save((int)Auth::id(), Request::json(), $id);
            Response::json(['success' => true, 'message' => 'Ausência atualizada', 'absence' => $absence]);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao salvar ausência');
        }
    }

    public function end(int $id): void
    {
        try {
            $absence = AgentAbsenceService::endNow($id, (int)Auth::id());
            Response::json(['success' => true, 'message' => 'Ausência encerrada', 'absence' => $absence]);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao encerrar ausência');
        }
    }

    public function destroy(int $id): void
    {
        try {
            AgentAbsenceService::delete($id, (int)Auth::id());
            Response::json(['success' => true, 'message' => 'Ausência excluída']);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao excluir ausência');
        }
    }

    /** Pode criar/editar ausências rápidas para toda a equipe */
    private static function canManageTeam(): bool
    {
        return Permission::isAdmin() || Permission::can('settings.manage');
    }

    /** Botão do topo: ausência ativa + ausências rápidas */
    public function quick(): void
    {
        try {
            Response::json(['success' => true, 'can_manage_team' => self::canManageTeam()]
                + AgentAbsenceService::quickState((int)Auth::id(), self::canManageTeam()));
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao carregar ausências rápidas');
        }
    }

    public function startPreset(int $id): void
    {
        try {
            $absence = AgentAbsenceService::startPreset($id, (int)Auth::id());
            Response::json(['success' => true, 'message' => 'Ausência iniciada até ' . $absence['ends_time'], 'absence' => $absence]);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao iniciar ausência');
        }
    }

    public function storePreset(): void
    {
        try {
            $preset = AgentAbsenceService::savePreset((int)Auth::id(), Request::json(), null, self::canManageTeam());
            Response::json(['success' => true, 'message' => 'Ausência rápida criada', 'preset' => $preset]);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao salvar ausência rápida');
        }
    }

    public function updatePreset(int $id): void
    {
        try {
            $preset = AgentAbsenceService::savePreset((int)Auth::id(), Request::json(), $id, self::canManageTeam());
            Response::json(['success' => true, 'message' => 'Ausência rápida atualizada', 'preset' => $preset]);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao salvar ausência rápida');
        }
    }

    public function destroyPreset(int $id): void
    {
        try {
            AgentAbsenceService::deletePreset($id, (int)Auth::id(), self::canManageTeam());
            Response::json(['success' => true, 'message' => 'Ausência rápida excluída']);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao excluir ausência rápida');
        }
    }

    public function replies(int $id): void
    {
        try {
            Response::json(['success' => true, 'replies' => AgentAbsenceService::listReplies($id, (int)Auth::id())]);
        } catch (\Throwable $e) {
            self::fail($e, 'Erro ao carregar envios');
        }
    }
}
