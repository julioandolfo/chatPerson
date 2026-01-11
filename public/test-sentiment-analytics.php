<?php
/**
 * Script de teste para analytics de sentimento
 */

header('Content-Type: text/plain; charset=utf-8');

// Bootstrap
require_once __DIR__ . '/../config/bootstrap.php';

use App\Models\ConversationSentiment;
use App\Helpers\Database;

echo "=== TESTE DE ANALYTICS DE SENTIMENTO ===\n\n";

try {
    // Contar total de análises
    $totalCount = Database::fetch("SELECT COUNT(*) as total FROM conversation_sentiments");
    echo "📊 Total de análises na tabela: " . ($totalCount['total'] ?? 0) . "\n\n";
    
    if (($totalCount['total'] ?? 0) == 0) {
        echo "⚠️ Nenhuma análise encontrada na tabela conversation_sentiments\n";
        echo "Execute: php public/scripts/analyze-sentiments.php\n\n";
        exit;
    }
    
    // Testar método getAnalytics sem filtros
    echo "🔍 Testando ConversationSentiment::getAnalytics()...\n";
    $stats = ConversationSentiment::getAnalytics([]);
    
    echo "Resultado:\n";
    echo "  - total_analyses: " . ($stats['total_analyses'] ?? 'NULL') . "\n";
    echo "  - avg_sentiment: " . ($stats['avg_sentiment'] ?? 'NULL') . "\n";
    echo "  - positive_count: " . ($stats['positive_count'] ?? 'NULL') . "\n";
    echo "  - neutral_count: " . ($stats['neutral_count'] ?? 'NULL') . "\n";
    echo "  - negative_count: " . ($stats['negative_count'] ?? 'NULL') . "\n";
    echo "  - critical_count: " . ($stats['critical_count'] ?? 'NULL') . "\n";
    echo "  - total_cost: " . ($stats['total_cost'] ?? 'NULL') . "\n\n";
    
    // Verificar estrutura da query
    echo "🔎 Verificando estrutura dos dados...\n";
    $sample = Database::fetch("SELECT cs.*, c.id as conv_id, c.department_id, c.agent_id 
                               FROM conversation_sentiments cs 
                               LEFT JOIN conversations c ON cs.conversation_id = c.id 
                               LIMIT 1");
    
    if ($sample) {
        echo "✅ Estrutura da tabela parece correta\n";
        echo "Campos disponíveis:\n";
        foreach ($sample as $key => $value) {
            echo "  - {$key}: " . (is_null($value) ? 'NULL' : substr(var_export($value, true), 0, 50)) . "\n";
        }
    } else {
        echo "❌ Erro ao buscar dados de exemplo\n";
    }
    
    echo "\n";
    
    // Testar com filtros
    echo "🔍 Testando com filtros (últimos 30 dias)...\n";
    $stats30 = ConversationSentiment::getAnalytics([
        'start_date' => date('Y-m-d', strtotime('-30 days')),
        'end_date' => date('Y-m-d')
    ]);
    
    echo "Resultado (últimos 30 dias):\n";
    echo "  - total_analyses: " . ($stats30['total_analyses'] ?? 'NULL') . "\n";
    echo "  - avg_sentiment: " . ($stats30['avg_sentiment'] ?? 'NULL') . "\n";
    echo "  - negative_count: " . ($stats30['negative_count'] ?? 'NULL') . "\n";
    echo "  - total_cost: $" . number_format($stats30['total_cost'] ?? 0, 4) . "\n\n";
    
    echo "✅ Teste concluído!\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "=== FIM DO TESTE ===\n";
