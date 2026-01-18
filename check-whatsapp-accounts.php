<?php
/**
 * Script para verificar contas WhatsApp disponíveis
 */

require_once __DIR__ . '/config/bootstrap.php';

echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║     CONTAS WHATSAPP DISPONÍVEIS NO SISTEMA           ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

try {
    // Buscar todas as contas WhatsApp
    $sql = "SELECT id, name, phone_number, status, provider, created_at 
            FROM integration_accounts 
            WHERE channel = 'whatsapp'
            ORDER BY status DESC, id ASC";
    
    $accounts = \App\Helpers\Database::fetchAll($sql, []);
    
    if (empty($accounts)) {
        echo "❌ NENHUMA CONTA WHATSAPP ENCONTRADA!\n\n";
        echo "Para usar campanhas, você precisa:\n";
        echo "1. Ter pelo menos 1 conta WhatsApp conectada\n";
        echo "2. A conta deve estar com status 'active'\n\n";
        echo "Configure uma conta em: /integrations\n";
        exit(1);
    }
    
    $totalActive = 0;
    $totalInactive = 0;
    
    foreach ($accounts as $account) {
        $statusIcon = $account['status'] === 'active' ? '✅' : '⚠️';
        $statusLabel = $account['status'] === 'active' ? 'ATIVA' : strtoupper($account['status']);
        
        echo "{$statusIcon} ID: {$account['id']}\n";
        echo "   Nome: {$account['name']}\n";
        echo "   Número: {$account['phone_number']}\n";
        echo "   Status: {$statusLabel}\n";
        echo "   Provider: {$account['provider']}\n";
        echo "   Criada em: {$account['created_at']}\n";
        echo "\n";
        
        if ($account['status'] === 'active') {
            $totalActive++;
        } else {
            $totalInactive++;
        }
    }
    
    echo "───────────────────────────────────────────────────────\n";
    echo "📊 RESUMO:\n";
    echo "   Total de contas: " . count($accounts) . "\n";
    echo "   ✅ Ativas: {$totalActive}\n";
    echo "   ⚠️ Inativas: {$totalInactive}\n\n";
    
    if ($totalActive === 0) {
        echo "⚠️ ATENÇÃO: Nenhuma conta ATIVA encontrada!\n";
        echo "   Ative pelo menos 1 conta para usar campanhas.\n\n";
    } elseif ($totalActive === 1) {
        echo "✅ Você tem 1 conta ativa.\n";
        echo "💡 Dica: Adicione mais contas para usar rotação!\n\n";
    } else {
        echo "🎉 PERFEITO! Você tem {$totalActive} contas ativas.\n";
        echo "   Rotação funcionará perfeitamente!\n\n";
        
        echo "📝 IDs para usar em campanhas:\n";
        echo "   'integration_account_ids' => [";
        $activeIds = array_column(array_filter($accounts, fn($a) => $a['status'] === 'active'), 'id');
        echo implode(', ', $activeIds);
        echo "]\n\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════\n";
