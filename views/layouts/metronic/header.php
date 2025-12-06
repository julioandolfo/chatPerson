<!--begin::Header-->
<div id="kt_header" class="header mt-0 mt-lg-0 pt-lg-0" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{lg: '300px'}">
    <!--begin::Container-->
    <div class="container-fluid d-flex flex-stack flex-wrap gap-4 px-0" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-10 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <!--begin::Heading-->
            <?php 
            // Page title será inserido dinamicamente pelo JavaScript do Metronic
            // Por enquanto, mostrar título padrão
            $pageTitle = $title ?? 'Dashboard';
            ?>
            <h1 class="d-flex flex-column text-gray-900 fw-bold my-0 fs-1">
                <?= htmlspecialchars($pageTitle) ?>
                <small class="text-muted fs-6 fw-semibold pt-1">Sistema Multiatendimento</small>
            </h1>
            <!--end::Heading-->
        </div>
        <!--end::Page title=-->
        
        <!--begin::Wrapper-->
        <div class="d-flex d-lg-none align-items-center ms-n3 me-2">
            <!--begin::Aside mobile toggle-->
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle">
                <i class="ki-duotone ki-abstract-14 fs-1 mt-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
            </div>
            <!--end::Aside mobile toggle-->
            <!--begin::Logo-->
            <a href="<?= \App\Helpers\Url::to('/dashboard') ?>" class="d-flex align-items-center">
                <?php
                $appLogo = \App\Services\SettingService::get('app_logo', '');
                $appName = \App\Services\SettingService::get('app_name', 'Sistema Multiatendimento');
                ?>
                <?php if (!empty($appLogo)): ?>
                    <!-- Logo customizada -->
                    <img alt="<?= htmlspecialchars($appName) ?>" 
                         src="<?= \App\Helpers\Url::to($appLogo) ?>" 
                         class="h-20px" 
                         style="max-height: 20px; object-fit: contain;" />
                <?php else: ?>
                    <!-- Logo padrão -->
                    <img alt="Logo" src="<?= \App\Helpers\Url::asset('media/logos/demo3.svg') ?>" class="theme-light-show h-20px" />
                    <img alt="Logo" src="<?= \App\Helpers\Url::asset('media/logos/demo3-dark.svg') ?>" class="theme-dark-show h-20px" />
                <?php endif; ?>
            </a>
            <!--end::Logo-->
        </div>
        <!--end::Wrapper-->
        
        <!--begin::Topbar-->
        <div class="d-flex align-items-center flex-shrink-0 mb-0 mb-lg-0">
            <!--begin::Notifications-->
            <div class="d-flex align-items-center ms-3 ms-lg-4">
                <?php include __DIR__ . '/../../components/notifications-dropdown.php'; ?>
            </div>
            <!--end::Notifications-->
            
            <!--begin::Theme mode-->
            <div class="d-flex align-items-center ms-3 ms-lg-4">
                <!--begin::Menu toggle-->
                <a href="#" class="btn btn-icon btn-color-gray-700 btn-active-color-primary btn-outline w-40px h-40px" data-kt-menu-trigger="{default:'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                    <i class="ki-duotone ki-night-day theme-light-show fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                        <span class="path6"></span>
                        <span class="path7"></span>
                        <span class="path8"></span>
                        <span class="path9"></span>
                        <span class="path10"></span>
                    </i>
                    <i class="ki-duotone ki-moon theme-dark-show fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </a>
                <!--begin::Menu toggle-->
                <!--begin::Menu-->
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px" data-kt-menu="true" data-kt-element="theme-mode-menu">
                    <!--begin::Menu item-->
                    <div class="menu-item px-3 my-0">
                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="light">
                            <span class="menu-icon" data-kt-element="icon">
                                <i class="ki-duotone ki-night-day fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                    <span class="path5"></span>
                                    <span class="path6"></span>
                                    <span class="path7"></span>
                                    <span class="path8"></span>
                                    <span class="path9"></span>
                                    <span class="path10"></span>
                                </i>
                            </span>
                            <span class="menu-title">Light</span>
                        </a>
                    </div>
                    <!--end::Menu item-->
                    <!--begin::Menu item-->
                    <div class="menu-item px-3 my-0">
                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="dark">
                            <span class="menu-icon" data-kt-element="icon">
                                <i class="ki-duotone ki-moon fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Dark</span>
                        </a>
                    </div>
                    <!--end::Menu item-->
                    <!--begin::Menu item-->
                    <div class="menu-item px-3 my-0">
                        <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="system">
                            <span class="menu-icon" data-kt-element="icon">
                                <i class="ki-duotone ki-screen fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                            </span>
                            <span class="menu-title">System</span>
                        </a>
                    </div>
                    <!--end::Menu item-->
                </div>
                <!--end::Menu-->
            </div>
            <!--end::Theme mode-->
            
            <!--begin::User-->
            <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                <div class="cursor-pointer symbol symbol-30px symbol-md-40px" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                    <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                        <?= mb_substr(htmlspecialchars(\App\Helpers\Auth::userField('name', 'U')), 0, 1) ?>
                    </div>
                </div>
                <!--begin::Menu-->
                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                    <!--begin::Menu item-->
                    <div class="menu-item px-3">
                        <div class="menu-content d-flex align-items-center px-3">
                            <div class="symbol symbol-50px me-5">
                                <div class="symbol-label fs-3 fw-semibold text-primary bg-light-primary">
                                    <?= mb_substr(htmlspecialchars(\App\Helpers\Auth::userField('name', 'U')), 0, 1) ?>
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <div class="fw-bold d-flex align-items-center fs-5">
                                    <?= htmlspecialchars(\App\Helpers\Auth::userField('name', 'Usuário')) ?>
                                </div>
                                <a href="#" class="fw-semibold text-muted text-hover-primary fs-7">
                                    <?= htmlspecialchars(\App\Helpers\Auth::userField('email', '')) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!--end::Menu item-->
                    
                    <!--begin::Menu separator-->
                    <div class="separator my-2"></div>
                    <!--end::Menu separator-->
                    
                    <!--begin::Menu item-->
                    <div class="menu-item px-5">
                        <a href="<?= \App\Helpers\Url::to('/logout') ?>" class="menu-link px-5">
                            Sair
                        </a>
                    </div>
                    <!--end::Menu item-->
                </div>
                <!--end::Menu-->
            </div>
            <!--end::User-->
        </div>
        <!--end::Topbar-->
    </div>
    <!--end::Container-->
</div>
<!--end::Header-->

