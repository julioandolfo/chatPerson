<?php
/**
 * Service ConversationSettingsService
 * Lógica de negócio para configurações avançadas de conversas
 */

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\Department;
use App\Models\Funnel;
use App\Models\SLARule;
use App\Helpers\Database;
use App\Helpers\WorkingHoursCalculator;

class ConversationSettingsService
{
    const SETTINGS_KEY = 'conversation_settings';
    
    // =========================================================================
    // FUNÇÕES AUXILIARES PARA SLA
    // =========================================================================
    
    /**
     * Verificar se cliente respondeu ao bot
     * Retorna true se existe mensagem do cliente APÓS uma mensagem do bot/agente
     */
    public static function hasClientRespondedToBot(int $conversationId): bool
    {
        $lastAgentMessage = Database::fetch(
            "SELECT created_at 
             FROM messages 
             WHERE conversation_id = ? 
             AND sender_type = 'agent'
             ORDER BY created_at DESC 
             LIMIT 1",
            [$conversationId]
        );
        
        if (!$lastAgentMessage) {
            $hasContact = Database::fetch(
                "SELECT 1 FROM messages WHERE conversation_id = ? AND sender_type = 'contact' LIMIT 1",
                [$conversationId]
            );
            return (bool)$hasContact;
        }
        
        $clientAfterAgent = Database::fetch(
            "SELECT 1 
             FROM messages 
             WHERE conversation_id = ? 
             AND sender_type = 'contact'
             AND created_at > ?
             LIMIT 1",
            [$conversationId, $lastAgentMessage['created_at']]
        );
        
        return (bool)$clientAfterAgent;
    }
    
    // =========================================================================

    /**
     * Obter todas as configurações
     * ✅ COM CACHE de 5 minutos para evitar SELECT repetido
     */
    public static function getSettings(): array
    {
        // ✅ Cache de 5 minutos (300 segundos)
        $cacheKey = 'conversation_settings_config';
        
        return \App\Helpers\Cache::remember($cacheKey, 300, function() {
            $setting = Setting::whereFirst('key', '=', self::SETTINGS_KEY);
            
            if (!$setting) {
                return self::getDefaultSettings();
            }
            
            $settings = json_decode($setting['value'], true);
            if (!is_array($settings)) {
                return self::getDefaultSettings();
            }
            
            // Mesclar com padrões para garantir que todas as chaves existam
            return array_merge(self::getDefaultSettings(), $settings);
        });
    }

    /**
     * Salvar configurações
     */
    public static function saveSettings(array $settings): bool
    {
        // Usa upsert simples (insert on duplicate) para reduzir risco de falha quando nada muda
        // Setting::set já serializa o valor como JSON quando o tipo é 'json'
        // e faz ON DUPLICATE KEY UPDATE internamente.
        Setting::set(
            self::SETTINGS_KEY,
            $settings,            // array será serializado como json
            'json',
            'conversations'
        );
        
        // ✅ Limpar cache após salvar
        \App\Helpers\Cache::forget('conversation_settings_config');
        
        return true;
    }

    /**
     * Obter configurações padrão
     */
    public static function getDefaultSettings(): array
    {
        return [
            // Limites globais
            'global_limits' => [
                'max_conversations_per_agent' => null, // null = ilimitado
                'max_conversations_per_department' => null,
                'max_conversations_per_funnel' => null,
                'max_conversations_per_stage' => null,
            ],
            
            // SLA
            'sla' => [
                'first_response_time' => 15, // minutos
                'resolution_time' => 60, // minutos
                'enable_sla_monitoring' => true,
                'enable_resolution_sla' => true, // permitir desativar SLA de resolução
                'ongoing_response_time' => 15, // SLA para respostas durante a conversa
                'working_hours_enabled' => false,
                'working_hours_start' => '08:00',
                'working_hours_end' => '18:00',
                'auto_reassign_on_sla_breach' => true,
                'reassign_after_minutes' => 30, // minutos após SLA
                'message_delay_enabled' => true, // habilitar delay mínimo para iniciar SLA
                'message_delay_minutes' => 1, // delay mínimo para iniciar SLA (evita mensagens automáticas/despedidas)
            ],
            
            // Distribuição
            'distribution' => [
                'method' => 'round_robin', // round_robin, by_load, by_specialty, by_performance, percentage
                'enable_auto_assignment' => true,
                'assign_to_ai_agent' => false, // Se deve considerar agentes de IA
                'consider_availability' => true,
                'consider_max_conversations' => true,
            ],
            
            // Distribuição por porcentagem
            'percentage_distribution' => [
                'enabled' => false,
                'rules' => [] // [{agent_id: 1, percentage: 30}, {department_id: 2, percentage: 70}]
            ],
            
            // Reatribuição
            'reassignment' => [
                'enable_auto_reassignment' => true,
                'reassign_on_inactivity_minutes' => 60, // minutos sem resposta
                'reassign_on_sla_breach' => true,
                'reassign_on_agent_offline' => true,
                'max_reassignments' => 3, // máximo de reatribuições por conversa
            ],
            
            // Agentes do Contato
            'contact_agents' => [
                'auto_set_primary_agent_on_first_assignment' => true, // Definir agente principal automaticamente na primeira atribuição
                'auto_assign_on_reopen' => true, // Atribuir automaticamente ao agente principal quando conversa fechada for reaberta
            ],
            
            // Priorização
            'prioritization' => [
                'enabled' => true,
                'rules' => [
                    // [{field: 'channel', value: 'whatsapp', priority: 10}, ...]
                ]
            ],
            
            // Filas
            'queues' => [
                'enabled' => false,
                'rules' => [] // [{name: 'VIP', conditions: [...], priority: 10}]
            ],
            
            // Balanceamento
            'load_balancing' => [
                'enabled' => true,
                'method' => 'even', // even, weighted
                'consider_performance' => false,
            ],
            
            // Configurações por horário
            'time_based' => [
                'enabled' => false,
                'rules' => [] // [{time_from: '09:00', time_to: '18:00', settings: {...}}]
            ],
            
            // Configurações por canal
            'channel_based' => [
                'enabled' => false,
                'rules' => [] // [{channel: 'whatsapp', settings: {...}}]
            ],
            
            // Análise de Sentimento
            'sentiment_analysis' => [
                'enabled' => false,
                'model' => 'gpt-3.5-turbo',
                'temperature' => 0.3,
                'check_interval_hours' => 5,
                'max_conversation_age_days' => 30,
                'analyze_on_new_message' => true,
                'analyze_on_message_count' => 5,
                'min_messages_to_analyze' => 3,
                'analyze_last_messages' => null, // null = toda conversa, número = últimas X mensagens
                'include_emotions' => true,
                'include_urgency' => true,
                'auto_tag_negative' => false,
                'negative_tag_id' => null,
                'cost_limit_per_day' => 5.00,
            ],
            
            // Transcrição de Áudio (OpenAI Whisper)
            'audio_transcription' => [
                'enabled' => false, // Habilitar/desabilitar transcrição automática
                'auto_transcribe' => true, // Transcrever automaticamente quando áudio chega
                'only_for_ai_agents' => true, // Só transcrever se conversa tem agente de IA atribuído
                'language' => 'pt', // Código ISO 639-1 (pt, en, es, etc)
                'model' => 'whisper-1', // Modelo Whisper (sempre whisper-1 por enquanto)
                'update_message_content' => true, // Atualizar conteúdo da mensagem com texto transcrito
                'max_file_size_mb' => 25, // Limite de tamanho do arquivo (25MB é limite da OpenAI)
                'cost_limit_per_day' => 10.00, // Limite de custo diário em USD ($0.006/minuto)
                'show_transcription_in_chat' => true, // ✅ NOVO: Exibir transcrição abaixo do áudio no chat
            ],
            
            // Text-to-Speech (Geração de Áudio)
            'text_to_speech' => [
                'enabled' => false, // Habilitar/desabilitar geração de áudio
                'provider' => 'openai', // 'openai' ou 'elevenlabs'
                'auto_generate_audio' => false, // Gerar áudio automaticamente para respostas da IA
                'only_for_ai_agents' => true, // Só gerar áudio se for resposta de agente de IA
                'send_mode' => 'intelligent', // 'text_only', 'audio_only', 'both', 'intelligent', 'adaptive' - Modo de envio
                'voice_id' => null, // ID da voz (específico por provider)
                'model' => null, // Modelo (específico por provider, null = usar padrão)
                'language' => 'pt', // Idioma
                'speed' => 1.0, // Velocidade (0.25 a 4.0)
                'stability' => 0.5, // Estabilidade (ElevenLabs: 0.0 a 1.0)
                'similarity_boost' => 0.75, // Similaridade (ElevenLabs: 0.0 a 1.0)
                'output_format' => 'mp3', // mp3, opus, ogg, pcm
                'convert_to_whatsapp_format' => true, // Converter para formato compatível com WhatsApp
                'cost_limit_per_day' => 5.00, // Limite de custo diário em USD
                
                // Regras Inteligentes (modo 'intelligent' ou 'adaptive')
                'intelligent_rules' => [
                    'adaptive_mode' => false, // ✅ NOVO: Modo adaptativo (espelha cliente)
                    'first_message_always_text' => true, // ✅ NOVO: Primeira mensagem sempre em texto
                    'custom_behavior_prompt' => '', // ✅ NOVO: Prompt customizado
                    
                    'use_text_length' => true, // Considerar tamanho do texto
                    'max_chars_for_audio' => 500, // Máximo de caracteres para enviar como áudio
                    'min_chars_for_text' => 1000, // Mínimo de caracteres para forçar texto
                    
                    'use_content_type' => true, // Considerar tipo de conteúdo
                    'force_text_if_urls' => true, // Forçar texto se contém URLs
                    'force_text_if_code' => true, // Forçar texto se contém código/formatação
                    'force_text_if_numbers' => false, // Forçar texto se contém muitos números
                    'max_numbers_for_audio' => 5, // Máximo de números para enviar como áudio
                    
                    'use_complexity' => true, // Considerar complexidade
                    'force_text_if_complex' => true, // Forçar texto se muito complexo
                    'complexity_keywords' => ['instrução', 'passo a passo', 'tutorial', 'configuração', 'instalar', 'configurar', 'ajustar'],
                    
                    'use_emojis' => true, // Considerar emojis
                    'max_emojis_for_audio' => 3, // Máximo de emojis para enviar como áudio
                    
                    'use_time' => false, // Considerar horário
                    'audio_hours_start' => 8, // Horário início para preferir áudio
                    'audio_hours_end' => 20, // Horário fim para preferir áudio
                    'timezone' => 'America/Sao_Paulo', // Timezone
                    
                    'use_conversation_history' => false, // Considerar histórico da conversa
                    'prefer_audio_if_client_sent_audio' => true, // Preferir áudio se cliente enviou áudio
                    'prefer_text_if_client_sent_text' => false, // Preferir texto se cliente enviou texto
                    
                    'custom_behavior_prompt' => '', // ✅ NOVO: Prompt customizável (em desenvolvimento)
                    
                    'default_mode' => 'audio_only', // Modo padrão quando não há regras aplicáveis
                ],
            ],
            
            // Análise de Performance de Vendedores
            'realtime_coaching' => [
                'enabled' => false,
                'model' => 'gpt-3.5-turbo', // Modelo para coaching (mais rápido e barato)
                'temperature' => 0.5,
                
                // ⚡ Rate Limiting (Controle de análises)
                'max_analyses_per_minute' => 10, // Máximo 10 análises por minuto
                'min_interval_between_analyses' => 10, // Mínimo 10 segundos entre análises do mesmo agente
                
                // 📋 Fila e Processamento
                'use_queue' => true, // Usar fila (recomendado para alto volume)
                'queue_processing_delay' => 3, // Delay de 3 segundos antes de processar (debouncing)
                'max_queue_size' => 100, // Máximo 100 itens na fila por vez
                
                // 🎯 Filtros (Quando analisar)
                'analyze_only_client_messages' => true, // Só mensagens do cliente
                'min_message_length' => 10, // Mínimo 10 caracteres
                'skip_if_agent_typing' => true, // Pular se agente está digitando
                
                // 💾 Cache (Evitar análises duplicadas)
                'use_cache' => true,
                'cache_ttl_minutes' => 60, // Cache válido por 60 minutos
                'cache_similarity_threshold' => 0.85, // 85% similar = usa cache
                
                // 💰 Custo e Limites
                'cost_limit_per_hour' => 1.00, // Máx $1/hora
                'cost_limit_per_day' => 10.00, // Máx $10/dia
                
                // 🎯 Tipos de Dica (Quais situações detectar)
                'hint_types' => [
                    'objection' => true, // Detectar objeções
                    'opportunity' => true, // Detectar oportunidades
                    'question' => true, // Pergunta importante
                    'negative_sentiment' => true, // Cliente insatisfeito
                    'buying_signal' => true, // Sinais de compra
                    'closing_opportunity' => true, // Momento de fechar
                    'escalation_needed' => true, // Precisa escalar
                ],
                
                // 🎨 Apresentação
                'auto_show_hint' => true, // Mostrar automaticamente
                'hint_display_duration' => 30, // Mostrar por 30 segundos
                'play_sound' => false, // Tocar som ao receber dica
            ],
            
            'agent_performance_analysis' => [
                'enabled' => false,
                'model' => 'gpt-4-turbo', // gpt-4o, gpt-4-turbo, gpt-4, gpt-3.5-turbo
                'temperature' => 0.3,
                'check_interval_hours' => 24, // Intervalo para análise automática
                'max_conversation_age_days' => 7, // Idade máxima da conversa para analisar
                'min_messages_to_analyze' => 5, // Mínimo de mensagens totais
                'min_agent_messages' => 3, // Mínimo de mensagens do agente
                'analyze_closed_only' => true, // Analisar apenas conversas fechadas
                'cost_limit_per_day' => 10.00, // Limite de custo diário em USD
                
                // Dimensões ativas (podem ser habilitadas/desabilitadas individualmente)
                'dimensions' => [
                    'proactivity' => ['enabled' => true, 'weight' => 1.0],
                    'objection_handling' => ['enabled' => true, 'weight' => 1.5],
                    'rapport' => ['enabled' => true, 'weight' => 1.0],
                    'closing_techniques' => ['enabled' => true, 'weight' => 1.5],
                    'qualification' => ['enabled' => true, 'weight' => 1.2],
                    'clarity' => ['enabled' => true, 'weight' => 1.0],
                    'value_proposition' => ['enabled' => true, 'weight' => 1.3],
                    'response_time' => ['enabled' => true, 'weight' => 0.8],
                    'follow_up' => ['enabled' => true, 'weight' => 1.0],
                    'professionalism' => ['enabled' => true, 'weight' => 1.0]
                ],
                
                // Filtros (quais conversas analisar)
                'filters' => [
                    'only_sales_funnels' => false, // Analisar apenas conversas de funis de vendas
                    'funnel_ids' => [], // IDs específicos de funis (vazio = todos)
                    'only_sales_stages' => [], // Estágios específicos (ex: ['negociacao', 'proposta'])
                    'exclude_agents' => [], // IDs de agentes a excluir
                    'only_agents' => [], // IDs específicos de agentes (vazio = todos)
                    'min_conversation_value' => 0, // Valor mínimo da conversa
                    'tags_to_include' => [], // Tags que devem estar presentes
                    'tags_to_exclude' => [] // Tags que NÃO devem estar presentes
                ],
                
                // Relatórios
                'reports' => [
                    'generate_individual_report' => true, // Relatório de cada conversa
                    'generate_agent_ranking' => true, // Ranking de agentes
                    'generate_team_average' => true, // Média do time
                    'send_to_agent' => false, // Enviar análise para o próprio agente
                    'send_to_supervisor' => true, // Enviar para supervisor
                    'auto_tag_low_performance' => true, // Adicionar tag em conversas com baixa performance
                    'low_performance_threshold' => 2.5 // Nota considerada baixa
                ],
                
                // Gamificação
                'gamification' => [
                    'enabled' => true,
                    'award_badges' => true, // Premiar badges automaticamente
                    'show_ranking' => true, // Exibir ranking público
                    'celebrate_achievements' => true // Comemorar conquistas
                ],
                
                // Coaching
                'coaching' => [
                    'enabled' => true,
                    'auto_create_goals' => true, // Criar metas automaticamente
                    'auto_send_feedback' => false, // Enviar feedback automaticamente
                    'save_best_practices' => true, // Salvar melhores práticas
                    'min_score_for_best_practice' => 4.5 // Nota mínima para melhor prática
                ]
            ],
        ];
    }

    /**
     * Obter limite máximo de conversas para um agente
     */
    public static function getMaxConversationsForAgent(int $agentId): ?int
    {
        $settings = self::getSettings();
        $agent = User::find($agentId);
        
        if (!$agent) {
            return null;
        }
        
        // Primeiro verificar limite individual do agente
        if ($agent['max_conversations'] !== null) {
            return (int)$agent['max_conversations'];
        }
        
        // Depois verificar limite global
        return $settings['global_limits']['max_conversations_per_agent'];
    }

    /**
     * Obter limite máximo de conversas para um setor
     */
    public static function getMaxConversationsForDepartment(int $departmentId): ?int
    {
        $settings = self::getSettings();
        return $settings['global_limits']['max_conversations_per_department'];
    }

    /**
     * Obter limite máximo de conversas para um funil
     */
    public static function getMaxConversationsForFunnel(int $funnelId): ?int
    {
        $settings = self::getSettings();
        return $settings['global_limits']['max_conversations_per_funnel'];
    }

    /**
     * Obter limite máximo de conversas para um estágio
     */
    public static function getMaxConversationsForStage(int $stageId): ?int
    {
        $settings = self::getSettings();
        return $settings['global_limits']['max_conversations_per_stage'];
    }

    /**
     * Verificar se pode atribuir conversa a agente considerando limites
     */
    public static function canAssignToAgent(int $agentId, ?int $departmentId = null, ?int $funnelId = null, ?int $stageId = null): bool
    {
        $agent = User::find($agentId);
        if (!$agent || $agent['status'] !== 'active') {
            return false;
        }
        
        // Verificar disponibilidade
        if ($agent['availability_status'] !== 'online') {
            return false;
        }
        
        // Verificar se está habilitado para receber da fila
        // Default é true se o campo não existir ou for NULL
        if (($agent['queue_enabled'] ?? 1) != 1) {
            return false;
        }
        
        // Verificar limite do agente
        $maxAgent = self::getMaxConversationsForAgent($agentId);
        if ($maxAgent !== null && $agent['current_conversations'] >= $maxAgent) {
            return false;
        }
        
        // Verificar limite do setor (se fornecido)
        if ($departmentId !== null) {
            $maxDept = self::getMaxConversationsForDepartment($departmentId);
            if ($maxDept !== null) {
                $deptConversations = self::getCurrentConversationsForDepartment($departmentId);
                if ($deptConversations >= $maxDept) {
                    return false;
                }
            }
        }
        
        // Verificar limite do funil (se fornecido)
        if ($funnelId !== null) {
            $maxFunnel = self::getMaxConversationsForFunnel($funnelId);
            if ($maxFunnel !== null) {
                $funnelConversations = self::getCurrentConversationsForFunnel($funnelId);
                if ($funnelConversations >= $maxFunnel) {
                    return false;
                }
            }
        }
        
        // Verificar limite do estágio (se fornecido)
        if ($stageId !== null) {
            $maxStage = self::getMaxConversationsForStage($stageId);
            if ($maxStage !== null) {
                $stageConversations = self::getCurrentConversationsForStage($stageId);
                if ($stageConversations >= $maxStage) {
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Obter número atual de conversas de um setor
     */
    public static function getCurrentConversationsForDepartment(int $departmentId): int
    {
        $sql = "SELECT COUNT(*) as total 
                FROM conversations c
                INNER JOIN users u ON c.agent_id = u.id
                INNER JOIN agent_departments ad ON u.id = ad.user_id
                WHERE ad.department_id = ? AND c.status IN ('open', 'pending')";
        $result = Database::fetch($sql, [$departmentId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Obter número atual de conversas de um funil
     */
    public static function getCurrentConversationsForFunnel(int $funnelId): int
    {
        $sql = "SELECT COUNT(*) as total 
                FROM conversations c
                WHERE c.funnel_id = ? AND c.status IN ('open', 'pending')";
        $result = Database::fetch($sql, [$funnelId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Obter número atual de conversas de um estágio
     */
    public static function getCurrentConversationsForStage(int $stageId): int
    {
        $sql = "SELECT COUNT(*) as total 
                FROM conversations c
                WHERE c.funnel_stage_id = ? AND c.status IN ('open', 'pending')";
        $result = Database::fetch($sql, [$stageId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Distribuir conversa automaticamente usando método configurado
     * ATUALIZADO: Agora aceita excludeAgentId para evitar reatribuir para o mesmo agente
     */
    public static function autoAssignConversation(
        int $conversationId, 
        ?int $departmentId = null, 
        ?int $funnelId = null, 
        ?int $stageId = null,
        ?int $excludeAgentId = null
    ): ?int
    {
        // ✅ PRIORIDADE 1: Verificar se contato tem Agente Principal
        // Isso garante que mesmo em automações, o agente do contato é respeitado
        try {
            $conversation = \App\Models\Conversation::find($conversationId);
            if ($conversation && !empty($conversation['contact_id'])) {
                $contactAgentId = \App\Services\ContactAgentService::shouldAutoAssignOnConversation(
                    $conversation['contact_id'],
                    $conversationId
                );
                
                if ($contactAgentId && $contactAgentId != $excludeAgentId) {
                    \App\Helpers\Logger::debug(
                        "autoAssignConversation: Contato tem Agente Principal (#{$contactAgentId}). Priorizando sobre automação.",
                        'conversas.log'
                    );
                    return $contactAgentId;
                }
            }
        } catch (\Exception $e) {
            \App\Helpers\Logger::error(
                "autoAssignConversation: Erro ao verificar agente do contato: " . $e->getMessage(),
                'conversas.log'
            );
        }
        
        // ✅ PRIORIDADE 2: Se não tem agente do contato, usar distribuição configurada
        $settings = self::getSettings();
        
        if (!$settings['distribution']['enable_auto_assignment']) {
            return null;
        }
        
        $method = $settings['distribution']['method'];
        $includeAI = $settings['distribution']['assign_to_ai_agent'] ?? false;
        
        switch ($method) {
            case 'round_robin':
                return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI, true, true, $excludeAgentId);
            case 'by_load':
                return self::assignByLoad($departmentId, $funnelId, $stageId, $includeAI, true, true, $excludeAgentId);
            case 'by_specialty':
                return self::assignBySpecialty($departmentId, $funnelId, $stageId, $includeAI, $excludeAgentId);
            case 'by_performance':
                return self::assignByPerformance($departmentId, $funnelId, $stageId, $includeAI, true, true, $excludeAgentId);
            case 'percentage':
                return self::assignByPercentage($departmentId, $funnelId, $stageId, $includeAI, $excludeAgentId);
            default:
                return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI, true, true, $excludeAgentId);
        }
    }

    /**
     * Distribuição Round-Robin
     * ATUALIZADO: Agora aceita excludeAgentId
     */
    public static function assignRoundRobin(
        ?int $departmentId = null, 
        ?int $funnelId = null, 
        ?int $stageId = null, 
        bool $includeAI = false,
        bool $considerAvailability = true,
        bool $considerMaxConversations = true,
        ?int $excludeAgentId = null
    ): ?int
    {
        $agents = self::getAvailableAgents($departmentId, $funnelId, $stageId, $includeAI, $considerAvailability, $considerMaxConversations);
        
        // Filtrar agente excluído
        if ($excludeAgentId !== null) {
            $agents = array_filter($agents, function($agent) use ($excludeAgentId) {
                return ($agent['id'] ?? null) != $excludeAgentId;
            });
        }
        
        if (empty($agents)) {
            return null;
        }
        
        // Ordenar por última atribuição (mais antiga primeiro)
        usort($agents, function($a, $b) {
            $aTime = strtotime($a['last_assignment_at'] ?? '1970-01-01');
            $bTime = strtotime($b['last_assignment_at'] ?? '1970-01-01');
            return $aTime <=> $bTime;
        });
        
        $selectedAgent = $agents[0] ?? null;
        if (!$selectedAgent) {
            return null;
        }
        
        // Se for agente de IA, retornar ID especial
        if (($selectedAgent['agent_type'] ?? 'human') === 'ai') {
            return -1 * ($selectedAgent['ai_agent_id'] ?? 0); // Negativo para identificar como IA
        }
        
        return $selectedAgent['id'] ?? null;
    }

    /**
     * Distribuição por carga (menor carga primeiro)
     * ATUALIZADO: Agora aceita excludeAgentId
     */
    public static function assignByLoad(
        ?int $departmentId = null, 
        ?int $funnelId = null, 
        ?int $stageId = null, 
        bool $includeAI = false,
        bool $considerAvailability = true,
        bool $considerMaxConversations = true,
        ?int $excludeAgentId = null
    ): ?int
    {
        $agents = self::getAvailableAgents($departmentId, $funnelId, $stageId, $includeAI, $considerAvailability, $considerMaxConversations);
        
        // Filtrar agente excluído
        if ($excludeAgentId !== null) {
            $agents = array_filter($agents, function($agent) use ($excludeAgentId) {
                return ($agent['id'] ?? null) != $excludeAgentId;
            });
        }
        
        if (empty($agents)) {
            return null;
        }
        
        // Ordenar por carga atual (menor primeiro)
        usort($agents, function($a, $b) {
            $aLoad = $a['current_conversations'] ?? 0;
            $bLoad = $b['current_conversations'] ?? 0;
            return $aLoad <=> $bLoad;
        });
        
        $selectedAgent = $agents[0] ?? null;
        if (!$selectedAgent) {
            return null;
        }
        
        // Se for agente de IA, retornar ID especial
        if (($selectedAgent['agent_type'] ?? 'human') === 'ai') {
            return -1 * ($selectedAgent['ai_agent_id'] ?? 0);
        }
        
        return $selectedAgent['id'] ?? null;
    }

    /**
     * Distribuição por especialidade (simplificado - pode ser expandido)
     * ATUALIZADO: Agora aceita excludeAgentId
     */
    public static function assignBySpecialty(
        ?int $departmentId = null, 
        ?int $funnelId = null, 
        ?int $stageId = null, 
        bool $includeAI = false,
        ?int $excludeAgentId = null
    ): ?int
    {
        // Por enquanto, usar round-robin dentro do setor
        return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI, true, true, $excludeAgentId);
    }

    /**
     * Distribuição por performance (melhor performance primeiro)
     * ATUALIZADO: Agora aceita excludeAgentId
     */
    public static function assignByPerformance(
        ?int $departmentId = null, 
        ?int $funnelId = null, 
        ?int $stageId = null, 
        bool $includeAI = false,
        bool $considerAvailability = true,
        bool $considerMaxConversations = true,
        ?int $excludeAgentId = null
    ): ?int
    {
        $agents = self::getAvailableAgents($departmentId, $funnelId, $stageId, $includeAI, $considerAvailability, $considerMaxConversations);
        
        // Filtrar agente excluído
        if ($excludeAgentId !== null) {
            $agents = array_filter($agents, function($agent) use ($excludeAgentId) {
                return ($agent['id'] ?? null) != $excludeAgentId;
            });
        }
        
        if (empty($agents)) {
            return null;
        }
        
        // Obter performance de cada agente
        foreach ($agents as &$agent) {
            if (($agent['agent_type'] ?? 'human') === 'ai') {
                // Para agentes de IA, usar estatísticas de conversas de IA
                $stats = \App\Models\AIConversation::getAgentStats($agent['ai_agent_id'] ?? 0);
                $agent['performance_score'] = $stats['completed_conversations'] ?? 0;
            } else {
                $performance = \App\Services\AgentPerformanceService::getAgentPerformance($agent['id']);
                $agent['performance_score'] = $performance['resolution_rate'] ?? 0;
            }
        }
        
        // Ordenar por performance (maior primeiro)
        usort($agents, function($a, $b) {
            return ($b['performance_score'] ?? 0) <=> ($a['performance_score'] ?? 0);
        });
        
        $selectedAgent = $agents[0] ?? null;
        if (!$selectedAgent) {
            return null;
        }
        
        // Se for agente de IA, retornar ID especial
        if (($selectedAgent['agent_type'] ?? 'human') === 'ai') {
            return -1 * ($selectedAgent['ai_agent_id'] ?? 0);
        }
        
        return $selectedAgent['id'] ?? null;
    }

    /**
     * Distribuição por porcentagem
     * ATUALIZADO: Agora aceita excludeAgentId
     */
    public static function assignByPercentage(
        ?int $departmentId = null, 
        ?int $funnelId = null, 
        ?int $stageId = null, 
        bool $includeAI = false,
        ?int $excludeAgentId = null
    ): ?int
    {
        $settings = self::getSettings();
        
        if (!$settings['percentage_distribution']['enabled']) {
            return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI, true, true, $excludeAgentId);
        }
        
        $rules = $settings['percentage_distribution']['rules'] ?? [];
        
        // Filtrar agente excluído das regras
        if ($excludeAgentId !== null) {
            $rules = array_filter($rules, function($rule) use ($excludeAgentId) {
                return ($rule['agent_id'] ?? null) != $excludeAgentId;
            });
        }
        
        // Calcular probabilidades e escolher agente aleatoriamente baseado nas porcentagens
        $totalPercentage = array_sum(array_column($rules, 'percentage'));
        if ($totalPercentage <= 0) {
            return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI);
        }
        
        $random = mt_rand(1, 100);
        $cumulative = 0;
        
        foreach ($rules as $rule) {
            $cumulative += ($rule['percentage'] / $totalPercentage) * 100;
            if ($random <= $cumulative) {
                if (isset($rule['agent_id'])) {
                    $agentId = (int)$rule['agent_id'];
                    if (self::canAssignToAgent($agentId, $departmentId, $funnelId, $stageId)) {
                        return $agentId;
                    }
                } elseif (isset($rule['department_id'])) {
                    // Escolher agente aleatório do setor
                    return self::assignRoundRobin((int)$rule['department_id'], $funnelId, $stageId, $includeAI);
                }
            }
        }
        
        // Fallback para round-robin
        return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI);
    }

    /**
     * Obter agentes disponíveis para atribuição (humanos e IA)
     */
    private static function getAvailableAgents(
        ?int $departmentId = null, 
        ?int $funnelId = null, 
        ?int $stageId = null, 
        bool $includeAI = false,
        bool $considerAvailability = true,
        bool $considerMaxConversations = true
    ): array
    {
        $settings = self::getSettings();
        $agents = [];
        
        // Agentes humanos
        $sql = "SELECT u.id, u.name, u.current_conversations, u.max_conversations, u.availability_status, u.queue_enabled,
                       MAX(c.updated_at) as last_assignment_at, 'human' as agent_type
                FROM users u
                LEFT JOIN conversations c ON u.id = c.agent_id AND c.status IN ('open', 'pending')
                WHERE u.status = 'active' 
                AND u.role IN ('agent', 'admin', 'supervisor', 'senior_agent', 'junior_agent')
                AND (u.queue_enabled IS NULL OR u.queue_enabled = 1)";
        
        // Filtrar por disponibilidade apenas se considerAvailability = true
        if ($considerAvailability) {
            $sql .= " AND u.availability_status = 'online'";
        }
        
        $params = [];
        
        if ($departmentId !== null) {
            $sql .= " AND u.id IN (
                        SELECT user_id FROM agent_departments WHERE department_id = ?
                    )";
            $params[] = $departmentId;
        }
        
        $sql .= " GROUP BY u.id";
        
        // Filtrar por limite de conversas apenas se considerMaxConversations = true
        if ($considerMaxConversations) {
            $sql .= " HAVING (u.max_conversations IS NULL OR u.current_conversations < u.max_conversations)";
        }
        
        $humanAgents = Database::fetchAll($sql, $params);
        $agents = array_merge($agents, $humanAgents);
        
        // Agentes de IA (se habilitado)
        if ($includeAI && $settings['distribution']['assign_to_ai_agent']) {
            $aiAgents = \App\Models\AIAgent::getAvailableAgents();
            foreach ($aiAgents as $aiAgent) {
                $agents[] = [
                    'id' => -($aiAgent['id']), // ID negativo para identificar como IA
                    'name' => $aiAgent['name'] . ' (IA)',
                    'current_conversations' => $aiAgent['current_conversations'] ?? 0,
                    'max_conversations' => $aiAgent['max_conversations'],
                    'availability_status' => 'online',
                    'last_assignment_at' => null,
                    'agent_type' => 'ai',
                    'ai_agent_id' => $aiAgent['id']
                ];
            }
        }
        
        return $agents;
    }

    /**
     * Verificar SLA de primeira resposta
     * ATUALIZADO: Considera working hours, SLA por contexto, delay e cliente respondeu ao bot
     */
    public static function checkFirstResponseSLA(int $conversationId, bool $humanOnly = false): bool
    {
        $settings = self::getSettings();
        
        if (!$settings['sla']['enable_sla_monitoring']) {
            return true;
        }
        
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation) {
            return false;
        }
        
        // ✅ REGRA: Se cliente não respondeu ao bot, SLA está OK (não conta)
        if (!self::hasClientRespondedToBot($conversationId)) {
            return true;
        }
        
        // Obter agente atribuído à conversa
        $assignedAgentId = $conversation['agent_id'] ?? null;
        
        // Verificar se já houve primeira resposta do agente ATRIBUÍDO
        if ($humanOnly) {
            // Apenas respostas de agentes humanos
            if ($assignedAgentId) {
                // Filtrar pelo agente atribuído
                $firstAgentMessage = Database::fetch(
                    "SELECT MIN(created_at) as first_response 
                     FROM messages 
                     WHERE conversation_id = ? AND sender_type = 'agent' AND sender_id = ? AND ai_agent_id IS NULL",
                    [$conversationId, $assignedAgentId]
                );
            } else {
                // Se não há agente atribuído, considera qualquer agente humano
                $firstAgentMessage = Database::fetch(
                    "SELECT MIN(created_at) as first_response 
                     FROM messages 
                     WHERE conversation_id = ? AND sender_type = 'agent' AND ai_agent_id IS NULL",
                    [$conversationId]
                );
            }
        } else {
            // Qualquer resposta (IA ou humano) - mas do agente atribuído se houver
            if ($assignedAgentId) {
                $firstAgentMessage = Database::fetch(
                    "SELECT MIN(created_at) as first_response 
                     FROM messages 
                     WHERE conversation_id = ? AND sender_type = 'agent' AND sender_id = ?",
                    [$conversationId, $assignedAgentId]
                );
            } else {
                $firstAgentMessage = Database::fetch(
                    "SELECT MIN(created_at) as first_response 
                     FROM messages 
                     WHERE conversation_id = ? AND sender_type = 'agent'",
                    [$conversationId]
                );
            }
        }
        
        if ($firstAgentMessage && $firstAgentMessage['first_response']) {
            return true; // Já respondeu
        }
        
        // ✅ NOVO: Verificar se mensagem do cliente precisa esperar delay mínimo
        if (!self::shouldStartSLACount($conversationId)) {
            return true; // SLA ainda não deve começar a contar
        }
        
        // Obter SLA aplicável (pode ser personalizado por prioridade/canal/setor)
        $slaConfig = SLARule::getSLAForConversation($conversation);
        $slaMinutes = $slaConfig['first_response_time'];
        
        // Calcular tempo decorrido considerando working hours e pausas
        $startTime = self::getSLAStartTime($conversationId);
        $now = new \DateTime();
        
        // Descontar tempo pausado
        $pausedDuration = (int)($conversation['sla_paused_duration'] ?? 0);
        
        // Calcular minutos considerando working hours
        $elapsedMinutes = WorkingHoursCalculator::calculateMinutes($startTime, $now);
        $elapsedMinutes -= $pausedDuration;
        
        return $elapsedMinutes < $slaMinutes;
    }

    /**
     * Verificar se SLA deve começar a contar (delay de 1 minuto após última mensagem do agente)
     * Evita contar mensagens automáticas, despedidas rápidas ("ok", "obrigado", etc)
     * NOTA: Considera apenas mensagens do agente ATRIBUÍDO à conversa
     */
    public static function shouldStartSLACount(int $conversationId): bool
    {
        $settings = self::getSettings();
        $delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
        $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
        
        // Se delay desabilitado, sempre começar a contar
        if (!$delayEnabled) {
            return true;
        }
        
        // Se delay for 0, sempre começar a contar
        if ($delayMinutes <= 0) {
            return true;
        }
        
        // Obter agente atribuído à conversa
        $conversation = \App\Models\Conversation::find($conversationId);
        $assignedAgentId = $conversation['agent_id'] ?? null;
        
        // Buscar última mensagem do agente ATRIBUÍDO e primeira mensagem do cliente após ela
        if ($assignedAgentId) {
            $sql = "SELECT 
                        (SELECT MAX(created_at) FROM messages 
                         WHERE conversation_id = ? AND sender_type = 'agent' AND sender_id = ?) as last_agent_message,
                        (SELECT MIN(created_at) FROM messages 
                         WHERE conversation_id = ? AND sender_type = 'contact' 
                         AND created_at > COALESCE(
                             (SELECT MAX(created_at) FROM messages 
                              WHERE conversation_id = ? AND sender_type = 'agent' AND sender_id = ?), 
                             '1970-01-01'
                         )) as first_contact_after_agent";
            
            $result = Database::fetch($sql, [$conversationId, $assignedAgentId, $conversationId, $conversationId, $assignedAgentId]);
        } else {
            // Se não há agente atribuído, considera qualquer agente
            $sql = "SELECT 
                        (SELECT MAX(created_at) FROM messages 
                         WHERE conversation_id = ? AND sender_type = 'agent') as last_agent_message,
                        (SELECT MIN(created_at) FROM messages 
                         WHERE conversation_id = ? AND sender_type = 'contact' 
                         AND created_at > COALESCE(
                             (SELECT MAX(created_at) FROM messages 
                              WHERE conversation_id = ? AND sender_type = 'agent'), 
                             '1970-01-01'
                         )) as first_contact_after_agent";
            
            $result = Database::fetch($sql, [$conversationId, $conversationId, $conversationId]);
        }
        
        // Se não há mensagem do agente ainda, começar a contar desde a criação
        if (!$result || !$result['last_agent_message']) {
            return true;
        }
        
        // Se não há mensagem do contato após agente, não há o que contar
        if (!$result['first_contact_after_agent']) {
            return false;
        }
        
        // Calcular diferença em minutos
        $lastAgent = new \DateTime($result['last_agent_message']);
        $firstContact = new \DateTime($result['first_contact_after_agent']);
        
        $diffSeconds = $firstContact->getTimestamp() - $lastAgent->getTimestamp();
        $diffMinutes = $diffSeconds / 60;
        
        // SLA só começa a contar se passou mais de X minutos
        return $diffMinutes >= $delayMinutes;
    }
    
    /**
     * Obter momento em que SLA deve começar a contar
     * Considera delay de 1 minuto após última mensagem do agente ATRIBUÍDO
     */
    public static function getSLAStartTime(int $conversationId): \DateTime
    {
        $settings = self::getSettings();
        $delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
        $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
        
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation) {
            return new \DateTime();
        }
        
        // Se delay desabilitado, usar created_at
        if (!$delayEnabled || $delayMinutes <= 0) {
            return new \DateTime($conversation['created_at']);
        }
        
        // Obter agente atribuído à conversa
        $assignedAgentId = $conversation['agent_id'] ?? null;
        
        // Buscar última mensagem do agente ATRIBUÍDO e primeira do contato após ela
        if ($assignedAgentId) {
            $sql = "SELECT 
                        (SELECT MAX(created_at) FROM messages 
                         WHERE conversation_id = ? AND sender_type = 'agent' AND sender_id = ?) as last_agent_message,
                        (SELECT MIN(created_at) FROM messages 
                         WHERE conversation_id = ? AND sender_type = 'contact' 
                         AND created_at > COALESCE(
                             (SELECT MAX(created_at) FROM messages 
                              WHERE conversation_id = ? AND sender_type = 'agent' AND sender_id = ?), 
                             '1970-01-01'
                         )) as first_contact_after_agent";
            
            $result = Database::fetch($sql, [$conversationId, $assignedAgentId, $conversationId, $conversationId, $assignedAgentId]);
        } else {
            // Se não há agente atribuído, considera qualquer agente
            $sql = "SELECT 
                        (SELECT MAX(created_at) FROM messages 
                         WHERE conversation_id = ? AND sender_type = 'agent') as last_agent_message,
                        (SELECT MIN(created_at) FROM messages 
                         WHERE conversation_id = ? AND sender_type = 'contact' 
                         AND created_at > COALESCE(
                             (SELECT MAX(created_at) FROM messages 
                              WHERE conversation_id = ? AND sender_type = 'agent'), 
                             '1970-01-01'
                         )) as first_contact_after_agent";
            
            $result = Database::fetch($sql, [$conversationId, $conversationId, $conversationId]);
        }
        
        // Se não há mensagem do agente, usar created_at
        if (!$result || !$result['last_agent_message']) {
            return new \DateTime($conversation['created_at']);
        }
        
        // Se não há mensagem do contato após agente, o SLA ainda não iniciou.
        // Retornar o momento em que ele começaria (última mensagem do agente + delay)
        if (!$result['first_contact_after_agent']) {
            $startTime = new \DateTime($result['last_agent_message']);
            if ($delayMinutes > 0) {
                $startTime->modify("+{$delayMinutes} minutes");
            }
            return $startTime;
        }
        
        // Calcular se passou o delay
        $lastAgent = new \DateTime($result['last_agent_message']);
        $firstContact = new \DateTime($result['first_contact_after_agent']);
        
        $diffSeconds = $firstContact->getTimestamp() - $lastAgent->getTimestamp();
        $diffMinutes = $diffSeconds / 60;
        
        // Se passou o delay, SLA começa X minutos após mensagem do agente
        if ($diffMinutes >= $delayMinutes) {
            $startTime = clone $lastAgent;
            $startTime->modify("+{$delayMinutes} minutes");
            return $startTime;
        }
        
        // Se não passou, SLA ainda não iniciou; retornar momento previsto
        $startTime = clone $lastAgent;
        if ($delayMinutes > 0) {
            $startTime->modify("+{$delayMinutes} minutes");
        }
        return $startTime;
    }
    
    /**
     * Verificar SLA de resolução
     * ATUALIZADO: Agora considera working hours, pausas e SLA por contexto
     */
    public static function checkResolutionSLA(int $conversationId): bool
    {
        $settings = self::getSettings();
        
        if (!$settings['sla']['enable_sla_monitoring'] || !$settings['sla']['enable_resolution_sla']) {
            return true;
        }
        
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation || $conversation['status'] === 'closed') {
            return true; // Já resolvida
        }
        
        // Obter SLA aplicável
        $slaConfig = SLARule::getSLAForConversation($conversation);
        $slaMinutes = $slaConfig['resolution_time'];
        
        // Calcular tempo decorrido considerando working hours e pausas
        $createdAt = new \DateTime($conversation['created_at']);
        $now = new \DateTime();
        
        // Descontar tempo pausado
        $pausedDuration = (int)($conversation['sla_paused_duration'] ?? 0);
        
        // Calcular minutos considerando working hours
        $elapsedMinutes = WorkingHoursCalculator::calculateMinutes($createdAt, $now);
        $elapsedMinutes -= $pausedDuration;
        
        return $elapsedMinutes < $slaMinutes;
    }

    /**
     * Pausar SLA (quando conversa está em snooze, aguardando cliente, etc)
     * 
     * ✅ CHAMADO AUTOMATICAMENTE:
     * - Quando conversa é fechada (ConversationService::close)
     * 
     * PODE SER CHAMADO MANUALMENTE:
     * - Quando conversa é colocada em snooze
     * - Quando aguardando resposta do cliente
     * - Quando fora de horário de atendimento
     */
    public static function pauseSLA(int $conversationId): bool
    {
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation || $conversation['sla_paused_at']) {
            return false; // Já pausado ou não existe
        }
        
        return \App\Models\Conversation::update($conversationId, [
            'sla_paused_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Retomar SLA (despausar)
     * 
     * ✅ CHAMADO AUTOMATICAMENTE:
     * - Quando conversa é reaberta (ConversationService::reopen)
     * 
     * PODE SER CHAMADO MANUALMENTE:
     * - Quando conversa sai do snooze
     * - Quando cliente responde
     * - Quando volta ao horário de atendimento
     */
    public static function resumeSLA(int $conversationId): bool
    {
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation || !$conversation['sla_paused_at']) {
            return false; // Não está pausado
        }
        
        // Calcular duração da pausa
        $pausedAt = new \DateTime($conversation['sla_paused_at']);
        $now = new \DateTime();
        
        $pauseDuration = WorkingHoursCalculator::calculateMinutes($pausedAt, $now);
        $totalPaused = (int)($conversation['sla_paused_duration'] ?? 0) + $pauseDuration;
        
        return \App\Models\Conversation::update($conversationId, [
            'sla_paused_at' => null,
            'sla_paused_duration' => $totalPaused
        ]);
    }
    
    /**
     * Obter tempo de SLA decorrido (em minutos)
     * ATUALIZADO: Considera delay de mensagem e ponto de início correto
     */
    public static function getElapsedSLAMinutes(int $conversationId): int
    {
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation) {
            return 0;
        }
        
        // ✅ Verificar se SLA deve começar a contar
        if (!self::shouldStartSLACount($conversationId)) {
            return 0; // SLA ainda não começou
        }
        
        // ✅ Usar tempo de início correto (considerando delay)
        $startTime = self::getSLAStartTime($conversationId);
        $now = new \DateTime();
        
        // Se está pausado, usar o tempo até a pausa
        if ($conversation['sla_paused_at']) {
            $now = new \DateTime($conversation['sla_paused_at']);
        }
        
        $elapsed = WorkingHoursCalculator::calculateMinutes($startTime, $now);
        $paused = (int)($conversation['sla_paused_duration'] ?? 0);
        
        return max(0, $elapsed - $paused);
    }
    
    /**
     * Verificar se deve reatribuir conversa
     */
    public static function shouldReassign(int $conversationId): bool
    {
        $settings = self::getSettings();
        
        if (!$settings['reassignment']['enable_auto_reassignment']) {
            return false;
        }
        
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation || $conversation['status'] === 'closed') {
            return false;
        }
        
        $agentId = $conversation['agent_id'] ?? null;
        if (!$agentId) {
            return false; // Sem agente atribuído
        }
        
        $agent = User::find($agentId);
        if (!$agent) {
            return true; // Agente não existe mais
        }
        
        // Verificar se agente está offline
        if ($settings['reassignment']['reassign_on_agent_offline'] && $agent['availability_status'] !== 'online') {
            return true;
        }
        
        // Verificar SLA
        if ($settings['reassignment']['reassign_on_sla_breach']) {
            if (!self::checkFirstResponseSLA($conversationId)) {
                return true;
            }
        }
        
        // Verificar inatividade
        $lastMessage = Database::fetch(
            "SELECT MAX(created_at) as last_message 
             FROM messages 
             WHERE conversation_id = ? AND sender_type = 'agent'",
            [$conversationId]
        );
        
        if ($lastMessage && $lastMessage['last_message']) {
            $lastMessageTime = strtotime($lastMessage['last_message']);
            $inactivityMinutes = $settings['reassignment']['reassign_on_inactivity_minutes'];
            $now = time();
            
            if (($now - $lastMessageTime) > ($inactivityMinutes * 60)) {
                return true;
            }
        }
        
        return false;
    }
}

