<?php
/**
 * Script de Debug para Reordenação de Etapas
 */

// Configurar para mostrar todos os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Services\FunnelService;
use App\Models\FunnelStage;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Debug - Reordenação de Etapas</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #1a202c;
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
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Debug - Reordenação de Etapas do Kanban</h1>
        <p>Este script testa a funcionalidade de reordenação passo a passo</p>
    </div>

    <?php
    // PASSO 1: Verificar conexão com banco
    echo '<div class="card">';
    echo '<h2>1️⃣ Verificando Conexão com Banco de Dados</h2>';
    try {
        $db = \App\Helpers\Database::getInstance();
        echo '<div class="success">✅ Conexão estabelecida com sucesso!</div>';
    } catch (\Exception $e) {
        echo '<div class="error">❌ Erro ao conectar: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
    echo '</div>';

    // PASSO 2: Listar etapas disponíveis
    echo '<div class="card">';
    echo '<h2>2️⃣ Etapas Disponíveis no Banco</h2>';
    try {
        $sql = "SELECT fs.id, fs.name, fs.funnel_id, fs.stage_order, f.name as funnel_name 
                FROM funnel_stages fs 
                LEFT JOIN funnels f ON f.id = fs.funnel_id 
                ORDER BY fs.funnel_id, fs.stage_order";
        $stmt = $db->query($sql);
        $stages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($stages)) {
            echo '<div class="info">ℹ️ Nenhuma etapa encontrada no banco de dados.</div>';
        } else {
            echo '<table>';
            echo '<thead><tr><th>ID</th><th>Nome</th><th>Funil</th><th>Ordem</th><th>Ações</th></tr></thead>';
            echo '<tbody>';
            foreach ($stages as $stage) {
                $order = $stage['stage_order'] ?? 'NULL';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($stage['id']) . '</td>';
                echo '<td>' . htmlspecialchars($stage['name']) . '</td>';
                echo '<td>' . htmlspecialchars($stage['funnel_name']) . '</td>';
                echo '<td>' . htmlspecialchars($order) . '</td>';
                echo '<td>';
                echo '<button class="btn" onclick="testReorder(' . $stage['id'] . ', \'up\')">⬅️ Up</button> ';
                echo '<button class="btn" onclick="testReorder(' . $stage['id'] . ', \'down\')">➡️ Down</button>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }
    } catch (\Exception $e) {
        echo '<div class="error">❌ Erro ao buscar etapas: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    echo '</div>';

    // PASSO 3: Área de teste
    echo '<div class="card">';
    echo '<h2>3️⃣ Teste de Reordenação</h2>';
    echo '<div id="test-result"></div>';
    
    if (isset($_POST['test_stage_id']) && isset($_POST['test_direction'])) {
        $stageId = (int)$_POST['test_stage_id'];
        $direction = $_POST['test_direction'];
        
        echo '<div class="info">🧪 Testando reordenação...</div>';
        echo '<pre>';
        echo "Etapa ID: $stageId\n";
        echo "Direção: $direction\n\n";
        echo '</pre>';
        
        try {
            echo '<strong>Chamando FunnelService::reorderStage()...</strong><br><br>';
            
            // Capturar qualquer output
            ob_start();
            $result = FunnelService::reorderStage($stageId, $direction);
            $output = ob_get_clean();
            
            if (!empty($output)) {
                echo '<div class="error">⚠️ Output inesperado capturado:</div>';
                echo '<pre>' . htmlspecialchars($output) . '</pre>';
            }
            
            if ($result) {
                echo '<div class="success">✅ Reordenação executada com sucesso!</div>';
                echo '<p><a href="?" class="btn">🔄 Recarregar página</a></p>';
            } else {
                echo '<div class="error">❌ Reordenação retornou false</div>';
            }
            
        } catch (\Exception $e) {
            echo '<div class="error">❌ Erro durante reordenação:</div>';
            echo '<pre>';
            echo "Mensagem: " . htmlspecialchars($e->getMessage()) . "\n";
            echo "Arquivo: " . htmlspecialchars($e->getFile()) . "\n";
            echo "Linha: " . $e->getLine() . "\n\n";
            echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
        }
    }
    echo '</div>';

    // PASSO 4: Verificar FunnelService
    echo '<div class="card">';
    echo '<h2>4️⃣ Verificação do Código</h2>';
    
    echo '<h3>Método reorderStage() existe?</h3>';
    if (method_exists(FunnelService::class, 'reorderStage')) {
        echo '<div class="success">✅ Método existe</div>';
        
        $reflection = new \ReflectionMethod(FunnelService::class, 'reorderStage');
        echo '<pre>';
        echo "Arquivo: " . $reflection->getFileName() . "\n";
        echo "Linha inicial: " . $reflection->getStartLine() . "\n";
        echo "Linha final: " . $reflection->getEndLine() . "\n";
        echo '</pre>';
    } else {
        echo '<div class="error">❌ Método não encontrado!</div>';
    }
    echo '</div>';
    ?>

    <script>
        function testReorder(stageId, direction) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'test_stage_id';
            inputId.value = stageId;
            
            const inputDir = document.createElement('input');
            inputDir.type = 'hidden';
            inputDir.name = 'test_direction';
            inputDir.value = direction;
            
            form.appendChild(inputId);
            form.appendChild(inputDir);
            document.body.appendChild(form);
            form.submit();
        }
    </script>

    <div style="text-align: center; padding: 30px; opacity: 0.5;">
        <p>🔍 Debug Script - Remova após os testes</p>
        <p><code>public/debug-reorder.php</code></p>
    </div>
</body>
</html>

