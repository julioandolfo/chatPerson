<?php
/**
 * Seed: Criar funil padrão com estágios
 */

function seed_default_funnel() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🚀 Criando funil padrão...\n";
    
    // Criar funil padrão
    $funnelData = [
        'name' => 'Funil Principal',
        'description' => 'Funil padrão do sistema',
        'is_default' => true,
        'status' => 'active'
    ];
    
    // Verificar se já existe funil padrão
    $existing = $db->query("SELECT id FROM funnels WHERE is_default = TRUE LIMIT 1")->fetch();
    
    if ($existing) {
        $funnelId = $existing['id'];
        echo "✅ Funil padrão já existe (ID: {$funnelId})\n";
    } else {
        $sql = "INSERT INTO funnels (name, description, is_default, status) 
                VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $funnelData['name'],
            $funnelData['description'],
            $funnelData['is_default'] ? 1 : 0,
            $funnelData['status']
        ]);
        
        $funnelId = $db->lastInsertId();
        echo "✅ Funil padrão criado (ID: {$funnelId})\n";
    }
    
    if (!$funnelId) {
        echo "⚠️  Erro ao criar funil padrão\n";
        return;
    }
    
    echo "✅ Funil padrão criado (ID: {$funnelId})\n";
    
    // Criar estágios padrão
    $stages = [
        ['name' => 'Novo', 'description' => 'Conversas novas', 'position' => 0, 'color' => '#009ef7', 'is_default' => true],
        ['name' => 'Em Andamento', 'description' => 'Conversas em atendimento', 'position' => 1, 'color' => '#ffc700', 'is_default' => false],
        ['name' => 'Aguardando', 'description' => 'Aguardando resposta', 'position' => 2, 'color' => '#7239ea', 'is_default' => false],
        ['name' => 'Resolvido', 'description' => 'Conversas resolvidas', 'position' => 3, 'color' => '#50cd89', 'is_default' => false],
        ['name' => 'Fechado', 'description' => 'Conversas fechadas', 'position' => 4, 'color' => '#a1a5b7', 'is_default' => false],
    ];
    
    // Verificar se já existem estágios
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM funnel_stages WHERE funnel_id = ?");
    $stmt->execute([$funnelId]);
    $existingStages = $stmt->fetch();
    
    if ($existingStages && $existingStages['count'] > 0) {
        echo "✅ Estágios já existem para este funil\n";
    } else {
        foreach ($stages as $stage) {
            $sql = "INSERT INTO funnel_stages (funnel_id, name, description, position, color, is_default) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $funnelId,
                $stage['name'],
                $stage['description'],
                $stage['position'],
                $stage['color'],
                $stage['is_default'] ? 1 : 0
            ]);
            echo "✅ Estágio '{$stage['name']}' criado\n";
        }
    }
    
    echo "✅ Funil padrão criado com sucesso!\n";
}

