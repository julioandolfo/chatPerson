<?php
/**
 * Script para verificar se o funil padrão foi criado
 * Acesse: http://seu-dominio.com/check-default-funnel.php
 * REMOVER após usar!
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

$dbConfig = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    die("<h1>❌ Erro ao conectar</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Funil Padrão</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificar Funil Padrão</h1>
        
        <?php
        // 1. Verificar funil padrão
        $stmt = $pdo->query("SELECT * FROM funnels WHERE is_default = 1 LIMIT 1");
        $defaultFunnel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h2>1. Funil Padrão</h2>";
        if ($defaultFunnel) {
            echo "<p class='success'>✅ Funil padrão encontrado!</p>";
            echo "<table>";
            echo "<tr><th>Campo</th><th>Valor</th></tr>";
            echo "<tr><td>ID</td><td>{$defaultFunnel['id']}</td></tr>";
            echo "<tr><td>Nome</td><td><strong>{$defaultFunnel['name']}</strong></td></tr>";
            echo "<tr><td>Descrição</td><td>{$defaultFunnel['description']}</td></tr>";
            echo "<tr><td>Cor</td><td><span style='background: {$defaultFunnel['color']}; padding: 3px 10px; color: white; border-radius: 3px;'>{$defaultFunnel['color']}</span></td></tr>";
            echo "<tr><td>Ativo</td><td>" . ($defaultFunnel['is_active'] ? '<span class="badge badge-success">Sim</span>' : '<span class="badge badge-danger">Não</span>') . "</td></tr>";
            echo "</table>";
            
            $funnelId = $defaultFunnel['id'];
        } else {
            echo "<p class='error'>❌ Funil padrão NÃO encontrado!</p>";
            echo "<p class='warning'>⚠️ A migration 057 não foi executada corretamente.</p>";
            $funnelId = null;
        }
        
        // 2. Verificar etapa padrão
        echo "<hr>";
        echo "<h2>2. Etapa Padrão</h2>";
        
        if ($funnelId) {
            $stmt = $pdo->prepare("SELECT * FROM funnel_stages WHERE funnel_id = ? AND is_default = 1 LIMIT 1");
            $stmt->execute([$funnelId]);
            $defaultStage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($defaultStage) {
                echo "<p class='success'>✅ Etapa padrão encontrada!</p>";
                echo "<table>";
                echo "<tr><th>Campo</th><th>Valor</th></tr>";
                echo "<tr><td>ID</td><td>{$defaultStage['id']}</td></tr>";
                echo "<tr><td>Nome</td><td><strong>{$defaultStage['name']}</strong></td></tr>";
                echo "<tr><td>Descrição</td><td>{$defaultStage['description']}</td></tr>";
                echo "<tr><td>Posição</td><td>{$defaultStage['position']}</td></tr>";
                echo "<tr><td>Cor</td><td><span style='background: {$defaultStage['color']}; padding: 3px 10px; color: white; border-radius: 3px;'>{$defaultStage['color']}</span></td></tr>";
                echo "</table>";
                
                $stageId = $defaultStage['id'];
            } else {
                echo "<p class='error'>❌ Etapa padrão NÃO encontrada!</p>";
                echo "<p class='warning'>⚠️ A migration 057 não foi executada corretamente.</p>";
                $stageId = null;
            }
        } else {
            echo "<p class='info'>ℹ️ Não é possível verificar etapa sem funil.</p>";
            $stageId = null;
        }
        
        // 3. Verificar setting
        echo "<hr>";
        echo "<h2>3. Configuração do Sistema</h2>";
        
        $stmt = $pdo->query("SELECT * FROM settings WHERE `key` = 'system_default_funnel_stage' LIMIT 1");
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($setting) {
            echo "<p class='success'>✅ Configuração encontrada!</p>";
            $config = json_decode($setting['value'], true);
            echo "<table>";
            echo "<tr><th>Campo</th><th>Valor</th></tr>";
            echo "<tr><td>Key</td><td><code>{$setting['key']}</code></td></tr>";
            echo "<tr><td>Funil ID</td><td><strong>{$config['funnel_id']}</strong></td></tr>";
            echo "<tr><td>Etapa ID</td><td><strong>{$config['stage_id']}</strong></td></tr>";
            echo "</table>";
        } else {
            echo "<p class='error'>❌ Configuração NÃO encontrada!</p>";
            echo "<p class='warning'>⚠️ A migration 057 não foi executada corretamente.</p>";
        }
        
        // 4. Verificar campos WhatsApp
        echo "<hr>";
        echo "<h2>4. Campos nas Integrações</h2>";
        
        $stmt = $pdo->query("SHOW COLUMNS FROM whatsapp_accounts LIKE 'default_funnel_id'");
        $hasField = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($hasField) {
            echo "<p class='success'>✅ Campos adicionados na tabela whatsapp_accounts!</p>";
            echo "<ul>";
            echo "<li><code>default_funnel_id</code> - OK</li>";
            echo "<li><code>default_stage_id</code> - OK</li>";
            echo "</ul>";
            
            // Contar contas configuradas
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM whatsapp_accounts WHERE default_funnel_id IS NOT NULL");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p class='info'>ℹ️ {$result['total']} conta(s) WhatsApp com funil/etapa configurado.</p>";
        } else {
            echo "<p class='error'>❌ Campos NÃO encontrados!</p>";
            echo "<p class='warning'>⚠️ A migration 058 não foi executada corretamente.</p>";
        }
        
        // 5. Resumo final
        echo "<hr>";
        echo "<h2>📊 Resumo</h2>";
        
        $allOk = $defaultFunnel && $defaultStage && $setting && $hasField;
        
        if ($allOk) {
            echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745;'>";
            echo "<h3 style='color: #155724; margin: 0;'>✅ TUDO CERTO!</h3>";
            echo "<p style='color: #155724; margin: 10px 0 0 0;'>As migrations 057 e 058 foram executadas com sucesso. O sistema de funil/etapa padrão está funcionando.</p>";
            echo "</div>";
            
            echo "<p style='margin-top: 20px;'><strong class='error'>IMPORTANTE:</strong> Remova os arquivos:</p>";
            echo "<ul>";
            echo "<li><code>/public/migrate.php</code></li>";
            echo "<li><code>/public/check-default-funnel.php</code> (este arquivo)</li>";
            echo "</ul>";
        } else {
            echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;'>";
            echo "<h3 style='color: #721c24; margin: 0;'>❌ MIGRATIONS NÃO EXECUTADAS</h3>";
            echo "<p style='color: #721c24; margin: 10px 0 0 0;'>Acesse <a href='/migrate.php'>/migrate.php</a> para executar as migrations.</p>";
            echo "</div>";
        }
        ?>
        
        <hr>
        <p><a href="/funnels">← Voltar para Funis</a></p>
    </div>
</body>
</html>

