<?php
$layout = 'layouts.metronic.app';
$title = 'Agentes Kanban';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Agentes Kanban</h3>
        </div>
        <div class="card-toolbar">
            <?php if (\App\Helpers\Permission::can('ai_agents.create')): ?>
            <a href="<?= \App\Helpers\Url::to('/kanban-agents/create') ?>" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Novo Agente Kanban
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($agents)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-robot fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum agente Kanban encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo agente Kanban para monitorar funis e etapas.</div>
                <?php if (\App\Helpers\Permission::can('ai_agents.create')): ?>
                <a href="<?= \App\Helpers\Url::to('/kanban-agents/create') ?>" class="btn btn-primary">
                    Criar Primeiro Agente
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-5">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-200px">Nome</th>
                            <th class="min-w-100px">Tipo</th>
                            <th class="min-w-150px">Execução</th>
                            <th class="min-w-100px">Status</th>
                            <th class="min-w-150px">Próxima Execução</th>
                            <th class="text-end min-w-100px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 fw-semibold">
                        <?php foreach ($agents as $agent): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <a href="<?= \App\Helpers\Url::to('/kanban-agents/' . $agent['id']) ?>" class="text-gray-800 fw-bold text-hover-primary">
                                            <?= htmlspecialchars($agent['name']) ?>
                                        </a>
                                        <?php if (!empty($agent['description'])): ?>
                                            <span class="text-muted fs-7"><?= htmlspecialchars(mb_substr($agent['description'], 0, 80)) ?><?= mb_strlen($agent['description']) > 80 ? '...' : '' ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-info"><?= htmlspecialchars($agent['agent_type']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $execType = $agent['execution_type'] ?? 'manual';
                                    $execLabel = [
                                        'interval' => 'Intervalo',
                                        'schedule' => 'Agendado',
                                        'manual' => 'Manual'
                                    ][$execType] ?? $execType;
                                    
                                    if ($execType === 'interval' && $agent['execution_interval_hours']) {
                                        $hours = (int)$agent['execution_interval_hours'];
                                        $execLabel .= " ({$hours}h)";
                                    }
                                    ?>
                                    <span class="text-gray-800"><?= htmlspecialchars($execLabel) ?></span>
                                </td>
                                <td>
                                    <?php if ($agent['enabled']): ?>
                                        <span class="badge badge-light-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($agent['next_execution_at']): ?>
                                        <span class="text-gray-800"><?= date('d/m/Y H:i', strtotime($agent['next_execution_at'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= \App\Helpers\Url::to('/kanban-agents/' . $agent['id']) ?>" class="btn btn-sm btn-light-primary me-2">
                                        Ver
                                    </a>
                                    <?php if (\App\Helpers\Permission::can('ai_agents.edit')): ?>
                                    <a href="<?= \App\Helpers\Url::to('/kanban-agents/' . $agent['id'] . '/edit') ?>" class="btn btn-sm btn-light-info me-2">
                                        Editar
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

