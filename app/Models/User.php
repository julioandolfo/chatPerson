<?php
/**
 * Model User
 */

namespace App\Models;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'password', 'role', 'status', 'avatar', 'woocommerce_seller_id', 'availability_status', 'max_conversations', 'current_conversations', 'last_seen_at', 'agent_settings'];
    protected array $hidden = ['password'];
    protected bool $timestamps = true;

    /**
     * Buscar por email
     */
    public static function findByEmail(string $email): ?array
    {
        return self::whereFirst('email', '=', $email);
    }

    /**
     * Verificar senha
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Hash da senha
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Buscar agentes ativos (todos os níveis de agentes)
     */
    public static function getActiveAgents(): array
    {
        $instance = new static();
        $sql = "SELECT id, name, email, avatar, role, status FROM {$instance->table} 
                WHERE status = 'active' AND role IN ('super_admin', 'admin', 'supervisor', 'senior_agent', 'agent', 'junior_agent') 
                ORDER BY name ASC";
        return \App\Helpers\Database::fetchAll($sql);
    }

    /**
     * Obter roles do usuário
     */
    public static function getRoles(int $userId): array
    {
        $sql = "SELECT r.* FROM roles r
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
                ORDER BY r.level DESC";
        return \App\Helpers\Database::fetchAll($sql, [$userId]);
    }

    /**
     * Adicionar role ao usuário e limpar cache
     */
    public static function addRole(int $userId, int $roleId): bool
    {
        try {
            $sql = "INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)";
            \App\Helpers\Database::execute($sql, [$userId, $roleId]);
            
            // Limpar cache de permissões do usuário
            \App\Services\PermissionService::clearUserCache($userId);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remover role do usuário e limpar cache
     */
    public static function removeRole(int $userId, int $roleId): bool
    {
        try {
            $sql = "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?";
            \App\Helpers\Database::execute($sql, [$userId, $roleId]);
            
            // Limpar cache de permissões do usuário
            \App\Services\PermissionService::clearUserCache($userId);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verificar se usuário tem role
     */
    public static function hasRole(int $userId, string $roleSlug): bool
    {
        $sql = "SELECT COUNT(*) as count FROM user_roles ur
                INNER JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ? AND r.slug = ?";
        $result = \App\Helpers\Database::fetch($sql, [$userId, $roleSlug]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Obter setores do usuário
     */
    public static function getDepartments(int $userId): array
    {
        $sql = "SELECT d.* FROM departments d
                INNER JOIN agent_departments ad ON d.id = ad.department_id
                WHERE ad.user_id = ?
                ORDER BY d.name ASC";
        return \App\Helpers\Database::fetchAll($sql, [$userId]);
    }

    /**
     * Verificar se usuário pertence ao setor
     */
    public static function belongsToDepartment(int $userId, int $departmentId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM agent_departments
                WHERE user_id = ? AND department_id = ?";
        $result = \App\Helpers\Database::fetch($sql, [$userId, $departmentId]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Obter nível hierárquico máximo do usuário
     */
    public static function getMaxLevel(int $userId): int
    {
        $sql = "SELECT MAX(r.level) as max_level FROM roles r
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?";
        $result = \App\Helpers\Database::fetch($sql, [$userId]);
        return (int)($result['max_level'] ?? 0);
    }

    /**
     * Atualizar status de disponibilidade
     */
    public static function updateAvailabilityStatus(int $userId, string $status): bool
    {
        $allowedStatuses = ['online', 'offline', 'away', 'busy'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $user = self::find($userId);
        if (!$user) {
            return false;
        }

        $oldStatus = $user['availability_status'] ?? 'offline';

        $data = [
            'availability_status' => $status,
            'last_seen_at' => date('Y-m-d H:i:s')
        ];

        if ($status === 'online') {
            $data['last_seen_at'] = date('Y-m-d H:i:s');
        }

        $result = self::update($userId, $data);
        
        // Log de atividade se mudou
        if ($oldStatus !== $status) {
            try {
                if (class_exists('\App\Services\ActivityService')) {
                    \App\Services\ActivityService::logAvailabilityChanged($userId, $status, $oldStatus, \App\Helpers\Auth::id());
                }
            } catch (\Exception $e) {
                error_log("Erro ao logar atividade: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Obter contagem atual de conversas do agente
     */
    public static function getCurrentConversationsCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) as count FROM conversations 
                WHERE agent_id = ? AND status IN ('open', 'pending')";
        $result = \App\Helpers\Database::fetch($sql, [$userId]);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Atualizar contagem de conversas do agente
     */
    public static function updateConversationsCount(int $userId): bool
    {
        $count = self::getCurrentConversationsCount($userId);
        return self::update($userId, ['current_conversations' => $count]);
    }

    /**
     * Verificar se agente pode receber mais conversas
     */
    public static function canReceiveMoreConversations(int $userId): bool
    {
        $user = self::find($userId);
        if (!$user) {
            return false;
        }

        // Se não tem limite configurado, pode receber
        if (empty($user['max_conversations']) || $user['max_conversations'] === null) {
            return true;
        }

        // Verificar se está abaixo do limite
        $currentCount = $user['current_conversations'] ?? 0;
        return $currentCount < $user['max_conversations'];
    }

    /**
     * Obter agentes disponíveis (online e com capacidade)
     */
    public static function getAvailableAgents(?int $departmentId = null): array
    {
        $sql = "SELECT u.*, 
                       COUNT(DISTINCT c.id) as current_conversations_count
                FROM users u
                LEFT JOIN conversations c ON u.id = c.agent_id AND c.status IN ('open', 'pending')
                WHERE u.status = 'active' 
                  AND u.role IN ('agent', 'admin', 'supervisor')
                  AND u.availability_status = 'online'";
        
        $params = [];

        if ($departmentId) {
            $sql .= " AND u.id IN (
                        SELECT user_id FROM agent_departments WHERE department_id = ?
                      )";
            $params[] = $departmentId;
        }

        $sql .= " GROUP BY u.id
                  HAVING (u.max_conversations IS NULL OR current_conversations_count < u.max_conversations)
                  ORDER BY current_conversations_count ASC, u.name ASC";

        return \App\Helpers\Database::fetchAll($sql, $params);
    }
    
    /**
     * Obter todos os agentes (para filtros e seleções)
     */
    public static function getAgents(): array
    {
        $sql = "SELECT u.id, u.name, u.email
                FROM users u
                WHERE u.status = 'active' 
                  AND u.role IN ('agent', 'admin', 'supervisor', 'senior_agent', 'junior_agent')
                ORDER BY u.name ASC";
        
        return \App\Helpers\Database::fetchAll($sql);
    }
    
    /**
     * Buscar agente por ID do WooCommerce Seller
     */
    public static function findByWooCommerceSellerId(int $sellerId): ?array
    {
        return self::whereFirst('woocommerce_seller_id', '=', $sellerId);
    }
    
    /**
     * Obter agentes que são vendedores (têm woocommerce_seller_id)
     */
    public static function getSellers(): array
    {
        $sql = "SELECT u.* 
                FROM users u
                WHERE u.woocommerce_seller_id IS NOT NULL 
                  AND u.status = 'active' 
                ORDER BY u.name ASC";
        return \App\Helpers\Database::fetchAll($sql);
    }
}

