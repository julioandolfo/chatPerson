<?php
$layout = 'layouts.metronic.app';
$title = 'Sequências Drip';

// Content
ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h2 class="fw-bold">Sequências Drip (Campanhas Multi-etapas)</h2>
        </div>
        <div class="card-toolbar">
            <a href="<?= \App\Helpers\Url::to('/drip-sequences/create') ?>" class="btn btn-sm btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i>
                Nova Sequência
            </a>
        </div>
    </div>
    <div class="card-body pt-0">
        
        <!--begin::Table-->
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                        <th class="min-w-200px">Sequência</th>
                        <th class="min-w-100px">Status</th>
                        <th class="min-w-100px">Etapas</th>
                        <th class="min-w-100px">Contatos</th>
                        <th class="min-w-150px">Criada</th>
                        <th class="text-end min-w-70px">Ações</th>
                    </tr>
                </thead>
                <tbody id="sequences-tbody" class="text-gray-600 fw-semibold">
                    <tr>
                        <td colspan="6" class="text-center py-10">
                            <i class="ki-duotone ki-information fs-3x text-gray-400 mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="fw-bold fs-5">Nenhuma sequência encontrada</div>
                            <div class="fs-7 mt-2">Crie sua primeira sequência drip para nutrição automática</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!--end::Table-->
        
    </div>
</div>
<!--end::Card-->

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/metronic/app.php';
?>
