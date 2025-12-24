<?php
/**
 * Script de Teste: Modo TTS Adaptativo
 * Acesse: http://seudominio.com/test-tts-adaptive.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Testar configurações
$settings = \App\Services\ConversationSettingsService::getSettings();
$tts = $settings['text_to_speech'] ?? [];

echo "<h1>🧪 Teste: Modo TTS Adaptativo</h1>";
echo "<hr>";

// 1. Configurações Atuais
echo "<h2>⚙️ Configurações TTS</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>Configuração</th><th>Valor</th></tr>";
echo "<tr><td>Provider</td><td>{$tts['provider']}</td></tr>";
echo "<tr><td>Send Mode</td><td><strong>{$tts['send_mode']}</strong></td></tr>";
echo "<tr><td>Adaptive Mode (rules)</td><td>" . ($tts['intelligent_rules']['adaptive_mode'] ?? false ? '✅ Sim' : '❌ Não') . "</td></tr>";
echo "<tr><td>First Message Always Text</td><td>" . ($tts['intelligent_rules']['first_message_always_text'] ?? false ? '✅ Sim' : '❌ Não') . "</td></tr>";
echo "</table>";

echo "<hr>";

// 2. Cenários de Teste
echo "<h2>📋 Cenários de Teste</h2>";

$scenarios = [
    [
        'title' => '🔄 Modo Adaptativo: Cliente envia áudio',
        'description' => '1. Cliente envia mensagem de áudio<br>2. IA deve responder com áudio',
        'expected' => 'audio_only'
    ],
    [
        'title' => '🔄 Modo Adaptativo: Cliente envia texto',
        'description' => '1. Cliente envia mensagem de texto<br>2. IA deve responder com texto',
        'expected' => 'text_only'
    ],
    [
        'title' => '🔄 Modo Adaptativo: Cliente pede "não envie áudio"',
        'description' => '1. Cliente envia: "não envie áudio"<br>2. IA salva preferência<br>3. Todas as próximas mensagens são texto',
        'expected' => 'text_only (permanente)'
    ],
    [
        'title' => '🤖 Modo Inteligente: Primeira mensagem',
        'description' => '1. Primeira mensagem da IA<br>2. Deve ser sempre texto (segurança)',
        'expected' => 'text_only'
    ],
    [
        'title' => '🤖 Modo Inteligente: Mensagem com URL',
        'description' => '1. IA envia mensagem com link<br>2. Deve ser texto para preservar link clicável',
        'expected' => 'text_only'
    ],
];

foreach ($scenarios as $i => $scenario) {
    $num = $i + 1;
    echo "<div style='border:1px solid #ddd; padding:15px; margin:10px 0; border-radius:5px;'>";
    echo "<h3>{$num}. {$scenario['title']}</h3>";
    echo "<p>{$scenario['description']}</p>";
    echo "<p><strong>Resultado Esperado:</strong> <code>{$scenario['expected']}</code></p>";
    echo "</div>";
}

echo "<hr>";

// 3. Palavras-chave detectadas
echo "<h2>🔑 Palavras-chave para Modo Adaptativo</h2>";
echo "<p>Se o cliente digitar qualquer uma destas frases, a IA passa a enviar <strong>SOMENTE TEXTO</strong>:</p>";
echo "<ul>";
$keywords = [
    'não envie áudio',
    'não mande áudio',
    'sem áudio',
    'apenas texto',
    'só texto',
    'somente texto',
    'prefiro texto',
    'não gosto de áudio',
    'pare de enviar áudio',
    'não quero áudio'
];
foreach ($keywords as $keyword) {
    echo "<li><code>{$keyword}</code></li>";
}
echo "</ul>";

echo "<hr>";

// 4. Instruções
echo "<h2>📝 Como Testar</h2>";
echo "<ol>";
echo "<li>Acesse <strong>Configurações → Conversas</strong></li>";
echo "<li>Na seção <strong>Text-to-Speech</strong>:</li>";
echo "<ul>";
echo "<li>✅ Ative <strong>Ativar Text-to-Speech</strong></li>";
echo "<li>✅ Ative <strong>Gerar áudio automaticamente</strong></li>";
echo "<li>🔄 Selecione <strong>Modo: Adaptativo</strong></li>";
echo "<li>✅ Marque <strong>Primeira mensagem sempre em texto</strong></li>";
echo "</ul>";
echo "<li>Salve as configurações</li>";
echo "<li>Crie uma nova conversa de teste</li>";
echo "<li>Teste os cenários acima</li>";
echo "</ol>";

echo "<hr>";

// 5. Logs
echo "<h2>📊 Verificar Logs</h2>";
echo "<p>Após testar, verifique os logs para entender as decisões:</p>";
echo "<ul>";
echo "<li><a href='/view-all-logs.php?file=app' target='_blank'>app.log</a> - Logs detalhados do TTSIntelligentService</li>";
echo "<li><a href='/view-all-logs.php?file=ai_agent' target='_blank'>ai_agent.log</a> - Logs do AIAgentService</li>";
echo "</ul>";

echo "<p>Procure por linhas como:</p>";
echo "<pre style='background:#f5f5f5; padding:10px; border-radius:5px;'>";
echo "[INFO] TTSIntelligentService - 🔄 Modo ADAPTATIVO ativado\n";
echo "[INFO] TTSIntelligentService - 📊 Últimas 3 mensagens: 2 áudios, 1 textos\n";
echo "[INFO] TTSIntelligentService - ✅ Cliente usa áudio! Enviando audio_only\n";
echo "</pre>";

echo "<hr>";
echo "<p><a href='/'>← Voltar para Home</a> | <a href='/settings'>Ir para Configurações</a></p>";

