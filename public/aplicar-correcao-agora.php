<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ Aplicando Correção...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .card {
            background: white;
            color: #212529;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-top: 0;
            text-align: center;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .error {
            background: #fee;
            color: #c82333;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #22c55e;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin: 10px 5px;
            font-weight: 600;
            font-size: 1.1em;
        }
        .btn:hover {
            background: #16a34a;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
        .loading {
            text-align: center;
            padding: 40px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config/database.php';
    
    echo '<div class="card">';
    echo '<h1>⚡ Aplicando Correção Automática</h1>';
    
    try {
        $db = \App\Helpers\Database::getInstance();
        $db->beginTransaction();
        
        echo '<div class="loading">';
        echo '<div class="spinner"></div>';
        echo '<p>Processando...</p>';
        echo '</div>';
        
        // Buscar todos os funis
        $sql = "SELECT DISTINCT funnel_id FROM funnel_stages ORDER BY funnel_id";
        $stmt = $db->query($sql);
        $funnelIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo '<h2>📝 Detalhes da Correção</h2>';
        
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
            echo '<pre>';
            
            // Atualizar stage_order para cada etapa
            foreach ($stages as $index => $stage) {
                $newOrder = $index + 1;
                
                $sql = "UPDATE funnel_stages SET stage_order = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$newOrder, $stage['id']]);
                
                $isSystem = !empty($stage['is_system_stage']) ? ' [SISTEMA]' : '';
                $oldOrder = $stage['stage_order'] ?? 'NULL';
                echo "Etapa '{$stage['name']}'{$isSystem}: {$oldOrder} → {$newOrder}\n";
                
                $totalUpdated++;
            }
            
            echo '</pre>';
        }
        
        $db->commit();
        
        echo '<div class="success">';
        echo '<h2>✅ Correção Aplicada com Sucesso!</h2>';
        echo '<p><strong>Total de etapas atualizadas:</strong> ' . $totalUpdated . '</p>';
        echo '<p>Todas as etapas agora estão com valores de <code>stage_order</code> corretos e únicos!</p>';
        echo '</div>';
        
        echo '<div style="text-align: center; margin-top: 30px;">';
        echo '<p><strong>Próximos passos:</strong></p>';
        echo '<ol style="text-align: left; display: inline-block;">';
        echo '<li>Limpe o cache do navegador (<kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>R</kbd>)</li>';
        echo '<li>Acesse o Kanban</li>';
        echo '<li>Teste a reordenação com as setas ← →</li>';
        echo '</ol>';
        echo '<p><a href="/funnels/kanban" class="btn">🚀 Ir para o Kanban</a></p>';
        echo '<p><a href="/debug-reorder.php" class="btn" style="background: #3182ce;">🔍 Ver Debug</a></p>';
        echo '</div>';
        
    } catch (\Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        
        echo '<div class="error">';
        echo '<h2>❌ Erro ao Aplicar Correção</h2>';
        echo '<p><strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>Arquivo:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>Linha:</strong> ' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
    }
    
    echo '</div>';
    ?>
    
    <div style="text-align: center; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 8px;">
        <p>📝 Remova este arquivo após os testes: <code>public/aplicar-correcao-agora.php</code></p>
    </div>
</body>
</html>

