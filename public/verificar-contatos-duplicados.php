<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$pdo = \App\Helpers\Database::getInstance();

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         VERIFICAR CONTATOS DUPLICADOS                     ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// Buscar contatos duplicados por telefone
$sql = "SELECT phone, COUNT(*) as total, 
        GROUP_CONCAT(id ORDER BY id) as ids,
        GROUP_CONCAT(name ORDER BY id SEPARATOR ' | ') as names
        FROM contacts 
        WHERE phone IS NOT NULL AND phone != ''
        GROUP BY phone 
        HAVING COUNT(*) > 1
        ORDER BY total DESC
        LIMIT 10";

$stmt = $pdo->query($sql);
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "✅ Nenhum contato duplicado encontrado!\n\n";
} else {
    echo "🚨 CONTATOS DUPLICADOS ENCONTRADOS:\n\n";
    foreach ($duplicates as $dup) {
        echo "📱 Telefone: {$dup['phone']}\n";
        echo "   Total: {$dup['total']} contatos\n";
        echo "   IDs: {$dup['ids']}\n";
        echo "   Nomes: {$dup['names']}\n\n";
    }
}

// Buscar últimos contatos criados
echo "\n📊 ÚLTIMOS 10 CONTATOS CRIADOS:\n";
echo str_repeat("─", 60) . "\n\n";

$sql = "SELECT id, name, phone, whatsapp_id, created_at 
        FROM contacts 
        ORDER BY created_at DESC 
        LIMIT 10";

$stmt = $pdo->query($sql);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($recent as $contact) {
    echo "ID: {$contact['id']} | {$contact['name']}\n";
    echo "   📱 Phone: {$contact['phone']}\n";
    echo "   💬 WhatsApp: {$contact['whatsapp_id']}\n";
    echo "   📅 Criado: {$contact['created_at']}\n\n";
}

// Verificar último contato específico mencionado nos logs (443)
echo "\n🔍 DETALHES DO CONTATO #443:\n";
echo str_repeat("─", 60) . "\n\n";

$stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = 443");
$stmt->execute();
$contact443 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($contact443) {
    echo "✅ Contato encontrado:\n";
    foreach ($contact443 as $key => $value) {
        echo "   {$key}: {$value}\n";
    }
    
    // Verificar se há outros contatos com mesmo telefone
    echo "\n\n🔍 OUTROS CONTATOS COM MESMO TELEFONE:\n";
    $stmt = $pdo->prepare("SELECT id, name, phone, created_at FROM contacts WHERE phone = ? AND id != 443");
    $stmt->execute([$contact443['phone']]);
    $others = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($others)) {
        echo "   ✅ Nenhum outro contato com esse telefone\n";
    } else {
        echo "   🚨 DUPLICATAS ENCONTRADAS:\n";
        foreach ($others as $other) {
            echo "      ID: {$other['id']} | {$other['name']} | {$other['created_at']}\n";
        }
    }
} else {
    echo "❌ Contato #443 não encontrado\n";
}

echo "\n\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Verificação concluída!\n";
echo "═══════════════════════════════════════════════════════════\n";

