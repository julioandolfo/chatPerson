<?php
/**
 * Script para limpar logs antigos de automações
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

header('Content-Type: text/html; charset=UTF-8');

echo '<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
    h1 { color: #333; }
    .error { color: #ef4444; font-weight: bold; }
    .success { color: #22c55e; font-weight: bold; }
    .warning { color: #f59e0b; font-weight: bold; }
    .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #f2f2f2; }
    .btn { display: inline-block; padding: 10px 20px; background: #009ef7; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
    .btn-danger { background: #ef4444; }
</style>';

echo "<h1>🧹 Limpeza de Logs de Automações</h1>";

try {
    // Contar logs por status
    $stats = \App\Helpers\Database::fetchAll("
        SELECT 
            status,
            COUNT(*) as total,
            MIN(created_at) as oldest,
            MAX(created_at) as newest
        FROM automation_executions
        GROUP BY status
        ORDER BY status
    ", []);
    
    echo '<div class="card">';
    echo '<h2>📊 Estatísticas Atuais</h2>';
    echo '<table>';
    echo '<tr><th>Status</th><th>Total</th><th>Mais Antiga</th><th>Mais Recente</th></tr>';
    
    $totalFailed = 0;
    $totalRunning = 0;
    
    foreach ($stats as $stat) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($stat['status']) . '</strong></td>';
        echo '<td>' . $stat['total'] . '</td>';
        echo '<td>' . $stat['oldest'] . '</td>';
        echo '<td>' . $stat['newest'] . '</td>';
        echo '</tr>';
        
        if ($stat['status'] === 'failed') $totalFailed = $stat['total'];
        if ($stat['status'] === 'running') $totalRunning = $stat['total'];
    }
    
    echo '</table>';
    echo '</div>';
    
    // Opções de limpeza
    if ($totalFailed > 0 || $totalRunning > 0) {
        echo '<div class="card">';
        echo '<h2>🗑️ Opções de Limpeza</h2>';
        
        if ($totalFailed > 0) {
            echo '<p class="error">❌ ' . $totalFailed . ' execução(ões) com falha encontrada(s)</p>';
            if (!isset($_GET['clean_failed'])) {
                echo '<a href="?clean_failed=1" class="btn btn-danger" onclick="return confirm(\'Tem certeza que deseja remover TODAS as execuções com falha?\')">Limpar Execuções com Falha</a>';
            }
        }
        
        if ($totalRunning > 0) {
            echo '<p class="warning">⏸️ ' . $totalRunning . ' execução(ões) com status "running" (provavelmente travadas)</p>';
            if (!isset($_GET['clean_running'])) {
                echo '<a href="?clean_running=1" class="btn btn-danger" onclick="return confirm(\'Tem certeza que deseja remover TODAS as execuções travadas?\')">Limpar Execuções Travadas</a>';
            }
        }
        
        if ($totalFailed > 0 || $totalRunning > 0) {
            if (!isset($_GET['clean_all'])) {
                echo '<br><br>';
                echo '<a href="?clean_all=1" class="btn btn-danger" onclick="return confirm(\'Tem certeza que deseja limpar TODAS as execuções falhas E travadas?\')">Limpar Tudo (Falhas + Travadas)</a>';
            }
        }
        
        echo '</div>';
    } else {
        echo '<div class="card">';
        echo '<p class="success">✅ Não há logs para limpar! Todas as execuções estão OK.</p>';
        echo '</div>';
    }
    
    // Processar limpeza
    if (isset($_GET['clean_failed'])) {
        $deleted = \App\Helpers\Database::getInstance()->exec("
            DELETE FROM automation_executions
            WHERE status = 'failed'
        ");
        
        echo '<div class="card">';
        echo '<h2 class="success">✅ Limpeza Concluída!</h2>';
        echo '<p>' . $deleted . ' execução(ões) com falha removida(s).</p>';
        echo '<a href="?" class="btn">← Voltar</a>';
        echo '</div>';
    }
    
    if (isset($_GET['clean_running'])) {
        $deleted = \App\Helpers\Database::getInstance()->exec("
            DELETE FROM automation_executions
            WHERE status = 'running'
        ");
        
        echo '<div class="card">';
        echo '<h2 class="success">✅ Limpeza Concluída!</h2>';
        echo '<p>' . $deleted . ' execução(ões) travada(s) removida(s).</p>';
        echo '<a href="?" class="btn">← Voltar</a>';
        echo '</div>';
    }
    
    if (isset($_GET['clean_all'])) {
        $deleted = \App\Helpers\Database::getInstance()->exec("
            DELETE FROM automation_executions
            WHERE status IN ('failed', 'running')
        ");
        
        echo '<div class="card">';
        echo '<h2 class="success">✅ Limpeza Concluída!</h2>';
        echo '<p>' . $deleted . ' execução(ões) removida(s) (falhas + travadas).</p>';
        echo '<a href="?" class="btn">← Voltar</a>';
        echo '</div>';
    }
    
    // Listar últimas execuções
    if (!isset($_GET['clean_failed']) && !isset($_GET['clean_running']) && !isset($_GET['clean_all'])) {
        echo '<div class="card">';
        echo '<h2>📋 Últimas 10 Execuções</h2>';
        
        $recent = \App\Helpers\Database::fetchAll("
            SELECT ae.*, a.name as automation_name
            FROM automation_executions ae
            LEFT JOIN automations a ON ae.automation_id = a.id
            ORDER BY ae.id DESC
            LIMIT 10
        ", []);
        
        echo '<table>';
        echo '<tr><th>ID</th><th>Automação</th><th>Conversa</th><th>Status</th><th>Erro</th><th>Criado</th></tr>';
        
        foreach ($recent as $exec) {
            echo '<tr>';
            echo '<td>' . $exec['id'] . '</td>';
            echo '<td>' . htmlspecialchars($exec['automation_name']) . '</td>';
            echo '<td>' . $exec['conversation_id'] . '</td>';
            
            $statusClass = 'success';
            if ($exec['status'] === 'failed') $statusClass = 'error';
            if ($exec['status'] === 'running') $statusClass = 'warning';
            
            echo '<td class="' . $statusClass . '">' . $exec['status'] . '</td>';
            echo '<td>' . htmlspecialchars($exec['error_message'] ?: '-') . '</td>';
            echo '<td>' . $exec['created_at'] . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }
    
} catch (\Exception $e) {
    echo '<div class="card">';
    echo '<p class="error">❌ ERRO: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}

echo '<br><br>';
echo '<a href="test-trigger-automation.php" class="btn">🧪 Testar Automações</a>';
echo '<a href="test-automation-integration.php" class="btn">📊 Teste de Integração</a>';

