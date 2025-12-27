<?php
/**
 * Script para verificar e criar estrutura de diretórios storage
 */

// Caminho absoluto do root
$rootPath = dirname(__DIR__);

// Diretórios necessários
$directories = [
    'storage',
    'storage/cache',
    'storage/cache/permissions',
    'storage/config',
    'storage/logs',
    'storage/uploads',
    'storage/uploads/avatars',
    'storage/uploads/attachments',
    'storage/uploads/temp',
];

$results = [];
$hasErrors = false;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificação Storage</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 40px; background: #f5f8fa; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e3a8a; margin-bottom: 10px; }
        .subtitle { color: #64748b; margin-bottom: 30px; }
        .result { padding: 15px; margin-bottom: 15px; border-radius: 6px; border-left: 4px solid; }
        .success { background: #ecfdf5; border-color: #10b981; color: #065f46; }
        .error { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
        .warning { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
        .info { background: #eff6ff; border-color: #3b82f6; color: #1e3a8a; }
        .path { font-family: 'Courier New', monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
        .icon { margin-right: 8px; font-weight: bold; }
        .summary { margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 6px; }
        .button { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .button:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificação de Diretórios Storage</h1>
        <p class='subtitle'>Verificando estrutura de diretórios necessários para o sistema</p>";

foreach ($directories as $dir) {
    $fullPath = $rootPath . DIRECTORY_SEPARATOR . $dir;
    $relativePath = str_replace('\\', '/', $dir);
    
    echo "<div class='result ";
    
    if (is_dir($fullPath)) {
        // Diretório existe, verificar permissões
        if (is_writable($fullPath)) {
            echo "success'><span class='icon'>✅</span>";
            echo "<strong>OK:</strong> <span class='path'>/{$relativePath}</span> existe e tem permissões de escrita";
            $results[$dir] = 'ok';
        } else {
            echo "warning'><span class='icon'>⚠️</span>";
            echo "<strong>Aviso:</strong> <span class='path'>/{$relativePath}</span> existe mas <strong>não</strong> tem permissões de escrita";
            echo "<br><small>Execute: <code>chmod 755 {$fullPath}</code></small>";
            $results[$dir] = 'warning';
        }
    } else {
        // Tentar criar o diretório
        try {
            if (@mkdir($fullPath, 0755, true)) {
                echo "success'><span class='icon'>✨</span>";
                echo "<strong>Criado:</strong> <span class='path'>/{$relativePath}</span> foi criado com sucesso";
                $results[$dir] = 'created';
            } else {
                echo "error'><span class='icon'>❌</span>";
                echo "<strong>Erro:</strong> Não foi possível criar <span class='path'>/{$relativePath}</span>";
                echo "<br><small>Caminho completo: <code>{$fullPath}</code></small>";
                $results[$dir] = 'error';
                $hasErrors = true;
            }
        } catch (\Exception $e) {
            echo "error'><span class='icon'>❌</span>";
            echo "<strong>Erro:</strong> Exceção ao criar <span class='path'>/{$relativePath}</span>";
            echo "<br><small>Mensagem: {$e->getMessage()}</small>";
            $results[$dir] = 'error';
            $hasErrors = true;
        }
    }
    
    echo "</div>";
}

// Criar arquivos especiais
echo "<div class='result info'><span class='icon'>📝</span>";
echo "<strong>Arquivos Especiais:</strong> Verificando arquivos de configuração</div>";

// .gitignore em storage/config
$gitignorePath = $rootPath . '/storage/config/.gitignore';
if (!file_exists($gitignorePath)) {
    $gitignoreContent = "# Ignorar TODAS as configurações sensíveis\n*\n!.gitignore\n!README.md\n";
    if (@file_put_contents($gitignorePath, $gitignoreContent)) {
        echo "<div class='result success'><span class='icon'>✅</span>";
        echo "Criado: <span class='path'>/storage/config/.gitignore</span></div>";
    } else {
        echo "<div class='result error'><span class='icon'>❌</span>";
        echo "Erro ao criar: <span class='path'>/storage/config/.gitignore</span></div>";
        $hasErrors = true;
    }
} else {
    echo "<div class='result success'><span class='icon'>✅</span>";
    echo "OK: <span class='path'>/storage/config/.gitignore</span> já existe</div>";
}

// README.md em storage/config
$readmePath = $rootPath . '/storage/config/README.md';
if (!file_exists($readmePath)) {
    $readmeContent = "# Configurações Sensíveis\n\n";
    $readmeContent .= "Este diretório armazena configurações sensíveis que NÃO devem ser commitadas no Git.\n\n";
    $readmeContent .= "## Arquivos:\n";
    $readmeContent .= "- `meta.json` - Credenciais do Meta App (Facebook/Instagram)\n";
    $readmeContent .= "- Outros arquivos de configuração sensíveis\n\n";
    $readmeContent .= "## Segurança:\n";
    $readmeContent .= "- Todos os arquivos (exceto este README) são ignorados pelo Git\n";
    $readmeContent .= "- As credenciais são carregadas dinamicamente em runtime\n";
    $readmeContent .= "- NUNCA commite arquivos de configuração sensíveis!\n";
    
    if (@file_put_contents($readmePath, $readmeContent)) {
        echo "<div class='result success'><span class='icon'>✅</span>";
        echo "Criado: <span class='path'>/storage/config/README.md</span></div>";
    } else {
        echo "<div class='result error'><span class='icon'>❌</span>";
        echo "Erro ao criar: <span class='path'>/storage/config/README.md</span></div>";
        $hasErrors = true;
    }
} else {
    echo "<div class='result success'><span class='icon'>✅</span>";
    echo "OK: <span class='path'>/storage/config/README.md</span> já existe</div>";
}

// Resumo
$okCount = count(array_filter($results, fn($r) => $r === 'ok'));
$createdCount = count(array_filter($results, fn($r) => $r === 'created'));
$warningCount = count(array_filter($results, fn($r) => $r === 'warning'));
$errorCount = count(array_filter($results, fn($r) => $r === 'error'));

echo "<div class='summary'>";
echo "<h3>📊 Resumo</h3>";
echo "<ul>";
echo "<li><strong>✅ OK:</strong> {$okCount} diretório(s) já existiam com permissões corretas</li>";
echo "<li><strong>✨ Criados:</strong> {$createdCount} diretório(s) foram criados</li>";
if ($warningCount > 0) {
    echo "<li><strong>⚠️ Avisos:</strong> {$warningCount} diretório(s) precisam de permissões</li>";
}
if ($errorCount > 0) {
    echo "<li><strong>❌ Erros:</strong> {$errorCount} problema(s) encontrado(s)</li>";
}
echo "</ul>";

if ($hasErrors) {
    echo "<p style='color: #991b1b; margin-top: 20px;'><strong>⚠️ Ação necessária:</strong> Alguns diretórios não puderam ser criados. Verifique as permissões do servidor.</p>";
    echo "<p><strong>Solução:</strong> Execute manualmente:</p>";
    echo "<pre style='background: #f1f5f9; padding: 15px; border-radius: 6px; overflow-x: auto;'>";
    echo "cd " . str_replace('\\', '/', $rootPath) . "\n";
    echo "mkdir -p " . implode(' ', array_map(fn($d) => str_replace('\\', '/', $d), $directories)) . "\n";
    echo "chmod -R 755 storage/\n";
    echo "</pre>";
} else {
    echo "<p style='color: #065f46; margin-top: 20px;'><strong>✅ Tudo pronto!</strong> Todos os diretórios estão configurados corretamente.</p>";
}

echo "</div>";

echo "<a href='/integrations/meta' class='button'>← Voltar para Integrações Meta</a>";

echo "</div></body></html>";
