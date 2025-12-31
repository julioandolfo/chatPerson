<?php
/**
 * Processa um único buffer após aguardar o timer
 * Executado em background pelo AIAgentService
 * 
 * Uso: php process-single-buffer.php <conversationId> <timerSeconds>
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied');
}

// Obter argumentos
$conversationId = isset($argv[1]) ? (int)$argv[1] : 0;
$timerSeconds = isset($argv[2]) ? (int)$argv[2] : 5;

if (!$conversationId) {
    die("Usage: php process-single-buffer.php <conversationId> <timerSeconds>\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

// ✅ CRÍTICO: Definir timezone ANTES de qualquer operação com data/hora
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers\Logger;
use App\Services\AIAgentService;

Logger::aiTools("[BUFFER BG] Iniciando processamento background: conv={$conversationId}, timer={$timerSeconds}s");

// Aguardar o timer
sleep($timerSeconds);

Logger::aiTools("[BUFFER BG] Timer expirado, processando: conv={$conversationId}");

// Diretório de buffers
$bufferDir = __DIR__ . '/../storage/ai_buffers/';
$bufferFile = $bufferDir . 'buffer_' . $conversationId . '.json';

if (!file_exists($bufferFile)) {
    Logger::aiTools("[BUFFER BG] Buffer não encontrado (já processado?): conv={$conversationId}");
    exit(0);
}

try {
    // Ler dados do buffer
    $bufferData = json_decode(file_get_contents($bufferFile), true);
    
    if (!$bufferData) {
        Logger::aiTools("[BUFFER BG ERROR] Buffer inválido: conv={$conversationId}");
        @unlink($bufferFile);
        exit(1);
    }
    
    $agentId = $bufferData['agent_id'] ?? null;
    $messages = $bufferData['messages'] ?? [];
    $expiresAt = $bufferData['expires_at'] ?? 0;
    $now = time();
    
    if (!$agentId || empty($messages)) {
        Logger::aiTools("[BUFFER BG ERROR] Dados incompletos: conv={$conversationId}");
        @unlink($bufferFile);
        exit(1);
    }
    
    // Verificar se não foi renovado (nova mensagem chegou)
    if ($expiresAt > $now) {
        $remaining = $expiresAt - $now;
        Logger::aiTools("[BUFFER BG] Timer renovado, aguardando mais {$remaining}s: conv={$conversationId}");
        // Reagendar
        sleep($remaining);
        // Reprocessar (recursivo)
        exec(sprintf('php %s %d %d > /dev/null 2>&1 &', __FILE__, $conversationId, $remaining));
        exit(0);
    }
    
    // Agrupar mensagens
    $groupedMessage = implode("\n\n", array_map(function($msg) {
        return $msg['content'];
    }, $messages));
    
    Logger::aiTools("[BUFFER BG] Processando " . count($messages) . " mensagens agrupadas: conv={$conversationId}");
    
    // Processar
    AIAgentService::processMessage($conversationId, $agentId, $groupedMessage);
    
    Logger::aiTools("[BUFFER BG] ✅ Processamento concluído: conv={$conversationId}");
    
    // Remover buffer
    @unlink($bufferFile);
    
    exit(0);
    
} catch (\Exception $e) {
    Logger::aiTools("[BUFFER BG ERROR] Erro: " . $e->getMessage());
    Logger::aiTools("[BUFFER BG ERROR] Stack: " . $e->getTraceAsString());
    @unlink($bufferFile);
    exit(1);
}

