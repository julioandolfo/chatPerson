<?php
/**
 * Job FollowupJob
 * Executa followups automáticos periodicamente
 */

namespace App\Jobs;

use App\Services\FollowupService;

class FollowupJob
{
    /**
     * Executar job de followup
     */
    public static function run(): void
    {
        try {
            echo "[" . date('Y-m-d H:i:s') . "] Iniciando FollowupJob...\n";
            
            // Executar todos os tipos de followup
            FollowupService::runFollowups();
            
            echo "[" . date('Y-m-d H:i:s') . "] FollowupJob executado com sucesso\n";
            error_log("FollowupJob executado com sucesso");
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] ERRO no FollowupJob: " . $e->getMessage() . "\n";
            error_log("Erro ao executar FollowupJob: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Executar followup para uma conversa específica
     */
    public static function runForConversation(int $conversationId): void
    {
        try {
            $conversation = \App\Models\Conversation::find($conversationId);
            if ($conversation && $conversation['status'] === 'closed') {
                FollowupService::processFollowup($conversation, 'general');
            }
        } catch (\Exception $e) {
            error_log("Erro ao executar followup para conversa {$conversationId}: " . $e->getMessage());
        }
    }
}

