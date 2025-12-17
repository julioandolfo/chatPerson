<?php
/**
 * Migration: Criar funil e etapa padrão do sistema
 * 
 * Cria um funil "Funil Entrada" e uma etapa "Nova Entrada" que serão
 * usados como padrão quando não houver automação ou configuração específica.
 */

require_once __DIR__ . '/../../app/Helpers/Database.php';

function up_create_default_funnel_and_stage() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Verificar se já existe um funil padrão
        $existingFunnel = $db->query("SELECT id FROM funnels WHERE is_default = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingFunnel) {
            // Criar Funil Padrão
            $stmt = $db->prepare("
                INSERT INTO funnels (name, description, status, is_default, color, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                'Funil Entrada',
                'Funil padrão do sistema. Todas as conversas sem configuração específica iniciam aqui.',
                'active', // status
                1, // is_default
                '#3F4254' // cor padrão
            ]);
            
            $funnelId = $db->lastInsertId();
            echo "✅ Funil padrão 'Funil Entrada' criado com ID: {$funnelId}\n";
            
            // Criar Etapa Padrão
            $stmt = $db->prepare("
                INSERT INTO funnel_stages (
                    funnel_id, 
                    name, 
                    description, 
                    color, 
                    position, 
                    is_default,
                    allow_move_back,
                    allow_skip_stages,
                    created_at, 
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $funnelId,
                'Nova Entrada',
                'Etapa padrão para novas conversas sem configuração específica.',
                '#3F4254', // cor cinza escuro
                1, // position
                1, // is_default
                1, // allow_move_back (TRUE)
                1, // allow_skip_stages (TRUE)
            ]);
            
            $stageId = $db->lastInsertId();
            echo "✅ Etapa padrão 'Nova Entrada' criada com ID: {$stageId}\n";
            
            // Criar configuração do sistema para armazenar IDs padrão
            $stmt = $db->prepare("
                INSERT INTO settings (`key`, `value`, `type`, `group`, label, description, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()
            ");
            
            $defaultConfig = json_encode([
                'funnel_id' => $funnelId,
                'stage_id' => $stageId
            ]);
            
            $stmt->execute([
                'system_default_funnel_stage',
                $defaultConfig,
                'json',
                'system',
                'Funil e Etapa Padrão do Sistema',
                'Funil e etapa usados como padrão quando não há configuração específica'
            ]);
            
            echo "✅ Configuração padrão salva em settings\n";
        } else {
            echo "ℹ️ Funil padrão já existe (ID: {$existingFunnel['id']})\n";
        }
        
        echo "✅ Migration 030_create_default_funnel_and_stage executada com sucesso!\n";
        
    } catch (PDOException $e) {
        echo "❌ Erro ao criar funil e etapa padrão: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_create_default_funnel_and_stage() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Buscar funil padrão
        $funnel = $db->query("SELECT id FROM funnels WHERE is_default = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if ($funnel) {
            // Remover configuração
            $db->exec("DELETE FROM settings WHERE `key` = 'system_default_funnel_stage'");
            
            // Remover etapas do funil
            $stmt = $db->prepare("DELETE FROM funnel_stages WHERE funnel_id = ?");
            $stmt->execute([$funnel['id']]);
            
            // Remover funil
            $stmt = $db->prepare("DELETE FROM funnels WHERE id = ?");
            $stmt->execute([$funnel['id']]);
            
            echo "✅ Funil e etapa padrão removidos\n";
        }
        
        echo "✅ Rollback da migration 030 executado com sucesso!\n";
        
    } catch (PDOException $e) {
        echo "❌ Erro ao reverter migration: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Executar se chamado diretamente
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'] ?? '')) {
    require_once __DIR__ . '/../../config/database.php';
    up_create_default_funnel_and_stage();
}

