<?php
/**
 * Controller de Coaching em Tempo Real
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Auth;
use App\Helpers\Request;
use App\Services\RealtimeCoachingService;
use App\Models\RealtimeCoachingHint;

class RealtimeCoachingController
{
    /**
     * Buscar hints de uma conversa
     * GET /api/coaching/hints/:conversationId
     */
    public function getHintsByConversation(int $conversationId): void
    {
        // ✅ Verificar se coaching está habilitado
        $settings = \App\Services\RealtimeCoachingService::getSettings();
        if (!$settings['enabled']) {
            Response::json([
                'success' => true,
                'enabled' => false,
                'hints' => [],
                'hints_by_message' => []
            ]);
            return;
        }
        
        $userId = Auth::user()['id'];
        
        // Buscar hints da conversa
        $sql = "SELECT * FROM realtime_coaching_hints 
                WHERE conversation_id = :conversation_id 
                AND agent_id = :agent_id 
                ORDER BY created_at DESC";
        
        $hints = \App\Helpers\Database::fetchAll($sql, [
            'conversation_id' => $conversationId,
            'agent_id' => $userId
        ]);
        
        // Agrupar hints por message_id
        $hintsByMessage = [];
        foreach ($hints as $hint) {
            $messageId = $hint['message_id'];
            if ($messageId) {
                if (!isset($hintsByMessage[$messageId])) {
                    $hintsByMessage[$messageId] = [];
                }
                $hintsByMessage[$messageId][] = $hint;
            }
        }
        
        Response::json([
            'success' => true,
            'enabled' => true,
            'hints' => $hints,
            'hints_by_message' => $hintsByMessage
        ]);
    }
    
    /**
     * Buscar hints pendentes (não visualizados)
     * GET /api/coaching/hints/pending
     */
    public function getPendingHints(): void
    {
        // ✅ Verificar se coaching está habilitado
        $settings = \App\Services\RealtimeCoachingService::getSettings();
        if (!$settings['enabled']) {
            Response::json([
                'success' => true,
                'enabled' => false,
                'hints' => []
            ]);
            return;
        }
        
        $userId = Auth::user()['id'];
        $conversationId = Request::get('conversation_id');
        
        if (!$conversationId) {
            Response::json(['error' => 'conversation_id é obrigatório'], 400);
            return;
        }
        
        $hints = RealtimeCoachingService::getPendingHintsForAgent(
            $userId,
            (int)$conversationId,
            30 // Últimos 30 segundos
        );
        
        Response::json([
            'success' => true,
            'hints' => $hints
        ]);
    }
    
    /**
     * Marcar hint como visualizado
     * POST /api/coaching/hints/:hintId/view
     */
    public function markAsViewed(int $hintId): void
    {
        $userId = Auth::user()['id'];
        
        // Verificar se o hint pertence ao agente
        $hint = RealtimeCoachingHint::find($hintId);
        
        if (!$hint || $hint['agent_id'] != $userId) {
            Response::json(['error' => 'Hint não encontrado'], 404);
            return;
        }
        
        $success = RealtimeCoachingHint::markAsViewed($hintId);
        
        Response::json([
            'success' => $success
        ]);
    }
    
    /**
     * Enviar feedback do hint
     * POST /api/coaching/hints/:hintId/feedback
     */
    public function sendFeedback(int $hintId): void
    {
        $userId = Auth::user()['id'];
        $feedback = Request::post('feedback'); // 'helpful' ou 'not_helpful'
        
        if (!in_array($feedback, ['helpful', 'not_helpful'])) {
            Response::json(['error' => 'Feedback inválido'], 400);
            return;
        }
        
        // Verificar se o hint pertence ao agente
        $hint = RealtimeCoachingHint::find($hintId);
        
        if (!$hint || $hint['agent_id'] != $userId) {
            Response::json(['error' => 'Hint não encontrado'], 404);
            return;
        }
        
        $success = RealtimeCoachingHint::setFeedback($hintId, $feedback);
        
        Response::json([
            'success' => $success
        ]);
    }
    
    /**
     * Usar sugestão (copiar para área de transferência)
     * POST /api/coaching/hints/:hintId/use-suggestion
     */
    public function useSuggestion(int $hintId): void
    {
        $userId = Auth::user()['id'];
        $suggestionIndex = Request::post('suggestion_index');
        
        // Verificar se o hint pertence ao agente
        $hint = RealtimeCoachingHint::find($hintId);
        
        if (!$hint || $hint['agent_id'] != $userId) {
            Response::json(['error' => 'Hint não encontrado'], 404);
            return;
        }
        
        // Marcar como visualizado se ainda não foi
        if (!$hint['viewed_at']) {
            RealtimeCoachingHint::markAsViewed($hintId);
        }
        
        // Retornar a sugestão
        $suggestions = json_decode($hint['suggestions'], true) ?? [];
        $suggestion = $suggestions[$suggestionIndex] ?? null;
        
        Response::json([
            'success' => true,
            'suggestion' => $suggestion
        ]);
    }
}
