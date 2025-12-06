<?php
$layout = 'layouts.metronic.app';
$title = 'Integrações';

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Integrações</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="row g-5">
            <!--begin::Col-->
            <div class="col-xl-4">
                <div class="card card-flush h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center mb-5">
                            <div class="symbol symbol-50px me-5">
                                <div class="symbol-label bg-light-success">
                                    <i class="ki-duotone ki-sms fs-2x text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold text-gray-900 mb-1">WhatsApp</h3>
                                <span class="text-muted fw-semibold d-block fs-7">Quepasa e Evolution API</span>
                            </div>
                        </div>
                        <div class="mb-5">
                            <span class="badge badge-light-warning">Em desenvolvimento</span>
                        </div>
                        <a href="<?= \App\Helpers\Url::to('/integrations/whatsapp') ?>" class="btn btn-light-primary w-100">
                            Configurar
                        </a>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
    </div>
</div>
<!--end::Card-->

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>

