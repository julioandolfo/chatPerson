<?php
$layout = 'layouts.metronic.app';
$title = 'Logs NotificaMe';
$pageTitle = 'Logs NotificaMe - Webhook';

// Ler últimas linhas do log que contenham "Notificame"
$logFile = storage_path('logs/laravel.log');
$lines = [];
$totalLines = 0;
$fileSize = 0;
$lastModified = 0;
$maxLines = 500; // Padrão

if (isset($_GET['lines'])) {
    $maxLines = (int)$_GET['lines'];
    if ($maxLines === 0) $maxLines = 999999; // Todas as linhas
}

$searchTerm = $_GET['search'] ?? '';

if (file_exists($logFile)) {
    $fileSize = filesize($logFile);
    $lastModified = filemtime($logFile);
    $fileLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $totalLines = count($fileLines);
    $fileLines = array_reverse($fileLines); // Inverter para ter as mais recentes primeiro
    
    $count = 0;
    foreach ($fileLines as $line) {
        // Filtrar por "Notificame"
        if (stripos($line, 'Notificame') === false) continue;
        
        // Filtrar por termo de busca adicional, se fornecido
        if ($searchTerm && stripos($line, $searchTerm) === false) continue;
        
        $lines[] = $line;
        $count++;
        if ($count >= $maxLines) break;
    }
}

$fileSizeKB = number_format($fileSize / 1024, 2);
$fileSizeMB = number_format($fileSize / (1024 * 1024), 2);
$fileSizeStr = $fileSize > 1024 * 1024 ? "{$fileSizeMB} MB" : "{$fileSizeKB} KB";
$lastModifiedStr = date('d/m/Y H:i:s', $lastModified);

// Content
ob_start();
?>

<!--begin::Card-->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <h3 class="fw-bold m-0">Logs NotificaMe (Webhook)</h3>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-sm btn-light-primary" onclick="window.location.reload()">
                <i class="ki-duotone ki-arrows-circle fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Atualizar
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Informações do arquivo -->
        <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded">
            <div>
                <h5 class="fw-semibold mb-1">laravel.log (Filtrado: Notificame)</h5>
                <div class="text-muted fs-7">
                    <?= $fileSizeStr ?> • <?= number_format($totalLines) ?> linhas totais • Modificado em <?= $lastModifiedStr ?>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Buscar no log</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="ki-duotone ki-magnifier fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </span>
                        <input type="text" name="search" class="form-control form-control-solid" 
                               placeholder="Digite para buscar..." 
                               value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </div>
                    <div class="form-text">Busca adicional dentro das linhas que contêm "Notificame"</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Quantidade de linhas</label>
                    <select name="lines" class="form-select form-select-solid" onchange="this.form.submit()">
                        <option value="100" <?= $maxLines == 100 ? 'selected' : '' ?>>100 linhas</option>
                        <option value="500" <?= $maxLines == 500 ? 'selected' : '' ?>>500 linhas</option>
                        <option value="1000" <?= $maxLines == 1000 ? 'selected' : '' ?>>1.000 linhas</option>
                        <option value="5000" <?= $maxLines == 5000 ? 'selected' : '' ?>>5.000 linhas</option>
                        <option value="0" <?= $maxLines >= 999999 ? 'selected' : '' ?>>Todas</option>
                    </select>
                </div>
            </form>
        </div>
        
        <?php if (empty($lines)): ?>
            <div class="alert alert-warning">
                <i class="ki-duotone ki-information-5 fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Nenhuma linha encontrada com os filtros aplicados.
                <?php if ($searchTerm): ?>
                    <br>Termo de busca: <strong><?= htmlspecialchars($searchTerm) ?></strong>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <div class="text-muted fs-7">
                    Mostrando <?= count($lines) ?> linhas (filtradas por "Notificame"<?= $searchTerm ? ' e "' . htmlspecialchars($searchTerm) . '"' : '' ?>)
                </div>
            </div>
            
            <div class="card">
                <div class="card-body p-0">
                    <div class="log-content" style="max-height: 700px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; background: #1e1e1e; color: #d4d4d4; padding: 15px;">
                        <?php foreach ($lines as $index => $line): ?>
                            <?php
                            $isError = stripos($line, 'ERROR') !== false || stripos($line, 'erro') !== false || stripos($line, 'Exception') !== false;
                            $isWarning = stripos($line, 'WARNING') !== false || stripos($line, 'warning') !== false;
                            $isInfo = stripos($line, 'INFO') !== false || stripos($line, 'info') !== false;
                            $isSuccess = stripos($line, 'success') !== false || stripos($line, 'sucesso') !== false || stripos($line, '✅') !== false;
                            
                            $lineClass = '';
                            if ($isError) $lineClass = 'text-danger';
                            elseif ($isWarning) $lineClass = 'text-warning';
                            elseif ($isSuccess) $lineClass = 'text-success';
                            elseif ($isInfo) $lineClass = 'text-info';
                            
                            // Highlight do termo de busca
                            $displayLine = htmlspecialchars($line);
                            if ($searchTerm) {
                                $displayLine = str_ireplace(
                                    $searchTerm, 
                                    '<span style="background: #ffeb3b; color: #000; padding: 2px 4px; border-radius: 3px;">' . htmlspecialchars($searchTerm) . '</span>', 
                                    $displayLine
                                );
                            }
                            ?>
                            <div class="log-line <?= $lineClass ?>" style="margin-bottom: 3px; white-space: pre-wrap; word-break: break-word; line-height: 1.5;">
                                <span class="text-muted" style="margin-right: 10px; user-select: none;"><?= str_pad($index + 1, 6, '0', STR_PAD_LEFT) ?></span>
                                <?= $displayLine ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="text-muted fs-7 mt-3">
                <strong>Dica:</strong> Use o filtro de busca acima para encontrar mensagens específicas (ex: "erro", "webhook", "instagram", etc)
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<style>
.log-line {
    transition: background 0.1s;
}
.log-line:hover {
    background: rgba(255, 255, 255, 0.05);
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/metronic/app.php';
?>
