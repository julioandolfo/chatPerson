<?php
$layout = 'layouts.metronic.app';
$title = 'Agente Kanban: ' . htmlspecialchars($agent['name'] ?? '');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0"><?= htmlspecialchars($agent['name']) ?></h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
            <a href="<?= \App\Helpers\Url::to('/kanban-agents/' . $agent['id'] . '/edit') ?>" class="btn btn-light-info me-2">
                Editar
            </a>
            <button type="button" class="btn btn-primary me-2" onclick="executeAgent(<?= $agent['id'] ?>, false)">
                <i class="ki-duotone ki-double-right fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Executar Agora
            </button>
            <button type="button" class="btn btn-warning" onclick="executeAgent(<?= $agent['id'] ?>, true)">
                <i class="ki-duotone ki-flash fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Forçar Re-execução
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="row mb-10">
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Descrição</span>
                    <span class="text-gray-800 fw-semibold"><?= htmlspecialchars($agent['description'] ?? 'Sem descrição') ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Tipo</span>
                    <span class="badge badge-light-info"><?= htmlspecialchars($agent['agent_type']) ?></span>
                </div>
            </div>
        </div>
        
        <div class="row mb-10">
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Modelo</span>
                    <span class="text-gray-800 fw-semibold"><?= htmlspecialchars($agent['model'] ?? 'gpt-4') ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Status</span>
                    <?php if ($agent['enabled']): ?>
                        <span class="badge badge-light-success">Ativo</span>
                    <?php else: ?>
                        <span class="badge badge-light-secondary">Inativo</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="row mb-10">
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Tipo de Execução</span>
                    <span class="text-gray-800 fw-semibold">
                        <?php
                        $execType = $agent['execution_type'] ?? 'manual';
                        echo [
                            'interval' => 'Por Intervalo',
                            'schedule' => 'Agendado',
                            'manual' => 'Manual'
                        ][$execType] ?? $execType;
                        ?>
                    </span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Próxima Execução</span>
                    <span class="text-gray-800 fw-semibold">
                        <?= $agent['next_execution_at'] ? date('d/m/Y H:i', strtotime($agent['next_execution_at'])) : '-' ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="row mb-10">
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Última Execução</span>
                    <span class="text-gray-800 fw-semibold">
                        <?= $agent['last_execution_at'] ? date('d/m/Y H:i', strtotime($agent['last_execution_at'])) : 'Nunca' ?>
                    </span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Máximo de Conversas</span>
                    <span class="text-gray-800 fw-semibold">
                        <?= $agent['max_conversations_per_execution'] ?? 50 ?> por execução
                    </span>
                </div>
            </div>
        </div>
        
        <div class="row mb-10">
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Cooldown</span>
                    <span class="text-gray-800 fw-semibold">
                        <?= $agent['cooldown_hours'] ?? 24 ?> hora(s)
                    </span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">Re-execução em Mudanças</span>
                    <?php if ($agent['allow_reexecution_on_change'] ?? true): ?>
                        <span class="badge badge-light-success">Habilitado</span>
                    <?php else: ?>
                        <span class="badge badge-light-secondary">Desabilitado</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php 
        $workingHours = $agent['settings']['working_hours'] ?? null;
        if ($workingHours && ($workingHours['enabled'] ?? false)):
            $dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
            $selectedDays = $workingHours['days'] ?? [1,2,3,4,5];
            $daysText = implode(', ', array_map(fn($d) => $dayNames[$d], $selectedDays));
        ?>
        <div class="row mb-10">
            <div class="col-md-12">
                <div class="d-flex flex-column">
                    <span class="text-muted fs-7 mb-1">
                        <i class="ki-duotone ki-calendar fs-7 text-warning me-1"><span class="path1"></span><span class="path2"></span></i>
                        Horário de Funcionamento
                    </span>
                    <span class="text-gray-800 fw-semibold">
                        <?= $daysText ?>, <?= $workingHours['start_time'] ?? '08:00' ?> às <?= $workingHours['end_time'] ?? '18:00' ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Funis e Etapas Alvo -->
        <?php if (!empty($funnels) || !empty($stages)): ?>
        <div class="separator separator-dashed my-10"></div>
        <h4 class="fw-bold mb-5">Funis e Etapas Alvo</h4>
        <div class="row mb-10">
            <?php if (!empty($funnels)): ?>
            <div class="col-md-6">
                <span class="text-muted fs-7 mb-2 d-block">Funis:</span>
                <?php foreach ($funnels as $funnel): ?>
                    <span class="badge badge-light-primary me-2 mb-2"><?= htmlspecialchars($funnel['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="col-md-6">
                <span class="text-muted fs-7 mb-2 d-block">Funis:</span>
                <span class="badge badge-light-info">Todos os funis</span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($stages)): ?>
            <div class="col-md-6">
                <span class="text-muted fs-7 mb-2 d-block">Etapas:</span>
                <?php foreach ($stages as $stage): ?>
                    <span class="badge badge-light-success me-2 mb-2"><?= htmlspecialchars($stage['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="col-md-6">
                <span class="text-muted fs-7 mb-2 d-block">Etapas:</span>
                <span class="badge badge-light-info">Todas as etapas</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Condições e Ações -->
        <div class="separator separator-dashed my-10"></div>
        <div class="row mb-10">
            <div class="col-md-6">
                <h5 class="fw-bold mb-3">Condições</h5>
                <?php 
                $conditions = $agent['conditions'] ?? ['operator' => 'AND', 'conditions' => []];
                $conditionsCount = count($conditions['conditions'] ?? []);
                ?>
                <div class="d-flex align-items-center mb-2">
                    <span class="badge badge-light-info me-2"><?= strtoupper($conditions['operator'] ?? 'AND') ?></span>
                    <span class="text-gray-600"><?= $conditionsCount ?> condição(ões) configurada(s)</span>
                </div>
                <?php if ($conditionsCount === 0): ?>
                    <div class="text-muted fs-7">Nenhuma condição configurada (todas as conversas serão analisadas)</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold mb-3">Ações</h5>
                <?php 
                $actions = $agent['actions'] ?? [];
                $actionsCount = count($actions);
                ?>
                <div class="d-flex align-items-center mb-2">
                    <span class="badge badge-light-success me-2"><?= $actionsCount ?></span>
                    <span class="text-gray-600">ação(ões) configurada(s)</span>
                </div>
                <?php if ($actionsCount === 0): ?>
                    <div class="text-muted fs-7">Nenhuma ação configurada</div>
                <?php else: ?>
                    <div class="mt-2">
                        <?php foreach (array_slice($actions, 0, 3) as $action): ?>
                            <?php if ($action['enabled'] ?? true): ?>
                                <span class="badge badge-light-primary me-1 mb-1"><?= htmlspecialchars($action['type'] ?? 'unknown') ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($actionsCount > 3): ?>
                            <span class="text-muted fs-7">+<?= $actionsCount - 3 ?> mais</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Últimas Execuções -->
        <div class="separator separator-dashed my-10"></div>
        <h4 class="fw-bold mb-5">Últimas Execuções</h4>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Conversas Analisadas</th>
                        <th>Ações Executadas</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($executions)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-10">Nenhuma execução ainda</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($executions as $execution): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($execution['started_at'])) ?></td>
                                <td><?= htmlspecialchars($execution['execution_type']) ?></td>
                                <td>
                                    <?php
                                    $statusBadge = [
                                        'running' => 'badge-light-warning',
                                        'completed' => 'badge-light-success',
                                        'failed' => 'badge-light-danger',
                                        'cancelled' => 'badge-light-secondary'
                                    ][$execution['status']] ?? 'badge-light-secondary';
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($execution['status']) ?></span>
                                </td>
                                <td><?= $execution['conversations_analyzed'] ?? 0 ?></td>
                                <td><?= $execution['actions_executed'] ?? 0 ?></td>
                                <td class="text-end">
                                    <?php if (($execution['conversations_analyzed'] ?? 0) > 0): ?>
                                        <button type="button" class="btn btn-sm btn-light-primary" onclick="viewExecutionDetails(<?= $execution['id'] ?>)">
                                            <i class="ki-duotone ki-eye fs-4">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            Ver
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted fs-7">-</span>
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
<!--end::Card-->

<!-- Modal de Detalhes da Execução -->
<div class="modal fade" id="executionDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Detalhes da Execução</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <!-- Loading -->
                <div id="execution-loading" class="text-center py-10">
                    <span class="spinner-border spinner-border-lg text-primary" role="status"></span>
                    <div class="text-muted mt-3">Carregando detalhes...</div>
                </div>
                
                <!-- Conteúdo -->
                <div id="execution-content" style="display: none;">
                    <!-- Resumo -->
                    <div class="card mb-5">
                        <div class="card-body p-5">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted fs-7 mb-1">Conversas Encontradas</span>
                                        <span class="fw-bold fs-2 text-gray-800" id="exec-total-found">-</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted fs-7 mb-1">Analisadas com IA</span>
                                        <span class="fw-bold fs-2 text-primary" id="exec-analyzed">-</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted fs-7 mb-1">Com Ações Executadas</span>
                                        <span class="fw-bold fs-2 text-success" id="exec-acted">-</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex flex-column">
                                        <span class="text-muted fs-7 mb-1">Erros</span>
                                        <span class="fw-bold fs-2 text-danger" id="exec-errors">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabela de Conversas -->
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th class="min-w-50px">ID</th>
                                    <th class="min-w-150px">Contato</th>
                                    <th class="min-w-100px">Etapa</th>
                                    <th class="min-w-80px">Score</th>
                                    <th class="min-w-100px">Sentimento</th>
                                    <th class="min-w-100px">Condições</th>
                                    <th class="min-w-100px">Ações</th>
                                    <th class="min-w-80px text-end">Ver</th>
                                </tr>
                            </thead>
                            <tbody id="conversations-list">
                                <!-- Preenchido via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function executeAgent(agentId, force = false) {
    const title = force ? 'Forçar Re-execução?' : 'Executar Agente?';
    const text = force 
        ? 'Isso irá ignorar o cooldown e executar em TODAS as conversas novamente. Deseja continuar?' 
        : 'Deseja executar este agente agora respeitando o cooldown configurado?';
    const icon = force ? 'warning' : 'question';
    
    Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonText: force ? 'Sim, forçar' : 'Sim, executar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: force ? '#f1416c' : '#009ef7'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/kanban-agents/${agentId}/execute`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ force: force })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Sucesso!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro!', 'Erro ao executar agente', 'error');
            });
        }
    });
}

// Ver detalhes da execução
function viewExecutionDetails(executionId) {
    const modal = new bootstrap.Modal(document.getElementById('executionDetailsModal'));
    modal.show();
    
    // Mostrar loading
    document.getElementById('execution-loading').style.display = 'block';
    document.getElementById('execution-content').style.display = 'none';
    
    // Buscar dados
    fetch(`/kanban-agents/executions/${executionId}/details`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Preencher resumo
            document.getElementById('exec-total-found').textContent = data.execution.conversations_found || 0;
            document.getElementById('exec-analyzed').textContent = data.execution.conversations_analyzed || 0;
            document.getElementById('exec-acted').textContent = data.execution.conversations_acted_upon || 0;
            document.getElementById('exec-errors').textContent = data.execution.errors_count || 0;
            
            // Preencher tabela de conversas
            const tbody = document.getElementById('conversations-list');
            tbody.innerHTML = '';
            
            if (data.logs && data.logs.length > 0) {
                data.logs.forEach(log => {
                    const row = document.createElement('tr');
                    
                    // Badge de sentimento
                    const sentimentBadges = {
                        'positive': 'badge-light-success',
                        'neutral': 'badge-light-secondary',
                        'negative': 'badge-light-danger'
                    };
                    const sentimentBadge = sentimentBadges[log.analysis_sentiment] || 'badge-light-secondary';
                    
                    // Badge de condições
                    const conditionsBadge = log.conditions_met ? 'badge-light-success' : 'badge-light-warning';
                    const conditionsText = log.conditions_met ? 'Atendidas' : 'Não atendidas';
                    
                    // Contagem de ações (vem como array ou JSON string)
                    let actionsExecuted = 0;
                    try {
                        const actionsValue = Array.isArray(log.actions_executed)
                            ? log.actions_executed
                            : (log.actions_executed ? JSON.parse(log.actions_executed) : []);
                        actionsExecuted = Array.isArray(actionsValue) ? actionsValue.length : 0;
                    } catch (e) {
                        actionsExecuted = 0;
                    }
                    
                    row.innerHTML = `
                        <td class="fw-bold">#${log.conversation_id}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="d-flex flex-column">
                                    <span class="text-gray-800 fw-semibold">${escapeHtml(log.contact_name || 'Sem nome')}</span>
                                    <span class="text-muted fs-7">${escapeHtml(log.contact_phone || '-')}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-light-info">${escapeHtml(log.stage_name || '-')}</span>
                        </td>
                        <td>
                            <span class="badge badge-light-primary">${log.analysis_score || '-'}</span>
                        </td>
                        <td>
                            <span class="badge ${sentimentBadge}">${escapeHtml(log.analysis_sentiment || '-')}</span>
                        </td>
                        <td>
                            <span class="badge ${conditionsBadge}">${conditionsText}</span>
                        </td>
                        <td>
                            <span class="badge badge-light-success">${actionsExecuted} ação(ões)</span>
                        </td>
                        <td class="text-end">
                            <a href="/conversations?id=${log.conversation_id}" class="btn btn-sm btn-light-primary" target="_blank">
                                <i class="ki-duotone ki-arrow-right fs-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </a>
                        </td>
                    `;
                    
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">Nenhuma conversa processada</td></tr>';
            }
            
            // Esconder loading, mostrar conteúdo
            document.getElementById('execution-loading').style.display = 'none';
            document.getElementById('execution-content').style.display = 'block';
        } else {
            Swal.fire('Erro!', data.message || 'Erro ao carregar detalhes', 'error');
            modal.hide();
        }
    })
    .catch(error => {
        Swal.fire('Erro!', 'Erro ao carregar detalhes da execução', 'error');
        modal.hide();
    });
}

// Helper para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

