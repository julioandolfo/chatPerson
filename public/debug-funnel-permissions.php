<?php
/**
 * Script de DEBUG - Permissões de Funil
 * Acesse: /debug-funnel-permissions.php?user_id=SEU_ID
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar configurações
$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Iniciar sessão
session_start();

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    die('❌ Você precisa estar logado para acessar este debug');
}

$userId = $_SESSION['user_id'];
echo "<h2>🔍 DEBUG - Permissões de Funil</h2>";
echo "<p><strong>Usuário ID:</strong> {$userId}</p>";
echo "<hr>";

// 1. Verificar dados do usuário
$user = \App\Models\User::find($userId);
if (!$user) {
    die("❌ Usuário não encontrado");
}

echo "<h3>👤 Dados do Usuário</h3>";
echo "<ul>";
echo "<li><strong>Nome:</strong> {$user['name']}</li>";
echo "<li><strong>Email:</strong> {$user['email']}</li>";
echo "<li><strong>Role:</strong> {$user['role']}</li>";
echo "</ul>";

// 2. Verificar se é admin
$isAdmin = \App\Services\PermissionService::isAdmin($userId);
$isSuperAdmin = \App\Services\PermissionService::isSuperAdmin($userId);
echo "<h3>🔐 Tipo de Usuário</h3>";
echo "<ul>";
echo "<li><strong>É Admin:</strong> " . ($isAdmin ? '✅ SIM' : '❌ NÃO') . "</li>";
echo "<li><strong>É Super Admin:</strong> " . ($isSuperAdmin ? '✅ SIM' : '❌ NÃO') . "</li>";
echo "</ul>";

if ($isAdmin || $isSuperAdmin) {
    echo "<p><strong>⚠️ ATENÇÃO:</strong> Admin/Super Admin tem acesso a TODAS as conversas (bypass de permissões)</p>";
}

// 3. Listar permissões de funil
echo "<h3>📊 Permissões de Funil Configuradas</h3>";
$permissions = \App\Models\AgentFunnelPermission::getUserPermissions($userId);

if (empty($permissions)) {
    echo "<p>⚠️ <strong>Nenhuma permissão de funil configurada para este usuário!</strong></p>";
    if (!$isAdmin && !$isSuperAdmin) {
        echo "<p>❌ Este usuário NÃO verá conversas não atribuídas (exceto conversas sem funil)</p>";
    }
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Funil</th><th>Etapa</th><th>Tipo Permissão</th></tr>";
    foreach ($permissions as $perm) {
        echo "<tr>";
        echo "<td>{$perm['id']}</td>";
        echo "<td>" . ($perm['funnel_name'] ?? '<em>Todos os funis</em>') . " (ID: " . ($perm['funnel_id'] ?? 'NULL') . ")</td>";
        echo "<td>" . ($perm['stage_name'] ?? '<em>Todas as etapas</em>') . " (ID: " . ($perm['stage_id'] ?? 'NULL') . ")</td>";
        echo "<td>{$perm['permission_type']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. Funis permitidos (IDs)
echo "<h3>🎯 Funis Permitidos (IDs)</h3>";
$allowedFunnels = \App\Models\AgentFunnelPermission::getAllowedFunnelIds($userId);

if ($allowedFunnels === null) {
    echo "<p>✅ <strong>NULL = Acesso a TODOS os funis (Admin/Super Admin)</strong></p>";
} elseif (empty($allowedFunnels)) {
    echo "<p>❌ <strong>Array vazio = SEM acesso a nenhum funil</strong></p>";
} else {
    echo "<p>✅ Funis permitidos: <strong>" . implode(', ', $allowedFunnels) . "</strong></p>";
}

// 5. Etapas permitidas (IDs)
echo "<h3>🎯 Etapas Permitidas (IDs)</h3>";
$allowedStages = \App\Models\AgentFunnelPermission::getAllowedStageIds($userId);

if ($allowedStages === null) {
    echo "<p>✅ <strong>NULL = Acesso a TODAS as etapas (Admin/Super Admin)</strong></p>";
} elseif (empty($allowedStages)) {
    echo "<p>❌ <strong>Array vazio = SEM acesso a nenhuma etapa</strong></p>";
} else {
    echo "<p>✅ Etapas permitidas: <strong>" . implode(', ', $allowedStages) . "</strong></p>";
}

// 6. Testar algumas conversas
echo "<h3>🧪 Teste com Conversas Reais</h3>";
$sql = "SELECT id, contact_id, agent_id, funnel_id, funnel_stage_id, status, channel 
        FROM conversations 
        WHERE funnel_id IS NOT NULL 
        ORDER BY id DESC 
        LIMIT 10";
$testConversations = \App\Helpers\Database::fetchAll($sql);

if (empty($testConversations)) {
    echo "<p>⚠️ Nenhuma conversa com funil encontrada para teste</p>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Conv ID</th><th>Agent ID</th><th>Funil ID</th><th>Etapa ID</th><th>Status</th><th>Pode Ver?</th></tr>";
    
    foreach ($testConversations as $conv) {
        $canView = \App\Models\AgentFunnelPermission::canViewConversation($userId, $conv);
        $canViewFull = \App\Services\PermissionService::canViewConversation($userId, $conv);
        
        $statusIcon = $canViewFull ? '✅' : '❌';
        $bgColor = $canViewFull ? '#d4edda' : '#f8d7da';
        
        echo "<tr style='background-color: {$bgColor};'>";
        echo "<td>{$conv['id']}</td>";
        echo "<td>" . ($conv['agent_id'] ?? '<em>não atribuída</em>') . "</td>";
        echo "<td>{$conv['funnel_id']}</td>";
        echo "<td>" . ($conv['funnel_stage_id'] ?? '<em>sem etapa</em>') . "</td>";
        echo "<td>{$conv['status']}</td>";
        echo "<td>{$statusIcon} " . ($canViewFull ? 'SIM' : 'NÃO') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 7. Limpar cache
echo "<h3>🧹 Cache</h3>";
echo "<form method='post'>";
echo "<button type='submit' name='clear_cache' style='padding: 10px 20px; background: #dc3545; color: white; border: none; cursor: pointer;'>🗑️ Limpar Cache de Conversas</button>";
echo "</form>";

if (isset($_POST['clear_cache'])) {
    \App\Services\ConversationService::clearAllCache();
    echo "<p style='color: green;'>✅ <strong>Cache limpo com sucesso!</strong></p>";
    echo "<script>setTimeout(() => window.location.reload(), 2000);</script>";
}

echo "<hr>";
echo "<p><small>Para ver os logs detalhados, verifique: <code>logs/conversas.log</code></small></p>";
