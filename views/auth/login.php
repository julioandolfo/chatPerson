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

    // Nome e logo do app (com fallbacks — página é pré-autenticação)
    $appName = 'Sistema Multiatendimento';
    $appLogoUrl = '';
    try {
        if (class_exists('App\Services\SettingService')) {
            $appName = \App\Services\SettingService::get('app_name', $appName) ?: $appName;
            $appLogo = \App\Services\SettingService::get('app_logo', '');
            if (!empty($appLogo)) {
                $appLogoUrl = \App\Helpers\Url::to($appLogo);
            }
        }
    } catch (\Throwable $e) {
        // Fallbacks acima
    }
    ?>

    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <!--end::Fonts-->

    <!--begin::Global Stylesheets Bundle(used by all pages)-->
    <link href="<?= \App\Helpers\Url::asset('plugins/global/plugins.bundle.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= \App\Helpers\Url::asset('css/metronic/style.bundle.css') ?>" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->

    <style>
        /* ====================================================================
           LOGIN — card de vidro centralizado (linguagem do menu flutuante)
           ==================================================================== */
        :root {
            --lg-surface: rgba(255, 255, 255, 0.78);
            --lg-border: rgba(20, 30, 55, 0.08);
            --lg-shadow: 0 18px 50px rgba(20, 30, 55, 0.14);
            --lg-input-bg: rgba(255, 255, 255, 0.85);
            --lg-glow-1: rgba(var(--bs-primary-rgb), 0.16);
            --lg-glow-2: rgba(112, 72, 232, 0.10);
        }
        [data-bs-theme="dark"] {
            --lg-surface: rgba(30, 30, 45, 0.72);
            --lg-border: rgba(255, 255, 255, 0.09);
            --lg-shadow: 0 18px 50px rgba(0, 0, 0, 0.55);
            --lg-input-bg: rgba(0, 0, 0, 0.22);
            --lg-glow-1: rgba(var(--bs-primary-rgb), 0.14);
            --lg-glow-2: rgba(112, 72, 232, 0.12);
        }
        @supports not (backdrop-filter: blur(1px)) {
            :root { --lg-surface: rgba(255, 255, 255, 0.97); }
            [data-bs-theme="dark"] { --lg-surface: rgba(30, 30, 45, 0.97); }
        }

        body.login-page {
            min-height: 100vh;
            min-height: 100dvh;
            background:
                radial-gradient(900px 550px at 12% -8%, var(--lg-glow-1), transparent 60%),
                radial-gradient(800px 500px at 95% 105%, var(--lg-glow-2), transparent 60%),
                var(--bs-body-bg);
        }

        .login-wrap {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: var(--lg-surface);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--lg-border);
            border-radius: 22px;
            box-shadow: var(--lg-shadow);
            padding: 40px 36px 34px;
            animation: login-in 0.35s ease;
        }

        @keyframes login-in {
            from { opacity: 0; transform: translateY(14px) scale(0.99); }
            to   { opacity: 1; transform: none; }
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 18px;
            margin: 0 auto 18px;
            background: rgba(var(--bs-primary-rgb), 0.12);
            box-shadow: 0 6px 20px rgba(var(--bs-primary-rgb), 0.18);
            overflow: hidden;
        }

        .login-logo img {
            max-width: 48px;
            max-height: 48px;
            object-fit: contain;
        }

        .login-logo-letter {
            color: var(--bs-primary);
            font-weight: 700;
            font-size: 1.8rem;
        }

        .login-card .form-control {
            border-radius: 14px !important;
            border: 1px solid var(--lg-border) !important;
            background: var(--lg-input-bg) !important;
            padding: 13px 18px;
            font-size: 14px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .login-card .form-control:focus {
            border-color: rgba(var(--bs-primary-rgb), 0.5) !important;
            box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.12);
        }

        .login-card .alert {
            border-radius: 14px;
        }

        .login-submit {
            border-radius: 14px !important;
            padding: 13px !important;
            font-weight: 600;
            background: linear-gradient(135deg, var(--bs-primary) 0%, #1b84ff 100%) !important;
            border: 0 !important;
            box-shadow: 0 6px 18px rgba(var(--bs-primary-rgb), 0.32);
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }

        .login-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(var(--bs-primary-rgb), 0.4);
        }

        .login-footer-note {
            text-align: center;
            margin-top: 22px;
            font-size: 12px;
            color: var(--bs-gray-500);
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 30px 22px 26px;
                border-radius: 18px;
            }
        }
    </style>
</head>
<body id="kt_body" class="auth-bg login-page">
    <!--begin::Theme mode setup on page load-->
    <script>
        var defaultThemeMode = "dark";
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

    <!--begin::Login centralizado-->
    <div class="login-wrap">
        <div class="login-card">
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
                <div class="text-center mb-10">
                    <div class="login-logo">
                        <?php if (!empty($appLogoUrl)): ?>
                            <img alt="<?= htmlspecialchars($appName) ?>" src="<?= $appLogoUrl ?>" />
                        <?php else: ?>
                            <span class="login-logo-letter"><?= htmlspecialchars(mb_strtoupper(mb_substr($appName, 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-gray-900 fw-bolder mb-2 fs-2"><?= htmlspecialchars($appName) ?></h1>
                    <div class="text-gray-500 fw-semibold fs-6">Entre com suas credenciais</div>
                </div>
                <!--end::Heading-->

                <!--begin::Alert-->
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center p-5 mb-8">
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
                    <div class="alert alert-danger d-flex align-items-center p-5 mb-8">
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
                <div class="fv-row mb-6">
                    <input type="text"
                           placeholder="Email"
                           name="email"
                           autocomplete="off"
                           class="form-control"
                           value="<?= htmlspecialchars($email ?? '') ?>"
                           required />
                </div>
                <!--end::Input group=-->

                <!--begin::Input group=-->
                <div class="fv-row mb-3">
                    <input type="password"
                           placeholder="Senha"
                           name="password"
                           autocomplete="off"
                           class="form-control"
                           required />
                </div>
                <!--end::Input group=-->

                <!--begin::Wrapper-->
                <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                    <div></div>
                    <a href="#" class="link-primary">Esqueceu a senha?</a>
                </div>
                <!--end::Wrapper-->

                <!--begin::Submit button-->
                <div class="d-grid">
                    <button type="submit" id="kt_sign_in_submit" class="btn btn-primary login-submit">
                        <span class="indicator-label">Entrar</span>
                        <span class="indicator-progress">Aguarde...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
                <!--end::Submit button-->
            </form>

            <div class="login-footer-note">
                Gerencie todas as suas conversas e atendimentos em um só lugar
            </div>
        </div>
    </div>
    <!--end::Login centralizado-->

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
