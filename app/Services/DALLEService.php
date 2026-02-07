<?php
/**
 * Service DALLEService
 * Integração com DALL-E 3 para geração de mockups
 * Usa GPT-4o Vision para análise de imagens e otimização de prompts
 */

namespace App\Services;

use App\Models\Setting;

class DALLEService
{
    const GPT4_VISION_URL = 'https://api.openai.com/v1/chat/completions';
    const DALLE3_URL = 'https://api.openai.com/v1/images/generations';
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 2; // segundos

    /**
     * Obter API Key das configurações
     */
    private static function getApiKey(): ?string
    {
        // Restaurar error_reporting temporariamente para que a query funcione
        $oldErrorReporting = error_reporting();
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
        
        try {
            $apiKey = Setting::get('openai_api_key');
            
            if (empty($apiKey)) {
                // Fallback: buscar diretamente no banco
                $setting = \App\Helpers\Database::fetch(
                    "SELECT `value` FROM settings WHERE `key` = 'openai_api_key' LIMIT 1"
                );
                $apiKey = $setting['value'] ?? null;
            }
            
            if (empty($apiKey)) {
                // Fallback: variáveis de ambiente
                $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: null;
            }
        } catch (\Exception $e) {
            error_log('[DALLEService] Erro ao buscar API Key: ' . $e->getMessage());
            $apiKey = null;
        }
        
        error_reporting($oldErrorReporting);
        return $apiKey;
    }

    /**
     * Gerar mockup com IA (GPT-4o Vision + DALL-E 3)
     * 
     * @param string $productImagePath Caminho da imagem do produto
     * @param string $logoImagePath Caminho da logo
     * @param array $logoConfig Configurações da logo (position, size, style, etc)
     * @param string|null $userPrompt Prompt opcional do usuário
     * @param string $size Tamanho da imagem (1024x1024, 1024x1792, 1792x1024)
     * @param string $quality Qualidade (standard ou hd)
     * @return array ['success' => bool, 'image_url' => string, 'optimized_prompt' => string, 'gpt4_analysis' => string, 'error' => string, 'costs' => array]
     */
    public static function generateMockup(
        string $productImagePath,
        string $logoImagePath,
        array $logoConfig,
        ?string $userPrompt = null,
        string $size = '1024x1024',
        string $quality = 'standard'
    ): array {
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return [
                'success' => false,
                'error' => 'API Key da OpenAI não configurada'
            ];
        }

        $startTime = microtime(true);
        $costs = ['gpt4' => 0, 'dalle' => 0, 'total' => 0];

        try {
            // ETAPA 1: Analisar imagens com GPT-4o Vision e gerar prompt otimizado
            $analysisResult = self::analyzeImagesWithGPT4Vision(
                $productImagePath,
                $logoImagePath,
                $logoConfig,
                $userPrompt,
                $apiKey
            );

            if (!$analysisResult['success']) {
                return $analysisResult;
            }

            $optimizedPrompt = $analysisResult['optimized_prompt'];
            $gpt4Analysis = $analysisResult['analysis'];
            $costs['gpt4'] = $analysisResult['cost'];

            // ETAPA 2: Gerar imagem com DALL-E 3
            $dalleResult = self::generateImageWithDALLE3(
                $optimizedPrompt,
                $size,
                $quality,
                $apiKey
            );

            if (!$dalleResult['success']) {
                return $dalleResult;
            }

            $costs['dalle'] = $dalleResult['cost'];
            $costs['total'] = $costs['gpt4'] + $costs['dalle'];

            $processingTime = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'image_url' => $dalleResult['image_url'],
                'revised_prompt' => $dalleResult['revised_prompt'] ?? null,
                'optimized_prompt' => $optimizedPrompt,
                'gpt4_analysis' => $gpt4Analysis,
                'processing_time' => $processingTime,
                'costs' => $costs
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'costs' => $costs
            ];
        }
    }

    /**
     * Analisar imagens com GPT-4o Vision e gerar prompt otimizado
     */
    private static function analyzeImagesWithGPT4Vision(
        string $productImagePath,
        string $logoImagePath,
        array $logoConfig,
        ?string $userPrompt,
        string $apiKey
    ): array {
        try {
            // Converter imagens para base64
            $productBase64 = self::imageToBase64($productImagePath);
            $logoBase64 = self::imageToBase64($logoImagePath);

            if (!$productBase64 || !$logoBase64) {
                return [
                    'success' => false,
                    'error' => 'Falha ao carregar imagens para análise'
                ];
            }

            // Construir mensagem para GPT-4o Vision
            $systemPrompt = self::buildVisionSystemPrompt();
            $userMessage = self::buildVisionUserMessage($logoConfig, $userPrompt);

            $payload = [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $userMessage
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:image/jpeg;base64,{$productBase64}",
                                    'detail' => 'high'
                                ]
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:image/jpeg;base64,{$logoBase64}",
                                    'detail' => 'high'
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.7
            ];

            $response = self::makeRequest(self::GPT4_VISION_URL, $payload, $apiKey);

            if (!$response['success']) {
                return $response;
            }

            $content = $response['data']['choices'][0]['message']['content'] ?? '';
            
            // Extrair o prompt otimizado do conteúdo
            // GPT-4o retorna análise + prompt otimizado
            $optimizedPrompt = $content;
            
            // Calcular custo aproximado (GPT-4o: $0.0025/1K input, $0.01/1K output)
            $inputTokens = $response['data']['usage']['prompt_tokens'] ?? 0;
            $outputTokens = $response['data']['usage']['completion_tokens'] ?? 0;
            $cost = ($inputTokens / 1000 * 0.0025) + ($outputTokens / 1000 * 0.01);

            return [
                'success' => true,
                'optimized_prompt' => $optimizedPrompt,
                'analysis' => $content,
                'cost' => round($cost, 6)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro na análise com GPT-4o Vision: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Gerar imagem com DALL-E 3
     */
    private static function generateImageWithDALLE3(
        string $prompt,
        string $size,
        string $quality,
        string $apiKey
    ): array {
        try {
            $payload = [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1, // DALL-E 3 só permite 1 imagem por vez
                'size' => $size,
                'quality' => $quality, // 'standard' ou 'hd'
                'response_format' => 'url' // ou 'b64_json'
            ];

            $response = self::makeRequest(self::DALLE3_URL, $payload, $apiKey);

            if (!$response['success']) {
                return $response;
            }

            $imageUrl = $response['data']['data'][0]['url'] ?? null;
            $revisedPrompt = $response['data']['data'][0]['revised_prompt'] ?? null;

            if (!$imageUrl) {
                return [
                    'success' => false,
                    'error' => 'URL da imagem não retornada pela API'
                ];
            }

            // Calcular custo baseado em tamanho e qualidade
            $cost = self::calculateDALLE3Cost($size, $quality);

            return [
                'success' => true,
                'image_url' => $imageUrl,
                'revised_prompt' => $revisedPrompt,
                'cost' => $cost
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro na geração com DALL-E 3: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fazer requisição HTTP com retry
     */
    private static function makeRequest(string $url, array $payload, string $apiKey, int $retry = 0): array
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 120, // DALL-E pode demorar
            CURLOPT_CONNECTTIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'Erro cURL: ' . $error
            ];
        }
        
        $data = json_decode($response, true);
        
        // Rate limit (429) - retry
        if ($httpCode === 429 && $retry < self::MAX_RETRIES) {
            sleep(self::RETRY_DELAY * ($retry + 1));
            return self::makeRequest($url, $payload, $apiKey, $retry + 1);
        }
        
        // Erro da API
        if ($httpCode !== 200) {
            $errorMessage = $data['error']['message'] ?? 'Erro desconhecido da API OpenAI';
            return [
                'success' => false,
                'error' => "Erro HTTP {$httpCode}: {$errorMessage}",
                'http_code' => $httpCode
            ];
        }
        
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Converter imagem para base64
     */
    private static function imageToBase64(string $imagePath): ?string
    {
        // Se for URL absoluta com http/https, fazer download
        if (preg_match('/^https?:\/\//', $imagePath)) {
            $imageData = @file_get_contents($imagePath);
            if ($imageData === false) {
                return null;
            }
            return base64_encode($imageData);
        }

        // Caminho local
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imagePath, '/');
        
        if (!file_exists($fullPath)) {
            return null;
        }
        
        $imageData = @file_get_contents($fullPath);
        if ($imageData === false) {
            return null;
        }
        
        return base64_encode($imageData);
    }

    /**
     * Construir prompt do sistema para GPT-4o Vision
     */
    private static function buildVisionSystemPrompt(): string
    {
        return "You are an expert in product photography and mockup creation. Your task is to analyze product and logo images, then create the PERFECT DALL-E 3 prompt for generating a professional, commercial-grade mockup.

CRITICAL RULES:
1. Analyze both images in detail (product type, colors, materials, logo style)
2. Create a DALL-E 3 prompt that results in photorealistic product mockup
3. Be EXTREMELY specific about: lighting, angle, background, logo placement, composition
4. Use professional photography terminology
5. Output ONLY the optimized DALL-E 3 prompt (no explanations, no extra text)
6. The prompt MUST be in English for DALL-E 3
7. Include exact logo positioning, size, and style as specified by user
8. Ensure the mockup looks like professional e-commerce photography";
    }

    /**
     * Construir mensagem do usuário para GPT-4o Vision
     */
    private static function buildVisionUserMessage(array $logoConfig, ?string $userPrompt): string
    {
        $position = $logoConfig['position'] ?? 'center';
        $size = $logoConfig['size'] ?? 20;
        $style = $logoConfig['style'] ?? 'original';
        $orientation = $logoConfig['orientation'] ?? 'auto';
        $opacity = $logoConfig['opacity'] ?? 100;
        $effects = $logoConfig['effects'] ?? [];

        $positionMap = [
            'center' => 'centered on the product',
            'top-center' => 'at the top center',
            'bottom-center' => 'at the bottom center',
            'top-left' => 'at the top left corner',
            'top-right' => 'at the top right corner',
            'bottom-left' => 'at the bottom left corner',
            'bottom-right' => 'at the bottom right corner',
            'center-left' => 'centered on the left side',
            'center-right' => 'centered on the right side'
        ];

        $styleMap = [
            'original' => 'maintaining original colors',
            'white' => 'converted to pure white',
            'black' => 'converted to pure black',
            'grayscale' => 'in grayscale'
        ];

        $message = "IMAGE 1 is the PRODUCT. IMAGE 2 is the LOGO.\n\n";
        $message .= "Create a DALL-E 3 prompt for a professional product mockup with these specifications:\n\n";
        $message .= "LOGO PLACEMENT:\n";
        $message .= "- Position: " . ($positionMap[$position] ?? $position) . "\n";
        $message .= "- Size: {$size}% of the product's visible surface\n";
        $message .= "- Style: " . ($styleMap[$style] ?? $style) . "\n";
        $message .= "- Orientation: {$orientation}\n";
        $message .= "- Opacity: {$opacity}%\n";
        
        if (!empty($effects)) {
            $message .= "- Effects: ";
            $effectsList = [];
            if (!empty($effects['shadow'])) $effectsList[] = "subtle shadow";
            if (!empty($effects['border'])) $effectsList[] = "thin border";
            if (!empty($effects['reflection'])) $effectsList[] = "reflection effect";
            $message .= implode(", ", $effectsList) . "\n";
        }

        $message .= "\nREQUIREMENTS:\n";
        $message .= "- Professional product photography style\n";
        $message .= "- Clean white or light gray background\n";
        $message .= "- Soft, even studio lighting\n";
        $message .= "- 3/4 front angle view\n";
        $message .= "- High-quality, commercial-grade result\n";
        $message .= "- Logo perfectly integrated and clearly visible\n";

        if ($userPrompt) {
            $message .= "\nADDITIONAL USER INSTRUCTIONS:\n{$userPrompt}\n";
        }

        $message .= "\nGenerate the optimized DALL-E 3 prompt NOW (prompt only, no extra text):";

        return $message;
    }

    /**
     * Calcular custo do DALL-E 3
     */
    private static function calculateDALLE3Cost(string $size, string $quality): float
    {
        // Tabela de preços DALL-E 3 (2024)
        $prices = [
            '1024x1024' => [
                'standard' => 0.040,
                'hd' => 0.080
            ],
            '1024x1792' => [
                'standard' => 0.080,
                'hd' => 0.120
            ],
            '1792x1024' => [
                'standard' => 0.080,
                'hd' => 0.120
            ]
        ];

        return $prices[$size][$quality] ?? 0.040;
    }

    /**
     * Download de imagem da URL para salvar localmente
     */
    public static function downloadImage(string $url, string $savePath): bool
    {
        try {
            $imageData = @file_get_contents($url);
            if ($imageData === false) {
                return false;
            }

            // Criar diretório se não existir
            $dir = dirname($savePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            return @file_put_contents($savePath, $imageData) !== false;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gerar thumbnail de imagem
     */
    public static function generateThumbnail(string $sourcePath, string $thumbPath, int $maxWidth = 300): bool
    {
        try {
            $info = @getimagesize($sourcePath);
            if (!$info) {
                return false;
            }

            list($width, $height, $type) = $info;

            // Calcular novo tamanho mantendo proporção
            if ($width > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = intval($height * ($maxWidth / $width));
            } else {
                return copy($sourcePath, $thumbPath);
            }

            // Criar imagem de acordo com o tipo
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = @imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $source = @imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $source = @imagecreatefromgif($sourcePath);
                    break;
                case IMAGETYPE_WEBP:
                    $source = @imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return false;
            }

            if (!$source) {
                return false;
            }

            // Criar thumbnail
            $thumb = @imagecreatetruecolor($newWidth, $newHeight);
            
            // Preservar transparência para PNG
            if ($type === IMAGETYPE_PNG) {
                @imagealphablending($thumb, false);
                @imagesavealpha($thumb, true);
            }

            @imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Criar diretório se não existir
            $dir = dirname($thumbPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            // Salvar thumbnail
            $result = @imagejpeg($thumb, $thumbPath, 85);

            @imagedestroy($source);
            @imagedestroy($thumb);

            return $result;

        } catch (\Exception $e) {
            return false;
        }
    }
}
