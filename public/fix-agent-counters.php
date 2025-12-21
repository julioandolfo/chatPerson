<?php
/**
 * Script de Recálculo: Contadores de Conversas dos Agentes
 * 
 * Este script recalcula os contadores de conversas ativas de todos os agentes,
 * corrigindo possíveis inconsistências causadas pelo bug de incremento duplicado.
 */

require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

$db = Database::getInstance();

echo "<h1>🔄 Recálculo dos Contadores de Conversas dos Agentes</h1>";
echo "<pre>";

try {
    $db->beginTransaction();
    
    echo "📊 Buscando todos os agentes ativos...\n\n";
    
    // Buscar todos os agentes
    $sql = "SELECT id, name, current_conversations, role 
            FROM users 
            WHERE role IN ('agent', 'admin', 'supervisor', 'senior_agent', 'junior_agent') 
            AND status = 'active'
            ORDER BY name";
    $agents = Database::fetchAll($sql);
    
    echo "Encontrados " . count($agents) . " agentes.\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $totalFixed = 0;
    $totalCorrect = 0;
    
    foreach ($agents as $agent) {
        $agentId = $agent['id'];
        $agentName = $agent['name'];
        $currentCounter = $agent['current_conversations'] ?? 0;
        
        // Contar conversas ativas do agente no banco
        $sql = "SELECT COUNT(*) as total 
                FROM conversations 
                WHERE agent_id = ? 
                AND status IN ('open', 'pending')";
        $result = Database::fetch($sql, [$agentId]);
        $realCount = (int)$result['total'];
        
        // Comparar contador atual com contagem real
        if ($currentCounter != $realCount) {
            // Contador incorreto - corrigir
            $sql = "UPDATE users SET current_conversations = ? WHERE id = ?";
            Database::execute($sql, [$realCount, $agentId]);
            
            $diff = $realCount - $currentCounter;
            $diffText = $diff > 0 ? "+{$diff}" : $diff;
            
            echo "🔧 CORRIGIDO: {$agentName} ({$agent['role']})\n";
            echo "   Contador antigo: {$currentCounter}\n";
            echo "   Conversas reais: {$realCount}\n";
            echo "   Diferença: {$diffText}\n\n";
            
            $totalFixed++;
        } else {
            // Contador correto
            echo "✅ OK: {$agentName} ({$agent['role']}) - {$realCount} conversas\n";
            $totalCorrect++;
        }
    }
    
    $db->commit();
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ RECÁLCULO CONCLUÍDO!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "📊 Estatísticas:\n";
    echo "   • Total de agentes: " . count($agents) . "\n";
    echo "   • Contadores corretos: {$totalCorrect}\n";
    echo "   • Contadores corrigidos: {$totalFixed}\n\n";
    
    if ($totalFixed > 0) {
        echo "🎉 {$totalFixed} contador(es) foram corrigidos!\n\n";
        echo "📝 Detalhes:\n";
        echo "   - Os contadores foram recalculados baseados nas conversas reais\n";
        echo "   - Status considerados: 'open' e 'pending'\n";
        echo "   - Conversas 'resolved', 'closed' ou outras não contam\n\n";
    } else {
        echo "ℹ️  Todos os contadores já estavam corretos!\n\n";
    }
    
    echo "📌 Próximos passos:\n";
    echo "   1. Verifique se os contadores nos cards dos agentes estão corretos\n";
    echo "   2. O sistema agora NÃO incrementará contadores em atribuições repetidas\n";
    echo "   3. Monitore os logs para ver mensagens de debug sobre contadores\n\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<p><a href='javascript:history.back()'>← Voltar</a></p>";

