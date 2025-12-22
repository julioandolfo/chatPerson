<?php
/**
 * Visualizador de Logs de Agentes de IA
 */

$logFile = __DIR__ . '/../logs/ai-agents.log';

// Criar arquivo se não existir
if (!file_exists($logFile)) {
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    touch($logFile);
    @chmod($logFile, 0666);
}

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

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logs de Agentes de IA</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1a1a2e;
            color: #eee;
        }
        h1 {
            color: #7c3aed;
        }
        .log-entry {
            padding: 8px 12px;
            margin: 4px 0;
            border-left: 3px solid #7c3aed;
            background: #16213e;
            border-radius: 0 4px 4px 0;
        }
        .error {
            border-left-color: #ef4444;
            background: #3c1f1e;
        }
        .success {
            border-left-color: #22c55e;
            background: #1a3d2e;
        }
        .warning {
            border-left-color: #f59e0b;
            background: #3d3a1a;
        }
        .add-agent {
            border-left-color: #06b6d4;
            background: #1a3a3d;
        }
        .remove-agent {
            border-left-color: #f43f5e;
            background: #3d1a2a;
        }
        .timestamp {
            color: #a3a3a3;
        }
        .controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        button {
            padding: 8px 16px;
            background: #7c3aed;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        button:hover {
            background: #8b5cf6;
        }
        .clear-btn {
            background: #ef4444;
        }
        .clear-btn:hover {
            background: #f87171;
        }
        .filter-input {
            padding: 8px 12px;
            background: #16213e;
            border: 1px solid #7c3aed;
            color: #eee;
            border-radius: 4px;
            width: 300px;
        }
        .stats {
            background: #16213e;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #7c3aed;
        }
        .stat-label {
            font-size: 12px;
            color: #a3a3a3;
        }
    </style>
</head>
<body>
    <h1>🤖 Logs de Agentes de IA</h1>
    
    <?php
    $lines = [];
    $stats = ['total' => 0, 'add' => 0, 'remove' => 0, 'error' => 0, 'success' => 0];
    
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $stats['total'] = count($lines);
        
        foreach ($lines as $line) {
            if (stripos($line, 'addAIAgent') !== false) $stats['add']++;
            if (stripos($line, 'removeAIAgent') !== false) $stats['remove']++;
            if (stripos($line, 'erro') !== false || stripos($line, 'error') !== false) $stats['error']++;
            if (stripos($line, 'sucesso') !== false || stripos($line, 'success') !== false) $stats['success']++;
        }
    }
    ?>
    
    <div class="stats">
        <div class="stat-item">
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total de Logs</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #06b6d4;"><?= $stats['add'] ?></div>
            <div class="stat-label">Adições</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #f43f5e;"><?= $stats['remove'] ?></div>
            <div class="stat-label">Remoções</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #22c55e;"><?= $stats['success'] ?></div>
            <div class="stat-label">Sucesso</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #ef4444;"><?= $stats['error'] ?></div>
            <div class="stat-label">Erros</div>
        </div>
    </div>
    
    <div class="controls">
        <button onclick="location.reload()">🔄 Atualizar</button>
        <button class="clear-btn" onclick="clearLogs()">🗑️ Limpar Logs</button>
        <input type="text" class="filter-input" id="filterInput" placeholder="Filtrar logs... (ex: conversationId, erro)" onkeyup="filterLogs()">
        <button onclick="window.history.back()">← Voltar</button>
    </div>
    
    <div id="logs">
        <?php
        if (!empty($lines)) {
            $lines = array_reverse($lines); // Mais recentes primeiro
            $count = 0;
            $maxLines = 300;
            
            foreach ($lines as $line) {
                if ($count >= $maxLines) break;
                $count++;
                
                $line = htmlspecialchars($line);
                $cssClass = 'log-entry';
                
                if (stripos($line, 'erro') !== false || stripos($line, 'error') !== false || stripos($line, 'falha') !== false) {
                    $cssClass .= ' error';
                } elseif (stripos($line, 'sucesso') !== false || stripos($line, 'success') !== false || stripos($line, 'criado') !== false) {
                    $cssClass .= ' success';
                } elseif (stripos($line, 'addAIAgent') !== false) {
                    $cssClass .= ' add-agent';
                } elseif (stripos($line, 'removeAIAgent') !== false) {
                    $cssClass .= ' remove-agent';
                } elseif (stripos($line, 'warning') !== false || stripos($line, 'aviso') !== false) {
                    $cssClass .= ' warning';
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
    
    function filterLogs() {
        const filter = document.getElementById('filterInput').value.toLowerCase();
        const entries = document.querySelectorAll('.log-entry');
        
        entries.forEach(entry => {
            const text = entry.textContent.toLowerCase();
            entry.style.display = text.includes(filter) ? 'block' : 'none';
        });
    }
    </script>
</body>
</html>

