<?php
/**
 * Arquivo temporário para testar login diretamente
 * Acesse: http://localhost/chat/public/login.php
 * 
 * Este arquivo será removido depois que o Router estiver funcionando
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/Helpers/autoload.php';

// Simular que estamos acessando via Router
$_SERVER['REQUEST_METHOD'] = 'GET';

// Chamar o controller diretamente
try {
    $controller = new \App\Controllers\AuthController();
    $controller->showLogin();
} catch (\Throwable $e) {
    echo "<h1>Erro</h1>";
    echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

