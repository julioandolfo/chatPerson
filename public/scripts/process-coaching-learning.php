<?php
/**
 * Job: Processar aprendizado contínuo do coaching
 * Extrai conhecimento de hints bem-sucedidos para RAG
 * 
 * Executar diariamente via cron (ex: às 3h da manhã)
 * Crontab: 0 3 * * * cd /var/www/html && php public/scripts/process-coaching-learning.php >> storage/logs/coaching-learning.log 2>&1
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Services\CoachingLearningService;

echo "[" . date('Y-m-d H:i:s') . "] 🧠 Iniciando processamento de aprendizado de coaching...\n";

try {
    // Processar hints de ontem
    $result = CoachingLearningService::processSuccessfulHints(1);
    
    echo "[" . date('Y-m-d H:i:s') . "] 📊 Resultados do processamento:\n";
    echo "  • Data: {$result['date']}\n";
    echo "  • Total de hints: {$result['total_hints']}\n";
    echo "  • Processados (adicionados ao RAG): {$result['processed']}\n";
    echo "  • Pulados (score < 4): {$result['skipped']}\n";
    echo "  • Erros: {$result['errors']}\n";
    
    if ($result['processed'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ✅ {$result['processed']} novos conhecimentos adicionados à base!\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ℹ️  Nenhum novo conhecimento adicionado hoje\n";
    }
    
    // Aos domingos, descobrir padrões
    if (date('w') == 0) {
        echo "[" . date('Y-m-d H:i:s') . "] 🔍 Descobrindo novos padrões (execução semanal)...\n";
        $patterns = CoachingLearningService::discoverPatterns();
        
        if (!empty($patterns)) {
            echo "[" . date('Y-m-d H:i:s') . "] 📊 Padrões encontrados:\n";
            foreach ($patterns as $pattern) {
                echo "  • {$pattern['situation_type']}: {$pattern['count']} casos (Score: " . 
                     round($pattern['avg_score'], 2) . ")\n";
            }
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Processamento concluído com sucesso!\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
