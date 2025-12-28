<?php

namespace App\Helpers;

use PDO;
use PDOException;
use App\Services\PostgreSQLSettingsService;

class PostgreSQL
{
    private static ?PDO $connection = null;

    /**
     * Obter conexão PostgreSQL
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        // Verificar se PostgreSQL está habilitado
        if (!PostgreSQLSettingsService::isEnabled()) {
            throw new \Exception('PostgreSQL não está habilitado nas configurações do sistema');
        }

        // Obter credenciais das configurações
        $credentials = PostgreSQLSettingsService::getCredentials();
        
        if (empty($credentials['password'])) {
            throw new \Exception('POSTGRES_PASSWORD não configurado nas configurações do sistema');
        }

        try {
            self::$connection = new PDO(
                $credentials['dsn'], 
                $credentials['username'], 
                $credentials['password'], 
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Verificar se pgvector está instalado
            self::checkPgvectorExtension();

            return self::$connection;
        } catch (PDOException $e) {
            throw new \Exception("Erro ao conectar PostgreSQL: " . $e->getMessage());
        }
    }

    /**
     * Verificar se extensão pgvector está instalada
     */
    private static function checkPgvectorExtension(): void
    {
        try {
            $stmt = self::$connection->query("SELECT * FROM pg_extension WHERE extname = 'vector'");
            $result = $stmt->fetch();

            if (empty($result)) {
                throw new \Exception('Extensão pgvector não está instalada no PostgreSQL. Execute: CREATE EXTENSION vector;');
            }
        } catch (PDOException $e) {
            throw new \Exception('Erro ao verificar extensão pgvector: ' . $e->getMessage());
        }
    }

    /**
     * Executar query
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Executar query e retornar primeira linha
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Executar comando (INSERT, UPDATE, DELETE)
     */
    public static function execute(string $sql, array $params = []): bool
    {
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Inserir e retornar ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql . ' RETURNING id');
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['id'] ?? 0;
    }

    /**
     * Verificar se PostgreSQL está disponível
     */
    public static function isAvailable(): bool
    {
        try {
            if (!PostgreSQLSettingsService::isEnabled()) {
                return false;
            }
            
            self::getConnection();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

