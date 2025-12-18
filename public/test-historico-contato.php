<?php
/**
 * Script de teste para verificar histórico de contatos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

// Listar alguns contatos com conversas
echo "=== TESTE DE HISTÓRICO DE CONTATOS ===\n\n";

$db = \App\Helpers\Database::getInstance();

// Buscar contatos que têm conversas
$sql = "SELECT 
            co.id,
            co.name,
            co.phone,
            COUNT(DISTINCT c.id) as total_conversations,
            COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_conversations
        FROM contacts co
        LEFT JOIN conversations c ON c.contact_id = co.id
        GROUP BY co.id
        HAVING total_conversations > 0
        ORDER BY closed_conversations DESC
        LIMIT 5";

$contacts = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "📋 Contatos com conversas:\n\n";

foreach ($contacts as $contact) {
    echo "────────────────────────────────────────────────────\n";
    echo "👤 Contato: {$contact['name']} (ID: {$contact['id']})\n";
    echo "   📞 Telefone: {$contact['phone']}\n";
    echo "   💬 Total conversas: {$contact['total_conversations']}\n";
    echo "   ✅ Conversas fechadas: {$contact['closed_conversations']}\n\n";
    
    // Buscar estatísticas detalhadas
    $statsQuery = "SELECT 
                    COUNT(*) AS total_conversations,
                    AVG(TIMESTAMPDIFF(SECOND, c.created_at, COALESCE(c.resolved_at, c.updated_at))) AS avg_duration_seconds,
                    AVG(TIMESTAMPDIFF(MINUTE, c.created_at, COALESCE(c.resolved_at, c.updated_at))) AS avg_duration_minutes,
                    AVG(TIMESTAMPDIFF(HOUR, c.created_at, COALESCE(c.resolved_at, c.updated_at))) AS avg_duration_hours
                FROM conversations c
                WHERE c.contact_id = ? 
                AND c.status IN ('closed', 'resolved')
                AND (c.resolved_at IS NOT NULL OR c.updated_at IS NOT NULL)";
    
    $stmt = $db->prepare($statsQuery);
    $stmt->execute([$contact['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   📊 Estatísticas de conversas fechadas/resolvidas:\n";
    echo "      Total: " . ($stats['total_conversations'] ?? 0) . "\n";
    
    if ($stats['avg_duration_seconds'] !== null) {
        $avgSec = round($stats['avg_duration_seconds']);
        $avgMin = round($stats['avg_duration_minutes'], 1);
        $avgHours = round($stats['avg_duration_hours'], 2);
        
        echo "      Tempo médio: {$avgSec}s ({$avgMin} min / {$avgHours}h)\n";
        
        // Formatar de forma legível
        $h = floor($avgSec / 3600);
        $m = floor(($avgSec % 3600) / 60);
        $s = $avgSec % 60;
        
        $formatted = '';
        if ($h > 0) $formatted .= "{$h}h ";
        if ($m > 0) $formatted .= "{$m}m ";
        if ($s > 0 || empty($formatted)) $formatted .= "{$s}s";
        
        echo "      Formatado: {$formatted}\n";
    } else {
        echo "      Tempo médio: Sem dados (não há conversas resolvidas)\n";
    }
    
    // Listar as conversas fechadas desse contato
    $convsQuery = "SELECT 
                    id,
                    status,
                    created_at,
                    updated_at,
                    resolved_at,
                    TIMESTAMPDIFF(SECOND, created_at, COALESCE(resolved_at, updated_at)) as duration_seconds
                FROM conversations
                WHERE contact_id = ?
                AND status IN ('closed', 'resolved')
                ORDER BY updated_at DESC
                LIMIT 3";
    
    $stmt = $db->prepare($convsQuery);
    $stmt->execute([$contact['id']]);
    $convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($convs)) {
        echo "\n   📝 Últimas conversas fechadas:\n";
        foreach ($convs as $conv) {
            $duration = $conv['duration_seconds'];
            $h = floor($duration / 3600);
            $m = floor(($duration % 3600) / 60);
            $formatted = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
            
            echo "      - Conv #{$conv['id']}: {$conv['status']} | Duração: {$formatted} ({$duration}s)\n";
            echo "        Criada: {$conv['created_at']}\n";
            echo "        Resolvida: " . ($conv['resolved_at'] ?? $conv['updated_at']) . "\n";
        }
    }
    
    echo "\n";
}

echo "\n────────────────────────────────────────────────────\n";
echo "\n✅ Teste concluído!\n\n";
echo "💡 Para testar via API:\n";
if (!empty($contacts)) {
    $firstContact = $contacts[0];
    echo "   curl -X GET 'http://localhost/contacts/{$firstContact['id']}/history' \\\n";
    echo "        -H 'X-Requested-With: XMLHttpRequest' \\\n";
    echo "        -H 'Accept: application/json'\n\n";
}

