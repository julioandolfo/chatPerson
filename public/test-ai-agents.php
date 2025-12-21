<?php
/**
 * Script de teste para verificar agentes de IA disponíveis
 */

// Carregar autoloader
require_once __DIR__ . '/../app/Helpers/Database.php';

use App\Helpers\Database;

header('Content-Type: application/json');

try {
    // 1. Testar conexão com banco
    $db = Database::getInstance();
    echo "✅ Conexão com banco OK\n\n";
    
    // 2. Verificar se existem agentes de IA
    $sql = "SELECT * FROM ai_agents";
    $allAgents = Database::fetchAll($sql);
    echo "📊 Total de agentes no banco: " . count($allAgents) . "\n";
    
    if (count($allAgents) > 0) {
        echo "\nAgentes encontrados:\n";
        foreach ($allAgents as $agent) {
            echo "  - ID: {$agent['id']}, Nome: {$agent['name']}, Tipo: {$agent['agent_type']}, Ativo: " . ($agent['enabled'] ? 'Sim' : 'Não') . "\n";
        }
    }
    
    // 3. Testar query de agentes disponíveis (mesma do Model)
    echo "\n\n📋 Testando query de agentes disponíveis:\n";
    $sql = "SELECT * FROM ai_agents WHERE enabled = TRUE";
    $sql .= " AND (max_conversations IS NULL OR current_conversations < max_conversations)";
    $sql .= " ORDER BY name ASC";
    
    $availableAgents = Database::fetchAll($sql);
    echo "✅ Agentes disponíveis: " . count($availableAgents) . "\n";
    
    if (count($availableAgents) > 0) {
        echo "\nAgentes disponíveis:\n";
        foreach ($availableAgents as $agent) {
            echo "  - ID: {$agent['id']}, Nome: {$agent['name']}, Tipo: {$agent['agent_type']}\n";
            echo "    Conversas: {$agent['current_conversations']} / " . ($agent['max_conversations'] ?? 'ilimitado') . "\n";
        }
    }
    
    // 4. Teste do Service
    echo "\n\n🔧 Testando via Service:\n";
    require_once __DIR__ . '/../app/Models/Model.php';
    require_once __DIR__ . '/../app/Models/AIAgent.php';
    
    $serviceAgents = \App\Models\AIAgent::getAvailableAgents();
    echo "✅ Agentes retornados pelo Service: " . count($serviceAgents) . "\n";
    
    echo "\n\n🎯 RESULTADO FINAL:\n";
    echo json_encode([
        'success' => true,
        'total_agents' => count($allAgents),
        'available_agents' => count($availableAgents),
        'data' => $availableAgents
    ], JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    echo "\n\n❌ ERRO:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    echo "\n\nJSON:\n";
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}

