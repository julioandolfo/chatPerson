<?php
$layout = 'layouts.metronic.app';
$title = 'Usuário - ' . htmlspecialchars($user['name'] ?? '');

ob_start();
?>
<!--begin::Layout-->
<div class="d-flex flex-column flex-xl-row">
    <!--begin::Sidebar-->
    <div class="flex-column flex-lg-row-auto w-100 w-xl-350px mb-10">
        <!--begin::Card-->
        <div class="card mb-5 mb-xl-8">
            <div class="card-body pt-15">
                <div class="d-flex flex-center flex-column mb-5">
                    <div class="symbol symbol-100px mb-5">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" />
                        <?php else: ?>
                            <div class="symbol-label fs-1 fw-semibold text-primary bg-light-primary">
                                <?= mb_substr(htmlspecialchars($user['name']), 0, 1) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-gray-800 fw-bold mb-3"><?= htmlspecialchars($user['name']) ?></h3>
                    <div class="mb-9">
                        <span class="badge badge-lg badge-light-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                            <?= $user['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                        </span>
                        <span class="badge badge-lg badge-light-info ms-2"><?= htmlspecialchars($user['role'] ?? 'agent') ?></span>
                        <?php if (!empty($user['availability_status'])): ?>
                            <?php
                            $availabilityColors = [
                                'online' => 'success',
                                'offline' => 'secondary',
                                'away' => 'warning',
                                'busy' => 'danger'
                            ];
                            $availabilityLabels = [
                                'online' => 'Online',
                                'offline' => 'Offline',
                                'away' => 'Ausente',
                                'busy' => 'Ocupado'
                            ];
                            $availStatus = $user['availability_status'] ?? 'offline';
                            $availColor = $availabilityColors[$availStatus] ?? 'secondary';
                            $availLabel = $availabilityLabels[$availStatus] ?? 'Desconhecido';
                            ?>
                            <span class="badge badge-lg badge-light-<?= $availColor ?> ms-2"><?= $availLabel ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-gray-600 text-center"><?= htmlspecialchars($user['email']) ?></p>
                    <?php if ($user['role'] === 'agent' || $user['role'] === 'admin' || $user['role'] === 'supervisor'): ?>
                        <div class="mt-5">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted fs-7">Conversas Atuais:</span>
                                <span class="fw-bold fs-6"><?= $user['current_conversations'] ?? 0 ?></span>
                            </div>
                            <?php if (!empty($user['max_conversations'])): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted fs-7">Limite:</span>
                                    <span class="fw-bold fs-6"><?= $user['max_conversations'] ?></span>
                                </div>
                                <div class="progress h-6px mb-5" style="width: 100%;">
                                    <?php
                                    $percentage = $user['max_conversations'] > 0 
                                        ? min(100, (($user['current_conversations'] ?? 0) / $user['max_conversations']) * 100) 
                                        : 0;
                                    $progressColor = $percentage >= 90 ? 'danger' : ($percentage >= 70 ? 'warning' : 'success');
                                    ?>
                                    <div class="progress-bar bg-<?= $progressColor ?>" 
                                         role="progressbar" 
                                         style="width: <?= $percentage ?>%"
                                         aria-valuenow="<?= $percentage ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (\App\Helpers\Permission::can('users.edit')): ?>
                    <div class="mt-5">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_edit_user">
                            <i class="ki-duotone ki-pencil fs-2"></i>
                            Editar Usuário
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!--end::Card-->
    </div>
    <!--end::Sidebar-->

    <!--begin::Content-->
    <div class="flex-lg-row-fluid ms-xl-10">
        <!--begin::Card - Roles-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Roles</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Gerencie as roles deste usuário</span>
                </h3>
                <?php if (\App\Helpers\Permission::can('users.edit')): ?>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_assign_role">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Adicionar Role
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body py-3">
                <?php if (empty($user['roles'])): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-shield fs-3x text-gray-400 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-800 fw-bold mb-2">Nenhuma role atribuída</h3>
                        <div class="text-gray-500 fs-6">Adicione roles a este usuário.</div>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($user['roles'] as $role): ?>
                            <span class="badge badge-lg badge-light-primary">
                                <?= htmlspecialchars($role['name']) ?>
                                <?php if (\App\Helpers\Permission::can('users.edit')): ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-active-color-primary ms-2" 
                                            onclick="removeRole(<?= $user['id'] ?>, <?= $role['id'] ?>)">
                                        <i class="ki-duotone ki-cross fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card - Roles-->

        <!--begin::Card - Departments-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Setores</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Gerencie os setores deste usuário</span>
                </h3>
                <?php if (\App\Helpers\Permission::can('users.edit')): ?>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_assign_department">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Adicionar Setor
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body py-3">
                <?php if (empty($user['departments'])): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-abstract-26 fs-3x text-gray-400 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-800 fw-bold mb-2">Nenhum setor atribuído</h3>
                        <div class="text-gray-500 fs-6">Adicione setores a este usuário.</div>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($user['departments'] as $dept): ?>
                            <span class="badge badge-lg badge-light-success">
                                <?= htmlspecialchars($dept['name']) ?>
                                <?php if (\App\Helpers\Permission::can('users.edit')): ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-active-color-primary ms-2" 
                                            onclick="removeDepartment(<?= $user['id'] ?>, <?= $dept['id'] ?>)">
                                        <i class="ki-duotone ki-cross fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </button>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card - Departments-->

        <!--begin::Card - Permissões de Funil/Estágio-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Permissões de Funis e Estágios</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Controle quais funis e estágios este agente pode visualizar</span>
                </h3>
                <?php if (\App\Helpers\Permission::can('users.edit')): ?>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_assign_funnel_permission">
                        <i class="ki-duotone ki-plus fs-2"></i>
                        Adicionar Permissão
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body py-3">
                <?php if (empty($funnelPermissions)): ?>
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-grid fs-3x text-gray-400 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-800 fw-bold mb-2">Nenhuma permissão de funil atribuída</h3>
                        <div class="text-gray-500 fs-6">Adicione permissões para este agente visualizar funis e estágios específicos.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-150px">Funil</th>
                                    <th class="min-w-150px">Estágio</th>
                                    <th class="min-w-100px">Tipo</th>
                                    <th class="text-end min-w-70px">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold">
                                <?php foreach ($funnelPermissions as $perm): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($perm['funnel_name'] ?? 'Todos os Funis') ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($perm['stage_name'] ?? 'Todos os Estágios') ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-primary"><?= htmlspecialchars($perm['permission_type']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php if (\App\Helpers\Permission::can('users.edit')): ?>
                                            <button type="button" class="btn btn-sm btn-light-danger" 
                                                    onclick="removeFunnelPermission(<?= $user['id'] ?>, <?= $perm['funnel_id'] ?? 'null' ?>, <?= $perm['stage_id'] ?? 'null' ?>, '<?= $perm['permission_type'] ?>')">
                                                Remover
                                            </button>
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
        <!--end::Card - Permissões de Funil/Estágio-->

        <!--begin::Card - Performance e Estatísticas-->
        <?php if ($user['role'] === 'agent' || $user['role'] === 'admin' || $user['role'] === 'supervisor'): ?>
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Performance e Estatísticas</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Métricas de desempenho do agente</span>
                </h3>
                <div class="card-toolbar">
                    <div class="d-flex align-items-center gap-2">
                        <input type="date" id="kt_performance_date_from" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($performanceStats['period']['from'] ?? date('Y-m-01')) ?>" 
                               style="width: 150px;" />
                        <span class="text-muted">até</span>
                        <input type="date" id="kt_performance_date_to" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars(date('Y-m-d', strtotime($performanceStats['period']['to'] ?? 'now'))) ?>" 
                               style="width: 150px;" />
                        <button type="button" class="btn btn-sm btn-primary" onclick="loadPerformanceStats()">
                            <i class="ki-duotone ki-magnifier fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Filtrar
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body py-3">
                <?php if ($performanceStats): ?>
                    <div class="row g-5 g-xl-8">
                        <!-- Total de Conversas -->
                        <div class="col-xl-3">
                            <div class="card bg-light-primary card-xl-stretch mb-xl-8">
                                <div class="card-body">
                                    <i class="ki-duotone ki-chat-text fs-2x text-primary mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($performanceStats['total_conversations']) ?></div>
                                    <div class="fw-semibold text-gray-500">Total de Conversas</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Conversas Fechadas -->
                        <div class="col-xl-3">
                            <div class="card bg-light-success card-xl-stretch mb-xl-8">
                                <div class="card-body">
                                    <i class="ki-duotone ki-check-circle fs-2x text-success mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($performanceStats['closed_conversations']) ?></div>
                                    <div class="fw-semibold text-gray-500">Conversas Fechadas</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Taxa de Resolução -->
                        <div class="col-xl-3">
                            <div class="card bg-light-info card-xl-stretch mb-xl-8">
                                <div class="card-body">
                                    <i class="ki-duotone ki-chart-simple fs-2x text-info mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($performanceStats['resolution_rate'], 1) ?>%</div>
                                    <div class="fw-semibold text-gray-500">Taxa de Resolução</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Total de Mensagens -->
                        <div class="col-xl-3">
                            <div class="card bg-light-warning card-xl-stretch mb-xl-8">
                                <div class="card-body">
                                    <i class="ki-duotone ki-message-text-2 fs-2x text-warning mb-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div class="text-gray-900 fw-bold fs-2 mb-2"><?= number_format($performanceStats['total_messages']) ?></div>
                                    <div class="fw-semibold text-gray-500">Mensagens Enviadas</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-5 g-xl-8 mt-2">
                        <!-- Tempo Médio de Resposta -->
                        <div class="col-xl-4">
                            <div class="card card-xl-stretch">
                                <div class="card-header border-0 pt-5">
                                    <h3 class="card-title fw-bold">Tempo Médio de Primeira Resposta</h3>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-time fs-3x text-primary me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <div class="fs-2x fw-bold text-gray-800">
                                                <?= $performanceStats['avg_first_response_time'] 
                                                    ? \App\Services\AgentPerformanceService::formatTime($performanceStats['avg_first_response_time'])
                                                    : '-' ?>
                                            </div>
                                            <div class="text-muted fs-6">Tempo médio para primeira resposta</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tempo Médio de Resolução -->
                        <div class="col-xl-4">
                            <div class="card card-xl-stretch">
                                <div class="card-header border-0 pt-5">
                                    <h3 class="card-title fw-bold">Tempo Médio de Resolução</h3>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-calendar-tick fs-3x text-success me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <div class="fs-2x fw-bold text-gray-800">
                                                <?= $performanceStats['avg_resolution_time'] 
                                                    ? \App\Services\AgentPerformanceService::formatTime($performanceStats['avg_resolution_time'])
                                                    : '-' ?>
                                            </div>
                                            <div class="text-muted fs-6">Tempo médio para resolver conversas</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Conversas por Dia -->
                        <div class="col-xl-4">
                            <div class="card card-xl-stretch">
                                <div class="card-header border-0 pt-5">
                                    <h3 class="card-title fw-bold">Conversas por Dia</h3>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-chart fs-3x text-info me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <div class="fs-2x fw-bold text-gray-800">
                                                <?= number_format($performanceStats['conversations_per_day'], 1) ?>
                                            </div>
                                            <div class="text-muted fs-6">Média de conversas por dia</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10">
                        <div class="text-muted">Carregando estatísticas...</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card - Performance e Estatísticas-->
        <?php endif; ?>

        <!--begin::Card - Histórico de Atividades-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Histórico de Atividades</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Últimas ações realizadas por este usuário</span>
                </h3>
            </div>
            <div class="card-body py-3">
                <div id="kt_activities_list">
                    <div class="text-center py-10">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card - Histórico de Atividades-->
    </div>
    <!--end::Content-->
</div>
<!--end::Layout-->

<!--begin::Modal - Adicionar Role-->
<?php if (\App\Helpers\Permission::can('users.edit')): ?>
<div class="modal fade" id="kt_modal_assign_role" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Role ao Usuário</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_assign_role_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Role</label>
                        <select name="role_id" class="form-select form-select-solid" required>
                            <option value="">Selecione uma role</option>
                            <?php
                            $currentRoleIds = array_column($user['roles'] ?? [], 'id');
                            foreach ($allRoles as $role):
                                if (!in_array($role['id'], $currentRoleIds)):
                            ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_assign_role_submit" class="btn btn-primary">
                        <span class="indicator-label">Adicionar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Adicionar Role-->

<!--begin::Modal - Adicionar Setor-->
<?php if (\App\Helpers\Permission::can('users.edit')): ?>
<div class="modal fade" id="kt_modal_assign_department" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Setor ao Usuário</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_assign_department_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Setor</label>
                        <select name="department_id" class="form-select form-select-solid" required>
                            <option value="">Selecione um setor</option>
                            <?php
                            $currentDeptIds = array_column($user['departments'] ?? [], 'id');
                            foreach ($allDepartments as $dept):
                                if (!in_array($dept['id'], $currentDeptIds)):
                            ?>
                                <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_assign_department_submit" class="btn btn-primary">
                        <span class="indicator-label">Adicionar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Adicionar Setor-->

<!--begin::Modal - Adicionar Permissão de Funil/Estágio-->
<?php if (\App\Helpers\Permission::can('users.edit')): ?>
<div class="modal fade" id="kt_modal_assign_funnel_permission" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Permissão de Funil/Estágio</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_assign_funnel_permission_form" class="form">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Funil</label>
                        <select name="funnel_id" id="kt_funnel_select" class="form-select form-select-solid">
                            <option value="">Todos os Funis</option>
                            <?php foreach ($allFunnels as $funnel): ?>
                                <option value="<?= $funnel['id'] ?>"><?= htmlspecialchars($funnel['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Deixe vazio para permitir acesso a todos os funis</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Estágio</label>
                        <select name="stage_id" id="kt_stage_select" class="form-select form-select-solid" disabled>
                            <option value="">Todos os Estágios</option>
                        </select>
                        <div class="form-text">Deixe vazio para permitir acesso a todos os estágios do funil selecionado</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Tipo de Permissão</label>
                        <select name="permission_type" class="form-select form-select-solid" required>
                            <option value="view">Visualizar</option>
                            <option value="edit">Editar</option>
                            <option value="move">Mover Conversas</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_assign_funnel_permission_submit" class="btn btn-primary">
                        <span class="indicator-label">Adicionar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Adicionar Permissão de Funil/Estágio-->

<!--begin::Modal - Editar Usuário-->
<?php if (\App\Helpers\Permission::can('users.edit')): ?>
<div class="modal fade" id="kt_modal_edit_user" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Usuário</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="kt_modal_edit_user_form" class="form" enctype="multipart/form-data">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Avatar</label>
                        <input type="file" name="avatar_file" class="form-control form-control-solid" accept="image/*" />
                        <div class="form-text">JPG, PNG, GIF ou WEBP. Máximo 2MB. Deixe vazio para manter o atual.</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Nome</label>
                        <input type="text" name="name" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($user['name']) ?>" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Email</label>
                        <input type="email" name="email" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($user['email']) ?>" required />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Nova Senha</label>
                        <input type="password" name="password" class="form-control form-control-solid" 
                               placeholder="Deixe em branco para manter a senha atual" />
                        <div class="form-text">Mínimo 6 caracteres. Deixe em branco para não alterar.</div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Role</label>
                        <select name="role" class="form-select form-select-solid">
                            <option value="agent" <?= ($user['role'] ?? 'agent') === 'agent' ? 'selected' : '' ?>>Agente</option>
                            <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="supervisor" <?= ($user['role'] ?? '') === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="active" <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <?php if ($user['role'] === 'agent' || $user['role'] === 'admin' || $user['role'] === 'supervisor'): ?>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Status de Disponibilidade</label>
                        <select name="availability_status" class="form-select form-select-solid">
                            <option value="online" <?= ($user['availability_status'] ?? 'offline') === 'online' ? 'selected' : '' ?>>Online</option>
                            <option value="offline" <?= ($user['availability_status'] ?? 'offline') === 'offline' ? 'selected' : '' ?>>Offline</option>
                            <option value="away" <?= ($user['availability_status'] ?? '') === 'away' ? 'selected' : '' ?>>Ausente</option>
                            <option value="busy" <?= ($user['availability_status'] ?? '') === 'busy' ? 'selected' : '' ?>>Ocupado</option>
                        </select>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limite de Conversas Simultâneas</label>
                        <input type="number" name="max_conversations" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($user['max_conversations'] ?? '') ?>" 
                               min="1" placeholder="Deixe vazio para ilimitado" />
                        <div class="form-text">Número máximo de conversas que este agente pode atender simultaneamente</div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer flex-center">
                    <button type="reset" data-bs-dismiss="modal" class="btn btn-light me-3">Cancelar</button>
                    <button type="submit" id="kt_modal_edit_user_submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar</span>
                        <span class="indicator-progress">Aguarde...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!--end::Modal - Editar Usuário-->

<?php 
$content = ob_get_clean(); 
$scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Carregar histórico de atividades
    loadActivities();
});

function loadActivities() {
    const activitiesList = document.getElementById("kt_activities_list");
    if (!activitiesList) return;
    
    const userId = ' . intval($user['id']) . ';
    const baseUrl = "' . \App\Helpers\Url::to('/activities/user/') . '";
    fetch(baseUrl + userId + "?limit=20")
        .then(response => response.json())
        .then(data => {
            if (data.success && data.activities && data.activities.length > 0) {
                let html = `<div class="timeline">`;
                data.activities.forEach(activity => {
                    const date = new Date(activity.created_at);
                    const dateStr = date.toLocaleDateString("pt-BR", { 
                        day: "2-digit", 
                        month: "short", 
                        year: "numeric",
                        hour: "2-digit",
                        minute: "2-digit"
                    });
                    
                    html += `
                        <div class="timeline-item">
                            <div class="timeline-line w-40px"></div>
                            <div class="timeline-icon symbol symbol-circle symbol-40px">
                                <div class="symbol-label bg-light-${getActivityColor(activity.activity_type)}">
                                    <i class="ki-duotone ${getActivityIcon(activity.activity_type)} fs-2 text-${getActivityColor(activity.activity_type)}">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div class="timeline-content mb-10 mt-n1">
                                <div class="pe-3 mb-5">
                                    <div class="fs-5 fw-semibold mb-2">${escapeHtml(activity.description || activity.activity_type)}</div>
                                    <div class="d-flex align-items-center mt-1 fs-6">
                                        <div class="text-muted me-2 fs-7">${dateStr}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += `</div>`;
                activitiesList.innerHTML = html;
            } else {
                activitiesList.innerHTML = `
                    <div class="text-center py-10">
                        <i class="ki-duotone ki-time fs-3x text-gray-400 mb-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <h3 class="text-gray-800 fw-bold mb-2">Nenhuma atividade registrada</h3>
                        <div class="text-gray-500 fs-6">Este usuário ainda não realizou nenhuma ação registrada.</div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error("Erro ao carregar atividades:", error);
            activitiesList.innerHTML = `
                <div class="text-center py-10">
                    <div class="text-danger">Erro ao carregar histórico de atividades.</div>
                </div>
            `;
        });
}

function getActivityColor(type) {
    const colors = {
        "conversation_assigned": "primary",
        "conversation_closed": "success",
        "conversation_reopened": "warning",
        "message_sent": "info",
        "tag_added": "success",
        "tag_removed": "danger",
        "stage_moved": "primary",
        "user_created": "success",
        "user_updated": "info",
        "availability_changed": "warning"
    };
    return colors[type] || "secondary";
}

function getActivityIcon(type) {
    const icons = {
        "conversation_assigned": "ki-user",
        "conversation_closed": "ki-check",
        "conversation_reopened": "ki-arrow-right",
        "message_sent": "ki-message-text",
        "tag_added": "ki-tag",
        "tag_removed": "ki-cross",
        "stage_moved": "ki-arrows-circle",
        "user_created": "ki-plus",
        "user_updated": "ki-pencil",
        "availability_changed": "ki-notification-status"
    };
    return icons[type] || "ki-notepad";
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const roleForm = document.getElementById("kt_modal_assign_role_form");
    if (roleForm) {
        roleForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const roleId = this.querySelector("[name=\"role_id\"]").value;
            if (!roleId) return;
            
            const submitBtn = document.getElementById("kt_modal_assign_role_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append("role_id", roleId);
            
            fetch("' . \App\Helpers\Url::to('/users/' . $user['id'] . '/roles') . '", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_assign_role"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao adicionar role"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao adicionar role");
            });
        });
    }
    
    const deptForm = document.getElementById("kt_modal_assign_department_form");
    if (deptForm) {
        deptForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const departmentId = this.querySelector("[name=\"department_id\"]").value;
            if (!departmentId) return;
            
            const submitBtn = document.getElementById("kt_modal_assign_department_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append("department_id", departmentId);
            
            fetch("' . \App\Helpers\Url::to('/users/' . $user['id'] . '/departments') . '", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_assign_department"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao adicionar setor"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao adicionar setor");
            });
        });
    }
});

function removeRole(userId, roleId) {
    if (!confirm("Tem certeza que deseja remover esta role do usuário?")) return;
    
    const formData = new FormData();
    formData.append("role_id", roleId);
    
    fetch("' . \App\Helpers\Url::to('/users/' . $user['id'] . '/roles/remove') . '", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao remover role"));
        }
    });
}

function removeDepartment(userId, departmentId) {
    if (!confirm("Tem certeza que deseja remover este setor do usuário?")) return;
    
    const formData = new FormData();
    formData.append("department_id", departmentId);
    
    fetch("' . \App\Helpers\Url::to('/users/' . $user['id'] . '/departments/remove') . '", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao remover setor"));
        }
    });
}

// Carregar estágios quando funil for selecionado
document.addEventListener("DOMContentLoaded", function() {
    const funnelSelect = document.getElementById("kt_funnel_select");
    const stageSelect = document.getElementById("kt_stage_select");
    
    if (funnelSelect && stageSelect) {
        funnelSelect.addEventListener("change", function() {
            const funnelId = this.value;
            stageSelect.innerHTML = "<option value=\"\">Todos os Estágios</option>";
            stageSelect.disabled = !funnelId;
            
            if (funnelId) {
                fetch("' . \App\Helpers\Url::to('/funnels') . '/" + funnelId + "/stages")
                    .then(response => response.json())
                    .then(data => {
                        if (data.stages) {
                            data.stages.forEach(stage => {
                                const option = document.createElement("option");
                                option.value = stage.id;
                                option.textContent = stage.name;
                                stageSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(() => {
                        // Se não houver endpoint, tentar buscar do DOM ou deixar vazio
                    });
            }
        });
    }
    
    const funnelPermissionForm = document.getElementById("kt_modal_assign_funnel_permission_form");
    if (funnelPermissionForm) {
        funnelPermissionForm.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById("kt_modal_assign_funnel_permission_submit");
            submitBtn.setAttribute("data-kt-indicator", "on");
            submitBtn.disabled = true;
            
            const formData = new FormData(funnelPermissionForm);
            
            fetch("' . \App\Helpers\Url::to('/users/' . $user['id'] . '/funnel-permissions') . '", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_assign_funnel_permission"));
                    modal.hide();
                    location.reload();
                } else {
                    alert("Erro: " + (data.message || "Erro ao adicionar permissão"));
                }
            })
            .catch(error => {
                submitBtn.removeAttribute("data-kt-indicator");
                submitBtn.disabled = false;
                alert("Erro ao adicionar permissão");
            });
        });
    }
});

function removeFunnelPermission(userId, funnelId, stageId, permissionType) {
    if (!confirm("Tem certeza que deseja remover esta permissão?")) return;
    
    const formData = new FormData();
    if (funnelId) formData.append("funnel_id", funnelId);
    if (stageId) formData.append("stage_id", stageId);
    formData.append("permission_type", permissionType);
    
    fetch("' . \App\Helpers\Url::to('/users/' . $user['id'] . '/funnel-permissions/remove') . '", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert("Erro: " + (data.message || "Erro ao remover permissão"));
        }
    });
}

// Formulário de edição de usuário
const editUserForm = document.getElementById("kt_modal_edit_user_form");
if (editUserForm) {
    editUserForm.addEventListener("submit", function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById("kt_modal_edit_user_submit");
        submitBtn.setAttribute("data-kt-indicator", "on");
        submitBtn.disabled = true;
        
        const formData = new FormData(editUserForm);
        // Remover senha se estiver vazia
        if (!formData.get("password")) {
            formData.delete("password");
        }
        
        fetch("' . \App\Helpers\Url::to('/users/' . $user['id']) . '", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.removeAttribute("data-kt-indicator");
            submitBtn.disabled = false;
            
            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById("kt_modal_edit_user"));
                modal.hide();
                location.reload();
            } else {
                alert("Erro: " + (data.message || "Erro ao atualizar usuário"));
            }
        })
        .catch(error => {
            submitBtn.removeAttribute("data-kt-indicator");
            submitBtn.disabled = false;
            alert("Erro ao atualizar usuário");
        });
    });
}

function loadPerformanceStats() {
    const dateFromEl = document.getElementById("kt_performance_date_from");
    const dateToEl = document.getElementById("kt_performance_date_to");
    
    const dateFrom = dateFromEl ? dateFromEl.value : "";
    const dateTo = dateToEl ? dateToEl.value : "";
    
    if (!dateFrom || !dateTo) {
        alert("Por favor, selecione as datas de início e fim.");
        return;
    }
    
    const url = "' . \App\Helpers\Url::to('/users/' . $user['id']) . '?date_from=" + dateFrom + "&date_to=" + dateTo;
    window.location.href = url;
}
</script>';
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

