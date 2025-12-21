<?php
/**
 * Página de Debug para Mensagens Agendadas
 * Acesse: /debug-scheduled-messages.php
 */

require_once __DIR__ . '/../config/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Auth;

// Verificar se está logado (opcional - remover se quiser acesso sem login)
if (!Auth::check()) {
    header('Location: /login');
    exit;
}

// Processar ação de teste manual
$testResult = null;
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    try {
        $processed = \App\Services\ScheduledMessageService::processPending(50);
        $testResult = [
            'success' => true,
            'processed' => $processed,
            'count' => count($processed)
        ];
    } catch (\Exception $e) {
        $testResult = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Buscar informações
$stats = [];
$pendingMessages = [];
$recentMessages = [];
$logs = [];

try {
    // Estatísticas
    $stats['total'] = Database::fetch("SELECT COUNT(*) as total FROM scheduled_messages")['total'] ?? 0;
    $stats['pending'] = Database::fetch("SELECT COUNT(*) as total FROM scheduled_messages WHERE status = 'pending'")['total'] ?? 0;
    $stats['pending_overdue'] = Database::fetch("SELECT COUNT(*) as total FROM scheduled_messages WHERE status = 'pending' AND scheduled_at <= NOW()")['total'] ?? 0;
    $stats['sent_today'] = Database::fetch("SELECT COUNT(*) as total FROM scheduled_messages WHERE status = 'sent' AND DATE(sent_at) = CURDATE()")['total'] ?? 0;
    $stats['failed_today'] = Database::fetch("SELECT COUNT(*) as total FROM scheduled_messages WHERE status = 'failed' AND DATE(updated_at) = CURDATE()")['total'] ?? 0;
    
    // Mensagens pendentes atrasadas
    $pendingMessages = Database::fetchAll(
        "SELECT sm.*, 
                c.contact_id,
                ct.name as contact_name,
                ct.phone as contact_phone,
                u.name as user_name,
                TIMESTAMPDIFF(MINUTE, sm.scheduled_at, NOW()) as minutes_late
         FROM scheduled_messages sm
         LEFT JOIN conversations c ON sm.conversation_id = c.id
         LEFT JOIN contacts ct ON c.contact_id = ct.id
         LEFT JOIN users u ON sm.user_id = u.id
         WHERE sm.status = 'pending' 
         AND sm.scheduled_at <= NOW()
         ORDER BY sm.scheduled_at ASC
         LIMIT 20"
    );
    
    // Mensagens recentes (últimas 10)
    $recentMessages = Database::fetchAll(
        "SELECT sm.*, 
                c.contact_id,
                ct.name as contact_name,
                u.name as user_name
         FROM scheduled_messages sm
         LEFT JOIN conversations c ON sm.conversation_id = c.id
         LEFT JOIN contacts ct ON c.contact_id = ct.id
         LEFT JOIN users u ON sm.user_id = u.id
         ORDER BY sm.created_at DESC
         LIMIT 10"
    );
    
} catch (\Exception $e) {
    $error = $e->getMessage();
}

// Verificar se script de processamento existe
$scriptExists = file_exists(__DIR__ . '/scripts/process-scheduled-messages.php');
$scriptPath = realpath(__DIR__ . '/scripts/process-scheduled-messages.php');

// Verificar logs
$logsDir = __DIR__ . '/../logs';
$logFiles = [
    'app.log' => 'Log Geral',
    'scheduled-messages.log' => 'Mensagens Agendadas',
];

foreach ($logFiles as $file => $desc) {
    $path = $logsDir . '/' . $file;
    if (file_exists($path)) {
        $logs[$desc] = [
            'exists' => true,
            'size' => filesize($path),
            'modified' => filemtime($path),
            'minutes_ago' => round((time() - filemtime($path)) / 60),
            'path' => $path
        ];
    } else {
        $logs[$desc] = ['exists' => false];
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🐛 Debug - Mensagens Agendadas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 20px; }
        h2 { color: #555; margin: 30px 0 15px; font-size: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .stat { padding: 15px; border-radius: 6px; text-align: center; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .stat-value { font-size: 32px; font-weight: bold; }
        .stat-primary { background: #e3f2fd; color: #1976d2; }
        .stat-warning { background: #fff3e0; color: #f57c00; }
        .stat-danger { background: #ffebee; color: #c62828; }
        .stat-success { background: #e8f5e9; color: #2e7d32; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { background: #f5f5f5; font-weight: 600; font-size: 13px; color: #666; }
        .table tr:hover { background: #f9f9f9; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-pending { background: #fff3e0; color: #f57c00; }
        .badge-sent { background: #e8f5e9; color: #2e7d32; }
        .badge-failed { background: #ffebee; color: #c62828; }
        .badge-cancelled { background: #f5f5f5; color: #666; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; font-size: 14px; }
        .btn-primary { background: #1976d2; color: white; }
        .btn-success { background: #2e7d32; color: white; }
        .btn-danger { background: #c62828; color: white; }
        .btn:hover { opacity: 0.9; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .alert-danger { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .alert-warning { background: #fff3e0; color: #f57c00; border-left: 4px solid #f57c00; }
        .alert-info { background: #e3f2fd; color: #1976d2; border-left: 4px solid #1976d2; }
        .code { background: #f5f5f5; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 13px; overflow-x: auto; margin: 10px 0; }
        .text-small { font-size: 12px; color: #666; }
        .text-danger { color: #c62828; }
        .text-success { color: #2e7d32; }
        .text-warning { color: #f57c00; }
        .empty { text-align: center; padding: 40px; color: #999; }
        .actions { display: flex; gap: 10px; margin-bottom: 20px; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐛 Debug - Mensagens Agendadas</h1>
        
        <!-- Ações -->
        <div class="actions">
            <a href="?action=test" class="btn btn-success" onclick="return confirm('Processar mensagens pendentes agora?')">▶️ Processar Agora (Teste)</a>
            <a href="?" class="btn btn-primary">🔄 Atualizar Página</a>
            <a href="/conversations" class="btn">← Voltar</a>
        </div>
        
        <?php if ($testResult): ?>
            <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?>">
                <?php if ($testResult['success']): ?>
                    <strong>✅ Processamento manual executado!</strong><br>
                    Total processado: <?= $testResult['count'] ?><br>
                    <pre><?php print_r($testResult['processed']); ?></pre>
                <?php else: ?>
                    <strong>❌ Erro no processamento:</strong><br>
                    <?= htmlspecialchars($testResult['error']) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>Erro ao buscar dados:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Estatísticas -->
        <div class="card">
            <h2>📊 Estatísticas</h2>
            <div class="stats">
                <div class="stat stat-primary">
                    <div class="stat-label">Total</div>
                    <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
                </div>
                <div class="stat stat-warning">
                    <div class="stat-label">Pendentes</div>
                    <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
                </div>
                <div class="stat stat-danger">
                    <div class="stat-label">Atrasadas ⏰</div>
                    <div class="stat-value"><?= $stats['pending_overdue'] ?? 0 ?></div>
                </div>
                <div class="stat stat-success">
                    <div class="stat-label">Enviadas Hoje</div>
                    <div class="stat-value"><?= $stats['sent_today'] ?? 0 ?></div>
                </div>
                <div class="stat stat-danger">
                    <div class="stat-label">Falhas Hoje</div>
                    <div class="stat-value"><?= $stats['failed_today'] ?? 0 ?></div>
                </div>
            </div>
        </div>
        
        <!-- Mensagens Pendentes Atrasadas -->
        <?php if (!empty($pendingMessages)): ?>
            <div class="card">
                <h2>⏰ Mensagens Pendentes ATRASADAS (<?= count($pendingMessages) ?>)</h2>
                <div class="alert alert-warning">
                    <strong>⚠️ ATENÇÃO:</strong> Estas mensagens já passaram do horário agendado e não foram enviadas!<br>
                    Isso indica que o cron job NÃO está rodando corretamente.
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Agendado Para</th>
                            <th>Atrasada</th>
                            <th>Contato</th>
                            <th>Usuário</th>
                            <th>Conteúdo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingMessages as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($msg['scheduled_at'])) ?></td>
                                <td class="text-danger"><strong><?= $msg['minutes_late'] ?> min</strong></td>
                                <td>
                                    <?= htmlspecialchars($msg['contact_name'] ?? 'N/A') ?><br>
                                    <span class="text-small"><?= htmlspecialchars($msg['contact_phone'] ?? '') ?></span>
                                </td>
                                <td class="text-small"><?= htmlspecialchars($msg['user_name'] ?? 'N/A') ?></td>
                                <td class="text-small"><?= substr(htmlspecialchars($msg['content']), 0, 50) ?>...</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Mensagens Recentes -->
        <div class="card">
            <h2>📨 Últimas 10 Mensagens</h2>
            <?php if (!empty($recentMessages)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Criada Em</th>
                            <th>Agendada Para</th>
                            <th>Enviada Em</th>
                            <th>Contato</th>
                            <th>Usuário</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMessages as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><span class="badge badge-<?= $msg['status'] ?>"><?= $msg['status'] ?></span></td>
                                <td class="text-small"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></td>
                                <td class="text-small"><?= date('d/m H:i', strtotime($msg['scheduled_at'])) ?></td>
                                <td class="text-small"><?= $msg['sent_at'] ? date('d/m H:i', strtotime($msg['sent_at'])) : '-' ?></td>
                                <td class="text-small"><?= htmlspecialchars($msg['contact_name'] ?? 'N/A') ?></td>
                                <td class="text-small"><?= htmlspecialchars($msg['user_name'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">Nenhuma mensagem agendada ainda</div>
            <?php endif; ?>
        </div>
        
        <!-- Informações do Script -->
        <div class="card">
            <h2>📁 Script de Processamento</h2>
            <?php if ($scriptExists): ?>
                <div class="alert alert-success">
                    <strong>✅ Script encontrado!</strong><br>
                    Caminho: <code><?= $scriptPath ?></code>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <strong>❌ Script NÃO encontrado!</strong><br>
                    O script <code>public/scripts/process-scheduled-messages.php</code> não existe.
                </div>
            <?php endif; ?>
            
            <h3 style="margin-top: 20px;">🔧 Como Configurar o Cron:</h3>
            <div class="code">* * * * * php <?= $scriptPath ?> >> <?= dirname(__DIR__) ?>/logs/scheduled-messages.log 2>&1</div>
            <p class="text-small">Execute este comando a cada minuto para processar mensagens agendadas.</p>
            
            <h3 style="margin-top: 20px;">🧪 Testar Manualmente:</h3>
            <div class="code">php <?= $scriptPath ?></div>
        </div>
        
        <!-- Logs -->
        <div class="card">
            <h2>📝 Logs</h2>
            <?php foreach ($logs as $desc => $log): ?>
                <div style="margin-bottom: 15px;">
                    <strong><?= $desc ?>:</strong>
                    <?php if ($log['exists']): ?>
                        <span class="<?= $log['minutes_ago'] < 10 ? 'text-success' : 'text-danger' ?>">
                            ✅ Última modificação: <?= $log['minutes_ago'] ?> minuto(s) atrás
                            (<?= date('d/m/Y H:i:s', $log['modified']) ?>)
                        </span>
                        <br>
                        <span class="text-small">
                            Tamanho: <?= number_format($log['size'] / 1024, 2) ?> KB | 
                            Caminho: <?= $log['path'] ?>
                        </span>
                    <?php else: ?>
                        <span class="text-warning">⚠️ Arquivo não existe ainda</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Diagnóstico -->
        <div class="card">
            <h2>🔍 Diagnóstico</h2>
            <?php
            $issues = [];
            
            if (!$scriptExists) {
                $issues[] = "❌ Script de processamento não encontrado";
            }
            
            if ($stats['pending_overdue'] > 0) {
                $issues[] = "❌ Há {$stats['pending_overdue']} mensagem(ns) atrasada(s) - CRON NÃO está rodando!";
            }
            
            foreach ($logs as $desc => $log) {
                if ($log['exists'] && $log['minutes_ago'] > 10 && $desc === 'Mensagens Agendadas') {
                    $issues[] = "⚠️ Log '{$desc}' não foi atualizado há {$log['minutes_ago']} minutos";
                }
            }
            
            if (empty($issues)): ?>
                <div class="alert alert-success">
                    <strong>✅ Tudo funcionando!</strong><br>
                    Nenhum problema detectado.
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <strong>Problemas detectados:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach ($issues as $issue): ?>
                            <li><?= $issue ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

