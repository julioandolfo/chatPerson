<?php
/**
 * Service FunnelService
 * Lógica de negócio para funis
 */

namespace App\Services;

use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Conversation;
use App\Helpers\Validator;

class FunnelService
{
    /**
     * Criar funil
     */
    public static function create(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Se for padrão, remover padrão dos outros
        if (!empty($data['is_default'])) {
            \App\Helpers\Database::execute("UPDATE funnels SET is_default = FALSE WHERE is_default = TRUE");
        }

        $data['status'] = $data['status'] ?? 'active';
        $funnelId = Funnel::create($data);
        
        // ✅ CRIAR 3 ETAPAS OBRIGATÓRIAS DO SISTEMA
        self::createSystemStages($funnelId);
        
        return $funnelId;
    }

    /**
     * Criar as 3 etapas obrigatórias do sistema para um funil
     * @param int $funnelId ID do funil
     */
    private static function createSystemStages(int $funnelId): void
    {
        // 1. Etapa "Entrada" (stage_order = 1)
        FunnelStage::create([
            'funnel_id' => $funnelId,
            'name' => 'Entrada',
            'description' => 'Etapa inicial do funil. Novas conversas e reaberturas entram aqui.',
            'color' => '#3b82f6', // Azul
            'stage_order' => 1,
            'is_system_stage' => 1,
            'system_stage_type' => 'entrada',
            'is_default' => 1 // Primeira etapa é sempre padrão
        ]);
        
        // 2. Etapa "Fechadas / Resolvidas" (stage_order = 998)
        FunnelStage::create([
            'funnel_id' => $funnelId,
            'name' => 'Fechadas / Resolvidas',
            'description' => 'Conversas fechadas ou resolvidas. Reabrem para "Entrada" após período de graça.',
            'color' => '#22c55e', // Verde
            'stage_order' => 998,
            'is_system_stage' => 1,
            'system_stage_type' => 'fechadas'
        ]);
        
        // 3. Etapa "Perdidas" (stage_order = 999)
        FunnelStage::create([
            'funnel_id' => $funnelId,
            'name' => 'Perdidas',
            'description' => 'Conversas perdidas ou descartadas. Não reabrem automaticamente.',
            'color' => '#ef4444', // Vermelho
            'stage_order' => 999,
            'is_system_stage' => 1,
            'system_stage_type' => 'perdidas'
        ]);
    }

    /**
     * Atualizar funil
     */
    public static function update(int $funnelId, array $data): bool
    {
        $funnel = Funnel::find($funnelId);
        if (!$funnel) {
            throw new \InvalidArgumentException('Funil não encontrado');
        }

        $errors = Validator::validate($data, [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Se for padrão, remover padrão dos outros
        if (!empty($data['is_default']) && !$funnel['is_default']) {
            \App\Helpers\Database::execute("UPDATE funnels SET is_default = FALSE WHERE is_default = TRUE");
        }

        return Funnel::update($funnelId, $data);
    }

    /**
     * Criar estágio
     */
    public static function createStage(int $funnelId, array $data): int
    {
        $funnel = Funnel::find($funnelId);
        if (!$funnel) {
            throw new \InvalidArgumentException('Funil não encontrado');
        }

        // Converter valores vazios para null ANTES da validação
        if (isset($data['max_conversations']) && $data['max_conversations'] === '') {
            $data['max_conversations'] = null;
        }
        if (isset($data['sla_hours']) && $data['sla_hours'] === '') {
            $data['sla_hours'] = null;
        }
        if (isset($data['auto_assign_department_id']) && $data['auto_assign_department_id'] === '') {
            $data['auto_assign_department_id'] = null;
        }

        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'position' => 'nullable|integer',
            'color' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
            'max_conversations' => 'nullable|integer|min:1',
            'allow_move_back' => 'nullable|boolean',
            'allow_skip_stages' => 'nullable|boolean',
            'blocked_stages' => 'nullable|string',
            'required_stages' => 'nullable|string',
            'required_tags' => 'nullable|string',
            'blocked_tags' => 'nullable|string',
            'auto_assign' => 'nullable|boolean',
            'auto_assign_department_id' => 'nullable|integer',
            'auto_assign_method' => 'nullable|string|in:round-robin,by-load,by-specialty',
            'sla_hours' => 'nullable|integer|min:1'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Processar campos JSON: garantir string JSON válida ou null
        $jsonFields = ['blocked_stages', 'required_stages', 'required_tags', 'blocked_tags'];
        foreach ($jsonFields as $jsonField) {
            if (!isset($data[$jsonField])) {
                continue;
            }
            if (is_string($data[$jsonField])) {
                $decoded = json_decode($data[$jsonField], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $data[$jsonField] = null;
                } else {
                    $data[$jsonField] = json_encode($decoded);
                }
            } elseif (is_array($data[$jsonField])) {
                $data[$jsonField] = json_encode($data[$jsonField]);
            } elseif ($data[$jsonField] === '' || $data[$jsonField] === null) {
                $data[$jsonField] = null;
            }
        }

        // Se não informar posição, colocar no final
        if (empty($data['position'])) {
            $sql = "SELECT MAX(position) as max_pos FROM funnel_stages WHERE funnel_id = ?";
            $result = \App\Helpers\Database::fetch($sql, [$funnelId]);
            $data['position'] = ($result['max_pos'] ?? -1) + 1;
        }

        $data['funnel_id'] = $funnelId;
        $data['color'] = $data['color'] ?? '#009ef7';
        $data['allow_move_back'] = $data['allow_move_back'] ?? true;

        // Se for padrão, remover padrão dos outros estágios do mesmo funil
        if (!empty($data['is_default'])) {
            \App\Helpers\Database::execute("UPDATE funnel_stages SET is_default = FALSE WHERE funnel_id = ? AND is_default = TRUE", [$funnelId]);
        }

        return FunnelStage::create($data);
    }

    /**
     * Atualizar estágio
     */
    public static function updateStage(int $stageId, array $data): bool
    {
        $stage = FunnelStage::find($stageId);
        if (!$stage) {
            throw new \InvalidArgumentException('Estágio não encontrado');
        }

        // ✅ PROTEÇÃO: Etapas do sistema não podem ter nome/descrição alterados
        if (!empty($stage['is_system_stage'])) {
            // Apenas permitir alteração de cor em etapas do sistema
            $allowedFields = ['color'];
            $data = array_intersect_key($data, array_flip($allowedFields));
            
            if (empty($data)) {
                throw new \InvalidArgumentException('Etapas do sistema só podem ter a cor alterada');
            }
        }

        // Converter valores vazios para null ANTES da validação
        if (isset($data['max_conversations']) && $data['max_conversations'] === '') {
            $data['max_conversations'] = null;
        }
        if (isset($data['sla_hours']) && $data['sla_hours'] === '') {
            $data['sla_hours'] = null;
        }
        if (isset($data['auto_assign_department_id']) && $data['auto_assign_department_id'] === '') {
            $data['auto_assign_department_id'] = null;
        }

        $errors = Validator::validate($data, [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'position' => 'nullable|integer',
            'color' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
            'max_conversations' => 'nullable|integer|min:1',
            'allow_move_back' => 'nullable|boolean',
            'allow_skip_stages' => 'nullable|boolean',
            'blocked_stages' => 'nullable|string',
            'required_stages' => 'nullable|string',
            'required_tags' => 'nullable|string',
            'blocked_tags' => 'nullable|string',
            'auto_assign' => 'nullable|boolean',
            'auto_assign_department_id' => 'nullable|integer',
            'auto_assign_method' => 'nullable|string|in:round-robin,by-load,by-specialty',
            'sla_hours' => 'nullable|integer|min:1'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        // Processar campos JSON: garantir string JSON válida ou null
        $jsonFields = ['blocked_stages', 'required_stages', 'required_tags', 'blocked_tags'];
        foreach ($jsonFields as $jsonField) {
            if (!isset($data[$jsonField])) {
                continue;
            }
            if (is_string($data[$jsonField])) {
                $decoded = json_decode($data[$jsonField], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $data[$jsonField] = null;
                } else {
                    $data[$jsonField] = json_encode($decoded);
                }
            } elseif (is_array($data[$jsonField])) {
                $data[$jsonField] = json_encode($data[$jsonField]);
            } elseif ($data[$jsonField] === '' || $data[$jsonField] === null) {
                $data[$jsonField] = null;
            }
        }

        // Se for padrão, remover padrão dos outros estágios do mesmo funil
        if (!empty($data['is_default']) && !$stage['is_default']) {
            \App\Helpers\Database::execute("UPDATE funnel_stages SET is_default = FALSE WHERE funnel_id = ? AND is_default = TRUE", [$stage['funnel_id']]);
        }

        return FunnelStage::update($stageId, $data);
    }

    /**
     * Mover conversa para estágio (com validações)
     */
    public static function moveConversation(int $conversationId, int $stageId, ?int $userId = null): bool
    {
        if ($userId === null) {
            $userId = \App\Helpers\Auth::id();
        }
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversa não encontrada');
        }

        $stage = FunnelStage::find($stageId);
        if (!$stage) {
            throw new \InvalidArgumentException('Estágio não encontrado');
        }

        // Verificar permissões
        if (!\App\Services\PermissionService::canEditConversation($userId, $conversation)) {
            throw new \Exception('Você não tem permissão para editar esta conversa');
        }

        // Verificar se usuário pode mover para este estágio
        if (!\App\Models\AgentFunnelPermission::canMoveToStage($userId, $stageId)) {
            throw new \Exception('Você não tem permissão para mover conversas para este estágio');
        }

        $oldStageId = $conversation['funnel_stage_id'] ?? null;
        $oldFunnelId = $conversation['funnel_id'] ?? null;
        
        // Validações de movimentação
        if ($oldStageId) {
            $oldStage = FunnelStage::find($oldStageId);
            if ($oldStage) {
                // Verificar se estágio permite mover para trás
                // (implementar regras específicas se necessário)
            }
        }

        // Verificar limite de conversas no estágio (se configurado)
        $conversationsInStage = Funnel::getConversationsByStage($stage['funnel_id'], $stageId);
        // TODO: Verificar limite configurado no estágio
        
        // Mover conversa
        if (Conversation::update($conversationId, [
            'funnel_id' => $stage['funnel_id'],
            'funnel_stage_id' => $stageId,
            'moved_at' => date('Y-m-d H:i:s')
        ])) {
            // Executar automações para movimentação
            try {
                \App\Services\AutomationService::executeForConversationMoved(
                    $conversationId, 
                    $oldStageId ?? 0, 
                    $stageId
                );
            } catch (\Exception $e) {
                error_log("Erro ao executar automações: " . $e->getMessage());
            }
            
            // Auto-atribuição por estágio (se habilitada)
            try {
                self::handleStageAutoAssignment($conversationId, $stage);
            } catch (\Exception $e) {
                error_log("Erro ao executar auto-atribuição do estágio: " . $e->getMessage());
            }
            
            // Registrar atividade
            try {
                if (class_exists('\App\Services\ActivityService')) {
                    \App\Services\ActivityService::logStageMoved($conversationId, $stageId, $oldStageId, $userId);
                }
            } catch (\Exception $e) {
                error_log("Erro ao logar atividade: " . $e->getMessage());
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Validar se pode mover conversa para estágio (com validações avançadas)
     */
    public static function canMoveConversation(int $conversationId, int $stageId, ?int $userId = null): array
    {
        if ($userId === null) {
            $userId = \App\Helpers\Auth::id();
        }
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return ['allowed' => false, 'message' => 'Conversa não encontrada'];
        }

        $stage = FunnelStage::find($stageId);
        if (!$stage) {
            return ['allowed' => false, 'message' => 'Estágio não encontrado'];
        }

        // Verificar permissões básicas
        if (!\App\Services\PermissionService::canEditConversation($userId, $conversation)) {
            return ['allowed' => false, 'message' => 'Você não tem permissão para editar esta conversa'];
        }

        if (!\App\Models\AgentFunnelPermission::canMoveToStage($userId, $stageId)) {
            return ['allowed' => false, 'message' => 'Você não tem permissão para mover conversas para este estágio'];
        }

        $oldStageId = $conversation['funnel_stage_id'] ?? null;
        $oldFunnelId = $conversation['funnel_id'] ?? null;
        $newFunnelId = $stage['funnel_id'];

        // Validação 1: Verificar se está tentando mover para o mesmo estágio
        if ($oldStageId === $stageId && $oldFunnelId === $newFunnelId) {
            return ['allowed' => false, 'message' => 'Conversa já está neste estágio'];
        }

        // Validação 2: Verificar limite de conversas no estágio
        if (!empty($stage['max_conversations'])) {
            $conversationsInStage = Funnel::getConversationsByStage($newFunnelId, $stageId);
            $currentCount = count($conversationsInStage);
            
            // Se não está saindo deste estágio, contar também
            if ($oldStageId !== $stageId) {
                $currentCount++;
            }
            
            if ($currentCount > $stage['max_conversations']) {
                return ['allowed' => false, 'message' => "Limite de conversas no estágio atingido ({$stage['max_conversations']} máximo)"];
            }
        }

        // Validação 3: Verificar se pode mover para trás
        if ($oldStageId && !empty($stage['allow_move_back']) && $stage['allow_move_back'] == false) {
            $oldStage = FunnelStage::find($oldStageId);
            if ($oldStage && $oldStage['position'] < $stage['position']) {
                return ['allowed' => false, 'message' => 'Não é permitido mover conversas para estágios anteriores'];
            }
        }

        // Validação 4: Verificar se pode pular estágios
        if ($oldStageId && !empty($stage['allow_skip_stages']) && $stage['allow_skip_stages'] == false) {
            $oldStage = FunnelStage::find($oldStageId);
            if ($oldStage && $oldStage['funnel_id'] === $newFunnelId) {
                $positionDiff = abs($stage['position'] - $oldStage['position']);
                if ($positionDiff > 1) {
                    return ['allowed' => false, 'message' => 'Não é permitido pular estágios intermediários'];
                }
            }
        }

        // Validação 5: Verificar estágios bloqueados
        if (!empty($stage['blocked_stages'])) {
            $blockedStages = is_string($stage['blocked_stages']) 
                ? json_decode($stage['blocked_stages'], true) ?? [] 
                : $stage['blocked_stages'];
            
            if (in_array($oldStageId, $blockedStages)) {
                return ['allowed' => false, 'message' => 'Não é permitido mover conversas deste estágio para o estágio de destino'];
            }
        }

        // Validação 6: Verificar estágios obrigatórios
        if (!empty($stage['required_stages'])) {
            $requiredStages = is_string($stage['required_stages']) 
                ? json_decode($stage['required_stages'], true) ?? [] 
                : $stage['required_stages'];
            
            // Verificar histórico de movimentações (se implementado)
            // Por enquanto, apenas verificar se passou pelo estágio anterior
            if (!empty($requiredStages) && $oldStageId) {
                $hasPassedRequired = false;
                foreach ($requiredStages as $requiredStageId) {
                    if ($oldStageId == $requiredStageId) {
                        $hasPassedRequired = true;
                        break;
                    }
                }
                if (!$hasPassedRequired) {
                    return ['allowed' => false, 'message' => 'É necessário passar pelos estágios obrigatórios antes'];
                }
            }
        }

        // Validação 7: Verificar tags obrigatórias
        if (!empty($stage['required_tags'])) {
            $requiredTags = is_string($stage['required_tags']) 
                ? json_decode($stage['required_tags'], true) ?? [] 
                : $stage['required_tags'];
            
            if (!empty($requiredTags)) {
                $conversationTags = \App\Models\Tag::getByConversation($conversationId);
                $conversationTagIds = array_column($conversationTags, 'id');
                
                foreach ($requiredTags as $requiredTagId) {
                    if (!in_array($requiredTagId, $conversationTagIds)) {
                        $tag = \App\Models\Tag::find($requiredTagId);
                        $tagName = $tag ? $tag['name'] : 'Tag obrigatória';
                        return ['allowed' => false, 'message' => "Tag obrigatória não encontrada: {$tagName}"];
                    }
                }
            }
        }

        // Validação 8: Verificar tags bloqueadas
        if (!empty($stage['blocked_tags'])) {
            $blockedTags = is_string($stage['blocked_tags']) 
                ? json_decode($stage['blocked_tags'], true) ?? [] 
                : $stage['blocked_tags'];
            
            if (!empty($blockedTags)) {
                $conversationTags = \App\Models\Tag::getByConversation($conversationId);
                $conversationTagIds = array_column($conversationTags, 'id');
                
                foreach ($blockedTags as $blockedTagId) {
                    if (in_array($blockedTagId, $conversationTagIds)) {
                        $tag = \App\Models\Tag::find($blockedTagId);
                        $tagName = $tag ? $tag['name'] : 'Tag bloqueada';
                        return ['allowed' => false, 'message' => "Conversa possui tag que bloqueia movimentação: {$tagName}"];
                    }
                }
            }
        }

        // Validação 9: Verificar se conversa está resolvida/fechada
        if (in_array($conversation['status'], ['resolved', 'closed'])) {
            // Se está resolvida/fechada, só pode mover para estágios finais ou reabrir
            if ($stage['position'] < ($oldStage ? $oldStage['position'] : 0)) {
                return ['allowed' => false, 'message' => 'Conversas resolvidas/fechadas não podem voltar para estágios anteriores'];
            }
        }

        return ['allowed' => true, 'message' => 'Movimentação permitida'];
    }

    /**
     * Reordenar estágios
     */
    public static function reorderStages(int $funnelId, array $stageIds): bool
    {
        return FunnelStage::reorder($funnelId, $stageIds);
    }
    
    /**
     * Obter métricas de um estágio (com informações de agentes)
     */
    public static function getStageMetrics(int $stageId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $dateTo ?? date('Y-m-d H:i:s') . ' 23:59:59';
        
        $stage = FunnelStage::find($stageId);
        if (!$stage) {
            return [];
        }
        
        // Conversas atuais no estágio (sem filtro de data)
        $currentConversations = Funnel::getConversationsByStage($stage['funnel_id'], $stageId);
        $currentCount = count($currentConversations);
        
        // Contar conversas atuais por status
        $currentOpen = 0;
        $currentResolved = 0;
        $currentClosed = 0;
        $currentUnassigned = 0;
        $agentsInStage = [];
        
        foreach ($currentConversations as $conv) {
            if ($conv['status'] === 'open') $currentOpen++;
            elseif ($conv['status'] === 'resolved') $currentResolved++;
            elseif ($conv['status'] === 'closed') $currentClosed++;
            
            if (empty($conv['agent_id'])) {
                $currentUnassigned++;
            } else {
                $agentId = $conv['agent_id'];
                if (!isset($agentsInStage[$agentId])) {
                    $agentsInStage[$agentId] = [
                        'id' => $agentId,
                        'name' => $conv['agent_name'] ?? 'Desconhecido',
                        'count' => 0
                    ];
                }
                $agentsInStage[$agentId]['count']++;
            }
        }
        
        // Conversas criadas no período (independente de onde estão agora)
        $sql = "SELECT COUNT(DISTINCT c.id) as total_created,
                       COUNT(DISTINCT CASE WHEN c.status = 'open' THEN c.id END) as open_created,
                       COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_created,
                       COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed_created
                FROM conversations c
                WHERE c.funnel_id = ?
                AND DATE(c.created_at) >= ?
                AND DATE(c.created_at) <= ?";
        
        $createdMetrics = \App\Helpers\Database::fetch($sql, [$stage['funnel_id'], $dateFrom, $dateTo]);
        
        // Conversas que passaram pelo estágio no período
        // Tempo médio = tempo médio de resposta do agente às mensagens do cliente neste estágio
        $sql = "SELECT COUNT(DISTINCT c.id) as total,
                       COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved,
                       COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed,
                       AVG(response_times.avg_response_minutes) as avg_response_minutes
                FROM conversations c
                LEFT JOIN (
                    SELECT 
                        m1.conversation_id,
                        AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as avg_response_minutes
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
                WHERE c.funnel_stage_id = ? 
                AND (
                    (c.created_at >= ? AND c.created_at <= ?) OR
                    (c.updated_at >= ? AND c.updated_at <= ?) OR
                    (c.moved_at >= ? AND c.moved_at <= ?)
                )";
        
        $metrics = \App\Helpers\Database::fetch($sql, [
            $stageId, 
            $dateFrom, $dateTo,
            $dateFrom, $dateTo,
            $dateFrom, $dateTo
        ]);
        
        // Converter minutos para horas para manter compatibilidade
        $avgTimeHours = isset($metrics['avg_response_minutes']) && $metrics['avg_response_minutes'] !== null
            ? round((float)$metrics['avg_response_minutes'] / 60, 2)
            : 0;
        
        $metrics['avg_time_hours'] = $avgTimeHours;
        $metrics['min_time_hours'] = 0; // Não calculamos mais min/max
        $metrics['max_time_hours'] = 0;
        
        // Conversas que entraram no estágio no período
        $sqlEntered = "SELECT COUNT(DISTINCT c.id) as entered
                       FROM conversations c
                       WHERE c.funnel_stage_id = ?
                       AND (c.created_at >= ? OR c.updated_at >= ?)
                       AND (c.created_at <= ? OR c.updated_at <= ?)";
        
        $entered = \App\Helpers\Database::fetch($sqlEntered, [
            $stageId, 
            $dateFrom,
            $dateFrom,
            $dateTo,
            $dateTo
        ]);
        
        // Métricas de agentes no período - tempo médio de resposta
        $sqlAgents = "SELECT 
                        c.agent_id,
                        u.name as agent_name,
                        COUNT(DISTINCT c.id) as conversations_count,
                        COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_count,
                        AVG(response_times.avg_response_minutes) as avg_response_minutes
                      FROM conversations c
                      LEFT JOIN users u ON u.id = c.agent_id
                      LEFT JOIN (
                          SELECT 
                              m1.conversation_id,
                              AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as avg_response_minutes
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
                      WHERE c.funnel_stage_id = ?
                      AND (c.created_at >= ? OR c.updated_at >= ?)
                      AND (c.created_at <= ? OR c.updated_at <= ?)
                      AND c.agent_id IS NOT NULL
                      GROUP BY c.agent_id, u.name
                      ORDER BY conversations_count DESC
                      LIMIT 10";
        
        $agentsMetrics = \App\Helpers\Database::fetchAll($sqlAgents, [
            $stageId,
            $dateFrom,
            $dateFrom,
            $dateTo,
            $dateTo
        ]);
        
        // Converter minutos para horas para os agentes
        if (!empty($agentsMetrics)) {
            foreach ($agentsMetrics as &$agentMetric) {
                $agentMetric['avg_time_hours'] = isset($agentMetric['avg_response_minutes']) && $agentMetric['avg_response_minutes'] !== null
                    ? round((float)$agentMetric['avg_response_minutes'] / 60, 2)
                    : 0;
            }
            unset($agentMetric);
        }
        
        // Taxa de conversão (se houver estágio seguinte)
        $nextStage = self::getNextStage($stage['funnel_id'], $stage['stage_order'] ?? 0);
        $conversionRate = 0;
        if ($nextStage && ($metrics['total'] ?? 0) > 0) {
            // Verificar conversas que saíram deste estágio e foram para o próximo
            $sqlConversion = "SELECT COUNT(DISTINCT c.id) as converted
                              FROM conversations c
                              WHERE c.funnel_stage_id = ?
                              AND EXISTS (
                                  SELECT 1 FROM conversations c2 
                                  WHERE c2.id = c.id 
                                  AND c2.funnel_stage_id = ?
                                  AND c2.updated_at > c.updated_at
                              )
                              AND (c.created_at >= ? OR c.updated_at >= ?)
                              AND (c.created_at <= ? OR c.updated_at <= ?)";
            $converted = \App\Helpers\Database::fetch($sqlConversion, [
                $stageId,
                $nextStage['id'],
                $dateFrom,
                $dateFrom,
                $dateTo,
                $dateTo
            ]);
            $conversionRate = ($converted['converted'] / max($metrics['total'], 1)) * 100;
        }
        
        return [
            'stage_id' => $stageId,
            'stage_name' => $stage['name'],
            'current_count' => $currentCount,
            'current_open' => $currentOpen,
            'current_resolved' => $currentResolved,
            'current_closed' => $currentClosed,
            'current_unassigned' => $currentUnassigned,
            'max_conversations' => $stage['max_conversations'] ?? null,
            'utilization_rate' => $stage['max_conversations'] ? round(($currentCount / $stage['max_conversations']) * 100, 2) : null,
            'total_in_period' => (int)($metrics['total'] ?? 0),
            'entered_in_period' => (int)($entered['entered'] ?? 0),
            'resolved' => (int)($metrics['resolved'] ?? 0),
            'closed' => (int)($metrics['closed'] ?? 0),
            'total_created_in_period' => (int)($createdMetrics['total_created'] ?? 0),
            'avg_time_hours' => round((float)($metrics['avg_time_hours'] ?? 0), 2),
            'min_time_hours' => round((float)($metrics['min_time_hours'] ?? 0), 2),
            'max_time_hours' => round((float)($metrics['max_time_hours'] ?? 0), 2),
            'conversion_rate' => round($conversionRate, 2),
            'sla_hours' => $stage['sla_hours'] ?? null,
            'sla_compliance' => $stage['sla_hours'] ? self::calculateSLACompliance($stageId, $stage['sla_hours'], $dateFrom, $dateTo) : null,
            'agents_current' => array_values($agentsInStage),
            'agents_period' => $agentsMetrics ?? []
        ];
    }
    
    /**
     * Obter métricas do funil completo (com informações de agentes)
     */
    public static function getFunnelMetrics(int $funnelId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $dateTo ?? date('Y-m-d H:i:s') . ' 23:59:59';
        
        $funnel = Funnel::find($funnelId);
        if (!$funnel) {
            return [];
        }
        
        $stages = Funnel::getStages($funnelId);
        $stageMetrics = [];
        
        foreach ($stages as $stage) {
            $stageMetrics[] = self::getStageMetrics($stage['id'], $dateFrom, $dateTo);
        }
        
        // Métricas gerais do funil - Conversas criadas no período
        $sql = "SELECT COUNT(DISTINCT c.id) as total_conversations,
                       COUNT(DISTINCT CASE WHEN c.status = 'open' THEN c.id END) as open_conversations,
                       COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_conversations,
                       COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed_conversations,
                       AVG(TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at))) as avg_resolution_hours,
                       MIN(TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at))) as min_resolution_hours,
                       MAX(TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at))) as max_resolution_hours
                FROM conversations c
                WHERE c.funnel_id = ?
                AND DATE(c.created_at) >= ?
                AND DATE(c.created_at) <= ?";
        
        $funnelMetrics = \App\Helpers\Database::fetch($sql, [$funnelId, $dateFrom, $dateTo]);
        
        // Conversas atuais no funil (todas as etapas)
        $sqlCurrent = "SELECT COUNT(DISTINCT c.id) as total_current,
                              COUNT(DISTINCT CASE WHEN c.status = 'open' THEN c.id END) as open_current,
                              COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_current,
                              COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed_current,
                              COUNT(DISTINCT CASE WHEN c.agent_id IS NULL THEN c.id END) as unassigned_current
                       FROM conversations c
                       WHERE c.funnel_id = ?";
        
        $currentMetrics = \App\Helpers\Database::fetch($sqlCurrent, [$funnelId]);
        
        // Top agentes do funil no período
        $sqlTopAgents = "SELECT 
                            c.agent_id,
                            u.name as agent_name,
                            u.avatar as agent_avatar,
                            COUNT(DISTINCT c.id) as conversations_count,
                            COUNT(DISTINCT CASE WHEN c.status = 'resolved' THEN c.id END) as resolved_count,
                            COUNT(DISTINCT CASE WHEN c.status = 'closed' THEN c.id END) as closed_count,
                            AVG(TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at))) as avg_time_hours
                          FROM conversations c
                          LEFT JOIN users u ON u.id = c.agent_id
                          WHERE c.funnel_id = ?
                          AND (c.created_at >= ? OR c.updated_at >= ?)
                          AND (c.created_at <= ? OR c.updated_at <= ?)
                          AND c.agent_id IS NOT NULL
                          GROUP BY c.agent_id, u.name, u.avatar
                          ORDER BY conversations_count DESC
                          LIMIT 10";
        
        $topAgents = \App\Helpers\Database::fetchAll($sqlTopAgents, [
            $funnelId,
            $dateFrom,
            $dateFrom,
            $dateTo,
            $dateTo
        ]);
        
        // Distribuição por etapa atual
        $sqlByStage = "SELECT 
                         fs.name as stage_name,
                         fs.id as stage_id,
                         COUNT(DISTINCT c.id) as count
                       FROM conversations c
                       INNER JOIN funnel_stages fs ON fs.id = c.funnel_stage_id
                       WHERE c.funnel_id = ?
                       GROUP BY fs.id, fs.name
                       ORDER BY fs.stage_order ASC";
        
        $byStage = \App\Helpers\Database::fetchAll($sqlByStage, [$funnelId]);
        
        $totalCreated = (int)($funnelMetrics['total_conversations'] ?? 0);
        $resolvedCreated = (int)($funnelMetrics['resolved_conversations'] ?? 0);
        
        return [
            'funnel_id' => $funnelId,
            'funnel_name' => $funnel['name'],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'stages' => $stageMetrics,
            'totals' => [
                'total_conversations' => $totalCreated,
                'open_conversations' => (int)($funnelMetrics['open_conversations'] ?? 0),
                'resolved_conversations' => $resolvedCreated,
                'closed_conversations' => (int)($funnelMetrics['closed_conversations'] ?? 0),
                'avg_resolution_hours' => round((float)($funnelMetrics['avg_resolution_hours'] ?? 0), 2),
                'min_resolution_hours' => round((float)($funnelMetrics['min_resolution_hours'] ?? 0), 2),
                'max_resolution_hours' => round((float)($funnelMetrics['max_resolution_hours'] ?? 0), 2),
                'resolution_rate' => $totalCreated > 0 
                    ? round(($resolvedCreated / $totalCreated) * 100, 2)
                    : 0
            ],
            'current' => [
                'total' => (int)($currentMetrics['total_current'] ?? 0),
                'open' => (int)($currentMetrics['open_current'] ?? 0),
                'resolved' => (int)($currentMetrics['resolved_current'] ?? 0),
                'closed' => (int)($currentMetrics['closed_current'] ?? 0),
                'unassigned' => (int)($currentMetrics['unassigned_current'] ?? 0)
            ],
            'top_agents' => $topAgents ?? [],
            'distribution_by_stage' => $byStage ?? []
        ];
    }
    
    /**
     * Obter próximo estágio
     */
    private static function getNextStage(int $funnelId, int $currentStageOrder): ?array
    {
        $sql = "SELECT * FROM funnel_stages 
                WHERE funnel_id = ? AND stage_order > ?
                ORDER BY stage_order ASC 
                LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$funnelId, $currentStageOrder]);
    }
    
    /**
     * Calcular compliance de SLA
     */
    private static function calculateSLACompliance(int $stageId, int $slaHours, string $dateFrom, string $dateTo): float
    {
        $sql = "SELECT COUNT(*) as total,
                       COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at)) <= ? THEN 1 END) as within_sla
                FROM conversations c
                WHERE c.funnel_stage_id = ?
                AND c.updated_at >= ?
                AND c.updated_at <= ?";
        
        $result = \App\Helpers\Database::fetch($sql, [$slaHours, $stageId, $dateFrom, $dateTo]);
        
        if (($result['total'] ?? 0) == 0) {
            return 100.0;
        }
        
        return round((($result['within_sla'] ?? 0) / ($result['total'] ?? 1)) * 100, 2);
    }

    /**
     * Obter dados do kanban (filtrado por permissões do usuário)
     */
    public static function getKanbanData(int $funnelId, ?int $userId = null): array
    {
        if ($userId === null) {
            $userId = \App\Helpers\Auth::id();
        }
        
        $funnel = Funnel::findWithStages($funnelId);
        if (!$funnel) {
            throw new \InvalidArgumentException('Funil não encontrado');
        }

        // Verificar permissão de visualização do funil
        if (!\App\Models\AgentFunnelPermission::canViewFunnel($userId, $funnelId)) {
            throw new \Exception('Você não tem permissão para ver este funil');
        }

        $kanbanData = [
            'funnel' => $funnel,
            'stages' => []
        ];

        foreach ($funnel['stages'] as $stage) {
            // Verificar permissão de visualização do estágio
            if (!\App\Models\AgentFunnelPermission::canViewStage($userId, $stage['id'])) {
                continue; // Pular estágios sem permissão
            }
            
            $conversations = \App\Models\Funnel::getConversationsByStage($funnelId, $stage['id']);
            
            // Filtrar conversas por permissões do usuário
            // Regra: Agentes veem apenas suas próprias conversas + conversas não atribuídas
            $filteredConversations = [];
            $isAdmin = \App\Services\PermissionService::isAdmin($userId);
            $isSuperAdmin = \App\Services\PermissionService::isSuperAdmin($userId);
            $canViewAll = \App\Services\PermissionService::hasPermission($userId, 'conversations.view.all');
            
            foreach ($conversations as $conv) {
                // Admin/Super Admin veem tudo
                if ($isAdmin || $isSuperAdmin || $canViewAll) {
                    $filteredConversations[] = $conv;
                    continue;
                }
                
                // Conversas não atribuídas - todos podem ver
                if (empty($conv['agent_id']) || $conv['agent_id'] === null) {
                    $filteredConversations[] = $conv;
                    continue;
                }
                
                // Conversas próprias
                if (isset($conv['agent_id']) && $conv['agent_id'] == $userId) {
                    $filteredConversations[] = $conv;
                    continue;
                }
                
                // Verificar permissões normais (participante, setor, etc)
                if (\App\Services\PermissionService::canViewConversation($userId, $conv)) {
                    $filteredConversations[] = $conv;
                }
            }
            
            $kanbanData['stages'][] = [
                'stage' => $stage,
                'conversations' => $filteredConversations,
                'count' => count($filteredConversations)
            ];
        }

        return $kanbanData;
    }

    /**
     * Processar auto-atribuição quando conversa entra em estágio
     */
    private static function handleStageAutoAssignment(int $conversationId, array $stage): void
    {
        // Verificar se auto-atribuição está habilitada para este estágio
        if (empty($stage['auto_assign']) || !$stage['auto_assign']) {
            return; // Auto-atribuição desabilitada
        }

        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return;
        }

        // Se já tem agente atribuído, não reatribuir automaticamente
        if (!empty($conversation['agent_id'])) {
            return;
        }

        // Obter configurações do estágio
        $departmentId = !empty($stage['auto_assign_department_id']) ? (int)$stage['auto_assign_department_id'] : null;
        $method = $stage['auto_assign_method'] ?? 'round-robin';
        
        // Mapear método do estágio para método do ConversationSettingsService
        $distributionMethod = self::mapStageMethodToDistributionMethod($method);
        
        // Obter agente usando sistema de distribuição
        $assignedAgentId = self::assignAgentForStage($conversationId, $departmentId, $stage['funnel_id'], $stage['id'], $distributionMethod);
        
        if ($assignedAgentId) {
            // Se for agente de IA (ID negativo), processar diferente
            if ($assignedAgentId < 0) {
                $aiAgentId = abs($assignedAgentId);
                // Atribuir conversa (agent_id pode ser NULL para IA, ou usar campo especial)
                Conversation::update($conversationId, [
                    'assigned_at' => date('Y-m-d H:i:s')
                ]);
                
                // Processar mensagem com agente de IA
                try {
                    $lastMessage = \App\Helpers\Database::fetch(
                        "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1",
                        [$conversationId]
                    );
                    
                    if ($lastMessage && $lastMessage['sender_type'] === 'contact') {
                        \App\Services\OpenAIService::processMessage(
                            $conversationId,
                            $aiAgentId,
                            $lastMessage['content'] ?? '',
                            []
                        );
                    }
                } catch (\Exception $e) {
                    error_log("Erro ao processar mensagem com agente de IA: " . $e->getMessage());
                }
            } else {
                // Atribuir conversa ao agente humano
                Conversation::update($conversationId, [
                    'agent_id' => $assignedAgentId,
                    'assigned_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Notificar via WebSocket
            try {
                \App\Helpers\WebSocket::notifyConversationAssigned($conversationId, $assignedAgentId);
            } catch (\Exception $e) {
                error_log("Erro ao notificar atribuição: " . $e->getMessage());
            }
            
            error_log("Conversa {$conversationId} auto-atribuída ao agente {$assignedAgentId} ao entrar no estágio {$stage['id']}");
        } else {
            error_log("Nenhum agente disponível para auto-atribuição da conversa {$conversationId} no estágio {$stage['id']}");
        }
    }

    /**
     * Mapear método do estágio para método de distribuição
     */
    private static function mapStageMethodToDistributionMethod(string $stageMethod): string
    {
        $mapping = [
            'round-robin' => 'round_robin',
            'by-load' => 'by_load',
            'by-specialty' => 'by_specialty',
            'by-performance' => 'by_performance'
        ];
        
        return $mapping[$stageMethod] ?? 'round_robin';
    }

    /**
     * Atribuir agente para conversa baseado em configurações do estágio
     */
    private static function assignAgentForStage(
        int $conversationId, 
        ?int $departmentId, 
        int $funnelId, 
        int $stageId, 
        string $method
    ): ?int {
        try {
            // Usar ConversationSettingsService mas com método específico do estágio
            // Temporariamente alterar configurações para usar método do estágio
            $settings = \App\Services\ConversationSettingsService::getSettings();
            $originalMethod = $settings['distribution']['method'] ?? 'round_robin';
            $originalEnabled = $settings['distribution']['enable_auto_assignment'] ?? false;
            
            // Temporariamente alterar para método do estágio
            $tempSettings = $settings;
            $tempSettings['distribution']['method'] = $method;
            $tempSettings['distribution']['enable_auto_assignment'] = true;
            
            // Salvar temporariamente (usando cache ou variável estática)
            // Por enquanto, vamos usar método direto chamando autoAssignConversation
            // mas antes vamos alterar temporariamente as configurações no banco
            
            // Alternativa: usar reflection para chamar método privado com parâmetros específicos
            // Ou criar método público auxiliar no ConversationSettingsService
            
            // Por simplicidade, vamos usar autoAssignConversation que já existe
            // mas precisamos garantir que use o método correto
            // Vamos criar uma versão simplificada que chama diretamente
            
            // Obter agentes disponíveis usando método público se existir, senão usar SQL direto
            $agents = self::getAvailableAgentsForStage($departmentId, $funnelId, $stageId);
            
            if (empty($agents)) {
                return null;
            }
            
            // Aplicar método de distribuição
            switch ($method) {
                case 'round_robin':
                    return self::assignRoundRobinForStage($agents);
                case 'by_load':
                    return self::assignByLoadForStage($agents);
                case 'by_specialty':
                    return self::assignBySpecialtyForStage($agents, $departmentId);
                case 'by_performance':
                    return self::assignByPerformanceForStage($agents);
                default:
                    return self::assignRoundRobinForStage($agents);
            }
        } catch (\Exception $e) {
            error_log("Erro ao atribuir agente para estágio: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obter agentes disponíveis para estágio
     */
    private static function getAvailableAgentsForStage(?int $departmentId, int $funnelId, int $stageId): array
    {
        $agents = [];
        
        // Agentes humanos
        $sql = "SELECT u.id, u.name, 
                       (SELECT COUNT(*) FROM conversations WHERE agent_id = u.id AND status IN ('open', 'pending')) as current_conversations,
                       u.max_conversations, u.availability_status,
                       MAX(c.updated_at) as last_assignment_at, 'human' as agent_type
                FROM users u
                LEFT JOIN conversations c ON u.id = c.agent_id AND c.status IN ('open', 'pending')
                WHERE u.status = 'active' 
                AND u.availability_status = 'online'
                AND u.role IN ('agent', 'admin', 'supervisor')";
        
        $params = [];
        
        if ($departmentId !== null) {
            $sql .= " AND u.id IN (
                        SELECT user_id FROM agent_departments WHERE department_id = ?
                    )";
            $params[] = $departmentId;
        }
        
        $sql .= " GROUP BY u.id
                  HAVING (u.max_conversations IS NULL OR current_conversations < u.max_conversations)";
        
        $humanAgents = \App\Helpers\Database::fetchAll($sql, $params);
        $agents = array_merge($agents, $humanAgents);
        
        return $agents;
    }

    /**
     * Distribuição Round-Robin para estágio
     */
    private static function assignRoundRobinForStage(array $agents): ?int
    {
        if (empty($agents)) {
            return null;
        }
        
        // Ordenar por última atribuição
        usort($agents, function($a, $b) {
            $aTime = strtotime($a['last_assignment_at'] ?? '1970-01-01');
            $bTime = strtotime($b['last_assignment_at'] ?? '1970-01-01');
            return $aTime <=> $bTime;
        });
        
        $selectedAgent = $agents[0] ?? null;
        return $selectedAgent ? (int)$selectedAgent['id'] : null;
    }

    /**
     * Distribuição por carga para estágio
     */
    private static function assignByLoadForStage(array $agents): ?int
    {
        if (empty($agents)) {
            return null;
        }
        
        // Ordenar por carga atual (menor primeiro)
        usort($agents, function($a, $b) {
            $aLoad = (int)($a['current_conversations'] ?? 0);
            $bLoad = (int)($b['current_conversations'] ?? 0);
            return $aLoad <=> $bLoad;
        });
        
        $selectedAgent = $agents[0] ?? null;
        return $selectedAgent ? (int)$selectedAgent['id'] : null;
    }

    /**
     * Distribuição por especialidade para estágio
     */
    private static function assignBySpecialtyForStage(array $agents, ?int $departmentId): ?int
    {
        // Por enquanto, usar round-robin dentro do setor
        return self::assignRoundRobinForStage($agents);
    }

    /**
     * Distribuição por performance para estágio
     */
    private static function assignByPerformanceForStage(array $agents): ?int
    {
        if (empty($agents)) {
            return null;
        }
        
        // Obter performance de cada agente
        foreach ($agents as &$agent) {
            try {
                if (class_exists('\App\Services\AgentPerformanceService')) {
                    $performance = \App\Services\AgentPerformanceService::getPerformanceStats($agent['id']);
                    $agent['performance_score'] = (float)($performance['resolution_rate'] ?? 0);
                } else {
                    $agent['performance_score'] = 0;
                }
            } catch (\Exception $e) {
                $agent['performance_score'] = 0;
            }
        }
        
        // Ordenar por performance (maior primeiro)
        usort($agents, function($a, $b) {
            return ($b['performance_score'] ?? 0) <=> ($a['performance_score'] ?? 0);
        });
        
        $selectedAgent = $agents[0] ?? null;
        return $selectedAgent ? (int)$selectedAgent['id'] : null;
    }

    /**
     * Deletar funil
     * 
     * @param int $funnelId
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return bool
     */
    public static function delete(int $funnelId): bool
    {
        $funnel = Funnel::find($funnelId);
        if (!$funnel) {
            throw new \InvalidArgumentException('Funil não encontrado');
        }

        // Não permitir deletar funil padrão
        if ($funnel['is_default']) {
            throw new \InvalidArgumentException('Não é possível deletar o funil padrão do sistema');
        }

        // Verificar se há conversas no funil
        $conversationCount = \App\Helpers\Database::query(
            "SELECT COUNT(*) as count FROM conversations c
             INNER JOIN funnel_stages fs ON c.funnel_stage_id = fs.id
             WHERE fs.funnel_id = ?",
            [$funnelId]
        )[0]['count'] ?? 0;

        if ($conversationCount > 0) {
            throw new \InvalidArgumentException(
                "Este funil possui {$conversationCount} conversa(s) ativa(s). " .
                "Mova ou finalize todas as conversas antes de deletar o funil."
            );
        }

        // Deletar funil (cascade vai deletar as etapas automaticamente)
        return Funnel::delete($funnelId);
    }
    
    /**
     * Reordenar etapa (mover para cima/baixo)
     * 
     * @param int $stageId ID da etapa
     * @param string $direction 'up' ou 'down'
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function reorderStage(int $stageId, string $direction): bool
    {
        $db = \App\Helpers\Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Buscar etapa atual
            $currentStage = \App\Models\FunnelStage::find($stageId);
            if (!$currentStage) {
                throw new \InvalidArgumentException('Etapa não encontrada');
            }
            
            $funnelId = $currentStage['funnel_id'];
            
            // Buscar todas as etapas do funil ordenadas por stage_order
            $sql = "SELECT * FROM funnel_stages WHERE funnel_id = ? ORDER BY stage_order ASC, id ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$funnelId]);
            $allStages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Se não houver stage_order definido, inicializar
            $needsInitialization = false;
            foreach ($allStages as $stage) {
                if ($stage['stage_order'] === null || $stage['stage_order'] === '') {
                    $needsInitialization = true;
                    break;
                }
            }
            
            if ($needsInitialization) {
                // Inicializar stage_order para todas as etapas
                foreach ($allStages as $index => $stage) {
                    $sql = "UPDATE funnel_stages SET stage_order = ? WHERE id = ?";
                    $db->prepare($sql)->execute([$index + 1, $stage['id']]);
                }
                // Recarregar etapas com os novos valores
                $stmt = $db->prepare("SELECT * FROM funnel_stages WHERE funnel_id = ? ORDER BY stage_order ASC, id ASC");
                $stmt->execute([$funnelId]);
                $allStages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            
            // Encontrar índice da etapa atual
            $currentIndex = null;
            foreach ($allStages as $idx => $stage) {
                if ($stage['id'] == $stageId) {
                    $currentIndex = $idx;
                    break;
                }
            }
            
            if ($currentIndex === null) {
                throw new \InvalidArgumentException('Etapa não encontrada no funil');
            }
            
            // Determinar índice da etapa para trocar
            $swapIndex = null;
            if ($direction === 'up' && $currentIndex > 0) {
                $swapIndex = $currentIndex - 1;
            } elseif ($direction === 'down' && $currentIndex < count($allStages) - 1) {
                $swapIndex = $currentIndex + 1;
            } else {
                // Já está na primeira ou última posição
                $db->rollBack();
                return true;
            }
            
            $swapStage = $allStages[$swapIndex];
            
            // Trocar os valores de stage_order entre as duas etapas
            $currentOrder = (int)$allStages[$currentIndex]['stage_order'];
            $swapOrder = (int)$swapStage['stage_order'];
            
            // Atualizar etapa atual
            $sql = "UPDATE funnel_stages SET stage_order = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$swapOrder, $stageId]);
            
            // Atualizar etapa trocada
            $sql = "UPDATE funnel_stages SET stage_order = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$currentOrder, $swapStage['id']]);
            
            $db->commit();
            
            error_log("Etapa {$stageId} reordenada: {$direction} (ordem {$currentOrder} <-> {$swapOrder})");
            
            return true;
            
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Erro ao reordenar etapa {$stageId}: " . $e->getMessage());
            throw new \Exception('Erro ao reordenar etapa: ' . $e->getMessage());
        }
    }
}

