<?php
/**
 * Helper Request
 * Facilita acesso a dados da requisição
 */

namespace App\Helpers;

class Request
{
    /**
     * Obter dados JSON do body da requisição
     */
    private static function getJsonBody(): ?array
    {
        static $jsonData = null;
        
        if ($jsonData === null) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            // Verificar se é JSON
            if (strpos($contentType, 'application/json') !== false) {
                $rawInput = file_get_contents('php://input');
                if (!empty($rawInput)) {
                    $jsonData = json_decode($rawInput, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log('Request::getJsonBody - Erro ao decodificar JSON: ' . json_last_error_msg());
                        $jsonData = [];
                    }
                } else {
                    $jsonData = [];
                }
            } else {
                $jsonData = false; // Não é JSON
            }
        }
        
        return $jsonData === false ? null : $jsonData;
    }

    /**
     * Obter valor de POST
     */
    public static function post(?string $key = null, $default = null)
    {
        // Tentar obter dados JSON primeiro
        $jsonData = self::getJsonBody();
        
        if ($jsonData !== null) {
            // É requisição JSON
            if ($key === null) {
                return $jsonData;
            }
            return $jsonData[$key] ?? $default;
        }
        
        // Requisição POST normal (form-data ou x-www-form-urlencoded)
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Obter valor de GET
     */
    public static function get(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Obter valor de qualquer método (POST, GET, etc)
     */
    public static function input(?string $key = null, $default = null)
    {
        $data = array_merge($_GET, $_POST);
        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    /**
     * Verificar se é requisição POST
     */
    public static function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Verificar se é requisição GET
     */
    public static function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * Verificar se é requisição AJAX
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Obter dados JSON da requisição
     * Retorna array vazio se não for JSON
     */
    public static function json(): array
    {
        $jsonData = self::getJsonBody();
        return $jsonData ?? [];
    }
}

