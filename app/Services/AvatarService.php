<?php
/**
 * Service AvatarService
 * Gerenciamento de avatares de contatos
 * Baixa e armazena avatares localmente para evitar expiração de URLs externas
 */

namespace App\Services;

use App\Helpers\Logger;

class AvatarService
{
    // Diretório base para armazenar avatares
    const AVATAR_DIR = 'public/storage/avatars/';
    
    /**
     * Baixar e salvar avatar de URL externa
     * Retorna o caminho local do avatar ou null se falhar
     */
    public static function downloadAndSaveAvatar(string $url, string $identifier, string $channel = 'default'): ?string
    {
        try {
            Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Iniciando download");
            Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - URL: " . substr($url, 0, 100) . "...");
            Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Identifier: {$identifier}, Channel: {$channel}");
            
            // Criar diretório se não existir
            self::ensureStorageDirs();
            
            $baseDir = __DIR__ . '/../../' . self::AVATAR_DIR;
            $channelDir = $baseDir . $channel . '/';
            if (!is_dir($channelDir)) {
                mkdir($channelDir, 0777, true);
                Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Subdiretório de canal criado: {$channelDir}");
            }
            
            // Baixar imagem (tentar múltiplos métodos)
            $imageData = self::downloadImageWithRetry($url, $channel);
            
            if ($imageData === false || empty($imageData)) {
                Logger::notificame("[ERROR] AvatarService::downloadAndSaveAvatar - Todas as tentativas de download falharam");
                return null;
            }
            
            Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Download bem-sucedido, tamanho: " . strlen($imageData) . " bytes");
            
            // Detectar tipo de imagem
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);
            Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - MIME type detectado: {$mimeType}");
            
            $extension = 'jpg'; // padrão
            switch ($mimeType) {
                case 'image/jpeg':
                    $extension = 'jpg';
                    break;
                case 'image/png':
                    $extension = 'png';
                    break;
                case 'image/gif':
                    $extension = 'gif';
                    break;
                case 'image/webp':
                    $extension = 'webp';
                    break;
            }
            
            // Gerar nome único baseado no identifier
            $filename = md5($identifier) . '_' . time() . '.' . $extension;
            $filePath = $channelDir . $filename;
            
            // Salvar arquivo
            Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Salvando arquivo: {$filePath}");
            $result = file_put_contents($filePath, $imageData);
            
            if ($result === false) {
                Logger::notificame("[ERROR] AvatarService::downloadAndSaveAvatar - Falha ao salvar arquivo");
                return null;
            }
            
            // Retornar caminho relativo (para salvar no banco)
            $relativePath = '/storage/avatars/' . $channel . '/' . $filename;
            Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Avatar salvo com sucesso: {$relativePath}");
            
            return $relativePath;
            
        } catch (\Exception $e) {
            Logger::notificame("[ERROR] AvatarService::downloadAndSaveAvatar - Erro: " . $e->getMessage());
            Logger::notificame("[ERROR] AvatarService::downloadAndSaveAvatar - Trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Deletar avatar antigo de um contato
     */
    public static function deleteAvatar(string $avatarPath): bool
    {
        try {
            // Se não for um caminho local, ignorar
            if (!str_starts_with($avatarPath, '/storage/avatars/')) {
                return true;
            }
            
            $fullPath = __DIR__ . '/../../public' . $avatarPath;
            
            if (file_exists($fullPath)) {
                unlink($fullPath);
                Logger::notificame("[INFO] AvatarService::deleteAvatar - Avatar deletado: {$avatarPath}");
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Logger::notificame("[ERROR] AvatarService::deleteAvatar - Erro: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se avatar é de URL externa (precisa ser baixado)
     */
    public static function isExternalUrl(string $avatar): bool
    {
        return str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://');
    }
    
    /**
     * Garantir que diretórios de armazenamento existem
     */
    private static function ensureStorageDirs(): void
    {
        $baseDir = __DIR__ . '/../../' . self::AVATAR_DIR;
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
            Logger::notificame("[INFO] AvatarService::ensureStorageDirs - Diretório base criado: {$baseDir}");
        }
        
        $initialsDir = $baseDir . 'initials/';
        if (!is_dir($initialsDir)) {
            mkdir($initialsDir, 0777, true);
            Logger::notificame("[INFO] AvatarService::ensureStorageDirs - Diretório de iniciais criado");
        }
    }
    
    /**
     * Baixar imagem com múltiplas tentativas e métodos
     * Retorna os bytes da imagem ou false se falhar
     */
    private static function downloadImageWithRetry(string $url, string $channel): string|false
    {
        Logger::notificame("[DEBUG] === DOWNLOAD IMAGE - INICIANDO ===");
        Logger::notificame("[DEBUG] URL para download: {$url}");
        Logger::notificame("[DEBUG] Canal: {$channel}");
        
        // Método 1: file_get_contents (simples e rápido)
        Logger::notificame("[DEBUG] === Tentativa 1/4: file_get_contents ===");
        $imageData = @file_get_contents($url);
        Logger::notificame("[DEBUG]   Resultado: " . ($imageData !== false ? strlen($imageData) . ' bytes baixados' : 'FALHOU'));
        if ($imageData !== false && !empty($imageData)) {
            Logger::notificame("[INFO] AvatarService::downloadImageWithRetry - ✅ Sucesso com file_get_contents");
            return $imageData;
        }
        
        // Método 2: cURL com User-Agent básico
        Logger::notificame("[DEBUG] === Tentativa 2/4: cURL básico ===");
        $imageData = self::downloadWithCurl($url, [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]);
        Logger::notificame("[DEBUG]   Resultado: " . ($imageData !== false ? strlen($imageData) . ' bytes baixados' : 'FALHOU'));
        if ($imageData !== false && !empty($imageData)) {
            Logger::notificame("[INFO] AvatarService::downloadImageWithRetry - ✅ Sucesso com cURL básico");
            return $imageData;
        }
        
        // Método 3: cURL com headers específicos do Instagram
        if ($channel === 'instagram' || str_contains($url, 'instagram.com') || str_contains($url, 'cdninstagram.com') || str_contains($url, 'fbcdn.net')) {
            Logger::notificame("[DEBUG] === Tentativa 3/4: cURL com headers Instagram ===");
            $imageData = self::downloadWithCurlInstagram($url);
            Logger::notificame("[DEBUG]   Resultado: " . ($imageData !== false ? strlen($imageData) . ' bytes baixados' : 'FALHOU'));
            if ($imageData !== false && !empty($imageData)) {
                Logger::notificame("[INFO] AvatarService::downloadImageWithRetry - ✅ Sucesso com cURL Instagram");
                return $imageData;
            }
        } else {
            Logger::notificame("[DEBUG] === Tentativa 3/4: PULADA (não é Instagram/fbcdn) ===");
        }
        
        // Método 4: cURL com User-Agent mobile (última tentativa)
        Logger::notificame("[DEBUG] === Tentativa 4/4: cURL mobile ===");
        $imageData = self::downloadWithCurl($url, [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1'
        ]);
        Logger::notificame("[DEBUG]   Resultado: " . ($imageData !== false ? strlen($imageData) . ' bytes baixados' : 'FALHOU'));
        if ($imageData !== false && !empty($imageData)) {
            Logger::notificame("[INFO] AvatarService::downloadImageWithRetry - ✅ Sucesso com cURL mobile");
            return $imageData;
        }
        
        Logger::notificame("[ERROR] AvatarService::downloadImageWithRetry - ❌ TODAS AS 4 TENTATIVAS FALHARAM!");
        return false;
    }
    
    /**
     * Download com cURL genérico
     */
    private static function downloadWithCurl(string $url, array $userAgents): string|false
    {
        foreach ($userAgents as $ua) {
            Logger::notificame("[DEBUG]     cURL: User-Agent: " . substr($ua, 0, 50) . "...");
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, $ua);
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            $bytes = is_string($imageData) ? strlen($imageData) : 0;
            Logger::notificame("[DEBUG]     cURL: HTTP {$httpCode}, Content-Type: {$contentType}, bytes: {$bytes}");
            if ($error) {
                Logger::notificame("[DEBUG]     cURL: Error: {$error}");
            }
            
            if ($imageData !== false && $httpCode === 200 && !empty($imageData)) {
                Logger::notificame("[DEBUG]     cURL: ✅ SUCESSO!");
                return $imageData;
            } else {
                Logger::notificame("[DEBUG]     cURL: ❌ Falhou (HTTP {$httpCode})");
            }
        }
        
        return false;
    }
    
    /**
     * Download com cURL otimizado para Instagram
     */
    private static function downloadWithCurlInstagram(string $url): string|false
    {
        Logger::notificame("[DEBUG]     cURL Instagram: Usando headers específicos do Instagram/Facebook");
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // Headers específicos do Instagram
        $headers = [
            'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,pt-BR;q=0.8,pt;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Referer: https://www.instagram.com/',
            'Origin: https://www.instagram.com',
            'Sec-Fetch-Dest: image',
            'Sec-Fetch-Mode: no-cors',
            'Sec-Fetch-Site: cross-site',
            'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        $bytes = is_string($imageData) ? strlen($imageData) : 0;
        Logger::notificame("[DEBUG]     cURL Instagram: HTTP {$httpCode}, Content-Type: {$contentType}, bytes: {$bytes}");
        if ($finalUrl !== $url) {
            Logger::notificame("[DEBUG]     cURL Instagram: Redirecionado para: {$finalUrl}");
        }
        if ($error) {
            Logger::notificame("[DEBUG]     cURL Instagram: Error: {$error}");
        }
        
        if ($imageData !== false && $httpCode === 200 && !empty($imageData)) {
            Logger::notificame("[DEBUG]     cURL Instagram: ✅ SUCESSO!");
            return $imageData;
        } else {
            Logger::notificame("[DEBUG]     cURL Instagram: ❌ Falhou (HTTP {$httpCode})");
        }
        
        return false;
    }
    
    /**
     * Decodificar e limpar URL da imagem
     */
    private static function cleanImageUrl(string $url): string
    {
        // Decodificar HTML entities (&amp; → &, &quot; → ", etc)
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remover espaços em branco
        $url = trim($url);
        
        return $url;
    }
    
    /**
     * Buscar URL da foto de perfil do Instagram usando scraping leve
     * Retorna URL da imagem ou null se falhar
     */
    public static function fetchInstagramProfileImageUrl(string $username): ?string
    {
        try {
            Logger::notificame("[INFO] AvatarService::fetchInstagramProfileImageUrl - Buscando imagem de perfil para @{$username}");
            
            $profileUrl = "https://www.instagram.com/{$username}/";
            
            $ch = curl_init($profileUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,pt-BR;q=0.8,pt;q=0.7',
                    'Accept-Encoding: gzip, deflate, br',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Sec-Fetch-Site: none',
                    'Cache-Control: max-age=0'
                ],
                CURLOPT_ENCODING => 'gzip, deflate'
            ]);
            
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($html === false || $httpCode >= 400) {
                Logger::notificame("[ERROR] AvatarService::fetchInstagramProfileImageUrl - Falha ao buscar perfil: HTTP {$httpCode}, error: {$error}");
                return null;
            }
            
            Logger::notificame("[DEBUG] AvatarService::fetchInstagramProfileImageUrl - HTML obtido, tamanho: " . strlen($html) . " bytes");
            Logger::notificame("[DEBUG] AvatarService::fetchInstagramProfileImageUrl - Primeiras 500 chars do HTML: " . substr($html, 0, 500));
            
            // Contar quantas tags <img> existem no HTML
            $imgCount = preg_match_all('/<img[^>]*>/i', $html, $allImgs);
            Logger::notificame("[DEBUG] AvatarService::fetchInstagramProfileImageUrl - Total de tags <img> encontradas: {$imgCount}");
            
            // 🎯 MÉTODO 1: Buscar tag <img> com alt contendo perfil do usuário (múltiplos idiomas)
            Logger::notificame("[DEBUG] === MÉTODO 1: Buscando <img> com alt= perfil ===");
            $altPatterns = [
                'Foto do perfil de',      // Português
                'Photo de profil de',     // Francês
                'Foto de perfil de',      // Espanhol
                'Profile picture of',     // Inglês
                'Profilbild von',         // Alemão
                "Foto del profilo di",    // Italiano
            ];
            
            foreach ($altPatterns as $pattern) {
                Logger::notificame("[DEBUG]   Tentando padrão: '{$pattern}'");
                
                // Formato 1: alt antes de src
                if (preg_match('/<img[^>]+alt=["\']' . preg_quote($pattern, '/') . '[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
                    Logger::notificame("[DEBUG]   ✅ MATCH (alt-src)! URL RAW: {$matches[1]}");
                    $imageUrl = self::cleanImageUrl($matches[1]);
                    Logger::notificame("[DEBUG]   ✅ URL LIMPA: {$imageUrl}");
                    Logger::notificame("[INFO] AvatarService::fetchInstagramProfileImageUrl - ✅ <img> tag encontrada (alt '{$pattern}')");
                    return $imageUrl;
                }
                
                // Formato 2: src antes de alt
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]+alt=["\']' . preg_quote($pattern, '/') . '[^"\']*["\']/i', $html, $matches)) {
                    Logger::notificame("[DEBUG]   ✅ MATCH (src-alt)! URL RAW: {$matches[1]}");
                    $imageUrl = self::cleanImageUrl($matches[1]);
                    Logger::notificame("[DEBUG]   ✅ URL LIMPA: {$imageUrl}");
                    Logger::notificame("[INFO] AvatarService::fetchInstagramProfileImageUrl - ✅ <img> tag encontrada (src-alt '{$pattern}')");
                    return $imageUrl;
                }
                
                Logger::notificame("[DEBUG]   ❌ Não encontrado com padrão '{$pattern}'");
            }
            
            // 🎯 MÉTODO 2: Buscar tag <img> com classes específicas do Instagram (fbcdn.net)
            Logger::notificame("[DEBUG] === MÉTODO 2: Buscando <img> com fbcdn.net ===");
            if (preg_match('/<img[^>]+src=["\']([^"\']*fbcdn\.net[^"\']+)["\']/i', $html, $matches)) {
                Logger::notificame("[DEBUG]   ✅ MATCH fbcdn! URL RAW: {$matches[1]}");
                $imageUrl = self::cleanImageUrl($matches[1]);
                Logger::notificame("[DEBUG]   ✅ URL LIMPA: {$imageUrl}");
                Logger::notificame("[INFO] AvatarService::fetchInstagramProfileImageUrl - ✅ <img> tag fbcdn encontrada");
                return $imageUrl;
            }
            Logger::notificame("[DEBUG]   ❌ Nenhuma tag <img> com fbcdn.net encontrada");
            
            // 🎯 MÉTODO 3: Meta tag og:image (fallback)
            Logger::notificame("[DEBUG] === MÉTODO 3: Buscando meta og:image ===");
            if (preg_match('/property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches)) {
                Logger::notificame("[DEBUG]   ✅ MATCH og:image! URL RAW: {$matches[1]}");
                $imageUrl = self::cleanImageUrl($matches[1]);
                Logger::notificame("[DEBUG]   ✅ URL LIMPA: {$imageUrl}");
                Logger::notificame("[INFO] AvatarService::fetchInstagramProfileImageUrl - ✅ og:image encontrado");
                return $imageUrl;
            }
            
            // Formato alternativo: content primeiro
            if (preg_match('/content=["\']([^"\']+)["\']\s+property=["\']og:image["\']/i', $html, $matches)) {
                Logger::notificame("[DEBUG]   ✅ MATCH og:image (alt format)! URL RAW: {$matches[1]}");
                $imageUrl = self::cleanImageUrl($matches[1]);
                Logger::notificame("[DEBUG]   ✅ URL LIMPA: {$imageUrl}");
                Logger::notificame("[INFO] AvatarService::fetchInstagramProfileImageUrl - ✅ og:image encontrado (formato alt)");
                return $imageUrl;
            }
            Logger::notificame("[DEBUG]   ❌ Meta og:image não encontrada");
            
            // 🎯 MÉTODO 4: Meta tag twitter:image (fallback)
            Logger::notificame("[DEBUG] === MÉTODO 4: Buscando meta twitter:image ===");
            if (preg_match('/name=["\']twitter:image["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches)) {
                Logger::notificame("[DEBUG]   ✅ MATCH twitter:image! URL RAW: {$matches[1]}");
                $imageUrl = self::cleanImageUrl($matches[1]);
                Logger::notificame("[DEBUG]   ✅ URL LIMPA: {$imageUrl}");
                Logger::notificame("[INFO] AvatarService::fetchInstagramProfileImageUrl - ✅ twitter:image encontrado");
                return $imageUrl;
            }
            Logger::notificame("[DEBUG]   ❌ Meta twitter:image não encontrada");
            
            Logger::notificame("[ERROR] AvatarService::fetchInstagramProfileImageUrl - ❌ TODOS OS 4 MÉTODOS FALHARAM!");
            Logger::notificame("[DEBUG] === AMOSTRA DO HTML (tags meta) ===");
            if (preg_match_all('/<meta[^>]*property=["\']og:[^>]+>/i', $html, $ogTags)) {
                foreach ($ogTags[0] as $tag) {
                    Logger::notificame("[DEBUG]   " . substr($tag, 0, 150));
                }
            } else {
                Logger::notificame("[DEBUG]   Nenhuma meta tag og: encontrada");
            }
            
            return null;
            
        } catch (\Exception $e) {
            Logger::notificame("[ERROR] AvatarService::fetchInstagramProfileImageUrl - Exceção: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Baixar avatar do Instagram usando scraping do perfil público
     * Retorna caminho local do avatar ou null se falhar
     */
    public static function downloadInstagramAvatar(string $username, string $identifier): ?string
    {
        try {
            Logger::notificame("[INFO] ========== DOWNLOAD INSTAGRAM AVATAR - INÍCIO ==========");
            Logger::notificame("[INFO] AvatarService::downloadInstagramAvatar - Username: @{$username}");
            Logger::notificame("[INFO] AvatarService::downloadInstagramAvatar - Identifier: {$identifier}");
            
            // Passo 1: Buscar URL da imagem via scraping
            Logger::notificame("[INFO] === PASSO 1: Scraping do perfil público ===");
            $imageUrl = self::fetchInstagramProfileImageUrl($username);
            
            if (!$imageUrl) {
                Logger::notificame("[ERROR] AvatarService::downloadInstagramAvatar - ❌ PASSO 1 FALHOU: Não foi possível encontrar URL da imagem");
                Logger::notificame("[INFO] ========== DOWNLOAD INSTAGRAM AVATAR - FIM (Falha no scraping) ==========");
                return null;
            }
            
            Logger::notificame("[INFO] AvatarService::downloadInstagramAvatar - ✅ PASSO 1 OK: URL encontrada");
            Logger::notificame("[INFO] AvatarService::downloadInstagramAvatar - URL completa: {$imageUrl}");
            
            // Passo 2: Baixar a imagem
            Logger::notificame("[INFO] === PASSO 2: Download da imagem ===");
            
            $savedPath = self::downloadAndSaveAvatar($imageUrl, $identifier, 'instagram');
            
            if ($savedPath) {
                Logger::notificame("[INFO] AvatarService::downloadInstagramAvatar - ✅ PASSO 2 OK: Avatar salvo");
                Logger::notificame("[INFO] AvatarService::downloadInstagramAvatar - Caminho local: {$savedPath}");
                Logger::notificame("[INFO] ========== DOWNLOAD INSTAGRAM AVATAR - FIM (Sucesso) ==========");
                return $savedPath;
            }
            
            Logger::notificame("[ERROR] AvatarService::downloadInstagramAvatar - ❌ PASSO 2 FALHOU: Não foi possível baixar/salvar imagem");
            Logger::notificame("[INFO] ========== DOWNLOAD INSTAGRAM AVATAR - FIM (Falha no download) ==========");
            return null;
            
        } catch (\Exception $e) {
            Logger::notificame("[ERROR] AvatarService::downloadInstagramAvatar - ❌ EXCEÇÃO: " . $e->getMessage());
            Logger::notificame("[ERROR] AvatarService::downloadInstagramAvatar - Stack trace: " . $e->getTraceAsString());
            Logger::notificame("[INFO] ========== DOWNLOAD INSTAGRAM AVATAR - FIM (Exceção) ==========");
            return null;
        }
    }
    
    /**
     * Gerar avatar com iniciais (fallback quando download falha)
     * Retorna um caminho para imagem SVG com as iniciais
     */
    public static function generateInitialsAvatar(string $name, string $identifier): ?string
    {
        try {
            Logger::notificame("[INFO] AvatarService::generateInitialsAvatar - Gerando avatar com iniciais");
            Logger::notificame("[INFO] AvatarService::generateInitialsAvatar - Name: {$name}");
            
            // Extrair iniciais (primeiras letras de cada palavra, máximo 2)
            $words = explode(' ', trim($name));
            $initials = '';
            
            if (count($words) >= 2) {
                // Primeira letra do primeiro e último nome
                $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
            } else {
                // Primeiras 2 letras do nome único
                $initials = mb_strtoupper(mb_substr($name, 0, 2));
            }
            
            Logger::notificame("[INFO] AvatarService::generateInitialsAvatar - Iniciais: {$initials}");
            
            // Gerar cor baseada no identifier (sempre a mesma cor para o mesmo usuário)
            $colors = [
                '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8',
                '#F7DC6F', '#BB8FCE', '#85C1E2', '#F8B195', '#C06C84',
                '#6C5B7B', '#355C7D', '#F67280', '#C44569', '#2C3A47'
            ];
            $colorIndex = hexdec(substr(md5($identifier), 0, 2)) % count($colors);
            $bgColor = $colors[$colorIndex];
            
            // Criar SVG
            $svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
  <rect width="200" height="200" fill="' . $bgColor . '"/>
  <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="80" font-weight="bold" 
        fill="#FFFFFF" text-anchor="middle" dominant-baseline="central">
    ' . htmlspecialchars($initials) . '
  </text>
</svg>';
            
            // Criar diretório se não existir
            self::ensureStorageDirs();
            
            $baseDir = __DIR__ . '/../../' . self::AVATAR_DIR;
            $initialsDir = $baseDir . 'initials/';
            
            // Salvar SVG
            $filename = md5($identifier) . '_initials.svg';
            $filePath = $initialsDir . $filename;
            
            file_put_contents($filePath, $svg);
            
            $relativePath = '/storage/avatars/initials/' . $filename;
            Logger::notificame("[INFO] AvatarService::generateInitialsAvatar - Avatar de iniciais criado: {$relativePath}");
            
            return $relativePath;
            
        } catch (\Exception $e) {
            Logger::notificame("[ERROR] AvatarService::generateInitialsAvatar - Erro: " . $e->getMessage());
            return null;
        }
    }
}

