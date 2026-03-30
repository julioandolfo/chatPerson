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

$conversationId = isset($argv[1]) ? (int)$argv[1] : 0;
$timerSeconds = isset($argv[2]) ? (int)$argv[2] : 5;

if (!$conversationId) {
    die("Usage: php process-single-buffer.php <conversationId> <timerSeconds>\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('America/Sao_Paulo');

use App\Helpers\Logger;
use App\Services\AIAgentService;

// Registrar shutdown handler para capturar fatal errors
register_shutdown_function(function() use ($conversationId) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        Logger::aiTools("[BUFFER BG FATAL] conv={$conversationId}: {$error['message']} em {$error['file']}:{$error['line']}");
    }
});

Logger::aiTools("[BUFFER BG] Iniciando processamento background: conv={$conversationId}, timer={$timerSeconds}s");

sleep($timerSeconds);

Logger::aiTools("[BUFFER BG] Timer expirado, processando: conv={$conversationId}");

$bufferDir = __DIR__ . '/../storage/ai_buffers/';
$bufferFile = $bufferDir . 'buffer_' . $conversationId . '.json';

if (!file_exists($bufferFile)) {
    Logger::aiTools("[BUFFER BG] Buffer não encontrado (já processado?): conv={$conversationId}");
    exit(0);
}

$lockFile = $bufferDir . 'lock_' . $conversationId . '.lock';
$lockFp = fopen($lockFile, 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    Logger::aiTools("[BUFFER BG] ⏭️ Outro processador já está tratando este buffer, saindo: conv={$conversationId}");
    if ($lockFp) fclose($lockFp);
    exit(0);
}

try {
    if (!file_exists($bufferFile)) {
        Logger::aiTools("[BUFFER BG] Buffer já foi processado por outro processo: conv={$conversationId}");
        flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile);
        exit(0);
    }
    
    $bufferData = json_decode(file_get_contents($bufferFile), true);
    
    if (!$bufferData) {
        Logger::aiTools("[BUFFER BG ERROR] Buffer inválido: conv={$conversationId}");
        @unlink($bufferFile);
        flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile);
        exit(1);
    }
    
    $agentId = $bufferData['agent_id'] ?? null;
    $messages = $bufferData['messages'] ?? [];
    $expiresAt = $bufferData['expires_at'] ?? 0;
    $now = time();
    
    if (!$agentId || empty($messages)) {
        Logger::aiTools("[BUFFER BG ERROR] Dados incompletos: conv={$conversationId}, agentId=" . ($agentId ?? 'NULL') . ", messages=" . count($messages));
        @unlink($bufferFile);
        flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile);
        exit(1);
    }
    
    if ($expiresAt > $now) {
        $remaining = $expiresAt - $now;
        Logger::aiTools("[BUFFER BG] Timer renovado, aguardando mais {$remaining}s: conv={$conversationId}");
        flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile);
        sleep($remaining);
        exec(sprintf('php %s %d %d > /dev/null 2>&1 &', __FILE__, $conversationId, $remaining));
        exit(0);
    }
    
    $groupedMessage = implode("\n\n", array_map(function($msg) {
        return $msg['content'];
    }, $messages));
    
    Logger::aiTools("[BUFFER BG] Processando " . count($messages) . " mensagens agrupadas: conv={$conversationId}, agentId={$agentId}, msgLen=" . strlen($groupedMessage));
    
    @unlink($bufferFile);
    
    Logger::aiTools("[BUFFER BG] Chamando AIAgentService::processMessage...");
    $result = AIAgentService::processMessage($conversationId, $agentId, $groupedMessage);
    Logger::aiTools("[BUFFER BG] ✅ Processamento concluído: conv={$conversationId}, result=" . json_encode($result ?? 'null'));
    
    flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile);
    exit(0);
    
} catch (\Throwable $e) {
    Logger::aiTools("[BUFFER BG ERROR] " . get_class($e) . ": " . $e->getMessage());
    Logger::aiTools("[BUFFER BG ERROR] File: " . $e->getFile() . ":" . $e->getLine());
    Logger::aiTools("[BUFFER BG ERROR] Stack: " . $e->getTraceAsString());
    @unlink($bufferFile ?? '');
    if (isset($lockFp) && $lockFp) { flock($lockFp, LOCK_UN); fclose($lockFp); }
    if (isset($lockFile)) @unlink($lockFile);
    exit(1);
}
