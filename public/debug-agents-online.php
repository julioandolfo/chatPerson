<?php
/**
 * Script de debug para verificar status de agentes online
 * Ajuda a diagnosticar problemas com o card "Agentes Online" no dashboard
 * 
 * Uso: Acesse via navegador http://localhost/debug-agents-online.php
 */

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Se executado via HTTP, definir header de texto
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug - Agentes Online</title>";
echo "<style>
body { font-family: 'Courier New', monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
h1, h2 { color: #4ec9b0; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; background: #252526; }
th, td { padding: 12px; text-align: left; border: 1px solid #3e3e42; }
th { background: #2d2d30; color: #4ec9b0; font-weight: bold; }
tr:hover { background: #2a2d2e; }
.online { color: #4ec9b0; font-weight: bold; }
.offline { color: #f48771; }
.away { color: #dcdcaa; }
.busy { color: #ce9178; }
.warning { color: #f48771; padding: 10px; background: #3a1a1a; border-left: 3px solid #f48771; margin: 10px 0; }
.info { color: #4ec9b0; padding: 10px; background: #1a3a3a; border-left: 3px solid #4ec9b0; margin: 10px 0; }
.success { color: #4ec9b0; padding: 10px; background: #1a3a1a; border-left: 3px solid #4ec9b0; margin: 10px 0; }
.code { background: #1e1e1e; padding: 10px; border-left: 3px solid #569cd6; margin: 10px 0; overflow-x: auto; }
</style></head><body>";

echo "<h1>🔍 Debug - Agentes Online</h1>";
echo "<p><small>Executado em: " . date('d/m/Y H:i:s') . "</small></p>";

// Carregar autoload
$autoloadPath = dirname(__DIR__) . '/app/Helpers/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "<div class='warning'>❌ Erro: app/Helpers/autoload.php não encontrado!</div>";
    exit(1);
}

require_once $autoloadPath;

use App\Services\AvailabilityService;
use App\Helpers\Database;

try {
    // Obter configurações
    $settings = AvailabilityService::getSettings();
    $offlineTimeoutMinutes = $settings['offline_timeout_minutes'];
    $awayTimeoutMinutes = $settings['away_timeout_minutes'];
    $heartbeatIntervalSeconds = $settings['heartbeat_interval_seconds'];
    
    echo "<h2>⚙️ Configurações de Disponibilidade</h2>";
    echo "<table>";
    echo "<tr><th>Configuração</th><th>Valor</th></tr>";
    echo "<tr><td>Timeout para Offline (sem heartbeat)</td><td>{$offlineTimeoutMinutes} minutos</td></tr>";
    echo "<tr><td>Timeout para Away (sem atividade)</td><td>{$awayTimeoutMinutes} minutos</td></tr>";
    echo "<tr><td>Intervalo de Heartbeat</td><td>{$heartbeatIntervalSeconds} segundos</td></tr>";
    echo "<tr><td>Auto-online no login</td><td>" . ($settings['auto_online_on_login'] ? 'Sim' : 'Não') . "</td></tr>";
    echo "<tr><td>Auto-offline no logout</td><td>" . ($settings['auto_offline_on_logout'] ? 'Sim' : 'Não') . "</td></tr>";
    echo "<tr><td>Auto-away habilitado</td><td>" . ($settings['auto_away_enabled'] ? 'Sim' : 'Não') . "</td></tr>";
    echo "</table>";
    
    // Buscar todos os agentes
    $sql = "SELECT 
                id, 
                name, 
                email,
                role,
                status,
                availability_status, 
                last_seen_at, 
                last_activity_at,
                updated_at,
                TIMESTAMPDIFF(MINUTE, last_seen_at, NOW()) as minutes_since_heartbeat,
                TIMESTAMPDIFF(MINUTE, last_activity_at, NOW()) as minutes_since_activity
            FROM users 
            WHERE role IN ('agent', 'admin', 'supervisor')
            ORDER BY 
                CASE availability_status
                    WHEN 'online' THEN 1
                    WHEN 'busy' THEN 2
                    WHEN 'away' THEN 3
                    WHEN 'offline' THEN 4
                    ELSE 5
                END,
                name ASC";
    
    $agents = Database::fetchAll($sql);
    
    echo "<h2>👥 Status dos Agentes</h2>";
    
    // Contar por status
    $countByStatus = [
        'online' => 0,
        'offline' => 0,
        'away' => 0,
        'busy' => 0,
        'online_real' => 0 // Online com heartbeat ativo
    ];
    
    $now = new DateTime();
    
    foreach ($agents as $agent) {
        $status = $agent['availability_status'] ?? 'offline';
        $countByStatus[$status]++;
        
        // Verificar se realmente está online (com heartbeat recente)
        if ($status === 'online' && $agent['last_seen_at']) {
            $minutesSinceHeartbeat = $agent['minutes_since_heartbeat'] ?? 999;
            if ($minutesSinceHeartbeat < $offlineTimeoutMinutes) {
                $countByStatus['online_real']++;
            }
        }
    }
    
    echo "<div class='info'>";
    echo "<strong>📊 Resumo:</strong><br>";
    echo "Total de agentes: " . count($agents) . "<br>";
    echo "Online (banco de dados): <span class='online'>{$countByStatus['online']}</span><br>";
    echo "Online (heartbeat ativo): <span class='online'>{$countByStatus['online_real']}</span><br>";
    echo "Away: <span class='away'>{$countByStatus['away']}</span><br>";
    echo "Busy: <span class='busy'>{$countByStatus['busy']}</span><br>";
    echo "Offline: <span class='offline'>{$countByStatus['offline']}</span><br>";
    echo "</div>";
    
    if ($countByStatus['online'] != $countByStatus['online_real']) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Inconsistência Detectada!</strong><br>";
        echo "Há " . ($countByStatus['online'] - $countByStatus['online_real']) . " agente(s) marcado(s) como 'online' no banco, ";
        echo "mas sem heartbeat recente (> {$offlineTimeoutMinutes} minutos).<br>";
        echo "<strong>Solução:</strong> Execute o cron check-availability.php para atualizar o status.";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "✅ Status dos agentes está consistente com o heartbeat!";
        echo "</div>";
    }
    
    echo "<h2>📋 Detalhes dos Agentes</h2>";
    echo "<table>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Nome</th>";
    echo "<th>Role</th>";
    echo "<th>Status Sistema</th>";
    echo "<th>Status Disponibilidade</th>";
    echo "<th>Último Heartbeat</th>";
    echo "<th>Última Atividade</th>";
    echo "<th>Tempo sem Heartbeat</th>";
    echo "<th>Tempo sem Atividade</th>";
    echo "<th>Status Real</th>";
    echo "</tr>";
    
    foreach ($agents as $agent) {
        $status = $agent['availability_status'] ?? 'offline';
        $statusClass = strtolower($status);
        $lastSeen = $agent['last_seen_at'] ?? 'Nunca';
        $lastActivity = $agent['last_activity_at'] ?? 'Nunca';
        $minutesSinceHeartbeat = $agent['minutes_since_heartbeat'] ?? null;
        $minutesSinceActivity = $agent['minutes_since_activity'] ?? null;
        
        // Determinar status real
        $realStatus = 'offline';
        $realStatusText = 'Offline';
        
        if ($status === 'online' && $minutesSinceHeartbeat !== null) {
            if ($minutesSinceHeartbeat < $offlineTimeoutMinutes) {
                $realStatus = 'online';
                $realStatusText = '✅ Online (heartbeat ativo)';
            } else {
                $realStatus = 'offline';
                $realStatusText = "❌ Deveria estar Offline (sem heartbeat há {$minutesSinceHeartbeat} min)";
            }
        } elseif ($status === 'away' && $minutesSinceHeartbeat !== null) {
            if ($minutesSinceHeartbeat < $offlineTimeoutMinutes) {
                $realStatus = 'away';
                $realStatusText = '⏸️ Away (heartbeat ativo)';
            } else {
                $realStatus = 'offline';
                $realStatusText = "❌ Deveria estar Offline (sem heartbeat há {$minutesSinceHeartbeat} min)";
            }
        } elseif ($status === 'busy' && $minutesSinceHeartbeat !== null) {
            if ($minutesSinceHeartbeat < $offlineTimeoutMinutes) {
                $realStatus = 'busy';
                $realStatusText = '🔴 Busy (heartbeat ativo)';
            } else {
                $realStatus = 'offline';
                $realStatusText = "❌ Deveria estar Offline (sem heartbeat há {$minutesSinceHeartbeat} min)";
            }
        } else {
            $realStatusText = '⚪ Offline';
        }
        
        echo "<tr>";
        echo "<td>{$agent['id']}</td>";
        echo "<td>{$agent['name']}</td>";
        echo "<td>{$agent['role']}</td>";
        echo "<td>{$agent['status']}</td>";
        echo "<td class='{$statusClass}'>" . ucfirst($status) . "</td>";
        echo "<td>{$lastSeen}</td>";
        echo "<td>{$lastActivity}</td>";
        echo "<td>" . ($minutesSinceHeartbeat !== null ? "{$minutesSinceHeartbeat} min" : 'N/A') . "</td>";
        echo "<td>" . ($minutesSinceActivity !== null ? "{$minutesSinceActivity} min" : 'N/A') . "</td>";
        echo "<td class='{$realStatus}'>{$realStatusText}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Informações sobre a correção
    echo "<h2>🔧 Sobre a Correção</h2>";
    echo "<div class='info'>";
    echo "<strong>O que foi corrigido:</strong><br>";
    echo "A função <code>getOnlineAgents()</code> no DashboardService foi atualizada para verificar não apenas o campo ";
    echo "<code>availability_status = 'online'</code>, mas também se o agente teve heartbeat recente (<code>last_seen_at</code>).<br><br>";
    echo "<strong>Como funciona agora:</strong><br>";
    echo "1. Verifica se <code>availability_status = 'online'</code><br>";
    echo "2. Verifica se <code>last_seen_at</code> foi atualizado nos últimos {$offlineTimeoutMinutes} minutos<br>";
    echo "3. Somente conta como online se ambas condições forem verdadeiras<br><br>";
    echo "<strong>Benefícios:</strong><br>";
    echo "- O card \"Agentes Online\" agora mostra apenas agentes realmente conectados<br>";
    echo "- Não depende mais exclusivamente do cron para atualizar a contagem<br>";
    echo "- Reflete o estado em tempo real dos agentes<br>";
    echo "</div>";
    
    echo "<h2>📝 Recomendações</h2>";
    echo "<div class='info'>";
    echo "<strong>1. Configure o Cron:</strong><br>";
    echo "Execute periodicamente (a cada 5 minutos) o script:<br>";
    echo "<div class='code'>php " . dirname(__DIR__) . "/public/check-availability.php</div>";
    echo "<strong>2. Verifique o Heartbeat:</strong><br>";
    echo "Certifique-se de que o JavaScript está carregando corretamente:<br>";
    echo "<div class='code'>public/assets/js/activity-tracker.js</div>";
    echo "<strong>3. Monitore o WebSocket:</strong><br>";
    echo "Se usar WebSocket, verifique se o servidor está rodando corretamente.<br>";
    echo "</div>";
    
} catch (\Exception $e) {
    echo "<div class='warning'>";
    echo "<strong>❌ Erro ao executar debug:</strong><br>";
    echo htmlspecialchars($e->getMessage()) . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
