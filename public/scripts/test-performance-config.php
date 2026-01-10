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
    
    echo "\n🎮 Gamificação:\n";
    echo "  Enabled: " . (isset($perf['gamification']['enabled']) && $perf['gamification']['enabled'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "  Auto Award Badges: " . (isset($perf['gamification']['auto_award_badges']) && $perf['gamification']['auto_award_badges'] ? '✅ SIM' : '❌ NÃO') . "\n";
    
    echo "\n🎯 Coaching:\n";
    echo "  Enabled: " . (isset($perf['coaching']['enabled']) && $perf['coaching']['enabled'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "  Auto Create Goals: " . (isset($perf['coaching']['auto_create_goals']) && $perf['coaching']['auto_create_goals'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "  Save Best Practices: " . (isset($perf['coaching']['save_best_practices']) && $perf['coaching']['save_best_practices'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "  Min Score: " . ($perf['coaching']['min_score_for_best_practice'] ?? 'não definido') . "\n";
    
    echo "\n📚 Melhores Práticas:\n";
    echo "  Enabled: " . (isset($perf['best_practices']['enabled']) && $perf['best_practices']['enabled'] ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "  Auto Save: " . (isset($perf['best_practices']['auto_save']) && $perf['best_practices']['auto_save'] ? '✅ SIM' : '❌ NÃO') . "\n";
    
    echo "\n📊 Dimensões:\n";
    if (isset($perf['dimensions']) && is_array($perf['dimensions'])) {
        echo "  Total: " . count($perf['dimensions']) . "\n";
        foreach ($perf['dimensions'] as $key => $dim) {
            $enabled = isset($dim['enabled']) && $dim['enabled'] ? '✅' : '❌';
            $weight = $dim['weight'] ?? 1.0;
            echo "  {$enabled} " . ucfirst(str_replace('_', ' ', $key)) . ": peso {$weight}\n";
        }
    } else {
        echo "  ❌ Nenhuma dimensão configurada\n";
    }
    
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
    'dimensions' => [
        'proactivity' => ['enabled' => true, 'weight' => 1.5],
        'objection_handling' => ['enabled' => true, 'weight' => 2.0],
        'rapport' => ['enabled' => true, 'weight' => 1.0],
        'closing_techniques' => ['enabled' => true, 'weight' => 2.0],
        'qualification' => ['enabled' => true, 'weight' => 1.0],
        'clarity' => ['enabled' => true, 'weight' => 1.0],
        'value_proposition' => ['enabled' => true, 'weight' => 1.5],
        'response_time' => ['enabled' => true, 'weight' => 1.0],
        'follow_up' => ['enabled' => true, 'weight' => 1.0],
        'professionalism' => ['enabled' => true, 'weight' => 1.0],
    ],
    'gamification' => [
        'enabled' => true,
        'auto_award_badges' => true,
    ],
    'coaching' => [
        'enabled' => true,
        'auto_create_goals' => true,
        'goal_threshold' => 3.5,
        'save_best_practices' => true,
        'min_score_for_best_practice' => 4.5,
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
    
    $perf = $reloaded['agent_performance_analysis'] ?? [];
    
    echo "\n📋 Verificando persistência:\n";
    
    $checks = [
        ['field' => 'enabled', 'path' => $perf['enabled'] ?? false, 'expected' => true, 'label' => 'Análise Habilitada'],
        ['field' => 'gamification.enabled', 'path' => $perf['gamification']['enabled'] ?? false, 'expected' => true, 'label' => '🎮 Gamificação'],
        ['field' => 'coaching.enabled', 'path' => $perf['coaching']['enabled'] ?? false, 'expected' => true, 'label' => '🎯 Coaching'],
        ['field' => 'coaching.save_best_practices', 'path' => $perf['coaching']['save_best_practices'] ?? false, 'expected' => true, 'label' => '📚 Save Best Practices'],
        ['field' => 'dimensions.proactivity.weight', 'path' => $perf['dimensions']['proactivity']['weight'] ?? 1.0, 'expected' => 1.5, 'label' => '🚀 Peso Proatividade'],
        ['field' => 'dimensions.objection_handling.weight', 'path' => $perf['dimensions']['objection_handling']['weight'] ?? 1.0, 'expected' => 2.0, 'label' => '💪 Peso Objeções'],
    ];
    
    $passed = 0;
    $failed = 0;
    
    foreach ($checks as $check) {
        if ($check['path'] == $check['expected']) {
            echo "  ✅ {$check['label']}: OK (valor: {$check['path']})\n";
            $passed++;
        } else {
            echo "  ❌ {$check['label']}: FALHOU (esperado: {$check['expected']}, obtido: {$check['path']})\n";
            $failed++;
        }
    }
    
    echo "\n📊 Resultado: {$passed} passou, {$failed} falhou\n";
    
    if ($failed === 0) {
        echo "🎉 TODOS OS TESTES PASSARAM!\n";
    } else {
        echo "⚠️ Alguns testes falharam. Verifique a implementação.\n";
    }
} else {
    echo "❌ Erro ao salvar!\n";
}

echo "\n=== Fim do teste ===\n";
