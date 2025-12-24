<?php
/**
 * Helper de URLs
 * Detecta automaticamente se está em subdiretório ou raiz
 */

namespace App\Helpers;

class Url
{
    private static ?string $basePath = null;

    /**
     * Obter base path da aplicação
     */
    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $scriptDir = dirname($scriptName);
        
        // Normalizar caminhos
        $scriptDir = str_replace('\\', '/', $scriptDir);
        
        // Remover /public do caminho (pasta public é apenas entrada, não parte do base path)
        $scriptDir = str_replace('/public', '', $scriptDir);
        $scriptDir = rtrim($scriptDir, '/');
        
        // Se estiver na raiz, retornar vazio
        if ($scriptDir === '/' || $scriptDir === '' || $scriptDir === '.') {
            self::$basePath = '';
        } else {
            self::$basePath = $scriptDir;
        }

        return self::$basePath;
    }

    /**
     * Gerar URL completa
     */
    public static function to(string $path = ''): string
    {
        $base = self::basePath();
        $path = ltrim($path, '/');
        
        if (empty($base)) {
            return '/' . $path;
        }
        
        return $base . '/' . $path;
    }

    /**
     * Gerar URL para asset
     */
    public static function asset(string $path): string
    {
        return self::to('assets/' . ltrim($path, '/'));
    }

    /**
     * Gerar URL para API
     */
    public static function api(string $path = ''): string
    {
        return self::to('api/v1/' . ltrim($path, '/'));
    }

    /**
     * Gerar URL completa com protocolo e domínio
     * Sempre usa HTTPS, exceto em localhost (desenvolvimento)
     */
    public static function fullUrl(string $path = ''): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $relativePath = self::to($path);
        
        // Sempre usar HTTPS, exceto em localhost (desenvolvimento)
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']) || 
                       strpos($host, 'localhost') !== false ||
                       strpos($host, '.local') !== false;
        
        // Se não for localhost, sempre usar HTTPS
        // Se for localhost, verificar se já está usando HTTPS, senão usar HTTP
        if ($isLocalhost) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        } else {
            $protocol = 'https';
        }
        
        return $protocol . '://' . $host . $relativePath;
    }

    /**
     * Formatar tempo relativo (há X tempo)
     */
    public static function timeAgo(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'há alguns segundos';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return 'há ' . $minutes . ' minuto' . ($minutes > 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return 'há ' . $hours . ' hora' . ($hours > 1 ? 's' : '');
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return 'há ' . $days . ' dia' . ($days > 1 ? 's' : '');
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return 'há ' . $months . ' mês' . ($months > 1 ? 'es' : '');
        } else {
            $years = floor($diff / 31536000);
            return 'há ' . $years . ' ano' . ($years > 1 ? 's' : '');
        }
    }

    /**
     * Formatar data e hora para exibição
     */
    public static function formatDateTime(?string $datetime, string $format = 'd/m/Y H:i'): string
    {
        if (empty($datetime)) {
            return '-';
        }
        
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '-';
        }
        
        return date($format, $timestamp);
    }
}

