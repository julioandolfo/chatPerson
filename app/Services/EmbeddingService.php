<?php
/**
 * Service EmbeddingService
 * Gera embeddings para RAG usando OpenAI
 */

namespace App\Services;

use App\Models\Setting;

class EmbeddingService
{
    private const API_URL = 'https://api.openai.com/v1/embeddings';
    private const MODEL = 'text-embedding-3-small'; // Mais barato e rápido
    private const DIMENSION = 1536; // Dimensão padrão
    
    /**
     * Gerar embedding para um texto
     * 
     * @param string $text Texto para gerar embedding
     * @return array|null Array com 1536 dimensões ou null se falhar
     */
    public static function generate(string $text): ?array
    {
        try {
            // Obter API key
            $apiKey = Setting::get('openai_api_key');
            if (empty($apiKey)) {
                error_log("EmbeddingService: OpenAI API key não configurada");
                return null;
            }
            
            // Limpar e truncar texto (máximo 8191 tokens ~= 32k caracteres)
            $text = trim($text);
            if (strlen($text) > 32000) {
                $text = substr($text, 0, 32000);
            }
            
            if (empty($text)) {
                error_log("EmbeddingService: Texto vazio");
                return null;
            }
            
            // Fazer requisição à API
            $payload = [
                'model' => self::MODEL,
                'input' => $text,
                'encoding_format' => 'float'
            ];
            
            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                error_log("EmbeddingService: Erro cURL: {$error}");
                return null;
            }
            
            if ($httpCode !== 200) {
                error_log("EmbeddingService: HTTP {$httpCode}: {$response}");
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['data'][0]['embedding'])) {
                error_log("EmbeddingService: Resposta inválida da API");
                return null;
            }
            
            $embedding = $data['data'][0]['embedding'];
            
            // Validar dimensão
            if (count($embedding) !== self::DIMENSION) {
                error_log("EmbeddingService: Dimensão incorreta: " . count($embedding));
                return null;
            }
            
            return $embedding;
            
        } catch (\Exception $e) {
            error_log("EmbeddingService: Exceção: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Gerar embeddings para múltiplos textos (batch)
     * 
     * @param array $texts Array de textos
     * @return array Array de embeddings (mesmo índice dos textos)
     */
    public static function generateBatch(array $texts): array
    {
        try {
            // Obter API key
            $apiKey = Setting::get('openai_api_key');
            if (empty($apiKey)) {
                error_log("EmbeddingService: OpenAI API key não configurada");
                return [];
            }
            
            // Limpar textos
            $cleanTexts = array_map(function($text) {
                $text = trim($text);
                if (strlen($text) > 32000) {
                    $text = substr($text, 0, 32000);
                }
                return $text;
            }, $texts);
            
            // Remover vazios
            $cleanTexts = array_filter($cleanTexts);
            
            if (empty($cleanTexts)) {
                return [];
            }
            
            // Fazer requisição à API
            $payload = [
                'model' => self::MODEL,
                'input' => array_values($cleanTexts),
                'encoding_format' => 'float'
            ];
            
            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 60 // Mais tempo para batch
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error || $httpCode !== 200) {
                error_log("EmbeddingService: Erro batch: HTTP {$httpCode}, {$error}");
                return [];
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['data']) || !is_array($data['data'])) {
                error_log("EmbeddingService: Resposta batch inválida");
                return [];
            }
            
            // Extrair embeddings
            $embeddings = [];
            foreach ($data['data'] as $item) {
                if (isset($item['embedding']) && count($item['embedding']) === self::DIMENSION) {
                    $embeddings[] = $item['embedding'];
                }
            }
            
            return $embeddings;
            
        } catch (\Exception $e) {
            error_log("EmbeddingService: Exceção batch: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calcular similaridade de cosseno entre dois embeddings
     * 
     * @param array $embedding1 Primeiro embedding
     * @param array $embedding2 Segundo embedding
     * @return float Similaridade (0 a 1)
     */
    public static function cosineSimilarity(array $embedding1, array $embedding2): float
    {
        if (count($embedding1) !== count($embedding2)) {
            return 0.0;
        }
        
        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;
        
        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $magnitude1 += $embedding1[$i] * $embedding1[$i];
            $magnitude2 += $embedding2[$i] * $embedding2[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }
        
        return $dotProduct / ($magnitude1 * $magnitude2);
    }
    
    /**
     * Obter dimensão do embedding
     */
    public static function getDimension(): int
    {
        return self::DIMENSION;
    }
    
    /**
     * Obter modelo usado
     */
    public static function getModel(): string
    {
        return self::MODEL;
    }
}
