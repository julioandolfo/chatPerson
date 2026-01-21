<?php
/**
 * Script para verificar configuração de timezone do servidor
 * Execute: php check-timezone.php
 */

echo "=== VERIFICAÇÃO DE TIMEZONE DO SERVIDOR ===\n\n";

// 1. Verificar timezone configurado no PHP
echo "1. TIMEZONE PHP:\n";
echo "   Timezone atual: " . date_default_timezone_get() . "\n";
echo "   Data/Hora atual: " . date('Y-m-d H:i:s') . "\n";
echo "   Data/Hora atual (formato BR): " . date('d/m/Y H:i:s') . "\n\n";

// 2. Verificar timezone via ini
echo "2. CONFIGURAÇÃO PHP.INI:\n";
$phpIniTz = ini_get('date.timezone');
echo "   date.timezone: " . ($phpIniTz ?: '(não configurado)') . "\n\n";

// 3. Testar após configurar explicitamente
date_default_timezone_set('America/Sao_Paulo');
echo "3. APÓS CONFIGURAR America/Sao_Paulo:\n";
echo "   Timezone: " . date_default_timezone_get() . "\n";
echo "   Data/Hora: " . date('Y-m-d H:i:s') . "\n";
echo "   Data/Hora (formato BR): " . date('d/m/Y H:i:s') . "\n\n";

// 4. Verificar offset UTC
$now = new DateTime();
echo "4. OFFSET UTC:\n";
echo "   Offset: " . $now->format('P') . "\n";
echo "   Timezone Name: " . $now->getTimezone()->getName() . "\n\n";

// 5. Verificar timezone do MySQL/MariaDB
echo "5. TENTANDO CONECTAR AO MYSQL...\n";

try {
    require_once __DIR__ . '/config/bootstrap.php';
    $db = \App\Helpers\Database::getInstance();
    
    echo "   ✅ Conectado ao MySQL\n\n";
    
    echo "6. TIMEZONE MYSQL/MARIADB:\n";
    
    // Timezone do sistema MySQL
    $result = $db->query("SELECT @@system_time_zone as system_tz, @@time_zone as session_tz")->fetch(PDO::FETCH_ASSOC);
    echo "   System Timezone: " . $result['system_tz'] . "\n";
    echo "   Session Timezone: " . $result['session_tz'] . "\n";
    
    // Data/hora atual do MySQL
    $result = $db->query("SELECT NOW() as mysql_now, UTC_TIMESTAMP() as mysql_utc")->fetch(PDO::FETCH_ASSOC);
    echo "   MySQL NOW(): " . $result['mysql_now'] . "\n";
    echo "   MySQL UTC_TIMESTAMP(): " . $result['mysql_utc'] . "\n\n";
    
    // 7. Comparar com uma conversa real
    echo "7. TESTE COM DADOS REAIS:\n";
    $conv = $db->query("SELECT id, created_at, updated_at FROM conversations ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    if ($conv) {
        echo "   Última conversa ID: {$conv['id']}\n";
        echo "   Created_at (MySQL): {$conv['created_at']}\n";
        echo "   Updated_at (MySQL): {$conv['updated_at']}\n";
        
        // Calcular diferença com agora
        $createdAt = new DateTime($conv['created_at']);
        $now = new DateTime();
        $diff = $now->diff($createdAt);
        
        echo "   Diferença com agora: ";
        if ($diff->days > 0) echo "{$diff->days} dias ";
        if ($diff->h > 0) echo "{$diff->h} horas ";
        if ($diff->i > 0) echo "{$diff->i} minutos ";
        echo "{$diff->s} segundos atrás\n";
    } else {
        echo "   Nenhuma conversa encontrada\n";
    }
    
    echo "\n";
    
    // 8. Verificar working hours
    echo "8. CONFIGURAÇÃO WORKING HOURS:\n";
    $settings = \App\Services\ConversationSettingsService::getSettings();
    $sla = $settings['sla'] ?? [];
    
    echo "   Working Hours Enabled: " . ($sla['working_hours_enabled'] ?? false ? 'SIM' : 'NÃO') . "\n";
    echo "   Working Hours Start: " . ($sla['working_hours_start'] ?? 'N/A') . "\n";
    echo "   Working Hours End: " . ($sla['working_hours_end'] ?? 'N/A') . "\n";
    echo "   First Response SLA: " . ($sla['first_response_time'] ?? 'N/A') . " minutos\n";
    echo "   Ongoing Response SLA: " . ($sla['ongoing_response_time'] ?? 'N/A') . " minutos\n";
    echo "   Message Delay: " . ($sla['message_delay_minutes'] ?? 'N/A') . " minutos\n\n";
    
    // 9. Recomendações
    echo "\n=== ANÁLISE E RECOMENDAÇÕES ===\n";
    $phpTz = date_default_timezone_get();
    $mysqlTz = $result['session_tz'] ?? '';
    
    if ($phpTz !== 'America/Sao_Paulo') {
        echo "⚠️  AVISO: PHP timezone não é America/Sao_Paulo (atual: {$phpTz})\n";
        echo "   Corrija no php.ini ou no início dos scripts\n";
    } else {
        echo "✅ PHP timezone correto: America/Sao_Paulo\n";
    }
    
    if (!$phpIniTz || $phpIniTz === '') {
        echo "⚠️  AVISO: php.ini não tem date.timezone configurado\n";
        echo "   Recomenda-se configurar: date.timezone = America/Sao_Paulo\n";
        echo "   📄 Veja: CONFIGURAR_TIMEZONE_LARAGON.md\n";
    } elseif ($phpIniTz !== 'America/Sao_Paulo') {
        echo "⚠️  AVISO: php.ini configurado como {$phpIniTz}\n";
        echo "   Recomenda-se mudar para: America/Sao_Paulo\n";
        echo "   📄 Veja: CONFIGURAR_TIMEZONE_LARAGON.md\n";
    } else {
        echo "✅ php.ini configurado corretamente\n";
    }
    
    if ($mysqlTz !== 'America/Sao_Paulo' && $mysqlTz !== '-03:00' && $mysqlTz !== 'SYSTEM') {
        echo "⚠️  AVISO: MySQL timezone pode estar incorreto (atual: {$mysqlTz})\n";
        echo "   Configure com: SET GLOBAL time_zone = 'America/Sao_Paulo';\n";
        echo "   📄 Veja: CONFIGURAR_TIMEZONE_LARAGON.md\n";
    } else {
        echo "✅ MySQL timezone compatível\n";
    }
    
    echo "\n✅ Verificação concluída!\n";
    echo "\n📚 Documentação: CORRECAO_TIMEZONE_CRITICO_21_JAN_2026.md\n";
    echo "📚 Guia de config: CONFIGURAR_TIMEZONE_LARAGON.md\n\n";
    
} catch (\Exception $e) {
    echo "   ❌ Erro ao conectar: " . $e->getMessage() . "\n";
    echo "   (MySQL pode estar desligado ou credenciais incorretas)\n\n";
    
    echo "=== ANÁLISE (SEM MYSQL) ===\n";
    $phpTz = date_default_timezone_get();
    
    if ($phpTz !== 'America/Sao_Paulo') {
        echo "⚠️  AVISO: PHP timezone não é America/Sao_Paulo (atual: {$phpTz})\n";
    } else {
        echo "✅ PHP timezone correto: America/Sao_Paulo\n";
    }
    
    if (!$phpIniTz || $phpIniTz === '') {
        echo "⚠️  AVISO: php.ini não tem date.timezone configurado\n";
        echo "   📄 Veja: CONFIGURAR_TIMEZONE_LARAGON.md\n";
    } elseif ($phpIniTz !== 'America/Sao_Paulo') {
        echo "⚠️  php.ini configurado como {$phpIniTz}\n";
        echo "   📄 Veja: CONFIGURAR_TIMEZONE_LARAGON.md\n";
    } else {
        echo "✅ php.ini configurado corretamente\n";
    }
    
    echo "\n✅ Verificação PHP concluída (MySQL offline)\n\n";
}
