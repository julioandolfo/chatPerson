<?php
require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance();
    
    // Estrutura da tabela whatsapp_accounts
    $stmt = $db->query("DESCRIBE whatsapp_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Exemplo de registro
    $stmt = $db->query("SELECT * FROM whatsapp_accounts LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'table' => 'whatsapp_accounts',
        'columns' => $columns,
        'column_names' => array_column($columns, 'Field'),
        'sample_record' => $sample
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
