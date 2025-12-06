<?php
/**
 * Model Activity
 * Histórico de atividades e auditoria
 */

namespace App\Models;

class Activity extends Model
{
    protected string $table = 'activities';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'activity_type', 'entity_type', 'entity_id', 'description', 'metadata', 'ip_address', 'user_agent'];
    protected bool $timestamps = false; // Usa created_at manualmente

    /**
     * Criar atividade
     */
    public static function log(string $activityType, string $entityType, ?int $entityId = null, ?int $userId = null, ?string $description = null, array $metadata = []): int
    {
        // Se não informado, pegar usuário logado
        if ($userId === null) {
            $userId = \App\Helpers\Auth::id();
        }

        // Obter IP e User Agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $data = [
            'user_id' => $userId,
            'activity_type' => $activityType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if (!empty($metadata)) {
            $data['metadata'] = json_encode($metadata);
        }

        return self::create($data);
    }

    /**
     * Obter atividades por usuário
     */
    public static function getByUser(int $userId, array $filters = []): array
    {
        $sql = "SELECT a.*, u.name as user_name, u.email as user_email
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.user_id = ?";
        
        $params = [$userId];

        if (!empty($filters['activity_type'])) {
            $sql .= " AND a.activity_type = ?";
            $params[] = $filters['activity_type'];
        }

        if (!empty($filters['entity_type'])) {
            $sql .= " AND a.entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY a.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        $activities = \App\Helpers\Database::fetchAll($sql, $params);
        
        // Decodificar metadata
        foreach ($activities as &$activity) {
            if (!empty($activity['metadata'])) {
                $activity['metadata'] = json_decode($activity['metadata'], true);
            } else {
                $activity['metadata'] = [];
            }
        }

        return $activities;
    }

    /**
     * Obter atividades por entidade
     */
    public static function getByEntity(string $entityType, int $entityId, array $filters = []): array
    {
        $sql = "SELECT a.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.entity_type = ? AND a.entity_id = ?";
        
        $params = [$entityType, $entityId];

        if (!empty($filters['activity_type'])) {
            $sql .= " AND a.activity_type = ?";
            $params[] = $filters['activity_type'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY a.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        $activities = \App\Helpers\Database::fetchAll($sql, $params);
        
        // Decodificar metadata
        foreach ($activities as &$activity) {
            if (!empty($activity['metadata'])) {
                $activity['metadata'] = json_decode($activity['metadata'], true);
            } else {
                $activity['metadata'] = [];
            }
        }

        return $activities;
    }

    /**
     * Obter todas as atividades (com filtros)
     */
    public static function getAll(array $filters = []): array
    {
        $sql = "SELECT a.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar
                FROM activities a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['activity_type'])) {
            $sql .= " AND a.activity_type = ?";
            $params[] = $filters['activity_type'];
        }

        if (!empty($filters['entity_type'])) {
            $sql .= " AND a.entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $sql .= " AND a.entity_id = ?";
            $params[] = $filters['entity_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (a.description LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY a.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        $activities = \App\Helpers\Database::fetchAll($sql, $params);
        
        // Decodificar metadata
        foreach ($activities as &$activity) {
            if (!empty($activity['metadata'])) {
                $activity['metadata'] = json_decode($activity['metadata'], true);
            } else {
                $activity['metadata'] = [];
            }
        }

        return $activities;
    }

    /**
     * Obter estatísticas de atividades por usuário
     */
    public static function getStatsByUser(int $userId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT 
                    activity_type,
                    COUNT(*) as count,
                    MIN(created_at) as first_activity,
                    MAX(created_at) as last_activity
                FROM activities
                WHERE user_id = ?";
        
        $params = [$userId];

        if ($dateFrom) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateTo;
        }

        $sql .= " GROUP BY activity_type ORDER BY count DESC";

        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Obter tipos de atividade disponíveis
     */
    public static function getActivityTypes(): array
    {
        return [
            'conversation_assigned' => 'Conversa Atribuída',
            'conversation_closed' => 'Conversa Fechada',
            'conversation_reopened' => 'Conversa Reaberta',
            'conversation_created' => 'Conversa Criada',
            'message_sent' => 'Mensagem Enviada',
            'tag_added' => 'Tag Adicionada',
            'tag_removed' => 'Tag Removida',
            'stage_moved' => 'Movido no Funil',
            'user_created' => 'Usuário Criado',
            'user_updated' => 'Usuário Atualizado',
            'user_deleted' => 'Usuário Deletado',
            'contact_created' => 'Contato Criado',
            'contact_updated' => 'Contato Atualizado',
            'contact_deleted' => 'Contato Deletado',
            'role_assigned' => 'Role Atribuída',
            'role_removed' => 'Role Removida',
            'department_assigned' => 'Setor Atribuído',
            'department_removed' => 'Setor Removido',
            'availability_changed' => 'Status de Disponibilidade Alterado',
            'settings_updated' => 'Configurações Atualizadas',
            'automation_executed' => 'Automação Executada',
            'funnel_created' => 'Funil Criado',
            'funnel_updated' => 'Funil Atualizado',
            'stage_created' => 'Estágio Criado',
            'stage_updated' => 'Estágio Atualizado',
            'stage_deleted' => 'Estágio Deletado'
        ];
    }
}

