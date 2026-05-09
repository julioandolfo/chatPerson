<?php
/**
 * SLAResponseTimeHelper
 *
 * Centraliza o subquery usado para calcular o tempo médio de resposta
 * do agente por conversa, aplicando as regras de qualificação:
 *
 *   - Considerar apenas conversas com no mínimo MIN_TOTAL_MESSAGES
 *     mensagens trocadas (cliente + agente).
 *   - Ignorar as EXCLUDE_LAST_N mensagens mais recentes da conversa
 *     (qualquer remetente) — geralmente agradecimentos/despedidas.
 *
 * Isso evita inflar a métrica com conversas curtíssimas e com a fase
 * final de "obrigado / até mais", que não reflete velocidade real de
 * atendimento.
 */

namespace App\Services;

class SLAResponseTimeHelper
{
    public const MIN_TOTAL_MESSAGES = 5;
    public const EXCLUDE_LAST_N    = 3;

    /**
     * Retorna o trecho SQL do subquery que produz, por conversa, o
     * tempo médio (em minutos) entre a mensagem do contato e a
     * primeira resposta do agente — já com as regras aplicadas.
     *
     * Uso típico:
     *
     *     LEFT JOIN " . SLAResponseTimeHelper::buildPerConversationAvgSubquery('rt')
     *     . " ON rt.conversation_id = c.id
     *
     * O alias é configurável para permitir múltiplos joins na mesma query.
     * Colunas expostas: `conversation_id`, `response_time_minutes`.
     */
    public static function buildPerConversationAvgSubquery(string $alias = 'response_times'): string
    {
        $minTotal     = self::MIN_TOTAL_MESSAGES;
        $excludeLastN = self::EXCLUDE_LAST_N;

        return "(
            SELECT
                m1.conversation_id,
                AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) AS response_time_minutes
            FROM (
                SELECT id, conversation_id, sender_type, created_at,
                       ROW_NUMBER() OVER (PARTITION BY conversation_id ORDER BY created_at DESC) AS rn_desc,
                       COUNT(*)  OVER (PARTITION BY conversation_id) AS total_msgs
                FROM messages
            ) m1
            INNER JOIN (
                SELECT id, conversation_id, sender_type, created_at,
                       ROW_NUMBER() OVER (PARTITION BY conversation_id ORDER BY created_at DESC) AS rn_desc
                FROM messages
            ) m2
                ON m2.conversation_id = m1.conversation_id
               AND m2.sender_type = 'agent'
               AND m2.created_at > m1.created_at
               AND m2.rn_desc > {$excludeLastN}
               AND m2.created_at = (
                   SELECT MIN(m3.created_at)
                   FROM messages m3
                   WHERE m3.conversation_id = m1.conversation_id
                     AND m3.sender_type = 'agent'
                     AND m3.created_at > m1.created_at
               )
            WHERE m1.sender_type   = 'contact'
              AND m1.total_msgs   >= {$minTotal}
              AND m1.rn_desc       > {$excludeLastN}
            GROUP BY m1.conversation_id
        ) {$alias}";
    }

    /**
     * Aplica as regras a uma lista de mensagens (já carregada e ordenada
     * cronologicamente ASC) e devolve apenas as mensagens elegíveis para
     * cálculo de tempo médio de resposta corrente.
     *
     * Retorna [] se a conversa não tem mensagens suficientes (< MIN_TOTAL_MESSAGES).
     *
     * Cada item preserva exatamente as chaves recebidas no input.
     */
    public static function filterEligibleMessages(array $messagesAsc): array
    {
        $total = count($messagesAsc);
        if ($total < self::MIN_TOTAL_MESSAGES) {
            return [];
        }
        $cutoff = $total - self::EXCLUDE_LAST_N;
        if ($cutoff <= 0) {
            return [];
        }
        return array_slice($messagesAsc, 0, $cutoff);
    }
}
