<?php
/**
 * Verificar Coaching - Versão simples (usa config do sistema)
 */

header('Content-Type: text/plain; charset=utf-8');

// Usar o bootstrap e o Database do projeto (pega credenciais corretas)
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

echo "=== COACHING CONFIG ===\n\n";

try {
    $result = Database::fetchOne("SELECT * FROM settings WHERE `key` = 'conversation_settings' LIMIT 1");

    if ($result) {
        $settings = json_decode($result['value'], true);

        if (isset($settings['realtime_coaching'])) {
            echo "✅ Seção realtime_coaching EXISTE\n\n";

            $coaching = $settings['realtime_coaching'];

            echo "enabled: " . (!empty($coaching['enabled']) ? '✅ SIM' : '❌ NÃO') . "\n";
            echo "model: " . ($coaching['model'] ?? 'não definido') . "\n";
            echo "temperature: " . ($coaching['temperature'] ?? 'não definido') . "\n";
            echo "use_queue: " . (!empty($coaching['use_queue']) ? '✅ SIM' : '❌ NÃO') . "\n";
            echo "use_cache: " . (!empty($coaching['use_cache']) ? '✅ SIM' : '❌ NÃO') . "\n";

            echo "\nTipos de Hint:\n";
            if (isset($coaching['hint_types']) && is_array($coaching['hint_types'])) {
                foreach ($coaching['hint_types'] as $type => $enabled) {
                    $status = $enabled ? '✅' : '❌';
                    echo "  $status $type\n";
                }
            } else {
                echo "  ❌ hint_types não é um array ou não existe\n";
                echo "  Valor: " . json_encode($coaching['hint_types'] ?? null) . "\n";
            }

            echo "\n=== JSON COMPLETO ===\n";
            echo json_encode($coaching, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } else {
            echo "❌ Seção realtime_coaching NÃO EXISTE\n\n";
            echo "Seções disponíveis:\n";
            foreach (array_keys($settings) as $key) {
                echo "  - $key\n";
            }
        }
    } else {
        echo "❌ Nenhum registro 'conversation_settings' encontrado\n";
    }
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
