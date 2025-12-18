<?php
/**
 * Migration: Inicializar stage_order nas etapas
 * 
 * Esta migration garante que todas as etapas tenham valores de stage_order definidos
 */

function up_initialize_stage_order() {
    global $pdo;
    if (!isset($pdo)) {
        $pdo = \App\Helpers\Database::getInstance();
    }
    
    echo "📝 Inicializando stage_order para todas as etapas...\n";
    
    try {
        // Buscar todos os funis
        $sql = "SELECT DISTINCT funnel_id FROM funnel_stages ORDER BY funnel_id";
        $stmt = $pdo->query($sql);
        $funnelIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "   Encontrados " . count($funnelIds) . " funis...\n";
        
        $totalUpdated = 0;
        
        foreach ($funnelIds as $funnelId) {
            // Buscar etapas do funil ordenadas por ID (ordem de criação)
            $sql = "SELECT id, name, stage_order FROM funnel_stages WHERE funnel_id = ? ORDER BY id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$funnelId]);
            $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "   Funil $funnelId: " . count($stages) . " etapas\n";
            
            // Atualizar stage_order para cada etapa
            foreach ($stages as $index => $stage) {
                $newOrder = $index + 1;
                
                // Só atualizar se for NULL ou diferente do esperado
                if ($stage['stage_order'] === null || $stage['stage_order'] != $newOrder) {
                    $sql = "UPDATE funnel_stages SET stage_order = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$newOrder, $stage['id']]);
                    
                    echo "      ✓ Etapa '{$stage['name']}' (ID: {$stage['id']}): stage_order = {$newOrder}\n";
                    $totalUpdated++;
                }
            }
        }
        
        echo "✅ Migration 061 concluída! Total atualizado: {$totalUpdated} etapas\n\n";
        
    } catch (\Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_initialize_stage_order() {
    // Não há down para esta migration pois não queremos remover os valores
    echo "⚠️  Não há rollback para esta migration (valores de stage_order mantidos)\n";
}

