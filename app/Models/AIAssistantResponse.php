<?php
/**
 * Model AIAssistantResponse
 * Histórico de respostas geradas pelo Assistente IA
 */

namespace App\Models;

use App\Helpers\Database;

class AIAssistantResponse extends Model
{
    protected string $table = 'ai_assistant_responses';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'conversation_id', 'feature_key', 'ai_agent_id',
        'response_text', 'tone', 'tokens_used', 'cost', 'is_favorite', 'used_at'
    ];
    protected bool $timestamps = false; // Usa created_at manualmente

    /**
     * Salvar resposta gerada
     */
    public static function saveResponse(
        int $userId,
        int $conversationId,
        string $featureKey,
        ?int $aiAgentId,
        string $responseText,
        ?string $tone = null,
        int $tokensUsed = 0,
        float $cost = 0.0
    ): int {
        $data = [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'feature_key' => $featureKey,
            'ai_agent_id' => $aiAgentId,
            'response_text' => $responseText,
            'tone' => $tone,
            'tokens_used' => $tokensUsed,
            'cost' => $cost,
            'is_favorite' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return self::create($data);
    }

    /**
     * Marcar resposta como usada
     */
    public static function markAsUsed(int $id): bool
    {
        return self::update($id, [
            'used_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Alternar favorito
     */
    public static function toggleFavorite(int $id): bool
    {
        $response = self::find($id);
        if (!$response) {
            return false;
        }

        $newValue = $response['is_favorite'] ? 0 : 1;
        return self::update($id, [
            'is_favorite' => $newValue
        ]);
    }

    /**
     * Obter histórico de respostas para uma conversa
     */
    public static function getHistory(int $conversationId, ?int $userId = null, int $limit = 20): array
    {
        $sql = "SELECT r.*, a.name as agent_name
                FROM ai_assistant_responses r
                LEFT JOIN ai_agents a ON r.ai_agent_id = a.id
                WHERE r.conversation_id = ?";
        
        $params = [$conversationId];
        
        if ($userId) {
            $sql .= " AND r.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Obter respostas favoritas do usuário
     */
    public static function getFavorites(int $userId, int $limit = 50): array
    {
        $sql = "SELECT r.*, a.name as agent_name, c.id as conversation_id
                FROM ai_assistant_responses r
                LEFT JOIN ai_agents a ON r.ai_agent_id = a.id
                LEFT JOIN conversations c ON r.conversation_id = c.id
                WHERE r.user_id = ? AND r.is_favorite = 1
                ORDER BY r.created_at DESC LIMIT ?";
        
        return Database::fetchAll($sql, [$userId, $limit]);
    }

    /**
     * Deletar resposta antiga (manter apenas últimas N, preservando favoritas)
     */
    public static function cleanupOld(int $conversationId, int $keepCount = 50): int
    {
        // Obter IDs das respostas que devem ser mantidas (favoritas + últimas N)
        $keepIds = Database::fetchAll(
            "SELECT id FROM ai_assistant_responses 
             WHERE conversation_id = ? 
             AND (is_favorite = 1 OR id IN (
                 SELECT id FROM (
                     SELECT id FROM ai_assistant_responses 
                     WHERE conversation_id = ? 
                     ORDER BY created_at DESC 
                     LIMIT ?
                 ) AS temp
             ))
             ORDER BY created_at DESC",
            [$conversationId, $conversationId, $keepCount]
        );
        
        if (empty($keepIds)) {
            return 0;
        }
        
        $keepIdsArray = array_column($keepIds, 'id');
        $placeholders = implode(',', array_fill(0, count($keepIdsArray), '?'));
        
        $sql = "DELETE FROM ai_assistant_responses 
                WHERE conversation_id = ? 
                AND id NOT IN ($placeholders)";
        
        $params = array_merge([$conversationId], $keepIdsArray);
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

