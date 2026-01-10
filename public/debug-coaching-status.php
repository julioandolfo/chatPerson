<?php
/**
 * Script de diagnóstico completo do Coaching em Tempo Real
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>🔍 Diagnóstico de Coaching</title>
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
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
            padding: 10px;
            background: #ecf0f1;
            border-left: 4px solid #3498db;
        }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-weight: bold;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #3498db;
            color: white;
        }
        table tr:hover {
            background: #f5f5f5;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico Completo - Coaching em Tempo Real</h1>
        
        <?php
        $errors = [];
        $warnings = [];
        $success = [];
        
        // 1. Verificar se classes existem
        echo "<h2>1️⃣ Verificação de Classes</h2>";
        
        $classes = [
            'App\Services\RealtimeCoachingService',
            'App\Listeners\MessageReceivedListener',
            'App\Services\ConversationSettingsService',
            'App\Models\Message',
        ];
        
        foreach ($classes as $class) {
            if (class_exists($class)) {
                echo "<div class='status success'>✅ Classe existe: <code>{$class}</code></div>";
            } else {
                echo "<div class='status error'>❌ Classe NÃO existe: <code>{$class}</code></div>";
                $errors[] = "Classe {$class} não encontrada";
            }
        }
        
        // 2. Verificar configurações no banco
        echo "<h2>2️⃣ Configurações no Banco de Dados</h2>";
        
        try {
            $db = \App\Helpers\Database::getInstance();
            $stmt = $db->query("SELECT * FROM settings WHERE `key` = 'conversation_settings' ORDER BY id DESC LIMIT 1");
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($setting) {
                echo "<div class='status success'>✅ Configuração encontrada no banco (ID: {$setting['id']})</div>";
                
                $settings = json_decode($setting['value'], true);
                if (isset($settings['realtime_coaching'])) {
                    $coaching = $settings['realtime_coaching'];
                    
                    echo "<h3>Configurações de Coaching:</h3>";
                    echo "<table>";
                    echo "<tr><th>Configuração</th><th>Valor</th></tr>";
                    
                    $enabled = $coaching['enabled'] ?? false;
                    $enabledStr = $enabled ? '<span style="color:green">✅ HABILITADO</span>' : '<span style="color:red">❌ DESABILITADO</span>';
                    echo "<tr><td><strong>Status</strong></td><td>{$enabledStr}</td></tr>";
                    
                    foreach ($coaching as $key => $value) {
                        if ($key === 'enabled') continue;
                        
                        $displayValue = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? 'Sim' : 'Não') : $value);
                        echo "<tr><td>{$key}</td><td>{$displayValue}</td></tr>";
                    }
                    echo "</table>";
                    
                    if (!$enabled) {
                        echo "<div class='status error'>❌ COACHING ESTÁ DESABILITADO! Habilite em /settings?tab=conversations</div>";
                        $errors[] = "Coaching desabilitado";
                    } else {
                        echo "<div class='status success'>✅ Coaching está HABILITADO</div>";
                    }
                } else {
                    echo "<div class='status error'>❌ Seção 'realtime_coaching' não existe nas configurações</div>";
                    $errors[] = "Seção realtime_coaching não encontrada";
                }
            } else {
                echo "<div class='status error'>❌ Configuração 'conversation_settings' não encontrada no banco</div>";
                $errors[] = "conversation_settings não encontrado";
            }
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro ao buscar configurações: " . htmlspecialchars($e->getMessage()) . "</div>";
            $errors[] = $e->getMessage();
        }
        
        // 3. Verificar tabelas necessárias
        echo "<h2>3️⃣ Tabelas do Banco de Dados</h2>";
        
        $tables = [
            'coaching_queue' => 'Fila de análise de coaching',
            'realtime_coaching_hints' => 'Hints gerados pelo coaching',
        ];
        
        foreach ($tables as $table => $description) {
            try {
                $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    // Contar registros
                    $stmt = $db->query("SELECT COUNT(*) as total FROM `{$table}`");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    echo "<div class='status success'>✅ Tabela <code>{$table}</code> existe ({$count} registros)</div>";
                    echo "<div class='info'>{$description}</div>";
                } else {
                    echo "<div class='status error'>❌ Tabela <code>{$table}</code> NÃO existe</div>";
                    $errors[] = "Tabela {$table} não existe";
                }
            } catch (Exception $e) {
                echo "<div class='status error'>❌ Erro ao verificar tabela {$table}: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        // 4. Verificar mensagens recentes
        echo "<h2>4️⃣ Mensagens Recentes</h2>";
        
        try {
            $stmt = $db->query("SELECT id, conversation_id, sender_type, content, created_at 
                               FROM messages 
                               WHERE sender_type = 'contact'
                               ORDER BY created_at DESC 
                               LIMIT 5");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($messages) > 0) {
                echo "<div class='status success'>✅ Encontradas " . count($messages) . " mensagens de clientes recentes</div>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Conversa</th><th>Conteúdo</th><th>Data</th></tr>";
                foreach ($messages as $msg) {
                    $content = mb_substr($msg['content'], 0, 50) . (mb_strlen($msg['content']) > 50 ? '...' : '');
                    echo "<tr>";
                    echo "<td>{$msg['id']}</td>";
                    echo "<td>{$msg['conversation_id']}</td>";
                    echo "<td>" . htmlspecialchars($content) . "</td>";
                    echo "<td>{$msg['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='status warning'>⚠️ Nenhuma mensagem de cliente encontrada</div>";
                $warnings[] = "Nenhuma mensagem para testar";
            }
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro ao buscar mensagens: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // 5. Testar MessageReceivedListener
        echo "<h2>5️⃣ Teste do MessageReceivedListener</h2>";
        
        if (count($messages) > 0) {
            $testMessage = $messages[0];
            echo "<div class='info'>Testando com mensagem ID: {$testMessage['id']}</div>";
            
            try {
                // Chamar o listener
                \App\Listeners\MessageReceivedListener::handle($testMessage['id']);
                echo "<div class='status success'>✅ MessageReceivedListener executado sem erros</div>";
                echo "<div class='info'>Verifique o log em: <a href='/view-all-logs.php' target='_blank'>/view-all-logs.php</a></div>";
            } catch (Exception $e) {
                echo "<div class='status error'>❌ Erro ao executar MessageReceivedListener: " . htmlspecialchars($e->getMessage()) . "</div>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                $errors[] = "Erro no listener: " . $e->getMessage();
            }
        } else {
            echo "<div class='status warning'>⚠️ Não há mensagens para testar</div>";
        }
        
        // 6. Verificar arquivo de log
        echo "<h2>6️⃣ Arquivo de Log</h2>";
        
        $logFile = __DIR__ . '/../logs/coaching.log';
        if (file_exists($logFile)) {
            $size = filesize($logFile);
            $lines = count(file($logFile));
            echo "<div class='status success'>✅ Arquivo de log existe</div>";
            echo "<div class='info'>Tamanho: " . number_format($size) . " bytes | Linhas: {$lines}</div>";
            
            if ($size > 100) {
                echo "<h3>Últimas 10 linhas do log:</h3>";
                $content = file($logFile);
                $lastLines = array_slice($content, -10);
                echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
            } else {
                echo "<div class='status warning'>⚠️ Log está vazio ou muito pequeno</div>";
            }
        } else {
            echo "<div class='status error'>❌ Arquivo de log não existe</div>";
            $errors[] = "Arquivo coaching.log não existe";
        }
        
        // 7. Verificar API Key OpenAI
        echo "<h2>7️⃣ API Key OpenAI</h2>";
        
        try {
            $stmt = $db->query("SELECT * FROM settings WHERE `key` = 'openai_api_key' LIMIT 1");
            $apiKey = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($apiKey && !empty($apiKey['value'])) {
                $keyPreview = substr($apiKey['value'], 0, 10) . '...' . substr($apiKey['value'], -4);
                echo "<div class='status success'>✅ API Key configurada: {$keyPreview}</div>";
            } else {
                echo "<div class='status error'>❌ API Key da OpenAI NÃO configurada</div>";
                $errors[] = "API Key não configurada";
            }
        } catch (Exception $e) {
            echo "<div class='status error'>❌ Erro ao verificar API Key: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // Resumo Final
        echo "<h2>📊 Resumo do Diagnóstico</h2>";
        
        if (count($errors) === 0) {
            echo "<div class='status success'>";
            echo "<h3>✅ TUDO OK!</h3>";
            echo "<p>O sistema de Coaching está configurado corretamente.</p>";
            echo "<p>Se ainda não está funcionando, envie uma mensagem de teste do WhatsApp e verifique os logs.</p>";
            echo "</div>";
        } else {
            echo "<div class='status error'>";
            echo "<h3>❌ PROBLEMAS ENCONTRADOS:</h3>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li>{$error}</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        if (count($warnings) > 0) {
            echo "<div class='status warning'>";
            echo "<h3>⚠️ AVISOS:</h3>";
            echo "<ul>";
            foreach ($warnings as $warning) {
                echo "<li>{$warning}</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        // Ações Recomendadas
        echo "<h2>🔧 Ações Recomendadas</h2>";
        
        echo "<div style='margin: 20px 0;'>";
        echo "<a href='/view-all-logs.php' class='btn' target='_blank'>📋 Ver Logs</a>";
        echo "<a href='/settings?tab=conversations' class='btn'>⚙️ Configurações</a>";
        echo "<a href='/conversations' class='btn'>💬 Conversas</a>";
        echo "<a href='?' class='btn'>🔄 Recarregar Diagnóstico</a>";
        echo "</div>";
        
        ?>
        
        <h2>📝 Como Testar</h2>
        <div class="info">
            <ol>
                <li>Envie uma mensagem de teste do WhatsApp</li>
                <li>Acesse <a href="/view-all-logs.php" target="_blank">/view-all-logs.php</a></li>
                <li>Clique no botão verde "⚡ Coaching"</li>
                <li>Você deve ver logs como:
                    <pre>📩 Nova mensagem recebida - ID: 123
🎯 queueMessageForAnalysis()
✅ Coaching está HABILITADO</pre>
                </li>
            </ol>
        </div>
    </div>
</body>
</html>
