<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste de Datas - Gráficos</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #4ec9b0; font-weight: bold; }
        .error { color: #f14c4c; font-weight: bold; }
        .warning { color: #ffc700; font-weight: bold; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 Teste de Datas - Gráficos do Dashboard</h1>

<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Helpers\Database;

$dateFrom = '2025-12-01';
$dateTo = '2025-12-18';

echo "<div class='card'>";
echo "<h2>📅 Período de Teste</h2>";
echo "<p><strong>De:</strong> {$dateFrom}</p>";
echo "<p><strong>Até:</strong> {$dateTo}</p>";
echo "</div>";

// Teste 1: Verificar conversas na tabela
echo "<div class='card'>";
echo "<h2>1️⃣ Conversas na Tabela</h2>";

$sql = "SELECT 
            id,
            contact_id,
            channel,
            status,
            created_at,
            updated_at
        FROM conversations
        ORDER BY created_at DESC
        LIMIT 5";

$conversations = Database::fetchAll($sql);

if (empty($conversations)) {
    echo "<p class='error'>❌ Nenhuma conversa encontrada na tabela!</p>";
} else {
    echo "<p class='success'>✅ " . count($conversations) . " conversas encontradas (mostrando últimas 5)</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Canal</th><th>Status</th><th>Created At</th><th>Updated At</th></tr>";
    foreach ($conversations as $conv) {
        echo "<tr>";
        echo "<td>{$conv['id']}</td>";
        echo "<td>{$conv['channel']}</td>";
        echo "<td>{$conv['status']}</td>";
        echo "<td>{$conv['created_at']}</td>";
        echo "<td>{$conv['updated_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// Teste 2: Conversas com filtro de data
echo "<div class='card'>";
echo "<h2>2️⃣ Conversas COM Filtro de Data (created_at)</h2>";

$sql = "SELECT COUNT(*) as total FROM conversations WHERE created_at >= ? AND created_at <= ?";
$result = Database::fetch($sql, [$dateFrom, $dateTo]);
$totalWithFilter = $result['total'] ?? 0;

if ($totalWithFilter == 0) {
    echo "<p class='error'>❌ 0 conversas encontradas com filtro de data!</p>";
    echo "<p class='warning'>⚠️ <strong>PROBLEMA IDENTIFICADO:</strong> As conversas foram criadas FORA do período {$dateFrom} a {$dateTo}</p>";
    
    // Verificar a data mais antiga
    $sqlOldest = "SELECT MIN(created_at) as oldest FROM conversations";
    $oldest = Database::fetch($sqlOldest);
    echo "<p>📅 Conversa mais antiga: <strong>" . ($oldest['oldest'] ?? 'N/A') . "</strong></p>";
    
    // Verificar a data mais recente
    $sqlNewest = "SELECT MAX(created_at) as newest FROM conversations";
    $newest = Database::fetch($sqlNewest);
    echo "<p>📅 Conversa mais recente: <strong>" . ($newest['newest'] ?? 'N/A') . "</strong></p>";
    
    echo "<p class='warning'>💡 <strong>Solução:</strong> Ajuste o período no dashboard para incluir essas datas!</p>";
} else {
    echo "<p class='success'>✅ {$totalWithFilter} conversas encontradas com filtro!</p>";
}

echo "<pre>";
echo "Query: SELECT COUNT(*) as total FROM conversations \n";
echo "       WHERE created_at >= '{$dateFrom}' AND created_at <= '{$dateTo}'\n";
echo "Resultado: {$totalWithFilter} conversas";
echo "</pre>";
echo "</div>";

// Teste 3: Conversas por Canal (com filtro)
echo "<div class='card'>";
echo "<h2>3️⃣ Conversas por Canal (COM Filtro)</h2>";

$sql = "SELECT 
            COALESCE(channel, 'N/A') as channel,
            COUNT(*) as count
        FROM conversations
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY channel
        ORDER BY count DESC";

$byChannel = Database::fetchAll($sql, [$dateFrom, $dateTo]);

if (empty($byChannel)) {
    echo "<p class='error'>❌ Nenhum canal encontrado com filtro de data!</p>";
} else {
    echo "<p class='success'>✅ " . count($byChannel) . " canais encontrados</p>";
    echo "<table>";
    echo "<tr><th>Canal</th><th>Total</th></tr>";
    foreach ($byChannel as $row) {
        echo "<tr><td>{$row['channel']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// Teste 4: Conversas por Canal (SEM filtro)
echo "<div class='card'>";
echo "<h2>4️⃣ Conversas por Canal (SEM Filtro)</h2>";

$sql = "SELECT 
            COALESCE(channel, 'N/A') as channel,
            COUNT(*) as count
        FROM conversations
        GROUP BY channel
        ORDER BY count DESC";

$byChannelNoFilter = Database::fetchAll($sql);

if (empty($byChannelNoFilter)) {
    echo "<p class='error'>❌ Nenhum canal encontrado!</p>";
} else {
    echo "<p class='success'>✅ " . count($byChannelNoFilter) . " canais encontrados</p>";
    echo "<table>";
    echo "<tr><th>Canal</th><th>Total</th></tr>";
    foreach ($byChannelNoFilter as $row) {
        echo "<tr><td>{$row['channel']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// Teste 5: Conversas ao Longo do Tempo
echo "<div class='card'>";
echo "<h2>5️⃣ Conversas ao Longo do Tempo (COM Filtro)</h2>";

$sql = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m-%d') as period,
            COUNT(*) as total
        FROM conversations
        WHERE created_at >= ? AND created_at <= ?
        GROUP BY period
        ORDER BY period ASC";

$overTime = Database::fetchAll($sql, [$dateFrom, $dateTo]);

if (empty($overTime)) {
    echo "<p class='error'>❌ Nenhum dado encontrado!</p>";
} else {
    echo "<p class='success'>✅ " . count($overTime) . " dias com conversas</p>";
    echo "<table>";
    echo "<tr><th>Data</th><th>Total</th></tr>";
    foreach ($overTime as $row) {
        echo "<tr><td>{$row['period']}</td><td>{$row['total']}</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// Resumo e Solução
echo "<div class='card'>";
echo "<h2>✅ Resumo e Solução</h2>";

if ($totalWithFilter == 0) {
    echo "<p class='error'><strong>❌ PROBLEMA IDENTIFICADO:</strong></p>";
    echo "<p>As conversas foram criadas em datas FORA do período configurado no dashboard ({$dateFrom} a {$dateTo}).</p>";
    echo "<p class='warning'><strong>💡 SOLUÇÃO:</strong></p>";
    echo "<ol>";
    echo "<li>No dashboard, ajuste o filtro de data para incluir o período correto</li>";
    echo "<li>Ou remova o filtro de data dos gráficos para mostrar TODAS as conversas</li>";
    echo "<li>Ou altere as queries para usar <code>updated_at</code> ao invés de <code>created_at</code></li>";
    echo "</ol>";
} else {
    echo "<p class='success'><strong>✅ TUDO OK!</strong></p>";
    echo "<p>As conversas estão no período correto. O problema pode ser no JavaScript ou na rota do endpoint.</p>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ol>";
    echo "<li>Verifique o console do navegador (F12) por erros JavaScript</li>";
    echo "<li>Teste o endpoint diretamente: <code>/dashboard/chart-data?type=conversations_by_channel&date_from={$dateFrom}&date_to={$dateTo}</code></li>";
    echo "<li>Verifique os logs em <code>/view-dash-logs.php</code></li>";
    echo "</ol>";
}
echo "</div>";
?>

<div class="card">
    <a href="/dashboard" style="display: inline-block; background: #009ef7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        📊 Voltar para Dashboard
    </a>
    <a href="/view-dash-logs.php" style="display: inline-block; background: #4ec9b0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">
        📋 Ver Logs
    </a>
</div>

</body>
</html>

