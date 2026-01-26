<?php
/**
 * Model Automation
 */

namespace App\Models;

use App\Helpers\Database;

class Automation extends Model
{
    protected string $table = 'automations';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'description', 'trigger_type', 'trigger_config', 'funnel_id', 'stage_id', 'status', 'is_active'];
    protected bool $timestamps = true;

    /**
     * Obter nós da automação
     */
    public static function getNodes(int $automationId): array
    {
        $sql = "SELECT * FROM automation_nodes 
                WHERE automation_id = ? 
                ORDER BY position_y ASC, position_x ASC";
        $nodes = Database::fetchAll($sql, [$automationId]);
        
        // Debug: verificar se os nós foram encontrados
        \App\Helpers\Logger::automation("getNodes - Automation ID: {$automationId}, Nós encontrados: " . count($nodes));
        if (!empty($nodes)) {
            \App\Helpers\Logger::automation("getNodes - Primeiro nó: " . json_encode($nodes[0]));
        }
        
        return $nodes;
    }

    /**
     * Obter automação com nós
     */
    public static function findWithNodes(int $automationId): ?array
    {
        $automation = self::find($automationId);
        if (!$automation) {
            return null;
        }
        
        $automation['nodes'] = self::getNodes($automationId);
        
        // Decodificar JSONs
        if (!empty($automation['trigger_config'])) {
            $automation['trigger_config'] = json_decode($automation['trigger_config'], true);
        }
        
        foreach ($automation['nodes'] as &$node) {
            // Garantir que o ID seja um inteiro
            if (isset($node['id'])) {
                $node['id'] = (int)$node['id'];
            }
            // Garantir que position_x e position_y sejam inteiros
            if (isset($node['position_x'])) {
                $node['position_x'] = (int)$node['position_x'];
            }
            if (isset($node['position_y'])) {
                $node['position_y'] = (int)$node['position_y'];
            }
            if (!empty($node['node_data'])) {
                $node['node_data'] = json_decode($node['node_data'], true);
            }
        }
        
        return $automation;
    }

    /**
     * Obter automações ativas por trigger
     */
    public static function getActiveByTrigger(string $triggerType, array $triggerData = [], ?int $funnelId = null, ?int $stageId = null): array
    {
        error_log("🔥🔥🔥 getActiveByTrigger CHAMADO! triggerType={$triggerType}, triggerData=" . json_encode($triggerData));
        \App\Helpers\Logger::automation("🔥 === Automation::getActiveByTrigger INÍCIO ===");
        \App\Helpers\Logger::automation("🔥 Parâmetros: triggerType={$triggerType}, funnelId={$funnelId}, stageId={$stageId}, triggerData=" . json_encode($triggerData));
        
        $sql = "SELECT a.*, f.name as funnel_name, fs.name as stage_name
                FROM automations a
                LEFT JOIN funnels f ON a.funnel_id = f.id
                LEFT JOIN funnel_stages fs ON a.stage_id = fs.id
                WHERE a.trigger_type = ? AND a.status = 'active' AND a.is_active = TRUE";
        
        $params = [$triggerType];
        
        // Filtrar por funil se fornecido
        if ($funnelId !== null) {
            $sql .= " AND (a.funnel_id = ? OR a.funnel_id IS NULL)";
            $params[] = $funnelId;
        }
        
        // Filtrar por estágio se fornecido
        if ($stageId !== null) {
            $sql .= " AND (a.stage_id = ? OR a.stage_id IS NULL)";
            $params[] = $stageId;
        }
        
        \App\Helpers\Logger::automation("SQL: {$sql}");
        \App\Helpers\Logger::automation("Params: " . json_encode($params));
        
        $automations = Database::fetchAll($sql, $params);
        
        \App\Helpers\Logger::automation("Automações encontradas no banco: " . count($automations));
        
        if (!empty($automations)) {
            foreach ($automations as $idx => $auto) {
                \App\Helpers\Logger::automation("  [{$idx}] ID: {$auto['id']}, Nome: {$auto['name']}, Funil: {$auto['funnel_id']}, Estágio: {$auto['stage_id']}, Trigger Config: {$auto['trigger_config']}");
            }
        }
        
        // Filtrar por trigger_config se fornecido
        if (!empty($triggerData)) {
            \App\Helpers\Logger::automation("Aplicando filtro de trigger_config...");
            $filtered = [];
            foreach ($automations as $automation) {
                // Evitar warning de json_decode com null/empty e tratar erro de decode
                $configRaw = $automation['trigger_config'] ?? '';
                if ($configRaw === null || $configRaw === '') {
                    $config = [];
                } else {
                    $config = json_decode($configRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log('Automation::getActiveByTrigger - JSON inválido em trigger_config: ' . json_last_error_msg());
                        \App\Helpers\Logger::automation("JSON inválido em trigger_config para automação ID {$automation['id']}: " . json_last_error_msg());
                        $config = [];
                    }
                }

                $matches = self::matchesTriggerConfig($config, $triggerData);
                \App\Helpers\Logger::automation("  Automação ID {$automation['id']}: Config=" . json_encode($config) . ", Matches=" . ($matches ? 'SIM' : 'NÃO'));
                
                if ($matches) {
                    $filtered[] = $automation;
                }
            }
            \App\Helpers\Logger::automation("Após filtro trigger_config: " . count($filtered) . " automações");
            return $filtered;
        }
        
        \App\Helpers\Logger::automation("=== Automation::getActiveByTrigger FIM === Retornando " . count($automations) . " automações");
        return $automations;
    }

    /**
     * Verificar se configuração do trigger corresponde aos dados
     */
    private static function matchesTriggerConfig(?array $config, array $data): bool
    {
        if (empty($config)) {
            \App\Helpers\Logger::automation("  matchesTriggerConfig: Config vazio, aceitando");
            return true; // Sem filtros específicos
        }
        
        \App\Helpers\Logger::automation("  matchesTriggerConfig: Verificando config=" . json_encode($config) . " contra data=" . json_encode($data));
        
        // Verificar contas de integração (suporte a arrays)
        $accountMatched = self::matchesAccountConfig($config, $data);
        if (!$accountMatched) {
            return false;
        }
        
        foreach ($config as $key => $value) {
            // Pular chaves de contas que já foram verificadas
            if (in_array($key, ['whatsapp_account_id', 'integration_account_id', 'whatsapp_account_ids', 'integration_account_ids'])) {
                continue;
            }
            
            // Se o filtro é por canal e está vazio, aceitar qualquer canal
            if ($key === 'channel' && empty($value)) {
                \App\Helpers\Logger::automation("    ✓ Canal vazio, aceitando qualquer canal");
                continue;
            }
            
            // Verificar correspondência exata
            if (!isset($data[$key])) {
                \App\Helpers\Logger::automation("    ✗ Chave '{$key}' não existe nos dados - REJEITADO");
                return false;
            }
            
            // Comparação normal (inclui canal)
            if ($data[$key] != $value) {
                \App\Helpers\Logger::automation("    ✗ Campo '{$key}' não corresponde: esperado='{$value}', recebido='{$data[$key]}' - REJEITADO");
                return false;
            }
            \App\Helpers\Logger::automation("    ✓ Campo '{$key}' corresponde: '{$value}'");
        }
        
        \App\Helpers\Logger::automation("  matchesTriggerConfig: TODOS os critérios atendidos - ACEITO");
        return true;
    }
    
    /**
     * Verificar se a conta da conversa corresponde às contas configuradas no trigger
     * Suporta múltiplas contas (arrays) e valores únicos (legado)
     */
    private static function matchesAccountConfig(array $config, array $data): bool
    {
        // Obter contas de integração configuradas (array ou valor único)
        $configIntegrationIds = [];
        if (!empty($config['integration_account_ids']) && is_array($config['integration_account_ids'])) {
            $configIntegrationIds = $config['integration_account_ids'];
        } elseif (!empty($config['integration_account_id'])) {
            $configIntegrationIds = [$config['integration_account_id']];
        }
        
        // Obter contas WhatsApp configuradas (array ou valor único)
        $configWhatsappIds = [];
        if (!empty($config['whatsapp_account_ids']) && is_array($config['whatsapp_account_ids'])) {
            $configWhatsappIds = $config['whatsapp_account_ids'];
        } elseif (!empty($config['whatsapp_account_id'])) {
            $configWhatsappIds = [$config['whatsapp_account_id']];
        }
        
        // Se nenhuma conta está configurada, aceitar qualquer conta
        if (empty($configIntegrationIds) && empty($configWhatsappIds)) {
            \App\Helpers\Logger::automation("    ✓ Nenhuma conta configurada, aceitando qualquer conta");
            return true;
        }
        
        // Obter conta da conversa
        $dataIntegrationId = $data['integration_account_id'] ?? null;
        $dataWhatsappId = $data['whatsapp_account_id'] ?? null;
        
        // Verificar se a conta de integração da conversa está nas contas configuradas
        if (!empty($configIntegrationIds) && !empty($dataIntegrationId)) {
            // Converter para strings para comparação consistente
            $configIntegrationIdsStr = array_map('strval', $configIntegrationIds);
            $dataIntegrationIdStr = strval($dataIntegrationId);
            
            if (in_array($dataIntegrationIdStr, $configIntegrationIdsStr)) {
                \App\Helpers\Logger::automation("    ✓ Conta de integração {$dataIntegrationId} está na lista configurada: " . json_encode($configIntegrationIds));
                return true;
            }
        }
        
        // Verificar se a conta WhatsApp da conversa está nas contas configuradas
        if (!empty($configWhatsappIds) && !empty($dataWhatsappId)) {
            // Converter para strings para comparação consistente
            $configWhatsappIdsStr = array_map('strval', $configWhatsappIds);
            $dataWhatsappIdStr = strval($dataWhatsappId);
            
            if (in_array($dataWhatsappIdStr, $configWhatsappIdsStr)) {
                \App\Helpers\Logger::automation("    ✓ Conta WhatsApp {$dataWhatsappId} está na lista configurada: " . json_encode($configWhatsappIds));
                return true;
            }
        }
        
        // Verificar correspondência cruzada (WhatsApp ID pode estar em integration_account_ids via migração)
        if (!empty($configIntegrationIds) && !empty($dataWhatsappId)) {
            // Buscar integration_account correspondente ao whatsapp_account
            $integrationAccount = \App\Helpers\Database::fetch(
                "SELECT ia.id FROM integration_accounts ia 
                 JOIN whatsapp_accounts wa ON ia.phone_number = wa.phone_number 
                 WHERE wa.id = ?",
                [$dataWhatsappId]
            );
            
            if ($integrationAccount && in_array(strval($integrationAccount['id']), array_map('strval', $configIntegrationIds))) {
                \App\Helpers\Logger::automation("    ✓ Conta WhatsApp {$dataWhatsappId} corresponde a integration_account {$integrationAccount['id']} na lista configurada");
                return true;
            }
        }
        
        // Nenhuma correspondência encontrada
        \App\Helpers\Logger::automation("    ✗ Conta não corresponde. Config: integration_ids=" . json_encode($configIntegrationIds) . ", whatsapp_ids=" . json_encode($configWhatsappIds) . " | Data: integration_id={$dataIntegrationId}, whatsapp_id={$dataWhatsappId} - REJEITADO");
        return false;
    }
}

