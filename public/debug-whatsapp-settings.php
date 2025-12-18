<?php
/**
 * Debug: Verificar configurações de funil/etapa das integrações WhatsApp
 */

require_once __DIR__ . '/../app/Helpers/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Debug: Configurações WhatsApp</h1>";

try {
    $db = \App\Helpers\Database::getInstance();
    
    // Verificar estrutura da tabela
    echo "<h2>1. Estrutura da Tabela whatsapp_accounts</h2>";
    $sql = "SHOW COLUMNS FROM whatsapp_accounts";
    $stmt = $db->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar se colunas existem
    $hasDefaultFunnel = false;
    $hasDefaultStage = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'default_funnel_id') $hasDefaultFunnel = true;
        if ($col['Field'] === 'default_stage_id') $hasDefaultStage = true;
    }
    
    echo "<p><strong>default_funnel_id:</strong> " . ($hasDefaultFunnel ? '✅ Existe' : '❌ NÃO existe') . "</p>";
    echo "<p><strong>default_stage_id:</strong> " . ($hasDefaultStage ? '✅ Existe' : '❌ NÃO existe') . "</p>";
    
    // Listar contas WhatsApp
    echo "<h2>2. Contas WhatsApp e Configurações</h2>";
    $sql = "SELECT 
                wa.id,
                wa.name,
                wa.phone_number,
                wa.default_funnel_id,
                wa.default_stage_id,
                f.name as funnel_name,
                fs.name as stage_name
            FROM whatsapp_accounts wa
            LEFT JOIN funnels f ON wa.default_funnel_id = f.id
            LEFT JOIN funnel_stages fs ON wa.default_stage_id = fs.id
            ORDER BY wa.id";
    $stmt = $db->query($sql);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($accounts)) {
        echo "<p>❌ Nenhuma conta WhatsApp encontrada!</p>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>ID</th><th>Nome</th><th>Telefone</th>";
        echo "<th>default_funnel_id</th><th>Funil</th>";
        echo "<th>default_stage_id</th><th>Etapa</th>";
        echo "<th>Status</th>";
        echo "</tr>";
        
        foreach ($accounts as $acc) {
            $status = '';
            if (empty($acc['default_funnel_id']) && empty($acc['default_stage_id'])) {
                $status = '⚠️ Sem configuração';
            } elseif (!empty($acc['default_funnel_id']) && !empty($acc['default_stage_id'])) {
                $status = '✅ Configurado';
            } else {
                $status = '⚠️ Configuração parcial';
            }
            
            echo "<tr>";
            echo "<td>" . $acc['id'] . "</td>";
            echo "<td>" . htmlspecialchars($acc['name']) . "</td>";
            echo "<td>" . htmlspecialchars($acc['phone_number']) . "</td>";
            echo "<td>" . ($acc['default_funnel_id'] ?? '<em>NULL</em>') . "</td>";
            echo "<td>" . htmlspecialchars($acc['funnel_name'] ?? '-') . "</td>";
            echo "<td>" . ($acc['default_stage_id'] ?? '<em>NULL</em>') . "</td>";
            echo "<td>" . htmlspecialchars($acc['stage_name'] ?? '-') . "</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Listar funis e etapas disponíveis
    echo "<h2>3. Funis e Etapas Disponíveis</h2>";
    $sql = "SELECT 
                f.id as funnel_id,
                f.name as funnel_name,
                fs.id as stage_id,
                fs.name as stage_name,
                fs.is_system_stage,
                fs.system_stage_type
            FROM funnels f
            LEFT JOIN funnel_stages fs ON f.id = fs.funnel_id
            ORDER BY f.id, fs.stage_order, fs.id";
    $stmt = $db->query($sql);
    $funnels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $currentFunnelId = null;
    foreach ($funnels as $row) {
        if ($currentFunnelId !== $row['funnel_id']) {
            if ($currentFunnelId !== null) {
                echo "</ul>";
            }
            $currentFunnelId = $row['funnel_id'];
            echo "<h3>📊 Funil: {$row['funnel_name']} (ID: {$row['funnel_id']})</h3>";
            echo "<ul>";
        }
        
        if ($row['stage_id']) {
            $badge = '';
            if ($row['is_system_stage']) {
                $badge = " <span style='background: #22c55e; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em;'>🛡️ Sistema: {$row['system_stage_type']}</span>";
            }
            echo "<li><strong>{$row['stage_name']}</strong> (ID: {$row['stage_id']}){$badge}</li>";
        }
    }
    if ($currentFunnelId !== null) {
        echo "</ul>";
    }
    
    // Configuração do sistema padrão
    echo "<h2>4. Configuração Padrão do Sistema</h2>";
    $sql = "SELECT * FROM settings WHERE `key` = 'system_default_funnel_stage'";
    $stmt = $db->query($sql);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($setting) {
        $value = json_decode($setting['value'], true);
        echo "<pre>" . print_r($value, true) . "</pre>";
        
        if (!empty($value['funnel_id'])) {
            $sql = "SELECT name FROM funnels WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$value['funnel_id']]);
            $funnel = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p><strong>Funil Padrão:</strong> {$funnel['name']} (ID: {$value['funnel_id']})</p>";
        }
        
        if (!empty($value['stage_id'])) {
            $sql = "SELECT name FROM funnel_stages WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$value['stage_id']]);
            $stage = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p><strong>Etapa Padrão:</strong> {$stage['name']} (ID: {$value['stage_id']})</p>";
        }
    } else {
        echo "<p>❌ Configuração padrão do sistema NÃO encontrada!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← Voltar</a> | <a href='javascript:location.reload()'>🔄 Atualizar</a></p>";

