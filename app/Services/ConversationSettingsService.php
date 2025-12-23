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
use App\Helpers\Database;

class ConversationSettingsService
{
    const SETTINGS_KEY = 'conversation_settings';

    /**
     * Obter todas as configurações
     */
    public static function getSettings(): array
    {
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
    }

    /**
     * Salvar configurações
     */
    public static function saveSettings(array $settings): bool
    {
        $existing = Setting::whereFirst('key', '=', self::SETTINGS_KEY);
        
        $data = [
            'key' => self::SETTINGS_KEY,
            'value' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'type' => 'json',
            'group' => 'conversations',
            'label' => 'Configurações Avançadas de Conversas',
            'description' => 'Configurações de limites, SLA, distribuição e reatribuição de conversas'
        ];
        
        if ($existing) {
            return Setting::update($existing['id'], $data);
        } else {
            Setting::create($data);
            return true;
        }
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
     */
    public static function autoAssignConversation(int $conversationId, ?int $departmentId = null, ?int $funnelId = null, ?int $stageId = null): ?int
    {
        $settings = self::getSettings();
        
        if (!$settings['distribution']['enable_auto_assignment']) {
            return null;
        }
        
        $method = $settings['distribution']['method'];
        $includeAI = $settings['distribution']['assign_to_ai_agent'] ?? false;
        
        switch ($method) {
            case 'round_robin':
                return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI);
            case 'by_load':
                return self::assignByLoad($departmentId, $funnelId, $stageId, $includeAI);
            case 'by_specialty':
                return self::assignBySpecialty($departmentId, $funnelId, $stageId, $includeAI);
            case 'by_performance':
                return self::assignByPerformance($departmentId, $funnelId, $stageId, $includeAI);
            case 'percentage':
                return self::assignByPercentage($departmentId, $funnelId, $stageId, $includeAI);
            default:
                return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI);
        }
    }

    /**
     * Distribuição Round-Robin
     */
    public static function assignRoundRobin(?int $departmentId = null, ?int $funnelId = null, ?int $stageId = null, bool $includeAI = false): ?int
    {
        $agents = self::getAvailableAgents($departmentId, $funnelId, $stageId, $includeAI);
        
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
     */
    public static function assignByLoad(?int $departmentId = null, ?int $funnelId = null, ?int $stageId = null, bool $includeAI = false): ?int
    {
        $agents = self::getAvailableAgents($departmentId, $funnelId, $stageId, $includeAI);
        
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
     */
    public static function assignBySpecialty(?int $departmentId = null, ?int $funnelId = null, ?int $stageId = null, bool $includeAI = false): ?int
    {
        // Por enquanto, usar round-robin dentro do setor
        return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI);
    }

    /**
     * Distribuição por performance (melhor performance primeiro)
     */
    public static function assignByPerformance(?int $departmentId = null, ?int $funnelId = null, ?int $stageId = null, bool $includeAI = false): ?int
    {
        $agents = self::getAvailableAgents($departmentId, $funnelId, $stageId, $includeAI);
        
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
     */
    public static function assignByPercentage(?int $departmentId = null, ?int $funnelId = null, ?int $stageId = null, bool $includeAI = false): ?int
    {
        $settings = self::getSettings();
        
        if (!$settings['percentage_distribution']['enabled']) {
            return self::assignRoundRobin($departmentId, $funnelId, $stageId, $includeAI);
        }
        
        $rules = $settings['percentage_distribution']['rules'] ?? [];
        
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
    private static function getAvailableAgents(?int $departmentId = null, ?int $funnelId = null, ?int $stageId = null, bool $includeAI = false): array
    {
        $settings = self::getSettings();
        $agents = [];
        
        // Agentes humanos
        $sql = "SELECT u.id, u.name, u.current_conversations, u.max_conversations, u.availability_status,
                       MAX(c.updated_at) as last_assignment_at, 'human' as agent_type
                FROM users u
                LEFT JOIN conversations c ON u.id = c.agent_id AND c.status IN ('open', 'pending')
                WHERE u.status = 'active' 
                AND u.availability_status = 'online'
                AND u.role IN ('agent', 'admin', 'supervisor')";
        
        $params = [];
        
        if ($departmentId !== null) {
            $sql .= " AND u.id IN (
                        SELECT user_id FROM agent_departments WHERE department_id = ?
                    )";
            $params[] = $departmentId;
        }
        
        $sql .= " GROUP BY u.id
                  HAVING (u.max_conversations IS NULL OR u.current_conversations < u.max_conversations)";
        
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
     */
    public static function checkFirstResponseSLA(int $conversationId): bool
    {
        $settings = self::getSettings();
        
        if (!$settings['sla']['enable_sla_monitoring']) {
            return true;
        }
        
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation) {
            return false;
        }
        
        // Verificar se já houve primeira resposta do agente
        $firstAgentMessage = Database::fetch(
            "SELECT MIN(created_at) as first_response 
             FROM messages 
             WHERE conversation_id = ? AND sender_type = 'agent'",
            [$conversationId]
        );
        
        if ($firstAgentMessage && $firstAgentMessage['first_response']) {
            return true; // Já respondeu
        }
        
        // Verificar se passou do SLA
        $createdAt = strtotime($conversation['created_at']);
        $slaMinutes = $settings['sla']['first_response_time'];
        $now = time();
        
        return ($now - $createdAt) < ($slaMinutes * 60);
    }

    /**
     * Verificar SLA de resolução
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
        
        // Verificar se passou do SLA
        $createdAt = strtotime($conversation['created_at']);
        $slaMinutes = $settings['sla']['resolution_time'];
        $now = time();
        
        return ($now - $createdAt) < ($slaMinutes * 60);
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

