<?php
/**
 * Script de Debug para Sistema de Disponibilidade
 * Mostra o estado atual e histórico de um agente
 * 
 * Uso: php public/debug-availability.php [user_id]
 * Ou via HTTP: http://localhost/debug-availability.php?user_id=1
 */

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Se executado via HTTP, definir header de texto
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

// Carregar autoloader
require_once dirname(__DIR__) . '/app/Helpers/autoload.php';

use App\Helpers\Database;
use App\Services\AvailabilityService;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║        DEBUG - SISTEMA DE DISPONIBILIDADE                          ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Obter user_id
$userId = null;
if (php_sapi_name() === 'cli') {
    $userId = isset($argv[1]) ? (int)$argv[1] : null;
} else {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
}

// Se não informou, listar agentes
if (!$userId) {
    echo "📋 AGENTES DISPONÍVEIS:\n";
    echo str_repeat("─", 70) . "\n\n";
    
    $sql = "SELECT id, name, email, role, availability_status, 
                   last_seen_at, last_activity_at, updated_at
            FROM users 
            WHERE role IN ('agent', 'admin', 'supervisor')
            AND status = 'active'
            ORDER BY availability_status DESC, name ASC";
    
    $agents = Database::fetchAll($sql);
    
    foreach ($agents as $agent) {
        $status = $agent['availability_status'] ?? 'offline';
        $statusIcon = [
            'online' => '🟢',
            'away' => '🟡',
            'busy' => '🔴',
            'offline' => '⚫'
        ][$status] ?? '⚪';
        
        echo "{$statusIcon} ID: {$agent['id']} - {$agent['name']} ({$agent['email']})\n";
        echo "   Status: {$status}\n";
        echo "   Last Seen: " . ($agent['last_seen_at'] ?? 'N/A') . "\n";
        echo "   Last Activity: " . ($agent['last_activity_at'] ?? 'N/A') . "\n";
        echo "   Updated: " . ($agent['updated_at'] ?? 'N/A') . "\n";
        echo "\n";
    }
    
    echo "\n💡 Uso: php public/debug-availability.php [user_id]\n";
    echo "   Ou: http://localhost/debug-availability.php?user_id=1\n";
    exit(0);
}

// Buscar agente específico
$sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$agent = Database::fetch($sql, [$userId]);

if (!$agent) {
    echo "❌ ERRO: Agente ID {$userId} não encontrado!\n";
    exit(1);
}

echo "👤 AGENTE: {$agent['name']} (ID: {$userId})\n";
echo str_repeat("═", 70) . "\n\n";

// Estado atual
echo "📊 ESTADO ATUAL:\n";
echo str_repeat("─", 70) . "\n";
echo "Status: {$agent['availability_status']}\n";
echo "Email: {$agent['email']}\n";
echo "Role: {$agent['role']}\n";
echo "Ativo: " . ($agent['status'] === 'active' ? 'Sim' : 'Não') . "\n";
echo "Last Seen At: " . ($agent['last_seen_at'] ?? 'N/A') . "\n";
echo "Last Activity At: " . ($agent['last_activity_at'] ?? 'N/A') . "\n";
echo "Updated At: " . ($agent['updated_at'] ?? 'N/A') . "\n";
echo "\n";

// Configurações do sistema
echo "⚙️  CONFIGURAÇÕES DO SISTEMA:\n";
echo str_repeat("─", 70) . "\n";
$settings = AvailabilityService::getSettings();
echo "Auto Online on Login: " . ($settings['auto_online_on_login'] ? 'Sim' : 'Não') . "\n";
echo "Auto Offline on Logout: " . ($settings['auto_offline_on_logout'] ? 'Sim' : 'Não') . "\n";
echo "Auto Away Enabled: " . ($settings['auto_away_enabled'] ? 'Sim' : 'Não') . "\n";
echo "Away Timeout: {$settings['away_timeout_minutes']} minutos\n";
echo "Offline Timeout: {$settings['offline_timeout_minutes']} minutos\n";
echo "Activity Tracking: " . ($settings['activity_tracking_enabled'] ? 'Sim' : 'Não') . "\n";
echo "Heartbeat Interval: {$settings['heartbeat_interval_seconds']} segundos\n";
echo "\n";

// Cálculos de tempo
$now = new DateTime();
$lastSeen = $agent['last_seen_at'] ? new DateTime($agent['last_seen_at']) : null;
$lastActivity = $agent['last_activity_at'] ? new DateTime($agent['last_activity_at']) : null;

echo "⏱️  ANÁLISE DE TEMPO:\n";
echo str_repeat("─", 70) . "\n";
echo "Data/Hora Atual: " . $now->format('Y-m-d H:i:s') . "\n\n";

if ($lastSeen) {
    $diffSeen = ($now->getTimestamp() - $lastSeen->getTimestamp());
    $minutesSeen = $diffSeen / 60;
    $secondsSeen = $diffSeen;
    
    echo "🕐 Tempo desde Last Seen (heartbeat):\n";
    echo "   - {$secondsSeen} segundos\n";
    echo "   - " . round($minutesSeen, 2) . " minutos\n";
    
    $offlineThreshold = $settings['offline_timeout_minutes'];
    $seenStatus = $minutesSeen >= $offlineThreshold ? '❌ PASSOU DO TIMEOUT' : '✅ Dentro do limite';
    echo "   - Limite: {$offlineThreshold} minutos\n";
    echo "   - Status: {$seenStatus}\n";
    
    if ($minutesSeen >= $offlineThreshold) {
        echo "   ⚠️  DEVERIA SER MARCADO COMO OFFLINE!\n";
    }
    echo "\n";
} else {
    echo "🕐 Last Seen: N/A\n\n";
}

if ($lastActivity) {
    $diffActivity = ($now->getTimestamp() - $lastActivity->getTimestamp());
    $minutesActivity = $diffActivity / 60;
    $secondsActivity = $diffActivity;
    
    echo "🕐 Tempo desde Last Activity (atividade real):\n";
    echo "   - {$secondsActivity} segundos\n";
    echo "   - " . round($minutesActivity, 2) . " minutos\n";
    
    $awayThreshold = $settings['away_timeout_minutes'];
    $activityStatus = $minutesActivity >= $awayThreshold ? '❌ PASSOU DO TIMEOUT' : '✅ Dentro do limite';
    echo "   - Limite: {$awayThreshold} minutos\n";
    echo "   - Status: {$activityStatus}\n";
    
    if ($minutesActivity >= $awayThreshold && $agent['availability_status'] === 'online') {
        echo "   ⚠️  DEVERIA SER MARCADO COMO AWAY!\n";
    }
    echo "\n";
} else {
    echo "🕐 Last Activity: N/A\n\n";
}

// Histórico recente (últimas 20 mudanças)
echo "📜 HISTÓRICO RECENTE (últimas 20 mudanças):\n";
echo str_repeat("─", 70) . "\n";

$sql = "SELECT status, started_at, ended_at, duration_seconds, 
               is_business_hours, metadata
        FROM user_availability_history
        WHERE user_id = ?
        ORDER BY started_at DESC
        LIMIT 20";

$history = Database::fetchAll($sql, [$userId]);

if (empty($history)) {
    echo "Nenhum histórico encontrado.\n\n";
} else {
    foreach ($history as $i => $record) {
        $statusIcon = [
            'online' => '🟢',
            'away' => '🟡',
            'busy' => '🔴',
            'offline' => '⚫'
        ][$record['status']] ?? '⚪';
        
        $metadata = $record['metadata'] ? json_decode($record['metadata'], true) : [];
        $reason = $metadata['reason'] ?? 'N/A';
        
        $duration = $record['duration_seconds'] ?? 0;
        $durationStr = $duration > 0 ? gmdate('H:i:s', $duration) : 'Em andamento';
        
        echo ($i + 1) . ". {$statusIcon} {$record['status']} - {$record['started_at']} → ";
        echo ($record['ended_at'] ?? 'Agora') . " ({$durationStr})\n";
        echo "   Razão: {$reason}\n";
        
        // Verificar mudanças muito rápidas (menos de 1 minuto)
        if ($duration > 0 && $duration < 60) {
            echo "   ⚠️  MUDANÇA MUITO RÁPIDA (< 1 minuto)\n";
        }
        echo "\n";
    }
}

// Estatísticas do dia
echo "📈 ESTATÍSTICAS DE HOJE:\n";
echo str_repeat("─", 70) . "\n";

$today = date('Y-m-d 00:00:00');
$stats = AvailabilityService::getTimeInStatus($userId, $today);

foreach (['online', 'away', 'busy', 'offline'] as $status) {
    $seconds = $stats[$status] ?? 0;
    $formatted = AvailabilityService::formatTime($seconds);
    echo ucfirst($status) . ": {$formatted}\n";
}
echo "\n";

// Diagnóstico
echo "🔍 DIAGNÓSTICO:\n";
echo str_repeat("─", 70) . "\n";

$issues = [];

// Verificar mudanças rápidas (menos de 2 minutos)
$sql = "SELECT COUNT(*) as count
        FROM user_availability_history
        WHERE user_id = ?
        AND duration_seconds > 0
        AND duration_seconds < 120
        AND started_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
$quickChanges = Database::fetch($sql, [$userId]);

if ($quickChanges['count'] > 3) {
    $issues[] = "❌ {$quickChanges['count']} mudanças de status muito rápidas (< 2 min) na última hora";
}

// Verificar status inconsistente
if ($lastSeen && $minutesSeen >= $settings['offline_timeout_minutes']) {
    if ($agent['availability_status'] !== 'offline') {
        $issues[] = "❌ Agente deveria estar OFFLINE (sem heartbeat há " . round($minutesSeen, 1) . " min)";
    }
}

if ($lastActivity && $minutesActivity >= $settings['away_timeout_minutes']) {
    if ($agent['availability_status'] === 'online') {
        $issues[] = "❌ Agente deveria estar AWAY (sem atividade há " . round($minutesActivity, 1) . " min)";
    }
}

// Verificar se last_activity é muito diferente de last_seen
if ($lastSeen && $lastActivity) {
    $diff = abs($lastSeen->getTimestamp() - $lastActivity->getTimestamp());
    if ($diff > 300) { // 5 minutos
        $diffMin = round($diff / 60, 1);
        echo "⚠️  Last Seen e Last Activity estão muito diferentes ({$diffMin} min)\n";
        echo "   Isso pode indicar problema na lógica de atualização\n";
    }
}

if (empty($issues)) {
    echo "✅ Nenhum problema identificado!\n";
} else {
    foreach ($issues as $issue) {
        echo $issue . "\n";
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                    FIM DO DEBUG                                     ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
