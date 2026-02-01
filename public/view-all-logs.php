<?php
/**
 * Visualizador Completo de Logs da API
 * Acesse: https://chat.personizi.com.br/view-all-logs.php
 */

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações
$logFile = __DIR__ . '/../storage/logs/api.log';
$maxLines = isset($_GET['lines']) ? (int)$_GET['lines'] : 500;
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$level = isset($_GET['level']) ? $_GET['level'] : '';

// Ler logs
$logs = [];
if (file_exists($logFile)) {
    $allLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logs = array_slice(array_reverse($allLines), 0, $maxLines);
} else {
    $logs = ['Arquivo de log não encontrado: ' . $logFile];
}

// Aplicar filtros
if (!empty($filter) || !empty($level)) {
    $logs = array_filter($logs, function($line) use ($filter, $level) {
        $matchFilter = empty($filter) || stripos($line, $filter) !== false;
        $matchLevel = empty($level) || stripos($line, "[$level]") !== false;
        return $matchFilter && $matchLevel;
    });
}

// Estatísticas
$stats = [
    'total' => count($logs),
    'errors' => 0,
    'warnings' => 0,
    'info' => 0,
    'debug' => 0
];

foreach ($logs as $log) {
    if (stripos($log, '[ERROR]') !== false) $stats['errors']++;
    if (stripos($log, '[WARNING]') !== false) $stats['warnings']++;
    if (stripos($log, '[INFO]') !== false) $stats['info']++;
    if (stripos($log, '[DEBUG]') !== false) $stats['debug']++;
}

// Função para colorir logs
function colorizeLog($log) {
    $log = htmlspecialchars($log);
    
    // Níveis
    $log = preg_replace('/\[ERROR\]/', '<span class="badge-error">[ERROR]</span>', $log);
    $log = preg_replace('/\[WARNING\]/', '<span class="badge-warning">[WARNING]</span>', $log);
    $log = preg_replace('/\[INFO\]/', '<span class="badge-info">[INFO]</span>', $log);
    $log = preg_replace('/\[DEBUG\]/', '<span class="badge-debug">[DEBUG]</span>', $log);
    
    // URLs
    $log = preg_replace('/(https?:\/\/[^\s]+)/', '<span class="url">$1</span>', $log);
    
    // Números
    $log = preg_replace('/\b(\d+)\b/', '<span class="number">$1</span>', $log);
    
    // Timestamps
    $log = preg_replace('/(\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/', '<span class="timestamp">$1</span>', $log);
    
    // JSON
    if (preg_match('/(\{.*\}|\[.*\])/', $log)) {
        $log = preg_replace_callback('/(\{.*\}|\[.*\])/', function($matches) {
            $json = $matches[1];
            $decoded = json_decode($json, true);
            if ($decoded !== null) {
                return '<span class="json">' . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</span>';
            }
            return $json;
        }, $log);
    }
    
    // Separadores
    $log = preg_replace('/(━+)/', '<span class="separator">$1</span>', $log);
    
    // Emojis/Símbolos
    $log = preg_replace('/(✅|❌|⚠️|🔧|📥|📤|🔍|💡|⏱️|🚀)/', '<span class="emoji">$1</span>', $log);
    
    return $log;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs da API - Chat Personizi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        header {
            background: #252526;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007acc;
        }
        
        h1 {
            color: #ffffff;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .stat {
            background: #2d2d30;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .stat-label {
            color: #858585;
            font-size: 12px;
        }
        
        .stat-value {
            color: #ffffff;
            font-size: 20px;
            font-weight: bold;
        }
        
        .stat.errors .stat-value { color: #f48771; }
        .stat.warnings .stat-value { color: #dcdcaa; }
        .stat.info .stat-value { color: #4ec9b0; }
        .stat.debug .stat-value { color: #9cdcfe; }
        
        .filters {
            background: #252526;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        label {
            color: #858585;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        input, select, button {
            background: #3c3c3c;
            border: 1px solid #555;
            color: #d4d4d4;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #007acc;
        }
        
        button {
            background: #007acc;
            border: none;
            color: #ffffff;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        
        button:hover {
            background: #005a9e;
        }
        
        button.secondary {
            background: #3c3c3c;
            border: 1px solid #555;
        }
        
        button.secondary:hover {
            background: #505050;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }
        
        .logs-container {
            background: #1e1e1e;
            border: 1px solid #3c3c3c;
            border-radius: 8px;
            padding: 20px;
            overflow-x: auto;
        }
        
        .log-line {
            padding: 8px 10px;
            margin-bottom: 2px;
            border-left: 3px solid transparent;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .log-line:hover {
            background: #2d2d30;
        }
        
        .log-line.error {
            border-left-color: #f48771;
            background: rgba(244, 135, 113, 0.05);
        }
        
        .log-line.warning {
            border-left-color: #dcdcaa;
            background: rgba(220, 220, 170, 0.05);
        }
        
        .log-line.info {
            border-left-color: #4ec9b0;
        }
        
        .log-line.debug {
            border-left-color: #9cdcfe;
            opacity: 0.8;
        }
        
        .badge-error {
            background: #f48771;
            color: #1e1e1e;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }
        
        .badge-warning {
            background: #dcdcaa;
            color: #1e1e1e;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }
        
        .badge-info {
            background: #4ec9b0;
            color: #1e1e1e;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }
        
        .badge-debug {
            background: #9cdcfe;
            color: #1e1e1e;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }
        
        .timestamp {
            color: #858585;
        }
        
        .url {
            color: #4ec9b0;
            text-decoration: underline;
        }
        
        .number {
            color: #b5cea8;
        }
        
        .json {
            display: block;
            background: #2d2d30;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            color: #ce9178;
            font-size: 12px;
        }
        
        .separator {
            color: #555;
        }
        
        .emoji {
            font-size: 16px;
        }
        
        .no-logs {
            text-align: center;
            padding: 40px;
            color: #858585;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #858585;
            font-size: 12px;
            margin-top: 20px;
        }
        
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .auto-refresh input[type="checkbox"] {
            width: auto;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .actions {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📋 Visualizador de Logs - API Chat</h1>
            <div class="stats">
                <div class="stat">
                    <div class="stat-label">Total</div>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                </div>
                <div class="stat errors">
                    <div class="stat-label">Erros</div>
                    <div class="stat-value"><?= $stats['errors'] ?></div>
                </div>
                <div class="stat warnings">
                    <div class="stat-label">Avisos</div>
                    <div class="stat-value"><?= $stats['warnings'] ?></div>
                </div>
                <div class="stat info">
                    <div class="stat-label">Info</div>
                    <div class="stat-value"><?= $stats['info'] ?></div>
                </div>
                <div class="stat debug">
                    <div class="stat-label">Debug</div>
                    <div class="stat-value"><?= $stats['debug'] ?></div>
                </div>
            </div>
        </header>
        
        <div class="filters">
            <form method="GET" class="filter-row">
                <div class="filter-group">
                    <label>Buscar</label>
                    <input type="text" name="filter" placeholder="Token, URL, erro..." value="<?= htmlspecialchars($filter) ?>" style="min-width: 300px;">
                </div>
                
                <div class="filter-group">
                    <label>Nível</label>
                    <select name="level">
                        <option value="">Todos</option>
                        <option value="ERROR" <?= $level === 'ERROR' ? 'selected' : '' ?>>Erros</option>
                        <option value="WARNING" <?= $level === 'WARNING' ? 'selected' : '' ?>>Avisos</option>
                        <option value="INFO" <?= $level === 'INFO' ? 'selected' : '' ?>>Info</option>
                        <option value="DEBUG" <?= $level === 'DEBUG' ? 'selected' : '' ?>>Debug</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Linhas</label>
                    <select name="lines">
                        <option value="100" <?= $maxLines === 100 ? 'selected' : '' ?>>100</option>
                        <option value="500" <?= $maxLines === 500 ? 'selected' : '' ?>>500</option>
                        <option value="1000" <?= $maxLines === 1000 ? 'selected' : '' ?>>1000</option>
                        <option value="5000" <?= $maxLines === 5000 ? 'selected' : '' ?>>5000</option>
                        <option value="10000" <?= $maxLines === 10000 ? 'selected' : '' ?>>Todos</option>
                    </select>
                </div>
                
                <div class="actions">
                    <button type="submit">🔍 Filtrar</button>
                    <button type="button" class="secondary" onclick="window.location.href='?'">🔄 Limpar</button>
                    <button type="button" class="secondary" onclick="window.location.reload()">♻️ Atualizar</button>
                </div>
            </form>
            
            <div class="filter-row" style="margin-top: 15px;">
                <div class="auto-refresh">
                    <input type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh(this)">
                    <label for="autoRefresh" style="text-transform: none;">Auto-atualizar a cada 5 segundos</label>
                </div>
                <div style="margin-left: auto;">
                    <button class="secondary" onclick="downloadLogs()">💾 Baixar Logs</button>
                </div>
            </div>
        </div>
        
        <div class="logs-container">
            <?php if (empty($logs)): ?>
                <div class="no-logs">
                    <h2>Nenhum log encontrado</h2>
                    <p>Tente ajustar os filtros ou aguarde novas requisições</p>
                </div>
            <?php else: ?>
                <?php foreach ($logs as $log): 
                    $class = '';
                    if (stripos($log, '[ERROR]') !== false) $class = 'error';
                    elseif (stripos($log, '[WARNING]') !== false) $class = 'warning';
                    elseif (stripos($log, '[INFO]') !== false) $class = 'info';
                    elseif (stripos($log, '[DEBUG]') !== false) $class = 'debug';
                ?>
                    <div class="log-line <?= $class ?>"><?= colorizeLog($log) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Arquivo: <?= $logFile ?></p>
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?></p>
            <p>
                <a href="/debug-token.php" style="color: #4ec9b0;">Debug Token</a> | 
                <a href="/test-headers.php" style="color: #4ec9b0;">Test Headers</a> | 
                <a href="/api-test.php" style="color: #4ec9b0;">Test API</a>
            </p>
        </div>
    </div>
    
    <script>
        let autoRefreshInterval = null;
        
        function toggleAutoRefresh(checkbox) {
            if (checkbox.checked) {
                autoRefreshInterval = setInterval(() => {
                    window.location.reload();
                }, 5000);
            } else {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                    autoRefreshInterval = null;
                }
            }
        }
        
        function downloadLogs() {
            const content = document.querySelector('.logs-container').innerText;
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'api-logs-' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.txt';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        // Scroll automático para o topo
        window.scrollTo(0, 0);
    </script>
</body>
</html>
