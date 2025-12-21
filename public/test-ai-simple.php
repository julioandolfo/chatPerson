<?php
/**
 * Teste simples de agentes de IA
 */

// Mostrar todos os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "=== TESTE DE AGENTES DE IA ===\n\n";

try {
    // 1. Carregar autoloader
    echo "1. Carregando autoloader...\n";
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "   ✅ Autoloader OK\n\n";
    
    // 2. Carregar helpers
    echo "2. Carregando Database helper...\n";
    require_once __DIR__ . '/../app/Helpers/Database.php';
    echo "   ✅ Database helper OK\n\n";
    
    // 3. Testar conexão
    echo "3. Testando conexão com banco...\n";
    $db = \App\Helpers\Database::getInstance();
    echo "   ✅ Conexão OK\n\n";
    
    // 4. Testar query simples
    echo "4. Testando query na tabela ai_agents...\n";
    $result = $db->query("SELECT COUNT(*) as total FROM ai_agents");
    $row = $result->fetch();
    echo "   ✅ Total de agentes: {$row['total']}\n\n";
    
    // 5. Carregar Model
    echo "5. Carregando Model base...\n";
    require_once __DIR__ . '/../app/Models/Model.php';
    echo "   ✅ Model OK\n\n";
    
    // 6. Carregar AIAgent
    echo "6. Carregando AIAgent model...\n";
    require_once __DIR__ . '/../app/Models/AIAgent.php';
    echo "   ✅ AIAgent OK\n\n";
    
    // 7. Testar método estático
    echo "7. Testando AIAgent::getAvailableAgents()...\n";
    $agents = \App\Models\AIAgent::getAvailableAgents();
    echo "   ✅ Método executado com sucesso!\n";
    echo "   📊 Retornou " . count($agents) . " agente(s)\n\n";
    
    // 8. Mostrar agentes
    if (count($agents) > 0) {
        echo "8. Agentes encontrados:\n";
        foreach ($agents as $agent) {
            echo "   - ID: {$agent['id']}\n";
            echo "     Nome: {$agent['name']}\n";
            echo "     Tipo: {$agent['agent_type']}\n";
            echo "     Ativo: " . ($agent['enabled'] ? 'Sim' : 'Não') . "\n";
            echo "\n";
        }
    } else {
        echo "8. ⚠️ Nenhum agente disponível no momento\n\n";
    }
    
    // 9. Carregar Service
    echo "9. Testando ConversationAIService...\n";
    require_once __DIR__ . '/../app/Services/ConversationAIService.php';
    $serviceAgents = \App\Services\ConversationAIService::getAvailableAgents();
    echo "   ✅ Service executado com sucesso!\n";
    echo "   📊 Retornou " . count($serviceAgents) . " agente(s)\n\n";
    
    echo "\n=== ✅ TODOS OS TESTES PASSARAM! ===\n";
    echo "\nJSON final:\n";
    echo json_encode([
        'success' => true,
        'data' => $serviceAgents
    ], JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    echo "\n=== ❌ ERRO ENCONTRADO! ===\n\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";

