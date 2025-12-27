<?php
/**
 * Migration: Sincronizar trigger_config das automações com os dados dos nós trigger
 * 
 * Problema: Automações criadas antes da correção não têm o trigger_config sincronizado
 * Solução: Extrair dados do nó trigger e atualizar trigger_config da automação
 */

function up_sync_trigger_config() {
    global $pdo;
    
    echo "🔄 Sincronizando trigger_config das automações existentes...\n\n";
    
    // Buscar todas as automações com nós trigger
    $sql = "SELECT a.id, a.name, a.trigger_type, an.node_data 
            FROM automations a
            INNER JOIN automation_nodes an ON an.automation_id = a.id
            WHERE an.node_type = 'trigger'";
    
    if (isset($pdo)) {
        $stmt = $pdo->query($sql);
    } else {
        $stmt = \App\Helpers\Database::getInstance()->query($sql);
    }
    
    $automations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($automations)) {
        echo "ℹ️  Nenhuma automação encontrada para sincronizar.\n";
        return;
    }
    
    echo "📊 Total de automações encontradas: " . count($automations) . "\n\n";
    
    $syncCount = 0;
    $skipCount = 0;
    
    foreach ($automations as $auto) {
        $nodeData = json_decode($auto['node_data'], true);
        
        if (!$nodeData) {
            echo "⚠️  Automação #{$auto['id']} ({$auto['name']}): node_data inválido - PULADO\n";
            $skipCount++;
            continue;
        }
        
        // Extrair campos relevantes do node_data
        $triggerConfig = [];
        
        // Canal
        if (isset($nodeData['channel']) && !empty($nodeData['channel'])) {
            $triggerConfig['channel'] = $nodeData['channel'];
        }
        
        // Conta de integração
        if (isset($nodeData['integration_account_id']) && !empty($nodeData['integration_account_id'])) {
            $triggerConfig['integration_account_id'] = $nodeData['integration_account_id'];
        }
        
        // Conta WhatsApp legacy
        if (isset($nodeData['whatsapp_account_id']) && !empty($nodeData['whatsapp_account_id'])) {
            $triggerConfig['whatsapp_account_id'] = $nodeData['whatsapp_account_id'];
        }
        
        // Palavra-chave (message_received)
        if (isset($nodeData['keyword']) && !empty($nodeData['keyword'])) {
            $triggerConfig['keyword'] = $nodeData['keyword'];
        }
        
        // Campo que mudou (conversation_updated)
        if (isset($nodeData['field']) && !empty($nodeData['field'])) {
            $triggerConfig['field'] = $nodeData['field'];
        }
        
        // Estágios (conversation_moved)
        if (isset($nodeData['from_stage_id']) && !empty($nodeData['from_stage_id'])) {
            $triggerConfig['from_stage_id'] = $nodeData['from_stage_id'];
        }
        if (isset($nodeData['to_stage_id']) && !empty($nodeData['to_stage_id'])) {
            $triggerConfig['to_stage_id'] = $nodeData['to_stage_id'];
        }
        
        // Tempo de espera (inactivity)
        if (isset($nodeData['wait_time_value']) && !empty($nodeData['wait_time_value'])) {
            $triggerConfig['wait_time_value'] = $nodeData['wait_time_value'];
            $triggerConfig['wait_time_unit'] = $nodeData['wait_time_unit'] ?? 'minutes';
            $triggerConfig['only_open_conversations'] = $nodeData['only_open_conversations'] ?? true;
        }
        
        // Webhook
        if (isset($nodeData['webhook_url']) && !empty($nodeData['webhook_url'])) {
            $triggerConfig['webhook_url'] = $nodeData['webhook_url'];
        }
        
        // Atualizar trigger_config
        $triggerConfigJson = json_encode($triggerConfig, JSON_UNESCAPED_UNICODE);
        
        $updateSql = "UPDATE automations SET trigger_config = ? WHERE id = ?";
        
        if (isset($pdo)) {
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$triggerConfigJson, $auto['id']]);
        } else {
            $updateStmt = \App\Helpers\Database::getInstance()->prepare($updateSql);
            $updateStmt->execute([$triggerConfigJson, $auto['id']]);
        }
        
        echo "✅ Automação #{$auto['id']} ({$auto['name']}): ";
        if (!empty($triggerConfig)) {
            echo "trigger_config atualizado - " . json_encode($triggerConfig, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "sem filtros específicos (aceita qualquer canal/conta)\n";
        }
        
        $syncCount++;
    }
    
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "✅ Sincronização concluída!\n";
    echo "   - {$syncCount} automações sincronizadas\n";
    if ($skipCount > 0) {
        echo "   - {$skipCount} automações puladas (node_data inválido)\n";
    }
    echo "═══════════════════════════════════════════════════════════\n";
}

function down_sync_trigger_config() {
    echo "⚠️  Não há rollback para esta migration (apenas sincronização de dados).\n";
    echo "   Se necessário, restaure backup do banco de dados.\n";
}

