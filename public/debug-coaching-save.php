<?php
/**
 * Debug: Verificar salvamento de Coaching (modo independente, sem autoload)
 */

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Caminhos esperados
$base = dirname(__DIR__);
$configPath = $base . '/config/database.php';
$logFile = $base . '/storage/logs/coaching_debug.log';

function log_debug($msg) {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

log_debug('--- debug-coaching-save.php iniciado ---');

// Carregar config do banco (sem autoload)
if (!file_exists($configPath)) {
    log_debug("Config database.php não encontrado em {$configPath}");
    echo "ERRO: config/database.php não encontrado.\n";
    exit;
}

$dbConfig = require $configPath;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database'],
        $dbConfig['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    log_debug('Conexão PDO criada com sucesso');

    $stmt = $pdo->query("SELECT * FROM settings WHERE `key` = 'conversation_settings' ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch();

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
    log_debug('Erro: ' . $e->getMessage());
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
