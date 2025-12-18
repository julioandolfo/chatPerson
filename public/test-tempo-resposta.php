<?php
/**
 * Script de teste para validar cálculo de tempo médio de resposta
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

echo "=== TESTE DE TEMPO MÉDIO DE RESPOSTA ===\n\n";

$db = \App\Helpers\Database::getInstance();

// Buscar conversas com mensagens de ambos os lados
$sql = "SELECT 
            c.id,
            c.contact_id,
            co.name as contact_name,
            c.agent_id,
            u.name as agent_name,
            c.created_at,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'contact') as contact_messages,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'agent') as agent_messages
        FROM conversations c
        LEFT JOIN contacts co ON co.id = c.contact_id
        LEFT JOIN users u ON u.id = c.agent_id
        WHERE EXISTS (
            SELECT 1 FROM messages m1 
            WHERE m1.conversation_id = c.id 
            AND m1.sender_type = 'contact'
        )
        AND EXISTS (
            SELECT 1 FROM messages m2 
            WHERE m2.conversation_id = c.id 
            AND m2.sender_type = 'agent'
        )
        ORDER BY c.updated_at DESC
        LIMIT 5";

$conversations = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "📋 Conversas com troca de mensagens:\n\n";

foreach ($conversations as $conv) {
    echo "────────────────────────────────────────────────────\n";
    echo "💬 Conversa #{$conv['id']}\n";
    echo "   👤 Contato: {$conv['contact_name']} (ID: {$conv['contact_id']})\n";
    echo "   👨‍💼 Agente: " . ($conv['agent_name'] ?? 'Não atribuído') . "\n";
    echo "   📨 Mensagens do cliente: {$conv['contact_messages']}\n";
    echo "   📤 Mensagens do agente: {$conv['agent_messages']}\n";
    echo "   🕐 Criada em: {$conv['created_at']}\n\n";
    
    // Buscar pares de mensagens (cliente → agente)
    $sqlPairs = "SELECT 
                    m1.id as contact_msg_id,
                    m1.created_at as contact_msg_time,
                    m1.content as contact_msg_content,
                    m2.id as agent_msg_id,
                    m2.created_at as agent_msg_time,
                    m2.content as agent_msg_content,
                    TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds,
                    TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at) as response_time_minutes
                FROM messages m1
                LEFT JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                    AND m2.sender_type = 'agent'
                    AND m2.created_at > m1.created_at
                    AND m2.created_at = (
                        SELECT MIN(m3.created_at)
                        FROM messages m3
                        WHERE m3.conversation_id = m1.conversation_id
                        AND m3.sender_type = 'agent'
                        AND m3.created_at > m1.created_at
                    )
                WHERE m1.conversation_id = ?
                AND m1.sender_type = 'contact'
                ORDER BY m1.created_at ASC";
    
    $stmt = $db->prepare($sqlPairs);
    $stmt->execute([$conv['id']]);
    $pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pairs)) {
        echo "   📊 Tempos de resposta:\n";
        $totalResponseTime = 0;
        $countResponses = 0;
        
        foreach ($pairs as $pair) {
            if ($pair['agent_msg_id']) {
                $seconds = $pair['response_time_seconds'];
                $minutes = $pair['response_time_minutes'];
                
                // Formatar tempo
                $h = floor($seconds / 3600);
                $m = floor(($seconds % 3600) / 60);
                $s = $seconds % 60;
                $formatted = '';
                if ($h > 0) $formatted .= "{$h}h ";
                if ($m > 0) $formatted .= "{$m}m ";
                $formatted .= "{$s}s";
                
                echo "      ⏱️  {$formatted} ({$minutes} min)\n";
                echo "         Cliente: " . substr($pair['contact_msg_content'], 0, 50) . "...\n";
                echo "         Agente: " . substr($pair['agent_msg_content'], 0, 50) . "...\n\n";
                
                $totalResponseTime += $minutes;
                $countResponses++;
            } else {
                echo "      ⏱️  Sem resposta ainda\n";
                echo "         Cliente: " . substr($pair['contact_msg_content'], 0, 50) . "...\n\n";
            }
        }
        
        if ($countResponses > 0) {
            $avgResponseTime = round($totalResponseTime / $countResponses, 1);
            echo "   📈 Tempo médio de resposta: {$avgResponseTime} minutos\n";
        }
    } else {
        echo "   ℹ️  Nenhum par de mensagens encontrado\n";
    }
    
    echo "\n";
}

echo "\n────────────────────────────────────────────────────\n";
echo "\n🧪 Testando query do sistema:\n\n";

// Testar a query que usamos no sistema
$sqlTest = "SELECT 
                c.id,
                AVG(response_times.response_time_minutes) as avg_response_time_minutes
            FROM conversations c
            LEFT JOIN (
                SELECT 
                    m1.conversation_id,
                    AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as response_time_minutes
                FROM messages m1
                INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                    AND m2.sender_type = 'agent'
                    AND m2.created_at > m1.created_at
                    AND m2.created_at = (
                        SELECT MIN(m3.created_at)
                        FROM messages m3
                        WHERE m3.conversation_id = m1.conversation_id
                        AND m3.sender_type = 'agent'
                        AND m3.created_at > m1.created_at
                    )
                WHERE m1.sender_type = 'contact'
                GROUP BY m1.conversation_id
            ) response_times ON response_times.conversation_id = c.id
            WHERE c.id IN (" . implode(',', array_column($conversations, 'id')) . ")
            GROUP BY c.id";

$results = $db->query($sqlTest)->fetchAll(PDO::FETCH_ASSOC);

echo "Resultados da query do sistema:\n";
foreach ($results as $result) {
    $avgMin = $result['avg_response_time_minutes'] !== null 
        ? round($result['avg_response_time_minutes'], 1) 
        : 'null';
    echo "   Conversa #{$result['id']}: {$avgMin} minutos\n";
}

echo "\n✅ Teste concluído!\n\n";

