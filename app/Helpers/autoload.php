<?php
/**
 * Autoloader Simples
 */

// ✅ CRÍTICO: Carregar autoloader do Composer PRIMEIRO (para dependências externas)
$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(function ($class) {
    // Converter namespace para caminho
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Carregar helpers
require __DIR__ . '/Database.php';
require __DIR__ . '/Response.php';
require __DIR__ . '/Validator.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Router.php';
require __DIR__ . '/Url.php';
require __DIR__ . '/Request.php';
require __DIR__ . '/Permission.php';
require __DIR__ . '/Logger.php';

