<?php
/**
 * Cron Job: Processar a fila de Análise de Conversas por Coorte
 * Executar a cada minuto.
 *
 * Windows: C:\laragon\bin\php\php-8.x\php.exe C:\laragon\www\chat\public\scripts\process-conversation-analysis.php
 * Linux:   * * * * * php /var/www/html/public/scripts/process-conversation-analysis.php
 * Docker:  docker exec CONTAINER php /var/www/html/public/scripts/process-conversation-analysis.php
 *
 * Pega o lote pendente mais antigo e analisa um pedaço dele. É retomável:
 * cada conversa já analisada é pulada na próxima execução, então travar no meio
 * não perde trabalho (nem dinheiro já gasto com a IA).
 *
 * Opções:
 *   --batch=ID    Processar um lote específico
 *   --items=N     Quantas conversas analisar nesta rodada (padrão: config)
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\Logger;
use App\Models\ConversationAnalysisBatch;
use App\Services\ConversationBatchAnalysisService;

$maxExecutionTime = 600; // 10 minutos
set_time_limit($maxExecutionTime);

$batchId = null;
$itemsPerRun = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--batch=') === 0) {
        $batchId = (int)substr($arg, 8);
    }
    if (strpos($arg, '--items=') === 0) {
        $itemsPerRun = (int)substr($arg, 8);
    }
}

echo "=== ANÁLISE DE CONVERSAS (fila) ===\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n\n";

// Lock: o cron roda a cada minuto, mas uma rodada pode demorar mais que isso.
// Sem o lock, execuções se sobrepõem e a mesma conversa é analisada (e paga) 2x.
// A reserva atômica em ConversationAnalysisItem::claim() é a segunda linha de defesa.
$lockFile = sys_get_temp_dir() . '/chatperson-conversation-analysis.lock';
$lockHandle = fopen($lockFile, 'c');

if ($lockHandle === false) {
    echo "⚠️  Não foi possível abrir o arquivo de lock ({$lockFile}). Abortando.\n";
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "ℹ️  Outra execução já está em andamento. Saindo.\n";
    fclose($lockHandle);
    exit(0);
}

register_shutdown_function(static function () use ($lockHandle) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

$start = microtime(true);

try {
    if (!$batchId) {
        $batch = ConversationAnalysisBatch::getNextPending();

        if (!$batch) {
            echo "Nenhum lote pendente. Nada a fazer.\n";
            exit(0);
        }

        $batchId = (int)$batch['id'];
    }

    $settings = ConversationBatchAnalysisService::getSettings();
    $itemsPerRun = $itemsPerRun ?: (int)($settings['items_per_run'] ?? 10);

    echo "Processando lote #{$batchId} ({$itemsPerRun} conversas nesta rodada)...\n";

    $result = ConversationBatchAnalysisService::processBatch($batchId, $itemsPerRun);

    $elapsed = round(microtime(true) - $start, 1);

    echo "\n--- Resultado ---\n";
    echo "Status:      " . ($result['status'] ?? '?') . "\n";
    echo "Processadas: " . ($result['processed'] ?? 0) . "\n";

    if (isset($result['remaining'])) {
        echo "Restantes:   {$result['remaining']}\n";
    }

    if (isset($result['analyzed'])) {
        echo "Analisadas:  {$result['analyzed']}\n";
    }

    echo "Tempo:       {$elapsed}s\n";

    Logger::info(
        "process-conversation-analysis - Lote {$batchId}: status={$result['status']}, "
        . "processadas=" . ($result['processed'] ?? 0) . ", tempo={$elapsed}s"
    );
} catch (\Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n";
    Logger::error('process-conversation-analysis - ' . $e->getMessage());
    exit(1);
}

echo "\nFinalizado em: " . date('Y-m-d H:i:s') . "\n";
