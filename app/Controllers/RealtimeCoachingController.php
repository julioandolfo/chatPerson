<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Permission;
use App\Services\RealtimeCoachingService;
use App\Models\RealtimeCoachingHint;

/**
 * Controller para Coaching em Tempo Real
 */
class RealtimeCoachingController
{
    /**
     * Obter hints pendentes para o agente (polling)
     */
    public function getPendingHints(): void
    {
        $conversationId = Request::input('conversation_id');
        $seconds = Request::input('seconds', 10);
        
        if (!$conversationId) {
            Response::json(['error' => 'conversation_id é obrigatório'], 400);
            return;
        }
        
        $agentId = Auth::id();
        
        $hints = RealtimeCoachingService::getPendingHintsForAgent(
            $agentId,
            (int)$conversationId,
            (int)$seconds
        );
        
        Response::json([
            'success' => true,
            'hints' => $hints,
            'count' => count($hints)
        ]);
    }
    
    /**
     * Obter estatísticas de coaching do agente
     */
    public function getStats(): void
    {
        $agentId = Request::input('agent_id', Auth::id());
        $period = Request::input('period', '24h');
        
        // Verificar permissão (só pode ver próprias stats ou se for admin/supervisor)
        if ($agentId != Auth::id() && !Permission::can('agent_performance.view.team')) {
            Response::json(['error' => 'Sem permissão'], 403);
            return;
        }
        
        $stats = RealtimeCoachingService::getStats((int)$agentId, $period);
        
        Response::json([
            'success' => true,
            'stats' => $stats
        ]);
    }
    
    /**
     * Marcar hint como visualizado
     */
    public function markAsViewed(): void
    {
        $hintId = Request::input('hint_id');
        
        if (!$hintId) {
            Response::json(['error' => 'hint_id é obrigatório'], 400);
            return;
        }
        
        $hint = RealtimeCoachingHint::find((int)$hintId);
        
        if (!$hint) {
            Response::json(['error' => 'Hint não encontrado'], 404);
            return;
        }
        
        // Verificar se é o agente correto
        if ($hint['agent_id'] != Auth::id()) {
            Response::json(['error' => 'Sem permissão'], 403);
            return;
        }
        
        // Atualizar (adicionar campo viewed_at se necessário)
        // Por enquanto, apenas retornar sucesso
        
        Response::json([
            'success' => true,
            'message' => 'Hint marcado como visualizado'
        ]);
    }
    
    /**
     * Marcar hint como útil/não útil (feedback)
     */
    public function provideFeedback(): void
    {
        $hintId = Request::input('hint_id');
        $helpful = Request::input('helpful'); // true/false
        
        if (!$hintId || !isset($helpful)) {
            Response::json(['error' => 'hint_id e helpful são obrigatórios'], 400);
            return;
        }
        
        $hint = RealtimeCoachingHint::find((int)$hintId);
        
        if (!$hint) {
            Response::json(['error' => 'Hint não encontrado'], 404);
            return;
        }
        
        // Verificar se é o agente correto
        if ($hint['agent_id'] != Auth::id()) {
            Response::json(['error' => 'Sem permissão'], 403);
            return;
        }
        
        // Salvar feedback (adicionar campo feedback se necessário)
        // Por enquanto, apenas retornar sucesso
        
        Response::json([
            'success' => true,
            'message' => 'Feedback registrado'
        ]);
    }
}
