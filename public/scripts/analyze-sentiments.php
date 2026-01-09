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
    
    // Verificar se está habilitado
    $settings = \App\Services\ConversationSettingsService::getSettings();
    $sentimentSettings = $settings['sentiment_analysis'] ?? [];
    
    if (empty($sentimentSettings['enabled'])) {
        echo "[" . date('Y-m-d H:i:s') . "] ⚠️ AVISO: Análise de sentimento está DESABILITADA nas configurações!\n";
        echo "[" . date('Y-m-d H:i:s') . "] Acesse: Configurações > Botões de Ação > Análise de Sentimento\n\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Análise habilitada\n";
    echo "[" . date('Y-m-d H:i:s') . "] 📊 Configurações:\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Modelo: " . ($sentimentSettings['model'] ?? 'N/A') . "\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Intervalo: " . ($sentimentSettings['check_interval_hours'] ?? 'N/A') . " horas\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Idade máxima: " . ($sentimentSettings['max_conversation_age_days'] ?? 'N/A') . " dias\n";
    echo "[" . date('Y-m-d H:i:s') . "]    - Mín. mensagens: " . ($sentimentSettings['min_messages_to_analyze'] ?? 'N/A') . "\n\n";
    
    // Verificar conversas elegíveis antes de processar
    $intervalHours = (int)($sentimentSettings['check_interval_hours'] ?? 5);
    $maxAgeDays = (int)($sentimentSettings['max_conversation_age_days'] ?? 30);
    $minMessages = (int)($sentimentSettings['min_messages_to_analyze'] ?? 3);
    
    $sql = "SELECT COUNT(DISTINCT c.id) as total
            FROM conversations c
            LEFT JOIN conversation_sentiments cs ON c.id = cs.conversation_id 
                AND cs.analyzed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            WHERE c.status = 'open'
            AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND cs.id IS NULL
            AND (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact') >= ?";
    
    $eligible = \App\Helpers\Database::fetch($sql, [$intervalHours, $maxAgeDays, $minMessages]);
    $eligibleCount = $eligible['total'] ?? 0;
    
    echo "[" . date('Y-m-d H:i:s') . "] 🔍 Conversas elegíveis para análise: {$eligibleCount}\n";
    
    if ($eligibleCount == 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ℹ️ Nenhuma conversa precisa ser analisada no momento.\n";
        echo "[" . date('Y-m-d H:i:s') . "] Motivos possíveis:\n";
        echo "[" . date('Y-m-d H:i:s') . "]    - Não há conversas abertas\n";
        echo "[" . date('Y-m-d H:i:s') . "]    - Conversas não têm mensagens suficientes (mín: {$minMessages})\n";
        echo "[" . date('Y-m-d H:i:s') . "]    - Conversas já foram analisadas recentemente (últimas {$intervalHours}h)\n";
        echo "[" . date('Y-m-d H:i:s') . "]    - Conversas são muito antigas (máx: {$maxAgeDays} dias)\n";
        echo "[" . date('Y-m-d H:i:s') . "] 💡 Para mais detalhes, execute: php public/scripts/debug-sentiment-analysis.php\n\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] 🚀 Processando conversas...\n\n";
    
    $result = \App\Services\SentimentAnalysisService::processPendingConversations();
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Análises processadas: " . $result['processed'] . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] ⚠️ Erros: " . $result['errors'] . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] 💰 Custo total: $" . number_format($result['cost'], 4) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Concluído.\n\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO: " . $e->getMessage() . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Stack trace: " . $e->getTraceAsString() . "\n\n";
    exit(1);
}

