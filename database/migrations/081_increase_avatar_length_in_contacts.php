<?php
/**
 * Migration: Aumentar tamanho da coluna avatar na tabela contacts
 * URLs de avatares de redes sociais (Instagram, Facebook) podem ser muito longas
 */

function up_increase_avatar_length_in_contacts() {
    $db = \App\Helpers\Database::getInstance();
    
    // Alterar de VARCHAR(255) para TEXT para suportar URLs longas
    $sql = "ALTER TABLE contacts MODIFY COLUMN avatar TEXT NULL";
    
    $db->exec($sql);
    echo "✅ Coluna 'avatar' alterada para TEXT na tabela 'contacts'!\n";
}

function down_increase_avatar_length_in_contacts() {
    $db = \App\Helpers\Database::getInstance();
    
    // Reverter para VARCHAR(255)
    $sql = "ALTER TABLE contacts MODIFY COLUMN avatar VARCHAR(255) NULL";
    
    $db->exec($sql);
    echo "✅ Coluna 'avatar' revertida para VARCHAR(255) na tabela 'contacts'!\n";
}

