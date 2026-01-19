<?php
require_once __DIR__ . '/config/bootstrap.php';

use App\Helpers\Database;

echo "== Debug WhatsApp Accounts ==\n";

try {
    $dbName = Database::fetch("SELECT DATABASE() as db");
    echo "DB: " . ($dbName['db'] ?? 'n/a') . "\n\n";

    $waCount = Database::fetch("SELECT COUNT(*) as total FROM whatsapp_accounts");
    $iaCount = Database::fetch("SELECT COUNT(*) as total FROM integration_accounts WHERE channel = 'whatsapp'");
    $iaAllCount = Database::fetch("SELECT COUNT(*) as total FROM integration_accounts");

    echo "whatsapp_accounts total: " . ($waCount['total'] ?? 0) . "\n";
    echo "integration_accounts (channel=whatsapp): " . ($iaCount['total'] ?? 0) . "\n";
    echo "integration_accounts total: " . ($iaAllCount['total'] ?? 0) . "\n\n";

    echo "-- Sample whatsapp_accounts --\n";
    $waRows = Database::fetchAll("SELECT id, name, phone_number, provider, status FROM whatsapp_accounts ORDER BY id DESC LIMIT 10");
    foreach ($waRows as $row) {
        echo "#{$row['id']} | {$row['name']} | {$row['phone_number']} | {$row['provider']} | {$row['status']}\n";
    }
    if (empty($waRows)) {
        echo "(no rows)\n";
    }

    echo "\n-- Sample integration_accounts (whatsapp) --\n";
    $iaRows = Database::fetchAll("SELECT id, name, phone_number, provider, status FROM integration_accounts WHERE channel = 'whatsapp' ORDER BY id DESC LIMIT 10");
    foreach ($iaRows as $row) {
        echo "#{$row['id']} | {$row['name']} | {$row['phone_number']} | {$row['provider']} | {$row['status']}\n";
    }
    if (empty($iaRows)) {
        echo "(no rows)\n";
    }

    echo "\n-- Columns whatsapp_accounts --\n";
    $columns = Database::fetchAll("SHOW COLUMNS FROM whatsapp_accounts");
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (\Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
