<?php
/**
 * Script para corrigir schemas de AI Tools no banco de dados
 * Corrige problemas como properties: [] ao invés de properties: {}
 */

require_once __DIR__ . '/../public/index.php';

use App\Helpers\Database;

echo "=== Corrigindo schemas de AI Tools ===\n\n";

// Buscar todas as tools
$tools = Database::fetchAll("SELECT id, name, function_schema FROM ai_tools");

echo "Encontradas " . count($tools) . " tools\n\n";

$fixed = 0;
$errors = 0;

foreach ($tools as $tool) {
    echo "Processando tool #{$tool['id']}: {$tool['name']}... ";
    
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
            
            $stmt = Database::getInstance()->prepare("UPDATE ai_tools SET function_schema = ? WHERE id = ?");
            $stmt->execute([$newSchemaJson, $tool['id']]);
            
            echo "✅ CORRIGIDO\n";
            echo "   Antes:  " . substr($tool['function_schema'], 0, 100) . "...\n";
            echo "   Depois: " . substr($newSchemaJson, 0, 100) . "...\n";
            $fixed++;
        } else {
            echo "OK (não precisa correção)\n";
        }
    } catch (Exception $e) {
        echo "❌ ERRO: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Resultado ===\n";
echo "✅ Corrigidas: $fixed\n";
echo "❌ Erros: $errors\n";
echo "➖ Não precisavam correção: " . (count($tools) - $fixed - $errors) . "\n";

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

