<?php
/**
 * Script para adicionar APENAS as permissões da Análise de Conversas por Coorte
 * Pode ser executado sem afetar outras permissões.
 *
 * USO: php database/seeds/add_conversation_insights_permissions.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\Database;

echo "🚀 Adicionando permissões da Análise de Conversas...\n\n";

try {
    $db = Database::getInstance();

    $permissions = [
        [
            'name' => 'Ver análises de conversas',
            'slug' => 'conversation_insights.view',
            'description' => 'Visualizar análises de conversas por coorte',
            'module' => 'conversation_insights'
        ],
        [
            'name' => 'Executar análise de conversas',
            'slug' => 'conversation_insights.run',
            'description' => 'Criar e cancelar análises de conversas (consome créditos de IA)',
            'module' => 'conversation_insights'
        ],
    ];

    $permissionIds = [];

    foreach ($permissions as $perm) {
        $stmt = $db->prepare("SELECT id FROM permissions WHERE slug = ?");
        $stmt->execute([$perm['slug']]);
        $existing = $stmt->fetch();

        if ($existing) {
            echo "⚠️  Permissão '{$perm['name']}' já existe (ID: {$existing['id']})\n";
            $permissionIds[$perm['slug']] = $existing['id'];
            continue;
        }

        $stmt = $db->prepare("INSERT INTO permissions (name, slug, description, module) VALUES (?, ?, ?, ?)");
        $stmt->execute([$perm['name'], $perm['slug'], $perm['description'], $perm['module']]);

        $permId = $db->lastInsertId();
        $permissionIds[$perm['slug']] = $permId;

        echo "✅ Permissão '{$perm['name']}' criada (ID: {$permId})\n";
    }

    // Atribuir aos papéis que fazem sentido: super-admin e admin recebem tudo;
    // supervisor/manager recebe se existir no sistema.
    $roleSlugs = ['super-admin', 'admin', 'supervisor', 'manager', 'gerente'];

    $placeholders = implode(',', array_fill(0, count($roleSlugs), '?'));
    $stmt = $db->prepare("SELECT id, slug FROM roles WHERE slug IN ({$placeholders})");
    $stmt->execute($roleSlugs);
    $roles = $stmt->fetchAll();

    if (empty($roles)) {
        echo "\n⚠️  Nenhum papel encontrado para vincular. Vincule manualmente em /roles.\n";
    }

    foreach ($roles as $role) {
        foreach ($permissionIds as $slug => $permId) {
            $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmt->execute([$role['id'], $permId]);
        }

        echo "✅ Permissões vinculadas ao papel '{$role['slug']}'\n";
    }

    echo "\n✅ Concluído!\n";
} catch (\Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
