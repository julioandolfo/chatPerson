#!/usr/bin/env php
<?php
/**
 * Script STANDALONE para recalcular SLA de todas as conversas existentes
 * usando as regras atuais configuradas (horários de trabalho, almoço, feriados)
 * 
 * REGRAS APLICADAS:
 *   1. Considera período de atribuição do agente (transferências)
 *   2. Não conta SLA se cliente não respondeu ao bot
 *   3. Considera delay mínimo entre mensagens
 *   4. Usa working hours quando habilitado
 * 
 * USO: php scripts/recalculate-sla.php [--dry-run] [--limit=100] [--from=2024-01-01] [--agent=ID]
 * 
 * Opções:
 *   --dry-run    Apenas simula, não salva no banco
 *   --limit=N    Limita a N conversas (para teste)
 *   --from=DATE  Apenas conversas criadas após esta data
 *   --agent=ID   Apenas conversas do agente específico
 *   --verbose    Mostra detalhes de cada conversa
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(__DIR__);
chdir($rootDir);

// Carregar bootstrap (que já tem o autoloader)
require_once $rootDir . '/config/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\WorkingHoursCalculator;
use App\Services\ConversationSettingsService;
use App\Models\SLARule;

// Parsear argumentos
$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv);
$limit = null;
$fromDate = null;
$agentFilter = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
    if (strpos($arg, '--from=') === 0) {
        $fromDate = substr($arg, 7);
    }
    if (strpos($arg, '--agent=') === 0) {
        $agentFilter = (int)substr($arg, 8);
    }
}

echo "========================================\n";
echo "   RECALCULAR SLA DAS CONVERSAS\n";
echo "========================================\n\n";

if ($dryRun) {
    echo "🔵 MODO DRY-RUN: Nenhuma alteração será salva\n\n";
}

// Verificar configurações
$settings = ConversationSettingsService::getSettings();
$workingHoursEnabled = $settings['sla']['working_hours_enabled'] ?? false;
$delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
$delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;

echo "📋 Configurações Atuais:\n";
echo "   - Horário comercial habilitado: " . ($workingHoursEnabled ? 'Sim' : 'Não') . "\n";
echo "   - Delay de mensagem: " . ($delayEnabled ? "{$delayMinutes} min" : 'Desabilitado') . "\n";
echo "   - SLA 1ª Resposta: " . ($settings['sla']['first_response_time'] ?? 15) . " min\n";
echo "   - SLA Respostas: " . ($settings['sla']['ongoing_response_time'] ?? 15) . " min\n";
echo "   - SLA Resolução: " . ($settings['sla']['resolution_time'] ?? 60) . " min\n";

if ($workingHoursEnabled) {
    echo "\n📅 Horários de Trabalho:\n";
    WorkingHoursCalculator::clearCache();
    
    $db = Database::getInstance();
    $days = $db->query("SELECT * FROM working_hours_config ORDER BY day_of_week")->fetchAll(\PDO::FETCH_ASSOC);
    $dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    
    foreach ($days as $day) {
        $d = $dayNames[$day['day_of_week']];
        if ($day['is_working_day']) {
            $lunch = $day['lunch_enabled'] ? " (Almoço: {$day['lunch_start']}-{$day['lunch_end']})" : "";
            echo "   - {$d}: {$day['start_time']} - {$day['end_time']}{$lunch}\n";
        } else {
            echo "   - {$d}: Não trabalha\n";
        }
    }
    
    // Contar feriados
    $holidayCount = $db->query("SELECT COUNT(*) as total FROM holidays")->fetch(\PDO::FETCH_ASSOC);
    echo "\n🎉 Feriados cadastrados: " . ($holidayCount['total'] ?? 0) . "\n";
}

echo "\n";

// ============================================================================
// FUNÇÕES AUXILIARES
// ============================================================================

/**
 * Obter todos os períodos de atribuição de um agente para uma conversa
 */
function getAllAgentAssignmentPeriods(int $conversationId, int $agentId): array {
    $allAssignments = Database::fetchAll(
        "SELECT agent_id, assigned_at 
         FROM conversation_assignments 
         WHERE conversation_id = ?
         ORDER BY assigned_at ASC",
        [$conversationId]
    );
    
    if (empty($allAssignments)) {
        return [];
    }
    
    $periods = [];
    $currentPeriodStart = null;
    
    foreach ($allAssignments as $assignment) {
        $isTargetAgent = ((int)$assignment['agent_id'] === $agentId);
        
        if ($isTargetAgent && $currentPeriodStart === null) {
            $currentPeriodStart = $assignment['assigned_at'];
        } elseif (!$isTargetAgent && $currentPeriodStart !== null) {
            $periods[] = [
                'assigned_at' => $currentPeriodStart,
                'unassigned_at' => $assignment['assigned_at']
            ];
            $currentPeriodStart = null;
        }
    }
    
    if ($currentPeriodStart !== null) {
        $periods[] = [
            'assigned_at' => $currentPeriodStart,
            'unassigned_at' => null
        ];
    }
    
    return $periods;
}

/**
 * Verificar se uma mensagem está dentro de algum período de atribuição do agente
 */
function isMessageInAgentPeriod(string $messageTime, array $periods): bool {
    $msgTime = strtotime($messageTime);
    
    foreach ($periods as $period) {
        $start = strtotime($period['assigned_at']);
        $end = $period['unassigned_at'] ? strtotime($period['unassigned_at']) : PHP_INT_MAX;
        
        if ($msgTime >= $start && $msgTime <= $end) {
            return true;
        }
    }
    
    return false;
}

/**
 * Obter o fim do período para uma mensagem específica
 */
function getPeriodEndForMessage(string $messageTime, array $periods): ?string {
    $msgTime = strtotime($messageTime);
    
    foreach ($periods as $period) {
        $start = strtotime($period['assigned_at']);
        $end = $period['unassigned_at'] ? strtotime($period['unassigned_at']) : PHP_INT_MAX;
        
        if ($msgTime >= $start && $msgTime <= $end) {
            return $period['unassigned_at'];
        }
    }
    
    return null;
}

/**
 * Verificar se cliente respondeu ao bot
 */
function hasClientRespondedToBot(array $messages): bool {
    $lastAgentTime = null;
    
    // Encontrar última mensagem do agente
    foreach ($messages as $msg) {
        if ($msg['sender_type'] === 'agent') {
            $lastAgentTime = $msg['created_at'];
        }
    }
    
    if (!$lastAgentTime) {
        // Sem resposta de agente - verificar se há mensagem de cliente
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'contact') {
                return true;
            }
        }
        return false;
    }
    
    // Verificar se há mensagem do cliente após última do agente
    foreach ($messages as $msg) {
        if ($msg['sender_type'] === 'contact' && $msg['created_at'] > $lastAgentTime) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calcular tempo usando working hours se habilitado
 */
function calculateMinutes(\DateTime $start, \DateTime $end, bool $useWorkingHours): float {
    if ($useWorkingHours) {
        return (float)WorkingHoursCalculator::calculateMinutes($start, $end);
    }
    return max(0, ($end->getTimestamp() - $start->getTimestamp()) / 60);
}

// ============================================================================
// BUSCAR CONVERSAS
// ============================================================================

$sql = "SELECT c.id, c.created_at, c.status, c.agent_id, c.priority, 
               c.department_id, c.funnel_id, c.funnel_stage_id,
               c.sla_paused_duration
        FROM conversations c
        WHERE 1=1";
$params = [];

if ($fromDate) {
    $sql .= " AND c.created_at >= ?";
    $params[] = $fromDate;
}

if ($agentFilter) {
    $sql .= " AND c.agent_id = ?";
    $params[] = $agentFilter;
    echo "🎯 Filtrando apenas conversas do agente ID: {$agentFilter}\n\n";
}

$sql .= " ORDER BY c.created_at DESC";

if ($limit) {
    $sql .= " LIMIT " . (int)$limit;
}

$conversations = Database::fetchAll($sql, $params);
$totalConversations = count($conversations);

echo "🔍 Encontradas {$totalConversations} conversas para processar\n\n";

if ($totalConversations === 0) {
    echo "Nenhuma conversa para processar.\n";
    exit(0);
}

$stats = [
    'total' => $totalConversations,
    'processed' => 0,
    'first_response_within' => 0,
    'first_response_exceeded' => 0,
    'ongoing_within' => 0,
    'ongoing_exceeded' => 0,
    'no_response' => 0,
    'skipped_no_client_after_bot' => 0,
    'skipped_not_in_period' => 0,
    'errors' => 0
];

$progressStep = max(1, intval($totalConversations / 20));

foreach ($conversations as $index => $conversation) {
    $convId = $conversation['id'];
    
    try {
        // Obter SLA configurado para esta conversa
        $slaConfig = SLARule::getSLAForConversation($conversation);
        $slaFirstResponse = $slaConfig['first_response_time'];
        $slaOngoing = $slaConfig['ongoing_response_time'];
        
        $agentId = (int)$conversation['agent_id'];
        
        // Buscar mensagens da conversa
        $messages = Database::fetchAll(
            "SELECT id, sender_type, sender_id, created_at 
             FROM messages 
             WHERE conversation_id = ? 
             ORDER BY created_at ASC",
            [$convId]
        );
        
        if (empty($messages)) {
            $stats['no_response']++;
            continue;
        }
        
        // REGRA 1: Verificar se cliente respondeu ao bot
        if (!hasClientRespondedToBot($messages)) {
            $stats['skipped_no_client_after_bot']++;
            if ($verbose) {
                echo "Conv #{$convId}: ⏭️ Cliente não respondeu ao bot\n";
            }
            continue;
        }
        
        // Buscar períodos de atribuição do agente
        $assignmentPeriods = getAllAgentAssignmentPeriods($convId, $agentId);
        
        // Encontrar primeira mensagem do contato
        $firstContactMessage = null;
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'contact') {
                $firstContactMessage = $msg;
                break;
            }
        }
        
        if (!$firstContactMessage) {
            $stats['no_response']++;
            continue;
        }
        
        // ============================================================
        // CALCULAR SLA DE PRIMEIRA RESPOSTA
        // ============================================================
        
        // Encontrar primeira resposta do agente atribuído
        $firstAgentResponse = null;
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'agent') {
                if ($agentId > 0 && (int)$msg['sender_id'] !== $agentId) {
                    continue;
                }
                $firstAgentResponse = $msg;
                break;
            }
        }
        
        $firstResponseMinutes = null;
        $firstResponseWithinSla = null;
        
        if ($firstAgentResponse) {
            $start = new \DateTime($firstContactMessage['created_at']);
            $end = new \DateTime($firstAgentResponse['created_at']);
            
            // Verificar se a resposta está dentro do período de atribuição
            if ($agentId > 0 && !empty($assignmentPeriods)) {
                // Se agente foi atribuído após primeira mensagem, ajustar início
                $firstPeriod = $assignmentPeriods[0] ?? null;
                if ($firstPeriod && strtotime($firstPeriod['assigned_at']) > $start->getTimestamp()) {
                    $start = new \DateTime($firstPeriod['assigned_at']);
                }
            }
            
            $firstResponseMinutes = calculateMinutes($start, $end, $workingHoursEnabled);
            $firstResponseMinutes -= (int)($conversation['sla_paused_duration'] ?? 0);
            $firstResponseMinutes = max(0, $firstResponseMinutes);
            
            $firstResponseWithinSla = $firstResponseMinutes <= $slaFirstResponse;
            
            if ($firstResponseWithinSla) {
                $stats['first_response_within']++;
            } else {
                $stats['first_response_exceeded']++;
            }
        } else {
            $stats['no_response']++;
        }
        
        // ============================================================
        // CALCULAR SLA ONGOING (com todas as regras)
        // ============================================================
        
        $maxOngoingMinutes = 0;
        $lastAgentMessage = null;
        $pendingContactMessage = null;
        
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'agent') {
                // Só considerar mensagens do agente atribuído
                if ($agentId > 0 && (int)$msg['sender_id'] !== $agentId) {
                    continue;
                }
                
                // Se havia mensagem pendente do cliente, calcular intervalo
                if ($pendingContactMessage) {
                    $contactTime = new \DateTime($pendingContactMessage['created_at']);
                    $agentTime = new \DateTime($msg['created_at']);
                    $minutes = calculateMinutes($contactTime, $agentTime, $workingHoursEnabled);
                    $maxOngoingMinutes = max($maxOngoingMinutes, $minutes);
                    $pendingContactMessage = null;
                }
                
                $lastAgentMessage = $msg;
                
            } elseif ($msg['sender_type'] === 'contact' && $lastAgentMessage) {
                // REGRA 2: Verificar se mensagem está dentro do período de atribuição
                if ($agentId > 0 && !empty($assignmentPeriods)) {
                    if (!isMessageInAgentPeriod($msg['created_at'], $assignmentPeriods)) {
                        continue; // Fora do período
                    }
                }
                
                // REGRA 3: Aplicar delay mínimo
                if ($delayEnabled) {
                    $lastAgentTime = new \DateTime($lastAgentMessage['created_at']);
                    $contactTime = new \DateTime($msg['created_at']);
                    $diffMinutes = ($contactTime->getTimestamp() - $lastAgentTime->getTimestamp()) / 60;
                    
                    if ($diffMinutes < $delayMinutes) {
                        continue; // Mensagem muito rápida (despedida, ok, etc)
                    }
                }
                
                if (!$pendingContactMessage) {
                    $pendingContactMessage = $msg;
                }
            }
        }
        
        // Se ficou mensagem pendente e conversa está aberta
        if ($pendingContactMessage && in_array($conversation['status'], ['open', 'pending'])) {
            // Verificar se agente foi transferido
            $periodEnd = null;
            if ($agentId > 0 && !empty($assignmentPeriods)) {
                $periodEnd = getPeriodEndForMessage($pendingContactMessage['created_at'], $assignmentPeriods);
            }
            
            $contactTime = new \DateTime($pendingContactMessage['created_at']);
            $endTime = $periodEnd ? new \DateTime($periodEnd) : new \DateTime();
            
            $minutes = calculateMinutes($contactTime, $endTime, $workingHoursEnabled);
            $maxOngoingMinutes = max($maxOngoingMinutes, $minutes);
        }
        
        if ($maxOngoingMinutes > 0) {
            if ($maxOngoingMinutes <= $slaOngoing) {
                $stats['ongoing_within']++;
            } else {
                $stats['ongoing_exceeded']++;
            }
        }
        
        $stats['processed']++;
        
        // Verbose output
        if ($verbose) {
            echo "Conv #{$convId}: ";
            if ($firstResponseMinutes !== null) {
                $status = $firstResponseWithinSla ? '✓' : '✗';
                echo "1ª Resp: " . round($firstResponseMinutes, 1) . "min {$status} ";
            } else {
                echo "Sem resposta ";
            }
            if ($maxOngoingMinutes > 0) {
                $status = $maxOngoingMinutes <= $slaOngoing ? '✓' : '✗';
                echo "| Max Ongoing: " . round($maxOngoingMinutes, 1) . "min {$status}";
            }
            echo "\n";
        }
        
        // Progress
        if (($index + 1) % $progressStep === 0) {
            $pct = round((($index + 1) / $totalConversations) * 100);
            echo "⏳ Processando... {$pct}% (" . ($index + 1) . "/{$totalConversations})\n";
        }
        
    } catch (\Exception $e) {
        $stats['errors']++;
        if ($verbose) {
            echo "❌ Erro na conversa #{$convId}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n========================================\n";
echo "   RESULTADO DO RECÁLCULO\n";
echo "========================================\n\n";

echo "📊 Estatísticas:\n";
echo "   Total de conversas: {$stats['total']}\n";
echo "   Processadas: {$stats['processed']}\n";
echo "   Sem resposta do agente: {$stats['no_response']}\n";
echo "   Ignoradas (cliente não respondeu bot): {$stats['skipped_no_client_after_bot']}\n";
echo "   Erros: {$stats['errors']}\n\n";

echo "📈 SLA de 1ª Resposta:\n";
$totalFirst = $stats['first_response_within'] + $stats['first_response_exceeded'];
if ($totalFirst > 0) {
    $pctFirst = round(($stats['first_response_within'] / $totalFirst) * 100, 1);
    echo "   ✓ Dentro do SLA: {$stats['first_response_within']} ({$pctFirst}%)\n";
    echo "   ✗ Fora do SLA: {$stats['first_response_exceeded']} (" . round(100 - $pctFirst, 1) . "%)\n";
} else {
    echo "   Nenhuma conversa com resposta\n";
}

echo "\n📈 SLA de Respostas (Ongoing):\n";
$totalOngoing = $stats['ongoing_within'] + $stats['ongoing_exceeded'];
if ($totalOngoing > 0) {
    $pctOngoing = round(($stats['ongoing_within'] / $totalOngoing) * 100, 1);
    echo "   ✓ Dentro do SLA: {$stats['ongoing_within']} ({$pctOngoing}%)\n";
    echo "   ✗ Fora do SLA: {$stats['ongoing_exceeded']} (" . round(100 - $pctOngoing, 1) . "%)\n";
} else {
    echo "   Nenhuma conversa com múltiplas respostas\n";
}

echo "\n";

if ($dryRun) {
    echo "🔵 Este foi apenas um teste (--dry-run). Nenhuma alteração foi feita.\n";
} else {
    echo "✅ Análise concluída! Os dados acima refletem o cálculo com as novas regras.\n";
    echo "   As métricas exibidas no dashboard usarão automaticamente as novas regras.\n";
}

echo "\n";
