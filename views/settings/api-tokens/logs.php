<?php
/**
 * View: Logs de Requisições da API
 */

use App\Helpers\Url;

$pageTitle = 'Logs de API';
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h1 class="fw-bold mb-1">Logs de Requisições da API</h1>
            <p class="text-muted mb-0">Acompanhe todas as requisições feitas aos seus tokens</p>
        </div>
        <a href="<?= Url::to('/settings/api-tokens') ?>" class="btn btn-light-primary">
            <i class="ki-duotone ki-arrow-left fs-2"></i>
            Voltar para Tokens
        </a>
    </div>
    
    <!-- Filtros -->
    <div class="card mb-5">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fs-7 fw-bold">Token</label>
                    <select class="form-select form-select-sm" id="filter_token" onchange="filterLogs()">
                        <option value="">Todos os tokens</option>
                        <?php foreach ($tokens as $token): ?>
                            <option value="<?= $token['id'] ?>" <?= ($selectedTokenId == $token['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($token['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-bold">Método</label>
                    <select class="form-select form-select-sm" id="filter_method" onchange="filterLogsClient()">
                        <option value="">Todos</option>
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="DELETE">DELETE</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-bold">Status</label>
                    <select class="form-select form-select-sm" id="filter_status" onchange="filterLogsClient()">
                        <option value="">Todos</option>
                        <option value="2">2xx (Sucesso)</option>
                        <option value="4">4xx (Erro Cliente)</option>
                        <option value="5">5xx (Erro Servidor)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-bold">Limite</label>
                    <select class="form-select form-select-sm" id="filter_limit" onchange="filterLogs()">
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Logs -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Requisições Recentes</h3>
            <div class="card-toolbar">
                <button type="button" class="btn btn-sm btn-light-primary" onclick="refreshLogs()">
                    <i class="ki-duotone ki-arrows-circle fs-2"><span class="path1"></span><span class="path2"></span></i>
                    Atualizar
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-row-bordered table-hover align-middle gs-0 gy-3">
                    <thead>
                        <tr class="fw-bold text-muted bg-light">
                            <th class="min-w-50px">Status</th>
                            <th class="min-w-80px">Método</th>
                            <th class="min-w-200px">Endpoint</th>
                            <th class="min-w-120px">IP</th>
                            <th class="min-w-80px">Tempo</th>
                            <th class="min-w-150px">Data/Hora</th>
                            <th class="min-w-50px text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="logs_table_body">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-10 text-muted">
                                    Nenhuma requisição registrada ainda
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $statusClass = 'success';
                                if ($log['response_code'] >= 400 && $log['response_code'] < 500) {
                                    $statusClass = 'warning';
                                } elseif ($log['response_code'] >= 500) {
                                    $statusClass = 'danger';
                                }
                                
                                $methodClass = [
                                    'GET' => 'primary',
                                    'POST' => 'success',
                                    'PUT' => 'warning',
                                    'DELETE' => 'danger'
                                ][$log['method']] ?? 'secondary';
                                ?>
                                <tr data-method="<?= $log['method'] ?>" data-status="<?= substr($log['response_code'], 0, 1) ?>">
                                    <td>
                                        <span class="badge badge-light-<?= $statusClass ?> fs-7">
                                            <?= $log['response_code'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-<?= $methodClass ?> fs-7 fw-bold">
                                            <?= $log['method'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code class="fs-7"><?= htmlspecialchars($log['endpoint']) ?></code>
                                    </td>
                                    <td>
                                        <span class="text-muted fs-7"><?= $log['ip_address'] ?? 'N/A' ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light fs-7"><?= $log['execution_time_ms'] ?>ms</span>
                                    </td>
                                    <td>
                                        <span class="text-muted fs-7">
                                            <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-light-primary" 
                                                onclick='viewLogDetails(<?= json_encode($log, JSON_HEX_APOS) ?>)'>
                                            <i class="ki-duotone ki-eye fs-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Detalhes do Log -->
<div class="modal fade" id="kt_modal_log_details" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Detalhes da Requisição</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="row g-5">
                    <!-- Info Geral -->
                    <div class="col-12">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge badge-light me-2" id="log_method"></span>
                            <code class="fs-6" id="log_endpoint"></code>
                            <span class="badge ms-auto" id="log_status"></span>
                        </div>
                        <div class="text-muted fs-7">
                            <span id="log_date"></span> · 
                            <span id="log_ip"></span> · 
                            <span id="log_time"></span>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3">Request Headers</h5>
                        <pre class="bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;"><code id="log_request_headers"></code></pre>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-3">Request Body</h5>
                        <pre class="bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;"><code id="log_request_body"></code></pre>
                    </div>
                    
                    <div class="col-12">
                        <h5 class="fw-bold mb-3">Response Body</h5>
                        <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;"><code id="log_response_body"></code></pre>
                    </div>
                    
                    <div class="col-12" id="log_error_container" style="display: none;">
                        <h5 class="fw-bold mb-3 text-danger">Erro</h5>
                        <div class="alert alert-danger">
                            <code id="log_error"></code>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <h5 class="fw-bold mb-3">User Agent</h5>
                        <p class="text-muted fs-7" id="log_user_agent"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Ver detalhes do log
function viewLogDetails(log) {
    document.getElementById('log_method').textContent = log.method;
    document.getElementById('log_method').className = `badge badge-light-${getMethodClass(log.method)}`;
    
    document.getElementById('log_endpoint').textContent = log.endpoint;
    
    document.getElementById('log_status').textContent = log.response_code;
    document.getElementById('log_status').className = `badge badge-light-${getStatusClass(log.response_code)}`;
    
    document.getElementById('log_date').textContent = new Date(log.created_at).toLocaleString('pt-BR');
    document.getElementById('log_ip').textContent = `IP: ${log.ip_address || 'N/A'}`;
    document.getElementById('log_time').textContent = `${log.execution_time_ms}ms`;
    
    document.getElementById('log_request_headers').textContent = formatJson(log.request_headers) || 'N/A';
    document.getElementById('log_request_body').textContent = formatJson(log.request_body) || 'Vazio';
    document.getElementById('log_response_body').textContent = formatJson(log.response_body) || 'Vazio';
    document.getElementById('log_user_agent').textContent = log.user_agent || 'N/A';
    
    if (log.error_message) {
        document.getElementById('log_error').textContent = log.error_message;
        document.getElementById('log_error_container').style.display = 'block';
    } else {
        document.getElementById('log_error_container').style.display = 'none';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_log_details'));
    modal.show();
}

function getMethodClass(method) {
    const classes = {
        'GET': 'primary',
        'POST': 'success',
        'PUT': 'warning',
        'DELETE': 'danger'
    };
    return classes[method] || 'secondary';
}

function getStatusClass(code) {
    if (code >= 200 && code < 300) return 'success';
    if (code >= 400 && code < 500) return 'warning';
    if (code >= 500) return 'danger';
    return 'secondary';
}

function formatJson(str) {
    if (!str) return '';
    try {
        const obj = JSON.parse(str);
        return JSON.stringify(obj, null, 2);
    } catch (e) {
        return str;
    }
}

// Filtrar logs (servidor)
function filterLogs() {
    const tokenId = document.getElementById('filter_token').value;
    const limit = document.getElementById('filter_limit').value;
    
    let url = '<?= Url::to('/settings/api-tokens/logs') ?>?limit=' + limit;
    if (tokenId) {
        url += '&token_id=' + tokenId;
    }
    
    window.location.href = url;
}

// Filtrar logs (cliente - sem reload)
function filterLogsClient() {
    const method = document.getElementById('filter_method').value;
    const status = document.getElementById('filter_status').value;
    
    const rows = document.querySelectorAll('#logs_table_body tr[data-method]');
    
    rows.forEach(row => {
        let show = true;
        
        if (method && row.dataset.method !== method) {
            show = false;
        }
        
        if (status && row.dataset.status !== status) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

// Atualizar logs
function refreshLogs() {
    location.reload();
}
</script>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../../layouts/metronic/app.php'; ?>
