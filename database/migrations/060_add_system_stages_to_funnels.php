<?php

/**
 * Migration: Adicionar etapas obrigatórias do sistema em todos os funis
 * 
 * Etapas obrigatórias (não podem ser deletadas/renomeadas):
 * 1. Entrada - Etapa inicial para novas conversas ou reaberturas
 * 2. Fechadas / Resolvidas - Para conversas fechadas/resolvidas
 * 3. Perdidas - Para conversas perdidas
 * 
 * Data: 2025-01-17
 */

function up_add_system_stages_to_funnels() {
    $db = \App\Helpers\Database::getInstance();
    
    echo "=== Adicionando Sistema de Etapas Obrigatórias ===\n\n";
    
    // 1. Adicionar campo is_system_stage na tabela funnel_stages
    echo "1. Adicionando campo is_system_stage...\n";
    
    // Verificar se coluna já existe
    $sql = "SHOW COLUMNS FROM funnel_stages LIKE 'is_system_stage'";
    $stmt = $db->query($sql);
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        $sql = "ALTER TABLE funnel_stages 
                ADD COLUMN is_system_stage TINYINT(1) DEFAULT 0 
                COMMENT 'Etapa do sistema (não pode ser deletada/renomeada)'";
        $db->exec($sql);
        echo "   ✅ Campo is_system_stage adicionado!\n\n";
    } else {
        echo "   ℹ️  Campo is_system_stage já existe!\n\n";
    }
    
    // 1.5. Adicionar campo stage_order (usado para ordenação fixa das etapas do sistema)
    echo "1.5. Adicionando campo stage_order...\n";
    
    $sql = "SHOW COLUMNS FROM funnel_stages LIKE 'stage_order'";
    $stmt = $db->query($sql);
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        $sql = "ALTER TABLE funnel_stages 
                ADD COLUMN stage_order INT DEFAULT 0 
                COMMENT 'Ordem fixa (1=Entrada, 998=Fechadas, 999=Perdidas)'";
        $db->exec($sql);
        echo "   ✅ Campo stage_order adicionado!\n\n";
    } else {
        echo "   ℹ️  Campo stage_order já existe!\n\n";
    }
    
    // 2. Adicionar campo system_stage_type para identificar tipo de etapa do sistema
    echo "2. Adicionando campo system_stage_type...\n";
    
    // Verificar se coluna já existe
    $sql = "SHOW COLUMNS FROM funnel_stages LIKE 'system_stage_type'";
    $stmt = $db->query($sql);
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        $sql = "ALTER TABLE funnel_stages 
                ADD COLUMN system_stage_type VARCHAR(50) NULL 
                COMMENT 'Tipo da etapa do sistema: entrada, fechadas, perdidas' 
                AFTER is_system_stage";
        $db->exec($sql);
        echo "   ✅ Campo system_stage_type adicionado!\n\n";
    } else {
        echo "   ℹ️  Campo system_stage_type já existe!\n\n";
    }
    
    // 3. Buscar todos os funis existentes
    echo "3. Buscando funis existentes...\n";
    $sql = "SELECT id, name FROM funnels ORDER BY id";
    $stmt = $db->query($sql);
    $funnels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   📊 Encontrados " . count($funnels) . " funil(is)\n\n";
    
    // 4. Para cada funil, criar as 3 etapas obrigatórias (se não existirem)
    foreach ($funnels as $funnel) {
        echo "4. Processando funil: {$funnel['name']} (ID: {$funnel['id']})\n";
        
        // Verificar etapas existentes
        $sql = "SELECT id, name, system_stage_type FROM funnel_stages WHERE funnel_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$funnel['id']]);
        $existingStages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasEntrada = false;
        $hasFechadas = false;
        $hasPerdidas = false;
        
        foreach ($existingStages as $stage) {
            if ($stage['system_stage_type'] === 'entrada') $hasEntrada = true;
            if ($stage['system_stage_type'] === 'fechadas') $hasFechadas = true;
            if ($stage['system_stage_type'] === 'perdidas') $hasPerdidas = true;
        }
        
        // Criar etapas faltantes
        $createdCount = 0;
        
        // 4.1. Etapa "Entrada" (sempre stage_order = 1)
        if (!$hasEntrada) {
            echo "   → Criando etapa 'Entrada'...\n";
            $sql = "INSERT INTO funnel_stages 
                    (funnel_id, name, description, color, stage_order, is_system_stage, system_stage_type, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $funnel['id'],
                'Entrada',
                'Etapa inicial do funil. Novas conversas e reaberturas entram aqui.',
                '#3b82f6', // Azul
                1, // Sempre primeiro
                1, // is_system_stage
                'entrada' // system_stage_type
            ]);
            $createdCount++;
        }
        
        // 4.2. Etapa "Fechadas / Resolvidas" (stage_order = 998)
        if (!$hasFechadas) {
            echo "   → Criando etapa 'Fechadas / Resolvidas'...\n";
            $sql = "INSERT INTO funnel_stages 
                    (funnel_id, name, description, color, stage_order, is_system_stage, system_stage_type, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $funnel['id'],
                'Fechadas / Resolvidas',
                'Conversas fechadas ou resolvidas. Reabrem para "Entrada" após período de graça.',
                '#22c55e', // Verde
                998, // Penúltima
                1, // is_system_stage
                'fechadas' // system_stage_type
            ]);
            $createdCount++;
        }
        
        // 4.3. Etapa "Perdidas" (stage_order = 999)
        if (!$hasPerdidas) {
            echo "   → Criando etapa 'Perdidas'...\n";
            $sql = "INSERT INTO funnel_stages 
                    (funnel_id, name, description, color, stage_order, is_system_stage, system_stage_type, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $funnel['id'],
                'Perdidas',
                'Conversas perdidas ou descartadas. Não reabrem automaticamente.',
                '#ef4444', // Vermelho
                999, // Última
                1, // is_system_stage
                'perdidas' // system_stage_type
            ]);
            $createdCount++;
        }
        
        if ($createdCount > 0) {
            echo "   ✅ {$createdCount} etapa(s) criada(s)!\n";
        } else {
            echo "   ℹ️  Etapas do sistema já existem\n";
        }
        echo "\n";
    }
    
    // 5. Criar índice para system_stage_type
    echo "5. Criando índice para system_stage_type...\n";
    
    // Verificar se índice já existe
    $sql = "SHOW INDEX FROM funnel_stages WHERE Key_name = 'idx_funnel_stages_system_type'";
    $stmt = $db->query($sql);
    $indexExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$indexExists) {
        $sql = "CREATE INDEX idx_funnel_stages_system_type 
                ON funnel_stages(funnel_id, system_stage_type)";
        $db->exec($sql);
        echo "   ✅ Índice criado!\n\n";
    } else {
        echo "   ℹ️  Índice já existe!\n\n";
    }
    
    echo "=== Migration Concluída com Sucesso! ===\n";
}

function down_add_system_stages_to_funnels() {
    $db = \App\Helpers\Database::getInstance();
    
    echo "=== Revertendo Sistema de Etapas Obrigatórias ===\n\n";
    
    // 1. Remover etapas do sistema
    echo "1. Removendo etapas do sistema...\n";
    $sql = "DELETE FROM funnel_stages WHERE is_system_stage = 1";
    $db->exec($sql);
    echo "   ✅ Etapas do sistema removidas!\n\n";
    
    // 2. Remover índice
    echo "2. Removendo índice...\n";
    
    // Verificar se índice existe
    $sql = "SHOW INDEX FROM funnel_stages WHERE Key_name = 'idx_funnel_stages_system_type'";
    $stmt = $db->query($sql);
    $indexExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($indexExists) {
        $sql = "DROP INDEX idx_funnel_stages_system_type ON funnel_stages";
        $db->exec($sql);
        echo "   ✅ Índice removido!\n\n";
    } else {
        echo "   ℹ️  Índice não existe!\n\n";
    }
    
    // 3. Remover campos
    echo "3. Removendo campos...\n";
    
    // Verificar e remover system_stage_type
    $sql = "SHOW COLUMNS FROM funnel_stages LIKE 'system_stage_type'";
    $stmt = $db->query($sql);
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($columnExists) {
        $sql = "ALTER TABLE funnel_stages DROP COLUMN system_stage_type";
        $db->exec($sql);
        echo "   ✅ Campo system_stage_type removido!\n";
    } else {
        echo "   ℹ️  Campo system_stage_type não existe!\n";
    }
    
    // Verificar e remover is_system_stage
    $sql = "SHOW COLUMNS FROM funnel_stages LIKE 'is_system_stage'";
    $stmt = $db->query($sql);
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($columnExists) {
        $sql = "ALTER TABLE funnel_stages DROP COLUMN is_system_stage";
        $db->exec($sql);
        echo "   ✅ Campo is_system_stage removido!\n";
    } else {
        echo "   ℹ️  Campo is_system_stage não existe!\n";
    }
    
    // Verificar e remover stage_order
    $sql = "SHOW COLUMNS FROM funnel_stages LIKE 'stage_order'";
    $stmt = $db->query($sql);
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($columnExists) {
        $sql = "ALTER TABLE funnel_stages DROP COLUMN stage_order";
        $db->exec($sql);
        echo "   ✅ Campo stage_order removido!\n\n";
    } else {
        echo "   ℹ️  Campo stage_order não existe!\n\n";
    }
    
    echo "=== Rollback Concluído! ===\n";
}

