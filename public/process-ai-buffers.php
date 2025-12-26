<?php
/**
 * Script para processar buffers de mensagens da IA
 * Deve ser executado periodicamente (a cada 1 segundo via cron/scheduler)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\Logger;
use App\Services\AIAgentService;

// Diretório de buffers
$bufferDir = __DIR__ . '/../storage/ai_buffers/';
if (!is_dir($bufferDir)) {
    mkdir($bufferDir, 0755, true);
}

// Buscar arquivos de buffer
$bufferFiles = glob($bufferDir . 'buffer_*.json');

if (empty($bufferFiles)) {
    // Sem buffers para processar
    exit(0);
}

foreach ($bufferFiles as $bufferFile) {
    try {
        // Ler dados do buffer
        $bufferData = json_decode(file_get_contents($bufferFile), true);
        
        if (!$bufferData) {
            Logger::error("process-ai-buffers - Arquivo de buffer inválido: {$bufferFile}");
            @unlink($bufferFile);
            continue;
        }
        
        $conversationId = $bufferData['conversation_id'] ?? null;
        $agentId = $bufferData['agent_id'] ?? null;
        $message = $bufferData['message'] ?? null;
        $scheduledTime = $bufferData['scheduled_time'] ?? 0;
        
        if (!$conversationId || !$agentId || !$message) {
            Logger::error("process-ai-buffers - Dados incompletos no buffer: {$bufferFile}");
            @unlink($bufferFile);
            continue;
        }
        
        // Verificar se já passou o tempo agendado
        if (time() < $scheduledTime) {
            // Ainda não é hora de processar
            continue;
        }
        
        // Processar mensagem
        Logger::info("process-ai-buffers - Processando buffer: conv={$conversationId}, agent={$agentId}, msgLen=" . strlen($message));
        
        AIAgentService::processMessage($conversationId, $agentId, $message);
        
        Logger::info("process-ai-buffers - ✅ Buffer processado com sucesso: {$bufferFile}");
        
        // Remover arquivo de buffer
        @unlink($bufferFile);
        
    } catch (\Exception $e) {
        Logger::error("process-ai-buffers - Erro ao processar buffer {$bufferFile}: " . $e->getMessage());
        Logger::error("process-ai-buffers - Stack trace: " . $e->getTraceAsString());
        // Remover arquivo problemático
        @unlink($bufferFile);
    }
}

exit(0);

