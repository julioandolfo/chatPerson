<?php
/**
 * Seed: Criar configurações de disponibilidade e horário comercial
 */

use App\Models\Setting;

function seed_availability_settings() {
    echo "🌐 Criando configurações de disponibilidade...\n";
    
    // Configurações de disponibilidade
    $availabilitySettings = [
        [
            'key' => 'availability.auto_online_on_login',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'availability',
            'label' => 'Marcar como online automaticamente ao fazer login',
            'description' => 'Quando habilitado, o agente será marcado como online automaticamente ao fazer login'
        ],
        [
            'key' => 'availability.auto_offline_on_logout',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'availability',
            'label' => 'Marcar como offline automaticamente ao fazer logout',
            'description' => 'Quando habilitado, o agente será marcado como offline automaticamente ao fazer logout'
        ],
        [
            'key' => 'availability.auto_away_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'availability',
            'label' => 'Habilitar mudança automática para Ausente',
            'description' => 'Quando habilitado, o agente será marcado como ausente após período de inatividade'
        ],
        [
            'key' => 'availability.away_timeout_minutes',
            'value' => '15',
            'type' => 'integer',
            'group' => 'availability',
            'label' => 'Minutos de inatividade para mudar para Ausente',
            'description' => 'Tempo em minutos sem atividade para mudar automaticamente para status "Ausente"'
        ],
        [
            'key' => 'availability.activity_tracking_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'availability',
            'label' => 'Rastrear atividade do usuário',
            'description' => 'Quando habilitado, o sistema rastreia atividade do usuário (mouse, teclado, etc)'
        ],
        [
            'key' => 'availability.heartbeat_interval_seconds',
            'value' => '30',
            'type' => 'integer',
            'group' => 'availability',
            'label' => 'Intervalo de heartbeat (segundos)',
            'description' => 'Intervalo em segundos para envio de heartbeat (WebSocket/Polling)'
        ],
        [
            'key' => 'availability.offline_timeout_minutes',
            'value' => '5',
            'type' => 'integer',
            'group' => 'availability',
            'label' => 'Timeout para offline (minutos)',
            'description' => 'Tempo em minutos sem heartbeat para marcar como offline'
        ],
        [
            'key' => 'availability.track_mouse_movement',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'availability',
            'label' => 'Rastrear movimento do mouse',
            'description' => 'Quando habilitado, movimento do mouse é considerado como atividade'
        ],
        [
            'key' => 'availability.track_keyboard',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'availability',
            'label' => 'Rastrear digitação',
            'description' => 'Quando habilitado, digitação é considerada como atividade'
        ],
        [
            'key' => 'availability.track_page_visibility',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'availability',
            'label' => 'Considerar visibilidade da aba',
            'description' => 'Quando habilitado, mudanças na visibilidade da aba são consideradas'
        ],
    ];

    foreach ($availabilitySettings as $setting) {
        Setting::set(
            $setting['key'],
            $setting['value'],
            $setting['type'],
            $setting['group']
        );
        
        // Atualizar label e description se já existir
        $existing = Setting::whereFirst('key', '=', $setting['key']);
        if ($existing) {
            Setting::update($existing['id'], [
                'label' => $setting['label'],
                'description' => $setting['description']
            ]);
        }
    }

    echo "✅ Configurações de disponibilidade criadas!\n";

    // Configurações de horário comercial
    echo "🕐 Criando configurações de horário comercial...\n";
    
    $businessHoursSettings = [
        [
            'key' => 'business_hours.enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'business_hours',
            'label' => 'Habilitar horário comercial',
            'description' => 'Quando habilitado, apenas o tempo dentro do horário comercial será considerado nos cálculos'
        ],
        [
            'key' => 'business_hours.timezone',
            'value' => 'America/Sao_Paulo',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Fuso horário',
            'description' => 'Fuso horário para cálculo do horário comercial'
        ],
        [
            'key' => 'business_hours.monday_start',
            'value' => '09:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Segunda-feira - Início',
            'description' => 'Horário de início do atendimento na segunda-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.monday_end',
            'value' => '18:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Segunda-feira - Fim',
            'description' => 'Horário de fim do atendimento na segunda-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.tuesday_start',
            'value' => '09:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Terça-feira - Início',
            'description' => 'Horário de início do atendimento na terça-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.tuesday_end',
            'value' => '18:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Terça-feira - Fim',
            'description' => 'Horário de fim do atendimento na terça-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.wednesday_start',
            'value' => '09:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Quarta-feira - Início',
            'description' => 'Horário de início do atendimento na quarta-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.wednesday_end',
            'value' => '18:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Quarta-feira - Fim',
            'description' => 'Horário de fim do atendimento na quarta-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.thursday_start',
            'value' => '09:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Quinta-feira - Início',
            'description' => 'Horário de início do atendimento na quinta-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.thursday_end',
            'value' => '18:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Quinta-feira - Fim',
            'description' => 'Horário de fim do atendimento na quinta-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.friday_start',
            'value' => '09:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Sexta-feira - Início',
            'description' => 'Horário de início do atendimento na sexta-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.friday_end',
            'value' => '18:00',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Sexta-feira - Fim',
            'description' => 'Horário de fim do atendimento na sexta-feira (formato HH:mm)'
        ],
        [
            'key' => 'business_hours.saturday_start',
            'value' => '',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Sábado - Início',
            'description' => 'Horário de início do atendimento no sábado (formato HH:mm, deixe vazio para não atender)'
        ],
        [
            'key' => 'business_hours.saturday_end',
            'value' => '',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Sábado - Fim',
            'description' => 'Horário de fim do atendimento no sábado (formato HH:mm, deixe vazio para não atender)'
        ],
        [
            'key' => 'business_hours.sunday_start',
            'value' => '',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Domingo - Início',
            'description' => 'Horário de início do atendimento no domingo (formato HH:mm, deixe vazio para não atender)'
        ],
        [
            'key' => 'business_hours.sunday_end',
            'value' => '',
            'type' => 'string',
            'group' => 'business_hours',
            'label' => 'Domingo - Fim',
            'description' => 'Horário de fim do atendimento no domingo (formato HH:mm, deixe vazio para não atender)'
        ],
    ];

    foreach ($businessHoursSettings as $setting) {
        Setting::set(
            $setting['key'],
            $setting['value'],
            $setting['type'],
            $setting['group']
        );
        
        // Atualizar label e description se já existir
        $existing = Setting::whereFirst('key', '=', $setting['key']);
        if ($existing) {
            Setting::update($existing['id'], [
                'label' => $setting['label'],
                'description' => $setting['description']
            ]);
        }
    }

    echo "✅ Configurações de horário comercial criadas!\n";
}

