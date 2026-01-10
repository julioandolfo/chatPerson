<?php
/**
 * Debug: Verificar salvamento de Coaching (modo verboso)
 */

header('Content-Type: text/plain; charset=utf-8');

// Mostrar erros para diagnosticar 500
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log dedicado
$logFile = __DIR__ . '/../storage/logs/coaching_debug.log';

function log_debug($msg) {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

log_debug('--- debug-coaching-save.php iniciado ---');

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config/bootstrap.php';
    log_debug('Bootstrap carregado com sucesso');
} catch (\Exception $e) {
    log_debug('Erro ao carregar bootstrap: ' . $e->getMessage());
    echo "ERRO AO CARREGAR: " . $e->getMessage() . "\n";
    exit;
}

use App\Helpers\Database;

echo "=== DEBUG: COACHING CONFIG ===\n\n";

try {
    $sql = "SELECT * FROM settings WHERE `key` = 'conversation_settings' LIMIT 1";
    $result = Database::fetchOne($sql);

    if (!$result) {
        log_debug('Nenhum registro conversation_settings encontrado');
        echo "❌ Nenhum registro 'conversation_settings' encontrado\n";
        exit;
    }

    log_debug('Registro encontrado: id=' . $result['id']);
    echo "✅ Registro encontrado (id: {$result['id']})\n\n";

    $settings = json_decode($result['value'], true);
    if (!is_array($settings)) {
        log_debug('Valor inválido (não é JSON). Valor: ' . $result['value']);
        echo "❌ Valor não é JSON válido\n";
        echo "Valor bruto: {$result['value']}\n";
        exit;
    }

    if (!isset($settings['realtime_coaching'])) {
        log_debug('Seção realtime_coaching não existe. Seções: ' . implode(',', array_keys($settings)));
        echo "❌ Seção realtime_coaching NÃO existe\n";
        echo "Seções disponíveis:\n";
        foreach (array_keys($settings) as $section) {
            echo "  - $section\n";
        }
        exit;
    }

    $coaching = $settings['realtime_coaching'];
    log_debug('Seção realtime_coaching carregada: ' . json_encode($coaching));
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

} catch (\Throwable $e) {
    log_debug('Erro ao consultar: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    echo "ERRO AO CONSULTAR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
