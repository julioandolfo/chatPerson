<?php
/**
 * Teste rápido da tabela ai_agents
 */

require_once __DIR__ . '/../app/Helpers/Database.php';

use App\Helpers\Database;

header('Content-Type: text/plain; charset=utf-8');

echo "=== TESTE DA TABELA AI_AGENTS ===\n\n";

try {
    $db = Database::getInstance();
    echo "✅ Conexão com banco OK\n\n";
    
    // 1. Verificar se a tabela existe
    echo "1. Verificando se tabela ai_agents existe...\n";
    $tables = $db->query("SHOW TABLES LIKE 'ai_agents'")->fetchAll();
    
    if (count($tables) === 0) {
        echo "   ❌ ERRO: Tabela 'ai_agents' não existe!\n";
        echo "   Execute as migrations para criar a tabela.\n";
        exit;
    }
    echo "   ✅ Tabela existe\n\n";
    
    // 2. Ver estrutura da tabela
    echo "2. Estrutura da tabela:\n";
    $structure = $db->query("DESCRIBE ai_agents")->fetchAll();
    foreach ($structure as $field) {
        echo "   - {$field['Field']} ({$field['Type']})\n";
    }
    echo "\n";
    
    // 3. Contar registros
    echo "3. Total de registros:\n";
    $result = $db->query("SELECT COUNT(*) as total FROM ai_agents")->fetch();
    echo "   Total: {$result['total']}\n\n";
    
    if ($result['total'] == 0) {
        echo "   ⚠️ ATENÇÃO: Não há agentes cadastrados!\n";
        echo "   Você precisa criar agentes de IA antes de usar.\n\n";
    }
    
    // 4. Listar todos os agentes
    echo "4. Listando todos os agentes:\n";
    $agents = Database::fetchAll("SELECT * FROM ai_agents");
    
    if (count($agents) > 0) {
        foreach ($agents as $agent) {
            echo "\n   Agente #{$agent['id']}:\n";
            echo "   - Nome: {$agent['name']}\n";
            echo "   - Tipo: {$agent['agent_type']}\n";
            echo "   - Modelo: {$agent['model']}\n";
            echo "   - Ativo: " . ($agent['enabled'] ? 'Sim' : 'Não') . "\n";
            echo "   - Max conversas: " . ($agent['max_conversations'] ?? 'Ilimitado') . "\n";
            echo "   - Conversas atuais: {$agent['current_conversations']}\n";
        }
    } else {
        echo "   (Nenhum agente cadastrado)\n";
    }
    echo "\n";
    
    // 5. Testar query de disponíveis
    echo "5. Testando query de agentes disponíveis:\n";
    $sql = "SELECT * FROM ai_agents WHERE enabled = TRUE";
    $sql .= " AND (max_conversations IS NULL OR current_conversations < max_conversations)";
    $sql .= " ORDER BY name ASC";
    
    $available = Database::fetchAll($sql);
    echo "   Agentes disponíveis: " . count($available) . "\n\n";
    
    if (count($available) > 0) {
        foreach ($available as $agent) {
            echo "   - {$agent['name']} (ID: {$agent['id']})\n";
        }
    } else {
        echo "   ⚠️ Nenhum agente disponível!\n";
        if ($result['total'] > 0) {
            echo "   Possíveis motivos:\n";
            echo "   - Todos os agentes estão desabilitados (enabled = FALSE)\n";
            echo "   - Todos os agentes atingiram o limite de conversas\n";
        }
    }
    
    echo "\n=== FIM DO TESTE ===\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO:\n";
    echo "Mensagem: {$e->getMessage()}\n";
    echo "Arquivo: {$e->getFile()}:{$e->getLine()}\n";
}

