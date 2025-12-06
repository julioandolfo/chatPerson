<?php
/**
 * Service AIAgentSelectorService
 * Seleção inteligente de agentes baseada em contexto
 */

namespace App\Services;

use App\Models\AIAssistantFeature;
use App\Models\AIAssistantUserSetting;
use App\Models\AIAssistantFeatureAgent;
use App\Models\AIAgent;

class AIAgentSelectorService
{
    /**
     * Selecionar melhor agente para uma funcionalidade baseado em contexto
     */
    public static function selectAgent(
        int $userId,
        string $featureKey,
        array $context = []
    ): ?int {
        // 1. Verificar preferência do usuário
        $userPreferredAgent = AIAssistantUserSetting::getUserPreferredAgent($userId, $featureKey);
        if ($userPreferredAgent) {
            $agent = AIAgent::find($userPreferredAgent);
            if ($agent && $agent['enabled']) {
                return $userPreferredAgent;
            }
        }

        // 2. Obter funcionalidade
        $feature = AIAssistantFeature::getByKey($featureKey);
        if (!$feature || !$feature['auto_select_agent']) {
            // Se não tem seleção automática, usar agente padrão
            return $feature['default_ai_agent_id'] ? (int)$feature['default_ai_agent_id'] : null;
        }

        // 3. Tentar encontrar agente baseado em condições
        $bestAgent = AIAssistantFeatureAgent::findBestAgent($featureKey, $context);
        if ($bestAgent) {
            return $bestAgent;
        }

        // 4. Usar agente padrão da funcionalidade
        return $feature['default_ai_agent_id'] ? (int)$feature['default_ai_agent_id'] : null;
    }

    /**
     * Construir contexto da conversa para seleção de agente
     */
    public static function buildContext(int $conversationId): array
    {
        $conversation = \App\Models\Conversation::findWithRelations($conversationId);
        if (!$conversation) {
            return [];
        }

        // Obter tags da conversa
        $tags = [];
        if (!empty($conversation['tags'])) {
            foreach ($conversation['tags'] as $tag) {
                $tags[] = $tag['name'];
            }
        }

        // Analisar sentimento (se disponível)
        $sentiment = self::analyzeSentiment($conversationId);

        // Detectar urgência
        $urgency = self::detectUrgency($conversation);

        return [
            'conversation_id' => $conversationId,
            'channel' => $conversation['channel'] ?? null,
            'status' => $conversation['status'] ?? null,
            'department_id' => $conversation['department_id'] ?? null,
            'funnel_id' => $conversation['funnel_id'] ?? null,
            'funnel_stage_id' => $conversation['funnel_stage_id'] ?? null,
            'tags' => $tags,
            'sentiment' => $sentiment,
            'urgency' => $urgency,
            'agent_id' => $conversation['agent_id'] ?? null,
            'unread_count' => $conversation['unread_count'] ?? 0
        ];
    }

    /**
     * Analisar sentimento básico baseado em palavras-chave e padrões
     */
    private static function analyzeSentiment(int $conversationId): string
    {
        // Obter últimas mensagens da conversa
        $messages = \App\Models\Message::getMessagesWithSenderDetails($conversationId, 10);
        
        if (empty($messages)) {
            return 'neutral';
        }
        
        // Palavras-chave para análise de sentimento
        $positiveKeywords = [
            'obrigado', 'obrigada', 'obrigad', 'gratidão', 'gratid', 'perfeito', 'perfeita',
            'excelente', 'ótimo', 'ótima', 'maravilhoso', 'maravilhosa', 'adorei', 'amei',
            'satisfeito', 'satisfeita', 'feliz', 'alegre', 'content', 'contente', 'bom', 'boa',
            'ótimo atendimento', 'parabéns', 'sucesso', 'resolvido', 'resolvida'
        ];
        
        $negativeKeywords = [
            'ruim', 'péssimo', 'péssima', 'horrível', 'terrível', 'desapontado', 'desapontada',
            'insatisfeito', 'insatisfeita', 'reclamação', 'reclamar', 'problema', 'erro',
            'não funciona', 'não funcionou', 'lento', 'lenta', 'demora', 'atraso',
            'cancelar', 'cancelamento', 'devolução', 'reembolso', 'processo', 'processar',
            'frustrado', 'frustrada', 'irritado', 'irritada', 'nervoso', 'nervosa',
            'não gostei', 'não gostou', 'péssimo atendimento', 'ruim atendimento'
        ];
        
        $urgentKeywords = [
            'urgente', 'urgência', 'urgentemente', 'imediatamente', 'agora', 'já',
            'emergência', 'emergente', 'crítico', 'crítica', 'importante', 'prioridade',
            'preciso', 'precisa', 'necessito', 'necessita', 'hoje', 'hoje mesmo',
            'asap', 'asap', 'rápido', 'rápida', 'logo', 'logo mais'
        ];
        
        $positiveCount = 0;
        $negativeCount = 0;
        $urgentCount = 0;
        $totalWords = 0;
        
        // Analisar mensagens do contato (não do agente)
        foreach ($messages as $message) {
            if ($message['sender_type'] !== 'contact') {
                continue;
            }
            
            $content = mb_strtolower($message['content'] ?? '', 'UTF-8');
            $words = preg_split('/\s+/', $content);
            $totalWords += count($words);
            
            // Contar palavras-chave positivas
            foreach ($positiveKeywords as $keyword) {
                if (mb_stripos($content, $keyword) !== false) {
                    $positiveCount++;
                }
            }
            
            // Contar palavras-chave negativas
            foreach ($negativeKeywords as $keyword) {
                if (mb_stripos($content, $keyword) !== false) {
                    $negativeCount++;
                }
            }
            
            // Contar palavras-chave de urgência
            foreach ($urgentKeywords as $keyword) {
                if (mb_stripos($content, $keyword) !== false) {
                    $urgentCount++;
                }
            }
        }
        
        // Determinar sentimento
        if ($negativeCount > $positiveCount && $negativeCount > 0) {
            return 'negative';
        } elseif ($positiveCount > $negativeCount && $positiveCount > 0) {
            return 'positive';
        } else {
            return 'neutral';
        }
    }

    /**
     * Detectar urgência baseado em padrões e contexto
     */
    private static function detectUrgency(array $conversation): string
    {
        $conversationId = $conversation['id'] ?? null;
        if (!$conversationId) {
            return 'medium';
        }
        
        // Obter últimas mensagens
        $messages = \App\Models\Message::getMessagesWithSenderDetails($conversationId, 5);
        
        if (empty($messages)) {
            return 'medium';
        }
        
        // Palavras-chave de urgência
        $criticalKeywords = ['urgente', 'emergência', 'crítico', 'crítica', 'asap', 'imediatamente', 'agora'];
        $highKeywords = ['importante', 'prioridade', 'preciso', 'necessito', 'hoje', 'hoje mesmo', 'rápido'];
        $lowKeywords = ['quando possível', 'sem pressa', 'tranquilo', 'tranquila', 'depois'];
        
        $criticalCount = 0;
        $highCount = 0;
        $lowCount = 0;
        
        // Verificar mensagens não lidas
        $unreadCount = $conversation['unread_count'] ?? 0;
        
        // Analisar conteúdo das mensagens
        foreach ($messages as $message) {
            if ($message['sender_type'] !== 'contact') {
                continue;
            }
            
            $content = mb_strtolower($message['content'] ?? '', 'UTF-8');
            
            foreach ($criticalKeywords as $keyword) {
                if (mb_stripos($content, $keyword) !== false) {
                    $criticalCount++;
                }
            }
            
            foreach ($highKeywords as $keyword) {
                if (mb_stripos($content, $keyword) !== false) {
                    $highCount++;
                }
            }
            
            foreach ($lowKeywords as $keyword) {
                if (mb_stripos($content, $keyword) !== false) {
                    $lowCount++;
                }
            }
        }
        
        // Determinar urgência
        if ($criticalCount > 0 || $unreadCount > 5) {
            return 'critical';
        } elseif ($highCount > 0 || $unreadCount > 2) {
            return 'high';
        } elseif ($lowCount > 0) {
            return 'low';
        } else {
            return 'medium';
        }
    }

    /**
     * Obter informações do agente selecionado
     */
    public static function getAgentInfo(int $agentId): ?array
    {
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            return null;
        }

        return [
            'id' => $agent['id'],
            'name' => $agent['name'],
            'agent_type' => $agent['agent_type'],
            'model' => $agent['model'],
            'temperature' => $agent['temperature'],
            'max_tokens' => $agent['max_tokens']
        ];
    }
}

