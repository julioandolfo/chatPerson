<?php
/**
 * Service PermissionService
 * Lógica de negócio para permissões
 */

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Department;

class PermissionService
{
    /**
     * Diretório de cache
     */
    private static string $cacheDir = __DIR__ . '/../../storage/cache/permissions/';
    
    /**
     * TTL do cache em segundos (1 hora)
     */
    private static int $cacheTTL = 3600;

    /**
     * Verificar se usuário tem permissão (com cache)
     */
    public static function hasPermission(int $userId, string $permissionSlug, ?array $context = null): bool
    {
        // Super admin tem todas as permissões
        if (self::isSuperAdmin($userId)) {
            return true;
        }

        // Verificar cache primeiro
        $cacheKey = "user_{$userId}_perm_{$permissionSlug}";
        $cached = self::getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Obter permissões do usuário (com herança)
        $userPermissions = self::getUserPermissions($userId);
        
        // Verificar se tem a permissão diretamente
        $hasPermission = isset($userPermissions[$permissionSlug]);
        
        // Se não tiver diretamente, verificar herança hierárquica
        if (!$hasPermission) {
            $hasPermission = self::checkHierarchicalPermission($userId, $permissionSlug, $userPermissions);
        }

        // Verificar permissões condicionais se contexto fornecido
        if ($hasPermission && $context !== null) {
            $hasPermission = self::checkConditionalPermission($userId, $permissionSlug, $context);
        }

        // Salvar no cache
        self::setCache($cacheKey, $hasPermission);

        return $hasPermission;
    }

    /**
     * Verificar permissão considerando herança hierárquica
     */
    private static function checkHierarchicalPermission(int $userId, string $permissionSlug, array $userPermissions): bool
    {
        // Obter nível máximo do usuário
        $userLevel = User::getMaxLevel($userId);
        
        // Verificar permissões mais genéricas baseadas no nível
        // Exemplo: se tem conversations.view.all e nível >= 1, tem todas as permissões de view
        $permissionParts = explode('.', $permissionSlug);
        
        if (count($permissionParts) >= 3) {
            $resource = $permissionParts[0];
            $action = $permissionParts[1];
            $scope = $permissionParts[2];
            
            // Verificar permissão mais genérica (all)
            $genericPermission = "{$resource}.{$action}.all";
            if (isset($userPermissions[$genericPermission])) {
                return true;
            }
            
            // Verificar permissões hierárquicas por nível
            if ($userLevel >= 1 && $scope === 'department') {
                // Admin pode ver department
                return isset($userPermissions["{$resource}.{$action}.all"]);
            }
            
            if ($userLevel >= 2 && $scope === 'team') {
                // Supervisor pode ver team
                return isset($userPermissions["{$resource}.{$action}.department"]) || 
                       isset($userPermissions["{$resource}.{$action}.all"]);
            }
        }
        
        return false;
    }

    /**
     * Verificar permissões condicionais (temporais, por status, etc)
     */
    private static function checkConditionalPermission(int $userId, string $permissionSlug, array $context): bool
    {
        // Verificar condições temporais
        if (isset($context['time_restriction'])) {
            $currentHour = (int)date('H');
            $restriction = $context['time_restriction'];
            
            if (isset($restriction['start']) && isset($restriction['end'])) {
                if ($currentHour < $restriction['start'] || $currentHour >= $restriction['end']) {
                    return false;
                }
            }
        }
        
        // Verificar condições por status
        if (isset($context['conversation_status'])) {
            $status = $context['conversation_status'];
            
            // Se conversa está resolvida, apenas visualização permitida
            if ($status === 'resolved' && strpos($permissionSlug, '.edit.') !== false) {
                return false;
            }
            
            // Se conversa está arquivada, permissões limitadas
            if ($status === 'archived' && strpos($permissionSlug, '.delete.') === false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Verificar se usuário tem qualquer uma das permissões
     */
    public static function hasAnyPermission(int $userId, array $permissionSlugs, ?array $context = null): bool
    {
        foreach ($permissionSlugs as $slug) {
            if (self::hasPermission($userId, $slug, $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar se usuário tem todas as permissões
     */
    public static function hasAllPermissions(int $userId, array $permissionSlugs, ?array $context = null): bool
    {
        foreach ($permissionSlugs as $slug) {
            if (!self::hasPermission($userId, $slug, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verificar se usuário é super admin
     */
    public static function isSuperAdmin(int $userId): bool
    {
        // Level 0 = Super Admin (quanto menor o nível, maior o poder)
        return User::hasRole($userId, 'super-admin') || User::getMaxLevel($userId) <= 0;
    }

    /**
     * Verificar se usuário é admin
     */
    public static function isAdmin(int $userId): bool
    {
        // Level 0-1 = Super Admin e Admin (quanto menor o nível, maior o poder)
        return User::hasRole($userId, 'admin') || User::getMaxLevel($userId) <= 1;
    }

    /**
     * Verificar se usuário pode ver conversa
     */
    public static function canViewConversation(int $userId, array $conversation): bool
    {
        // Super admin e admin podem ver todas
        if (self::isSuperAdmin($userId) || self::isAdmin($userId)) {
            return true;
        }

        // Verificar permissões específicas
        if (self::hasPermission($userId, 'conversations.view.all')) {
            return true;
        }

        // ✅ NOVA PRIORIDADE: Agente do Contato SEMPRE pode ver
        // Verificar se o usuário é um agente do contato (especialmente o agente principal)
        if (!empty($conversation['contact_id'])) {
            try {
                $contactAgents = \App\Models\ContactAgent::getByContact($conversation['contact_id']);
                foreach ($contactAgents as $ca) {
                    if ($ca['agent_id'] == $userId) {
                        \App\Helpers\Log::debug("✅ [canViewConversation] Usuário é Agente do Contato - userId={$userId}, contactId={$conversation['contact_id']}, isPrimary=" . ($ca['is_primary'] ? 'true' : 'false'), 'conversas.log');
                        return true; // Agente do contato sempre pode ver
                    }
                }
            } catch (\Exception $e) {
                \App\Helpers\Log::error("Erro ao verificar agentes do contato: " . $e->getMessage(), 'conversas.log');
            }
        }

        // ⚠️ IMPORTANTE: Verificar permissão de FUNIL primeiro
        // Essa verificação se aplica a TODAS as conversas (atribuídas ou não)
        if (class_exists('\App\Models\AgentFunnelPermission')) {
            $hasFunnelPermission = \App\Models\AgentFunnelPermission::canViewConversation($userId, $conversation);
            
            // 🐛 DEBUG - Remover depois dos testes
            if (!$hasFunnelPermission) {
                \App\Helpers\Log::debug("🚫 [canViewConversation] Conversa bloqueada por permissão de funil - convId={$conversation['id']}, funnelId={$conversation['funnel_id']}, stageId={$conversation['funnel_stage_id']}, userId={$userId}", 'conversas.log');
            }
            
            if (!$hasFunnelPermission) {
                return false; // Não tem permissão para o funil/etapa desta conversa
            }
        }

        // ✅ NOVA REGRA: Conversas NÃO ATRIBUÍDAS são visíveis para agentes com permissão
        // Isso permite que qualquer agente veja e responda conversas sem dono
        // Verificar se agent_id é NULL, 0 ou string '0'
        $agentId = $conversation['agent_id'] ?? null;
        $isUnassigned = ($agentId === null || $agentId === 0 || $agentId === '0' || $agentId === '');
        
        if ($isUnassigned) {
            // Verificar se tem permissão para ver conversas não atribuídas
            if (self::hasPermission($userId, 'conversations.view.unassigned')) {
                return true; // ✅ Tem permissão de funil E de ver não atribuídas
            }
            // Ou se tem permissão para ver próprias (agentes padrão também podem ver não atribuídas)
            if (self::hasPermission($userId, 'conversations.view.own')) {
                return true; // ✅ Tem permissão de funil E de ver próprias
            }
        }

        // Verificar se é participante da conversa
        if (isset($conversation['participants_data']) && !empty($conversation['participants_data'])) {
            $participants = explode('|||', $conversation['participants_data']);
            foreach ($participants as $participant) {
                if (!empty($participant)) {
                    $parts = explode(':', $participant);
                    if (isset($parts[0]) && (int)$parts[0] === $userId) {
                        return true; // Usuário é participante
                    }
                }
            }
        }
        
        // Verificar também via tabela de participantes (fallback)
        if (class_exists('\App\Models\ConversationParticipant')) {
            if (\App\Models\ConversationParticipant::isParticipant($conversation['id'], $userId)) {
                return true;
            }
        }

        // Verificar se é própria conversa
        if (isset($conversation['agent_id']) && $conversation['agent_id'] == $userId) {
            return self::hasPermission($userId, 'conversations.view.own');
        }

        // Verificar se é do setor
        if (isset($conversation['department_id'])) {
            $userDepartments = User::getDepartments($userId);
            foreach ($userDepartments as $dept) {
                if ($dept['id'] == $conversation['department_id']) {
                    return self::hasPermission($userId, 'conversations.view.department');
                }
            }
        }

        return false;
    }

    /**
     * Verificar se usuário pode editar conversa
     */
    public static function canEditConversation(int $userId, array $conversation): bool
    {
        // Super admin e admin podem editar todas
        if (self::isSuperAdmin($userId) || self::isAdmin($userId)) {
            return true;
        }

        // Verificar permissões específicas
        if (self::hasPermission($userId, 'conversations.edit.all')) {
            return true;
        }

        // Verificar se é própria conversa
        if (isset($conversation['agent_id']) && $conversation['agent_id'] == $userId) {
            return self::hasPermission($userId, 'conversations.edit.own');
        }

        // Verificar se é do setor
        if (isset($conversation['department_id'])) {
            $userDepartments = User::getDepartments($userId);
            foreach ($userDepartments as $dept) {
                if ($dept['id'] == $conversation['department_id']) {
                    return self::hasPermission($userId, 'conversations.edit.department');
                }
            }
        }

        return false;
    }

    /**
     * Verificar se usuário pode enviar mensagem em conversa
     */
    public static function canSendMessage(int $userId, array $conversation): bool
    {
        // Super admin e admin podem enviar em qualquer conversa
        if (self::isSuperAdmin($userId) || self::isAdmin($userId)) {
            return true;
        }

        // Verificar permissões específicas
        if (self::hasPermission($userId, 'messages.send.all')) {
            return true;
        }

        // Verificar se pode ver a conversa primeiro
        if (!self::canViewConversation($userId, $conversation)) {
            return false;
        }

        // Verificar se é o agente responsável
        if (isset($conversation['agent_id']) && $conversation['agent_id'] == $userId) {
            return self::hasPermission($userId, 'messages.send.own');
        }

        // ✅ Verificar se é PARTICIPANTE da conversa - participantes podem enviar mensagens
        if (isset($conversation['id'])) {
            // Verificar via participants_data (otimizado)
            if (isset($conversation['participants_data']) && !empty($conversation['participants_data'])) {
                $participants = explode('|||', $conversation['participants_data']);
                foreach ($participants as $participant) {
                    if (!empty($participant)) {
                        $parts = explode(':', $participant);
                        if (isset($parts[0]) && (int)$parts[0] === $userId) {
                            // Participante pode enviar mensagens
                            return self::hasPermission($userId, 'messages.send.own');
                        }
                    }
                }
            }
            
            // Fallback: verificar na tabela de participantes
            if (class_exists('\App\Models\ConversationParticipant')) {
                if (\App\Models\ConversationParticipant::isParticipant($conversation['id'], $userId)) {
                    return self::hasPermission($userId, 'messages.send.own');
                }
            }
        }

        // ✅ NOVA REGRA: Conversas NÃO ATRIBUÍDAS - agentes com permissão de funil podem enviar
        $agentId = $conversation['agent_id'] ?? null;
        $isUnassigned = ($agentId === null || $agentId === 0 || $agentId === '0' || $agentId === '');
        
        if ($isUnassigned) {
            // Se tem permissão de funil (canViewConversation já passou), pode enviar
            if (\App\Models\AgentFunnelPermission::canViewConversation($userId, $conversation)) {
                return self::hasPermission($userId, 'messages.send.own');
            }
        }

        // Verificar se é do mesmo departamento
        if (isset($conversation['department_id'])) {
            $userDepartments = User::getDepartments($userId);
            foreach ($userDepartments as $dept) {
                if ($dept['id'] == $conversation['department_id']) {
                    return self::hasPermission($userId, 'messages.send.department');
                }
            }
        }

        return false;
    }

    /**
     * Filtrar conversas por permissões do usuário
     */
    public static function filterConversationsByPermission(int $userId, array $conversations): array
    {
        return array_filter($conversations, function($conversation) use ($userId) {
            return self::canViewConversation($userId, $conversation);
        });
    }

    /**
     * Obter permissões do usuário (com cache e herança)
     */
    public static function getUserPermissions(int $userId): array
    {
        // Verificar cache
        $cacheKey = "user_{$userId}_permissions";
        $cached = self::getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $roles = User::getRoles($userId);
        $permissions = [];
        
        // Obter permissões diretas das roles
        foreach ($roles as $role) {
            $rolePermissions = Role::getPermissions($role['id']);
            foreach ($rolePermissions as $permission) {
                $permissions[$permission['slug']] = $permission;
            }
        }
        
        // Adicionar permissões herdadas por hierarquia
        $userLevel = User::getMaxLevel($userId);
        $inheritedPermissions = self::getInheritedPermissions($userLevel);
        
        foreach ($inheritedPermissions as $permission) {
            if (!isset($permissions[$permission['slug']])) {
                $permissions[$permission['slug']] = $permission;
            }
        }
        
        // Salvar no cache
        self::setCache($cacheKey, $permissions);
        
        return $permissions;
    }

    /**
     * Obter permissões herdadas por nível hierárquico
     */
    private static function getInheritedPermissions(int $level): array
    {
        // Nível 0 (Super Admin): Todas as permissões
        if ($level <= 0) {
            return Permission::all();
        }
        
        // Nível 1 (Admin): Herda de Supervisor
        if ($level <= 1) {
            return self::getPermissionsByLevel(2);
        }
        
        // Nível 2 (Supervisor): Herda de Agente Sênior
        if ($level <= 2) {
            return self::getPermissionsByLevel(3);
        }
        
        // Nível 3 (Agente Sênior): Herda de Agente
        if ($level <= 3) {
            return self::getPermissionsByLevel(4);
        }
        
        // Nível 4 (Agente): Permissões base
        return [];
    }

    /**
     * Obter permissões por nível hierárquico
     */
    private static function getPermissionsByLevel(int $level): array
    {
        $sql = "SELECT DISTINCT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN roles r ON rp.role_id = r.id
                WHERE r.level >= ?
                ORDER BY p.module, p.name";
        return \App\Helpers\Database::fetchAll($sql, [$level]);
    }

    /**
     * Limpar cache de permissões do usuário
     */
    public static function clearUserCache(int $userId): void
    {
        $pattern = self::$cacheDir . "user_{$userId}_*";
        $files = glob($pattern);
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Limpar todo o cache de permissões
     */
    public static function clearAllCache(): void
    {
        $files = glob(self::$cacheDir . "*");
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Obter valor do cache
     */
    private static function getCache(string $key)
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
        
        $file = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        // Verificar TTL
        if (time() - filemtime($file) > self::$cacheTTL) {
            @unlink($file);
            return null;
        }
        
        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }
        
        $decoded = @json_decode($data, true);
        return $decoded['value'] ?? null;
    }

    /**
     * Salvar valor no cache
     */
    private static function setCache(string $key, $value): void
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
        
        $file = self::$cacheDir . md5($key) . '.cache';
        $data = json_encode([
            'key' => $key,
            'value' => $value,
            'created_at' => time()
        ]);
        
        @file_put_contents($file, $data);
    }

    /**
     * Verificar nível hierárquico do usuário
     */
    public static function getUserLevel(int $userId): int
    {
        return User::getMaxLevel($userId);
    }

    /**
     * Verificar se usuário tem nível mínimo
     */
    public static function hasMinimumLevel(int $userId, int $minLevel): bool
    {
        return User::getMaxLevel($userId) <= $minLevel;
    }

    /**
     * Obter permissões por módulo
     */
    public static function getPermissionsByModule(int $userId, string $module): array
    {
        $allPermissions = self::getUserPermissions($userId);
        return array_filter($allPermissions, function($perm) use ($module) {
            return ($perm['module'] ?? 'other') === $module;
        });
    }
}

