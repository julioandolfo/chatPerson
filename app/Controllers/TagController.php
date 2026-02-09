<?php
/**
 * Controller de Tags
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;
use App\Services\TagService;
use App\Models\UserConversationTab;

class TagController
{
    /**
     * Listar tags
     */
    public function index(): void
    {
        Permission::abortIfCannot('tags.view');
        
        $filters = [
            'search' => Request::get('search'),
            'limit' => Request::get('limit', 100),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $tags = TagService::list($filters);
            
            Response::view('tags/index', [
                'tags' => $tags,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('tags/index', [
                'tags' => [],
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Criar tag
     */
    public function store(): void
    {
        Permission::abortIfCannot('tags.create');
        
        try {
            $data = Request::post();
            $tagId = TagService::create($data);
            
            Response::successOrRedirect(
                'Tag criada com sucesso!',
                '/tags',
                ['id' => $tagId]
            );
        } catch (\InvalidArgumentException $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            } else {
                Response::redirect('/tags?error=' . urlencode($e->getMessage()));
            }
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao criar tag: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/tags?error=' . urlencode('Erro ao criar tag: ' . $e->getMessage()));
            }
        }
    }

    /**
     * Atualizar tag
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('tags.edit');
        
        try {
            $data = Request::post();
            if (TagService::update($id, $data)) {
                Response::successOrRedirect(
                    'Tag atualizada com sucesso!',
                    '/tags'
                );
            } else {
                if (Request::isAjax()) {
                    Response::json([
                        'success' => false,
                        'message' => 'Falha ao atualizar tag.'
                    ], 404);
                } else {
                    Response::redirect('/tags?error=' . urlencode('Falha ao atualizar tag.'));
                }
            }
        } catch (\InvalidArgumentException $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            } else {
                Response::redirect('/tags?error=' . urlencode($e->getMessage()));
            }
        } catch (\Exception $e) {
            if (Request::isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao atualizar tag: ' . $e->getMessage()
                ], 500);
            } else {
                Response::redirect('/tags?error=' . urlencode('Erro ao atualizar tag: ' . $e->getMessage()));
            }
        }
    }

    /**
     * Deletar tag
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('tags.delete');
        
        try {
            if (TagService::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tag deletada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar tag.'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Adicionar tag a conversa
     */
    public function addToConversation(int $conversationId): void
    {
        // Permitir se o agente tem permissão de editar tags OU se tem acesso às conversas
        // O "Setar Como" é uma funcionalidade operacional para organizar conversas
        Permission::abortIfCannotAny(['conversations.edit.tags', 'conversations.view.own', 'conversations.view.all']);
        
        try {
            $tagId = Request::post('tag_id');
            if (TagService::addToConversation($conversationId, $tagId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tag adicionada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao adicionar tag.'
                ], 500);
            }
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao adicionar tag: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover tag de conversa
     */
    public function removeFromConversation(int $conversationId): void
    {
        // Permitir se o agente tem permissão de editar tags OU se tem acesso às conversas
        Permission::abortIfCannotAny(['conversations.edit.tags', 'conversations.view.own', 'conversations.view.all']);
        
        try {
            $tagId = Request::post('tag_id');
            if (TagService::removeFromConversation($conversationId, $tagId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tag removida com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao remover tag.'
                ], 500);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obter tags de uma conversa (JSON)
     */
    public function getByConversation(int $conversationId): void
    {
        // ✅ CORRIGIDO: Verificar permissão correta
        if (!Permission::can('conversations.view.own') && !Permission::can('conversations.view.all')) {
            Permission::abortIfCannot('conversations.view.own');
        }
        
        try {
            $tags = TagService::getByConversation($conversationId);
            Response::json([
                'success' => true,
                'tags' => $tags
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'tags' => []
            ], 500);
        }
    }

    /**
     * Obter todas as tags (JSON - para uso em selects)
     */
    public function getAll(): void
    {
        Permission::abortIfCannot('tags.view');
        
        try {
            $tags = TagService::getAll();
            Response::json([
                'success' => true,
                'tags' => $tags
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'tags' => []
            ], 500);
        }
    }

    // ============================================
    // ABAS DE CONVERSAS (por agente)
    // ============================================

    /**
     * Listar abas do usuário logado (com contagens)
     */
    public function getUserTabs(): void
    {
        try {
            $userId = Auth::id();
            $tabs = UserConversationTab::getByUserWithCounts($userId);
            
            // Contagem total de conversas abertas + aguardando resposta
            $totalCount = 0;
            $totalAwaitingCount = 0;
            try {
                $result = \App\Helpers\Database::fetch(
                    "SELECT COUNT(*) as total FROM conversations WHERE status = 'open'"
                );
                $totalCount = $result ? (int)$result['total'] : 0;
                
                $awaitingResult = \App\Helpers\Database::fetch(
                    "SELECT COUNT(*) as total FROM conversations c 
                     WHERE c.status = 'open' 
                       AND (SELECT m.sender_type FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) = 'contact'"
                );
                $totalAwaitingCount = $awaitingResult ? (int)$awaitingResult['total'] : 0;
            } catch (\Exception $e) {
                // Ignorar erro na contagem
            }

            // Carregar dados auxiliares para o modal avançado (funis, departamentos)
            $funnels = [];
            $departments = [];
            try {
                $funnels = \App\Models\Funnel::whereActive();
                $departments = \App\Models\Department::all();
            } catch (\Exception $e) {
                // Ignorar
            }
            
            Response::json([
                'success' => true,
                'tabs' => $tabs,
                'total_count' => $totalCount,
                'total_awaiting_count' => $totalAwaitingCount,
                'funnels' => $funnels,
                'departments' => $departments
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'tabs' => []
            ], 500);
        }
    }

    /**
     * Adicionar aba (simples por tag ou avançada com condições)
     */
    public function addTab(): void
    {
        try {
            $userId = Auth::id();
            $data = Request::post();

            // Modo simples (compatibilidade): apenas tag_id
            $tagId = isset($data['tag_id']) ? (int) $data['tag_id'] : null;
            $name = $data['name'] ?? null;
            $color = $data['color'] ?? null;
            $conditions = $data['conditions'] ?? null;
            $matchType = $data['match_type'] ?? 'AND';

            // Validar: precisa ter pelo menos tag_id ou conditions
            if (empty($tagId) && empty($conditions)) {
                Response::json(['success' => false, 'message' => 'A aba precisa ter pelo menos uma tag ou condição'], 400);
                return;
            }

            // Se tag_id fornecido, verificar se existe
            if ($tagId) {
                $tag = \App\Models\Tag::find($tagId);
                if (!$tag) {
                    Response::json(['success' => false, 'message' => 'Tag não encontrada'], 404);
                    return;
                }
            }

            // Parse conditions se veio como string JSON
            if (is_string($conditions)) {
                $conditions = json_decode($conditions, true);
            }

            // Validar match_type
            if (!in_array($matchType, ['AND', 'OR'])) {
                $matchType = 'AND';
            }

            $ok = UserConversationTab::addTab($userId, $tagId, $name, $color, $conditions, $matchType);
            $tabs = UserConversationTab::getByUserWithCounts($userId);

            Response::json([
                'success' => $ok,
                'message' => $ok ? 'Aba adicionada com sucesso!' : 'Falha ao adicionar aba',
                'tabs' => $tabs
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao adicionar aba: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar aba existente
     */
    public function updateTab(): void
    {
        try {
            $userId = Auth::id();
            $data = Request::post();

            $tabId = isset($data['tab_id']) ? (int) $data['tab_id'] : 0;
            if (!$tabId) {
                Response::json(['success' => false, 'message' => 'tab_id é obrigatório'], 400);
                return;
            }

            // Verificar se a aba pertence ao usuário
            $tab = UserConversationTab::findTab($tabId);
            if (!$tab || (int)$tab['user_id'] !== $userId) {
                Response::json(['success' => false, 'message' => 'Aba não encontrada'], 404);
                return;
            }

            $updateData = [];

            if (array_key_exists('tag_id', $data)) {
                $updateData['tag_id'] = $data['tag_id'] ? (int) $data['tag_id'] : null;
            }
            if (array_key_exists('name', $data)) {
                $updateData['name'] = $data['name'] ?: null;
            }
            if (array_key_exists('color', $data)) {
                $updateData['color'] = $data['color'] ?: null;
            }
            if (array_key_exists('conditions', $data)) {
                $conditions = $data['conditions'];
                if (is_string($conditions)) {
                    $conditions = json_decode($conditions, true);
                }
                $updateData['conditions'] = $conditions;
            }
            if (array_key_exists('match_type', $data)) {
                $updateData['match_type'] = $data['match_type'];
            }

            $ok = UserConversationTab::updateTab($tabId, $userId, $updateData);
            $tabs = UserConversationTab::getByUserWithCounts($userId);

            Response::json([
                'success' => $ok,
                'message' => $ok ? 'Aba atualizada com sucesso!' : 'Falha ao atualizar aba',
                'tabs' => $tabs
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar aba: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover aba do usuário (suporta por tag_id ou tab_id)
     */
    public function removeTab(): void
    {
        try {
            $userId = Auth::id();
            $data = Request::post();

            // Suporte a remoção por tab_id (novo) ou tag_id (compatibilidade)
            $tabId = isset($data['tab_id']) ? (int) $data['tab_id'] : 0;
            $tagId = isset($data['tag_id']) ? (int) $data['tag_id'] : 0;

            if (!$tabId && !$tagId) {
                Response::json(['success' => false, 'message' => 'tab_id ou tag_id é obrigatório'], 400);
                return;
            }

            if ($tabId) {
                $ok = UserConversationTab::removeTabById($tabId, $userId);
            } else {
                $ok = UserConversationTab::removeTab($userId, $tagId);
            }

            $tabs = UserConversationTab::getByUserWithCounts($userId);

            Response::json([
                'success' => $ok,
                'message' => $ok ? 'Aba removida com sucesso!' : 'Falha ao remover aba',
                'tabs' => $tabs
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao remover aba: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reordenar abas do usuário
     */
    public function reorderTabs(): void
    {
        try {
            $userId = Auth::id();
            $tabIds = Request::post('tab_ids');

            if (!is_array($tabIds) || empty($tabIds)) {
                Response::json(['success' => false, 'message' => 'tab_ids (array) é obrigatório'], 400);
                return;
            }

            $ok = UserConversationTab::reorder($userId, array_map('intval', $tabIds));

            Response::json([
                'success' => $ok,
                'message' => $ok ? 'Abas reordenadas!' : 'Falha ao reordenar'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao reordenar abas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar nova tag E adicioná-la como aba do usuário (atalho)
     */
    public function createTagAndTab(): void
    {
        try {
            $userId = Auth::id();
            $data = Request::post();

            // Criar a tag
            $tagId = TagService::create([
                'name' => $data['name'] ?? '',
                'color' => $data['color'] ?? '#009EF7',
                'description' => $data['description'] ?? ''
            ]);

            // Adicionar como aba do usuário (simples ou com condições)
            $conditions = $data['conditions'] ?? null;
            if (is_string($conditions)) {
                $conditions = json_decode($conditions, true);
            }
            $matchType = $data['match_type'] ?? 'AND';

            UserConversationTab::addTab($userId, $tagId, $data['name'] ?? null, $data['color'] ?? null, $conditions, $matchType);

            // Se conversationId fornecido, já vincular a tag
            if (!empty($data['conversation_id'])) {
                try {
                    TagService::addToConversation((int) $data['conversation_id'], $tagId);
                } catch (\Exception $e) {
                    // Não falhar se vincular à conversa falhar
                    error_log("Erro ao vincular tag à conversa: " . $e->getMessage());
                }
            }

            $tabs = UserConversationTab::getByUserWithCounts($userId);
            $tag = \App\Models\Tag::find($tagId);

            Response::json([
                'success' => true,
                'message' => 'Tag e aba criadas com sucesso!',
                'tag' => $tag,
                'tag_id' => $tagId,
                'tabs' => $tabs
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar tag/aba: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter etapas de um funil (para dropdown dinâmico no modal de abas)
     */
    public function getFunnelStages(): void
    {
        try {
            $funnelId = (int) Request::get('funnel_id');
            if (!$funnelId) {
                Response::json(['success' => false, 'message' => 'funnel_id é obrigatório'], 400);
                return;
            }

            $stages = \App\Models\Funnel::getStages($funnelId);

            Response::json([
                'success' => true,
                'stages' => $stages
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'stages' => []
            ], 500);
        }
    }
}
