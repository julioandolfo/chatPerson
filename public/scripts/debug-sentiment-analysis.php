<?php
/**
 * Script de debug para análise de sentimento
 */

// Mudar para diretório raiz do projeto
chdir(__DIR__ . '/../../');

// Autoloader
require_once __DIR__ . '/../../app/Helpers/autoload.php';

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

use App\Services\ConversationSettingsService;
use App\Helpers\Database;

echo "========================================\n";
echo "DEBUG - ANÁLISE DE SENTIMENTO\n";
echo "========================================\n\n";

// 1. Verificar configurações
echo "1️⃣ CONFIGURAÇÕES:\n";
echo "-------------------\n";
$allSettings = ConversationSettingsService::getSettings();
$settings = $allSettings['sentiment_analysis'] ?? [];

echo "   Habilitado: " . ($settings['enabled'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "   Modelo: " . ($settings['model'] ?? 'N/A') . "\n";
echo "   Temperature: " . ($settings['temperature'] ?? 'N/A') . "\n";
echo "   Intervalo de verificação: " . ($settings['check_interval_hours'] ?? 'N/A') . " horas\n";
echo "   Idade máxima conversa: " . ($settings['max_conversation_age_days'] ?? 'N/A') . " dias\n";
echo "   Mín. mensagens para analisar: " . ($settings['min_messages_to_analyze'] ?? 'N/A') . "\n";
echo "   Analisar a cada X mensagens: " . ($settings['analyze_on_message_count'] ?? 'N/A') . "\n";
echo "   Analisar últimas X mensagens: " . ($settings['analyze_last_messages'] ?? 'toda conversa') . "\n";
echo "   Incluir emoções: " . ($settings['include_emotions'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "   Incluir urgência: " . ($settings['include_urgency'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "   Auto-tag negativo: " . ($settings['auto_tag_negative'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "   Limite custo diário: $" . ($settings['cost_limit_per_day'] ?? 'N/A') . "\n\n";

// 2. Verificar API Key
echo "2️⃣ API KEY:\n";
echo "-------------------\n";
$apiKey = \App\Models\Setting::get('openai_api_key');
if (empty($apiKey)) {
    $apiKey = getenv('OPENAI_API_KEY') ?: null;
}
echo "   API Key: " . ($apiKey ? '✅ Configurada (' . substr($apiKey, 0, 8) . '...)' : '❌ NÃO configurada') . "\n\n";

// 3. Verificar conversas
echo "3️⃣ CONVERSAS NO BANCO:\n";
echo "-------------------\n";
$totalConversations = Database::fetch("SELECT COUNT(*) as total FROM conversations")['total'];
echo "   Total de conversas: {$totalConversations}\n";

$openConversations = Database::fetch("SELECT COUNT(*) as total FROM conversations WHERE status = 'open'")['total'];
echo "   Conversas abertas: {$openConversations}\n";

$maxAgeDays = (int)($settings['max_conversation_age_days'] ?? 30);
$recentConversations = Database::fetch(
    "SELECT COUNT(*) as total FROM conversations WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
    [$maxAgeDays]
)['total'];
echo "   Conversas dos últimos {$maxAgeDays} dias: {$recentConversations}\n\n";

// 4. Verificar conversas abertas recentes
echo "4️⃣ CONVERSAS ABERTAS RECENTES:\n";
echo "-------------------\n";
$recentOpen = Database::fetchAll(
    "SELECT id, contact_id, status, created_at, updated_at 
     FROM conversations 
     WHERE status = 'open' 
     AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     ORDER BY updated_at DESC
     LIMIT 10",
    [$maxAgeDays]
);

if (empty($recentOpen)) {
    echo "   ⚠️ Nenhuma conversa aberta nos últimos {$maxAgeDays} dias\n\n";
} else {
    echo "   Encontradas " . count($recentOpen) . " conversas:\n\n";
    foreach ($recentOpen as $conv) {
        echo "   📧 Conversa #{$conv['id']} (Contato: {$conv['contact_id']})\n";
        echo "      Status: {$conv['status']}\n";
        echo "      Criada: {$conv['created_at']}\n";
        echo "      Atualizada: {$conv['updated_at']}\n";
        
        // Contar mensagens do contato
        $msgCount = Database::fetch(
            "SELECT COUNT(*) as total FROM messages WHERE conversation_id = ? AND sender_type = 'contact'",
            [$conv['id']]
        )['total'];
        echo "      Mensagens do contato: {$msgCount}\n";
        
        // Verificar se já foi analisada
        $intervalHours = (int)($settings['check_interval_hours'] ?? 5);
        $recentAnalysis = Database::fetch(
            "SELECT id, analyzed_at FROM conversation_sentiments 
             WHERE conversation_id = ? 
             AND analyzed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY analyzed_at DESC LIMIT 1",
            [$conv['id'], $intervalHours]
        );
        
        if ($recentAnalysis) {
            echo "      ✅ Já analisada: {$recentAnalysis['analyzed_at']}\n";
        } else {
            echo "      ⚠️ Ainda não analisada nas últimas {$intervalHours} horas\n";
        }
        
        echo "\n";
    }
}

// 5. Verificar a query exata do cron
echo "5️⃣ CONVERSAS ELEGÍVEIS PARA ANÁLISE:\n";
echo "-------------------\n";

if (!$settings['enabled']) {
    echo "   ⚠️ Análise desabilitada! Ative nas configurações.\n\n";
} else {
    $intervalHours = (int)($settings['check_interval_hours'] ?? 5);
    $minMessages = (int)($settings['min_messages_to_analyze'] ?? 3);
    
    $sql = "SELECT DISTINCT c.id, c.updated_at,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact') as msg_count
            FROM conversations c
            LEFT JOIN conversation_sentiments cs ON c.id = cs.conversation_id 
                AND cs.analyzed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            WHERE c.status = 'open'
            AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND cs.id IS NULL
            AND (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact') >= ?
            ORDER BY c.updated_at DESC
            LIMIT 50";
    
    $eligible = Database::fetchAll($sql, [$intervalHours, $maxAgeDays, $minMessages]);
    
    echo "   Parâmetros da query:\n";
    echo "   - Intervalo: {$intervalHours} horas\n";
    echo "   - Idade máxima: {$maxAgeDays} dias\n";
    echo "   - Mín. mensagens: {$minMessages}\n\n";
    
    if (empty($eligible)) {
        echo "   ❌ NENHUMA conversa elegível encontrada!\n\n";
        echo "   Motivos possíveis:\n";
        echo "   - Todas as conversas abertas já foram analisadas nas últimas {$intervalHours} horas\n";
        echo "   - Nenhuma conversa tem pelo menos {$minMessages} mensagens do contato\n";
        echo "   - Não há conversas abertas nos últimos {$maxAgeDays} dias\n\n";
        
        // Verificar conversas que quase se qualificam
        echo "   🔍 ANÁLISE DETALHADA:\n";
        echo "   -------------------\n\n";
        
        // Conversas abertas com poucas mensagens
        $lowMessages = Database::fetchAll(
            "SELECT c.id, 
                    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact') as msg_count
             FROM conversations c
             WHERE c.status = 'open'
             AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             AND (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact') < ?
             ORDER BY c.updated_at DESC
             LIMIT 5",
            [$maxAgeDays, $minMessages]
        );
        
        if (!empty($lowMessages)) {
            echo "   Conversas abertas com POUCAS mensagens:\n";
            foreach ($lowMessages as $conv) {
                echo "   - Conversa #{$conv['id']}: {$conv['msg_count']} mensagens (mínimo: {$minMessages})\n";
            }
            echo "\n";
        }
        
        // Conversas já analisadas recentemente
        $recentlyAnalyzed = Database::fetchAll(
            "SELECT cs.conversation_id, cs.analyzed_at, cs.sentiment_label,
                    (SELECT COUNT(*) FROM messages WHERE conversation_id = cs.conversation_id AND sender_type = 'contact') as msg_count
             FROM conversation_sentiments cs
             INNER JOIN conversations c ON c.id = cs.conversation_id
             WHERE c.status = 'open'
             AND cs.analyzed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY cs.analyzed_at DESC
             LIMIT 5",
            [$intervalHours]
        );
        
        if (!empty($recentlyAnalyzed)) {
            echo "   Conversas JÁ ANALISADAS recentemente:\n";
            foreach ($recentlyAnalyzed as $conv) {
                echo "   - Conversa #{$conv['conversation_id']}: analisada em {$conv['analyzed_at']} ({$conv['sentiment_label']}, {$conv['msg_count']} msgs)\n";
            }
            echo "\n";
        }
        
    } else {
        echo "   ✅ {$eligible} conversas elegíveis:\n\n";
        foreach ($eligible as $conv) {
            echo "   - Conversa #{$conv['id']}: {$conv['msg_count']} mensagens, atualizada em {$conv['updated_at']}\n";
        }
        echo "\n";
    }
}

// 6. Verificar custo diário
echo "6️⃣ CUSTO DIÁRIO:\n";
echo "-------------------\n";
$todayCost = Database::fetch("SELECT SUM(cost) as total FROM conversation_sentiments WHERE DATE(analyzed_at) = CURDATE()")['total'] ?? 0;
$limit = (float)($settings['cost_limit_per_day'] ?? 0);
echo "   Custo hoje: $" . number_format($todayCost, 4) . "\n";
echo "   Limite: $" . number_format($limit, 2) . "\n";
echo "   Status: " . ($todayCost < $limit ? '✅ OK' : '❌ LIMITE ATINGIDO') . "\n\n";

// 7. Análises realizadas
echo "7️⃣ HISTÓRICO DE ANÁLISES:\n";
echo "-------------------\n";
$totalAnalyses = Database::fetch("SELECT COUNT(*) as total FROM conversation_sentiments")['total'];
echo "   Total de análises: {$totalAnalyses}\n";

if ($totalAnalyses > 0) {
    $lastAnalysis = Database::fetch("SELECT analyzed_at FROM conversation_sentiments ORDER BY analyzed_at DESC LIMIT 1")['analyzed_at'];
    echo "   Última análise: {$lastAnalysis}\n";
    
    $todayAnalyses = Database::fetch("SELECT COUNT(*) as total FROM conversation_sentiments WHERE DATE(analyzed_at) = CURDATE()")['total'];
    echo "   Análises hoje: {$todayAnalyses}\n";
}

echo "\n========================================\n";
echo "FIM DO DEBUG\n";
echo "========================================\n";
