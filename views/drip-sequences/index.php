<?php
$layout = 'layouts.metronic.app';
$title = 'Sequências Drip';
$pageTitle = 'Sequências Drip';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Sequências Drip (Campanhas Multi-etapas)
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">Drip Sequences</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="/drip-sequences/create" class="btn btn-sm btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Nova Sequência
                </a>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted bg-light">
                                    <th class="ps-4 min-w-200px">Sequência</th>
                                    <th class="min-w-100px">Status</th>
                                    <th class="min-w-100px">Etapas</th>
                                    <th class="min-w-100px">Contatos</th>
                                    <th class="min-w-150px">Criada</th>
                                    <th class="text-end pe-4 min-w-100px">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sequences)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-10">
                                        <div class="text-gray-600">
                                            <i class="ki-duotone ki-information fs-3x mb-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                            </i>
                                            <div class="fw-bold fs-5">Nenhuma sequência encontrada</div>
                                            <div class="fs-7 mt-2">Crie sua primeira sequência drip para nutrição automática</div>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($sequences as $seq): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex flex-column">
                                            <a href="/drip-sequences/<?php echo $seq['id']; ?>" class="text-gray-900 fw-bold text-hover-primary mb-1 fs-6">
                                                <?php echo htmlspecialchars($seq['name']); ?>
                                            </a>
                                            <?php if ($seq['description']): ?>
                                            <span class="text-muted fw-semibold d-block fs-7"><?php echo htmlspecialchars($seq['description']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($seq['status'] === 'active'): ?>
                                        <span class="badge badge-light-success">Ativa</span>
                                        <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-light-primary"><?php echo $seq['total_steps']; ?> etapas</span></td>
                                    <td><span class="badge badge-light-info"><?php echo $seq['total_contacts']; ?> contatos</span></td>
                                    <td class="text-muted fs-7"><?php echo date('d/m/Y H:i', strtotime($seq['created_at'])); ?></td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light btn-active-light-primary" type="button" data-bs-toggle="dropdown">
                                                Ações <i class="ki-duotone ki-down fs-5 ms-1"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="/drip-sequences/<?php echo $seq['id']; ?>"><i class="ki-duotone ki-eye fs-6 me-2"></i>Visualizar</a></li>
                                                <li><a class="dropdown-item" href="/drip-sequences/<?php echo $seq['id']; ?>/edit"><i class="ki-duotone ki-pencil fs-6 me-2"></i>Editar</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteSequence(<?php echo $seq['id']; ?>)"><i class="ki-duotone ki-trash fs-6 me-2"></i>Deletar</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
function deleteSequence(id) {
    if (!confirm('Deseja deletar esta sequência? Esta ação não pode ser desfeita.')) return;
    
    fetch(`/drip-sequences/${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Sequência deletada!');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}
</script>
