<?php
/**
 * Script para criar e testar o arquivo de log de Goals
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Helpers\Logger;

$logFile = __DIR__ . '/../logs/goals.log';
$logsDir = __DIR__ . '/../logs';

echo "<pre style='background:#1e1e1e;color:#d4d4d4;padding:20px;font-family:monospace;font-size:12px;'>";
echo "<div style='color:#4ec9b0;font-size:16px;border-bottom:2px solid #4ec9b0;padding-bottom:10px;margin-bottom:20px;'>";
echo "🎯 DIAGNÓSTICO: Log de Goals\n";
echo "</div>";

// 1. Verificar se o diretório existe
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>1. Verificando diretório de logs...</strong>\n";
if (!is_dir($logsDir)) {
    echo "   <span style='color:#f48771;'>❌ Diretório não existe:</span> {$logsDir}\n";
    echo "   <span style='color:#dcdcaa;'>Criando...</span>\n";
    mkdir($logsDir, 0777, true);
    echo "   <span style='color:#4ec9b0;'>✅ Diretório criado!</span>\n";
} else {
    echo "   <span style='color:#4ec9b0;'>✅ Diretório existe:</span> {$logsDir}\n";
    echo "   <span style='color:#888;'>   Gravável: " . (is_writable($logsDir) ? 'SIM' : 'NÃO') . "</span>\n";
    echo "   <span style='color:#888;'>   Permissões: " . substr(sprintf('%o', fileperms($logsDir)), -4) . "</span>\n";
}
echo "</div>";

// 2. Verificar arquivo
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>2. Verificando arquivo goals.log...</strong>\n";
if (file_exists($logFile)) {
    echo "   <span style='color:#4ec9b0;'>✅ Arquivo existe:</span> {$logFile}\n";
    echo "   <span style='color:#888;'>   Tamanho: " . filesize($logFile) . " bytes</span>\n";
    echo "   <span style='color:#888;'>   Gravável: " . (is_writable($logFile) ? 'SIM' : 'NÃO') . "</span>\n";
    echo "   <span style='color:#888;'>   Permissões: " . substr(sprintf('%o', fileperms($logFile)), -4) . "</span>\n";
} else {
    echo "   <span style='color:#f48771;'>❌ Arquivo não existe</span>\n";
    echo "   <span style='color:#dcdcaa;'>Criando...</span>\n";
    touch($logFile);
    chmod($logFile, 0666);
    echo "   <span style='color:#4ec9b0;'>✅ Arquivo criado!</span>\n";
}
echo "</div>";

// 3. Testar escrita direta
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>3. Teste de escrita direta...</strong>\n";
$testMessage = "[" . date('Y-m-d H:i:s') . "] [INFO] ✅ TESTE DIRETO - file_put_contents funcionando!\n";
$result = @file_put_contents($logFile, $testMessage, FILE_APPEND);

if ($result !== false) {
    echo "   <span style='color:#4ec9b0;'>✅ Escrita OK!</span> ({$result} bytes)\n";
} else {
    echo "   <span style='color:#f48771;'>❌ Erro ao escrever!</span>\n";
    $error = error_get_last();
    if ($error) {
        echo "   <span style='color:#f48771;'>Erro: " . htmlspecialchars($error['message']) . "</span>\n";
    }
}
echo "</div>";

// 4. Testar classe Logger
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>4. Teste da classe Logger...</strong>\n";
try {
    Logger::info('✅ TESTE via Logger::info() - Funcionando!', 'goals.log');
    echo "   <span style='color:#4ec9b0;'>✅ Logger::info() executado sem erros!</span>\n";
} catch (\Exception $e) {
    echo "   <span style='color:#f48771;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}
echo "</div>";

// 5. Ler conteúdo atual
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>5. Conteúdo atual do arquivo:</strong>\n";
if (file_exists($logFile) && filesize($logFile) > 0) {
    $content = file_get_contents($logFile);
    $lines = explode("\n", trim($content));
    $lastLines = array_slice($lines, -10); // Últimas 10 linhas
    
    echo "<div style='background:#252526;padding:10px;border-left:3px solid #007acc;margin:10px 0;'>";
    foreach ($lastLines as $line) {
        if (trim($line)) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</div>";
    echo "   <span style='color:#888;'>Total de linhas: " . count($lines) . "</span>\n";
} else {
    echo "   <span style='color:#dcdcaa;'>⚠️ Arquivo vazio ou não existe</span>\n";
}
echo "</div>";

// 6. Informações do sistema
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>6. Informações do sistema:</strong>\n";
echo "   <span style='color:#888;'>PHP User: " . (function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "</span>\n";
echo "   <span style='color:#888;'>PHP Version: " . phpversion() . "</span>\n";
echo "   <span style='color:#888;'>Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</span>\n";
if (function_exists('posix_geteuid')) {
    echo "   <span style='color:#888;'>UID: " . posix_geteuid() . " | GID: " . posix_getegid() . "</span>\n";
}
echo "</div>";

// 7. Instruções finais
echo "<div style='margin:20px 0;padding:15px;background:#3c3c1e;border-left:3px solid #dcdcaa;'>";
echo "<strong style='color:#dcdcaa;'>📋 Próximos Passos:</strong>\n\n";
echo "1. Se tudo está ✅, o arquivo foi criado com sucesso!\n";
echo "2. Acesse: <a href='/goals/edit?id=1' style='color:#569cd6;'>/goals/edit?id=1</a> (use um ID válido)\n";
echo "3. Adicione tiers e condições, salve a meta\n";
echo "4. Veja os logs em: <a href='/view-all-logs.php' style='color:#569cd6;'>/view-all-logs.php</a>\n\n";
echo "Se o teste direto funcionou mas os logs da meta não aparecem:\n";
echo "→ O problema está no código do GoalController, não nas permissões!\n";
echo "</div>";

// 8. Testar código real do controller
echo "<div style='margin:15px 0;'><strong style='color:#569cd6;'>7. Simulando log do GoalController...</strong>\n";
Logger::info('saveBonusTiers - TESTE SIMULADO - goalId: 999', 'goals.log');
Logger::info('saveBonusTiers - TESTE SIMULADO - tiers empty: NO', 'goals.log');
Logger::info('saveGoalConditions - TESTE SIMULADO - conditions empty: NO', 'goals.log');
echo "   <span style='color:#4ec9b0;'>✅ 3 logs de teste escritos!</span>\n";
echo "   <span style='color:#888;'>Verifique se aparecem no arquivo acima</span>\n";
echo "</div>";

echo "\n<div style='border-top:2px solid #4ec9b0;padding-top:15px;margin-top:20px;color:#4ec9b0;font-size:14px;'>";
echo "✅ DIAGNÓSTICO COMPLETO!\n";
echo "</div>";

echo "</pre>";
