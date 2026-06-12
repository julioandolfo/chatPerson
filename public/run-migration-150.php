<?php
/**
 * Script para executar a migration 150 - Tabelas do canal de Email
 * (email_ingestion_rules, email_ingestion_log)
 *
 * Acesse uma única vez:  /run-migration-150.php
 * REMOVA ESTE ARQUIVO APÓS EXECUTAR!
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "<h1>Migration 150 - Canal de Email (regras + log)</h1>";

try {
    // Disponibiliza o PDO global esperado pela migration
    $pdo = \App\Helpers\Database::getInstance();

    // Reusa exatamente a migration oficial (idempotente)
    require_once __DIR__ . '/../database/migrations/150_create_email_ingestion_tables.php';

    echo "<pre>";
    up_create_email_ingestion_tables();
    echo "</pre>";

    // Conferência
    foreach (['email_ingestion_rules', 'email_ingestion_log'] as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        $exists = $stmt->rowCount() > 0;
        echo "<p style='color: " . ($exists ? 'green' : 'red') . ";'>"
            . ($exists ? '✅' : '❌') . " Tabela '{$table}' "
            . ($exists ? 'existe' : 'NÃO existe') . ".</p>";
    }

    echo "<h2 style='color: green;'>Migration concluída!</h2>";
    echo "<p><strong>⚠️ IMPORTANTE:</strong> Apague este arquivo (public/run-migration-150.php) após executar.</p>";
    echo "<p><a href='/email-integration'>Ir para Integrações &raquo; Email</a></p>";

} catch (\Throwable $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
