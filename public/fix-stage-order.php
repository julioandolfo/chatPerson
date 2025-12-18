<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Corrigir Ordem das Etapas</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #e2e8f0;
        }
        .card {
            background: #2d3748;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #4a5568;
        }
        h1 {
            color: #63b3ed;
            margin-top: 0;
        }
        h2 {
            color: #48bb78;
            border-bottom: 2px solid #48bb78;
            padding-bottom: 10px;
        }
        .success {
            background: #276749;
            color: #9ae6b4;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .error {
            background: #742a2a;
            color: #fc8181;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .info {
            background: #2c5282;
            color: #90cdf4;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        pre {
            background: #1a202c;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            border: 1px solid #4a5568;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3182ce;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 5px;
            cursor: pointer;
            border: none;
            font-size: 1em;
        }
        .btn:hover {
            background: #2c5282;
        }
        .btn-success {
            background: #38a169;
        }
        .btn-success:hover {
            background: #2f855a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #4a5568;
        }
        th {
            background: #1a202c;
            color: #63b3ed;
        }
        .order-null {
            color: #fc8181;
            font-weight: bold;
        }
        .order-ok {
            color: #9ae6b4;
        }
    </style>
</head>
<body>
    <?php
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config/database.php';
    
    $db = \App\Helpers\Database::getInstance();
    
    // Processar ação de correção
    if (isset($_POST['fix_all'])) {
        echo '<div class="card">';
        echo '<h2>🔧 Corrigindo Ordem das Etapas...</h2>';
        
        try {
            $db->beginTransaction();
            
            // Buscar todos os funis
            $sql = "SELECT DISTINCT funnel_id FROM funnel_stages ORDER BY funnel_id";
            $stmt = $db->query($sql);
            $funnelIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $totalUpdated = 0;
            
            foreach ($funnelIds as $funnelId) {
                // Buscar etapas do funil com ordenação especial para etapas do sistema
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
                
                echo '<h3>Funil ID: ' . $funnelId . '</h3>';
                echo '<ul>';
                
                // Atualizar stage_order para cada etapa
                foreach ($stages as $index => $stage) {
                    $newOrder = $index + 1;
                    
                    $sql = "UPDATE funnel_stages SET stage_order = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$newOrder, $stage['id']]);
                    
                    $isSystem = !empty($stage['is_system_stage']) ? ' [SISTEMA]' : '';
                    $oldOrder = $stage['stage_order'] ?? 'NULL';
                    echo '<li>Etapa "' . htmlspecialchars($stage['name']) . '"' . $isSystem . ': ' . $oldOrder . ' → <strong>' . $newOrder . '</strong></li>';
                    
                    $totalUpdated++;
                }
                
                echo '</ul>';
            }
            
            $db->commit();
            
            echo '<div class="success">✅ Sucesso! Total de etapas atualizadas: ' . $totalUpdated . '</div>';
            echo '<p><a href="?" class="btn btn-success">🔄 Ver Resultado</a> <a href="/funnels/kanban" class="btn btn-success">🚀 Ir para Kanban</a></p>';
            
        } catch (\Exception $e) {
            $db->rollBack();
            echo '<div class="error">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        echo '</div>';
    }
    ?>
    
    <div class="card">
        <h1>🔧 Corrigir Ordem das Etapas do Kanban</h1>
        <p>Este script verifica e corrige os valores de <code>stage_order</code> para todas as etapas.</p>
    </div>

    <div class="card">
        <h2>📊 Estado Atual das Etapas</h2>
        <?php
        try {
            // Buscar todas as etapas agrupadas por funil
            $sql = "SELECT fs.id, fs.name, fs.funnel_id, fs.stage_order, f.name as funnel_name 
                    FROM funnel_stages fs 
                    LEFT JOIN funnels f ON f.id = fs.funnel_id 
                    ORDER BY fs.funnel_id, fs.id";
            $stmt = $db->query($sql);
            $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($stages)) {
                echo '<div class="info">ℹ️ Nenhuma etapa encontrada no banco de dados.</div>';
            } else {
                $currentFunnelId = null;
                $problemsFound = 0;
                
                foreach ($stages as $stage) {
                    if ($currentFunnelId !== $stage['funnel_id']) {
                        if ($currentFunnelId !== null) {
                            echo '</tbody></table>';
                        }
                        $currentFunnelId = $stage['funnel_id'];
                        echo '<h3>Funil: ' . htmlspecialchars($stage['funnel_name']) . ' (ID: ' . $stage['funnel_id'] . ')</h3>';
                        echo '<table>';
                        echo '<thead><tr><th>ID</th><th>Nome</th><th>stage_order</th><th>Status</th></tr></thead>';
                        echo '<tbody>';
                    }
                    
                    $orderValue = $stage['stage_order'] ?? 'NULL';
                    $isNull = ($stage['stage_order'] === null || $stage['stage_order'] === '');
                    $statusClass = $isNull ? 'order-null' : 'order-ok';
                    $statusText = $isNull ? '❌ Precisa corrigir' : '✅ OK';
                    
                    if ($isNull) {
                        $problemsFound++;
                    }
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($stage['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($stage['name']) . '</td>';
                    echo '<td class="' . $statusClass . '">' . htmlspecialchars($orderValue) . '</td>';
                    echo '<td class="' . $statusClass . '">' . $statusText . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                
                if ($problemsFound > 0) {
                    echo '<div class="error">';
                    echo '<strong>⚠️ Problemas encontrados: ' . $problemsFound . ' etapas sem stage_order</strong>';
                    echo '<form method="POST" style="margin-top: 15px;">';
                    echo '<button type="submit" name="fix_all" class="btn btn-success">🔧 Corrigir Todas as Etapas</button>';
                    echo '</form>';
                    echo '</div>';
                } else {
                    echo '<div class="success">✅ Todas as etapas estão com stage_order definido!</div>';
                }
            }
        } catch (\Exception $e) {
            echo '<div class="error">❌ Erro ao buscar etapas: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>

    <div class="card">
        <h2>📝 O que este script faz?</h2>
        <ol>
            <li>Verifica todas as etapas no banco de dados</li>
            <li>Identifica etapas sem <code>stage_order</code> definido (NULL)</li>
            <li>Ao clicar em "Corrigir", define valores sequenciais (1, 2, 3...)</li>
            <li>Mantém a ordem pela data de criação (ID crescente)</li>
        </ol>
    </div>

    <div class="card">
        <h2>🧪 Após Corrigir</h2>
        <div class="info">
            <p><strong>Próximos passos:</strong></p>
            <ol>
                <li>Verifique se todos os valores estão OK (verde)</li>
                <li>Limpe o cache do navegador (<kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>R</kbd>)</li>
                <li>Acesse o Kanban e teste a reordenação</li>
            </ol>
            <p style="text-align: center; margin-top: 20px;">
                <a href="/funnels/kanban" class="btn btn-success">🚀 Ir para o Kanban</a>
            </p>
        </div>
    </div>

    <div style="text-align: center; padding: 30px; opacity: 0.5;">
        <p>🔧 Fix Script - Remova após corrigir</p>
        <p><code>public/fix-stage-order.php</code></p>
    </div>
</body>
</html>

