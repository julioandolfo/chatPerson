<?php
/**
 * Cron Job: Verificação de Conexões WhatsApp
 * Executar a cada 5 minutos via Task Scheduler ou cron
 * 
 * Windows: C:\laragon\bin\php\php-8.x\php.exe C:\laragon\www\chat\public\scripts\check-whatsapp-connections.php
 * Linux: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /var/www/html/public/scripts/check-whatsapp-connections.php
 * Docker: (a cada 5 min) docker exec CONTAINER php /var/www/html/public/scripts/check-whatsapp-connections.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Jobs\WhatsAppConnectionMonitoringJob;
use App\Helpers\Logger;

echo "=== VERIFICAÇÃO DE CONEXÕES WHATSAPP ===\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n\n";

$start = microtime(true);

try {
    // Verificar se o Job existe
    if (!class_exists(WhatsAppConnectionMonitoringJob::class)) {
        throw new \Exception("WhatsAppConnectionMonitoringJob não encontrado");
    }
    
    // Executar job
    $results = WhatsAppConnectionMonitoringJob::run();
    
    $duration = round((microtime(true) - $start) * 1000);
    
    // Exibir resultados
    echo "Verificação concluída em {$duration}ms\n";
    echo "  - Contas verificadas: " . ($results['checked'] ?? 0) . "\n";
    echo "  - Conectadas: " . ($results['connected'] ?? 0) . "\n";
    echo "  - Desconectadas: " . ($results['disconnected'] ?? 0) . "\n";
    echo "  - Alertas criados: " . ($results['alerts_created'] ?? 0) . "\n";
    echo "  - Alertas resolvidos: " . ($results['alerts_resolved'] ?? 0) . "\n";
    echo "  - Erros: " . ($results['errors'] ?? 0) . "\n";
    
    // Se houver desconexões, listar
    if (($results['disconnected'] ?? 0) > 0 && !empty($results['details'])) {
        echo "\n⚠️  Contas desconectadas:\n";
        foreach ($results['details'] as $detail) {
            if (isset($detail['connected']) && !$detail['connected']) {
                echo "  - " . ($detail['account_name'] ?? 'N/A') . " (" . ($detail['phone_number'] ?? 'N/A') . "): " . ($detail['message'] ?? '') . "\n";
            }
        }
    }
    
    // Se houver erros, listar
    if (($results['errors'] ?? 0) > 0 && !empty($results['details'])) {
        echo "\n❌ Erros encontrados:\n";
        foreach ($results['details'] as $detail) {
            if (isset($detail['error'])) {
                echo "  - Conta " . ($detail['account_id'] ?? 'N/A') . ": " . $detail['error'] . "\n";
            }
        }
    }
    
    echo "\n=== CONCLUÍDO ===\n";
    echo "Tempo total: {$duration}ms\n";
    echo "Finalizado em: " . date('Y-m-d H:i:s') . "\n";
    
    Logger::info("WhatsApp Connection Check: {$results['checked']} verificadas, {$results['disconnected']} desconectadas");
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    Logger::error("Erro ao verificar conexões WhatsApp: " . $e->getMessage());
    exit(1);
}

exit(0);
