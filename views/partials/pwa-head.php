<?php
/**
 * Meta tags do app instalável (PWA). Incluir dentro do <head>.
 */
try {
    $pwaAppName = trim((string)\App\Services\SettingService::get('app_name', '')) ?: 'Sistema Multiatendimento';
    $pwaLogo = (string)\App\Services\SettingService::get('app_logo', '');
} catch (\Throwable $e) {
    // Banco indisponível (ex.: página de erro): segue com os padrões
    $pwaAppName = 'Sistema Multiatendimento';
    $pwaLogo = '';
}
$pwaAppleIcon = preg_match('/\.(png|jpe?g|webp|gif)$/i', $pwaLogo) && is_file(dirname(__DIR__, 2) . '/public/' . ltrim($pwaLogo, '/'))
    ? \App\Helpers\Url::to('pwa/icon/180')
    : \App\Helpers\Url::asset('media/pwa/apple-touch-icon.png');
?>
    <link rel="manifest" href="<?= \App\Helpers\Url::to('manifest.webmanifest') ?>" />
    <meta name="theme-color" content="#1B84FF" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($pwaAppName, ENT_QUOTES) ?>" />
    <link rel="apple-touch-icon" href="<?= $pwaAppleIcon ?>" />
