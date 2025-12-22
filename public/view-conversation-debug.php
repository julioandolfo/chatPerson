<?php
/**
 * Visualizador de Debug por Conversa
 * Mostra todos os logs relacionados a uma conversa específica
 */

// Conversa a monitorar (pode ser passada por GET)
$conversationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Diretórios de logs
$logDir = __DIR__ . '/../logs';
$conversationLogFile = $logDir . '/conversation-debug.log';

// Criar arquivo se não existir
if (!file_exists($conversationLogFile)) {
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    touch($conversationLogFile);
    @chmod($conversationLogFile, 0666);
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'clear') {
        file_put_contents($conversationLogFile, '');
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'enable') {
        $id = (int)($_POST['conversation_id'] ?? 0);
        if ($id > 0) {
            // Salvar ID da conversa a monitorar
            file_put_contents($logDir . '/debug-conversation-id.txt', $id);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Debug ativado para conversa #{$id}"]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
        }
        exit;
    }
    
    if ($action === 'disable') {
        @unlink($logDir . '/debug-conversation-id.txt');
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Debug desativado']);
        exit;
    }
}

// Verificar qual conversa está sendo monitorada
$monitoredId = 0;
$idFile = $logDir . '/debug-conversation-id.txt';
if (file_exists($idFile)) {
    $monitoredId = (int)trim(file_get_contents($idFile));
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug de Conversa</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            padding: 20px;
            background: #0d1117;
            color: #c9d1d9;
            margin: 0;
        }
        h1 {
            color: #58a6ff;
            margin-bottom: 20px;
        }
        .controls {
            background: #161b22;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        input[type="number"] {
            padding: 10px 15px;
            background: #0d1117;
            border: 1px solid #30363d;
            color: #c9d1d9;
            border-radius: 6px;
            width: 150px;
            font-size: 16px;
        }
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #238636;
            color: white;
        }
        .btn-primary:hover {
            background: #2ea043;
        }
        .btn-danger {
            background: #da3633;
            color: white;
        }
        .btn-danger:hover {
            background: #f85149;
        }
        .btn-secondary {
            background: #21262d;
            color: #c9d1d9;
            border: 1px solid #30363d;
        }
        .btn-secondary:hover {
            background: #30363d;
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-active {
            background: #238636;
            color: white;
        }
        .status-inactive {
            background: #6e7681;
            color: white;
        }
        .log-container {
            background: #161b22;
            border-radius: 8px;
            overflow: hidden;
        }
        .log-header {
            background: #21262d;
            padding: 15px 20px;
            border-bottom: 1px solid #30363d;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .log-content {
            padding: 15px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .log-entry {
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 6px;
            border-left: 4px solid #30363d;
            background: #0d1117;
            font-size: 13px;
            line-height: 1.5;
        }
        .log-entry.message-received {
            border-left-color: #3fb950;
            background: #0d1117;
        }
        .log-entry.openai-request {
            border-left-color: #58a6ff;
        }
        .log-entry.openai-response {
            border-left-color: #a371f7;
        }
        .log-entry.tool-call {
            border-left-color: #f0883e;
        }
        .log-entry.tool-response {
            border-left-color: #d29922;
        }
        .log-entry.error {
            border-left-color: #f85149;
            background: #1c0d0d;
        }
        .log-entry.ai-agent {
            border-left-color: #8b5cf6;
        }
        .log-entry.send-message {
            border-left-color: #22c55e;
        }
        .timestamp {
            color: #6e7681;
            font-size: 12px;
        }
        .log-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 8px;
        }
        .type-msg { background: #238636; color: white; }
        .type-openai { background: #1f6feb; color: white; }
        .type-tool { background: #9e6a03; color: white; }
        .type-error { background: #da3633; color: white; }
        .type-ai { background: #8b5cf6; color: white; }
        .type-send { background: #22c55e; color: white; }
        .type-info { background: #6e7681; color: white; }
        pre {
            background: #0d1117;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            margin: 10px 0 0 0;
            font-size: 12px;
            border: 1px solid #30363d;
        }
        .filter-input {
            padding: 10px 15px;
            background: #0d1117;
            border: 1px solid #30363d;
            color: #c9d1d9;
            border-radius: 6px;
            width: 250px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6e7681;
        }
        .empty-state h3 {
            color: #c9d1d9;
            margin-bottom: 10px;
        }
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .auto-refresh input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
    </style>
</head>
<body>
    <h1>🔍 Debug de Conversa</h1>
    
    <div class="controls">
        <div>
            <label>Conversa ID:</label>
            <input type="number" id="conversationId" value="<?= $monitoredId ?>" placeholder="Ex: 347">
        </div>
        
        <button class="btn-primary" onclick="enableDebug()">
            ▶️ Ativar Debug
        </button>
        
        <button class="btn-danger" onclick="disableDebug()">
            ⏹️ Desativar
        </button>
        
        <button class="btn-secondary" onclick="location.reload()">
            🔄 Atualizar
        </button>
        
        <button class="btn-danger" onclick="clearLogs()">
            🗑️ Limpar Logs
        </button>
        
        <div class="auto-refresh">
            <input type="checkbox" id="autoRefresh" checked>
            <label for="autoRefresh">Auto-refresh (5s)</label>
        </div>
        
        <?php if ($monitoredId > 0): ?>
        <span class="status-badge status-active">
            🟢 Monitorando #<?= $monitoredId ?>
        </span>
        <?php else: ?>
        <span class="status-badge status-inactive">
            ⚫ Debug Desativado
        </span>
        <?php endif; ?>
    </div>
    
    <div class="log-container">
        <div class="log-header">
            <span>Logs de Debug</span>
            <input type="text" class="filter-input" id="filterInput" placeholder="Filtrar logs..." onkeyup="filterLogs()">
        </div>
        
        <div class="log-content" id="logContent">
            <?php
            if (file_exists($conversationLogFile)) {
                $lines = file($conversationLogFile);
                $lines = array_reverse($lines);
                $count = 0;
                $maxLines = 500;
                
                if (empty($lines) || (count($lines) == 1 && empty(trim($lines[0])))) {
                    echo '<div class="empty-state">';
                    echo '<h3>Nenhum log ainda</h3>';
                    echo '<p>Ative o debug para uma conversa e envie uma mensagem para ver os logs aqui.</p>';
                    echo '</div>';
                } else {
                    foreach ($lines as $line) {
                        if (empty(trim($line))) continue;
                        if ($count >= $maxLines) break;
                        $count++;
                        
                        $line = htmlspecialchars($line);
                        $cssClass = 'log-entry';
                        $typeClass = 'type-info';
                        $typeLabel = 'INFO';
                        
                        if (stripos($line, '[MSG_RECV]') !== false) {
                            $cssClass .= ' message-received';
                            $typeClass = 'type-msg';
                            $typeLabel = 'MSG';
                        } elseif (stripos($line, '[OPENAI_REQ]') !== false) {
                            $cssClass .= ' openai-request';
                            $typeClass = 'type-openai';
                            $typeLabel = 'OPENAI→';
                        } elseif (stripos($line, '[OPENAI_RES]') !== false) {
                            $cssClass .= ' openai-response';
                            $typeClass = 'type-openai';
                            $typeLabel = '←OPENAI';
                        } elseif (stripos($line, '[TOOL_CALL]') !== false) {
                            $cssClass .= ' tool-call';
                            $typeClass = 'type-tool';
                            $typeLabel = 'TOOL→';
                        } elseif (stripos($line, '[TOOL_RES]') !== false) {
                            $cssClass .= ' tool-response';
                            $typeClass = 'type-tool';
                            $typeLabel = '←TOOL';
                        } elseif (stripos($line, '[ERROR]') !== false || stripos($line, 'erro') !== false) {
                            $cssClass .= ' error';
                            $typeClass = 'type-error';
                            $typeLabel = 'ERROR';
                        } elseif (stripos($line, '[AI_AGENT]') !== false) {
                            $cssClass .= ' ai-agent';
                            $typeClass = 'type-ai';
                            $typeLabel = 'AI';
                        } elseif (stripos($line, '[SEND_MSG]') !== false) {
                            $cssClass .= ' send-message';
                            $typeClass = 'type-send';
                            $typeLabel = 'SEND';
                        }
                        
                        // Formatar timestamp
                        $line = preg_replace('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', '<span class="timestamp">[$1]</span>', $line);
                        
                        echo "<div class='{$cssClass}'>";
                        echo "<span class='log-type {$typeClass}'>{$typeLabel}</span>";
                        echo $line;
                        echo "</div>";
                    }
                }
            } else {
                echo '<div class="empty-state">';
                echo '<h3>Arquivo de log não encontrado</h3>';
                echo '<p>Ative o debug para uma conversa para começar a capturar logs.</p>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
    
    <script>
    function enableDebug() {
        const id = document.getElementById('conversationId').value;
        if (!id || id <= 0) {
            alert('Digite um ID de conversa válido');
            return;
        }
        
        const formData = new FormData();
        formData.append('conversation_id', id);
        
        fetch('?action=enable', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            location.reload();
        });
    }
    
    function disableDebug() {
        fetch('?action=disable', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            location.reload();
        });
    }
    
    function clearLogs() {
        if (!confirm('Limpar todos os logs?')) return;
        
        fetch('?action=clear', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
        });
    }
    
    function filterLogs() {
        const filter = document.getElementById('filterInput').value.toLowerCase();
        document.querySelectorAll('.log-entry').forEach(entry => {
            entry.style.display = entry.textContent.toLowerCase().includes(filter) ? 'block' : 'none';
        });
    }
    
    // Auto-refresh
    let refreshInterval;
    function setupAutoRefresh() {
        const checkbox = document.getElementById('autoRefresh');
        if (checkbox.checked) {
            refreshInterval = setInterval(() => location.reload(), 5000);
        } else {
            clearInterval(refreshInterval);
        }
    }
    
    document.getElementById('autoRefresh').addEventListener('change', setupAutoRefresh);
    setupAutoRefresh();
    </script>
</body>
</html>

