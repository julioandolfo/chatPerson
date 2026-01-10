<?php
/**
 * Script para executar migration de Coaching em Tempo Real
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>🔧 Executar Migration - Coaching</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Executar Migration - Coaching em Tempo Real</h1>
        
        <?php
        // 1. Conectar ao banco
        try {
            $dbConfig = require __DIR__ . '/../config/database.php';
            
            $pdo = new PDO(
                "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['database'] . ";charset=" . ($dbConfig['charset'] ?? 'utf8mb4'),
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['options'] ?? []
            );
            
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "<div class='success'>✅ Conectado ao banco de dados</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Erro ao conectar: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "</div></body></html>";
            exit;
        }
        
        // 2. Executar migration
        echo "<h2>📝 Executando Migration...</h2>";
        
        try {
            // Criar tabela realtime_coaching_hints
            $sql1 = "CREATE TABLE IF NOT EXISTS realtime_coaching_hints (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                agent_id INT NOT NULL,
                message_id INT DEFAULT NULL,
                hint_type VARCHAR(50) NOT NULL,
                hint_text TEXT NOT NULL,
                suggestions JSON DEFAULT NULL,
                model_used VARCHAR(50) DEFAULT NULL,
                tokens_used INT DEFAULT 0,
                cost DECIMAL(10,6) DEFAULT 0,
                viewed_at TIMESTAMP NULL DEFAULT NULL,
                feedback VARCHAR(20) DEFAULT NULL COMMENT 'helpful, not_helpful',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
                INDEX idx_conversation (conversation_id),
                INDEX idx_agent (agent_id),
                INDEX idx_message (message_id),
                INDEX idx_created_at (created_at),
                INDEX idx_hint_type (hint_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $pdo->exec($sql1);
            echo "<div class='success'>✅ Tabela 'realtime_coaching_hints' criada com sucesso!</div>";
            
            // Criar tabela de cache
            $sql2 = "CREATE TABLE IF NOT EXISTS realtime_coaching_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_hash VARCHAR(64) NOT NULL UNIQUE,
                hint_type VARCHAR(50) NOT NULL,
                hint_text TEXT NOT NULL,
                suggestions JSON DEFAULT NULL,
                model_used VARCHAR(50) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                INDEX idx_hash (message_hash),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $pdo->exec($sql2);
            echo "<div class='success'>✅ Tabela 'realtime_coaching_cache' criada com sucesso!</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Erro ao criar tabelas: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        
        // 3. Criar arquivo de log
        echo "<h2>📄 Criando Arquivo de Log...</h2>";
        
        $logFile = __DIR__ . '/../logs/coaching.log';
        $logsDir = dirname($logFile);
        
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
            echo "<div class='info'>📁 Diretório 'logs' criado</div>";
        }
        
        if (!file_exists($logFile)) {
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[{$timestamp}] 🎯 Sistema de Coaching em Tempo Real - Log iniciado\n");
            echo "<div class='success'>✅ Arquivo de log criado: {$logFile}</div>";
        } else {
            echo "<div class='info'>ℹ️ Arquivo de log já existe</div>";
        }
        
        // 4. Verificar tudo
        echo "<h2>🔍 Verificando Instalação...</h2>";
        
        try {
            // Verificar tabela hints
            $stmt = $pdo->query("SHOW TABLES LIKE 'realtime_coaching_hints'");
            if ($stmt->rowCount() > 0) {
                echo "<div class='success'>✅ Tabela 'realtime_coaching_hints' existe</div>";
                
                // Contar registros
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM realtime_coaching_hints");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                echo "<div class='info'>📊 Hints na tabela: {$count}</div>";
            } else {
                echo "<div class='error'>❌ Tabela 'realtime_coaching_hints' NÃO foi criada</div>";
            }
            
            // Verificar tabela cache
            $stmt = $pdo->query("SHOW TABLES LIKE 'realtime_coaching_cache'");
            if ($stmt->rowCount() > 0) {
                echo "<div class='success'>✅ Tabela 'realtime_coaching_cache' existe</div>";
            }
            
            // Verificar tabela queue
            $stmt = $pdo->query("SHOW TABLES LIKE 'coaching_queue'");
            if ($stmt->rowCount() > 0) {
                echo "<div class='success'>✅ Tabela 'coaching_queue' existe</div>";
            } else {
                echo "<div class='error'>❌ Tabela 'coaching_queue' não existe - Execute migration 018</div>";
            }
            
            // Verificar log
            if (file_exists($logFile)) {
                echo "<div class='success'>✅ Arquivo de log existe</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Erro na verificação: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        ?>
        
        <h2>✅ Instalação Completa!</h2>
        
        <div class="success">
            <h3>🎉 Coaching em Tempo Real está pronto!</h3>
            <p><strong>Próximos passos:</strong></p>
            <ol>
                <li>Envie uma mensagem de teste do WhatsApp</li>
                <li>Verifique os logs</li>
                <li>Observe os hints sendo gerados</li>
            </ol>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="/debug-coaching-simple.php" class="btn">🔍 Verificar Diagnóstico</a>
            <a href="/view-all-logs.php" class="btn">📋 Ver Logs</a>
            <a href="/conversations" class="btn">💬 Ir para Conversas</a>
        </div>
    </div>
</body>
</html>
