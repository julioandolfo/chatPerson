<?php
/**
 * Script de diagnóstico SIMPLES do Coaching
 */

// Ativar erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Diagnóstico Rápido - Coaching</h1>";
echo "<hr>";

// 1. Autoload
echo "<h2>1. Verificando Autoload...</h2>";
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "✅ Autoload OK<br>";
} else {
    echo "❌ Autoload não encontrado<br>";
    exit;
}

// 2. Classes
echo "<h2>2. Verificando Classes...</h2>";
$classes = [
    '\App\Helpers\Database',
    '\App\Services\RealtimeCoachingService',
    '\App\Listeners\MessageReceivedListener',
    '\App\Models\Message',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ {$class}<br>";
    } else {
        echo "❌ {$class} - NÃO ENCONTRADA<br>";
    }
}

// 3. Banco de dados
echo "<h2>3. Testando Conexão com Banco...</h2>";
try {
    $db = \App\Helpers\Database::getInstance();
    echo "✅ Conexão OK<br>";
    
    // Testar query simples
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Query de teste OK (resultado: {$result['test']})<br>";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit;
}

// 4. Verificar configurações
echo "<h2>4. Verificando Configurações...</h2>";
try {
    $stmt = $db->query("SELECT * FROM settings WHERE `key` = 'conversation_settings' LIMIT 1");
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($setting) {
        echo "✅ Configuração encontrada (ID: {$setting['id']})<br>";
        
        $settings = json_decode($setting['value'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "❌ Erro ao decodificar JSON: " . json_last_error_msg() . "<br>";
        } else {
            echo "✅ JSON decodificado com sucesso<br>";
            
            if (isset($settings['realtime_coaching'])) {
                echo "✅ Seção 'realtime_coaching' existe<br>";
                
                $coaching = $settings['realtime_coaching'];
                $enabled = $coaching['enabled'] ?? false;
                
                if ($enabled) {
                    echo "✅✅✅ <strong style='color:green'>COACHING ESTÁ HABILITADO!</strong><br>";
                } else {
                    echo "❌❌❌ <strong style='color:red'>COACHING ESTÁ DESABILITADO!</strong><br>";
                    echo "<p>👉 Vá em <a href='/settings?tab=conversations'>/settings?tab=conversations</a> e habilite</p>";
                }
                
                echo "<br><strong>Configurações atuais:</strong><br>";
                echo "<pre>" . htmlspecialchars(json_encode($coaching, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
                
            } else {
                echo "❌ Seção 'realtime_coaching' NÃO existe<br>";
            }
        }
    } else {
        echo "❌ Configuração 'conversation_settings' não encontrada no banco<br>";
    }
} catch (Exception $e) {
    echo "❌ ERRO: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 5. Verificar tabelas
echo "<h2>5. Verificando Tabelas...</h2>";
$tables = ['coaching_queue', 'realtime_coaching_hints', 'messages'];

foreach ($tables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) as total FROM `{$table}`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            echo "✅ Tabela '{$table}' existe ({$count} registros)<br>";
        } else {
            echo "❌ Tabela '{$table}' NÃO existe<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao verificar '{$table}': " . htmlspecialchars($e->getMessage()) . "<br>";
    }
}

// 6. Verificar mensagens recentes
echo "<h2>6. Mensagens Recentes de Clientes...</h2>";
try {
    $stmt = $db->query("SELECT id, conversation_id, sender_type, LEFT(content, 50) as content, created_at 
                       FROM messages 
                       WHERE sender_type = 'contact'
                       ORDER BY created_at DESC 
                       LIMIT 3");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($messages) > 0) {
        echo "✅ Encontradas " . count($messages) . " mensagens recentes<br>";
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>Conversa</th><th>Conteúdo</th><th>Data</th></tr>";
        foreach ($messages as $msg) {
            echo "<tr>";
            echo "<td>{$msg['id']}</td>";
            echo "<td>{$msg['conversation_id']}</td>";
            echo "<td>" . htmlspecialchars($msg['content']) . "</td>";
            echo "<td>{$msg['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "⚠️ Nenhuma mensagem de cliente encontrada<br>";
    }
} catch (Exception $e) {
    echo "❌ ERRO: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 7. Testar listener manualmente
echo "<h2>7. TESTE DO LISTENER</h2>";

if (isset($messages) && count($messages) > 0) {
    $testMsg = $messages[0];
    echo "<p>Testando com mensagem ID: <strong>{$testMsg['id']}</strong></p>";
    
    try {
        echo "<p style='background:#fff3cd; padding:10px'>🔄 Executando MessageReceivedListener...</p>";
        
        \App\Listeners\MessageReceivedListener::handle($testMsg['id']);
        
        echo "<p style='background:#d4edda; padding:10px'>✅ Listener executado SEM ERROS!</p>";
        echo "<p>Agora verifique o log em: <a href='/view-all-logs.php' target='_blank'>/view-all-logs.php</a></p>";
        
    } catch (Exception $e) {
        echo "<p style='background:#f8d7da; padding:10px'>❌ ERRO ao executar listener:</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

// 8. Verificar log
echo "<h2>8. Arquivo de Log...</h2>";
$logFile = __DIR__ . '/../logs/coaching.log';

if (file_exists($logFile)) {
    $size = filesize($logFile);
    $lines = count(file($logFile));
    echo "✅ Arquivo existe (Tamanho: {$size} bytes, Linhas: {$lines})<br>";
    
    if ($size > 50) {
        echo "<br><strong>Últimas 10 linhas:</strong><br>";
        $content = file($logFile);
        $lastLines = array_slice($content, -10);
        echo "<pre style='background:#2c3e50; color:#ecf0f1; padding:15px'>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
    } else {
        echo "⚠️ Log está vazio ou muito pequeno<br>";
    }
} else {
    echo "❌ Arquivo de log NÃO existe<br>";
}

// 9. API Key
echo "<h2>9. API Key OpenAI...</h2>";
try {
    $stmt = $db->query("SELECT value FROM settings WHERE `key` = 'openai_api_key' LIMIT 1");
    $apiKey = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($apiKey && !empty($apiKey['value'])) {
        $preview = substr($apiKey['value'], 0, 10) . '...' . substr($apiKey['value'], -4);
        echo "✅ API Key configurada: {$preview}<br>";
    } else {
        echo "❌ API Key NÃO configurada<br>";
    }
} catch (Exception $e) {
    echo "❌ ERRO: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<hr>";
echo "<h2>✅ Diagnóstico Completo!</h2>";
echo "<p><a href='/view-all-logs.php' target='_blank' style='padding:10px 20px; background:#3498db; color:white; text-decoration:none; border-radius:4px'>📋 Ver Logs</a></p>";
echo "<p><a href='?' style='padding:10px 20px; background:#95a5a6; color:white; text-decoration:none; border-radius:4px'>🔄 Recarregar</a></p>";
