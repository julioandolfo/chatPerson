<?php
// Arquivo temporário para limpar cache do OPcache

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpo com sucesso!<br>";
} else {
    echo "⚠️ OPcache não está ativado<br>";
}

if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "✅ APCu limpo com sucesso!<br>";
}

echo "<br>✅ Agora teste o chatbot novamente!";
echo "<br><br><a href='/'>← Voltar</a>";
