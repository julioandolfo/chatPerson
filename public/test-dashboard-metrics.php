<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Métricas do Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #009ef7;
            padding-bottom: 10px;
        }
        h2 {
            color: #009ef7;
            margin-top: 0;
        }
        .metric {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border-left: 4px solid #009ef7;
        }
        .metric.success {
            border-left-color: #50cd89;
            background: #e8fff3;
        }
        .metric.warning {
            border-left-color: #ffc700;
            background: #fff8dd;
        }
        .metric.error {
            border-left-color: #f1416c;
            background: #ffe2e5;
        }
        .value {
            font-weight: bold;
            font-size: 1.2em;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            background: #009ef7;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #0077c7;
        }
        .btn-success {
            background: #50cd89;
        }
        .btn-success:hover {
            background: #3ba76a;
        }
    </style>
</head>
<body>
    <h1>🧪 Teste de Métricas do Dashboard</h1>
    
    <div class="card">
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn">🔄 Recarregar</a>
        <a href="/dashboard" class="btn btn-success">📊 Ir para Dashboard</a>
    </div>

<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Helpers\Database;

// Simular sessão de usuário (pegar primeiro agente)
$firstAgent = Database::fetch("SELECT id, name, email FROM users WHERE status = 'active' LIMIT 1");
$userId = $firstAgent['id'] ?? 1;

echo "<div class='card'>";
echo "<h2>👤 Usuário de Teste</h2>";
echo "<p><strong>ID:</strong> {$userId}</p>";
echo "<p><strong>Nome:</strong> " . ($firstAgent['name'] ?? 'N/A') . "</p>";
echo "<p><strong>Email:</strong> " . ($firstAgent['email'] ?? 'N/A') . "</p>";
echo "</div>";

$dateFrom = date('Y-m-01');
$dateTo = date('Y-m-d H:i:s');

echo "<div class='card'>";
echo "<h2>📅 Período</h2>";
echo "<p><strong>De:</strong> {$dateFrom}</p>";
echo "<p><strong>Até:</strong> {$dateTo}</p>";
echo "</div>";

// Teste 1: Minhas Conversas
echo "<div class='card'>";
echo "<h2>1️⃣ Minhas Conversas</h2>";

$sql = "SELECT COUNT(*) as total FROM conversations WHERE agent_id = ?";
$result = Database::fetch($sql, [$userId]);
$myTotal = (int)($result['total'] ?? 0);

$sql = "SELECT COUNT(*) as total FROM conversations WHERE agent_id = ? AND status IN ('open', 'pending')";
$result = Database::fetch($sql, [$userId]);
$myOpen = (int)($result['total'] ?? 0);

$status = $myTotal > 0 ? 'success' : 'warning';
echo "<div class='metric {$status}'>";
echo "<span>Total de Conversas do Agente</span>";
echo "<span class='value'>{$myTotal}</span>";
echo "</div>";

echo "<div class='metric {$status}'>";
echo "<span>Conversas Abertas do Agente</span>";
echo "<span class='value'>{$myOpen}</span>";
echo "</div>";

echo "<div class='metric {$status}'>";
echo "<span>Formato Dashboard</span>";
echo "<span class='value'>{$myOpen} / {$myTotal}</span>";
echo "</div>";

if ($myTotal === 0) {
    echo "<p style='color: #ffc700;'>⚠️ <strong>Aviso:</strong> Nenhuma conversa encontrada para este agente. Crie conversas atribuídas a ele para testar.</p>";
}

echo "<pre>";
echo "Query: SELECT COUNT(*) as total FROM conversations WHERE agent_id = {$userId}\n";
echo "Resultado: {$myTotal} conversas";
echo "</pre>";
echo "</div>";

// Teste 2: Conversas sem Atribuição
echo "<div class='card'>";
echo "<h2>2️⃣ Conversas sem Atribuição</h2>";

$sql = "SELECT COUNT(*) as total FROM conversations WHERE (agent_id IS NULL OR agent_id = 0) AND status IN ('open', 'pending')";
$result = Database::fetch($sql);
$unassigned = (int)($result['total'] ?? 0);

$status = $unassigned > 0 ? 'success' : 'warning';
echo "<div class='metric {$status}'>";
echo "<span>Conversas Não Atribuídas</span>";
echo "<span class='value'>{$unassigned}</span>";
echo "</div>";

if ($unassigned === 0) {
    echo "<p style='color: #ffc700;'>⚠️ <strong>Aviso:</strong> Nenhuma conversa não atribuída. Crie conversas sem agent_id para testar.</p>";
}

echo "<pre>";
echo "Query: SELECT COUNT(*) as total FROM conversations \n";
echo "       WHERE (agent_id IS NULL OR agent_id = 0) \n";
echo "       AND status IN ('open', 'pending')\n";
echo "Resultado: {$unassigned} conversas";
echo "</pre>";
echo "</div>";

// Teste 3: Tempo Médio de Primeira Resposta
echo "<div class='card'>";
echo "<h2>3️⃣ Tempo Médio de Primeira Resposta</h2>";

$sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, 
            (SELECT MIN(m1.created_at) 
             FROM messages m1 
             WHERE m1.conversation_id = c.id 
             AND m1.sender_type = 'contact'),
            (SELECT MIN(m2.created_at) 
             FROM messages m2 
             WHERE m2.conversation_id = c.id 
             AND m2.sender_type = 'agent')
        )) as avg_time
        FROM conversations c
        WHERE c.created_at >= ?
        AND c.created_at <= ?
        AND EXISTS (
            SELECT 1 FROM messages m3 
            WHERE m3.conversation_id = c.id 
            AND m3.sender_type = 'agent'
        )";

$result = Database::fetch($sql, [$dateFrom, $dateTo]);
$avgFirstResponse = $result && $result['avg_time'] !== null ? round((float)$result['avg_time'], 2) : null;

$status = $avgFirstResponse !== null ? 'success' : 'warning';
echo "<div class='metric {$status}'>";
echo "<span>Tempo Médio (minutos)</span>";
echo "<span class='value'>" . ($avgFirstResponse !== null ? $avgFirstResponse : 'null') . "</span>";
echo "</div>";

if ($avgFirstResponse !== null) {
    $hours = floor($avgFirstResponse / 60);
    $minutes = $avgFirstResponse % 60;
    echo "<div class='metric {$status}'>";
    echo "<span>Formato Legível</span>";
    echo "<span class='value'>";
    if ($hours > 0) echo "{$hours}h ";
    echo "{$minutes}min";
    echo "</span>";
    echo "</div>";
}

if ($avgFirstResponse === null) {
    echo "<p style='color: #ffc700;'>⚠️ <strong>Aviso:</strong> Sem dados de tempo de resposta. Certifique-se de que há conversas com mensagens de agentes.</p>";
    
    // Verificar se há mensagens
    $sql = "SELECT 
                (SELECT COUNT(*) FROM messages WHERE sender_type = 'contact') as client_msgs,
                (SELECT COUNT(*) FROM messages WHERE sender_type = 'agent') as agent_msgs";
    $msgCount = Database::fetch($sql);
    
    echo "<p><strong>Mensagens de Clientes:</strong> " . ($msgCount['client_msgs'] ?? 0) . "</p>";
    echo "<p><strong>Mensagens de Agentes:</strong> " . ($msgCount['agent_msgs'] ?? 0) . "</p>";
}

echo "</div>";

// Teste 4: Conversas ao Longo do Tempo
echo "<div class='card'>";
echo "<h2>4️⃣ Conversas ao Longo do Tempo</h2>";

$sql = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d') as period,
            COUNT(*) as total,
            COUNT(CASE WHEN status IN ('open', 'pending') THEN 1 END) as open_count,
            COUNT(CASE WHEN status IN ('closed', 'resolved') THEN 1 END) as closed_count
        FROM conversations
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY period
        ORDER BY period ASC";

$overTime = Database::fetchAll($sql, [$dateFrom, $dateTo]);

$status = !empty($overTime) ? 'success' : 'warning';
echo "<div class='metric {$status}'>";
echo "<span>Dias com Conversas</span>";
echo "<span class='value'>" . count($overTime) . "</span>";
echo "</div>";

if (!empty($overTime)) {
    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr style='background: #f8f9fa; border-bottom: 2px solid #ddd;'>";
    echo "<th style='padding: 10px; text-align: left;'>Data</th>";
    echo "<th style='padding: 10px; text-align: center;'>Total</th>";
    echo "<th style='padding: 10px; text-align: center;'>Abertas</th>";
    echo "<th style='padding: 10px; text-align: center;'>Fechadas</th>";
    echo "</tr>";
    foreach ($overTime as $row) {
        echo "<tr style='border-bottom: 1px solid #eee;'>";
        echo "<td style='padding: 8px;'>{$row['period']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$row['total']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$row['open_count']}</td>";
        echo "<td style='padding: 8px; text-align: center;'>{$row['closed_count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: #ffc700;'>⚠️ <strong>Aviso:</strong> Nenhuma conversa no período selecionado.</p>";
}

echo "</div>";

// Teste 5: Conversas por Canal
echo "<div class='card'>";
echo "<h2>5️⃣ Conversas por Canal</h2>";

$sql = "SELECT 
            COALESCE(channel, 'N/A') as channel,
            COUNT(*) as count
        FROM conversations
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY channel
        ORDER BY count DESC";

$byChannel = Database::fetchAll($sql, [$dateFrom, $dateTo]);

$status = !empty($byChannel) ? 'success' : 'warning';
echo "<div class='metric {$status}'>";
echo "<span>Canais Ativos</span>";
echo "<span class='value'>" . count($byChannel) . "</span>";
echo "</div>";

if (!empty($byChannel)) {
    foreach ($byChannel as $row) {
        echo "<div class='metric'>";
        echo "<span>{$row['channel']}</span>";
        echo "<span class='value'>{$row['count']}</span>";
        echo "</div>";
    }
} else {
    echo "<p style='color: #ffc700;'>⚠️ <strong>Aviso:</strong> Nenhuma conversa no período selecionado.</p>";
}

echo "</div>";

// Teste 6: Conversas por Status
echo "<div class='card'>";
echo "<h2>6️⃣ Conversas por Status</h2>";

$sql = "SELECT 
            status,
            COUNT(*) as count
        FROM conversations
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY status
        ORDER BY count DESC";

$byStatus = Database::fetchAll($sql, [$dateFrom, $dateTo]);

$status = !empty($byStatus) ? 'success' : 'warning';
echo "<div class='metric {$status}'>";
echo "<span>Status Diferentes</span>";
echo "<span class='value'>" . count($byStatus) . "</span>";
echo "</div>";

if (!empty($byStatus)) {
    $statusMap = [
        'open' => 'Aberta',
        'pending' => 'Pendente',
        'closed' => 'Fechada',
        'resolved' => 'Resolvida'
    ];
    
    foreach ($byStatus as $row) {
        $statusLabel = $statusMap[$row['status']] ?? $row['status'];
        echo "<div class='metric'>";
        echo "<span>{$statusLabel}</span>";
        echo "<span class='value'>{$row['count']}</span>";
        echo "</div>";
    }
} else {
    echo "<p style='color: #ffc700;'>⚠️ <strong>Aviso:</strong> Nenhuma conversa no período selecionado.</p>";
}

echo "</div>";

// Resumo Final
echo "<div class='card'>";
echo "<h2>✅ Resumo do Teste</h2>";

$allGood = true;
$issues = [];

if ($myTotal === 0) {
    $allGood = false;
    $issues[] = "Nenhuma conversa atribuída ao agente de teste";
}

if ($avgFirstResponse === null) {
    $allGood = false;
    $issues[] = "Sem dados de tempo de resposta (pode ser normal se não houver conversas com respostas de agentes)";
}

if (empty($overTime)) {
    $allGood = false;
    $issues[] = "Nenhuma conversa no período selecionado";
}

if ($allGood) {
    echo "<p style='color: #50cd89; font-size: 1.2em;'>✅ <strong>Tudo OK!</strong> Todas as métricas estão funcionando corretamente.</p>";
} else {
    echo "<p style='color: #ffc700; font-size: 1.2em;'>⚠️ <strong>Avisos Encontrados:</strong></p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ul>";
    echo "<p><strong>Nota:</strong> Estes avisos podem ser normais se você ainda não tem dados suficientes no sistema. Crie algumas conversas e mensagens para testar completamente.</p>";
}

echo "</div>";
?>

<div class="card">
    <h2>🔗 Próximos Passos</h2>
    <ol>
        <li>Se todos os testes passaram, acesse o <a href="/dashboard" class="btn btn-success">📊 Dashboard</a></li>
        <li>Verifique se as métricas aparecem corretamente</li>
        <li>Abra o console do navegador (F12) e verifique se há erros</li>
        <li>Teste os gráficos interativos</li>
        <li>Se tudo estiver OK, remova este arquivo de teste por segurança</li>
    </ol>
</div>

</body>
</html>

