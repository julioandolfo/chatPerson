<?php
/**
 * Cron Job: Verificação de Conexões WhatsApp
 * 
 * Executa verificação periódica das conexões WhatsApp
 * e cria alertas quando detecta desconexões.
 * 
 * Recomendação: Executar a cada 1 hora
 * 
 * Configuração no crontab:
 * 0 * * * * php /caminho/para/chat/cron/check-whatsapp-connections.php >> /var/log/whatsapp-check.log 2>&1
 * 
 * Configuração no Windows Task Scheduler:
 * Programa: php
 * Argumentos: C:\laragon\www\chat\cron\check-whatsapp-connections.php
 * Repetir: A cada 1 hora
 */

// Definir diretório base
$baseDir = dirname(__DIR__);

// Carregar autoloader
require_once $baseDir . '/public/index.php';

use App\Jobs\WhatsAppConnectionMonitoringJob;

// Registrar início da execução
$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "[{$timestamp}] WhatsApp Connection Check - Iniciando...\n";

try {
    // Executar job
    $results = WhatsAppConnectionMonitoringJob::run();
    
    // Calcular tempo de execução
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    // Exibir resultados
    echo "[{$timestamp}] Verificação concluída em {$duration}ms\n";
    echo "  - Contas verificadas: {$results['checked']}\n";
    echo "  - Conectadas: {$results['connected']}\n";
    echo "  - Desconectadas: {$results['disconnected']}\n";
    echo "  - Alertas criados: {$results['alerts_created']}\n";
    echo "  - Alertas resolvidos: {$results['alerts_resolved']}\n";
    echo "  - Erros: {$results['errors']}\n";
    
    // Se houver desconexões, listar
    if ($results['disconnected'] > 0) {
        echo "\n⚠️  Contas desconectadas:\n";
        foreach ($results['details'] as $detail) {
            if (isset($detail['connected']) && !$detail['connected']) {
                echo "  - {$detail['account_name']} ({$detail['phone_number']}): {$detail['message']}\n";
            }
        }
    }
    
    // Se houver erros, listar
    if ($results['errors'] > 0) {
        echo "\n❌ Erros encontrados:\n";
        foreach ($results['details'] as $detail) {
            if (isset($detail['error'])) {
                echo "  - Conta {$detail['account_id']}: {$detail['error']}\n";
            }
        }
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] ❌ Erro fatal: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
