<?php
/**
 * Controller de Autenticação
 */

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\Database;

class AuthController
{
    /**
     * Log para arquivo logs/auth.log
     */
    private static function logAuth(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/auth.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    /**
     * Mostrar página de login
     */
    public function showLogin(): void
    {
        self::logAuth("showLogin - Iniciando");
        
        if (Auth::check()) {
            $userId = Auth::id();
            self::logAuth("showLogin - Usuário já autenticado (userId={$userId}), redirecionando para /dashboard");
            Response::redirect('/dashboard');
            return;
        }

        self::logAuth("showLogin - Usuário não autenticado, exibindo formulário de login");
        Response::view('auth/login');
        self::logAuth("showLogin - View exibida");
    }

    /**
     * Processar login
     */
    public function login(): void
    {
        self::logAuth("login - Iniciando processamento POST");
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        self::logAuth("login - Email: {$email}");

        // Validar
        $errors = Validator::validate([
            'email' => $email,
            'password' => $password
        ], [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        if (!Validator::passes($errors)) {
            self::logAuth("login - Validação falhou: " . json_encode($errors));
            Response::view('auth/login', [
                'errors' => $errors,
                'email' => $email
            ]);
            return;
        }

        self::logAuth("login - Validação OK, buscando usuário...");
        
        // Buscar usuário
        $user = Database::fetch(
            "SELECT * FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            self::logAuth("login - Credenciais inválidas para {$email}");
            Response::view('auth/login', [
                'error' => 'Email ou senha inválidos',
                'email' => $email
            ]);
            return;
        }

        self::logAuth("login - Usuário encontrado: id={$user['id']}, name={$user['name']}");

        // Fazer login
        Auth::login($user['id'], [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);
        
        self::logAuth("login - Auth::login chamado com sucesso");

        // Marcar como online automaticamente (se configurado)
        try {
            \App\Services\AvailabilityService::markOnlineOnLogin($user['id']);
            self::logAuth("login - Marcado como online");
        } catch (\Exception $e) {
            self::logAuth("login - Erro ao marcar online: " . $e->getMessage());
        }

        self::logAuth("login - Redirecionando para /dashboard...");
        Response::redirect('/dashboard');
        self::logAuth("login - Redirect executado");
    }

    /**
     * Fazer logout
     */
    public function logout(): void
    {
        $userId = Auth::id();
        
        // Marcar como offline automaticamente (se configurado)
        if ($userId) {
            try {
                \App\Services\AvailabilityService::markOfflineOnLogout($userId);
            } catch (\Exception $e) {
                error_log("Erro ao marcar como offline no logout: " . $e->getMessage());
            }
        }
        
        Auth::logout();
        Response::redirect('/login');
    }
}

