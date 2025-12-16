<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Sistema Multiatendimento</title>
    <?php 
    try {
        if (class_exists('App\Services\SettingService')) {
            $favicon = \App\Services\SettingService::get('app_favicon', '');
            $faviconUrl = !empty($favicon) ? \App\Helpers\Url::to($favicon) : \App\Helpers\Url::asset('media/logos/favicon.ico');
        } else {
            $faviconUrl = \App\Helpers\Url::asset('media/logos/favicon.ico');
        }
    } catch (\Throwable $e) {
        $faviconUrl = \App\Helpers\Url::asset('media/logos/favicon.ico');
    }
    ?>
    <link rel="icon" type="image/x-icon" href="<?= $faviconUrl ?>" />
    <?php 
    try {
        if (!class_exists('App\Helpers\Url')) {
            require_once __DIR__ . '/../../app/Helpers/autoload.php';
        }
    } catch (\Throwable $e) {
        // Se houver erro, continuar mesmo assim
    }
    ?>
    
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <!--end::Fonts-->
    
    <!--begin::Global Stylesheets Bundle(used by all pages)-->
    <link href="<?= \App\Helpers\Url::asset('plugins/global/plugins.bundle.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= \App\Helpers\Url::asset('css/metronic/style.bundle.css') ?>" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
</head>
<body id="kt_body" class="auth-bg">
    <!--begin::Theme mode setup on page load-->
    <script>
        var defaultThemeMode = "light";
        var themeMode;
        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }
            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
    </script>
    <!--end::Theme mode setup on page load-->
    
    <!--begin::Main-->
    <!--begin::Root-->
    <div class="d-flex flex-column flex-root">
        <!--begin::Authentication - Sign-in -->
        <div class="d-flex flex-column flex-lg-row flex-column-fluid">
            <!--begin::Body-->
            <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
                <!--begin::Form-->
                <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                    <!--begin::Wrapper-->
                    <div class="w-lg-500px p-10">
                        <!--begin::Form-->
                        <?php 
                        $loginUrl = '/login';
                        try {
                            if (class_exists('App\Helpers\Url')) {
                                $loginUrl = \App\Helpers\Url::to('/login');
                            }
                        } catch (\Throwable $e) {
                            // Usar URL padrão se houver erro
                        }
                        ?>
                        <form class="form w-100" method="POST" action="<?= $loginUrl ?>" novalidate="novalidate" id="kt_sign_in_form">
                            <!--begin::Heading-->
                            <div class="text-center mb-11">
                                <!--begin::Title-->
                                <h1 class="text-gray-900 fw-bolder mb-3">Sistema Multiatendimento</h1>
                                <!--end::Title-->
                                <!--begin::Subtitle-->
                                <div class="text-gray-500 fw-semibold fs-6">Entre com suas credenciais</div>
                                <!--end::Subtitle-->
                            </div>
                            <!--end::Heading-->
                            
                            <!--begin::Alert-->
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                                    <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="d-flex flex-column">
                                        <h4 class="mb-1 text-danger">Erro</h4>
                                        <span><?= htmlspecialchars($error) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($errors)): ?>
                                <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                                    <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    <div class="d-flex flex-column">
                                        <h4 class="mb-1 text-danger">Erros de Validação</h4>
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $field => $fieldErrors): ?>
                                                <?php foreach ($fieldErrors as $err): ?>
                                                    <li><?= htmlspecialchars($err) ?></li>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!--end::Alert-->
                            
                            <!--begin::Input group=-->
                            <div class="fv-row mb-8">
                                <!--begin::Email-->
                                <input type="text" 
                                       placeholder="Email" 
                                       name="email" 
                                       autocomplete="off" 
                                       class="form-control bg-transparent" 
                                       value="<?= htmlspecialchars($email ?? '') ?>"
                                       required />
                                <!--end::Email-->
                            </div>
                            <!--end::Input group=-->
                            
                            <!--begin::Input group=-->
                            <div class="fv-row mb-3">
                                <!--begin::Password-->
                                <input type="password" 
                                       placeholder="Senha" 
                                       name="password" 
                                       autocomplete="off" 
                                       class="form-control bg-transparent" 
                                       required />
                                <!--end::Password-->
                            </div>
                            <!--end::Input group=-->
                            
                            <!--begin::Wrapper-->
                            <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                                <div></div>
                                <!--begin::Link-->
                                <a href="#" class="link-primary">Esqueceu a senha?</a>
                                <!--end::Link-->
                            </div>
                            <!--end::Wrapper-->
                            
                            <!--begin::Submit button-->
                            <div class="d-grid mb-10">
                                <button type="submit" id="kt_sign_in_submit" class="btn btn-primary">
                                    <!--begin::Indicator label-->
                                    <span class="indicator-label">Entrar</span>
                                    <!--end::Indicator label-->
                                    <!--begin::Indicator progress-->
                                    <span class="indicator-progress">Aguarde... 
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                    <!--end::Indicator progress-->
                                </button>
                            </div>
                            <!--end::Submit button-->
                        </form>
                        <!--end::Form-->
                    </div>
                    <!--end::Wrapper-->
                </div>
                <!--end::Form-->
            </div>
            <!--end::Body-->
            
            <!--begin::Aside-->
            <div class="d-flex flex-lg-row-fluid w-lg-50 bgi-size-cover bgi-position-center order-1 order-lg-2" style="background-image: url(<?= \App\Helpers\Url::asset('media/misc/auth-bg.png') ?>)">
                <div class="d-flex flex-column flex-center py-7 py-lg-15 px-5 px-md-15 w-100">
                    <!--begin::Logo-->
                    <a href="<?= \App\Helpers\Url::to('/') ?>" class="mb-12">
                        <?php 
                        $appLogo = \App\Services\SettingService::get('app_logo', '');
                        $logoUrl = !empty($appLogo) ? \App\Helpers\Url::to($appLogo) : \App\Helpers\Url::asset('media/logos/demo3.svg');
                        ?>
                        <img alt="Logo" src="<?= $logoUrl ?>" class="h-60px" />
                    </a>
                    <!--end::Logo-->
                    
                    <!--begin::Image-->
                    <img class="mw-100 mh-300px theme-light-show" alt="" src="<?= \App\Helpers\Url::asset('media/misc/auth-screens.png') ?>" onerror="this.style.display='none'" />
                    <!--end::Image-->
                    
                    <!--begin::Title-->
                    <h1 class="d-none d-lg-block text-white fs-2qx fw-bolder text-center mb-7">
                        Sistema Multiatendimento
                    </h1>
                    <!--end::Title-->
                    
                    <!--begin::Text-->
                    <div class="d-none d-lg-block text-white fs-base text-center">
                        Gerencie todas as suas conversas e atendimentos em um só lugar
                    </div>
                    <!--end::Text-->
                </div>
            </div>
            <!--end::Aside-->
        </div>
        <!--end::Authentication - Sign-in -->
    </div>
    <!--end::Root-->
    <!--end::Main-->
    
    <!--begin::Javascript-->
    <script>var hostUrl = "<?= \App\Helpers\Url::asset('') ?>";</script>
    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    <script src="<?= \App\Helpers\Url::asset('plugins/global/plugins.bundle.js') ?>"></script>
    <script src="<?= \App\Helpers\Url::asset('js/metronic/scripts.bundle.js') ?>"></script>
    <!--end::Global Javascript Bundle-->
    
    <!--begin::Custom Javascript(used for this page only)-->
    <script>
        // Form validation
        var form = document.getElementById('kt_sign_in_form');
        if (form) {
            form.addEventListener('submit', function(e) {
                var submitButton = document.getElementById('kt_sign_in_submit');
                if (submitButton) {
                    submitButton.setAttribute('data-kt-indicator', 'on');
                    submitButton.disabled = true;
                }
            });
        }
    </script>
    <!--end::Custom Javascript-->
    <!--end::Javascript-->
</body>
</html>

