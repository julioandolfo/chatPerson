<?php
/**
 * Script de diagnóstico SIMPLES do Coaching (sem autoload)
 */

// Ativar erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>🔍 Diagnóstico - Coaching em Tempo Real</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f5f5f5;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; padding: 10px; background: #ecf0f1; border-left: 4px solid #3498db; }
        .status { padding: 15px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table th, table td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        table th { background: #3498db; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico Completo - Coaching em Tempo Real</h1>
        
        <?php
        $errors = [];
        $warnings = [];
        
        // 1. Conectar ao banco
        echo "<h2>1️⃣ Conexão com Banco de Dados</h2>";
        
        try {
            $dbConfig = require __DIR__ . '/../config/database.php';
            
            $pdo = new PDO(
                "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['database'] . ";charset=" . ($dbConfig['charset'] ?? 'utf8mb4'),
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['options'] ?? []
            );
            
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "<div class='status success'>✅ Conectado ao banco de dados com sucesso!</div>";
            
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro ao conectar: " . htmlspecialchars($e->getMessage()) . "</div>";
            $errors[] = "Erro de conexão com banco";
            echo "</div></body></html>";
            exit;
        }
        
        // 2. Verificar configurações
        echo "<h2>2️⃣ Configurações no Banco de Dados</h2>";
        
        try {
            $stmt = $pdo->query("SELECT * FROM settings WHERE `key` = 'conversation_settings' ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($setting) {
                echo "<div class='status success'>✅ Configuração encontrada no banco (ID: {$setting['id']})</div>";
                
                $settings = json_decode($setting['value'], true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "<div class='status error'>❌ Erro ao decodificar JSON: " . json_last_error_msg() . "</div>";
                    $errors[] = "JSON inválido";
                } else {
                    echo "<div class='status success'>✅ JSON decodificado com sucesso</div>";
                    
                    if (isset($settings['realtime_coaching'])) {
                        $coaching = $settings['realtime_coaching'];
                        $enabled = $coaching['enabled'] ?? false;
                        
                        echo "<h3>Status do Coaching:</h3>";
                        if ($enabled) {
                            echo "<div class='status success'>";
                            echo "<h3 style='margin:0'>✅✅✅ COACHING ESTÁ HABILITADO!</h3>";
                            echo "</div>";
                        } else {
                            echo "<div class='status error'>";
                            echo "<h3 style='margin:0'>❌❌❌ COACHING ESTÁ DESABILITADO!</h3>";
                            echo "<p>👉 Vá em <a href='/settings?tab=conversations'>/settings?tab=conversations</a> e habilite o Coaching em Tempo Real</p>";
                            echo "</div>";
                            $errors[] = "Coaching desabilitado";
                        }
                        
                        echo "<h3>Configurações Atuais:</h3>";
                        echo "<table>";
                        echo "<tr><th>Configuração</th><th>Valor</th></tr>";
                        foreach ($coaching as $key => $value) {
                            $displayValue = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? 'Sim' : 'Não') : $value);
                            echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars($displayValue) . "</td></tr>";
                        }
                        echo "</table>";
                        
                    } else {
                        echo "<div class='status error'>❌ Seção 'realtime_coaching' NÃO existe nas configurações</div>";
                        $errors[] = "Seção realtime_coaching não encontrada";
                    }
                }
            } else {
                echo "<div class='status error'>❌ Configuração 'conversation_settings' não encontrada no banco</div>";
                $errors[] = "conversation_settings não encontrado";
            }
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
            $errors[] = $e->getMessage();
        }
        
        // 3. Verificar tabelas
        echo "<h2>3️⃣ Tabelas do Banco de Dados</h2>";
        
        $tables = [
            'coaching_queue' => 'Fila de análise de coaching',
            'realtime_coaching_hints' => 'Hints gerados pelo coaching',
            'messages' => 'Mensagens do sistema'
        ];
        
        foreach ($tables as $table => $description) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM `{$table}`");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    echo "<div class='status success'>✅ Tabela <code>{$table}</code> existe ({$count} registros)</div>";
                    echo "<div class='info'>{$description}</div>";
                } else {
                    echo "<div class='status error'>❌ Tabela <code>{$table}</code> NÃO existe</div>";
                    $errors[] = "Tabela {$table} não existe - Execute as migrations";
                }
            } catch (Exception $e) {
                echo "<div class='status error'>❌ Erro ao verificar tabela {$table}: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        // 4. Verificar mensagens recentes
        echo "<h2>4️⃣ Mensagens Recentes de Clientes</h2>";
        
        try {
            $stmt = $pdo->query("SELECT id, conversation_id, sender_type, LEFT(content, 100) as content, created_at 
                                FROM messages 
                                WHERE sender_type = 'contact'
                                ORDER BY created_at DESC 
                                LIMIT 5");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($messages) > 0) {
                echo "<div class='status success'>✅ Encontradas " . count($messages) . " mensagens recentes de clientes</div>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Conversa</th><th>Conteúdo</th><th>Data</th></tr>";
                foreach ($messages as $msg) {
                    echo "<tr>";
                    echo "<td>{$msg['id']}</td>";
                    echo "<td>{$msg['conversation_id']}</td>";
                    echo "<td>" . htmlspecialchars($msg['content']) . "</td>";
                    echo "<td>{$msg['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='status warning'>⚠️ Nenhuma mensagem de cliente encontrada</div>";
                echo "<div class='info'>Envie uma mensagem de teste do WhatsApp para testar o coaching</div>";
                $warnings[] = "Nenhuma mensagem para testar";
            }
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // 5. Verificar arquivo de log
        echo "<h2>5️⃣ Arquivo de Log</h2>";
        
        $logFile = __DIR__ . '/../logs/coaching.log';
        
        if (file_exists($logFile)) {
            $size = filesize($logFile);
            $lines = count(file($logFile));
            
            echo "<div class='status success'>✅ Arquivo de log existe</div>";
            echo "<div class='info'>Tamanho: " . number_format($size) . " bytes | Linhas: {$lines}</div>";
            
            if ($size > 100) {
                echo "<h3>Últimas 15 linhas do log:</h3>";
                $content = file($logFile);
                $lastLines = array_slice($content, -15);
                echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
            } else {
                echo "<div class='status warning'>⚠️ Log está vazio ou muito pequeno - Nenhuma atividade registrada ainda</div>";
                echo "<div class='info'>O log será preenchido quando uma mensagem de cliente for recebida e o coaching estiver habilitado</div>";
            }
        } else {
            echo "<div class='status error'>❌ Arquivo de log não existe: {$logFile}</div>";
            echo "<div class='info'>Execute: <code>touch logs/coaching.log</code></div>";
            $errors[] = "Arquivo coaching.log não existe";
        }
        
        // 6. Verificar API Key
        echo "<h2>6️⃣ API Key OpenAI</h2>";
        
        try {
            $stmt = $pdo->query("SELECT value FROM settings WHERE `key` = 'openai_api_key' LIMIT 1");
            $apiKey = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($apiKey && !empty($apiKey['value'])) {
                $keyPreview = substr($apiKey['value'], 0, 10) . '...' . substr($apiKey['value'], -4);
                echo "<div class='status success'>✅ API Key configurada: {$keyPreview}</div>";
            } else {
                echo "<div class='status error'>❌ API Key da OpenAI NÃO configurada</div>";
                echo "<div class='info'>Configure em: <a href='/settings?tab=general'>/settings?tab=general</a></div>";
                $errors[] = "API Key não configurada";
            }
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // 7. Verificar fila de coaching
        echo "<h2>7️⃣ Fila de Coaching</h2>";
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM coaching_queue WHERE status = 'pending'");
            $pending = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM coaching_queue WHERE status = 'completed'");
            $completed = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM coaching_queue WHERE status = 'failed'");
            $failed = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo "<table>";
            echo "<tr><th>Status</th><th>Quantidade</th></tr>";
            echo "<tr><td>Pendentes</td><td>{$pending}</td></tr>";
            echo "<tr><td>Completados</td><td>{$completed}</td></tr>";
            echo "<tr><td>Falhados</td><td>{$failed}</td></tr>";
            echo "</table>";
            
            if ($pending > 0) {
                echo "<div class='status warning'>⚠️ Há {$pending} itens pendentes na fila</div>";
                echo "<div class='info'>Execute o worker de processamento: <code>php public/scripts/coaching-worker.php</code></div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // 8. Verificar hints gerados
        echo "<h2>8️⃣ Hints Gerados</h2>";
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM realtime_coaching_hints");
            $totalHints = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($totalHints > 0) {
                echo "<div class='status success'>✅ {$totalHints} hints já foram gerados!</div>";
                
                // Mostrar últimos hints
                $stmt = $pdo->query("SELECT id, conversation_id, agent_id, hint_type, hint_text, created_at 
                                    FROM realtime_coaching_hints 
                                    ORDER BY created_at DESC 
                                    LIMIT 5");
                $hints = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h3>Últimos 5 hints:</h3>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Tipo</th><th>Texto</th><th>Data</th></tr>";
                foreach ($hints as $hint) {
                    echo "<tr>";
                    echo "<td>{$hint['id']}</td>";
                    echo "<td>{$hint['hint_type']}</td>";
                    echo "<td>" . htmlspecialchars(substr($hint['hint_text'], 0, 80)) . "</td>";
                    echo "<td>{$hint['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='status warning'>⚠️ Nenhum hint foi gerado ainda</div>";
                echo "<div class='info'>Hints serão gerados quando mensagens de clientes forem recebidas e analisadas</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // Resumo Final
        echo "<h2>📊 Resumo do Diagnóstico</h2>";
        
        if (count($errors) === 0) {
            echo "<div class='status success'>";
            echo "<h3>✅ SISTEMA OK!</h3>";
            echo "<p>O Coaching em Tempo Real está configurado corretamente.</p>";
            echo "<p><strong>Próximos passos:</strong></p>";
            echo "<ul>";
            echo "<li>Envie uma mensagem de teste do WhatsApp</li>";
            echo "<li>Verifique os logs em <a href='/view-all-logs.php'>/view-all-logs.php</a></li>";
            echo "<li>Observe os hints sendo gerados</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='status error'>";
            echo "<h3>❌ PROBLEMAS ENCONTRADOS:</h3>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
            echo "<p><strong>Corrija os problemas acima antes de continuar.</strong></p>";
            echo "</div>";
        }
        
        if (count($warnings) > 0) {
            echo "<div class='status warning'>";
            echo "<h3>⚠️ AVISOS:</h3>";
            echo "<ul>";
            foreach ($warnings as $warning) {
                echo "<li>" . htmlspecialchars($warning) . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        ?>
        
        <h2>🔧 Ações</h2>
        <div style="margin: 20px 0;">
            <a href="/view-all-logs.php" class="btn" target="_blank">📋 Ver Logs</a>
            <a href="/settings?tab=conversations" class="btn">⚙️ Configurações</a>
            <a href="/conversations" class="btn">💬 Conversas</a>
            <a href="?" class="btn">🔄 Recarregar</a>
        </div>
    </div>
</body>
</html>
