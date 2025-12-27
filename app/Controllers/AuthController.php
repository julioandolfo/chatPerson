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
     * Mostrar página de login
     */
    public function showLogin(): void
    {
        $debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
        
        if ($debug) {
            error_log("AuthController::showLogin - Iniciando");
        }
        
        if (Auth::check()) {
            if ($debug) {
                error_log("AuthController::showLogin - Usuário já autenticado, redirecionando");
            }
            Response::redirect('/dashboard');
            return;
        }

        if ($debug) {
            error_log("AuthController::showLogin - Chamando Response::view");
        }
        
        Response::view('auth/login');
        
        if ($debug) {
            error_log("AuthController::showLogin - View chamada");
        }
    }

    /**
     * Processar login
     */
    public function login(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validar
        $errors = Validator::validate([
            'email' => $email,
            'password' => $password
        ], [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        if (!Validator::passes($errors)) {
            Response::view('auth/login', [
                'errors' => $errors,
                'email' => $email
            ]);
            return;
        }

        // Buscar usuário
        $user = Database::fetch(
            "SELECT * FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            Response::view('auth/login', [
                'error' => 'Email ou senha inválidos',
                'email' => $email
            ]);
            return;
        }

        // Fazer login
        Auth::login($user['id'], [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        // Marcar como online automaticamente (se configurado)
        try {
            \App\Services\AvailabilityService::markOnlineOnLogin($user['id']);
        } catch (\Exception $e) {
            error_log("Erro ao marcar como online no login: " . $e->getMessage());
        }

        Response::redirect('/dashboard');
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

