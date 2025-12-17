<?php
/**
 * Corrigir Funil Padrão
 * 
 * Acesse: http://seu-dominio.com/fix-default-funnel.php
 * REMOVER após usar!
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

$dbConfig = require __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    die("<h1>❌ Erro ao conectar</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

// ============================================================================
// PROCESSAR AÇÕES AJAX PRIMEIRO (antes de qualquer HTML)
// ============================================================================
if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_GET['action'];
        
        if ($action === 'set_default') {
            $funnelId = (int)$_GET['funnel_id'];
            
            // Desmarcar todos como padrão
            $pdo->exec("UPDATE funnels SET is_default = 0");
            
            // Marcar o selecionado
            $stmt = $pdo->prepare("UPDATE funnels SET is_default = 1 WHERE id = ?");
            $stmt->execute([$funnelId]);
            
            echo json_encode(['success' => true, 'message' => 'Funil marcado como padrão!']);
            
        } elseif ($action === 'set_default_stage') {
            $stageId = (int)$_GET['stage_id'];
            
            // Buscar funil da etapa
            $stmt = $pdo->prepare("SELECT funnel_id FROM funnel_stages WHERE id = ?");
            $stmt->execute([$stageId]);
            $stage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stage) {
                // Desmarcar todas etapas deste funil
                $pdo->prepare("UPDATE funnel_stages SET is_default = 0 WHERE funnel_id = ?")->execute([$stage['funnel_id']]);
                
                // Marcar a selecionada
                $pdo->prepare("UPDATE funnel_stages SET is_default = 1 WHERE id = ?")->execute([$stageId]);
                
                echo json_encode(['success' => true, 'message' => 'Etapa marcada como padrão!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Etapa não encontrada']);
            }
            
        } elseif ($action === 'create_default') {
            // Desmarcar todos como padrão
            $pdo->exec("UPDATE funnels SET is_default = 0");
            
            // Criar novo funil
            $stmt = $pdo->prepare("
                INSERT INTO funnels (name, description, status, is_default, color, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                'Funil Entrada',
                'Funil padrão do sistema. Todas as conversas sem configuração específica iniciam aqui.',
                'active',
                1,
                '#3F4254'
            ]);
            
            $funnelId = $pdo->lastInsertId();
            
            // Criar etapa padrão
            $stmt = $pdo->prepare("
                INSERT INTO funnel_stages (
                    funnel_id, name, description, color, position, 
                    is_default, allow_move_back, allow_skip_stages, 
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $funnelId,
                'Nova Entrada',
                'Etapa padrão para novas conversas sem configuração específica.',
                '#3F4254',
                1,
                1,
                1,
                1
            ]);
            
            $stageId = $pdo->lastInsertId();
            
            // Salvar configuração
            $config = json_encode(['funnel_id' => $funnelId, 'stage_id' => $stageId]);
            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`, `value`, `type`, `group`, label, description, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()
            ");
            $stmt->execute([
                'system_default_funnel_stage',
                $config,
                'json',
                'system',
                'Funil e Etapa Padrão do Sistema',
                'Funil e etapa usados como padrão quando não há configuração específica'
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Funil "Funil Entrada" criado com sucesso!']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit; // IMPORTANTE: Parar aqui para não gerar HTML
}

// ============================================================================
// INTERFACE HTML (só é executado se não for ação AJAX)
// ============================================================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corrigir Funil Padrão</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .action-box {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            margin: 20px 0;
        }
        button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0056b3;
        }
        button.danger {
            background: #dc3545;
        }
        button.danger:hover {
            background: #c82333;
        }
        button.success {
            background: #28a745;
        }
        button.success:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Corrigir Funil Padrão</h1>
        
        <?php
        // 1. Listar todos os funis
        echo "<h2>📊 Funis Existentes</h2>";
        $stmt = $pdo->query("SELECT * FROM funnels ORDER BY id ASC");
        $funnels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($funnels)) {
            echo "<p class='error'>❌ Nenhum funil encontrado!</p>";
        } else {
            echo "<table>";
            echo "<tr><th>ID</th><th>Nome</th><th>Status</th><th>É Padrão?</th><th>Cor</th><th>Ações</th></tr>";
            
            foreach ($funnels as $funnel) {
                $isDefault = $funnel['is_default'] ? '<span class="badge badge-success">SIM</span>' : '<span class="badge badge-warning">NÃO</span>';
                $statusBadge = $funnel['status'] === 'active' ? '<span class="badge badge-success">Ativo</span>' : '<span class="badge badge-danger">Inativo</span>';
                
                echo "<tr>";
                echo "<td><strong>{$funnel['id']}</strong></td>";
                echo "<td>{$funnel['name']}</td>";
                echo "<td>{$statusBadge}</td>";
                echo "<td>{$isDefault}</td>";
                echo "<td><span style='background: {$funnel['color']}; padding: 3px 10px; color: white; border-radius: 3px;'>{$funnel['color']}</span></td>";
                echo "<td>";
                if (!$funnel['is_default']) {
                    echo "<button class='success' onclick=\"setAsDefault({$funnel['id']}, '" . htmlspecialchars($funnel['name']) . "')\">Marcar como Padrão</button>";
                }
                echo "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
        
        // 2. Verificar se tem funil padrão
        echo "<hr>";
        echo "<h2>🎯 Status Atual</h2>";
        
        $stmt = $pdo->query("SELECT * FROM funnels WHERE is_default = 1 LIMIT 1");
        $defaultFunnel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($defaultFunnel) {
            echo "<div class='action-box' style='border-left-color: #28a745; background: #d4edda;'>";
            echo "<p class='success'>✅ Funil padrão encontrado!</p>";
            echo "<p><strong>Nome:</strong> {$defaultFunnel['name']}</p>";
            echo "<p><strong>ID:</strong> {$defaultFunnel['id']}</p>";
            echo "<p><strong>Status:</strong> {$defaultFunnel['status']}</p>";
            
            // Verificar etapa padrão
            $stmt = $pdo->prepare("SELECT * FROM funnel_stages WHERE funnel_id = ? AND is_default = 1 LIMIT 1");
            $stmt->execute([$defaultFunnel['id']]);
            $defaultStage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($defaultStage) {
                echo "<p><strong>Etapa Padrão:</strong> {$defaultStage['name']} (ID: {$defaultStage['id']})</p>";
            } else {
                echo "<p class='warning'>⚠️ Este funil não tem etapa padrão!</p>";
                
                // Buscar primeira etapa
                $stmt = $pdo->prepare("SELECT * FROM funnel_stages WHERE funnel_id = ? ORDER BY position ASC LIMIT 1");
                $stmt->execute([$defaultFunnel['id']]);
                $firstStage = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($firstStage) {
                    echo "<button onclick=\"setStageAsDefault({$firstStage['id']}, '{$firstStage['name']}')\">Marcar '{$firstStage['name']}' como Etapa Padrão</button>";
                }
            }
            
            echo "</div>";
        } else {
            echo "<div class='action-box' style='border-left-color: #dc3545; background: #f8d7da;'>";
            echo "<p class='error'>❌ NENHUM funil está marcado como padrão!</p>";
            echo "<p>Você precisa marcar um funil como padrão na tabela acima.</p>";
            echo "</div>";
        }
        
        // 3. Opção de criar novo funil padrão
        echo "<hr>";
        echo "<h2>➕ Criar Novo Funil Padrão</h2>";
        echo "<div class='action-box'>";
        echo "<p>Se preferir, pode criar um novo funil \"Funil Entrada\" como padrão do sistema:</p>";
        echo "<button onclick=\"createDefaultFunnel()\">Criar Funil \"Funil Entrada\"</button>";
        echo "</div>";
        ?>
        
        <hr>
        <div style="margin-top: 20px;">
            <a href="/funnels" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">← Ver Funis</a>
            <a href="/funnels/kanban" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">→ Ver Kanban</a>
        </div>
    </div>
    
    <script>
    function setAsDefault(funnelId, funnelName) {
        if (!confirm('Marcar "' + funnelName + '" como funil padrão do sistema?\n\nO funil padrão anterior (se houver) será desmarcado.')) {
            return;
        }
        
        fetch('fix-default-funnel.php?action=set_default&funnel_id=' + funnelId, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Erro: ' + error);
        });
    }
    
    function setStageAsDefault(stageId, stageName) {
        if (!confirm('Marcar "' + stageName + '" como etapa padrão?')) {
            return;
        }
        
        fetch('fix-default-funnel.php?action=set_default_stage&stage_id=' + stageId, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Erro: ' + error);
        });
    }
    
    function createDefaultFunnel() {
        if (!confirm('Criar novo funil "Funil Entrada" como padrão do sistema?\n\nO funil padrão anterior (se houver) será desmarcado.')) {
            return;
        }
        
        fetch('fix-default-funnel.php?action=create_default', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Erro: ' + error);
        });
    }
    </script>
</body>
</html>