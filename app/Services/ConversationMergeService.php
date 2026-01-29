<?php
/**
 * Service ConversationMergeService
 * Gerencia a mesclagem de conversas quando o mesmo contato fala por múltiplos números
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\Database;
use App\Helpers\Logger;

class ConversationMergeService
{
    /**
     * Verificar se o contato tem outras conversas abertas em outros números
     * 
     * @param int $conversationId ID da conversa atual
     * @return array Lista de outras conversas abertas do mesmo contato
     */
    public static function getOtherOpenConversations(int $conversationId): array
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return [];
        }
        
        $sql = "SELECT c.*, 
                       ia.name as account_name, 
                       ia.phone_number as account_phone,
                       wa.name as wa_account_name,
                       wa.phone_number as wa_account_phone,
                       (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count
                FROM conversations c
                LEFT JOIN integration_accounts ia ON c.integration_account_id = ia.id
                LEFT JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id
                WHERE c.contact_id = ? 
                  AND c.status = 'open'
                  AND c.id != ?
                  AND c.channel = 'whatsapp'
                ORDER BY c.updated_at DESC";
        
        return Database::fetchAll($sql, [$conversation['contact_id'], $conversationId]);
    }
    
    /**
     * Verificar se deve mostrar aviso de múltiplas conversas
     * 
     * @param int $conversationId ID da conversa atual
     * @return array|null Informações sobre outras conversas ou null se não houver
     */
    public static function checkMultipleConversations(int $conversationId): ?array
    {
        $others = self::getOtherOpenConversations($conversationId);
        
        if (empty($others)) {
            return null;
        }
        
        return [
            'has_others' => true,
            'count' => count($others),
            'conversations' => array_map(function($conv) {
                return [
                    'id' => $conv['id'],
                    'account_name' => $conv['account_name'] ?? $conv['wa_account_name'] ?? 'Desconhecido',
                    'account_phone' => $conv['account_phone'] ?? $conv['wa_account_phone'] ?? '',
                    'message_count' => $conv['message_count'],
                    'updated_at' => $conv['updated_at']
                ];
            }, $others)
        ];
    }
    
    /**
     * Mesclar conversas - Move mensagens de outras conversas para a principal
     * 
     * @param int $targetConversationId ID da conversa principal (destino)
     * @param array $sourceConversationIds IDs das conversas a serem mescladas (origem)
     * @return array Resultado da mesclagem
     */
    public static function merge(int $targetConversationId, array $sourceConversationIds): array
    {
        $target = Conversation::find($targetConversationId);
        if (!$target) {
            throw new \Exception('Conversa de destino não encontrada');
        }
        
        Logger::info("ConversationMergeService::merge - Iniciando mesclagem: target={$targetConversationId}, sources=" . json_encode($sourceConversationIds));
        
        $db = Database::getInstance();
        $db->beginTransaction();
        
        try {
            $totalMessagesMoved = 0;
            $linkedAccountIds = [];
            
            // Adicionar conta atual da conversa principal aos links
            if (!empty($target['integration_account_id'])) {
                $linkedAccountIds[] = (int)$target['integration_account_id'];
            }
            if (!empty($target['whatsapp_account_id'])) {
                $linkedAccountIds[] = (int)$target['whatsapp_account_id'];
            }
            
            // Se já tinha links, manter
            if (!empty($target['linked_account_ids'])) {
                $existing = json_decode($target['linked_account_ids'], true) ?? [];
                $linkedAccountIds = array_merge($linkedAccountIds, $existing);
            }
            
            foreach ($sourceConversationIds as $sourceId) {
                $source = Conversation::find($sourceId);
                if (!$source) {
                    Logger::warning("ConversationMergeService::merge - Conversa origem não encontrada: {$sourceId}");
                    continue;
                }
                
                // Verificar se é do mesmo contato
                if ($source['contact_id'] != $target['contact_id']) {
                    Logger::warning("ConversationMergeService::merge - Conversa {$sourceId} é de outro contato, ignorando");
                    continue;
                }
                
                // Coletar IDs das contas da conversa origem
                if (!empty($source['integration_account_id'])) {
                    $linkedAccountIds[] = (int)$source['integration_account_id'];
                }
                if (!empty($source['whatsapp_account_id'])) {
                    $linkedAccountIds[] = (int)$source['whatsapp_account_id'];
                }
                
                // Mover mensagens
                $moveResult = Database::execute(
                    "UPDATE messages SET conversation_id = ?, via_account_id = COALESCE(via_account_id, ?) WHERE conversation_id = ?",
                    [$targetConversationId, $source['integration_account_id'] ?? $source['whatsapp_account_id'], $sourceId]
                );
                
                $movedCount = $moveResult ? Database::getInstance()->query("SELECT ROW_COUNT()")->fetchColumn() : 0;
                $totalMessagesMoved += $movedCount;
                
                Logger::info("ConversationMergeService::merge - Movidas {$movedCount} mensagens de {$sourceId} para {$targetConversationId}");
                
                // Remover ai_conversations associadas
                Database::execute("DELETE FROM ai_conversations WHERE conversation_id = ?", [$sourceId]);
                
                // Deletar conversa origem
                Database::execute("DELETE FROM conversations WHERE id = ?", [$sourceId]);
                
                Logger::info("ConversationMergeService::merge - Conversa {$sourceId} deletada");
            }
            
            // Remover duplicatas dos linked_account_ids
            $linkedAccountIds = array_values(array_unique($linkedAccountIds));
            
            // Atualizar conversa principal
            Conversation::update($targetConversationId, [
                'is_merged' => 1,
                'linked_account_ids' => json_encode($linkedAccountIds)
            ]);
            
            $db->commit();
            
            Logger::info("ConversationMergeService::merge - Mesclagem concluída: {$totalMessagesMoved} mensagens movidas, linked_accounts=" . json_encode($linkedAccountIds));
            
            return [
                'success' => true,
                'messages_moved' => $totalMessagesMoved,
                'linked_account_ids' => $linkedAccountIds,
                'conversations_merged' => count($sourceConversationIds)
            ];
            
        } catch (\Exception $e) {
            $db->rollBack();
            Logger::error("ConversationMergeService::merge - Erro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar conversa mesclada para um contato e conta
     * Usado pelo webhook para rotear mensagens
     * 
     * @param int $contactId ID do contato
     * @param int $accountId ID da conta de integração ou WhatsApp
     * @return array|null Conversa mesclada ou null
     */
    public static function findMergedConversation(int $contactId, int $accountId): ?array
    {
        // Buscar conversa aberta que tenha essa conta nos linked_account_ids
        $sql = "SELECT * FROM conversations 
                WHERE contact_id = ? 
                  AND status = 'open'
                  AND is_merged = 1
                  AND (
                      JSON_CONTAINS(linked_account_ids, ?) 
                      OR JSON_CONTAINS(linked_account_ids, ?)
                  )
                ORDER BY updated_at DESC
                LIMIT 1";
        
        return Database::fetch($sql, [$contactId, json_encode($accountId), "\"$accountId\""]);
    }
    
    /**
     * Atualizar o último número usado pelo cliente
     * 
     * @param int $conversationId ID da conversa
     * @param int $accountId ID da conta usada
     */
    public static function updateLastCustomerAccount(int $conversationId, int $accountId): void
    {
        Conversation::update($conversationId, [
            'last_customer_account_id' => $accountId
        ]);
    }
    
    /**
     * Obter todas as contas vinculadas a uma conversa
     * 
     * @param int $conversationId ID da conversa
     * @return array Lista de contas com detalhes
     */
    public static function getLinkedAccounts(int $conversationId): array
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return [];
        }
        
        $accountIds = [];
        
        // Conta principal
        if (!empty($conversation['integration_account_id'])) {
            $accountIds[] = $conversation['integration_account_id'];
        }
        
        // Contas vinculadas (mescladas)
        if (!empty($conversation['linked_account_ids'])) {
            $linked = json_decode($conversation['linked_account_ids'], true) ?? [];
            $accountIds = array_merge($accountIds, $linked);
        }
        
        $accountIds = array_unique($accountIds);
        
        if (empty($accountIds)) {
            return [];
        }
        
        // Buscar detalhes das contas
        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $sql = "SELECT id, name, phone_number, status FROM integration_accounts WHERE id IN ({$placeholders})";
        
        return Database::fetchAll($sql, $accountIds);
    }
}
