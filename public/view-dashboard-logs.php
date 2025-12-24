<?php
/**
 * Visualizador de Logs do Dashboard
 */

$logFile = __DIR__ . '/../logs/dash.log';

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
    <title>Logs do Dashboard</title>
    <meta http-equiv="refresh" content="10">
    <style>
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            padding: 20px;
            background: #0d1117;
            color: #c9d1d9;
            margin: 0;
        }
        h1 {
            color: #58a6ff;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #8b949e;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .log-entry {
            padding: 10px 12px;
            margin: 4px 0;
            border-left: 4px solid #30363d;
            background: #161b22;
            border-radius: 0 6px 6px 0;
            font-size: 13px;
            line-height: 1.5;
        }
        .log-entry:hover {
            background: #1f2937;
        }
        .error {
            border-left-color: #f85149;
            background: #21121d;
        }
        .success {
            border-left-color: #3fb950;
            background: #12261e;
        }
        .warning {
            border-left-color: #d29922;
            background: #27231a;
        }
        .info {
            border-left-color: #58a6ff;
            background: #121d2f;
        }
        .timestamp {
            color: #7ee787;
            font-weight: bold;
        }
        .service-name {
            color: #d2a8ff;
            font-weight: bold;
        }
        .metric-value {
            color: #79c0ff;
            background: #0d1117;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
        .controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        button {
            padding: 10px 18px;
            background: #238636;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        button:hover {
            background: #2ea043;
        }
        .clear-btn {
            background: #da3633;
        }
        .clear-btn:hover {
            background: #f85149;
        }
        .back-btn {
            background: #30363d;
        }
        .back-btn:hover {
            background: #484f58;
        }
        .stats {
            background: #21262d;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #58a6ff;
        }
        .stat-label {
            color: #8b949e;
            font-size: 12px;
            margin-top: 4px;
        }
        .filter-input {
            padding: 8px 12px;
            background: #21262d;
            border: 1px solid #30363d;
            color: #c9d1d9;
            border-radius: 6px;
            font-size: 14px;
            min-width: 200px;
        }
        .filter-input:focus {
            outline: none;
            border-color: #58a6ff;
        }
        #logs {
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 10px;
        }
        #logs::-webkit-scrollbar {
            width: 8px;
        }
        #logs::-webkit-scrollbar-track {
            background: #21262d;
            border-radius: 4px;
        }
        #logs::-webkit-scrollbar-thumb {
            background: #484f58;
            border-radius: 4px;
        }
        .no-logs {
            text-align: center;
            padding: 50px;
            color: #8b949e;
        }
        .no-logs i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        .json-data {
            color: #79c0ff;
            background: #0d1117;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>📊 Logs do Dashboard</h1>
    <p class="subtitle">Atualização automática a cada 10 segundos | Arquivo: <?= htmlspecialchars($logFile) ?></p>
    
    <?php
    $lines = [];
    $errorCount = 0;
    $infoCount = 0;
    $warningCount = 0;
    
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $lines = array_reverse($lines);
        
        foreach ($lines as $line) {
            if (stripos($line, 'erro') !== false || stripos($line, 'error') !== false) {
                $errorCount++;
            } elseif (stripos($line, 'warning') !== false || stripos($line, 'alerta') !== false) {
                $warningCount++;
            } else {
                $infoCount++;
            }
        }
    }
    ?>
    
    <div class="stats">
        <div class="stat-item">
            <div class="stat-value"><?= count($lines) ?></div>
            <div class="stat-label">Total de Logs</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #3fb950;"><?= $infoCount ?></div>
            <div class="stat-label">Info</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #d29922;"><?= $warningCount ?></div>
            <div class="stat-label">Warnings</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" style="color: #f85149;"><?= $errorCount ?></div>
            <div class="stat-label">Erros</div>
        </div>
    </div>
    
    <div class="controls">
        <button onclick="location.reload()">🔄 Atualizar</button>
        <button class="clear-btn" onclick="clearLogs()">🗑️ Limpar Logs</button>
        <button class="back-btn" onclick="window.location.href='/dashboard'">← Voltar ao Dashboard</button>
        <input type="text" class="filter-input" id="filterInput" placeholder="🔍 Filtrar logs..." onkeyup="filterLogs()">
    </div>
    
    <div id="logs">
        <?php
        if (!empty($lines)) {
            $count = 0;
            $maxLines = 500;
            
            foreach ($lines as $line) {
                if ($count >= $maxLines) break;
                $count++;
                
                $originalLine = trim($line);
                if (empty($originalLine)) continue;
                
                $line = htmlspecialchars($originalLine);
                $cssClass = 'log-entry';
                
                // Determinar tipo de log
                if (stripos($line, 'erro') !== false || stripos($line, 'error') !== false || stripos($line, 'CRÍTICO') !== false) {
                    $cssClass .= ' error';
                } elseif (stripos($line, 'sucesso') !== false || stripos($line, 'success') !== false) {
                    $cssClass .= ' success';
                } elseif (stripos($line, 'warning') !== false || stripos($line, 'alerta') !== false) {
                    $cssClass .= ' warning';
                } elseif (stripos($line, 'result') !== false || stripos($line, 'check') !== false) {
                    $cssClass .= ' info';
                }
                
                // Destacar timestamp
                $line = preg_replace('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', '<span class="timestamp">[$1]</span>', $line);
                
                // Destacar nome do service
                $line = preg_replace('/\[(DashboardService|AgentPerformanceService|SLAMonitoringService)\]/', '<span class="service-name">[$1]</span>', $line);
                
                // Destacar valores numéricos importantes
                $line = preg_replace('/=(\d+\.?\d*)/', '=<span class="metric-value">$1</span>', $line);
                
                // Destacar JSON
                $line = preg_replace('/(\{.*\})/', '<span class="json-data">$1</span>', $line);
                
                echo "<div class='{$cssClass}'>{$line}</div>";
            }
            
            if (count($lines) > $maxLines) {
                echo "<div class='log-entry warning'>⚠️ Mostrando apenas as últimas {$maxLines} linhas. Total: " . count($lines) . " linhas</div>";
            }
        } else {
            echo '<div class="no-logs">';
            echo '<span style="font-size: 48px;">📭</span><br><br>';
            echo '<strong>Nenhum log encontrado</strong><br>';
            echo '<span style="font-size: 13px;">Os logs aparecerão aqui quando você acessar o Dashboard</span>';
            echo '</div>';
        }
        ?>
    </div>
    
    <script>
    function clearLogs() {
        if (confirm('Tem certeza que deseja limpar todos os logs do dashboard?')) {
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
            })
            .catch(error => {
                alert('Erro ao processar requisição: ' + error);
            });
        }
    }
    
    function filterLogs() {
        const filter = document.getElementById('filterInput').value.toLowerCase();
        const logs = document.querySelectorAll('.log-entry');
        
        logs.forEach(log => {
            const text = log.textContent.toLowerCase();
            if (text.includes(filter)) {
                log.style.display = 'block';
            } else {
                log.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>

