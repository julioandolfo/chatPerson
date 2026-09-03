<?php
/**
 * Script para executar a migration 156 - Auditoria de webhooks do WhatsApp
 * (tabela whatsapp_webhook_audit)
 *
 * Acesse uma única vez:  /run-migration-156.php
 * REMOVA ESTE ARQUIVO APÓS EXECUTAR!
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "<h1>Migration 156 - Auditoria de webhooks do WhatsApp</h1>";

try {
    // Disponibiliza o PDO global esperado pela migration
    $pdo = \App\Helpers\Database::getInstance();

    // Reusa exatamente a migration oficial (idempotente)
    require_once __DIR__ . '/../database/migrations/156_create_whatsapp_webhook_audit_table.php';

    echo "<pre>";
    up_create_whatsapp_webhook_audit_table();
    echo "</pre>";

    // Conferência
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote('whatsapp_webhook_audit'));
    $exists = $stmt->rowCount() > 0;
    echo "<p style='color: " . ($exists ? 'green' : 'red') . ";'>"
        . ($exists ? '✅' : '❌') . " Tabela 'whatsapp_webhook_audit' "
        . ($exists ? 'existe' : 'NÃO existe') . ".</p>";

    if ($exists) {
        $cols = $pdo->query("SHOW COLUMNS FROM whatsapp_webhook_audit")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Colunas (" . count($cols) . "): " . htmlspecialchars(implode(', ', $cols)) . "</p>";
    }

    echo "<h2 style='color: green;'>Migration concluída!</h2>";
    echo "<p>A partir de agora todo webhook de WhatsApp fica registrado com o desfecho "
        . "(processado, descartado com motivo, erro ou fatal).</p>";
    echo "<p><strong>⚠️ IMPORTANTE:</strong> Apague este arquivo (public/run-migration-156.php) após executar.</p>";
    echo "<p><a href='/debug-whatsapp-numero.php'>Ir para o Debug WhatsApp por número &raquo;</a></p>";

} catch (\Throwable $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
