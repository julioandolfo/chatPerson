<?php
/**
 * Verificar configurações de análise de sentimento no banco
 */

// Mudar para diretório raiz do projeto
chdir(__DIR__ . '/../../');

// Autoloader
require_once __DIR__ . '/../../app/Helpers/autoload.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers\Database;

echo "========================================\n";
echo "VERIFICAR CONFIGURAÇÕES NO BANCO\n";
echo "========================================\n\n";

// Buscar configuração no banco
$setting = Database::fetch("SELECT * FROM settings WHERE `key` = 'conversation_settings'");

if (!$setting) {
    echo "❌ PROBLEMA: Configuração 'conversation_settings' NÃO ENCONTRADA no banco!\n";
    echo "Isso significa que as configurações nunca foram salvas.\n\n";
    echo "Solução:\n";
    echo "1. Acesse: Configurações > Botões de Ação > Análise de Sentimento\n";
    echo "2. Configure as opções\n";
    echo "3. Clique em SALVAR no final da página\n\n";
    exit(1);
}

echo "✅ Configuração encontrada no banco!\n\n";
echo "ID: " . $setting['id'] . "\n";
echo "Grupo: " . $setting['group'] . "\n";
echo "Tipo: " . $setting['type'] . "\n";
echo "Atualizado em: " . $setting['updated_at'] . "\n\n";

// Decodificar JSON
$config = json_decode($setting['value'], true);

if (!$config || !is_array($config)) {
    echo "❌ ERRO: Não foi possível decodificar o JSON!\n";
    echo "Valor bruto:\n";
    echo $setting['value'] . "\n\n";
    exit(1);
}

// Verificar se sentiment_analysis existe
if (!isset($config['sentiment_analysis'])) {
    echo "❌ PROBLEMA: Chave 'sentiment_analysis' NÃO ENCONTRADA nas configurações!\n";
    echo "Chaves disponíveis: " . implode(', ', array_keys($config)) . "\n\n";
    exit(1);
}

$sentiment = $config['sentiment_analysis'];

echo "📊 CONFIGURAÇÕES DE ANÁLISE DE SENTIMENTO:\n";
echo "==========================================\n\n";
echo "Habilitado: " . ($sentiment['enabled'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Modelo: " . ($sentiment['model'] ?? 'N/A') . "\n";
echo "Temperature: " . ($sentiment['temperature'] ?? 'N/A') . "\n";
echo "Intervalo de verificação: " . ($sentiment['check_interval_hours'] ?? 'N/A') . " horas\n";
echo "Idade máxima da conversa: " . ($sentiment['max_conversation_age_days'] ?? 'N/A') . " dias\n";
echo "Analisar ao receber nova mensagem: " . ($sentiment['analyze_on_new_message'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Analisar a cada X mensagens: " . ($sentiment['analyze_on_message_count'] ?? 'N/A') . "\n";
echo "Mín. mensagens para analisar: " . ($sentiment['min_messages_to_analyze'] ?? 'N/A') . "\n";
echo "Analisar últimas X mensagens: " . ($sentiment['analyze_last_messages'] ?? 'toda conversa') . "\n";
echo "Incluir emoções específicas: " . ($sentiment['include_emotions'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Incluir nível de urgência: " . ($sentiment['include_urgency'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Auto-tag quando sentimento negativo: " . ($sentiment['auto_tag_negative'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Tag negativa (ID): " . ($sentiment['negative_tag_id'] ?? 'N/A') . "\n";
echo "Limite de custo diário: $" . number_format($sentiment['cost_limit_per_day'] ?? 0, 2) . "\n\n";

// Verificar se as configurações fazem sentido
echo "🔍 VALIDAÇÃO:\n";
echo "==========================================\n\n";

$issues = [];

if (!$sentiment['enabled']) {
    $issues[] = "❌ Análise está DESABILITADA";
}

$minMessages = (int)($sentiment['min_messages_to_analyze'] ?? 3);
if ($minMessages > 50) {
    $issues[] = "⚠️ Mínimo de mensagens muito alto ({$minMessages}) - conversas podem nunca atingir esse número";
}

if ($minMessages > 100) {
    $issues[] = "❌ Mínimo de mensagens EXTREMAMENTE alto ({$minMessages}) - isso é muito improvável de acontecer";
}

$maxAge = (int)($sentiment['max_conversation_age_days'] ?? 30);
if ($maxAge < 1) {
    $issues[] = "❌ Idade máxima muito baixa ({$maxAge} dias)";
}

$costLimit = (float)($sentiment['cost_limit_per_day'] ?? 0);
if ($costLimit <= 0) {
    $issues[] = "⚠️ Limite de custo diário zerado - pode bloquear análises";
}

if (empty($issues)) {
    echo "✅ Configurações parecem válidas!\n\n";
} else {
    echo "⚠️ Problemas encontrados:\n\n";
    foreach ($issues as $issue) {
        echo "   " . $issue . "\n";
    }
    echo "\n";
}

// JSON formatado
echo "📄 JSON COMPLETO (formatado):\n";
echo "==========================================\n";
echo json_encode($sentiment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "========================================\n";
echo "FIM DA VERIFICAÇÃO\n";
echo "========================================\n";
