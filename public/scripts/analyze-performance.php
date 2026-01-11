#!/usr/bin/env php
<?php
/**
 * Script para análise periódica de performance de vendedores (standalone)
 * Exemplo de cron: 0 */6 * * * cd /var/www/html && php public/scripts/analyze-performance.php >> storage/logs/performance-analysis.log 2>&1
 */

@header('Content-Type: text/plain; charset=utf-8');

// Ajustar diretório raiz
$rootDir = dirname(__DIR__, 2);
chdir($rootDir);

// Erros verbosos para evitar 500 silencioso
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
// Evitar buffers silenciosos (especialmente se rodar via web)
while (ob_get_level() > 0) {
    ob_end_flush();
}
// Dumpa erro fatal no final, se houver
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "[", date('Y-m-d H:i:s'), "] ❌ FATAL: {$error['message']} em {$error['file']}:{$error['line']}\n";
    }
});
// Handlers explícitos para mostrar erros/exceções
set_error_handler(function ($severity, $message, $file, $line) {
    echo "[", date('Y-m-d H:i:s'), "] ⚠️ ERRO: {$message} em {$file}:{$line}\n";
    return false; // permite que o handler padrão também atue
});
set_exception_handler(function ($e) {
    echo "[", date('Y-m-d H:i:s'), "] ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
});

// Log
$logDir = $rootDir . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Bootstrap (autoload + env)
$bootstrapPath = $rootDir . '/config/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    echo "[", date('Y-m-d H:i:s'), "] ERRO: config/bootstrap.php não encontrado em {$bootstrapPath}\n";
    exit(1);
}
require_once $bootstrapPath;
// Reforça exibição de erros caso o bootstrap mude configs
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '0');
ini_set('log_errors', '1');

use App\Services\AgentPerformanceAnalysisService;
use App\Services\ConversationSettingsService;
use App\Services\CoachingService;
use App\Helpers\Database;

try {
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando análise de performance de vendedores...\n";
    
    // Verificar se está habilitado
    $settings = ConversationSettingsService::getSettings();
    $perfSettings = $settings['agent_performance_analysis'] ?? [];
    
    if (empty($perfSettings['enabled'])) {
        echo "[" . date('Y-m-d H:i:s') . "] ⚠️ Análise de performance DESABILITADA nas configurações.\n";
        echo "[" . date('Y-m-d H:i:s') . "] Acesse: Configurações > Botões de Ação > Análise de Performance\n\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Análise habilitada\n";
    echo "[" . date('Y-m-d H:i:s') . "] 📊 Configurações:\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Modelo: " . ($perfSettings['model'] ?? 'N/A') . "\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Intervalo: " . ($perfSettings['check_interval_hours'] ?? 'N/A') . " horas\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Idade máxima: " . ($perfSettings['max_conversation_age_days'] ?? 'N/A') . " dias\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Mín. mensagens: " . ($perfSettings['min_messages_to_analyze'] ?? 'N/A') . "\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Apenas fechadas: " . (($perfSettings['analyze_closed_only'] ?? true) ? 'Sim' : 'Não') . "\n\n";
    
    // Status respeitando configuração (fechadas apenas ou também abertas)
    $statuses = ($perfSettings['analyze_closed_only'] ?? true)
        ? ['closed', 'resolved']
        : ['open', 'pending', 'in_progress', 'waiting', 'assigned', 'closed', 'resolved'];
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));

    // Contar elegíveis
    $sql = "SELECT COUNT(*) as total 
            FROM conversations c
            LEFT JOIN agent_performance_analysis apa ON c.id = apa.conversation_id
            WHERE c.status IN ({$placeholders})
            AND c.agent_id IS NOT NULL
            AND apa.id IS NULL
            AND (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'agent') >= ?";
    
    $minAgentMessages = (int)($perfSettings['min_agent_messages'] ?? 3);
    $params = array_merge($statuses, [$minAgentMessages]);
    $eligible = Database::fetch($sql, $params);
    $eligibleCount = $eligible['total'] ?? 0;
    
    echo "[" . date('Y-m-d H:i:s') . "] 🔍 Conversas elegíveis para análise: {$eligibleCount}\n";
    
    if ($eligibleCount == 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ℹ️ Nenhuma conversa precisa ser analisada no momento.\n";
        echo "[" . date('Y-m-d H:i:s') . "] Motivos possíveis:\n";
        echo "[" . date('Y-m-d H:i:s') . "]    - Não há conversas no status configurado com agente atribuído\n";
        echo "[" . date('Y-m-d H:i:s') . "]    - Conversas não têm mensagens suficientes do agente (mín: {$minAgentMessages})\n";
        echo "[" . date('Y-m-d H:i:s') . "]    - Todas as conversas já foram analisadas\n\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] 🚀 Processando conversas...\n\n";
    
    $result = AgentPerformanceAnalysisService::processPendingConversations();
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Análises processadas: " . ($result['processed'] ?? 0) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] ⚠️ Erros: " . ($result['errors'] ?? 0) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] 💰 Custo total: $" . number_format($result['cost'] ?? 0, 4) . "\n\n";
    
    // Atualizar status de metas dos agentes analisados
    if (($result['processed'] ?? 0) > 0 && ($perfSettings['coaching']['enabled'] ?? false)) {
        echo "[" . date('Y-m-d H:i:s') . "] 🎯 Atualizando metas dos agentes...\n";
        
        $agents = Database::fetchAll("SELECT DISTINCT agent_id FROM agent_performance_analysis WHERE DATE(analyzed_at) = CURDATE()");
        $goalsUpdated = 0;
        
        foreach ($agents as $agent) {
            $updated = CoachingService::updateGoalsStatus($agent['agent_id']);
            $goalsUpdated += count($updated);
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] ✅ Metas atualizadas: {$goalsUpdated}\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Concluído.\n\n";
    
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Stack trace: " . $e->getTraceAsString() . "\n\n";
    exit(1);
}
