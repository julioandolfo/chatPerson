<?php
/**
 * Migration: adicionar max_sections em manual_jobs
 * (controle de nº de cenários detalhados na geração de manuais).
 */

function up_add_max_sections_to_manual_jobs() {
    $db = \App\Helpers\Database::getInstance();
    try {
        $db->exec("ALTER TABLE manual_jobs ADD COLUMN max_sections INT DEFAULT 18 COMMENT 'Max. de cenarios detalhados'");
        echo "✅ Coluna 'max_sections' adicionada em manual_jobs.\n";
    } catch (\Throwable $e) {
        echo "⚠️  max_sections pode já existir: " . $e->getMessage() . "\n";
    }
}

function down_add_max_sections_to_manual_jobs() {
    $db = \App\Helpers\Database::getInstance();
    try {
        $db->exec("ALTER TABLE manual_jobs DROP COLUMN max_sections");
        echo "✅ Coluna 'max_sections' removida.\n";
    } catch (\Throwable $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
}
