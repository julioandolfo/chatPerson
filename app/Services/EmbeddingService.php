<?php
/**
 * Service EmbeddingService
 * Geração de embeddings usando OpenAI Embeddings API
 */

namespace App\Services;

use App\Models\Setting;
use App\Helpers\Logger;

class EmbeddingService
{
    const API_URL = 'https://api.openai.com/v1/embeddings';
    const DEFAULT_MODEL = 'text-embedding-3-small'; // 1536 dimensões, mais barato
    const LARGE_MODEL = 'text-embedding-3-large'; // 3072 dimensões, mais caro
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 1; // segundos
    
    // Cache simples em memória (para evitar múltiplas chamadas na mesma requisição)
    private static array $cache = [];

    /**
     * Obter API Key das configurações
     */
    private static function getApiKey(): ?string
    {
        $apiKey = Setting::get('openai_api_key');
        if (empty($apiKey)) {
            $apiKey = getenv('OPENAI_API_KEY') ?: null;
        }
        return $apiKey;
    }

    /**
     * Gerar embedding para um texto
     * 
     * @param string $text Texto para gerar embedding
     * @param string $model Modelo a usar (text-embedding-3-small ou text-embedding-3-large)
     * @return array Array de floats (dimensões do embedding)
     * @throws \Exception
     */
    public static function generate(string $text, string $model = self::DEFAULT_MODEL): array
    {
        // Verificar cache
        $cacheKey = md5($text . $model);
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            throw new \Exception('API Key da OpenAI não configurada');
        }

        // Limpar texto (remover espaços extras, quebras de linha excessivas)
        $text = self::cleanText($text);
        
        if (empty($text)) {
            throw new \Exception('Texto vazio não pode gerar embedding');
        }

        // Preparar payload
        $payload = [
            'model' => $model,
            'input' => $text
        ];

        $attempt = 0;
        $lastError = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $ch = curl_init(self::API_URL);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new \Exception("Erro cURL: {$error}");
                }

                if ($httpCode !== 200) {
                    $errorData = json_decode($response, true);
                    $errorMessage = $errorData['error']['message'] ?? "HTTP {$httpCode}";
                    throw new \Exception("Erro da API: {$errorMessage}");
                }

                $data = json_decode($response, true);
                
                if (!isset($data['data'][0]['embedding'])) {
                    throw new \Exception('Resposta da API inválida: embedding não encontrado');
                }

                $embedding = $data['data'][0]['embedding'];
                
                // Validar dimensões
                $expectedDimensions = $model === self::LARGE_MODEL ? 3072 : 1536;
                if (count($embedding) !== $expectedDimensions) {
                    Logger::warning("EmbeddingService::generate - Dimensões incorretas: esperado {$expectedDimensions}, recebido " . count($embedding));
                }

                // Cachear resultado
                self::$cache[$cacheKey] = $embedding;
                
                return $embedding;

            } catch (\Exception $e) {
                $lastError = $e;
                $attempt++;
                
                if ($attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY * $attempt); // Backoff exponencial
                }
            }
        }

        throw new \Exception("Falha ao gerar embedding após " . self::MAX_RETRIES . " tentativas: " . $lastError->getMessage());
    }

    /**
     * Gerar embeddings em batch (múltiplos textos de uma vez)
     * 
     * @param array $texts Array de textos
     * @param string $model Modelo a usar
     * @return array Array de arrays (cada um é um embedding)
     * @throws \Exception
     */
    public static function generateBatch(array $texts, string $model = self::DEFAULT_MODEL): array
    {
        if (empty($texts)) {
            return [];
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            throw new \Exception('API Key da OpenAI não configurada');
        }

        // Limpar textos
        $cleanedTexts = array_map([self::class, 'cleanText'], $texts);
        $cleanedTexts = array_filter($cleanedTexts, fn($t) => !empty($t));
        
        if (empty($cleanedTexts)) {
            throw new \Exception('Nenhum texto válido para gerar embeddings');
        }

        // Preparar payload
        $payload = [
            'model' => $model,
            'input' => array_values($cleanedTexts) // Reindexar array
        ];

        $attempt = 0;
        $lastError = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $ch = curl_init(self::API_URL);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_TIMEOUT => 60, // Timeout maior para batch
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new \Exception("Erro cURL: {$error}");
                }

                if ($httpCode !== 200) {
                    $errorData = json_decode($response, true);
                    $errorMessage = $errorData['error']['message'] ?? "HTTP {$httpCode}";
                    throw new \Exception("Erro da API: {$errorMessage}");
                }

                $data = json_decode($response, true);
                
                if (!isset($data['data']) || !is_array($data['data'])) {
                    throw new \Exception('Resposta da API inválida: data não encontrado');
                }

                $embeddings = [];
                foreach ($data['data'] as $item) {
                    if (isset($item['embedding'])) {
                        $embeddings[] = $item['embedding'];
                    }
                }

                return $embeddings;

            } catch (\Exception $e) {
                $lastError = $e;
                $attempt++;
                
                if ($attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY * $attempt);
                }
            }
        }

        throw new \Exception("Falha ao gerar embeddings em batch após " . self::MAX_RETRIES . " tentativas: " . $lastError->getMessage());
    }

    /**
     * Limpar texto antes de gerar embedding
     */
    private static function cleanText(string $text): string
    {
        // Remover espaços extras
        $text = preg_replace('/\s+/', ' ', $text);
        // Remover quebras de linha excessivas
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        // Trim
        $text = trim($text);
        return $text;
    }

    /**
     * Obter embedding do cache (se disponível)
     */
    public static function getCached(string $text, string $model = self::DEFAULT_MODEL): ?array
    {
        $cacheKey = md5($text . $model);
        return self::$cache[$cacheKey] ?? null;
    }

    /**
     * Limpar cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Estimar custo de geração de embeddings
     * 
     * @param int $tokens Número de tokens
     * @param string $model Modelo usado
     * @return float Custo em USD
     */
    public static function estimateCost(int $tokens, string $model = self::DEFAULT_MODEL): float
    {
        // Preços por 1M tokens (janeiro 2025)
        $prices = [
            self::DEFAULT_MODEL => 0.02, // $0.02 por 1M tokens
            self::LARGE_MODEL => 0.13,   // $0.13 por 1M tokens
        ];

        $pricePerMillion = $prices[$model] ?? $prices[self::DEFAULT_MODEL];
        return ($tokens / 1_000_000) * $pricePerMillion;
    }
}

