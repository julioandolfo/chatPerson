<?php
/**
 * Script para corrigir schemas de AI Tools no banco de dados
 * Corrige problemas como properties: [] ao invés de properties: {}
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head><title>Fix AI Tools Schema</title>';
echo '<style>body{font-family:monospace;background:#0d1117;color:#c9d1d9;padding:20px;}';
echo 'h1{color:#58a6ff;}.ok{color:#3fb950;}.fix{color:#f0883e;}.error{color:#f85149;}pre{white-space:pre-wrap;}</style>';
echo '</head><body>';
echo '<h1>🔧 Corrigindo Schemas de AI Tools</h1><pre>';

try {
    // Carregar configuração do banco diretamente
    $configFile = __DIR__ . '/../config/database.php';
    if (!file_exists($configFile)) {
        throw new Exception("Arquivo de configuração não encontrado: $configFile");
    }
    
    $config = require $configFile;
    
    // Conectar ao banco
    $host = $config['host'] ?? 'localhost';
    $port = $config['port'] ?? '3306';
    $database = $config['database'] ?? 'chat';
    $username = $config['username'] ?? 'root';
    $password = $config['password'] ?? '';
    
    echo "Conectando ao banco: $host:$port/$database\n";
    
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Conexão estabelecida!\n\n";
    
    // Buscar todas as tools
    $stmt = $pdo->query("SELECT id, name, function_schema FROM ai_tools");
    $tools = $stmt->fetchAll();
    
    echo "Encontradas " . count($tools) . " tools\n\n";
    
    $fixed = 0;
    $errors = 0;
    $ok = 0;
    
    foreach ($tools as $tool) {
        echo "Tool #{$tool['id']}: {$tool['name']}... ";
        
        try {
            $schema = json_decode($tool['function_schema'], true);
            
            if (!$schema) {
                echo "SKIP (schema vazio ou inválido)\n";
                continue;
            }
            
            $needsFix = false;
            $fixedSchema = normalizeSchema($schema, $needsFix);
            
            if ($needsFix) {
                $newSchemaJson = json_encode($fixedSchema, JSON_UNESCAPED_UNICODE);
                
                $updateStmt = $pdo->prepare("UPDATE ai_tools SET function_schema = ? WHERE id = ?");
                $updateStmt->execute([$newSchemaJson, $tool['id']]);
                
                echo "<span class='fix'>✅ CORRIGIDO</span>\n";
                echo "   Antes:  " . htmlspecialchars(substr($tool['function_schema'], 0, 150)) . "...\n";
                echo "   Depois: " . htmlspecialchars(substr($newSchemaJson, 0, 150)) . "...\n";
                $fixed++;
            } else {
                echo "<span class='ok'>OK</span>\n";
                $ok++;
            }
        } catch (Exception $e) {
            echo "<span class='error'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</span>\n";
            $errors++;
        }
    }
    
    echo "\n=== Resultado ===\n";
    echo "<span class='fix'>✅ Corrigidas: $fixed</span>\n";
    echo "<span class='ok'>✔️ Já estavam OK: $ok</span>\n";
    if ($errors > 0) {
        echo "<span class='error'>❌ Erros: $errors</span>\n";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</span>\n";
    echo "<span class='error'>Trace: " . htmlspecialchars($e->getTraceAsString()) . "</span>\n";
}

echo '</pre>';
echo '<p><a href="view-conversation-debug.php" style="color:#58a6ff;">← Voltar ao Debug</a></p>';
echo '</body></html>';

/**
 * Normalizar schema e retornar se precisa de correção
 */
function normalizeSchema(array $schema, &$needsFix): array
{
    // Se é o formato wrapper {type: function, function: {...}}
    if (isset($schema['function'])) {
        $func = &$schema['function'];
    } else {
        $func = &$schema;
    }
    
    // Verificar e corrigir parameters
    if (isset($func['parameters'])) {
        $params = &$func['parameters'];
        
        // Corrigir type
        if (!isset($params['type'])) {
            $params['type'] = 'object';
            $needsFix = true;
        }
        
        // Corrigir properties: [] para properties: {}
        if (isset($params['properties'])) {
            if (is_array($params['properties']) && empty($params['properties'])) {
                // Array vazio - precisa virar objeto
                $params['properties'] = new \stdClass();
                $needsFix = true;
            }
        } else {
            $params['properties'] = new \stdClass();
            $needsFix = true;
        }
        
        // Adicionar required se não existir
        if (!isset($params['required'])) {
            $params['required'] = [];
            $needsFix = true;
        }
    } else {
        // Sem parameters - adicionar
        $func['parameters'] = [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => []
        ];
        $needsFix = true;
    }
    
    return $schema;
}
