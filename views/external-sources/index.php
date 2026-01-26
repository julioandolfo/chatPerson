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
                                <a href="<?= \App\Helpers\Url::to('/external-sources/' . $source['id'] . '/logs') ?>" class="btn btn-sm btn-light-info" title="Ver Logs">
                                    <i class="ki-duotone ki-time fs-6"><span class="path1"></span><span class="path2"></span></i>
                                </a>
                                <a href="<?= \App\Helpers\Url::to('/external-sources/' . $source['id'] . '/edit') ?>" class="btn btn-sm btn-light-primary" title="Editar">
                                    <i class="ki-duotone ki-pencil fs-6"><span class="path1"></span><span class="path2"></span></i>
                                </a>
                                <button class="btn btn-sm btn-light-success" onclick="openSyncModal(<?= $source['id'] ?>, '<?= htmlspecialchars($source['name']) ?>')" title="Sincronizar agora">
                                    <i class="ki-duotone ki-arrows-circle fs-6"><span class="path1"></span><span class="path2"></span></i>
                                </button>
                                <button class="btn btn-sm btn-light-danger" onclick="deleteSource(<?= $source['id'] ?>)" title="Deletar">
                                    <i class="ki-duotone ki-trash fs-6"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
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

<!-- Modal de Sincronização -->
<div class="modal fade" id="sync_modal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sincronizar Fonte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="sync_close_btn"></button>
            </div>
            <div class="modal-body">
                <!-- Formulário de seleção -->
                <div id="sync_form_section">
                    <div class="mb-5">
                        <p class="text-gray-600">Fonte: <strong id="sync_source_name"></strong></p>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Selecione a Lista de Destino</label>
                        <select class="form-select" id="sync_list_id">
                            <option value="">Carregando listas...</option>
                        </select>
                        <div class="form-text">Os contatos serão importados para esta lista</div>
                    </div>
                    
                    <div class="mb-5">
                        <a href="/contact-lists/create" target="_blank" class="text-primary">
                            <i class="ki-duotone ki-plus fs-6"><span class="path1"></span><span class="path2"></span></i>
                            Criar nova lista
                        </a>
                    </div>
                </div>
                
                <!-- Progresso da sincronização -->
                <div id="sync_progress_section" style="display: none;">
                    <div class="text-center mb-5">
                        <div class="mb-4">
                            <i class="ki-duotone ki-arrows-circle fs-3x text-primary rotating">
                                <span class="path1"></span><span class="path2"></span>
                            </i>
                        </div>
                        <h4 class="mb-2" id="sync_status_title">Sincronizando...</h4>
                        <p class="text-gray-500" id="sync_status_text">Conectando ao banco de dados externo</p>
                    </div>
                    
                    <div class="mb-5">
                        <div class="progress h-20px">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                                 role="progressbar" 
                                 id="sync_progress_bar"
                                 style="width: 0%">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span class="text-muted fs-7" id="sync_progress_label">Iniciando...</span>
                            <span class="text-muted fs-7" id="sync_progress_percent">0%</span>
                        </div>
                    </div>
                    
                    <!-- Resultado -->
                    <div id="sync_result_section" style="display: none;">
                        <div class="separator my-5"></div>
                        <div class="d-flex flex-wrap gap-3 justify-content-center" id="sync_stats">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="sync_footer_buttons">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_sync" onclick="executSync()">
                    <span class="indicator-label">
                        <i class="ki-duotone ki-arrows-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Sincronizar Agora
                    </span>
                </button>
            </div>
            <div class="modal-footer" id="sync_footer_done" style="display: none;">
                <button type="button" class="btn btn-primary" onclick="location.reload()">
                    <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                    Concluído
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes rotating {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.rotating {
    animation: rotating 1.5s linear infinite;
}
</style>

<script>
let currentSourceId = null;
let syncModal = null;

document.addEventListener('DOMContentLoaded', function() {
    syncModal = new bootstrap.Modal(document.getElementById('sync_modal'));
    
    // Reset modal ao fechar
    document.getElementById('sync_modal').addEventListener('hidden.bs.modal', resetSyncModal);
});

function resetSyncModal() {
    document.getElementById('sync_form_section').style.display = 'block';
    document.getElementById('sync_progress_section').style.display = 'none';
    document.getElementById('sync_result_section').style.display = 'none';
    document.getElementById('sync_footer_buttons').style.display = 'flex';
    document.getElementById('sync_footer_done').style.display = 'none';
    document.getElementById('sync_close_btn').style.display = 'block';
    document.getElementById('btn_sync').disabled = false;
    document.getElementById('sync_progress_bar').style.width = '0%';
    document.getElementById('sync_progress_percent').textContent = '0%';
}

function openSyncModal(sourceId, sourceName) {
    currentSourceId = sourceId;
    document.getElementById('sync_source_name').textContent = sourceName;
    
    // Carregar listas disponíveis
    fetch('/api/contact-lists')
        .then(r => r.json())
        .then(result => {
            const select = document.getElementById('sync_list_id');
            select.innerHTML = '<option value="">Selecione uma lista...</option>';
            
            if (result.data && result.data.length > 0) {
                result.data.forEach(list => {
                    const option = document.createElement('option');
                    option.value = list.id;
                    option.textContent = `${list.name} (${list.total_contacts || 0} contatos)`;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">Nenhuma lista encontrada - crie uma primeiro</option>';
            }
        })
        .catch(err => {
            console.error('Erro ao carregar listas:', err);
            document.getElementById('sync_list_id').innerHTML = '<option value="">Erro ao carregar listas</option>';
        });
    
    syncModal.show();
}

function updateProgress(percent, label, status) {
    document.getElementById('sync_progress_bar').style.width = percent + '%';
    document.getElementById('sync_progress_percent').textContent = percent + '%';
    document.getElementById('sync_progress_label').textContent = label;
    if (status) {
        document.getElementById('sync_status_text').textContent = status;
    }
}

function executSync() {
    const listId = document.getElementById('sync_list_id').value;
    if (!listId) {
        toastr.warning('Selecione uma lista de destino');
        return;
    }
    
    // Esconder formulário, mostrar progresso
    document.getElementById('sync_form_section').style.display = 'none';
    document.getElementById('sync_progress_section').style.display = 'block';
    document.getElementById('sync_footer_buttons').style.display = 'none';
    document.getElementById('sync_close_btn').style.display = 'none';
    
    // Simular progresso enquanto aguarda resposta
    updateProgress(5, 'Conectando...', 'Estabelecendo conexão com banco externo');
    
    let progressInterval = setInterval(() => {
        const bar = document.getElementById('sync_progress_bar');
        let current = parseInt(bar.style.width) || 0;
        if (current < 85) {
            const steps = [
                { pct: 10, label: 'Conectado', status: 'Lendo registros da tabela...' },
                { pct: 25, label: 'Lendo dados...', status: 'Processando registros encontrados' },
                { pct: 40, label: 'Processando...', status: 'Verificando contatos existentes' },
                { pct: 55, label: 'Importando...', status: 'Criando novos contatos' },
                { pct: 70, label: 'Atualizando...', status: 'Atualizando contatos existentes' },
                { pct: 85, label: 'Finalizando...', status: 'Salvando alterações' }
            ];
            
            const nextStep = steps.find(s => s.pct > current);
            if (nextStep) {
                updateProgress(nextStep.pct, nextStep.label, nextStep.status);
            }
        }
    }, 800);
    
    fetch(`/external-sources/${currentSourceId}/sync`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({list_id: parseInt(listId)})
    })
    .then(r => r.json())
    .then(result => {
        clearInterval(progressInterval);
        
        if (result.success) {
            const stats = result.stats || {};
            
            // Completar progresso
            updateProgress(100, 'Concluído!', 'Sincronização finalizada com sucesso');
            document.getElementById('sync_status_title').innerHTML = '<span class="text-success">Sincronização Concluída!</span>';
            document.getElementById('sync_progress_bar').classList.remove('progress-bar-animated', 'progress-bar-striped');
            document.getElementById('sync_progress_bar').classList.add('bg-success');
            
            // Parar animação do ícone
            document.querySelector('.rotating').classList.remove('rotating');
            document.querySelector('.rotating i, #sync_progress_section .ki-arrows-circle').outerHTML = 
                '<i class="ki-duotone ki-check-circle fs-3x text-success"><span class="path1"></span><span class="path2"></span></i>';
            
            // Mostrar estatísticas
            document.getElementById('sync_result_section').style.display = 'block';
            document.getElementById('sync_stats').innerHTML = `
                <div class="border border-success border-dashed rounded py-3 px-4 text-center">
                    <div class="fs-2 fw-bold text-success">${stats.records_created || 0}</div>
                    <div class="text-muted fs-7">Novos</div>
                </div>
                <div class="border border-primary border-dashed rounded py-3 px-4 text-center">
                    <div class="fs-2 fw-bold text-primary">${stats.records_updated || 0}</div>
                    <div class="text-muted fs-7">Atualizados</div>
                </div>
                <div class="border border-warning border-dashed rounded py-3 px-4 text-center">
                    <div class="fs-2 fw-bold text-warning">${stats.records_skipped || 0}</div>
                    <div class="text-muted fs-7">Ignorados</div>
                </div>
                <div class="border border-gray-300 border-dashed rounded py-3 px-4 text-center">
                    <div class="fs-2 fw-bold text-gray-800">${stats.total_processed || 0}</div>
                    <div class="text-muted fs-7">Total</div>
                </div>
            `;
            
            // Mostrar botão de concluído
            document.getElementById('sync_footer_done').style.display = 'flex';
            
            toastr.success('Sincronização concluída!');
        } else {
            showSyncError(result.message || 'Erro desconhecido');
        }
    })
    .catch(err => {
        clearInterval(progressInterval);
        showSyncError('Erro de conexão: ' + err.message);
        console.error(err);
    });
}

function showSyncError(message) {
    document.getElementById('sync_status_title').innerHTML = '<span class="text-danger">Erro na Sincronização</span>';
    document.getElementById('sync_status_text').textContent = message;
    document.getElementById('sync_progress_bar').classList.remove('bg-primary', 'progress-bar-animated');
    document.getElementById('sync_progress_bar').classList.add('bg-danger');
    document.getElementById('sync_progress_bar').style.width = '100%';
    document.getElementById('sync_progress_label').textContent = 'Falhou';
    document.getElementById('sync_progress_percent').textContent = '';
    
    // Parar animação e mostrar ícone de erro
    const iconContainer = document.querySelector('#sync_progress_section .ki-arrows-circle');
    if (iconContainer) {
        iconContainer.outerHTML = '<i class="ki-duotone ki-cross-circle fs-3x text-danger"><span class="path1"></span><span class="path2"></span></i>';
    }
    
    // Mostrar botão de fechar
    document.getElementById('sync_close_btn').style.display = 'block';
    document.getElementById('sync_footer_buttons').style.display = 'flex';
    document.getElementById('btn_sync').textContent = 'Tentar Novamente';
    document.getElementById('btn_sync').disabled = false;
    
    toastr.error(message);
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
