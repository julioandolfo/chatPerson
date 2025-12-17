<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\FunnelService;
use App\Models\Funnel;

class FunnelController
{
    /**
     * Listar funis
     */
    public function index(): void
    {
        Permission::abortIfCannot('funnels.view');
        
        try {
            $funnels = Funnel::all();
            Response::view('funnels/index', ['funnels' => $funnels]);
        } catch (\Exception $e) {
            Response::view('funnels/index', [
                'funnels' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mostrar kanban do funil
     */
    public function kanban(?int $id = null): void
    {
        Permission::abortIfCannot('funnels.view');
        
        try {
            // Se não informar ID, usar funil padrão
            if (!$id) {
                $defaultFunnel = Funnel::getDefault();
                if ($defaultFunnel) {
                    $id = $defaultFunnel['id'];
                } else {
                    // Se não houver padrão, usar o primeiro
                    $funnels = Funnel::where('status', '=', 'active');
                    if (empty($funnels)) {
                        throw new \Exception('Nenhum funil encontrado');
                    }
                    $id = $funnels[0]['id'];
                }
            }

            $userId = \App\Helpers\Auth::id();
            $kanbanData = FunnelService::getKanbanData($id, $userId);
            $allFunnels = Funnel::where('status', '=', 'active');
            $funnel = $kanbanData['funnel'] ?? null;
            
            Response::view('funnels/kanban', [
                'kanbanData' => $kanbanData,
                'allFunnels' => $allFunnels,
                'currentFunnelId' => $id,
                'funnel' => $funnel
            ]);
        } catch (\Exception $e) {
            Response::view('funnels/kanban', [
                'kanbanData' => null,
                'allFunnels' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Criar funil
     */
    public function store(): void
    {
        Permission::abortIfCannot('funnels.create');
        
        try {
            $data = Request::post();
            $funnelId = FunnelService::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'Funil criado com sucesso!',
                'id' => $funnelId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar funil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar funil
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('funnels.edit');
        
        try {
            $data = Request::post();
            FunnelService::update($id, $data);
            
            Response::json([
                'success' => true,
                'message' => 'Funil atualizado com sucesso!'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar funil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar funil
     */
    public function delete(int $id): void
    {
        Permission::abortIfCannot('funnels.delete');
        
        try {
            // Verificar se é funil padrão
            $funnel = Funnel::find($id);
            if (!$funnel) {
                throw new \Exception('Funil não encontrado');
            }
            
            if ($funnel['is_default']) {
                throw new \InvalidArgumentException('Não é possível deletar o funil padrão');
            }
            
            FunnelService::delete($id);
            
            Response::json([
                'success' => true,
                'message' => 'Funil deletado com sucesso!'
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao deletar funil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar estágio
     */
    public function createStage(int $id): void
    {
        Permission::abortIfCannot('funnels.edit');
        
        try {
            $data = Request::post();
            $stageId = FunnelService::createStage($id, $data);
            
            Response::json([
                'success' => true,
                'message' => 'Estágio criado com sucesso!',
                'id' => $stageId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar estágio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mover conversa para estágio
     */
    public function moveConversation(int $id): void
    {
        Permission::abortIfCannot('funnels.edit');
        
        try {
            $conversationId = Request::post('conversation_id');
            $stageId = Request::post('stage_id');
            $validateOnly = Request::post('validate_only') === '1';
            $userId = \App\Helpers\Auth::id();
            
            if (!$conversationId || !$stageId) {
                throw new \InvalidArgumentException('Dados inválidos');
            }
            
            // Validar antes de mover
            $validation = FunnelService::canMoveConversation($conversationId, $stageId, $userId);
            
            // Se for apenas validação, retornar resultado
            if ($validateOnly) {
                Response::json([
                    'allowed' => $validation['allowed'],
                    'message' => $validation['message'] ?? ''
                ]);
                return;
            }
            
            if (!$validation['allowed']) {
                Response::json([
                    'success' => false,
                    'message' => $validation['message']
                ], 403);
                return;
            }
            
            if (FunnelService::moveConversation($conversationId, $stageId, $userId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Conversa movida com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao mover conversa.'
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
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar estágio
     */
    public function updateStage(int $id, int $stageId): void
    {
        Permission::abortIfCannot('funnels.edit');
        
        try {
            $data = Request::post();
            if (FunnelService::updateStage($stageId, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Estágio atualizado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar estágio.'
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
                'message' => 'Erro ao atualizar estágio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar estágio
     */
    public function deleteStage(int $id, int $stageId): void
    {
        Permission::abortIfCannot('funnels.edit');
        
        try {
            $data = Request::post();
            $targetStageId = $data['target_stage_id'] ?? null;
            
            // Verificar se há conversas neste estágio
            $conversations = \App\Models\Funnel::getConversationsByStage($id, $stageId);
            
            if (!empty($conversations)) {
                // Se há conversas, precisa especificar para onde mover
                if (!$targetStageId) {
                    Response::json([
                        'success' => false,
                        'message' => 'Este estágio possui conversas. Especifique para qual estágio transferir.',
                        'conversation_count' => count($conversations),
                        'requires_transfer' => true
                    ], 400);
                    return;
                }
                
                // Validar estágio de destino
                $targetStage = \App\Models\FunnelStage::find($targetStageId);
                if (!$targetStage) {
                    throw new \InvalidArgumentException('Estágio de destino não encontrado');
                }
                
                if ($targetStage['funnel_id'] != $id) {
                    throw new \InvalidArgumentException('Estágio de destino deve pertencer ao mesmo funil');
                }
                
                // Transferir todas as conversas para o estágio de destino
                foreach ($conversations as $conversation) {
                    FunnelService::moveConversation(
                        $conversation['id'],
                        $targetStageId,
                        \App\Helpers\Auth::id(),
                        false // skipValidation = false
                    );
                }
            }
            
            // Deletar estágio
            if (\App\Models\FunnelStage::delete($stageId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Estágio deletado com sucesso!' . 
                                 (!empty($conversations) ? ' ' . count($conversations) . ' conversa(s) transferida(s).' : '')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar estágio.'
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
                'message' => 'Erro ao deletar estágio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reordenar estágios
     */
    public function reorderStages(int $id): void
    {
        Permission::abortIfCannot('funnels.edit');
        
        try {
            $stageIds = Request::post('stage_ids', []);
            
            if (empty($stageIds) || !is_array($stageIds)) {
                throw new \InvalidArgumentException('IDs dos estágios inválidos');
            }
            
            if (FunnelService::reorderStages($id, $stageIds)) {
                Response::json([
                    'success' => true,
                    'message' => 'Estágios reordenados com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao reordenar estágios.'
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
     * Obter estágio em formato JSON (para edição)
     */
    public function getStageJson(int $id, int $stageId): void
    {
        Permission::abortIfCannot('funnels.view');
        
        try {
            $stage = \App\Models\FunnelStage::find($stageId);
            if (!$stage || $stage['funnel_id'] != $id) {
                Response::json([
                    'success' => false,
                    'message' => 'Estágio não encontrado'
                ], 404);
                return;
            }
            
            Response::json([
                'success' => true,
                'stage' => $stage
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar estágio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter estágios de um funil (ou todos os estágios se id = 0)
     */
    public function getStages(int $id): void
    {
        Permission::abortIfCannot('funnels.view');
        
        try {
            // Se id = 0, retornar todos os estágios de todos os funis
            if ($id === 0) {
                $sql = "SELECT fs.*, f.name as funnel_name 
                        FROM funnel_stages fs
                        INNER JOIN funnels f ON fs.funnel_id = f.id
                        WHERE f.status = 'active'
                        ORDER BY f.name ASC, fs.position ASC, fs.id ASC";
                $stages = \App\Helpers\Database::fetchAll($sql);
                
                Response::json([
                    'success' => true,
                    'stages' => $stages
                ]);
                return;
            }
            
            $funnel = Funnel::find($id);
            if (!$funnel) {
                Response::json([
                    'success' => false,
                    'message' => 'Funil não encontrado',
                    'stages' => []
                ], 404);
                return;
            }
            
            $stages = Funnel::getStages($id);
            
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
    
    /**
     * Obter métricas de um estágio
     */
    public function getStageMetrics(int $id): void
    {
        Permission::abortIfCannot('funnels.view');
        
        try {
            $stageId = Request::get('stage_id');
            $dateFrom = Request::get('date_from') ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = Request::get('date_to') ?? date('Y-m-d H:i:s');
            
            if (!$stageId) {
                Response::json(['success' => false, 'message' => 'stage_id é obrigatório'], 400);
                return;
            }
            
            $metrics = FunnelService::getStageMetrics((int)$stageId, $dateFrom, $dateTo);
            
            Response::json([
                'success' => true,
                'metrics' => $metrics
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Obter métricas do funil completo
     */
    public function getFunnelMetrics(int $id): void
    {
        Permission::abortIfCannot('funnels.view');
        
        try {
            $dateFrom = Request::get('date_from') ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = Request::get('date_to') ?? date('Y-m-d H:i:s');
            
            $metrics = FunnelService::getFunnelMetrics($id, $dateFrom, $dateTo);
            
            Response::json([
                'success' => true,
                'metrics' => $metrics
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Obter etapas de um funil (JSON) para uso em selects dinâmicos
     */
    public function getStagesJson(int $id): void
    {
        Permission::abortIfCannot('funnels.view');
        
        try {
            $stages = \App\Models\FunnelStage::where('funnel_id', '=', $id);
            
            // Ordenar por posição
            usort($stages, function($a, $b) {
                return ($a['position'] ?? 0) - ($b['position'] ?? 0);
            });
            
            Response::json([
                'success' => true,
                'stages' => $stages
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
