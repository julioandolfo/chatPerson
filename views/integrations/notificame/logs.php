<?php
$layout = 'layouts.metronic.app';
$title = 'Logs Notificame';

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Logs Notificame (últimas linhas)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($lines)): ?>
            <div class="alert alert-info">Nenhuma linha contendo "Notificame" encontrada em storage/logs/laravel.log</div>
        <?php else: ?>
            <pre class="bg-dark text-white p-3" style="max-height: 600px; overflow: auto;"><?php
                foreach ($lines as $line) {
                    echo htmlspecialchars($line) . "\n";
                }
            ?></pre>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/metronic/app.php';
?>

