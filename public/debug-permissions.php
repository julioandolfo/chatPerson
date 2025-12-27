<?php
/**
 * Script de Debug de Permissões
 * Acesse via: http://localhost/debug-permissions.php?user_id=X
 */

// Carregar dependências manualmente (sem Composer)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Iniciar sessão ANTES de qualquer output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carregar helpers necessários
require_once __DIR__ . '/../config/database.php';

// Conexão direta com banco
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Obter user_id da URL ou da sessão
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);

if (!$userId) {
    echo "<h1>❌ Erro</h1>";
    echo "<p>Nenhum usuário especificado.</p>";
    echo "<p><strong>Opção 1:</strong> Use <code>?user_id=X</code> na URL (exemplo: ?user_id=1)</p>";
    echo "<p><strong>Opção 2:</strong> Faça login no sistema primeiro, depois acesse esta página</p>";
    echo "<hr>";
    echo "<p><a href='/login'>👉 Ir para Login</a></p>";
    exit;
}

echo "<h1>🔍 Debug de Permissões - Usuário ID: {$userId}</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
</style>";

// 1. Informações do Usuário
echo "<div class='section'>";
echo "<h2>👤 Informações do Usuário</h2>";
$user = $db->query("SELECT * FROM users WHERE id = {$userId}")->fetch();
if ($user) {
    echo "<table>";
    echo "<tr><th>Campo</th><th>Valor</th></tr>";
    echo "<tr><td>ID</td><td>{$user['id']}</td></tr>";
    echo "<tr><td>Nome</td><td>{$user['name']}</td></tr>";
    echo "<tr><td>Email</td><td>{$user['email']}</td></tr>";
    echo "<tr><td>Status</td><td>{$user['status']}</td></tr>";
    echo "</table>";
} else {
    echo "<p class='error'>❌ Usuário não encontrado!</p>";
    exit;
}
echo "</div>";

// 2. Roles do Usuário
echo "<div class='section'>";
echo "<h2>🎭 Roles do Usuário</h2>";
$roles = $db->query("
    SELECT r.* 
    FROM roles r
    INNER JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = {$userId}
    ORDER BY r.level ASC
")->fetchAll();

if (empty($roles)) {
    echo "<p class='warning'>⚠️ Usuário não tem nenhuma role atribuída!</p>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Nome</th><th>Slug</th><th>Nível</th><th>Sistema</th></tr>";
    foreach ($roles as $role) {
        echo "<tr>";
        echo "<td>{$role['id']}</td>";
        echo "<td>{$role['name']}</td>";
        echo "<td>{$role['slug']}</td>";
        echo "<td>{$role['level']}</td>";
        echo "<td>" . ($role['is_system'] ? 'Sim' : 'Não') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// 3. Permissões DIRETAS das Roles
echo "<div class='section'>";
echo "<h2>🔑 Permissões DIRETAS das Roles</h2>";
$permissions = $db->query("
    SELECT DISTINCT p.*, r.name as role_name, r.slug as role_slug
    FROM permissions p
    INNER JOIN role_permissions rp ON p.id = rp.permission_id
    INNER JOIN roles r ON rp.role_id = r.id
    INNER JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = {$userId}
    ORDER BY p.module, p.slug
")->fetchAll();

if (empty($permissions)) {
    echo "<p class='warning'>⚠️ Nenhuma permissão direta encontrada!</p>";
} else {
    echo "<p><strong>Total de permissões diretas:</strong> " . count($permissions) . "</p>";
    
    // Agrupar por módulo
    $byModule = [];
    foreach ($permissions as $perm) {
        $module = $perm['module'] ?? 'other';
        if (!isset($byModule[$module])) {
            $byModule[$module] = [];
        }
        $byModule[$module][] = $perm;
    }
    
    echo "<table>";
    echo "<tr><th>Módulo</th><th>Permissão</th><th>Slug</th><th>Via Role</th></tr>";
    foreach ($byModule as $module => $perms) {
        foreach ($perms as $perm) {
            echo "<tr>";
            echo "<td><strong>{$module}</strong></td>";
            echo "<td>{$perm['name']}</td>";
            echo "<td><code>{$perm['slug']}</code></td>";
            echo "<td>{$perm['role_name']}</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
}
echo "</div>";

// 4. Verificar Permissões Específicas Importantes
echo "<div class='section'>";
echo "<h2>✅ Verificação de Permissões Críticas</h2>";

$criticalPermissions = [
    'conversations.view.own' => 'Ver próprias conversas',
    'conversations.view.unassigned' => 'Ver conversas não atribuídas',
    'conversations.view.all' => 'Ver todas as conversas',
    'conversations.edit.own' => 'Editar próprias conversas',
    'messages.send.own' => 'Enviar mensagens',
    'funnels.view' => 'Ver funis (Kanban)',
];

echo "<table>";
echo "<tr><th>Permissão</th><th>Descrição</th><th>Status</th></tr>";

foreach ($criticalPermissions as $slug => $desc) {
    $hasPermission = false;
    foreach ($permissions as $perm) {
        if ($perm['slug'] === $slug) {
            $hasPermission = true;
            break;
        }
    }
    
    echo "<tr>";
    echo "<td><code>{$slug}</code></td>";
    echo "<td>{$desc}</td>";
    if ($hasPermission) {
        echo "<td class='success'>✅ TEM</td>";
    } else {
        echo "<td class='error'>❌ NÃO TEM</td>";
    }
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 5. Testar Conversas Não Atribuídas
echo "<div class='section'>";
echo "<h2>💬 Teste: Conversas Não Atribuídas</h2>";

$unassignedConversations = $db->query("
    SELECT c.id, c.status, c.channel, ct.name as contact_name, c.agent_id
    FROM conversations c
    LEFT JOIN contacts ct ON c.contact_id = ct.id
    WHERE (c.agent_id IS NULL OR c.agent_id = 0)
    AND c.status = 'open'
    LIMIT 10
")->fetchAll();

echo "<p><strong>Total de conversas não atribuídas (abertas):</strong> " . count($unassignedConversations) . "</p>";

if (empty($unassignedConversations)) {
    echo "<p class='warning'>⚠️ Não há conversas não atribuídas no sistema no momento.</p>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Contato</th><th>Canal</th><th>Status</th><th>Agent ID</th></tr>";
    foreach ($unassignedConversations as $conv) {
        echo "<tr>";
        echo "<td>{$conv['id']}</td>";
        echo "<td>{$conv['contact_name']}</td>";
        echo "<td>{$conv['channel']}</td>";
        echo "<td>{$conv['status']}</td>";
        echo "<td>" . ($conv['agent_id'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// 6. Verificar Cache de Permissões
echo "<div class='section'>";
echo "<h2>💾 Cache de Permissões</h2>";
$cacheDir = __DIR__ . '/../storage/cache/permissions/';
if (is_dir($cacheDir)) {
    $cacheFiles = glob($cacheDir . "user_{$userId}_*");
    echo "<p><strong>Arquivos de cache para este usuário:</strong> " . count($cacheFiles) . "</p>";
    
    if (!empty($cacheFiles)) {
        echo "<ul>";
        foreach ($cacheFiles as $file) {
            $age = time() - filemtime($file);
            $ageMinutes = round($age / 60);
            echo "<li>" . basename($file) . " (idade: {$ageMinutes} minutos)</li>";
        }
        echo "</ul>";
        echo "<p class='warning'>⚠️ Cache encontrado. Se você acabou de adicionar permissões, limpe o cache!</p>";
        echo "<p><a href='fix-permissions.php' target='_blank'>👉 Clique aqui para limpar o cache</a></p>";
    } else {
        echo "<p class='success'>✅ Nenhum cache encontrado (permissões serão buscadas do banco)</p>";
    }
} else {
    echo "<p class='error'>❌ Diretório de cache não existe!</p>";
}
echo "</div>";

// 7. Diagnóstico Final
echo "<div class='section'>";
echo "<h2>🎯 Diagnóstico Final</h2>";

$hasUnassignedPerm = false;
$hasFunnelsPerm = false;

foreach ($permissions as $perm) {
    if ($perm['slug'] === 'conversations.view.unassigned') {
        $hasUnassignedPerm = true;
    }
    if ($perm['slug'] === 'funnels.view') {
        $hasFunnelsPerm = true;
    }
}

echo "<table>";
echo "<tr><th>Verificação</th><th>Status</th><th>Ação</th></tr>";

// Verificação 1: Permissão de conversas não atribuídas
echo "<tr>";
echo "<td>Permissão 'conversations.view.unassigned'</td>";
if ($hasUnassignedPerm) {
    echo "<td class='success'>✅ OK</td>";
    echo "<td>-</td>";
} else {
    echo "<td class='error'>❌ FALTANDO</td>";
    echo "<td><a href='fix-permissions.php' target='_blank'>Corrigir</a></td>";
}
echo "</tr>";

// Verificação 2: Permissão de funis
echo "<tr>";
echo "<td>Permissão 'funnels.view'</td>";
if ($hasFunnelsPerm) {
    echo "<td class='success'>✅ OK</td>";
    echo "<td>-</td>";
} else {
    echo "<td class='error'>❌ FALTANDO</td>";
    echo "<td><a href='fix-permissions.php' target='_blank'>Corrigir</a></td>";
}
echo "</tr>";

// Verificação 3: Conversas não atribuídas existem
echo "<tr>";
echo "<td>Conversas não atribuídas no sistema</td>";
if (!empty($unassignedConversations)) {
    echo "<td class='success'>✅ Existem (" . count($unassignedConversations) . ")</td>";
    echo "<td>-</td>";
} else {
    echo "<td class='warning'>⚠️ Nenhuma encontrada</td>";
    echo "<td>Crie uma conversa de teste</td>";
}
echo "</tr>";

// Verificação 4: Cache
echo "<tr>";
echo "<td>Cache de permissões</td>";
if (!empty($cacheFiles)) {
    echo "<td class='warning'>⚠️ Cache existe</td>";
    echo "<td><a href='fix-permissions.php' target='_blank'>Limpar cache</a></td>";
} else {
    echo "<td class='success'>✅ Sem cache</td>";
    echo "<td>-</td>";
}
echo "</tr>";

echo "</table>";

// Conclusão
echo "<hr>";
if ($hasUnassignedPerm && $hasFunnelsPerm) {
    echo "<h3 class='success'>✅ PERMISSÕES CORRETAS!</h3>";
    echo "<p>O usuário tem as permissões necessárias. Se ainda não consegue ver as conversas:</p>";
    echo "<ol>";
    echo "<li>Limpe o cache: <a href='fix-permissions.php' target='_blank'>fix-permissions.php</a></li>";
    echo "<li>Faça logout e login novamente</li>";
    echo "<li>Limpe o cache do navegador (Ctrl+Shift+Delete)</li>";
    echo "<li>Verifique se há conversas não atribuídas no sistema</li>";
    echo "</ol>";
} else {
    echo "<h3 class='error'>❌ PERMISSÕES FALTANDO!</h3>";
    echo "<p>Execute o script de correção: <a href='fix-permissions.php' target='_blank'>fix-permissions.php</a></p>";
}

echo "</div>";

