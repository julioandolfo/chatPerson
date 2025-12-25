<?php
/**
 * Página de Perfil / Preferências do Usuário
 */

$layout = 'layouts.metronic.app';
$title = $pageTitle ?? 'Meu Perfil';

use App\Helpers\Url;
use App\Models\UserSoundSettings;

$activeTab = $activeTab ?? 'notifications';
$user = $user ?? [];
$soundSettings = $soundSettings ?? [];
$availableSounds = $availableSounds ?? [];

// Content
ob_start();
?>

<div class="d-flex flex-column flex-column-fluid">
    <!--begin::Toolbar-->
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
                    <i class="ki-duotone ki-user fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Minhas Preferências
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="<?= Url::to('/') ?>" class="text-muted text-hover-primary">Home</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span class="bullet bg-gray-500 w-5px h-2px"></span>
                    </li>
                    <li class="breadcrumb-item text-muted">Preferências</li>
                </ul>
            </div>
        </div>
    </div>
    <!--end::Toolbar-->

    <!--begin::Content-->
    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-fluid">
            <div class="card">
                <div class="card-header card-header-stretch">
                    <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'notifications' ? 'active' : '' ?>" 
                               data-bs-toggle="tab" href="#tab_notifications">
                                <i class="ki-duotone ki-notification fs-4 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Notificações
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Tab: Notificações -->
                        <div class="tab-pane fade <?= $activeTab === 'notifications' ? 'show active' : '' ?>" id="tab_notifications">
                            <?php include __DIR__ . '/notifications-tab.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Content-->
</div>

<?php 
$content = ob_get_clean(); 
?>

<?php include __DIR__ . '/../layouts/metronic/app.php'; ?>
