<?php
/**
 * AIUsageLogger
 *
 * Ponto único de registro de consumo de IA (tokens/custo) na tabela
 * unificada `ai_usage_logs`. Usado pelos recursos que antes não eram
 * rastreados pelo relatório /dashboard/ai (embeddings, TTS, transcrição de
 * áudio de mensagens, extração de memória, visão/DALL·E, agentes Kanban).
 *
 * É resiliente por design: qualquer falha de registro é silenciada (apenas
 * logada em arquivo) para nunca interromper o fluxo principal. A tabela é
 * criada sob demanda, então funciona mesmo antes de rodar a migration.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;

class AIUsageLogger
{
    private static bool $tableEnsured = false;

    /**
     * Preço por 1 milhão de tokens (USD). Entrada e saída.
     * Valores aproximados — servem para estimativa de custo no relatório.
     */
    private const CHAT_PRICES = [
        'gpt-4'                  => ['in' => 30.00, 'out' => 60.00],
        'gpt-4-turbo'            => ['in' => 10.00, 'out' => 30.00],
        'gpt-4o'                 => ['in' => 2.50,  'out' => 10.00],
        'gpt-4o-mini'            => ['in' => 0.15,  'out' => 0.60],
        'gpt-3.5-turbo'          => ['in' => 0.50,  'out' => 1.50],
    ];

    /** Preço por 1 milhão de tokens (USD) para embeddings. */
    private const EMBEDDING_PRICES = [
        'text-embedding-3-small' => 0.02,
        'text-embedding-3-large' => 0.13,
        'text-embedding-ada-002' => 0.10,
    ];

    /**
     * Registrar um consumo de IA.
     *
     * @param string $feature Ex.: 'embedding', 'tts', 'audio_transcription',
     *                        'agent_memory', 'mockup_generation', 'kanban_agent'
     * @param array  $data    tokens_used, prompt_tokens, completion_tokens, cost,
     *                        model, provider, conversation_id, user_id, metadata
     */
    public static function record(string $feature, array $data = []): void
    {
        try {
            self::ensureTable();

            $sql = "INSERT INTO ai_usage_logs
                        (feature, provider, model, tokens_used, prompt_tokens,
                         completion_tokens, cost, conversation_id, user_id, metadata, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            Database::insert($sql, [
                $feature,
                $data['provider'] ?? 'openai',
                $data['model'] ?? null,
                (int)($data['tokens_used'] ?? 0),
                (int)($data['prompt_tokens'] ?? 0),
                (int)($data['completion_tokens'] ?? 0),
                round((float)($data['cost'] ?? 0), 6),
                $data['conversation_id'] ?? null,
                $data['user_id'] ?? null,
                isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            // Nunca interromper o fluxo principal por causa do registro de uso.
            Logger::error('AIUsageLogger::record falhou (' . $feature . '): ' . $e->getMessage());
        }
    }

    /**
     * Estimar custo (USD) de uma chamada de chat a partir dos tokens.
     */
    public static function estimateChatCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $prices = self::CHAT_PRICES[$model] ?? null;
        if ($prices === null) {
            // Normalizar variações de nome (ex.: gpt-4o-2024-..., gpt-4-0613)
            foreach (self::CHAT_PRICES as $key => $p) {
                if (str_starts_with($model, $key)) {
                    $prices = $p;
                    break;
                }
            }
        }
        // Fallback conservador: usar tarifa do gpt-3.5-turbo.
        $prices = $prices ?? self::CHAT_PRICES['gpt-3.5-turbo'];

        return ($promptTokens / 1000000) * $prices['in']
             + ($completionTokens / 1000000) * $prices['out'];
    }

    /**
     * Estimar custo (USD) de uma chamada de embedding a partir dos tokens.
     */
    public static function estimateEmbeddingCost(string $model, int $tokens): float
    {
        $price = self::EMBEDDING_PRICES[$model] ?? self::EMBEDDING_PRICES['text-embedding-3-small'];
        return ($tokens / 1000000) * $price;
    }

    /**
     * Criar a tabela sob demanda (idempotente). Mantém o recurso funcional
     * mesmo que a migration 151 ainda não tenha sido executada.
     */
    private static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS ai_usage_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            feature VARCHAR(80) NOT NULL,
            provider VARCHAR(40) NOT NULL DEFAULT 'openai',
            model VARCHAR(80) NULL,
            tokens_used INT DEFAULT 0,
            prompt_tokens INT DEFAULT 0,
            completion_tokens INT DEFAULT 0,
            cost DECIMAL(12,6) DEFAULT 0,
            conversation_id INT NULL,
            user_id INT NULL,
            metadata JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_feature (feature),
            INDEX idx_provider (provider),
            INDEX idx_created_at (created_at),
            INDEX idx_conversation_id (conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::getInstance()->exec($sql);
        self::$tableEnsured = true;
    }
}
