#!/usr/bin/env php
<?php
/**
 * Script: Processar Aprendizado de Coaching (RAG) - STANDALONE
 * Execução: Diária via cron (01:00)
 * Função: Extrair conhecimento de hints bem-sucedidos para RAG
 * 
 * Versão standalone que não depende do Composer.
 * Usa o autoloader nativo do sistema.
 * 
 * Uso: php public/scripts/process-coaching-learning.php
 * Cron: 0 1 * * * cd /var/www/html && php public/scripts/process-coaching-learning.php >> logs/coaching-learning.log 2>&1
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(dirname(__DIR__));
chdir($rootDir);

// Carregar bootstrap (que já tem o autoloader)
require_once $rootDir . '/config/bootstrap.php';

use App\Services\CoachingLearningService;

// Garantir que o diretório de logs existe
$logDir = $rootDir . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

echo "🧠 === PROCESSAMENTO DE APRENDIZADO DE COACHING ===\n";
echo "📅 Data: " . date('Y-m-d H:i:s') . "\n";
echo "📁 Root Dir: {$rootDir}\n\n";

try {
    // Processar hints de ontem
    echo "📊 Processando hints de ontem...\n";
    $result = CoachingLearningService::processSuccessfulHints(1);
    
    if (isset($result['error'])) {
        echo "❌ ERRO: {$result['error']}\n";
        exit(1);
    }
    
    echo "✅ Processamento concluído!\n\n";
    echo "📈 Estatísticas:\n";
    echo "   Data: {$result['date']}\n";
    echo "   Total de hints: {$result['total_hints']}\n";
    echo "   Processados: {$result['processed']}\n";
    echo "   Pulados: {$result['skipped']}\n";
    echo "   Erros: {$result['errors']}\n\n";
    
    // Descobrir padrões (semanal - apenas domingo)
    if (date('w') == 0) { // Domingo
        echo "🔍 Descobrindo padrões (executado semanalmente)...\n";
        $patterns = CoachingLearningService::discoverPatterns();
        
        echo "✅ Padrões descobertos: " . count($patterns) . "\n";
        
        if (!empty($patterns)) {
            echo "\n📊 Top 5 Padrões:\n";
            foreach (array_slice($patterns, 0, 5) as $pattern) {
                echo "   • {$pattern['situation_type']}: ";
                echo "{$pattern['count']} ocorrências, ";
                echo "Score médio: " . number_format($pattern['avg_score'], 2) . ", ";
                echo "Taxa sucesso: " . number_format($pattern['avg_success_rate'] * 100, 1) . "%\n";
            }
        }
    }
    
    echo "\n✅ Script finalizado com sucesso!\n";
    exit(0);
    
} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
