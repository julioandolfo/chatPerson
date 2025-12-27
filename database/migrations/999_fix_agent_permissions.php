<?php
/**
 * Migration: Adicionar permissões faltantes aos agentes
 * - conversations.view.unassigned (ver conversas não atribuídas)
 * - Permissões de Kanban
 */

function up_fix_agent_permissions() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🚀 Corrigindo permissões dos agentes...\n";
    
    // 1. Obter ID da role 'agent'
    $agentRole = $db->query("SELECT id FROM roles WHERE slug = 'agent' LIMIT 1")->fetch();
    if (!$agentRole) {
        echo "❌ Role 'agent' não encontrada!\n";
        return;
    }
    $agentRoleId = $agentRole['id'];
    echo "✅ Role 'agent' encontrada (ID: {$agentRoleId})\n";
    
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
        echo "✅ Role 'agent-junior' encontrada (ID: {$juniorRoleId})\n";
    }
    
    // 4. Permissões que devem ser adicionadas aos agentes
    $permissionsToAdd = [
        'conversations.view.unassigned',  // Ver conversas não atribuídas
        'conversations.view.own',         // Ver próprias conversas
        'conversations.edit.own',         // Editar próprias conversas
        'messages.send.own',              // Enviar mensagens
        'funnels.view',                   // Ver funis (para Kanban)
    ];
    
    // 5. Adicionar cada permissão
    $added = 0;
    foreach ($permissionsToAdd as $permSlug) {
        // Buscar ID da permissão
        $perm = $db->query("SELECT id FROM permissions WHERE slug = '{$permSlug}' LIMIT 1")->fetch();
        if (!$perm) {
            echo "⚠️  Permissão '{$permSlug}' não encontrada no banco\n";
            continue;
        }
        $permId = $perm['id'];
        
        // Adicionar para role 'agent'
        try {
            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$agentRoleId, $permId]);
            
            if ($stmt->rowCount() > 0) {
                echo "  ✅ Permissão '{$permSlug}' adicionada à role 'agent'\n";
                $added++;
            }
        } catch (\Exception $e) {
            echo "  ❌ Erro ao adicionar '{$permSlug}' à role 'agent': " . $e->getMessage() . "\n";
        }
        
        // Adicionar para role 'agent-senior' se existir
        if ($seniorRoleId) {
            try {
                $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$seniorRoleId, $permId]);
                
                if ($stmt->rowCount() > 0) {
                    echo "  ✅ Permissão '{$permSlug}' adicionada à role 'agent-senior'\n";
                }
            } catch (\Exception $e) {
                echo "  ❌ Erro ao adicionar '{$permSlug}' à role 'agent-senior': " . $e->getMessage() . "\n";
            }
        }
        
        // Adicionar para role 'agent-junior' se existir
        if ($juniorRoleId) {
            try {
                $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$juniorRoleId, $permId]);
                
                if ($stmt->rowCount() > 0) {
                    echo "  ✅ Permissão '{$permSlug}' adicionada à role 'agent-junior'\n";
                }
            } catch (\Exception $e) {
                echo "  ❌ Erro ao adicionar '{$permSlug}' à role 'agent-junior': " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n";
    echo "✅ Permissões corrigidas com sucesso!\n";
    echo "   Total de permissões adicionadas: {$added}\n";
    
    // 6. Limpar cache de permissões de todos os usuários
    echo "\n🧹 Limpando cache de permissões...\n";
    $cacheDir = __DIR__ . '/../../storage/cache/permissions/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*');
        $cleared = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
                $cleared++;
            }
        }
        echo "✅ {$cleared} arquivos de cache removidos\n";
    } else {
        echo "⚠️  Diretório de cache não existe\n";
    }
    
    echo "\n";
    echo "🎉 Concluído! Agentes agora podem ver conversas não atribuídas e acessar o Kanban.\n";
}

function down_fix_agent_permissions() {
    // Não é necessário reverter
    echo "⚠️  Esta migration não pode ser revertida.\n";
}

