<?php
/**
 * Script para migrar sons customizados de storage/sounds para public/assets/sounds
 * Execute este script uma vez após o deploy para mover os arquivos existentes
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Helpers\Database;

echo "<h2>Migração de Sons Customizados</h2>\n";
echo "<pre>\n";

$baseDir = dirname(__DIR__);
$oldDir = $baseDir . '/storage/sounds';
$newDir = $baseDir . '/public/assets/sounds';

// Verificar se diretório antigo existe
if (!is_dir($oldDir)) {
    echo "✅ Diretório storage/sounds não existe ou já foi migrado.\n";
    echo "</pre>";
    exit;
}

// Listar arquivos no diretório antigo
$files = glob($oldDir . '/*.*');

if (empty($files)) {
    echo "✅ Nenhum arquivo para migrar.\n";
    echo "</pre>";
    exit;
}

echo "📁 Encontrados " . count($files) . " arquivo(s) para migrar.\n\n";

$migrated = 0;
$errors = 0;

foreach ($files as $file) {
    $filename = basename($file);
    $newPath = $newDir . '/' . $filename;
    
    echo "📦 Processando: $filename\n";
    
    // Verificar se já existe no destino
    if (file_exists($newPath)) {
        echo "   ⚠️ Arquivo já existe no destino, pulando...\n";
        continue;
    }
    
    // Mover arquivo
    if (rename($file, $newPath)) {
        echo "   ✅ Movido com sucesso!\n";
        
        // Atualizar registro no banco de dados
        $sql = "UPDATE custom_sounds SET file_path = ? WHERE filename = ?";
        try {
            Database::query($sql, [$newPath, $filename]);
            echo "   ✅ Banco de dados atualizado!\n";
        } catch (Exception $e) {
            echo "   ⚠️ Erro ao atualizar banco: " . $e->getMessage() . "\n";
        }
        
        $migrated++;
    } else {
        echo "   ❌ Erro ao mover arquivo!\n";
        $errors++;
    }
    
    echo "\n";
}

echo "=================================\n";
echo "✅ Migrados: $migrated arquivo(s)\n";
echo "❌ Erros: $errors\n";

// Remover diretório antigo se estiver vazio
$remainingFiles = glob($oldDir . '/*.*');
if (empty($remainingFiles)) {
    if (rmdir($oldDir)) {
        echo "\n🗑️ Diretório storage/sounds removido (estava vazio).\n";
    }
}

echo "</pre>\n";
echo "<p><strong>Migração concluída!</strong> Você pode excluir este arquivo.</p>";

