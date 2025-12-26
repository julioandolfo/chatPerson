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
            $baseDir = __DIR__ . '/../../' . self::AVATAR_DIR;
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0777, true);
                Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Diretório criado: {$baseDir}");
            }
            
            $channelDir = $baseDir . $channel . '/';
            if (!is_dir($channelDir)) {
                mkdir($channelDir, 0777, true);
                Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Subdiretório de canal criado: {$channelDir}");
            }
            
            // Baixar imagem
            Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Iniciando download com file_get_contents...");
            $imageData = @file_get_contents($url);
            
            if ($imageData === false) {
                Logger::notificame("[WARNING] AvatarService::downloadAndSaveAvatar - Falha ao baixar com file_get_contents, tentando cURL...");
                
                // Tentar com cURL se file_get_contents falhar
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                
                // Headers adicionais para Instagram/Facebook
                $headers = [
                    'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,pt-BR;q=0.8,pt;q=0.7',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache',
                    'Referer: https://www.instagram.com/',
                    'Sec-Fetch-Dest: image',
                    'Sec-Fetch-Mode: no-cors',
                    'Sec-Fetch-Site: cross-site'
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                
                $imageData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($imageData === false || $httpCode !== 200) {
                    Logger::notificame("[ERROR] AvatarService::downloadAndSaveAvatar - Falha ao baixar com cURL (HTTP {$httpCode})");
                    return null;
                }
                
                Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Download com cURL bem-sucedido");
            } else {
                Logger::notificame("[INFO] AvatarService::downloadAndSaveAvatar - Download com file_get_contents bem-sucedido");
            }
            
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
            $baseDir = __DIR__ . '/../../' . self::AVATAR_DIR;
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0777, true);
            }
            
            $initialsDir = $baseDir . 'initials/';
            if (!is_dir($initialsDir)) {
                mkdir($initialsDir, 0777, true);
                Logger::notificame("[INFO] AvatarService::generateInitialsAvatar - Diretório de iniciais criado");
            }
            
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

