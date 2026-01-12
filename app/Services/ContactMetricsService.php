<?php
/**
 * Service ContactMetricsService
 * Calcula e armazena métricas de contatos de forma inteligente
 */

namespace App\Services;

use App\Helpers\Database;
use App\Models\ContactMetric;

class ContactMetricsService
{
    /**
     * Calcular métricas de um contato específico
     */
    public static function calculateForContact(int $contactId): array
    {
        // Calcular métricas (mesma query pesada, mas executada em background)
        $stats = Database::fetch("
            SELECT 
                COUNT(DISTINCT c.id) AS total_conversations,
                COUNT(DISTINCT CASE WHEN c.status IN ('open', 'pending') THEN c.id END) AS open_conversations,
                COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) AS closed_conversations,
                AVG(response_times.response_time_minutes) AS avg_response_time_minutes,
                MAX(m.created_at) AS last_message_at
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            LEFT JOIN (
                SELECT 
                    m1.conversation_id,
                    AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as response_time_minutes
                FROM messages m1
                INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                    AND m2.sender_type = 'agent'
                    AND m2.created_at > m1.created_at
                    AND m2.created_at = (
                        SELECT MIN(m3.created_at)
                        FROM messages m3
                        WHERE m3.conversation_id = m1.conversation_id
                        AND m3.sender_type = 'agent'
                        AND m3.created_at > m1.created_at
                    )
                WHERE m1.sender_type = 'contact'
                GROUP BY m1.conversation_id
            ) response_times ON response_times.conversation_id = c.id
            WHERE c.contact_id = ?
        ", [$contactId]);
        
        if (!$stats) {
            $stats = [
                'total_conversations' => 0,
                'open_conversations' => 0,
                'closed_conversations' => 0,
                'avg_response_time_minutes' => null,
                'last_message_at' => null
            ];
        }
        
        // Obter última conversa para saber o status
        $lastConv = Database::fetch(
            "SELECT status FROM conversations WHERE contact_id = ? ORDER BY updated_at DESC LIMIT 1",
            [$contactId]
        );
        
        $hasOpen = (int)$stats['open_conversations'] > 0;
        $lastStatus = $lastConv['status'] ?? null;
        
        // Salvar ou atualizar métricas
        $existing = ContactMetric::getByContact($contactId);
        
        $data = [
            'total_conversations' => (int)$stats['total_conversations'],
            'open_conversations' => (int)$stats['open_conversations'],
            'closed_conversations' => (int)$stats['closed_conversations'],
            'avg_response_time_minutes' => $stats['avg_response_time_minutes'] ? round((float)$stats['avg_response_time_minutes'], 2) : null,
            'last_message_at' => $stats['last_message_at'],
            'last_calculated_at' => date('Y-m-d H:i:s'),
            'needs_recalculation' => 0,
            'calculation_priority' => $hasOpen ? 2 : 0, // 2 se tem aberta, 0 se tudo fechado
            'has_open_conversations' => $hasOpen,
            'last_conversation_status' => $lastStatus
        ];
        
        if ($existing) {
            Database::execute(
                "UPDATE contact_metrics SET
                    total_conversations = ?,
                    open_conversations = ?,
                    closed_conversations = ?,
                    avg_response_time_minutes = ?,
                    last_message_at = ?,
                    last_calculated_at = ?,
                    needs_recalculation = ?,
                    calculation_priority = ?,
                    has_open_conversations = ?,
                    last_conversation_status = ?
                 WHERE contact_id = ?",
                [
                    $data['total_conversations'],
                    $data['open_conversations'],
                    $data['closed_conversations'],
                    $data['avg_response_time_minutes'],
                    $data['last_message_at'],
                    $data['last_calculated_at'],
                    $data['needs_recalculation'],
                    $data['calculation_priority'],
                    $data['has_open_conversations'],
                    $data['last_conversation_status'],
                    $contactId
                ]
            );
        } else {
            $data['contact_id'] = $contactId;
            Database::execute(
                "INSERT INTO contact_metrics 
                 (contact_id, total_conversations, open_conversations, closed_conversations, 
                  avg_response_time_minutes, last_message_at, last_calculated_at, 
                  needs_recalculation, calculation_priority, has_open_conversations, 
                  last_conversation_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['contact_id'],
                    $data['total_conversations'],
                    $data['open_conversations'],
                    $data['closed_conversations'],
                    $data['avg_response_time_minutes'],
                    $data['last_message_at'],
                    $data['last_calculated_at'],
                    $data['needs_recalculation'],
                    $data['calculation_priority'],
                    $data['has_open_conversations'],
                    $data['last_conversation_status']
                ]
            );
        }
        
        return $data;
    }
    
    /**
     * Processar lote de contatos que precisam de recálculo
     */
    public static function processBatch(int $limit = 100): array
    {
        $results = [
            'processed' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        // 1. Obter contatos que precisam de recálculo
        $contacts = ContactMetric::getContactsNeedingRecalculation($limit);
        
        foreach ($contacts as $metric) {
            try {
                self::calculateForContact($metric['contact_id']);
                $results['processed']++;
            } catch (\Exception $e) {
                $results['errors']++;
                error_log("Erro ao calcular métricas do contato {$metric['contact_id']}: " . $e->getMessage());
            }
        }
        
        // 2. Verificar se há conversas abertas sem métricas
        if ($results['processed'] < $limit) {
            $remaining = $limit - $results['processed'];
            $openContacts = ContactMetric::getOpenConversationsNeedingCalculation($remaining);
            
            foreach ($openContacts as $contact) {
                try {
                    self::calculateForContact($contact['contact_id']);
                    $results['processed']++;
                } catch (\Exception $e) {
                    $results['errors']++;
                    error_log("Erro ao calcular métricas do contato {$contact['contact_id']}: " . $e->getMessage());
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Marcar contato para recálculo quando houver nova mensagem
     * Deve ser chamado após criar uma mensagem
     */
    public static function onNewMessage(int $contactId, bool $isUrgent = false): void
    {
        $priority = $isUrgent ? 3 : 2; // 3 = urgente, 2 = normal
        ContactMetric::markForRecalculation($contactId, $priority);
    }
    
    /**
     * Marcar contato para recálculo quando conversa for fechada
     * Deve ser chamado após fechar uma conversa
     */
    public static function onConversationClosed(int $contactId): void
    {
        // Prioridade 1 = baixa (conversas fechadas são menos urgentes)
        ContactMetric::markForRecalculation($contactId, 1);
    }
}
