<?php
/**
 * Script para atualizar automaticamente as configurações de análise de sentimento
 * com valores mais sensatos
 */

// Mudar para diretório raiz do projeto
chdir(__DIR__ . '/../../');

// Autoloader
require_once __DIR__ . '/../../app/Helpers/autoload.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

use App\Services\ConversationSettingsService;

echo "========================================\n";
echo "ATUALIZAR CONFIGURAÇÕES DE SENTIMENTO\n";
echo "========================================\n\n";

// Obter configurações atuais
$settings = ConversationSettingsService::getSettings();
$sentiment = $settings['sentiment_analysis'];

echo "📊 CONFIGURAÇÕES ATUAIS:\n";
echo "-------------------\n";
echo "Habilitado: " . ($sentiment['enabled'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Mín. mensagens: " . ($sentiment['min_messages_to_analyze'] ?? 'N/A') . "\n";
echo "Intervalo: " . ($sentiment['check_interval_hours'] ?? 'N/A') . " horas\n";
echo "Idade máxima: " . ($sentiment['max_conversation_age_days'] ?? 'N/A') . " dias\n";
echo "Analisar a cada X: " . ($sentiment['analyze_on_message_count'] ?? 'N/A') . " mensagens\n\n";

// Novos valores recomendados
$newValues = [
    'min_messages_to_analyze' => 5,
    'analyze_on_message_count' => 100,  // Manter
    'check_interval_hours' => 10,       // Manter
    'max_conversation_age_days' => 3,   // Manter
];

echo "✅ NOVOS VALORES RECOMENDADOS:\n";
echo "-------------------\n";
echo "Mín. mensagens: " . $newValues['min_messages_to_analyze'] . "\n";
echo "Analisar a cada X: " . $newValues['analyze_on_message_count'] . " mensagens\n";
echo "Intervalo: " . $newValues['check_interval_hours'] . " horas\n";
echo "Idade máxima: " . $newValues['max_conversation_age_days'] . " dias\n\n";

// Perguntar confirmação
echo "⚠️ ATENÇÃO: Isso irá sobrescrever as configurações atuais!\n";
echo "Deseja continuar? (Digite 'SIM' para confirmar): ";

$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtoupper($line) !== 'SIM') {
    echo "\n❌ Operação cancelada pelo usuário.\n\n";
    exit(0);
}

// Atualizar configurações
$settings['sentiment_analysis'] = array_merge($sentiment, $newValues);

try {
    $success = ConversationSettingsService::saveSettings($settings);
    
    if ($success) {
        echo "\n✅ CONFIGURAÇÕES ATUALIZADAS COM SUCESSO!\n\n";
        
        echo "📊 NOVA CONFIGURAÇÃO:\n";
        echo "-------------------\n";
        echo "Mín. mensagens: " . $settings['sentiment_analysis']['min_messages_to_analyze'] . "\n";
        echo "Intervalo: " . $settings['sentiment_analysis']['check_interval_hours'] . " horas\n";
        echo "Idade máxima: " . $settings['sentiment_analysis']['max_conversation_age_days'] . " dias\n";
        echo "Analisar a cada X: " . $settings['sentiment_analysis']['analyze_on_message_count'] . " mensagens\n\n";
        
        // Verificar conversas elegíveis agora
        $sql = "SELECT COUNT(DISTINCT c.id) as total
                FROM conversations c
                WHERE c.status = 'open'
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact') >= ?";
        
        $result = \App\Helpers\Database::fetch($sql, [
            $settings['sentiment_analysis']['max_conversation_age_days'],
            $settings['sentiment_analysis']['min_messages_to_analyze']
        ]);
        
        $eligible = $result['total'] ?? 0;
        
        echo "🎉 CONVERSAS ELEGÍVEIS AGORA: {$eligible}\n\n";
        
        echo "🚀 PRÓXIMO PASSO:\n";
        echo "Execute: php public/scripts/analyze-sentiments.php\n\n";
        
    } else {
        echo "\n❌ ERRO ao salvar configurações!\n\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "========================================\n";
echo "CONCLUÍDO\n";
echo "========================================\n";
