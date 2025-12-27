<?php
/**
 * Script Simples de Debug de Permissões
 * Acesse via: http://localhost/debug-simples.php?user_id=X
 */

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexão direta com banco (sem dependências)
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("❌ Erro de conexão: " . $e->getMessage());
}

// Obter user_id
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug de Permissões - Simples</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #4CAF50; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f9f9f9; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 15px 0; }
        .section { margin: 25px 0; padding: 20px; background: #fafafa; border-radius: 5px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .button { display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .button:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug de Permissões - Simples</h1>

<?php
if (!$userId) {
    echo '<div class="info">';
    echo '<h3>❌ Nenhum Usuário Especificado</h3>';
    echo '<p><strong>Opção 1:</strong> Adicione <code>?user_id=X</code> na URL</p>';
    echo '<p>Exemplo: <code>debug-simples.php?user_id=1</code></p>';
    echo '<p><strong>Opção 2:</strong> Faça login no sistema primeiro</p>';
    echo '<a href="/login" class="button">👉 Ir para Login</a>';
    echo '</div>';
    
    // Listar usuários disponíveis
    echo '<h2>👥 Usuários Disponíveis:</h2>';
    $users = $pdo->query("SELECT id, name, email FROM users ORDER BY id")->fetchAll();
    echo '<table>';
    echo '<tr><th>ID</th><th>Nome</th><th>Email</th><th>Ação</th></tr>';
    foreach ($users as $u) {
        echo '<tr>';
        echo '<td>' . $u['id'] . '</td>';
        echo '<td>' . htmlspecialchars($u['name']) . '</td>';
        echo '<td>' . htmlspecialchars($u['email']) . '</td>';
        echo '<td><a href="?user_id=' . $u['id'] . '" class="button">Debug</a></td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div></body></html>';
    exit;
}

// Buscar informações do usuário
$user = $pdo->query("SELECT * FROM users WHERE id = {$userId}")->fetch();
if (!$user) {
    die('<div class="error">❌ Usuário ID ' . $userId . ' não encontrado!</div></div></body></html>');
}

echo "<div class='info'>";
echo "<strong>👤 Usuário:</strong> {$user['name']} (ID: {$user['id']}, Email: {$user['email']})";
echo "</div>";

// 1. Roles do usuário
echo "<div class='section'>";
echo "<h2>🎭 Roles do Usuário</h2>";
$roles = $pdo->query("
    SELECT r.* 
    FROM roles r
    INNER JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = {$userId}
    ORDER BY r.level
")->fetchAll();

if (empty($roles)) {
    echo "<p class='warning'>⚠️ Usuário não tem nenhuma role!</p>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Nome</th><th>Slug</th><th>Nível</th></tr>";
    foreach ($roles as $role) {
        echo "<tr><td>{$role['id']}</td><td>{$role['name']}</td><td><code>{$role['slug']}</code></td><td>{$role['level']}</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// 2. Permissões CRÍTICAS
echo "<div class='section'>";
echo "<h2>✅ Permissões Críticas</h2>";

$criticalPerms = [
    'conversations.view.own' => 'Ver próprias conversas',
    'conversations.view.unassigned' => 'Ver conversas NÃO ATRIBUÍDAS',
    'conversations.view.all' => 'Ver TODAS as conversas',
    'funnels.view' => 'Ver funis (Kanban)',
    'messages.send.own' => 'Enviar mensagens',
];

$userPermissions = $pdo->query("
    SELECT DISTINCT p.slug
    FROM permissions p
    INNER JOIN role_permissions rp ON p.id = rp.permission_id
    INNER JOIN user_roles ur ON rp.role_id = ur.role_id
    WHERE ur.user_id = {$userId}
")->fetchAll(PDO::FETCH_COLUMN);

echo "<table>";
echo "<tr><th>Permissão</th><th>Descrição</th><th>Status</th></tr>";
foreach ($criticalPerms as $slug => $desc) {
    $tem = in_array($slug, $userPermissions);
    echo "<tr>";
    echo "<td><code>{$slug}</code></td>";
    echo "<td>{$desc}</td>";
    echo "<td>" . ($tem ? "<span class='success'>✅ TEM</span>" : "<span class='error'>❌ NÃO TEM</span>") . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 3. Conversas Não Atribuídas
echo "<div class='section'>";
echo "<h2>💬 Conversas Não Atribuídas (Teste)</h2>";
$unassigned = $pdo->query("
    SELECT c.id, ct.name, c.channel, c.status, c.agent_id
    FROM conversations c
    LEFT JOIN contacts ct ON c.contact_id = ct.id
    WHERE (c.agent_id IS NULL OR c.agent_id = 0)
    AND c.status = 'open'
    ORDER BY c.updated_at DESC
    LIMIT 10
")->fetchAll();

echo "<p><strong>Total:</strong> " . count($unassigned) . " conversas não atribuídas (abertas)</p>";

if (empty($unassigned)) {
    echo "<p class='warning'>⚠️ Não há conversas não atribuídas no momento. Crie uma para testar!</p>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Contato</th><th>Canal</th><th>Status</th><th>Agent ID</th></tr>";
    foreach ($unassigned as $c) {
        echo "<tr>";
        echo "<td>{$c['id']}</td>";
        echo "<td>" . htmlspecialchars($c['name']) . "</td>";
        echo "<td>{$c['channel']}</td>";
        echo "<td>{$c['status']}</td>";
        echo "<td>" . ($c['agent_id'] ?? '<span class="warning">NULL</span>') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// 4. DIAGNÓSTICO FINAL
echo "<div class='section'>";
echo "<h2>🎯 Diagnóstico Final</h2>";

$hasUnassigned = in_array('conversations.view.unassigned', $userPermissions);
$hasFunnels = in_array('funnels.view', $userPermissions);
$hasConversations = !empty($unassigned);

echo "<table>";
echo "<tr><th>Item</th><th>Status</th><th>Ação</th></tr>";

echo "<tr>";
echo "<td>Permissão 'conversations.view.unassigned'</td>";
if ($hasUnassigned) {
    echo "<td class='success'>✅ OK</td><td>-</td>";
} else {
    echo "<td class='error'>❌ FALTANDO</td>";
    echo "<td><a href='fix-permissions.php' class='button'>Corrigir</a></td>";
}
echo "</tr>";

echo "<tr>";
echo "<td>Permissão 'funnels.view'</td>";
if ($hasFunnels) {
    echo "<td class='success'>✅ OK</td><td>-</td>";
} else {
    echo "<td class='error'>❌ FALTANDO</td>";
    echo "<td><a href='fix-permissions.php' class='button'>Corrigir</a></td>";
}
echo "</tr>";

echo "<tr>";
echo "<td>Conversas não atribuídas existem?</td>";
if ($hasConversations) {
    echo "<td class='success'>✅ Sim (" . count($unassigned) . ")</td><td>-</td>";
} else {
    echo "<td class='warning'>⚠️ Não</td><td>Crie uma conversa de teste</td>";
}
echo "</tr>";

echo "</table>";

// Conclusão
if ($hasUnassigned && $hasFunnels) {
    echo "<div class='info'>";
    echo "<h3 class='success'>✅ PERMISSÕES OK!</h3>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ol>";
    echo "<li>Faça <strong>LOGOUT</strong> e <strong>LOGIN</strong> novamente</li>";
    echo "<li>Limpe o cache do navegador (Ctrl+Shift+Delete)</li>";
    echo "<li>Acesse <code>/conversations</code> e use o filtro 'Não atribuídas'</li>";
    echo "<li>Acesse <code>/funnels/1/kanban</code></li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ PERMISSÕES FALTANDO!</h3>";
    echo "<p>Execute: <a href='fix-permissions.php' class='button'>fix-permissions.php</a></p>";
    echo "</div>";
}
echo "</div>";

// Links úteis
echo "<hr>";
echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='fix-permissions.php' class='button'>🔧 Corrigir Permissões</a>";
echo "<a href='?' class='button'>🔄 Recarregar</a>";
echo "<a href='/conversations' class='button'>💬 Ver Conversas</a>";
echo "</div>";
?>

    </div>
</body>
</html>

