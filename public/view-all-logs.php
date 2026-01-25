<?php
/**
 * Visualizador de TODOS os Logs
 */

// Lista de arquivos de log para verificar
$logFiles = [
    'External Sources (Google Maps/WooCommerce)' => __DIR__ . '/../logs/external_sources.log',
    'Dashboard' => __DIR__ . '/../logs/dash.log',
    'Metas e OTE (Goals)' => __DIR__ . '/../logs/goals.log',
    'Coaching em Tempo Real' => __DIR__ . '/../logs/coaching.log',
    'Jobs Agendados (Cron)' => __DIR__ . '/../storage/logs/jobs.log',
    'Webhook WooCommerce' => __DIR__ . '/../logs/webhook.log',
    'Aplicação' => __DIR__ . '/../logs/app.log',
    'Conversas' => __DIR__ . '/../logs/conversas.log',
    'Quepasa' => __DIR__ . '/../logs/quepasa.log',
    'Automação' => __DIR__ . '/../logs/automacao.log',
    'AI Agent' => __DIR__ . '/../logs/ai_agent.log',
    'AI Tools' => __DIR__ . '/../logs/ai_tools.log',
    'Kanban Agents' => __DIR__ . '/../logs/kanban_agents.log',
    'Kanban Agents Cron' => __DIR__ . '/../storage/logs/kanban-agents-cron.log',
    'Erros PHP' => __DIR__ . '/../logs/error.log',
];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Todos os Logs</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
            font-size: 12px;
        }
        h1 {
            color: #4ec9b0;
        }
        h2 {
            color: #569cd6;
            border-bottom: 2px solid #569cd6;
            padding-bottom: 5px;
            margin-top: 30px;
        }
        .log-entry {
            padding: 8px;
            margin: 4px 0;
            border-left: 3px solid #007acc;
            background: #252526;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .error {
            border-left-color: #f48771;
            background: #3c1f1e;
        }
        .success {
            border-left-color: #4ec9b0;
        }
        .warning {
            border-left-color: #dcdcaa;
            background: #3c3c1e;
        }
        .timestamp {
            color: #608b4e;
        }
        .controls {
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            background: #1e1e1e;
            padding: 10px 0;
            z-index: 100;
        }
        button {
            padding: 8px 16px;
            background: #0e639c;
            color: white;
            border: none;
            cursor: pointer;
            margin-right: 8px;
        }
        button:hover {
            background: #1177bb;
        }
        .clear-btn {
            background: #f48771;
        }
        .clear-btn:hover {
            background: #ff6b6b;
        }
        .nav-btn {
            background: #569cd6;
        }
        .nav-btn:hover {
            background: #6aacd6;
        }
        .file-not-found {
            color: #f48771;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>📋 Todos os Logs do Sistema</h1>
    
    <div class="controls">
        <button onclick="location.reload()">🔄 Atualizar</button>
        <button class="nav-btn" onclick="document.getElementById('metas-e-ote-goals-log').scrollIntoView({behavior: 'smooth'})" style="background: #ffd700; color: #000">🎯 Metas/OTE</button>
        <button class="nav-btn" onclick="document.getElementById('coaching-em-tempo-real-log').scrollIntoView({behavior: 'smooth'})" style="background: #4ec9b0">⚡ Coaching</button>
        <button class="nav-btn" onclick="document.getElementById('jobs-agendados-cron-log').scrollIntoView({behavior: 'smooth'})" style="background: #dcdcaa">⏰ Jobs Cron</button>
        <button class="nav-btn" onclick="document.getElementById('webhook-woocommerce-log').scrollIntoView({behavior: 'smooth'})" style="background: #4caf50">🔗 Webhook</button>
        <button class="nav-btn" onclick="document.getElementById('aplicacao-log').scrollIntoView({behavior: 'smooth'})">Aplicação</button>
        <button class="nav-btn" onclick="document.getElementById('conversas-log').scrollIntoView({behavior: 'smooth'})">Conversas</button>
        <button class="nav-btn" onclick="document.getElementById('quepasa-log').scrollIntoView({behavior: 'smooth'})">Quepasa</button>
        <button class="nav-btn" onclick="document.getElementById('automacao-log').scrollIntoView({behavior: 'smooth'})">Automação</button>
        <button class="nav-btn" onclick="document.getElementById('ai-agent-log').scrollIntoView({behavior: 'smooth'})">AI Agent</button>
        <button class="nav-btn" onclick="document.getElementById('ai-tools-log').scrollIntoView({behavior: 'smooth'})">AI Tools</button>
        <button class="nav-btn" onclick="document.getElementById('kanban-agents-log').scrollIntoView({behavior: 'smooth'})">Kanban Agents</button>
        <button class="nav-btn" onclick="document.getElementById('kanban-agents-cron-log').scrollIntoView({behavior: 'smooth'})">Kanban Cron</button>
        <button onclick="window.history.back()">← Voltar</button>
    </div>
    
    <?php foreach ($logFiles as $name => $logFile): ?>
        <h2 id="<?= strtolower(str_replace(' ', '-', $name)) ?>-log"><?= $name ?></h2>
        <div>
            <?php
            if (file_exists($logFile)) {
                $content = file_get_contents($logFile);
                if (trim($content) === '') {
                    echo "<div class='warning' style='padding: 20px; text-align: center;'>";
                    echo "📋 Log vazio - Nenhuma atividade registrada ainda<br>";
                    echo "<small style='color: #888;'>O log será preenchido automaticamente quando houver atividade</small>";
                    echo "</div>";
                } else {
                    $lines = file($logFile);
                    $lines = array_reverse(array_slice($lines, -100)); // Últimas 100 linhas, mais recentes primeiro
                    
                    foreach ($lines as $line) {
                        $line = htmlspecialchars($line);
                        $cssClass = 'log-entry';
                        
                        if (stripos($line, 'erro') !== false || stripos($line, 'error') !== false || stripos($line, 'exception') !== false) {
                            $cssClass .= ' error';
                        } elseif (stripos($line, 'sucesso') !== false || stripos($line, 'success') !== false || stripos($line, '✅') !== false) {
                            $cssClass .= ' success';
                        } elseif (stripos($line, 'warning') !== false || stripos($line, '⚠️') !== false) {
                            $cssClass .= ' warning';
                        }
                        
                        // Destacar timestamp
                        $line = preg_replace('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', '<span class="timestamp">[$1]</span>', $line);
                        
                        echo "<div class='{$cssClass}'>{$line}</div>";
                    }
                }
            } else {
                echo "<div class='file-not-found'>";
                echo "❌ Arquivo não encontrado: {$logFile}<br>";
                echo "<small>Crie o arquivo executando: touch {$logFile}</small>";
                echo "</div>";
            }
            ?>
        </div>
    <?php endforeach; ?>
    
</body>
</html>

