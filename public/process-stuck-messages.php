<?php
/**
 * Script temporário para processar mensagens de agentes que ficaram presas no banco
 * sem serem enviadas ao WhatsApp.
 * 
 * Acesse via navegador: https://seu-dominio/process-stuck-messages.php
 * DELETAR ESTE ARQUIVO APÓS USO.
 */

date_default_timezone_set('America/Sao_Paulo');
ini_set('max_execution_time', '600');
ini_set('memory_limit', '256M');

$appConfig = require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/autoload.php';
mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Processando mensagens presas ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = \App\Helpers\Database::getInstance();
    
    $stmt = $db->prepare("
        SELECT m.id, m.conversation_id, m.content, m.sender_type, m.message_type, 
               m.status, m.external_id, m.created_at,
               c.channel, c.contact_id
        FROM messages m
        JOIN conversations c ON c.id = m.conversation_id
        WHERE m.sender_type = 'agent'
          AND m.external_id IS NULL
          AND (m.message_type IS NULL OR m.message_type NOT IN ('note'))
          AND (m.status IS NULL OR m.status NOT IN ('sent', 'error', 'failed', 'queued'))
          AND c.channel IN ('whatsapp', 'instagram', 'facebook', 'telegram')
          AND m.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute();
    $stuckMessages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "Encontradas " . count($stuckMessages) . " mensagem(ns) presa(s)\n\n";
    
    if (empty($stuckMessages)) {
        echo "Nenhuma mensagem para processar!\n";
        exit;
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($stuckMessages as $msg) {
        $msgId = $msg['id'];
        $convId = $msg['conversation_id'];
        $preview = mb_substr($msg['content'] ?? '[mídia]', 0, 60);
        
        echo "Msg #{$msgId} (conv #{$convId}) | {$msg['created_at']} | {$msg['channel']}\n";
        echo "  Conteúdo: {$preview}\n";
        
        try {
            \App\Services\ConversationService::processIntegrationSend($msgId);
            
            $updated = \App\Models\Message::find($msgId);
            $newStatus = $updated['status'] ?? 'NULL';
            $extId = $updated['external_id'] ?? 'NULL';
            
            if ($newStatus === 'sent' || !empty($updated['external_id'])) {
                echo "  ✅ ENVIADA (external_id={$extId})\n\n";
                $successCount++;
            } else {
                echo "  ⚠️ Status={$newStatus}, external_id={$extId}\n\n";
                $errorCount++;
            }
        } catch (\Exception $e) {
            echo "  ❌ ERRO: " . $e->getMessage() . "\n\n";
            $errorCount++;
        }
        
        flush();
    }
    
    echo "=== RESULTADO ===\n";
    echo "✅ Enviadas: {$successCount}\n";
    echo "❌ Erros: {$errorCount}\n";
    echo "Total: " . count($stuckMessages) . "\n";
    
} catch (\Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
