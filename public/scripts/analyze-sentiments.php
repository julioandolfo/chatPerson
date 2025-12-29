<?php
/**
 * Script para análise periódica de sentimento
 * Executar via cron a cada 5 minutos:
 * cd /var/www/html && php public/scripts/analyze-sentiments.php >> logs/sentiment-analysis.log 2>&1
 */

// Mudar para diretório raiz do projeto
chdir(__DIR__ . '/../../');

// Autoloader
require_once __DIR__ . '/../../app/Helpers/autoload.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurar error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Garantir que o diretório de logs existe
$logsDir = __DIR__ . '/../../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

use App\Services\SentimentAnalysisService;

try {
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando análise de sentimentos...\n";
    
    $result = SentimentAnalysisService::processPendingConversations();
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Análises processadas: " . $result['processed'] . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] ⚠️ Erros: " . $result['errors'] . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] 💰 Custo total: $" . number_format($result['cost'], 4) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Concluído.\n\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Stack trace: " . $e->getTraceAsString() . "\n\n";
    exit(1);
}

