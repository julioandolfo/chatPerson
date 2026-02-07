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
        Permission::abortIfCannot('conversations.edit.tags');
        
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
        Permission::abortIfCannot('conversations.edit.tags');
        
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
            Response::json([
                'success' => true,
                'tabs' => $tabs
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
     * Adicionar aba (vincular tag como aba para o usuário)
     */
    public function addTab(): void
    {
        try {
            $userId = Auth::id();
            $tagId = (int) Request::post('tag_id');

            if (!$tagId) {
                Response::json(['success' => false, 'message' => 'tag_id é obrigatório'], 400);
                return;
            }

            // Verificar se a tag existe
            $tag = \App\Models\Tag::find($tagId);
            if (!$tag) {
                Response::json(['success' => false, 'message' => 'Tag não encontrada'], 404);
                return;
            }

            if (UserConversationTab::isTab($userId, $tagId)) {
                Response::json(['success' => true, 'message' => 'Aba já existe', 'already_exists' => true]);
                return;
            }

            $ok = UserConversationTab::addTab($userId, $tagId);
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
     * Remover aba do usuário
     */
    public function removeTab(): void
    {
        try {
            $userId = Auth::id();
            $tagId = (int) Request::post('tag_id');

            if (!$tagId) {
                Response::json(['success' => false, 'message' => 'tag_id é obrigatório'], 400);
                return;
            }

            $ok = UserConversationTab::removeTab($userId, $tagId);
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

            // Adicionar como aba do usuário
            UserConversationTab::addTab($userId, $tagId);

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
}

