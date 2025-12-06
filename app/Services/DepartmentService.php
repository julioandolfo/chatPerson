<?php
/**
 * Service DepartmentService
 * Lógica de negócio para setores/departamentos
 */

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Helpers\Validator;

class DepartmentService
{
    /**
     * Listar setores com hierarquia
     */
    public static function list(array $filters = []): array
    {
        $departments = Department::all();
        
        // Se não houver filtro de parent, retornar apenas raiz
        if (!isset($filters['parent_id'])) {
            $departments = array_filter($departments, function($dept) {
                return empty($dept['parent_id']);
            });
        }
        
        // Adicionar contagem de agentes e filhos
        foreach ($departments as &$dept) {
            $dept['agents_count'] = count(Department::getAgents($dept['id']));
            $dept['children_count'] = count(Department::getChildren($dept['id']));
        }
        
        return array_values($departments);
    }

    /**
     * Obter setor com relacionamentos
     */
    public static function get(int $departmentId): ?array
    {
        $department = Department::find($departmentId);
        if (!$department) {
            return null;
        }
        
        $department['agents'] = Department::getAgents($departmentId);
        
        // Adicionar informações de conversas aos agentes
        foreach ($department['agents'] as &$agent) {
            $agent['current_conversations'] = User::getCurrentConversationsCount($agent['id']);
            $agent['max_conversations'] = User::find($agent['id'])['max_conversations'] ?? null;
            $agent['availability_status'] = User::find($agent['id'])['availability_status'] ?? null;
        }
        
        $department['children'] = Department::getChildren($departmentId);
        $department['parent'] = Department::getParent($departmentId);
        $department['tree'] = Department::getTree($departmentId);
        
        return $department;
    }

    /**
     * Criar setor
     */
    public static function create(array $data): int
    {
        // Validar dados
        self::validate($data);
        
        // Normalizar parent_id: converter string vazia para null
        $parentId = null;
        if (!empty($data['parent_id']) && $data['parent_id'] !== '') {
            $parentId = (int)$data['parent_id'];
        }
        
        // Verificar se parent existe (se fornecido)
        if ($parentId !== null) {
            $parent = Department::find($parentId);
            if (!$parent) {
                throw new \InvalidArgumentException('Setor pai não encontrado');
            }
            
            // Verificar se não está criando loop hierárquico
            if (self::wouldCreateLoop($parentId, null)) {
                throw new \InvalidArgumentException('Não é possível criar loop hierárquico');
            }
        }
        
        // Criar setor
        $departmentId = Department::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'parent_id' => $parentId
        ]);
        
        if (!$departmentId) {
            throw new \Exception('Falha ao criar setor');
        }
        
        return $departmentId;
    }

    /**
     * Atualizar setor
     */
    public static function update(int $departmentId, array $data): bool
    {
        $department = Department::find($departmentId);
        if (!$department) {
            throw new \InvalidArgumentException('Setor não encontrado');
        }
        
        // Validar dados
        self::validate($data, $departmentId);
        
        // Normalizar parent_id: converter string vazia para null
        if (isset($data['parent_id'])) {
            if ($data['parent_id'] === '' || $data['parent_id'] === null) {
                $data['parent_id'] = null;
            } else {
                $data['parent_id'] = (int)$data['parent_id'];
            }
        }
        
        // Verificar se está mudando parent_id
        if (isset($data['parent_id']) && $data['parent_id'] != $department['parent_id']) {
            // Se parent_id for null, está removendo o pai (tornando raiz)
            if ($data['parent_id'] === null) {
                // Permitir remover pai
            } else {
                // Não pode ser pai de si mesmo
                if ($data['parent_id'] == $departmentId) {
                    throw new \InvalidArgumentException('Setor não pode ser pai de si mesmo');
                }
                
                // Verificar se não está criando loop hierárquico
                if (self::wouldCreateLoop($data['parent_id'], $departmentId)) {
                    throw new \InvalidArgumentException('Não é possível criar loop hierárquico');
                }
                
                // Não pode mover para filho (seria loop)
                $children = Department::getTree($departmentId);
                foreach ($children as $child) {
                    if ($child['id'] == $data['parent_id']) {
                        throw new \InvalidArgumentException('Não é possível mover setor para dentro de seus próprios filhos');
                    }
                }
            }
        }
        
        // Atualizar
        return Department::update($departmentId, $data);
    }

    /**
     * Deletar setor
     */
    public static function delete(int $departmentId): bool
    {
        $department = Department::find($departmentId);
        if (!$department) {
            throw new \InvalidArgumentException('Setor não encontrado');
        }
        
        // Verificar se tem filhos
        $children = Department::getChildren($departmentId);
        if (!empty($children)) {
            throw new \Exception('Não é possível deletar setor que possui setores filhos. Mova ou delete os setores filhos primeiro.');
        }
        
        // Verificar se tem agentes
        $agents = Department::getAgents($departmentId);
        if (!empty($agents)) {
            throw new \Exception('Não é possível deletar setor que possui agentes. Remova os agentes primeiro.');
        }
        
        // Deletar
        return Department::delete($departmentId);
    }

    /**
     * Adicionar agente ao setor
     */
    public static function addAgent(int $departmentId, int $userId): bool
    {
        $department = Department::find($departmentId);
        if (!$department) {
            throw new \InvalidArgumentException('Setor não encontrado');
        }
        
        $user = User::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Usuário não encontrado');
        }
        
        // Verificar se já está no setor
        if (User::belongsToDepartment($userId, $departmentId)) {
            throw new \InvalidArgumentException('Usuário já está neste setor');
        }
        
        return Department::addAgent($departmentId, $userId);
    }

    /**
     * Remover agente do setor
     */
    public static function removeAgent(int $departmentId, int $userId): bool
    {
        $department = Department::find($departmentId);
        if (!$department) {
            throw new \InvalidArgumentException('Setor não encontrado');
        }
        
        // Verificar se está no setor
        if (!User::belongsToDepartment($userId, $departmentId)) {
            throw new \InvalidArgumentException('Usuário não está neste setor');
        }
        
        return Department::removeAgent($departmentId, $userId);
    }

    /**
     * Obter árvore completa de setores
     */
    public static function getTree(?int $rootId = null): array
    {
        if ($rootId === null) {
            // Retornar todos os setores raiz com seus filhos
            $roots = array_filter(Department::all(), function($dept) {
                return empty($dept['parent_id']);
            });
            
            $tree = [];
            foreach ($roots as $root) {
                $tree[] = self::buildTreeNode($root['id']);
            }
            
            return $tree;
        }
        
        return self::buildTreeNode($rootId);
    }

    /**
     * Construir nó da árvore recursivamente
     */
    private static function buildTreeNode(int $departmentId): array
    {
        $department = Department::find($departmentId);
        if (!$department) {
            return [];
        }
        
        $node = $department;
        $children = Department::getChildren($departmentId);
        $node['children'] = [];
        
        foreach ($children as $child) {
            $node['children'][] = self::buildTreeNode($child['id']);
        }
        
        $node['agents_count'] = count(Department::getAgents($departmentId));
        $node['children_count'] = count($children);
        
        return $node;
    }

    /**
     * Validar dados do setor
     */
    private static function validate(array $data, ?int $excludeId = null): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Nome do setor é obrigatório');
        }
        
        if (strlen($data['name']) < 3) {
            throw new \InvalidArgumentException('Nome do setor deve ter pelo menos 3 caracteres');
        }
        
        if (strlen($data['name']) > 255) {
            throw new \InvalidArgumentException('Nome do setor não pode ter mais de 255 caracteres');
        }
        
        // Verificar se nome já existe (exceto o próprio setor)
        $existing = Department::whereFirst('name', '=', $data['name']);
        if ($existing && ($excludeId === null || $existing['id'] != $excludeId)) {
            throw new \InvalidArgumentException('Já existe um setor com este nome');
        }
    }

    /**
     * Verificar se criar/mover setor criaria loop hierárquico
     */
    private static function wouldCreateLoop(int $newParentId, ?int $departmentId): bool
    {
        if ($departmentId === null) {
            return false; // Criando novo, não pode criar loop
        }
        
        // Verificar se o novo pai é descendente do setor atual
        $newParent = Department::find($newParentId);
        if (!$newParent) {
            return false;
        }
        
        // Obter todos os descendentes do setor atual
        $descendants = Department::getTree($departmentId);
        
        // Verificar se novo pai está entre os descendentes
        foreach ($descendants as $descendant) {
            if ($descendant['id'] == $newParentId) {
                return true; // Criaria loop!
            }
        }
        
        return false;
    }

    /**
     * Obter setores disponíveis para ser pai (excluindo o próprio e descendentes)
     */
    public static function getAvailableParents(?int $excludeDepartmentId = null): array
    {
        $allDepartments = Department::all();
        
        if ($excludeDepartmentId === null) {
            return $allDepartments;
        }
        
        // Excluir o próprio setor e seus descendentes
        $excludeIds = [$excludeDepartmentId];
        $descendants = Department::getTree($excludeDepartmentId);
        foreach ($descendants as $descendant) {
            $excludeIds[] = $descendant['id'];
        }
        
        return array_filter($allDepartments, function($dept) use ($excludeIds) {
            return !in_array($dept['id'], $excludeIds);
        });
    }

    /**
     * Obter estatísticas do setor
     */
    public static function getStats(int $departmentId): array
    {
        $department = Department::find($departmentId);
        if (!$department) {
            return [];
        }
        
        $agents = Department::getAgents($departmentId);
        $children = Department::getChildren($departmentId);
        
        // Contar conversas do setor
        $sql = "SELECT COUNT(*) as total FROM conversations WHERE department_id = ?";
        $conversations = \App\Helpers\Database::fetch($sql, [$departmentId]);
        
        return [
            'id' => $departmentId,
            'name' => $department['name'],
            'agents_count' => count($agents),
            'children_count' => count($children),
            'conversations_count' => (int)($conversations['total'] ?? 0),
            'created_at' => $department['created_at']
        ];
    }
}

