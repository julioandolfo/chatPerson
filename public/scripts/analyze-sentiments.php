<?php
/**
 * Script para análise periódica de sentimento
 * Executar via cron a cada 5 minutos:
 * cd /var/www/html && php public/scripts/analyze-sentiments.php >> logs/sentiment-analysis.log 2>&1
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/bootstrap.php';

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

