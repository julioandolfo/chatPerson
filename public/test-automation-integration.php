<?php
/**
 * Script de Teste: Validação de Integração Funis → Automações
 * 
 * Este script valida se o sistema está corretamente integrando:
 * - Criação de conversas com funil/etapa da integração
 * - Disparo de automações quando conversa entra em uma etapa
 * - Execução de nós da automação
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo '<html><head><meta charset="utf-8"><title>Teste de Integração Funis → Automações</title></head><body>';
echo '<h1>🔍 Teste de Integração: Funis → Automações</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    pre { background: #f4f4f4; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>';

$db = \App\Helpers\Database::getInstance();

// ========================================
// 1. VERIFICAR INTEGRAÇÕES WHATSAPP
// ========================================
echo '<h2>1️⃣ Integrações WhatsApp Configuradas</h2>';

$integrations = $db->query("
    SELECT id, name, phone_number, default_funnel_id, default_stage_id, status
    FROM whatsapp_accounts
    ORDER BY id
");

if (empty($integrations)) {
    echo '<p class="error">❌ Nenhuma integração WhatsApp encontrada!</p>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Nome</th><th>Telefone</th><th>Funil Padrão</th><th>Estágio Padrão</th><th>Ativa</th></tr>';
    foreach ($integrations as $int) {
        $funnelName = '-';
        $stageName = '-';
        
        if ($int['default_funnel_id']) {
            $funnel = $db->fetch("SELECT name FROM funnels WHERE id = ?", [$int['default_funnel_id']]);
            $funnelName = $funnel ? $funnel['name'] : '<span class="error">Funil não encontrado!</span>';
        }
        
        if ($int['default_stage_id']) {
            $stage = $db->fetch("SELECT name FROM funnel_stages WHERE id = ?", [$int['default_stage_id']]);
            $stageName = $stage ? $stage['name'] : '<span class="error">Estágio não encontrado!</span>';
        }
        
        $activeStatus = $int['status'] === 'active' ? '<span class="success">✅ Ativa</span>' : '<span class="error">❌ ' . htmlspecialchars($int['status']) . '</span>';
        
        echo "<tr>
            <td>{$int['id']}</td>
            <td>{$int['name']}</td>
            <td>{$int['phone_number']}</td>
            <td>{$funnelName}</td>
            <td>{$stageName}</td>
            <td>{$activeStatus}</td>
        </tr>";
    }
    echo '</table>';
}

// ========================================
// 2. VERIFICAR AUTOMAÇÕES ATIVAS
// ========================================
echo '<h2>2️⃣ Automações Ativas</h2>';

$automations = $db->query("
    SELECT a.id, a.name, a.trigger_type, a.funnel_id, a.stage_id, a.status, a.is_active,
           f.name as funnel_name, fs.name as stage_name
    FROM automations a
    LEFT JOIN funnels f ON a.funnel_id = f.id
    LEFT JOIN funnel_stages fs ON a.stage_id = fs.id
    WHERE a.is_active = TRUE
    ORDER BY a.id
");

if (empty($automations)) {
    echo '<p class="warning">⚠️ Nenhuma automação ativa encontrada!</p>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Nome</th><th>Trigger</th><th>Funil</th><th>Estágio</th><th>Status</th></tr>';
    foreach ($automations as $auto) {
        $funnelDisplay = $auto['funnel_name'] ?? '<span class="info">Todos</span>';
        $stageDisplay = $auto['stage_name'] ?? '<span class="info">Todos</span>';
        $statusClass = $auto['status'] === 'active' ? 'success' : 'error';
        
        echo "<tr>
            <td>{$auto['id']}</td>
            <td>{$auto['name']}</td>
            <td>{$auto['trigger_type']}</td>
            <td>{$funnelDisplay}</td>
            <td>{$stageDisplay}</td>
            <td><span class='{$statusClass}'>{$auto['status']}</span></td>
        </tr>";
    }
    echo '</table>';
}

// ========================================
// 3. VERIFICAR ÚLTIMAS CONVERSAS CRIADAS
// ========================================
echo '<h2>3️⃣ Últimas 10 Conversas Criadas</h2>';

$conversations = $db->query("
    SELECT c.id, c.contact_id, c.channel, c.funnel_id, c.funnel_stage_id, 
           c.whatsapp_account_id, c.created_at,
           co.name as contact_name, co.phone as contact_phone,
           f.name as funnel_name, fs.name as stage_name
    FROM conversations c
    LEFT JOIN contacts co ON c.contact_id = co.id
    LEFT JOIN funnels f ON c.funnel_id = f.id
    LEFT JOIN funnel_stages fs ON c.funnel_stage_id = fs.id
    ORDER BY c.id DESC
    LIMIT 10
");

if (empty($conversations)) {
    echo '<p class="warning">⚠️ Nenhuma conversa encontrada!</p>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Contato</th><th>Canal</th><th>Funil</th><th>Estágio</th><th>Integração</th><th>Criado em</th></tr>';
    foreach ($conversations as $conv) {
        $funnelDisplay = $conv['funnel_name'] ?? '<span class="error">❌ Sem funil</span>';
        $stageDisplay = $conv['stage_name'] ?? '<span class="error">❌ Sem estágio</span>';
        $contactDisplay = $conv['contact_name'] . ' (' . $conv['contact_phone'] . ')';
        
        echo "<tr>
            <td>{$conv['id']}</td>
            <td>{$contactDisplay}</td>
            <td>{$conv['channel']}</td>
            <td>{$funnelDisplay}</td>
            <td>{$stageDisplay}</td>
            <td>{$conv['whatsapp_account_id']}</td>
            <td>{$conv['created_at']}</td>
        </tr>";
    }
    echo '</table>';
}

// ========================================
// 4. VERIFICAR EXECUÇÕES DE AUTOMAÇÕES
// ========================================
echo '<h2>4️⃣ Últimas 10 Execuções de Automações</h2>';

$executions = $db->query("
    SELECT ae.id, ae.automation_id, ae.conversation_id, ae.status, ae.error_message, ae.created_at,
           a.name as automation_name,
           c.id as conv_id, co.name as contact_name
    FROM automation_executions ae
    LEFT JOIN automations a ON ae.automation_id = a.id
    LEFT JOIN conversations c ON ae.conversation_id = c.id
    LEFT JOIN contacts co ON c.contact_id = co.id
    ORDER BY ae.id DESC
    LIMIT 10
");

if (empty($executions)) {
    echo '<p class="warning">⚠️ Nenhuma execução de automação registrada!</p>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Automação</th><th>Conversa</th><th>Contato</th><th>Status</th><th>Erro</th><th>Data</th></tr>';
    foreach ($executions as $exec) {
        $statusClass = $exec['status'] === 'completed' ? 'success' : ($exec['status'] === 'failed' ? 'error' : 'warning');
        $errorDisplay = $exec['error_message'] ? substr($exec['error_message'], 0, 50) . '...' : '-';
        
        echo "<tr>
            <td>{$exec['id']}</td>
            <td>{$exec['automation_name']}</td>
            <td>{$exec['conv_id']}</td>
            <td>{$exec['contact_name']}</td>
            <td><span class='{$statusClass}'>{$exec['status']}</span></td>
            <td>{$errorDisplay}</td>
            <td>{$exec['created_at']}</td>
        </tr>";
    }
    echo '</table>';
}

// ========================================
// 5. RESUMO E RECOMENDAÇÕES
// ========================================
echo '<h2>5️⃣ Resumo e Recomendações</h2>';

$issues = [];
$recommendations = [];

// Verificar se há integrações sem funil/estágio
foreach ($integrations as $int) {
    if (!$int['default_funnel_id'] || !$int['default_stage_id']) {
        $issues[] = "Integração '{$int['name']}' não tem funil/estágio padrão configurado";
        $recommendations[] = "Configure funil e estágio padrão na integração '{$int['name']}'";
    }
}

// Verificar se há conversas sem funil/estágio
$conversationsWithoutFunnel = $db->fetch("
    SELECT COUNT(*) as total
    FROM conversations
    WHERE funnel_id IS NULL OR funnel_stage_id IS NULL
");

if ($conversationsWithoutFunnel['total'] > 0) {
    $issues[] = "Existem {$conversationsWithoutFunnel['total']} conversas sem funil/estágio";
    $recommendations[] = "Certifique-se de que todas as integrações estão configuradas corretamente";
}

// Verificar se há automações sem execuções
if (count($automations) > 0 && empty($executions)) {
    $issues[] = "Existem automações ativas mas nenhuma foi executada";
    $recommendations[] = "Teste criar uma conversa nova para verificar se as automações disparam";
}

if (empty($issues)) {
    echo '<p class="success">✅ Tudo parece estar configurado corretamente!</p>';
} else {
    echo '<h3>⚠️ Problemas Encontrados:</h3><ul>';
    foreach ($issues as $issue) {
        echo "<li class='error'>{$issue}</li>";
    }
    echo '</ul>';
    
    echo '<h3>💡 Recomendações:</h3><ul>';
    foreach ($recommendations as $rec) {
        echo "<li class='info'>{$rec}</li>";
    }
    echo '</ul>';
}

// ========================================
// 6. FLUXO ESPERADO
// ========================================
echo '<h2>6️⃣ Fluxo Esperado (Como Deve Funcionar)</h2>';
echo '<pre>';
echo "1. Cliente envia mensagem WhatsApp
   ↓
2. WhatsAppService::processWebhook detecta mensagem
   ↓
3. ConversationService::create é chamado com:
   - contact_id
   - channel (whatsapp)
   - whatsapp_account_id
   - funnel_id (da integração) ✅
   - stage_id (da integração) ✅
   ↓
4. Conversa é criada no banco com funil e estágio
   ↓
5. AutomationService::executeForNewConversation é chamado
   ↓
6. Automações são buscadas filtradas por:
   - trigger_type = 'new_conversation'
   - funnel_id = da conversa
   - stage_id = da conversa
   ↓
7. Automações correspondentes são executadas
   ↓
8. Nós da automação são processados sequencialmente
";
echo '</pre>';

echo '<hr>';
echo '<p><a href="javascript:history.back()">← Voltar</a> | <a href="javascript:location.reload()">🔄 Atualizar</a></p>';
echo '</body></html>';

