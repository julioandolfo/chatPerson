<?php
/**
 * Helper de Banco de Dados
 * Singleton para conexão PDO
 */

namespace App\Helpers;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Obter instância única da conexão
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$config = require __DIR__ . '/../../config/database.php';
            self::connect();
        }

        return self::$instance;
    }

    /**
     * Conectar ao banco de dados
     */
    private static function connect(): void
    {
        try {
            // Log config para debug (remover em produção)
            $debug = ($_ENV['APP_DEBUG'] ?? 'true') === 'true';
            if ($debug) {
                error_log('Database config: ' . json_encode([
                    'host' => self::$config['host'] ?? 'não definido',
                    'port' => self::$config['port'] ?? 'não definido',
                    'database' => self::$config['database'] ?? 'não definido',
                    'username' => self::$config['username'] ?? 'não definido',
                    'password' => '***',
                ]));
            }
            
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                self::$config['host'],
                self::$config['port'],
                self::$config['database'],
                self::$config['charset']
            );

            self::$instance = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                self::$config['options']
            );
            
            // Configurar timezone do MySQL para America/Sao_Paulo (GMT-3)
            self::$instance->exec("SET time_zone = '-03:00'");
            
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            error_log('DSN attempted: mysql:host=' . (self::$config['host'] ?? '?') . ';port=' . (self::$config['port'] ?? '?') . ';dbname=' . (self::$config['database'] ?? '?'));
            throw new \RuntimeException('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        }
    }

    /**
     * Executar query
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $db = self::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Obter uma linha
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obter múltiplas linhas
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    }

    /**
     * Executar insert e retornar último ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        \App\Helpers\Logger::info("Database::insert - Preparando SQL", 'kanban_agents.log');
        \App\Helpers\Logger::info("Database::insert - SQL: {$sql}", 'kanban_agents.log');
        \App\Helpers\Logger::info("Database::insert - Params: " . json_encode($params), 'kanban_agents.log');
        
        try {
            $db = self::getInstance();
            \App\Helpers\Logger::info("Database::insert - Conexão obtida com sucesso", 'kanban_agents.log');
            
            $stmt = $db->prepare($sql);
            \App\Helpers\Logger::info("Database::insert - Statement preparado com sucesso", 'kanban_agents.log');
            
            $result = $stmt->execute($params);
            \App\Helpers\Logger::info("Database::insert - Execute retornou: " . ($result ? 'true' : 'false'), 'kanban_agents.log');
            
            $lastId = (int) $db->lastInsertId();
            \App\Helpers\Logger::info("Database::insert - LastInsertId: {$lastId}", 'kanban_agents.log');
            
            return $lastId;
        } catch (\Throwable $e) {
            \App\Helpers\Logger::error("Database::insert - ERRO ao executar INSERT", 'kanban_agents.log');
            \App\Helpers\Logger::error("Database::insert - Tipo: " . get_class($e), 'kanban_agents.log');
            \App\Helpers\Logger::error("Database::insert - Mensagem: " . $e->getMessage(), 'kanban_agents.log');
            
            // Capturar errorInfo se $db estiver definido
            if (isset($db)) {
                try {
                    \App\Helpers\Logger::error("Database::insert - PDO ErrorInfo: " . json_encode($db->errorInfo()), 'kanban_agents.log');
                } catch (\Throwable $errorInfoException) {
                    \App\Helpers\Logger::error("Database::insert - Não foi possível obter errorInfo", 'kanban_agents.log');
                }
            }
            
            \App\Helpers\Logger::error("Database::insert - Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")", 'kanban_agents.log');
            \App\Helpers\Logger::error("Database::insert - Stack trace: " . $e->getTraceAsString(), 'kanban_agents.log');
            throw $e;
        }
    }

    /**
     * Executar update/delete e retornar linhas afetadas
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Iniciar transação
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit transação
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback transação
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }
}

