<?php
/**
 * Script para verificar estatísticas de uma campanha
 * 
 * Uso: php check-stats.php [campaign_id]
 */

require_once __DIR__ . '/config/bootstrap.php';

use App\Services\CampaignService;
use App\Models\Campaign;

$campaignId = isset($argv[1]) ? (int)$argv[1] : null;

if (!$campaignId) {
    echo "❌ Informe o ID da campanha!\n";
    echo "Uso: php check-stats.php [campaign_id]\n\n";
    
    // Listar campanhas disponíveis
    echo "Campanhas disponíveis:\n";
    $campaigns = Campaign::all();
    foreach ($campaigns as $camp) {
        echo "  - ID {$camp['id']}: {$camp['name']} ({$camp['status']})\n";
    }
    exit(1);
}

echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║         ESTATÍSTICAS DA CAMPANHA #{$campaignId}              ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

try {
    $campaign = Campaign::find($campaignId);
    if (!$campaign) {
        echo "❌ Campanha não encontrada!\n";
        exit(1);
    }
    
    echo "📝 INFORMAÇÕES:\n";
    echo "   Nome: {$campaign['name']}\n";
    echo "   Status: " . strtoupper($campaign['status']) . "\n";
    echo "   Canal: {$campaign['channel']}\n";
    echo "   Estratégia: {$campaign['rotation_strategy']}\n";
    echo "   Criada em: {$campaign['created_at']}\n";
    
    if ($campaign['started_at']) {
        echo "   Iniciada em: {$campaign['started_at']}\n";
    }
    if ($campaign['completed_at']) {
        echo "   Concluída em: {$campaign['completed_at']}\n";
    }
    
    echo "\n───────────────────────────────────────────────────────\n\n";
    
    $stats = CampaignService::getStats($campaignId);
    
    echo "📊 ESTATÍSTICAS:\n\n";
    
    echo "   📈 Contatos:\n";
    echo "      Total: {$stats['total_contacts']}\n\n";
    
    echo "   📤 Envios:\n";
    echo "      Enviadas: {$stats['total_sent']}\n";
    echo "      Entregues: {$stats['total_delivered']} ({$stats['delivery_rate']}%)\n";
    echo "      Lidas: {$stats['total_read']} ({$stats['read_rate']}%)\n";
    echo "      Respondidas: {$stats['total_replied']} ({$stats['reply_rate']}%)\n\n";
    
    echo "   ❌ Problemas:\n";
    echo "      Falhas: {$stats['total_failed']} ({$stats['failure_rate']}%)\n";
    echo "      Puladas: {$stats['total_skipped']}\n\n";
    
    echo "   ⏱️ Progresso:\n";
    echo "      ";
    $progress = (int)$stats['progress'];
    $bars = (int)($progress / 5);
    echo str_repeat('█', $bars) . str_repeat('░', 20 - $bars);
    echo " {$stats['progress']}%\n\n";
    
    // Ver log de rotação
    $sqlRotation = "SELECT 
        ia.name as conta,
        ia.phone_number,
        COUNT(*) as total_msgs
    FROM campaign_messages cm
    INNER JOIN integration_accounts ia ON cm.integration_account_id = ia.id
    WHERE cm.campaign_id = ? AND cm.status IN ('sent', 'delivered', 'read', 'replied')
    GROUP BY ia.id
    ORDER BY total_msgs DESC";
    
    $rotation = \App\Helpers\Database::fetchAll($sqlRotation, [$campaignId]);
    
    if (!empty($rotation)) {
        echo "───────────────────────────────────────────────────────\n\n";
        echo "🔄 ROTAÇÃO DE CONTAS:\n\n";
        foreach ($rotation as $row) {
            echo "   {$row['conta']} ({$row['phone_number']}): {$row['total_msgs']} mensagens\n";
        }
        echo "\n";
    }
    
    echo "═══════════════════════════════════════════════════════\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
