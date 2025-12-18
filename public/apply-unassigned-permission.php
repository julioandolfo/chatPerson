<?php
/**
 * Script para adicionar permissão de ver conversas não atribuídas
 */

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<meta charset='UTF-8'>";
echo "<title>Adicionar Permissão: Conversas Não Atribuídas</title>";
echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
h1 { color: #333; }
.success { color: #22c55e; font-weight: bold; }
.error { color: #ef4444; font-weight: bold; }
.info { color: #3b82f6; }
.btn { display: inline-block; padding: 10px 20px; background: #009ef7; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
</style>";
echo "</head><body>";

echo "<h1>🔧 Adicionar Novas Permissões</h1>";
echo "<p>Este script adiciona novas permissões para agentes:</p>";
echo "<ul>";
echo "<li><code>conversations.view.unassigned</code> - Ver conversas não atribuídas</li>";
echo "<li><code>tags.view</code> - Ver tags</li>";
echo "<li><code>tags.assign</code> - Atribuir tags a conversas</li>";
echo "<li><code>tags.create</code> - Criar tags (Admin/Supervisor)</li>";
echo "<li><code>tags.edit</code> - Editar tags (Admin/Supervisor)</li>";
echo "<li><code>tags.delete</code> - Deletar tags (Admin/Supervisor)</li>";
echo "<li><code>message_templates.view</code> - Ver templates de mensagem</li>";
echo "<li><code>message_templates.create</code> - Criar templates (Admin/Supervisor)</li>";
echo "<li><code>message_templates.edit</code> - Editar templates (Admin/Supervisor)</li>";
echo "<li><code>message_templates.delete</code> - Deletar templates (Admin/Supervisor)</li>";
echo "</ul>";

echo "<hr>";

// Carregar configuração do banco
$dbConfig = require __DIR__ . '/../config/database.php';

// Conectar ao banco
try {
    $pdo = new PDO(
        "mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['database'] . ";charset=" . $dbConfig['charset'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    
    echo "<p class='success'>✅ Conectado ao banco de dados com sucesso!</p>";
} catch (PDOException $e) {
    echo "<p class='error'>❌ Erro ao conectar ao banco: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</body></html>";
    exit;
}

echo "<h2>📝 Passo 1: Criar Permissões</h2>";

$newPermissions = [
    ['name' => 'Ver conversas não atribuídas', 'slug' => 'conversations.view.unassigned', 'description' => 'Ver conversas sem atribuição (disponíveis para todos)', 'module' => 'conversations'],
    ['name' => 'Ver tags', 'slug' => 'tags.view', 'description' => 'Ver tags', 'module' => 'tags'],
    ['name' => 'Criar tags', 'slug' => 'tags.create', 'description' => 'Criar tags', 'module' => 'tags'],
    ['name' => 'Editar tags', 'slug' => 'tags.edit', 'description' => 'Editar tags', 'module' => 'tags'],
    ['name' => 'Deletar tags', 'slug' => 'tags.delete', 'description' => 'Deletar tags', 'module' => 'tags'],
    ['name' => 'Atribuir tags a conversas', 'slug' => 'tags.assign', 'description' => 'Atribuir/remover tags em conversas', 'module' => 'tags'],
    ['name' => 'Ver templates de mensagem', 'slug' => 'message_templates.view', 'description' => 'Ver templates de mensagem', 'module' => 'message_templates'],
    ['name' => 'Criar templates de mensagem', 'slug' => 'message_templates.create', 'description' => 'Criar templates de mensagem', 'module' => 'message_templates'],
    ['name' => 'Editar templates de mensagem', 'slug' => 'message_templates.edit', 'description' => 'Editar templates de mensagem', 'module' => 'message_templates'],
    ['name' => 'Deletar templates de mensagem', 'slug' => 'message_templates.delete', 'description' => 'Deletar templates de mensagem', 'module' => 'message_templates'],
];

$permissionIds = [];

foreach ($newPermissions as $perm) {
    // Verificar se permissão já existe
    $sql = "SELECT id FROM permissions WHERE slug = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$perm['slug']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "<p class='info'>ℹ️ {$perm['name']} já existe (ID: {$existing['id']})</p>";
        $permissionIds[$perm['slug']] = $existing['id'];
    } else {
        // Criar permissão
        $sql = "INSERT INTO permissions (name, slug, description, module) 
                VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$perm['name'], $perm['slug'], $perm['description'], $perm['module']]);
        $permissionIds[$perm['slug']] = $pdo->lastInsertId();
        echo "<p class='success'>✅ {$perm['name']} criada! (ID: {$permissionIds[$perm['slug']]})</p>";
    }
}

echo "<h2>📝 Passo 2: Atribuir para Roles</h2>";

// Definir quais permissões cada role deve ter
$rolePermissions = [
    'agent' => [
        'conversations.view.unassigned',
        'tags.view',
        'tags.assign',
        'message_templates.view'
    ],
    'agent-senior' => [
        'conversations.view.unassigned',
        'tags.view',
        'tags.assign',
        'tags.create',
        'message_templates.view',
        'message_templates.create'
    ],
    'supervisor' => [
        'conversations.view.unassigned',
        'tags.view',
        'tags.assign',
        'tags.create',
        'tags.edit',
        'message_templates.view',
        'message_templates.create',
        'message_templates.edit'
    ],
    'admin' => [
        'conversations.view.unassigned',
        'tags.view',
        'tags.assign',
        'tags.create',
        'tags.edit',
        'tags.delete',
        'message_templates.view',
        'message_templates.create',
        'message_templates.edit',
        'message_templates.delete'
    ]
];

foreach ($rolePermissions as $roleSlug => $permissions) {
    // Buscar role
    $sql = "SELECT id, name FROM roles WHERE slug = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roleSlug]);
    $role = $stmt->fetch();
    
    if (!$role) {
        echo "<p class='info'>ℹ️ Role '{$roleSlug}' não encontrada (pulando)</p>";
        continue;
    }
    
    echo "<h3 style='margin-top: 15px;'>Role: {$role['name']}</h3>";
    
    foreach ($permissions as $permSlug) {
        if (!isset($permissionIds[$permSlug])) {
            continue;
        }
        
        // Verificar se já tem a permissão
        $sql = "SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$role['id'], $permissionIds[$permSlug]]);
        $hasPermission = $stmt->fetch();
        
        if ($hasPermission) {
            echo "<p class='info' style='margin-left: 20px;'>ℹ️ {$permSlug} - já tem</p>";
        } else {
            // Atribuir permissão
            $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$role['id'], $permissionIds[$permSlug]]);
            echo "<p class='success' style='margin-left: 20px;'>✅ {$permSlug} - atribuída!</p>";
        }
    }
}

echo "<h2>📝 Passo 3: Limpar Cache</h2>";

// Limpar cache de permissões
$cacheDir = __DIR__ . '/../storage/cache/permissions/';
$count = 0;

if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
            $count++;
        }
    }
    echo "<p class='success'>✅ Cache de permissões limpo! ($count arquivos removidos)</p>";
} else {
    echo "<p class='info'>ℹ️ Diretório de cache não existe</p>";
}

// Limpar cache de conversas
$cacheDir2 = __DIR__ . '/../storage/cache/conversations/';
$count2 = 0;

if (is_dir($cacheDir2)) {
    $files2 = glob($cacheDir2 . '*');
    foreach ($files2 as $file) {
        if (is_file($file)) {
            @unlink($file);
            $count2++;
        }
    }
    echo "<p class='success'>✅ Cache de conversas limpo! ($count2 arquivos removidos)</p>";
}

echo "<hr>";

echo "<h2>✅ Concluído!</h2>";

echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 4px; border-left: 4px solid #22c55e;'>";
echo "<h3 style='margin-top: 0;'>🎯 O Que Mudou?</h3>";
echo "<ul>";
echo "<li><strong>Conversas Não Atribuídas:</strong> Agora são visíveis para TODOS os agentes</li>";
echo "<li><strong>Primeiro a Responder:</strong> Quando um agente responde, a conversa é automaticamente atribuída a ele</li>";
echo "<li><strong>Kanban:</strong> Agentes veem apenas suas conversas + não atribuídas em cada funil</li>";
echo "<li><strong>Lista:</strong> Mesma regra aplicada na lista de conversas</li>";
echo "<li><strong>Tags:</strong> Agentes podem ver e atribuir tags; Supervisores+ podem criar/editar</li>";
echo "<li><strong>Templates:</strong> Agentes podem ver templates; Sênior+ podem criar; Supervisor+ podem editar</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #fff3e0; padding: 15px; border-radius: 4px; border-left: 4px solid #ff9800; margin-top: 20px;'>";
echo "<h3 style='margin-top: 0;'>⚠️ Ação Necessária</h3>";
echo "<p><strong>Peça para TODOS os usuários fazerem logout e login novamente</strong> para aplicar as novas permissões!</p>";
echo "</div>";

echo "<p><a href='/conversations' class='btn'>Ir para Conversas</a></p>";
echo "<p><a href='/clear-permissions-cache.php' class='btn' style='background: #6c757d;'>Ver Status das Permissões</a></p>";

echo "</body></html>";

