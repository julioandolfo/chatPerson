#!/usr/bin/env php
<?php
/**
 * Script STANDALONE para recalcular SLA de todas as conversas existentes
 * usando as regras atuais configuradas (horários de trabalho, almoço, feriados)
 * 
 * USO: php scripts/recalculate-sla.php [--dry-run] [--limit=100] [--from=2024-01-01]
 * 
 * Opções:
 *   --dry-run    Apenas simula, não salva no banco
 *   --limit=N    Limita a N conversas (para teste)
 *   --from=DATE  Apenas conversas criadas após esta data
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

foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
    if (strpos($arg, '--from=') === 0) {
        $fromDate = substr($arg, 7);
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

echo "📋 Configurações Atuais:\n";
echo "   - Horário comercial habilitado: " . ($workingHoursEnabled ? 'Sim' : 'Não') . "\n";
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

// Buscar conversas
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
    'errors' => 0
];

$progressStep = max(1, intval($totalConversations / 20)); // 5% cada

foreach ($conversations as $index => $conversation) {
    $convId = $conversation['id'];
    
    try {
        // Obter SLA configurado para esta conversa
        $slaConfig = SLARule::getSLAForConversation($conversation);
        $slaFirstResponse = $slaConfig['first_response_time'];
        $slaOngoing = $slaConfig['ongoing_response_time'];
        
        // Obter agente atribuído
        $agentId = $conversation['agent_id'];
        
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
        
        // Encontrar primeira resposta do agente atribuído
        $firstAgentResponse = null;
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'agent') {
                // Se há agente atribuído, filtrar por ele
                if ($agentId && $msg['sender_id'] != $agentId) {
                    continue;
                }
                $firstAgentResponse = $msg;
                break;
            }
        }
        
        // Calcular tempo de primeira resposta
        $firstResponseMinutes = null;
        $firstResponseWithinSla = null;
        
        if ($firstAgentResponse) {
            $start = new \DateTime($firstContactMessage['created_at']);
            $end = new \DateTime($firstAgentResponse['created_at']);
            
            $firstResponseMinutes = WorkingHoursCalculator::calculateMinutes($start, $end);
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
        
        // Calcular tempo máximo de resposta ongoing (entre mensagens do cliente e do agente)
        $maxOngoingMinutes = 0;
        $lastAgentMessage = null;
        
        foreach ($messages as $msg) {
            if ($msg['sender_type'] === 'agent') {
                if ($agentId && $msg['sender_id'] != $agentId) {
                    continue;
                }
                $lastAgentMessage = $msg;
            } elseif ($msg['sender_type'] === 'contact' && $lastAgentMessage) {
                // Mensagem do cliente após agente - calcular intervalo até próxima resposta do agente
                $contactTime = new \DateTime($msg['created_at']);
                
                // Encontrar próxima resposta do agente atribuído
                $nextAgentResponse = null;
                $foundContact = false;
                foreach ($messages as $m2) {
                    if ($m2['id'] == $msg['id']) {
                        $foundContact = true;
                        continue;
                    }
                    if ($foundContact && $m2['sender_type'] === 'agent') {
                        if ($agentId && $m2['sender_id'] != $agentId) {
                            continue;
                        }
                        $nextAgentResponse = $m2;
                        break;
                    }
                }
                
                if ($nextAgentResponse) {
                    $responseTime = new \DateTime($nextAgentResponse['created_at']);
                    $minutes = WorkingHoursCalculator::calculateMinutes($contactTime, $responseTime);
                    $maxOngoingMinutes = max($maxOngoingMinutes, $minutes);
                }
            }
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
                echo "1ª Resp: {$firstResponseMinutes}min {$status} ";
            } else {
                echo "Sem resposta ";
            }
            if ($maxOngoingMinutes > 0) {
                $status = $maxOngoingMinutes <= $slaOngoing ? '✓' : '✗';
                echo "| Max Ongoing: {$maxOngoingMinutes}min {$status}";
            }
            echo "\n";
        }
        
        // Progress
        if (($index + 1) % $progressStep === 0) {
            $pct = round((($index + 1) / $totalConversations) * 100);
            echo "⏳ Processando... {$pct}% ({$index}/{$totalConversations})\n";
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
echo "   Sem resposta: {$stats['no_response']}\n";
echo "   Erros: {$stats['errors']}\n\n";

echo "📈 SLA de 1ª Resposta:\n";
$totalFirst = $stats['first_response_within'] + $stats['first_response_exceeded'];
if ($totalFirst > 0) {
    $pctFirst = round(($stats['first_response_within'] / $totalFirst) * 100, 1);
    echo "   ✓ Dentro do SLA: {$stats['first_response_within']} ({$pctFirst}%)\n";
    echo "   ✗ Fora do SLA: {$stats['first_response_exceeded']} (" . (100 - $pctFirst) . "%)\n";
} else {
    echo "   Nenhuma conversa com resposta\n";
}

echo "\n📈 SLA de Respostas (Ongoing):\n";
$totalOngoing = $stats['ongoing_within'] + $stats['ongoing_exceeded'];
if ($totalOngoing > 0) {
    $pctOngoing = round(($stats['ongoing_within'] / $totalOngoing) * 100, 1);
    echo "   ✓ Dentro do SLA: {$stats['ongoing_within']} ({$pctOngoing}%)\n";
    echo "   ✗ Fora do SLA: {$stats['ongoing_exceeded']} (" . (100 - $pctOngoing) . "%)\n";
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
