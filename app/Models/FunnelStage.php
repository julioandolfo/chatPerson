<?php
/**
 * Model FunnelStage
 */

namespace App\Models;

use App\Helpers\Database;

class FunnelStage extends Model
{
    protected string $table = 'funnel_stages';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'funnel_id', 'name', 'description', 'ai_description', 'ai_keywords', 
        'position', 'color', 'is_default',
        'max_conversations', 'allow_move_back', 'allow_skip_stages',
        'blocked_stages', 'required_stages', 'required_tags', 'blocked_tags',
        'auto_assign', 'auto_assign_department_id', 'auto_assign_method',
        'sla_hours', 'settings', 'stage_order', 'is_system_stage', 'system_stage_type'
    ];
    protected bool $timestamps = true;

    /**
     * Obter estágio padrão do funil
     */
    public static function getDefault(int $funnelId): ?array
    {
        $sql = "SELECT * FROM funnel_stages WHERE funnel_id = ? AND is_default = TRUE LIMIT 1";
        return Database::fetch($sql, [$funnelId]);
    }

    /**
     * Reordenar estágios
     */
    public static function reorder(int $funnelId, array $stageIds): bool
    {
        try {
            $db = Database::getInstance();
            $db->beginTransaction();
            
            // Atualizar AMBOS position e stage_order para manter sincronizados
            foreach ($stageIds as $index => $stageId) {
                $newOrder = $index + 1; // Começar em 1
                $sql = "UPDATE funnel_stages 
                        SET position = ?, stage_order = ? 
                        WHERE id = ? AND funnel_id = ?";
                Database::execute($sql, [$newOrder, $newOrder, $stageId, $funnelId]);
                
                error_log("Atualizando etapa ID $stageId: position=$newOrder, stage_order=$newOrder");
            }
            
            $db->commit();
            error_log("✅ Etapas reordenadas com sucesso!");
            return true;
        } catch (\Exception $e) {
            error_log("❌ Erro ao reordenar etapas: " . $e->getMessage());
            Database::getInstance()->rollBack();
            return false;
        }
    }

    /**
     * Mover conversa para estágio
     */
    public static function moveConversation(int $conversationId, int $stageId): bool
    {
        $stage = self::find($stageId);
        if (!$stage) {
            return false;
        }
        
        return \App\Models\Conversation::update($conversationId, [
            'funnel_id' => $stage['funnel_id'],
            'funnel_stage_id' => $stageId
        ]);
    }

    /**
     * Buscar etapa do sistema por tipo e funil
     * @param int $funnelId ID do funil
     * @param string $type Tipo: entrada, fechadas, perdidas
     * @return array|null Etapa encontrada ou null
     */
    public static function getSystemStage(int $funnelId, string $type): ?array
    {
        $sql = "SELECT * FROM funnel_stages 
                WHERE funnel_id = ? 
                AND is_system_stage = 1 
                AND system_stage_type = ? 
                LIMIT 1";
        return Database::fetch($sql, [$funnelId, $type]);
    }

    /**
     * Buscar todas as etapas do sistema de um funil
     * @param int $funnelId ID do funil
     * @return array Array com as 3 etapas do sistema
     */
    public static function getSystemStages(int $funnelId): array
    {
        $sql = "SELECT * FROM funnel_stages 
                WHERE funnel_id = ? 
                AND is_system_stage = 1 
                ORDER BY stage_order ASC";
        return Database::fetchAll($sql, [$funnelId]);
    }

    /**
     * Verificar se uma etapa é do sistema (não pode ser deletada/renomeada)
     * @param int $stageId ID da etapa
     * @return bool True se for etapa do sistema
     */
    public static function isSystemStage(int $stageId): bool
    {
        $stage = self::find($stageId);
        return !empty($stage['is_system_stage']);
    }
}

