<?php
$layout = 'layouts.metronic.app';
$title = 'Listas de Contatos';
$pageTitle = 'Listas de Contatos';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    Listas de Contatos
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="/dashboard" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">Listas</li>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="/contact-lists/create" class="btn btn-sm btn-primary">
                    <i class="ki-duotone ki-plus fs-2"></i>
                    Nova Lista
                </a>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            
            <div class="row g-5 g-xl-8">
                <?php foreach ($lists as $list): ?>
                <div class="col-xl-4">
                    <div class="card card-flush h-xl-100">
                        <div class="card-header pt-7">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold text-gray-800"><?php echo htmlspecialchars($list['name']); ?></span>
                                <span class="text-gray-500 mt-1 fw-semibold fs-6"><?php echo $list['total_contacts']; ?> contatos</span>
                            </h3>
                            <div class="card-toolbar">
                                <button type="button" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                    <i class="ki-duotone ki-more-2 fs-3"></i>
                                </button>
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-semibold w-200px py-3" data-kt-menu="true">
                                    <div class="menu-item px-3">
                                        <a href="/contact-lists/<?php echo $list['id']; ?>" class="menu-link px-3">
                                            <i class="ki-duotone ki-eye fs-6 me-2"></i>
                                            Visualizar
                                        </a>
                                    </div>
                                    <div class="menu-item px-3">
                                        <a href="/contact-lists/<?php echo $list['id']; ?>/edit" class="menu-link px-3">
                                            <i class="ki-duotone ki-pencil fs-6 me-2"></i>
                                            Editar
                                        </a>
                                    </div>
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3 text-danger" onclick="deleteList(<?php echo $list['id']; ?>)">
                                            <i class="ki-duotone ki-trash fs-6 me-2"></i>
                                            Deletar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-5">
                            <div class="mb-5">
                                <div class="text-gray-700"><?php echo htmlspecialchars($list['description'] ?? 'Sem descrição'); ?></div>
                            </div>
                            <div class="separator my-5"></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-gray-600 fs-7">Criada em</span>
                                <span class="text-gray-800 fs-7"><?php echo date('d/m/Y', strtotime($list['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="/contact-lists/<?php echo $list['id']; ?>" class="btn btn-light-primary btn-sm w-100">
                                Ver Contatos
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($lists)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-15">
                            <i class="ki-duotone ki-information-5 fs-3x text-muted mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="fw-bold fs-3 mb-3">Nenhuma lista encontrada</div>
                            <div class="text-muted mb-5">Crie sua primeira lista para organizar seus contatos</div>
                            <a href="/contact-lists/create" class="btn btn-primary">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Criar Lista
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</div>

<script>
function deleteList(id) {
    if (!confirm('Deseja deletar esta lista? Esta ação não pode ser desfeita.')) return;
    
    fetch(`/contact-lists/${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toastr.success('Lista deletada!');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(err => toastr.error('Erro de rede'));
}
</script>
