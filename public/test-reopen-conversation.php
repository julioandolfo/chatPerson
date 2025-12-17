<?php
/**
 * Script de Teste - Reabertura Automática de Conversas
 * Execute este script no servidor para verificar a lógica
 */

require_once __DIR__ . '/../app/Helpers/Database.php';

use App\Helpers\Database;

echo "<h1>🧪 Teste de Reabertura Automática</h1>";
echo "<hr>";

// 1. Verificar configuração
echo "<h2>1️⃣ Configuração Atual</h2>";
$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM settings WHERE `key` = 'conversation_reopen_grace_period_minutes'");
$stmt->execute();
$setting = $stmt->fetch(PDO::FETCH_ASSOC);

if ($setting) {
    $gracePeriod = $setting['value'];
    echo "<p><strong>✅ Período de Graça:</strong> {$gracePeriod} minutos</p>";
} else {
    echo "<p><strong>⚠️ Configuração não encontrada!</strong> Usando padrão: 60 minutos</p>";
    $gracePeriod = 60;
}

echo "<hr>";

// 2. Listar conversas fechadas recentes
echo "<h2>2️⃣ Conversas Fechadas Recentes (últimas 24h)</h2>";
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.status,
        c.updated_at,
        ct.name as contact_name,
        ct.phone as contact_phone,
        TIMESTAMPDIFF(MINUTE, c.updated_at, NOW()) as minutes_since_update
    FROM conversations c
    LEFT JOIN contacts ct ON c.contact_id = ct.id
    WHERE c.status IN ('closed', 'resolved')
    AND c.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY c.updated_at DESC
    LIMIT 10
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "<p>Nenhuma conversa fechada nas últimas 24 horas.</p>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th>";
    echo "<th>Contato</th>";
    echo "<th>Status</th>";
    echo "<th>Fechada há</th>";
    echo "<th>Ação Esperada</th>";
    echo "</tr>";
    
    foreach ($conversations as $conv) {
        $minutesSince = $conv['minutes_since_update'];
        $action = $minutesSince >= $gracePeriod 
            ? "<span style='color: orange;'>🔄 REABRIR como NOVA conversa</span>" 
            : "<span style='color: red;'>🚫 NÃO reabrir (continua fechada)</span>";
        
        $statusColor = $conv['status'] === 'closed' ? '#dc3545' : '#6c757d';
        
        echo "<tr>";
        echo "<td><strong>#{$conv['id']}</strong></td>";
        echo "<td>{$conv['contact_name']}<br><small>{$conv['contact_phone']}</small></td>";
        echo "<td><span style='color: {$statusColor}; font-weight: bold;'>{$conv['status']}</span></td>";
        echo "<td><strong>{$minutesSince} min</strong><br><small>{$conv['updated_at']}</small></td>";
        echo "<td>{$action}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "<hr>";

// 3. Simular cenários
echo "<h2>3️⃣ Simulação de Cenários</h2>";

$scenarios = [
    ['minutes' => 1, 'message' => 'Ok, obrigado!'],
    ['minutes' => 5, 'message' => 'Entendido'],
    ['minutes' => 30, 'message' => 'Tem mais alguma coisa?'],
    ['minutes' => 90, 'message' => 'Preciso de outro produto'],
    ['minutes' => 180, 'message' => 'Olá, gostaria de fazer um pedido']
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>Tempo desde Fechamento</th>";
echo "<th>Mensagem do Cliente</th>";
echo "<th>Ação do Sistema</th>";
echo "</tr>";

foreach ($scenarios as $scenario) {
    $minutes = $scenario['minutes'];
    $message = $scenario['message'];
    
    if ($minutes >= $gracePeriod) {
        $action = "<strong style='color: orange;'>🔄 REABRIR como NOVA</strong><br>";
        $action .= "<small>✅ Auto-atribuição<br>✅ Funil/Etapa padrão<br>✅ Automações</small>";
        $bgColor = '#fff3cd';
    } else {
        $action = "<strong style='color: red;'>🚫 NÃO REABRIR</strong><br>";
        $action .= "<small>✅ Mensagem salva<br>❌ Conversa continua fechada<br>❌ Sem notificação</small>";
        $bgColor = '#f8d7da';
    }
    
    echo "<tr style='background: {$bgColor};'>";
    echo "<td><strong>{$minutes} minutos</strong></td>";
    echo "<td>\"{$message}\"</td>";
    echo "<td>{$action}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";

// 4. Teste de validação
echo "<h2>4️⃣ Validação da Lógica</h2>";

$testCases = [
    ['minutes' => 1, 'expected' => 'NÃO REABRIR'],
    ['minutes' => $gracePeriod - 1, 'expected' => 'NÃO REABRIR'],
    ['minutes' => $gracePeriod, 'expected' => 'REABRIR'],
    ['minutes' => $gracePeriod + 1, 'expected' => 'REABRIR'],
    ['minutes' => $gracePeriod * 2, 'expected' => 'REABRIR'],
];

$allPassed = true;

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>Teste</th>";
echo "<th>Tempo</th>";
echo "<th>Esperado</th>";
echo "<th>Resultado</th>";
echo "</tr>";

foreach ($testCases as $i => $test) {
    $minutes = $test['minutes'];
    $expected = $test['expected'];
    
    $actual = $minutes >= $gracePeriod ? 'REABRIR' : 'NÃO REABRIR';
    $passed = $actual === $expected;
    $allPassed = $allPassed && $passed;
    
    $icon = $passed ? '✅' : '❌';
    $bgColor = $passed ? '#d4edda' : '#f8d7da';
    
    echo "<tr style='background: {$bgColor};'>";
    echo "<td><strong>Caso " . ($i + 1) . "</strong></td>";
    echo "<td>{$minutes} min</td>";
    echo "<td>{$expected}</td>";
    echo "<td>{$icon} {$actual}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<br>";
if ($allPassed) {
    echo "<div style='padding: 15px; background: #d4edda; border: 2px solid #28a745; border-radius: 5px;'>";
    echo "<h3 style='color: #155724; margin: 0;'>✅ Todos os testes passaram!</h3>";
    echo "<p style='margin: 10px 0 0 0;'>A lógica de reabertura está funcionando corretamente.</p>";
    echo "</div>";
} else {
    echo "<div style='padding: 15px; background: #f8d7da; border: 2px solid #dc3545; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24; margin: 0;'>❌ Alguns testes falharam!</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Verifique a lógica de reabertura.</p>";
    echo "</div>";
}

echo "<hr>";

// 5. Instruções
echo "<h2>5️⃣ Como Testar Manualmente</h2>";
echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; border: 2px solid #dc3545; margin-bottom: 15px;'>";
echo "<h4 style='color: #721c24; margin-top: 0;'>Teste 1: Dentro do Período Mínimo (NÃO deve reabrir)</h4>";
echo "<ol>";
echo "<li>Feche uma conversa manualmente no sistema</li>";
echo "<li>Aguarde <strong>MENOS de {$gracePeriod} minutos</strong></li>";
echo "<li>Envie uma mensagem pelo WhatsApp (ex: \"Ok, obrigado!\")</li>";
echo "<li><strong>Resultado esperado:</strong> 🚫 Conversa continua FECHADA (mensagem é salva mas não reabre)</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border: 2px solid #ffc107;'>";
echo "<h4 style='color: #856404; margin-top: 0;'>Teste 2: Após o Período Mínimo (deve reabrir)</h4>";
echo "<ol>";
echo "<li>Feche outra conversa manualmente</li>";
echo "<li>Aguarde <strong>{$gracePeriod} minutos ou mais</strong> (ou altere updated_at no banco)</li>";
echo "<li>Envie uma mensagem pelo WhatsApp (ex: \"Preciso de outro produto\")</li>";
echo "<li><strong>Resultado esperado:</strong> 🔄 NOVA conversa criada (com todas as automações)</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";

// 6. Logs recentes
echo "<h2>6️⃣ Verificar Logs</h2>";
echo "<p>Para ver os logs detalhados da reabertura, execute no servidor:</p>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
echo "tail -f storage/logs/quepasa.log | grep -E '🔄|⏱️|✅|🆕|🔓'\n";
echo "</pre>";

echo "<p>Ou para ver as últimas 50 linhas:</p>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
echo "tail -n 50 storage/logs/quepasa.log | grep -E 'REABERTURA|Período de graça'\n";
echo "</pre>";

echo "<hr>";
echo "<p style='text-align: center; color: #6c757d;'><small>Script executado em: " . date('d/m/Y H:i:s') . "</small></p>";

