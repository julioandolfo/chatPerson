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

// ── Ação: Limpar lock do cron ──
$clearLockResult = null;
if (isset($_GET['action']) && $_GET['action'] === 'clear_lock') {
    // Tentar remover lock do shell (/tmp/) e lock legado (storage/cache/)
    $lockFiles = ['/tmp/run_scheduled_jobs.lock', __DIR__ . '/../storage/cache/jobs.lock'];
    $removed = false;
    foreach ($lockFiles as $lockFile) {
        if (file_exists($lockFile)) {
            @unlink($lockFile);
            $removed = true;
        }
    }
    if ($removed) {
        $clearLockResult = ['success' => true, 'message' => 'Lock removido com sucesso'];
    } else {
        $clearLockResult = ['success' => true, 'message' => 'Lock já não existia'];
    }
}

// ── Ação: Reset total de backlog (limpar tudo acumulado) ──
if (isset($_GET['action']) && $_GET['action'] === 'reset_backlog') {
    require_once __DIR__ . '/../config/bootstrap.php';
    $db = \App\Helpers\Database::getInstance();
    $results = [];
    
    // 1. Cancelar delays pendentes
    try {
        $stmt = $db->exec("UPDATE automation_delays SET status = 'cancelled', executed_at = NOW() WHERE status IN ('pending', 'executing')");
        $results[] = "Delays pendentes cancelados";
    } catch (\Throwable $e) { $results[] = "Delays: " . $e->getMessage(); }
    
    // 2. Limpar chatbot timeout de conversas (resetar metadata)
    try {
        $db->exec("UPDATE conversations SET metadata = JSON_REMOVE(
            JSON_REMOVE(
                JSON_REMOVE(
                    JSON_REMOVE(
                        JSON_REMOVE(
                            JSON_REMOVE(metadata, '$.chatbot_active'),
                        '$.chatbot_timeout_at'),
                    '$.chatbot_timeout_action'),
                '$.chatbot_timeout_node_id'),
            '$.chatbot_automation_id'),
        '$.chatbot_reconnect_current')
        WHERE metadata IS NOT NULL AND JSON_EXTRACT(metadata, '$.chatbot_active') = true");
        $results[] = "Chatbot timeouts ativos resetados";
    } catch (\Throwable $e) { $results[] = "Chatbot: " . $e->getMessage(); }
    
    // 3. Limpar buffers de IA
    $bufferDir = __DIR__ . '/../storage/ai_buffers/';
    $bufferFiles = glob($bufferDir . 'buffer_*.json') ?: [];
    $bufferCount = count($bufferFiles);
    foreach ($bufferFiles as $f) { @unlink($f); }
    $results[] = "{$bufferCount} buffer(s) de IA removidos";
    
    // 4. Limpar automation executions pendentes
    try {
        $db->exec("UPDATE automation_executions SET status = 'cancelled' WHERE status IN ('pending', 'running', 'waiting')");
        $results[] = "Execuções de automação pendentes canceladas";
    } catch (\Throwable $e) { $results[] = "Execuções: " . $e->getMessage(); }
    
    // 5. Limpar estado e histórico do cron
    @unlink(__DIR__ . '/../storage/cache/jobs_state.json');
    @unlink(__DIR__ . '/../storage/cache/cron_history.json');
    $results[] = "Estado e histórico do cron limpos";
    
    $clearLockResult = ['success' => true, 'message' => "Reset completo:\n• " . implode("\n• ", $results)];
}

// ── Ação: Limpar histórico do cron ──
if (isset($_GET['action']) && $_GET['action'] === 'clear_cron_history') {
    $cronHistFile = __DIR__ . '/../storage/cache/cron_history.json';
    if (file_exists($cronHistFile)) {
        @unlink($cronHistFile);
    }
    $clearLockResult = ['success' => true, 'message' => 'Histórico do cron limpo. Próxima execução registrará novo histórico.'];
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
        
        // 5. Conversas com chatbot ativo (timeout pendente/expirado)
        $chatbotTimeouts = $db->query("
            SELECT c.id as conv_id, c.status, c.metadata, c.contact_id,
                   ct.name as contact_name, ct.phone as contact_phone,
                   COALESCE(ia.name, wa.name) as account_name,
                   c.updated_at
            FROM conversations c
            LEFT JOIN contacts ct ON ct.id = c.contact_id
            LEFT JOIN integration_accounts ia ON c.integration_account_id = ia.id
            LEFT JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id AND c.integration_account_id IS NULL
            WHERE c.status != 'closed'
              AND c.metadata IS NOT NULL
              AND c.metadata LIKE '%chatbot_active%'
            ORDER BY c.updated_at DESC
            LIMIT 100
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        // Filtrar e enriquecer só os que têm chatbot_active = true
        $activeChatbots = [];
        $now = time();
        foreach ($chatbotTimeouts as $row) {
            $meta = json_decode($row['metadata'] ?? '{}', true);
            if (!empty($meta['chatbot_active'])) {
                $timeoutAt = $meta['chatbot_timeout_at'] ?? null;
                $remaining = $timeoutAt ? ($timeoutAt - $now) : null;
                $inactivityMode = $meta['chatbot_inactivity_mode'] ?? 'timeout';
                $reconnectAttempts = $meta['chatbot_reconnect_attempts'] ?? [];
                $reconnectCurrent = (int)($meta['chatbot_reconnect_current'] ?? 0);
                $reconnectTotal = count($reconnectAttempts);
                
                $activeChatbots[] = [
                    'conv_id' => $row['conv_id'],
                    'status' => $row['status'],
                    'contact_name' => $row['contact_name'],
                    'contact_phone' => $row['contact_phone'],
                    'account_name' => $row['account_name'],
                    'chatbot_type' => $meta['chatbot_type'] ?? '?',
                    'chatbot_timeout_at' => $timeoutAt,
                    'timeout_at_formatted' => $timeoutAt ? date('d/m/Y H:i:s', $timeoutAt) : 'N/A',
                    'remaining_seconds' => $remaining,
                    'is_expired' => $remaining !== null && $remaining <= 0,
                    'expired_ago' => ($remaining !== null && $remaining <= 0) ? abs($remaining) : null,
                    'timeout_action' => $meta['chatbot_timeout_action'] ?? 'nothing',
                    'timeout_node_id' => $meta['chatbot_timeout_node_id'] ?? null,
                    'automation_id' => $meta['chatbot_automation_id'] ?? null,
                    'chatbot_node_id' => $meta['chatbot_node_id'] ?? null,
                    'invalid_attempts' => $meta['chatbot_invalid_attempts'] ?? 0,
                    'max_attempts' => $meta['chatbot_max_attempts'] ?? 3,
                    'inactivity_mode' => $inactivityMode,
                    'reconnect_current' => $reconnectCurrent,
                    'reconnect_total' => $reconnectTotal,
                    'reconnect_attempts' => $reconnectAttempts,
                    'updated_at' => $row['updated_at'],
                ];
            }
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
            'activeChatbots' => $activeChatbots,
        ];
    } catch (\Exception $e) {
        $automationData = ['error' => $e->getMessage()];
    }
}

// ── Diagnóstico Instagram/Notificame ──
$instagramData = null;
if ($activeTab === 'instagram') {
    try {
        require_once __DIR__ . '/../config/bootstrap.php';
        $db = \App\Helpers\Database::getInstance();

        // 1. Contas de integração Instagram (Notificame)
        $igIntegrationAccounts = $db->query("
            SELECT id, name, provider, channel, account_id, username, status,
                   webhook_url, api_url, error_message, last_sync_at, created_at
            FROM integration_accounts
            WHERE channel = 'instagram'
            ORDER BY id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Instagram accounts (via Meta Graph API)
        $igAccounts = [];
        try {
            $igAccounts = $db->query("
                SELECT ia.id, ia.instagram_user_id, ia.username, ia.name,
                       ia.account_type, ia.is_active, ia.is_connected,
                       ia.integration_account_id, ia.facebook_page_id,
                       ia.followers_count, ia.last_synced_at,
                       intacc.name as integration_name, intacc.provider as integration_provider
                FROM instagram_accounts ia
                LEFT JOIN integration_accounts intacc ON intacc.id = ia.integration_account_id
                ORDER BY ia.id
            ")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $igAccounts = [['_error' => 'Tabela instagram_accounts: ' . $e->getMessage()]];
        }

        // 3. Conversas Instagram/instagram_comment
        $igConversations = $db->query("
            SELECT c.id, c.contact_id, c.channel, c.status, c.integration_account_id,
                   ct.name as contact_name, ct.identifier as contact_identifier,
                   ia.name as account_name, ia.provider as account_provider,
                   (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) as msg_count,
                   c.created_at, c.updated_at
            FROM conversations c
            LEFT JOIN contacts ct ON ct.id = c.contact_id
            LEFT JOIN integration_accounts ia ON ia.id = c.integration_account_id
            WHERE c.channel IN ('instagram', 'instagram_comment')
            ORDER BY c.updated_at DESC
            LIMIT 30
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 4. Últimas mensagens Instagram
        $igMessages = $db->query("
            SELECT m.id, m.conversation_id, m.content, m.message_type,
                   m.sender_type, m.sender_id, m.status, m.external_id, m.created_at,
                   ct.name as sender_name
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id
                AND c.channel IN ('instagram', 'instagram_comment')
            LEFT JOIN contacts ct ON ct.id = c.contact_id
            ORDER BY m.created_at DESC
            LIMIT 20
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 5. Contagem geral
        $igTotalConvs = $db->query("SELECT COUNT(*) as c FROM conversations WHERE channel IN ('instagram','instagram_comment')")->fetch(\PDO::FETCH_ASSOC)['c'];
        $igTotalMsgs  = $db->query("SELECT COUNT(*) as c FROM messages WHERE conversation_id IN (SELECT id FROM conversations WHERE channel IN ('instagram','instagram_comment'))")->fetch(\PDO::FETCH_ASSOC)['c'];
        $igTotalConvsNoAccount = $db->query("SELECT COUNT(*) as c FROM conversations WHERE channel IN ('instagram','instagram_comment') AND integration_account_id IS NULL")->fetch(\PDO::FETCH_ASSOC)['c'];

        // 6. Webhook URL configurada no servidor
        $webhookBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $notificameWebhookUrl = $webhookBase . '/notificame-webhook.php';

        // 7. Diagnósticos de configuração
        $igDiag = [];
        foreach ($igIntegrationAccounts as $acc) {
            $diag = ['account' => $acc, 'issues' => [], 'ok' => []];
            if (empty($acc['account_id'])) {
                $diag['issues'][] = 'CRÍTICO: account_id vazio — obrigatório para envio de mensagens no Instagram via Notificame (campo "from")';
            } else {
                $diag['ok'][] = 'account_id configurado: ' . $acc['account_id'];
            }
            if (empty($acc['api_url'])) {
                $diag['issues'][] = 'api_url não configurada — usando URL padrão https://api.notificame.com.br/v1/';
            } else {
                $diag['ok'][] = 'api_url: ' . $acc['api_url'];
            }
            if (empty($acc['webhook_url'])) {
                $diag['issues'][] = 'webhook_url não configurada — webhook do Notificame não está apontando para este servidor';
                $diag['issues'][] = 'URL sugerida: ' . $notificameWebhookUrl;
            } else {
                $diag['ok'][] = 'webhook_url: ' . $acc['webhook_url'];
                if (strpos($acc['webhook_url'], 'notificame-webhook.php') === false) {
                    $diag['issues'][] = 'webhook_url não aponta para /notificame-webhook.php — verifique se está correto';
                }
            }
            if ($acc['status'] !== 'active') {
                $diag['issues'][] = 'Status: ' . $acc['status'] . (!empty($acc['error_message']) ? ' — ' . $acc['error_message'] : '');
            } else {
                $diag['ok'][] = 'Status: active';
            }
            if ($acc['provider'] !== 'notificame') {
                $diag['issues'][] = 'Provider inesperado: ' . $acc['provider'] . ' (esperado: notificame)';
            } else {
                $diag['ok'][] = 'Provider: notificame';
            }
            $igDiag[] = $diag;
        }

        // 8. Últimas linhas do notificame.log filtradas por instagram
        $notificameLogFile = __DIR__ . '/../logs/notificame.log';
        $igLogLines = [];
        if (file_exists($notificameLogFile)) {
            $allNotifLines = file($notificameLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $igLogLines = array_filter(array_reverse($allNotifLines), function($l) {
                return stripos($l, 'instagram') !== false || stripos($l, 'Instagram') !== false;
            });
            $igLogLines = array_slice(array_values($igLogLines), 0, 100);
        }

        $instagramData = [
            'integrationAccounts' => $igIntegrationAccounts,
            'igAccounts' => $igAccounts,
            'conversations' => $igConversations,
            'messages' => $igMessages,
            'totalConvs' => $igTotalConvs,
            'totalMsgs' => $igTotalMsgs,
            'totalConvsNoAccount' => $igTotalConvsNoAccount,
            'webhookUrl' => $notificameWebhookUrl,
            'diagnostics' => $igDiag,
            'igLogLines' => $igLogLines,
        ];
    } catch (\Exception $e) {
        $instagramData = ['error' => $e->getMessage()];
    }
}

// ── Diagnóstico WhatsApp Notificame ──
$waNotificameData = null;
if ($activeTab === 'wa_notificame') {
    try {
        require_once __DIR__ . '/../config/bootstrap.php';
        $db = \App\Helpers\Database::getInstance();

        // 1. Contas WhatsApp via Notificame
        $waAccounts = $db->query("
            SELECT id, name, provider, channel, account_id, phone_number, username, status,
                   webhook_url, api_url, api_token, error_message, last_sync_at, created_at
            FROM integration_accounts
            WHERE provider = 'notificame' AND channel = 'whatsapp'
            ORDER BY id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Todas as contas Notificame (qualquer canal)
        $allNotificameAccounts = $db->query("
            SELECT id, name, channel, account_id, status, last_sync_at
            FROM integration_accounts
            WHERE provider = 'notificame'
            ORDER BY channel, id
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Conversas WhatsApp via Notificame (últimas 30)
        $waConversations = $db->query("
            SELECT c.id, c.contact_id, c.channel, c.status, c.integration_account_id,
                   ct.name as contact_name, ct.phone as contact_phone,
                   ia.name as account_name, ia.provider as account_provider,
                   (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) as msg_count,
                   c.created_at, c.updated_at
            FROM conversations c
            LEFT JOIN contacts ct ON ct.id = c.contact_id
            LEFT JOIN integration_accounts ia ON ia.id = c.integration_account_id
            WHERE ia.provider = 'notificame' AND c.channel = 'whatsapp'
            ORDER BY c.updated_at DESC
            LIMIT 30
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 4. Últimas mensagens WhatsApp Notificame
        $waMessages = $db->query("
            SELECT m.id, m.conversation_id, m.content, m.message_type,
                   m.sender_type, m.status as msg_status, m.external_id, m.created_at,
                   c.channel, ct.name as contact_name, ct.phone as contact_phone
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id AND c.channel = 'whatsapp'
            INNER JOIN integration_accounts ia ON ia.id = c.integration_account_id AND ia.provider = 'notificame'
            LEFT JOIN contacts ct ON ct.id = c.contact_id
            ORDER BY m.created_at DESC
            LIMIT 30
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 5. Mensagens com erro/falha (últimas 20)
        $waFailedMessages = $db->query("
            SELECT m.id, m.conversation_id, m.content, m.message_type,
                   m.sender_type, m.status as msg_status, m.external_id, m.error_message, m.created_at,
                   ct.name as contact_name, ct.phone as contact_phone
            FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id AND c.channel = 'whatsapp'
            INNER JOIN integration_accounts ia ON ia.id = c.integration_account_id AND ia.provider = 'notificame'
            LEFT JOIN contacts ct ON ct.id = c.contact_id
            WHERE m.status IN ('failed', 'error', 'rejected')
            ORDER BY m.created_at DESC
            LIMIT 20
        ")->fetchAll(\PDO::FETCH_ASSOC);

        // 6. Contagens
        $waTotalConvs = $db->query("
            SELECT COUNT(*) as c FROM conversations c
            INNER JOIN integration_accounts ia ON ia.id = c.integration_account_id
            WHERE ia.provider = 'notificame' AND c.channel = 'whatsapp'
        ")->fetch(\PDO::FETCH_ASSOC)['c'];

        $waTotalMsgs = $db->query("
            SELECT COUNT(*) as c FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id AND c.channel = 'whatsapp'
            INNER JOIN integration_accounts ia ON ia.id = c.integration_account_id AND ia.provider = 'notificame'
        ")->fetch(\PDO::FETCH_ASSOC)['c'];

        $waTotalMsgsIn = $db->query("
            SELECT COUNT(*) as c FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id AND c.channel = 'whatsapp'
            INNER JOIN integration_accounts ia ON ia.id = c.integration_account_id AND ia.provider = 'notificame'
            WHERE m.sender_type = 'contact'
        ")->fetch(\PDO::FETCH_ASSOC)['c'];

        $waTotalMsgsOut = $db->query("
            SELECT COUNT(*) as c FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id AND c.channel = 'whatsapp'
            INNER JOIN integration_accounts ia ON ia.id = c.integration_account_id AND ia.provider = 'notificame'
            WHERE m.sender_type IN ('user', 'agent', 'system', 'bot')
        ")->fetch(\PDO::FETCH_ASSOC)['c'];

        $waTotalFailed = $db->query("
            SELECT COUNT(*) as c FROM messages m
            INNER JOIN conversations c ON c.id = m.conversation_id AND c.channel = 'whatsapp'
            INNER JOIN integration_accounts ia ON ia.id = c.integration_account_id AND ia.provider = 'notificame'
            WHERE m.status IN ('failed', 'error', 'rejected')
        ")->fetch(\PDO::FETCH_ASSOC)['c'];

        // 7. Webhook URL
        $webhookBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $notificameWebhookUrl = $webhookBase . '/notificame-webhook.php';

        // 8. Diagnósticos de configuração por conta
        $waDiag = [];
        foreach ($waAccounts as $acc) {
            $diag = ['account' => $acc, 'issues' => [], 'ok' => [], 'warnings' => []];

            if (empty($acc['account_id'])) {
                $diag['issues'][] = 'CRITICO: account_id vazio — campo "from" para API e token do canal para templates';
            } else {
                $diag['ok'][] = 'account_id (token do canal): ' . $acc['account_id'];
            }
            if (empty($acc['api_token'])) {
                $diag['issues'][] = 'CRITICO: api_token vazio — necessario para autenticacao (X-Api-Token)';
            } else {
                $diag['ok'][] = 'api_token configurado (' . strlen($acc['api_token']) . ' chars)';
            }
            if (empty($acc['api_url'])) {
                $diag['warnings'][] = 'api_url vazia — usando padrao https://api.notificame.com.br/v1/';
            } else {
                $diag['ok'][] = 'api_url: ' . $acc['api_url'];
            }
            if (empty($acc['webhook_url'])) {
                $diag['warnings'][] = 'webhook_url nao configurada';
                $diag['warnings'][] = 'URL sugerida: ' . $notificameWebhookUrl;
            } else {
                $diag['ok'][] = 'webhook_url: ' . $acc['webhook_url'];
                if (strpos($acc['webhook_url'], 'notificame-webhook.php') === false) {
                    $diag['warnings'][] = 'webhook_url nao aponta para /notificame-webhook.php';
                }
            }
            if ($acc['status'] !== 'active') {
                $diag['issues'][] = 'Status: ' . $acc['status'] . (!empty($acc['error_message']) ? ' — ' . $acc['error_message'] : '');
            } else {
                $diag['ok'][] = 'Status: active';
            }
            if (empty($acc['phone_number'])) {
                $diag['warnings'][] = 'phone_number nao configurado';
            } else {
                $diag['ok'][] = 'phone_number: ' . $acc['phone_number'];
            }

            // Verificar endpoints de template e mensagem
            $diag['endpoints'] = [
                'templates_list' => 'GET templates/' . ($acc['account_id'] ?: '{account_id}'),
                'templates_create' => 'POST templates/' . ($acc['account_id'] ?: '{account_id}'),
                'send_message' => 'POST channels/whatsapp/messages',
                'send_template' => 'POST channels/whatsapp/messages (type=template)',
            ];

            $waDiag[] = $diag;
        }

        // 9. Logs do notificame.log filtrados por whatsapp
        $notificameLogFile = __DIR__ . '/../logs/notificame.log';
        if (!file_exists($notificameLogFile)) {
            $notificameLogFile = __DIR__ . '/../storage/logs/notificame.log';
        }
        $waLogLines = [];
        if (file_exists($notificameLogFile)) {
            $allNotifLines = file($notificameLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $waLogLines = array_filter(array_reverse($allNotifLines), function($l) {
                return stripos($l, 'whatsapp') !== false
                    || stripos($l, 'template') !== false
                    || stripos($l, 'sendMessage') !== false
                    || stripos($l, 'sendTemplate') !== false;
            });
            $waLogLines = array_slice(array_values($waLogLines), 0, 150);
        }

        // 10. Logs de template especificamente
        $waTemplateLogLines = [];
        if (file_exists($notificameLogFile)) {
            $waTemplateLogLines = array_filter(array_reverse($allNotifLines), function($l) {
                return stripos($l, 'template') !== false;
            });
            $waTemplateLogLines = array_slice(array_values($waTemplateLogLines), 0, 50);
        }

        $waNotificameData = [
            'waAccounts' => $waAccounts,
            'allAccounts' => $allNotificameAccounts,
            'conversations' => $waConversations,
            'messages' => $waMessages,
            'failedMessages' => $waFailedMessages,
            'totalConvs' => $waTotalConvs,
            'totalMsgs' => $waTotalMsgs,
            'totalMsgsIn' => $waTotalMsgsIn,
            'totalMsgsOut' => $waTotalMsgsOut,
            'totalFailed' => $waTotalFailed,
            'webhookUrl' => $notificameWebhookUrl,
            'diagnostics' => $waDiag,
            'waLogLines' => $waLogLines,
            'templateLogLines' => $waTemplateLogLines,
            'logFile' => $notificameLogFile,
        ];
    } catch (\Exception $e) {
        $waNotificameData = ['error' => $e->getMessage()];
    }
}

// ── Logs ──
$logFileMap = [
    'logs' => __DIR__ . '/../logs/app.log',
    'automacao' => __DIR__ . '/../logs/automacao.log',
    'quepasa' => __DIR__ . '/../logs/quepasa.log',
    'evolution' => __DIR__ . '/../logs/evolution.log',
    'conversas' => __DIR__ . '/../logs/conversas.log',
    'unificacao_logs' => __DIR__ . '/../logs/unificacao.log',
    'webhook' => __DIR__ . '/../logs/webhook.log',
    'media_queue' => __DIR__ . '/../logs/media_queue.log',
    'wc_sync' => __DIR__ . '/../logs/wc_sync.log',
    'notificame' => __DIR__ . '/../logs/notificame.log',
    'meta' => __DIR__ . '/../logs/meta.log',
    'auto_close' => __DIR__ . '/../logs/auto_close.log',
    'templates' => __DIR__ . '/../logs/app.log',
    'ai_tools' => __DIR__ . '/../logs/ai_tools.log',
    'ai_debug' => __DIR__ . '/../logs/conversation-debug.log',
    'campaigns' => __DIR__ . '/../logs/campaigns.log',
];
// Fallback: se não existir em logs/, tentar em storage/logs/
foreach ($logFileMap as $key => $path) {
    if (!file_exists($path)) {
        $fallback = str_replace('/logs/', '/storage/logs/', $path);
        if (file_exists($fallback)) {
            $logFileMap[$key] = $fallback;
        }
    }
}
$logFile = $logFileMap[$activeTab] ?? $logFileMap['logs'];
$maxLines = isset($_GET['lines']) ? (int)$_GET['lines'] : 500;
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$level = isset($_GET['level']) ? $_GET['level'] : '';

// Auto-criar diretório e arquivo de log se não existirem
if (!file_exists($logFile)) {
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    @touch($logFile);
    @chmod($logFile, 0666);
}

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
            <a href="?tab=unificacao_logs" class="tab <?= $activeTab === 'unificacao_logs' ? 'active' : '' ?>">📊 Logs Unificação</a>
            <a href="?tab=quepasa" class="tab <?= $activeTab === 'quepasa' ? 'active' : '' ?>">📱 Logs Quepasa</a>
            <a href="?tab=evolution" class="tab <?= $activeTab === 'evolution' ? 'active' : '' ?>">🔗 Logs Evolution</a>
            <a href="?tab=instagram" class="tab <?= $activeTab === 'instagram' ? 'active' : '' ?>" style="<?= $activeTab === 'instagram' ? '' : 'border-color:#e1306c;color:#e1306c;' ?>">📷 Instagram Diagnóstico</a>
            <a href="?tab=wa_notificame" class="tab <?= $activeTab === 'wa_notificame' ? 'active' : '' ?>" style="<?= $activeTab === 'wa_notificame' ? '' : 'border-color:#25d366;color:#25d366;' ?>">📲 WhatsApp Notificame</a>
            <a href="?tab=notificame" class="tab <?= $activeTab === 'notificame' ? 'active' : '' ?>" style="<?= $activeTab === 'notificame' ? '' : 'border-color:#6c63ff;color:#9c99ff;' ?>">🔔 Logs Notificame</a>
            <a href="?tab=media_queue" class="tab <?= $activeTab === 'media_queue' ? 'active' : '' ?>">📦 Media Queue</a>
            <a href="?tab=webhook" class="tab <?= $activeTab === 'webhook' ? 'active' : '' ?>">🛒 Webhook WooCommerce</a>
            <a href="?tab=wc_sync" class="tab <?= $activeTab === 'wc_sync' ? 'active' : '' ?>">🔄 Cron Sync WooCommerce</a>
            <a href="?tab=auto_close" class="tab <?= $activeTab === 'auto_close' ? 'active' : '' ?>" style="<?= $activeTab === 'auto_close' ? '' : 'border-color:#f44747;color:#f48771;' ?>">⏰ Auto Close</a>
            <a href="?tab=templates" class="tab <?= $activeTab === 'templates' ? 'active' : '' ?>" style="<?= $activeTab === 'templates' ? '' : 'border-color:#25D366;color:#25D366;' ?>">📋 Templates WhatsApp</a>
            <a href="?tab=ai_tools" class="tab <?= $activeTab === 'ai_tools' ? 'active' : '' ?>" style="<?= $activeTab === 'ai_tools' ? '' : 'border-color:#a855f7;color:#c084fc;' ?>">🧠 AI Tools</a>
            <a href="?tab=ai_debug" class="tab <?= $activeTab === 'ai_debug' ? 'active' : '' ?>" style="<?= $activeTab === 'ai_debug' ? '' : 'border-color:#06b6d4;color:#22d3ee;' ?>">🔬 AI Debug Conversa</a>
            <a href="?tab=campaigns" class="tab <?= $activeTab === 'campaigns' ? 'active' : '' ?>" style="<?= $activeTab === 'campaigns' ? '' : 'border-color:#f59e0b;color:#fbbf24;' ?>">📢 Campanhas</a>
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
        
        <?php if ($clearLockResult): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($clearLockResult['message']) ?></div>
        <?php endif; ?>
        
        <?php if (isset($automationData['error'])): ?>
            <div class="alert alert-error">❌ Erro ao carregar dados: <?= htmlspecialchars($automationData['error']) ?></div>
        <?php else: ?>
        
        <header>
            <h1>🤖 Diagnóstico de Automações</h1>
            <p style="color: #858585; font-size: 13px;">Visualize todas as automações, suas configurações de contas/triggers e execuções recentes.</p>
        </header>
        
        <!-- Histórico do Cron -->
        <?php
            $cronHistoryFile = __DIR__ . '/../storage/cache/cron_history.json';
            $cronHistory = [];
            if (file_exists($cronHistoryFile)) {
                $cronHistory = json_decode(file_get_contents($cronHistoryFile), true) ?: [];
            }
            
            $lastRun = !empty($cronHistory) ? $cronHistory[0] : null;
            $lastRunAgo = $lastRun ? (time() - strtotime($lastRun['started_at'])) : null;
            $cronRunning = $lastRunAgo !== null && $lastRunAgo < 180; // Considerado ativo se rodou nos últimos 3 min
            
            // Contadores das últimas 50 execuções
            $recentHistory = array_slice($cronHistory, 0, 50);
            $cronErrors = 0;
            $cronSkipped = 0;
            $cronSuccess = 0;
            foreach ($recentHistory as $ch) {
                if (($ch['status'] ?? '') === 'error') $cronErrors++;
                elseif (($ch['status'] ?? '') === 'skipped') $cronSkipped++;
                else $cronSuccess++;
            }
        ?>
        <div class="diag-section <?= $cronRunning ? 'success' : 'danger' ?>">
            <h2>🕐 Status do Cron (run-scheduled-jobs.php)</h2>
            
            <div class="grid-4" style="margin-bottom: 15px;">
                <div class="grid-card">
                    <div class="label">Status</div>
                    <?php if ($cronRunning): ?>
                        <div class="big-number green" style="font-size: 24px;">ATIVO</div>
                        <div class="label" style="color: #4ec9b0;">Última exec: <?= $lastRunAgo ?>s atrás</div>
                    <?php elseif ($lastRun): ?>
                        <div class="big-number red" style="font-size: 24px;">PARADO?</div>
                        <div class="label" style="color: #f48771;">Última exec: <?= round($lastRunAgo / 60, 1) ?> min atrás</div>
                    <?php else: ?>
                        <div class="big-number red" style="font-size: 24px;">SEM DADOS</div>
                        <div class="label" style="color: #f48771;">Nenhuma execução registrada</div>
                    <?php endif; ?>
                </div>
                <div class="grid-card">
                    <div class="label">Última Execução</div>
                    <div style="color: #fff; font-size: 14px; font-weight: bold;">
                        <?= $lastRun ? $lastRun['started_at'] : 'Nunca' ?>
                    </div>
                    <?php if ($lastRun): ?>
                        <div class="label">Duração: <?= $lastRun['duration_s'] ?>s | Jobs: <?= $lastRun['jobs_count'] ?></div>
                    <?php endif; ?>
                </div>
                <div class="grid-card">
                    <div class="label">Últimas 50 exec.</div>
                    <div class="big-number green" style="font-size: 20px;"><?= $cronSuccess ?> ok</div>
                    <?php if ($cronErrors > 0): ?>
                        <div class="label" style="color: #f48771;"><?= $cronErrors ?> erro(s)</div>
                    <?php endif; ?>
                    <?php if ($cronSkipped > 0): ?>
                        <div class="label" style="color: #dcdcaa;"><?= $cronSkipped ?> skip(s)</div>
                    <?php endif; ?>
                </div>
                <div class="grid-card">
                    <div class="label">Último Status</div>
                    <?php if ($lastRun): ?>
                        <?php if ($lastRun['status'] === 'success'): ?>
                            <span class="badge badge-ok" style="font-size: 14px;">SUCCESS</span>
                        <?php elseif ($lastRun['status'] === 'error'): ?>
                            <span class="badge badge-miss" style="font-size: 14px;">ERRO</span>
                            <div class="label" style="color: #f48771; margin-top: 5px;"><?= htmlspecialchars(substr($lastRun['error'] ?? '', 0, 60)) ?></div>
                        <?php elseif ($lastRun['status'] === 'skipped'): ?>
                            <span class="badge badge-warn" style="font-size: 14px;">SKIP (LOCK)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-na">N/A</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$cronRunning && $lastRunAgo !== null && $lastRunAgo > 300): ?>
            <div style="background: rgba(244,135,113,0.1); border: 1px solid #f48771; border-radius: 6px; padding: 12px; margin-bottom: 15px;">
                <strong style="color: #f48771;">⚠️ O cron parece estar PARADO!</strong>
                <p style="color: #d4d4d4; font-size: 13px; margin-top: 5px;">Última execução foi há <?= round($lastRunAgo / 60, 1) ?> minutos. O cron deveria rodar a cada ~1 minuto.</p>
                <p style="color: #858585; font-size: 12px; margin-top: 5px;">Verifique: <code style="color: #ce9178;">crontab -l</code> deve conter algo como: <code style="color: #ce9178;">* * * * * php /caminho/public/run-scheduled-jobs.php</code></p>
                <?php 
                    $lockFiles = ['/tmp/run_scheduled_jobs.lock', __DIR__ . '/../storage/cache/jobs.lock'];
                    $lockLocked = false;
                    foreach ($lockFiles as $lf) {
                        if (file_exists($lf)) {
                            // Verificar se o arquivo está REALMENTE trancado (em uso por outro processo)
                            $testFp = @fopen($lf, 'r');
                            $isActuallyLocked = false;
                            if ($testFp) {
                                $isActuallyLocked = !@flock($testFp, LOCK_EX | LOCK_NB);
                                if (!$isActuallyLocked) @flock($testFp, LOCK_UN);
                                @fclose($testFp);
                            }
                            
                            if ($isActuallyLocked) {
                                $lockAge = time() - filemtime($lf);
                                $lockLocked = true;
                ?>
                <p style="color: #dcdcaa; font-size: 12px; margin-top: 5px;">🔒 Lock <strong>ATIVO</strong> em <code style="color: #ce9178;"><?= $lf ?></code> (rodando há <?= round($lockAge / 60, 1) ?> min). Se > 5 min, pode estar travado. 
                    <a href="?tab=automacao&action=clear_lock" style="color: #4ec9b0;" onclick="return confirm('Limpar arquivo de lock? Isso pode causar execuções duplicadas se o cron estiver realmente rodando.')">🔓 Forçar Liberação</a>
                </p>
                <?php
                            }
                        }
                    }
                    if (!$lockLocked): ?>
                <p style="color: #6a9955; font-size: 12px; margin-top: 5px;">✅ Nenhum lock ativo — o cron não está rodando neste momento.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Botão de Reset Total -->
            <div style="margin-top: 10px; margin-bottom: 10px;">
                <a href="?tab=automacao&action=reset_backlog" 
                   style="display: inline-block; background: #dc3545; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600;"
                   onclick="return confirm('⚠️ RESET TOTAL DO BACKLOG\n\nIsso vai:\n• Cancelar todos os delays de automação pendentes\n• Resetar todos os chatbot timeouts ativos\n• Remover todos os buffers de IA\n• Cancelar execuções pendentes\n• Limpar estado e histórico do cron\n\nTem certeza?')">
                    🔄 Reset Total do Backlog (Limpar tudo acumulado)
                </a>
            </div>

            <!-- Histórico detalhado (últimas 20 execuções) -->
            <details style="margin-top: 10px;">
                <summary style="cursor: pointer; color: #9cdcfe; font-size: 13px; font-weight: bold;">📜 Histórico de Execuções (últimas <?= min(20, count($cronHistory)) ?> de <?= count($cronHistory) ?>)
                    <?php if (!empty($cronHistory)): ?>
                        <a href="?tab=automacao&action=clear_cron_history" style="color: #858585; font-size: 11px; margin-left: 10px;" onclick="return confirm('Limpar todo o histórico do cron?')">🗑️ Limpar</a>
                    <?php endif; ?>
                </summary>
                <div style="margin-top: 10px; overflow-x: auto;">
                    <?php if (empty($cronHistory)): ?>
                        <p style="color: #858585; padding: 10px;">Nenhuma execução registrada ainda. O histórico será preenchido após a próxima execução do cron.</p>
                    <?php else: ?>
                    <table class="diag-table">
                        <thead>
                            <tr>
                                <th>Início</th>
                                <th>Duração</th>
                                <th>Status</th>
                                <th>Jobs</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($cronHistory, 0, 20) as $run): ?>
                            <tr style="<?= ($run['status'] ?? '') === 'error' ? 'background: rgba(244,135,113,0.1);' : (($run['status'] ?? '') === 'skipped' ? 'background: rgba(220,220,170,0.05);' : '') ?>">
                                <td style="white-space: nowrap; color: #858585; font-size: 12px;"><?= $run['started_at'] ?></td>
                                <td style="text-align: center;"><?= $run['duration_s'] ?>s</td>
                                <td>
                                    <?php if (($run['status'] ?? '') === 'success'): ?>
                                        <span class="badge badge-ok">OK</span>
                                    <?php elseif (($run['status'] ?? '') === 'error'): ?>
                                        <span class="badge badge-miss">ERRO</span>
                                    <?php elseif (($run['status'] ?? '') === 'skipped'): ?>
                                        <span class="badge badge-warn">SKIP</span>
                                    <?php else: ?>
                                        <span class="badge badge-na"><?= $run['status'] ?? '?' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;"><?= $run['jobs_count'] ?? 0 ?></td>
                                <td style="font-size: 11px;">
                                    <?php if (!empty($run['error'])): ?>
                                        <span style="color: #f48771;"><?= htmlspecialchars(substr($run['error'], 0, 80)) ?></span>
                                    <?php elseif (!empty($run['jobs'])): ?>
                                        <?php foreach ($run['jobs'] as $job): ?>
                                            <span style="color: <?= ($job['status'] ?? 'ok') === 'ok' ? '#4ec9b0' : '#f48771' ?>;">
                                                <?= $job['job'] ?> (<?= $job['duration'] ?>s<?= ($job['status'] ?? 'ok') !== 'ok' ? ' ❌' : '' ?>)
                                            </span>
                                            <?php if ($job !== end($run['jobs'])): ?> | <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: #555;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </details>
        </div>
        
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
        
        <!-- Chatbot Timeouts Ativos -->
        <?php $activeChatbots = $automationData['activeChatbots'] ?? []; ?>
        <div class="diag-section <?= !empty($activeChatbots) ? (count(array_filter($activeChatbots, fn($c) => $c['is_expired'])) > 0 ? 'danger' : 'warning') : 'success' ?>">
            <h2>⏰ Chatbot Timeouts Ativos (<?= count($activeChatbots) ?>)</h2>
            <p style="color: #858585; font-size: 12px; margin-bottom: 15px;">
                Conversas com chatbot aguardando resposta do contato. Se um timeout expirou e a ação não foi executada, há um problema no processamento.
                <br><strong style="color: #dcdcaa;">Dica:</strong> O <code style="color: #ce9178;">ChatbotTimeoutJob</code> roda a cada execução do cron (~1 min). Se houver timeouts expirados persistentes, verifique se o cron está rodando.
            </p>
            
            <?php if (empty($activeChatbots)): ?>
                <p style="color: #4ec9b0;">Nenhum chatbot ativo no momento. Todos os timeouts foram processados.</p>
            <?php else: ?>
                <?php 
                    $expiredCount = count(array_filter($activeChatbots, fn($c) => $c['is_expired']));
                    $pendingCount = count($activeChatbots) - $expiredCount;
                ?>
                <div class="grid-4" style="margin-bottom: 15px;">
                    <div class="grid-card">
                        <div class="label">Chatbots Ativos</div>
                        <div class="big-number blue"><?= count($activeChatbots) ?></div>
                    </div>
                    <div class="grid-card">
                        <div class="label">Aguardando (timer ativo)</div>
                        <div class="big-number green"><?= $pendingCount ?></div>
                    </div>
                    <div class="grid-card">
                        <div class="label">EXPIRADOS (não processados!)</div>
                        <div class="big-number <?= $expiredCount > 0 ? 'red' : 'green' ?>"><?= $expiredCount ?></div>
                    </div>
                </div>
                
                <div style="overflow-x: auto;">
                <table class="diag-table">
                    <thead>
                        <tr>
                            <th>Conv ID</th>
                            <th>Contato</th>
                            <th>Conta</th>
                            <th>Modo</th>
                            <th>Reconexão</th>
                            <th>Próximo Evento</th>
                            <th>Status Timer</th>
                            <th>Ação Final</th>
                            <th>Nó Destino</th>
                            <th>Automação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeChatbots as $cb): ?>
                        <tr style="<?= $cb['is_expired'] ? 'background: rgba(244,135,113,0.1);' : '' ?>">
                            <td><strong>#<?= $cb['conv_id'] ?></strong></td>
                            <td>
                                <?= htmlspecialchars($cb['contact_name'] ?? '?') ?>
                                <?php if (!empty($cb['contact_phone'])): ?>
                                    <br><small style="color: #858585;"><?= $cb['contact_phone'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($cb['account_name'] ?? '?') ?></td>
                            <td>
                                <?php if ($cb['inactivity_mode'] === 'reconnect'): ?>
                                    <span class="badge" style="background: #c586c0; color: #fff;">🔄 Reconexão</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #007acc; color: #fff;">⏱️ Timeout</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cb['inactivity_mode'] === 'reconnect'): ?>
                                    <span style="color: #c586c0; font-weight: bold;">
                                        <?= $cb['reconnect_current'] ?>/<?= $cb['reconnect_total'] ?>
                                    </span>
                                    <?php if ($cb['reconnect_current'] < $cb['reconnect_total']): ?>
                                        <br><small style="color: #4ec9b0;">Próxima: #<?= $cb['reconnect_current'] + 1 ?></small>
                                    <?php else: ?>
                                        <br><small style="color: #dcdcaa;">Todas enviadas</small>
                                    <?php endif; ?>
                                    <?php if (!empty($cb['reconnect_attempts'])): ?>
                                        <br><small style="color: #858585;" title="<?php foreach ($cb['reconnect_attempts'] as $i => $a) { echo 'Tentativa ' . ($i+1) . ': ' . htmlspecialchars(substr($a['message'], 0, 40)) . ' (' . $a['delay'] . 's)\n'; } ?>">
                                            ver detalhes ℹ️
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #555;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <?= $cb['timeout_at_formatted'] ?>
                                <?php if ($cb['is_expired']): ?>
                                    <br><small style="color: #f48771; font-weight: bold;">Expirou há <?= round($cb['expired_ago'] / 60, 1) ?> min</small>
                                <?php elseif ($cb['remaining_seconds'] !== null): ?>
                                    <br><small style="color: #4ec9b0;">Faltam <?= round($cb['remaining_seconds'] / 60, 1) ?> min</small>
                                <?php endif; ?>
                                <?php if ($cb['inactivity_mode'] === 'reconnect' && $cb['reconnect_current'] < $cb['reconnect_total']): ?>
                                    <br><small style="color: #c586c0;">→ Enviar tentativa #<?= $cb['reconnect_current'] + 1 ?></small>
                                <?php elseif ($cb['inactivity_mode'] === 'reconnect' && $cb['reconnect_current'] >= $cb['reconnect_total']): ?>
                                    <br><small style="color: #dcdcaa;">→ Ação final</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cb['is_expired']): ?>
                                    <span class="badge badge-miss">EXPIRADO</span>
                                <?php else: ?>
                                    <span class="badge badge-ok">ATIVO</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $actionLabels = [
                                    'go_to_node' => '🔄 Seguir para Nó',
                                    'assign_agent' => '👤 Atribuir Agente',
                                    'send_message' => '💬 Enviar Mensagem',
                                    'close' => '🔒 Encerrar',
                                    'nothing' => '⚪ Nada'
                                ];
                                ?>
                                <span style="color: #dcdcaa;"><?= $actionLabels[$cb['timeout_action']] ?? $cb['timeout_action'] ?></span>
                            </td>
                            <td>
                                <?php if ($cb['timeout_node_id']): ?>
                                    <span class="badge badge-ok">ID: <?= $cb['timeout_node_id'] ?></span>
                                <?php else: ?>
                                    <span style="color: #555;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cb['automation_id']): ?>
                                    <span class="badge" style="background: #007acc; color: #fff;">#<?= $cb['automation_id'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-miss">SEM ID!</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                
                <?php if ($expiredCount > 0): ?>
                <div style="background: rgba(244,135,113,0.1); border: 1px solid #f48771; border-radius: 6px; padding: 12px; margin-top: 15px;">
                    <strong style="color: #f48771;">⚠️ Existem <?= $expiredCount ?> timeout(s) expirado(s) que NÃO foram processados!</strong>
                    <p style="color: #d4d4d4; font-size: 13px; margin-top: 8px;">Possíveis causas:</p>
                    <ol style="color: #d4d4d4; font-size: 13px; padding-left: 20px; line-height: 2;">
                        <li><strong style="color: #4ec9b0;">Cron não está rodando</strong> — Verifique se <code style="color: #ce9178;">run-scheduled-jobs.php</code> está configurado no cron</li>
                        <li><strong style="color: #4ec9b0;">Erro no ChatbotTimeoutJob</strong> — Verifique os logs de automação abaixo</li>
                        <li><strong style="color: #4ec9b0;">Lock de jobs travado</strong> — O arquivo de lock (<code style="color: #ce9178;">/tmp/run_scheduled_jobs.lock</code>) pode estar travado</li>
                    </ol>
                </div>
                <?php endif; ?>
            <?php endif; ?>
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
        
        <?php elseif ($activeTab === 'media_queue'): ?>
        <!-- ═══════════════ ABA MEDIA QUEUE ═══════════════ -->
        <?php
            $mqStats = null;
            $mqItems = [];
            $mqCronLog = [];
            $mqLockFile = '/tmp/process_media_queue.lock';
            $mqCronRunning = file_exists($mqLockFile) && (function() use ($mqLockFile) {
                $fp = @fopen($mqLockFile, 'r');
                if (!$fp) return false;
                $locked = !@flock($fp, LOCK_EX | LOCK_NB);
                if (!$locked) @flock($fp, LOCK_UN);
                @fclose($fp);
                return $locked;
            })();
            
            try {
                if (!isset($db)) {
                    require_once __DIR__ . '/../config/bootstrap.php';
                    $db = \App\Helpers\Database::getInstance();
                }
                
                $mqStats = $db->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                        SUM(CASE WHEN direction = 'download' THEN 1 ELSE 0 END) as downloads,
                        SUM(CASE WHEN direction = 'upload' THEN 1 ELSE 0 END) as uploads
                    FROM media_queue
                ")->fetch(\PDO::FETCH_ASSOC);
                
                $mqItems = $db->query("
                    SELECT id, message_id, conversation_id, account_id, external_message_id,
                           direction, media_type, status, priority, attempts, max_attempts,
                           error_message, next_attempt_at, created_at, updated_at, processed_at
                    FROM media_queue
                    ORDER BY 
                        CASE status 
                            WHEN 'processing' THEN 1 
                            WHEN 'queued' THEN 2 
                            WHEN 'failed' THEN 3 
                            WHEN 'completed' THEN 4 
                            WHEN 'cancelled' THEN 5 
                        END,
                        created_at DESC
                    LIMIT 50
                ")->fetchAll(\PDO::FETCH_ASSOC);
                
            } catch (\Exception $e) {
                $mqStats = null;
            }
            
            $mqLogFile = $logFileMap['media_queue'];
            $mqLogLines = [];
            if (file_exists($mqLogFile)) {
                $allMqLines = file($mqLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $mqLogLines = array_slice(array_reverse($allMqLines), 0, $maxLines);
            }
            
            $mqLastRunLine = null;
            foreach ($mqLogLines as $line) {
                if (strpos($line, 'CRON INICIADO') !== false) {
                    $mqLastRunLine = $line;
                    break;
                }
            }
            $mqLastRunTime = null;
            if ($mqLastRunLine && preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $mqLastRunLine, $m)) {
                $mqLastRunTime = strtotime($m[1]);
            }
            $mqLastRunAgo = $mqLastRunTime ? (time() - $mqLastRunTime) : null;
        ?>
        
        <div class="content">
            <h2 style="color: #9cdcfe; margin-bottom: 15px;">📦 Media Queue — Fila de Downloads/Uploads</h2>
            
            <!-- Status do CRON -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 20px;">
                <div class="stat" style="border-left: 3px solid <?= $mqCronRunning ? '#4ec9b0' : ($mqLastRunAgo !== null && $mqLastRunAgo < 120 ? '#4ec9b0' : '#f48771') ?>;">
                    <div class="label">CRON Status</div>
                    <div class="value" style="font-size: 14px; color: <?= $mqCronRunning ? '#4ec9b0' : ($mqLastRunAgo !== null && $mqLastRunAgo < 120 ? '#4ec9b0' : '#f48771') ?>;">
                        <?php if ($mqCronRunning): ?>
                            🟢 Rodando agora
                        <?php elseif ($mqLastRunAgo !== null && $mqLastRunAgo < 120): ?>
                            🟢 Ativo (há <?= $mqLastRunAgo ?>s)
                        <?php elseif ($mqLastRunAgo !== null): ?>
                            🔴 Parado (há <?= round($mqLastRunAgo / 60, 1) ?> min)
                        <?php else: ?>
                            ⚪ Nunca executou
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($mqStats): ?>
                <div class="stat" style="border-left: 3px solid #569cd6;">
                    <div class="label">Total</div>
                    <div class="value"><?= $mqStats['total'] ?? 0 ?></div>
                </div>
                <div class="stat" style="border-left: 3px solid #dcdcaa;">
                    <div class="label">Na Fila</div>
                    <div class="value" style="color: #dcdcaa;"><?= $mqStats['queued'] ?? 0 ?></div>
                </div>
                <div class="stat" style="border-left: 3px solid #569cd6;">
                    <div class="label">Processando</div>
                    <div class="value" style="color: #569cd6;"><?= $mqStats['processing'] ?? 0 ?></div>
                </div>
                <div class="stat" style="border-left: 3px solid #4ec9b0;">
                    <div class="label">Concluídos</div>
                    <div class="value" style="color: #4ec9b0;"><?= $mqStats['completed'] ?? 0 ?></div>
                </div>
                <div class="stat" style="border-left: 3px solid #f48771;">
                    <div class="label">Falharam</div>
                    <div class="value" style="color: #f48771;"><?= $mqStats['failed'] ?? 0 ?></div>
                </div>
                <div class="stat" style="border-left: 3px solid #858585;">
                    <div class="label">Downloads</div>
                    <div class="value"><?= $mqStats['downloads'] ?? 0 ?></div>
                </div>
                <div class="stat" style="border-left: 3px solid #858585;">
                    <div class="label">Uploads</div>
                    <div class="value"><?= $mqStats['uploads'] ?? 0 ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($mqLastRunAgo !== null && $mqLastRunAgo > 300 && !$mqCronRunning): ?>
            <div style="background: rgba(244,135,113,0.1); border: 1px solid #f48771; border-radius: 6px; padding: 12px; margin-bottom: 15px;">
                <strong style="color: #f48771;">⚠️ O cron da Media Queue parece estar PARADO!</strong>
                <p style="color: #d4d4d4; font-size: 13px; margin-top: 5px;">Última execução foi há <?= round($mqLastRunAgo / 60, 1) ?> minutos.</p>
                <p style="color: #858585; font-size: 12px; margin-top: 5px;">Verifique o crontab ou execute manualmente:
                    <code style="color: #ce9178;">php public/scripts/process-media-queue.php</code>
                </p>
            </div>
            <?php elseif ($mqLastRunAgo === null && !$mqCronRunning): ?>
            <div style="background: rgba(220,220,170,0.1); border: 1px solid #dcdcaa; border-radius: 6px; padding: 12px; margin-bottom: 15px;">
                <strong style="color: #dcdcaa;">⚠️ O cron da Media Queue nunca executou!</strong>
                <p style="color: #858585; font-size: 12px; margin-top: 5px;">Configure no crontab:
                    <code style="color: #ce9178;">* * * * * cd /var/www/html && php public/scripts/process-media-queue.php</code>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Tabela de itens da fila -->
            <?php if (!empty($mqItems)): ?>
            <details open style="margin-bottom: 20px;">
                <summary style="cursor: pointer; color: #9cdcfe; font-size: 14px; font-weight: bold; margin-bottom: 10px;">📋 Itens na Fila (últimos <?= count($mqItems) ?>)</summary>
                <div style="overflow-x: auto;">
                    <table class="diag-table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Dir</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Tentativas</th>
                                <th>Msg ID</th>
                                <th>Conv ID</th>
                                <th>Erro</th>
                                <th>Próxima Tentativa</th>
                                <th>Criado</th>
                                <th>Processado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mqItems as $mqi): ?>
                            <tr style="<?php
                                if ($mqi['status'] === 'failed') echo 'background: rgba(244,135,113,0.08);';
                                elseif ($mqi['status'] === 'processing') echo 'background: rgba(86,156,214,0.08);';
                                elseif ($mqi['status'] === 'completed') echo 'background: rgba(78,201,176,0.05);';
                                elseif ($mqi['status'] === 'queued') echo 'background: rgba(220,220,170,0.05);';
                            ?>">
                                <td style="color: #858585;"><?= $mqi['id'] ?></td>
                                <td>
                                    <?php if ($mqi['direction'] === 'download'): ?>
                                        <span style="color: #4ec9b0;">⬇ down</span>
                                    <?php else: ?>
                                        <span style="color: #569cd6;">⬆ up</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #d4d4d4;"><?= htmlspecialchars($mqi['media_type'] ?? '-') ?></td>
                                <td>
                                    <?php
                                        $statusColors = ['queued' => '#dcdcaa', 'processing' => '#569cd6', 'completed' => '#4ec9b0', 'failed' => '#f48771', 'cancelled' => '#858585'];
                                        $statusLabels = ['queued' => 'Na Fila', 'processing' => 'Processando', 'completed' => 'Concluído', 'failed' => 'Falhou', 'cancelled' => 'Cancelado'];
                                        $sc = $statusColors[$mqi['status']] ?? '#858585';
                                        $sl = $statusLabels[$mqi['status']] ?? $mqi['status'];
                                    ?>
                                    <span class="badge" style="background: <?= $sc ?>20; color: <?= $sc ?>; border: 1px solid <?= $sc ?>40; padding: 2px 6px; border-radius: 3px; font-size: 11px;"><?= $sl ?></span>
                                </td>
                                <td style="text-align: center; color: <?= $mqi['attempts'] >= $mqi['max_attempts'] ? '#f48771' : '#d4d4d4' ?>;">
                                    <?= $mqi['attempts'] ?>/<?= $mqi['max_attempts'] ?>
                                </td>
                                <td style="color: #858585; font-size: 11px;"><?= $mqi['message_id'] ?? '-' ?></td>
                                <td style="color: #858585; font-size: 11px;"><?= $mqi['conversation_id'] ?? '-' ?></td>
                                <td style="color: #f48771; font-size: 11px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($mqi['error_message'] ?? '') ?>">
                                    <?= htmlspecialchars(substr($mqi['error_message'] ?? '-', 0, 60)) ?>
                                </td>
                                <td style="color: #858585; font-size: 11px; white-space: nowrap;"><?= $mqi['next_attempt_at'] ?? '-' ?></td>
                                <td style="color: #858585; font-size: 11px; white-space: nowrap;"><?= $mqi['created_at'] ?></td>
                                <td style="color: #858585; font-size: 11px; white-space: nowrap;"><?= $mqi['processed_at'] ?? '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
            <?php elseif ($mqStats && $mqStats['total'] == 0): ?>
            <div style="background: rgba(78,201,176,0.05); border: 1px solid #4ec9b030; border-radius: 6px; padding: 15px; margin-bottom: 20px; text-align: center;">
                <span style="color: #4ec9b0; font-size: 14px;">✅ Fila vazia — nenhum item pendente.</span>
            </div>
            <?php endif; ?>
            
            <!-- Logs do CRON -->
            <details <?= empty($mqItems) ? 'open' : '' ?> style="margin-bottom: 20px;">
                <summary style="cursor: pointer; color: #9cdcfe; font-size: 14px; font-weight: bold; margin-bottom: 10px;">📄 Logs do CRON (últimas <?= count($mqLogLines) ?> linhas)</summary>
                
                <div style="margin-bottom: 10px;">
                    <form method="get" style="display: flex; gap: 8px; align-items: center;">
                        <input type="hidden" name="tab" value="media_queue">
                        <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" placeholder="Filtrar logs..." 
                               style="background: #2d2d2d; color: #d4d4d4; border: 1px solid #404040; padding: 6px 12px; border-radius: 4px; font-size: 13px; width: 250px;">
                        <select name="level" style="background: #2d2d2d; color: #d4d4d4; border: 1px solid #404040; padding: 6px 8px; border-radius: 4px; font-size: 13px;">
                            <option value="">Todos os níveis</option>
                            <option value="INFO" <?= $level === 'INFO' ? 'selected' : '' ?>>INFO</option>
                            <option value="WARNING" <?= $level === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                            <option value="ERROR" <?= $level === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                        </select>
                        <button type="submit" style="background: #0e639c; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; font-size: 13px;">Filtrar</button>
                        <button type="button" onclick="window.location.href='?tab=media_queue'" style="background: #3c3c3c; color: #d4d4d4; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer; font-size: 13px;">Limpar</button>
                        <span style="color: #858585; font-size: 12px; margin-left: auto;">
                            <a href="?tab=media_queue&lines=1000" style="color: #4ec9b0;">Ver 1000</a> |
                            <a href="?tab=media_queue&lines=5000" style="color: #4ec9b0;">Ver 5000</a>
                        </span>
                    </form>
                </div>
                
                <?php if (empty($mqLogLines)): ?>
                <div style="background: rgba(220,220,170,0.05); border: 1px solid #dcdcaa30; border-radius: 6px; padding: 15px; text-align: center;">
                    <span style="color: #dcdcaa;">⚠️ Nenhum log encontrado. O cron ainda não foi executado ou o arquivo de log não existe.</span>
                    <p style="color: #858585; font-size: 12px; margin-top: 5px;">Esperado em: <code style="color: #ce9178;"><?= htmlspecialchars($mqLogFile) ?></code></p>
                </div>
                <?php else: ?>
                <div class="log-container" style="max-height: 600px; overflow-y: auto; background: #1a1a1a; border-radius: 6px; padding: 10px; font-family: 'Fira Code', 'Cascadia Code', monospace; font-size: 12px; line-height: 1.6;">
                    <?php 
                    $filteredMqLogs = $mqLogLines;
                    if (!empty($filter) || !empty($level)) {
                        $filteredMqLogs = array_filter($filteredMqLogs, function($line) use ($filter, $level) {
                            $matchFilter = empty($filter) || stripos($line, $filter) !== false;
                            $matchLevel = empty($level) || stripos($line, "[$level]") !== false;
                            return $matchFilter && $matchLevel;
                        });
                    }
                    foreach ($filteredMqLogs as $line): 
                        $lineColor = '#d4d4d4';
                        if (strpos($line, '[ERROR]') !== false) $lineColor = '#f48771';
                        elseif (strpos($line, '[WARNING]') !== false) $lineColor = '#dcdcaa';
                        elseif (strpos($line, 'CRON INICIADO') !== false || strpos($line, 'CRON FINALIZADO') !== false) $lineColor = '#569cd6';
                        elseif (strpos($line, 'sucesso') !== false || strpos($line, 'concluído') !== false || strpos($line, 'Concluído') !== false || strpos($line, 'enviado') !== false) $lineColor = '#4ec9b0';
                        elseif (strpos($line, 'falhou') !== false || strpos($line, 'Falhou') !== false) $lineColor = '#f48771';
                    ?>
                    <div style="color: <?= $lineColor ?>; padding: 1px 0; border-bottom: 1px solid #2a2a2a; word-break: break-all;"><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </details>
        </div>
        
        <div class="footer">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?> | <a href="?tab=media_queue" style="color: #4ec9b0;">🔄 Atualizar</a></p>
        </div>
        
        <?php elseif ($activeTab === 'webhook'): ?>
        <!-- ═══════════════ ABA WEBHOOK WOOCOMMERCE ═══════════════ -->
        <?php
            $webhookLogFile = $logFileMap['webhook'] ?? '';
            
            // Ler logs
            $webhookLogs = [];
            $allWebhookLines = [];
            if (file_exists($webhookLogFile)) {
                $allWebhookLines = file($webhookLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $webhookLogs = array_slice(array_reverse($allWebhookLines), 0, $maxLines);
            }
            
            // Aplicar filtro de texto
            if (!empty($filter)) {
                $webhookLogs = array_filter($webhookLogs, function($line) use ($filter) {
                    return stripos($line, $filter) !== false;
                });
            }
            
            // Filtro por nível
            $webhookLevel = $_GET['wh_level'] ?? '';
            if (!empty($webhookLevel)) {
                $webhookLogs = array_filter($webhookLogs, function($line) use ($webhookLevel) {
                    return stripos($line, "[$webhookLevel]") !== false;
                });
            }
            
            // Calcular stats dos logs
            $whStats = ['TOTAL' => 0, 'INFO' => 0, 'SUCCESS' => 0, 'WARNING' => 0, 'ERROR' => 0, 'REQUESTS' => 0, 'PING' => 0, 'IGNORED' => 0, 'PROCESSED' => 0];
            $recentLines = !empty($allWebhookLines) ? array_slice(array_reverse($allWebhookLines), 0, 10000) : [];
            foreach ($recentLines as $l) {
                $whStats['TOTAL']++;
                if (stripos($l, '[INFO]') !== false) $whStats['INFO']++;
                if (stripos($l, '[SUCCESS]') !== false) $whStats['SUCCESS']++;
                if (stripos($l, '[WARNING]') !== false) $whStats['WARNING']++;
                if (stripos($l, '[ERROR]') !== false) $whStats['ERROR']++;
                if (stripos($l, 'WEBHOOK RECEBIDO') !== false || stripos($l, 'REQUEST RECEBIDA') !== false) $whStats['REQUESTS']++;
                if (stripos($l, 'PING') !== false) $whStats['PING']++;
                if (stripos($l, 'Evento ignorado') !== false) $whStats['IGNORED']++;
                if (stripos($l, 'processado com sucesso') !== false) $whStats['PROCESSED']++;
            }
            
            $webhookTotal = count($webhookLogs);
        ?>
        
        <header>
            <h1>🛒 Webhook WooCommerce - Logs Detalhados</h1>
            <p style="color: #888; margin-top: 5px; font-size: 13px;">
                Monitoramento completo de todos os webhooks recebidos do WooCommerce. 
                Endpoint: <code style="color: #4ec9b0;">POST /webhooks/woocommerce</code>
            </p>
        </header>
        
        <!-- Stats -->
        <div class="stats" style="margin-bottom: 15px;">
            <div class="stat" style="cursor:pointer;" onclick="window.location.href='?tab=webhook&lines=<?= $maxLines ?>'">
                <div class="stat-label">Requests Total</div>
                <div class="stat-value" style="color: #569cd6;"><?= $whStats['REQUESTS'] ?></div>
            </div>
            <div class="stat" style="cursor:pointer;" onclick="window.location.href='?tab=webhook&wh_level=SUCCESS&lines=<?= $maxLines ?>'">
                <div class="stat-label">Processados OK</div>
                <div class="stat-value" style="color: #4ec9b0;"><?= $whStats['PROCESSED'] ?></div>
            </div>
            <div class="stat" style="cursor:pointer;" onclick="window.location.href='?tab=webhook&wh_level=ERROR&lines=<?= $maxLines ?>'">
                <div class="stat-label">Erros</div>
                <div class="stat-value" style="color: #f44747;"><?= $whStats['ERROR'] ?></div>
            </div>
            <div class="stat" style="cursor:pointer;" onclick="window.location.href='?tab=webhook&wh_level=WARNING&lines=<?= $maxLines ?>'">
                <div class="stat-label">Avisos</div>
                <div class="stat-value" style="color: #dcdcaa;"><?= $whStats['WARNING'] ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Pings</div>
                <div class="stat-value" style="color: #888;"><?= $whStats['PING'] ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Eventos Ignorados</div>
                <div class="stat-value" style="color: #ce9178;"><?= $whStats['IGNORED'] ?></div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div style="background: #252526; padding: 12px; border-radius: 6px; margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                <input type="hidden" name="tab" value="webhook">
                <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" placeholder="Buscar: order ID, email, erro, IP..." 
                    style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px; flex: 1; min-width: 200px;">
                <select name="wh_level" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px;">
                    <option value="">Todos os níveis</option>
                    <option value="INFO" <?= $webhookLevel === 'INFO' ? 'selected' : '' ?>>INFO</option>
                    <option value="SUCCESS" <?= $webhookLevel === 'SUCCESS' ? 'selected' : '' ?>>SUCCESS</option>
                    <option value="WARNING" <?= $webhookLevel === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                    <option value="ERROR" <?= $webhookLevel === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                </select>
                <select name="lines" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px;">
                    <option value="100" <?= $maxLines == 100 ? 'selected' : '' ?>>100 linhas</option>
                    <option value="500" <?= $maxLines == 500 ? 'selected' : '' ?>>500 linhas</option>
                    <option value="1000" <?= $maxLines == 1000 ? 'selected' : '' ?>>1000 linhas</option>
                    <option value="5000" <?= $maxLines == 5000 ? 'selected' : '' ?>>5000 linhas</option>
                </select>
                <button type="submit" style="background: #0e639c; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer;">🔍 Filtrar</button>
                <button type="button" onclick="window.location.href='?tab=webhook'" style="background: #3c3c3c; color: #d4d4d4; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer;">🔄 Limpar</button>
            </form>
        </div>
        
        <!-- Dica de debug -->
        <div style="background: #1e3a1e; border: 1px solid #2d5a2d; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 12px; color: #b5cea8;">
            <strong>💡 Dicas para Debug:</strong><br>
            • Cada request gera uma linha <code style="color: #4ec9b0;">REQUEST RECEBIDA</code> com IP, método, headers e tamanho do body<br>
            • Se um webhook do WooCommerce não aparece aqui, o problema é <strong>antes</strong> de chegar no servidor (firewall, DNS, timeout do WooCommerce, etc.)<br>
            • Filtre por <code style="color: #4ec9b0;">REQUEST_ID</code> para rastrear todo o fluxo de uma única request<br>
            • Status HTTP 200 = sucesso, 400 = payload inválido, 500 = erro interno
        </div>
        
        <!-- Info do arquivo -->
        <div style="background: #252526; padding: 8px 15px; border-radius: 6px; margin-bottom: 10px; font-size: 12px; color: #888;">
            📁 Arquivo: <span style="color: #d4d4d4;"><?= htmlspecialchars($webhookLogFile) ?></span> | 
            <?php if (file_exists($webhookLogFile)): ?>
                Tamanho: <span style="color: #d4d4d4;"><?= number_format(filesize($webhookLogFile) / 1024, 1) ?> KB</span> | 
                Última modificação: <span style="color: #d4d4d4;"><?= date('d/m/Y H:i:s', filemtime($webhookLogFile)) ?></span> | 
            <?php endif; ?>
            Exibindo: <span style="color: #4ec9b0;"><?= $webhookTotal ?></span> linhas
            <?php if (!empty($webhookLevel)): ?>
                | Filtro nível: <span style="color: #dcdcaa;">[<?= $webhookLevel ?>]</span>
            <?php endif; ?>
        </div>
        
        <!-- Logs -->
        <div class="log-container" style="max-height: 70vh; overflow-y: auto; background: #1e1e1e; border-radius: 6px; padding: 10px;">
            <?php if (empty($webhookLogs)): ?>
                <div style="text-align: center; padding: 40px; color: #888;">
                    <?php if (!file_exists($webhookLogFile)): ?>
                        <p style="font-size: 18px;">📭 Arquivo de log ainda não existe</p>
                        <p style="margin-top: 10px;">O arquivo <code style="color: #4ec9b0;">webhook.log</code> será criado quando o primeiro webhook for recebido.</p>
                        <p style="margin-top: 5px; font-size: 12px;">Configure o webhook no WooCommerce para: <code style="color: #4ec9b0;">https://chat.personizi.com.br/webhooks/woocommerce</code></p>
                    <?php else: ?>
                        <p style="font-size: 18px;">🔍 Nenhum log encontrado com os filtros aplicados</p>
                        <p style="margin-top: 10px;">Tente ajustar ou limpar os filtros.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($webhookLogs as $line): ?>
                    <?php
                    $lineColor = '#d4d4d4';
                    $bgColor = 'transparent';
                    $borderLeft = 'none';
                    if (stripos($line, '[ERROR]') !== false || strpos($line, '❌') !== false) {
                        $lineColor = '#f44747';
                        $bgColor = 'rgba(244, 71, 71, 0.08)';
                        $borderLeft = '3px solid #f44747';
                    } elseif (stripos($line, '[WARNING]') !== false || strpos($line, '⚠️') !== false) {
                        $lineColor = '#dcdcaa';
                        $bgColor = 'rgba(220, 220, 170, 0.05)';
                        $borderLeft = '3px solid #dcdcaa';
                    } elseif (stripos($line, '[SUCCESS]') !== false || strpos($line, '✅') !== false) {
                        $lineColor = '#4ec9b0';
                        $bgColor = 'rgba(78, 201, 176, 0.05)';
                        $borderLeft = '3px solid #4ec9b0';
                    } elseif (stripos($line, 'REQUEST RECEBIDA') !== false || stripos($line, 'WEBHOOK RECEBIDO') !== false) {
                        $lineColor = '#569cd6';
                        $bgColor = 'rgba(86, 156, 214, 0.08)';
                        $borderLeft = '3px solid #569cd6';
                    } elseif (stripos($line, 'PING') !== false) {
                        $lineColor = '#888';
                    }
                    ?>
                    <div style="padding: 3px 8px; font-family: 'Consolas', 'Courier New', monospace; font-size: 12px; color: <?= $lineColor ?>; background: <?= $bgColor ?>; border-bottom: 1px solid #2a2a2a; border-left: <?= $borderLeft ?>; word-break: break-all;">
                        <?= htmlspecialchars($line) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer" style="margin-top: 15px;">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?></p>
            <p style="margin-top: 5px; font-size: 12px; color: #888;">
                Auto-refresh: 
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 5000)" style="color: #4ec9b0;">5s</a> | 
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 15000)" style="color: #4ec9b0;">15s</a> | 
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 30000)" style="color: #4ec9b0;">30s</a>
            </p>
        </div>
        
        <?php elseif ($activeTab === 'wc_sync'): ?>
        <!-- ═══════════════ ABA CRON SYNC WOOCOMMERCE ═══════════════ -->
        <?php
            $wcSyncLogFile = $logFileMap['wc_sync'] ?? '';
            $wcSyncLines = [];
            if (file_exists($wcSyncLogFile)) {
                $allWcLines = file($wcSyncLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $wcSyncLines = array_slice(array_reverse($allWcLines), 0, $maxLines);
            }
            if (!empty($filter)) {
                $wcSyncLines = array_filter($wcSyncLines, fn($l) => stripos($l, $filter) !== false);
            }
            $wcSyncLevelFilter = $_GET['wcs_level'] ?? '';
            if (!empty($wcSyncLevelFilter)) {
                $wcSyncLines = array_filter($wcSyncLines, fn($l) => stripos($l, "[{$wcSyncLevelFilter}]") !== false);
            }
            $wcsStats = ['SUCCESS' => 0, 'WARNING' => 0, 'ERROR' => 0, 'INFO' => 0];
            foreach (($allWcLines ?? []) as $l) {
                foreach (array_keys($wcsStats) as $lvl) {
                    if (stripos($l, "[{$lvl}]") !== false) { $wcsStats[$lvl]++; break; }
                }
            }
        ?>
        <header>
            <h1>🔄 Cron Sync WooCommerce — Logs</h1>
            <p style="color:#888;margin-top:5px;font-size:13px;">
                Execuções automáticas do cron (a cada hora) — sincroniza pedidos dos últimos <strong>30 dias</strong> com TTL de <strong>30 dias</strong>.
            </p>
        </header>
        <div class="stats" style="margin-bottom:15px;">
            <div class="stat"><div class="stat-label">Sucesso</div><div class="stat-value" style="color:#4ec9b0;"><?= $wcsStats['SUCCESS'] ?></div></div>
            <div class="stat"><div class="stat-label">Avisos</div><div class="stat-value" style="color:#dcdcaa;"><?= $wcsStats['WARNING'] ?></div></div>
            <div class="stat"><div class="stat-label">Erros</div><div class="stat-value" style="color:#f44747;"><?= $wcsStats['ERROR'] ?></div></div>
            <div class="stat"><div class="stat-label">Info</div><div class="stat-value" style="color:#569cd6;"><?= $wcsStats['INFO'] ?></div></div>
        </div>
        <div style="background:#252526;padding:12px;border-radius:6px;margin-bottom:15px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%;">
                <input type="hidden" name="tab" value="wc_sync">
                <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" placeholder="Buscar: integração, pedidos, erro..."
                    style="background:#1e1e1e;color:#d4d4d4;border:1px solid #3c3c3c;padding:6px 12px;border-radius:4px;flex:1;min-width:200px;">
                <select name="wcs_level" style="background:#1e1e1e;color:#d4d4d4;border:1px solid #3c3c3c;padding:6px 12px;border-radius:4px;">
                    <option value="">Todos</option>
                    <option value="SUCCESS" <?= $wcSyncLevelFilter==='SUCCESS'?'selected':'' ?>>SUCCESS</option>
                    <option value="WARNING" <?= $wcSyncLevelFilter==='WARNING'?'selected':'' ?>>WARNING</option>
                    <option value="ERROR" <?= $wcSyncLevelFilter==='ERROR'?'selected':'' ?>>ERROR</option>
                    <option value="INFO" <?= $wcSyncLevelFilter==='INFO'?'selected':'' ?>>INFO</option>
                </select>
                <select name="lines" style="background:#1e1e1e;color:#d4d4d4;border:1px solid #3c3c3c;padding:6px 12px;border-radius:4px;">
                    <option value="200" <?= $maxLines==200?'selected':'' ?>>200</option>
                    <option value="500" <?= $maxLines==500?'selected':'' ?>>500</option>
                    <option value="1000" <?= $maxLines==1000?'selected':'' ?>>1000</option>
                </select>
                <button type="submit" style="background:#0e639c;color:white;border:none;padding:6px 16px;border-radius:4px;cursor:pointer;">🔍 Filtrar</button>
                <a href="?tab=wc_sync" style="background:#3c3c3c;color:#d4d4d4;padding:6px 16px;border-radius:4px;text-decoration:none;">🔄 Limpar</a>
            </form>
        </div>
        <div style="background:#1a2e1a;border:1px solid #2d5a2d;padding:10px 15px;border-radius:6px;margin-bottom:15px;font-size:12px;color:#b5cea8;">
            <strong>💡 Como funciona o cron:</strong><br>
            • Executa automaticamente a cada <strong>1 hora</strong> via <code style="color:#4ec9b0;">run-scheduled-jobs.php</code><br>
            • Busca pedidos dos últimos <strong>30 dias</strong> do WooCommerce e salva no cache com TTL de <strong>30 dias</strong><br>
            • <code style="color:#dcdcaa;">[WARNING] Limpeza</code> indica pedidos removidos por TTL expirado (>30 dias sem renovação) — normal<br>
            • <code style="color:#f44747;">[ERROR]</code> indica falha de comunicação com a API WooCommerce
        </div>
        <div style="background:#252526;padding:8px 15px;border-radius:6px;margin-bottom:10px;font-size:12px;color:#888;">
            📁 <span style="color:#d4d4d4;"><?= htmlspecialchars($wcSyncLogFile) ?></span>
            <?php if (file_exists($wcSyncLogFile)): ?>
                | Tamanho: <span style="color:#d4d4d4;"><?= number_format(filesize($wcSyncLogFile)/1024, 1) ?> KB</span>
                | Última modificação: <span style="color:#d4d4d4;"><?= date('d/m/Y H:i:s', filemtime($wcSyncLogFile)) ?></span>
            <?php endif; ?>
            | Exibindo: <span style="color:#4ec9b0;"><?= count($wcSyncLines) ?></span> linhas
        </div>
        <div class="log-container" style="max-height:70vh;overflow-y:auto;background:#1e1e1e;border-radius:6px;padding:10px;">
            <?php if (empty($wcSyncLines)): ?>
                <div style="text-align:center;padding:40px;color:#888;">
                    <?php if (!file_exists($wcSyncLogFile)): ?>
                        <p style="font-size:18px;">📭 Arquivo de log ainda não existe</p>
                        <p style="margin-top:10px;">O arquivo <code style="color:#4ec9b0;">wc_sync.log</code> será criado na próxima execução do cron.</p>
                        <p style="margin-top:5px;font-size:12px;">Force a execução acessando: <code style="color:#4ec9b0;">/run-scheduled-jobs.php?force_wc_sync=1</code></p>
                    <?php else: ?>
                        <p>Nenhum log encontrado com os filtros aplicados.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($wcSyncLines as $line): ?>
                    <?php
                        $color = '#d4d4d4';
                        if (stripos($line, '[ERROR]') !== false) $color = '#f44747';
                        elseif (stripos($line, '[WARNING]') !== false) $color = '#dcdcaa';
                        elseif (stripos($line, '[SUCCESS]') !== false) $color = '#4ec9b0';
                        elseif (stripos($line, '[INFO]') !== false) $color = '#569cd6';
                    ?>
                    <div style="color:<?= $color ?>;font-size:12px;line-height:1.6;border-bottom:1px solid #2d2d2d;padding:2px 0;">
                        <?= htmlspecialchars($line) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="footer" style="margin-top:15px;">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?></p>
        </div>

        <?php elseif ($activeTab === 'instagram'): ?>
        <!-- ═══════════════ ABA INSTAGRAM DIAGNÓSTICO ═══════════════ -->
        <header>
            <h1>📷 Diagnóstico Instagram / Notificame</h1>
            <p style="color:#888;margin-top:5px;font-size:13px;">Verifica configuração de contas, webhook, envio e recebimento de mensagens Instagram via Notificame.</p>
        </header>

        <?php if (!empty($instagramData['error'])): ?>
            <div class="alert alert-error">❌ Erro ao carregar dados: <?= htmlspecialchars($instagramData['error']) ?></div>
        <?php else: ?>

        <!-- ── Totais ── -->
        <div class="stats" style="margin-bottom:20px;">
            <div class="stat">
                <div class="stat-label">Contas Integração Instagram</div>
                <div class="stat-value" style="color:#e1306c;"><?= count($instagramData['integrationAccounts']) ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Instagram Accounts (Meta)</div>
                <div class="stat-value" style="color:#4ec9b0;"><?= count(array_filter($instagramData['igAccounts'], fn($r) => !isset($r['_error']))) ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Conversas Instagram</div>
                <div class="stat-value"><?= $instagramData['totalConvs'] ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Mensagens Instagram</div>
                <div class="stat-value"><?= $instagramData['totalMsgs'] ?></div>
            </div>
            <div class="stat <?= $instagramData['totalConvsNoAccount'] > 0 ? 'errors' : '' ?>">
                <div class="stat-label">Conversas sem conta vinculada</div>
                <div class="stat-value"><?= $instagramData['totalConvsNoAccount'] ?></div>
            </div>
        </div>

        <!-- ── Diagnóstico de Configuração ── -->
        <div style="background:#1e3a1e;border:1px solid #3a3;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="color:#4ec9b0;margin-bottom:15px;font-size:16px;">🔍 Diagnóstico de Configuração</h2>

            <?php if (empty($instagramData['integrationAccounts'])): ?>
                <div style="color:#f44747;padding:15px;background:#2d1a1a;border-radius:6px;">
                    ❌ <strong>Nenhuma conta de integração Instagram encontrada!</strong><br>
                    <span style="color:#888;font-size:13px;">Acesse Configurações → Integrações → Notificame e crie uma conta com canal = instagram.</span>
                </div>
            <?php else: ?>
                <?php foreach ($instagramData['diagnostics'] as $diag): ?>
                <div style="background:#252526;border-radius:6px;padding:15px;margin-bottom:15px;border-left:4px solid <?= empty($diag['issues']) ? '#4ec9b0' : '#f44747' ?>;">
                    <div style="font-size:15px;font-weight:bold;color:#fff;margin-bottom:10px;">
                        <?= empty($diag['issues']) ? '✅' : '❌' ?> 
                        Conta: <?= htmlspecialchars($diag['account']['name']) ?>
                        <span style="color:#858585;font-size:12px;margin-left:10px;">ID: <?= $diag['account']['id'] ?> | Provider: <?= $diag['account']['provider'] ?></span>
                    </div>

                    <?php if (!empty($diag['issues'])): ?>
                    <div style="margin-bottom:10px;">
                        <div style="color:#f44747;font-size:12px;font-weight:bold;margin-bottom:5px;">PROBLEMAS ENCONTRADOS:</div>
                        <?php foreach ($diag['issues'] as $issue): ?>
                            <div style="color:#f88;font-size:13px;padding:3px 0;">⚠️ <?= htmlspecialchars($issue) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($diag['ok'])): ?>
                    <div>
                        <div style="color:#4ec9b0;font-size:12px;font-weight:bold;margin-bottom:5px;">OK:</div>
                        <?php foreach ($diag['ok'] as $ok): ?>
                            <div style="color:#aaa;font-size:12px;padding:2px 0;">✓ <?= htmlspecialchars($ok) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div style="margin-top:12px;padding-top:10px;border-top:1px solid #333;">
                        <div style="color:#858585;font-size:11px;margin-bottom:4px;">DETALHES DA CONTA:</div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:6px;">
                            <div style="font-size:12px;color:#ccc;">account_id: <span style="color:<?= empty($diag['account']['account_id']) ? '#f44747' : '#4ec9b0' ?>"><?= htmlspecialchars($diag['account']['account_id'] ?? '(vazio)') ?></span></div>
                            <div style="font-size:12px;color:#ccc;">username: <span style="color:#dcdcaa;"><?= htmlspecialchars($diag['account']['username'] ?? '(vazio)') ?></span></div>
                            <div style="font-size:12px;color:#ccc;">api_url: <span style="color:#9cdcfe;"><?= htmlspecialchars($diag['account']['api_url'] ?? '(padrão)') ?></span></div>
                            <div style="font-size:12px;color:#ccc;">webhook_url: <span style="color:<?= empty($diag['account']['webhook_url']) ? '#f44747' : '#9cdcfe' ?>"><?= htmlspecialchars($diag['account']['webhook_url'] ?? '(não configurado)') ?></span></div>
                            <div style="font-size:12px;color:#ccc;">status: <span style="color:<?= $diag['account']['status'] === 'active' ? '#4ec9b0' : '#f44747' ?>"><?= htmlspecialchars($diag['account']['status']) ?></span></div>
                            <div style="font-size:12px;color:#ccc;">last_sync: <span style="color:#858585;"><?= htmlspecialchars($diag['account']['last_sync_at'] ?? 'nunca') ?></span></div>
                        </div>
                        <?php if (!empty($diag['account']['error_message'])): ?>
                        <div style="color:#f44747;font-size:12px;margin-top:6px;">Último erro: <?= htmlspecialchars($diag['account']['error_message']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ── Webhook Info ── -->
        <div style="background:#252526;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="color:#dcdcaa;margin-bottom:12px;font-size:16px;">🔗 Configuração do Webhook Notificame</h2>
            <p style="color:#aaa;font-size:13px;margin-bottom:10px;">O Notificame precisa enviar webhooks para esta URL:</p>
            <div style="background:#1e1e1e;padding:10px 15px;border-radius:4px;border-left:3px solid #dcdcaa;margin-bottom:15px;">
                <code style="color:#4ec9b0;font-size:14px;"><?= htmlspecialchars($instagramData['webhookUrl']) ?></code>
            </div>
            <div style="color:#888;font-size:12px;line-height:1.8;">
                <div>• O canal Instagram no webhook é identificado pelo campo <code style="color:#dcdcaa;">channel</code> no payload</div>
                <div>• O endpoint aceita: <code style="color:#9cdcfe;">POST /notificame-webhook.php</code></div>
                <div>• Para configurar via API: use <code style="color:#9cdcfe;">POST /subscriptions/</code> com <code style="color:#dcdcaa;">criteria.channel = {account_id}</code></div>
                <div>• O <code style="color:#e1306c;">account_id</code> da conta de integração é usado como campo <strong>from</strong> no envio de mensagens</div>
            </div>
        </div>

        <!-- ── Contas Instagram (Meta Graph API) ── -->
        <?php if (!empty($instagramData['igAccounts'])): ?>
        <div style="background:#252526;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="color:#c586c0;margin-bottom:12px;font-size:16px;">🎭 Instagram Accounts (Meta Graph API)</h2>
            <?php if (isset($instagramData['igAccounts'][0]['_error'])): ?>
                <div style="color:#f44747;font-size:13px;">⚠️ <?= htmlspecialchars($instagramData['igAccounts'][0]['_error']) ?></div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="color:#858585;border-bottom:1px solid #3c3c3c;">
                            <th style="text-align:left;padding:6px 10px;">ID</th>
                            <th style="text-align:left;padding:6px 10px;">instagram_user_id</th>
                            <th style="text-align:left;padding:6px 10px;">username</th>
                            <th style="text-align:left;padding:6px 10px;">account_type</th>
                            <th style="text-align:left;padding:6px 10px;">integration_account_id</th>
                            <th style="text-align:left;padding:6px 10px;">is_active</th>
                            <th style="text-align:left;padding:6px 10px;">is_connected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instagramData['igAccounts'] as $ig): ?>
                        <tr style="border-bottom:1px solid #2d2d30;">
                            <td style="padding:6px 10px;color:#9cdcfe;"><?= $ig['id'] ?></td>
                            <td style="padding:6px 10px;color:#dcdcaa;"><?= htmlspecialchars($ig['instagram_user_id'] ?? '') ?></td>
                            <td style="padding:6px 10px;color:#e1306c;">@<?= htmlspecialchars($ig['username'] ?? '') ?></td>
                            <td style="padding:6px 10px;"><?= htmlspecialchars($ig['account_type'] ?? '') ?></td>
                            <td style="padding:6px 10px;color:<?= empty($ig['integration_account_id']) ? '#f44747' : '#4ec9b0' ?>;">
                                <?= $ig['integration_account_id'] ?? '❌ NULL' ?>
                                <?php if (!empty($ig['integration_name'])): ?>
                                    <span style="color:#888;"> (<?= htmlspecialchars($ig['integration_name']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:6px 10px;color:<?= $ig['is_active'] ? '#4ec9b0' : '#f44747' ?>;"><?= $ig['is_active'] ? 'Sim' : 'Não' ?></td>
                            <td style="padding:6px 10px;color:<?= $ig['is_connected'] ? '#4ec9b0' : '#f44747' ?>;"><?= $ig['is_connected'] ? 'Sim' : 'Não' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── Conversas Recentes ── -->
        <div style="background:#252526;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="color:#569cd6;margin-bottom:12px;font-size:16px;">💬 Conversas Instagram Recentes (últimas 30)</h2>
            <?php if (empty($instagramData['conversations'])): ?>
                <p style="color:#858585;font-size:13px;">Nenhuma conversa Instagram encontrada. Aguardando primeiro contato via webhook.</p>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="color:#858585;border-bottom:1px solid #3c3c3c;">
                            <th style="text-align:left;padding:6px 10px;">Conv ID</th>
                            <th style="text-align:left;padding:6px 10px;">Canal</th>
                            <th style="text-align:left;padding:6px 10px;">Contato</th>
                            <th style="text-align:left;padding:6px 10px;">Identifier</th>
                            <th style="text-align:left;padding:6px 10px;">Conta Integração</th>
                            <th style="text-align:left;padding:6px 10px;">Status</th>
                            <th style="text-align:left;padding:6px 10px;">Msgs</th>
                            <th style="text-align:left;padding:6px 10px;">Atualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instagramData['conversations'] as $conv): ?>
                        <tr style="border-bottom:1px solid #2d2d30;">
                            <td style="padding:6px 10px;color:#9cdcfe;"><?= $conv['id'] ?></td>
                            <td style="padding:6px 10px;color:<?= $conv['channel'] === 'instagram_comment' ? '#dcdcaa' : '#e1306c' ?>;"><?= htmlspecialchars($conv['channel']) ?></td>
                            <td style="padding:6px 10px;"><?= htmlspecialchars($conv['contact_name'] ?? '—') ?></td>
                            <td style="padding:6px 10px;color:#858585;font-size:11px;"><?= htmlspecialchars($conv['contact_identifier'] ?? '—') ?></td>
                            <td style="padding:6px 10px;color:<?= empty($conv['integration_account_id']) ? '#f44747' : '#4ec9b0' ?>;">
                                <?= empty($conv['integration_account_id']) ? '❌ NULL' : ($conv['account_name'] ?? $conv['integration_account_id']) ?>
                            </td>
                            <td style="padding:6px 10px;"><?= htmlspecialchars($conv['status']) ?></td>
                            <td style="padding:6px 10px;color:#9cdcfe;"><?= $conv['msg_count'] ?></td>
                            <td style="padding:6px 10px;color:#858585;font-size:11px;"><?= $conv['updated_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Últimas Mensagens ── -->
        <?php if (!empty($instagramData['messages'])): ?>
        <div style="background:#252526;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="color:#b5cea8;margin-bottom:12px;font-size:16px;">📨 Últimas Mensagens Instagram</h2>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="color:#858585;border-bottom:1px solid #3c3c3c;">
                            <th style="text-align:left;padding:6px 10px;">ID</th>
                            <th style="text-align:left;padding:6px 10px;">Conv</th>
                            <th style="text-align:left;padding:6px 10px;">Direção</th>
                            <th style="text-align:left;padding:6px 10px;">Tipo</th>
                            <th style="text-align:left;padding:6px 10px;">Status</th>
                            <th style="text-align:left;padding:6px 10px;">Conteúdo</th>
                            <th style="text-align:left;padding:6px 10px;">Criado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instagramData['messages'] as $msg):
                            $isInbound = ($msg['sender_type'] ?? '') === 'contact';
                        ?>
                        <tr style="border-bottom:1px solid #2d2d30;">
                            <td style="padding:6px 10px;color:#9cdcfe;"><?= $msg['id'] ?></td>
                            <td style="padding:6px 10px;color:#9cdcfe;"><?= $msg['conversation_id'] ?></td>
                            <td style="padding:6px 10px;color:<?= $isInbound ? '#4ec9b0' : '#c586c0' ?>;">
                                <?= $isInbound ? '⬇ IN' : '⬆ OUT' ?>
                                <span style="color:#555;font-size:10px;">(<?= htmlspecialchars($msg['sender_type'] ?? '?') ?>)</span>
                            </td>
                            <td style="padding:6px 10px;"><?= htmlspecialchars($msg['message_type'] ?? 'text') ?></td>
                            <td style="padding:6px 10px;color:<?= $msg['status'] === 'sent' || $msg['status'] === 'delivered' ? '#4ec9b0' : ($msg['status'] === 'failed' ? '#f44747' : '#858585') ?>;"><?= htmlspecialchars($msg['status'] ?? '—') ?></td>
                            <td style="padding:6px 10px;color:#d4d4d4;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($msg['content'] ?? '') ?>">
                                <?= htmlspecialchars(mb_substr($msg['content'] ?? '', 0, 80)) ?>
                            </td>
                            <td style="padding:6px 10px;color:#858585;font-size:11px;"><?= $msg['created_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Logs Notificame (filtrado Instagram) ── -->
        <div style="background:#252526;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="color:#6c63ff;margin-bottom:12px;font-size:16px;">
                📋 Últimas Entradas no notificame.log (filtradas: instagram)
                <a href="?tab=notificame" style="font-size:12px;color:#9c99ff;margin-left:10px;">Ver log completo →</a>
            </h2>
            <?php if (empty($instagramData['igLogLines'])): ?>
                <div style="color:#858585;font-size:13px;">
                    <p>Nenhuma linha com "instagram" encontrada no notificame.log.</p>
                    <?php $logPath = realpath(__DIR__ . '/../logs/notificame.log'); ?>
                    <p style="margin-top:5px;">Arquivo: <code style="color:#4ec9b0;"><?= $logPath ?: '(não existe ainda — será criado na primeira requisição)' ?></code></p>
                    <p style="margin-top:8px;color:#dcdcaa;">💡 Para testar, envie uma mensagem de DM no Instagram para o perfil conectado.</p>
                </div>
            <?php else: ?>
                <div style="font-size:11px;color:#858585;margin-bottom:8px;">Exibindo últimas <?= count($instagramData['igLogLines']) ?> linhas com "instagram"</div>
                <div style="background:#1e1e1e;border-radius:4px;padding:10px;max-height:400px;overflow-y:auto;">
                    <?php foreach ($instagramData['igLogLines'] as $line):
                        $lineColor = '#d4d4d4';
                        if (stripos($line, '[ERROR]') !== false) $lineColor = '#f44747';
                        elseif (stripos($line, '[WARNING]') !== false) $lineColor = '#dcdcaa';
                        elseif (stripos($line, '[INFO]') !== false) $lineColor = '#569cd6';
                    ?>
                        <div style="color:<?= $lineColor ?>;font-size:11px;line-height:1.7;border-bottom:1px solid #2d2d2d;padding:2px 0;word-break:break-all;">
                            <?= htmlspecialchars($line) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Guia de Troubleshooting ── -->
        <div style="background:#1a1a2e;border:1px solid #6c63ff;border-radius:8px;padding:20px;margin-bottom:20px;">
            <h2 style="color:#9c99ff;margin-bottom:15px;font-size:16px;">🛠️ Guia de Troubleshooting Instagram + Notificame</h2>
            <div style="color:#ccc;font-size:13px;line-height:2;">
                <div style="margin-bottom:10px;"><span style="color:#f44747;font-weight:bold;">❌ Não recebe mensagens?</span></div>
                <div style="padding-left:15px;color:#aaa;">
                    1. Verifique se o <strong style="color:#dcdcaa;">webhook_url</strong> está configurado na conta de integração<br>
                    2. O Notificame precisa apontar o webhook para: <code style="color:#4ec9b0;"><?= htmlspecialchars($instagramData['webhookUrl']) ?></code><br>
                    3. Configure via API Notificame: <code style="color:#9cdcfe;">POST /subscriptions/</code> com <code>criteria.channel = {account_id}</code><br>
                    4. Verifique se o <strong style="color:#e1306c;">account_id</strong> da conta de integração está preenchido (ID do canal Instagram no Notificame)<br>
                    5. Confirme no painel Notificame que o canal Instagram está conectado e ativo<br>
                    6. Verifique os logs: <a href="?tab=notificame" style="color:#9c99ff;">Aba Logs Notificame</a>
                </div>
                <div style="margin-top:15px;margin-bottom:10px;"><span style="color:#f44747;font-weight:bold;">❌ Não envia mensagens?</span></div>
                <div style="padding-left:15px;color:#aaa;">
                    1. O campo <strong style="color:#e1306c;">account_id</strong> é obrigatório para envio (usado como <code>from</code> no payload)<br>
                    2. Endpoint usado: <code style="color:#4ec9b0;">POST /channels/instagram/messages</code><br>
                    3. Payload esperado: <code style="color:#dcdcaa;">{"from": "{account_id}", "to": "{instagram_user_id}", "contents": [{"type":"text","text":"..."}]}</code><br>
                    4. Verifique se o token API está correto e tem permissão para enviar mensagens<br>
                    5. Verifique os logs de envio na <a href="?tab=notificame" style="color:#9c99ff;">aba Notificame</a> — procure por "sendMessage"
                </div>
                <div style="margin-top:15px;margin-bottom:10px;"><span style="color:#dcdcaa;font-weight:bold;">⚠️ Tabela instagram_accounts vazia?</span></div>
                <div style="padding-left:15px;color:#aaa;">
                    A tabela <code>instagram_accounts</code> é usada pela integração <strong>Meta Graph API direta</strong> (não Notificame).<br>
                    Se você usa Notificame, apenas a tabela <code>integration_accounts</code> (com channel=instagram) é necessária.
                </div>
            </div>
        </div>

        <?php endif; // instagramData error check ?>

        <div class="footer">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?> | <a href="?tab=instagram" style="color:#e1306c;">🔄 Atualizar</a></p>
        </div>

        <?php elseif ($activeTab === 'wa_notificame'): ?>
        <!-- ═══════════════ ABA WHATSAPP NOTIFICAME DIAGNÓSTICO ═══════════════ -->
        <?php if (isset($waNotificameData['error'])): ?>
            <header><h1>📲 WhatsApp Notificame — Diagnóstico</h1></header>
            <div class="log-line error">❌ Erro ao carregar dados: <?= htmlspecialchars($waNotificameData['error']) ?></div>
        <?php else: ?>
            <header>
                <h1>📲 Diagnóstico WhatsApp / Notificame</h1>
                <p style="color:#888;margin-top:5px;font-size:13px;">Contas, templates, mensagens, validações e logs do WhatsApp via NotificaMe Hub.</p>
                <div class="stats" style="margin-top:15px;">
                    <div class="stat"><div class="stat-label">Contas WA</div><div class="stat-value" style="color:#25d366;"><?= count($waNotificameData['waAccounts']) ?></div></div>
                    <div class="stat"><div class="stat-label">Total Notificame</div><div class="stat-value" style="color:#6c63ff;"><?= count($waNotificameData['allAccounts']) ?></div></div>
                    <div class="stat"><div class="stat-label">Conversas</div><div class="stat-value"><?= $waNotificameData['totalConvs'] ?></div></div>
                    <div class="stat"><div class="stat-label">Msgs Total</div><div class="stat-value"><?= $waNotificameData['totalMsgs'] ?></div></div>
                    <div class="stat"><div class="stat-label">Recebidas</div><div class="stat-value" style="color:#4ec9b0;"><?= $waNotificameData['totalMsgsIn'] ?></div></div>
                    <div class="stat"><div class="stat-label">Enviadas</div><div class="stat-value" style="color:#569cd6;"><?= $waNotificameData['totalMsgsOut'] ?></div></div>
                    <div class="stat errors"><div class="stat-label">Falhas</div><div class="stat-value"><?= $waNotificameData['totalFailed'] ?></div></div>
                </div>
            </header>

            <!-- ── Diagnóstico de Configuração por Conta ── -->
            <h2 style="color:#25d366;margin:20px 0 12px;font-size:16px;">🔧 Diagnóstico de Configuração</h2>
            <?php if (empty($waNotificameData['waAccounts'])): ?>
                <div style="background:#2d2d2d;padding:20px;border-radius:6px;text-align:center;">
                    <p style="color:#dcdcaa;font-size:15px;">Nenhuma conta WhatsApp Notificame encontrada</p>
                    <span style="color:#888;font-size:13px;">Acesse Integrações → Notificame e crie uma conta com canal = whatsapp.</span>
                </div>
            <?php else: ?>
                <?php foreach ($waNotificameData['diagnostics'] as $diag): ?>
                <div style="background:#2d2d2d;padding:15px;border-radius:6px;margin-bottom:12px;border-left:3px solid <?= empty($diag['issues']) ? '#25d366' : '#f44747' ?>;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <strong style="color:#d4d4d4;font-size:14px;"><?= htmlspecialchars($diag['account']['name']) ?> (#<?= $diag['account']['id'] ?>)</strong>
                        <span style="font-size:12px;padding:3px 8px;border-radius:3px;background:<?= $diag['account']['status'] === 'active' ? '#1e3a1e' : '#3a1e1e' ?>;color:<?= $diag['account']['status'] === 'active' ? '#4ec9b0' : '#f44747' ?>;">
                            <?= $diag['account']['status'] ?>
                        </span>
                    </div>
                    <?php if (!empty($diag['issues'])): ?>
                        <?php foreach ($diag['issues'] as $issue): ?>
                            <div style="color:#f44747;font-size:12px;margin:4px 0;">❌ <?= htmlspecialchars($issue) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($diag['warnings'])): ?>
                        <?php foreach ($diag['warnings'] as $warn): ?>
                            <div style="color:#dcdcaa;font-size:12px;margin:4px 0;">⚠️ <?= htmlspecialchars($warn) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($diag['ok'])): ?>
                        <?php foreach ($diag['ok'] as $ok): ?>
                            <div style="color:#4ec9b0;font-size:12px;margin:4px 0;">✅ <?= htmlspecialchars($ok) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- Endpoints -->
                    <?php if (!empty($diag['endpoints'])): ?>
                        <div style="margin-top:10px;padding-top:8px;border-top:1px solid #3c3c3c;">
                            <span style="color:#858585;font-size:11px;display:block;margin-bottom:4px;">Endpoints API (conforme docs NotificaMe):</span>
                            <?php foreach ($diag['endpoints'] as $label => $ep): ?>
                                <div style="font-size:11px;color:#9cdcfe;margin:2px 0;font-family:monospace;"><?= $label ?>: <code style="color:#ce9178;"><?= htmlspecialchars($ep) ?></code></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ── Webhook ── -->
            <h2 style="color:#dcdcaa;margin:20px 0 12px;font-size:16px;">🔗 Configuração do Webhook</h2>
            <div style="background:#2d2d2d;padding:15px;border-radius:6px;margin-bottom:15px;">
                <div style="color:#9cdcfe;font-size:13px;margin-bottom:8px;">URL do Webhook neste servidor:</div>
                <code style="color:#4ec9b0;font-size:14px;display:block;padding:8px;background:#1e1e1e;border-radius:4px;word-break:break-all;"><?= htmlspecialchars($waNotificameData['webhookUrl']) ?></code>
                <div style="color:#858585;font-size:12px;margin-top:8px;">
                    Configure esta URL no painel do NotificaMe para receber webhooks de mensagens e status.
                </div>
            </div>

            <!-- ── Todas as Contas Notificame ── -->
            <h2 style="color:#c586c0;margin:20px 0 12px;font-size:16px;">📋 Todas as Contas Notificame</h2>
            <div class="table-container" style="overflow-x:auto;margin-bottom:15px;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="border-bottom:1px solid #3c3c3c;">
                            <th style="padding:8px;text-align:left;color:#569cd6;">ID</th>
                            <th style="padding:8px;text-align:left;color:#569cd6;">Nome</th>
                            <th style="padding:8px;text-align:left;color:#569cd6;">Canal</th>
                            <th style="padding:8px;text-align:left;color:#569cd6;">Account ID</th>
                            <th style="padding:8px;text-align:left;color:#569cd6;">Status</th>
                            <th style="padding:8px;text-align:left;color:#569cd6;">Última Sync</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($waNotificameData['allAccounts'] as $acc): ?>
                        <tr style="border-bottom:1px solid #2d2d2d;">
                            <td style="padding:6px 8px;color:#d4d4d4;"><?= $acc['id'] ?></td>
                            <td style="padding:6px 8px;color:#d4d4d4;"><?= htmlspecialchars($acc['name']) ?></td>
                            <td style="padding:6px 8px;"><span style="color:<?= $acc['channel'] === 'whatsapp' ? '#25d366' : '#e1306c' ?>;"><?= htmlspecialchars($acc['channel']) ?></span></td>
                            <td style="padding:6px 8px;color:#ce9178;font-family:monospace;font-size:11px;"><?= htmlspecialchars($acc['account_id'] ?: '-') ?></td>
                            <td style="padding:6px 8px;color:<?= $acc['status'] === 'active' ? '#4ec9b0' : '#f44747' ?>;"><?= $acc['status'] ?></td>
                            <td style="padding:6px 8px;color:#858585;"><?= $acc['last_sync_at'] ? date('d/m H:i', strtotime($acc['last_sync_at'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ── Últimas Conversas WhatsApp Notificame ── -->
            <h2 style="color:#569cd6;margin:20px 0 12px;font-size:16px;">💬 Últimas Conversas WhatsApp Notificame (<?= count($waNotificameData['conversations']) ?>)</h2>
            <?php if (empty($waNotificameData['conversations'])): ?>
                <div style="background:#2d2d2d;padding:15px;border-radius:6px;color:#858585;text-align:center;">Nenhuma conversa WhatsApp via Notificame encontrada</div>
            <?php else: ?>
                <div class="table-container" style="overflow-x:auto;margin-bottom:15px;">
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="border-bottom:1px solid #3c3c3c;">
                                <th style="padding:8px;text-align:left;color:#569cd6;">ID</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Contato</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Telefone</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Conta</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Status</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Msgs</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Criada</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Atualizada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($waNotificameData['conversations'] as $conv): ?>
                            <tr style="border-bottom:1px solid #2d2d2d;">
                                <td style="padding:6px 8px;color:#d4d4d4;"><?= $conv['id'] ?></td>
                                <td style="padding:6px 8px;color:#d4d4d4;"><?= htmlspecialchars($conv['contact_name'] ?: '-') ?></td>
                                <td style="padding:6px 8px;color:#9cdcfe;font-family:monospace;"><?= htmlspecialchars($conv['contact_phone'] ?: '-') ?></td>
                                <td style="padding:6px 8px;color:#c586c0;"><?= htmlspecialchars($conv['account_name'] ?: '-') ?></td>
                                <td style="padding:6px 8px;color:<?= $conv['status'] === 'open' ? '#4ec9b0' : '#858585' ?>;"><?= $conv['status'] ?></td>
                                <td style="padding:6px 8px;color:#dcdcaa;"><?= $conv['msg_count'] ?></td>
                                <td style="padding:6px 8px;color:#858585;font-size:11px;"><?= date('d/m H:i', strtotime($conv['created_at'])) ?></td>
                                <td style="padding:6px 8px;color:#858585;font-size:11px;"><?= $conv['updated_at'] ? date('d/m H:i', strtotime($conv['updated_at'])) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- ── Últimas Mensagens ── -->
            <h2 style="color:#4ec9b0;margin:20px 0 12px;font-size:16px;">📨 Últimas Mensagens (<?= count($waNotificameData['messages']) ?>)</h2>
            <?php if (empty($waNotificameData['messages'])): ?>
                <div style="background:#2d2d2d;padding:15px;border-radius:6px;color:#858585;text-align:center;">Nenhuma mensagem encontrada</div>
            <?php else: ?>
                <div class="table-container" style="overflow-x:auto;margin-bottom:15px;">
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="border-bottom:1px solid #3c3c3c;">
                                <th style="padding:8px;text-align:left;color:#569cd6;">ID</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Conv</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Contato</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Tipo</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Direção</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Status</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Conteúdo</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">External ID</th>
                                <th style="padding:8px;text-align:left;color:#569cd6;">Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($waNotificameData['messages'] as $msg): ?>
                            <?php
                                $dirColor = $msg['sender_type'] === 'contact' ? '#4ec9b0' : '#569cd6';
                                $dirLabel = $msg['sender_type'] === 'contact' ? '← IN' : '→ OUT';
                                $statusColor = in_array($msg['msg_status'], ['failed','error','rejected']) ? '#f44747' : ($msg['msg_status'] === 'delivered' ? '#4ec9b0' : '#858585');
                                $contentPreview = mb_substr(strip_tags($msg['content'] ?? ''), 0, 80);
                            ?>
                            <tr style="border-bottom:1px solid #2d2d2d;">
                                <td style="padding:6px 8px;color:#d4d4d4;font-size:11px;"><?= $msg['id'] ?></td>
                                <td style="padding:6px 8px;color:#d4d4d4;"><?= $msg['conversation_id'] ?></td>
                                <td style="padding:6px 8px;color:#d4d4d4;"><?= htmlspecialchars($msg['contact_name'] ?: ($msg['contact_phone'] ?: '-')) ?></td>
                                <td style="padding:6px 8px;color:#dcdcaa;"><?= $msg['message_type'] ?: 'text' ?></td>
                                <td style="padding:6px 8px;color:<?= $dirColor ?>;font-weight:bold;"><?= $dirLabel ?></td>
                                <td style="padding:6px 8px;color:<?= $statusColor ?>;"><?= $msg['msg_status'] ?: '-' ?></td>
                                <td style="padding:6px 8px;color:#d4d4d4;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($msg['content'] ?? '') ?>"><?= htmlspecialchars($contentPreview) ?></td>
                                <td style="padding:6px 8px;color:#ce9178;font-family:monospace;font-size:10px;max-width:120px;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($msg['external_id'] ?? '') ?>"><?= htmlspecialchars(substr($msg['external_id'] ?? '-', 0, 20)) ?></td>
                                <td style="padding:6px 8px;color:#858585;font-size:11px;white-space:nowrap;"><?= date('d/m H:i:s', strtotime($msg['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- ── Mensagens com Erro ── -->
            <?php if (!empty($waNotificameData['failedMessages'])): ?>
            <h2 style="color:#f44747;margin:20px 0 12px;font-size:16px;">❌ Mensagens com Falha (<?= count($waNotificameData['failedMessages']) ?>)</h2>
            <div class="table-container" style="overflow-x:auto;margin-bottom:15px;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead>
                        <tr style="border-bottom:1px solid #3c3c3c;">
                            <th style="padding:8px;text-align:left;color:#f44747;">ID</th>
                            <th style="padding:8px;text-align:left;color:#f44747;">Contato</th>
                            <th style="padding:8px;text-align:left;color:#f44747;">Tipo</th>
                            <th style="padding:8px;text-align:left;color:#f44747;">Status</th>
                            <th style="padding:8px;text-align:left;color:#f44747;">Conteúdo</th>
                            <th style="padding:8px;text-align:left;color:#f44747;">Erro</th>
                            <th style="padding:8px;text-align:left;color:#f44747;">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($waNotificameData['failedMessages'] as $msg): ?>
                        <tr style="border-bottom:1px solid #2d2d2d;background:#2a1a1a;">
                            <td style="padding:6px 8px;color:#d4d4d4;"><?= $msg['id'] ?></td>
                            <td style="padding:6px 8px;color:#d4d4d4;"><?= htmlspecialchars($msg['contact_name'] ?: ($msg['contact_phone'] ?: '-')) ?></td>
                            <td style="padding:6px 8px;color:#dcdcaa;"><?= $msg['message_type'] ?: 'text' ?></td>
                            <td style="padding:6px 8px;color:#f44747;font-weight:bold;"><?= $msg['msg_status'] ?></td>
                            <td style="padding:6px 8px;color:#d4d4d4;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(mb_substr($msg['content'] ?? '', 0, 60)) ?></td>
                            <td style="padding:6px 8px;color:#f48771;font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($msg['error_message'] ?? '') ?>"><?= htmlspecialchars($msg['error_message'] ?? '-') ?></td>
                            <td style="padding:6px 8px;color:#858585;font-size:11px;"><?= date('d/m H:i:s', strtotime($msg['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- ── Logs de Templates ── -->
            <h2 style="color:#dcdcaa;margin:20px 0 12px;font-size:16px;">📝 Logs de Templates (<?= count($waNotificameData['templateLogLines']) ?> entradas)</h2>
            <div class="logs-container" style="max-height:350px;overflow-y:auto;margin-bottom:15px;">
                <?php if (empty($waNotificameData['templateLogLines'])): ?>
                    <div style="padding:15px;text-align:center;color:#858585;">Nenhum log de template encontrado no notificame.log</div>
                <?php else: ?>
                    <?php foreach ($waNotificameData['templateLogLines'] as $line):
                        $cls = '';
                        if (stripos($line, '[ERROR]') !== false) $cls = 'error';
                        elseif (stripos($line, '[WARNING]') !== false) $cls = 'warning';
                        elseif (stripos($line, '[INFO]') !== false) $cls = 'info';
                    ?>
                        <div class="log-line <?= $cls ?>"><?= colorizeLog($line) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ── Logs WhatsApp Notificame ── -->
            <h2 style="color:#25d366;margin:20px 0 12px;font-size:16px;">📋 Logs WhatsApp + Templates + SendMessage (<?= count($waNotificameData['waLogLines']) ?> entradas)</h2>
            <div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;">
                <a href="?tab=notificame&filter=whatsapp" style="font-size:11px;padding:3px 8px;border-radius:3px;background:#3c3c3c;color:#25d366;text-decoration:none;">Ver todos no Logs Notificame →</a>
                <a href="?tab=notificame&filter=template" style="font-size:11px;padding:3px 8px;border-radius:3px;background:#3c3c3c;color:#dcdcaa;text-decoration:none;">Filtrar: template</a>
                <a href="?tab=notificame&filter=sendMessage" style="font-size:11px;padding:3px 8px;border-radius:3px;background:#3c3c3c;color:#4ec9b0;text-decoration:none;">Filtrar: sendMessage</a>
                <a href="?tab=notificame&filter=sendTemplate" style="font-size:11px;padding:3px 8px;border-radius:3px;background:#3c3c3c;color:#c586c0;text-decoration:none;">Filtrar: sendTemplate</a>
                <a href="?tab=notificame&filter=ERROR" style="font-size:11px;padding:3px 8px;border-radius:3px;background:#3c3c3c;color:#f44747;text-decoration:none;">Filtrar: ERROR</a>
            </div>
            <div class="logs-container" style="max-height:500px;overflow-y:auto;">
                <?php if (empty($waNotificameData['waLogLines'])): ?>
                    <div style="padding:15px;text-align:center;color:#858585;">
                        Nenhum log filtrado encontrado no notificame.log<br>
                        <span style="font-size:12px;">Arquivo: <?= htmlspecialchars($waNotificameData['logFile']) ?></span>
                    </div>
                <?php else: ?>
                    <?php foreach ($waNotificameData['waLogLines'] as $line):
                        $cls = '';
                        if (stripos($line, '[ERROR]') !== false) $cls = 'error';
                        elseif (stripos($line, '[WARNING]') !== false) $cls = 'warning';
                        elseif (stripos($line, '[INFO]') !== false) $cls = 'info';
                    ?>
                        <div class="log-line <?= $cls ?>"><?= colorizeLog($line) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="footer">
                <p>Arquivo de logs: <?= htmlspecialchars($waNotificameData['logFile']) ?></p>
                <p>Última atualização: <?= date('d/m/Y H:i:s') ?> | <a href="?tab=wa_notificame" style="color:#25d366;">🔄 Atualizar</a></p>
            </div>
        <?php endif; ?>

        <?php elseif ($activeTab === 'notificame'): ?>
        <!-- ═══════════════ ABA LOGS NOTIFICAME ═══════════════ -->
        <?php
            $notifLogFile  = $logFileMap['notificame'] ?? '';
            $notifTitle    = '🔔 Logs Notificame';
            $notifDesc     = 'Todos os eventos de webhook, envio e recebimento de mensagens via Notificame (WhatsApp, Instagram, Facebook, etc).';
            $notifLines    = [];
            if (file_exists($notifLogFile)) {
                $allNotifRaw = file($notifLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $notifLines  = array_slice(array_reverse($allNotifRaw), 0, $maxLines);
            }
            if (!empty($filter)) {
                $notifLines = array_filter($notifLines, fn($l) => stripos($l, $filter) !== false);
            }
            if (!empty($level)) {
                $notifLines = array_filter($notifLines, fn($l) => stripos($l, "[$level]") !== false);
            }
            $notifTotal   = count($notifLines);
            $notifErrors  = count(array_filter($notifLines, fn($l) => stripos($l, '[ERROR]') !== false));
            $notifWarns   = count(array_filter($notifLines, fn($l) => stripos($l, '[WARNING]') !== false));
            $notifWebhook = count(array_filter($notifLines, fn($l) => stripos($l, 'Webhook') !== false));
            $notifSend    = count(array_filter($notifLines, fn($l) => stripos($l, 'sendMessage') !== false));
            $notifInsta   = count(array_filter($notifLines, fn($l) => stripos($l, 'instagram') !== false));
        ?>

        <header>
            <h1><?= $notifTitle ?></h1>
            <p style="color:#888;margin-top:5px;font-size:13px;"><?= $notifDesc ?></p>
            <div class="stats" style="margin-top:15px;">
                <div class="stat"><div class="stat-label">Total</div><div class="stat-value"><?= $notifTotal ?></div></div>
                <div class="stat errors"><div class="stat-label">Erros</div><div class="stat-value"><?= $notifErrors ?></div></div>
                <div class="stat warnings"><div class="stat-label">Warnings</div><div class="stat-value"><?= $notifWarns ?></div></div>
                <div class="stat"><div class="stat-label">Webhooks</div><div class="stat-value" style="color:#c586c0;"><?= $notifWebhook ?></div></div>
                <div class="stat"><div class="stat-label">SendMessage</div><div class="stat-value" style="color:#4ec9b0;"><?= $notifSend ?></div></div>
                <div class="stat"><div class="stat-label">Instagram</div><div class="stat-value" style="color:#e1306c;"><?= $notifInsta ?></div></div>
            </div>
        </header>

        <div class="filters">
            <form method="GET">
                <input type="hidden" name="tab" value="notificame">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Buscar</label>
                        <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" placeholder="instagram, sendMessage, webhook, ERROR..." style="min-width:280px;">
                    </div>
                    <div class="filter-group">
                        <label>Nível</label>
                        <select name="level">
                            <option value="">Todos</option>
                            <option value="ERROR" <?= $level === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                            <option value="WARNING" <?= $level === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                            <option value="INFO" <?= $level === 'INFO' ? 'selected' : '' ?>>INFO</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Linhas</label>
                        <select name="lines">
                            <option value="200" <?= $maxLines == 200 ? 'selected' : '' ?>>200</option>
                            <option value="500" <?= $maxLines == 500 ? 'selected' : '' ?>>500</option>
                            <option value="1000" <?= $maxLines == 1000 ? 'selected' : '' ?>>1000</option>
                            <option value="3000" <?= $maxLines == 3000 ? 'selected' : '' ?>>3000</option>
                        </select>
                    </div>
                    <div class="actions">
                        <button type="submit">🔍 Filtrar</button>
                        <button type="button" class="secondary" onclick="window.location.href='?tab=notificame'">🔄 Limpar</button>
                        <a href="?tab=instagram" style="color:#e1306c;font-size:13px;margin-left:10px;">📷 Diagnóstico Instagram</a>
                    </div>
                </div>
                <!-- Atalhos rápidos por canal -->
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <span style="color:#858585;font-size:11px;align-self:center;">Filtrar:</span>
                    <?php foreach (['instagram','whatsapp','facebook','Webhook INÍCIO','sendMessage','ERROR','Contact','Conversa'] as $quick): ?>
                        <a href="?tab=notificame&filter=<?= urlencode($quick) ?>&lines=<?= $maxLines ?>"
                           style="font-size:11px;padding:3px 8px;border-radius:3px;background:#3c3c3c;color:#<?= $filter === $quick ? 'fff' : '9cdcfe' ?>;text-decoration:none;">
                            <?= htmlspecialchars($quick) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>

        <div class="logs-container">
            <?php if (!file_exists($notifLogFile)): ?>
                <div class="no-logs">
                    <h2>Arquivo notificame.log ainda não existe</h2>
                    <p>Será criado automaticamente na primeira requisição/webhook do Notificame.</p>
                    <p style="color:#858585;margin-top:10px;">Caminho esperado: <code style="color:#4ec9b0;"><?= realpath(__DIR__ . '/../logs') ?>/notificame.log</code></p>
                </div>
            <?php elseif (empty($notifLines)): ?>
                <div class="no-logs">
                    <h2>Nenhum log encontrado</h2>
                    <p>Tente remover os filtros ou aguarde novos eventos do Notificame.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifLines as $line):
                    $cls = '';
                    if (stripos($line, '[ERROR]') !== false) $cls = 'error';
                    elseif (stripos($line, '[WARNING]') !== false) $cls = 'warning';
                    elseif (stripos($line, '[INFO]') !== false) $cls = 'info';
                ?>
                    <div class="log-line <?= $cls ?>"><?= colorizeLog($line) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Arquivo: <?= $notifLogFile ?></p>
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?> | <a href="?tab=notificame&filter=<?= urlencode($filter) ?>&lines=<?= $maxLines ?>" style="color:#9c99ff;">🔄 Atualizar</a></p>
        </div>

        <?php elseif ($activeTab === 'unificacao_logs' || $activeTab === 'quepasa'): ?>
        <!-- ═══════════════ ABA LOGS UNIFICAÇÃO / QUEPASA ═══════════════ -->
        <?php
            $tabTitle = $activeTab === 'unificacao_logs' ? '📊 Logs de Unificação' : '📱 Logs Quepasa';
            $tabDesc = $activeTab === 'unificacao_logs' 
                ? 'Rastreamento de todas as operações de unificação: resolução de contas, fallbacks, envios, webhooks, automações e erros.'
                : 'Logs de comunicação com a API Quepasa: envio/recebimento de mensagens, QR codes, webhooks.';
            $tabLogFile = $logFileMap[$activeTab] ?? '';
            
            // Ler logs específicos
            $tabLogs = [];
            if (file_exists($tabLogFile)) {
                $allTabLines = file($tabLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $tabLogs = array_slice(array_reverse($allTabLines), 0, $maxLines);
            }
            
            // Aplicar filtro
            if (!empty($filter)) {
                $tabLogs = array_filter($tabLogs, function($line) use ($filter) {
                    return stripos($line, $filter) !== false;
                });
            }
            
            // Filtro por nível/tipo para unificação
            $tabLevelFilter = $_GET['tipo'] ?? '';
            if (!empty($tabLevelFilter)) {
                $tabLogs = array_filter($tabLogs, function($line) use ($tabLevelFilter) {
                    return stripos($line, "[$tabLevelFilter]") !== false;
                });
            }
            
            // Stats
            $tabTotal = count($tabLogs);
            if ($activeTab === 'unificacao_logs' && !empty($allTabLines)) {
                $catStats = [
                    'SEND' => 0, 'RESOLVE' => 0, 'FALLBACK' => 0, 'WEBHOOK' => 0,
                    'AUTOMACAO' => 0, 'CONVERSA' => 0, 'CRUD' => 0, 'ERROR' => 0, 'WHATSAPP_SEND' => 0
                ];
                foreach (array_slice(array_reverse($allTabLines), 0, 5000) as $l) {
                    foreach ($catStats as $cat => $_) {
                        if (stripos($l, "[$cat]") !== false) $catStats[$cat]++;
                    }
                }
            }
        ?>
        
        <header>
            <h1><?= $tabTitle ?></h1>
            <p style="color: #888; margin-top: 5px; font-size: 13px;"><?= $tabDesc ?></p>
        </header>
        
        <?php if ($activeTab === 'unificacao_logs' && !empty($catStats)): ?>
        <div class="stats" style="margin-bottom: 15px;">
            <?php 
            $catLabels = [
                'WHATSAPP_SEND' => ['label' => 'Envios WA', 'color' => '#4ec9b0'],
                'SEND' => ['label' => 'Resolução Envio', 'color' => '#569cd6'],
                'RESOLVE' => ['label' => 'Resolução ID', 'color' => '#dcdcaa'],
                'FALLBACK' => ['label' => 'Fallbacks', 'color' => '#ce9178'],
                'WEBHOOK' => ['label' => 'Webhooks', 'color' => '#c586c0'],
                'AUTOMACAO' => ['label' => 'Automações', 'color' => '#b5cea8'],
                'CONVERSA' => ['label' => 'Conversas', 'color' => '#9cdcfe'],
                'CRUD' => ['label' => 'CRUD Contas', 'color' => '#d4d4d4'],
                'ERROR' => ['label' => 'Erros', 'color' => '#f44747'],
            ];
            foreach ($catLabels as $cat => $info): ?>
                <div class="stat" style="cursor:pointer;" onclick="window.location.href='?tab=unificacao_logs&tipo=<?= $cat ?>&lines=<?= $maxLines ?>'">
                    <div class="stat-label"><?= $info['label'] ?></div>
                    <div class="stat-value" style="color: <?= $info['color'] ?>"><?= $catStats[$cat] ?? 0 ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div style="background: #252526; padding: 12px; border-radius: 6px; margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                <input type="hidden" name="tab" value="<?= $activeTab ?>">
                <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" placeholder="Buscar nos logs..." 
                    style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px; flex: 1; min-width: 200px;">
                <?php if ($activeTab === 'unificacao_logs'): ?>
                <select name="tipo" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px;">
                    <option value="">Todos os tipos</option>
                    <option value="WHATSAPP_SEND" <?= $tabLevelFilter === 'WHATSAPP_SEND' ? 'selected' : '' ?>>Envios WhatsApp</option>
                    <option value="SEND" <?= $tabLevelFilter === 'SEND' ? 'selected' : '' ?>>Resolução Envio</option>
                    <option value="RESOLVE" <?= $tabLevelFilter === 'RESOLVE' ? 'selected' : '' ?>>Resolução ID</option>
                    <option value="FALLBACK" <?= $tabLevelFilter === 'FALLBACK' ? 'selected' : '' ?>>Fallbacks (legado)</option>
                    <option value="WEBHOOK" <?= $tabLevelFilter === 'WEBHOOK' ? 'selected' : '' ?>>Webhooks</option>
                    <option value="AUTOMACAO" <?= $tabLevelFilter === 'AUTOMACAO' ? 'selected' : '' ?>>Automações</option>
                    <option value="CONVERSA" <?= $tabLevelFilter === 'CONVERSA' ? 'selected' : '' ?>>Conversas</option>
                    <option value="CRUD" <?= $tabLevelFilter === 'CRUD' ? 'selected' : '' ?>>CRUD Contas</option>
                    <option value="ERROR" <?= $tabLevelFilter === 'ERROR' ? 'selected' : '' ?>>Erros</option>
                </select>
                <?php endif; ?>
                <select name="lines" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px;">
                    <option value="100" <?= $maxLines == 100 ? 'selected' : '' ?>>100 linhas</option>
                    <option value="500" <?= $maxLines == 500 ? 'selected' : '' ?>>500 linhas</option>
                    <option value="1000" <?= $maxLines == 1000 ? 'selected' : '' ?>>1000 linhas</option>
                    <option value="5000" <?= $maxLines == 5000 ? 'selected' : '' ?>>5000 linhas</option>
                </select>
                <button type="submit" style="background: #0e639c; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer;">Filtrar</button>
                <button type="button" onclick="window.location.href='?tab=<?= $activeTab ?>'" style="background: #3c3c3c; color: #d4d4d4; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer;">Limpar</button>
            </form>
        </div>
        
        <!-- Legenda de cores para logs de unificação -->
        <?php if ($activeTab === 'unificacao_logs'): ?>
        <div style="background: #1e1e1e; padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; display: flex; gap: 15px; flex-wrap: wrap; font-size: 12px;">
            <span style="color: #888;">Legenda:</span>
            <span style="color: #4ec9b0;">✅ Sucesso</span>
            <span style="color: #ce9178;">⚠️ Fallback (legado)</span>
            <span style="color: #f44747;">❌ Erro</span>
            <span style="color: #569cd6;">[SEND] Envio</span>
            <span style="color: #c586c0;">[WEBHOOK] Webhook</span>
            <span style="color: #b5cea8;">[AUTOMACAO] Automação</span>
            <span style="color: #9cdcfe;">[CONVERSA] Conversa</span>
            <span style="color: #dcdcaa;">[RESOLVE] Resolução</span>
            <span style="color: #d4d4d4;">[CRUD] CRUD</span>
        </div>
        <?php endif; ?>
        
        <!-- Info do arquivo -->
        <div style="background: #252526; padding: 8px 15px; border-radius: 6px; margin-bottom: 10px; font-size: 12px; color: #888;">
            📁 Arquivo: <span style="color: #d4d4d4;"><?= htmlspecialchars($tabLogFile) ?></span> | 
            <?php if (file_exists($tabLogFile)): ?>
                Tamanho: <span style="color: #d4d4d4;"><?= number_format(filesize($tabLogFile) / 1024, 1) ?> KB</span> | 
                Última modificação: <span style="color: #d4d4d4;"><?= date('d/m/Y H:i:s', filemtime($tabLogFile)) ?></span> | 
            <?php endif; ?>
            Exibindo: <span style="color: #4ec9b0;"><?= $tabTotal ?></span> linhas
            <?php if (!empty($tabLevelFilter)): ?>
                | Filtro tipo: <span style="color: #dcdcaa;">[<?= $tabLevelFilter ?>]</span>
            <?php endif; ?>
        </div>
        
        <!-- Logs -->
        <div class="log-container" style="max-height: 70vh; overflow-y: auto; background: #1e1e1e; border-radius: 6px; padding: 10px;">
            <?php if (empty($tabLogs)): ?>
                <div style="text-align: center; padding: 40px; color: #888;">
                    <?php if (!file_exists($tabLogFile)): ?>
                        <p style="font-size: 18px;">📭 Arquivo de log ainda não existe</p>
                        <p style="margin-top: 10px;">O arquivo <code style="color: #4ec9b0;"><?= basename($tabLogFile) ?></code> será criado automaticamente quando o primeiro evento for registrado.</p>
                        <p style="margin-top: 5px; font-size: 12px;">Faça uma operação (enviar mensagem, criar conversa, etc.) para gerar os primeiros logs.</p>
                    <?php else: ?>
                        <p style="font-size: 18px;">🔍 Nenhum log encontrado</p>
                        <p style="margin-top: 10px;">Nenhum registro corresponde aos filtros aplicados.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($tabLogs as $line): ?>
                    <?php
                    // Colorir linhas baseado no conteúdo
                    $lineColor = '#d4d4d4';
                    $bgColor = 'transparent';
                    if (strpos($line, '❌') !== false || stripos($line, '[ERROR]') !== false) {
                        $lineColor = '#f44747';
                        $bgColor = 'rgba(244, 71, 71, 0.05)';
                    } elseif (strpos($line, '⚠️') !== false || stripos($line, '[FALLBACK]') !== false) {
                        $lineColor = '#ce9178';
                        $bgColor = 'rgba(206, 145, 120, 0.05)';
                    } elseif (strpos($line, '✅') !== false) {
                        $lineColor = '#4ec9b0';
                    } elseif (stripos($line, '[WEBHOOK]') !== false) {
                        $lineColor = '#c586c0';
                    } elseif (stripos($line, '[AUTOMACAO]') !== false) {
                        $lineColor = '#b5cea8';
                    } elseif (stripos($line, '[CONVERSA]') !== false) {
                        $lineColor = '#9cdcfe';
                    } elseif (stripos($line, '[CRUD]') !== false) {
                        $lineColor = '#d4d4d4';
                    } elseif (stripos($line, '[WHATSAPP_SEND]') !== false) {
                        $lineColor = '#4ec9b0';
                    } elseif (stripos($line, '[SEND]') !== false || stripos($line, '[RESOLVE]') !== false) {
                        $lineColor = '#569cd6';
                    }
                    ?>
                    <div style="padding: 3px 8px; font-family: 'Consolas', 'Courier New', monospace; font-size: 12px; color: <?= $lineColor ?>; background: <?= $bgColor ?>; border-bottom: 1px solid #2a2a2a; word-break: break-all;">
                        <?= htmlspecialchars($line) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer" style="margin-top: 15px;">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?></p>
            <p style="margin-top: 5px; font-size: 12px; color: #888;">
                Auto-refresh: 
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 5000)" style="color: #4ec9b0;">5s</a> | 
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 15000)" style="color: #4ec9b0;">15s</a> | 
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 30000)" style="color: #4ec9b0;">30s</a>
            </p>
        </div>
        
        <?php elseif ($activeTab === 'evolution'): ?>
        <!-- ═══════════════ ABA LOGS EVOLUTION ═══════════════ -->
        <?php
            $evoLogFile = $logFileMap['evolution'] ?? '';
            $evoLogs = [];
            if (file_exists($evoLogFile)) {
                $allEvoLines = file($evoLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $evoLogs = array_slice(array_reverse($allEvoLines), 0, $maxLines);
            }
            if (!empty($filter)) {
                $evoLogs = array_filter($evoLogs, function($line) use ($filter) {
                    return stripos($line, $filter) !== false;
                });
            }
            $evoLevelFilter = $_GET['tipo'] ?? '';
            if (!empty($evoLevelFilter)) {
                $evoLogs = array_filter($evoLogs, function($line) use ($evoLevelFilter) {
                    return stripos($line, $evoLevelFilter) !== false;
                });
            }
            $evoTotal = count($evoLogs);

            // Stats por categoria
            $evoStats = ['INFO' => 0, 'ERROR' => 0, 'getQRCode' => 0, 'getConnectionStatus' => 0, 'handleMessageUpsert' => 0, 'handleConnectionUpdate' => 0, 'processWebhook' => 0, 'sendMessage' => 0, 'createInstance' => 0];
            if (!empty($allEvoLines)) {
                foreach (array_slice(array_reverse($allEvoLines), 0, 5000) as $l) {
                    foreach ($evoStats as $cat => $_) {
                        if (stripos($l, $cat) !== false) $evoStats[$cat]++;
                    }
                }
            }
        ?>

        <header>
            <h1>🔗 Logs Evolution API</h1>
            <p style="color: #888; margin-top: 5px; font-size: 13px;">Logs de comunicação com a Evolution API: instâncias, QR codes, webhooks, envio/recebimento de mensagens.</p>
        </header>

        <!-- Stats -->
        <div class="stats" style="margin-bottom: 15px;">
            <?php
            $evoCatLabels = [
                'INFO' => ['label' => 'Info', 'color' => '#569cd6'],
                'ERROR' => ['label' => 'Erros', 'color' => '#f44747'],
                'processWebhook' => ['label' => 'Webhooks', 'color' => '#c586c0'],
                'handleMessageUpsert' => ['label' => 'Msgs Recebidas', 'color' => '#4ec9b0'],
                'sendMessage' => ['label' => 'Msgs Enviadas', 'color' => '#b5cea8'],
                'handleConnectionUpdate' => ['label' => 'Conexão', 'color' => '#dcdcaa'],
                'getQRCode' => ['label' => 'QR Code', 'color' => '#9cdcfe'],
                'createInstance' => ['label' => 'Criar Instância', 'color' => '#ce9178'],
                'getConnectionStatus' => ['label' => 'Check Status', 'color' => '#d4d4d4'],
            ];
            foreach ($evoCatLabels as $cat => $info): ?>
                <div class="stat" style="cursor:pointer;" onclick="window.location.href='?tab=evolution&tipo=<?= urlencode($cat) ?>&lines=<?= $maxLines ?>'">
                    <div class="stat-label"><?= $info['label'] ?></div>
                    <div class="stat-value" style="color: <?= $info['color'] ?>"><?= $evoStats[$cat] ?? 0 ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filtros -->
        <div style="background: #252526; padding: 12px; border-radius: 6px; margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                <input type="hidden" name="tab" value="evolution">
                <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" placeholder="Buscar nos logs Evolution..."
                    style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px; flex: 1; min-width: 200px;">
                <select name="tipo" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px;">
                    <option value="">Todos</option>
                    <option value="ERROR" <?= $evoLevelFilter === 'ERROR' ? 'selected' : '' ?>>Erros</option>
                    <option value="processWebhook" <?= $evoLevelFilter === 'processWebhook' ? 'selected' : '' ?>>Webhooks</option>
                    <option value="handleMessageUpsert" <?= $evoLevelFilter === 'handleMessageUpsert' ? 'selected' : '' ?>>Msgs Recebidas</option>
                    <option value="sendMessage" <?= $evoLevelFilter === 'sendMessage' ? 'selected' : '' ?>>Msgs Enviadas</option>
                    <option value="handleConnectionUpdate" <?= $evoLevelFilter === 'handleConnectionUpdate' ? 'selected' : '' ?>>Conexão</option>
                    <option value="getQRCode" <?= $evoLevelFilter === 'getQRCode' ? 'selected' : '' ?>>QR Code</option>
                    <option value="createInstance" <?= $evoLevelFilter === 'createInstance' ? 'selected' : '' ?>>Criar Instância</option>
                    <option value="getConnectionStatus" <?= $evoLevelFilter === 'getConnectionStatus' ? 'selected' : '' ?>>Check Status</option>
                </select>
                <select name="lines" style="background: #1e1e1e; color: #d4d4d4; border: 1px solid #3c3c3c; padding: 6px 12px; border-radius: 4px;">
                    <option value="100" <?= $maxLines == 100 ? 'selected' : '' ?>>100 linhas</option>
                    <option value="500" <?= $maxLines == 500 ? 'selected' : '' ?>>500 linhas</option>
                    <option value="1000" <?= $maxLines == 1000 ? 'selected' : '' ?>>1000 linhas</option>
                    <option value="5000" <?= $maxLines == 5000 ? 'selected' : '' ?>>5000 linhas</option>
                </select>
                <button type="submit" style="background: #0e639c; color: white; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer;">Filtrar</button>
                <button type="button" onclick="window.location.href='?tab=evolution'" style="background: #3c3c3c; color: #d4d4d4; border: none; padding: 6px 16px; border-radius: 4px; cursor: pointer;">Limpar</button>
            </form>
        </div>

        <!-- Info do arquivo -->
        <div style="background: #252526; padding: 8px 15px; border-radius: 6px; margin-bottom: 10px; font-size: 12px; color: #888;">
            📁 Arquivo: <span style="color: #d4d4d4;"><?= htmlspecialchars($evoLogFile) ?></span> |
            <?php if (file_exists($evoLogFile)): ?>
                Tamanho: <span style="color: #d4d4d4;"><?= number_format(filesize($evoLogFile) / 1024, 1) ?> KB</span> |
                Última modificação: <span style="color: #d4d4d4;"><?= date('d/m/Y H:i:s', filemtime($evoLogFile)) ?></span> |
            <?php endif; ?>
            Exibindo: <span style="color: #4ec9b0;"><?= $evoTotal ?></span> linhas
            <?php if (!empty($evoLevelFilter)): ?>
                | Filtro: <span style="color: #dcdcaa;"><?= htmlspecialchars($evoLevelFilter) ?></span>
            <?php endif; ?>
        </div>

        <!-- Logs -->
        <div class="log-container" style="max-height: 70vh; overflow-y: auto; background: #1e1e1e; border-radius: 6px; padding: 10px;">
            <?php if (empty($evoLogs)): ?>
                <div style="text-align: center; padding: 40px; color: #888;">
                    <?php if (!file_exists($evoLogFile)): ?>
                        <p style="font-size: 18px;">📭 Arquivo de log ainda não existe</p>
                        <p style="margin-top: 10px;">O arquivo <code style="color: #4ec9b0;">evolution.log</code> será criado automaticamente quando a primeira operação com Evolution API for realizada.</p>
                        <p style="margin-top: 5px; font-size: 12px;">Conecte uma conta Evolution, envie uma mensagem ou receba um webhook para gerar os primeiros logs.</p>
                    <?php else: ?>
                        <p style="font-size: 18px;">🔍 Nenhum log encontrado</p>
                        <p style="margin-top: 10px;">Nenhum registro corresponde aos filtros aplicados.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($evoLogs as $line): ?>
                    <?php
                    $lineColor = '#d4d4d4';
                    $bgColor = 'transparent';
                    if (stripos($line, '[ERROR]') !== false || stripos($line, 'Falha') !== false || stripos($line, 'Erro') !== false) {
                        $lineColor = '#f44747';
                        $bgColor = 'rgba(244, 71, 71, 0.05)';
                    } elseif (stripos($line, 'processWebhook') !== false) {
                        $lineColor = '#c586c0';
                    } elseif (stripos($line, 'handleMessageUpsert') !== false || stripos($line, 'Encaminhando para WhatsAppService') !== false) {
                        $lineColor = '#4ec9b0';
                        $bgColor = 'rgba(78, 201, 176, 0.05)';
                    } elseif (stripos($line, 'sendMessage') !== false || stripos($line, 'Mensagem enviada') !== false) {
                        $lineColor = '#b5cea8';
                    } elseif (stripos($line, 'handleConnectionUpdate') !== false || stripos($line, 'conectada') !== false || stripos($line, 'desconectada') !== false) {
                        $lineColor = '#dcdcaa';
                    } elseif (stripos($line, 'getQRCode') !== false) {
                        $lineColor = '#9cdcfe';
                    } elseif (stripos($line, 'createInstance') !== false) {
                        $lineColor = '#ce9178';
                    } elseif (stripos($line, 'getConnectionStatus') !== false || stripos($line, 'resolveConnectionState') !== false) {
                        $lineColor = '#569cd6';
                    }
                    ?>
                    <div style="padding: 3px 8px; font-family: 'Consolas', 'Courier New', monospace; font-size: 12px; color: <?= $lineColor ?>; background: <?= $bgColor ?>; border-bottom: 1px solid #2a2a2a; word-break: break-all;">
                        <?= htmlspecialchars($line) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="footer" style="margin-top: 15px;">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?></p>
            <p style="margin-top: 5px; font-size: 12px; color: #888;">
                Auto-refresh:
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 5000)" style="color: #4ec9b0;">5s</a> |
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 15000)" style="color: #4ec9b0;">15s</a> |
                <a href="javascript:void(0)" onclick="setInterval(()=>location.reload(), 30000)" style="color: #4ec9b0;">30s</a>
            </p>
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

        <?php /* ═══════════════ ABA TEMPLATES WHATSAPP (inline no else genérico) ═══════════════ */ ?>
        <?php if ($activeTab === 'templates'): ?>
        <?php
            // Ler logs filtrados por [TEMPLATE]
            $tplLogFile = __DIR__ . '/../logs/app.log';
            if (!file_exists($tplLogFile)) {
                $tplLogFile = __DIR__ . '/../storage/logs/app.log';
            }
            $tplLines = [];
            $tplDbTemplates = [];
            if (file_exists($tplLogFile)) {
                $allTplRaw = file($tplLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $tplAllFiltered = array_filter($allTplRaw, fn($l) => stripos($l, '[TEMPLATE') !== false);
                $tplLines = array_slice(array_reverse($tplAllFiltered), 0, 200);
            }
            if (!empty($filter)) {
                $tplLines = array_filter($tplLines, fn($l) => stripos($l, $filter) !== false);
            }

            // Buscar templates do banco
            try {
                if (!class_exists('\\App\\Helpers\\Database')) {
                    require_once __DIR__ . '/../config/bootstrap.php';
                }
                $db = \App\Helpers\Database::getInstance();
                $tplDbTemplates = $db->query("
                    SELECT id, waba_id, name, display_name, language, category, status, quality_score,
                           body_text, sent_count, delivered_count, read_count, failed_count,
                           rejection_reason, last_synced_at, created_at, updated_at
                    FROM whatsapp_templates
                    ORDER BY 
                        CASE status 
                            WHEN 'PENDING' THEN 1 
                            WHEN 'DRAFT' THEN 2 
                            WHEN 'APPROVED' THEN 3 
                            WHEN 'REJECTED' THEN 4 
                        END, 
                        updated_at DESC
                    LIMIT 50
                ")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $tplDbTemplates = [];
            }

            $tplTotal = count($tplLines);
            $tplErrors = count(array_filter($tplLines, fn($l) => stripos($l, '[ERROR]') !== false || stripos($l, 'ERRO') !== false));
            $tplSuccess = count(array_filter($tplLines, fn($l) => stripos($l, 'sucesso') !== false || stripos($l, 'Resultado Meta') !== false));
        ?>
        <header>
            <h1>📋 Templates WhatsApp Cloud API</h1>
            <p>Diagnóstico de criação, submissão e sincronização de templates com a Meta.</p>
        </header>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 20px;">
            <div style="background: #1e1e2e; padding: 12px; border-radius: 8px; text-align: center; border-left: 3px solid #569cd6;">
                <div style="font-size: 24px; font-weight: bold; color: #569cd6;"><?= count($tplDbTemplates) ?></div>
                <div style="color: #888; font-size: 12px;">Templates</div>
            </div>
            <div style="background: #1e1e2e; padding: 12px; border-radius: 8px; text-align: center; border-left: 3px solid #4ec9b0;">
                <div style="font-size: 24px; font-weight: bold; color: #4ec9b0;"><?= count(array_filter($tplDbTemplates, fn($t) => $t['status'] === 'APPROVED')) ?></div>
                <div style="color: #888; font-size: 12px;">Aprovados</div>
            </div>
            <div style="background: #1e1e2e; padding: 12px; border-radius: 8px; text-align: center; border-left: 3px solid #dcdcaa;">
                <div style="font-size: 24px; font-weight: bold; color: #dcdcaa;"><?= count(array_filter($tplDbTemplates, fn($t) => $t['status'] === 'PENDING')) ?></div>
                <div style="color: #888; font-size: 12px;">Pendentes</div>
            </div>
            <div style="background: #1e1e2e; padding: 12px; border-radius: 8px; text-align: center; border-left: 3px solid #f48771;">
                <div style="font-size: 24px; font-weight: bold; color: #f48771;"><?= count(array_filter($tplDbTemplates, fn($t) => $t['status'] === 'REJECTED')) ?></div>
                <div style="color: #888; font-size: 12px;">Rejeitados</div>
            </div>
            <div style="background: #1e1e2e; padding: 12px; border-radius: 8px; text-align: center; border-left: 3px solid #ce9178;">
                <div style="font-size: 24px; font-weight: bold; color: #ce9178;"><?= count(array_filter($tplDbTemplates, fn($t) => $t['status'] === 'DRAFT')) ?></div>
                <div style="color: #888; font-size: 12px;">Rascunhos</div>
            </div>
            <div style="background: #1e1e2e; padding: 12px; border-radius: 8px; text-align: center; border-left: 3px solid #f48771;">
                <div style="font-size: 24px; font-weight: bold; color: #f48771;"><?= $tplErrors ?></div>
                <div style="color: #888; font-size: 12px;">Erros nos logs</div>
            </div>
        </div>

        <?php if (!empty($tplDbTemplates)): ?>
        <details open style="margin-bottom: 20px;">
            <summary style="cursor: pointer; padding: 8px; background: #1e1e2e; border-radius: 6px; color: #569cd6; font-weight: bold;">
                📄 Templates no Banco de Dados (<?= count($tplDbTemplates) ?>)
            </summary>
            <div style="overflow-x: auto; margin-top: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: #2d2d3d; color: #888;">
                            <th style="padding: 6px 8px; text-align: left;">ID</th>
                            <th style="padding: 6px 8px; text-align: left;">Nome</th>
                            <th style="padding: 6px 8px; text-align: left;">Categoria</th>
                            <th style="padding: 6px 8px; text-align: left;">Idioma</th>
                            <th style="padding: 6px 8px; text-align: center;">Status</th>
                            <th style="padding: 6px 8px; text-align: right;">Enviados</th>
                            <th style="padding: 6px 8px; text-align: right;">Falhas</th>
                            <th style="padding: 6px 8px; text-align: left;">WABA ID</th>
                            <th style="padding: 6px 8px; text-align: left;">Atualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tplDbTemplates as $tpl):
                            $statusColors = [
                                'APPROVED' => '#4ec9b0',
                                'PENDING' => '#dcdcaa',
                                'REJECTED' => '#f48771',
                                'DRAFT' => '#ce9178',
                            ];
                            $sColor = $statusColors[$tpl['status']] ?? '#888';
                        ?>
                        <tr style="border-bottom: 1px solid #2a2a2a;">
                            <td style="padding: 6px 8px; color: #888;"><?= $tpl['id'] ?></td>
                            <td style="padding: 6px 8px; color: #dcdcaa;"><?= htmlspecialchars($tpl['display_name'] ?: $tpl['name']) ?></td>
                            <td style="padding: 6px 8px; color: #888;"><?= $tpl['category'] ?></td>
                            <td style="padding: 6px 8px; color: #888;"><?= $tpl['language'] ?></td>
                            <td style="padding: 6px 8px; text-align: center;"><span style="color: <?= $sColor ?>; font-weight: bold;"><?= $tpl['status'] ?></span></td>
                            <td style="padding: 6px 8px; text-align: right; color: #4ec9b0;"><?= $tpl['sent_count'] ?></td>
                            <td style="padding: 6px 8px; text-align: right; color: <?= $tpl['failed_count'] > 0 ? '#f48771' : '#888' ?>;"><?= $tpl['failed_count'] ?></td>
                            <td style="padding: 6px 8px; color: #888; font-size: 11px;"><?= $tpl['waba_id'] ?></td>
                            <td style="padding: 6px 8px; color: #888; font-size: 11px;"><?= $tpl['updated_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php endif; ?>

        <details open>
            <summary style="cursor: pointer; padding: 8px; background: #1e1e2e; border-radius: 6px; color: #569cd6; font-weight: bold;">
                📜 Logs de Templates (<?= $tplTotal ?> entradas)
            </summary>
            <div style="margin-top: 8px;">
                <div style="margin-bottom: 10px;">
                    <form method="get" style="display: flex; gap: 8px; align-items: center;">
                        <input type="hidden" name="tab" value="templates">
                        <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" placeholder="Filtrar logs..." 
                               style="background: #2d2d3d; border: 1px solid #444; color: #ccc; padding: 5px 10px; border-radius: 4px; flex: 1;">
                        <button type="submit" style="background: #25D366; color: #fff; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer;">Filtrar</button>
                        <a href="?tab=templates" style="color: #888; text-decoration: none; padding: 5px;">Limpar</a>
                    </form>
                </div>
                <?php if (empty($tplLines)): ?>
                    <div style="padding: 20px; text-align: center; color: #888;">
                        Nenhum log de template encontrado. Os logs aparecem com o prefixo <code>[TEMPLATE]</code> no app.log.
                    </div>
                <?php else: ?>
                    <div style="background: #1a1a2e; padding: 8px; border-radius: 6px; max-height: 500px; overflow-y: auto; font-family: 'Consolas', monospace; font-size: 12px;">
                    <?php foreach ($tplLines as $line):
                        $cls = '';
                        if (stripos($line, 'ERRO') !== false || stripos($line, '[ERROR]') !== false) $cls = 'color: #f48771;';
                        elseif (stripos($line, 'sucesso') !== false || stripos($line, 'Resultado Meta') !== false) $cls = 'color: #4ec9b0;';
                        elseif (stripos($line, 'Enviando') !== false || stripos($line, 'Payload') !== false) $cls = 'color: #dcdcaa;';
                        else $cls = 'color: #ccc;';
                    ?>
                        <div style="<?= $cls ?> padding: 2px 4px; border-bottom: 1px solid #2a2a2a; word-break: break-all;"><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>

        <div class="footer" style="margin-top: 15px;">
            <p>Última atualização: <?= date('d/m/Y H:i:s') ?> | <a href="?tab=templates" style="color:#25D366;">🔄 Atualizar</a></p>
        </div>

        <?php endif; // templates ?>
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
