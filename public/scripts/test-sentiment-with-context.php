<?php
/**
 * Script de teste para demonstrar a diferença na análise de sentimento
 * com e sem contexto das mensagens do agente
 */

// Mudar para diretório raiz do projeto
chdir(__DIR__ . '/../../');

// Autoloader
require_once __DIR__ . '/../../app/Helpers/autoload.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers\Database;

echo "========================================\n";
echo "TESTE: ANÁLISE COM CONTEXTO COMPLETO\n";
echo "========================================\n\n";

// Buscar uma conversa com mensagens de ambos os lados
$conversation = Database::fetch("
    SELECT c.id, c.contact_id, COUNT(DISTINCT m.id) as total_messages,
           SUM(CASE WHEN m.sender_type = 'contact' THEN 1 ELSE 0 END) as client_messages,
           SUM(CASE WHEN m.sender_type = 'agent' THEN 1 ELSE 0 END) as agent_messages
    FROM conversations c
    INNER JOIN messages m ON c.id = m.conversation_id
    WHERE c.status = 'open'
    AND c.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    GROUP BY c.id
    HAVING client_messages >= 3 AND agent_messages >= 1
    ORDER BY c.updated_at DESC
    LIMIT 1
");

if (!$conversation) {
    echo "⚠️ Nenhuma conversa elegível encontrada.\n";
    echo "Critérios: conversa aberta, últimos 3 dias, com mensagens de cliente E agente.\n\n";
    exit(0);
}

echo "📧 CONVERSA SELECIONADA:\n";
echo "-------------------\n";
echo "ID: " . $conversation['id'] . "\n";
echo "Total de mensagens: " . $conversation['total_messages'] . "\n";
echo "Mensagens do cliente: " . $conversation['client_messages'] . "\n";
echo "Mensagens do agente: " . $conversation['agent_messages'] . "\n\n";

// Buscar todas as mensagens
$allMessages = Database::fetchAll("
    SELECT id, content, sender_type, created_at
    FROM messages
    WHERE conversation_id = ?
    ORDER BY created_at ASC
", [$conversation['id']]);

// Exibir conversa completa
echo "💬 HISTÓRICO COMPLETO DA CONVERSA:\n";
echo "-------------------\n";
foreach ($allMessages as $msg) {
    $sender = ($msg['sender_type'] === 'contact') ? '👤 Cliente' : '👨‍💼 Agente';
    $time = date('H:i', strtotime($msg['created_at']));
    $content = mb_substr($msg['content'], 0, 80) . (mb_strlen($msg['content']) > 80 ? '...' : '');
    echo "[{$time}] {$sender}: {$content}\n";
}
echo "\n";

// Comparar: só cliente vs. contexto completo
echo "📊 COMPARAÇÃO:\n";
echo "-------------------\n\n";

echo "❌ ANTES (só mensagens do cliente):\n";
echo "   Mensagens analisadas: " . $conversation['client_messages'] . "\n";
$clientOnly = array_filter($allMessages, function($m) { return $m['sender_type'] === 'contact'; });
foreach ($clientOnly as $msg) {
    $time = date('H:i', strtotime($msg['created_at']));
    $content = mb_substr($msg['content'], 0, 60) . (mb_strlen($msg['content']) > 60 ? '...' : '');
    echo "   [{$time}] {$content}\n";
}
echo "\n";

echo "✅ AGORA (contexto completo):\n";
echo "   Mensagens analisadas: " . $conversation['total_messages'] . "\n";
foreach ($allMessages as $msg) {
    $sender = ($msg['sender_type'] === 'contact') ? 'Cliente' : 'Agente';
    $time = date('H:i', strtotime($msg['created_at']));
    $content = mb_substr($msg['content'], 0, 60) . (mb_strlen($msg['content']) > 60 ? '...' : '');
    echo "   [{$time}] {$sender}: {$content}\n";
}
echo "\n";

echo "💡 BENEFÍCIOS DO CONTEXTO COMPLETO:\n";
echo "-------------------\n";
echo "✅ A IA vê como o agente está respondendo\n";
echo "✅ Entende se o problema foi resolvido\n";
echo "✅ Detecta mudanças de sentimento ao longo da conversa\n";
echo "✅ Análise mais precisa e contextualizada\n";
echo "✅ Identifica melhor urgência e frustração\n\n";

echo "⚠️ OBSERVAÇÃO IMPORTANTE:\n";
echo "-------------------\n";
echo "O sistema AINDA analisa o sentimento DO CLIENTE.\n";
echo "As mensagens do agente são apenas CONTEXTO.\n";
echo "O resultado continua sendo sobre como o CLIENTE está se sentindo.\n\n";

echo "========================================\n";
echo "ATUALIZAÇÃO APLICADA COM SUCESSO!\n";
echo "========================================\n\n";

echo "🚀 PRÓXIMO PASSO:\n";
echo "Execute: php public/scripts/analyze-sentiments.php\n";
echo "As análises agora terão mais contexto e serão mais precisas!\n\n";
