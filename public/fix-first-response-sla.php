<?php
/**
 * Script para popular first_response_at nas conversas existentes
 */

require_once __DIR__ . '/../app/bootstrap.php';

echo "════════════════════════════════════════════════════════════\n";
echo "  POPULANDO first_response_at PARA CONVERSAS EXISTENTES\n";
echo "════════════════════════════════════════════════════════════\n\n";

try {
    $pdo = \App\Helpers\Database::getInstance();
    
    // 1. Adicionar coluna se não existir
    echo "1. Verificando se coluna first_response_at existe...\n";
    $sql = "SHOW COLUMNS FROM conversations LIKE 'first_response_at'";
    $result = $pdo->query($sql)->fetchAll();
    
    if (empty($result)) {
        echo "   → Coluna não existe. Criando...\n";
        $pdo->exec("ALTER TABLE conversations ADD COLUMN first_response_at TIMESTAMP NULL AFTER resolved_at");
        echo "   ✅ Coluna criada!\n\n";
    } else {
        echo "   ✅ Coluna já existe!\n\n";
    }
    
    // 2. Buscar conversas sem first_response_at
    echo "2. Buscando conversas sem first_response_at...\n";
    $sql = "SELECT id FROM conversations WHERE first_response_at IS NULL";
    $conversations = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   → Encontradas " . count($conversations) . " conversas\n\n";
    
    if (empty($conversations)) {
        echo "✅ Nenhuma conversa precisa ser atualizada!\n";
        exit(0);
    }
    
    // 3. Atualizar cada conversa
    echo "3. Atualizando conversas...\n";
    $updated = 0;
    
    foreach ($conversations as $conv) {
        $convId = $conv['id'];
        
        // Buscar primeira mensagem do agente ou AI
        $sql = "SELECT MIN(created_at) as first_response 
                FROM messages 
                WHERE conversation_id = ? 
                AND sender_type IN ('agent', 'ai_agent')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$convId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['first_response']) {
            // Atualizar conversa
            $updateSql = "UPDATE conversations 
                         SET first_response_at = ? 
                         WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$result['first_response'], $convId]);
            
            $updated++;
            
            if ($updated % 100 == 0) {
                echo "   → Atualizado: {$updated} conversas...\n";
            }
        }
    }
    
    echo "\n✅ Script concluído!\n";
    echo "   Total atualizado: {$updated} conversas\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

