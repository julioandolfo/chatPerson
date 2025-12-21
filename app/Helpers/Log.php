<?php
/**
 * Helper de Log
 * Facilita o registro de logs em arquivos específicos
 */

namespace App\Helpers;

class Log
{
    /**
     * Diretório de logs
     */
    private static string $logDir = __DIR__ . '/../../logs/';
    
    /**
     * Escrever log em arquivo específico
     */
    public static function write(string $message, string $file = 'app.log', string $level = 'INFO'): void
    {
        // Garantir que o diretório existe
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0777, true);
        }
        
        // Garantir permissões de escrita
        if (is_dir(self::$logDir) && !is_writable(self::$logDir)) {
            @chmod(self::$logDir, 0777);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        $filePath = self::$logDir . $file;
        
        // Tentar escrever, mas não falhar se não conseguir (silenciar erro)
        @file_put_contents($filePath, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log de informação
     */
    public static function info(string $message, string $file = 'app.log'): void
    {
        self::write($message, $file, 'INFO');
    }
    
    /**
     * Log de erro
     */
    public static function error(string $message, string $file = 'app.log'): void
    {
        self::write($message, $file, 'ERROR');
    }
    
    /**
     * Log de debug
     */
    public static function debug(string $message, string $file = 'app.log'): void
    {
        self::write($message, $file, 'DEBUG');
    }
    
    /**
     * Log de warning
     */
    public static function warning(string $message, string $file = 'app.log'): void
    {
        self::write($message, $file, 'WARNING');
    }
    
    /**
     * Log com contexto (array de dados)
     */
    public static function context(string $message, array $context = [], string $file = 'app.log', string $level = 'INFO'): void
    {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        self::write($message . $contextStr, $file, $level);
    }
}

