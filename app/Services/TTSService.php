<?php
/**
 * Service TTSService
 * Serviço genérico de Text-to-Speech (TTS)
 * Suporta OpenAI TTS e ElevenLabs
 */

namespace App\Services;

use App\Models\Setting;
use App\Helpers\Logger;

class TTSService
{
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_ELEVENLABS = 'elevenlabs';

    /**
     * Obter configurações de TTS
     */
    public static function getSettings(): array
    {
        $settings = ConversationSettingsService::getSettings();
        $ttsSettings = $settings['text_to_speech'] ?? [];
        
        return array_merge([
            'enabled' => false,
            'provider' => self::PROVIDER_OPENAI, // 'openai' ou 'elevenlabs'
            'auto_generate_audio' => false, // Gerar áudio automaticamente para respostas da IA
            'only_for_ai_agents' => true, // Só gerar áudio se for resposta de agente de IA
            'voice_id' => null, // ID da voz (específico por provider)
            'model' => null, // Modelo (específico por provider)
            'language' => 'pt', // Idioma
            'speed' => 1.0, // Velocidade (0.25 a 4.0)
            'stability' => 0.5, // Estabilidade (ElevenLabs: 0.0 a 1.0)
            'similarity_boost' => 0.75, // Similaridade (ElevenLabs: 0.0 a 1.0)
            'output_format' => 'mp3', // mp3, opus, ogg, pcm
            'convert_to_whatsapp_format' => true, // Converter para formato compatível com WhatsApp
        ], $ttsSettings);
    }

    /**
     * Verificar se TTS está habilitado
     */
    public static function isEnabled(): bool
    {
        $settings = self::getSettings();
        return !empty($settings['enabled']);
    }

    /**
     * Gerar áudio a partir de texto
     * 
     * @param string $text Texto para converter em áudio
     * @param array $options Opções adicionais:
     *   - 'voice_id' => string (opcional, sobrescreve configuração)
     *   - 'language' => string (opcional, sobrescreve configuração)
     *   - 'speed' => float (opcional, sobrescreve configuração)
     * @return array ['success' => bool, 'audio_path' => string|null, 'audio_url' => string|null, 'error' => string|null, 'cost' => float, 'duration' => float]
     */
    public static function generateAudio(string $text, array $options = []): array
    {
        if (empty(trim($text))) {
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => 'Texto não pode estar vazio',
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }

        $settings = self::getSettings();
        $provider = $options['provider'] ?? $settings['provider'] ?? self::PROVIDER_OPENAI;

        Logger::info("TTSService::generateAudio - Iniciando geração de áudio", [
            'provider' => $provider,
            'text_length' => strlen($text),
            'options' => $options
        ]);

        try {
            switch ($provider) {
                case self::PROVIDER_OPENAI:
                    return self::generateWithOpenAI($text, array_merge($settings, $options));
                
                case self::PROVIDER_ELEVENLABS:
                    return self::generateWithElevenLabs($text, array_merge($settings, $options));
                
                default:
                    return [
                        'success' => false,
                        'audio_path' => null,
                        'audio_url' => null,
                        'error' => "Provider não suportado: {$provider}",
                        'cost' => 0.0,
                        'duration' => 0.0
                    ];
            }
        } catch (\Exception $e) {
            Logger::error("TTSService::generateAudio - Erro: " . $e->getMessage());
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => $e->getMessage(),
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }
    }

    /**
     * Gerar áudio usando OpenAI TTS
     */
    private static function generateWithOpenAI(string $text, array $settings): array
    {
        $startTime = microtime(true);
        
        // Obter API Key
        $apiKey = Setting::get('openai_api_key');
        if (empty($apiKey)) {
            $apiKey = getenv('OPENAI_API_KEY') ?: null;
        }
        
        if (empty($apiKey)) {
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => 'API Key da OpenAI não configurada',
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }

        // Configurações
        $model = $settings['model'] ?? 'tts-1'; // tts-1 (rápido) ou tts-1-hd (alta qualidade)
        $voice = $settings['voice_id'] ?? 'alloy'; // alloy, echo, fable, onyx, nova, shimmer
        $speed = isset($settings['speed']) ? (float)$settings['speed'] : 1.0;
        $speed = max(0.25, min(4.0, $speed)); // Limitar entre 0.25 e 4.0
        $outputFormat = $settings['output_format'] ?? 'mp3'; // mp3, opus, aac, flac

        // Preparar payload
        $payload = [
            'model' => $model,
            'input' => $text,
            'voice' => $voice,
            'speed' => $speed,
            'response_format' => $outputFormat
        ];

        // Fazer requisição
        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60
        ]);

        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => "Erro cURL: {$curlError}",
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($audioData, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP {$httpCode}";
            return [
                'success' => false,
                'audio_path' => null,
                'audio_url' => null,
                'error' => "Erro da API: {$errorMessage}",
                'cost' => 0.0,
                'duration' => 0.0
            ];
        }

        // Salvar arquivo temporário
        $tempDir = __DIR__ . '/../../public/assets/media/tts/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filename = 'tts_' . uniqid() . '_' . time() . '.' . $outputFormat;
        $filePath = $tempDir . $filename;
        file_put_contents($filePath, $audioData);

        // Converter para formato WhatsApp se necessário
        $finalPath = $filePath;
        $finalUrl = '/assets/media/tts/' . $filename;
        
        if (!empty($settings['convert_to_whatsapp_format']) && $outputFormat !== 'ogg') {
            $converted = self::convertToWhatsAppFormat($filePath, $tempDir);
            if ($converted['success']) {
                $finalPath = $converted['path'];
                $finalUrl = $converted['url'];
                // Remover arquivo original
                @unlink($filePath);
            }
        }

        // Calcular custo (OpenAI TTS: $0.015 por 1000 caracteres)
        $charCount = mb_strlen($text);
        $cost = ($charCount / 1000) * 0.015;

        // Estimar duração (aproximada: 150 palavras por minuto)
        $wordCount = str_word_count($text);
        $duration = ($wordCount / 150) * 60; // segundos

        $executionTime = microtime(true) - $startTime;

        Logger::info("TTSService::generateWithOpenAI - ✅ Áudio gerado com sucesso", [
            'file' => $finalPath,
            'size' => filesize($finalPath),
            'cost' => $cost,
            'duration' => round($duration, 2),
            'execution_time' => round($executionTime, 2)
        ]);

        return [
            'success' => true,
            'audio_path' => $finalPath,
            'audio_url' => $finalUrl,
            'error' => null,
            'cost' => $cost,
            'duration' => $duration,
            'provider' => self::PROVIDER_OPENAI,
            'model' => $model,
            'voice' => $voice
        ];
    }

    /**
     * Gerar áudio usando ElevenLabs
     */
    private static function generateWithElevenLabs(string $text, array $settings): array
    {
        return \App\Services\ElevenLabsService::generateAudio($text, $settings);
    }

    /**
     * Converter áudio para formato compatível com WhatsApp (OGG/Opus)
     * Método público para ser usado por outros serviços
     */
    public static function convertToWhatsAppFormat(string $inputPath, string $outputDir): array
    {
        $outputFilename = pathinfo($inputPath, PATHINFO_FILENAME) . '.ogg';
        $outputPath = $outputDir . $outputFilename;

        // Tentar usar ffmpeg se disponível
        if (function_exists('shell_exec')) {
            $ffmpegPath = self::findFFmpeg();
            if ($ffmpegPath) {
                $command = escapeshellarg($ffmpegPath) . 
                    ' -i ' . escapeshellarg($inputPath) . 
                    ' -c:a libopus -b:a 32k -ar 16000' .
                    ' -y ' . escapeshellarg($outputPath) . 
                    ' 2>&1';
                
                $output = @shell_exec($command);
                
                if (file_exists($outputPath) && filesize($outputPath) > 0) {
                    return [
                        'success' => true,
                        'path' => $outputPath,
                        'url' => '/assets/media/tts/' . $outputFilename
                    ];
                }
            }
        }

        // Fallback: retornar arquivo original
        return [
            'success' => false,
            'path' => $inputPath,
            'url' => null,
            'error' => 'FFmpeg não disponível para conversão'
        ];
    }

    /**
     * Encontrar caminho do ffmpeg
     */
    private static function findFFmpeg(): ?string
    {
        $possiblePaths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'ffmpeg', // No PATH
            'C:\\ffmpeg\\bin\\ffmpeg.exe', // Windows comum
        ];

        foreach ($possiblePaths as $path) {
            if ($path === 'ffmpeg' || file_exists($path)) {
                $test = @shell_exec(escapeshellarg($path) . ' -version 2>&1');
                if ($test && strpos($test, 'ffmpeg') !== false) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Obter vozes disponíveis por provider
     */
    public static function getAvailableVoices(string $provider): array
    {
        switch ($provider) {
            case self::PROVIDER_OPENAI:
                return [
                    ['id' => 'alloy', 'name' => 'Alloy', 'gender' => 'neutral', 'description' => 'Voz neutra e equilibrada'],
                    ['id' => 'echo', 'name' => 'Echo', 'gender' => 'male', 'description' => 'Voz masculina clara'],
                    ['id' => 'fable', 'name' => 'Fable', 'gender' => 'neutral', 'description' => 'Voz neutra expressiva'],
                    ['id' => 'onyx', 'name' => 'Onyx', 'gender' => 'male', 'description' => 'Voz masculina profunda'],
                    ['id' => 'nova', 'name' => 'Nova', 'gender' => 'female', 'description' => 'Voz feminina suave'],
                    ['id' => 'shimmer', 'name' => 'Shimmer', 'gender' => 'female', 'description' => 'Voz feminina brilhante'],
                ];
            
            case self::PROVIDER_ELEVENLABS:
                return \App\Services\ElevenLabsService::getAvailableVoices();
            
            default:
                return [];
        }
    }
}

