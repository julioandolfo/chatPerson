<?php
/**
 * Controller StageAlertController
 * Alerta de conversa parada na etapa: configuração da etapa e consulta do agente
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Permission;
use App\Helpers\Request;
use App\Helpers\Auth;
use App\Services\StageAlertService;

class StageAlertController
{
    private static function missingMigrationMessage(\Throwable $e): ?string
    {
        if ($e instanceof \PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
            return 'O banco ainda não tem a tabela do alerta por etapa. No servidor, execute: '
                . 'php -d variables_order=EGPCS database/run_migrations.php 162';
        }
        return null;
    }

    public function getStageConfig(int $stageId): void
    {
        Permission::abortIfCannot('funnels.view');

        try {
            Response::json([
                'success' => true,
                'config' => StageAlertService::getStageConfig($stageId),
                'sounds' => StageAlertService::SOUNDS,
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => self::missingMigrationMessage($e) ?? $e->getMessage()], 400);
        }
    }

    public function saveStageConfig(int $stageId): void
    {
        Permission::abortIfCannot('funnels.edit');

        try {
            $config = StageAlertService::saveStageConfig($stageId, Request::json());
            Response::json(['success' => true, 'message' => 'Alerta salvo', 'config' => $config]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => self::missingMigrationMessage($e) ?? ('Erro ao salvar alerta: ' . $e->getMessage())], 500);
        }
    }

    /**
     * Conversas em alerta para o usuário logado (consultado periodicamente pelo navegador)
     */
    public function myAlerts(): void
    {
        try {
            $result = StageAlertService::getAlertsForUser((int)Auth::id());
            Response::json(['success' => true, 'server_time' => date('c')] + $result);
        } catch (\PDOException $e) {
            // Tabela ainda não criada: sem alertas, sem erro na tela
            if (self::missingMigrationMessage($e)) {
                Response::json(['success' => true, 'has_rules' => false, 'alerts' => []]);
                return;
            }
            Response::json(['success' => false, 'message' => 'Erro ao consultar alertas'], 500);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao consultar alertas'], 500);
        }
    }
}
