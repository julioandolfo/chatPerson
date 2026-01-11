<?php
/**
 * Service TeamService
 * Lógica de negócio para Times/Equipes
 */

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Helpers\Validator;
use App\Helpers\Database;

class TeamService
{
    /**
     * Criar time
     */
    public static function create(array $data): int
    {
        // Validação
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'string',
            'color' => 'string|max:7',
            'leader_id' => 'integer',
            'department_id' => 'integer'
        ]);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        // Garantir valores padrão
        $data['is_active'] = $data['is_active'] ?? 1;
        
        // Validar se leader_id existe
        if (isset($data['leader_id']) && $data['leader_id'] > 0) {
            $leader = User::find($data['leader_id']);
            if (!$leader) {
                throw new \InvalidArgumentException('Líder não encontrado');
            }
        }
        
        // Criar time
        $teamId = Team::create($data);
        
        // Se foi definido um líder, adicionar ele ao time automaticamente
        if (isset($data['leader_id']) && $data['leader_id'] > 0) {
            Team::addMember($teamId, $data['leader_id']);
        }
        
        // Log de atividade
        if (class_exists('\App\Services\ActivityService')) {
            try {
                ActivityService::logTeamCreated($teamId, $data['name'], \App\Helpers\Auth::id());
            } catch (\Exception $e) {
                error_log("Erro ao logar atividade: " . $e->getMessage());
            }
        }
        
        return $teamId;
    }
    
    /**
     * Atualizar time
     */
    public static function update(int $id, array $data): bool
    {
        // Validação
        $errors = Validator::validate($data, [
            'name' => 'string|max:255',
            'description' => 'string',
            'color' => 'string|max:7',
            'leader_id' => 'integer',
            'department_id' => 'integer',
            'is_active' => 'boolean'
        ]);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        $team = Team::find($id);
        if (!$team) {
            throw new \InvalidArgumentException('Time não encontrado');
        }
        
        // Validar se leader_id existe
        if (isset($data['leader_id']) && $data['leader_id'] > 0) {
            $leader = User::find($data['leader_id']);
            if (!$leader) {
                throw new \InvalidArgumentException('Líder não encontrado');
            }
            
            // Garantir que o líder está no time
            if (!Team::isMember($id, $data['leader_id'])) {
                Team::addMember($id, $data['leader_id']);
            }
        }
        
        $result = Team::update($id, $data);
        
        // Log de atividade
        if (class_exists('\App\Services\ActivityService')) {
            try {
                ActivityService::logTeamUpdated($id, $data['name'] ?? $team['name'], \App\Helpers\Auth::id());
            } catch (\Exception $e) {
                error_log("Erro ao logar atividade: " . $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * Deletar time
     */
    public static function delete(int $id): bool
    {
        $team = Team::find($id);
        if (!$team) {
            throw new \InvalidArgumentException('Time não encontrado');
        }
        
        // Remover todos os membros primeiro (CASCADE já faz isso, mas por garantia)
        TeamMember::removeAllByTeam($id);
        
        $result = Team::delete($id);
        
        // Log de atividade
        if (class_exists('\App\Services\ActivityService')) {
            try {
                ActivityService::logTeamDeleted($id, $team['name'], \App\Helpers\Auth::id());
            } catch (\Exception $e) {
                error_log("Erro ao logar atividade: " . $e->getMessage());
            }
        }
        
        return $result;
    }
    
    /**
     * Adicionar membros ao time (múltiplos)
     */
    public static function addMembers(int $teamId, array $userIds): bool
    {
        $team = Team::find($teamId);
        if (!$team) {
            throw new \InvalidArgumentException('Time não encontrado');
        }
        
        $added = 0;
        foreach ($userIds as $userId) {
            // Validar se usuário existe
            $user = User::find($userId);
            if ($user) {
                if (Team::addMember($teamId, $userId)) {
                    $added++;
                }
            }
        }
        
        return $added > 0;
    }
    
    /**
     * Remover membros do time (múltiplos)
     */
    public static function removeMembers(int $teamId, array $userIds): bool
    {
        $team = Team::find($teamId);
        if (!$team) {
            throw new \InvalidArgumentException('Time não encontrado');
        }
        
        $removed = 0;
        foreach ($userIds as $userId) {
            if (Team::removeMember($teamId, $userId)) {
                $removed++;
            }
        }
        
        return $removed > 0;
    }
    
    /**
     * Listar todos os times
     */
    public static function list(bool $activeOnly = true): array
    {
        if ($activeOnly) {
            return Team::getActive();
        }
        
        $sql = "SELECT t.*, 
                       u.name as leader_name,
                       d.name as department_name,
                       COUNT(tm.user_id) as members_count
                FROM teams t
                LEFT JOIN users u ON t.leader_id = u.id
                LEFT JOIN departments d ON t.department_id = d.id
                LEFT JOIN team_members tm ON t.id = tm.team_id
                GROUP BY t.id, u.name, d.name
                ORDER BY t.name ASC";
        return Database::fetchAll($sql);
    }
    
    /**
     * Obter time com detalhes completos
     */
    public static function getDetails(int $id): ?array
    {
        $team = Team::findWithDetails($id);
        if (!$team) {
            return null;
        }
        
        // Adicionar membros
        $team['members'] = Team::getMembers($id);
        $team['members_count'] = count($team['members']);
        
        return $team;
    }
    
    /**
     * Sincronizar membros do time (substituir todos)
     */
    public static function syncMembers(int $teamId, array $userIds): bool
    {
        $team = Team::find($teamId);
        if (!$team) {
            throw new \InvalidArgumentException('Time não encontrado');
        }
        
        try {
            // Começar transação
            Database::beginTransaction();
            
            // Remover todos os membros atuais
            TeamMember::removeAllByTeam($teamId);
            
            // Adicionar novos membros
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    Team::addMember($teamId, $userId);
                }
            }
            
            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollback();
            error_log("Erro ao sincronizar membros do time: " . $e->getMessage());
            return false;
        }
    }
}
