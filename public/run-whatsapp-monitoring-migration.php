<?php
/**
 * Script para executar a migration de monitoramento de conexão WhatsApp
 * 
 * Acesse: http://seu-dominio.com/run-whatsapp-monitoring-migration.php
 * REMOVA APÓS EXECUTAR!
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

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
    <title>Executar Migration - Monitoramento WhatsApp</title>
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
        <h1>🚀 Migration: Monitoramento de Conexão WhatsApp</h1>
        
        <?php
        echo "<h2>Executando migrations:</h2>";
        
        $migrations = [
            '127_add_connection_monitoring_fields.php' => 'up_connection_monitoring_fields'
        ];
        
        foreach ($migrations as $file => $function) {
            $fullPath = __DIR__ . '/../database/migrations/' . $file;
            
            echo "<div class='migration'>";
            echo "<strong>📄 {$file}</strong><br>";
            
            if (!file_exists($fullPath)) {
                echo "<span class='error'>❌ Arquivo não encontrado</span>";
                echo "</div>";
                continue;
            }
            
            ob_start();
            
            try {
                require $fullPath;
                
                if (function_exists($function)) {
                    $function();
                    $output = ob_get_clean();
                    echo "<pre>" . htmlspecialchars($output) . "</pre>";
                    echo "<span class='success'>✅ Concluído!</span>";
                } else {
                    $output = ob_get_clean();
                    echo "<span class='error'>❌ Função '{$function}' não encontrada</span>";
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
        echo "<p><strong class='error'>IMPORTANTE:</strong> Remova este arquivo após usar!</p>";
        ?>
        
        <hr>
        <p><a href="/integrations/whatsapp">← Voltar para WhatsApp</a></p>
    </div>
</body>
</html>
