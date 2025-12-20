<?php
/**
 * Script para verificar se os crons estão configurados corretamente
 * 
 * Execute: php public/check-crons.php
 */

require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

echo "🔍 VERIFICAÇÃO DE CRONS E PROCESSOS\n";
echo str_repeat("=", 60) . "\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar se os scripts existem
echo "📁 Verificando scripts...\n";
$scripts = [
    'process-scheduled-messages.php' => 'public/scripts/process-scheduled-messages.php',
    'process-reminders.php' => 'public/scripts/process-reminders.php',
    'run-scheduled-jobs.php' => 'public/run-scheduled-jobs.php',
    'websocket-server.php' => 'public/websocket-server.php',
];

foreach ($scripts as $name => $path) {
    $fullPath = __DIR__ . '/../' . $path;
    if (file_exists($fullPath)) {
        echo "  ✅ {$name} existe\n";
        $success[] = "Script {$name} encontrado";
    } else {
        echo "  ❌ {$name} NÃO encontrado em {$path}\n";
        $errors[] = "Script {$name} não encontrado";
    }
}

echo "\n";

// 2. Verificar diretório de logs
echo "📝 Verificando diretório de logs...\n";
$logsDir = __DIR__ . '/../storage/logs';
if (is_dir($logsDir)) {
    echo "  ✅ Diretório storage/logs existe\n";
    if (is_writable($logsDir)) {
        echo "  ✅ Diretório storage/logs é gravável\n";
        $success[] = "Diretório de logs existe e é gravável";
    } else {
        echo "  ⚠️  Diretório storage/logs NÃO é gravável\n";
        $warnings[] = "Diretório de logs não é gravável (chmod 755 ou 777)";
    }
} else {
    echo "  ❌ Diretório storage/logs NÃO existe\n";
    $errors[] = "Diretório de logs não existe";
}

echo "\n";

// 3. Verificar se os logs foram gerados recentemente
echo "📊 Verificando logs recentes...\n";
$logFiles = [
    'scheduled-messages.log' => 'Mensagens Agendadas',
    'reminders.log' => 'Lembretes',
    'jobs.log' => 'Jobs Agendados',
    'websocket.log' => 'WebSocket',
];

foreach ($logFiles as $logFile => $description) {
    $logPath = $logsDir . '/' . $logFile;
    if (file_exists($logPath)) {
        $lastModified = filemtime($logPath);
        $minutesAgo = round((time() - $lastModified) / 60);
        
        if ($minutesAgo < 10) {
            echo "  ✅ {$description}: atualizado há {$minutesAgo} minuto(s)\n";
            $success[] = "Log {$description} atualizado recentemente";
        } elseif ($minutesAgo < 60) {
            echo "  ⚠️  {$description}: atualizado há {$minutesAgo} minuto(s) (pode estar parado)\n";
            $warnings[] = "Log {$description} não atualizado há {$minutesAgo} minutos";
        } else {
            echo "  ❌ {$description}: atualizado há {$minutesAgo} minuto(s) (provavelmente parado)\n";
            $errors[] = "Log {$description} não atualizado há {$minutesAgo} minutos";
        }
    } else {
        echo "  ⚠️  {$description}: arquivo de log não existe ainda\n";
        $warnings[] = "Log {$description} ainda não foi criado";
    }
}

echo "\n";

// 4. Verificar conexão com banco de dados
echo "🗄️  Verificando banco de dados...\n";
try {
    $db = Database::getInstance();
    echo "  ✅ Conexão com banco de dados OK\n";
    $success[] = "Conexão com banco de dados funcionando";
} catch (\Exception $e) {
    echo "  ❌ Erro ao conectar ao banco: " . $e->getMessage() . "\n";
    $errors[] = "Erro ao conectar ao banco de dados";
}

echo "\n";

// 5. Verificar se há mensagens agendadas pendentes
echo "📨 Verificando mensagens agendadas pendentes...\n";
try {
    $pending = Database::fetch("SELECT COUNT(*) as total FROM scheduled_messages WHERE status = 'pending' AND scheduled_at <= NOW()");
    $total = $pending['total'] ?? 0;
    if ($total > 0) {
        echo "  ⚠️  Há {$total} mensagem(ns) agendada(s) pendente(s)\n";
        $warnings[] = "Há {$total} mensagens agendadas pendentes (cron pode não estar rodando)";
    } else {
        echo "  ✅ Nenhuma mensagem agendada pendente\n";
        $success[] = "Nenhuma mensagem agendada pendente";
    }
} catch (\Exception $e) {
    echo "  ⚠️  Não foi possível verificar: " . $e->getMessage() . "\n";
    $warnings[] = "Não foi possível verificar mensagens agendadas";
}

echo "\n";

// 6. Verificar se há lembretes pendentes
echo "🔔 Verificando lembretes pendentes...\n";
try {
    $pending = Database::fetch("SELECT COUNT(*) as total FROM reminders WHERE status = 'pending' AND remind_at <= NOW()");
    $total = $pending['total'] ?? 0;
    if ($total > 0) {
        echo "  ⚠️  Há {$total} lembrete(s) pendente(s)\n";
        $warnings[] = "Há {$total} lembretes pendentes (cron pode não estar rodando)";
    } else {
        echo "  ✅ Nenhum lembrete pendente\n";
        $success[] = "Nenhum lembrete pendente";
    }
} catch (\Exception $e) {
    echo "  ⚠️  Não foi possível verificar: " . $e->getMessage() . "\n";
    $warnings[] = "Não foi possível verificar lembretes";
}

echo "\n";

// 7. Verificar se há delays de automação pendentes
echo "⏱️  Verificando delays de automação pendentes...\n";
try {
    $pending = Database::fetch("SELECT COUNT(*) as total FROM automation_delays WHERE status = 'pending' AND execute_at <= NOW()");
    $total = $pending['total'] ?? 0;
    if ($total > 0) {
        echo "  ⚠️  Há {$total} delay(s) de automação pendente(s)\n";
        $warnings[] = "Há {$total} delays de automação pendentes";
    } else {
        echo "  ✅ Nenhum delay de automação pendente\n";
        $success[] = "Nenhum delay de automação pendente";
    }
} catch (\Exception $e) {
    echo "  ⚠️  Não foi possível verificar: " . $e->getMessage() . "\n";
    $warnings[] = "Não foi possível verificar delays de automação";
}

echo "\n";

// 8. Verificar se WebSocket está rodando (Linux)
echo "🌐 Verificando processo WebSocket...\n";
if (PHP_OS_FAMILY !== 'Windows') {
    $output = shell_exec('ps aux | grep websocket-server.php | grep -v grep');
    if ($output) {
        echo "  ✅ Servidor WebSocket está rodando\n";
        $success[] = "Servidor WebSocket está ativo";
    } else {
        echo "  ⚠️  Servidor WebSocket NÃO está rodando\n";
        $warnings[] = "Servidor WebSocket não está rodando (opcional, mas recomendado)";
    }
} else {
    echo "  ⚠️  Verificação de processo não disponível no Windows\n";
    $warnings[] = "Verifique manualmente se o WebSocket está rodando";
}

echo "\n";

// 9. Verificar permissões dos scripts
echo "🔐 Verificando permissões dos scripts...\n";
foreach ($scripts as $name => $path) {
    $fullPath = __DIR__ . '/../' . $path;
    if (file_exists($fullPath)) {
        if (is_readable($fullPath)) {
            echo "  ✅ {$name} é legível\n";
        } else {
            echo "  ❌ {$name} NÃO é legível\n";
            $errors[] = "Script {$name} não é legível";
        }
    }
}

echo "\n";

// Resumo final
echo str_repeat("=", 60) . "\n";
echo "📊 RESUMO DA VERIFICAÇÃO\n";
echo str_repeat("=", 60) . "\n\n";

if (count($success) > 0) {
    echo "✅ SUCESSOS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "   • {$msg}\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  AVISOS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "   • {$msg}\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo "❌ ERROS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "   • {$msg}\n";
    }
    echo "\n";
}

// Recomendações
echo "💡 RECOMENDAÇÕES:\n";
echo str_repeat("-", 60) . "\n";

if (count($errors) > 0) {
    echo "❌ Corrija os erros acima antes de continuar.\n\n";
}

if (count($warnings) > 0) {
    echo "⚠️  Revise os avisos acima. Alguns podem indicar problemas.\n\n";
}

echo "📋 CRONS NECESSÁRIOS:\n";
echo "   1. * * * * * php /caminho/para/public/scripts/process-scheduled-messages.php >> /caminho/para/storage/logs/scheduled-messages.log 2>&1\n";
echo "   2. * * * * * php /caminho/para/public/scripts/process-reminders.php >> /caminho/para/storage/logs/reminders.log 2>&1\n";
echo "   3. */5 * * * * php /caminho/para/public/run-scheduled-jobs.php >> /caminho/para/storage/logs/jobs.log 2>&1\n\n";

echo "🌐 WEBSOCKET:\n";
echo "   Execute: php public/websocket-server.php\n";
echo "   Ou configure Supervisor/systemd/PM2 para manter rodando.\n\n";

echo "📖 Para mais informações, consulte: CRONS_COMPLETO.md\n";

// Exit code
if (count($errors) > 0) {
    exit(1);
} elseif (count($warnings) > 0) {
    exit(2);
} else {
    exit(0);
}

