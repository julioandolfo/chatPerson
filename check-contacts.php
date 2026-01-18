<?php
/**
 * Script para verificar contatos disponíveis
 */

require_once __DIR__ . '/config/bootstrap.php';

echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║        CONTATOS DISPONÍVEIS NO SISTEMA               ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

try {
    $limit = isset($argv[1]) ? (int)$argv[1] : 10;
    
    // Buscar contatos
    $sql = "SELECT id, name, phone, email, created_at 
            FROM contacts 
            ORDER BY id ASC 
            LIMIT ?";
    
    $contacts = \App\Helpers\Database::fetchAll($sql, [$limit]);
    
    if (empty($contacts)) {
        echo "❌ NENHUM CONTATO ENCONTRADO!\n\n";
        echo "Para testar campanhas, você precisa ter contatos cadastrados.\n\n";
        echo "Crie contatos em: /contacts\n";
        exit(1);
    }
    
    foreach ($contacts as $contact) {
        echo "📱 ID: {$contact['id']}\n";
        echo "   Nome: {$contact['name']}\n";
        echo "   Telefone: " . ($contact['phone'] ?? '-') . "\n";
        echo "   Email: " . ($contact['email'] ?? '-') . "\n";
        echo "   Criado: {$contact['created_at']}\n";
        echo "\n";
    }
    
    echo "───────────────────────────────────────────────────────\n";
    echo "📊 RESUMO:\n";
    echo "   Mostrando: " . count($contacts) . " contatos\n";
    
    $sqlTotal = "SELECT COUNT(*) as total FROM contacts";
    $result = \App\Helpers\Database::fetch($sqlTotal, []);
    $total = $result['total'] ?? 0;
    
    echo "   Total no banco: {$total} contatos\n\n";
    
    if ($total < 2) {
        echo "⚠️ ATENÇÃO: Você tem apenas {$total} contato(s)!\n";
        echo "   Recomendamos ter pelo menos 2 contatos para testar rotação.\n\n";
    } else {
        echo "✅ Você tem {$total} contatos cadastrados.\n\n";
        
        echo "📝 IDs para usar em lista de teste:\n";
        echo "   \$contactIds = [";
        $ids = array_column(array_slice($contacts, 0, 5), 'id');
        echo implode(', ', $ids);
        echo "];\n\n";
    }
    
    echo "💡 Para ver mais contatos: php check-contacts.php 20\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════\n";
