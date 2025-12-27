<?php
/**
 * Script para corrigir permissões dos agentes
 * Acesse via: http://localhost/fix-permissions.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

$db = \App\Helpers\Database::getInstance();

echo "<h1>🔧 Corrigindo Permissões dos Agentes</h1>";
echo "<pre>";

// 1. Obter ID da role 'agent'
$agentRole = $db->query("SELECT id FROM roles WHERE slug = 'agent' LIMIT 1")->fetch();
if (!$agentRole) {
    echo "❌ Role 'agent' não encontrada!\n";
    exit;
}
$agentRoleId = $agentRole['id'];
echo "✅ Role 'agent' encontrada (ID: {$agentRoleId})\n\n";

// 2. Obter ID da role 'agent-senior'
$seniorRole = $db->query("SELECT id FROM roles WHERE slug = 'agent-senior' LIMIT 1")->fetch();
$seniorRoleId = $seniorRole ? $seniorRole['id'] : null;
if ($seniorRoleId) {
    echo "✅ Role 'agent-senior' encontrada (ID: {$seniorRoleId})\n";
}

// 3. Obter ID da role 'agent-junior'
$juniorRole = $db->query("SELECT id FROM roles WHERE slug = 'agent-junior' LIMIT 1")->fetch();
$juniorRoleId = $juniorRole ? $juniorRole['id'] : null;
if ($juniorRoleId) {
    echo "✅ Role 'agent-junior' encontrada (ID: {$juniorRoleId})\n\n";
}

// 4. Permissões que devem ser adicionadas aos agentes
$permissionsToAdd = [
    'conversations.view.unassigned',  // Ver conversas não atribuídas
    'conversations.view.own',         // Ver próprias conversas
    'conversations.edit.own',         // Editar próprias conversas
    'messages.send.own',              // Enviar mensagens
    'funnels.view',                   // Ver funis (para Kanban)
];

echo "📋 Permissões a serem adicionadas:\n";
foreach ($permissionsToAdd as $perm) {
    echo "   - {$perm}\n";
}
echo "\n";

// 5. Adicionar cada permissão
$added = 0;
$alreadyExists = 0;

foreach ($permissionsToAdd as $permSlug) {
    echo "🔍 Processando '{$permSlug}'...\n";
    
    // Buscar ID da permissão
    $perm = $db->query("SELECT id FROM permissions WHERE slug = '{$permSlug}' LIMIT 1")->fetch();
    if (!$perm) {
        echo "   ⚠️  Permissão não encontrada no banco\n\n";
        continue;
    }
    $permId = $perm['id'];
    
    // Adicionar para role 'agent'
    try {
        // Verificar se já existe
        $exists = $db->query("SELECT COUNT(*) as count FROM role_permissions WHERE role_id = {$agentRoleId} AND permission_id = {$permId}")->fetch();
        
        if ($exists['count'] > 0) {
            echo "   ℹ️  Já existe para 'agent'\n";
            $alreadyExists++;
        } else {
            $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$agentRoleId, $permId]);
            echo "   ✅ Adicionada à role 'agent'\n";
            $added++;
        }
    } catch (\Exception $e) {
        echo "   ❌ Erro ao adicionar à role 'agent': " . $e->getMessage() . "\n";
    }
    
    // Adicionar para role 'agent-senior' se existir
    if ($seniorRoleId) {
        try {
            $exists = $db->query("SELECT COUNT(*) as count FROM role_permissions WHERE role_id = {$seniorRoleId} AND permission_id = {$permId}")->fetch();
            
            if ($exists['count'] == 0) {
                $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$seniorRoleId, $permId]);
                echo "   ✅ Adicionada à role 'agent-senior'\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Erro ao adicionar à role 'agent-senior': " . $e->getMessage() . "\n";
        }
    }
    
    // Adicionar para role 'agent-junior' se existir
    if ($juniorRoleId) {
        try {
            $exists = $db->query("SELECT COUNT(*) as count FROM role_permissions WHERE role_id = {$juniorRoleId} AND permission_id = {$permId}")->fetch();
            
            if ($exists['count'] == 0) {
                $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$juniorRoleId, $permId]);
                echo "   ✅ Adicionada à role 'agent-junior'\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Erro ao adicionar à role 'agent-junior': " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "✅ Permissões corrigidas com sucesso!\n";
echo "   - Novas permissões adicionadas: {$added}\n";
echo "   - Permissões que já existiam: {$alreadyExists}\n\n";

// 6. Limpar cache de permissões de todos os usuários
echo "🧹 Limpando cache de permissões...\n";
$cacheDir = __DIR__ . '/../storage/cache/permissions/';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*');
    $cleared = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
            $cleared++;
        }
    }
    echo "✅ {$cleared} arquivos de cache removidos\n\n";
} else {
    echo "⚠️  Diretório de cache não existe\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "🎉 CONCLUÍDO!\n\n";
echo "Agentes agora podem:\n";
echo "   ✅ Ver conversas não atribuídas\n";
echo "   ✅ Ver e mover conversas no Kanban\n";
echo "   ✅ Ver todas as etapas e funis\n\n";
echo "Você pode fechar esta página.\n";

echo "</pre>";

