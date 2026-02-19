<?php

namespace App\Models;

use App\Helpers\Database;

class MediaQueue extends Model
{
    protected string $table = 'media_queue';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    
    protected array $fillable = [
        'message_id', 'conversation_id', 'account_id', 'external_message_id',
        'direction', 'media_type', 'status', 'priority', 'payload', 'result',
        'attempts', 'max_attempts', 'error_message', 'next_attempt_at', 'processed_at'
    ];
    
    protected array $jsonFields = ['payload', 'result'];

    public static function enqueue(array $data): int
    {
        $data['status'] = 'queued';
        $data['attempts'] = 0;
        $data['next_attempt_at'] = date('Y-m-d H:i:s');
        return self::create($data);
    }

    /**
     * Buscar próximo item da fila pronto para processar.
     * Respeita rate limit por account_id (1 download por vez por conta).
     */
    public static function getNext(): ?array
    {
        $db = Database::getInstance();
        
        // Resetar itens "processing" travados há mais de 3 minutos
        $db->exec("
            UPDATE media_queue 
            SET status = 'queued', error_message = CONCAT(IFNULL(error_message,''), ' | Reset: stuck processing')
            WHERE status = 'processing' 
              AND updated_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)
        ");
        
        $processing = $db->query("
            SELECT 1 FROM media_queue WHERE status = 'processing' LIMIT 1
        ")->fetch(\PDO::FETCH_ASSOC);
        
        if ($processing) {
            return null;
        }
        
        $nextId = $db->query("
            SELECT id FROM media_queue 
            WHERE status = 'queued' 
              AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
              AND attempts < max_attempts
            ORDER BY priority ASC, id ASC
            LIMIT 1
        ")->fetch(\PDO::FETCH_ASSOC);
        
        if (!$nextId) {
            return null;
        }
        
        $item = $db->query("
            SELECT * FROM media_queue WHERE id = " . (int)$nextId['id']
        )->fetch(\PDO::FETCH_ASSOC);
        
        if (!$item) {
            return null;
        }
        
        // Decodificar campos JSON
        if (!empty($item['payload'])) {
            $item['payload'] = json_decode($item['payload'], true) ?? [];
        }
        if (!empty($item['result'])) {
            $item['result'] = json_decode($item['result'], true) ?? [];
        }
        
        return $item;
    }

    public static function markProcessing(int $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE media_queue 
            SET status = 'processing', attempts = attempts + 1, updated_at = NOW()
            WHERE id = ? AND status = 'queued'
        ");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function markCompleted(int $id, array $result): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE media_queue 
            SET status = 'completed', result = ?, processed_at = NOW(), updated_at = NOW(), error_message = NULL
            WHERE id = ?
        ");
        $stmt->execute([json_encode($result), $id]);
        return $stmt->rowCount() > 0;
    }

    public static function markFailed(int $id, string $error, int $currentAttempts, int $maxAttempts): bool
    {
        $db = Database::getInstance();
        
        if ($currentAttempts >= $maxAttempts) {
            $stmt = $db->prepare("
                UPDATE media_queue 
                SET status = 'failed', error_message = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$error, $id]);
        } else {
            // Backoff exponencial: 30s, 60s, 120s, 240s, 480s...
            $delaySec = min(30 * pow(2, $currentAttempts - 1), 1800);
            $nextAttempt = date('Y-m-d H:i:s', time() + $delaySec);
            
            $stmt = $db->prepare("
                UPDATE media_queue 
                SET status = 'queued', error_message = ?, next_attempt_at = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$error, $nextAttempt, $id]);
        }
        
        return true;
    }

    /**
     * Buscar itens pendentes de uma conversa (para exibir na UI)
     */
    public static function getPendingByConversation(int $conversationId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT id, message_id, direction, media_type, status, attempts, max_attempts, error_message, 
                   created_at, next_attempt_at, external_message_id,
                   JSON_UNQUOTE(JSON_EXTRACT(payload, '$.filename')) as filename,
                   JSON_UNQUOTE(JSON_EXTRACT(payload, '$.mimetype')) as mimetype
            FROM media_queue 
            WHERE conversation_id = ? AND status IN ('queued', 'processing', 'failed')
            ORDER BY created_at DESC
        ");
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Estatísticas gerais da fila
     */
    public static function getStats(): array
    {
        $db = Database::getInstance();
        $stats = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM media_queue
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetch(\PDO::FETCH_ASSOC);
        
        return $stats ?: ['total' => 0, 'queued' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
    }

    /**
     * Limpar itens concluídos antigos (mais de 7 dias)
     */
    public static function cleanup(int $days = 7): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            DELETE FROM media_queue 
            WHERE status IN ('completed', 'cancelled') 
              AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    /**
     * Verificar se já existe um item na fila para esta mensagem
     */
    public static function existsForMessage(string $externalMessageId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM media_queue 
            WHERE external_message_id = ? AND status IN ('queued', 'processing')
        ");
        $stmt->execute([$externalMessageId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ($row['cnt'] ?? 0) > 0;
    }
}
