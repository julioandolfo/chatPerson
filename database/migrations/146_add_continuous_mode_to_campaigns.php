<?php
/**
 * Migration 146: Modo Contínuo de Campanha
 * Permite que campanhas ligadas a fontes com sincronização diária
 * continuem ativas, absorvendo novos contatos automaticamente.
 */

use App\Helpers\Database;

$pdo = Database::getInstance();

$pdo->exec("ALTER TABLE campaigns
    ADD COLUMN continuous_mode TINYINT(1) NOT NULL DEFAULT 0
");

echo "✅ Migration 146: continuous_mode adicionado à tabela campaigns\n";
