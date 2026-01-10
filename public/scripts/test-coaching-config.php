#!/usr/bin/env php
<?php

/**
 * Testar Configurações de Coaching em Tempo Real
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\ConversationSettingsService;
use App\Helpers\Database;

echo "=== TESTE DE CONFIGURAÇÕES DE COACHING ===\n\n";

// 1. Verificar no banco de dados
echo "1. Verificando no banco de dados...\n";
$sql = "SELECT * FROM settings WHERE `key` = 'conversation_settings' LIMIT 1";
$result = Database::fetchOne($sql);

if ($result) {
    echo "✅ Registro encontrado no banco\n";
    echo "   ID: {$result['id']}\n";
    echo "   Key: {$result['key']}\n";
    echo "   Tamanho do valor: " . strlen($result['value']) . " bytes\n\n";
    
    // Decodificar JSON
    $settings = json_decode($result['value'], true);
    
    if (isset($settings['realtime_coaching'])) {
        echo "✅ Seção 'realtime_coaching' encontrada\n\n";
        echo "Configurações atuais:\n";
        echo "-------------------\n";
        
        $coaching = $settings['realtime_coaching'];
        
        echo "enabled: " . ($coaching['enabled'] ? 'true' : 'false') . "\n";
        echo "model: " . ($coaching['model'] ?? 'não definido') . "\n";
        echo "temperature: " . ($coaching['temperature'] ?? 'não definido') . "\n";
        echo "max_analyses_per_minute: " . ($coaching['max_analyses_per_minute'] ?? 'não definido') . "\n";
        echo "min_interval_between_analyses: " . ($coaching['min_interval_between_analyses'] ?? 'não definido') . "\n";
        echo "use_queue: " . ($coaching['use_queue'] ? 'true' : 'false') . "\n";
        echo "queue_processing_delay: " . ($coaching['queue_processing_delay'] ?? 'não definido') . "\n";
        echo "max_queue_size: " . ($coaching['max_queue_size'] ?? 'não definido') . "\n";
        echo "analyze_only_client_messages: " . ($coaching['analyze_only_client_messages'] ? 'true' : 'false') . "\n";
        echo "min_message_length: " . ($coaching['min_message_length'] ?? 'não definido') . "\n";
        echo "skip_if_agent_typing: " . ($coaching['skip_if_agent_typing'] ? 'true' : 'false') . "\n";
        echo "use_cache: " . ($coaching['use_cache'] ? 'true' : 'false') . "\n";
        echo "cache_ttl_minutes: " . ($coaching['cache_ttl_minutes'] ?? 'não definido') . "\n";
        echo "cache_similarity_threshold: " . ($coaching['cache_similarity_threshold'] ?? 'não definido') . "\n";
        echo "cost_limit_per_hour: " . ($coaching['cost_limit_per_hour'] ?? 'não definido') . "\n";
        echo "cost_limit_per_day: " . ($coaching['cost_limit_per_day'] ?? 'não definido') . "\n";
        echo "auto_show_hint: " . ($coaching['auto_show_hint'] ? 'true' : 'false') . "\n";
        echo "hint_display_duration: " . ($coaching['hint_display_duration'] ?? 'não definido') . "\n";
        echo "play_sound: " . ($coaching['play_sound'] ? 'true' : 'false') . "\n";
        
        echo "\nTipos de Hint:\n";
        if (isset($coaching['hint_types'])) {
            foreach ($coaching['hint_types'] as $type => $enabled) {
                $status = $enabled ? '✅' : '❌';
                echo "  {$status} {$type}\n";
            }
        } else {
            echo "  ❌ Nenhum tipo de hint configurado\n";
        }
        
    } else {
        echo "❌ Seção 'realtime_coaching' NÃO encontrada\n";
        echo "Seções disponíveis: " . implode(', ', array_keys($settings)) . "\n";
    }
} else {
    echo "❌ Nenhum registro encontrado no banco\n";
}

echo "\n";

// 2. Verificar via Service
echo "2. Verificando via ConversationSettingsService...\n";
$settings = ConversationSettingsService::getSettings();

if (isset($settings['realtime_coaching'])) {
    echo "✅ Seção 'realtime_coaching' encontrada via Service\n";
    echo "   enabled: " . ($settings['realtime_coaching']['enabled'] ? 'true' : 'false') . "\n";
} else {
    echo "❌ Seção 'realtime_coaching' NÃO encontrada via Service\n";
}

echo "\n=== FIM DO TESTE ===\n";
