<?php
/**
 * Verificador de Permissões - Versão Standalone
 * NÃO PRECISA DE NENHUM ARQUIVO EXTERNO
 */

// Ativar erros para ver o que está acontecendo
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Tentar obter configurações de várias formas
$dbConfig = null;

// Método 1: Tentar carregar do config/database.php
$configFile = __DIR__ . '/../config/database.php';
if (file_exists($configFile)) {
    require_once $configFile;
    if (defined('DB_HOST')) {
        $dbConfig = [
            'host' => DB_HOST,
            'name' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASS
        ];
    }
}

// Método 2: Se não conseguiu, usar valores padrão do Laragon
if (!$dbConfig) {
    $dbConfig = [
        'host' => 'localhost',
        'name' => 'chat',
        'user' => 'root',
        'pass' => ''
    ];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador de Permissões</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 1100px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { 
            color: #2d3748; 
            margin-bottom: 8px;
            font-size: 32px;
            font-weight: 700;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 16px;
        }
        h2 { 
            color: #2d3748; 
            margin: 40px 0 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid #667eea;
            font-size: 24px;
        }
        .box {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 5px solid #667eea;
        }
        .success-box { 
            background: #f0fdf4; 
            border-left-color: #22c55e;
            color: #166534;
        }
        .error-box { 
            background: #fef2f2; 
            border-left-color: #ef4444;
            color: #991b1b;
        }
        .warning-box { 
            background: #fffbeb; 
            border-left-color: #f59e0b;
            color: #92400e;
        }
        .info-box { 
            background: #eff6ff; 
            border-left-color: #3b82f6;
            color: #1e40af;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th, td { 
            padding: 16px; 
            text-align: left; 
            border-bottom: 1px solid #e5e7eb;
        }
        th { 
            background: #f9fafb; 
            font-weight: 600; 
            color: #374151;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        tr:hover { background: #f9fafb; }
        tr:last-child td { border-bottom: none; }
        code { 
            background: #f1f5f9; 
            padding: 3px 8px; 
            border-radius: 4px; 
            font-family: 'Courier New', monospace; 
            font-size: 13px;
            color: #1e293b;
        }
        .btn { 
            display: inline-block; 
            padding: 12px 24px; 
            background: #667eea; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            margin: 8px;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(102,126,234,0.4);
        }
        .btn:hover { 
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102,126,234,0.4);
        }
        .btn-success { 
            background: #22c55e;
            box-shadow: 0 2px 4px rgba(34,197,94,0.4);
        }
        .btn-success:hover { 
            background: #16a34a;
            box-shadow: 0 4px 8px rgba(34,197,94,0.4);
        }
        .success { color: #22c55e; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        ol, ul { margin-left: 25px; line-height: 2; }
        .actions { text-align: center; margin: 40px 0; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificador de Permissões</h1>
        <div class="subtitle">Diagnóstico completo do sistema de permissões</div>

<?php
// Conectar ao banco
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo '<div class="box success-box">✅ <strong>Conectado ao banco de dados com sucesso!</strong></div>';
} catch (PDOException $e) {
    echo '<div class="box error-box">';
    echo '<strong>❌ Erro de conexão com banco de dados</strong><br><br>';
    echo '<strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>';
    echo '<strong>Configuração usada:</strong><br>';
    echo 'Host: ' . htmlspecialchars($dbConfig['host']) . '<br>';
    echo 'Banco: ' . htmlspecialchars($dbConfig['name']) . '<br>';
    echo 'Usuário: ' . htmlspecialchars($dbConfig['user']);
    echo '</div>';
    echo '</div></body></html>';
    exit;
}

// Obter user_id
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Se não especificou user_id, mostrar lista
if (!$userId) {
    echo '<div class="box info-box">';
    echo '<strong>ℹ️ Selecione um usuário para verificar</strong><br>';
    echo 'Clique no botão "Verificar" ao lado do usuário desejado.';
    echo '</div>';
    
    echo '<h2>👥 Usuários Disponíveis</h2>';
    
    $stmt = $pdo->query("SELECT id, name, email, status FROM users ORDER BY id LIMIT 30");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo '<div class="box warning-box">⚠️ Nenhum usuário encontrado no banco de dados.</div>';
    } else {
        echo '<table>';
        echo '<tr><th>ID</th><th>Nome</th><th>Email</th><th>Status</th><th>Ação</th></tr>';
        foreach ($users as $u) {
            echo '<tr>';
            echo '<td><strong>' . $u['id'] . '</strong></td>';
            echo '<td>' . htmlspecialchars($u['name']) . '</td>';
            echo '<td>' . htmlspecialchars($u['email']) . '</td>';
            echo '<td><span class="badge badge-' . ($u['status'] === 'active' ? 'success' : 'error') . '">' . $u['status'] . '</span></td>';
            echo '<td><a href="?user_id=' . $u['id'] . '" class="btn">🔍 Verificar</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    echo '</div></body></html>';
    exit;
}

// Buscar usuário específico
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo '<div class="box error-box">❌ Usuário ID ' . $userId . ' não encontrado!</div>';
    echo '<div class="actions"><a href="?" class="btn">← Voltar</a></div>';
    echo '</div></body></html>';
    exit;
}

echo '<div class="box success-box">';
echo '<strong>👤 Usuário Selecionado:</strong> ' . htmlspecialchars($user['name']);
echo ' <small>(ID: ' . $user['id'] . ' | Email: ' . htmlspecialchars($user['email']) . ')</small>';
echo '</div>';

// ROLES
echo '<h2>🎭 Roles do Usuário</h2>';
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
    echo '<div class="box error-box">❌ <strong>Usuário não tem nenhuma role atribuída!</strong></div>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Nome</th><th>Slug</th><th>Nível Hierárquico</th></tr>';
    foreach ($roles as $r) {
        echo '<tr>';
        echo '<td>' . $r['id'] . '</td>';
        echo '<td><strong>' . htmlspecialchars($r['name']) . '</strong></td>';
        echo '<td><code>' . htmlspecialchars($r['slug']) . '</code></td>';
        echo '<td>' . $r['level'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

// PERMISSÕES CRÍTICAS
echo '<h2>✅ Permissões Críticas</h2>';
echo '<div class="box info-box">Estas são as permissões mais importantes para o funcionamento correto do sistema.</div>';

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
    'conversations.view.unassigned' => '🎯 Ver conversas NÃO atribuídas (PRINCIPAL)',
    'conversations.view.own' => 'Ver conversas próprias',
    'funnels.view' => '📊 Ver funis e acessar Kanban',
    'conversations.edit.own' => 'Editar conversas próprias',
    'messages.send.own' => 'Enviar mensagens',
];

echo '<table>';
echo '<tr><th style="width:45%">Permissão</th><th style="width:40%">Descrição</th><th style="width:15%">Status</th></tr>';

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
        echo '<span class="badge badge-success">✅ TEM</span>';
    } else {
        echo '<span class="badge badge-error">❌ NÃO</span>';
    }
    echo '</td>';
    echo '</tr>';
}
echo '</table>';

// CONVERSAS NÃO ATRIBUÍDAS
echo '<h2>💬 Conversas Não Atribuídas Disponíveis</h2>';
$stmt = $pdo->query("
    SELECT c.id, ct.name as contact_name, c.channel, c.status, c.agent_id, c.updated_at
    FROM conversations c
    LEFT JOIN contacts ct ON c.contact_id = ct.id
    WHERE (c.agent_id IS NULL OR c.agent_id = 0 OR c.agent_id = '')
    AND c.status = 'open'
    ORDER BY c.updated_at DESC
    LIMIT 20
");
$unassigned = $stmt->fetchAll();

echo '<div class="box info-box">';
echo '<strong>📊 Total encontrado:</strong> ' . count($unassigned) . ' conversas não atribuídas (status: open)';
echo '</div>';

if (empty($unassigned)) {
    echo '<div class="box warning-box">';
    echo '⚠️ <strong>Não há conversas não atribuídas no momento.</strong><br><br>';
    echo 'Para testar o sistema, crie uma conversa sem atribuir a nenhum agente.';
    echo '</div>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Contato</th><th>Canal</th><th>Agent ID</th><th>Última Atualização</th></tr>';
    foreach ($unassigned as $c) {
        echo '<tr>';
        echo '<td><strong>' . $c['id'] . '</strong></td>';
        echo '<td>' . htmlspecialchars($c['contact_name'] ?? 'Sem nome') . '</td>';
        echo '<td>' . htmlspecialchars($c['channel']) . '</td>';
        echo '<td><span class="warning">' . ($c['agent_id'] ?: 'NULL') . '</span></td>';
        echo '<td><small>' . htmlspecialchars($c['updated_at']) . '</small></td>';
        echo '</tr>';
    }
    echo '</table>';
}

// DIAGNÓSTICO FINAL
echo '<h2>🎯 Diagnóstico Final</h2>';

if ($temUnassigned && $temFunnels) {
    echo '<div class="box success-box">';
    echo '<h3 class="success">✅ PERMISSÕES CORRETAS!</h3>';
    echo '<p style="margin: 15px 0;">O usuário possui todas as permissões necessárias para:</p>';
    echo '<ul>';
    echo '<li>✅ Ver conversas não atribuídas</li>';
    echo '<li>✅ Acessar o Kanban</li>';
    echo '<li>✅ Gerenciar suas conversas</li>';
    echo '</ul>';
    echo '<hr style="margin: 20px 0; border:none; border-top: 1px solid #22c55e;">';
    echo '<strong>📋 Se ainda não consegue ver as conversas não atribuídas:</strong>';
    echo '<ol>';
    echo '<li><strong>Faça LOGOUT</strong> do sistema</li>';
    echo '<li><strong>Faça LOGIN</strong> novamente</li>';
    echo '<li><strong>Limpe o cache do navegador</strong> (Ctrl+Shift+Delete)</li>';
    echo '<li>Acesse <code>/conversations</code></li>';
    echo '<li>Use o filtro de agentes e selecione "🔴 Não atribuídas"</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<div class="box error-box">';
    echo '<h3 class="error">❌ PERMISSÕES FALTANDO!</h3>';
    echo '<p style="margin: 15px 0;"><strong>Problemas encontrados:</strong></p>';
    echo '<ul>';
    if (!$temUnassigned) {
        echo '<li>❌ Falta permissão: <code>conversations.view.unassigned</code></li>';
    }
    if (!$temFunnels) {
        echo '<li>❌ Falta permissão: <code>funnels.view</code></li>';
    }
    echo '</ul>';
    echo '<hr style="margin: 20px 0; border:none; border-top: 1px solid #ef4444;">';
    echo '<p><strong>💡 Solução:</strong> Execute o script de correção para adicionar as permissões faltantes.</p>';
    echo '</div>';
}

// AÇÕES
echo '<div class="actions">';
if (!$temUnassigned || !$temFunnels) {
    echo '<a href="fix-permissions.php" class="btn btn-success" target="_blank">🔧 Corrigir Permissões Agora</a>';
}
echo '<a href="?" class="btn">🔄 Verificar Outro Usuário</a>';
echo '<a href="/conversations" class="btn">💬 Ir para Conversas</a>';
echo '</div>';

?>

    </div>
</body>
</html>

