<?php
/**
 * Script para verificar rotação de contas em uma campanha
 * 
 * Uso: php check-rotation.php [campaign_id]
 */

require_once __DIR__ . '/config/bootstrap.php';

$campaignId = isset($argv[1]) ? (int)$argv[1] : null;

if (!$campaignId) {
    echo "❌ Informe o ID da campanha!\n";
    echo "Uso: php check-rotation.php [campaign_id]\n\n";
    exit(1);
}

echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║     LOG DE ROTAÇÃO - CAMPANHA #{$campaignId}                ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

try {
    $campaign = \App\Models\Campaign::find($campaignId);
    if (!$campaign) {
        echo "❌ Campanha não encontrada!\n";
        exit(1);
    }
    
    echo "📝 Campanha: {$campaign['name']}\n";
    echo "🔄 Estratégia: {$campaign['rotation_strategy']}\n\n";
    
    // Buscar mensagens com conta usada
    $sql = "SELECT 
        cm.id as msg_id,
        c.name as contato,
        c.phone,
        ia.name as conta_usada,
        ia.phone_number as numero_conta,
        cm.status,
        cm.sent_at
    FROM campaign_messages cm
    INNER JOIN contacts c ON cm.contact_id = c.id
    LEFT JOIN integration_accounts ia ON cm.integration_account_id = ia.id
    WHERE cm.campaign_id = ?
    ORDER BY cm.id ASC";
    
    $messages = \App\Helpers\Database::fetchAll($sql, [$campaignId]);
    
    if (empty($messages)) {
        echo "⚠️ Nenhuma mensagem processada ainda.\n";
        echo "Execute: php public\\scripts\\process-campaigns.php\n\n";
        exit(0);
    }
    
    echo "───────────────────────────────────────────────────────\n";
    echo "📨 MENSAGENS ENVIADAS:\n\n";
    
    foreach ($messages as $msg) {
        $statusIcon = $msg['status'] === 'sent' ? '✅' : ($msg['status'] === 'failed' ? '❌' : '⏳');
        
        echo "#{$msg['msg_id']} {$statusIcon} {$msg['contato']} ({$msg['phone']})\n";
        echo "       → Enviada por: {$msg['conta_usada']} ({$msg['numero_conta']})\n";
        echo "       Status: {$msg['status']}\n";
        if ($msg['sent_at']) {
            echo "       Enviada: {$msg['sent_at']}\n";
        }
        echo "\n";
    }
    
    echo "───────────────────────────────────────────────────────\n\n";
    
    // Resumo por conta
    $sqlSummary = "SELECT 
        ia.id,
        ia.name as conta,
        ia.phone_number,
        COUNT(*) as total_enviadas,
        SUM(CASE WHEN cm.status = 'sent' THEN 1 ELSE 0 END) as enviadas,
        SUM(CASE WHEN cm.status = 'delivered' THEN 1 ELSE 0 END) as entregues,
        SUM(CASE WHEN cm.status = 'failed' THEN 1 ELSE 0 END) as falhas
    FROM campaign_messages cm
    INNER JOIN integration_accounts ia ON cm.integration_account_id = ia.id
    WHERE cm.campaign_id = ?
    GROUP BY ia.id
    ORDER BY ia.id ASC";
    
    $summary = \App\Helpers\Database::fetchAll($sqlSummary, [$campaignId]);
    
    if (!empty($summary)) {
        echo "📊 DISTRIBUIÇÃO POR CONTA:\n\n";
        
        foreach ($summary as $row) {
            $percent = count($messages) > 0 ? round(($row['total_enviadas'] / count($messages)) * 100, 1) : 0;
            
            echo "   {$row['conta']} ({$row['phone_number']})\n";
            echo "      Total: {$row['total_enviadas']} mensagens ({$percent}%)\n";
            echo "      Enviadas: {$row['enviadas']}\n";
            echo "      Entregues: {$row['entregues']}\n";
            echo "      Falhas: {$row['falhas']}\n";
            echo "\n";
        }
        
        // Verificar se a distribuição está balanceada
        $counts = array_column($summary, 'total_enviadas');
        $max = max($counts);
        $min = min($counts);
        $diff = $max - $min;
        
        echo "───────────────────────────────────────────────────────\n\n";
        echo "⚖️ BALANCEAMENTO:\n";
        
        if ($diff <= 1) {
            echo "   ✅ PERFEITO! Distribuição balanceada.\n";
            echo "      Diferença entre contas: {$diff} mensagem(ns)\n\n";
        } elseif ($diff <= 3) {
            echo "   ✅ BOM! Distribuição razoável.\n";
            echo "      Diferença entre contas: {$diff} mensagens\n\n";
        } else {
            echo "   ⚠️ ATENÇÃO! Distribuição desbalanceada.\n";
            echo "      Diferença entre contas: {$diff} mensagens\n";
            echo "      Verifique a estratégia de rotação.\n\n";
        }
    }
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════\n";
