<?php
/**
 * Seed: Criar roles e permissões básicas
 */

function seed_roles_and_permissions() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🚀 Criando roles e permissões...\n";
    
    // Criar roles
    $roles = [
        ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Acesso total ao sistema', 'level' => 0, 'is_system' => true],
        ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrador do sistema', 'level' => 1, 'is_system' => true],
        ['name' => 'Supervisor', 'slug' => 'supervisor', 'description' => 'Supervisor de equipe', 'level' => 2, 'is_system' => false],
        ['name' => 'Agente Sênior', 'slug' => 'agent-senior', 'description' => 'Agente com acesso amplo', 'level' => 3, 'is_system' => false],
        ['name' => 'Agente', 'slug' => 'agent', 'description' => 'Agente padrão', 'level' => 4, 'is_system' => false],
        ['name' => 'Agente Júnior', 'slug' => 'agent-junior', 'description' => 'Agente com acesso limitado', 'level' => 5, 'is_system' => false],
        ['name' => 'Visualizador', 'slug' => 'viewer', 'description' => 'Apenas visualização', 'level' => 6, 'is_system' => false],
    ];
    
    $roleIds = [];
    foreach ($roles as $role) {
        $sql = "INSERT INTO roles (name, slug, description, level, is_system) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), level = VALUES(level)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$role['name'], $role['slug'], $role['description'], $role['level'], $role['is_system'] ? 1 : 0]);
        $roleIds[$role['slug']] = $db->lastInsertId() ?: $db->query("SELECT id FROM roles WHERE slug = '{$role['slug']}'")->fetch()['id'];
        echo "✅ Role '{$role['name']}' criada/atualizada\n";
    }
    
    // Criar permissões básicas
    $permissions = [
        // Conversas - Visualização
        ['name' => 'Ver próprias conversas', 'slug' => 'conversations.view.own', 'description' => 'Ver apenas conversas próprias', 'module' => 'conversations'],
        ['name' => 'Ver conversas atribuídas', 'slug' => 'conversations.view.assigned', 'description' => 'Ver conversas atribuídas', 'module' => 'conversations'],
        ['name' => 'Ver conversas não atribuídas', 'slug' => 'conversations.view.unassigned', 'description' => 'Ver conversas sem atribuição (disponíveis para todos)', 'module' => 'conversations'],
        ['name' => 'Ver conversas do setor', 'slug' => 'conversations.view.department', 'description' => 'Ver conversas do setor', 'module' => 'conversations'],
        ['name' => 'Ver todas as conversas', 'slug' => 'conversations.view.all', 'description' => 'Ver todas as conversas', 'module' => 'conversations'],
        
        // Conversas - Edição
        ['name' => 'Editar próprias conversas', 'slug' => 'conversations.edit.own', 'description' => 'Editar apenas conversas próprias', 'module' => 'conversations'],
        ['name' => 'Editar conversas do setor', 'slug' => 'conversations.edit.department', 'description' => 'Editar conversas do setor', 'module' => 'conversations'],
        ['name' => 'Editar todas as conversas', 'slug' => 'conversations.edit.all', 'description' => 'Editar todas as conversas', 'module' => 'conversations'],
        ['name' => 'Atribuir conversas', 'slug' => 'conversations.edit.assign', 'description' => 'Atribuir conversas a agentes', 'module' => 'conversations'],
        ['name' => 'Deletar conversas', 'slug' => 'conversations.delete', 'description' => 'Deletar conversas', 'module' => 'conversations'],
        
        // Mensagens
        ['name' => 'Enviar mensagens em próprias conversas', 'slug' => 'messages.send.own', 'description' => 'Enviar mensagens em conversas próprias', 'module' => 'messages'],
        ['name' => 'Enviar mensagens no setor', 'slug' => 'messages.send.department', 'description' => 'Enviar mensagens em conversas do setor', 'module' => 'messages'],
        ['name' => 'Enviar mensagens em qualquer conversa', 'slug' => 'messages.send.all', 'description' => 'Enviar mensagens em qualquer conversa', 'module' => 'messages'],
        
        // Contatos
        ['name' => 'Ver contatos', 'slug' => 'contacts.view', 'description' => 'Ver contatos', 'module' => 'contacts'],
        ['name' => 'Criar contatos', 'slug' => 'contacts.create', 'description' => 'Criar contatos', 'module' => 'contacts'],
        ['name' => 'Editar contatos', 'slug' => 'contacts.edit', 'description' => 'Editar contatos', 'module' => 'contacts'],
        ['name' => 'Deletar contatos', 'slug' => 'contacts.delete', 'description' => 'Deletar contatos', 'module' => 'contacts'],
        
        // Agentes
        ['name' => 'Ver agentes', 'slug' => 'agents.view', 'description' => 'Ver agentes', 'module' => 'agents'],
        ['name' => 'Criar agentes', 'slug' => 'agents.create', 'description' => 'Criar agentes', 'module' => 'agents'],
        ['name' => 'Editar agentes', 'slug' => 'agents.edit', 'description' => 'Editar agentes', 'module' => 'agents'],
        ['name' => 'Deletar agentes', 'slug' => 'agents.delete', 'description' => 'Deletar agentes', 'module' => 'agents'],
        
        // Usuários
        ['name' => 'Ver usuários', 'slug' => 'users.view', 'description' => 'Ver usuários', 'module' => 'users'],
        ['name' => 'Criar usuários', 'slug' => 'users.create', 'description' => 'Criar usuários', 'module' => 'users'],
        ['name' => 'Editar usuários', 'slug' => 'users.edit', 'description' => 'Editar usuários', 'module' => 'users'],
        ['name' => 'Deletar usuários', 'slug' => 'users.delete', 'description' => 'Deletar usuários', 'module' => 'users'],
        
        // Funis
        ['name' => 'Ver funis', 'slug' => 'funnels.view', 'description' => 'Ver funis', 'module' => 'funnels'],
        ['name' => 'Criar funis', 'slug' => 'funnels.create', 'description' => 'Criar funis', 'module' => 'funnels'],
        ['name' => 'Editar funis', 'slug' => 'funnels.edit', 'description' => 'Editar funis', 'module' => 'funnels'],
        
        // Automações
        ['name' => 'Ver automações', 'slug' => 'automations.view', 'description' => 'Ver automações', 'module' => 'automations'],
        ['name' => 'Criar automações', 'slug' => 'automations.create', 'description' => 'Criar automações', 'module' => 'automations'],
        ['name' => 'Editar automações', 'slug' => 'automations.edit', 'description' => 'Editar automações', 'module' => 'automations'],
        
        // Roles e Permissões
        ['name' => 'Ver roles', 'slug' => 'roles.view', 'description' => 'Ver roles', 'module' => 'roles'],
        ['name' => 'Criar roles', 'slug' => 'roles.create', 'description' => 'Criar roles', 'module' => 'roles'],
        ['name' => 'Editar roles', 'slug' => 'roles.edit', 'description' => 'Editar roles', 'module' => 'roles'],
        
        // Setores
        ['name' => 'Ver setores', 'slug' => 'departments.view', 'description' => 'Ver setores', 'module' => 'departments'],
        ['name' => 'Criar setores', 'slug' => 'departments.create', 'description' => 'Criar setores', 'module' => 'departments'],
        ['name' => 'Editar setores', 'slug' => 'departments.edit', 'description' => 'Editar setores', 'module' => 'departments'],
        ['name' => 'Atribuir agentes a setores', 'slug' => 'departments.assign_agents', 'description' => 'Atribuir agentes a setores', 'module' => 'departments'],
        
        // Agentes de IA
        ['name' => 'Ver agentes de IA', 'slug' => 'ai_agents.view', 'description' => 'Ver agentes de IA', 'module' => 'ai_agents'],
        ['name' => 'Criar agentes de IA', 'slug' => 'ai_agents.create', 'description' => 'Criar agentes de IA', 'module' => 'ai_agents'],
        ['name' => 'Editar agentes de IA', 'slug' => 'ai_agents.edit', 'description' => 'Editar agentes de IA', 'module' => 'ai_agents'],
        ['name' => 'Deletar agentes de IA', 'slug' => 'ai_agents.delete', 'description' => 'Deletar agentes de IA', 'module' => 'ai_agents'],
        
        // Tags
        ['name' => 'Ver tags', 'slug' => 'tags.view', 'description' => 'Ver tags', 'module' => 'tags'],
        ['name' => 'Criar tags', 'slug' => 'tags.create', 'description' => 'Criar tags', 'module' => 'tags'],
        ['name' => 'Editar tags', 'slug' => 'tags.edit', 'description' => 'Editar tags', 'module' => 'tags'],
        ['name' => 'Deletar tags', 'slug' => 'tags.delete', 'description' => 'Deletar tags', 'module' => 'tags'],
        ['name' => 'Atribuir tags a conversas', 'slug' => 'tags.assign', 'description' => 'Atribuir/remover tags em conversas', 'module' => 'tags'],
        
        // Templates de Mensagem
        ['name' => 'Ver templates de mensagem', 'slug' => 'message_templates.view', 'description' => 'Ver templates de mensagem', 'module' => 'message_templates'],
        ['name' => 'Criar templates de mensagem', 'slug' => 'message_templates.create', 'description' => 'Criar templates de mensagem', 'module' => 'message_templates'],
        ['name' => 'Editar templates de mensagem', 'slug' => 'message_templates.edit', 'description' => 'Editar templates de mensagem', 'module' => 'message_templates'],
        ['name' => 'Deletar templates de mensagem', 'slug' => 'message_templates.delete', 'description' => 'Deletar templates de mensagem', 'module' => 'message_templates'],
        
        // Tools de IA
        ['name' => 'Ver tools de IA', 'slug' => 'ai_tools.view', 'description' => 'Ver tools de IA', 'module' => 'ai_tools'],
        
        // Assistente IA (Chat)
        ['name' => 'Usar Assistente IA', 'slug' => 'ai_assistant.use', 'description' => 'Usar funcionalidades do Assistente IA no chat', 'module' => 'ai_assistant'],
        ['name' => 'Configurar Assistente IA', 'slug' => 'ai_assistant.configure', 'description' => 'Configurar funcionalidades e regras do Assistente IA', 'module' => 'ai_assistant'],
        ['name' => 'Ver logs do Assistente IA', 'slug' => 'ai_assistant.view_logs', 'description' => 'Ver logs e estatísticas do Assistente IA', 'module' => 'ai_assistant'],
        ['name' => 'Criar tools de IA', 'slug' => 'ai_tools.create', 'description' => 'Criar tools de IA', 'module' => 'ai_tools'],
        ['name' => 'Editar tools de IA', 'slug' => 'ai_tools.edit', 'description' => 'Editar tools de IA', 'module' => 'ai_tools'],
        ['name' => 'Deletar tools de IA', 'slug' => 'ai_tools.delete', 'description' => 'Deletar tools de IA', 'module' => 'ai_tools'],
        
        // Chamadas de Voz (WavoIP)
        ['name' => 'Ver chamadas de voz', 'slug' => 'voice_calls.view', 'description' => 'Ver chamadas de voz', 'module' => 'voice_calls'],
        ['name' => 'Criar chamadas de voz', 'slug' => 'voice_calls.create', 'description' => 'Iniciar chamadas de voz', 'module' => 'voice_calls'],
        ['name' => 'Encerrar chamadas de voz', 'slug' => 'voice_calls.end', 'description' => 'Encerrar chamadas de voz', 'module' => 'voice_calls'],
        
        // WhatsApp
        ['name' => 'Ver contas WhatsApp', 'slug' => 'whatsapp.view', 'description' => 'Ver contas WhatsApp', 'module' => 'whatsapp'],
        ['name' => 'Criar contas WhatsApp', 'slug' => 'whatsapp.create', 'description' => 'Criar contas WhatsApp', 'module' => 'whatsapp'],
        ['name' => 'Editar contas WhatsApp', 'slug' => 'whatsapp.edit', 'description' => 'Editar contas WhatsApp', 'module' => 'whatsapp'],
        ['name' => 'Deletar contas WhatsApp', 'slug' => 'whatsapp.delete', 'description' => 'Deletar contas WhatsApp', 'module' => 'whatsapp'],
        
        // Api4Com
        ['name' => 'Ver contas Api4Com', 'slug' => 'api4com.view', 'description' => 'Ver contas Api4Com', 'module' => 'api4com'],
        ['name' => 'Criar contas Api4Com', 'slug' => 'api4com.create', 'description' => 'Criar contas Api4Com', 'module' => 'api4com'],
        ['name' => 'Editar contas Api4Com', 'slug' => 'api4com.edit', 'description' => 'Editar contas Api4Com', 'module' => 'api4com'],
        ['name' => 'Deletar contas Api4Com', 'slug' => 'api4com.delete', 'description' => 'Deletar contas Api4Com', 'module' => 'api4com'],
        ['name' => 'Ver chamadas Api4Com', 'slug' => 'api4com_calls.view', 'description' => 'Ver chamadas Api4Com', 'module' => 'api4com_calls'],
        ['name' => 'Criar chamadas Api4Com', 'slug' => 'api4com_calls.create', 'description' => 'Iniciar chamadas Api4Com', 'module' => 'api4com_calls'],
        ['name' => 'Encerrar chamadas Api4Com', 'slug' => 'api4com_calls.end', 'description' => 'Encerrar chamadas Api4Com', 'module' => 'api4com_calls'],
        
        // Integrações
        ['name' => 'Ver integrações', 'slug' => 'integrations.view', 'description' => 'Ver integrações', 'module' => 'integrations'],
        
        // Administração
        ['name' => 'Ver logs do sistema', 'slug' => 'admin.logs', 'description' => 'Visualizar logs do sistema', 'module' => 'admin'],
    ];
    
    $permissionIds = [];
    foreach ($permissions as $permission) {
        $sql = "INSERT INTO permissions (name, slug, description, module) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), module = VALUES(module)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$permission['name'], $permission['slug'], $permission['description'], $permission['module']]);
        $permissionIds[$permission['slug']] = $db->lastInsertId() ?: $db->query("SELECT id FROM permissions WHERE slug = '{$permission['slug']}'")->fetch()['id'];
        echo "✅ Permissão '{$permission['name']}' criada/atualizada\n";
    }
    
    // Atribuir permissões às roles
    // Super Admin - todas as permissões
    foreach ($permissionIds as $slug => $permId) {
        $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$roleIds['super-admin'], $permId]);
    }
    
    // Admin - quase todas as permissões
    $adminPermissions = array_filter($permissionIds, function($slug) {
        return true; // Admin tem quase todas
    }, ARRAY_FILTER_USE_KEY);
    foreach ($adminPermissions as $slug => $permId) {
        $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$roleIds['admin'], $permId]);
    }
    
    // Agente - permissões básicas
    $agentPermissions = [
        'conversations.view.own',
        'conversations.view.unassigned',  // ✅ NOVO: Ver conversas não atribuídas
        'conversations.edit.own',
        'messages.send.own',
        'contacts.view',
        'contacts.create',
        'contacts.edit',
        'tags.view',           // ✅ Ver tags
        'tags.assign',         // ✅ Atribuir tags a conversas
        'message_templates.view', // ✅ Ver templates
        'funnels.view',        // Ver funis
        'automations.view',    // Ver automações
        'ai_assistant.use',    // Agentes podem usar o Assistente IA
        'voice_calls.view',    // Ver chamadas de voz
        'voice_calls.create',  // Criar chamadas de voz
        'voice_calls.end',     // Encerrar chamadas de voz
        'api4com_calls.view',  // Ver chamadas Api4Com
        'api4com_calls.create', // Criar chamadas Api4Com
        'api4com_calls.end',   // Encerrar chamadas Api4Com
    ];
    foreach ($agentPermissions as $slug) {
        if (isset($permissionIds[$slug])) {
            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$roleIds['agent'], $permissionIds[$slug]]);
        }
    }
    
    // Atribuir role super-admin ao usuário admin padrão
    $adminUser = $db->query("SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1")->fetch();
    if ($adminUser) {
        $sql = "INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$adminUser['id'], $roleIds['super-admin']]);
        echo "✅ Role 'Super Admin' atribuída ao usuário admin\n";
    }
    
    echo "✅ Roles e permissões criadas com sucesso!\n";
}

