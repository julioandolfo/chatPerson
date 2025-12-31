<?php
/**
 * Endpoint para polling de buffers (alternativa ao cron)
 * Chame via AJAX a cada 2-3 segundos do frontend ou via curl em loop
 * 
 * Uso: curl http://localhost/poll-buffers.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

// ✅ CRÍTICO: Definir timezone ANTES de qualquer operação com data/hora
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers\Logger;
use App\Services\AIAgentService;

// Diretório de buffers
$bufferDir = __DIR__ . '/../storage/ai_buffers/';
if (!is_dir($bufferDir)) {
    mkdir($bufferDir, 0755, true);
}

// Buscar arquivos de buffer
$bufferFiles = glob($bufferDir . 'buffer_*.json');
$now = time();
$processed = 0;
$waiting = 0;

foreach ($bufferFiles as $bufferFile) {
    try {
        $bufferData = json_decode(file_get_contents($bufferFile), true);
        
        if (!$bufferData) {
            @unlink($bufferFile);
            continue;
        }
        
        $conversationId = (int)str_replace(['buffer_', '.json'], '', basename($bufferFile));
        $agentId = $bufferData['agent_id'] ?? null;
        $messages = $bufferData['messages'] ?? [];
        $expiresAt = $bufferData['expires_at'] ?? 0;
        
        if (!$conversationId || !$agentId || empty($messages)) {
            @unlink($bufferFile);
            continue;
        }
        
        // Verificar se expirou
        if ($expiresAt > $now) {
            $waiting++;
            continue;
        }
        
        // Agrupar e processar
        $groupedMessage = implode("\n\n", array_map(function($msg) {
            return $msg['content'];
        }, $messages));
        
        Logger::aiTools("[POLL] Processando buffer expirado: conv={$conversationId}, msgs=" . count($messages));
        
        AIAgentService::processMessage($conversationId, $agentId, $groupedMessage);
        
        @unlink($bufferFile);
        $processed++;
        
    } catch (\Exception $e) {
        Logger::aiTools("[POLL ERROR] " . $e->getMessage());
        @unlink($bufferFile);
    }
}

echo json_encode([
    'success' => true,
    'processed' => $processed,
    'waiting' => $waiting,
    'timestamp' => date('Y-m-d H:i:s')
]);

