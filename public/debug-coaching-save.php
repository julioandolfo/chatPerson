<?php
/**
 * Debug: Verificar salvamento de Coaching (versão robusta)
 */

header('Content-Type: text/plain; charset=utf-8');

// Evitar que erros quebrem o output
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config/bootstrap.php';
} catch (\Exception $e) {
    echo "ERRO AO CARREGAR: " . $e->getMessage() . "\n";
    exit;
}

use App\Helpers\Database;

echo "=== DEBUG: COACHING CONFIG ===\n\n";

try {
    $sql = "SELECT * FROM settings WHERE `key` = 'conversation_settings' LIMIT 1";
    $result = Database::fetchOne($sql);

    if (!$result) {
        echo "❌ Nenhum registro 'conversation_settings' encontrado\n";
        exit;
    }

    echo "✅ Registro encontrado (id: {$result['id']})\n\n";

    $settings = json_decode($result['value'], true);
    if (!is_array($settings)) {
        echo "❌ Valor não é JSON válido\n";
        echo "Valor bruto: {$result['value']}\n";
        exit;
    }

    if (!isset($settings['realtime_coaching'])) {
        echo "❌ Seção realtime_coaching NÃO existe\n";
        echo "Seções disponíveis:\n";
        foreach (array_keys($settings) as $section) {
            echo "  - $section\n";
        }
        exit;
    }

    $coaching = $settings['realtime_coaching'];
    echo "✅ Seção realtime_coaching existe\n\n";

    echo "CONFIGURAÇÕES:\n";
    echo "-------------\n";
    foreach ($coaching as $key => $value) {
        if (is_array($value)) {
            echo "$key: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
        } elseif (is_bool($value)) {
            echo "$key: " . ($value ? 'true' : 'false') . "\n";
        } else {
            echo "$key: $value\n";
        }
    }

    echo "\nJSON COMPLETO:\n";
    echo json_encode($coaching, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} catch (\Exception $e) {
    echo "ERRO AO CONSULTAR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
