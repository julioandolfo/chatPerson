<?php
/**
 * Script para criar a view de logs diretamente no Docker
 * 
 * Execute dentro do container:
 *   docker exec -it nome-do-container php /var/www/html/scripts/create-logs-view-in-docker.php
 */

$baseDir = __DIR__ . '/..';
$viewsDir = $baseDir . '/views';
$logsDir = $viewsDir . '/logs';
$logViewFile = $logsDir . '/index.php';

echo "🔄 Criando view de logs no Docker...\n\n";

// Criar diretório se não existir
if (!is_dir($logsDir)) {
    echo "📁 Criando diretório: {$logsDir}\n";
    if (!mkdir($logsDir, 0755, true)) {
        die("❌ Erro ao criar diretório: {$logsDir}\n");
    }
    echo "✅ Diretório criado!\n\n";
}

// Conteúdo do arquivo
$fileContent = <<<'PHP'
<?php
$layout = 'layouts.metronic.app';
$title = 'Logs do Sistema';
$pageTitle = 'Logs do Sistema';

// Content
ob_start();
?>

<!--begin::Card-->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <h3 class="fw-bold m-0">Logs do Sistema</h3>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-sm btn-light-primary" onclick="refreshLogs()">
                <i class="ki-duotone ki-arrows-circle fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Atualizar
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Coluna 1: Lista de Arquivos -->
            <div class="col-md-4 border-end">
                <h4 class="fw-semibold mb-4">Arquivos de Log</h4>
                <div id="logFilesList" class="list-group">
                    <?php foreach ($logFiles as $logFile): ?>
                        <a href="#" class="list-group-item list-group-item-action log-file-item" 
                           data-file="<?= htmlspecialchars($logFile['name']) ?>"
                           data-dir="<?= htmlspecialchars($logFile['dir']) ?>"
                           onclick="loadLog('<?= htmlspecialchars($logFile['name']) ?>', '<?= htmlspecialchars($logFile['dir']) ?>'); return false;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-gray-800"><?= htmlspecialchars($logFile['name']) ?></div>
                                    <div class="text-muted fs-7 mt-1">
                                        <?= htmlspecialchars($logFile['dir']) ?>
                                    </div>
                                </div>
                                <div class="text-end ms-2">
                                    <div class="text-muted fs-7">
                                        <?= number_format($logFile['size'] / 1024, 2) ?> KB
                                    </div>
                                    <div class="text-muted fs-7 mt-1">
                                        <?= date('d/m/Y H:i', $logFile['modified']) ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if (empty($logFiles)): ?>
                        <div class="text-center text-muted py-10">
                            <i class="ki-duotone ki-file fs-3x text-gray-400 mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>Nenhum arquivo de log encontrado</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Coluna 2: Visualização do Log -->
            <div class="col-md-8">
                <div id="logViewerContainer">
                    <div class="text-center text-muted py-20">
                        <i class="ki-duotone ki-file fs-3x text-gray-400 mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div>Selecione um arquivo de log para visualizar</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Card-->

<script>
let currentFile = null;
let currentDir = null;

function refreshLogs() {
    window.location.reload();
}

function loadLog(file, dir) {
    currentFile = file;
    currentDir = dir;
    
    // Atualizar seleção visual
    document.querySelectorAll('.log-file-item').forEach(item => {
        item.classList.remove('active');
    });
    event.target.closest('.log-file-item').classList.add('active');
    
    // Mostrar loading
    document.getElementById('logViewerContainer').innerHTML = `
        <div class="text-center py-10">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <div class="text-muted mt-3">Carregando log...</div>
        </div>
    `;
    
    // Carregar log
    const url = new URL('<?= \App\Helpers\Url::to("/logs/view") ?>', window.location.origin);
    url.searchParams.set('file', file);
    url.searchParams.set('dir', dir);
    url.searchParams.set('lines', document.getElementById('logLines')?.value || 500);
    const search = document.getElementById('logSearch')?.value;
    if (search) {
        url.searchParams.set('search', search);
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('logViewerContainer').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="ki-duotone ki-information-5 fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        ${escapeHtml(data.error)}
                    </div>
                `;
                return;
            }
            
            renderLogViewer(data);
        })
        .catch(error => {
            console.error('Erro ao carregar log:', error);
            document.getElementById('logViewerContainer').innerHTML = `
                <div class="alert alert-danger">
                    <i class="ki-duotone ki-information-5 fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Erro ao carregar log: ${escapeHtml(error.message)}
                </div>
            `;
        });
}

function renderLogViewer(data) {
    const container = document.getElementById('logViewerContainer');
    
    const fileSizeKB = (data.fileSize / 1024).toFixed(2);
    const fileSizeMB = (data.fileSize / (1024 * 1024)).toFixed(2);
    const fileSizeStr = data.fileSize > 1024 * 1024 ? `${fileSizeMB} MB` : `${fileSizeKB} KB`;
    const lastModified = new Date(data.lastModified * 1000).toLocaleString('pt-BR');
    
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-semibold mb-1">${escapeHtml(data.file)}</h4>
                <div class="text-muted fs-7">
                    ${fileSizeStr} • ${data.totalLines} linhas • Modificado em ${lastModified}
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-light-primary" onclick="downloadLog('${escapeHtml(data.file)}', '${escapeHtml(data.dir)}')">
                    <i class="ki-duotone ki-file-down fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Download
                </button>
                <button type="button" class="btn btn-sm btn-light-danger" onclick="clearLog('${escapeHtml(data.file)}', '${escapeHtml(data.dir)}')">
                    <i class="ki-duotone ki-trash fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Limpar
                </button>
            </div>
        </div>
        
        <div class="mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Buscar no log</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="ki-duotone ki-magnifier fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <input type="text" id="logSearch" class="form-control form-control-solid" 
                               placeholder="Digite para buscar..." 
                               onkeyup="if(event.key === 'Enter') loadLog('${escapeHtml(data.file)}', '${escapeHtml(data.dir)}')">
                        <button type="button" class="btn btn-primary" onclick="loadLog('${escapeHtml(data.file)}', '${escapeHtml(data.dir)}')">
                            Buscar
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Últimas linhas</label>
                    <select id="logLines" class="form-select form-select-solid" onchange="loadLog('${escapeHtml(data.file)}', '${escapeHtml(data.dir)}')">
                        <option value="100">100 linhas</option>
                        <option value="500" selected>500 linhas</option>
                        <option value="1000">1.000 linhas</option>
                        <option value="5000">5.000 linhas</option>
                        <option value="10000">10.000 linhas</option>
                        <option value="0">Todas</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body p-0">
                <div class="log-content" style="max-height: 600px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; background: #1e1e1e; color: #d4d4d4; padding: 15px;">
    `;
    
    if (data.lines.length === 0) {
        html += `
            <div class="text-center text-muted py-10">
                Nenhuma linha encontrada${data.search ? ' com o termo de busca' : ''}
            </div>
        `;
    } else {
        data.lines.forEach(line => {
            const isError = line.content.includes('[ERROR]') || line.content.includes('ERROR');
            const isWarning = line.content.includes('[WARNING]') || line.content.includes('WARNING');
            const isDebug = line.content.includes('[DEBUG]') || line.content.includes('DEBUG');
            
            let lineClass = '';
            if (isError) lineClass = 'text-danger';
            else if (isWarning) lineClass = 'text-warning';
            else if (isDebug) lineClass = 'text-info';
            
            html += `
                <div class="log-line ${lineClass}" style="margin-bottom: 2px; white-space: pre-wrap; word-break: break-word;">
                    <span class="text-muted" style="margin-right: 10px;">${line.number.toString().padStart(6, '0')}</span>
                    ${line.content}
                </div>
            `;
        });
    }
    
    html += `
                </div>
            </div>
        </div>
        
        <div class="text-muted fs-7 mt-2">
            Mostrando ${data.showingLines} de ${data.totalLines} linhas
        </div>
    `;
    
    container.innerHTML = html;
}

function downloadLog(file, dir) {
    const url = new URL('<?= \App\Helpers\Url::to("/logs/download") ?>', window.location.origin);
    url.searchParams.set('file', file);
    url.searchParams.set('dir', dir);
    window.open(url, '_blank');
}

function clearLog(file, dir) {
    if (!confirm(`Tem certeza que deseja limpar o arquivo "${file}"?\n\nEsta ação não pode ser desfeita.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('dir', dir);
    
    fetch('<?= \App\Helpers\Url::to("/logs/clear") ?>', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Log limpo',
                    text: 'O arquivo de log foi limpo com sucesso.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#009ef7'
                });
                refreshLogs();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: data.error || 'Erro ao limpar log',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#009ef7'
                });
            }
        })
        .catch(error => {
            console.error('Erro ao limpar log:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao limpar log: ' + error.message,
                confirmButtonText: 'OK',
                confirmButtonColor: '#009ef7'
            });
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

PHP;

// Criar arquivo
echo "📝 Criando arquivo: {$logViewFile}\n";
if (file_put_contents($logViewFile, $fileContent) === false) {
    die("❌ Erro ao criar arquivo: {$logViewFile}\n");
}

echo "✅ Arquivo criado com sucesso!\n";
echo "📊 Tamanho: " . number_format(filesize($logViewFile)) . " bytes\n";
echo "\n✅ View de logs criada com sucesso!\n";
echo "🌐 Acesse: /logs\n";

