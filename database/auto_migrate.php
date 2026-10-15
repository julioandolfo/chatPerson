<?php
/**
 * Migrations automáticas no deploy (executado pelo docker/entrypoint.sh ao subir o container)
 *
 * O projeto não registrava quais migrations já rodaram, e várias das antigas não podem ser
 * repetidas (ALTER TABLE ADD COLUMN...). Por isso aqui só entram migrations IDEMPOTENTES
 * (CREATE TABLE IF NOT EXISTS / checam a coluna antes), listadas explicitamente.
 *
 * Ao criar uma migration nova que deva rodar sozinha no deploy:
 *   1. escreva-a de forma idempotente;
 *   2. acrescente o número em AUTO_MIGRATIONS abaixo.
 *
 * O que rodou fica em `schema_migrations`. Se o banco não responder, sai sem erro fatal
 * (o site sobe do mesmo jeito; rode manualmente: php database/auto_migrate.php).
 */

const AUTO_MIGRATIONS = [
    '159', // follow-up por etapa
    '160', // PWA: manter conectado + push
    '161', // follow-up: limites de envio anti-spam
    '162', // alerta de conversa parada na etapa
];

const DB_WAIT_SECONDS = 60;

function amLog(string $message): void
{
    echo '[auto-migrate ' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
}

$root = dirname(__DIR__);
$dbConfig = require $root . '/config/database.php';

// Banco pode subir depois da aplicação: tenta por até DB_WAIT_SECONDS
$pdo = null;
$deadline = time() + DB_WAIT_SECONDS;
do {
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['options']
        );
    } catch (PDOException $e) {
        if (time() >= $deadline) {
            amLog('Banco indisponível (' . $e->getMessage() . '). Migrations NÃO executadas.');
            exit(1);
        }
        sleep(3);
    }
} while ($pdo === null);

// As migrations usam o helper Database quando $pdo não está no escopo global
require_once $root . '/app/Helpers/autoload.php';
$GLOBALS['pdo'] = $pdo;
$pdo->exec("SET time_zone = '-03:00'");

// Um container por vez (vários subindo juntos no deploy)
$lock = $pdo->query("SELECT GET_LOCK('chatperson_auto_migrate', 120)")->fetchColumn();
if ((int)$lock !== 1) {
    amLog('Outra instância está migrando; pulando.');
    exit(0);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(100) NOT NULL PRIMARY KEY,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$done = $pdo->query("SELECT migration FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$failed = false;

foreach (AUTO_MIGRATIONS as $number) {
    $files = glob($root . "/database/migrations/{$number}_*.php");
    if (empty($files)) {
        amLog("Migration {$number} não encontrada; ignorando.");
        continue;
    }
    $name = basename($files[0], '.php');

    if (in_array($name, $done, true)) {
        continue;
    }

    $function = 'up_' . explode('_', $name, 2)[1];
    amLog("Executando {$name}...");

    try {
        require_once $files[0];
        if (!function_exists($function)) {
            throw new RuntimeException("função {$function} não encontrada");
        }
        ob_start();
        $function();
        $output = trim((string)ob_get_clean());
        if ($output !== '') {
            amLog('  ' . str_replace("\n", "\n  ", $output));
        }

        $stmt = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
        $stmt->execute([$name]);
        amLog("OK {$name}");
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        amLog("ERRO em {$name}: " . $e->getMessage());
        $failed = true;
        break; // as seguintes podem depender desta
    }
}

$pdo->query("SELECT RELEASE_LOCK('chatperson_auto_migrate')");
amLog($failed ? 'Concluído com erro.' : 'Banco atualizado.');
exit($failed ? 1 : 0);
