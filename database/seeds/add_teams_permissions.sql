-- ===================================================================
-- Script SQL para adicionar APENAS as permissões de Times
-- Pode ser executado direto no MySQL sem afetar outras permissões
-- ===================================================================

-- Inserir permissões (INSERT IGNORE não duplica se já existir)
INSERT IGNORE INTO permissions (name, slug, description, module) VALUES
('Ver times', 'teams.view', 'Ver times/equipes', 'teams'),
('Criar times', 'teams.create', 'Criar times/equipes', 'teams'),
('Editar times', 'teams.edit', 'Editar times/equipes', 'teams'),
('Deletar times', 'teams.delete', 'Deletar times/equipes', 'teams'),
('Gerenciar membros de times', 'teams.manage_members', 'Adicionar/remover membros de times', 'teams');

-- Atribuir permissões ao Super Admin (role slug = 'super-admin')
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p
WHERE r.slug = 'super-admin' 
  AND p.module = 'teams';

-- Atribuir permissões ao Admin (role slug = 'admin')
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p
WHERE r.slug = 'admin' 
  AND p.module = 'teams';

-- Verificar permissões criadas
SELECT 
    '✅ Permissões de Times criadas:' as status,
    COUNT(*) as total 
FROM permissions 
WHERE module = 'teams';

-- Verificar atribuições
SELECT 
    '✅ Permissões atribuídas aos roles:' as status,
    r.name as role,
    COUNT(rp.permission_id) as total_permissions
FROM roles r
INNER JOIN role_permissions rp ON r.id = rp.role_id
INNER JOIN permissions p ON rp.permission_id = p.id
WHERE p.module = 'teams'
GROUP BY r.id, r.name;
