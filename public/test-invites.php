<?php
/**
 * Teste rápido para verificar convites
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Helpers\Database;
use App\Helpers\Auth;

// Simular autenticação (ajuste o ID do usuário)
$_SESSION['user_id'] = 1; // Ajustar para seu user_id

echo "🧪 TESTANDO SISTEMA DE CONVITES\n";
echo "================================\n\n";

// 1. Verificar se tabela existe
echo "1. Verificando tabela conversation_mentions...\n";
try {
    $db = Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'conversation_mentions'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "   ✅ Tabela existe\n";
        
        // Verificar estrutura
        $stmt = $db->query("DESCRIBE conversation_mentions");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "   📋 Colunas: " . implode(', ', $columns) . "\n";
    } else {
        echo "   ❌ Tabela NÃO existe!\n";
        echo "   💡 Execute a migration 066_create_conversation_mentions_table.php\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// 2. Testar getPendingInvites
echo "2. Testando getPendingInvites...\n";
try {
    $userId = Auth::id();
    echo "   🔑 User ID: {$userId}\n";
    
    $invites = \App\Services\ConversationMentionService::getPendingInvites($userId);
    echo "   ✅ Invites retornados: " . count($invites) . "\n";
    
    if (!empty($invites)) {
        echo "   📋 Primeiro invite:\n";
        print_r($invites[0]);
    }
} catch (\Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
    echo "   📍 File: " . $e->getFile() . "\n";
    echo "   📍 Line: " . $e->getLine() . "\n";
}

echo "\n";

// 3. Testar getPendingRequestsToApprove
echo "3. Testando getPendingRequestsToApprove...\n";
try {
    $userId = Auth::id();
    
    $requests = \App\Models\ConversationMention::getPendingRequestsToApprove($userId);
    echo "   ✅ Requests retornados: " . count($requests) . "\n";
    
    if (!empty($requests)) {
        echo "   📋 Primeira request:\n";
        print_r($requests[0]);
    }
} catch (\Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
    echo "   📍 File: " . $e->getFile() . "\n";
    echo "   📍 Line: " . $e->getLine() . "\n";
}

echo "\n";
echo "================================\n";
echo "✅ Testes concluídos!\n";
