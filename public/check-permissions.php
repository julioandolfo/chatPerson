<?php
/**
 * Verificador de Permissões - Versão Ultra Simples
 * Acesse: http://localhost/check-permissions.php?user_id=1
 */

// ATIVAR erros para debug
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Carregar configuração do banco
$configFile = __DIR__ . '/../config/database.php';
if (!file_exists($configFile)) {
    die('<div class="error-box">❌ Arquivo config/database.php não encontrado!</div></div></body></html>');
}
require_once $configFile;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verificador de Permissões</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a73e8; margin-bottom: 10px; }
        h2 { color: #333; margin: 30px 0 15px; padding-bottom: 10px; border-bottom: 2px solid #1a73e8; }
        .success { color: #0f9d58; font-weight: bold; }
        .error { color: #d93025; font-weight: bold; }
        .warning { color: #f9ab00; font-weight: bold; }
        .info-box { background: #e8f0fe; border-left: 4px solid #1a73e8; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .error-box { background: #fce8e6; border-left: 4px solid #d93025; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .success-box { background: #e6f4ea; border-left: 4px solid #0f9d58; padding: 15px; margin: 15px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f8f9fa; font-weight: 600; color: #5f6368; }
        tr:hover { background: #f8f9fa; }
        code { background: #f1f3f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; font-size: 13px; }
        .btn { display: inline-block; padding: 10px 20px; background: #1a73e8; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #1557b0; }
        .btn-success { background: #0f9d58; }
        .btn-success:hover { background: #0d7d46; }
        ol, ul { margin-left: 25px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificador de Permissões</h1>
        <p style="color: #5f6368; margin-bottom: 20px;">Diagnóstico rápido do sistema de permissões</p>

<?php
// Conectar ao banco (usando as constantes do config/database.php)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo '<div class="success-box">✅ Conexão com banco de dados OK</div>';
} catch (PDOException $e) {
    echo '<div class="error-box">';
    echo '<strong>❌ Erro de conexão com banco de dados!</strong><br>';
    echo 'Mensagem: ' . htmlspecialchars($e->getMessage()) . '<br><br>';
    echo '<strong>Verifique:</strong><br>';
    echo '1. O MySQL está rodando?<br>';
    echo '2. O banco de dados existe?<br>';
    echo '3. Verifique o arquivo <code>config/database.php</code>';
    echo '</div>';
    echo '</div></body></html>';
    exit;
}

// Obter user_id
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$userId) {
    echo '<div class="info-box">';
    echo '<strong>ℹ️ Especifique um usuário</strong><br>';
    echo 'Use <code>?user_id=X</code> na URL. Exemplo: <code>check-permissions.php?user_id=1</code>';
    echo '</div>';
    
    echo '<h2>👥 Usuários Disponíveis</h2>';
    try {
        $stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY id LIMIT 20");
        $users = $stmt->fetchAll();
        
        if (empty($users)) {
            echo '<div class="warning">⚠️ Nenhum usuário encontrado no banco</div>';
        } else {
            echo '<table>';
            echo '<tr><th>ID</th><th>Nome</th><th>Email</th><th>Ação</th></tr>';
            foreach ($users as $u) {
                echo '<tr>';
                echo '<td>' . $u['id'] . '</td>';
                echo '<td>' . htmlspecialchars($u['name']) . '</td>';
                echo '<td>' . htmlspecialchars($u['email']) . '</td>';
                echo '<td><a href="?user_id=' . $u['id'] . '" class="btn">Verificar</a></td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    } catch (PDOException $e) {
        echo '<div class="error-box">Erro ao buscar usuários: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    echo '</div></body></html>';
    exit;
}

// Buscar usuário
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo '<div class="error-box">❌ Usuário ID ' . $userId . ' não encontrado!</div>';
        echo '<a href="?" class="btn">← Voltar</a>';
        echo '</div></body></html>';
        exit;
    }
    
    echo '<div class="success-box">';
    echo '<strong>👤 Usuário:</strong> ' . htmlspecialchars($user['name']);
    echo ' <small>(ID: ' . $user['id'] . ' | Email: ' . htmlspecialchars($user['email']) . ')</small>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="error-box">Erro ao buscar usuário: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '</div></body></html>';
    exit;
}

// 1. ROLES
echo '<h2>🎭 Roles do Usuário</h2>';
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.slug, r.level 
        FROM roles r
        INNER JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ?
        ORDER BY r.level
    ");
    $stmt->execute([$userId]);
    $roles = $stmt->fetchAll();
    
    if (empty($roles)) {
        echo '<div class="error-box">❌ Usuário não tem nenhuma role atribuída!</div>';
    } else {
        echo '<table>';
        echo '<tr><th>ID</th><th>Nome</th><th>Slug</th><th>Nível</th></tr>';
        foreach ($roles as $r) {
            echo '<tr>';
            echo '<td>' . $r['id'] . '</td>';
            echo '<td>' . htmlspecialchars($r['name']) . '</td>';
            echo '<td><code>' . htmlspecialchars($r['slug']) . '</code></td>';
            echo '<td>' . $r['level'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} catch (PDOException $e) {
    echo '<div class="error-box">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// 2. PERMISSÕES CRÍTICAS
echo '<h2>✅ Permissões Críticas (IMPORTANTE)</h2>';
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.slug
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userPerms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $critical = [
        'conversations.view.unassigned' => '🎯 Ver conversas NÃO atribuídas',
        'conversations.view.own' => 'Ver próprias conversas',
        'funnels.view' => '📊 Ver funis (Kanban)',
        'conversations.edit.own' => 'Editar próprias conversas',
        'messages.send.own' => 'Enviar mensagens',
    ];
    
    echo '<table>';
    echo '<tr><th style="width: 40%">Permissão</th><th style="width: 40%">Descrição</th><th>Status</th></tr>';
    
    $temUnassigned = false;
    $temFunnels = false;
    
    foreach ($critical as $slug => $desc) {
        $tem = in_array($slug, $userPerms);
        
        if ($slug === 'conversations.view.unassigned' && $tem) $temUnassigned = true;
        if ($slug === 'funnels.view' && $tem) $temFunnels = true;
        
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars($slug) . '</code></td>';
        echo '<td>' . htmlspecialchars($desc) . '</td>';
        echo '<td>';
        if ($tem) {
            echo '<span class="success">✅ TEM</span>';
        } else {
            echo '<span class="error">❌ NÃO TEM</span>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
} catch (PDOException $e) {
    echo '<div class="error-box">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// 3. CONVERSAS NÃO ATRIBUÍDAS
echo '<h2>💬 Conversas Não Atribuídas no Sistema</h2>';
try {
    $stmt = $pdo->query("
        SELECT c.id, ct.name as contact_name, c.channel, c.status, c.agent_id
        FROM conversations c
        LEFT JOIN contacts ct ON c.contact_id = ct.id
        WHERE (c.agent_id IS NULL OR c.agent_id = 0 OR c.agent_id = '')
        AND c.status = 'open'
        ORDER BY c.updated_at DESC
        LIMIT 15
    ");
    $unassigned = $stmt->fetchAll();
    
    echo '<div class="info-box">';
    echo '<strong>Total encontrado:</strong> ' . count($unassigned) . ' conversas não atribuídas (abertas)';
    echo '</div>';
    
    if (empty($unassigned)) {
        echo '<div class="warning">⚠️ Não há conversas não atribuídas no momento.<br>';
        echo 'Crie uma conversa de teste para verificar se o sistema está funcionando.</div>';
    } else {
        echo '<table>';
        echo '<tr><th>ID</th><th>Contato</th><th>Canal</th><th>Status</th><th>Agent ID</th></tr>';
        foreach ($unassigned as $c) {
            echo '<tr>';
            echo '<td>' . $c['id'] . '</td>';
            echo '<td>' . htmlspecialchars($c['contact_name'] ?? 'Sem nome') . '</td>';
            echo '<td>' . htmlspecialchars($c['channel']) . '</td>';
            echo '<td>' . htmlspecialchars($c['status']) . '</td>';
            echo '<td><span class="warning">' . ($c['agent_id'] ?: 'NULL') . '</span></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} catch (PDOException $e) {
    echo '<div class="error-box">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// 4. DIAGNÓSTICO FINAL
echo '<h2>🎯 Diagnóstico Final</h2>';

if ($temUnassigned && $temFunnels) {
    echo '<div class="success-box">';
    echo '<h3 class="success">✅ PERMISSÕES CORRETAS!</h3>';
    echo '<p>O usuário tem todas as permissões necessárias.</p>';
    echo '<hr style="margin: 15px 0; border: none; border-top: 1px solid #0f9d58;">';
    echo '<strong>📋 Se ainda não consegue ver as conversas:</strong>';
    echo '<ol>';
    echo '<li><strong>Faça LOGOUT</strong> do sistema</li>';
    echo '<li><strong>Faça LOGIN</strong> novamente</li>';
    echo '<li><strong>Limpe o cache do navegador</strong> (Ctrl+Shift+Delete)</li>';
    echo '<li>Acesse <code>/conversations</code></li>';
    echo '<li>Use o filtro "🔴 Não atribuídas"</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<div class="error-box">';
    echo '<h3 class="error">❌ PERMISSÕES FALTANDO!</h3>';
    
    if (!$temUnassigned) {
        echo '<p>❌ Falta: <code>conversations.view.unassigned</code></p>';
    }
    if (!$temFunnels) {
        echo '<p>❌ Falta: <code>funnels.view</code></p>';
    }
    
    echo '<hr style="margin: 15px 0; border: none; border-top: 1px solid #d93025;">';
    echo '<p><strong>Solução:</strong></p>';
    echo '<a href="fix-permissions.php" class="btn btn-success" target="_blank">🔧 Corrigir Permissões Agora</a>';
    echo '</div>';
}

// Links úteis
echo '<hr style="margin: 30px 0;">';
echo '<div style="text-align: center;">';
echo '<a href="fix-permissions.php" class="btn">🔧 Corrigir Permissões</a>';
echo '<a href="?" class="btn">🔄 Recarregar</a>';
echo '<a href="/conversations" class="btn">💬 Ir para Conversas</a>';
echo '</div>';

?>

    </div>
</body>
</html>

