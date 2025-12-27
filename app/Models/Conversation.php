<?php
/**
 * Model Conversation
 */

namespace App\Models;

use App\Helpers\Database;

class Conversation extends Model
{
    protected string $table = 'conversations';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'contact_id', 
        'agent_id', 
        'department_id', 
        'channel', 
        'status', 
        'funnel_id', 
        'funnel_stage_id', 
        'whatsapp_account_id',  // Legacy, manter para compatibilidade
        'integration_account_id', // Novo campo unificado de integração
        'pinned', 
        'pinned_at', 
        'is_spam', 
        'spam_marked_at', 
        'spam_marked_by', 
        'metadata', 
        'priority',
        'assigned_at',
        'resolved_at',
        'moved_at'
    ];
    protected array $hidden = [];
    protected bool $timestamps = true;

    /**
     * Obter conversas do usuário logado
     */
    public static function getByAgent(int $agentId, array $filters = []): array
    {
        $sql = "SELECT c.*, 
                       ct.name as contact_name, ct.phone as contact_phone, ct.email as contact_email, ct.avatar as contact_avatar,
                       CASE 
                           WHEN c.agent_id IS NULL OR c.agent_id = 0 THEN NULL 
                           ELSE COALESCE(u.name, CONCAT('Agente #', c.agent_id)) 
                       END as agent_name,
                       u.email as agent_email,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'contact' AND m.read_at IS NULL) as unread_count,
                       (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_at
                FROM conversations c
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users u ON c.agent_id = u.id
                WHERE c.agent_id = ?";
        
        $params = [$agentId];
        
        // Aplicar filtros
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['channel'])) {
            $sql .= " AND c.channel = ?";
            $params[] = $filters['channel'];
        }
        
        // Ordenar já com o mesmo critério usado no frontend:
        // 1) Fixadas primeiro
        // 2) Dentro das fixadas, usar pinned_at DESC
        // 3) Demais conversas por updated_at DESC
        // 4) Critério de desempate: ID DESC (conversas mais recentes primeiro)
        $sql .= " ORDER BY COALESCE(c.pinned, 0) DESC, c.pinned_at DESC, c.updated_at DESC, c.id DESC";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Obter todas as conversas (com filtros)
     */
    public static function getAll(array $filters = []): array
    {
        try {
            $sql = "SELECT c.*, 
                       ct.name as contact_name, ct.phone as contact_phone, ct.email as contact_email, ct.avatar as contact_avatar,
                       CASE 
                           WHEN c.agent_id IS NULL OR c.agent_id = 0 THEN NULL 
                           ELSE COALESCE(u.name, CONCAT('Agente #', c.agent_id)) 
                       END as agent_name,
                       u.email as agent_email,
                       wa.name as whatsapp_account_name, wa.phone_number as whatsapp_account_phone,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'contact' AND m.read_at IS NULL) as unread_count,
                       (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
                       (SELECT created_at FROM messages m WHERE m.conversation_id = c.id AND m.sender_type IN ('agent', 'ai_agent') ORDER BY m.created_at ASC LIMIT 1) as first_response_at_calc,
                       (SELECT created_at FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'contact' ORDER BY m.created_at DESC LIMIT 1) as last_contact_message_at,
                       (SELECT created_at FROM messages m WHERE m.conversation_id = c.id AND m.sender_type IN ('agent','ai_agent') ORDER BY m.created_at DESC LIMIT 1) as last_agent_message_at,
                       GROUP_CONCAT(DISTINCT CONCAT(t.id, ':', t.name, ':', COALESCE(t.color, '#009ef7')) SEPARATOR '|||') as tags_data,
                       GROUP_CONCAT(DISTINCT CONCAT(cp.user_id, ':', cp_user.name) SEPARATOR '|||') as participants_data,
                       COALESCE(c.pinned, 0) as pinned,
                       c.pinned_at
                FROM conversations c
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users u ON c.agent_id = u.id
                LEFT JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id
                LEFT JOIN conversation_tags ctt ON c.id = ctt.conversation_id
                LEFT JOIN tags t ON ctt.tag_id = t.id
                LEFT JOIN conversation_participants cp ON c.id = cp.conversation_id AND cp.removed_at IS NULL
                LEFT JOIN users cp_user ON cp.user_id = cp_user.id
                WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        // Filtro de spam (se status = 'spam', filtrar por is_spam = 1)
        if (!empty($filters['is_spam'])) {
            $sql .= " AND c.is_spam = 1";
        } elseif (!empty($filters['status']) && $filters['status'] !== 'spam') {
            // Se não é filtro de spam, aplicar filtro de status normal
            // Mas excluir spam quando filtrar por outros status
            $sql .= " AND c.status = ? AND (c.is_spam IS NULL OR c.is_spam = 0)";
            $params[] = $filters['status'];
        } elseif (empty($filters['is_spam']) && empty($filters['status'])) {
            // Por padrão, excluir spam da listagem normal
            $sql .= " AND (c.is_spam IS NULL OR c.is_spam = 0)";
        }
        
        // Filtro por canal (suporta array para multi-select)
        if (!empty($filters['channels']) && is_array($filters['channels'])) {
            $placeholders = implode(',', array_fill(0, count($filters['channels']), '?'));
            $sql .= " AND c.channel IN ($placeholders)";
            $params = array_merge($params, $filters['channels']);
        } elseif (!empty($filters['channel'])) {
            $sql .= " AND c.channel = ?";
            $params[] = $filters['channel'];
        }
        
        // Filtro por agente (suporta array para multi-select)
        if (!empty($filters['agent_ids']) && is_array($filters['agent_ids'])) {
            $hasUnassigned = in_array('unassigned', $filters['agent_ids']);
            $agentIds = array_filter($filters['agent_ids'], function($id) {
                return $id !== 'unassigned' && $id !== '0';
            });
            
            if ($hasUnassigned && !empty($agentIds)) {
                // Tem ambos: não atribuídas E agentes específicos
                $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
                $sql .= " AND ((c.agent_id IS NULL OR c.agent_id = 0) OR c.agent_id IN ($placeholders))";
                $params = array_merge($params, array_map('intval', $agentIds));
            } elseif ($hasUnassigned) {
                // Apenas não atribuídas
                $sql .= " AND (c.agent_id IS NULL OR c.agent_id = 0)";
            } elseif (!empty($agentIds)) {
                // Apenas agentes específicos
                $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
                $sql .= " AND c.agent_id IN ($placeholders)";
                $params = array_merge($params, array_map('intval', $agentIds));
            }
        } elseif (isset($filters['agent_id'])) {
            $agentId = $filters['agent_id'];
            if ($agentId === '0' || $agentId === 0 || $agentId === 'unassigned') {
                $sql .= " AND (c.agent_id IS NULL OR c.agent_id = 0)";
            } else {
                $sql .= " AND c.agent_id = ?";
                $params[] = $agentId;
            }
        }
        
        if (!empty($filters['department_id'])) {
            $sql .= " AND c.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        // Filtro por tags (suporta array para multi-select)
        if (!empty($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['tag_ids']), '?'));
            $sql .= " AND EXISTS (SELECT 1 FROM conversation_tags ctt2 WHERE ctt2.conversation_id = c.id AND ctt2.tag_id IN ($placeholders))";
            $params = array_merge($params, $filters['tag_ids']);
        } elseif (!empty($filters['tag_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM conversation_tags ctt2 WHERE ctt2.conversation_id = c.id AND ctt2.tag_id = ?)";
            $params[] = $filters['tag_id'];
        }
        
        // Filtro por conta WhatsApp (suporta array para multi-select)
        if (!empty($filters['whatsapp_account_ids']) && is_array($filters['whatsapp_account_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['whatsapp_account_ids']), '?'));
            $sql .= " AND c.whatsapp_account_id IN ($placeholders)";
            $params = array_merge($params, $filters['whatsapp_account_ids']);
        } elseif (!empty($filters['whatsapp_account_id'])) {
            $sql .= " AND c.whatsapp_account_id = ?";
            $params[] = $filters['whatsapp_account_id'];
        }

        // Filtro por funil (suporta array para multi-select)
        if (!empty($filters['funnel_ids']) && is_array($filters['funnel_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['funnel_ids']), '?'));
            $sql .= " AND c.funnel_id IN ($placeholders)";
            $params = array_merge($params, $filters['funnel_ids']);
        } elseif (!empty($filters['funnel_id'])) {
            $sql .= " AND c.funnel_id = ?";
            $params[] = $filters['funnel_id'];
        }
        // Filtro por etapa do funil (suporta array para multi-select)
        if (!empty($filters['funnel_stage_ids']) && is_array($filters['funnel_stage_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['funnel_stage_ids']), '?'));
            $sql .= " AND c.funnel_stage_id IN ($placeholders)";
            $params = array_merge($params, $filters['funnel_stage_ids']);
        } elseif (!empty($filters['funnel_stage_id'])) {
            $sql .= " AND c.funnel_stage_id = ?";
            $params[] = $filters['funnel_stage_id'];
        }
        
        // Busca avançada (nome, telefone, email, mensagens, tags, participantes)
        $searchTerm = null;
        if (!empty($filters['search'])) {
            $searchTerm = trim($filters['search']);
            if (!empty($searchTerm)) {
                $search = "%{$searchTerm}%";
                $sql .= " AND (
                    ct.name LIKE ? OR 
                    ct.phone LIKE ? OR 
                    ct.email LIKE ? OR
                    EXISTS (
                        SELECT 1 FROM messages m 
                        WHERE m.conversation_id = c.id 
                        AND m.content LIKE ?
                    ) OR
                    EXISTS (
                        SELECT 1 FROM conversation_tags ctt_search 
                        INNER JOIN tags t_search ON ctt_search.tag_id = t_search.id
                        WHERE ctt_search.conversation_id = c.id 
                        AND t_search.name LIKE ?
                    ) OR
                    EXISTS (
                        SELECT 1 FROM conversation_participants cp_search 
                        INNER JOIN users u_search ON cp_search.user_id = u_search.id
                        WHERE cp_search.conversation_id = c.id 
                        AND cp_search.removed_at IS NULL
                        AND (u_search.name LIKE ? OR u_search.email LIKE ?)
                    )
                )";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search; // tags
                $params[] = $search; // participantes nome
                $params[] = $search; // participantes email
                
                // Log para debug
                \App\Helpers\Log::debug("Aplicando filtro de busca: '{$searchTerm}'", 'conversas.log');
            }
        }
        
        // Filtro: Sem resposta (última mensagem é do contato e não foi respondida)
        if (!empty($filters['unanswered'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM messages m1 
                WHERE m1.conversation_id = c.id 
                AND m1.sender_type = 'contact'
                AND m1.created_at = (
                    SELECT MAX(m2.created_at) 
                    FROM messages m2 
                    WHERE m2.conversation_id = c.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM messages m3 
                    WHERE m3.conversation_id = c.id 
                    AND m3.sender_type = 'agent'
                    AND m3.created_at > m1.created_at
                )
            )";
        }
        
        // Filtro: Respondido (última mensagem é do agente)
        if (!empty($filters['answered'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM messages m1 
                WHERE m1.conversation_id = c.id 
                AND m1.sender_type = 'agent'
                AND m1.created_at = (
                    SELECT MAX(m2.created_at) 
                    FROM messages m2 
                    WHERE m2.conversation_id = c.id
                )
            )";
        }
        
        // Filtro por período (data de criação ou última mensagem)
        if (!empty($filters['date_from'])) {
            $sql .= " AND (
                c.created_at >= ? OR 
                EXISTS (
                    SELECT 1 FROM messages m 
                    WHERE m.conversation_id = c.id 
                    AND m.created_at >= ?
                )
            )";
            $params[] = $filters['date_from'];
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND (
                c.created_at <= ? OR 
                EXISTS (
                    SELECT 1 FROM messages m 
                    WHERE m.conversation_id = c.id 
                    AND m.created_at <= ?
                )
            )";
            $params[] = $filters['date_to'] . ' 23:59:59';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Filtro por pinned (fixadas)
        if (isset($filters['pinned'])) {
            if ($filters['pinned'] === true || $filters['pinned'] === '1' || $filters['pinned'] === 1) {
                $sql .= " AND c.pinned = 1";
            } else {
                $sql .= " AND (c.pinned = 0 OR c.pinned IS NULL)";
            }
        }
        
        $sql .= " GROUP BY c.id";
        
        // Ordenação: pinned primeiro, depois por updated_at
        if (!empty($filters['order_by'])) {
            $orderBy = $filters['order_by'];
            $orderDir = !empty($filters['order_dir']) && strtoupper($filters['order_dir']) === 'ASC' ? 'ASC' : 'DESC';
            
            if ($orderBy === 'pinned') {
                $sql .= " ORDER BY c.pinned DESC, c.pinned_at DESC, c.updated_at DESC, c.id DESC";
            } elseif ($orderBy === 'last_message') {
                $sql .= " ORDER BY c.pinned DESC, last_message_at {$orderDir}, c.id DESC";
            } else {
                $sql .= " ORDER BY c.pinned DESC, c.{$orderBy} {$orderDir}, c.id DESC";
            }
        } else {
            // Ordenação padrão: pinned primeiro, depois updated_at, depois ID (critério de desempate)
            $sql .= " ORDER BY c.pinned DESC, c.pinned_at DESC, c.updated_at DESC, c.id DESC";
        }
        
        // Paginação
        if (!empty($filters['limit'])) {
            $limit = (int)$filters['limit'];
            $offset = !empty($filters['offset']) ? (int)$filters['offset'] : 0;
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        // Log da query SQL e parâmetros antes de executar
        \App\Helpers\Log::debug("SQL Query: " . substr($sql, 0, 500), 'conversas.log');
        \App\Helpers\Log::context("SQL Params", $params, 'conversas.log', 'DEBUG');
        
        $conversations = Database::fetchAll($sql, $params);
        
        \App\Helpers\Log::debug("Conversas retornadas do banco: " . count($conversations), 'conversas.log');
        
        // Se houver busca, identificar qual campo fez match
        if ($searchTerm !== null && !empty($searchTerm)) {
            foreach ($conversations as &$conversation) {
                $matchType = null;
                $matchText = null;
                
                // Verificar nome
                if (!empty($conversation['contact_name']) && mb_stripos($conversation['contact_name'], $searchTerm) !== false) {
                    $matchType = 'name';
                    $matchText = $conversation['contact_name'];
                }
                // Verificar telefone
                elseif (!empty($conversation['contact_phone']) && mb_stripos($conversation['contact_phone'], $searchTerm) !== false) {
                    $matchType = 'phone';
                    $matchText = $conversation['contact_phone'];
                }
                // Verificar email
                elseif (!empty($conversation['contact_email']) && mb_stripos($conversation['contact_email'], $searchTerm) !== false) {
                    $matchType = 'email';
                    $matchText = $conversation['contact_email'];
                }
                // Verificar tags
                elseif (!empty($conversation['tags_data'])) {
                    $tagsData = explode('|||', $conversation['tags_data']);
                    foreach ($tagsData as $tagData) {
                        if (!empty($tagData)) {
                            $parts = explode(':', $tagData, 3);
                            if (count($parts) >= 2 && mb_stripos($parts[1], $searchTerm) !== false) {
                                $matchType = 'tag';
                                $matchText = $parts[1];
                                break;
                            }
                        }
                    }
                }
                // Verificar participantes
                elseif (!empty($conversation['participants_data'])) {
                    $participantsData = explode('|||', $conversation['participants_data']);
                    foreach ($participantsData as $participantData) {
                        if (!empty($participantData)) {
                            $parts = explode(':', $participantData, 2);
                            if (count($parts) >= 2 && mb_stripos($parts[1], $searchTerm) !== false) {
                                $matchType = 'participant';
                                $matchText = $parts[1];
                                break;
                            }
                        }
                    }
                }
                // Verificar mensagens
                if ($matchType === null) {
                    // Buscar mensagem que contém o termo
                    $messageSql = "SELECT content FROM messages WHERE conversation_id = ? AND content LIKE ? LIMIT 1";
                    $messageParams = [$conversation['id'], "%{$searchTerm}%"];
                    $message = Database::fetch($messageSql, $messageParams);
                    if ($message && !empty($message['content'])) {
                        $matchType = 'message';
                        $matchText = $message['content'];
                    }
                }
                
                $conversation['search_match_type'] = $matchType;
                $conversation['search_match_text'] = $matchText;
            }
            unset($conversation); // Liberar referência
        }
        
        // Processar tags para cada conversa
        foreach ($conversations as &$conversation) {
            $tags = [];
            if (!empty($conversation['tags_data'])) {
                $tagsData = explode('|||', $conversation['tags_data']);
                foreach ($tagsData as $tagData) {
                    if (!empty($tagData)) {
                        $parts = explode(':', $tagData, 3);
                        if (count($parts) >= 2) {
                            $tags[] = [
                                'id' => (int)$parts[0],
                                'name' => $parts[1],
                                'color' => $parts[2] ?? '#009ef7'
                            ];
                        }
                    }
                }
            }
            $conversation['tags'] = $tags;
            // Manter tags_data disponível para renderização rápida na lista (JS/PHP)
        }
        
        return $conversations;
        } catch (\Exception $e) {
            \App\Helpers\Log::error("Erro em Conversation::getAll: " . $e->getMessage(), 'conversas.log');
            \App\Helpers\Log::error("SQL: " . ($sql ?? 'não definido'), 'conversas.log');
            \App\Helpers\Log::context("Params", $params ?? [], 'conversas.log', 'ERROR');
            \App\Helpers\Log::context("Filtros", $filters, 'conversas.log', 'ERROR');
            throw $e;
        }
    }

    /**
     * Obter conversa com relacionamentos
     */
    public static function findWithRelations(int $id): ?array
    {
        $sql = "SELECT c.*, 
                       ct.name as contact_name, ct.phone as contact_phone, ct.email as contact_email, ct.avatar as contact_avatar,
                       CASE 
                           WHEN c.agent_id IS NULL OR c.agent_id = 0 THEN NULL 
                           ELSE COALESCE(u.name, CONCAT('Agente #', c.agent_id)) 
                       END as agent_name,
                       u.email as agent_email, u.avatar as agent_avatar,
                       wa.name as whatsapp_account_name, wa.phone_number as whatsapp_account_phone,
                       f.name as funnel_name,
                       fs.name as stage_name,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'contact' AND m.read_at IS NULL) as unread_count
                FROM conversations c
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users u ON c.agent_id = u.id
                LEFT JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id
                LEFT JOIN funnels f ON c.funnel_id = f.id
                LEFT JOIN funnel_stages fs ON c.funnel_stage_id = fs.id
                WHERE c.id = ?";
        
        return Database::fetch($sql, [$id]);
    }

    /**
     * Atribuir conversa a um agente
     */
    public static function assignToAgent(int $conversationId, int $agentId): bool
    {
        return self::update($conversationId, [
            'agent_id' => $agentId,
            'status' => 'open'
        ]);
    }

    /**
     * Fechar conversa
     */
    public static function close(int $conversationId): bool
    {
        return self::update($conversationId, [
            'status' => 'closed'
        ]);
    }

    /**
     * Reabrir conversa
     */
    public static function reopen(int $conversationId): bool
    {
        return self::update($conversationId, [
            'status' => 'open'
        ]);
    }

    /**
     * Buscar conversa por contato e canal
     */
    public static function findByContactAndChannel(int $contactId, string $channel, ?int $whatsappAccountId = null): ?array
    {
        $sql = "SELECT * FROM conversations 
                WHERE contact_id = ? AND channel = ?";
        $params = [$contactId, $channel];
        
        if ($whatsappAccountId) {
            $sql .= " AND whatsapp_account_id = ?";
            $params[] = $whatsappAccountId;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 1";
        
        // Usar fetch ao invés de fetchOne (que não existe)
        return Database::fetch($sql, $params);
    }
}

