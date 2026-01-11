<?php
/**
 * Model TeamMember
 * Relação entre times e usuários
 */

namespace App\Models;

use App\Helpers\Database;

class TeamMember extends Model
{
    protected string $table = 'team_members';
    protected string $primaryKey = 'team_id'; // Chave composta, mas definimos uma como padrão
    protected array $fillable = [
        'team_id',
        'user_id',
        'joined_at'
    ];
    protected bool $timestamps = false; // Tem apenas joined_at
    
    /**
     * Obter todas as relações de um time
     */
    public static function getByTeam(int $teamId): array
    {
        $sql = "SELECT tm.*, u.name as user_name, u.email as user_email
                FROM team_members tm
                INNER JOIN users u ON tm.user_id = u.id
                WHERE tm.team_id = ?
                ORDER BY tm.joined_at ASC";
        return Database::fetchAll($sql, [$teamId]);
    }
    
    /**
     * Obter todas as relações de um usuário
     */
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT tm.*, t.name as team_name
                FROM team_members tm
                INNER JOIN teams t ON tm.team_id = t.id
                WHERE tm.user_id = ?
                ORDER BY tm.joined_at DESC";
        return Database::fetchAll($sql, [$userId]);
    }
    
    /**
     * Remover todos os membros de um time
     */
    public static function removeAllByTeam(int $teamId): bool
    {
        try {
            $sql = "DELETE FROM team_members WHERE team_id = ?";
            Database::execute($sql, [$teamId]);
            return true;
        } catch (\Exception $e) {
            error_log("Erro ao remover membros do time: " . $e->getMessage());
            return false;
        }
    }
}
