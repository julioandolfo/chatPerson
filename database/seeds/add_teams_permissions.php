<?php
/**
 * Script para adicionar APENAS as permissões de Times
 * Pode ser executado sem afetar outras permissões
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use App\Helpers\Database;

echo "🚀 Adicionando permissões de Times...\n\n";

try {
    $db = Database::getInstance();
    
    // Permissões de Times
    $permissions = [
        ['name' => 'Ver times', 'slug' => 'teams.view', 'description' => 'Ver times/equipes', 'module' => 'teams'],
        ['name' => 'Criar times', 'slug' => 'teams.create', 'description' => 'Criar times/equipes', 'module' => 'teams'],
        ['name' => 'Editar times', 'slug' => 'teams.edit', 'description' => 'Editar times/equipes', 'module' => 'teams'],
        ['name' => 'Deletar times', 'slug' => 'teams.delete', 'description' => 'Deletar times/equipes', 'module' => 'teams'],
        ['name' => 'Gerenciar membros de times', 'slug' => 'teams.manage_members', 'description' => 'Adicionar/remover membros de times', 'module' => 'teams'],
    ];
    
    $permissionIds = [];
    
    foreach ($permissions as $perm) {
        // Verificar se já existe
        $existing = $db->query(
            "SELECT id FROM permissions WHERE slug = '{$perm['slug']}'"
        )->fetch();
        
        if ($existing) {
            echo "⚠️  Permissão '{$perm['name']}' já existe (ID: {$existing['id']})\n";
            $permissionIds[$perm['slug']] = $existing['id'];
            continue;
        }
        
        // Inserir nova permissão
        $sql = "INSERT INTO permissions (name, slug, description, module) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$perm['name'], $perm['slug'], $perm['description'], $perm['module']]);
        $permId = $db->lastInsertId();
        $permissionIds[$perm['slug']] = $permId;
        
        echo "✅ Permissão '{$perm['name']}' criada (ID: {$permId})\n";
    }
    
    // Atribuir permissões aos roles
    echo "\n🔐 Atribuindo permissões aos roles...\n\n";
    
    // Buscar roles
    $roles = [
        'super-admin' => $db->query("SELECT id FROM roles WHERE slug = 'super-admin'")->fetch(),
        'admin' => $db->query("SELECT id FROM roles WHERE slug = 'admin'")->fetch(),
    ];
    
    // Super Admin e Admin têm todas as permissões de times
    foreach ($roles as $roleSlug => $role) {
        if (!$role) {
            echo "⚠️  Role '{$roleSlug}' não encontrada\n";
            continue;
        }
        
        foreach ($permissionIds as $slug => $permId) {
            // Verificar se já existe
            $existing = $db->query(
                "SELECT * FROM role_permissions WHERE role_id = {$role['id']} AND permission_id = {$permId}"
            )->fetch();
            
            if ($existing) {
                echo "⚠️  Permissão '{$slug}' já atribuída ao role '{$roleSlug}'\n";
                continue;
            }
            
            // Inserir
            $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$role['id'], $permId]);
            
            echo "✅ Permissão '{$slug}' atribuída ao role '{$roleSlug}'\n";
        }
    }
    
    echo "\n✅ Permissões de Times adicionadas com sucesso!\n";
    echo "\n📊 Resumo:\n";
    echo "   - " . count($permissions) . " permissões criadas/verificadas\n";
    echo "   - Atribuídas aos roles: Super Admin, Admin\n";
    echo "\n🎉 Pronto! Você já pode acessar /teams\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
