<?php
/**
 * Script de Debug de Conversa
 * 
 * USO: php debug-conversation.php [CONVERSATION_ID]
 * Exemplo: php debug-conversation.php 123
 */

require_once __DIR__ . '/bootstrap.php';

use App\Helpers\Database;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ContactAgent;

// Verificar se foi passado ID da conversa
if ($argc < 2) {
    echo "❌ ERRO: Informe o ID da conversa\n";
    echo "USO: php debug-conversation.php [CONVERSATION_ID]\n";
    echo "Exemplo: php debug-conversation.php 123\n";
    exit(1);
}

$conversationId = (int)$argv[1];

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
echo "║            🔍 DEBUG DE CONVERSA - ID: {$conversationId}                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// 1. INFORMAÇÕES BÁSICAS DA CONVERSA
// ============================================================================
echo "📋 INFORMAÇÕES BÁSICAS\n";
echo str_repeat("─", 76) . "\n";

$conversation = Conversation::find($conversationId);
if (!$conversation) {
    echo "❌ ERRO: Conversa #{$conversationId} não encontrada!\n";
    exit(1);
}

echo "ID: {$conversation['id']}\n";
echo "Status: {$conversation['status']}\n";
echo "Canal: {$conversation['channel']}\n";
echo "Agente Atual: " . ($conversation['agent_id'] ? "#{$conversation['agent_id']}" : "NÃO ATRIBUÍDA") . "\n";
echo "Criada em: {$conversation['created_at']}\n";
echo "Atualizada em: {$conversation['updated_at']}\n";
echo "\n";

// Buscar nome do contato
$contact = Database::fetch("SELECT * FROM contacts WHERE id = ?", [$conversation['contact_id']]);
if ($contact) {
    echo "👤 CONTATO\n";
    echo str_repeat("─", 76) . "\n";
    echo "ID: {$contact['id']}\n";
    echo "Nome: {$contact['name']}\n";
    echo "Telefone: {$contact['phone']}\n";
    echo "Email: " . ($contact['email'] ?? 'N/A') . "\n";
    echo "\n";
}

// ============================================================================
// 2. AGENTE PRINCIPAL DO CONTATO
// ============================================================================
echo "👥 AGENTES DO CONTATO\n";
echo str_repeat("─", 76) . "\n";

$contactAgents = ContactAgent::getByContact($conversation['contact_id']);
if (empty($contactAgents)) {
    echo "⚠️  Nenhum agente atribuído ao contato\n";
} else {
    foreach ($contactAgents as $ca) {
        $isPrimary = $ca['is_primary'] ? '⭐ PRINCIPAL' : '';
        $autoAssign = $ca['auto_assign_on_reopen'] ? '🔄 Auto-atribuir' : '';
        echo "  • Agente #{$ca['agent_id']} - {$ca['agent_name']} {$isPrimary} {$autoAssign}\n";
        echo "    Prioridade: {$ca['priority']}\n";
        echo "    Criado em: {$ca['created_at']}\n";
    }
}
echo "\n";

// ============================================================================
// 3. HISTÓRICO DE ATRIBUIÇÕES
// ============================================================================
echo "📊 HISTÓRICO DE ATRIBUIÇÕES (conversation_assignments)\n";
echo str_repeat("─", 76) . "\n";

$assignments = Database::fetchAll(
    "SELECT ca.*, 
            u.name as agent_name,
            u2.name as assigned_by_name
     FROM conversation_assignments ca
     LEFT JOIN users u ON ca.agent_id = u.id
     LEFT JOIN users u2 ON ca.assigned_by = u2.id
     WHERE ca.conversation_id = ?
     ORDER BY ca.assigned_at ASC",
    [$conversationId]
);

if (empty($assignments)) {
    echo "⚠️  Nenhuma atribuição registrada\n";
} else {
    $counter = 1;
    foreach ($assignments as $assign) {
        $status = $assign['removed_at'] ? '❌ REMOVIDO' : '✅ ATIVO';
        $assignedBy = $assign['assigned_by'] ?? 0;
        $method = ($assignedBy == 0) ? 'auto' : (($assignedBy == $assign['agent_id']) ? 'auto' : 'manual');
        
        echo "{$counter}. {$assign['assigned_at']} - {$status}\n";
        echo "   Agente: #{$assign['agent_id']} - {$assign['agent_name']}\n";
        echo "   Atribuído por: " . ($assign['assigned_by_name'] ?? "Sistema") . " (#{$assignedBy})\n";
        echo "   Método: {$method}\n";
        if ($assign['removed_at']) {
            echo "   ❌ Removido em: {$assign['removed_at']}\n";
        }
        echo "\n";
        $counter++;
    }
}

// ============================================================================
// 4. MENSAGENS E ATRIBUIÇÕES CORRELACIONADAS
// ============================================================================
echo "💬 MENSAGENS E EVENTOS (Timeline)\n";
echo str_repeat("─", 76) . "\n";

// Buscar mensagens
$messages = Database::fetchAll(
    "SELECT 
        m.id,
        m.sender_type,
        m.sender_id,
        m.content,
        m.message_type,
        m.created_at,
        u.name as sender_name
     FROM messages m
     LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'agent'
     WHERE m.conversation_id = ?
     ORDER BY m.created_at ASC",
    [$conversationId]
);

// Buscar atividades (atribuições)
$activities = Database::fetchAll(
    "SELECT 
        'assignment' as type,
        assigned_at as created_at,
        agent_id,
        assigned_by,
        removed_at
     FROM conversation_assignments
     WHERE conversation_id = ?
     UNION ALL
     SELECT 
        'message' as type,
        created_at,
        sender_id as agent_id,
        0 as assigned_by,
        NULL as removed_at
     FROM messages
     WHERE conversation_id = ?
     ORDER BY created_at ASC",
    [$conversationId, $conversationId]
);

if (empty($activities)) {
    echo "⚠️  Nenhuma atividade encontrada\n";
} else {
    $counter = 1;
    $lastAgentId = null;
    
    foreach ($activities as $activity) {
        if ($activity['type'] === 'message') {
            $senderId = $activity['agent_id'];
            
            // Buscar detalhes da mensagem
            $msg = Database::fetch(
                "SELECT m.*, u.name as sender_name 
                 FROM messages m 
                 LEFT JOIN users u ON m.sender_id = u.id 
                 WHERE m.conversation_id = ? AND m.created_at = ?
                 LIMIT 1",
                [$conversationId, $activity['created_at']]
            );
            
            $senderType = $msg['sender_type'] ?? 'unknown';
            $icon = $senderType === 'contact' ? '👤' : '🧑‍💼';
            $senderName = $msg['sender_name'] ?? 'Sistema';
            $content = mb_substr($msg['content'] ?? '', 0, 60);
            $isNote = ($msg['message_type'] ?? '') === 'note' ? '📝 NOTA' : '';
            
            echo "{$counter}. {$activity['created_at']} {$icon} MENSAGEM\n";
            echo "   De: {$senderType}";
            if ($senderId > 0) {
                echo " #{$senderId} ({$senderName})";
            }
            echo " {$isNote}\n";
            echo "   Conteúdo: \"{$content}...\"\n";
            
            // 🔍 ANÁLISE: Verificar se gerou auto-atribuição
            if ($senderType === 'agent' && $senderId > 0 && $senderId != $lastAgentId) {
                echo "   ⚠️  POSSÍVEL AUTO-ATRIBUIÇÃO: Agente mudou de #{$lastAgentId} para #{$senderId}\n";
            }
            
        } else {
            $agentId = $activity['agent_id'];
            $assignedBy = $activity['assigned_by'] ?? 0;
            $removed = $activity['removed_at'];
            
            // Determinar método baseado em quem atribuiu
            $method = ($assignedBy == 0) ? 'auto/sistema' : (($assignedBy == $agentId) ? 'auto' : 'manual');
            
            // Buscar nomes
            $agentName = Database::fetch("SELECT name FROM users WHERE id = ?", [$agentId])['name'] ?? "Desconhecido";
            $assignerName = $assignedBy > 0 
                ? (Database::fetch("SELECT name FROM users WHERE id = ?", [$assignedBy])['name'] ?? "Sistema")
                : "Sistema";
            
            $icon = $removed ? '❌' : '✅';
            echo "{$counter}. {$activity['created_at']} {$icon} ATRIBUIÇÃO\n";
            echo "   Agente: #{$agentId} ({$agentName})\n";
            echo "   Por: {$assignerName} (#{$assignedBy})\n";
            echo "   Método: {$method}\n";
            
            // 🔍 ANÁLISE: Auto-atribuição suspeita
            if ($agentId == $assignedBy && $assignedBy > 0) {
                echo "   ⚠️  AUTO-ATRIBUIÇÃO DETECTADA: Agente atribuiu para si mesmo!\n";
            }
            
            if ($agentId == $lastAgentId) {
                echo "   🔴 BUG: Reatribuição para o MESMO agente (#{$agentId} → #{$agentId})\n";
            }
            
            if ($removed) {
                echo "   ❌ Removido em: {$removed}\n";
            }
            
            $lastAgentId = $agentId;
        }
        
        echo "\n";
        $counter++;
    }
}

// ============================================================================
// 5. ANÁLISE DE PROBLEMAS
// ============================================================================
echo "🔍 ANÁLISE DE PROBLEMAS\n";
echo str_repeat("─", 76) . "\n";

$problems = [];

// Verificar reatribuições para o mesmo agente
$sameAgentAssignments = Database::fetchAll(
    "SELECT ca1.*, ca2.agent_id as next_agent_id
     FROM conversation_assignments ca1
     INNER JOIN conversation_assignments ca2 
        ON ca1.conversation_id = ca2.conversation_id 
        AND ca2.assigned_at > ca1.assigned_at
     WHERE ca1.conversation_id = ?
       AND ca1.agent_id = ca2.agent_id
     ORDER BY ca1.assigned_at ASC",
    [$conversationId]
);

if (!empty($sameAgentAssignments)) {
    $problems[] = "🔴 REATRIBUIÇÕES DESNECESSÁRIAS: " . count($sameAgentAssignments) . " atribuições para o mesmo agente";
}

// Verificar se houve auto-atribuição por mensagem
$autoAssignByMessage = Database::fetchAll(
    "SELECT ca.*, m.id as message_id, m.created_at as message_time
     FROM conversation_assignments ca
     LEFT JOIN messages m 
        ON m.conversation_id = ca.conversation_id 
        AND m.sender_id = ca.agent_id
        AND m.sender_type = 'agent'
        AND m.created_at <= ca.assigned_at
        AND m.created_at >= DATE_SUB(ca.assigned_at, INTERVAL 2 SECOND)
     WHERE ca.conversation_id = ?
       AND ca.agent_id = ca.assigned_by
       AND m.id IS NOT NULL
     ORDER BY ca.assigned_at ASC",
    [$conversationId]
);

if (!empty($autoAssignByMessage)) {
    $problems[] = "🔴 AUTO-ATRIBUIÇÃO POR MENSAGEM: " . count($autoAssignByMessage) . " atribuições logo após envio de mensagem";
}

// Verificar atribuições por participante
$participantAssignments = Database::fetchAll(
    "SELECT ca.*
     FROM conversation_assignments ca
     INNER JOIN conversation_participants cp
        ON cp.conversation_id = ca.conversation_id
        AND cp.user_id = ca.agent_id
        AND cp.removed_at IS NULL
     WHERE ca.conversation_id = ?
       AND ca.agent_id = ca.assigned_by
     ORDER BY ca.assigned_at ASC",
    [$conversationId]
);

if (!empty($participantAssignments)) {
    $problems[] = "🔴 ATRIBUIÇÃO DE PARTICIPANTE: " . count($participantAssignments) . " atribuições automáticas de participantes";
}

if (empty($problems)) {
    echo "✅ Nenhum problema óbvio detectado\n";
} else {
    foreach ($problems as $problem) {
        echo "{$problem}\n";
    }
}

echo "\n";

// ============================================================================
// 6. RECOMENDAÇÕES
// ============================================================================
echo "💡 RECOMENDAÇÕES\n";
echo str_repeat("─", 76) . "\n";

if (!empty($sameAgentAssignments)) {
    echo "1. Bug de auto-atribuição (assigned_to vs agent_id) - JÁ CORRIGIDO\n";
    echo "   Este problema deve ter parado após a correção aplicada hoje.\n";
}

if (!empty($autoAssignByMessage)) {
    echo "2. Verificar se campo 'assigned_to' foi usado ao invés de 'agent_id'\n";
    echo "   Arquivo: app/Controllers/ConversationController.php (linha 1190)\n";
}

if (!empty($participantAssignments)) {
    echo "3. Participantes estão assumindo atribuição ao enviar mensagem\n";
    echo "   Verificar lógica de auto-atribuição no sendMessage\n";
}

echo "\n";

// ============================================================================
// 7. COMANDOS ÚTEIS
// ============================================================================
echo "🔧 COMANDOS SQL ÚTEIS PARA INVESTIGAÇÃO\n";
echo str_repeat("─", 76) . "\n";
echo "\n";

echo "-- Ver todas as atribuições:\n";
echo "SELECT * FROM conversation_assignments WHERE conversation_id = {$conversationId} ORDER BY assigned_at;\n";
echo "\n";

echo "-- Ver todas as mensagens:\n";
echo "SELECT * FROM messages WHERE conversation_id = {$conversationId} ORDER BY created_at;\n";
echo "\n";

echo "-- Ver logs de atividades:\n";
echo "SELECT * FROM activity_logs WHERE conversation_id = {$conversationId} ORDER BY created_at DESC;\n";
echo "\n";

echo "-- Ver participantes:\n";
echo "SELECT * FROM conversation_participants WHERE conversation_id = {$conversationId};\n";
echo "\n";

echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                          FIM DO DEBUG                                    ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
