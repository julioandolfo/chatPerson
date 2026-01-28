<?php
/**
 * Service AgentMemoryService
 * Sistema de memória automática para agentes de IA
 */

namespace App\Services;

use App\Models\AIAgentMemory;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenAIService;
use App\Helpers\Logger;
use App\Models\Setting;

class AgentMemoryService
{
    /**
     * Extrair e salvar informações importantes de uma conversa
     */
    public static function extractAndSave(int $agentId, int $conversationId): void
    {
        try {
            if (!\App\Helpers\PostgreSQL::isAvailable()) {
                return;
            }

            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return;
            }

            // Buscar mensagens da conversa
            $messages = Message::where('conversation_id', '=', $conversationId);
            
            if (empty($messages)) {
                return;
            }

            // Extrair informações usando IA
            $extractedInfo = self::extractInfoWithAI($agentId, $messages);

            // Salvar memórias
            foreach ($extractedInfo as $info) {
                try {
                    // ✅ CORREÇÃO: Converter value para string se for array
                    $value = $info['value'] ?? '';
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    $value = (string) $value;
                    
                    AIAgentMemory::saveOrUpdate(
                        $agentId,
                        $conversationId,
                        $info['type'] ?? 'extracted_info',
                        $info['key'] ?? 'unknown',
                        $value,
                        $info['importance'] ?? 0.5,
                        $info['expires_at'] ?? null
                    );
                } catch (\Exception $e) {
                    Logger::warning("AgentMemoryService::extractAndSave - Erro ao salvar memória: " . $e->getMessage());
                }
            }

            Logger::info("AgentMemoryService::extractAndSave - Extraídas " . count($extractedInfo) . " informações da conversa {$conversationId}");

        } catch (\Exception $e) {
            Logger::error("AgentMemoryService::extractAndSave - Erro: " . $e->getMessage());
        }
    }

    /**
     * Extrair informações usando IA
     */
    private static function extractInfoWithAI(int $agentId, array $messages): array
    {
        try {
            // Construir contexto das mensagens
            $context = self::buildMessagesContext($messages);

            // Prompt para extração
            $prompt = "Analise a seguinte conversa e extraia informações importantes que devem ser lembradas para futuras interações. Retorne um JSON com o seguinte formato:

{
  \"memories\": [
    {
      \"type\": \"fact|preference|context|extracted_info\",
      \"key\": \"chave_da_informacao\",
      \"value\": \"valor_da_informacao\",
      \"importance\": 0.0-1.0
    }
  ]
}

Tipos:
- fact: Fatos sobre o cliente (ex: nome, empresa, cargo)
- preference: Preferências do cliente (ex: horário preferido, método de contato)
- context: Contexto da conversa (ex: problema atual, status)
- extracted_info: Informações extraídas (ex: produtos interessados, orçamento)

Conversa:
{$context}

Retorne APENAS o JSON, sem texto adicional.";

            // Chamar OpenAI para extrair informações
            $apiKey = \App\Models\Setting::get('openai_api_key');
            if (empty($apiKey)) {
                return [];
            }

            $response = self::callOpenAI($apiKey, $prompt);
            
            if (empty($response)) {
                return [];
            }

            // Parsear JSON
            $data = json_decode($response, true);
            
            if (!isset($data['memories']) || !is_array($data['memories'])) {
                return [];
            }

            return $data['memories'];

        } catch (\Exception $e) {
            Logger::error("AgentMemoryService::extractInfoWithAI - Erro: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Construir contexto das mensagens
     */
    private static function buildMessagesContext(array $messages): string
    {
        $context = [];
        
        foreach ($messages as $msg) {
            $role = $msg['sender_type'] === 'contact' ? 'Cliente' : 'Agente';
            $content = $msg['content'] ?? '';
            $context[] = "{$role}: {$content}";
        }

        return implode("\n", $context);
    }

    /**
     * Chamar OpenAI API
     */
    private static function callOpenAI(string $apiKey, string $prompt): string
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um assistente que extrai informações importantes de conversas. Retorne APENAS JSON válido.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000
            ]),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("OpenAI API retornou código {$httpCode}");
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Salvar memória manualmente
     */
    public static function saveMemory(int $agentId, int $conversationId, string $type, string $key, string $value, float $importance = 0.5, ?string $expiresAt = null): int
    {
        return AIAgentMemory::saveOrUpdate($agentId, $conversationId, $type, $key, $value, $importance, $expiresAt);
    }

    /**
     * Limpar memórias expiradas
     */
    public static function cleanExpired(): int
    {
        return AIAgentMemory::cleanExpired();
    }
}

