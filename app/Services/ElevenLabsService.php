<?php
/**
 * Service ElevenLabsService
 * Integração com ElevenLabs API para Text-to-Speech
 */

namespace App\Services;

use App\Models\Setting;
use App\Helpers\Logger;

class ElevenLabsService
{
    const API_URL = 'https://api.elevenlabs.io/v1';
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 1; // segundos

    /**
     * Obter API Key das configurações
     */
    private static function getApiKey(): ?string
    {
        $apiKey = Setting::get('elevenlabs_api_key');
        if (empty($apiKey)) {
            $apiKey = getenv('ELEVENLABS_API_KEY') ?: null;
        }
        return $apiKey;
    }

    /**
     * Gerar áudio usando ElevenLabs
     * 
     * @param string $text Texto para converter
     * @param array $settings Configurações
     * @return array ['success' => bool, 'audio_path' => string|null, 'audio_url' => string|null, 'error' => string|null, 'cost' => float, 'duration' => float]
     */
    public static function generateAudio(string $text, array $settings): array
    {
        $startTime = microtime(true);
        
        Logger::info("🔊 ElevenLabsService::generateAudio - INÍCIO (textLen=" . strlen($text) . ")");
        Logger::info("🔊 ElevenLabsService - Settings: " . json_encode($settings));
        
        // Obter API Key
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            Logger::error("🔊 ElevenLabsService - ❌ ERRO: API Key não configurada!");
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => 'API Key do ElevenLabs não configurada',
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }
        
        Logger::info("🔊 ElevenLabsService - ✅ API Key encontrada: " . substr($apiKey, 0, 8) . "...");

        // Configurações
        $voiceId = $settings['voice_id'] ?? '21m00Tcm4TlvDq8ikWAM'; // Voz padrão (Rachel)
        $model = $settings['model'] ?? 'eleven_multilingual_v2'; // Modelo multilíngue
        $stability = isset($settings['stability']) ? (float)$settings['stability'] : 0.5;
        $similarityBoost = isset($settings['similarity_boost']) ? (float)$settings['similarity_boost'] : 0.75;
        $speed = isset($settings['speed']) ? (float)$settings['speed'] : 1.0;
        $speed = max(0.25, min(4.0, $speed)); // Limitar entre 0.25 e 4.0

        Logger::info("🔊 ElevenLabsService - voiceId={$voiceId}, model={$model}, stability={$stability}, similarity={$similarityBoost}, speed={$speed}");

        // Preparar payload
        $payload = [
            'text' => $text,
            'model_id' => $model,
            'voice_settings' => [
                'stability' => $stability,
                'similarity_boost' => $similarityBoost,
                'style' => 0.0, // Estilo (0.0 a 1.0)
                'use_speaker_boost' => true
            ]
        ];

        // Adicionar speed se suportado (alguns modelos suportam)
        if ($speed !== 1.0) {
            $payload['voice_settings']['speed'] = $speed;
        }

        // URL da API
        $url = self::API_URL . '/text-to-speech/' . urlencode($voiceId);
        
        Logger::info("🔊 ElevenLabsService - URL: {$url}");
        Logger::info("🔊 ElevenLabsService - Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        // Fazer requisição com retry
        $attempt = 0;
        $lastError = null;
        $audioData = null;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;
            
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Accept: audio/mpeg',
                        'Content-Type: application/json',
                        'xi-api-key: ' . $apiKey
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_TIMEOUT => 120 // 2 minutos (áudios podem ser longos)
                ]);

                Logger::info("ElevenLabsService::generateAudio - Enviando requisição (tentativa {$attempt}/" . self::MAX_RETRIES . ")");

                $audioData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                Logger::info("🔊 ElevenLabsService - HTTP Code: {$httpCode}, Content-Type: {$contentType}, Data size: " . strlen($audioData ?? ''));

                if ($curlError) {
                    Logger::error("🔊 ElevenLabsService - ❌ cURL Error: {$curlError}");
                    throw new \Exception("Erro cURL: {$curlError}");
                }

                if ($httpCode !== 200) {
                    $errorData = json_decode($audioData, true);
                    $errorMessage = $errorData['detail']['message'] ?? $errorData['detail']['msg'] ?? $errorData['message'] ?? "HTTP {$httpCode}";
                    Logger::error("🔊 ElevenLabsService - ❌ API Error: HTTP {$httpCode} - {$errorMessage}");
                    Logger::error("🔊 ElevenLabsService - ❌ Response: " . substr($audioData ?? '', 0, 500));
                    throw new \Exception("Erro da API: {$errorMessage}");
                }

                // Sucesso
                Logger::info("🔊 ElevenLabsService - ✅ Requisição bem-sucedida!");
                break;

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Logger::error("ElevenLabsService::generateAudio - Erro na tentativa {$attempt}: {$lastError}");

                if ($attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY * $attempt);
                }
            }
        }

        if (!$audioData || empty($audioData)) {
            Logger::error("🔊 ElevenLabsService - ❌ Nenhum dado de áudio retornado após {$attempt} tentativas");
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => $lastError ?? 'Erro desconhecido na geração de áudio',
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }
        
        // ✅ NOVO: Verificar se o retorno é JSON de erro ao invés de áudio
        $possibleJson = @json_decode($audioData, true);
        if ($possibleJson && isset($possibleJson['detail'])) {
            // É um erro JSON, não áudio!
            $errorMsg = $possibleJson['detail']['message'] ?? $possibleJson['detail']['msg'] ?? json_encode($possibleJson['detail']);
            Logger::error("🔊 ElevenLabsService - ❌ API retornou erro JSON: {$errorMsg}");
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => $errorMsg,
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }

        // Salvar arquivo
        $tempDir = __DIR__ . '/../../public/assets/media/tts/';
        if (!is_dir($tempDir)) {
            Logger::info("🔊 ElevenLabsService - Criando diretório: {$tempDir}");
            mkdir($tempDir, 0755, true);
        }

        $filename = 'tts_elevenlabs_' . uniqid() . '_' . time() . '.mp3';
        $filePath = $tempDir . $filename;
        
        Logger::info("🔊 ElevenLabsService - Salvando arquivo: {$filePath}");
        file_put_contents($filePath, $audioData);
        
        if (!file_exists($filePath)) {
            Logger::error("🔊 ElevenLabsService - ❌ ERRO: Arquivo não foi salvo!");
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => 'Falha ao salvar arquivo de áudio',
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }
        
        Logger::info("🔊 ElevenLabsService - ✅ Arquivo salvo: " . filesize($filePath) . " bytes");

        // Converter para formato WhatsApp se necessário
        $finalPath = $filePath;
        $finalUrl = '/assets/media/tts/' . $filename;
        
        if (!empty($settings['convert_to_whatsapp_format'])) {
            Logger::info("🔊 ElevenLabsService - Convertendo para formato WhatsApp...");
            $converted = \App\Services\TTSService::convertToWhatsAppFormat($filePath, $tempDir);
            
            Logger::info("🔊 ElevenLabsService - Conversão: success=" . ($converted['success'] ? 'YES' : 'NO'));
            
            if ($converted['success']) {
                $finalPath = $converted['path'];
                $finalUrl = $converted['url'];
                @unlink($filePath);
                Logger::info("🔊 ElevenLabsService - ✅ Convertido para: {$finalPath}");
            } else {
                Logger::error("🔊 ElevenLabsService - ⚠️ Falha na conversão, usando MP3 original");
            }
        }

        // Calcular custo (ElevenLabs: ~$0.18 por 1000 caracteres no plano básico)
        $charCount = mb_strlen($text);
        $cost = ($charCount / 1000) * 0.18;

        // Estimar duração (aproximada: 150 palavras por minuto)
        $wordCount = str_word_count($text);
        $duration = ($wordCount / 150) * 60; // segundos

        $executionTime = microtime(true) - $startTime;

        Logger::info("ElevenLabsService::generateAudio - ✅ Áudio gerado: {$finalPath} (" . filesize($finalPath) . " bytes, cost=$" . number_format($cost, 4) . ", time=" . round($executionTime, 2) . "s)");

        return [
            'success' => true,
            'audio_path' => $finalPath,
            'audio_url' => $finalUrl,
            'error' => null,
            'cost' => $cost,
            'duration' => $duration,
            'provider' => 'elevenlabs',
            'model' => $model,
            'voice' => $voiceId
        ];
    }

    /**
     * Obter vozes disponíveis do ElevenLabs
     */
    public static function getAvailableVoices(): array
    {
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return [];
        }

        try {
            $ch = curl_init(self::API_URL . '/voices');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'xi-api-key: ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $voices = [];
                
                if (isset($data['voices']) && is_array($data['voices'])) {
                    foreach ($data['voices'] as $voice) {
                        $voices[] = [
                            'id' => $voice['voice_id'] ?? '',
                            'name' => $voice['name'] ?? '',
                            'gender' => $voice['labels']['gender'] ?? 'unknown',
                            'description' => $voice['description'] ?? '',
                            'category' => $voice['category'] ?? '',
                            'preview_url' => $voice['preview_url'] ?? null
                        ];
                    }
                }
                
                return $voices;
            }
        } catch (\Exception $e) {
            Logger::error("ElevenLabsService::getAvailableVoices - Erro: " . $e->getMessage());
        }

        // Retornar vozes padrão conhecidas se API falhar
        return [
            ['id' => '21m00Tcm4TlvDq8ikWAM', 'name' => 'Rachel', 'gender' => 'female', 'description' => 'Voz feminina profissional'],
            ['id' => 'AZnzlk1XvdvUeBnXmlld', 'name' => 'Domi', 'gender' => 'female', 'description' => 'Voz feminina expressiva'],
            ['id' => 'EXAVITQu4vr4xnSDxMaL', 'name' => 'Bella', 'gender' => 'female', 'description' => 'Voz feminina suave'],
            ['id' => 'ErXwobaYiN019PkySvjV', 'name' => 'Antoni', 'gender' => 'male', 'description' => 'Voz masculina clara'],
            ['id' => 'MF3mGyEYCl7XYWbV9V6O', 'name' => 'Elli', 'gender' => 'female', 'description' => 'Voz feminina jovem'],
            ['id' => 'TxGEqnHWrfWFTfGW9XjX', 'name' => 'Josh', 'gender' => 'male', 'description' => 'Voz masculina profunda'],
        ];
    }

    /**
     * Verificar se API Key é válida
     */
    public static function validateApiKey(): array
    {
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return ['valid' => false, 'error' => 'API Key não configurada'];
        }

        try {
            $ch = curl_init(self::API_URL . '/user');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'xi-api-key: ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return [
                    'valid' => true,
                    'user' => $data['first_name'] ?? 'Usuário',
                    'subscription' => $data['subscription'] ?? []
                ];
            } else {
                return ['valid' => false, 'error' => 'API Key inválida'];
            }
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}

