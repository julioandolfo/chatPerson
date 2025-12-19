<?php
/**
 * Script para testar se as automações estão sendo disparadas
 * Simula uma nova conversa e monitora os logs
 */

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

echo '<style>
    body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
    .error { color: #f00; font-weight: bold; }
    .success { color: #0f0; font-weight: bold; }
    .warning { color: #ff0; font-weight: bold; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #0f0; }
    pre { background: #111; padding: 10px; overflow-x: auto; }
</style>';

echo "<h1>🧪 TESTE DE DISPARO DE AUTOMAÇÕES</h1>";

try {
    // 1. Verificar se existem automações ativas
    echo '<div class="section">';
    echo '<h2>1️⃣ Verificando Automações Ativas</h2>';
    
    $automations = \App\Helpers\Database::fetchAll("
        SELECT id, name, trigger_type, funnel_id, stage_id, status, is_active, trigger_config
        FROM automations
        WHERE trigger_type = 'new_conversation' AND status = 'active' AND is_active = TRUE
    ", []);
    
    if (empty($automations)) {
        echo '<p class="error">❌ NENHUMA automação ativa encontrada!</p>';
        echo '<p>Crie uma automação com trigger "new_conversation" primeiro.</p>';
        exit;
    }
    
    echo '<p class="success">✅ ' . count($automations) . ' automação(ões) ativa(s) encontrada(s):</p>';
    echo '<table border="1" style="color: #0f0;">';
    echo '<tr><th>ID</th><th>Nome</th><th>Funil</th><th>Estágio</th><th>Trigger Config</th></tr>';
    foreach ($automations as $auto) {
        echo '<tr>';
        echo '<td>' . $auto['id'] . '</td>';
        echo '<td>' . htmlspecialchars($auto['name']) . '</td>';
        echo '<td>' . ($auto['funnel_id'] ?: 'Qualquer') . '</td>';
        echo '<td>' . ($auto['stage_id'] ?: 'Qualquer') . '</td>';
        echo '<td>' . htmlspecialchars($auto['trigger_config'] ?: '{}') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // 2. Buscar a última conversa criada
    echo '<div class="section">';
    echo '<h2>2️⃣ Buscando Última Conversa Criada</h2>';
    
    $lastConv = \App\Helpers\Database::fetch("
        SELECT c.*, co.name as contact_name, f.name as funnel_name, fs.name as stage_name
        FROM conversations c
        LEFT JOIN contacts co ON c.contact_id = co.id
        LEFT JOIN funnels f ON c.funnel_id = f.id
        LEFT JOIN funnel_stages fs ON c.funnel_stage_id = fs.id
        ORDER BY c.id DESC
        LIMIT 1
    ", []);
    
    if (!$lastConv) {
        echo '<p class="error">❌ Nenhuma conversa encontrada!</p>';
        exit;
    }
    
    echo '<p class="success">✅ Conversa ID: ' . $lastConv['id'] . '</p>';
    echo '<pre>';
    echo 'Contato: ' . htmlspecialchars($lastConv['contact_name']) . "\n";
    echo 'Canal: ' . $lastConv['channel'] . "\n";
    echo 'Funil: ' . htmlspecialchars($lastConv['funnel_name'] ?: 'N/A') . ' (ID: ' . ($lastConv['funnel_id'] ?: 'NULL') . ")\n";
    echo 'Estágio: ' . htmlspecialchars($lastConv['stage_name'] ?: 'N/A') . ' (ID: ' . ($lastConv['funnel_stage_id'] ?: 'NULL') . ")\n";
    echo 'WhatsApp Account: ' . ($lastConv['whatsapp_account_id'] ?: 'NULL') . "\n";
    echo 'Criado: ' . $lastConv['created_at'] . "\n";
    echo '</pre>';
    echo '</div>';
    
    // 3. Limpar log anterior
    $logFile = __DIR__ . '/../logs/automacao.log';
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    
    // 4. Disparar automações manualmente
    echo '<div class="section">';
    echo '<h2>3️⃣ Disparando Automações Manualmente</h2>';
    echo '<p class="warning">⚡ Executando AutomationService::executeForNewConversation(' . $lastConv['id'] . ')...</p>';
    
    try {
        \App\Services\AutomationService::executeForNewConversation($lastConv['id']);
        echo '<p class="success">✅ Execução completada sem erros!</p>';
    } catch (\Exception $e) {
        echo '<p class="error">❌ ERRO: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
    
    echo '</div>';
    
    // 5. Ler log
    echo '<div class="section">';
    echo '<h2>4️⃣ Log de Execução</h2>';
    
    if (file_exists($logFile) && filesize($logFile) > 0) {
        $logContent = file_get_contents($logFile);
        echo '<pre style="max-height: 500px; overflow-y: auto;">';
        echo htmlspecialchars($logContent);
        echo '</pre>';
    } else {
        echo '<p class="warning">⚠️ Nenhum log gerado em: ' . $logFile . '</p>';
    }
    echo '</div>';
    
    // 6. Verificar execuções registradas
    echo '<div class="section">';
    echo '<h2>5️⃣ Execuções Registradas no Banco</h2>';
    
    $executions = \App\Helpers\Database::fetchAll("
        SELECT ae.*, a.name as automation_name
        FROM automation_executions ae
        LEFT JOIN automations a ON ae.automation_id = a.id
        WHERE ae.conversation_id = ?
        ORDER BY ae.id DESC
    ", [$lastConv['id']]);
    
    if (empty($executions)) {
        echo '<p class="error">❌ NENHUMA execução registrada no banco para esta conversa!</p>';
    } else {
        echo '<p class="success">✅ ' . count($executions) . ' execução(ões) encontrada(s):</p>';
        echo '<table border="1" style="color: #0f0;">';
        echo '<tr><th>ID</th><th>Automação</th><th>Status</th><th>Erro</th><th>Criado</th></tr>';
        foreach ($executions as $exec) {
            echo '<tr>';
            echo '<td>' . $exec['id'] . '</td>';
            echo '<td>' . htmlspecialchars($exec['automation_name']) . '</td>';
            echo '<td>' . $exec['status'] . '</td>';
            echo '<td>' . htmlspecialchars($exec['error_message'] ?: '-') . '</td>';
            echo '<td>' . $exec['created_at'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 7. Resumo
    echo '<div class="section">';
    echo '<h2>📊 RESUMO</h2>';
    echo '<ul>';
    echo '<li>Automações Ativas: <strong>' . count($automations) . '</strong></li>';
    echo '<li>Execuções Registradas: <strong>' . count($executions) . '</strong></li>';
    
    if (count($automations) > 0 && count($executions) === 0) {
        echo '<li class="error">🚨 <strong>PROBLEMA:</strong> Existem automações mas nenhuma foi executada!</li>';
    } elseif (count($executions) > 0) {
        echo '<li class="success">✅ <strong>SUCESSO:</strong> Automações estão sendo executadas!</li>';
    }
    
    echo '</ul>';
    echo '</div>';
    
} catch (\Exception $e) {
    echo '<div class="section">';
    echo '<p class="error">❌ ERRO FATAL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}

echo '<br><br><a href="test-automation-integration.php" style="color: #0f0;">← Voltar para Teste de Integração</a>';

