<?php
/**
 * Script para executar migrations via web
 * 
 * ATENÇÃO: Remova este arquivo após executar!
 * Acesse: http://seu-dominio.com/migrate.php
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

// Verificar se já foi executado (segurança)
$lockFile = __DIR__ . '/../storage/migrations.lock';

// Carregar configurações
$dbConfig = require __DIR__ . '/../config/database.php';

// Conectar ao banco
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    
    // Usar database
    $pdo->exec("USE `{$dbConfig['database']}`");
    
} catch (PDOException $e) {
    die("<h1>❌ Erro ao conectar</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executar Migrations</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .migration { 
            margin: 10px 0;
            padding: 10px;
            border-left: 4px solid #28a745;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Executar Migrations</h1>
        
        <?php
        // Executar apenas migrations 057 e 058
        $migrationsToRun = [
            'database/migrations/057_create_default_funnel_and_stage.php',
            'database/migrations/058_add_default_funnel_stage_to_integrations.php'
        ];
        
        echo "<h2>Executando migrations específicas:</h2>";
        
        foreach ($migrationsToRun as $migrationPath) {
            $fullPath = __DIR__ . '/../' . $migrationPath;
            $filename = basename($fullPath);
            
            if (!file_exists($fullPath)) {
                echo "<div class='migration'>";
                echo "<span class='warning'>⚠️  {$filename}</span> - Arquivo não encontrado";
                echo "</div>";
                continue;
            }
            
            echo "<div class='migration'>";
            echo "<strong>📄 {$filename}</strong><br>";
            
            // Capturar output
            ob_start();
            
            try {
                require $fullPath;
                
                // Extrair nome da função
                $functionName = preg_replace('/^\d+_/', '', $filename);
                $functionName = str_replace('.php', '', $functionName);
                $functionName = 'up_' . $functionName;
                
                if (function_exists($functionName)) {
                    $functionName();
                    $output = ob_get_clean();
                    echo "<pre>" . htmlspecialchars($output) . "</pre>";
                    echo "<span class='success'>✅ Concluído!</span>";
                } else {
                    $output = ob_get_clean();
                    echo "<span class='error'>❌ Função '{$functionName}' não encontrada</span>";
                }
                
            } catch (Exception $e) {
                $output = ob_get_clean();
                echo "<pre>" . htmlspecialchars($output) . "</pre>";
                echo "<span class='error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</span>";
            }
            
            echo "</div>";
        }
        
        echo "<hr>";
        echo "<h2 class='success'>✅ Processo concluído!</h2>";
        echo "<p><strong class='error'>IMPORTANTE:</strong> Remova o arquivo <code>/public/migrate.php</code> agora!</p>";
        ?>
        
        <hr>
        <p><a href="/funnels">← Voltar para Funis</a></p>
    </div>
</body>
</html>

