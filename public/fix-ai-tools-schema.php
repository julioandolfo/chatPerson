<?php
/**
 * Script para corrigir schemas de AI Tools no banco de dados
 * Corrige problemas como properties: [] ao invés de properties: {}
 * 
 * Acesse via: https://seu-dominio.com/fix-ai-tools-schema.php
 */

// Carregar apenas o autoloader e config, sem passar pelo Router
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Helpers\Database;

// Se for acesso web, mostrar como HTML
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Fix AI Tools Schema</title>';
    echo '<style>body{font-family:monospace;background:#0d1117;color:#c9d1d9;padding:20px;}';
    echo 'h1{color:#58a6ff;}.ok{color:#3fb950;}.fix{color:#f0883e;}.error{color:#f85149;}</style>';
    echo '</head><body>';
    echo '<h1>🔧 Corrigindo Schemas de AI Tools</h1><pre>';
}

function output($msg, $class = '') {
    global $isWeb;
    if ($isWeb && $class) {
        echo "<span class='$class'>$msg</span>\n";
    } else {
        echo $msg . "\n";
    }
}

output("=== Corrigindo schemas de AI Tools ===\n");

try {
    // Buscar todas as tools
    $tools = Database::fetchAll("SELECT id, name, function_schema FROM ai_tools");
    
    output("Encontradas " . count($tools) . " tools\n");
    
    $fixed = 0;
    $errors = 0;
    $ok = 0;
    
    foreach ($tools as $tool) {
        $msg = "Tool #{$tool['id']}: {$tool['name']}... ";
        
        try {
            $schema = json_decode($tool['function_schema'], true);
            
            if (!$schema) {
                output($msg . "SKIP (schema vazio ou inválido)");
                continue;
            }
            
            $needsFix = false;
            $fixedSchema = normalizeSchema($schema, $needsFix);
            
            if ($needsFix) {
                $newSchemaJson = json_encode($fixedSchema, JSON_UNESCAPED_UNICODE);
                
                $stmt = Database::getInstance()->prepare("UPDATE ai_tools SET function_schema = ? WHERE id = ?");
                $stmt->execute([$newSchemaJson, $tool['id']]);
                
                output($msg . "✅ CORRIGIDO", 'fix');
                output("   Antes:  " . substr($tool['function_schema'], 0, 100) . "...");
                output("   Depois: " . substr($newSchemaJson, 0, 100) . "...");
                $fixed++;
            } else {
                output($msg . "OK (não precisa correção)", 'ok');
                $ok++;
            }
        } catch (Exception $e) {
            output($msg . "❌ ERRO: " . $e->getMessage(), 'error');
            $errors++;
        }
    }
    
    output("\n=== Resultado ===");
    output("✅ Corrigidas: $fixed", $fixed > 0 ? 'fix' : '');
    output("✔️ Já estavam OK: $ok", 'ok');
    output("❌ Erros: $errors", $errors > 0 ? 'error' : '');
    
} catch (Exception $e) {
    output("❌ ERRO FATAL: " . $e->getMessage(), 'error');
}

if ($isWeb) {
    echo '</pre>';
    echo '<p><a href="view-conversation-debug.php" style="color:#58a6ff;">← Voltar ao Debug</a></p>';
    echo '</body></html>';
}

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

