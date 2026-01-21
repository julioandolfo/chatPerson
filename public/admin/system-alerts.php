<?php
/**
 * Painel de Alertas do Sistema
 * Visualizar e gerenciar alertas críticos do sistema
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Auth;

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

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $alertId = (int)($_POST['alert_id'] ?? 0);
    
    if ($action === 'mark_read' && $alertId > 0) {
        Database::update(
            'system_alerts',
            ['is_read' => true, 'read_by' => $user['id'], 'read_at' => date('Y-m-d H:i:s')],
            ['id' => $alertId]
        );
        header('Location: system-alerts.php?msg=read');
        exit;
    }
    
    if ($action === 'mark_resolved' && $alertId > 0) {
        Database::update(
            'system_alerts',
            ['is_resolved' => true, 'resolved_by' => $user['id'], 'resolved_at' => date('Y-m-d H:i:s')],
            ['id' => $alertId]
        );
        header('Location: system-alerts.php?msg=resolved');
        exit;
    }
}

// Buscar alertas
$filter = $_GET['filter'] ?? 'active';

$sql = "SELECT * FROM system_alerts";
$params = [];

if ($filter === 'active') {
    $sql .= " WHERE is_resolved = FALSE";
} elseif ($filter === 'critical') {
    $sql .= " WHERE severity = 'critical' AND is_resolved = FALSE";
} elseif ($filter === 'resolved') {
    $sql .= " WHERE is_resolved = TRUE";
}

$sql .= " ORDER BY severity DESC, created_at DESC LIMIT 100";

$alerts = Database::fetchAll($sql, $params);

// Contadores
$totalActive = (int)Database::fetch("SELECT COUNT(*) as count FROM system_alerts WHERE is_resolved = FALSE")['count'];
$totalCritical = (int)Database::fetch("SELECT COUNT(*) as count FROM system_alerts WHERE severity = 'critical' AND is_resolved = FALSE")['count'];
$totalResolved = (int)Database::fetch("SELECT COUNT(*) as count FROM system_alerts WHERE is_resolved = TRUE")['count'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas do Sistema - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .alert-critical { border-left: 4px solid #dc3545; background-color: #fff5f5; }
        .alert-warning { border-left: 4px solid #ffc107; background-color: #fffbf0; }
        .alert-info { border-left: 4px solid #0dcaf0; background-color: #f0f9ff; }
        .alert-card { transition: all 0.2s; }
        .alert-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .badge-critical { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; }
        .badge-info { background-color: #0dcaf0; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard.php">
                <i class="bi bi-shield-exclamation"></i> Alertas do Sistema
            </a>
            <span class="navbar-text text-white">
                <?php echo htmlspecialchars($user['name']); ?>
            </span>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Cabeçalho -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">
                    <i class="bi bi-bell-fill"></i> Alertas do Sistema
                </h1>
                <p class="text-muted">Monitore problemas críticos e alertas do sistema</p>
            </div>
        </div>

        <!-- Mensagens de sucesso -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                if ($_GET['msg'] === 'read') echo '<i class="bi bi-check-circle"></i> Alerta marcado como lido!';
                if ($_GET['msg'] === 'resolved') echo '<i class="bi bi-check-circle-fill"></i> Alerta marcado como resolvido!';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Cards de estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="text-muted mb-0">Alertas Ativos</h6>
                                <h2 class="mb-0"><?php echo $totalActive; ?></h2>
                            </div>
                            <div class="text-primary fs-1">
                                <i class="bi bi-exclamation-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="text-muted mb-0">Críticos</h6>
                                <h2 class="mb-0 text-danger"><?php echo $totalCritical; ?></h2>
                            </div>
                            <div class="text-danger fs-1">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="text-muted mb-0">Resolvidos</h6>
                                <h2 class="mb-0 text-success"><?php echo $totalResolved; ?></h2>
                            </div>
                            <div class="text-success fs-1">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="btn-group" role="group">
                    <a href="?filter=active" class="btn btn-sm <?php echo $filter === 'active' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        <i class="bi bi-list-ul"></i> Ativos (<?php echo $totalActive; ?>)
                    </a>
                    <a href="?filter=critical" class="btn btn-sm <?php echo $filter === 'critical' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                        <i class="bi bi-exclamation-triangle"></i> Críticos (<?php echo $totalCritical; ?>)
                    </a>
                    <a href="?filter=resolved" class="btn btn-sm <?php echo $filter === 'resolved' ? 'btn-success' : 'btn-outline-success'; ?>">
                        <i class="bi bi-check-circle"></i> Resolvidos (<?php echo $totalResolved; ?>)
                    </a>
                </div>
            </div>
        </div>

        <!-- Lista de alertas -->
        <?php if (empty($alerts)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                    <h5 class="mt-3">Nenhum alerta encontrado</h5>
                    <p class="text-muted">Tudo está funcionando perfeitamente!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($alerts as $alert): ?>
                <div class="card border-0 shadow-sm mb-3 alert-card alert-<?php echo $alert['severity']; ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <!-- Ícone de severidade -->
                            <div class="me-3">
                                <?php if ($alert['severity'] === 'critical'): ?>
                                    <i class="bi bi-exclamation-triangle-fill text-danger fs-2"></i>
                                <?php elseif ($alert['severity'] === 'warning'): ?>
                                    <i class="bi bi-exclamation-circle-fill text-warning fs-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-info-circle-fill text-info fs-2"></i>
                                <?php endif; ?>
                            </div>

                            <!-- Conteúdo do alerta -->
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div>
                                        <h5 class="mb-1">
                                            <?php echo htmlspecialchars($alert['title']); ?>
                                            <?php if ($alert['is_read']): ?>
                                                <i class="bi bi-eye-fill text-muted ms-2" title="Lido"></i>
                                            <?php endif; ?>
                                        </h5>
                                        <div class="mb-2">
                                            <span class="badge badge-<?php echo $alert['severity']; ?>">
                                                <?php echo strtoupper($alert['severity']); ?>
                                            </span>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($alert['type']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> 
                                        <?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?>
                                    </small>
                                </div>

                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($alert['message'])); ?></p>

                                <!-- Ações -->
                                <div class="d-flex gap-2">
                                    <?php if ($alert['action_url']): ?>
                                        <a href="<?php echo htmlspecialchars($alert['action_url']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Resolver Problema
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!$alert['is_read']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-eye"></i> Marcar como Lido
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!$alert['is_resolved']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="mark_resolved">
                                            <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-check-circle"></i> Marcar como Resolvido
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <!-- Informações de resolução -->
                                <?php if ($alert['is_resolved']): ?>
                                    <div class="mt-3 pt-3 border-top">
                                        <small class="text-success">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <strong>Resolvido</strong> em 
                                            <?php echo date('d/m/Y H:i', strtotime($alert['resolved_at'])); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
