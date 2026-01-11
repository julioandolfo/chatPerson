<?php
/**
 * Model Team
 * Times/Equipes de agentes
 */

namespace App\Models;

use App\Helpers\Database;

class Team extends Model
{
    protected string $table = 'teams';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name',
        'description',
        'color',
        'leader_id',
        'department_id',
        'is_active'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter times ativos
     */
    public static function getActive(): array
    {
        $sql = "SELECT t.*, 
                       u.name as leader_name,
                       d.name as department_name,
                       COUNT(tm.user_id) as members_count
                FROM teams t
                LEFT JOIN users u ON t.leader_id = u.id
                LEFT JOIN departments d ON t.department_id = d.id
                LEFT JOIN team_members tm ON t.id = tm.team_id
                WHERE t.is_active = 1
                GROUP BY t.id, u.name, d.name
                ORDER BY t.name ASC";
        return Database::fetchAll($sql);
    }
    
    /**
     * Obter time com detalhes (líder, departamento, membros)
     */
    public static function findWithDetails(int $id): ?array
    {
        $sql = "SELECT t.*, 
                       u.name as leader_name,
                       u.email as leader_email,
                       u.avatar as leader_avatar,
                       d.name as department_name
                FROM teams t
                LEFT JOIN users u ON t.leader_id = u.id
                LEFT JOIN departments d ON t.department_id = d.id
                WHERE t.id = ?";
        return Database::fetch($sql, [$id]);
    }
    
    /**
     * Obter membros do time
     */
    public static function getMembers(int $teamId): array
    {
        $sql = "SELECT u.*, 
                       tm.joined_at,
                       (u.id = (SELECT leader_id FROM teams WHERE id = ?)) as is_leader
                FROM team_members tm
                INNER JOIN users u ON tm.user_id = u.id
                WHERE tm.team_id = ?
                ORDER BY is_leader DESC, u.name ASC";
        return Database::fetchAll($sql, [$teamId, $teamId]);
    }
    
    /**
     * Adicionar membro ao time
     */
    public static function addMember(int $teamId, int $userId): bool
    {
        try {
            $sql = "INSERT IGNORE INTO team_members (team_id, user_id) VALUES (?, ?)";
            Database::execute($sql, [$teamId, $userId]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao adicionar membro ao time: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remover membro do time
     */
    public static function removeMember(int $teamId, int $userId): bool
    {
        try {
            $sql = "DELETE FROM team_members WHERE team_id = ? AND user_id = ?";
            Database::execute($sql, [$teamId, $userId]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao remover membro do time: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se usuário é membro do time
     */
    public static function isMember(int $teamId, int $userId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM team_members 
                WHERE team_id = ? AND user_id = ?";
        $result = Database::fetch($sql, [$teamId, $userId]);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Obter times de um usuário
     */
    public static function getUserTeams(int $userId): array
    {
        $sql = "SELECT t.*, 
                       tm.joined_at,
                       (t.leader_id = ?) as is_leader
                FROM teams t
                INNER JOIN team_members tm ON t.id = tm.team_id
                WHERE tm.user_id = ?
                AND t.is_active = 1
                ORDER BY t.name ASC";
        return Database::fetchAll($sql, [$userId, $userId]);
    }
    
    /**
     * Obter contagem de membros
     */
    public static function getMembersCount(int $teamId): int
    {
        $sql = "SELECT COUNT(*) as count FROM team_members WHERE team_id = ?";
        $result = Database::fetch($sql, [$teamId]);
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * Obter IDs dos membros do time
     */
    public static function getMemberIds(int $teamId): array
    {
        $sql = "SELECT user_id FROM team_members WHERE team_id = ?";
        $results = Database::fetchAll($sql, [$teamId]);
        return array_column($results, 'user_id');
    }
}
