<?php
/**
 * Visualizador de Logs de Automação
 */

$logFile = __DIR__ . '/../storage/logs/automation.log';

// Criar arquivo se não existir
if (!file_exists($logFile)) {
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    touch($logFile);
    chmod($logFile, 0666);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logs de Automação</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        h1 {
            color: #4ec9b0;
        }
        .log-entry {
            padding: 8px;
            margin: 4px 0;
            border-left: 3px solid #007acc;
            background: #252526;
        }
        .error {
            border-left-color: #f48771;
            background: #3c1f1e;
        }
        .success {
            border-left-color: #4ec9b0;
        }
        .timestamp {
            color: #608b4e;
        }
        .controls {
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <h1>📋 Logs de Automação</h1>
    
    <div class="controls">
        <button onclick="location.reload()">🔄 Atualizar</button>
        <button class="clear-btn" onclick="clearLogs()">🗑️ Limpar Logs</button>
        <button onclick="window.history.back()">← Voltar</button>
    </div>
    
    <div id="logs">
        <?php
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $lines = array_reverse($lines); // Mais recentes primeiro
            $count = 0;
            $maxLines = 200;
            
            foreach ($lines as $line) {
                if ($count >= $maxLines) break;
                $count++;
                
                $line = htmlspecialchars($line);
                $cssClass = 'log-entry';
                
                if (stripos($line, 'erro') !== false || stripos($line, 'error') !== false) {
                    $cssClass .= ' error';
                } elseif (stripos($line, 'sucesso') !== false || stripos($line, 'success') !== false) {
                    $cssClass .= ' success';
                }
                
                // Destacar timestamp
                $line = preg_replace('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', '<span class="timestamp">[$1]</span>', $line);
                
                echo "<div class='{$cssClass}'>{$line}</div>";
            }
            
            if (count($lines) > $maxLines) {
                echo "<div class='log-entry'>... e mais " . (count($lines) - $maxLines) . " linhas</div>";
            }
        } else {
            echo "<div class='log-entry'>Nenhum log encontrado. Arquivo: {$logFile}</div>";
        }
        ?>
    </div>
    
    <script>
    function clearLogs() {
        if (confirm('Tem certeza que deseja limpar todos os logs?')) {
            fetch('<?= $_SERVER['PHP_SELF'] ?>?action=clear', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Logs limpos com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao limpar logs: ' + data.message);
                }
            });
        }
    }
    </script>
</body>
</html>

<?php
// Processar ação de limpar logs
if (isset($_GET['action']) && $_GET['action'] === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado']);
    }
    exit;
}

