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
            <button type="button" class="btn btn-primary" onclick="executeAgent(<?= $agent['id'] ?>)">
                Executar Agora
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($executions)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-10">Nenhuma execução ainda</td>
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
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!--end::Card-->

<script>
function executeAgent(agentId) {
    Swal.fire({
        title: 'Executar Agente?',
        text: 'Deseja executar este agente agora?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, executar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/kanban-agents/${agentId}/execute`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
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
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

