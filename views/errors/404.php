<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>404 - Página não encontrada</title>
    <?php require_once __DIR__ . '/../../app/Helpers/autoload.php'; ?>
    
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <!--end::Fonts-->
    
    <!--begin::Global Stylesheets Bundle(used by all pages)-->
    <link href="<?= \App\Helpers\Url::asset('plugins/global/plugins.bundle.css') ?>" rel="stylesheet" type="text/css" />
    <link href="<?= \App\Helpers\Url::asset('css/metronic/style.bundle.css') ?>" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
</head>
<body id="kt_body" class="app-blank">
    <!--begin::Root-->
    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <!--begin::Page bg image-->
        <style>
            body {
                background-image: url('<?= \App\Helpers\Url::asset('media/illustrations/auth-bg.png') ?>');
            }
            [data-bs-theme="dark"] body {
                background-image: url('<?= \App\Helpers\Url::asset('media/illustrations/auth-bg-dark.png') ?>');
            }
        </style>
        <!--end::Page bg image-->
        
        <!--begin::Authentication - Error 404 -->
        <div class="d-flex flex-column flex-center flex-column-fluid">
            <!--begin::Content-->
            <div class="d-flex flex-column flex-center text-center p-10">
                <!--begin::Wrapper-->
                <div class="card card-flush w-lg-650px py-5">
                    <div class="card-body py-15 py-lg-20">
                        <!--begin::Title-->
                        <h1 class="fw-bolder fs-2hx text-gray-900 mb-4">404</h1>
                        <!--end::Title-->
                        
                        <!--begin::Text-->
                        <div class="fw-semibold fs-6 text-gray-500 mb-7">
                            <?= htmlspecialchars($message ?? 'A página que você está procurando não existe.') ?>
                        </div>
                        <!--end::Text-->
                        
                        <!--begin::Illustration-->
                        <div class="mb-3">
                            <img src="<?= \App\Helpers\Url::asset('media/illustrations/404-error.png') ?>" 
                                 class="mw-100 mh-300px theme-light-show" 
                                 alt="404 Error" 
                                 onerror="this.style.display='none'" />
                        </div>
                        <!--end::Illustration-->
                        
                        <!--begin::Link-->
                        <div class="mb-0">
                            <a href="<?= \App\Helpers\Url::to('/dashboard') ?>" class="btn btn-sm btn-primary">
                                Voltar ao Dashboard
                            </a>
                        </div>
                        <!--end::Link-->
                    </div>
                </div>
                <!--end::Wrapper-->
            </div>
            <!--end::Content-->
        </div>
        <!--end::Authentication - Error 404 -->
    </div>
    <!--end::Root-->
    
    <!--begin::Javascript-->
    <script>var hostUrl = "<?= \App\Helpers\Url::asset('') ?>";</script>
    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    <script src="<?= \App\Helpers\Url::asset('plugins/global/plugins.bundle.js') ?>"></script>
    <script src="<?= \App\Helpers\Url::asset('js/metronic/scripts.bundle.js') ?>"></script>
    <!--end::Global Javascript Bundle-->
    <!--end::Javascript-->
</body>
</html>

