<?php
/**
 * Script: Limpar tabelas de campanhas para reinstalação
 */

require_once __DIR__ . '/config/bootstrap.php';

$db = \App\Helpers\Database::getInstance();

echo "🧹 Limpando tabelas de campanhas...\n\n";

$tables = [
    'campaign_rotation_log',
    'campaign_blacklist',
    'campaign_messages',
    'campaign_variants',
    'campaign_notifications',
    'drip_contact_progress',
    'drip_steps',
    'drip_sequences',
    'campaigns',
    'contact_list_items',
    'contact_lists'
];

foreach ($tables as $table) {
    try {
        $db->exec("DROP TABLE IF EXISTS {$table}");
        echo "✅ Tabela '{$table}' removida\n";
    } catch (\Exception $e) {
        echo "⚠️ Tabela '{$table}' não existe ou já foi removida\n";
    }
}

echo "\n✅ Limpeza concluída!\n";
echo "\nAgora execute: php database/migrate.php\n\n";
