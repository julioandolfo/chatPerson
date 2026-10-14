<?php
/**
 * Runtime do app instalável (PWA): service worker, instalação, push, offline.
 * Incluir antes de </body>.
 */
try {
    $pwaLoggedIn = \App\Helpers\Auth::check();
    $pwaUserId = \App\Helpers\Auth::id();
} catch (\Throwable $e) {
    $pwaLoggedIn = false;
    $pwaUserId = null;
}
$pwaConfig = [
    'scope' => \App\Helpers\Url::to('/'),
    'swUrl' => \App\Helpers\Url::to('sw.js'),
    'loggedIn' => $pwaLoggedIn,
    'userId' => $pwaUserId,
    'routes' => [
        'pushPublicKey' => \App\Helpers\Url::to('pwa/push/public-key'),
        'pushStatus' => \App\Helpers\Url::to('pwa/push/status'),
        'pushSubscribe' => \App\Helpers\Url::to('pwa/push/subscribe'),
        'pushUnsubscribe' => \App\Helpers\Url::to('pwa/push/unsubscribe'),
        'pushPreferences' => \App\Helpers\Url::to('pwa/push/preferences'),
        'pushTest' => \App\Helpers\Url::to('pwa/push/test'),
        'devices' => \App\Helpers\Url::to('pwa/devices'),
    ],
];
?>
<script>window.CP_PWA = <?= json_encode($pwaConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= \App\Helpers\Url::asset('js/pwa.js') ?>"></script>
