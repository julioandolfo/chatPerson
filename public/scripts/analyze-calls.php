<?php
/**
 * Cron: Análise de Chamadas Telefônicas
 * 
 * Processa chamadas com gravação que ainda não foram analisadas.
 * Transcreve o áudio usando Whisper e analisa com GPT-4.
 * 
 * Executar via cron a cada 5-15 minutos:
 * */5 * * * * php /var/www/html/public/scripts/analyze-calls.php >> /var/www/html/logs/call-analysis.log 2>&1
 */

// Configurações
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 minutos

// Autoload
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\CallPerformanceAnalysisService;
use App\Helpers\Logger;

echo "\n" . str_repeat('=', 60) . "\n";
echo "📞 ANÁLISE DE CHAMADAS TELEFÔNICAS\n";
echo "⏰ " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n\n";

// Verificar se está habilitado
if (!CallPerformanceAnalysisService::isEnabled()) {
    echo "⚠️ Análise de chamadas está desabilitada nas configurações.\n";
    echo "   Configure 'call_analysis_enabled' = '1' na tabela settings.\n";
    exit(0);
}

// Verificar API Key
$apiKey = \App\Helpers\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'openai_api_key' LIMIT 1");
if (empty($apiKey['value'])) {
    $envKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
    if (empty($envKey)) {
        echo "❌ API Key do OpenAI não configurada!\n";
        echo "   Configure 'openai_api_key' na tabela settings ou OPENAI_API_KEY no ambiente.\n";
        exit(1);
    }
}

echo "✅ Configurações OK\n\n";

// Configurações de processamento
$limit = (int)($_ENV['CALL_ANALYSIS_BATCH_SIZE'] ?? 5);
echo "📊 Processando até {$limit} chamadas por execução\n\n";

try {
    // Processar chamadas pendentes
    $results = CallPerformanceAnalysisService::processPendingCalls($limit);
    
    echo "\n" . str_repeat('-', 40) . "\n";
    echo "📈 RESULTADOS:\n";
    echo "   Processadas: {$results['processed']}\n";
    echo "   ✅ Sucesso: {$results['success']}\n";
    echo "   ❌ Falha: {$results['failed']}\n";
    echo str_repeat('-', 40) . "\n";
    
    if ($results['processed'] === 0) {
        echo "\n💤 Nenhuma chamada pendente de análise.\n";
    } else {
        echo "\n✅ Processamento concluído!\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    Logger::api4com("analyze-calls.php - ERRO: " . $e->getMessage(), 'ERROR');
    exit(1);
}

echo "\n⏱️ Tempo de execução: " . round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2) . "s\n";
echo str_repeat('=', 60) . "\n\n";
