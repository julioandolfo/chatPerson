<?php
/**
 * Verificação do Sistema de Logs
 */

$checks = [];

// 1. Verificar diretório logs
$logsDir = __DIR__ . '/../logs';
$checks['logs_dir'] = [
    'name' => 'Diretório logs/',
    'path' => $logsDir,
    'exists' => is_dir($logsDir),
    'writable' => is_dir($logsDir) && is_writable($logsDir),
    'permissions' => is_dir($logsDir) ? substr(sprintf('%o', fileperms($logsDir)), -4) : 'N/A'
];

// 2. Verificar arquivo conversas.log
$conversasLog = $logsDir . '/conversas.log';
$checks['conversas_log'] = [
    'name' => 'Arquivo conversas.log',
    'path' => $conversasLog,
    'exists' => file_exists($conversasLog),
    'writable' => file_exists($conversasLog) && is_writable($conversasLog),
    'permissions' => file_exists($conversasLog) ? substr(sprintf('%o', fileperms($conversasLog)), -4) : 'N/A',
    'size' => file_exists($conversasLog) ? filesize($conversasLog) : 0,
    'lines' => file_exists($conversasLog) ? count(file($conversasLog)) : 0
];

// 3. Verificar APP_DEBUG
$checks['app_debug'] = [
    'name' => 'APP_DEBUG',
    'defined' => defined('APP_DEBUG'),
    'value' => defined('APP_DEBUG') ? (APP_DEBUG ? 'true' : 'false') : 'não definido'
];

// 4. Tentar escrever no log
$testMessage = "Teste de escrita em " . date('Y-m-d H:i:s');
$writeSuccess = false;
$writeError = null;

try {
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0777, true);
    }
    
    $result = @file_put_contents($conversasLog, "[" . date('Y-m-d H:i:s') . "] [TEST] $testMessage\n", FILE_APPEND | LOCK_EX);
    $writeSuccess = ($result !== false);
    
    if (!$writeSuccess) {
        $writeError = error_get_last();
    }
} catch (\Exception $e) {
    $writeError = $e->getMessage();
}

$checks['write_test'] = [
    'name' => 'Teste de escrita',
    'success' => $writeSuccess,
    'error' => $writeError
];

// 5. Testar Logger class
$loggerTest = false;
$loggerError = null;

try {
    require_once __DIR__ . '/../app/Helpers/Logger.php';
    \App\Helpers\Logger::info("Teste via Logger class - " . date('Y-m-d H:i:s'), 'conversas.log');
    $loggerTest = true;
} catch (\Exception $e) {
    $loggerError = $e->getMessage();
}

$checks['logger_class'] = [
    'name' => 'Logger class',
    'success' => $loggerTest,
    'error' => $loggerError
];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verificação do Sistema de Logs</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f5f5f5;
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
        }
        .check-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .check-name {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-ok {
            background: #4CAF50;
            color: white;
        }
        .status-error {
            background: #f44336;
            color: white;
        }
        .status-warning {
            background: #ff9800;
            color: white;
        }
        .details {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-left: 3px solid #2196F3;
            font-family: monospace;
            font-size: 12px;
        }
        .details div {
            margin: 5px 0;
        }
        .label {
            font-weight: bold;
            color: #666;
            display: inline-block;
            width: 150px;
        }
        button {
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        button:hover {
            background: #1976D2;
        }
        .btn-view {
            background: #4CAF50;
        }
        .btn-view:hover {
            background: #45a049;
        }
        .actions {
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 Verificação do Sistema de Logs</h1>
    
    <div class="actions">
        <button onclick="location.reload()">🔄 Atualizar</button>
        <button class="btn-view" onclick="location.href='/view-conversas-logs.php'">📋 Ver Logs de Conversas</button>
        <button onclick="location.href='/conversations'">← Voltar</button>
    </div>
    
    <?php foreach ($checks as $key => $check): ?>
        <div class="check-item">
            <div class="check-name">
                <?= htmlspecialchars($check['name']) ?>
                <?php
                if (isset($check['exists'])) {
                    echo $check['exists'] 
                        ? '<span class="status status-ok">✓ Existe</span>' 
                        : '<span class="status status-error">✗ Não existe</span>';
                }
                if (isset($check['writable'])) {
                    echo $check['writable'] 
                        ? '<span class="status status-ok">✓ Gravável</span>' 
                        : '<span class="status status-error">✗ Não gravável</span>';
                }
                if (isset($check['success'])) {
                    echo $check['success'] 
                        ? '<span class="status status-ok">✓ Sucesso</span>' 
                        : '<span class="status status-error">✗ Falhou</span>';
                }
                if (isset($check['defined'])) {
                    echo $check['defined'] 
                        ? '<span class="status status-ok">✓ Definido</span>' 
                        : '<span class="status status-warning">⚠ Não definido</span>';
                }
                ?>
            </div>
            
            <div class="details">
                <?php foreach ($check as $k => $v): ?>
                    <?php if ($k !== 'name'): ?>
                        <div>
                            <span class="label"><?= htmlspecialchars($k) ?>:</span>
                            <?php
                            if (is_bool($v)) {
                                echo $v ? 'Sim' : 'Não';
                            } elseif (is_null($v)) {
                                echo 'NULL';
                            } else {
                                echo htmlspecialchars(print_r($v, true));
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="check-item">
        <div class="check-name">📄 Últimas 10 linhas do log</div>
        <div class="details" style="max-height: 300px; overflow-y: auto;">
            <?php
            if (file_exists($conversasLog)) {
                $lines = file($conversasLog);
                $lastLines = array_slice($lines, -10);
                foreach ($lastLines as $line) {
                    echo htmlspecialchars($line) . "<br>";
                }
            } else {
                echo "<em>Arquivo não existe ainda</em>";
            }
            ?>
        </div>
    </div>
    
    <div class="check-item">
        <div class="check-name">💡 Recomendações</div>
        <div class="details">
            <?php
            $recommendations = [];
            
            if (!$checks['logs_dir']['exists']) {
                $recommendations[] = "❌ Criar diretório logs/: mkdir " . $logsDir;
            }
            
            if (!$checks['logs_dir']['writable']) {
                $recommendations[] = "❌ Dar permissões ao diretório: chmod 0777 " . $logsDir;
            }
            
            if (!$checks['write_test']['success']) {
                $recommendations[] = "❌ Não foi possível escrever no arquivo. Verificar permissões.";
            }
            
            if (!$checks['app_debug']['defined']) {
                $recommendations[] = "⚠️ APP_DEBUG não está definido. Logger::debug() não funcionará. Use Logger::info() ao invés.";
            }
            
            if (empty($recommendations)) {
                echo "<strong style='color: #4CAF50;'>✅ Tudo OK! Sistema de logs funcionando corretamente.</strong>";
            } else {
                foreach ($recommendations as $rec) {
                    echo $rec . "<br><br>";
                }
            }
            ?>
        </div>
    </div>
</body>
</html>

