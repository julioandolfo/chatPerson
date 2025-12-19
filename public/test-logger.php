<?php
/**
 * Teste direto do Logger
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

header('Content-Type: text/html; charset=UTF-8');

echo '<style>
    body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
    .error { color: #f00; font-weight: bold; }
    .success { color: #0f0; font-weight: bold; }
    .warning { color: #ff0; font-weight: bold; }
    pre { background: #111; padding: 10px; overflow-x: auto; }
</style>';

echo "<h1>🧪 TESTE DO LOGGER</h1>";

$logFile = __DIR__ . '/../logs/automacao.log';

echo "<h2>1️⃣ Informações do Arquivo de Log</h2>";
echo "<pre>";
echo "Caminho: {$logFile}\n";
echo "Existe: " . (file_exists($logFile) ? 'SIM' : 'NÃO') . "\n";
if (file_exists($logFile)) {
    echo "Permissões: " . substr(sprintf('%o', fileperms($logFile)), -4) . "\n";
    echo "Tamanho: " . filesize($logFile) . " bytes\n";
    echo "Última modificação: " . date('Y-m-d H:i:s', filemtime($logFile)) . "\n";
}
echo "</pre>";

echo "<h2>2️⃣ Testando Escrita Direta</h2>";

// Limpar log anterior
if (file_exists($logFile)) {
    file_put_contents($logFile, '');
    echo "<p class='success'>✅ Log anterior limpo</p>";
}

// Testar Logger::debug
echo "<p>Escrevendo com Logger::debug...</p>";
\App\Helpers\Logger::debug("TESTE 1: Escrita direta via Logger::debug", 'automacao.log');
echo "<p class='success'>✅ Comando executado sem erro</p>";

// Testar escrita direta
echo "<p>Escrevendo com file_put_contents...</p>";
$testMsg = "[" . date('Y-m-d H:i:s') . "] TESTE 2: Escrita direta via file_put_contents\n";
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
    echo "<p class='warning'>⚠️ Diretório criado: {$logDir}</p>";
}
file_put_contents($logFile, $testMsg, FILE_APPEND);
echo "<p class='success'>✅ Escrita direta executada</p>";

echo "<h2>3️⃣ Conteúdo do Log Após Teste</h2>";
if (file_exists($logFile) && filesize($logFile) > 0) {
    echo "<pre>";
    echo htmlspecialchars(file_get_contents($logFile));
    echo "</pre>";
} else {
    echo "<p class='error'>❌ Log vazio ou não existe!</p>";
}

echo "<h2>4️⃣ Testando AutomationService Logs</h2>";
echo "<p>Buscando última conversa...</p>";

$lastConv = \App\Helpers\Database::fetch("
    SELECT id, funnel_id, funnel_stage_id, channel, whatsapp_account_id
    FROM conversations
    ORDER BY id DESC
    LIMIT 1
", []);

if ($lastConv) {
    echo "<pre>";
    echo "Conversa ID: {$lastConv['id']}\n";
    echo "Funil ID: {$lastConv['funnel_id']}\n";
    echo "Estágio ID: {$lastConv['funnel_stage_id']}\n";
    echo "</pre>";
    
    echo "<p>Chamando AutomationService::executeForNewConversation...</p>";
    
    try {
        \App\Services\AutomationService::executeForNewConversation($lastConv['id']);
        echo "<p class='success'>✅ Execução completada sem erro</p>";
    } catch (\Exception $e) {
        echo "<p class='error'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<h2>5️⃣ Log Após Execução</h2>";
    if (file_exists($logFile) && filesize($logFile) > 0) {
        echo "<pre style='max-height: 500px; overflow-y: auto;'>";
        echo htmlspecialchars(file_get_contents($logFile));
        echo "</pre>";
    } else {
        echo "<p class='error'>❌ NENHUM log gerado após executeForNewConversation!</p>";
        echo "<p class='warning'>⚠️ Isso significa que o código está em cache ou os logs não estão sendo escritos.</p>";
    }
} else {
    echo "<p class='error'>❌ Nenhuma conversa encontrada</p>";
}

echo "<br><br>";
echo "<p><strong>PRÓXIMOS PASSOS:</strong></p>";
echo "<ol>";
echo "<li><a href='clear-opcache.php' style='color: #0f0;'>Limpar OPcache</a></li>";
echo "<li>Atualizar esta página e testar novamente</li>";
echo "<li>Se ainda não funcionar, verificar se o autoload está carregando a versão correta dos arquivos</li>";
echo "</ol>";

