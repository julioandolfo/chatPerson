<?php
/**
 * Service CoachingLearningService
 * Sistema de aprendizado contínuo do coaching via RAG
 * Extrai conhecimento de hints bem-sucedidos
 */

namespace App\Services;

use App\Models\RealtimeCoachingHint;
use App\Models\CoachingConversationImpact;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\Database;
use App\Helpers\PostgreSQL;

class CoachingLearningService
{
    /**
     * Processar hints úteis e extrair conhecimento
     * (Executar diariamente via cron)
     * 
     * @param int|null $daysAgo Quantos dias atrás processar (padrão: 1 = ontem)
     * @return array Estatísticas do processamento
     */
    public static function processSuccessfulHints(?int $daysAgo = 1): array
    {
        if (!PostgreSQL::isAvailable()) {
            return [
                'error' => 'PostgreSQL não disponível',
                'processed' => 0
            ];
        }
        
        $date = date('Y-m-d', strtotime("-{$daysAgo} days"));
        $dateStart = $date . ' 00:00:00';
        $dateEnd = $date . ' 23:59:59';
        
        // 1. Buscar hints marcados como "helpful" no período
        $sql = "SELECT rch.*, c.status as conversation_status, c.contact_id
                FROM realtime_coaching_hints rch
                INNER JOIN conversations c ON rch.conversation_id = c.id
                WHERE rch.feedback = 'helpful'
                AND rch.created_at >= :date_start 
                AND rch.created_at <= :date_end
                AND rch.id NOT IN (
                    SELECT hint_id FROM coaching_knowledge_base WHERE hint_id = rch.id
                )";
        
        $hints = Database::fetchAll($sql, [
            'date_start' => $dateStart,
            'date_end' => $dateEnd
        ]);
        
        $processed = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($hints as $hint) {
            try {
                // 2. Verificar resultado da conversa
                $impact = CoachingConversationImpact::getByConversation($hint['conversation_id']);
                
                // 3. Calcular score de qualidade (1-5)
                $score = self::calculateQualityScore($hint, $impact);
                
                // 4. Se score >= 4, extrair conhecimento para RAG
                if ($score >= 4) {
                    self::extractKnowledgeToRAG($hint, $impact, $score);
                    $processed++;
                } else {
                    $skipped++;
                }
                
            } catch (\Exception $e) {
                $errors++;
                error_log("CoachingLearning: Erro ao processar hint #{$hint['id']}: " . $e->getMessage());
            }
        }
        
        return [
            'date' => $date,
            'total_hints' => count($hints),
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
    
    /**
     * Calcular score de qualidade de um hint (1-5)
     */
    private static function calculateQualityScore(array $hint, ?array $impact): int
    {
        $score = 3; // Base
        
        // +1 se conversa converteu
        if ($impact && $impact['conversation_outcome'] === 'converted') {
            $score += 1;
        }
        
        // +1 se performance melhorou significativamente
        if ($impact && $impact['performance_improvement_score'] >= 4.0) {
            $score += 1;
        }
        
        // +1 se sugestões foram usadas
        if ($impact && $impact['suggestions_used'] > 0) {
            $score += 0.5;
        }
        
        // +0.5 se tem valor de venda
        if ($impact && $impact['sales_value'] > 0) {
            $score += 0.5;
        }
        
        return (int)min(5, $score);
    }
    
    /**
     * Extrair conhecimento para RAG (PostgreSQL)
     */
    private static function extractKnowledgeToRAG(array $hint, ?array $impact, int $score): bool
    {
        try {
            // Buscar mensagem do cliente que gerou o hint
            $clientMessage = Message::find($hint['message_id']);
            if (!$clientMessage) {
                throw new \Exception("Mensagem não encontrada: {$hint['message_id']}");
            }
            
            // Buscar contexto da conversa (5 mensagens anteriores)
            $contextSql = "SELECT content, sender_type 
                          FROM messages 
                          WHERE conversation_id = :conversation_id 
                          AND id <= :message_id
                          ORDER BY created_at DESC 
                          LIMIT 5";
            
            $contextMessages = Database::fetchAll($contextSql, [
                'conversation_id' => $hint['conversation_id'],
                'message_id' => $hint['message_id']
            ]);
            
            $contextText = '';
            foreach (array_reverse($contextMessages) as $msg) {
                $sender = $msg['sender_type'] === 'contact' ? 'Cliente' : 'Agente';
                $contextText .= "{$sender}: {$msg['content']}\n";
            }
            
            // Buscar resposta bem-sucedida (próxima mensagem do agente após o hint)
            $successfulResponseSql = "SELECT content 
                                      FROM messages 
                                      WHERE conversation_id = :conversation_id 
                                      AND sender_type = 'agent'
                                      AND created_at > :hint_created_at
                                      ORDER BY created_at ASC 
                                      LIMIT 1";
            
            $responseMsg = Database::fetch($successfulResponseSql, [
                'conversation_id' => $hint['conversation_id'],
                'hint_created_at' => $hint['created_at']
            ]);
            
            $successfulResponse = $responseMsg ? $responseMsg['content'] : $hint['hint_text'];
            
            // Gerar embedding
            $embedding = EmbeddingService::generate($clientMessage['content'] . ' ' . $contextText);
            if (!$embedding) {
                throw new \Exception("Falha ao gerar embedding");
            }
            
            // Converter embedding para formato PostgreSQL
            $embeddingStr = '[' . implode(',', $embedding) . ']';
            
            // Buscar dados adicionais da conversa
            $conversation = Conversation::find($hint['conversation_id']);
            $department = null;
            $funnelStage = null;
            
            if ($conversation && $conversation['agent_id']) {
                $agent = \App\Models\User::find($conversation['agent_id']);
                $department = $agent['department'] ?? null;
                
                // Se tiver funil/etapa
                if ($conversation['funnel_step_id']) {
                    $step = \App\Models\FunnelStep::find($conversation['funnel_step_id']);
                    $funnelStage = $step ? $step['name'] : null;
                }
            }
            
            // Inserir no PostgreSQL
            $pgsql = PostgreSQL::getInstance();
            
            $insertSql = "INSERT INTO coaching_knowledge_base (
                            situation_type, client_message, conversation_context,
                            successful_response, agent_action, conversation_outcome,
                            sales_value, time_to_outcome_minutes, agent_id, conversation_id,
                            hint_id, department, funnel_stage, feedback_score,
                            embedding, created_at, updated_at
                          ) VALUES (
                            :situation_type, :client_message, :conversation_context,
                            :successful_response, :agent_action, :conversation_outcome,
                            :sales_value, :time_to_outcome_minutes, :agent_id, :conversation_id,
                            :hint_id, :department, :funnel_stage, :feedback_score,
                            :embedding, NOW(), NOW()
                          )";
            
            $stmt = $pgsql->prepare($insertSql);
            $stmt->execute([
                'situation_type' => $hint['hint_type'],
                'client_message' => $clientMessage['content'],
                'conversation_context' => $contextText,
                'successful_response' => $successfulResponse,
                'agent_action' => 'applied_hint',
                'conversation_outcome' => $impact['conversation_outcome'] ?? null,
                'sales_value' => $impact['sales_value'] ?? 0,
                'time_to_outcome_minutes' => $impact['conversion_time_minutes'] ?? null,
                'agent_id' => $hint['agent_id'],
                'conversation_id' => $hint['conversation_id'],
                'hint_id' => $hint['id'],
                'department' => $department,
                'funnel_stage' => $funnelStage,
                'feedback_score' => $score,
                'embedding' => $embeddingStr
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("CoachingLearning: Erro ao extrair conhecimento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar conhecimento similar no RAG
     * Usado ao gerar novos hints
     */
    public static function findSimilarKnowledge(string $context, int $limit = 5): array
    {
        if (!PostgreSQL::isAvailable()) {
            return [];
        }
        
        try {
            // Gerar embedding do contexto
            $embedding = EmbeddingService::generate($context);
            if (!$embedding) {
                return [];
            }
            
            $embeddingStr = '[' . implode(',', $embedding) . ']';
            
            // Busca vetorial com cosine similarity
            $pgsql = PostgreSQL::getInstance();
            
            $sql = "SELECT 
                        id, situation_type, client_message, successful_response,
                        conversation_outcome, sales_value, feedback_score, times_reused,
                        1 - (embedding <=> :embedding::vector) as similarity
                    FROM coaching_knowledge_base
                    WHERE feedback_score >= 4
                    ORDER BY embedding <=> :embedding::vector
                    LIMIT :limit";
            
            $stmt = $pgsql->prepare($sql);
            $stmt->execute([
                'embedding' => $embeddingStr,
                'limit' => $limit
            ]);
            
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Filtrar apenas com similaridade > 0.7
            return array_filter($results, function($r) {
                return $r['similarity'] > 0.7;
            });
            
        } catch (\Exception $e) {
            error_log("CoachingLearning: Erro ao buscar conhecimento similar: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Identificar novos padrões e sugerir melhorias
     * (Executar semanalmente)
     */
    public static function discoverPatterns(): array
    {
        if (!PostgreSQL::isAvailable()) {
            return [];
        }
        
        try {
            $pgsql = PostgreSQL::getInstance();
            
            // Agrupar por tipo de situação e analisar
            $sql = "SELECT 
                        situation_type,
                        COUNT(*) as count,
                        AVG(feedback_score) as avg_score,
                        AVG(success_rate) as avg_success_rate,
                        SUM(times_reused) as total_reuses
                    FROM coaching_knowledge_base
                    WHERE created_at >= NOW() - INTERVAL '30 days'
                    GROUP BY situation_type
                    ORDER BY count DESC";
            
            $patterns = $pgsql->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            
            // Identificar padrões emergentes (novos tipos não cobertos)
            // Identificar técnicas mais efetivas
            // Sugerir melhorias nos prompts base
            
            return $patterns;
            
        } catch (\Exception $e) {
            error_log("CoachingLearning: Erro ao descobrir padrões: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Atualizar contador de reutilização de conhecimento
     */
    public static function incrementReuseCount(int $knowledgeId): void
    {
        if (!PostgreSQL::isAvailable()) return;
        
        try {
            $pgsql = PostgreSQL::getInstance();
            
            $sql = "UPDATE coaching_knowledge_base 
                    SET times_reused = times_reused + 1,
                        updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $pgsql->prepare($sql);
            $stmt->execute(['id' => $knowledgeId]);
            
        } catch (\Exception $e) {
            error_log("CoachingLearning: Erro ao incrementar reuso: " . $e->getMessage());
        }
    }
}
