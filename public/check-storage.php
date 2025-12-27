<?php
/**
 * Script para verificar e criar estrutura de diretórios storage
 */

require_once __DIR__ . '/../vendor/autoload.php';

$rootPath = dirname(__DIR__);

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         VERIFICAR E CRIAR DIRETÓRIOS STORAGE              ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

echo "Root Path: {$rootPath}\n\n";

$directories = [
    '/storage',
    '/storage/config',
    '/storage/logs',
    '/storage/cache',
    '/storage/cache/permissions',
    '/storage/uploads',
    '/storage/uploads/attachments',
    '/storage/uploads/avatars',
    '/storage/uploads/temp'
];

foreach ($directories as $dir) {
    $fullPath = $rootPath . $dir;
    
    echo "Verificando: {$dir}\n";
    
    if (is_dir($fullPath)) {
        echo "  ✅ Existe\n";
        
        // Verificar permissões
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        echo "  📁 Permissões: {$perms}\n";
        
        // Verificar se é gravável
        if (is_writable($fullPath)) {
            echo "  ✅ Gravável\n";
        } else {
            echo "  ❌ NÃO gravável\n";
            echo "  🔧 Tentando corrigir permissões...\n";
            if (@chmod($fullPath, 0755)) {
                echo "  ✅ Permissões corrigidas\n";
            } else {
                echo "  ❌ Falha ao corrigir permissões\n";
            }
        }
    } else {
        echo "  ❌ NÃO existe\n";
        echo "  🔧 Criando diretório...\n";
        
        if (mkdir($fullPath, 0755, true)) {
            echo "  ✅ Criado com sucesso\n";
        } else {
            echo "  ❌ ERRO ao criar\n";
            $error = error_get_last();
            echo "  ⚠️  Erro: " . ($error['message'] ?? 'Desconhecido') . "\n";
        }
    }
    
    echo "\n";
}

// Criar .gitignore em storage/config se não existir
$configGitignore = $rootPath . '/storage/config/.gitignore';
if (!file_exists($configGitignore)) {
    echo "Criando .gitignore em storage/config...\n";
    $content = "# Não commitar arquivos de configuração sensíveis\n*.json\n!.gitignore\n";
    if (file_put_contents($configGitignore, $content)) {
        echo "✅ .gitignore criado\n\n";
    } else {
        echo "❌ Erro ao criar .gitignore\n\n";
    }
}

// Criar README em storage/config se não existir
$configReadme = $rootPath . '/storage/config/README.md';
if (!file_exists($configReadme)) {
    echo "Criando README em storage/config...\n";
    $content = "# Configurações do Sistema\n\n";
    $content .= "Este diretório armazena arquivos de configuração sensíveis do sistema.\n\n";
    $content .= "## Arquivos:\n\n";
    $content .= "- `meta.json` - Configurações do Meta (Facebook/Instagram)\n";
    $content .= "- Outros arquivos de configuração podem ser adicionados aqui\n\n";
    $content .= "## Segurança:\n\n";
    $content .= "⚠️ **IMPORTANTE:** Estes arquivos contêm informações sensíveis (tokens, secrets, etc) e **NÃO devem ser commitados** no Git.\n\n";
    $content .= "O `.gitignore` está configurado para ignorar arquivos `.json` neste diretório.\n";
    
    if (file_put_contents($configReadme, $content)) {
        echo "✅ README criado\n\n";
    } else {
        echo "❌ Erro ao criar README\n\n";
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "✅ Verificação concluída!\n";
echo "═══════════════════════════════════════════════════════════\n";

