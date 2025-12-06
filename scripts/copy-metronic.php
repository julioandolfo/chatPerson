<?php
/**
 * Script para copiar arquivos necessários do Metronic
 * 
 * Execute: php scripts/copy-metronic.php
 * 
 * Este script copia apenas os arquivos essenciais do Metronic
 * para a estrutura do projeto em public/assets/
 */

$metronicPath = __DIR__ . '/../metronic';
$publicPath = __DIR__ . '/../public/assets';

// Verificar se pasta metronic existe
if (!is_dir($metronicPath)) {
    die("ERRO: Pasta 'metronic' não encontrada!\n");
}

echo "🚀 Iniciando cópia de arquivos do Metronic...\n\n";

// Criar estrutura de pastas
$dirs = [
    'css/metronic',
    'js/metronic',
    'plugins/global',
    'plugins/custom',
    'media/logos',
    'media/avatars',
    'media/icons',
    'media/illustrations'
];

foreach ($dirs as $dir) {
    $fullPath = $publicPath . '/' . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
        echo "✅ Criado: $fullPath\n";
    }
}

// Arquivos individuais para copiar
$files = [
    // CSS Principal
    [
        'source' => 'assets/css/style.bundle.css',
        'dest' => 'css/metronic/style.bundle.css',
        'required' => true
    ],
    
    // CSS Plugins Globais
    [
        'source' => 'assets/plugins/global/plugins.bundle.css',
        'dest' => 'plugins/global/plugins.bundle.css',
        'required' => true
    ],
    
    // JavaScript Principal
    [
        'source' => 'assets/js/scripts.bundle.js',
        'dest' => 'js/metronic/scripts.bundle.js',
        'required' => true
    ],
    
    // JavaScript Widgets
    [
        'source' => 'assets/js/widgets.bundle.js',
        'dest' => 'js/metronic/widgets.bundle.js',
        'required' => true
    ],
];

// Copiar arquivos individuais
$copied = 0;
$failed = 0;

foreach ($files as $file) {
    $sourcePath = $metronicPath . '/' . $file['source'];
    $destPath = $publicPath . '/' . $file['dest'];
    
    if (is_file($sourcePath)) {
        // Criar diretório de destino se não existir
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        if (copy($sourcePath, $destPath)) {
            echo "✅ Copiado: {$file['source']} -> {$file['dest']}\n";
            $copied++;
        } else {
            echo "❌ Erro ao copiar: {$file['source']}\n";
            $failed++;
            if ($file['required']) {
                echo "   ⚠️  Arquivo obrigatório falhou!\n";
            }
        }
    } else {
        echo "⚠️  Não encontrado: {$file['source']}\n";
        if ($file['required']) {
            echo "   ⚠️  Arquivo obrigatório não encontrado!\n";
            $failed++;
        }
    }
}

// Copiar diretórios inteiros
$directories = [
    // Fontes (obrigatório)
    [
        'source' => 'assets/plugins/global/fonts',
        'dest' => 'plugins/global/fonts',
        'required' => true
    ],
    
    // Media - Logos
    [
        'source' => 'assets/media/logos',
        'dest' => 'media/logos',
        'required' => false
    ],
    
    // Media - Avatares
    [
        'source' => 'assets/media/avatars',
        'dest' => 'media/avatars',
        'required' => false
    ],
    
    // Media - Ícones (opcional, pode ser grande)
    [
        'source' => 'assets/media/icons',
        'dest' => 'media/icons',
        'required' => false
    ],
];

foreach ($directories as $dir) {
    $sourcePath = $metronicPath . '/' . $dir['source'];
    $destPath = $publicPath . '/' . $dir['dest'];
    
    if (is_dir($sourcePath)) {
        if (copyDirectory($sourcePath, $destPath)) {
            echo "✅ Copiado diretório: {$dir['source']} -> {$dir['dest']}\n";
            $copied++;
        } else {
            echo "❌ Erro ao copiar diretório: {$dir['source']}\n";
            $failed++;
            if ($dir['required']) {
                echo "   ⚠️  Diretório obrigatório falhou!\n";
            }
        }
    } else {
        echo "⚠️  Diretório não encontrado: {$dir['source']}\n";
        if ($dir['required']) {
            echo "   ⚠️  Diretório obrigatório não encontrado!\n";
            $failed++;
        }
    }
}

// Função para copiar diretório recursivamente
function copyDirectory($source, $dest) {
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $filesCopied = 0;
    
    foreach ($iterator as $item) {
        $destPath = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }
        } else {
            if (copy($item, $destPath)) {
                $filesCopied++;
            }
        }
    }
    
    return $filesCopied > 0;
}

// Resumo
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RESUMO\n";
echo str_repeat("=", 50) . "\n";
echo "✅ Arquivos copiados com sucesso: $copied\n";
if ($failed > 0) {
    echo "❌ Falhas: $failed\n";
}
echo "\n";

if ($failed == 0) {
    echo "🎉 Cópia concluída com sucesso!\n";
    echo "\n📝 Próximos passos:\n";
    echo "   1. Verificar se os arquivos foram copiados corretamente\n";
    echo "   2. Testar carregamento de CSS/JS no navegador\n";
    echo "   3. Criar layout base usando Metronic\n";
} else {
    echo "⚠️  Alguns arquivos falharam. Verifique os erros acima.\n";
}

echo "\n";

