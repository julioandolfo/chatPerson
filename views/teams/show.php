<?php
$layout = 'layouts.metronic.app';
$title = 'Time: ' . htmlspecialchars($team['name']);

ob_start();
?>

<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack pb-7">
    <div class="d-flex flex-wrap align-items-center my-1">
        <h3 class="fw-bold me-5 my-1">
            <span class="badge" style="background-color: <?= htmlspecialchars($team['color'] ?? '#009ef7') ?>;">&nbsp;&nbsp;&nbsp;</span>
            <?= htmlspecialchars($team['name']) ?>
        </h3>
    </div>
    <div class="d-flex align-items-center my-1">
        <a href="/teams" class="btn btn-sm btn-light me-2">
            <i class="ki-duotone ki-arrow-left fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Voltar
        </a>
        <?php if (\App\Helpers\Permission::can('teams.edit')): ?>
        <a href="/teams/edit?id=<?= $team['id'] ?>" class="btn btn-sm btn-primary">
            <i class="ki-duotone ki-pencil fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Editar
        </a>
        <?php endif; ?>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Row-->
<div class="row g-5 g-xl-10 mb-5">
    <!--begin::Col - Info-->
    <div class="col-lg-4">
        <!--begin::Card-->
        <div class="card card-flush h-lg-100">
            <div class="card-header pt-7">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-800">Informações do Time</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <?php if (!empty($team['description'])): ?>
                <div class="mb-7">
                    <label class="fw-semibold text-muted fs-7 mb-1">Descrição</label>
                    <div class="text-gray-800"><?= nl2br(htmlspecialchars($team['description'])) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($team['leader_name'])): ?>
                <div class="mb-7">
                    <label class="fw-semibold text-muted fs-7 mb-1">Líder</label>
                    <div class="d-flex align-items-center">
                        <?php if (!empty($team['leader_avatar'])): ?>
                        <div class="symbol symbol-35px me-3">
                            <img src="<?= htmlspecialchars($team['leader_avatar']) ?>" alt="">
                        </div>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold text-gray-800"><?= htmlspecialchars($team['leader_name']) ?></div>
                            <div class="text-muted fs-7"><?= htmlspecialchars($team['leader_email']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($team['department_name'])): ?>
                <div class="mb-7">
                    <label class="fw-semibold text-muted fs-7 mb-1">Setor</label>
                    <div class="text-gray-800"><?= htmlspecialchars($team['department_name']) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="mb-7">
                    <label class="fw-semibold text-muted fs-7 mb-1">Status</label>
                    <div>
                        <?php if ($team['is_active']): ?>
                        <span class="badge badge-light-success">Ativo</span>
                        <?php else: ?>
                        <span class="badge badge-light-danger">Inativo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
    </div>
    <!--end::Col-->
    
    <!--begin::Col - Stats-->
    <div class="col-lg-8">
        <!--begin::Row-->
        <div class="row g-5 g-xl-10">
            <!--begin::Col-->
            <div class="col-sm-6 col-xl-3 mb-5">
                <div class="card card-flush h-lg-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <i class="ki-duotone ki-people fs-3x text-primary mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fs-2hx fw-bold text-gray-800"><?= $performance['members_count'] ?? 0 ?></span>
                            <span class="text-gray-500 fw-semibold fs-6">Membros</span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-sm-6 col-xl-3 mb-5">
                <div class="card card-flush h-lg-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <i class="ki-duotone ki-message-text-2 fs-3x text-success mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fs-2hx fw-bold text-gray-800"><?= $performance['total_conversations'] ?? 0 ?></span>
                            <span class="text-gray-500 fw-semibold fs-6">Conversas</span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-sm-6 col-xl-3 mb-5">
                <div class="card card-flush h-lg-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <i class="ki-duotone ki-check-circle fs-3x text-success mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fs-2hx fw-bold text-gray-800"><?= number_format($performance['resolution_rate'] ?? 0, 1) ?>%</span>
                            <span class="text-gray-500 fw-semibold fs-6">Taxa de Resolução</span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-sm-6 col-xl-3 mb-5">
                <div class="card card-flush h-lg-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <i class="ki-duotone ki-timer fs-3x text-warning mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fs-2hx fw-bold text-gray-800">
                                <?= \App\Services\TeamPerformanceService::formatTime($performance['avg_first_response_time'] ?? null) ?>
                            </span>
                            <span class="text-gray-500 fw-semibold fs-6">TM de Resposta</span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<!--begin::Row-->
<div class="row g-5 g-xl-10 mb-5">
    <!--begin::Col - Members-->
    <div class="col-lg-12">
        <div class="card card-flush">
            <div class="card-header pt-7">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-gray-800">Membros do Time</span>
                    <span class="text-muted mt-1 fw-semibold fs-7"><?= count($team['members'] ?? []) ?> membro(s)</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <?php if (empty($team['members'])): ?>
                    <div class="text-center py-10">
                        <p class="text-muted">Nenhum membro no time</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>Agente</th>
                                    <th>Conversas</th>
                                    <th>Resolvidas</th>
                                    <th>Taxa Resolução</th>
                                    <th>TM Resposta</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600 fw-semibold">
                                <?php foreach ($performance['members_performance'] ?? [] as $member): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($member['user_avatar'])): ?>
                                            <div class="symbol symbol-35px me-3">
                                                <img src="<?= htmlspecialchars($member['user_avatar']) ?>" alt="">
                                            </div>
                                            <?php endif; ?>
                                            <div>
                                                <a href="/agent-performance/agent?id=<?= $member['user_id'] ?>" class="text-gray-800 text-hover-primary fw-bold">
                                                    <?= htmlspecialchars($member['user_name']) ?>
                                                </a>
                                                <?php 
                                                // Verificar se é o líder
                                                if ($member['user_id'] == $team['leader_id']): 
                                                ?>
                                                <span class="badge badge-light-primary ms-2">Líder</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $member['total_conversations'] ?? 0 ?></td>
                                    <td><?= $member['closed_conversations'] ?? 0 ?></td>
                                    <td>
                                        <span class="badge badge-light-success"><?= number_format($member['resolution_rate'] ?? 0, 1) ?>%</span>
                                    </td>
                                    <td><?= \App\Services\TeamPerformanceService::formatTime($member['avg_first_response_time'] ?? null) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!--end::Col-->
</div>
<!--end::Row-->

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
