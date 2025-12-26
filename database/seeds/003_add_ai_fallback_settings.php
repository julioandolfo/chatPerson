<?php
/**
 * Seed: Adicionar configurações de fallback de IA
 */

function seed_ai_fallback_settings() {
    $settings = [
        [
            'key' => 'ai_fallback_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'ai',
            'label' => 'Habilitar Fallback de IA',
            'description' => 'Monitorar e reprocessar conversas travadas onde a IA não respondeu'
        ],
        [
            'key' => 'ai_fallback_check_interval_minutes',
            'value' => '15',
            'type' => 'integer',
            'group' => 'ai',
            'label' => 'Intervalo de Verificação (minutos)',
            'description' => 'Frequência de verificação de conversas travadas (padrão: 15 minutos)'
        ],
        [
            'key' => 'ai_fallback_min_delay_minutes',
            'value' => '5',
            'type' => 'integer',
            'group' => 'ai',
            'label' => 'Delay Mínimo (minutos)',
            'description' => 'Tempo mínimo antes de considerar uma conversa como travada (padrão: 5 minutos)'
        ],
        [
            'key' => 'ai_fallback_max_delay_hours',
            'value' => '24',
            'type' => 'integer',
            'group' => 'ai',
            'label' => 'Delay Máximo (horas)',
            'description' => 'Tempo máximo para considerar uma conversa como travada (padrão: 24 horas)'
        ],
        [
            'key' => 'ai_fallback_max_retries',
            'value' => '3',
            'type' => 'integer',
            'group' => 'ai',
            'label' => 'Máximo de Tentativas',
            'description' => 'Número máximo de tentativas de reprocessamento antes de escalar (padrão: 3)'
        ],
        [
            'key' => 'ai_fallback_escalate_after_hours',
            'value' => '2',
            'type' => 'integer',
            'group' => 'ai',
            'label' => 'Escalar Após (horas)',
            'description' => 'Tempo após o qual escalar para humano mesmo sem exceder tentativas (padrão: 2 horas)'
        ],
        [
            'key' => 'ai_fallback_detect_closing_messages',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'ai',
            'label' => 'Detectar Mensagens de Encerramento',
            'description' => 'Ignorar mensagens de despedida/encerramento (padrão: sim)'
        ],
        [
            'key' => 'ai_fallback_use_ai_for_closing_detection',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'ai',
            'label' => 'Usar IA para Detecção de Encerramento',
            'description' => 'Usar OpenAI para detectar mensagens de encerramento com mais precisão (padrão: não)'
        ],
    ];
    
    foreach ($settings as $setting) {
        \App\Models\Setting::set(
            $setting['key'],
            $setting['value'],
            $setting['type'],
            $setting['group']
        );
        
        // Atualizar label e description se já existir
        $sql = "UPDATE settings SET 
                label = ?,
                description = ?
                WHERE `key` = ?";
        \App\Helpers\Database::execute($sql, [
            $setting['label'],
            $setting['description'],
            $setting['key']
        ]);
    }
    
    echo "✅ Configurações de fallback de IA adicionadas com sucesso!\n";
}

