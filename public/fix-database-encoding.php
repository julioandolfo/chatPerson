<?php
/**
 * Script para corrigir encoding UTF-8 no banco de dados
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<pre>";
echo "=== CORREÇÃO DE ENCODING NO BANCO DE DADOS ===\n\n";

$db = App\Helpers\Database::getInstance();

// Mapeamento de caracteres com encoding incorreto
$replacements = [
    'Ã§' => 'ç',
    'Ã£' => 'ã',
    'Ã¡' => 'á',
    'Ã ' => 'à',
    'Ã¢' => 'â',
    'Ãª' => 'ê',
    'Ã©' => 'é',
    'Ã¨' => 'è',
    'Ã¬' => 'ì',
    'Ã­' => 'í',
    'Ã³' => 'ó',
    'Ã´' => 'ô',
    'Ã²' => 'ò',
    'Ãº' => 'ú',
    'Ã¹' => 'ù',
    'Ã»' => 'û',
    'Ã' => 'Á',
    'Ã‰' => 'É',
    'Ã' => 'Í',
    'Ã"' => 'Ó',
    'Ãš' => 'Ú',
    'Ã‡' => 'Ç',
    'Ã' => 'Ã',
    'Ãµ' => 'Õ',
    'OÇø' => 'Oficial',
    'Oço' => 'Oficial',
    'eÃ' => 'há',
    'HÃ¡' => 'Há',
    
    // Números e unidades de tempo
    '8min' => '8min',
];

// Tabelas e campos a corrigir
$updates = [
    'whatsapp_accounts' => ['name', 'phone'],
    'integration_accounts' => ['name', 'username'],
    'contacts' => ['name', 'phone'],
    'conversations' => [],
    'messages' => ['content'],
    'departments' => ['name', 'description'],
    'tags' => ['name'],
    'canned_responses' => ['title', 'content'],
    'ai_agents' => ['name', 'instructions'],
    'funnels' => ['name', 'description'],
    'funnel_stages' => ['name', 'description'],
];

$totalUpdates = 0;

foreach ($updates as $table => $fields) {
    echo "\n📋 Tabela: $table\n";
    echo str_repeat("-", 50) . "\n";
    
    if (empty($fields)) {
        echo "  ⏭️  Pulando (sem campos de texto)\n";
        continue;
    }
    
    try {
        // Buscar todos os registros
        $stmt = $db->query("SELECT * FROM $table");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($records)) {
            echo "  ℹ️  Tabela vazia\n";
            continue;
        }
        
        echo "  📊 Total de registros: " . count($records) . "\n";
        
        $updatedInTable = 0;
        
        foreach ($records as $record) {
            $id = $record['id'];
            $updates = [];
            $changed = false;
            
            foreach ($fields as $field) {
                if (!isset($record[$field]) || empty($record[$field])) {
                    continue;
                }
                
                $original = $record[$field];
                $fixed = $original;
                
                // Aplicar todas as correções
                foreach ($replacements as $wrong => $correct) {
                    $fixed = str_replace($wrong, $correct, $fixed);
                }
                
                if ($fixed !== $original) {
                    $updates[$field] = $fixed;
                    $changed = true;
                    echo "  ✓ ID $id - Campo '$field': '$original' → '$fixed'\n";
                }
            }
            
            if ($changed) {
                // Montar UPDATE
                $setParts = [];
                $params = [];
                
                foreach ($updates as $field => $value) {
                    $setParts[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
                
                $params[':id'] = $id;
                
                $sql = "UPDATE $table SET " . implode(', ', $setParts) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $updatedInTable++;
                $totalUpdates++;
            }
        }
        
        if ($updatedInTable > 0) {
            echo "  ✅ Atualizados: $updatedInTable registros\n";
        } else {
            echo "  ✓ Nenhuma correção necessária\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Erro: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ TOTAL DE REGISTROS ATUALIZADOS: $totalUpdates\n";
echo str_repeat("=", 50) . "\n";

echo "</pre>";

