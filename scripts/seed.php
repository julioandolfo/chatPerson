<?php
/**
 * Script para executar seeds
 * 
 * Execute: php scripts/seed.php
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

// Verificar conexão
try {
    \App\Helpers\Database::getInstance();
    echo "✅ Conectado ao banco de dados!\n\n";
} catch (Exception $e) {
    die("❌ Erro ao conectar: " . $e->getMessage() . "\n");
}

// Executar seeds
$seedsDir = __DIR__ . '/../database/seeds';
$seeds = glob($seedsDir . '/*.php');

if (empty($seeds)) {
    echo "⚠️  Nenhum seed encontrado!\n";
    exit;
}

// Ordenar seeds
sort($seeds);

echo "🚀 Executando seeds...\n\n";

foreach ($seeds as $seedFile) {
    $filename = basename($seedFile);
    echo "📄 Executando: {$filename}\n";
    
    require $seedFile;
    
    // Extrair nome da função: remover número inicial, prefixo create_ e .php, adicionar prefixo seed_
    $functionName = preg_replace('/^\d+_/', '', $filename); // Remove número inicial (ex: 001_)
    $functionName = preg_replace('/^create_/', '', $functionName); // Remove prefixo create_
    $functionName = str_replace('.php', '', $functionName); // Remove extensão
    $functionName = 'seed_' . $functionName; // Adiciona prefixo seed_
    
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
        echo "   Funções disponíveis: " . implode(', ', array_filter(get_defined_functions()['user'], function($f) {
            return strpos($f, 'seed_') === 0;
        })) . "\n";
    }
    
    echo "\n";
}

echo "✅ Seeds concluídos!\n";

