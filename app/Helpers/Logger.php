<?php
/**
 * Helper Logger
 * Sistema de logging para arquivos
 */

namespace App\Helpers;

class Logger
{
    private static string $logDir = __DIR__ . '/../../logs';
    private static string $defaultFile = 'app.log';

    /**
     * Criar diretório de logs se não existir
     */
    private static function ensureLogDir(): void
    {
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0777, true);
        }
        
        // Garantir permissões de escrita
        if (is_dir(self::$logDir) && !is_writable(self::$logDir)) {
            @chmod(self::$logDir, 0777);
        }
    }

    /**
     * Escrever log em arquivo
     */
    public static function log(string $message, string $file = null): void
    {
        self::ensureLogDir();
        
        $logFile = $file ? self::$logDir . '/' . $file : self::$logDir . '/' . self::$defaultFile;
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // Garantir que o arquivo exista
        if (!is_file($logFile)) {
            @touch($logFile);
            @chmod($logFile, 0666);
        }

        // Tentar escrever, mas não falhar se não conseguir (silenciar erro)
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log específico para automações
     */
    public static function automation(string $message): void
    {
        self::log($message, 'automacao.log');
    }

    public static function quepasa(string $message): void
    {
        self::log($message, 'quepasa.log');
    }

    /**
     * Log específico para Notificame
     */
    public static function notificame(string $message): void
    {
        self::log($message, 'notificame.log');
    }

    /**
     * Log específico para AI Tools (ferramentas de IA)
     */
    public static function aiTools(string $message): void
    {
        self::log($message, 'ai_tools.log');
    }

    /**
     * Log específico para Meta (Instagram + WhatsApp)
     */
    public static function meta(string $level, string $message, array $context = []): void
    {
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $logMessage = "[{$level}] {$message}{$contextStr}";
        self::log($logMessage, 'meta.log');
    }

    /**
     * Log específico para Campanhas
     */
    public static function campaign(string $message): void
    {
        self::log($message, 'campaigns.log');
    }

    /**
     * Log de debug
     */
    public static function debug(string $message, string $file = null): void
    {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            self::log("[DEBUG] {$message}", $file);
        }
    }

    /**
     * Log de informação
     */
    public static function info(string $message, string $file = null): void
    {
        self::log("[INFO] {$message}", $file);
    }

    /**
     * Log de erro
     */
    public static function error(string $message, string $file = null): void
    {
        self::log("[ERROR] {$message}", $file);
    }

    /**
     * Log de warning
     */
    public static function warning(string $message, string $file = null): void
    {
        self::log("[WARNING] {$message}", $file);
    }
}

