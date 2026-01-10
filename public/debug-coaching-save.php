<?php
/**
 * Debug: Verificar salvamento de Coaching
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG: COACHING CONFIG ===\n\n";

// Verificar banco
$sql = "SELECT * FROM settings WHERE `key` = 'conversation_settings' LIMIT 1";
$result = Database::fetchOne($sql);

if ($result) {
    echo "✅ Registro encontrado\n";
    $settings = json_decode($result['value'], true);
    
    if (isset($settings['realtime_coaching'])) {
        echo "✅ Seção realtime_coaching existe\n\n";
        
        $coaching = $settings['realtime_coaching'];
        
        echo "CONFIGURAÇÕES:\n";
        echo "-------------\n";
        foreach ($coaching as $key => $value) {
            if (is_array($value)) {
                echo "$key: " . json_encode($value) . "\n";
            } elseif (is_bool($value)) {
                echo "$key: " . ($value ? 'true' : 'false') . "\n";
            } else {
                echo "$key: $value\n";
            }
        }
    } else {
        echo "❌ Seção realtime_coaching NÃO existe\n";
        echo "Seções disponíveis:\n";
        foreach (array_keys($settings) as $section) {
            echo "  - $section\n";
        }
    }
} else {
    echo "❌ Nenhum registro encontrado\n";
}
