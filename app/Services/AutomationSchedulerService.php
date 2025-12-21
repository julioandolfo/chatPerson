<?php
/**
 * Service AutomationSchedulerService
 * Processa automações baseadas em tempo (cronjob)
 */

namespace App\Services;

use App\Models\Automation;
use App\Models\Conversation;
use App\Helpers\Database;
use App\Helpers\Logger;

class AutomationSchedulerService
{
    /**
     * Processar gatilhos de tempo sem resposta do cliente
     */
    public static function processNoCustomerResponseTriggers(): void
    {
        Logger::automation("=== Processando gatilhos 'no_customer_response' ===");
        
        // Buscar automações ativas
        $sql = "SELECT * FROM automations WHERE trigger_type = ? AND status = ? AND is_active = ? ORDER BY id ASC";
        $automations = Database::fetchAll($sql, ['no_customer_response', 'active', 1]);
        
        Logger::automation("Encontradas " . count($automations) . " automações ativas.");
        
        foreach ($automations as $automation) {
            try {
                $config = json_decode($automation['trigger_config'], true) ?? [];
                $timeValue = $config['wait_time_value'] ?? 30;
                $timeUnit = $config['wait_time_unit'] ?? 'minutes';
                $onlyOpen = $config['only_open_conversations'] ?? true;
                
                // Converter tempo para minutos
                $minutes = self::convertToMinutes($timeValue, $timeUnit);
                
                Logger::automation("Automação #{$automation['id']}: {$automation['name']} - Aguardando {$timeValue} {$timeUnit} ({$minutes} min)");
                
                // Buscar conversas que atendem os critérios
                $sql = "
                    SELECT c.* 
                    FROM conversations c
                    WHERE c.id IN (
                        SELECT m.conversation_id
                        FROM messages m
                        WHERE m.id = (
                            SELECT MAX(id) 
                            FROM messages 
                            WHERE conversation_id = c.id
                        )
                        AND m.sender_type IN ('agent', 'ai_agent')
                        AND TIMESTAMPDIFF(MINUTE, m.created_at, NOW()) >= ?
                    )
                ";
                
                $params = [$minutes];
                
                // Filtrar por status
                if ($onlyOpen) {
                    $sql .= " AND c.status IN ('open', 'pending')";
                }
                
                // Filtrar por funil/estágio
                if (!empty($automation['funnel_id'])) {
                    $sql .= " AND c.funnel_id = ?";
                    $params[] = $automation['funnel_id'];
                }
                if (!empty($automation['stage_id'])) {
                    $sql .= " AND c.funnel_stage_id = ?";
                    $params[] = $automation['stage_id'];
                }
                
                $sql .= " ORDER BY c.id ASC";
                
                $conversations = Database::fetchAll($sql, $params);
                
                Logger::automation("  → Encontradas " . count($conversations) . " conversas elegíveis.");
                
                // Executar automação para cada conversa (evitar duplicatas)
                foreach ($conversations as $conversation) {
                    // Verificar se já foi executada recentemente (últimos 10 minutos)
                    if (!self::wasRecentlyExecuted($automation['id'], $conversation['id'], 10)) {
                        Logger::automation("  → Executando para conversa #{$conversation['id']}");
                        AutomationService::executeForConversation($automation['id'], $conversation['id']);
                    } else {
                        Logger::automation("  → Pulando conversa #{$conversation['id']} (executada recentemente)");
                    }
                }
                
            } catch (\Exception $e) {
                Logger::automation("ERRO ao processar automação #{$automation['id']}: " . $e->getMessage());
            }
        }
        
        Logger::automation("=== Fim do processamento 'no_customer_response' ===\n");
    }
    
    /**
     * Processar gatilhos de tempo sem resposta do agente
     */
    public static function processNoAgentResponseTriggers(): void
    {
        Logger::automation("=== Processando gatilhos 'no_agent_response' ===");
        
        // Buscar automações ativas
        $sql = "SELECT * FROM automations WHERE trigger_type = ? AND status = ? AND is_active = ? ORDER BY id ASC";
        $automations = Database::fetchAll($sql, ['no_agent_response', 'active', 1]);
        
        Logger::automation("Encontradas " . count($automations) . " automações ativas.");
        
        foreach ($automations as $automation) {
            try {
                $config = json_decode($automation['trigger_config'], true) ?? [];
                $timeValue = $config['wait_time_value'] ?? 15;
                $timeUnit = $config['wait_time_unit'] ?? 'minutes';
                $onlyOpen = $config['only_open_conversations'] ?? true;
                $onlyAssigned = $config['only_assigned'] ?? true;
                
                // Converter tempo para minutos
                $minutes = self::convertToMinutes($timeValue, $timeUnit);
                
                Logger::automation("Automação #{$automation['id']}: {$automation['name']} - Aguardando {$timeValue} {$timeUnit} ({$minutes} min)");
                
                // Buscar conversas que atendem os critérios
                $sql = "
                    SELECT c.* 
                    FROM conversations c
                    WHERE c.id IN (
                        SELECT m.conversation_id
                        FROM messages m
                        WHERE m.id = (
                            SELECT MAX(id) 
                            FROM messages 
                            WHERE conversation_id = c.id
                        )
                        AND m.sender_type = 'contact'
                        AND TIMESTAMPDIFF(MINUTE, m.created_at, NOW()) >= ?
                    )
                ";
                
                $params = [$minutes];
                
                // Filtrar por conversas atribuídas
                if ($onlyAssigned) {
                    $sql .= " AND c.agent_id IS NOT NULL";
                }
                
                // Filtrar por status
                if ($onlyOpen) {
                    $sql .= " AND c.status IN ('open', 'pending')";
                }
                
                // Filtrar por funil/estágio
                if (!empty($automation['funnel_id'])) {
                    $sql .= " AND c.funnel_id = ?";
                    $params[] = $automation['funnel_id'];
                }
                if (!empty($automation['stage_id'])) {
                    $sql .= " AND c.funnel_stage_id = ?";
                    $params[] = $automation['stage_id'];
                }
                
                $sql .= " ORDER BY c.id ASC";
                
                $conversations = Database::fetchAll($sql, $params);
                
                Logger::automation("  → Encontradas " . count($conversations) . " conversas elegíveis.");
                
                // Executar automação para cada conversa (evitar duplicatas)
                foreach ($conversations as $conversation) {
                    // Verificar se já foi executada recentemente (últimos 10 minutos)
                    if (!self::wasRecentlyExecuted($automation['id'], $conversation['id'], 10)) {
                        Logger::automation("  → Executando para conversa #{$conversation['id']}");
                        AutomationService::executeForConversation($automation['id'], $conversation['id']);
                    } else {
                        Logger::automation("  → Pulando conversa #{$conversation['id']} (executada recentemente)");
                    }
                }
                
            } catch (\Exception $e) {
                Logger::automation("ERRO ao processar automação #{$automation['id']}: " . $e->getMessage());
            }
        }
        
        Logger::automation("=== Fim do processamento 'no_agent_response' ===\n");
    }
    
    /**
     * Processar gatilhos baseados em tempo (agendados)
     */
    public static function processTimeBasedTriggers(): void
    {
        Logger::automation("=== Processando gatilhos 'time_based' ===");
        
        // Buscar automações ativas
        $sql = "SELECT * FROM automations WHERE trigger_type = ? AND status = ? AND is_active = ? ORDER BY id ASC";
        $automations = Database::fetchAll($sql, ['time_based', 'active', 1]);
        
        Logger::automation("Encontradas " . count($automations) . " automações ativas.");
        
        $now = new \DateTime();
        $currentHour = (int)$now->format('H');
        $currentMinute = (int)$now->format('i');
        $currentDay = (int)$now->format('N'); // 1=Segunda, 7=Domingo
        
        foreach ($automations as $automation) {
            try {
                $config = json_decode($automation['trigger_config'], true) ?? [];
                $scheduleType = $config['schedule_type'] ?? 'daily';
                $scheduleHour = isset($config['schedule_hour']) ? (int)$config['schedule_hour'] : 9;
                $scheduleMinute = isset($config['schedule_minute']) ? (int)$config['schedule_minute'] : 0;
                $scheduleDayOfWeek = isset($config['schedule_day_of_week']) ? (int)$config['schedule_day_of_week'] : 1;
                
                $shouldExecute = false;
                
                // Verificar se deve executar baseado no tipo de agendamento
                if ($scheduleType === 'daily') {
                    // Executar diariamente no horário especificado
                    $shouldExecute = ($currentHour === $scheduleHour && $currentMinute === $scheduleMinute);
                } elseif ($scheduleType === 'weekly') {
                    // Executar semanalmente no dia e horário especificados
                    $shouldExecute = (
                        $currentDay === $scheduleDayOfWeek &&
                        $currentHour === $scheduleHour &&
                        $currentMinute === $scheduleMinute
                    );
                }
                
                if ($shouldExecute) {
                    Logger::automation("Automação #{$automation['id']}: {$automation['name']} - Executando agendamento {$scheduleType}");
                    
                    // Executar para todas as conversas que atendem os critérios
                    $sql = "SELECT c.* FROM conversations c WHERE c.status IN ('open', 'pending')";
                    $params = [];
                    
                    // Filtrar por funil/estágio
                    if (!empty($automation['funnel_id'])) {
                        $sql .= " AND c.funnel_id = ?";
                        $params[] = $automation['funnel_id'];
                    }
                    if (!empty($automation['stage_id'])) {
                        $sql .= " AND c.funnel_stage_id = ?";
                        $params[] = $automation['stage_id'];
                    }
                    
                    $conversations = Database::query($sql, $params);
                    
                    Logger::automation("  → Encontradas " . count($conversations) . " conversas elegíveis.");
                    
                    foreach ($conversations as $conversation) {
                        Logger::automation("  → Executando para conversa #{$conversation['id']}");
                        AutomationService::executeForConversation($automation['id'], $conversation['id']);
                    }
                }
                
            } catch (\Exception $e) {
                Logger::automation("ERRO ao processar automação #{$automation['id']}: " . $e->getMessage());
            }
        }
        
        Logger::automation("=== Fim do processamento 'time_based' ===\n");
    }
    
    /**
     * Converter tempo para minutos
     */
    private static function convertToMinutes(int $value, string $unit): int
    {
        switch ($unit) {
            case 'hours':
                return $value * 60;
            case 'days':
                return $value * 1440; // 24 * 60
            case 'minutes':
            default:
                return $value;
        }
    }
    
    /**
     * Verificar se uma automação foi executada recentemente para uma conversa
     * Para evitar execuções duplicadas
     */
    private static function wasRecentlyExecuted(int $automationId, int $conversationId, int $minutesThreshold = 10): bool
    {
        try {
            $sql = "
                SELECT COUNT(*) as count
                FROM automation_executions
                WHERE automation_id = ?
                AND conversation_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ";
            
            $result = Database::fetch($sql, [$automationId, $conversationId, $minutesThreshold]);
            
            return !empty($result) && $result['count'] > 0;
            
        } catch (\Exception $e) {
            Logger::automation("ERRO ao verificar execução recente: " . $e->getMessage());
            return false;
        }
    }
}

