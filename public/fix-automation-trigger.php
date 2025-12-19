<?php
/**
 * Script para verificar e corrigir automações sem nó trigger
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

header('Content-Type: text/html; charset=UTF-8');

echo '<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
    h1 { color: #333; }
    .error { color: #ef4444; font-weight: bold; }
    .success { color: #22c55e; font-weight: bold; }
    .warning { color: #f59e0b; font-weight: bold; }
    .info { color: #3b82f6; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #f2f2f2; }
    .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
    .btn { display: inline-block; padding: 10px 20px; background: #009ef7; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
    .btn-danger { background: #ef4444; }
    .btn-success { background: #22c55e; }
</style>';

echo "<h1>🔧 CORREÇÃO: Automações sem Nó Trigger</h1>";

try {
    // Buscar todas as automações ativas
    $automations = \App\Helpers\Database::fetchAll("
        SELECT id, name, trigger_type, status
        FROM automations
        WHERE status = 'active' AND is_active = TRUE
        ORDER BY id
    ", []);
    
    if (empty($automations)) {
        echo '<div class="card">';
        echo '<p class="info">ℹ️ Nenhuma automação ativa encontrada.</p>';
        echo '</div>';
        exit;
    }
    
    echo '<div class="card">';
    echo '<h2>📊 Análise de Automações</h2>';
    echo '<table>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Nome</th>';
    echo '<th>Trigger</th>';
    echo '<th>Total de Nós</th>';
    echo '<th>Tem Nó Trigger?</th>';
    echo '<th>Status</th>';
    echo '<th>Ação</th>';
    echo '</tr>';
    
    $problemAutomations = [];
    
    foreach ($automations as $auto) {
        $nodes = \App\Helpers\Database::fetchAll("
            SELECT id, node_type, position_x, position_y
            FROM automation_nodes
            WHERE automation_id = ?
            ORDER BY id
        ", [$auto['id']]);
        
        $hasTrigger = false;
        foreach ($nodes as $node) {
            if ($node['node_type'] === 'trigger') {
                $hasTrigger = true;
                break;
            }
        }
        
        echo '<tr>';
        echo '<td>' . $auto['id'] . '</td>';
        echo '<td>' . htmlspecialchars($auto['name']) . '</td>';
        echo '<td>' . htmlspecialchars($auto['trigger_type']) . '</td>';
        echo '<td>' . count($nodes) . '</td>';
        
        if ($hasTrigger) {
            echo '<td class="success">✅ SIM</td>';
            echo '<td class="success">OK</td>';
            echo '<td>-</td>';
        } else {
            echo '<td class="error">❌ NÃO</td>';
            echo '<td class="error">PROBLEMA</td>';
            echo '<td><a href="?fix=' . $auto['id'] . '" class="btn btn-success">Corrigir</a></td>';
            $problemAutomations[] = ['automation' => $auto, 'nodes' => $nodes];
        }
        
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</div>';
    
    // Se houver automações com problema
    if (!empty($problemAutomations)) {
        echo '<div class="card">';
        echo '<h2 class="error">⚠️ ' . count($problemAutomations) . ' Automação(ões) com Problema</h2>';
        echo '<p><strong>O que acontece:</strong></p>';
        echo '<ul>';
        echo '<li>❌ A automação NÃO será executada porque não tem um nó inicial</li>';
        echo '<li>❌ O sistema não sabe por onde começar o fluxo</li>';
        echo '<li>❌ Mesmo que a automação seja disparada, nada acontecerá</li>';
        echo '</ul>';
        
        echo '<p><strong>Como corrigir manualmente:</strong></p>';
        echo '<ol>';
        echo '<li>Acesse a automação no menu <strong>Automações</strong></li>';
        echo '<li>Clique em <strong>Editar Diagrama</strong></li>';
        echo '<li>Na paleta de componentes, arraste um nó <strong>"Gatilho"</strong> para o canvas</li>';
        echo '<li>Configure o nó trigger com as condições desejadas</li>';
        echo '<li>Conecte o nó trigger aos demais nós</li>';
        echo '<li>Clique em <strong>Salvar Layout</strong></li>';
        echo '</ol>';
        
        echo '<p class="info">💡 <strong>Dica:</strong> Toda automação deve começar assim:<br>';
        echo '<code>Nó Trigger → Nó de Ação → Nó de Ação → ...</code></p>';
        echo '</div>';
        
        // Detalhes de cada automação
        foreach ($problemAutomations as $item) {
            $auto = $item['automation'];
            $nodes = $item['nodes'];
            
            echo '<div class="card">';
            echo '<h3>Automação: ' . htmlspecialchars($auto['name']) . ' (ID: ' . $auto['id'] . ')</h3>';
            echo '<p><strong>Nós atuais:</strong></p>';
            echo '<table style="width: auto;">';
            echo '<tr><th>ID</th><th>Tipo</th><th>Posição</th></tr>';
            foreach ($nodes as $node) {
                echo '<tr>';
                echo '<td>' . $node['id'] . '</td>';
                echo '<td>' . htmlspecialchars($node['node_type']) . '</td>';
                echo '<td>' . $node['position_x'] . ', ' . $node['position_y'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
        }
    } else {
        echo '<div class="card">';
        echo '<p class="success">✅ <strong>TUDO OK!</strong> Todas as automações têm nó trigger.</p>';
        echo '</div>';
    }
    
    // Botão para corrigir automaticamente
    if (!empty($problemAutomations) && !isset($_GET['fix'])) {
        echo '<div class="card">';
        echo '<h2>🛠️ Correção Automática</h2>';
        echo '<p><strong>Atenção:</strong> Este processo irá adicionar automaticamente um nó trigger no início de cada automação.</p>';
        echo '<p><strong>O que será feito:</strong></p>';
        echo '<ul>';
        echo '<li>✅ Criar um nó "Gatilho" para cada automação sem trigger</li>';
        echo '<li>✅ Posicionar o nó no canto superior esquerdo do canvas</li>';
        echo '<li>✅ Conectar automaticamente ao primeiro nó existente</li>';
        echo '</ul>';
        echo '<p class="warning">⚠️ Após a correção automática, você ainda precisará verificar as conexões manualmente no editor de diagramas.</p>';
        echo '<a href="?fix_all=1" class="btn btn-danger" onclick="return confirm(\'Tem certeza que deseja corrigir TODAS as automações automaticamente?\')">Corrigir Todas Automaticamente</a>';
        echo '</div>';
    }
    
    // Processar correção
    if (isset($_GET['fix']) && is_numeric($_GET['fix'])) {
        $autoId = (int)$_GET['fix'];
        
        echo '<div class="card">';
        echo '<h2>🔧 Corrigindo Automação ID: ' . $autoId . '</h2>';
        
        // Buscar primeiro nó existente
        $firstNode = \App\Helpers\Database::fetch("
            SELECT id, node_type, position_x, position_y
            FROM automation_nodes
            WHERE automation_id = ?
            ORDER BY position_x, position_y
            LIMIT 1
        ", [$autoId]);
        
        if (!$firstNode) {
            echo '<p class="error">❌ Nenhum nó encontrado para esta automação!</p>';
        } else {
            // Criar nó trigger
            $triggerData = [
                'label' => 'Gatilho',
                'connections' => [
                    [
                        'type' => 'next',
                        'target_node_id' => $firstNode['id']
                    ]
                ],
                'channel' => '',
                'whatsapp_account_id' => ''
            ];
            
            $stmt = \App\Helpers\Database::getInstance()->prepare("
                INSERT INTO automation_nodes 
                (automation_id, node_type, node_data, position_x, position_y, created_at, updated_at)
                VALUES (?, 'trigger', ?, 50, 50, NOW(), NOW())
            ");
            
            $stmt->execute([$autoId, json_encode($triggerData)]);
            
            echo '<p class="success">✅ Nó trigger criado com sucesso!</p>';
            echo '<p>Próximo nó conectado: ID ' . $firstNode['id'] . ' (' . htmlspecialchars($firstNode['node_type']) . ')</p>';
            echo '<p class="info">💡 Acesse o editor de diagramas para verificar e ajustar as conexões.</p>';
            echo '<a href="?" class="btn">← Voltar</a>';
        }
        
        echo '</div>';
    }
    
    if (isset($_GET['fix_all'])) {
        echo '<div class="card">';
        echo '<h2>🔧 Corrigindo Todas as Automações</h2>';
        
        $fixed = 0;
        foreach ($problemAutomations as $item) {
            $auto = $item['automation'];
            $nodes = $item['nodes'];
            
            if (empty($nodes)) {
                echo '<p class="warning">⚠️ Automação "' . htmlspecialchars($auto['name']) . '" não tem nós - pulando</p>';
                continue;
            }
            
            $firstNode = $nodes[0];
            
            $triggerData = [
                'label' => 'Gatilho',
                'connections' => [
                    [
                        'type' => 'next',
                        'target_node_id' => $firstNode['id']
                    ]
                ],
                'channel' => '',
                'whatsapp_account_id' => ''
            ];
            
            $stmt = \App\Helpers\Database::getInstance()->prepare("
                INSERT INTO automation_nodes 
                (automation_id, node_type, node_data, position_x, position_y, created_at, updated_at)
                VALUES (?, 'trigger', ?, 50, 50, NOW(), NOW())
            ");
            
            $stmt->execute([$auto['id'], json_encode($triggerData)]);
            
            echo '<p class="success">✅ Automação "' . htmlspecialchars($auto['name']) . '" corrigida!</p>';
            $fixed++;
        }
        
        echo '<h3 class="success">✅ ' . $fixed . ' automação(ões) corrigida(s)!</h3>';
        echo '<p class="info">💡 Acesse o editor de diagramas de cada automação para verificar as conexões.</p>';
        echo '<a href="?" class="btn">← Voltar para Análise</a>';
        echo '</div>';
    }
    
} catch (\Exception $e) {
    echo '<div class="card">';
    echo '<p class="error">❌ ERRO: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}

echo '<br><br>';
echo '<a href="test-trigger-automation.php" class="btn">🧪 Testar Automações</a>';
echo '<a href="test-automation-integration.php" class="btn">📊 Teste de Integração</a>';

