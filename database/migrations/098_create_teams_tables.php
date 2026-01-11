<?php
/**
 * Migration: Criar tabelas para Times/Equipes
 * Times são grupos de agentes para organização e métricas agregadas
 */

function up_teams_tables() {
    global $pdo;
    
    // Tabela de times
    $sql = "CREATE TABLE IF NOT EXISTS teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome do time',
        description TEXT NULL COMMENT 'Descrição do time',
        color VARCHAR(7) NULL COMMENT 'Cor hex para identificação visual',
        leader_id INT NULL COMMENT 'ID do líder do time',
        department_id INT NULL COMMENT 'Setor ao qual o time pertence (opcional)',
        is_active TINYINT(1) DEFAULT 1 COMMENT 'Se o time está ativo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        INDEX idx_is_active (is_active),
        INDEX idx_department_id (department_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'teams' criada com sucesso!\n";
    
    // Tabela de membros do time (muitos-para-muitos)
    $sql = "CREATE TABLE IF NOT EXISTS team_members (
        team_id INT NOT NULL,
        user_id INT NOT NULL COMMENT 'ID do agente/usuário',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de entrada no time',
        PRIMARY KEY (team_id, user_id),
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_team_id (team_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'team_members' criada com sucesso!\n";
}

function down_teams_tables() {
    $sql = "DROP TABLE IF EXISTS team_members";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'team_members' removida!\n";
    
    $sql = "DROP TABLE IF EXISTS teams";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'teams' removida!\n";
}
