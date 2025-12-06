<?php
/**
 * Service AutomationDelayService
 * Gerencia delays agendados de automações
 */

namespace App\Services;

use App\Models\AutomationDelay;
use App\Models\AutomationExecution;

class AutomationDelayService
{
    /**
     * Agendar delay de automação
     */
    public static function scheduleDelay(
        int $automationId,
        int $conversationId,
        string $nodeId,
        int $delaySeconds,
        array $nodeData = [],
        array $nextNodes = [],
        ?int $executionId = null
    ): int {
        return AutomationDelay::schedule(
            $automationId,
            $conversationId,
            $nodeId,
            $delaySeconds,
            $nodeData,
            $nextNodes,
            $executionId
        );
    }

    /**
     * Processar delays pendentes
     */
    public static function processPendingDelays(int $limit = 100): array
    {
        $delays = AutomationDelay::getPendingDelays($limit);
        $processed = [];
        $errors = [];

        foreach ($delays as $delay) {
            try {
                // Marcar como executando
                AutomationDelay::markAsExecuting($delay['id']);

                // Continuar execução da automação
                self::continueAutomationExecution($delay);

                // Marcar como concluído
                AutomationDelay::markAsCompleted($delay['id']);
                
                $processed[] = $delay['id'];
            } catch (\Exception $e) {
                // Marcar como falhou
                AutomationDelay::markAsFailed($delay['id'], $e->getMessage());
                $errors[] = [
                    'delay_id' => $delay['id'],
                    'error' => $e->getMessage()
                ];
                error_log("Erro ao processar delay {$delay['id']}: " . $e->getMessage());
            }
        }

        return [
            'processed' => $processed,
            'errors' => $errors,
            'total' => count($delays)
        ];
    }

    /**
     * Continuar execução da automação após delay
     */
    private static function continueAutomationExecution(array $delay): void
    {
        $nodeData = json_decode($delay['node_data'], true) ?? [];
        $nextNodes = json_decode($delay['next_nodes'], true) ?? [];
        $conversationId = (int)$delay['conversation_id'];
        $executionId = $delay['execution_id'] ? (int)$delay['execution_id'] : null;
        $automationId = (int)$delay['automation_id'];

        // Obter todos os nós da automação
        $automation = \App\Models\Automation::findWithNodes($automationId);
        if (!$automation || empty($automation['nodes'])) {
            throw new \Exception("Automação não encontrada ou sem nós");
        }
        $allNodes = $automation['nodes'];

        // Executar próximos nós
        if (!empty($nextNodes)) {
            foreach ($nextNodes as $nextNodeId) {
                $nextNode = self::findNodeById($nextNodeId, $allNodes);
                if ($nextNode) {
                    \App\Services\AutomationService::executeNodeForDelay(
                        $nextNode,
                        $conversationId,
                        $allNodes,
                        $executionId
                    );
                }
            }
        } else {
            // Se não há próximos nós definidos, tentar seguir conexões do nó de delay
            $delayNode = self::findNodeById($delay['node_id'], $allNodes);
            if ($delayNode && !empty($delayNode['node_data']['connections'])) {
                foreach ($delayNode['node_data']['connections'] as $connection) {
                    $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                    if ($nextNode) {
                        \App\Services\AutomationService::executeNodeForDelay(
                            $nextNode,
                            $conversationId,
                            $allNodes,
                            $executionId
                        );
                    }
                }
            }
        }
    }

    /**
     * Encontrar nó por ID
     */
    private static function findNodeById(string $nodeId, array $allNodes): ?array
    {
        foreach ($allNodes as $node) {
            if ($node['id'] === $nodeId || $node['node_id'] === $nodeId) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Cancelar delays de uma conversa
     */
    public static function cancelByConversation(int $conversationId): int
    {
        return AutomationDelay::cancelByConversation($conversationId);
    }

    /**
     * Cancelar delays de uma execução
     */
    public static function cancelByExecution(int $executionId): int
    {
        return AutomationDelay::cancelByExecution($executionId);
    }

    /**
     * Limpar delays antigos
     */
    public static function cleanOldDelays(int $days = 30): int
    {
        return AutomationDelay::cleanOldDelays($days);
    }
}

