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
