<?php
$layout = 'layouts.metronic.app';
$title = $title ?? 'Times';

ob_start();
?>
<!--begin::Toolbar-->
<div class="d-flex flex-wrap flex-stack pb-7">
    <div class="d-flex flex-wrap align-items-center my-1">
        <h3 class="fw-bold me-5 my-1"><?= $title ?></h3>
        <span class="text-muted fs-7"><?= count($teams) ?> time(s) encontrado(s)</span>
    </div>
    <div class="d-flex align-items-center my-1">
        <?php if (\App\Helpers\Permission::can('teams.create')): ?>
        <a href="/teams/create" class="btn btn-sm btn-primary">
            <i class="ki-duotone ki-plus fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            Novo Time
        </a>
        <?php endif; ?>
        <?php if (\App\Helpers\Permission::can('teams.view')): ?>
        <a href="/teams/dashboard" class="btn btn-sm btn-light ms-2">
            <i class="ki-duotone ki-chart-simple fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
            </i>
            Dashboard de Times
        </a>
        <?php endif; ?>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <input type="text" id="search-teams" class="form-control form-control-solid w-250px ps-13" placeholder="Buscar times..." />
            </div>
        </div>
    </div>
    <div class="card-body pt-0">
        <?php if (empty($teams)): ?>
            <div class="text-center py-20">
                <i class="ki-duotone ki-people fs-3x text-gray-400 mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhum time encontrado</h3>
                <div class="text-gray-500 fs-6 mb-7">Comece criando um novo time.</div>
                <?php if (\App\Helpers\Permission::can('teams.create')): ?>
                <a href="/teams/create" class="btn btn-primary">
                    <i class="ki-duotone ki-plus fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Criar Time
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-6 g-xl-9">
                <?php foreach ($teams as $team): ?>
                <div class="col-md-6 col-xl-4" data-team-name="<?= strtolower(htmlspecialchars($team['name'])) ?>">
                    <div class="card border border-2" style="border-color: <?= htmlspecialchars($team['color'] ?? '#009ef7') ?> !important;">
                        <div class="card-header border-0 pt-9">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-50px me-5" style="background-color: <?= htmlspecialchars($team['color'] ?? '#009ef7') ?>20;">
                                    <span class="symbol-label" style="color: <?= htmlspecialchars($team['color'] ?? '#009ef7') ?>;">
                                        <i class="ki-duotone ki-people fs-2x">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <a href="/teams/show?id=<?= $team['id'] ?>" class="text-gray-800 text-hover-primary fs-4 fw-bold"><?= htmlspecialchars($team['name']) ?></a>
                                    <?php if (!empty($team['department_name'])): ?>
                                    <span class="text-muted fw-semibold d-block fs-7">
                                        <i class="ki-duotone ki-abstract-26 fs-6 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <?= htmlspecialchars($team['department_name']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <?php if (!empty($team['description'])): ?>
                            <p class="text-gray-600 fs-7 mb-5"><?= nl2br(htmlspecialchars(mb_substr($team['description'], 0, 120))) ?></p>
                            <?php endif; ?>
                            
                            <div class="d-flex flex-wrap mb-5">
                                <div class="border border-gray-300 border-dashed rounded min-w-100px py-3 px-4 me-6 mb-3">
                                    <div class="fw-semibold text-gray-400">Membros</div>
                                    <div class="fs-2 fw-bold text-gray-800"><?= $team['members_count'] ?? 0 ?></div>
                                </div>
                                <?php if (!empty($team['leader_name'])): ?>
                                <div class="border border-gray-300 border-dashed rounded min-w-150px py-3 px-4 mb-3">
                                    <div class="fw-semibold text-gray-400">Líder</div>
                                    <div class="fs-7 fw-bold text-gray-800"><?= htmlspecialchars($team['leader_name']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <a href="/teams/show?id=<?= $team['id'] ?>" class="btn btn-sm btn-light btn-active-light-primary me-2">
                                    <i class="ki-duotone ki-eye fs-4 me-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Detalhes
                                </a>
                                <?php if (\App\Helpers\Permission::can('teams.edit')): ?>
                                <a href="/teams/edit?id=<?= $team['id'] ?>" class="btn btn-sm btn-light btn-active-light-primary me-2">
                                    <i class="ki-duotone ki-pencil fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </a>
                                <?php endif; ?>
                                <?php if (\App\Helpers\Permission::can('teams.delete')): ?>
                                <button class="btn btn-sm btn-light btn-active-light-danger" onclick="deleteTeam(<?= $team['id'] ?>, '<?= htmlspecialchars($team['name']) ?>')">
                                    <i class="ki-duotone ki-trash fs-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<!--end::Card-->

<script>
// Busca
document.getElementById('search-teams')?.addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('[data-team-name]');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-team-name');
        if (name.includes(search)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});

// Deletar time
function deleteTeam(id, name) {
    Swal.fire({
        title: 'Tem certeza?',
        text: `Deseja realmente deletar o time "${name}"? Todos os membros serão removidos.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar!',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/teams/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deletado!', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro!', 'Erro ao deletar time', 'error');
            });
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
