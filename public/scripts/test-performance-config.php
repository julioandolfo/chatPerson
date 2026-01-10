<?php
/**
 * Script para testar se as configurações de performance estão sendo salvas
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\ConversationSettingsService;

echo "=== Teste de Configurações de Performance ===\n\n";

// Obter configurações atuais
$settings = ConversationSettingsService::getSettings();

echo "Configurações carregadas:\n";
echo "========================\n\n";

if (isset($settings['agent_performance_analysis'])) {
    $perf = $settings['agent_performance_analysis'];
    echo "✅ Seção 'agent_performance_analysis' encontrada!\n\n";
    
    echo "Enabled: " . (isset($perf['enabled']) && $perf['enabled'] ? 'SIM' : 'NÃO') . "\n";
    echo "Model: " . ($perf['model'] ?? 'não definido') . "\n";
    echo "Temperature: " . ($perf['temperature'] ?? 'não definido') . "\n";
    echo "Check Interval: " . ($perf['check_interval_hours'] ?? 'não definido') . " horas\n";
    echo "Min Agent Messages: " . ($perf['min_agent_messages'] ?? 'não definido') . "\n";
    echo "Cost Limit: $" . ($perf['cost_limit_per_day'] ?? 'não definido') . "/dia\n";
    
    echo "\nGamificação:\n";
    echo "  Enabled: " . (isset($perf['gamification']['enabled']) && $perf['gamification']['enabled'] ? 'SIM' : 'NÃO') . "\n";
    echo "  Auto Award Badges: " . (isset($perf['gamification']['auto_award_badges']) && $perf['gamification']['auto_award_badges'] ? 'SIM' : 'NÃO') . "\n";
    
    echo "\nCoaching:\n";
    echo "  Enabled: " . (isset($perf['coaching']['enabled']) && $perf['coaching']['enabled'] ? 'SIM' : 'NÃO') . "\n";
    echo "  Auto Create Goals: " . (isset($perf['coaching']['auto_create_goals']) && $perf['coaching']['auto_create_goals'] ? 'SIM' : 'NÃO') . "\n";
    
    echo "\nMelhores Práticas:\n";
    echo "  Enabled: " . (isset($perf['best_practices']['enabled']) && $perf['best_practices']['enabled'] ? 'SIM' : 'NÃO') . "\n";
    echo "  Auto Save: " . (isset($perf['best_practices']['auto_save']) && $perf['best_practices']['auto_save'] ? 'SIM' : 'NÃO') . "\n";
    
} else {
    echo "❌ Seção 'agent_performance_analysis' NÃO encontrada!\n";
    echo "\nEstrutura disponível:\n";
    print_r(array_keys($settings));
}

echo "\n\n=== Testando salvamento ===\n\n";

// Tentar salvar uma configuração de teste
$testSettings = $settings;
$testSettings['agent_performance_analysis'] = [
    'enabled' => true,
    'model' => 'gpt-4-turbo',
    'temperature' => 0.3,
    'check_interval_hours' => 6,
    'analyze_on_close' => true,
    'min_agent_messages' => 5,
    'min_conversation_duration' => 5,
    'cost_limit_per_day' => 10.00,
    'dimension_weights' => [
        'proactivity' => 1.0,
        'objection_handling' => 1.0,
        'rapport' => 1.0,
        'closing_techniques' => 1.0,
        'qualification' => 1.0,
        'clarity' => 1.0,
        'value_proposition' => 1.0,
        'response_time' => 1.0,
        'follow_up' => 1.0,
        'professionalism' => 1.0,
    ],
    'gamification' => [
        'enabled' => true,
        'auto_award_badges' => true,
    ],
    'coaching' => [
        'enabled' => true,
        'auto_create_goals' => true,
        'goal_threshold' => 3.5,
    ],
    'best_practices' => [
        'enabled' => true,
        'auto_save' => true,
        'min_score_threshold' => 4.5,
    ],
    'reports' => [
        'send_weekly_summary' => false,
        'send_monthly_ranking' => false,
    ],
];

echo "Salvando configuração de teste...\n";
if (ConversationSettingsService::saveSettings($testSettings)) {
    echo "✅ Salvo com sucesso!\n\n";
    
    // Recarregar e verificar
    echo "Recarregando configurações...\n";
    $reloaded = ConversationSettingsService::getSettings();
    
    if (isset($reloaded['agent_performance_analysis']['enabled']) && $reloaded['agent_performance_analysis']['enabled']) {
        echo "✅ Configuração 'enabled' persistiu corretamente!\n";
    } else {
        echo "❌ Configuração 'enabled' NÃO persistiu!\n";
        echo "Valor atual: " . var_export($reloaded['agent_performance_analysis']['enabled'] ?? null, true) . "\n";
    }
} else {
    echo "❌ Erro ao salvar!\n";
}

echo "\n=== Fim do teste ===\n";
