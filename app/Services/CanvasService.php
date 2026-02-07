<?php
/**
 * Service CanvasService
 * Processa dados do canvas Fabric.js e gera imagem
 */

namespace App\Services;

class CanvasService
{
    /**
     * Renderizar canvas para imagem
     * Recebe o JSON do Fabric.js e converte para imagem PNG
     * 
     * NOTA: Esta é uma implementação simplificada.
     * Para produção, considere usar Node.js com canvas ou puppeteer para renderização server-side
     */
    public static function renderToImage(array $canvasData, int $conversationId): array
    {
        $startTime = microtime(true);

        try {
            // Extrair dimensões do canvas
            $width = $canvasData['width'] ?? 1024;
            $height = $canvasData['height'] ?? 1024;
            $objects = $canvasData['objects'] ?? [];

            // Criar imagem
            $image = @imagecreatetruecolor($width, $height);
            if (!$image) {
                return [
                    'success' => false,
                    'error' => 'Falha ao criar imagem'
                ];
            }

            // Background branco
            $bgColor = self::parseColor($canvasData['backgroundColor'] ?? '#FFFFFF');
            $white = imagecolorallocate($image, $bgColor['r'], $bgColor['g'], $bgColor['b']);
            imagefill($image, 0, 0, $white);

            // Processar objetos do canvas
            foreach ($objects as $obj) {
                $type = $obj['type'] ?? 'object';
                
                switch ($type) {
                    case 'image':
                        self::renderImage($image, $obj);
                        break;
                    case 'text':
                    case 'i-text':
                    case 'textbox':
                        self::renderText($image, $obj);
                        break;
                    case 'rect':
                        self::renderRect($image, $obj);
                        break;
                    case 'circle':
                        self::renderCircle($image, $obj);
                        break;
                    case 'line':
                        self::renderLine($image, $obj);
                        break;
                }
            }

            // Salvar imagem
            $filename = 'canvas_' . uniqid() . '_' . time() . '.png';
            $savePath = "assets/media/mockups/{$conversationId}/{$filename}";
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $savePath;

            // Criar diretório se não existir
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            // Salvar
            $saved = @imagepng($image, $fullPath, 9);
            
            if (!$saved) {
                @imagedestroy($image);
                return [
                    'success' => false,
                    'error' => 'Falha ao salvar imagem'
                ];
            }

            // Gerar thumbnail
            $thumbFilename = 'thumb_' . $filename;
            $thumbPath = "assets/media/mockups/{$conversationId}/{$thumbFilename}";
            $thumbFullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $thumbPath;
            
            $thumb = self::createThumbnail($image, 300);
            if ($thumb) {
                @imagepng($thumb, $thumbFullPath, 9);
                @imagedestroy($thumb);
            }

            // Obter tamanho
            $fileSize = @filesize($fullPath) ?: 0;

            @imagedestroy($image);

            $processingTime = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'image_path' => $savePath,
                'thumbnail_path' => $thumbPath,
                'file_size' => $fileSize,
                'processing_time' => $processingTime
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Renderizar imagem no canvas
     */
    private static function renderImage($canvas, array $obj): void
    {
        try {
            $src = $obj['src'] ?? null;
            if (!$src) return;

            // Converter caminho para absoluto
            if (!preg_match('/^https?:\/\//', $src)) {
                $src = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($src, '/');
            }

            // Carregar imagem
            $sourceImage = @self::loadImageFromFile($src);
            if (!$sourceImage) return;

            // Obter dimensões
            $srcWidth = imagesx($sourceImage);
            $srcHeight = imagesy($sourceImage);

            // Posição e tamanho
            $left = $obj['left'] ?? 0;
            $top = $obj['top'] ?? 0;
            $width = $obj['width'] ?? $srcWidth;
            $height = $obj['height'] ?? $srcHeight;
            $scaleX = $obj['scaleX'] ?? 1;
            $scaleY = $obj['scaleY'] ?? 1;

            $destWidth = $width * $scaleX;
            $destHeight = $height * $scaleY;

            // Copiar e redimensionar
            @imagecopyresampled(
                $canvas, $sourceImage,
                $left, $top,
                0, 0,
                $destWidth, $destHeight,
                $srcWidth, $srcHeight
            );

            @imagedestroy($sourceImage);

        } catch (\Exception $e) {
            // Ignorar erro de imagem específica
        }
    }

    /**
     * Renderizar texto no canvas
     */
    private static function renderText($canvas, array $obj): void
    {
        try {
            $text = $obj['text'] ?? '';
            if (empty($text)) return;

            $left = $obj['left'] ?? 0;
            $top = $obj['top'] ?? 0;
            $fontSize = $obj['fontSize'] ?? 20;
            $fontFamily = $obj['fontFamily'] ?? 'Arial';
            
            // Cor do texto
            $fillColor = self::parseColor($obj['fill'] ?? '#000000');
            $color = imagecolorallocate($canvas, $fillColor['r'], $fillColor['g'], $fillColor['b']);

            // Usar fonte TrueType se disponível
            $fontPath = self::getFontPath($fontFamily);
            
            if ($fontPath && function_exists('imagettftext')) {
                @imagettftext($canvas, $fontSize, 0, $left, $top + $fontSize, $color, $fontPath, $text);
            } else {
                // Fallback para fonte padrão
                @imagestring($canvas, 5, $left, $top, $text, $color);
            }

        } catch (\Exception $e) {
            // Ignorar erro de texto
        }
    }

    /**
     * Renderizar retângulo
     */
    private static function renderRect($canvas, array $obj): void
    {
        try {
            $left = $obj['left'] ?? 0;
            $top = $obj['top'] ?? 0;
            $width = $obj['width'] ?? 100;
            $height = $obj['height'] ?? 100;
            
            $fillColor = self::parseColor($obj['fill'] ?? '#CCCCCC');
            $color = imagecolorallocate($canvas, $fillColor['r'], $fillColor['g'], $fillColor['b']);

            @imagefilledrectangle($canvas, $left, $top, $left + $width, $top + $height, $color);

            // Stroke
            if (!empty($obj['stroke'])) {
                $strokeColor = self::parseColor($obj['stroke']);
                $stroke = imagecolorallocate($canvas, $strokeColor['r'], $strokeColor['g'], $strokeColor['b']);
                @imagerectangle($canvas, $left, $top, $left + $width, $top + $height, $stroke);
            }

        } catch (\Exception $e) {
            // Ignorar
        }
    }

    /**
     * Renderizar círculo
     */
    private static function renderCircle($canvas, array $obj): void
    {
        try {
            $left = $obj['left'] ?? 0;
            $top = $obj['top'] ?? 0;
            $radius = $obj['radius'] ?? 50;
            
            $fillColor = self::parseColor($obj['fill'] ?? '#CCCCCC');
            $color = imagecolorallocate($canvas, $fillColor['r'], $fillColor['g'], $fillColor['b']);

            @imagefilledellipse($canvas, $left + $radius, $top + $radius, $radius * 2, $radius * 2, $color);

        } catch (\Exception $e) {
            // Ignorar
        }
    }

    /**
     * Renderizar linha
     */
    private static function renderLine($canvas, array $obj): void
    {
        try {
            $x1 = $obj['x1'] ?? 0;
            $y1 = $obj['y1'] ?? 0;
            $x2 = $obj['x2'] ?? 100;
            $y2 = $obj['y2'] ?? 100;
            
            $strokeColor = self::parseColor($obj['stroke'] ?? '#000000');
            $color = imagecolorallocate($canvas, $strokeColor['r'], $strokeColor['g'], $strokeColor['b']);

            @imageline($canvas, $x1, $y1, $x2, $y2, $color);

        } catch (\Exception $e) {
            // Ignorar
        }
    }

    /**
     * Parse de cor hex para RGB
     */
    private static function parseColor(string $hex): array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }

    /**
     * Carregar imagem de arquivo
     */
    private static function loadImageFromFile(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) return false;

        list($width, $height, $type) = $info;

        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                return @imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    /**
     * Obter caminho de fonte
     */
    private static function getFontPath(string $fontFamily): ?string
    {
        // Mapear fontes comuns
        $fontMap = [
            'Arial' => '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'Helvetica' => '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'Times' => '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
            'Courier' => '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
        ];

        $path = $fontMap[$fontFamily] ?? null;
        
        if ($path && file_exists($path)) {
            return $path;
        }

        // Tentar fonte padrão
        $defaultFont = __DIR__ . '/../../public/assets/fonts/Arial.ttf';
        if (file_exists($defaultFont)) {
            return $defaultFont;
        }

        return null;
    }

    /**
     * Criar thumbnail
     */
    private static function createThumbnail($source, int $maxWidth): ?\GdImage
    {
        try {
            $width = imagesx($source);
            $height = imagesy($source);

            if ($width <= $maxWidth) {
                return imagescale($source, $width, $height);
            }

            $newWidth = $maxWidth;
            $newHeight = intval($height * ($maxWidth / $width));

            $thumb = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            return $thumb;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Renderizar canvas via Node.js (método alternativo mais robusto)
     * Requer node.js e canvas npm package instalados
     * 
     * Para usar este método, instale:
     * npm install canvas
     */
    public static function renderWithNode(array $canvasData, int $conversationId): array
    {
        try {
            // Salvar JSON temporário
            $tempJson = sys_get_temp_dir() . '/canvas_' . uniqid() . '.json';
            file_put_contents($tempJson, json_encode($canvasData));

            // Script Node.js (criar em public/assets/js/canvas-renderer.js)
            $nodeScript = $_SERVER['DOCUMENT_ROOT'] . '/assets/js/canvas-renderer.js';
            
            if (!file_exists($nodeScript)) {
                @unlink($tempJson);
                return self::renderToImage($canvasData, $conversationId); // Fallback
            }

            // Executar Node.js
            $outputPath = "assets/media/mockups/{$conversationId}/canvas_" . uniqid() . ".png";
            $fullOutputPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $outputPath;

            $cmd = "node " . escapeshellarg($nodeScript) . " " . escapeshellarg($tempJson) . " " . escapeshellarg($fullOutputPath);
            exec($cmd, $output, $returnCode);

            @unlink($tempJson);

            if ($returnCode !== 0 || !file_exists($fullOutputPath)) {
                return self::renderToImage($canvasData, $conversationId); // Fallback
            }

            // Gerar thumbnail
            $thumbFilename = 'thumb_' . basename($outputPath);
            $thumbPath = "assets/media/mockups/{$conversationId}/{$thumbFilename}";
            $thumbFullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $thumbPath;
            
            DALLEService::generateThumbnail($fullOutputPath, $thumbFullPath, 300);

            $fileSize = @filesize($fullOutputPath) ?: 0;

            return [
                'success' => true,
                'image_path' => $outputPath,
                'thumbnail_path' => $thumbPath,
                'file_size' => $fileSize,
                'processing_time' => 0
            ];

        } catch (\Exception $e) {
            return self::renderToImage($canvasData, $conversationId); // Fallback
        }
    }
}
