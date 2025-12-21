<?php
/**
 * Script de Correção: Inicializar e corrigir stage_order
 * 
 * Este script garante que todas as etapas tenham stage_order definido
 * e corrige a ordenação baseada na posição atual no banco.
 */

require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;

$db = Database::getInstance();

echo "<h1>🔧 Correção da Ordenação de Etapas do Kanban</h1>";
echo "<pre>";

try {
    $db->beginTransaction();
    
    echo "📝 Buscando todos os funis...\n\n";
    
    // Buscar todos os funis
    $sql = "SELECT id, name FROM funnels ORDER BY id";
    $funnels = Database::fetchAll($sql);
    
    echo "Encontrados " . count($funnels) . " funis.\n\n";
    
    $totalUpdated = 0;
    
    foreach ($funnels as $funnel) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📁 Funil: {$funnel['name']} (ID: {$funnel['id']})\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Buscar etapas do funil ordenadas por:
        // 1. Etapas do sistema primeiro (is_system_stage = 1)
        // 2. Depois por system_stage_type (entrada=1, null=2, fechadas=998, perdidas=999)
        // 3. Depois por position/id para etapas normais
        $sql = "SELECT id, name, stage_order, position, is_system_stage, system_stage_type 
                FROM funnel_stages 
                WHERE funnel_id = ? 
                ORDER BY 
                    CASE 
                        WHEN is_system_stage = 1 AND system_stage_type = 'entrada' THEN 1
                        WHEN is_system_stage = 0 OR is_system_stage IS NULL THEN 2
                        WHEN is_system_stage = 1 AND system_stage_type = 'fechadas' THEN 998
                        WHEN is_system_stage = 1 AND system_stage_type = 'perdidas' THEN 999
                        ELSE 500
                    END,
                    position ASC,
                    id ASC";
        
        $stages = Database::fetchAll($sql, [$funnel['id']]);
        
        echo "Etapas encontradas: " . count($stages) . "\n\n";
        
        // Atribuir novos valores de stage_order
        $orderCounter = 1;
        
        foreach ($stages as $stage) {
            $newOrder = null;
            
            // Definir ordem especial para etapas do sistema
            if ($stage['is_system_stage'] == 1) {
                switch ($stage['system_stage_type']) {
                    case 'entrada':
                        $newOrder = 1;
                        break;
                    case 'fechadas':
                        $newOrder = 998;
                        break;
                    case 'perdidas':
                        $newOrder = 999;
                        break;
                }
            } else {
                // Etapas normais: começar do 2 e ir incrementando
                // (pular 1 que é reservado para "entrada")
                if ($orderCounter == 1) {
                    $orderCounter = 2;
                }
                while ($orderCounter >= 998) {
                    $orderCounter++; // Pular 998 e 999 (reservados)
                }
                $newOrder = $orderCounter;
                $orderCounter++;
            }
            
            // Atualizar no banco
            if ($stage['stage_order'] != $newOrder) {
                $sql = "UPDATE funnel_stages SET stage_order = ? WHERE id = ?";
                Database::execute($sql, [$newOrder, $stage['id']]);
                
                $systemLabel = $stage['is_system_stage'] == 1 ? " [SISTEMA: {$stage['system_stage_type']}]" : "";
                echo "   ✅ Etapa '{$stage['name']}'$systemLabel\n";
                echo "      ID: {$stage['id']}\n";
                echo "      stage_order: {$stage['stage_order']} → {$newOrder}\n";
                echo "      position: {$stage['position']}\n\n";
                
                $totalUpdated++;
            } else {
                $systemLabel = $stage['is_system_stage'] == 1 ? " [SISTEMA]" : "";
                echo "   ⏭️  Etapa '{$stage['name']}'$systemLabel - Já está correta (stage_order = {$newOrder})\n\n";
            }
        }
        
        echo "\n";
    }
    
    $db->commit();
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ CORREÇÃO CONCLUÍDA!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "📊 Estatísticas:\n";
    echo "   • Funis processados: " . count($funnels) . "\n";
    echo "   • Etapas atualizadas: {$totalUpdated}\n\n";
    
    if ($totalUpdated > 0) {
        echo "🎉 A ordenação das etapas foi corrigida!\n";
        echo "   Agora as setas de ordenar no Kanban devem funcionar corretamente.\n\n";
    } else {
        echo "ℹ️  Todas as etapas já estavam com a ordenação correta.\n\n";
    }
    
    echo "📌 Próximos passos:\n";
    echo "   1. Acesse o Kanban no sistema\n";
    echo "   2. Teste as setas de ordenação (← →)\n";
    echo "   3. A ordem deve mudar e persistir após refresh\n\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<p><a href='javascript:history.back()'>← Voltar</a></p>";

