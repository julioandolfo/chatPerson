<?php
/**
 * Visualizador Completo de Logs da API + Diagnóstico de Unificação de Contas
 * Acesse: https://chat.personizi.com.br/view-all-logs.php
 * 
 * Tabs:
 *   - Logs: Visualizador de logs padrão
 *   - Unificação: Diagnóstico do mapeamento whatsapp_accounts <-> integration_accounts
 */

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Tab ativa
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'logs';

// ── Diagnóstico de Unificação ──
$unification = null;
if ($activeTab === 'unificacao') {
    try {
        require_once __DIR__ . '/../config/bootstrap.php';
        $db = \App\Helpers\Database::getInstance();
        
        // 1. Todas as whatsapp_accounts e seus correspondentes em integration_accounts
        $waAccounts = $db->query("
            SELECT wa.id as wa_id, wa.name as wa_name, wa.phone_number as wa_phone, wa.status as wa_status,
                   wa.quepasa_token,
                   ia.id as ia_id, ia.name as ia_name, ia.phone_number as ia_phone, ia.provider as ia_provider,
                   ia.channel as ia_channel, ia.status as ia_status
            FROM whatsapp_accounts wa
            LEFT JOIN integration_accounts ia ON ia.phone_number = wa.phone_number AND ia.channel = 'whatsapp'
            ORDER BY wa.id
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // 2. integration_accounts SEM whatsapp_accounts correspondente
        $iaOrphans = $db->query("
            SELECT ia.id as ia_id, ia.name as ia_name, ia.phone_number as ia_phone, 
                   ia.provider as ia_provider, ia.channel as ia_channel, ia.status as ia_status
            FROM integration_accounts ia
            LEFT JOIN whatsapp_accounts wa ON wa.phone_number = ia.phone_number
            WHERE ia.channel = 'whatsapp' AND wa.id IS NULL
            ORDER BY ia.id
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // 3. Conversas com whatsapp_account_id mas SEM integration_account_id
        $convsSemIntegration = $db->query("
            SELECT c.id as conv_id, c.contact_id, c.channel, c.status,
                   c.whatsapp_account_id, c.integration_account_id,
                   ct.name as contact_name, ct.phone as contact_phone,
                   wa.name as wa_name, wa.phone_number as wa_phone,
                   c.created_at
            FROM conversations c
            LEFT JOIN contacts ct ON ct.id = c.contact_id
            LEFT JOIN whatsapp_accounts wa ON wa.id = c.whatsapp_account_id
            WHERE c.whatsapp_account_id IS NOT NULL AND c.integration_account_id IS NULL
            ORDER BY c.created_at DESC
            LIMIT 100
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // 4. Conversas com DIVERGÊNCIA (integration_account aponta para número diferente do whatsapp_account)
        $convsDivergentes = $db->query("
            SELECT c.id as conv_id, c.contact_id, c.channel, c.status,
                   c.whatsapp_account_id, c.integration_account_id,
                   ct.name as contact_name,
                   wa.phone_number as wa_phone, wa.name as wa_name,
                   ia.phone_number as ia_phone, ia.name as ia_name,
                   c.created_at
            FROM conversations c
            LEFT JOIN contacts ct ON ct.id = c.contact_id
            LEFT JOIN whatsapp_accounts wa ON wa.id = c.whatsapp_account_id
            LEFT JOIN integration_accounts ia ON ia.id = c.integration_account_id
            WHERE c.whatsapp_account_id IS NOT NULL 
              AND c.integration_account_id IS NOT NULL
              AND wa.phone_number IS NOT NULL
              AND ia.phone_number IS NOT NULL
              AND REPLACE(REPLACE(REPLACE(wa.phone_number, '+', ''), ' ', ''), '-', '') 
                  != REPLACE(REPLACE(REPLACE(ia.phone_number, '+', ''), ' ', ''), '-', '')
            ORDER BY c.created_at DESC
            LIMIT 100
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // 5. Totais
        $totalWa = $db->query("SELECT COUNT(*) as c FROM whatsapp_accounts")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalIa = $db->query("SELECT COUNT(*) as c FROM integration_accounts WHERE channel = 'whatsapp'")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalIaAll = $db->query("SELECT COUNT(*) as c FROM integration_accounts")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalConvs = $db->query("SELECT COUNT(*) as c FROM conversations WHERE channel = 'whatsapp'")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalConvsSemIa = $db->query("SELECT COUNT(*) as c FROM conversations WHERE whatsapp_account_id IS NOT NULL AND integration_account_id IS NULL")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalConvsComAmbos = $db->query("SELECT COUNT(*) as c FROM conversations WHERE whatsapp_account_id IS NOT NULL AND integration_account_id IS NOT NULL")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalConvsSoIa = $db->query("SELECT COUNT(*) as c FROM conversations WHERE whatsapp_account_id IS NULL AND integration_account_id IS NOT NULL AND channel = 'whatsapp'")->fetch(\PDO::FETCH_ASSOC)['c'];
        
        $unification = [
            'waAccounts' => $waAccounts,
            'iaOrphans' => $iaOrphans,
            'convsSemIntegration' => $convsSemIntegration,
            'convsDivergentes' => $convsDivergentes,
            'totalWa' => $totalWa,
            'totalIa' => $totalIa,
            'totalIaAll' => $totalIaAll,
            'totalConvs' => $totalConvs,
            'totalConvsSemIa' => $totalConvsSemIa,
            'totalConvsComAmbos' => $totalConvsComAmbos,
            'totalConvsSoIa' => $totalConvsSoIa,
        ];
    } catch (\Exception $e) {
        $unification = ['error' => $e->getMessage()];
    }
}

// ── Ação: Corrigir conversas ──
$fixResult = null;
if (isset($_GET['action']) && $_GET['action'] === 'fix_conversations') {
    try {
        require_once __DIR__ . '/../config/bootstrap.php';
        $db = \App\Helpers\Database::getInstance();
        
        // Atualizar integration_account_id de conversas baseado no phone_number
        $stmt = $db->query("
            UPDATE conversations c
            INNER JOIN whatsapp_accounts wa ON wa.id = c.whatsapp_account_id
            INNER JOIN integration_accounts ia ON ia.phone_number = wa.phone_number AND ia.channel = 'whatsapp'
            SET c.integration_account_id = ia.id
            WHERE c.whatsapp_account_id IS NOT NULL AND c.integration_account_id IS NULL
        ");
        $fixResult = ['success' => true, 'affected' => $stmt->rowCount()];
    } catch (\Exception $e) {
        $fixResult = ['success' => false, 'error' => $e->getMessage()];
    }
}

// ── Ação: Corrigir divergências ──
$fixDivResult = null;
if (isset($_GET['action']) && $_GET['action'] === 'fix_divergencias') {
    try {
        require_once __DIR__ . '/../config/bootstrap.php';
        $db = \App\Helpers\Database::getInstance();
        
        // Corrigir integration_account_id para apontar para o mesmo número do whatsapp_account
        $stmt = $db->query("
            UPDATE conversations c
            INNER JOIN whatsapp_accounts wa ON wa.id = c.whatsapp_account_id
            INNER JOIN integration_accounts ia_correct ON ia_correct.phone_number = wa.phone_number AND ia_correct.channel = 'whatsapp'
            INNER JOIN integration_accounts ia_wrong ON ia_wrong.id = c.integration_account_id
            SET c.integration_account_id = ia_correct.id
            WHERE c.whatsapp_account_id IS NOT NULL 
              AND c.integration_account_id IS NOT NULL
              AND REPLACE(REPLACE(REPLACE(wa.phone_number, '+', ''), ' ', ''), '-', '') 
                  != REPLACE(REPLACE(REPLACE(ia_wrong.phone_number, '+', ''), ' ', ''), '-', '')
        ");
        $fixDivResult = ['success' => true, 'affected' => $stmt->rowCount()];
    } catch (\Exception $e) {
        $fixDivResult = ['success' => false, 'error' => $e->getMessage()];
    }
}

// ── Diagnóstico de Automação ──
$automationData = null;
if ($activeTab === 'automacao') {
    try {
        require_once __DIR__ . '/../config/bootstrap.php';
        $db = \App\Helpers\Database::getInstance();
        
        // 1. Todas as automações com detalhes
        $automations = $db->query("
            SELECT a.*, 
                   f.name as funnel_name, 
                   fs.name as stage_name,
                   (SELECT COUNT(*) FROM automation_nodes an WHERE an.automation_id = a.id) as total_nodes
            FROM automations a
            LEFT JOIN funnels f ON a.funnel_id = f.id
            LEFT JOIN funnel_stages fs ON a.stage_id = fs.id
            ORDER BY a.status ASC, a.is_active DESC, a.updated_at DESC
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // 2. Últimas execuções de automação
        $recentExecutions = $db->query("
            SELECT ae.*, 
                   a.name as automation_name, 
                   a.trigger_type,
                   c.contact_id,
                   ct.name as contact_name,
                   ct.phone as contact_phone,
                   COALESCE(ia.name, wa.name) as account_name,
                   COALESCE(ia.phone_number, wa.phone_number) as account_phone
            FROM automation_executions ae
            LEFT JOIN automations a ON ae.automation_id = a.id
            LEFT JOIN conversations c ON ae.conversation_id = c.id
            LEFT JOIN contacts ct ON c.contact_id = ct.id
            LEFT JOIN integration_accounts ia ON c.integration_account_id = ia.id
            LEFT JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id AND c.integration_account_id IS NULL
            ORDER BY ae.created_at DESC
            LIMIT 50
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // 3. Totais
        $totalAutomations = count($automations);
        $totalActive = 0;
        $totalInactive = 0;
        foreach ($automations as $a) {
            if ($a['status'] === 'active' && $a['is_active']) {
                $totalActive++;
            } else {
                $totalInactive++;
            }
        }
        
        $totalExecutions = $db->query("SELECT COUNT(*) as c FROM automation_executions")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalExecutionsToday = $db->query("SELECT COUNT(*) as c FROM automation_executions WHERE DATE(created_at) = CURDATE()")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalFailed = $db->query("SELECT COUNT(*) as c FROM automation_executions WHERE status = 'failed'")->fetch(\PDO::FETCH_ASSOC)['c'];
        $totalFailedToday = $db->query("SELECT COUNT(*) as c FROM automation_executions WHERE status = 'failed' AND DATE(created_at) = CURDATE()")->fetch(\PDO::FETCH_ASSOC)['c'];
        
        // 4. Mapeamento de accounts para referência
        $allIntegrationAccounts = $db->query("
            SELECT id, name, phone_number, channel, status FROM integration_accounts ORDER BY id
        ")->fetchAll(\PDO::FETCH_ASSOC);
        $allWhatsappAccounts = $db->query("
            SELECT id, name, phone_number, status FROM whatsapp_accounts ORDER BY id
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Indexar por ID para lookup rápido
        $iaById = [];
        foreach ($allIntegrationAccounts as $ia) {
            $iaById[$ia['id']] = $ia;
        }
        $waById = [];
        foreach ($allWhatsappAccounts as $wa) {
            $waById[$wa['id']] = $wa;
        }
        
        $automationData = [
            'automations' => $automations,
            'recentExecutions' => $recentExecutions,
            'totalAutomations' => $totalAutomations,
            'totalActive' => $totalActive,
            'totalInactive' => $totalInactive,
            'totalExecutions' => $totalExecutions,
            'totalExecutionsToday' => $totalExecutionsToday,
            'totalFailed' => $totalFailed,
            'totalFailedToday' => $totalFailedToday,
            'iaById' => $iaById,
            'waById' => $waById,
        ];
    } catch (\Exception $e) {
        $automationData = ['error' => $e->getMessage()];
    }
}

// ── Logs ──
$logFileMap = [
    'logs' => __DIR__ . '/../storage/logs/api.log',
    'automacao' => __DIR__ . '/../storage/logs/automacao.log',
    'quepasa' => __DIR__ . '/../storage/logs/quepasa.log',
    'conversas' => __DIR__ . '/../storage/logs/conversas.log',
];
$logFile = $logFileMap[$activeTab] ?? $logFileMap['logs'];
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
        
        /* Tabs */
        .tabs { display: flex; gap: 0; margin-bottom: 20px; }
        .tab { padding: 12px 24px; background: #2d2d30; color: #858585; cursor: pointer; 
               border: 1px solid #3c3c3c; border-bottom: none; border-radius: 8px 8px 0 0;
               text-decoration: none; font-family: inherit; font-size: 14px; font-weight: bold; }
        .tab:hover { background: #3c3c3c; color: #d4d4d4; }
        .tab.active { background: #1e1e1e; color: #4ec9b0; border-color: #4ec9b0; border-bottom: 2px solid #1e1e1e; }
        
        /* Diagnóstico */
        .diag-section { background: #252526; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #007acc; }
        .diag-section.warning { border-left-color: #dcdcaa; }
        .diag-section.danger { border-left-color: #f48771; }
        .diag-section.success { border-left-color: #4ec9b0; }
        .diag-section h2 { color: #fff; font-size: 18px; margin-bottom: 15px; }
        .diag-section h3 { color: #9cdcfe; font-size: 14px; margin-bottom: 10px; }
        
        .diag-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .diag-table th { background: #2d2d30; color: #9cdcfe; padding: 8px 12px; text-align: left; 
                         border-bottom: 2px solid #4ec9b0; white-space: nowrap; }
        .diag-table td { padding: 6px 12px; border-bottom: 1px solid #3c3c3c; color: #d4d4d4; }
        .diag-table tr:hover td { background: #2d2d30; }
        
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-ok { background: #4ec9b0; color: #1e1e1e; }
        .badge-miss { background: #f48771; color: #1e1e1e; }
        .badge-warn { background: #dcdcaa; color: #1e1e1e; }
        .badge-na { background: #555; color: #ccc; }
        
        .big-number { font-size: 36px; font-weight: bold; margin: 5px 0; }
        .big-number.green { color: #4ec9b0; }
        .big-number.red { color: #f48771; }
        .big-number.yellow { color: #dcdcaa; }
        .big-number.blue { color: #9cdcfe; }
        
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .grid-card { background: #2d2d30; border-radius: 8px; padding: 15px; text-align: center; }
        .grid-card .label { color: #858585; font-size: 12px; text-transform: uppercase; }
        
        .fix-btn { background: #4ec9b0; color: #1e1e1e; padding: 8px 16px; border: none; border-radius: 6px;
                   cursor: pointer; font-weight: bold; font-size: 13px; text-decoration: none; display: inline-block; margin: 5px 0; }
        .fix-btn:hover { background: #3db89d; }
        .fix-btn.danger { background: #f48771; }
        .fix-btn.danger:hover { background: #d3735f; }
        
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; }
        .alert-success { background: rgba(78,201,176,0.15); border: 1px solid #4ec9b0; color: #4ec9b0; }
        .alert-error { background: rgba(244,135,113,0.15); border: 1px solid #f48771; color: #f48771; }
        
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
        <div class="tabs">
            <a href="?tab=logs" class="tab <?= $activeTab === 'logs' ? 'active' : '' ?>">📋 Logs API</a>
            <a href="?tab=automacao" class="tab <?= $activeTab === 'automacao' ? 'active' : '' ?>">🤖 Automações</a>
            <a href="?tab=unificacao" class="tab <?= $activeTab === 'unificacao' ? 'active' : '' ?>">🔗 Unificação Contas</a>
        </div>
        
        <?php if ($activeTab === 'unificacao'): ?>
        <!-- ═══════════════ ABA UNIFICAÇÃO ═══════════════ -->
        <?php if ($fixResult): ?>
            <div class="alert <?= $fixResult['success'] ? 'alert-success' : 'alert-error' ?>">
                <?php if ($fixResult['success']): ?>
                    ✅ <?= $fixResult['affected'] ?> conversa(s) corrigida(s) com integration_account_id!
                <?php else: ?>
                    ❌ Erro: <?= htmlspecialchars($fixResult['error']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($fixDivResult): ?>
            <div class="alert <?= $fixDivResult['success'] ? 'alert-success' : 'alert-error' ?>">
                <?php if ($fixDivResult['success']): ?>
                    ✅ <?= $fixDivResult['affected'] ?> conversa(s) com divergência corrigida(s)!
                <?php else: ?>
                    ❌ Erro: <?= htmlspecialchars($fixDivResult['error']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($unification['error'])): ?>
            <div class="alert alert-error">❌ Erro ao conectar ao banco: <?= htmlspecialchars($unification['error']) ?></div>
        <?php elseif ($unification): ?>
        
        <header>
            <h1>🔗 Diagnóstico de Unificação - whatsapp_accounts → integration_accounts</h1>
            <p style="color: #858585; margin-top: 5px;">Objetivo: migrar tudo para integration_accounts e parar de usar whatsapp_accounts</p>
        </header>
        
        <!-- Resumo Geral -->
        <div class="grid-4">
            <div class="grid-card">
                <div class="label">WhatsApp Accounts</div>
                <div class="big-number blue"><?= $unification['totalWa'] ?></div>
                <div class="label">tabela legada</div>
            </div>
            <div class="grid-card">
                <div class="label">Integration Accounts (WA)</div>
                <div class="big-number green"><?= $unification['totalIa'] ?></div>
                <div class="label">de <?= $unification['totalIaAll'] ?> total</div>
            </div>
            <div class="grid-card">
                <div class="label">Conversas sem integration_id</div>
                <div class="big-number <?= $unification['totalConvsSemIa'] > 0 ? 'red' : 'green' ?>"><?= $unification['totalConvsSemIa'] ?></div>
                <div class="label">precisam correção</div>
            </div>
            <div class="grid-card">
                <div class="label">Conversas divergentes</div>
                <div class="big-number <?= count($unification['convsDivergentes']) > 0 ? 'red' : 'green' ?>"><?= count($unification['convsDivergentes']) ?></div>
                <div class="label">wa ≠ ia phone</div>
            </div>
            <div class="grid-card">
                <div class="label">Conversas com ambos IDs</div>
                <div class="big-number green"><?= $unification['totalConvsComAmbos'] ?></div>
                <div class="label">OK (prontas)</div>
            </div>
            <div class="grid-card">
                <div class="label">Conversas só integration_id</div>
                <div class="big-number green"><?= $unification['totalConvsSoIa'] ?></div>
                <div class="label">já migradas</div>
            </div>
        </div>
        
        <!-- Barra de progresso -->
        <?php 
            $totalMigradas = $unification['totalConvsComAmbos'] + $unification['totalConvsSoIa'];
            $totalWaConvs = $unification['totalConvsSemIa'] + $unification['totalConvsComAmbos'];
            $progressPct = $totalWaConvs > 0 ? round(($totalMigradas / ($totalMigradas + $unification['totalConvsSemIa'])) * 100) : 100;
        ?>
        <div class="diag-section success">
            <h2>Progresso da Unificação: <?= $progressPct ?>%</h2>
            <div style="background: #3c3c3c; border-radius: 8px; height: 24px; overflow: hidden;">
                <div style="background: #4ec9b0; height: 100%; width: <?= $progressPct ?>%; transition: width 0.3s; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #1e1e1e; font-weight: bold; font-size: 12px;">
                    <?= $progressPct ?>%
                </div>
            </div>
            <p style="color: #858585; margin-top: 8px; font-size: 12px;">
                <?= $totalMigradas ?> conversas já com integration_account_id | <?= $unification['totalConvsSemIa'] ?> aguardando
            </p>
        </div>
        
        <!-- 1. Mapeamento de Contas -->
        <div class="diag-section">
            <h2>📱 Mapeamento: whatsapp_accounts → integration_accounts</h2>
            <table class="diag-table">
                <thead>
                    <tr>
                        <th>WA ID</th>
                        <th>Nome (WA)</th>
                        <th>Telefone</th>
                        <th>Status WA</th>
                        <th>→</th>
                        <th>IA ID</th>
                        <th>Nome (IA)</th>
                        <th>Provider</th>
                        <th>Status IA</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unification['waAccounts'] as $row): ?>
                    <tr>
                        <td><strong><?= $row['wa_id'] ?></strong></td>
                        <td><?= htmlspecialchars($row['wa_name'] ?? '-') ?></td>
                        <td style="color: #4ec9b0;"><?= htmlspecialchars($row['wa_phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['wa_status'] ?? '-') ?></td>
                        <td style="color: #555;">→</td>
                        <td><strong><?= $row['ia_id'] ?? '<span style="color:#f48771">NULL</span>' ?></strong></td>
                        <td><?= htmlspecialchars($row['ia_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['ia_provider'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['ia_status'] ?? '-') ?></td>
                        <td>
                            <?php if ($row['ia_id']): ?>
                                <span class="badge badge-ok">VINCULADO</span>
                            <?php else: ?>
                                <span class="badge badge-miss">SEM VÍNCULO</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 2. Integration Accounts órfãs -->
        <?php if (!empty($unification['iaOrphans'])): ?>
        <div class="diag-section warning">
            <h2>⚠️ Integration Accounts SEM whatsapp_account correspondente (<?= count($unification['iaOrphans']) ?>)</h2>
            <p style="color: #858585; font-size: 12px; margin-bottom: 10px;">Estas contas existem apenas em integration_accounts. Se foram criadas pelo Notificame ou outra integração, está correto.</p>
            <table class="diag-table">
                <thead>
                    <tr><th>IA ID</th><th>Nome</th><th>Telefone</th><th>Provider</th><th>Channel</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($unification['iaOrphans'] as $row): ?>
                    <tr>
                        <td><strong><?= $row['ia_id'] ?></strong></td>
                        <td><?= htmlspecialchars($row['ia_name'] ?? '-') ?></td>
                        <td style="color: #dcdcaa;"><?= htmlspecialchars($row['ia_phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['ia_provider'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['ia_channel'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['ia_status'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- 3. Conversas sem integration_account_id -->
        <div class="diag-section <?= $unification['totalConvsSemIa'] > 0 ? 'danger' : 'success' ?>">
            <h2><?= $unification['totalConvsSemIa'] > 0 ? '❌' : '✅' ?> Conversas sem integration_account_id (<?= $unification['totalConvsSemIa'] ?>)</h2>
            <?php if ($unification['totalConvsSemIa'] > 0): ?>
                <p style="color: #858585; font-size: 12px; margin-bottom: 10px;">Estas conversas usam apenas whatsapp_account_id. Precisam de integration_account_id para envio correto.</p>
                <a href="?tab=unificacao&action=fix_conversations" class="fix-btn" 
                   onclick="return confirm('Isso vai atualizar <?= $unification['totalConvsSemIa'] ?> conversa(s). Continuar?')">
                    🔧 Corrigir Agora (preencher integration_account_id)
                </a>
                <table class="diag-table" style="margin-top: 15px;">
                    <thead>
                        <tr><th>Conv ID</th><th>Contato</th><th>Tel Contato</th><th>WA ID</th><th>Número WA</th><th>Status</th><th>Criada em</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($unification['convsSemIntegration'], 0, 30) as $row): ?>
                        <tr>
                            <td><strong><?= $row['conv_id'] ?></strong></td>
                            <td><?= htmlspecialchars($row['contact_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['contact_phone'] ?? '-') ?></td>
                            <td><?= $row['whatsapp_account_id'] ?></td>
                            <td style="color: #f48771;"><?= htmlspecialchars($row['wa_phone'] ?? '-') ?></td>
                            <td><?= $row['status'] ?></td>
                            <td style="color: #858585;"><?= $row['created_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($unification['totalConvsSemIa'] > 30): ?>
                        <tr><td colspan="7" style="text-align: center; color: #858585;">... e mais <?= $unification['totalConvsSemIa'] - 30 ?> conversas</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #4ec9b0;">Todas as conversas já possuem integration_account_id!</p>
            <?php endif; ?>
        </div>
        
        <!-- 4. Conversas com divergência -->
        <div class="diag-section <?= count($unification['convsDivergentes']) > 0 ? 'danger' : 'success' ?>">
            <h2><?= count($unification['convsDivergentes']) > 0 ? '⚠️' : '✅' ?> Conversas com DIVERGÊNCIA de número (<?= count($unification['convsDivergentes']) ?>)</h2>
            <?php if (!empty($unification['convsDivergentes'])): ?>
                <p style="color: #858585; font-size: 12px; margin-bottom: 10px;">
                    O whatsapp_account aponta para um número e o integration_account aponta para OUTRO. 
                    <strong style="color: #f48771;">Isso causa envio pelo número errado!</strong>
                </p>
                <a href="?tab=unificacao&action=fix_divergencias" class="fix-btn danger"
                   onclick="return confirm('Isso vai corrigir <?= count($unification['convsDivergentes']) ?> conversa(s) divergentes. Continuar?')">
                    🔧 Corrigir Divergências (alinhar integration_account_id com whatsapp_account)
                </a>
                <table class="diag-table" style="margin-top: 15px;">
                    <thead>
                        <tr><th>Conv ID</th><th>Contato</th><th>WA ID</th><th>Num WA</th><th>→</th><th>IA ID</th><th>Num IA</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unification['convsDivergentes'] as $row): ?>
                        <tr>
                            <td><strong><?= $row['conv_id'] ?></strong></td>
                            <td><?= htmlspecialchars($row['contact_name'] ?? '-') ?></td>
                            <td><?= $row['whatsapp_account_id'] ?></td>
                            <td style="color: #4ec9b0;"><?= htmlspecialchars($row['wa_phone'] ?? '-') ?></td>
                            <td style="color: #f48771; font-weight: bold;">≠</td>
                            <td><?= $row['integration_account_id'] ?></td>
                            <td style="color: #f48771;"><?= htmlspecialchars($row['ia_phone'] ?? '-') ?></td>
                            <td><?= $row['status'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #4ec9b0;">Nenhuma divergência encontrada! Todos os números estão alinhados.</p>
            <?php endif; ?>
        </div>
        
        <!-- 5. Automações e seus trigger_config -->
        <?php
            $automations = [];
            try {
                $automations = $db->query("
                    SELECT a.id, a.name, a.trigger_type, a.trigger_config, a.status, a.is_active,
                           f.name as funnel_name
                    FROM automations a
                    LEFT JOIN funnels f ON a.funnel_id = f.id
                    WHERE a.trigger_config IS NOT NULL 
                      AND a.trigger_config != ''
                      AND a.trigger_config != '{}'
                    ORDER BY a.is_active DESC, a.id
                ")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {}
        ?>
        <div class="diag-section">
            <h2>⚡ Automações e Contas Configuradas (<?= count($automations) ?>)</h2>
            <p style="color: #858585; font-size: 12px; margin-bottom: 10px;">
                Mostra quais integration_account_id e/ou whatsapp_account_id cada automação usa para filtrar.
                Se o ID estiver errado, a automação não dispara para a conversa certa.
            </p>
            <table class="diag-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Trigger</th>
                        <th>Ativa</th>
                        <th>integration_account_ids (config)</th>
                        <th>whatsapp_account_ids (config)</th>
                        <th>Números Correspondentes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($automations as $auto): 
                        $config = json_decode($auto['trigger_config'], true) ?? [];
                        $intIds = $config['integration_account_ids'] ?? (isset($config['integration_account_id']) ? [$config['integration_account_id']] : []);
                        $waIds = $config['whatsapp_account_ids'] ?? (isset($config['whatsapp_account_id']) ? [$config['whatsapp_account_id']] : []);
                        $intIds = array_filter($intIds);
                        $waIds = array_filter($waIds);
                        
                        // Buscar números reais
                        $intPhones = [];
                        foreach ($intIds as $iid) {
                            try {
                                $ia = $db->query("SELECT id, name, phone_number FROM integration_accounts WHERE id = " . intval($iid))->fetch(\PDO::FETCH_ASSOC);
                                $intPhones[] = $ia ? "{$ia['name']} ({$ia['phone_number']})" : "ID {$iid} NÃO ENCONTRADO";
                            } catch (\Exception $e) { $intPhones[] = "Erro"; }
                        }
                        $waPhones = [];
                        foreach ($waIds as $wid) {
                            try {
                                $wa = $db->query("SELECT id, name, phone_number FROM whatsapp_accounts WHERE id = " . intval($wid))->fetch(\PDO::FETCH_ASSOC);
                                $waPhones[] = $wa ? "{$wa['name']} ({$wa['phone_number']})" : "ID {$wid} NÃO ENCONTRADO";
                            } catch (\Exception $e) { $waPhones[] = "Erro"; }
                        }
                    ?>
                    <tr>
                        <td><strong><?= $auto['id'] ?></strong></td>
                        <td><?= htmlspecialchars($auto['name']) ?></td>
                        <td><span class="badge badge-na"><?= $auto['trigger_type'] ?></span></td>
                        <td><?= $auto['is_active'] ? '<span class="badge badge-ok">SIM</span>' : '<span class="badge badge-miss">NÃO</span>' ?></td>
                        <td>
                            <?php if (empty($intIds)): ?>
                                <span style="color: #858585;">-</span>
                            <?php else: ?>
                                <?= implode(', ', array_map('intval', $intIds)) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($waIds)): ?>
                                <span style="color: #858585;">-</span>
                            <?php else: ?>
                                <?= implode(', ', array_map('intval', $waIds)) ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 11px;">
                            <?php if (!empty($intPhones)): ?>
                                <span style="color: #4ec9b0;">IA:</span> <?= htmlspecialchars(implode(' | ', $intPhones)) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($waPhones)): ?>
                                <span style="color: #9cdcfe;">WA:</span> <?= htmlspecialchars(implode(' | ', $waPhones)) ?>
                            <?php endif; ?>
                            <?php if (empty($intPhones) && empty($waPhones)): ?>
                                <span style="color: #858585;">Qualquer conta</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- SQL de referência -->
        <div class="diag-section">
            <h2>📝 SQL Manual (se necessário)</h2>
            <h3>Preencher integration_account_id faltantes:</h3>
            <pre style="background: #1e1e1e; padding: 12px; border-radius: 6px; color: #ce9178; font-size: 12px; overflow-x: auto;">UPDATE conversations c
INNER JOIN whatsapp_accounts wa ON wa.id = c.whatsapp_account_id
INNER JOIN integration_accounts ia ON ia.phone_number = wa.phone_number AND ia.channel = 'whatsapp'
SET c.integration_account_id = ia.id
WHERE c.whatsapp_account_id IS NOT NULL AND c.integration_account_id IS NULL;</pre>
            
            <h3 style="margin-top: 15px;">Corrigir divergências:</h3>
            <pre style="background: #1e1e1e; padding: 12px; border-radius: 6px; color: #ce9178; font-size: 12px; overflow-x: auto;">UPDATE conversations c
INNER JOIN whatsapp_accounts wa ON wa.id = c.whatsapp_account_id
INNER JOIN integration_accounts ia_correct ON ia_correct.phone_number = wa.phone_number AND ia_correct.channel = 'whatsapp'
SET c.integration_account_id = ia_correct.id
WHERE c.whatsapp_account_id IS NOT NULL 
  AND c.integration_account_id IS NOT NULL
  AND c.integration_account_id != ia_correct.id;</pre>
        </div>
        
        <?php endif; // unification ?>
        
        <div class="footer">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <?php elseif ($activeTab === 'automacao'): ?>
        <!-- ═══════════════ ABA AUTOMAÇÃO ═══════════════ -->
        
        <?php if (isset($automationData['error'])): ?>
            <div class="alert alert-error">❌ Erro ao carregar dados: <?= htmlspecialchars($automationData['error']) ?></div>
        <?php else: ?>
        
        <header>
            <h1>🤖 Diagnóstico de Automações</h1>
            <p style="color: #858585; font-size: 13px;">Visualize todas as automações, suas configurações de contas/triggers e execuções recentes.</p>
        </header>
        
        <!-- Resumo -->
        <div class="grid-4">
            <div class="grid-card">
                <div class="label">Total Automações</div>
                <div class="big-number blue"><?= $automationData['totalAutomations'] ?></div>
            </div>
            <div class="grid-card">
                <div class="label">Ativas</div>
                <div class="big-number green"><?= $automationData['totalActive'] ?></div>
            </div>
            <div class="grid-card">
                <div class="label">Inativas</div>
                <div class="big-number yellow"><?= $automationData['totalInactive'] ?></div>
            </div>
            <div class="grid-card">
                <div class="label">Execuções Hoje</div>
                <div class="big-number green"><?= $automationData['totalExecutionsToday'] ?></div>
            </div>
            <div class="grid-card">
                <div class="label">Execuções Total</div>
                <div class="big-number blue"><?= $automationData['totalExecutions'] ?></div>
            </div>
            <div class="grid-card">
                <div class="label">Falhas Hoje</div>
                <div class="big-number <?= $automationData['totalFailedToday'] > 0 ? 'red' : 'green' ?>"><?= $automationData['totalFailedToday'] ?></div>
            </div>
            <div class="grid-card">
                <div class="label">Falhas Total</div>
                <div class="big-number <?= $automationData['totalFailed'] > 0 ? 'red' : 'green' ?>"><?= $automationData['totalFailed'] ?></div>
            </div>
        </div>
        
        <!-- Lista de Automações -->
        <div class="diag-section">
            <h2>📋 Todas as Automações (<?= $automationData['totalAutomations'] ?>)</h2>
            <p style="color: #858585; font-size: 12px; margin-bottom: 15px;">
                Detalhes de cada automação, incluindo trigger_config com IDs de contas configuradas e seus telefones correspondentes.
            </p>
            
            <?php if (empty($automationData['automations'])): ?>
                <p style="color: #858585;">Nenhuma automação cadastrada.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                <table class="diag-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Trigger</th>
                            <th>Status</th>
                            <th>Funil / Etapa</th>
                            <th>Nós</th>
                            <th>Contas Configuradas (trigger_config)</th>
                            <th>Atualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($automationData['automations'] as $auto): 
                            $isActive = ($auto['status'] === 'active' && $auto['is_active']);
                            $config = !empty($auto['trigger_config']) ? json_decode($auto['trigger_config'], true) : [];
                            
                            // Resolver IDs de contas configuradas
                            $configuredAccounts = [];
                            
                            // integration_account_ids (array)
                            if (!empty($config['integration_account_ids']) && is_array($config['integration_account_ids'])) {
                                foreach ($config['integration_account_ids'] as $iaId) {
                                    $ia = $automationData['iaById'][$iaId] ?? null;
                                    $configuredAccounts[] = [
                                        'type' => 'IA',
                                        'id' => $iaId,
                                        'name' => $ia ? $ia['name'] : '???',
                                        'phone' => $ia ? $ia['phone_number'] : '???',
                                        'status' => $ia ? $ia['status'] : 'unknown',
                                    ];
                                }
                            }
                            // integration_account_id (single)
                            if (!empty($config['integration_account_id'])) {
                                $iaId = $config['integration_account_id'];
                                $ia = $automationData['iaById'][$iaId] ?? null;
                                $configuredAccounts[] = [
                                    'type' => 'IA',
                                    'id' => $iaId,
                                    'name' => $ia ? $ia['name'] : '???',
                                    'phone' => $ia ? $ia['phone_number'] : '???',
                                    'status' => $ia ? $ia['status'] : 'unknown',
                                ];
                            }
                            // whatsapp_account_ids (array)
                            if (!empty($config['whatsapp_account_ids']) && is_array($config['whatsapp_account_ids'])) {
                                foreach ($config['whatsapp_account_ids'] as $waId) {
                                    $wa = $automationData['waById'][$waId] ?? null;
                                    $configuredAccounts[] = [
                                        'type' => 'WA',
                                        'id' => $waId,
                                        'name' => $wa ? $wa['name'] : '???',
                                        'phone' => $wa ? $wa['phone_number'] : '???',
                                        'status' => $wa ? $wa['status'] : 'unknown',
                                    ];
                                }
                            }
                            // whatsapp_account_id (single)
                            if (!empty($config['whatsapp_account_id'])) {
                                $waId = $config['whatsapp_account_id'];
                                $wa = $automationData['waById'][$waId] ?? null;
                                $configuredAccounts[] = [
                                    'type' => 'WA',
                                    'id' => $waId,
                                    'name' => $wa ? $wa['name'] : '???',
                                    'phone' => $wa ? $wa['phone_number'] : '???',
                                    'status' => $wa ? $wa['status'] : 'unknown',
                                ];
                            }
                            
                            // Outros campos do config
                            $otherConfig = array_diff_key($config, array_flip([
                                'integration_account_ids', 'integration_account_id', 
                                'whatsapp_account_ids', 'whatsapp_account_id'
                            ]));
                        ?>
                        <tr style="<?= !$isActive ? 'opacity: 0.5;' : '' ?>">
                            <td><?= $auto['id'] ?></td>
                            <td><strong style="color: #fff;"><?= htmlspecialchars($auto['name']) ?></strong></td>
                            <td>
                                <span class="badge" style="background: #007acc; color: #fff;"><?= htmlspecialchars($auto['trigger_type']) ?></span>
                                <?php if (!empty($config['keyword'])): ?>
                                    <br><small style="color: #ce9178;">keyword: "<?= htmlspecialchars($config['keyword']) ?>"</small>
                                <?php endif; ?>
                                <?php if (!empty($config['channel'])): ?>
                                    <br><small style="color: #9cdcfe;">channel: <?= htmlspecialchars($config['channel']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge badge-ok">ATIVO</span>
                                <?php else: ?>
                                    <span class="badge badge-miss">INATIVO</span>
                                    <br><small style="color: #858585;">status=<?= $auto['status'] ?>, is_active=<?= $auto['is_active'] ? 'true' : 'false' ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($auto['funnel_name'])): ?>
                                    <?= htmlspecialchars($auto['funnel_name']) ?>
                                    <?php if (!empty($auto['stage_name'])): ?>
                                        <br><small style="color: #858585;">→ <?= htmlspecialchars($auto['stage_name']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #555;">Todos</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;"><?= $auto['total_nodes'] ?></td>
                            <td>
                                <?php if (empty($configuredAccounts)): ?>
                                    <span style="color: #4ec9b0;">🌐 Todas as contas</span>
                                    <?php if (!empty($otherConfig)): ?>
                                        <br><small style="color: #858585;">config: <?= htmlspecialchars(json_encode($otherConfig, JSON_UNESCAPED_UNICODE)) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php foreach ($configuredAccounts as $ca): ?>
                                        <div style="margin: 2px 0;">
                                            <span class="badge <?= $ca['type'] === 'IA' ? 'badge-ok' : 'badge-warn' ?>"><?= $ca['type'] ?> #<?= $ca['id'] ?></span>
                                            <span style="color: #d4d4d4;"><?= htmlspecialchars($ca['name']) ?></span>
                                            <span style="color: #858585;">(<?= $ca['phone'] ?>)</span>
                                            <?php if ($ca['status'] !== 'active'): ?>
                                                <span class="badge badge-miss"><?= $ca['status'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!empty($otherConfig)): ?>
                                        <small style="color: #858585;">+ <?= htmlspecialchars(json_encode($otherConfig, JSON_UNESCAPED_UNICODE)) ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="color: #858585; font-size: 12px; white-space: nowrap;"><?= $auto['updated_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Últimas Execuções -->
        <div class="diag-section <?= $automationData['totalFailedToday'] > 0 ? 'danger' : 'success' ?>">
            <h2>🕐 Últimas 50 Execuções de Automação</h2>
            <p style="color: #858585; font-size: 12px; margin-bottom: 15px;">
                Histórico recente de execuções. Verifique se a automação esperada aparece aqui ou não.
            </p>
            
            <?php if (empty($automationData['recentExecutions'])): ?>
                <p style="color: #858585;">Nenhuma execução registrada.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                <table class="diag-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Automação</th>
                            <th>Trigger</th>
                            <th>Conversa</th>
                            <th>Contato</th>
                            <th>Conta WhatsApp</th>
                            <th>Status</th>
                            <th>Erro</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($automationData['recentExecutions'] as $exec): ?>
                        <tr>
                            <td><?= $exec['id'] ?></td>
                            <td>
                                <strong style="color: #fff;"><?= htmlspecialchars($exec['automation_name'] ?? 'ID ' . $exec['automation_id']) ?></strong>
                                <br><small style="color: #858585;">auto #<?= $exec['automation_id'] ?></small>
                            </td>
                            <td>
                                <span class="badge" style="background: #007acc; color: #fff;"><?= htmlspecialchars($exec['trigger_type'] ?? '?') ?></span>
                            </td>
                            <td style="text-align: center;">#<?= $exec['conversation_id'] ?></td>
                            <td>
                                <?= htmlspecialchars($exec['contact_name'] ?? '?') ?>
                                <?php if (!empty($exec['contact_phone'])): ?>
                                    <br><small style="color: #858585;"><?= $exec['contact_phone'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($exec['account_name'] ?? '?') ?>
                                <?php if (!empty($exec['account_phone'])): ?>
                                    <br><small style="color: #858585;"><?= $exec['account_phone'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $statusClass = 'badge-ok';
                                if ($exec['status'] === 'failed') $statusClass = 'badge-miss';
                                elseif ($exec['status'] === 'running') $statusClass = 'badge-warn';
                                elseif ($exec['status'] === 'pending') $statusClass = 'badge-na';
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= strtoupper($exec['status']) ?></span>
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php if (!empty($exec['error_message'])): ?>
                                    <span style="color: #f48771;" title="<?= htmlspecialchars($exec['error_message']) ?>">
                                        <?= htmlspecialchars(substr($exec['error_message'], 0, 80)) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #555;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #858585; font-size: 12px; white-space: nowrap;"><?= $exec['created_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Checklist de Debug -->
        <div class="diag-section warning">
            <h2>🔍 Checklist: Por que minha automação não executou?</h2>
            <div style="line-height: 2; font-size: 13px;">
                <p>Se uma automação não está executando, verifique os seguintes pontos:</p>
                <ol style="padding-left: 20px; color: #d4d4d4;">
                    <li><strong style="color: #4ec9b0;">Status ativo?</strong> — A automação precisa ter <code style="color: #ce9178;">status='active'</code> E <code style="color: #ce9178;">is_active=TRUE</code></li>
                    <li><strong style="color: #4ec9b0;">Trigger type correto?</strong> — 
                        <code style="color: #ce9178;">new_conversation</code> = nova conversa criada, 
                        <code style="color: #ce9178;">message_received</code> = mensagem recebida do contato,
                        <code style="color: #ce9178;">agent_message_sent</code> = mensagem do agente
                    </li>
                    <li><strong style="color: #4ec9b0;">Conta WhatsApp configurada?</strong> — Se a automação tem <code style="color: #ce9178;">integration_account_ids</code> ou <code style="color: #ce9178;">whatsapp_account_ids</code> no trigger_config, a conversa precisa estar associada a uma dessas contas</li>
                    <li><strong style="color: #4ec9b0;">Funil/Etapa corretos?</strong> — Se a automação tem funnel_id ou stage_id definidos, a conversa precisa estar nesse funil/etapa</li>
                    <li><strong style="color: #4ec9b0;">Canal correto?</strong> — Se o trigger_config tem <code style="color: #ce9178;">channel</code>, a conversa precisa ser desse canal</li>
                    <li><strong style="color: #4ec9b0;">Keyword definida?</strong> — Para trigger <code style="color: #ce9178;">message_received</code>, se há keyword, a mensagem precisa conter essa palavra</li>
                    <li><strong style="color: #4ec9b0;">Conversa tem integration_account_id?</strong> — Verifique na aba "Unificação Contas" se a conversa possui o campo preenchido</li>
                    <li><strong style="color: #4ec9b0;">Chatbot/IA ativo?</strong> — Se a conversa tem chatbot ou IA ativo, <code style="color: #ce9178;">message_received</code> pode ser interceptado pelo chatbot ao invés da automação</li>
                </ol>
            </div>
        </div>
        
        <!-- Logs de Automação -->
        <div class="diag-section">
            <h2>📄 Logs de Automação (automacao.log)</h2>
            <p style="color: #858585; font-size: 12px; margin-bottom: 15px;">
                Últimas <?= $maxLines ?> linhas do arquivo de log de automações. 
                <a href="?tab=automacao&lines=1000" style="color: #4ec9b0;">Ver 1000</a> | 
                <a href="?tab=automacao&lines=5000" style="color: #4ec9b0;">Ver 5000</a>
            </p>
            
            <div class="filters" style="margin-bottom: 15px; border-left: none; padding: 10px;">
                <form method="GET" class="filter-row">
                    <input type="hidden" name="tab" value="automacao">
                    <div class="filter-group">
                        <label>Buscar nos logs</label>
                        <input type="text" name="filter" placeholder="executeForNew, matchesAccount, REJEITADO..." value="<?= htmlspecialchars($filter) ?>" style="min-width: 300px;">
                    </div>
                    <div class="filter-group">
                        <label>Linhas</label>
                        <select name="lines">
                            <option value="100" <?= $maxLines === 100 ? 'selected' : '' ?>>100</option>
                            <option value="500" <?= $maxLines === 500 ? 'selected' : '' ?>>500</option>
                            <option value="1000" <?= $maxLines === 1000 ? 'selected' : '' ?>>1000</option>
                            <option value="5000" <?= $maxLines === 5000 ? 'selected' : '' ?>>5000</option>
                        </select>
                    </div>
                    <div class="actions">
                        <button type="submit">🔍 Filtrar</button>
                        <button type="button" class="secondary" onclick="window.location.href='?tab=automacao'">🔄 Limpar</button>
                    </div>
                </form>
            </div>
            
            <div class="logs-container" style="max-height: 600px; overflow-y: auto;">
                <?php if (empty($logs) || (count($logs) === 1 && strpos($logs[0], 'não encontrado') !== false)): ?>
                    <div class="no-logs">
                        <h2>Nenhum log de automação encontrado</h2>
                        <p>Arquivo: <?= htmlspecialchars($logFile) ?></p>
                        <p>Execute uma ação que dispare automação e os logs aparecerão aqui.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $class = '';
                        if (stripos($log, 'REJEITADO') !== false || stripos($log, '❌') !== false || stripos($log, 'ERROR') !== false || stripos($log, 'falhou') !== false) $class = 'error';
                        elseif (stripos($log, '⚠') !== false || stripos($log, 'WARNING') !== false) $class = 'warning';
                        elseif (stripos($log, '✅') !== false || stripos($log, 'INÍCIO') !== false || stripos($log, 'FIM') !== false) $class = 'info';
                        elseif (stripos($log, '🔍') !== false || stripos($log, 'matchesAccount') !== false) $class = 'debug';
                    ?>
                        <div class="log-line <?= $class ?>"><?= colorizeLog($log) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endif; // automationData error ?>
        
        <div class="footer">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <?php else: ?>
        <!-- ═══════════════ ABA LOGS ═══════════════ -->
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
        <?php endif; // activeTab ?>
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
