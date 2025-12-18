<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Logs</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .header {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #007acc;
        }
        h1 {
            margin: 0;
            color: #4ec9b0;
            font-size: 24px;
        }
        .actions {
            margin: 10px 0;
        }
        .btn {
            background: #007acc;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            font-size: 14px;
        }
        .btn:hover {
            background: #005a9e;
        }
        .btn-danger {
            background: #f14c4c;
        }
        .btn-danger:hover {
            background: #c93838;
        }
        .btn-success {
            background: #4ec9b0;
        }
        .btn-success:hover {
            background: #3da88f;
        }
        .log-container {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 5px;
            padding: 15px;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-line {
            padding: 2px 0;
            border-bottom: 1px solid #2d2d30;
        }
        .timestamp {
            color: #608b4e;
        }
        .service {
            color: #4ec9b0;
        }
        .error {
            color: #f14c4c;
            font-weight: bold;
        }
        .success {
            color: #4ec9b0;
        }
        .warning {
            color: #dcdcaa;
        }
        .info {
            color: #9cdcfe;
        }
        .stats {
            background: #252526;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
        }
        .stat {
            flex: 1;
        }
        .stat-label {
            color: #858585;
            font-size: 12px;
        }
        .stat-value {
            color: #4ec9b0;
            font-size: 20px;
            font-weight: bold;
        }
    </style>
    <script>
        function autoRefresh() {
            setInterval(() => {
                location.reload();
            }, 5000);
        }
        
        function clearLogs() {
            if (confirm('Tem certeza que deseja limpar os logs?')) {
                window.location.href = '?action=clear';
            }
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>📊 Dashboard Logs</h1>
        <p style="margin: 5px 0 0 0; color: #858585;">Monitoramento em tempo real</p>
    </div>

<?php
$logFile = __DIR__ . '/../logs/dash.log';

// Ações
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'clear') {
        file_put_contents($logFile, '');
        echo '<div class="header" style="border-left-color: #4ec9b0;">';
        echo '<p style="color: #4ec9b0; margin: 0;">✅ Logs limpos com sucesso!</p>';
        echo '</div>';
        echo '<script>setTimeout(() => window.location.href = "' . $_SERVER['PHP_SELF'] . '", 2000);</script>';
        exit;
    }
}

echo '<div class="actions">';
echo '<a href="' . $_SERVER['PHP_SELF'] . '" class="btn">🔄 Recarregar</a>';
echo '<a href="#" onclick="autoRefresh(); this.style.display=\'none\'; return false;" class="btn btn-success">⏱️ Auto-Refresh (5s)</a>';
echo '<a href="#" onclick="clearLogs(); return false;" class="btn btn-danger">🗑️ Limpar Logs</a>';
echo '<a href="/dashboard" class="btn" style="background: #4ec9b0;">📊 Ir para Dashboard</a>';
echo '</div>';

if (!file_exists($logFile)) {
    echo '<div class="header" style="border-left-color: #dcdcaa;">';
    echo '<p style="color: #dcdcaa; margin: 0;">⚠️ Arquivo de log não existe ainda. Acesse o dashboard para gerar logs.</p>';
    echo '</div>';
    exit;
}

$logContent = file_get_contents($logFile);
$lines = explode("\n", $logContent);
$lines = array_filter($lines); // Remove linhas vazias

if (empty($lines)) {
    echo '<div class="header" style="border-left-color: #dcdcaa;">';
    echo '<p style="color: #dcdcaa; margin: 0;">⚠️ Nenhum log registrado ainda. Acesse o dashboard para gerar logs.</p>';
    echo '</div>';
    exit;
}

// Estatísticas
$totalLines = count($lines);
$errors = 0;
$warnings = 0;
$lastTimestamp = '';

foreach ($lines as $line) {
    if (stripos($line, 'ERRO') !== false || stripos($line, 'ERROR') !== false) {
        $errors++;
    }
    if (stripos($line, 'WARNING') !== false || stripos($line, 'AVISO') !== false) {
        $warnings++;
    }
    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
        $lastTimestamp = $matches[1];
    }
}

echo '<div class="stats">';
echo '<div class="stat">';
echo '<div class="stat-label">Total de Linhas</div>';
echo '<div class="stat-value">' . $totalLines . '</div>';
echo '</div>';
echo '<div class="stat">';
echo '<div class="stat-label">Erros</div>';
echo '<div class="stat-value" style="color: ' . ($errors > 0 ? '#f14c4c' : '#4ec9b0') . ';">' . $errors . '</div>';
echo '</div>';
echo '<div class="stat">';
echo '<div class="stat-label">Avisos</div>';
echo '<div class="stat-value" style="color: ' . ($warnings > 0 ? '#dcdcaa' : '#4ec9b0') . ';">' . $warnings . '</div>';
echo '</div>';
echo '<div class="stat">';
echo '<div class="stat-label">Último Log</div>';
echo '<div class="stat-value" style="font-size: 14px; color: #9cdcfe;">' . ($lastTimestamp ?: 'N/A') . '</div>';
echo '</div>';
echo '</div>';

// Exibir logs (últimas 100 linhas, mais recentes primeiro)
$lines = array_reverse(array_slice($lines, -100));

echo '<div class="log-container">';
foreach ($lines as $line) {
    $cssClass = '';
    
    // Detectar tipo de mensagem
    if (stripos($line, 'ERRO') !== false || stripos($line, 'ERROR') !== false) {
        $cssClass = 'error';
    } elseif (stripos($line, 'WARNING') !== false || stripos($line, 'AVISO') !== false) {
        $cssClass = 'warning';
    } elseif (stripos($line, 'SUCCESS') !== false || stripos($line, 'OK') !== false) {
        $cssClass = 'success';
    } else {
        $cssClass = 'info';
    }
    
    // Colorir timestamp
    $line = preg_replace(
        '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/',
        '<span class="timestamp">[$1]</span>',
        $line
    );
    
    // Colorir [DashboardService]
    $line = str_replace('[DashboardService]', '<span class="service">[DashboardService]</span>', $line);
    
    // Highlight de números
    $line = preg_replace('/=(\d+)/', '=<span style="color: #b5cea8;">$1</span>', $line);
    
    // Highlight de true/false
    $line = preg_replace('/(true|false)/', '<span style="color: #569cd6;">$1</span>', $line);
    
    echo '<div class="log-line ' . $cssClass . '">' . $line . '</div>';
}
echo '</div>';
?>

<div class="header" style="margin-top: 20px; border-left-color: #608b4e;">
    <p style="margin: 0; color: #858585; font-size: 12px;">
        💡 <strong>Dica:</strong> Use Ctrl+F para buscar no log. 
        O arquivo completo está em <code style="color: #ce9178;"><?= $logFile ?></code>
    </p>
</div>

</body>
</html>

