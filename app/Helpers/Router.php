<?php
/**
 * Sistema de Rotas Simples
 */

namespace App\Helpers;

class Router
{
    private static array $routes = [];
    private static array $middlewares = [];
    
    /**
     * Log para external_sources.log (debug)
     */
    private static function logExternalSources(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/external_sources.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [Router] {$message}\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Registrar rota GET
     */
    public static function get(string $path, $handler, array $middlewares = []): void
    {
        self::addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Registrar rota POST
     */
    public static function post(string $path, $handler, array $middlewares = []): void
    {
        self::addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Registrar rota PUT
     */
    public static function put(string $path, $handler, array $middlewares = []): void
    {
        self::addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * Registrar rota DELETE
     */
    public static function delete(string $path, $handler, array $middlewares = []): void
    {
        self::addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Adicionar rota
     */
    private static function addRoute(string $method, string $path, $handler, array $middlewares): void
    {
        self::$routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    /**
     * Executar rotas
     */
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Obter base path usando helper Url
        $basePath = Url::basePath();
        
        // Normalizar caminhos
        $uri = str_replace('\\', '/', $uri);
        
        // Se estiver em subdiretório, remover do URI primeiro
        if (!empty($basePath) && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // Remover /public do URI se existir (pasta public não faz parte da rota)
        // Isso deve vir depois de remover o base path
        $uri = str_replace('/public', '', $uri);
        
        // Remover index.php se estiver no URI
        $uri = str_replace('/index.php', '', $uri);
        $uri = rtrim($uri, '/') ?: '/';
        
        // Debug (descomente para debug)
        // error_log("Method: {$method}, URI Original: " . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . ", URI Processado: {$uri}, BasePath: {$basePath}");

        // Debug temporário (DESABILITADO para APIs JSON)
        $debug = false; // ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
        
        // Debug específico para external-sources
        if (strpos($uri, 'external-sources') !== false) {
            self::logExternalSources("=== ROUTER DEBUG ===");
            self::logExternalSources("Method: {$method}");
            self::logExternalSources("URI Original: " . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
            self::logExternalSources("URI Processado: {$uri}");
            self::logExternalSources("BasePath: {$basePath}");
            self::logExternalSources("Total Routes: " . count(self::$routes));
        }
        
        if ($debug) {
            error_log("Router: Method={$method}, URI={$uri}, BasePath={$basePath}, Routes=" . count(self::$routes));
        }

        foreach (self::$routes as $routeIndex => $route) {
            if ($route['method'] !== $method) {
                if ($debug) {
                    error_log("Router: Route #{$routeIndex} - Method mismatch ({$route['method']} != {$method})");
                }
                continue;
            }

            $pattern = self::convertToRegex($route['path']);
            
            if ($debug) {
                error_log("Router: Route #{$routeIndex} - Testing '{$route['path']}' (pattern: {$pattern}) against '{$uri}'");
            }
            
            if (preg_match($pattern, $uri, $matches)) {
                if ($debug) {
                    error_log("Router: Route #{$routeIndex} MATCHED! Executing handler...");
                }
                
                // Log para external-sources
                if (strpos($uri, 'external-sources') !== false) {
                    self::logExternalSources("ROTA ENCONTRADA: {$route['path']}");
                    self::logExternalSources("Middlewares: " . implode(', ', $route['middlewares']));
                }
                
                // Executar middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $middlewareClass = "App\\Middleware\\{$middleware}";
                    
                    if (strpos($uri, 'external-sources') !== false) {
                        self::logExternalSources("Executando middleware: {$middlewareClass}");
                    }
                    
                    if (class_exists($middlewareClass)) {
                        $middlewareInstance = new $middlewareClass();
                        if (method_exists($middlewareInstance, 'handle')) {
                            $middlewareInstance->handle();
                        }
                    }
                }
                
                if (strpos($uri, 'external-sources') !== false) {
                    self::logExternalSources("Middlewares executados, chamando controller...");
                }

                // Liberar lock da sessão após middlewares (autenticação já leu os dados)
                // Isso permite que outras requisições do mesmo usuário não fiquem bloqueadas
                // enquanto operações longas (upload de áudio, envio WhatsApp) estão em andamento
                \App\Helpers\Auth::cacheSessionAndRelease();

                // Extrair parâmetros
                array_shift($matches);
                $params = array_values($matches);

                // Executar handler
                // IMPORTANTE: Verificar array primeiro, depois callable, depois string
                if (is_array($route['handler'])) {
                    if ($debug) {
                        error_log("Router: Executando array handler (Controller)");
                    }
                    self::callController($route['handler'], $params);
                } elseif (is_callable($route['handler'])) {
                    if ($debug) {
                        error_log("Router: Executando callable handler");
                    }
                    call_user_func_array($route['handler'], $params);
                } elseif (is_string($route['handler'])) {
                    if ($debug) {
                        error_log("Router: Executando string handler (Controller@method)");
                    }
                    self::callController($route['handler'], $params);
                }

                if ($debug) {
                    error_log("Router: Handler executado, retornando");
                }
                return;
            }
        }

        // Rota não encontrada
        if (strpos($uri, 'external-sources') !== false) {
            self::logExternalSources("!!! ROTA NAO ENCONTRADA !!! URI: {$uri}");
        }
        Response::notFound();
    }

    /**
     * Converter padrão de rota para regex
     */
    private static function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Chamar controller
     */
    private static function callController($handler, array $params): void
    {
        $debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
        
        // Suportar formato "Controller@method" ou array [Controller::class, 'method']
        if (is_array($handler)) {
            $controllerClass = $handler[0];
            $method = $handler[1];
        } else {
            list($controller, $method) = explode('@', $handler);
            $controllerClass = "App\\Controllers\\{$controller}";
        }

        if ($debug) {
            error_log("Router::callController - Class: {$controllerClass}, Method: {$method}, Params: " . json_encode($params));
        }

        if (!class_exists($controllerClass)) {
            if ($debug) {
                error_log("Router::callController - Classe não existe: {$controllerClass}");
            }
            Response::notFound("Controller não encontrado: {$controllerClass}");
            return;
        }

        try {
            $controllerInstance = new $controllerClass();
            
            if (!method_exists($controllerInstance, $method)) {
                if ($debug) {
                    error_log("Router::callController - Método não existe: {$method}");
                }
                Response::notFound("Método não encontrado: {$method}");
                return;
            }

            if ($debug) {
                error_log("Router::callController - Chamando método {$method}...");
            }
            
            call_user_func_array([$controllerInstance, $method], $params);
            
            if ($debug) {
                error_log("Router::callController - Método {$method} executado");
            }
        } catch (\Throwable $e) {
            if ($debug) {
                error_log("Router::callController - Erro: " . $e->getMessage());
                error_log("Router::callController - Trace: " . $e->getTraceAsString());
            }
            throw $e;
        }
    }
}

