<?php
/**
 * Script de Teste: Simulador de Erro de Quota OpenAI
 * 
 * ATENÇÃO: Este script é apenas para testes em ambiente de desenvolvimento!
 * Ele simula um erro de quota da OpenAI para verificar o tratamento de erros.
 * 
 * Para usar:
 * 1. Acesse: http://localhost/chat/public/test-quota-error.php
 * 2. Escolha o tipo de erro a simular
 * 3. Verifique os logs e alertas criados
 */

require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Auth;
use App\Services\KanbanAgentService;
use App\Models\Conversation;

// Verificar autenticação
if (!Auth::check()) {
    header('Location: /login.php');
    exit;
}

// Verificar permissão de admin
$user = Auth::user();
if (!in_array($user['role'], ['super_admin', 'admin'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Acesso negado. Apenas administradores podem acessar esta página.';
    exit;
}

// Processar teste
$testResult = null;
$errorType = $_GET['type'] ?? '';

if (!empty($errorType)) {
    // Criar uma conversa fictícia para teste
    $testConversation = [
        'id' => 9999999,
        'contact_id' => 1,
        'funnel_id' => 1,
        'funnel_stage_id' => 1,
        'status' => 'open',
        'priority' => 'normal'
    ];
    
    // Criar agente fictício
    $testAgent = [
        'id' => 9999999,
        'name' => 'Agente de Teste',
        'prompt' => 'Você é um assistente de testes.',
        'model' => 'gpt-4',
        'temperature' => 0.7,
        'max_tokens' => 2000,
        'conditions' => ['operator' => 'AND', 'conditions' => []],
        'actions' => []
    ];
    
    try {
        // Simular erro forçando uma chamada OpenAI com erro
        if ($errorType === 'quota_exceeded') {
            // Temporariamente substituir a API key por uma inválida para forçar erro
            $originalKey = \App\Models\Setting::get('openai_api_key');
            \App\Models\Setting::set('openai_api_key', 'sk-test-quota-exceeded');
            
            // Tentar analisar conversa (vai falhar)
            $reflection = new ReflectionClass('App\Services\KanbanAgentService');
            $method = $reflection->getMethod('analyzeConversation');
            $method->setAccessible(true);
            
            $testResult = $method->invoke(null, $testAgent, $testConversation);
            
            // Restaurar chave original
            \App\Models\Setting::set('openai_api_key', $originalKey);
        }
    } catch (\Exception $e) {
        $testResult = [
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ];
    }
}

// Buscar alertas criados
$recentAlerts = Database::fetchAll(
    "SELECT * FROM system_alerts 
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
     ORDER BY created_at DESC 
     LIMIT 10"
);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Erro de Quota OpenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .test-option {
            transition: all 0.2s;
            cursor: pointer;
        }
        .test-option:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard.php">
                <i class="bi bi-bug-fill"></i> Teste de Quota OpenAI
            </a>
            <div>
                <a href="admin/system-alerts.php" class="btn btn-sm btn-outline-light me-2">
                    <i class="bi bi-bell"></i> Ver Alertas
                </a>
                <span class="navbar-text text-white">
                    <?php echo htmlspecialchars($user['name']); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Aviso -->
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <h5 class="alert-heading">
                <i class="bi bi-exclamation-triangle-fill"></i> Ambiente de Teste
            </h5>
            <p class="mb-0">
                Este script simula erros da OpenAI para testar o tratamento de exceções.
                <strong>Use apenas em ambiente de desenvolvimento!</strong>
            </p>
        </div>

        <!-- Resultado do teste -->
        <?php if ($testResult): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-check"></i> Resultado do Teste
                    </h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded"><?php print_r($testResult); ?></pre>
                    
                    <?php if (isset($testResult['error']) && $testResult['error'] === 'quota_exceeded'): ?>
                        <div class="alert alert-success mt-3">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong>Sucesso!</strong> O sistema tratou o erro de quota corretamente e retornou uma análise padrão.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Opções de teste -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <a href="?type=quota_exceeded" class="text-decoration-none">
                    <div class="card border-0 shadow-sm test-option">
                        <div class="card-body text-center py-4">
                            <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 mb-2">Simular Quota Excedida</h5>
                            <p class="text-muted mb-0">
                                Testa o comportamento quando a quota da OpenAI é excedida
                            </p>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-6">
                <a href="admin/system-alerts.php" class="text-decoration-none">
                    <div class="card border-0 shadow-sm test-option">
                        <div class="card-body text-center py-4">
                            <i class="bi bi-bell-fill text-primary" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 mb-2">Ver Alertas Criados</h5>
                            <p class="text-muted mb-0">
                                Visualizar alertas do sistema criados durante os testes
                            </p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Alertas recentes -->
        <?php if (!empty($recentAlerts)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history"></i> Alertas Recentes (última hora)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentAlerts)): ?>
                        <p class="text-muted mb-0">Nenhum alerta criado recentemente.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($recentAlerts as $alert): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php if ($alert['severity'] === 'critical'): ?>
                                                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                                            <?php elseif ($alert['severity'] === 'warning'): ?>
                                                <i class="bi bi-exclamation-circle-fill text-warning"></i>
                                            <?php else: ?>
                                                <i class="bi bi-info-circle-fill text-info"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($alert['title']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo date('H:i:s', strtotime($alert['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($alert['message']); ?></p>
                                    <small class="text-muted">
                                        <span class="badge bg-secondary"><?php echo $alert['type']; ?></span>
                                        <?php if ($alert['is_resolved']): ?>
                                            <span class="badge bg-success">Resolvido</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Instruções -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-info-circle"></i> Como Usar
                </h5>
            </div>
            <div class="card-body">
                <ol>
                    <li><strong>Clique em "Simular Quota Excedida"</strong> para forçar um erro de quota</li>
                    <li><strong>Observe o resultado</strong> - O sistema deve retornar uma análise padrão ao invés de falhar</li>
                    <li><strong>Verifique os logs</strong> em <code>storage/logs/kanban_agents.log</code></li>
                    <li><strong>Verifique os alertas</strong> criados clicando em "Ver Alertas Criados"</li>
                    <li><strong>Marque como resolvido</strong> quando terminar o teste</li>
                </ol>
                
                <div class="alert alert-info mb-0 mt-3">
                    <h6 class="alert-heading">
                        <i class="bi bi-lightbulb-fill"></i> O que esperar:
                    </h6>
                    <ul class="mb-0">
                        <li>✅ Sistema <strong>não deve quebrar</strong></li>
                        <li>✅ Deve retornar análise padrão com score neutro (50)</li>
                        <li>✅ Deve criar alerta crítico em <code>system_alerts</code></li>
                        <li>✅ Deve logar erro detalhado em <code>kanban_agents.log</code></li>
                        <li>✅ Conversa deve continuar funcionando normalmente</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Links úteis -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-link-45deg"></i> Links Úteis
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li>
                        <a href="admin/system-alerts.php" target="_blank">
                            <i class="bi bi-bell"></i> Painel de Alertas do Sistema
                        </a>
                    </li>
                    <li>
                        <a href="https://platform.openai.com/account/billing" target="_blank">
                            <i class="bi bi-credit-card"></i> OpenAI Billing Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="https://platform.openai.com/account/usage" target="_blank">
                            <i class="bi bi-graph-up"></i> OpenAI Usage Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="https://platform.openai.com/docs/guides/error-codes" target="_blank">
                            <i class="bi bi-book"></i> OpenAI Error Codes Documentation
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
