<?php
require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance();
    
    // Estrutura da tabela api_tokens
    $stmt = $db->query("DESCRIBE api_tokens");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Exemplo de registro
    $stmt = $db->query("SELECT * FROM api_tokens LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'table' => 'api_tokens',
        'columns' => $columns,
        'sample_record' => $sample,
        'column_names' => array_column($columns, 'Field')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
