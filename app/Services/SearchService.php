<?php
/**
 * Service de Busca Global
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\Contact;
use App\Models\Message;
use App\Helpers\Database;
use App\Helpers\Auth;
use App\Helpers\Permission;

class SearchService
{
    /**
     * Busca global
     */
    public static function global(string $query, string $type = 'all', array $filters = []): array
    {
        $userId = Auth::id();
        $results = [
            'conversations' => [],
            'contacts' => [],
            'messages' => []
        ];
        
        // Buscar conversas
        if ($type === 'all' || $type === 'conversations') {
            $results['conversations'] = self::searchConversations($query, $filters, $userId);
        }
        
        // Buscar contatos
        if ($type === 'all' || $type === 'contacts') {
            $results['contacts'] = self::searchContacts($query, $filters);
        }
        
        // Buscar mensagens
        if ($type === 'all' || $type === 'messages') {
            $results['messages'] = self::searchMessages($query, $filters, $userId);
        }
        
        return $results;
    }
    
    /**
     * Buscar conversas
     */
    private static function searchConversations(string $query, array $filters, int $userId): array
    {
        $sql = "SELECT DISTINCT c.*, 
                COUNT(DISTINCT CASE WHEN m.sender_type = 'contact' AND m.read_at IS NULL THEN m.id END) as unread_count,
                GROUP_CONCAT(DISTINCT t.name SEPARATOR ',') as tags
                FROM conversations c
                LEFT JOIN messages m ON m.conversation_id = c.id
                LEFT JOIN conversation_tags ct ON ct.conversation_id = c.id
                LEFT JOIN tags t ON t.id = ct.tag_id
                WHERE 1=1";
        
        $params = [];
        
        // Verificar permissões
        if (!Permission::can('conversations.view.all')) {
            $sql .= " AND (c.assigned_to = ? OR c.assigned_to IS NULL)";
            $params[] = $userId;
        }
        
        // Busca por texto
        if (!empty($query)) {
            $sql .= " AND (
                c.subject LIKE ? OR
                EXISTS (
                    SELECT 1 FROM contacts co WHERE co.id = c.contact_id 
                    AND (co.name LIKE ? OR co.email LIKE ? OR co.phone LIKE ?)
                ) OR
                EXISTS (
                    SELECT 1 FROM messages m2 WHERE m2.conversation_id = c.id 
                    AND m2.content LIKE ?
                )
            )";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filtros
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['channel'])) {
            $sql .= " AND c.channel = ?";
            $params[] = $filters['channel'];
        }
        
        if (!empty($filters['department_id'])) {
            $sql .= " AND c.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['tag_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM conversation_tags ct2 WHERE ct2.conversation_id = c.id AND ct2.tag_id = ?)";
            $params[] = $filters['tag_id'];
        }
        
        if (!empty($filters['agent_id'])) {
            $sql .= " AND c.assigned_to = ?";
            $params[] = $filters['agent_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(c.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(c.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.updated_at DESC";
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $conversations = Database::fetchAll($sql, $params);
        
        // Formatar resultados
        return array_map(function($conv) {
            return [
                'id' => $conv['id'],
                'type' => 'conversation',
                'subject' => $conv['subject'] ?? '',
                'status' => $conv['status'],
                'channel' => $conv['channel'],
                'unread_count' => (int)($conv['unread_count'] ?? 0),
                'created_at' => $conv['created_at'],
                'updated_at' => $conv['updated_at'],
                'tags' => !empty($conv['tags']) ? explode(',', $conv['tags']) : []
            ];
        }, $conversations);
    }
    
    /**
     * Buscar contatos
     */
    private static function searchContacts(string $query, array $filters): array
    {
        $sql = "SELECT * FROM contacts WHERE 1=1";
        $params = [];
        
        // Busca por texto
        if (!empty($query)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR notes LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Filtros
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY updated_at DESC";
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $contacts = Database::fetchAll($sql, $params);
        
        // Formatar resultados
        return array_map(function($contact) {
            return [
                'id' => $contact['id'],
                'type' => 'contact',
                'name' => $contact['name'] ?? '',
                'email' => $contact['email'] ?? '',
                'phone' => $contact['phone'] ?? '',
                'created_at' => $contact['created_at'],
                'updated_at' => $contact['updated_at']
            ];
        }, $contacts);
    }
    
    /**
     * Buscar mensagens
     */
    private static function searchMessages(string $query, array $filters, int $userId): array
    {
        $sql = "SELECT m.*, c.subject as conversation_subject, co.name as contact_name
                FROM messages m
                INNER JOIN conversations c ON c.id = m.conversation_id
                LEFT JOIN contacts co ON co.id = c.contact_id
                WHERE 1=1";
        
        $params = [];
        
        // Verificar permissões
        if (!Permission::can('conversations.view.all')) {
            $sql .= " AND (c.assigned_to = ? OR c.assigned_to IS NULL)";
            $params[] = $userId;
        }
        
        // Busca por texto
        if (!empty($query)) {
            $sql .= " AND m.content LIKE ?";
            $params[] = "%{$query}%";
        }
        
        // Filtros
        if (!empty($filters['message_type'])) {
            $sql .= " AND m.type = ?";
            $params[] = $filters['message_type'];
        }
        
        if (!empty($filters['sender_type'])) {
            $sql .= " AND m.sender_type = ?";
            $params[] = $filters['sender_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(m.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(m.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['has_attachments'])) {
            $sql .= " AND m.attachments IS NOT NULL AND m.attachments != '[]'";
        }
        
        $sql .= " ORDER BY m.created_at DESC";
        
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $messages = Database::fetchAll($sql, $params);
        
        // Formatar resultados
        return array_map(function($msg) {
            return [
                'id' => $msg['id'],
                'type' => 'message',
                'content' => $msg['content'] ?? '',
                'conversation_id' => $msg['conversation_id'],
                'conversation_subject' => $msg['conversation_subject'] ?? '',
                'contact_name' => $msg['contact_name'] ?? '',
                'sender_type' => $msg['sender_type'],
                'created_at' => $msg['created_at']
            ];
        }, $messages);
    }
    
    /**
     * Salvar busca
     */
    public static function saveSearch(string $name, string $query, array $filters): array
    {
        $userId = Auth::id();
        
        $sql = "INSERT INTO saved_searches (user_id, name, query, filters, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $userId,
            $name,
            $query,
            json_encode($filters)
        ];
        
        Database::execute($sql, $params);
        $id = Database::lastInsertId();
        
        return [
            'id' => $id,
            'name' => $name,
            'query' => $query,
            'filters' => $filters
        ];
    }
    
    /**
     * Obter buscas salvas
     */
    public static function getSavedSearches(): array
    {
        $userId = Auth::id();
        
        $sql = "SELECT * FROM saved_searches WHERE user_id = ? ORDER BY updated_at DESC";
        $searches = Database::fetchAll($sql, [$userId]);
        
        return array_map(function($search) {
            return [
                'id' => $search['id'],
                'name' => $search['name'],
                'query' => $search['query'],
                'filters' => json_decode($search['filters'], true) ?? []
            ];
        }, $searches);
    }
    
    /**
     * Deletar busca salva
     */
    public static function deleteSavedSearch(int $id): bool
    {
        $userId = Auth::id();
        
        $sql = "DELETE FROM saved_searches WHERE id = ? AND user_id = ?";
        $affected = Database::execute($sql, [$id, $userId]);
        
        return $affected > 0;
    }
}

