<?php
/**
 * Service TagService
 * Lógica de negócio para tags
 */

namespace App\Services;

use App\Models\Tag;
use App\Helpers\Validator;

class TagService
{
    /**
     * Listar tags
     */
    public static function list(array $filters = []): array
    {
        $sql = "SELECT * FROM tags WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY name ASC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Obter tag específica
     */
    public static function get(int $tagId): ?array
    {
        return Tag::find($tagId);
    }

    /**
     * Criar tag
     */
    public static function create(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Verificar se nome já existe
        $existing = Tag::where('name', '=', $data['name']);
        if (!empty($existing)) {
            throw new \InvalidArgumentException('Tag com este nome já existe');
        }

        // Valores padrão
        $data['color'] = $data['color'] ?? '#009EF7';

        return Tag::create($data);
    }

    /**
     * Atualizar tag
     */
    public static function update(int $tagId, array $data): bool
    {
        $tag = Tag::find($tagId);
        if (!$tag) {
            throw new \InvalidArgumentException('Tag não encontrada');
        }

        $errors = Validator::validate($data, [
            'name' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Verificar se nome já existe (outra tag)
        if (isset($data['name']) && $data['name'] !== $tag['name']) {
            $existing = Tag::where('name', '=', $data['name']);
            if (!empty($existing)) {
                throw new \InvalidArgumentException('Tag com este nome já existe');
            }
        }

        return Tag::update($tagId, $data);
    }

    /**
     * Deletar tag
     */
    public static function delete(int $tagId): bool
    {
        $tag = Tag::find($tagId);
        if (!$tag) {
            throw new \InvalidArgumentException('Tag não encontrada');
        }

        // Verificar se tag está sendo usada em conversas
        $sql = "SELECT COUNT(*) as count FROM conversation_tags WHERE tag_id = ?";
        $result = \App\Helpers\Database::fetch($sql, [$tagId]);
        
        if ($result && $result['count'] > 0) {
            throw new \Exception('Tag está sendo usada em ' . $result['count'] . ' conversa(s). Remova as tags das conversas antes de deletar.');
        }

        return Tag::delete($tagId);
    }

    /**
     * Adicionar tag a conversa
     */
    public static function addToConversation(int $conversationId, int $tagId): bool
    {
        $tag = Tag::find($tagId);
        if (!$tag) {
            throw new \InvalidArgumentException('Tag não encontrada');
        }

        // Verificar se conversa existe
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversa não encontrada');
        }

        $ok = Tag::addToConversation($conversationId, $tagId);

        // Invalidar cache de conversa/lista para refletir tags na sidebar e na listagem
        if ($ok && class_exists('\App\Services\ConversationService')) {
            \App\Services\ConversationService::invalidateCache($conversationId);
        }

        return $ok;
    }

    /**
     * Remover tag de conversa
     */
    public static function removeFromConversation(int $conversationId, int $tagId): bool
    {
        $ok = Tag::removeFromConversation($conversationId, $tagId);

        // Invalidar cache de conversa/lista para refletir tags na sidebar e na listagem
        if ($ok && class_exists('\App\Services\ConversationService')) {
            \App\Services\ConversationService::invalidateCache($conversationId);
        }

        return $ok;
    }

    /**
     * Obter tags de uma conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        return Tag::getByConversation($conversationId);
    }

    /**
     * Obter todas as tags disponíveis
     */
    public static function getAll(): array
    {
        return Tag::all();
    }
}

