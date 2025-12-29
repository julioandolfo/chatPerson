<?php
/**
 * Bootstrap File
 * Inicializa o ambiente da aplicação para scripts CLI e testes
 */

// Garantir que estamos no diretório raiz do projeto
chdir(dirname(__DIR__));

// Autoloader personalizado (PHP Vanilla, sem Composer)
require_once __DIR__ . '/../app/Helpers/autoload.php';

// Garantir que o diretório de logs existe
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Configurar error reporting para scripts CLI
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', $logsDir . '/app.log');

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Garantir que sessions funcionem (caso necessário)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

