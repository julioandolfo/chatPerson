<?php
require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance();
    
    // Estrutura da tabela contacts
    $stmt = $db->query("DESCRIBE contacts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Exemplo de registro
    $stmt = $db->query("SELECT * FROM contacts LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'table' => 'contacts',
        'columns' => $columns,
        'column_names' => array_column($columns, 'Field'),
        'sample_record' => $sample
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
