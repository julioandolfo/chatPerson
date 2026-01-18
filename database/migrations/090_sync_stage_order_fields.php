<?php
/**
 * Migração: Sincronizar campos stage_order e position
 * 
 * Problema: O sistema tinha dois campos de ordenação (stage_order e position)
 * que não estavam sincronizados, causando problemas na ordenação das etapas.
 * 
 * Solução: Esta migração sincroniza ambos os campos para todas as etapas existentes.
 */

use App\Helpers\Database;

return new class {
    public function up(): void
    {
        echo "🔧 Sincronizando campos de ordenação das etapas...\n";
        
        $db = Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Buscar todos os funis
            $funnels = Database::fetchAll("SELECT id, name FROM funnels WHERE status = 'active'");
            
            echo "📊 Encontrados " . count($funnels) . " funis ativos\n";
            
            foreach ($funnels as $funnel) {
                echo "\n🔹 Processando funil: {$funnel['name']} (ID: {$funnel['id']})\n";
                
                // Buscar etapas do funil ordenadas por stage_order, position e id
                $sql = "SELECT id, name, stage_order, position 
                        FROM funnel_stages 
                        WHERE funnel_id = ? 
                        ORDER BY 
                            COALESCE(stage_order, 999999) ASC,
                            COALESCE(position, 999999) ASC,
                            id ASC";
                
                $stages = Database::fetchAll($sql, [$funnel['id']]);
                
                echo "   └─ Encontradas " . count($stages) . " etapas\n";
                
                // Atualizar cada etapa com ordem sequencial
                foreach ($stages as $index => $stage) {
                    $newOrder = $index + 1;
                    
                    $updateSql = "UPDATE funnel_stages 
                                  SET position = ?, stage_order = ? 
                                  WHERE id = ?";
                    
                    Database::execute($updateSql, [$newOrder, $newOrder, $stage['id']]);
                    
                    echo "      ✓ {$stage['name']}: position={$newOrder}, stage_order={$newOrder}\n";
                }
            }
            
            $db->commit();
            
            echo "\n✅ Sincronização concluída com sucesso!\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
        } catch (\Exception $e) {
            $db->rollBack();
            echo "\n❌ Erro durante a sincronização: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    public function down(): void
    {
        echo "⚠️ Esta migração não possui rollback (down).\n";
        echo "Os campos stage_order e position permanecerão sincronizados.\n";
    }
};
