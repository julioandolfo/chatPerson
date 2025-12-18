<?php
/**
 * Script de correção imediata para stage_order
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $db = \App\Helpers\Database::getInstance();
    $db->beginTransaction();
    
    // Buscar todos os funis
    $sql = "SELECT DISTINCT funnel_id FROM funnel_stages ORDER BY funnel_id";
    $stmt = $db->query($sql);
    $funnelIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totalUpdated = 0;
    $details = [];
    
    foreach ($funnelIds as $funnelId) {
        // Buscar etapas do funil
        // Prioridade: Etapas do sistema primeiro (Entrada, depois outras, Fechadas/Resolvidas e Perdidas por último)
        $sql = "SELECT id, name, is_system_stage, system_stage_type, stage_order 
                FROM funnel_stages 
                WHERE funnel_id = ? 
                ORDER BY 
                    CASE 
                        WHEN system_stage_type = 'entrada' THEN 1
                        WHEN system_stage_type IS NULL THEN 2
                        WHEN system_stage_type = 'fechadas_resolvidas' THEN 3
                        WHEN system_stage_type = 'perdidas' THEN 4
                        ELSE 5
                    END,
                    id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$funnelId]);
        $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $funnelDetails = [];
        
        // Atualizar stage_order para cada etapa
        foreach ($stages as $index => $stage) {
            $newOrder = $index + 1;
            
            $sql = "UPDATE funnel_stages SET stage_order = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$newOrder, $stage['id']]);
            
            $funnelDetails[] = [
                'id' => $stage['id'],
                'name' => $stage['name'],
                'old_order' => $stage['stage_order'],
                'new_order' => $newOrder,
                'is_system' => !empty($stage['is_system_stage'])
            ];
            
            $totalUpdated++;
        }
        
        $details['funnel_' . $funnelId] = $funnelDetails;
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ordem corrigida com sucesso!',
        'total_updated' => $totalUpdated,
        'details' => $details
    ], JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao corrigir: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}

