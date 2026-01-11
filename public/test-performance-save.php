<?php
/**
 * Script de teste para verificar salvamento de configurações de performance
 */

header('Content-Type: text/plain; charset=utf-8');

// Bootstrap
require_once __DIR__ . '/../config/bootstrap.php';

use App\Services\ConversationSettingsService;

echo "=== TESTE DE CONFIGURAÇÕES DE PERFORMANCE ===\n\n";

try {
    // Buscar configurações atuais
    $settings = ConversationSettingsService::getSettings();
    $perfSettings = $settings['agent_performance_analysis'] ?? [];
    
    echo "📊 Configurações Atuais:\n";
    echo "------------------------\n";
    echo "Habilitado: " . ($perfSettings['enabled'] ? 'Sim' : 'Não') . "\n";
    echo "Modelo: " . ($perfSettings['model'] ?? 'N/A') . "\n";
    echo "Temperature: " . ($perfSettings['temperature'] ?? 'N/A') . "\n";
    echo "Intervalo (horas): " . ($perfSettings['check_interval_hours'] ?? 'N/A') . "\n";
    echo "Idade máxima (dias): " . ($perfSettings['max_conversation_age_days'] ?? 'N/A') . "\n";
    echo "Mín. mensagens totais: " . ($perfSettings['min_messages_to_analyze'] ?? 'N/A') . "\n";
    echo "Mín. mensagens agente: " . ($perfSettings['min_agent_messages'] ?? 'N/A') . "\n";
    echo "Apenas fechadas: " . ($perfSettings['analyze_closed_only'] ?? 'N/A') . "\n";
    echo "Limite diário (USD): $" . ($perfSettings['cost_limit_per_day'] ?? 'N/A') . "\n\n";
    
    echo "🎮 Gamificação:\n";
    echo "Habilitada: " . (($perfSettings['gamification']['enabled'] ?? false) ? 'Sim' : 'Não') . "\n\n";
    
    echo "🎯 Coaching:\n";
    echo "Habilitado: " . (($perfSettings['coaching']['enabled'] ?? false) ? 'Sim' : 'Não') . "\n";
    echo "Salvar melhores práticas: " . (($perfSettings['coaching']['save_best_practices'] ?? false) ? 'Sim' : 'Não') . "\n";
    echo "Nota mínima para prática: " . ($perfSettings['coaching']['min_score_for_best_practice'] ?? 'N/A') . "\n\n";
    
    echo "📊 Dimensões:\n";
    if (!empty($perfSettings['dimensions'])) {
        foreach ($perfSettings['dimensions'] as $key => $dim) {
            $enabled = $dim['enabled'] ?? false;
            $weight = $dim['weight'] ?? 1.0;
            echo "  - " . ucwords(str_replace('_', ' ', $key)) . ": " . 
                 ($enabled ? "✅ Habilitada" : "❌ Desabilitada") . 
                 " (Peso: {$weight})\n";
        }
    } else {
        echo "  (Nenhuma dimensão configurada)\n";
    }
    
    echo "\n✅ Configurações carregadas com sucesso!\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
