<?php
/**
 * Model ConversationSentiment
 * Análises de sentimento de conversas
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationSentiment extends Model
{
    protected string $table = 'conversation_sentiments';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id', 'message_id', 'sentiment_score', 'sentiment_label',
        'emotions', 'urgency_level', 'confidence', 'analysis_text',
        'messages_analyzed', 'tokens_used', 'cost', 'model_used', 'analyzed_at'
    ];
    protected bool $timestamps = true;

    /**
     * Obter sentimento atual de uma conversa
     */
    public static function getCurrent(int $conversationId): ?array
    {
        $sql = "SELECT * FROM conversation_sentiments 
                WHERE conversation_id = ? 
                ORDER BY analyzed_at DESC 
                LIMIT 1";
        return Database::fetch($sql, [$conversationId]);
    }

    /**
     * Obter histórico de sentimentos de uma conversa
     */
    public static function getHistory(int $conversationId, int $limit = 50): array
    {
        $sql = "SELECT * FROM conversation_sentiments 
                WHERE conversation_id = ? 
                ORDER BY analyzed_at DESC 
                LIMIT ?";
        return Database::fetchAll($sql, [$conversationId, $limit]);
    }

    /**
     * Obter sentimento médio de um contato
     */
    public static function getContactAverage(int $contactId): ?float
    {
        $sql = "SELECT AVG(cs.sentiment_score) as avg_sentiment
                FROM conversation_sentiments cs
                INNER JOIN conversations c ON cs.conversation_id = c.id
                WHERE c.contact_id = ? 
                AND c.status IN ('closed', 'resolved')
                AND cs.analyzed_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $result = Database::fetch($sql, [$contactId]);
        return $result ? (float)$result['avg_sentiment'] : null;
    }

    /**
     * Verificar se conversa já foi analisada recentemente
     */
    public static function wasAnalyzedRecently(int $conversationId, int $minutesAgo = 60): bool
    {
        $sql = "SELECT COUNT(*) as count FROM conversation_sentiments 
                WHERE conversation_id = ? 
                AND analyzed_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $result = Database::fetch($sql, [$conversationId, $minutesAgo]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Obter estatísticas de sentimento para analytics
     */
    public static function getAnalytics(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['start_date'])) {
            $where[] = "cs.analyzed_at >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "cs.analyzed_at <= ?";
            $params[] = $filters['end_date'];
        }

        if (!empty($filters['department_id'])) {
            $where[] = "c.department_id = ?";
            $params[] = $filters['department_id'];
        }

        if (!empty($filters['agent_id'])) {
            $where[] = "c.agent_id = ?";
            $params[] = $filters['agent_id'];
        }

        $whereClause = implode(' AND ', $where);

        // Estatísticas gerais
        $sql = "SELECT 
                    COUNT(*) as total_analyses,
                    AVG(sentiment_score) as avg_sentiment,
                    SUM(CASE WHEN sentiment_label = 'positive' THEN 1 ELSE 0 END) as positive_count,
                    SUM(CASE WHEN sentiment_label = 'neutral' THEN 1 ELSE 0 END) as neutral_count,
                    SUM(CASE WHEN sentiment_label = 'negative' THEN 1 ELSE 0 END) as negative_count,
                    SUM(CASE WHEN urgency_level = 'critical' THEN 1 ELSE 0 END) as critical_count,
                    SUM(cost) as total_cost
                FROM conversation_sentiments cs
                INNER JOIN conversations c ON cs.conversation_id = c.id
                WHERE {$whereClause}";
        
        return Database::fetch($sql, $params) ?: [];
    }
}

