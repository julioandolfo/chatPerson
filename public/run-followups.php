<?php
/**
 * Script para executar followups automáticos
 * Executar via cron: */5 * * * * php /caminho/para/public/run-followups.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

use App\Services\FollowupService;

try {
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando execução de followups...\n";
    
    // Executar followups
    FollowupService::runFollowups();
    echo "[" . date('Y-m-d H:i:s') . "] Followups executados.\n";
    
    // Reengajar contatos inativos
    FollowupService::reengageInactiveContacts();
    echo "[" . date('Y-m-d H:i:s') . "] Reengajamento de contatos executado.\n";
    
    // Verificar satisfação
    FollowupService::checkPostServiceSatisfaction();
    echo "[" . date('Y-m-d H:i:s') . "] Verificação de satisfação executada.\n";
    
    echo "[" . date('Y-m-d H:i:s') . "] Processo concluído com sucesso!\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

