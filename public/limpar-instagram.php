<?php
/**
 * Script para limpar dados antigos do Instagram
 * Execute este script antes de tentar conectar novamente
 */

// Caminho absoluto do root
$rootPath = dirname(__DIR__);

// Carregar autoload
require_once $rootPath . '/app/Helpers/Database.php';

$db = \App\Helpers\Database::getInstance();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Limpar Dados Instagram</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 40px; background: #f5f8fa; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e3a8a; margin-bottom: 10px; }
        .subtitle { color: #64748b; margin-bottom: 30px; }
        .result { padding: 15px; margin-bottom: 15px; border-radius: 6px; border-left: 4px solid; }
        .success { background: #ecfdf5; border-color: #10b981; color: #065f46; }
        .error { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
        .info { background: #eff6ff; border-color: #3b82f6; color: #1e3a8a; }
        .button { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .button:hover { background: #2563eb; }
        .warning { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧹 Limpar Dados Instagram</h1>
        <p class='subtitle'>Este script remove todos os dados de conexões Instagram anteriores</p>";

try {
    // Contar registros antes
    $countInstagram = $db->query("SELECT COUNT(*) FROM instagram_accounts")->fetchColumn();
    $countTokens = $db->query("SELECT COUNT(*) FROM meta_oauth_tokens")->fetchColumn();
    $countIntegration = $db->query("SELECT COUNT(*) FROM integration_accounts WHERE provider = 'meta'")->fetchColumn();
    
    echo "<div class='result info'>";
    echo "<strong>📊 Dados atuais:</strong><br>";
    echo "• Instagram Accounts: <strong>{$countInstagram}</strong><br>";
    echo "• Meta OAuth Tokens: <strong>{$countTokens}</strong><br>";
    echo "• Integration Accounts (Meta): <strong>{$countIntegration}</strong>";
    echo "</div>";
    
    if ($countInstagram == 0 && $countTokens == 0 && $countIntegration == 0) {
        echo "<div class='result success'>";
        echo "<strong>✅ Nada para limpar!</strong> Não há dados antigos.";
        echo "</div>";
    } else {
        // Limpar dados
        echo "<div class='result warning'>";
        echo "<strong>⚠️ Limpando dados...</strong>";
        echo "</div>";
        
        // 1. Limpar instagram_accounts
        $db->exec("TRUNCATE TABLE instagram_accounts");
        echo "<div class='result success'>";
        echo "<strong>✅ Instagram Accounts limpo!</strong> {$countInstagram} registro(s) removido(s)";
        echo "</div>";
        
        // 2. Limpar meta_oauth_tokens
        $db->exec("TRUNCATE TABLE meta_oauth_tokens");
        echo "<div class='result success'>";
        echo "<strong>✅ Meta OAuth Tokens limpo!</strong> {$countTokens} registro(s) removido(s)";
        echo "</div>";
        
        // 3. Limpar integration_accounts (apenas Meta)
        $stmt = $db->prepare("DELETE FROM integration_accounts WHERE provider = 'meta'");
        $stmt->execute();
        echo "<div class='result success'>";
        echo "<strong>✅ Integration Accounts (Meta) limpo!</strong> {$countIntegration} registro(s) removido(s)";
        echo "</div>";
        
        echo "<div class='result success' style='margin-top: 20px; font-size: 16px;'>";
        echo "<strong>🎉 Limpeza concluída com sucesso!</strong><br>";
        echo "<small>Agora você pode tentar conectar novamente.</small>";
        echo "</div>";
    }
    
} catch (\Exception $e) {
    echo "<div class='result error'>";
    echo "<strong>❌ Erro ao limpar dados:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<h3>📋 Próximos Passos</h3>";
echo "<div class='result info'>";
echo "<strong>1.</strong> Limpe a sessão: <code>/integrations/meta?clear_session=1</code><br>";
echo "<strong>2.</strong> Vá para: <code>/integrations/meta</code><br>";
echo "<strong>3.</strong> Clique em: <strong>Conectar Instagram</strong><br>";
echo "<strong>4.</strong> Autorize as 4 permissões<br>";
echo "<strong>5.</strong> Confirme!";
echo "</div>";

echo "<a href='/integrations/meta?clear_session=1' class='button'>🔄 Limpar Sessão e Ir para Integrações</a>";

echo "</div></body></html>";

