<?php
/**
 * Script para executar migrations
 * 
 * Execute: php scripts/migrate.php
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

// Carregar configurações
$dbConfig = require __DIR__ . '/../config/database.php';

// Conectar ao banco (sem especificar database para criar se não existir)
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    
    // Criar database se não existir
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['database']}` CHARACTER SET {$dbConfig['charset']} COLLATE {$dbConfig['collation']}");
    $pdo->exec("USE `{$dbConfig['database']}`");
    
    echo "✅ Conectado ao banco de dados!\n\n";
    
    // As migrations usarão a variável global $pdo
} catch (PDOException $e) {
    die("❌ Erro ao conectar: " . $e->getMessage() . "\n");
}

// Executar migrations
$migrationsDir = __DIR__ . '/../database/migrations';
$migrations = glob($migrationsDir . '/*.php');

if (empty($migrations)) {
    echo "⚠️  Nenhuma migration encontrada!\n";
    exit;
}

// Ordenar migrations
sort($migrations);

echo "🚀 Executando migrations...\n\n";

foreach ($migrations as $migrationFile) {
    $filename = basename($migrationFile);
    echo "📄 Executando: {$filename}\n";
    
    require $migrationFile;
    
    // Extrair nome da função: remover número inicial e .php, adicionar prefixo up_
    $baseName = preg_replace('/^\d+_/', '', $filename); // Remove número inicial (ex: 001_)
    $baseName = str_replace('.php', '', $baseName); // Remove extensão
    
    // Tentar primeiro COM create_ (novo padrão)
    $functionName = 'up_' . $baseName;
    
    // Se não existir, tentar SEM create_ (padrão antigo)
    if (!function_exists($functionName)) {
        $baseNameWithoutCreate = preg_replace('/^create_/', '', $baseName);
        $functionNameAlt = 'up_' . $baseNameWithoutCreate;
        
        if (function_exists($functionNameAlt)) {
            $functionName = $functionNameAlt;
        }
    }
    
    // Debug: mostrar função procurada
    // echo "   Procurando função: {$functionName}\n";
    
    if (function_exists($functionName)) {
        try {
            $functionName();
        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
        }
    } else {
        echo "⚠️  Função '{$functionName}' não encontrada!\n";
        echo "   Funções disponíveis: " . implode(', ', get_defined_functions()['user']) . "\n";
    }
    
    echo "\n";
}

echo "✅ Migrations concluídas!\n";

