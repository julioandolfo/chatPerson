<?php
/**
 * Seed: Criar funcionalidades padrão do Assistente IA
 */

function seed_ai_assistant_features() {
    $features = [
        [
            'feature_key' => 'generate_response',
            'name' => 'Gerar Resposta',
            'description' => 'Gera sugestões de resposta baseadas no contexto da conversa',
            'icon' => 'ki-message-text',
            'enabled' => true,
            'auto_select_agent' => true,
            'settings' => json_encode([
                'generate_count' => 3,
                'include_context' => true,
                'max_context_messages' => 20,
                'include_contact_info' => true,
                'include_tags' => true,
                'tone_options' => ['professional', 'friendly', 'formal'],
                'default_tone' => 'professional'
            ]),
            'order_index' => 1
        ],
        [
            'feature_key' => 'summarize',
            'name' => 'Resumir Conversa',
            'description' => 'Cria um resumo estruturado da conversa com pontos principais',
            'icon' => 'ki-file-down',
            'enabled' => true,
            'auto_select_agent' => false,
            'settings' => json_encode([
                'summary_length' => 'medium',
                'include_key_points' => true,
                'include_action_items' => true,
                'include_sentiment' => true,
                'max_length' => 500
            ]),
            'order_index' => 2
        ],
        [
            'feature_key' => 'suggest_tags',
            'name' => 'Sugerir Tags',
            'description' => 'Sugere tags relevantes baseadas no conteúdo da conversa',
            'icon' => 'ki-tag',
            'enabled' => true,
            'auto_select_agent' => true,
            'settings' => json_encode([
                'max_tags' => 5,
                'confidence_threshold' => 0.7,
                'use_existing_tags' => true,
                'create_new_tags' => false
            ]),
            'order_index' => 3
        ],
        [
            'feature_key' => 'analyze_sentiment',
            'name' => 'Análise de Sentimento',
            'description' => 'Analisa o sentimento da conversa e detecta emoções',
            'icon' => 'ki-heart',
            'enabled' => true,
            'auto_select_agent' => true,
            'settings' => json_encode([
                'detect_emotions' => true,
                'track_changes' => true,
                'alert_negative' => true,
                'alert_positive' => false
            ]),
            'order_index' => 4
        ],
        [
            'feature_key' => 'translate',
            'name' => 'Traduzir Mensagens',
            'description' => 'Traduz mensagens entre idiomas mantendo o contexto',
            'icon' => 'ki-translate',
            'enabled' => true,
            'auto_select_agent' => true,
            'settings' => json_encode([
                'auto_detect_language' => true,
                'target_language' => 'pt-BR',
                'preserve_context' => true,
                'translate_attachments' => false
            ]),
            'order_index' => 5
        ],
        [
            'feature_key' => 'improve_grammar',
            'name' => 'Melhorar Gramática',
            'description' => 'Corrige e melhora a gramática e ortografia da mensagem',
            'icon' => 'ki-pencil',
            'enabled' => true,
            'auto_select_agent' => false,
            'settings' => json_encode([
                'fix_spelling' => true,
                'improve_clarity' => true,
                'suggest_synonyms' => false,
                'maintain_tone' => true
            ]),
            'order_index' => 6
        ],
        [
            'feature_key' => 'suggest_next_steps',
            'name' => 'Sugerir Próximos Passos',
            'description' => 'Sugere ações e próximos passos baseados no contexto',
            'icon' => 'ki-arrow-right',
            'enabled' => true,
            'auto_select_agent' => true,
            'settings' => json_encode([
                'max_suggestions' => 5,
                'include_automations' => true,
                'include_templates' => true,
                'prioritize_by_urgency' => true
            ]),
            'order_index' => 7
        ],
        [
            'feature_key' => 'extract_info',
            'name' => 'Extrair Informações',
            'description' => 'Extrai informações importantes da conversa (nome, email, telefone, etc)',
            'icon' => 'ki-information',
            'enabled' => true,
            'auto_select_agent' => false,
            'settings' => json_encode([
                'extract_contact_info' => true,
                'extract_dates' => true,
                'extract_numbers' => true,
                'extract_keywords' => true
            ]),
            'order_index' => 8
        ]
    ];

    $db = \App\Helpers\Database::getInstance();
    
    foreach ($features as $feature) {
        $sql = "INSERT INTO ai_assistant_features 
                (feature_key, name, description, icon, enabled, auto_select_agent, settings, order_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                icon = VALUES(icon),
                enabled = VALUES(enabled),
                auto_select_agent = VALUES(auto_select_agent),
                settings = VALUES(settings),
                order_index = VALUES(order_index)";
        
        $params = [
            $feature['feature_key'],
            $feature['name'],
            $feature['description'],
            $feature['icon'],
            $feature['enabled'] ? 1 : 0,
            $feature['auto_select_agent'] ? 1 : 0,
            $feature['settings'],
            $feature['order_index']
        ];
        
        try {
            $db->prepare($sql)->execute($params);
            echo "✅ Funcionalidade '{$feature['name']}' criada/atualizada!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao criar funcionalidade '{$feature['name']}': " . $e->getMessage() . "\n";
        }
    }
    
    echo "✅ Seed de funcionalidades do Assistente IA concluído!\n";
}

