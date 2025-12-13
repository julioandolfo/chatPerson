<?php
/**
 * Service ConversationNoteService
 */

namespace App\Services;

use App\Models\ConversationNote;
use App\Models\Conversation;

class ConversationNoteService
{
    /**
     * Criar nota em uma conversa
     */
    public static function create(int $conversationId, int $userId, string $content, bool $isPrivate = false): array
    {
        // Verificar se conversa existe
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }
        
        // Validar conteúdo
        $content = trim($content);
        if (empty($content)) {
            throw new \Exception('Conteúdo da nota não pode estar vazio');
        }
        
        // Criar nota
        $noteId = ConversationNote::createNote($conversationId, $userId, $content, $isPrivate);
        
        // Obter nota criada com dados do usuário
        $note = ConversationNote::findWithUser($noteId);
        
        if (!$note) {
            throw new \Exception('Erro ao criar nota');
        }
        
        // Notificar via WebSocket
        try {
            \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
        
        return $note;
    }

    /**
     * Listar notas de uma conversa
     */
    public static function list(int $conversationId, ?int $userId = null): array
    {
        return ConversationNote::getByConversation($conversationId, $userId);
    }

    /**
     * Atualizar nota
     */
    public static function update(int $noteId, int $userId, string $content): array
    {
        // Validar conteúdo
        $content = trim($content);
        if (empty($content)) {
            throw new \Exception('Conteúdo da nota não pode estar vazio');
        }
        
        // Atualizar
        $updated = ConversationNote::updateNote($noteId, $userId, $content);
        
        if (!$updated) {
            throw new \Exception('Nota não encontrada ou você não tem permissão para editá-la');
        }
        
        // Obter nota atualizada
        $note = ConversationNote::findWithUser($noteId);
        
        if (!$note) {
            throw new \Exception('Erro ao atualizar nota');
        }
        
        return $note;
    }

    /**
     * Deletar nota
     */
    public static function delete(int $noteId, int $userId): bool
    {
        $deleted = ConversationNote::deleteNote($noteId, $userId);
        
        if (!$deleted) {
            throw new \Exception('Nota não encontrada ou você não tem permissão para deletá-la');
        }
        
        return true;
    }
}

