<?php
/**
 * Script CLI para verificar e atualizar disponibilidade dos agentes
 * Deve ser executado periodicamente via cron (ex: a cada 5 minutos)
 * 
 * Cron exemplo (Linux):
 * */5 * * * * php /var/www/html/public/check-availability.php >> /var/log/availability-cron.log 2>&1
 * 
 * Cron exemplo (Windows Task Scheduler):
 * php C:\laragon\www\chat\public\check-availability.php
 */

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Services\AvailabilityService;
use App\Helpers\Database;

echo "=== Verificação de Disponibilidade dos Agentes ===\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Obter configurações
    $settings = AvailabilityService::getSettings();
    $offlineTimeoutMinutes = $settings['offline_timeout_minutes'];
    $awayTimeoutMinutes = $settings['away_timeout_minutes'];
    
    echo "Configurações:\n";
    echo "- Timeout para Away: {$awayTimeoutMinutes} minutos\n";
    echo "- Timeout para Offline: {$offlineTimeoutMinutes} minutos\n\n";
    
    // Buscar agentes que estão online ou away
    $sql = "SELECT id, name, availability_status, last_seen_at, last_activity_at, updated_at
            FROM users 
            WHERE role IN ('agent', 'admin', 'supervisor')
            AND status = 'active'
            AND availability_status IN ('online', 'away', 'busy')
            ORDER BY name ASC";
    
    $agents = Database::fetchAll($sql);
    
    echo "Agentes a verificar: " . count($agents) . "\n\n";
    
    $updated = 0;
    $now = new DateTime();
    
    foreach ($agents as $agent) {
        $agentId = $agent['id'];
        $agentName = $agent['name'];
        $currentStatus = $agent['availability_status'];
        $lastSeen = $agent['last_seen_at'];
        $lastActivity = $agent['last_activity_at'];
        
        echo "Verificando: {$agentName} (Status: {$currentStatus})\n";
        
        // Verificar last_seen_at para marcar como offline
        if ($lastSeen) {
            $lastSeenDt = new DateTime($lastSeen);
            $minutesSinceLastSeen = ($now->getTimestamp() - $lastSeenDt->getTimestamp()) / 60;
            
            echo "  - Último visto: {$lastSeen} ({$minutesSinceLastSeen} minutos atrás)\n";
            
            // Se passou do timeout de offline
            if ($minutesSinceLastSeen >= $offlineTimeoutMinutes) {
                echo "  ⚠️  AÇÃO: Marcar como OFFLINE (sem heartbeat há {$minutesSinceLastSeen} minutos)\n";
                AvailabilityService::updateAvailabilityStatus($agentId, 'offline', 'heartbeat_timeout_cron');
                $updated++;
                continue;
            }
        } else {
            echo "  - Sem registro de last_seen_at\n";
        }
        
        // Verificar last_activity_at para marcar como away (apenas se estiver online)
        if ($currentStatus === 'online' && $lastActivity && $settings['auto_away_enabled']) {
            $lastActivityDt = new DateTime($lastActivity);
            $minutesSinceActivity = ($now->getTimestamp() - $lastActivityDt->getTimestamp()) / 60;
            
            echo "  - Última atividade: {$lastActivity} ({$minutesSinceActivity} minutos atrás)\n";
            
            // Se passou do timeout de away
            if ($minutesSinceActivity >= $awayTimeoutMinutes) {
                echo "  ⚠️  AÇÃO: Marcar como AWAY (sem atividade há {$minutesSinceActivity} minutos)\n";
                AvailabilityService::updateAvailabilityStatus($agentId, 'away', 'inactivity_timeout_cron');
                $updated++;
                continue;
            }
        }
        
        echo "  ✓ Status OK\n";
    }
    
    echo "\n=== Resumo ===\n";
    echo "Total verificado: " . count($agents) . "\n";
    echo "Total atualizado: {$updated}\n";
    echo "Concluído em: " . date('Y-m-d H:i:s') . "\n";
    
} catch (\Exception $e) {
    echo "\n=== ERRO ===\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

