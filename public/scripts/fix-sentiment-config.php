<?php
/**
 * Script para sugerir correção nas configurações de análise de sentimento
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
echo "ANALISAR E SUGERIR CORREÇÕES\n";
echo "========================================\n\n";

// Obter configurações atuais
$settings = ConversationSettingsService::getSettings();
$sentiment = $settings['sentiment_analysis'];

echo "📊 CONFIGURAÇÕES ATUAIS:\n";
echo "-------------------\n";
echo "Habilitado: " . ($sentiment['enabled'] ? '✅ SIM' : '❌ NÃO') . "\n";
echo "Mín. mensagens: " . ($sentiment['min_messages_to_analyze'] ?? 'N/A') . "\n";
echo "Intervalo: " . ($sentiment['check_interval_hours'] ?? 'N/A') . " horas\n";
echo "Idade máxima: " . ($sentiment['max_conversation_age_days'] ?? 'N/A') . " dias\n\n";

// Analisar conversas reais
echo "🔍 ANALISANDO CONVERSAS REAIS:\n";
echo "-------------------\n";

$stats = Database::fetch("
    SELECT 
        COUNT(*) as total_conversations,
        AVG(msg_count) as avg_messages,
        MAX(msg_count) as max_messages,
        MIN(msg_count) as min_messages
    FROM (
        SELECT c.id, COUNT(m.id) as msg_count
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id AND m.sender_type = 'contact'
        WHERE c.status = 'open'
        AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY c.id
    ) as conversation_stats
");

if ($stats && $stats['total_conversations'] > 0) {
    echo "Total de conversas abertas (últimos 7 dias): " . $stats['total_conversations'] . "\n";
    echo "Média de mensagens do contato: " . round($stats['avg_messages'], 1) . "\n";
    echo "Máximo de mensagens: " . $stats['max_messages'] . "\n";
    echo "Mínimo de mensagens: " . $stats['min_messages'] . "\n\n";
    
    // Distribuição por faixas
    echo "📊 DISTRIBUIÇÃO POR FAIXAS:\n";
    $ranges = Database::fetchAll("
        SELECT 
            CASE 
                WHEN msg_count < 3 THEN '0-2 mensagens'
                WHEN msg_count >= 3 AND msg_count < 10 THEN '3-9 mensagens'
                WHEN msg_count >= 10 AND msg_count < 20 THEN '10-19 mensagens'
                WHEN msg_count >= 20 AND msg_count < 50 THEN '20-49 mensagens'
                WHEN msg_count >= 50 AND msg_count < 100 THEN '50-99 mensagens'
                ELSE '100+ mensagens'
            END as range_label,
            COUNT(*) as count
        FROM (
            SELECT c.id, COUNT(m.id) as msg_count
            FROM conversations c
            LEFT JOIN messages m ON c.id = m.conversation_id AND m.sender_type = 'contact'
            WHERE c.status = 'open'
            AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY c.id
        ) as conversation_stats
        GROUP BY range_label
        ORDER BY 
            CASE range_label
                WHEN '0-2 mensagens' THEN 1
                WHEN '3-9 mensagens' THEN 2
                WHEN '10-19 mensagens' THEN 3
                WHEN '20-49 mensagens' THEN 4
                WHEN '50-99 mensagens' THEN 5
                ELSE 6
            END
    ");
    
    foreach ($ranges as $range) {
        $percent = ($range['count'] / $stats['total_conversations']) * 100;
        echo "   " . str_pad($range['range_label'], 20) . ": " . $range['count'] . " (" . round($percent, 1) . "%)\n";
    }
    echo "\n";
} else {
    echo "⚠️ Nenhuma conversa aberta encontrada nos últimos 7 dias.\n\n";
}

// Verificar problemas
echo "🔍 PROBLEMAS IDENTIFICADOS:\n";
echo "-------------------\n";

$minMessages = (int)($sentiment['min_messages_to_analyze'] ?? 3);
$problems = [];
$suggestions = [];

if (!$sentiment['enabled']) {
    $problems[] = "❌ Análise está DESABILITADA";
    $suggestions[] = "Habilite a análise de sentimento nas configurações";
}

if ($minMessages >= 100) {
    $problems[] = "❌ CRÍTICO: Mínimo de mensagens muito alto ({$minMessages})";
    $suggestions[] = "Reduzir para 5-10 mensagens (recomendado: 5)";
    
    // Ver quantas conversas teriam
    if ($stats && $stats['total_conversations'] > 0) {
        $eligible = Database::fetch("
            SELECT COUNT(*) as count
            FROM (
                SELECT c.id, COUNT(m.id) as msg_count
                FROM conversations c
                LEFT JOIN messages m ON c.id = m.conversation_id AND m.sender_type = 'contact'
                WHERE c.status = 'open'
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY c.id
                HAVING COUNT(m.id) >= ?
            ) as conversation_stats
        ", [$minMessages]);
        
        $eligibleCount = $eligible['count'] ?? 0;
        $percent = ($eligibleCount / $stats['total_conversations']) * 100;
        
        echo "   Com {$minMessages} mensagens mínimas:\n";
        echo "   - Apenas {$eligibleCount} de {$stats['total_conversations']} conversas se qualificam (" . round($percent, 1) . "%)\n\n";
    }
} elseif ($minMessages > 50) {
    $problems[] = "⚠️ Mínimo de mensagens muito alto ({$minMessages})";
    $suggestions[] = "Considere reduzir para 5-20 mensagens";
}

if (empty($problems)) {
    echo "✅ Nenhum problema identificado!\n\n";
} else {
    foreach ($problems as $problem) {
        echo $problem . "\n";
    }
    echo "\n";
}

// Sugestões
if (!empty($suggestions)) {
    echo "💡 SUGESTÕES DE CORREÇÃO:\n";
    echo "-------------------\n";
    foreach ($suggestions as $i => $suggestion) {
        echo ($i + 1) . ". " . $suggestion . "\n";
    }
    echo "\n";
}

// Valores recomendados
echo "✅ VALORES RECOMENDADOS:\n";
echo "-------------------\n";
echo "Mín. mensagens para analisar: 5-10 (recomendado: 5)\n";
echo "   - Captura a maioria das conversas\n";
echo "   - Já há contexto suficiente para análise\n";
echo "   - Não desperdiça créditos em conversas muito curtas\n\n";

echo "Intervalo de verificação: 10-24 horas\n";
echo "   - Evita análises muito frequentes\n";
echo "   - Reduz custos\n";
echo "   - Ainda captura mudanças de sentimento\n\n";

echo "Idade máxima da conversa: 3-7 dias\n";
echo "   - Foca em conversas recentes\n";
echo "   - Sentimento é mais relevante\n\n";

echo "Analisar a cada X mensagens: 100\n";
echo "   - Para análise incremental\n";
echo "   - Reanalisa quando há muitas mensagens novas\n\n";

// Teste com valores recomendados
if ($stats && $stats['total_conversations'] > 0) {
    echo "🧪 SIMULAÇÃO COM VALORES RECOMENDADOS:\n";
    echo "-------------------\n";
    
    $recommendedMin = 5;
    $eligible = Database::fetch("
        SELECT COUNT(*) as count
        FROM (
            SELECT c.id
            FROM conversations c
            WHERE c.status = 'open'
            AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact') >= ?
        ) as conversation_stats
    ", [$recommendedMin]);
    
    $eligibleCount = $eligible['count'] ?? 0;
    $percent = $eligibleCount > 0 ? ($eligibleCount / $stats['total_conversations']) * 100 : 0;
    
    echo "Com mínimo de {$recommendedMin} mensagens:\n";
    echo "   ✅ {$eligibleCount} de {$stats['total_conversations']} conversas se qualificam (" . round($percent, 1) . "%)\n\n";
    
    // Estimar custo
    $avgTokens = 500; // Estimativa conservadora
    $costPerAnalysis = 0.001; // ~$0.001 com GPT-3.5-turbo
    $estimatedCost = $eligibleCount * $costPerAnalysis;
    
    echo "💰 ESTIMATIVA DE CUSTO:\n";
    echo "   Análises: {$eligibleCount}\n";
    echo "   Custo estimado: $" . number_format($estimatedCost, 4) . "\n";
    echo "   (Muito abaixo do limite de $" . number_format($sentiment['cost_limit_per_day'] ?? 5, 2) . "/dia)\n\n";
}

echo "========================================\n";
echo "PARA APLICAR AS CORREÇÕES:\n";
echo "========================================\n";
echo "1. Acesse: http://seu-dominio/settings?tab=action-buttons\n";
echo "2. Role até 'Análise de Sentimento'\n";
echo "3. Altere 'Mín. Mensagens para Analisar' para 5\n";
echo "4. Clique em 'Salvar Configurações' no final da página\n";
echo "5. Execute novamente: php public/scripts/analyze-sentiments.php\n\n";
