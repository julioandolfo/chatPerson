<?php
/**
 * Verificar Coaching - Versão Simples
 */

header('Content-Type: text/plain; charset=utf-8');

// Conectar direto ao banco
$host = 'localhost';
$dbname = 'chat_person';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== COACHING CONFIG ===\n\n";
    
    $stmt = $pdo->query("SELECT * FROM settings WHERE `key` = 'conversation_settings' LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $settings = json_decode($result['value'], true);
        
        if (isset($settings['realtime_coaching'])) {
            echo "✅ Seção realtime_coaching EXISTE\n\n";
            
            $coaching = $settings['realtime_coaching'];
            
            echo "enabled: " . ($coaching['enabled'] ? '✅ SIM' : '❌ NÃO') . "\n";
            echo "model: " . ($coaching['model'] ?? 'não definido') . "\n";
            echo "temperature: " . ($coaching['temperature'] ?? 'não definido') . "\n";
            echo "use_queue: " . ($coaching['use_queue'] ? '✅ SIM' : '❌ NÃO') . "\n";
            echo "use_cache: " . ($coaching['use_cache'] ? '✅ SIM' : '❌ NÃO') . "\n";
            
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
    echo "Trace: " . $e->getTraceAsString();
}
