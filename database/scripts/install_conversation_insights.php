#!/usr/bin/env php
<?php
/**
 * Instalador da Análise de Conversas por Coorte
 *
 * Roda, na ordem, tudo que a funcionalidade precisa:
 *   1. migration 157 — tabelas da análise
 *   2. migration 158 — histórico de etapas (tabela + colunas + índices)
 *   3. backfill do histórico de etapas
 *   4. permissões
 *
 * É seguro rodar de novo: cada passo é idempotente.
 *
 * USO:  php database/scripts/install_conversation_insights.php [--skip-backfill]
 * Docker: docker exec CONTAINER php /var/www/html/database/scripts/install_conversation_insights.php
 */

$rootDir = dirname(__DIR__, 2);
chdir($rootDir);
require_once $rootDir . '/config/bootstrap.php';

use App\Helpers\Database;

$skipBackfill = in_array('--skip-backfill', $argv, true);

echo "==========================================\n";
echo "  INSTALAÇÃO — Análise de Conversas\n";
echo "==========================================\n\n";

// Conexão usada pelas migrations (elas esperam $pdo global)
try {
    $pdo = Database::getInstance();
    echo "✅ Conectado ao banco\n\n";
} catch (\Exception $e) {
    echo "❌ Não foi possível conectar ao banco: " . $e->getMessage() . "\n";
    exit(1);
}

$steps = 0;
$failures = 0;

/**
 * Executa uma migration pelo número, resolvendo a função up_*
 */
function runMigration(string $number, string $rootDir): bool
{
    $files = glob($rootDir . "/database/migrations/{$number}_*.php");

    if (empty($files)) {
        echo "❌ Migration {$number} não encontrada\n";
        return false;
    }

    $file = $files[0];
    require_once $file;

    $parts = explode('_', basename($file, '.php'), 2);
    $function = 'up_' . ($parts[1] ?? '');

    if (!function_exists($function)) {
        echo "❌ Função {$function} não existe em " . basename($file) . "\n";
        return false;
    }

    try {
        $function();
        return true;
    } catch (\Throwable $e) {
        echo "❌ Erro na migration {$number}: " . $e->getMessage() . "\n";
        return false;
    }
}

// ---------------------------------------------------------------------------
echo "▶ Passo 1/4 — Tabelas da análise (migration 157)\n";
$steps++;
if (!runMigration('157', $rootDir)) {
    $failures++;
}
echo "\n";

// ---------------------------------------------------------------------------
echo "▶ Passo 2/4 — Histórico de etapas (migration 158)\n";
$steps++;
if (!runMigration('158', $rootDir)) {
    $failures++;
}
echo "\n";

// ---------------------------------------------------------------------------
echo "▶ Passo 3/4 — Backfill do histórico de etapas\n";
$steps++;

if ($skipBackfill) {
    echo "⏭️  Pulado (--skip-backfill)\n";
} else {
    $backfill = $rootDir . '/database/scripts/backfill_funnel_stage_history.php';

    if (!is_file($backfill)) {
        echo "❌ Script de backfill não encontrado\n";
        $failures++;
    } else {
        // Em subprocesso: o script tem seu próprio bootstrap e argumentos
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($backfill) . ' 2>&1';
        exec($command, $output, $exitCode);

        foreach ($output as $line) {
            echo '   ' . $line . "\n";
        }

        if ($exitCode !== 0) {
            echo "❌ Backfill retornou código {$exitCode}\n";
            $failures++;
        }
    }
}
echo "\n";

// ---------------------------------------------------------------------------
echo "▶ Passo 4/4 — Permissões\n";
$steps++;

$seed = $rootDir . '/database/seeds/add_conversation_insights_permissions.php';

if (!is_file($seed)) {
    echo "❌ Seed de permissões não encontrado\n";
    $failures++;
} else {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($seed) . ' 2>&1';
    exec($command, $seedOutput, $seedExit);

    foreach ($seedOutput as $line) {
        echo '   ' . $line . "\n";
    }

    if ($seedExit !== 0) {
        echo "❌ Seed retornou código {$seedExit}\n";
        $failures++;
    }
}
echo "\n";

// ---------------------------------------------------------------------------
echo "==========================================\n";

// Conferência final: as tabelas realmente existem?
$checks = [
    'conversation_analysis_batches' => 'Tabela dos lotes de análise',
    'conversation_analysis_items' => 'Tabela dos resultados por conversa',
    'funnel_stage_history' => 'Histórico de etapas',
];

echo "  VERIFICAÇÃO\n";
echo "==========================================\n";

foreach ($checks as $table => $label) {
    $exists = !empty(Database::fetch("SHOW TABLES LIKE " . Database::getInstance()->quote($table)));
    echo ($exists ? '✅' : '❌') . "  {$label} ({$table})\n";

    if (!$exists) {
        $failures++;
    }
}

$permissions = Database::fetchAll(
    "SELECT slug FROM permissions WHERE slug IN ('conversation_insights.view', 'conversation_insights.run')"
);
echo (count($permissions) === 2 ? '✅' : '❌') . "  Permissões (" . count($permissions) . "/2)\n";

$historyCount = 0;
try {
    $row = Database::fetch("SELECT COUNT(*) AS total FROM funnel_stage_history");
    $historyCount = (int)($row['total'] ?? 0);
} catch (\Exception $e) {
    // tabela pode não existir se a 158 falhou
}
echo "ℹ️   Eventos no histórico de etapas: {$historyCount}\n";

echo "\n";

if ($failures === 0) {
    echo "🎉 Instalação concluída. Acesse: Performance → Análise de Conversas\n\n";
    echo "Não esqueça de agendar o processamento (a cada minuto):\n";
    echo "  * * * * * php " . $rootDir . "/public/scripts/process-conversation-analysis.php\n";
    exit(0);
}

echo "⚠️  Concluído com {$failures} problema(s). Veja as mensagens acima.\n";
exit(1);
