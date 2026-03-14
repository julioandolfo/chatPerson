<?php
/**
 * Migration: Adicionar campos para round-robin de mensagens em campanhas
 */

function up_145_add_round_robin_to_campaigns(): void
{
    $db = \App\Helpers\Database::getInstance();

    echo "Adicionando campos de round-robin em campaigns...\n";

    $db->exec("ALTER TABLE campaigns
               ADD COLUMN IF NOT EXISTS round_robin_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER winning_variant,
               ADD COLUMN IF NOT EXISTS round_robin_current_index INT NOT NULL DEFAULT 0 AFTER round_robin_enabled");

    echo "✅ Campos round_robin_enabled e round_robin_current_index adicionados em campaigns\n";

    echo "Adicionando campos variant_type, message_type e message_variables em campaign_variants...\n";

    $db->exec("ALTER TABLE campaign_variants
               ADD COLUMN IF NOT EXISTS variant_type ENUM('ab_test', 'round_robin') NOT NULL DEFAULT 'ab_test' AFTER reply_rate,
               ADD COLUMN IF NOT EXISTS message_type ENUM('text', 'template') NOT NULL DEFAULT 'text' AFTER variant_type,
               ADD COLUMN IF NOT EXISTS message_variables TEXT NULL AFTER message_type");

    echo "✅ Campos adicionados em campaign_variants\n";
}

function down_145_add_round_robin_to_campaigns(): void
{
    $db = \App\Helpers\Database::getInstance();

    $db->exec("ALTER TABLE campaigns
               DROP COLUMN IF EXISTS round_robin_enabled,
               DROP COLUMN IF EXISTS round_robin_current_index");

    $db->exec("ALTER TABLE campaign_variants
               DROP COLUMN IF EXISTS variant_type,
               DROP COLUMN IF EXISTS message_type,
               DROP COLUMN IF EXISTS message_variables");

    echo "✅ Campos de round-robin removidos\n";
}
