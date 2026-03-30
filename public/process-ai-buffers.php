<?php
/**
 * Script para processar buffers de mensagens da IA (Timer de Contexto)
 * Deve ser executado periodicamente (a cada 1-2 segundos via cron/scheduler)
 * 
 * Formato do buffer:
 * {
 *   "messages": [{"content": "msg1", "timestamp": 123}, ...],
 *   "agent_id": 21,
 *   "timer_seconds": 5,
 *   "first_message_at": 123,
 *   "last_message_at": 456,
 *   "expires_at": 461,
 *   "scheduled": true
 * }
 */

// Saída silenciosa por padrão (CLI ou web)
// ✅ Só enviar header se não estamos sendo incluídos (para evitar "headers already sent")
if (!headers_sent()) {
    @header('Content-Type: text/plain; charset=utf-8');
}

try {
    $autoloadCandidates = [
        __DIR__ . '/../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../app/Helpers/autoload.php', // fallback simples do projeto
    ];
    $loaded = false;
    foreach ($autoloadCandidates as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            $loaded = true;
            break;
        }
    }
    if (!$loaded) {
        $msg = '[process-ai-buffers] Autoload não encontrado. Rode "composer install" ou verifique app/Helpers/autoload.php. Procurei: ' . implode(', ', $autoloadCandidates);
        error_log($msg);
        echo "error: autoload - not found\ncandidates:\n" . implode("\n", $autoloadCandidates) . "\n";
        if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== basename(__FILE__)) {
            return; // Sendo incluído por outro script
        }
        http_response_code(500);
        exit(1);
    }
} catch (\Throwable $e) {
    $msg = '[process-ai-buffers] Autoload error: ' . $e->getMessage();
    error_log($msg);
    echo "error: autoload - " . $e->getMessage() . "\n";
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== basename(__FILE__)) {
        return; // Sendo incluído por outro script
    }
    http_response_code(500);
    exit(1);
}

// ✅ CRÍTICO: Definir timezone ANTES de qualquer operação com data/hora
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers\Logger;
use App\Services\AIAgentService;
use App\Models\Conversation;
use App\Models\AIAgent;

// Diretório de buffers
$bufferDir = __DIR__ . '/../storage/ai_buffers/';
if (!is_dir($bufferDir)) {
    @mkdir($bufferDir, 0755, true);
}

// Buscar arquivos de buffer
$bufferFiles = glob($bufferDir . 'buffer_*.json') ?: [];

if (empty($bufferFiles)) {
    // Sem buffers para processar
    // Se sendo incluído por outro script, apenas retornar (não matar o processo pai)
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== basename(__FILE__)) {
        return;
    }
    exit(0);
}

$now = time();
$processed = 0;
$skipped = 0;

foreach ($bufferFiles as $bufferFile) {
    try {
        // Extrair ID da conversa do nome do arquivo (buffer_123.json)
        $conversationId = (int)str_replace(['buffer_', '.json'], '', basename($bufferFile));
        
        // ✅ LOCK EXCLUSIVO NÃO-BLOQUEANTE para evitar processamento duplicado
        $lockFile = $bufferDir . 'lock_' . $conversationId . '.lock';
        $lockFp = fopen($lockFile, 'c');
        if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
            Logger::aiTools("[BUFFER PROCESSOR] ⏭️ Outro processador já trata conv={$conversationId}, pulando");
            if ($lockFp) fclose($lockFp);
            $skipped++;
            continue;
        }
        
        try {
            // Re-verificar se buffer ainda existe após adquirir lock
            if (!file_exists($bufferFile)) {
                Logger::aiTools("[BUFFER PROCESSOR] Buffer já processado por outro processo: conv={$conversationId}");
                continue;
            }
            
            // Ler dados do buffer
            $bufferData = json_decode(@file_get_contents($bufferFile), true);
            
            if (!$bufferData) {
                Logger::aiTools("[BUFFER PROCESSOR] Arquivo de buffer inválido: " . basename($bufferFile));
                @unlink($bufferFile);
                continue;
            }
            
            $agentId = $bufferData['agent_id'] ?? null;
            $messages = $bufferData['messages'] ?? [];
            $expiresAt = $bufferData['expires_at'] ?? 0;
            
            if (!$conversationId || !$agentId || empty($messages)) {
                Logger::aiTools("[BUFFER PROCESSOR] Dados incompletos no buffer: convId={$conversationId}, agentId={$agentId}, msgs=" . count($messages));
                @unlink($bufferFile);
                continue;
            }
            
            // Verificar se a conversa e o agente ainda existem
            $conversation = Conversation::find($conversationId);
            if (empty($conversation) || empty($conversation['contact_id'])) {
                Logger::aiTools("[BUFFER PROCESSOR] 🔥 Descartando buffer: conversa inexistente ou sem contato (convId={$conversationId})");
                @unlink($bufferFile);
                continue;
            }
            $agentModel = AIAgent::find($agentId);
            if (empty($agentModel) || empty($agentModel['enabled'])) {
                Logger::aiTools("[BUFFER PROCESSOR] 🔥 Descartando buffer: agente inexistente/inativo (agentId={$agentId}, convId={$conversationId})");
                @unlink($bufferFile);
                continue;
            }
            
            // Verificar se já passou o tempo de expiração
            if ($expiresAt > $now) {
                $skipped++;
                continue;
            }
            
            // Agrupar mensagens
            $groupedMessage = implode("\n\n", array_map(function($msg) {
                return $msg['content'];
            }, $messages));
            
            // Processar mensagem
            Logger::aiTools("[BUFFER PROCESSOR] Processando buffer expirado: conv={$conversationId}, agent={$agentId}, msgs=" . count($messages) . ", groupedLen=" . strlen($groupedMessage));
            
            // ✅ DELETAR buffer ANTES de processar (evita que outro processador pegue o mesmo)
            @unlink($bufferFile);
            
            AIAgentService::processMessage($conversationId, $agentId, $groupedMessage);
            
            Logger::aiTools("[BUFFER PROCESSOR] ✅ Buffer processado com sucesso: conv={$conversationId}");
            
            $processed++;
            
        } finally {
            if (is_resource($lockFp)) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
            }
            @unlink($lockFile);
        }
        
    } catch (\Throwable $e) {
        Logger::aiTools("[BUFFER PROCESSOR ERROR] Erro ao processar " . basename($bufferFile) . ": " . $e->getMessage());
        Logger::aiTools("[BUFFER PROCESSOR ERROR] Stack trace: " . $e->getTraceAsString());
        // Remover arquivo problemático para evitar loop infinito
        @unlink($bufferFile);
    }
}

if ($processed > 0 || $skipped > 0) {
    Logger::aiTools("[BUFFER PROCESSOR] Ciclo concluído: processados={$processed}, aguardando={$skipped}");
}

echo "ok\n";
// Se sendo incluído por outro script, apenas retornar (não matar o processo pai)
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== basename(__FILE__)) {
    return;
}
exit(0);

