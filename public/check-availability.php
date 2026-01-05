<?php
/**
 * Script CLI para verificar e atualizar disponibilidade dos agentes
 * Deve ser executado periodicamente via cron (ex: a cada 5 minutos)
 * 
 * Cron exemplo (Linux):
 * A cada 5 minutos: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /var/www/html/public/check-availability.php >> /var/log/availability-cron.log 2>&1
 * 
 * Cron exemplo (Windows Task Scheduler):
 * php C:\laragon\www\chat\public\check-availability.php
 */

// Habilitar exibição de erros (máximo detalhamento)
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Se executado via HTTP, definir header de texto
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

// Capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n\n=== ERRO FATAL ===\n";
        echo "Tipo: " . $error['type'] . "\n";
        echo "Mensagem: " . $error['message'] . "\n";
        echo "Arquivo: " . $error['file'] . "\n";
        echo "Linha: " . $error['line'] . "\n";
        exit(1);
    }
});

// Handler de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "\n=== ERRO PHP ===\n";
    echo "Nível: " . $errno . "\n";
    echo "Mensagem: " . $errstr . "\n";
    echo "Arquivo: " . $errfile . "\n";
    echo "Linha: " . $errline . "\n\n";
    return false; // Continuar com o handler padrão
});

echo "Iniciando script...\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Diretório: " . __DIR__ . "\n";
echo "Diretório raiz: " . dirname(__DIR__) . "\n\n";

// Tentar carregar vendor/autoload.php (opcional - apenas se existir)
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "Verificando: {$vendorPath}\n";
    try {
        require_once $vendorPath;
        echo "✓ vendor/autoload.php carregado (opcional)\n";
    } catch (\Exception $e) {
        echo "⚠ Aviso: Não foi possível carregar vendor/autoload.php: " . $e->getMessage() . "\n";
        echo "Continuando apenas com app/Helpers/autoload.php...\n";
    }
} else {
    echo "ℹ vendor/autoload.php não encontrado (opcional - continuando...)\n";
}

// Verificar se app/Helpers/autoload.php existe
$autoloadPath = dirname(__DIR__) . '/app/Helpers/autoload.php';
echo "Verificando: {$autoloadPath}\n";

if (!file_exists($autoloadPath)) {
    echo "\n✗ ERRO CRÍTICO: Arquivo app/Helpers/autoload.php não encontrado!\n";
    echo "Caminho esperado: {$autoloadPath}\n";
    exit(1);
}

echo "✓ app/Helpers/autoload.php encontrado\n";

try {
    require_once $autoloadPath;
    echo "✓ app/Helpers/autoload.php carregado com sucesso\n\n";
} catch (\Exception $e) {
    echo "✗ ERRO ao carregar app/Helpers/autoload.php: " . $e->getMessage() . "\n";
    exit(1);
}

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
        
        echo "[" . date('H:i:s') . "] Verificando: {$agentName} (Status: {$currentStatus})\n";
        
        // Verificar last_seen_at para marcar como offline
        if ($lastSeen) {
            $lastSeenDt = new DateTime($lastSeen);
            $minutesSinceLastSeen = ($now->getTimestamp() - $lastSeenDt->getTimestamp()) / 60;
            
            echo "  - Último visto: {$lastSeen} (" . round($minutesSinceLastSeen, 1) . " minutos atrás)\n";
            
            // Se passou do timeout de offline
            if ($minutesSinceLastSeen >= $offlineTimeoutMinutes) {
                echo "  ⚠️  AÇÃO: Marcar como OFFLINE (sem heartbeat há " . round($minutesSinceLastSeen, 1) . " minutos)\n";
                AvailabilityService::updateAvailabilityStatus($agentId, 'offline', 'heartbeat_timeout_cron');
                $updated++;
                echo "\n"; // Separar agentes que mudaram
                continue;
            }
        } else {
            echo "  - Sem registro de last_seen_at\n";
        }
        
        // Verificar last_activity_at para marcar como away (apenas se estiver online)
        if ($currentStatus === 'online' && $lastActivity && $settings['auto_away_enabled']) {
            $lastActivityDt = new DateTime($lastActivity);
            $minutesSinceActivity = ($now->getTimestamp() - $lastActivityDt->getTimestamp()) / 60;
            
            echo "  - Última atividade: {$lastActivity} (" . round($minutesSinceActivity, 1) . " minutos atrás)\n";
            
            // Se passou do timeout de away
            if ($minutesSinceActivity >= $awayTimeoutMinutes) {
                echo "  ⚠️  AÇÃO: Marcar como AWAY (sem atividade há " . round($minutesSinceActivity, 1) . " minutos)\n";
                AvailabilityService::updateAvailabilityStatus($agentId, 'away', 'inactivity_timeout_cron');
                $updated++;
                echo "\n"; // Separar agentes que mudaram
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

