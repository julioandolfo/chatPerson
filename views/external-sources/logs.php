<?php
$layout = 'layouts.metronic.app';
$pageTitle = 'Logs de Sincronização - ' . htmlspecialchars($source['name']);

ob_start();
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <h2 class="fw-bold">
                <i class="ki-duotone ki-time fs-1 me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Logs de Sincronização
            </h2>
        </div>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/external-sources') ?>" class="btn btn-light-primary btn-sm">
                <i class="ki-duotone ki-arrow-left fs-4"></i> Voltar
            </a>
        </div>
    </div>
    <div class="card-body">
        
        <!-- Informações da Fonte -->
        <div class="row mb-8">
            <div class="col-md-3">
                <div class="bg-light-primary rounded p-4 text-center">
                    <div class="fs-2 fw-bold text-primary"><?= htmlspecialchars($source['name']) ?></div>
                    <div class="text-muted fs-7">Nome da Fonte</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-light-info rounded p-4 text-center">
                    <div class="fs-4 fw-bold text-info"><?= strtoupper($source['type']) ?></div>
                    <div class="text-muted fs-7">Tipo</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-light-warning rounded p-4 text-center">
                    <div class="fs-4 fw-bold text-warning"><?= ucfirst($source['sync_frequency'] ?? 'manual') ?></div>
                    <div class="text-muted fs-7">Frequência</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-light-success rounded p-4 text-center">
                    <div class="fs-6 fw-bold text-success"><?= $source['last_sync_at'] ? date('d/m/Y H:i', strtotime($source['last_sync_at'])) : 'Nunca' ?></div>
                    <div class="text-muted fs-7">Última Sincronização</div>
                </div>
            </div>
        </div>
        
        <!-- Alerta se cron não está funcionando -->
        <?php
        $lastSync = $source['last_sync_at'] ? strtotime($source['last_sync_at']) : 0;
        $now = time();
        $diffHours = ($now - $lastSync) / 3600;
        $syncFrequency = $source['sync_frequency'] ?? 'manual';
        
        $shouldHaveSynced = false;
        if ($syncFrequency === 'hourly' && $diffHours > 2) $shouldHaveSynced = true;
        if ($syncFrequency === 'daily' && $diffHours > 26) $shouldHaveSynced = true;
        if ($syncFrequency === 'weekly' && $diffHours > 170) $shouldHaveSynced = true;
        
        if ($shouldHaveSynced && $syncFrequency !== 'manual'):
        ?>
        <div class="alert alert-danger d-flex align-items-center mb-8">
            <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-4">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div>
                <h4 class="mb-1 text-danger">Cron Job não está funcionando!</h4>
                <span>A fonte está configurada como <strong><?= $syncFrequency ?></strong> mas a última sincronização foi há <strong><?= round($diffHours) ?> horas</strong>.</span>
                <br>
                <small>Configure o cron job no servidor: <code>0 * * * * docker exec CONTAINER php /var/www/html/public/scripts/process-external-sources.php</code></small>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabela de Logs -->
        <div class="table-responsive">
            <table class="table table-row-bordered table-hover gy-5">
                <thead>
                    <tr class="fw-bold fs-6 text-gray-800 border-bottom border-gray-200">
                        <th>Data/Hora</th>
                        <th>Status</th>
                        <th>Buscados</th>
                        <th>Criados</th>
                        <th>Atualizados</th>
                        <th>Falhas</th>
                        <th>Tempo</th>
                        <th>Mensagem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-10">
                            <i class="ki-duotone ki-information fs-3x text-gray-400 mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div>Nenhum log de sincronização encontrado.</div>
                            <small>Execute uma sincronização manual para gerar logs.</small>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= date('d/m/Y', strtotime($log['started_at'])) ?></div>
                            <div class="text-muted fs-7"><?= date('H:i:s', strtotime($log['started_at'])) ?></div>
                        </td>
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                            <span class="badge badge-light-success">Sucesso</span>
                            <?php elseif ($log['status'] === 'error'): ?>
                            <span class="badge badge-light-danger">Erro</span>
                            <?php else: ?>
                            <span class="badge badge-light-warning"><?= ucfirst($log['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $log['records_fetched'] ?? 0 ?></td>
                        <td class="text-center text-success fw-bold"><?= $log['records_created'] ?? 0 ?></td>
                        <td class="text-center text-info"><?= $log['records_updated'] ?? 0 ?></td>
                        <td class="text-center text-danger"><?= $log['records_failed'] ?? 0 ?></td>
                        <td>
                            <?php 
                            $ms = $log['execution_time_ms'] ?? 0;
                            if ($ms > 1000) {
                                echo round($ms/1000, 1) . 's';
                            } else {
                                echo $ms . 'ms';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($log['error_message'])): ?>
                            <span class="text-danger" title="<?= htmlspecialchars($log['error_message']) ?>">
                                <?= htmlspecialchars(substr($log['error_message'], 0, 50)) ?>...
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
