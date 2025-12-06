<?php
/**
 * Configurações Gerais da Aplicação
 */

// Habilitar exibição de erros em desenvolvimento
if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

return [
    'name' => 'Sistema Multiatendimento',
    'version' => '1.0.0',
    'env' => $_ENV['APP_ENV'] ?? 'development',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'true') === 'true',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost/chat',
    'timezone' => 'America/Sao_Paulo',
    'locale' => 'pt_BR',
    'session' => [
        'lifetime' => 120, // minutos
        'name' => 'chat_session',
    ],
    'pagination' => [
        'per_page' => 20,
    ],
];
