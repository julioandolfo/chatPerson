<?php
/**
 * Model AgentPerformanceBestPractice
 * Melhores práticas (golden conversations)
 */

namespace App\Models;

use App\Helpers\Database;

class AgentPerformanceBestPractice extends Model
{
    protected string $table = 'agent_performance_best_practices';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id',
        'agent_id',
        'analysis_id',
        'category',
        'title',
        'description',
        'excerpt',
        'score',
        'is_featured',
        'views',
        'helpful_votes'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter práticas por categoria
     */
    public static function getByCategory(string $category, int $limit = 20): array
    {
        $sql = "SELECT bp.*, u.name as agent_name, c.contact_id
                FROM agent_performance_best_practices bp
                INNER JOIN users u ON u.id = bp.agent_id
                LEFT JOIN conversations c ON c.id = bp.conversation_id
                WHERE bp.category = ?
                ORDER BY bp.score DESC, bp.helpful_votes DESC
                LIMIT ?";
        return Database::fetchAll($sql, [$category, $limit]);
    }
    
    /**
     * Obter práticas em destaque
     */
    public static function getFeatured(int $limit = 10): array
    {
        $sql = "SELECT bp.*, u.name as agent_name
                FROM agent_performance_best_practices bp
                INNER JOIN users u ON u.id = bp.agent_id
                WHERE bp.is_featured = TRUE
                ORDER BY bp.score DESC, bp.helpful_votes DESC
                LIMIT ?";
        return Database::fetchAll($sql, [$limit]);
    }
    
    /**
     * Incrementar visualizações
     */
    public static function incrementViews(int $id): bool
    {
        $sql = "UPDATE agent_performance_best_practices SET views = views + 1 WHERE id = ?";
        return Database::execute($sql, [$id]);
    }
    
    /**
     * Adicionar voto útil
     */
    public static function addHelpfulVote(int $id): bool
    {
        $sql = "UPDATE agent_performance_best_practices SET helpful_votes = helpful_votes + 1 WHERE id = ?";
        return Database::execute($sql, [$id]);
    }
}
