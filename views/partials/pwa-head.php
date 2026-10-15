<?php
/**
 * Meta tags do app instalável (PWA). Incluir dentro do <head>.
 */
try {
    $pwaAppName = trim((string)\App\Services\SettingService::get('app_name', '')) ?: 'Sistema Multiatendimento';
} catch (\Throwable $e) {
    // Banco indisponível (ex.: página de erro): segue com os padrões
    $pwaAppName = 'Sistema Multiatendimento';
}
try {
    $pwaIconVersion = \App\Controllers\PwaController::iconVersion();
} catch (\Throwable $e) {
    $pwaIconVersion = null;
}
// Mesmo ícone do manifest (gerado a partir do favicon/logo de Configurações, ou o padrão)
$pwaAppleIcon = $pwaIconVersion !== null
    ? \App\Helpers\Url::to('pwa/icon/180?v=' . $pwaIconVersion)
    : \App\Helpers\Url::to('assets/media/pwa/apple-touch-icon.png');
?>
    <link rel="manifest" href="<?= \App\Helpers\Url::to('manifest.webmanifest') ?>" />
    <meta name="theme-color" content="#1B84FF" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($pwaAppName, ENT_QUOTES) ?>" />
    <link rel="apple-touch-icon" href="<?= $pwaAppleIcon ?>" />
    <script>
        // App instalado (PWA): marca o <html> já no carregamento (antes do conteúdo aparecer)
        (function () {
            var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
            window.CP_IS_PWA = standalone;
            if (standalone) document.documentElement.classList.add('pwa-standalone');
        })();
    </script>
    <style>
        /* No app instalado: sem Copiloto e sem telefone (Api4Com/webphone) */
        html.pwa-standalone button[data-bs-target="#copilotPanel"],
        html.pwa-standalone #copilotPanel,
        html.pwa-standalone #btnApi4ComCall,
        html.pwa-standalone #api4com-webphone-widget {
            display: none !important;
        }
    </style>
