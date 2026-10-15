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
        $version = self::iconVersion();

        // URLs relativas ao próprio manifest: funciona também com o sistema em subpasta.
        // "v" muda quando o logo muda, para os aparelhos buscarem o ícone novo.
        $icons = $version !== null
            ? [
                ['src' => "pwa/icon/192?v={$version}", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => "pwa/icon/512?v={$version}", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => "pwa/icon/512?maskable=1&v={$version}", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
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

        self::discardOutput();
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Ícone a partir do favicon/logo enviado em Configurações (PNG/JPG/WebP/GIF)
     */
    public function icon(string $size): void
    {
        $size = in_array((int)$size, [180, 192, 512], true) ? (int)$size : 192;
        $maskable = !empty($_GET['maskable']);
        $source = self::iconSourcePath();
        $defaultDir = dirname(__DIR__, 2) . '/public/assets/media/pwa/';

        if ($source === null || !function_exists('imagecreatetruecolor')) {
            $file = $size === 180 ? 'apple-touch-icon.png' : ($maskable ? 'icon-maskable-512.png' : "icon-{$size}.png");
            self::sendPng(file_get_contents($defaultDir . $file));
        }

        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/pwa-icons';
        $cacheFile = $cacheDir . '/' . md5(self::ICON_GENERATOR_VERSION . $source . filemtime($source) . $size . (int)$maskable) . '.png';
        if (is_file($cacheFile)) {
            self::sendPng(file_get_contents($cacheFile));
        }

        $png = self::renderIcon($source, $size, $maskable);
        if ($png === null) {
            self::sendPng(file_get_contents($defaultDir . ($size === 180 ? 'apple-touch-icon.png' : 'icon-512.png')));
        }

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        @file_put_contents($cacheFile, $png);

        self::sendPng($png);
    }

    /** Muda quando a lógica de geração muda (invalida o cache de ícones) */
    private const ICON_GENERATOR_VERSION = '2';

    /**
     * Desenha o ícone quadrado. O fundo é escolhido pelo próprio logo:
     * - logo com fundo próprio (sem transparência): estende a cor do canto;
     * - logo claro (ex.: branco para menu escuro): fundo na cor do sistema;
     * - logo escuro/colorido: fundo branco.
     */
    private static function renderIcon(string $path, int $size, bool $maskable): ?string
    {
        $source = @imagecreatefromstring((string)file_get_contents($path));
        if (!$source) {
            return null;
        }
        if (!imageistruecolor($source)) {
            imagepalettetotruecolor($source);
        }

        $w = imagesx($source);
        $h = imagesy($source);
        [$background, $hasOwnBackground] = self::pickBackground($source, $w, $h);

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $background[0], $background[1], $background[2]));

        // Área útil: maskable precisa caber na zona segura (~60% central); logo com fundo
        // próprio pode ocupar mais, porque o fundo se confunde com o do ícone
        $ratio = $maskable ? 0.6 : ($hasOwnBackground ? 0.9 : 0.72);
        $box = (int)round($size * $ratio);
        $scale = min($box / $w, $box / $h);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        imagecopyresampled($canvas, $source, (int)(($size - $nw) / 2), (int)(($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($canvas);
        return (string)ob_get_clean();
    }

    /**
     * @return array{0: int[], 1: bool} [cor RGB do fundo, logo tem fundo próprio]
     */
    private static function pickBackground($image, int $w, int $h): array
    {
        $brand = [0x1B, 0x84, 0xFF];
        $step = max(1, (int)floor(max($w, $h) / 80));
        $opaque = 0;
        $total = 0;
        $lumSum = 0.0;

        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F; // 0 = opaco, 127 = transparente
                $total++;
                if ($alpha > 64) {
                    continue;
                }
                $opaque++;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $lumSum += (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
            }
        }

        if ($opaque === 0) {
            return [$brand, false];
        }

        // Quase sem transparência: o logo tem fundo próprio → usa a cor do canto
        if ($opaque / $total > 0.97) {
            $corner = imagecolorat($image, 0, 0);
            return [[($corner >> 16) & 0xFF, ($corner >> 8) & 0xFF, $corner & 0xFF], true];
        }

        // Logo claro sobre transparente some no branco → fundo na cor do sistema
        $avgLum = $lumSum / $opaque;
        return [$avgLum > 0.7 ? $brand : [255, 255, 255], false];
    }

    private static function sendPng(string $png): void
    {
        self::discardOutput();
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($png));
        header('Cache-Control: public, max-age=86400');
        echo $png;
        exit;
    }

    /**
     * Descarta qualquer saída anterior (aviso do PHP, espaço em branco...), que
     * corromperia o PNG/JSON
     */
    private static function discardOutput(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Imagem usada para o ícone: o favicon (costuma ser quadrado) se for imagem comum e
     * não minúscula; senão o logo
     */
    private static function iconSourcePath(): ?string
    {
        $favicon = self::uploadedImagePath((string)SettingService::get('app_favicon', ''));
        if ($favicon !== null) {
            $info = @getimagesize($favicon);
            if ($info && min($info[0], $info[1]) >= 64) {
                return $favicon;
            }
        }
        return self::uploadedImagePath((string)SettingService::get('app_logo', ''));
    }

    private static function uploadedImagePath(string $relative): ?string
    {
        if ($relative === '' || !preg_match('/\.(png|jpe?g|webp|gif)$/i', $relative)) {
            return null;
        }
        $path = dirname(__DIR__, 2) . '/public/' . ltrim($relative, '/');
        return is_file($path) ? $path : null;
    }

    /**
     * Versão do ícone personalizado (null = sem imagem personalizada, usa o padrão)
     */
    public static function iconVersion(): ?string
    {
        $source = self::iconSourcePath();
        return $source === null ? null : substr(md5(self::ICON_GENERATOR_VERSION . $source . filemtime($source)), 0, 8);
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
