<?php
/**
 * Executar APENAS migrations 057 e 058 (Funil Padrão)
 * 
 * Acesse: http://seu-dominio.com/run-default-funnel.php
 * REMOVER após usar!
 */

// Carregar autoload
require_once __DIR__ . '/../app/Helpers/autoload.php';

// Carregar configurações
$dbConfig = require __DIR__ . '/../config/database.php';

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
    <title>Criar Funil Padrão</title>
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
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border-left: 4px solid #007bff;
        }
        .step {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        .step h3 {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Criar Funil e Etapa Padrão</h1>
        
        <?php
        echo "<div class='step'>";
        echo "<h3>Migration 057: Criar Funil e Etapa Padrão</h3>";
        
        try {
            // Verificar se já existe
            $stmt = $pdo->query("SELECT id, name FROM funnels WHERE is_default = 1 LIMIT 1");
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                echo "<p class='warning'>⚠️ Funil padrão já existe: <strong>{$existing['name']}</strong> (ID: {$existing['id']})</p>";
            } else {
                // Executar migration 057
                require __DIR__ . '/../database/migrations/057_create_default_funnel_and_stage.php';
                
                ob_start();
                up_create_default_funnel_and_stage();
                $output = ob_get_clean();
                
                echo "<pre>" . htmlspecialchars($output) . "</pre>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h3>Migration 058: Adicionar Campos nas Integrações</h3>";
        
        try {
            // Verificar se campos já existem
            $stmt = $pdo->query("SHOW COLUMNS FROM whatsapp_accounts LIKE 'default_funnel_id'");
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                echo "<p class='warning'>⚠️ Campos já existem na tabela whatsapp_accounts</p>";
            } else {
                // Executar migration 058
                require __DIR__ . '/../database/migrations/058_add_default_funnel_stage_to_integrations.php';
                
                ob_start();
                up_add_default_funnel_stage_to_integrations();
                $output = ob_get_clean();
                
                echo "<pre>" . htmlspecialchars($output) . "</pre>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        
        echo "</div>";
        
        // Verificar resultado final
        echo "<hr>";
        echo "<h2>📊 Verificação Final</h2>";
        
        $stmt = $pdo->query("SELECT * FROM funnels WHERE is_default = 1 LIMIT 1");
        $funnel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($funnel) {
            echo "<div class='step' style='border-left-color: #28a745;'>";
            echo "<h3 class='success'>✅ SUCESSO!</h3>";
            echo "<p><strong>Funil Padrão:</strong> {$funnel['name']} (ID: {$funnel['id']})</p>";
            echo "<p><strong>Status:</strong> {$funnel['status']}</p>";
            echo "<p><strong>Cor:</strong> <span style='background: {$funnel['color']}; padding: 3px 10px; color: white; border-radius: 3px;'>{$funnel['color']}</span></p>";
            
            // Buscar etapa
            $stmt = $pdo->prepare("SELECT * FROM funnel_stages WHERE funnel_id = ? AND is_default = 1 LIMIT 1");
            $stmt->execute([$funnel['id']]);
            $stage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stage) {
                echo "<p><strong>Etapa Padrão:</strong> {$stage['name']} (ID: {$stage['id']})</p>";
                echo "<p><strong>Posição:</strong> {$stage['position']}</p>";
            }
            
            echo "<hr>";
            echo "<p class='success' style='font-size: 18px;'><strong>🎉 O funil padrão está criado e deve aparecer em:</strong></p>";
            echo "<ul>";
            echo "<li>✅ /funnels (Lista de funis)</li>";
            echo "<li>✅ /funnels/kanban (Kanban)</li>";
            echo "<li>✅ /integrations/whatsapp (Configuração de canais)</li>";
            echo "</ul>";
            
            echo "<hr>";
            echo "<p class='error' style='font-size: 16px;'><strong>⚠️ IMPORTANTE:</strong></p>";
            echo "<p>Remova os arquivos temporários agora:</p>";
            echo "<ul>";
            echo "<li><code>/public/run-default-funnel.php</code> (este arquivo)</li>";
            echo "<li><code>/public/migrate.php</code></li>";
            echo "<li><code>/public/check-default-funnel.php</code></li>";
            echo "</ul>";
            
            echo "</div>";
        } else {
            echo "<div class='step' style='border-left-color: #dc3545;'>";
            echo "<h3 class='error'>❌ FALHOU</h3>";
            echo "<p>O funil padrão não foi criado. Verifique os erros acima.</p>";
            echo "</div>";
        }
        ?>
        
        <hr>
        <div style="margin-top: 20px;">
            <a href="/funnels" class="btn" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">← Ver Funis</a>
            <a href="/funnels/kanban" class="btn" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">→ Ver Kanban</a>
        </div>
    </div>
</body>
</html>

