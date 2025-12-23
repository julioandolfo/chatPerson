<?php
/**
 * Verificar colunas da tabela funnels
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/plain');

$pdo = \App\Helpers\Database::getInstance();

echo "=== COLUNAS DA TABELA FUNNELS ===\n";
$result = $pdo->query('DESCRIBE funnels')->fetchAll(PDO::FETCH_COLUMN);
print_r($result);

echo "\n=== COLUNAS DA TABELA FUNNEL_STAGES ===\n";
$result = $pdo->query('DESCRIBE funnel_stages')->fetchAll(PDO::FETCH_COLUMN);
print_r($result);

echo "\n=== Verificando se ai_description existe ===\n";
$funnelsCols = $pdo->query('DESCRIBE funnels')->fetchAll(PDO::FETCH_COLUMN);
if (in_array('ai_description', $funnelsCols)) {
    echo "✅ ai_description EXISTE em funnels\n";
} else {
    echo "❌ ai_description NÃO EXISTE em funnels - Execute a migration!\n";
}

$stagesCols = $pdo->query('DESCRIBE funnel_stages')->fetchAll(PDO::FETCH_COLUMN);
if (in_array('ai_description', $stagesCols)) {
    echo "✅ ai_description EXISTE em funnel_stages\n";
} else {
    echo "❌ ai_description NÃO EXISTE em funnel_stages - Execute a migration!\n";
}

if (in_array('ai_keywords', $stagesCols)) {
    echo "✅ ai_keywords EXISTE em funnel_stages\n";
} else {
    echo "❌ ai_keywords NÃO EXISTE em funnel_stages - Execute a migration!\n";
}

