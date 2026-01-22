<?php
/**
 * Helper de Resposta HTTP
 */

namespace App\Helpers;

use App\Helpers\Request;

class Response
{
    /**
     * Retornar JSON
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        // Desabilitar display de erros para evitar HTML no JSON
        $oldDisplayErrors = ini_get('display_errors');
        $oldErrorReporting = error_reporting();
        
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
        
        // Limpar qualquer output buffer antes de enviar JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Limpar qualquer output que possa ter sido enviado
        if (headers_sent()) {
            error_log("⚠️ Headers já foram enviados antes de Response::json()");
        }
        
        // Garantir que não há nenhum output anterior
        if (ob_get_level() === 0) {
            ob_start();
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        
        // Limpar buffer novamente antes de enviar
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Restaurar configurações anteriores
        ini_set('display_errors', $oldDisplayErrors);
        error_reporting($oldErrorReporting);
        
        exit;
    }

    /**
     * Retornar sucesso
     */
    public static function success(array $data = [], string $message = 'Sucesso', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Retornar erro
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * Retornar resposta para DELETE
     */
    public static function delete(array $data = [], string $message = 'Deletado com sucesso', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Retornar view
     */
    public static function view(string $view, array $data = []): void
    {
        $debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
        
        try {
            // Extrair variáveis para a view
            extract($data);
            
            // Construir caminho da view
            $originalPath = __DIR__ . '/../../views/' . str_replace('.', '/', $view) . '.php';
            $baseDir = dirname($originalPath);
            
            // Criar diretório se não existir
            if (!is_dir($baseDir)) {
                if ($debug) {
                    error_log("Response::view - Criando diretório: {$baseDir}");
                }
                if (!mkdir($baseDir, 0755, true)) {
                    throw new \RuntimeException("Não foi possível criar o diretório: {$baseDir}");
                }
            }
            
            // Normalizar caminho (resolver .. e .)
            $viewPath = realpath($originalPath);
            
            // Se realpath falhar, usar o caminho original
            if (!$viewPath) {
                $viewPath = $originalPath;
            }
            
            if ($debug) {
                error_log("Response::view - View: {$view}, Original Path: {$originalPath}, Resolved Path: {$viewPath}, Exists: " . (file_exists($viewPath) ? 'yes' : 'no'));
            }
            
            if (!file_exists($viewPath)) {
                $baseDirExists = is_dir($baseDir);
                $baseDirReal = realpath($baseDir);
                
                $errorMsg = "View não encontrada: {$view}\n";
                $errorMsg .= "Caminho esperado: {$viewPath}\n";
                $errorMsg .= "Diretório base: {$baseDir} (existe: " . ($baseDirExists ? 'sim' : 'não') . ")\n";
                if ($baseDirReal) {
                    $errorMsg .= "Diretório base resolvido: {$baseDirReal}\n";
                }
                
                // Listar arquivos no diretório se existir
                if ($baseDirExists && is_readable($baseDir)) {
                    $files = scandir($baseDir);
                    $filesList = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
                    $errorMsg .= "Arquivos no diretório: " . (empty($filesList) ? 'nenhum' : implode(', ', $filesList)) . "\n";
                }
                
                $errorMsg .= "\n⚠️ SOLUÇÃO: Certifique-se de que o arquivo foi sincronizado para o Docker.\n";
                $errorMsg .= "   Se estiver usando volumes, reinicie o container Docker.\n";
                $errorMsg .= "   Ou copie manualmente: docker cp views/logs/index.php container:/var/www/html/views/logs/index.php";
                
                throw new \RuntimeException($errorMsg);
            }

            // Verificar se há output buffer ativo (pode estar bloqueando)
            $obLevel = ob_get_level();
            if ($obLevel > 0 && $debug) {
                error_log("Response::view - Output buffer ativo (nível: {$obLevel})");
            }

            // Carregar a view
            // Não usar require_once para permitir múltiplas chamadas se necessário
            require $viewPath;
            
            // Garantir que o output seja enviado
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
            
            if ($debug) {
                error_log("Response::view - View carregada e output enviado");
            }
        } catch (\Throwable $e) {
            // Limpar qualquer output buffer
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Em desenvolvimento, mostrar erro
            if ($debug) {
                echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Erro</title></head><body>";
                echo "<h1>Erro ao carregar view</h1>";
                echo "<p><strong>View:</strong> {$view}</p>";
                echo "<p><strong>Caminho:</strong> " . ($viewPath ?? 'não definido') . "</p>";
                echo "<p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
                echo "</body></html>";
            } else {
                echo "<h1>Erro interno</h1>";
            }
            exit;
        }
    }

    /**
     * Redirecionar
     */
    public static function redirect(string $url): void
    {
        // Log para debug
        self::logRedirect("redirect chamado para: {$url}");
        
        // Se URL não começar com /, assumir que é relativa
        if (strpos($url, '/') !== 0 && strpos($url, 'http') !== 0) {
            $url = '/' . $url;
        }
        
        // Adicionar base path se necessário
        $basePath = \App\Helpers\Url::basePath();
        if (!empty($basePath) && strpos($url, $basePath) !== 0) {
            $url = $basePath . $url;
        }
        
        self::logRedirect("redirect final URL: {$url}");
        
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Log para arquivo logs/auth.log (compartilhado com AuthController)
     */
    private static function logRedirect(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/auth.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [Response] {$message}\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Retornar 404
     */
    public static function notFound(string $message = 'Página não encontrada'): void
    {
        http_response_code(404);
        
        // Se for requisição AJAX ou JSON, retornar JSON
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptJson = !empty($_SERVER['HTTP_ACCEPT']) && 
                      strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        
        if ($isAjax || $acceptJson) {
            self::json([
                'success' => false,
                'message' => $message
            ], 404);
            return;
        }
        
        self::view('errors/404', ['message' => $message]);
        exit;
    }

    /**
     * Retornar 403
     */
    public static function forbidden(string $message = 'Acesso negado'): void
    {
        // Limpar qualquer output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        http_response_code(403);
        
        // Se for requisição AJAX ou JSON, retornar JSON
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptJson = !empty($_SERVER['HTTP_ACCEPT']) && 
                      strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        
        if ($isAjax || $acceptJson) {
            self::json([
                'success' => false,
                'message' => $message
            ], 403);
            return;
        }
        
        self::view('errors/403', ['message' => $message]);
        exit;
    }

    /**
     * Retornar sucesso: JSON se AJAX, redirect se não
     * 
     * @param string $message Mensagem de sucesso
     * @param string|null $redirectUrl URL para redirecionar (se não for AJAX). Se null, usa referer ou '/'
     * @param array $data Dados adicionais para retornar no JSON
     * @param int $statusCode Código HTTP
     */
    public static function successOrRedirect(
        string $message = 'Operação realizada com sucesso!',
        ?string $redirectUrl = null,
        array $data = [],
        int $statusCode = 200
    ): void {
        // Verificar se é requisição AJAX
        $isAjax = Request::isAjax();
        $acceptJson = !empty($_SERVER['HTTP_ACCEPT']) && 
                      strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        
        if ($isAjax || $acceptJson) {
            // Retornar JSON
            self::json(array_merge([
                'success' => true,
                'message' => $message
            ], $data), $statusCode);
            return;
        }
        
        // Não é AJAX, redirecionar
        if ($redirectUrl === null) {
            // Tentar usar referer
            $redirectUrl = $_SERVER['HTTP_REFERER'] ?? '/';
            // Se referer for da mesma origem, usar. Senão, usar '/'
            $parsedReferer = parse_url($redirectUrl);
            $parsedCurrent = parse_url($_SERVER['HTTP_HOST'] ?? '');
            if ($parsedReferer && isset($parsedReferer['host']) && 
                isset($parsedCurrent['host']) && 
                $parsedReferer['host'] !== $parsedCurrent['host']) {
                $redirectUrl = '/';
            }
        }
        
        // Adicionar mensagem de sucesso na query string
        $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
        $redirectUrl .= $separator . 'success=' . urlencode($message);
        
        self::redirect($redirectUrl);
    }
}

