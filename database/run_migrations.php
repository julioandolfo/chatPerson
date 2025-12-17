<?php
/**
 * Script para executar migrations
 * 
 * Uso: php database/run_migrations.php [numero_da_migration]
 * Exemplo: php database/run_migrations.php 030
 */

// Carregar configurações
$dbConfig = require __DIR__ . '/../config/database.php';

// Criar conexão PDO global para migrations
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    echo "❌ Erro ao conectar ao banco: " . $e->getMessage() . "\n";
    exit(1);
}

$migrationNumber = $argv[1] ?? null;

if (!$migrationNumber) {
    echo "❌ Erro: Especifique o número da migration\n";
    echo "Uso: php database/run_migrations.php [numero]\n";
    echo "Exemplo: php database/run_migrations.php 030\n";
    exit(1);
}

$migrationFile = __DIR__ . "/migrations/{$migrationNumber}_*.php";
$files = glob($migrationFile);

if (empty($files)) {
    echo "❌ Erro: Migration {$migrationNumber} não encontrada\n";
    exit(1);
}

$file = $files[0];
echo "📂 Executando migration: " . basename($file) . "\n\n";

require_once $file;

// Extrair nome da função up_
$filename = basename($file, '.php');
$parts = explode('_', $filename, 2);
$functionName = 'up_' . ($parts[1] ?? '');

if (function_exists($functionName)) {
    try {
        $functionName();
        echo "\n✅ Migration executada com sucesso!\n";
    } catch (Exception $e) {
        echo "\n❌ Erro ao executar migration: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "❌ Erro: Função {$functionName} não encontrada\n";
    exit(1);
}

