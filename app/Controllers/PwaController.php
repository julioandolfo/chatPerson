<?php
/**
 * Controller PwaController
 * App instalável (PWA): manifest, ícone, notificações push e aparelhos conectados
 */

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\PushService;
use App\Services\RememberMeService;
use App\Services\SettingService;

class PwaController
{
    private const DEFAULT_NAME = 'Sistema Multiatendimento';
    private const THEME_COLOR = '#1B84FF';

    // =====================================================================
    // MANIFEST E ÍCONES (públicos: o navegador busca o manifest sem cookies)
    // =====================================================================

    public function manifest(): void
    {
        $name = trim((string)SettingService::get('app_name', '')) ?: self::DEFAULT_NAME;
        $customIcon = self::customLogoPath() !== null;

        // URLs relativas ao próprio manifest: funciona também com o sistema em subpasta
        $icons = $customIcon
            ? [
                ['src' => 'pwa/icon/192', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => 'pwa/icon/512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => 'pwa/icon/512?maskable=1', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ]
            : [
                ['src' => 'assets/media/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => 'assets/media/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => 'assets/media/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ];

        $manifest = [
            'id' => './conversations',
            'name' => $name,
            'short_name' => self::shortName($name),
            'description' => 'Atendimento e conversas',
            'lang' => 'pt-BR',
            'start_url' => './conversations?source=pwa',
            'scope' => './',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => self::THEME_COLOR,
            'icons' => $icons,
            'shortcuts' => [
                [
                    'name' => 'Não respondidas',
                    'url' => './conversations?status=open&unanswered=1&source=pwa',
                    'icons' => [['src' => $icons[0]['src'], 'sizes' => '192x192']],
                ],
                [
                    'name' => 'Conversas abertas',
                    'url' => './conversations?status=open&source=pwa',
                    'icons' => [['src' => $icons[0]['src'], 'sizes' => '192x192']],
                ],
            ],
        ];

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Ícone a partir do logo enviado em Configurações (PNG/JPG/WebP/GIF)
     */
    public function icon(string $size): void
    {
        $size = in_array((int)$size, [180, 192, 512], true) ? (int)$size : 192;
        $maskable = !empty($_GET['maskable']);
        $logo = self::customLogoPath();

        if ($logo === null || !function_exists('imagecreatetruecolor')) {
            $file = $size === 180 ? 'apple-touch-icon.png' : ($maskable ? 'icon-maskable-512.png' : "icon-{$size}.png");
            self::sendPng(file_get_contents(dirname(__DIR__, 2) . '/public/assets/media/pwa/' . $file));
        }

        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/pwa-icons';
        $cacheFile = $cacheDir . '/' . md5($logo . filemtime($logo) . $size . (int)$maskable) . '.png';
        if (is_file($cacheFile)) {
            self::sendPng(file_get_contents($cacheFile));
        }

        $source = @imagecreatefromstring(file_get_contents($logo));
        if (!$source) {
            self::sendPng(file_get_contents(dirname(__DIR__, 2) . '/public/assets/media/pwa/icon-512.png'));
        }

        // Fundo branco, logo centralizado. Maskable: logo dentro da zona segura (~60%)
        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $box = (int)round($size * ($maskable ? 0.6 : 0.8));
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min($box / $w, $box / $h);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        imagecopyresampled($canvas, $source, (int)(($size - $nw) / 2), (int)(($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        @file_put_contents($cacheFile, $png);

        self::sendPng($png);
    }

    private static function sendPng(string $png): void
    {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        echo $png;
        exit;
    }

    private static function customLogoPath(): ?string
    {
        $logo = (string)SettingService::get('app_logo', '');
        if ($logo === '' || !preg_match('/\.(png|jpe?g|webp|gif)$/i', $logo)) {
            return null;
        }
        $path = dirname(__DIR__, 2) . '/public/' . ltrim($logo, '/');
        return is_file($path) ? $path : null;
    }

    private static function shortName(string $name): string
    {
        if (mb_strlen($name) <= 12) {
            return $name;
        }
        $first = explode(' ', $name)[0];
        return mb_strlen($first) <= 12 ? $first : mb_substr($first, 0, 12);
    }

    // =====================================================================
    // PUSH
    // =====================================================================

    public function pushPublicKey(): void
    {
        try {
            Response::json(['success' => true, 'public_key' => PushService::getVapid()['public']]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Notificações push indisponíveis: ' . $e->getMessage()], 500);
        }
    }

    public function pushStatus(): void
    {
        $endpoint = (string)(Request::json()['endpoint'] ?? Request::get('endpoint', ''));
        $subscription = $endpoint !== '' ? PushService::getSubscription((int)Auth::id(), $endpoint) : null;
        Response::json(['success' => true, 'subscribed' => $subscription !== null, 'subscription' => $subscription]);
    }

    public function pushSubscribe(): void
    {
        $data = Request::json();
        try {
            $subscription = PushService::subscribe(
                (int)Auth::id(),
                is_array($data['subscription'] ?? null) ? $data['subscription'] : [],
                filter_var($data['show_preview'] ?? true, FILTER_VALIDATE_BOOLEAN)
            );
            Response::json(['success' => true, 'message' => 'Notificações ativadas neste aparelho', 'subscription' => $subscription]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erro ao ativar notificações: ' . $e->getMessage()], 500);
        }
    }

    public function pushUnsubscribe(): void
    {
        $endpoint = (string)(Request::json()['endpoint'] ?? '');
        PushService::unsubscribe((int)Auth::id(), $endpoint);
        Response::json(['success' => true, 'message' => 'Notificações desativadas neste aparelho']);
    }

    public function pushPreferences(): void
    {
        $data = Request::json();
        $ok = PushService::setShowPreview(
            (int)Auth::id(),
            (string)($data['endpoint'] ?? ''),
            filter_var($data['show_preview'] ?? true, FILTER_VALIDATE_BOOLEAN)
        );
        Response::json(['success' => $ok, 'message' => $ok ? 'Preferência salva' : 'Aparelho não inscrito'], $ok ? 200 : 404);
    }

    /**
     * Push de teste para os aparelhos do usuário
     */
    public function pushTest(): void
    {
        $userId = (int)Auth::id();
        PushService::queue($userId, [
            'title' => 'Notificações funcionando ✅',
            'body' => 'Você vai receber as novas mensagens aqui.',
            'preview' => null,
            'conversation_id' => null,
            'type' => 'test',
        ]);
        $stats = PushService::flush();

        if ($stats['sent'] > 0) {
            Response::json(['success' => true, 'message' => "Teste enviado para {$stats['sent']} aparelho(s)"] + $stats);
        } else {
            Response::json(['success' => false, 'message' => 'Nenhum aparelho recebeu o teste. Ative as notificações neste aparelho.'] + $stats, 422);
        }
    }

    // =====================================================================
    // APARELHOS CONECTADOS ("Manter conectado")
    // =====================================================================

    public function devices(): void
    {
        try {
            Response::json(['success' => true, 'devices' => RememberMeService::listForUser((int)Auth::id())]);
        } catch (\Throwable $e) {
            Response::json(['success' => true, 'devices' => []]);
        }
    }

    public function revokeDevice(int $id): void
    {
        $ok = RememberMeService::revoke((int)Auth::id(), $id);
        Response::json(['success' => $ok, 'message' => $ok ? 'Aparelho desconectado' : 'Aparelho não encontrado'], $ok ? 200 : 404);
    }
}
