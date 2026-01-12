<?php
/**
 * Helper Cache
 * Sistema de cache simples baseado em arquivos
 * 
 * Uso:
 * $data = Cache::remember('chave', 300, function() {
 *     return Database::fetch("SELECT ...");
 * });
 */

namespace App\Helpers;

class Cache
{
    private static string $cacheDir = __DIR__ . '/../../storage/cache/queries/';
    
    /**
     * Recuperar do cache ou executar callback
     * 
     * @param string $key Chave do cache
     * @param int $seconds Tempo de expiração em segundos
     * @param callable $callback Função a executar se cache não existir
     * @return mixed
     */
    public static function remember(string $key, int $seconds, callable $callback): mixed
    {
        $file = self::$cacheDir . md5($key) . '.cache';
        
        // Criar diretório se não existir
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }
        
        // Verificar se cache existe e ainda é válido
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $data = @unserialize($content);
                if ($data !== false && isset($data['expires']) && time() < $data['expires']) {
                    // Cache válido
                    return $data['value'];
                }
            }
            // Cache inválido ou expirado, deletar
            @unlink($file);
        }
        
        // Executar callback e cachear resultado
        $value = $callback();
        
        $data = [
            'key' => $key,
            'value' => $value,
            'created' => time(),
            'expires' => time() + $seconds
        ];
        
        @file_put_contents($file, serialize($data), LOCK_EX);
        
        return $value;
    }
    
    /**
     * Esquecer/deletar cache específico
     * 
     * @param string $key Chave do cache
     * @return void
     */
    public static function forget(string $key): void
    {
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            @unlink($file);
        }
    }
    
    /**
     * Limpar todo o cache
     * 
     * @return void
     */
    public static function clear(): void
    {
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '*.cache');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Limpar cache por padrão (ex: "contact_*")
     * 
     * @param string $pattern Padrão de chave
     * @return int Número de caches deletados
     */
    public static function clearPattern(string $pattern): int
    {
        $deleted = 0;
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '*.cache');
            foreach ($files as $file) {
                $content = @file_get_contents($file);
                if ($content !== false) {
                    $data = @unserialize($content);
                    if ($data !== false && isset($data['key'])) {
                        // Converter padrão para regex
                        $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
                        if (preg_match($regex, $data['key'])) {
                            @unlink($file);
                            $deleted++;
                        }
                    }
                }
            }
        }
        return $deleted;
    }
    
    /**
     * Verificar se cache existe e é válido
     * 
     * @param string $key Chave do cache
     * @return bool
     */
    public static function has(string $key): bool
    {
        $file = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return false;
        }
        
        $data = @unserialize($content);
        if ($data === false || !isset($data['expires'])) {
            return false;
        }
        
        return time() < $data['expires'];
    }
    
    /**
     * Obter informações do cache
     * 
     * @return array
     */
    public static function info(): array
    {
        $info = [
            'total_files' => 0,
            'total_size' => 0,
            'valid_caches' => 0,
            'expired_caches' => 0
        ];
        
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '*.cache');
            $info['total_files'] = count($files);
            
            foreach ($files as $file) {
                $info['total_size'] += filesize($file);
                
                $content = @file_get_contents($file);
                if ($content !== false) {
                    $data = @unserialize($content);
                    if ($data !== false && isset($data['expires'])) {
                        if (time() < $data['expires']) {
                            $info['valid_caches']++;
                        } else {
                            $info['expired_caches']++;
                        }
                    }
                }
            }
        }
        
        return $info;
    }
}
