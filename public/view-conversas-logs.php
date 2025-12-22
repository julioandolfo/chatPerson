<?php
/**
 * Visualizador de Logs de Conversas
 */

$logFile = __DIR__ . '/../logs/conversas.log';

// Criar arquivo se não existir
if (!file_exists($logFile)) {
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    touch($logFile);
    @chmod($logFile, 0666);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logs de Conversas</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
            margin: 0;
        }
        h1 {
            color: #4ec9b0;
            margin-top: 0;
        }
        .log-entry {
            padding: 8px;
            margin: 4px 0;
            border-left: 3px solid #007acc;
            background: #252526;
            word-wrap: break-word;
            font-size: 12px;
            line-height: 1.5;
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
            background: #3a3a2a;
        }
        .debug {
            border-left-color: #569cd6;
            background: #1e2a3a;
        }
        .timestamp {
            color: #608b4e;
            font-weight: bold;
        }
        .controls {
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            background: #1e1e1e;
            padding: 10px 0;
            z-index: 100;
            border-bottom: 2px solid #007acc;
        }
        button {
            padding: 8px 16px;
            background: #0e639c;
            color: white;
            border: none;
            cursor: pointer;
            margin-right: 8px;
            border-radius: 4px;
            font-family: monospace;
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
        .filter-section {
            margin-top: 10px;
            padding: 10px;
            background: #252526;
            border-radius: 4px;
        }
        .filter-section label {
            margin-right: 15px;
            color: #4ec9b0;
        }
        .filter-section input[type="text"] {
            padding: 4px 8px;
            background: #3c3c3c;
            border: 1px solid #007acc;
            color: #d4d4d4;
            border-radius: 3px;
            width: 300px;
        }
        .stats {
            margin-top: 10px;
            padding: 10px;
            background: #252526;
            border-radius: 4px;
            color: #4ec9b0;
        }
        .highlight {
            background: #ffd700;
            color: #000;
            font-weight: bold;
        }
        .emoji {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h1>💬 Logs de Conversas</h1>
    
    <div class="controls">
        <button onclick="location.reload()">🔄 Atualizar</button>
        <button class="clear-btn" onclick="clearLogs()">🗑️ Limpar Logs</button>
        <button onclick="window.location.href='/conversations'">← Voltar para Conversas</button>
        
        <div class="filter-section">
            <label>🔍 Filtrar:</label>
            <input type="text" id="filterInput" placeholder="Digite para filtrar logs..." onkeyup="filterLogs()">
            <label style="margin-left: 20px;">
                <input type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh()"> 
                Auto-atualizar (10s)
            </label>
        </div>
        
        <div class="stats" id="stats"></div>
    </div>
    
    <div id="logs">
        <?php
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $lines = array_reverse($lines); // Mais recentes primeiro
            $count = 0;
            $maxLines = 500; // Aumentado para mais linhas
            
            $errorCount = 0;
            $warningCount = 0;
            $debugCount = 0;
            
            foreach ($lines as $line) {
                if ($count >= $maxLines) break;
                $count++;
                
                $line = htmlspecialchars($line);
                $cssClass = 'log-entry';
                
                // Classificar por tipo
                if (stripos($line, 'erro') !== false || stripos($line, 'error') !== false || stripos($line, '❌') !== false) {
                    $cssClass .= ' error';
                    $errorCount++;
                } elseif (stripos($line, 'sucesso') !== false || stripos($line, 'success') !== false || stripos($line, '✅') !== false) {
                    $cssClass .= ' success';
                } elseif (stripos($line, 'warning') !== false || stripos($line, 'aviso') !== false || stripos($line, '⚠️') !== false) {
                    $cssClass .= ' warning';
                    $warningCount++;
                } elseif (stripos($line, 'debug') !== false || stripos($line, '🔍') !== false) {
                    $cssClass .= ' debug';
                    $debugCount++;
                }
                
                // Destacar timestamp
                $line = preg_replace('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', '<span class="timestamp">[$1]</span>', $line);
                
                // Destacar messageId, conversationId
                $line = preg_replace('/(messageId|conversationId|direction|sender_type)=([^\s,]+)/', '<strong style="color: #dcdcaa;">$1</strong>=<strong style="color: #ce9178;">$2</strong>', $line);
                
                echo "<div class='{$cssClass}' data-log-text='" . strtolower(strip_tags($line)) . "'>{$line}</div>";
            }
            
            if (count($lines) > $maxLines) {
                echo "<div class='log-entry'>... e mais " . (count($lines) - $maxLines) . " linhas (total: " . count($lines) . ")</div>";
            }
            
            // Estatísticas
            echo "<script>
                document.getElementById('stats').innerHTML = 
                    '<strong>Total:</strong> {$count} linhas | ' +
                    '<strong style=\"color: #f48771;\">Erros:</strong> {$errorCount} | ' +
                    '<strong style=\"color: #dcdcaa;\">Avisos:</strong> {$warningCount} | ' +
                    '<strong style=\"color: #569cd6;\">Debug:</strong> {$debugCount}';
            </script>";
        } else {
            echo "<div class='log-entry'>Nenhum log encontrado. Arquivo: {$logFile}</div>";
        }
        ?>
    </div>
    
    <script>
    let autoRefreshInterval = null;
    
    function clearLogs() {
        if (confirm('Tem certeza que deseja limpar todos os logs de conversas?')) {
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
        const filterValue = document.getElementById('filterInput').value.toLowerCase();
        const logEntries = document.querySelectorAll('.log-entry');
        let visibleCount = 0;
        
        logEntries.forEach(entry => {
            const text = entry.getAttribute('data-log-text') || entry.textContent.toLowerCase();
            if (text.includes(filterValue)) {
                entry.style.display = '';
                visibleCount++;
                
                // Highlight do termo pesquisado
                if (filterValue) {
                    const regex = new RegExp('(' + filterValue + ')', 'gi');
                    const originalText = entry.innerHTML;
                    entry.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
                }
            } else {
                entry.style.display = 'none';
            }
        });
        
        console.log(`Filtro aplicado: "${filterValue}" - ${visibleCount} resultados`);
    }
    
    function toggleAutoRefresh() {
        const checkbox = document.getElementById('autoRefresh');
        
        if (checkbox.checked) {
            autoRefreshInterval = setInterval(() => {
                console.log('Auto-atualizando logs...');
                location.reload();
            }, 10000); // 10 segundos
            console.log('Auto-atualização ativada (10s)');
        } else {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
            console.log('Auto-atualização desativada');
        }
    }
    
    // Auto-scroll para o topo ao carregar
    window.scrollTo(0, 0);
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

