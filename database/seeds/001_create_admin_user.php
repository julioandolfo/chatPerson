<?php
/**
 * Seed: Criar usuário admin padrão
 */

function seed_admin_user() {
    $email = 'admin@example.com';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Verificar se já existe
    $existing = \App\Helpers\Database::fetch(
        "SELECT id FROM users WHERE email = ?",
        [$email]
    );
    
    if ($existing) {
        echo "⚠️  Usuário admin já existe!\n";
        return;
    }
    
    // Criar usuário admin
    \App\Helpers\Database::insert(
        "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)",
        ['Administrador', $email, $password, 'admin', 'active']
    );
    
    echo "✅ Usuário admin criado!\n";
    echo "   Email: {$email}\n";
    echo "   Senha: admin123\n";
}

