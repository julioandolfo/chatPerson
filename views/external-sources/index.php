<?php
$layout = 'layouts.metronic.app';
$title = 'Fontes de Dados Externas';

ob_start();
?>
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h2 class="fw-bold">Fontes de Dados Externas</h2>
        </div>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/external-sources/create') ?>" class="btn btn-sm btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Fonte
            </a>
        </div>
    </div>
    <div class="card-body pt-0">
        
        <?php if (empty($sources)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-technology-2 fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma fonte configurada</h3>
                <div class="text-gray-500 fs-6 mb-7">Conecte bancos de dados externos para sincronizar contatos automaticamente</div>
                <a href="<?= \App\Helpers\Url::to('/external-sources/create') ?>" class="btn btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Configurar Primeira Fonte
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Banco</th>
                            <th>Tabela</th>
                            <th>Registros</th>
                            <th>Frequência</th>
                            <th>Última Sync</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($sources as $source): ?>
                        <?php
                        $connectionConfig = json_decode($source['connection_config'], true);
                        $statusBadge = [
                            'active' => 'success',
                            'inactive' => 'secondary',
                            'error' => 'danger'
                        ][$source['status']] ?? 'secondary';
                        
                        $syncFreqText = [
                            'manual' => 'Manual',
                            'hourly' => 'A cada hora',
                            'daily' => 'Diária',
                            'weekly' => 'Semanal'
                        ][$source['sync_frequency']] ?? 'Manual';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($source['name']) ?></div>
                            </td>
                            <td>
                                <span class="badge badge-light-info"><?= strtoupper($source['type']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($connectionConfig['database'] ?? '-') ?></td>
                            <td>
                                <span class="text-muted font-monospace fs-7"><?= htmlspecialchars($source['table_name'] ?? '-') ?></span>
                            </td>
                            <td>
                                <span class="badge badge-light-primary"><?= number_format($source['total_records'] ?? 0) ?></span>
                            </td>
                            <td><?= $syncFreqText ?></td>
                            <td>
                                <?php if ($source['last_sync_at']): ?>
                                    <span class="text-muted fs-7"><?= date('d/m/Y H:i', strtotime($source['last_sync_at'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-light-<?= $statusBadge ?>">
                                    <?= ucfirst($source['status']) ?>
                                </span>
                                <?php if ($source['last_sync_status'] === 'error'): ?>
                                    <i class="ki-duotone ki-information-5 fs-6 text-danger ms-1" 
                                       title="<?= htmlspecialchars($source['last_sync_message'] ?? '') ?>">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-light-success" onclick="syncNow(<?= $source['id'] ?>)" title="Sincronizar agora">
                                    <i class="ki-duotone ki-arrows-circle fs-6"></i>
                                </button>
                                <button class="btn btn-sm btn-light-danger" onclick="deleteSource(<?= $source['id'] ?>)" title="Deletar">
                                    <i class="ki-duotone ki-trash fs-6"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
function syncNow(sourceId) {
    if (!confirm('Deseja sincronizar esta fonte agora?')) return;
    
    const listId = prompt('Digite o ID da lista para sincronizar:');
    if (!listId) return;
    
    toastr.info('Sincronizando...');
    
    fetch(`/external-sources/${sourceId}/sync`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({list_id: parseInt(listId)})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            const stats = result.stats;
            toastr.success(`Sincronizado! ${stats.records_created} novos, ${stats.records_updated} atualizados`);
            location.reload();
        } else {
            toastr.error(result.message);
        }
    })
    .catch(err => toastr.error('Erro ao sincronizar'));
}

function deleteSource(sourceId) {
    if (!confirm('Deseja deletar esta fonte? Isso não afetará os contatos já importados.')) return;
    
    fetch(`/external-sources/${sourceId}`, {
        method: 'DELETE'
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            toastr.success('Fonte removida!');
            location.reload();
        } else {
            toastr.error(result.message);
        }
    })
    .catch(err => toastr.error('Erro ao deletar'));
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
