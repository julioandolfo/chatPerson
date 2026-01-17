<?php
/**
 * Service UserService
 * Lógica de negócio para usuários/agentes
 */

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Helpers\Validator;

class UserService
{
    /**
     * Criar usuário
     */
    public static function create(array $data): int
    {
        // Converter valores vazios para null ANTES da validação
        if (isset($data['max_conversations']) && $data['max_conversations'] === '') {
            $data['max_conversations'] = null;
        }
        if (isset($data['woocommerce_seller_id']) && $data['woocommerce_seller_id'] === '') {
            $data['woocommerce_seller_id'] = null;
        }
        
        // Processar campo queue_enabled (checkbox)
        // Para novos usuários, default é habilitado (1)
        // Se existe a chave no array, processar o valor; senão, usar default 1
        if (array_key_exists('queue_enabled', $data)) {
            $data['queue_enabled'] = !empty($data['queue_enabled']) ? 1 : 0;
        } else {
            $data['queue_enabled'] = 1; // Default é habilitado para novos usuários
        }
        
        // Definir valores padrão
        $data['availability_status'] = $data['availability_status'] ?? 'offline';
        $data['current_conversations'] = 0;
        
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,inactive',
            'availability_status' => 'nullable|string|in:online,offline,away,busy',
            'max_conversations' => 'nullable|integer|min:1',
            'woocommerce_seller_id' => 'nullable|integer|min:1'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Verificar se email já existe
        $existing = User::findByEmail($data['email']);
        if ($existing) {
            throw new \InvalidArgumentException('Email já cadastrado');
        }

        // Hash da senha
        $data['password'] = User::hashPassword($data['password']);
        $data['status'] = $data['status'] ?? 'active';
        $data['role'] = $data['role'] ?? 'agent';

        $userId = User::create($data);
        
        // Atribuir role ao usuário
        $roleSlug = $data['role'];
        try {
            // Buscar ID da role pelo slug
            $sql = "SELECT id FROM roles WHERE slug = ? LIMIT 1";
            $role = \App\Helpers\Database::fetch($sql, [$roleSlug]);
            
            if ($role) {
                // Atribuir role ao usuário
                $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)";
                \App\Helpers\Database::execute($sql, [$userId, $role['id']]);
            }
        } catch (\Exception $e) {
            error_log("Erro ao atribuir role: " . $e->getMessage());
        }
        
        // Log de atividade
        try {
            if (class_exists('\App\Services\ActivityService')) {
                \App\Services\ActivityService::logUserCreated($userId, $data['name'], \App\Helpers\Auth::id());
            }
        } catch (\Exception $e) {
            error_log("Erro ao logar atividade: " . $e->getMessage());
        }
        
        return $userId;
    }

    /**
     * Atualizar usuário
     */
    public static function update(int $userId, array $data): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Usuário não encontrado');
        }

        // Converter valores vazios para null ANTES da validação
        if (isset($data['max_conversations']) && $data['max_conversations'] === '') {
            $data['max_conversations'] = null;
        }
        if (isset($data['current_conversations']) && $data['current_conversations'] === '') {
            $data['current_conversations'] = null;
        }
        if (isset($data['woocommerce_seller_id']) && $data['woocommerce_seller_id'] === '') {
            $data['woocommerce_seller_id'] = null;
        }
        
        // Processar campo queue_enabled (checkbox)
        // Para update: se a chave existe no array (vem do formulário), processar
        // Se não existe, não alterar (pode ser update de outros campos)
        // Se existe e tem valor truthy, converter para 1; se existe e não tem valor, converter para 0
        if (array_key_exists('queue_enabled', $data)) {
            $data['queue_enabled'] = !empty($data['queue_enabled']) ? 1 : 0;
        }

        $errors = Validator::validate($data, [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,inactive',
            'availability_status' => 'nullable|string|in:online,offline,away,busy',
            'max_conversations' => 'nullable|integer|min:1',
            'current_conversations' => 'nullable|integer|min:0',
            'avatar' => 'nullable|string|max:255',
            'woocommerce_seller_id' => 'nullable|integer|min:1'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Verificar se email já existe (outro usuário)
        if (isset($data['email']) && $data['email'] !== $user['email']) {
            $existing = User::findByEmail($data['email']);
            if ($existing) {
                throw new \InvalidArgumentException('Email já cadastrado');
            }
        }

        // Hash da senha se fornecida e não vazia
        if (isset($data['password']) && !empty(trim($data['password'] ?? ''))) {
            $data['password'] = User::hashPassword($data['password']);
        } else {
            // Remover campo password se vazio para não atualizar
            unset($data['password']);
        }
        
        // Detectar mudanças para log
        $changes = [];
        foreach ($data as $field => $value) {
            if ($field !== 'password' && isset($user[$field]) && $user[$field] != $value) {
                $changes[$field] = "{$user[$field]} → {$value}";
            }
        }
        
        // Verificar mudança de disponibilidade
        $oldAvailability = $user['availability_status'] ?? 'offline';
        $newAvailability = $data['availability_status'] ?? $oldAvailability;
        
        $result = User::update($userId, $data);
        
        // Log de atividade
        try {
            if (class_exists('\App\Services\ActivityService')) {
                if (!empty($changes)) {
                    \App\Services\ActivityService::logUserUpdated($userId, $user['name'], $changes, \App\Helpers\Auth::id());
                }
                
                // Log separado para mudança de disponibilidade
                if ($oldAvailability !== $newAvailability) {
                    \App\Services\ActivityService::logAvailabilityChanged($userId, $newAvailability, $oldAvailability, \App\Helpers\Auth::id());
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao logar atividade: " . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Listar usuários com filtros
     */
    public static function list(array $filters = []): array
    {
        $sql = "SELECT u.*, 
                       GROUP_CONCAT(DISTINCT r.name) as roles_names,
                       GROUP_CONCAT(DISTINCT d.name) as departments_names
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                LEFT JOIN agent_departments ad ON u.id = ad.user_id
                LEFT JOIN departments d ON ad.department_id = d.id
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " GROUP BY u.id ORDER BY u.name ASC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Obter usuário com relacionamentos
     */
    public static function get(int $userId): ?array
    {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        $user['roles'] = User::getRoles($userId);
        $user['departments'] = User::getDepartments($userId);

        return $user;
    }

    /**
     * Atribuir role ao usuário
     */
    public static function assignRole(int $userId, int $roleId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Usuário não encontrado');
        }

        $role = Role::find($roleId);
        if (!$role) {
            throw new \InvalidArgumentException('Role não encontrada');
        }

        return User::addRole($userId, $roleId);
    }

    /**
     * Remover role do usuário
     */
    public static function removeRole(int $userId, int $roleId): bool
    {
        return User::removeRole($userId, $roleId);
    }

    /**
     * Atribuir department ao usuário
     */
    public static function assignDepartment(int $userId, int $departmentId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Usuário não encontrado');
        }

        $department = Department::find($departmentId);
        if (!$department) {
            throw new \InvalidArgumentException('Setor não encontrado');
        }

        return Department::addAgent($departmentId, $userId);
    }

    /**
     * Remover department do usuário
     */
    public static function removeDepartment(int $userId, int $departmentId): bool
    {
        return Department::removeAgent($departmentId, $userId);
    }

    /**
     * Deletar usuário
     */
    public static function delete(int $userId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Usuário não encontrado');
        }

        // Verificar se é usuário do sistema (não pode deletar)
        if ($user['role'] === 'admin' && User::hasRole($userId, 'super-admin')) {
            throw new \Exception('Não é possível deletar super admin');
        }

        return User::delete($userId);
    }

    /**
     * Upload de avatar do usuário
     */
    public static function uploadAvatar(int $userId, array $file): string
    {
        // Validar arquivo
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Erro ao fazer upload do arquivo');
        }

        // Validar tipo de arquivo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new \Exception('Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP');
        }

        // Validar tamanho (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new \Exception('Arquivo muito grande. Tamanho máximo: 2MB');
        }

        // Criar diretório se não existir
        $uploadDir = __DIR__ . '/../../public/assets/media/avatars/users/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Gerar nome único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Erro ao salvar arquivo');
        }

        // Remover avatar antigo se existir
        $user = User::find($userId);
        if ($user && !empty($user['avatar'])) {
            $oldPath = __DIR__ . '/../../public' . str_replace(\App\Helpers\Url::basePath(), '', $user['avatar']);
            if (file_exists($oldPath) && strpos($oldPath, 'users/') !== false) {
                @unlink($oldPath);
            }
        }

        // Retornar URL relativa
        $avatarUrl = \App\Helpers\Url::asset('media/avatars/users/' . $filename);
        
        // Atualizar usuário
        User::update($userId, ['avatar' => $avatarUrl]);

        return $avatarUrl;
    }
}

